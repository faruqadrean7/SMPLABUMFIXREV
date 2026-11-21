<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\EarlyWarning;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DailyAttendanceSummaryCommand extends Command
{
    protected $signature = 'attendance:daily-summary';

    protected $description = 'Rekap presensi harian siswa dan hitung ambang peringatan dini (early warning BK)';

    public function handle(): int
    {
        $today = Carbon::today();
        $attendances = Attendance::whereDate('att_date', $today)->get();

        $summary = [
            'date' => $today->toDateString(),
            'total_recorded' => $attendances->count(),
            'hadir' => $attendances->where('status', 'hadir')->count(),
            'izin' => $attendances->where('status', 'izin')->count(),
            'sakit' => $attendances->where('status', 'sakit')->count(),
            'alpa' => $attendances->where('status', 'alpa')->count(),
            'terlambat' => $attendances->where('status', 'terlambat')->count(),
        ];

        $this->info("Rekap Presensi Tanggal {$summary['date']}: {$summary['total_recorded']} tercatat (Hadir: {$summary['hadir']}, Izin: {$summary['izin']}, Sakit: {$summary['sakit']}, Alpa: {$summary['alpa']}, Terlambat: {$summary['terlambat']})");

        // Evaluasi Ambang Deteksi Dini BK (Siswa dengan >= 3 alpa dalam 30 hari terakhir)
        $thirtyDaysAgo = Carbon::today()->subDays(30);
        $flaggedStudents = Student::where('status', 'active')
            ->whereHas('attendances', function ($q) use ($thirtyDaysAgo) {
                $q->whereDate('att_date', '>=', $thirtyDaysAgo)->where('status', 'alpa');
            }, '>=', 3)
            ->get();

        $warningCount = 0;
        foreach ($flaggedStudents as $student) {
            $alpaCount = $student->attendances()
                ->whereDate('att_date', '>=', $thirtyDaysAgo)
                ->where('status', 'alpa')
                ->count();

            EarlyWarning::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'rule_code' => 'ATT_LOW',
                    'status' => 'aktif',
                ],
                [
                    'severity' => $alpaCount >= 5 ? 'tinggi' : 'sedang',
                    'data' => [
                        'rule' => 'Akumulasi alpa presensi mencapai atau melampaui batas ambang',
                        'alpa_count' => $alpaCount,
                        'period_days' => 30,
                        'detected_at' => Carbon::now()->toIso8601String(),
                    ],
                ]
            );
            $warningCount++;
        }

        AuditLog::create([
            'action' => 'DAILY_ATTENDANCE_SUMMARY_PROCESSED',
            'entity' => 'attendances',
            'new_value' => array_merge($summary, ['early_warnings_triggered' => $warningCount]),
        ]);

        $this->info("Deteksi dini BK selesai: {$warningCount} siswa teridentifikasi ambang presensi kritis.");

        return Command::SUCCESS;
    }
}
