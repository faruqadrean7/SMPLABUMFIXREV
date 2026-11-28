<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Services\Bk\EarlyWarningService;
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

        // P1-27: Delegasikan evaluasi deteksi dini BK secara terpusat ke EarlyWarningService
        // (status siswa aktif 'active'/'aktif', agregasi harian tunggal AttendanceAggregator, aturan ATTENDANCE_LOW)
        $ewResult = app(EarlyWarningService::class)->evaluateRules(['min_absent_days' => 3]);
        $warningCount = (int) (($ewResult['created_alerts'] ?? 0) + ($ewResult['updated_alerts'] ?? 0));

        AuditLog::create([
            'action' => 'DAILY_ATTENDANCE_SUMMARY_PROCESSED',
            'entity' => 'attendances',
            'new_value' => array_merge($summary, ['early_warnings_triggered' => $warningCount]),
        ]);

        $this->info("Deteksi dini BK selesai: {$warningCount} siswa teridentifikasi ambang presensi kritis.");

        return Command::SUCCESS;
    }
}
