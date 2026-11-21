<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TicketSlaReminderCommand extends Command
{
    protected $signature = 'sarpras:ticket-sla-reminder';

    protected $description = 'Ingatkan tiket sarana dan prasarana pending yang melewati batas waktu SLA';

    /**
     * Batas jam SLA per prioritas tiket.
     */
    protected const SLA_HOURS = [
        'darurat' => 4,
        'tinggi' => 24,
        'sedang' => 48,
        'rendah' => 72,
    ];

    public function handle(): int
    {
        $now = Carbon::now();
        $unresolvedTickets = Ticket::whereIn('status', ['pending', 'diajukan', 'in_progress'])
            ->with(['reporter', 'assignee'])
            ->get();

        $facilityRole = Role::whereIn('code', ['facility_staff', 'super_admin'])->pluck('id');
        $facilityStaffUsers = User::whereHas('roles', fn ($q) => $q->whereIn('roles.id', $facilityRole))->get();

        $breachedCount = 0;

        foreach ($unresolvedTickets as $ticket) {
            $allowedHours = self::SLA_HOURS[$ticket->priority] ?? 48;
            $deadline = $ticket->created_at->copy()->addHours($allowedHours);

            if ($now->greaterThan($deadline)) {
                $hoursOverdue = (int) $deadline->diffInHours($now);
                $breachedCount++;

                // Penerima: assignee jika ada, atau seluruh staf sarpras jika belum ditugaskan
                $recipients = $ticket->assignee ? collect([$ticket->assignee]) : $facilityStaffUsers;

                foreach ($recipients as $recipient) {
                    Notification::create([
                        'user_id' => $recipient->id,
                        'notif_type' => 'ticket_sla_breach',
                        'title' => "Peringatan SLA Tiket Sarpras: {$ticket->ticket_number}",
                        'body' => "Tiket [{$ticket->ticket_number}] priority: {$ticket->priority} ('{$ticket->title}') telah melewati batas SLA selama {$hoursOverdue} jam.",
                        'data' => [
                            'ticket_id' => $ticket->id,
                            'priority' => $ticket->priority,
                            'hours_overdue' => $hoursOverdue,
                            'deep_link' => '/facility/tickets',
                        ],
                    ]);
                }
            }
        }

        AuditLog::create([
            'action' => 'TICKET_SLA_CHECK_PROCESSED',
            'entity' => 'tickets',
            'new_value' => [
                'total_checked' => $unresolvedTickets->count(),
                'sla_breached_count' => $breachedCount,
            ],
        ]);

        $this->info("Pemeriksaan SLA tiket selesai: {$breachedCount} tiket melewati SLA teridentifikasi dan diingatkan.");

        return Command::SUCCESS;
    }
}
