<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProgressReportReminderCommand extends Command
{
    protected $signature = 'academic:progress-report-reminder';

    protected $description = 'Ingatkan guru untuk mengisi nilai & wali kelas untuk mengisi catatan karakter siswa';

    public function handle(): int
    {
        $teachers = Teacher::whereNotNull('user_id')->get();
        $period = Carbon::now()->translatedFormat('F Y');
        $count = 0;

        foreach ($teachers as $teacher) {
            Notification::create([
                'user_id' => $teacher->user_id,
                'notif_type' => 'academic_reminder',
                'title' => 'Pengingat Pengisian Nilai & Karakter',
                'body' => "Bapak/Ibu Guru {$teacher->full_name}, mohon untuk segera melengkapi penilaian akademik dan catatan karakter periode {$period}.",
                'data' => [
                    'period' => $period,
                    'deep_link' => '/grades',
                    'sent_at' => Carbon::now()->toIso8601String(),
                ],
            ]);
            $count++;
        }

        AuditLog::create([
            'action' => 'PROGRESS_REPORT_REMINDER_SENT',
            'entity' => 'notifications',
            'new_value' => [
                'recipient_count' => $count,
                'period' => $period,
            ],
        ]);

        $this->info("Berhasil mengirim pengingat pengisian nilai dan karakter kepada {$count} guru & wali kelas.");

        return Command::SUCCESS;
    }
}
