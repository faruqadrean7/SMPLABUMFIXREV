<?php

namespace App\Console\Commands;

use App\Models\Exam;
use App\Models\GradeLevel;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\User;
use App\Services\ExamService;
use App\Services\QuestionBankService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * BE-CBT-IMPORT-MTK-01: Impor satu paket ujian (bank soal + butir soal + ujian) dari manifest JSON lokal.
 *
 * Membungkus layanan yang sudah ada (QuestionBankService::importQuestions + ExamService) agar impor
 * paket ujian nyata dapat diulang & idempoten, TANPA menaruh isi soal/kunci di dalam repo (manifest
 * disimpan di storage/app/import/ yang ter-gitignore). Lihat PROMPT_IMPORT_CBT_MATEMATIKA.md.
 */
class ImportCbtExamPackage extends Command
{
    protected $signature = 'cbt:import-package
        {manifest : Path manifest JSON (absolut, atau relatif terhadap storage/app)}
        {--fresh : Hapus bank & ujian bernama sama lebih dulu, lalu buat ulang}
        {--dry-run : Hanya validasi & resolusi master data, tanpa menulis apa pun}';

    protected $description = 'Impor paket ujian (bank soal + soal + ujian CBT) dari manifest JSON lokal (BE-CBT-IMPORT-MTK-01).';

