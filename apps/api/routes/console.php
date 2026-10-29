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
Schedule::command('ppdb:close-wave')->everyMinute()->withoutOverlapping();

// 2. exam-auto-submit: Tiap menit (Submit sesi yang melewati batas waktu)
Schedule::command('exam:auto-submit')->everyMinute()->withoutOverlapping();

// 3. daily-attendance-summary: Harian malam (Rekap presensi, hitung peringatan dini BK)
Schedule::command('attendance:daily-summary')->dailyAt('23:00')->withoutOverlapping();

// 4. progress-report-reminder: Bulanan (Ingatkan guru isi nilai & walas isi karakter)
Schedule::command('academic:progress-report-reminder')->monthly()->withoutOverlapping();

// 5. mou-expiry-reminder: Harian (Ingatkan MoU akan berakhir)
Schedule::command('partnership:mou-expiry-reminder')->dailyAt('07:00')->withoutOverlapping();

// 6. ticket-sla-reminder: Per jam (Tiket pending lewat SLA)
Schedule::command('sarpras:ticket-sla-reminder')->hourly()->withoutOverlapping();

// 7. backup-verify: Mingguan (Verifikasi backup)
Schedule::command('system:backup-verify')->weekly()->withoutOverlapping();

// 8. bk-evaluate-early-warnings: Harian malam (Evaluasi aturan deteksi dini siswa)
Schedule::command('bk:evaluate-early-warnings')->dailyAt('23:30')->withoutOverlapping();

// 9. reminder-dispatch: Pengingat otomatis CBT, LMS, dan ATT (Presensi) dengan idempotency guard
Schedule::command('reminder:dispatch --type=all')->everyFifteenMinutes()->withoutOverlapping(10);
Schedule::command('reminder:dispatch --type=att')->timezone('Asia/Jakarta')->dailyAt('10:00')->withoutOverlapping(10);
Schedule::command('reminder:dispatch --recover-stuck')->hourly()->withoutOverlapping(10);
