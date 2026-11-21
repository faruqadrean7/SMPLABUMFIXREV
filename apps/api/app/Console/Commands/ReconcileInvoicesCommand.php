<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileInvoicesCommand extends Command
{
    /**
     * Signature command: finance:reconcile
     */
    protected $signature = 'finance:reconcile
                            {--invoice= : Rekonsiliasi invoice tertentu berdasarkan UUID}
                            {--dry-run : Tampilkan ketidaksesuaian tanpa melakukan pembaruan ke database}';

    /**
     * Deskripsi command
     */
    protected $description = 'Rekonsiliasi akumulasi nominal terbayar (paid_amount) dan status tagihan siswa berbasis riwayat pembayaran terverifikasi secara idempoten';

    public function handle(): int
    {
        $invoiceId = $this->option('invoice');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Mode DRY-RUN aktif: tidak ada perubahan data yang disimpan.');
        }

        $query = Invoice::query()->with('payments');

        if ($invoiceId) {
            $query->where('id', $invoiceId);
        }

        $totalChecked = 0;
        $reconciledCount = 0;
        $cleanCount = 0;

        $query->chunk(100, function ($invoices) use ($dryRun, &$totalChecked, &$reconciledCount, &$cleanCount) {
            foreach ($invoices as $invoice) {
                $totalChecked++;

                $verifiedSum = (int) $invoice->payments()
                    ->where('status', 'terverifikasi')
                    ->sum('amount');

                $expectedStatus = $invoice->status;
                if ($invoice->status !== 'dibatalkan') {
                    if ($verifiedSum >= $invoice->total_amount && $invoice->total_amount > 0) {
                        $expectedStatus = 'lunas';
                    } elseif ($verifiedSum > 0) {
                        $expectedStatus = 'sebagian';
                    } else {
                        $expectedStatus = 'belum_bayar';
                    }
                }

                $hasDrift = ((int) $invoice->paid_amount !== $verifiedSum) || ($invoice->status !== $expectedStatus);

                if ($hasDrift) {
                    $reconciledCount++;
                    $this->line(sprintf(
                        '<fg=yellow>[DRIFT DETECTED]</> Invoice #%s: paid_amount %s -> %s, status %s -> %s',
                        $invoice->invoice_number,
                        number_format((int) $invoice->paid_amount, 0, ',', '.'),
                        number_format($verifiedSum, 0, ',', '.'),
                        $invoice->status,
                        $expectedStatus
                    ));

                    if (! $dryRun) {
                        DB::transaction(function () use ($invoice, $verifiedSum, $expectedStatus) {
                            $invoice->update([
                                'paid_amount' => $verifiedSum,
                                'status' => $expectedStatus,
                            ]);
                        });
                    }
                } else {
                    $cleanCount++;
                }
            }
        });

        $this->newLine();
        $this->info("Rekonsiliasi selesai: {$totalChecked} tagihan diperiksa.");
        $this->line(" - Sesuai (bersih): {$cleanCount}");
        $this->line(' - Drift ditemukan & '.($dryRun ? 'dilaporkan' : 'diperbaiki').': '.$reconciledCount);

        return Command::SUCCESS;
    }
}
