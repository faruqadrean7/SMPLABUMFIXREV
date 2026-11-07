<?php

namespace App\Services\Cbt;

use App\Jobs\ParseQuestionImportJob;
use App\Models\File;
use App\Models\QuestionBank;
use App\Models\QuestionImportJob;
use App\Models\User;
use App\Services\QuestionBankService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * BE-CBT-13: Layanan orkestrasi impor butir soal dari DOCX.
 */
class QuestionImportService
{
    public function __construct(
        protected QuestionBankService $questionBankService,
        protected DocxQuestionParser $parser
    ) {}

    /**
     * Memulai proses impor berkas DOCX (unggah, validasi magic bytes, simpan, dispatch parse job).
     */
    public function startImport(UploadedFile $file, string $bankId, User $user, Request $request): QuestionImportJob
    {
        // 1. Validasi Keamanan: Ekstensi, Ukuran, MIME, & Magic Bytes
        $this->validateDocxFile($file);

        // 2. Verifikasi Hak Akses & Cakupan Data (AC6)
        $bank = QuestionBank::findOrFail($bankId);
        $this->verifyBankAccess($bank, $user);

        // 3. Simpan berkas ke storage privat
        $objectKey = $file->store('cbt/imports', 'local');

        $fileRecord = File::create([
            'school_id' => $user->school_id,
            'owner_id' => $user->id,
            'owner_type' => 'question_import',
            'bucket' => 'local',
            'object_key' => $objectKey,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size_bytes' => $file->getSize(),
            'visibility' => 'privat',
            'checksum' => hash_file('sha256', $file->getRealPath()),
        ]);

        // 4. Buat entri antrean job
        $importJob = QuestionImportJob::create([
            'bank_id' => $bank->id,
            'uploaded_by' => $user->id,
            'source_file_id' => $fileRecord->id,
            'status' => 'queued',
        ]);

        // 5. Dispatch job parsing ke queue
        if (config('queue.default') === 'sync') {
            ParseQuestionImportJob::dispatchSync($importJob->id);
            $importJob->refresh();
        } else {
            ParseQuestionImportJob::dispatch($importJob->id);
        }

        return $importJob;
    }

    /**
     * Mengambil data status dan pratinjau draf soal hasil parsing (AC3).
     */
    public function getImportJob(string $id, User $user): QuestionImportJob
    {
        $job = QuestionImportJob::with(['bank', 'sourceFile'])->findOrFail($id);
        $this->verifyBankAccess($job->bank, $user);

        return $job;
    }

    /**
     * Mengoreksi draf butir soal sebelum dikomit (AC4).
     */
    public function updateDraft(string $id, array $questions, User $user): QuestionImportJob
    {
        $job = QuestionImportJob::with('bank')->findOrFail($id);
        $this->verifyBankAccess($job->bank, $user);

        if ($job->status === 'committed') {
            throw new HttpException(422, 'Draf impor yang sudah dikommit tidak dapat diedit kembali.');
        }

        // Validasi ulang aturan bisnis pada draf terkoreksi (AC7)
        $revalidatedErrors = [];
        foreach ($questions as $idx => &$q) {
            $num = $idx + 1;
            $type = $q['question_type'] ?? 'pg';
            $options = $q['options'] ?? [];

            if ($type === 'pg') {
                $correctCount = count(array_filter($options, fn ($o) => ! empty($o['is_correct'])));
                if ($correctCount !== 1) {
                    $revalidatedErrors[] = [
                        'item_number' => $num,
                        'error' => "Butir soal #{$num} bertipe Pilihan Ganda (PG) wajib memiliki tepat 1 kunci jawaban yang benar (ditemukan: {$correctCount}).",
                    ];
                }
            } elseif ($type === 'bs') {
                $correctCount = count(array_filter($options, fn ($o) => ! empty($o['is_correct'])));
                if ($correctCount !== 1) {
                    $revalidatedErrors[] = [
                        'item_number' => $num,
                        'error' => "Butir soal #{$num} bertipe Benar/Salah (B/S) wajib memiliki tepat 1 kunci jawaban yang benar (ditemukan: {$correctCount}).",
                    ];
                }
            }
        }

        $job->update([
            'status' => 'parsed',
            'parsed_payload' => [
                'bank_id' => $job->bank_id,
                'questions' => $questions,
            ],
            'parse_errors' => $revalidatedErrors,
        ]);

        return $job;
    }

    /**
     * Konfirmasi dan komit draf soal ke bank soal resmi via QuestionBankService::importQuestions (AC4 & AC5).
     */
    public function commitImport(string $id, ?string $idempotencyKey, User $user, Request $request): array
    {
        $job = QuestionImportJob::with('bank')->findOrFail($id);
        $this->verifyBankAccess($job->bank, $user);

        // AC5: Idempotensi - jika sudah dikommit dengan kunci yang sama, kembalikan hasil sebelumnya
        if ($idempotencyKey && $job->idempotency_key === $idempotencyKey && $job->status === 'committed') {
            return [
                'bank_id' => $job->bank_id,
                'imported_count' => $job->imported_count,
                'message' => 'Impor soal telah dikomit sebelumnya (idempoten).',
            ];
        }

        if ($job->status === 'committed') {
            return [
                'bank_id' => $job->bank_id,
                'imported_count' => $job->imported_count,
                'message' => 'Impor soal telah berhasil dikomit.',
            ];
        }

        if ($job->status !== 'parsed') {
            throw new HttpException(422, "Berkas draf belum siap dikomit (status saat ini: {$job->status}).");
        }

        if (! empty($job->parse_errors) && count($job->parse_errors) > 0) {
            throw new HttpException(422, 'Masih terdapat butir soal yang memiliki kesalahan format/kunci. Harap koreksi draf terlebih dahulu.');
        }

        $payload = $job->parsed_payload;
        if (empty($payload['questions'])) {
            throw new HttpException(422, 'Tidak ada butir soal yang valid untuk diimpor.');
        }

        // Panggil service impor massal resmi yang sudah ada
        $result = $this->questionBankService->importQuestions($payload, $request);

        $job->update([
            'status' => 'committed',
            'imported_count' => $result['imported_count'],
            'idempotency_key' => $idempotencyKey,
        ]);

        return $result;
    }

