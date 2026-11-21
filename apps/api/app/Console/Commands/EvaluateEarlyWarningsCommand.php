<?php

namespace App\Console\Commands;

use App\Services\Bk\EarlyWarningService;
use Illuminate\Console\Command;

class EvaluateEarlyWarningsCommand extends Command
{
    protected $signature = 'bk:evaluate-early-warnings
                            {--school= : ID Sekolah untuk membatasi evaluasi}
                            {--class= : ID Rombel/Kelas untuk evaluasi spesifik}
                            {--att-threshold=75.0 : Ambang batas persentase kehadiran}
                            {--min-absent=3 : Batas minimum hari alpa}
                            {--grade-drop=15.0 : Ambang batas penurunan rata-rata nilai}
                            {--violation-points=20 : Ambang batas akumulasi poin pelanggaran}';

    protected $description = 'BE-BK-02: Jalankan evaluasi otomatis aturan deteksi dini siswa berisiko (presensi, nilai, disiplin)';

    public function __construct(
        protected EarlyWarningService $service
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Memulai evaluasi deteksi dini siswa (Early Warning System)...');

        $options = [
            'school_id' => $this->option('school'),
            'class_id' => $this->option('class'),
            'attendance_threshold_percent' => (float) $this->option('att-threshold'),
            'min_absent_days' => (int) $this->option('min-absent'),
            'grade_drop_threshold' => (float) $this->option('grade-drop'),
            'violation_points_threshold' => (int) $this->option('violation-points'),
        ];

        try {
            $result = $this->service->evaluateRules($options);

            $this->info($result['message']);
            $this->table(
                ['Metrik', 'Jumlah'],
                [
                    ['Siswa Dievaluasi', $result['students_evaluated']],
                    ['Peringatan Baru Dibuat', $result['created_alerts']],
                    ['Peringatan Diperbarui', $result['updated_alerts']],
                ]
            );

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Gagal mengevaluasi deteksi dini: '.$e->getMessage());
            report($e);

            return Command::FAILURE;
        }
    }
}
