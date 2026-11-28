<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// =============================================================================
// Section 17: Job Terjadwal (Scheduler) - Portal Sekolah Satu Pintu
// =============================================================================

// 1. ppdb-close-wave: Tiap menit (Tutup gelombang saat lewat waktu)
Schedule::command('ppdb:close-wave')->everyMinute()->withoutOverlapping()->onOneServer();

// 2. exam-auto-submit: Tiap menit (Submit sesi yang melewati batas waktu)
Schedule::command('exam:auto-submit')->everyMinute()->withoutOverlapping()->onOneServer();

// 3. daily-attendance-summary: Harian malam (Rekap presensi, hitung peringatan dini BK)
Schedule::command('attendance:daily-summary')->dailyAt('23:00')->withoutOverlapping()->onOneServer();

// 4. progress-report-reminder: Bulanan (Ingatkan guru isi nilai & walas isi karakter)
Schedule::command('academic:progress-report-reminder')->monthly()->withoutOverlapping()->onOneServer();

// 5. mou-expiry-reminder: Harian (Ingatkan MoU akan berakhir)
Schedule::command('partnership:mou-expiry-reminder')->dailyAt('07:00')->withoutOverlapping()->onOneServer();

// 6. ticket-sla-reminder: Per jam (Tiket pending lewat SLA)
Schedule::command('sarpras:ticket-sla-reminder')->hourly()->withoutOverlapping()->onOneServer();

// Anti-bullying: eskalasi aduan yang melewati tenggat penanganan (tiap 30 menit)
Schedule::command('complaints:escalate-overdue')->everyThirtyMinutes()->withoutOverlapping()->onOneServer();

// 7. backup-verify: Mingguan (Verifikasi backup)
Schedule::command('system:backup-verify')->weekly()->withoutOverlapping()->onOneServer();

// 8. bk-evaluate-early-warnings: Harian malam (Evaluasi aturan deteksi dini siswa)
Schedule::command('bk:evaluate-early-warnings')->dailyAt('23:30')->withoutOverlapping()->onOneServer();

// 9. reminder-dispatch: Pengingat otomatis CBT, LMS, dan ATT (Presensi) dengan idempotency guard
Schedule::command('reminder:dispatch --type=all')->everyFifteenMinutes()->withoutOverlapping(10)->onOneServer();
Schedule::command('reminder:dispatch --type=att')->timezone('Asia/Jakarta')->dailyAt('10:00')->withoutOverlapping(10)->onOneServer();
Schedule::command('reminder:dispatch --recover-stuck')->hourly()->withoutOverlapping(10)->onOneServer();

// 10. finance-send-due-reminders: Harian pagi (Pengingat jatuh tempo tagihan H-3)
Schedule::command('finance:send-due-reminders --days=3')->timezone('Asia/Jakarta')->dailyAt('07:30')->withoutOverlapping()->onOneServer();

// 11. finance-reconcile: Harian dini hari (Rekonsiliasi status & paid_amount tagihan)
Schedule::command('finance:reconcile')->timezone('Asia/Jakarta')->dailyAt('01:30')->withoutOverlapping()->onOneServer();

// 12. auth-prune-tokens: Harian dini hari (P1-45: Bersihkan refresh_tokens & password_resets kedaluwarsa)
Schedule::command('auth:prune-tokens')->timezone('Asia/Jakarta')->dailyAt('02:30')->withoutOverlapping()->onOneServer();
