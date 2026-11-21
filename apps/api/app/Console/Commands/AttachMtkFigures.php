<?php

namespace App\Console\Commands;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\Question;
use Illuminate\Console\Command;
use Throwable;

/**
 * BE-CBT-IMPORT-MTK-01 (figures): Tautkan figur SVG ke soal ujian PAS Matematika yang sudah diimpor.
 *
 * Memetakan sort_order butir (dari exam_questions) ke berkas SVG di public/cbt/mtk-pas-9/ lalu
 * menyetel body.media_url. Update langsung model Question (BUKAN lewat service) agar tidak memicu
 * pembuatan versi baru yang akan memutus relasi exam_questions. Idempoten.
 *
 * Figur adalah gambar orisinal (redraw) — bukan berkas asli sekolah — sehingga aman dipakai &
 * di-commit; juga menyelesaikan isu hak cipta foto kubah (diganti ilustrasi setengah bola generik).
 */
class AttachMtkFigures extends Command
{
    protected $signature = 'cbt:attach-mtk-figures
        {--exam-title=PAS Matematika Kelas 9 — Genap 2025/2026 : Judul ujian target}
        {--base-url= : Basis URL publik figur (default config app.url)}
        {--path=cbt/mtk-pas-9 : Sub-path publik tempat SVG di-host}';

    protected $description = 'Tautkan figur SVG ke soal PAS Matematika hasil impor (set body.media_url). Idempoten.';

    /** sort_order => nama berkas SVG. Beberapa soal berbagi figur (stimulus sama). */
    private array $map = [
        4 => 'q04-line.svg',
        5 => 'q05-coord.svg',
        9 => 'q09-prism.svg',
        11 => 'q11-pool.svg',
        13 => 'q13-dome.svg',
        17 => 'q17-scatter.svg',
        18 => 'q18-scatter-options.svg',
        19 => 'q19-stemleaf.svg',
        20 => 'q19-stemleaf.svg',
        21 => 'q21-plants.svg',
        22 => 'q22-shapes.svg',
        23 => 'q22-shapes.svg',
        24 => 'q22-shapes.svg',
        25 => 'q22-shapes.svg',
        26 => 'q22-shapes.svg',
    ];

    public function handle(): int
    {
        try {
            $exam = Exam::where('title', $this->option('exam-title'))->first();
            if (! $exam) {
                $this->error('Ujian tidak ditemukan: '.$this->option('exam-title'));

                return self::FAILURE;
            }

            $base = rtrim($this->option('base-url') ?: (string) config('app.url'), '/');
            $path = trim($this->option('path'), '/');

            $examQuestions = ExamQuestion::where('exam_id', $exam->id)->get()->keyBy('sort_order');
            $updated = 0;
            $missingFiles = [];

            foreach ($this->map as $sortOrder => $file) {
                $eq = $examQuestions->get($sortOrder);
                if (! $eq) {
                    $this->warn("  ! Tidak ada butir pada sort_order {$sortOrder}, dilewati.");

                    continue;
                }

                $full = public_path($path.'/'.$file);
                if (! is_file($full)) {
                    $missingFiles[$file] = true;
                }

                $question = Question::find($eq->question_id);
                if (! $question) {
                    continue;
                }

                $body = is_array($question->body) ? $question->body : [];
                $body['media_url'] = "{$base}/{$path}/{$file}";
                unset($body['media_pending']); // bersihkan penanda sementara
                $question->body = $body;
                $question->save();
                $updated++;
            }

            $this->info("Selesai: {$updated} butir ditautkan ke figur (base {$base}/{$path}).");
            if (! empty($missingFiles)) {
                $this->warn('  ! Berkas SVG belum ada di public: '.implode(', ', array_keys($missingFiles)));
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Gagal menautkan figur: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
