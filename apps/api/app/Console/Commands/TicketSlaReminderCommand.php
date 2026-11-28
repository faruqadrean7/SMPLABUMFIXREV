<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\ReminderDispatch;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
        $todayKey = $now->toDateString();
        $unresolvedTickets = Ticket::whereIn('status', ['pending', 'diajukan', 'in_progress', 'diproses'])
            ->with(['reporter', 'assignee'])
            ->get();

        $facilityRoleIds = Role::whereIn('code', ['facility_staff', 'sarpras', 'super_admin'])->pluck('id');
        $facilityStaffUsers = User::where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->whereIn('roles.id', $facilityRoleIds))
            ->get();

        $breachedCount = 0;

        foreach ($unresolvedTickets as $ticket) {
            $allowedHours = self::SLA_HOURS[$ticket->priority] ?? 48;
            $deadline = $ticket->created_at->copy()->addHours($allowedHours);

            if ($now->greaterThan($deadline)) {
                $hoursOverdue = (int) $deadline->diffInHours($now);
                $breachedCount++;

                // Penerima: assignee jika ada, atau staf sarpras di sekolah terkait jika belum ditugaskan
                if ($ticket->assignee) {
                    $recipients = collect([$ticket->assignee]);
                } else {
                    $recipients = ! empty($ticket->school_id)
                        ? $facilityStaffUsers->filter(fn ($u) => empty($u->school_id) || $u->school_id === $ticket->school_id)
                        : $facilityStaffUsers;
                }

                $milestone = "sla_{$todayKey}";
                foreach ($recipients as $recipient) {
                    $alreadyNotified = ReminderDispatch::where('event_type', 'ticket_sla_breach')
                        ->where('event_id', $ticket->id)
                        ->where('recipient_id', $recipient->id)
                        ->where('milestone', $milestone)
                        ->exists();

                    if ($alreadyNotified) {
                        continue;
                    }

                    try {
                        ReminderDispatch::create([
                            'school_id' => $ticket->school_id,
                            'event_type' => 'ticket_sla_breach',
                            'event_id' => $ticket->id,
                            'recipient_id' => $recipient->id,
                            'milestone' => $milestone,
                            'channel' => 'in_app',
                            'status' => 'dispatched',
                            'dispatched_at' => $now,
                        ]);

                        Notification::create([
                            'user_id' => $recipient->id,
                            'notif_type' => 'ticket_sla_breach',
                            'title' => "Peringatan SLA Tiket Sarpras: {$ticket->ticket_number}",
                            'body' => "Tiket [{$ticket->ticket_number}] prioritas {$ticket->priority} ('{$ticket->title}') telah melewati batas SLA selama {$hoursOverdue} jam.",
                            'data' => [
                                'ticket_id' => $ticket->id,
                                'priority' => $ticket->priority,
                                'hours_overdue' => $hoursOverdue,
                                'target_url' => '/admin/tickets',
                            ],
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning("Skip duplicate SLA ticket dispatch {$ticket->id}/{$recipient->id}: {$e->getMessage()}");
                    }
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