    public function handle(QuestionBankService $bankService, ExamService $examService): int
    {
        try {
            $manifest = $this->loadManifest();
            if ($manifest === null) {
                return self::FAILURE;
            }

            $this->validateManifest($manifest);

            // --- Resolusi master data (gagal cepat & jelas; tidak membuat master baru) ---
            $owner = $this->resolveUser($manifest['owner']['email'], 'owner (pemilik bank soal)');
            $schoolId = $manifest['school']['id'] ?? $owner->school_id;

            $creatorEmail = $manifest['exam']['creator_email'] ?? $manifest['owner']['email'];
            $creator = $this->resolveUser($creatorEmail, 'pembuat ujian');

            $subject = $this->resolveSubject($manifest['bank'], $schoolId);
            $grade = $this->resolveGrade($manifest['bank']['grade_aliases'] ?? [], $schoolId);

            $this->line('');
            $this->info('Resolusi master data:');
            $this->line("  Sekolah    : {$schoolId}");
            $this->line("  Mapel      : {$subject->name} ({$subject->id})");
            $this->line('  Jenjang    : '.($grade?->name ?? '—').' ('.($grade?->id ?? 'null').')');
            $this->line("  Owner bank : {$owner->email} ({$owner->id})");
            $this->line("  Pembuat uji: {$creator->email} ({$creator->id})");

            if (! $owner->hasPermission('cbt.bank.write')) {
                $this->warn("  ! Owner {$owner->email} tidak punya permission cbt.bank.write.");
            }
            if (! $creator->hasPermission('cbt.exam.manage')) {
                $this->warn("  ! Pembuat ujian {$creator->email} tidak punya permission cbt.exam.manage.");
            }

            $totalWeight = array_sum(array_column($manifest['questions'], 'weight'));
            $this->line('  Butir soal : '.count($manifest['questions'])." (total bobot {$totalWeight})");

            if ($this->option('dry-run')) {
                $this->info('[dry-run] Validasi & resolusi OK. Tidak ada data yang ditulis.');

                return self::SUCCESS;
            }

            // --- Request pembawa actor untuk audit & school scoping di service ---
            $ownerReq = $this->requestAs($owner);
            $creatorReq = $this->requestAs($creator);

            $result = DB::transaction(function () use (
                $manifest, $subject, $grade, $schoolId, $ownerReq, $creatorReq, $bankService, $examService
            ) {
                $this->handleFresh($manifest, $subject->id, $schoolId);

                // 1) Bank soal (idempoten by name+subject+grade+school)
                $bank = QuestionBank::where('name', $manifest['bank']['name'])
                    ->where('subject_id', $subject->id)
                    ->where('grade_level_id', $grade?->id)
                    ->where(fn ($q) => $q->where('school_id', $schoolId)->orWhereNull('school_id'))
                    ->first();

                if ($bank && $bank->questions()->exists()) {
                    throw new \RuntimeException(
                        "Bank '{$bank->name}' sudah berisi soal. Jalankan ulang dengan --fresh untuk membuat ulang."
                    );
                }

                if (! $bank) {
                    $bank = $bankService->createQuestionBank([
                        'name' => $manifest['bank']['name'],
                        'subject_id' => $subject->id,
                        'grade_level_id' => $grade?->id,
                    ], $ownerReq);
                    $this->line("  + Bank dibuat: {$bank->id}");
                }

                // 2) Import massal butir soal (service menandai status 'aktif' + audit log)
                $import = $bankService->importQuestions([
                    'bank_id' => $bank->id,
                    'questions' => array_map(fn ($q) => $this->questionPayload($q), $manifest['questions']),
                ], $ownerReq);

                // Peta id soal hasil impor -> sort_order (urutan kembali = urutan manifest)
                $created = $import['questions'];
                $assignments = [];
                foreach ($manifest['questions'] as $i => $src) {
                    $assignments[] = [
                        'question_id' => $created[$i]->id,
                        'sort_order' => $src['sort_order'] ?? ($i + 1),
                    ];
                }

                // 3) Ujian SAS (idempoten by title+subject+school)
                $exam = Exam::where('title', $manifest['exam']['title'])
                    ->where('subject_id', $subject->id)
                    ->where(fn ($q) => $q->where('school_id', $schoolId)->orWhereNull('school_id'))
                    ->first();

                if (! $exam) {
                    [$startAt, $endAt] = $this->placeholderSchedule($manifest['exam']);
                    $exam = $examService->createExam([
                        'subject_id' => $subject->id,
                        'title' => $manifest['exam']['title'],
                        'exam_type' => $manifest['exam']['exam_type'],
                        'duration_min' => $manifest['exam']['duration_min'],
                        'start_at' => $startAt,
                        'end_at' => $endAt,
                        'passing_grade' => $manifest['exam']['passing_grade'] ?? 75,
                        'shuffle_questions' => $manifest['exam']['shuffle_questions'] ?? true,
                        'shuffle_options' => $manifest['exam']['shuffle_options'] ?? true,
                        'package_mode' => $manifest['exam']['package_mode'] ?? 'single',
                        'show_result_mode' => $manifest['exam']['show_result_mode'] ?? 'after_release',
                        'status' => $manifest['exam']['status'] ?? 'draft',
                    ], $creatorReq);
                    $this->line("  + Ujian dibuat: {$exam->id}");
                }

                // 4) Plotting soal ke ujian (updateOrCreate -> idempoten)
                $examService->assignQuestions($exam->id, ['questions' => $assignments], $creatorReq);

                return ['bank' => $bank, 'imported' => $import['imported_count'], 'exam' => $exam];
            });

            $this->newLine();
            $this->info('Impor selesai.');
            $this->line('  bank_id        : '.$result['bank']->id);
            $this->line('  soal terimpor  : '.$result['imported']);
            $this->line('  exam_id        : '.$result['exam']->id);
            $this->line('  total bobot    : '.$totalWeight.' (harus 100)');
            if (! empty($manifest['exam']['schedule_placeholder'])) {
                $this->warn('  Jadwal ujian masih PLACEHOLDER (status draft). Tetapkan start/end + ruang + peserta sebelum digelar.');
            }
            $this->warn('  Beberapa soal menunggu unggah gambar (cari "media_pending" di manifest), isi ke body.media_url.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Gagal impor: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function loadManifest(): ?array
    {
        $path = $this->argument('manifest');
        if (! str_starts_with($path, '/')) {
            $path = storage_path('app/'.$path);
        }
        if (! is_file($path)) {
            $this->error("Manifest tidak ditemukan: {$path}");

            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            $this->error('Manifest bukan JSON valid: '.json_last_error_msg());

            return null;
        }

        return $data;
    }

    private function validateManifest(array $manifest): void
    {
        $validator = Validator::make($manifest, [
            'bank.name' => ['required', 'string', 'max:200'],
            'owner.email' => ['required', 'email'],
            'exam.title' => ['required', 'string', 'max:200'],
            'exam.exam_type' => ['required', 'in:kuis,harian,STS,SAS,ASAT,remedial,pengayaan'],
            'exam.duration_min' => ['required', 'integer', 'min:5', 'max:360'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.question_type' => ['required', 'in:pg,pg_kompleks,bs,menjodohkan,isian,uraian'],
            'questions.*.body.content' => ['required', 'string'],
            'questions.*.weight' => ['required', 'numeric', 'min:0.1', 'max:100'],
            'questions.*.options' => ['nullable', 'array'],
            'questions.*.options.*.label' => ['required_with:questions.*.options', 'string', 'max:10'],
        ]);
        $validator->validate();

        // Aturan bisnis: pg/bs tepat 1 kunci; menjodohkan tiap opsi punya match_key.
        foreach ($manifest['questions'] as $i => $q) {
            $no = $q['sort_order'] ?? ($i + 1);
            if (in_array($q['question_type'], ['pg', 'bs'], true)) {
                $correct = collect($q['options'] ?? [])->filter(fn ($o) => ! empty($o['is_correct']))->count();
                if ($correct !== 1) {
                    throw new \RuntimeException("Soal #{$no} ({$q['question_type']}) harus punya tepat 1 opsi benar, ditemukan {$correct}.");
                }
            }
            if ($q['question_type'] === 'menjodohkan') {
                foreach ($q['options'] ?? [] as $o) {
                    if (empty($o['match_key'])) {
                        throw new \RuntimeException("Soal menjodohkan #{$no} punya opsi tanpa match_key.");
                    }
                }
            }
        }
    }

    private function resolveUser(string $email, string $peran): User
    {
        $user = User::where('email', $email)->first();
        if (! $user) {
            throw new \RuntimeException("User {$peran} tidak ditemukan: {$email}");
        }

        return $user;
    }

    private function resolveSubject(array $bank, string $schoolId): Subject
    {
        $query = Subject::where('school_id', $schoolId);
        if (! empty($bank['subject_code'])) {
            $query->where('code', $bank['subject_code']);
        } elseif (! empty($bank['subject_name'])) {
            $query->where('name', $bank['subject_name']);
        } else {
            throw new \RuntimeException('Manifest bank harus memuat subject_code atau subject_name.');
        }

        $matches = $query->get();
        if ($matches->count() === 0) {
            throw new \RuntimeException('Mata pelajaran tidak ditemukan untuk sekolah tsb (cek subject_code/name).');
        }
        if ($matches->count() > 1) {
            throw new \RuntimeException('Mata pelajaran ambigu ('.$matches->count().' cocok). Gunakan subject_code yang unik.');
        }

        return $matches->first();
    }

    private function resolveGrade(array $aliases, string $schoolId): ?GradeLevel
    {
        if (empty($aliases)) {
            return null;
        }
        $matches = GradeLevel::where('school_id', $schoolId)->whereIn('name', $aliases)->get();
        if ($matches->count() > 1) {
            throw new \RuntimeException('Jenjang ambigu ('.$matches->count().' cocok). Persempit grade_aliases.');
        }

        return $matches->first();
    }

    private function handleFresh(array $manifest, string $subjectId, string $schoolId): void
    {
        if (! $this->option('fresh')) {
            return;
        }

        // Hapus ujian dulu (cascade exam_questions) agar FK restrict di questions tidak menghalangi hapus bank.
        Exam::where('title', $manifest['exam']['title'])
            ->where('subject_id', $subjectId)
            ->where(fn ($q) => $q->where('school_id', $schoolId)->orWhereNull('school_id'))
            ->get()->each(fn ($e) => $e->delete());

        QuestionBank::where('name', $manifest['bank']['name'])
            ->where('subject_id', $subjectId)
            ->where(fn ($q) => $q->where('school_id', $schoolId)->orWhereNull('school_id'))
            ->get()->each(fn ($b) => $b->delete());

        $this->line('  ~ --fresh: ujian & bank bernama sama dihapus.');
    }

    /** Ambil hanya field yang dipakai importQuestions (sisanya seperti sort_order/media_pending diabaikan service). */
    private function questionPayload(array $q): array
    {
        return [
            'question_type' => $q['question_type'],
            'body' => $q['body'],
            'cp_code' => $q['cp_code'] ?? null,
            'cognitive_level' => $q['cognitive_level'] ?? 'MOTS',
            'difficulty' => $q['difficulty'] ?? 3,
            'weight' => $q['weight'] ?? 1.0,
            'explanation' => $q['explanation'] ?? null,
            'options' => array_map(fn ($o) => [
                'label' => $o['label'],
                'body' => $o['body'],
                'is_correct' => (bool) ($o['is_correct'] ?? false),
                'match_key' => $o['match_key'] ?? null,
            ], $q['options'] ?? []),
        ];
    }

    /** @return array{0:string,1:string} start_at, end_at ISO */
    private function placeholderSchedule(array $exam): array
    {
        $start = now()->addDays(30)->startOfHour();
        $end = (clone $start)->addMinutes((int) ($exam['duration_min'] ?? 90));

        return [$start->toDateTimeString(), $end->toDateTimeString()];
    }

    private function requestAs(User $user): Request
    {
        $req = Request::create('/cli/cbt-import', 'POST');
        $req->setUserResolver(fn () => $user);

        return $req;
    }
}
