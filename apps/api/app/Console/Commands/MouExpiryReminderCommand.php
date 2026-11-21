<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Partnership;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MouExpiryReminderCommand extends Command
{
    protected $signature = 'partnership:mou-expiry-reminder';

    protected $description = 'Ingatkan MoU kemitraan (partnership) yang akan segera berakhir masa berlakunya';

    public function handle(): int
    {
        $today = Carbon::today();
        $thirtyDaysAhead = Carbon::today()->addDays(30);

        // 1. Update status kemitraan yang sudah kedaluwarsa
        $expiredCount = Partnership::where('status', 'active')
            ->whereDate('valid_until', '<', $today)
            ->update(['status' => 'expired']);

        // 2. Kemitraan yang akan berakhir dalam 30 hari ke depan
        $expiringPartnerships = Partnership::where('status', 'active')
            ->whereDate('valid_until', '>=', $today)
            ->whereDate('valid_until', '<=', $thirtyDaysAhead)
            ->get();

        // Cari admin / humas untuk diberi notifikasi
        $adminRole = Role::whereIn('code', ['super_admin', 'admin', 'principal'])->pluck('id');
        $adminUsers = User::whereHas('roles', fn ($q) => $q->whereIn('roles.id', $adminRole))->get();

        $remindedCount = 0;
        foreach ($expiringPartnerships as $partnership) {
            $daysLeft = $today->diffInDays($partnership->valid_until, false);

            foreach ($adminUsers as $admin) {
                Notification::create([
                    'user_id' => $admin->id,
                    'notif_type' => 'partnership_reminder',
                    'title' => "Masa Berlaku MoU Segera Berakhir: {$partnership->partner_name}",
                    'body' => "Perjanjian kerja sama (No: {$partnership->mou_number}) dengan {$partnership->partner_name} akan berakhir dalam {$daysLeft} hari (tanggal {$partnership->valid_until->toDateString()}).",
                    'data' => [
                        'partnership_id' => $partnership->id,
                        'days_remaining' => $daysLeft,
                        'deep_link' => '/partnerships',
                    ],
                ]);
            }
            $remindedCount++;
        }

        AuditLog::create([
            'action' => 'MOU_EXPIRY_CHECK_PROCESSED',
            'entity' => 'partnerships',
            'new_value' => [
                'marked_as_expired' => $expiredCount,
                'expiring_soon_count' => $remindedCount,
            ],
        ]);

        $this->info("Pengecekan MoU selesai: {$expiredCount} kedaluwarsa diperbarui, {$remindedCount} MoU mendekati jatuh tempo diingatkan.");

        return Command::SUCCESS;
    }
}