    /**
     * Hasilkan berkas DOCX template baku untuk diunduh pengguna.
     */
    public function generateTemplateDocx(): string
    {
        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection();

        $section->addTitle('TEMPLATE BAKU IMPOR SOAL CBT', 1);
        $section->addText(
            'Panduan: Tuliskan butir-butir soal di bawah ini sesuai format penomoran dan tag yang disediakan. '
            .'Format ini mendukung soal Pilihan Ganda (PG), Benar/Salah (BS), Isian Singkat (ISIAN), dan Uraian/Esai (URAIAN).'
        );
        $section->addTextBreak(1);

        // Contoh Soal 1: PG
        $section->addText('1. [PG] [BOBOT: 2] [LEVEL: MOTS]');
        $section->addText('Sebuah kubus memiliki panjang rusuk 5 cm. Berapakah volume kubus tersebut?');
        $section->addText('A. 25 cm3');
        $section->addText('B. 100 cm3');
        $section->addText('C. 125 cm3');
        $section->addText('D. 150 cm3');
        $section->addText('KUNCI: C');
        $section->addText('PEMBAHASAN: Volume kubus = s x s x s = 5 x 5 x 5 = 125 cm3.');
        $section->addTextBreak(1);

        // Contoh Soal 2: BS
        $section->addText('2. [BS] [BOBOT: 1] [LEVEL: LOTS]');
        $section->addText('Kubus memiliki 6 sisi yang semuanya berbentuk persegi sama besar.');
        $section->addText('A. Benar');
        $section->addText('B. Salah');
        $section->addText('KUNCI: A');
        $section->addTextBreak(1);

        // Contoh Soal 3: ISIAN
        $section->addText('3. [ISIAN] [BOBOT: 3] [LEVEL: MOTS]');
        $section->addText('Bangun ruang sisi lengkung yang memiliki tepat satu titik puncak dan satu sisi alas lingkaran adalah ...');
        $section->addText('KUNCI: Kerucut');
        $section->addTextBreak(1);

        // Contoh Soal 4: URAIAN
        $section->addText('4. [URAIAN] [BOBOT: 4] [LEVEL: HOTS]');
        $section->addText('Jelaskan perbedaan antara prisma segitiga dan limas segitiga ditinjau dari jumlah sisi, rusuk, dan titik sudutnya!');
        $section->addText('PEMBAHASAN: Prisma memiliki sepasang sisi kongruen (alas dan tutup) serta 5 sisi, 9 rusuk, 6 titik sudut. Limas memiliki 1 alas dan 4 sisi, 6 rusuk, 4 titik sudut.');

        $tempPath = tempnam(sys_get_temp_dir(), 'cbt_tpl_').'.docx';
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempPath);

        return $tempPath;
    }

    /**
     * Validasi berkas .docx (ekstensi, ukuran, MIME, dan magic bytes zip).
     */
    protected function validateDocxFile(UploadedFile $file): void
    {
        // 1. Ekstensi
        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext !== 'docx') {
            throw new HttpException(400, 'Berkas harus memiliki format .docx');
        }

        // 2. Ukuran maksimal 10 MB
        if ($file->getSize() > 10 * 1024 * 1024) {
            throw new HttpException(400, 'Ukuran berkas melebihi batas maksimal 10 MB.');
        }

        // 3. Magic Bytes Check: Header ZIP PK\x03\x04
        $handle = fopen($file->getRealPath(), 'rb');
        if (! $handle) {
            throw new HttpException(400, 'Berkas tidak dapat dibaca.');
        }
        $header = fread($handle, 4);
        fclose($handle);

        if ($header !== "PK\x03\x04") {
            throw new HttpException(400, 'Format berkas tidak valid (magic bytes bukan berkas OpenXML/DOCX).');
        }
    }

    /**
     * Verifikasi cakupan hak akses ke bank soal (AC6).
     */
    protected function verifyBankAccess(QuestionBank $bank, User $user): void
    {
        // Jika user memiliki profil guru (teacher) dan bukan admin sistem/cbt, ia hanya boleh mengakses bank soal miliknya
        $isTeacher = (bool) $user->teacher;
        $isAdmin = $user->hasRole('admin') || $user->hasRole('super_admin') || $user->hasRole('cbt_operator');

        if ($isTeacher && ! $isAdmin && $bank->owner_id !== $user->id) {
            throw new HttpException(403, 'Akses ditolak: Anda hanya dapat mengimpor atau mengelola bank soal milik Anda sendiri.');
        }
    }
}
