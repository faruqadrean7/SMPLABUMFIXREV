<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendInvoiceDueRemindersCommand extends Command
{
    protected $signature = 'finance:send-due-reminders {--days=3 : Jumlah hari sebelum jatuh tempo}';

    protected $description = 'Kirim notifikasi pengingat tagihan keuangan yang mendekati jatuh tempo (default H-3)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $targetDate = Carbon::today()->addDays($days)->toDateString();

        $this->info("Memeriksa tagihan aktif dengan jatuh tempo: {$targetDate} (H-{$days})...");

        $invoices = Invoice::whereIn('status', ['belum_bayar', 'sebagian'])
            ->whereDate('due_date', $targetDate)
            ->with(['student.user', 'student.guardians.user'])
            ->get();

        $notifiedCount = 0;

        foreach ($invoices as $invoice) {
            $student = $invoice->student;
            if (! $student) {
                continue;
            }

            $remainingFormatted = number_format($invoice->remaining_amount, 0, ',', '.');
            $title = "Pengingat Jatuh Tempo Tagihan (H-{$days})";
            $body = "Tagihan {$invoice->invoice_number} untuk {$student->full_name} dengan sisa Rp {$remainingFormatted} akan jatuh tempo pada {$invoice->due_date->format('Y-m-d')}. Harap segera menyelesaikan pembayaran.";

            // Siswa
            if ($student->user) {
                Notification::create([
                    'user_id' => $student->user->id,
                    'notif_type' => 'finance_reminder',
                    'title' => $title,
                    'body' => $body,
                    'data' => [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'due_date' => $invoice->due_date->format('Y-m-d'),
                        'remaining_amount' => $invoice->remaining_amount,
                    ],
                ]);
                $notifiedCount++;
            }

            // Wali
            foreach ($student->guardians as $guardian) {
                if ($guardian->user) {
                    Notification::create([
                        'user_id' => $guardian->user->id,
                        'notif_type' => 'finance_reminder',
                        'title' => $title,
                        'body' => $body,
                        'data' => [
                            'invoice_id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'student_id' => $student->id,
                            'due_date' => $invoice->due_date->format('Y-m-d'),
                            'remaining_amount' => $invoice->remaining_amount,
                        ],
                    ]);
                    $notifiedCount++;
                }
            }
        }

        $this->info("Pengingat berhasil dikirim ke {$notifiedCount} penerima dari {$invoices->count()} tagihan.");

        return Command::SUCCESS;
    }
}
