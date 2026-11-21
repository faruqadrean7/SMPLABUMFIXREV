<?php

namespace App\Console\Commands;

use App\Services\Ntf\ReminderService;
use Illuminate\Console\Command;

class DispatchRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminder:dispatch 
                            {--type=all : Tipe pengingat yang akan diproses (all, cbt, lms, att/attendance_daily)} 
                            {--school= : ID sekolah untuk filter multi-tenant} 
                            {--async : Jalankan pengiriman melalui background queue job}
                            {--stats : Tampilkan statistik metrik dan observabilitas reminder}
                            {--retry-failed : Jalankan ulang dispatch pengingat yang gagal}
                            {--recover-stuck : Pulihkan dispatch yang macet dalam status processing}';

    /**
     * The console command aliases for backward and plural compatibility.
     *
     * @var array<int, string>
     */
    protected $aliases = ['reminders:dispatch'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Jalankan Reminder Engine otomatis untuk CBT, tenggat LMS, dan presensi (ATT)';

    /**
     * Execute the console command.
     */
    public function handle(ReminderService $reminderService): int
    {
        $schoolId = $this->option('school') ?: null;
        $async = (bool) $this->option('async');

        // 1. Opsi Observabilitas: Tampilkan Statistik Metrik (tidak mengubah data)
        if ($this->option('stats')) {
            $stats = $reminderService->getStats($schoolId);
            $this->info('=== STATISTIK REMINDER ENGINE ===');
            $this->line("Total Dispatches : {$stats['total']}");
            $this->line("Stuck Processing : {$stats['stuck_processing']}");
            $this->line("Failed           : {$stats['failed']}");
            $this->newLine();

            $this->info('Breakdown per Status:');
            foreach ($stats['by_status'] as $status => $count) {
                $this->line(" - {$status}: {$count}");
            }
            $this->newLine();

            $this->info('Breakdown per Event Type:');
            foreach ($stats['by_event_type'] as $event => $count) {
                $this->line(" - {$event}: {$count}");
            }

            return Command::SUCCESS;
        }

        // 2. Opsi Recovery: Retry Failed Dispatches (tidak menjalankan dispatch biasa)
        if ($this->option('retry-failed')) {
            $this->info('Menjalankan ulang dispatch pengingat yang gagal (school: '.($schoolId ?? 'all').')...');
            $retried = $reminderService->retryFailedDispatches($schoolId, 50, $async);
            $this->info("Selesai. Total dispatch gagal yang diproses ulang: {$retried}.");

            return Command::SUCCESS;
        }

        // 3. Opsi Recovery: Pulihkan dispatch macet (tidak menjalankan dispatch biasa)
        if ($this->option('recover-stuck')) {
            $this->info("Memulihkan dispatch yang macet di status 'processing' > 15 menit...");
            $recovered = $reminderService->recoverStuckProcessing(15, $async);
            $this->info("Selesai. Total dispatch macet yang dipulihkan: {$recovered}.");

            return Command::SUCCESS;
        }

        // 4. Validasi Opsi --type
        $type = strtolower((string) ($this->option('type') ?? 'all'));
        $validTypes = ['all', 'cbt', 'cbt_exam', 'lms', 'lms_assignment', 'att', 'attendance', 'attendance_daily'];

        if (! in_array($type, $validTypes, true)) {
            $this->error("Tipe pengingat '{$type}' tidak valid. Pilihan valid: all, cbt, lms, att (atau attendance_daily).");

            return Command::FAILURE;
        }

        // 5. Default: Eksekusi Reminder Engine sesuai tipe yang divalidasi
        $this->info("Menjalankan Reminder Engine (type: {$type}, school: ".($schoolId ?? 'all').', mode: '.($async ? 'async/queue' : 'sync').')...');

        $result = match ($type) {
            'cbt', 'cbt_exam' => ['cbt' => $reminderService->dispatchCbtExamReminders($schoolId, $async)],
            'lms', 'lms_assignment' => ['lms' => $reminderService->dispatchLmsAssignmentReminders($schoolId, $async)],
            'att', 'attendance', 'attendance_daily' => ['attendance' => $reminderService->dispatchAttendanceReminders($schoolId, $async)],
            default => $reminderService->dispatchAllReminders($schoolId, $async),
        };

        $totalDispatched = 0;
        $totalSkipped = 0;

        foreach ($result as $category => $data) {
            if (is_array($data) && isset($data['dispatched'])) {
                $totalDispatched += $data['dispatched'];
                $totalSkipped += $data['skipped'];
                $this->line(" - [{$category}] Dispatched: {$data['dispatched']}, Skipped (idempotent/preference/stale): {$data['skipped']}");
            }
        }

        $this->info("Selesai. Total dispatched: {$totalDispatched}, Total skipped: {$totalSkipped}.");

        return Command::SUCCESS;
    }
}
