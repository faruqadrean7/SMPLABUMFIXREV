<?php

namespace App\Console\Commands;

use App\Models\Applicant;
use App\Models\Student;
use Illuminate\Console\Command;

class BackfillNikHashCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sistlabum:backfill-nik-hash {--dry-run : Hanya hitung dan laporkan record tanpa mengubah database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill kolom blind index nik_hash (HMAC-SHA256) untuk data Student dan Applicant yang belum terindeks.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        $this->info($isDryRun ? 'Menjalankan simulasi backfill nik_hash (--dry-run)...' : 'Memulai backfill nik_hash...');

        // 1. Siswa (Student)
        $studentQuery = Student::withTrashed()->whereNotNull('nik_enc')->whereNull('nik_hash');
        $totalStudents = $studentQuery->count();
        $this->line("Ditemukan {$totalStudents} data siswa yang membutuhkan backfill nik_hash.");

        $updatedStudents = 0;
        if (! $isDryRun && $totalStudents > 0) {
            $studentQuery->chunkById(100, function ($students) use (&$updatedStudents) {
                foreach ($students as $student) {
                    try {
                        if (! empty($student->nik_enc)) {
                            $student->nik_hash = Student::hashNik((string) $student->nik_enc);
                            $student->saveQuietly();
                            $updatedStudents++;
                        }
                    } catch (\Throwable $e) {
                        $this->warn("Gagal backfill siswa ID {$student->id}: {$e->getMessage()}");
                    }
                }
            });
        }

        // 2. Pendaftar PPDB (Applicant)
        $applicantQuery = Applicant::whereNotNull('nik_enc')->whereNull('nik_hash');
        $totalApplicants = $applicantQuery->count();
        $this->line("Ditemukan {$totalApplicants} data calon siswa (pendaftar PPDB) yang membutuhkan backfill nik_hash.");

        $updatedApplicants = 0;
        if (! $isDryRun && $totalApplicants > 0) {
            $applicantQuery->chunkById(100, function ($applicants) use (&$updatedApplicants) {
                foreach ($applicants as $applicant) {
                    try {
                        if (! empty($applicant->nik_enc)) {
                            $applicant->nik_hash = Applicant::hashNik((string) $applicant->nik_enc);
                            $applicant->saveQuietly();
                            $updatedApplicants++;
                        }
                    } catch (\Throwable $e) {
                        $this->warn("Gagal backfill pendaftar ID {$applicant->id}: {$e->getMessage()}");
                    }
                }
            });
        }

        if ($isDryRun) {
            $this->info("Simulasi selesai. Total potensi update: {$totalStudents} siswa, {$totalApplicants} calon siswa.");
        } else {
            $this->info("Backfill selesai. Berhasil memperbarui {$updatedStudents}/{$totalStudents} siswa, {$updatedApplicants}/{$totalApplicants} calon siswa.");
        }

        return Command::SUCCESS;
    }
}
