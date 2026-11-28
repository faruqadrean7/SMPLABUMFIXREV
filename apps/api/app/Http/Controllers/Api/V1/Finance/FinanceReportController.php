<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceReportController extends Controller
{
    use ApiResponse;

    /**
     * Rekapitulasi tunggakan siswa (belum bayar & sebagian).
     */
    public function arrears(Request $request): JsonResponse
    {
        $classId = $request->input('class_id');

        $query = Invoice::whereIn('status', ['belum_bayar', 'sebagian'])
            ->with(['student.classes', 'items.feeType']);

        if ($classId) {
            $query->whereHas('student.classes', function ($q) use ($classId) {
                $q->where('classes.id', $classId);
            });
        }

        $invoices = $query->orderBy('due_date')->get();

        $totalInvoices = $invoices->count();
        $totalBilled = $invoices->sum('total_amount');
        $totalPaid = $invoices->sum('paid_amount');
        $totalArrears = max(0, $totalBilled - $totalPaid);

        // Rekap per kelas
        $classes = SchoolClass::withCount(['students'])->get();
        $classRecap = $classes->map(function ($cls) use ($invoices) {
            $classInvoices = $invoices->filter(function ($inv) use ($cls) {
                return $inv->student?->classes?->contains('id', $cls->id);
            });

            $billed = $classInvoices->sum('total_amount');
            $paid = $classInvoices->sum('paid_amount');
            $arrears = max(0, $billed - $paid);

            return [
                'class_id' => $cls->id,
                'class_name' => $cls->name,
                'unpaid_invoices_count' => $classInvoices->count(),
                'total_billed' => (int) $billed,
                'total_paid' => (int) $paid,
                'total_arrears' => (int) $arrears,
            ];
        })->filter(fn ($r) => $r['unpaid_invoices_count'] > 0)->values();

        return $this->successResponse([
            'summary' => [
                'total_unpaid_invoices' => $totalInvoices,
                'total_billed' => (int) $totalBilled,
                'total_paid' => (int) $totalPaid,
                'total_arrears' => (int) $totalArrears,
            ],
            'class_recap' => $classRecap,
            'invoices' => $invoices->map(fn ($inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'student' => [
                    'id' => $inv->student?->id,
                    'name' => $inv->student?->full_name,
                    'nis' => $inv->student?->nis,
                    'class_name' => $inv->student?->classes?->first()?->name ?? 'Belum ada kelas',
                ],
                'period' => $inv->period,
                'due_date' => $inv->due_date ? $inv->due_date->format('Y-m-d') : null,
                'total_amount' => (int) $inv->total_amount,
                'paid_amount' => (int) $inv->paid_amount,
                'remaining_amount' => (int) $inv->remaining_amount,
                'status' => $inv->status,
            ]),
        ]);
    }

    /**
     * Rekapitulasi penerimaan kas/pemasukan keuangan terverifikasi.
     * Mendukung ekspor format CSV serta kompatibilitas parameter `channel` / `payment_method`
     * dan `date_from`/`date_to` / `start_date`/`end_date` (P0-24).
     */
    public function income(Request $request): JsonResponse|StreamedResponse
    {
        $dateFrom = $request->input('date_from')
            ?? $request->input('start_date')
            ?? now()->startOfMonth()->toDateString();
        $dateTo = $request->input('date_to')
            ?? $request->input('end_date')
            ?? now()->toDateString();
        $channelFilter = $request->input('channel') ?? $request->input('payment_method');
        $format = $request->input('format');

        $query = Payment::where('status', 'terverifikasi')
            ->whereBetween('payment_date', [$dateFrom, $dateTo])
            ->with(['invoice.student.classes', 'invoice.items.feeType', 'verifiedBy']);

        if (is_string($channelFilter) && trim($channelFilter) !== '') {
            $needle = strtolower(trim($channelFilter));
            if (in_array($needle, ['tunai', 'cash'], true)) {
                $query->where(function ($q) {
                    $q->whereIlike('channel', '%tunai%')
                        ->orWhereIlike('channel', '%cash%');
                });
            } elseif ($needle === 'transfer') {
                $query->where(function ($q) {
                    $q->whereNull('channel')
                        ->orWhere(function ($sub) {
                            $sub->whereNotIlike('channel', '%tunai%')
                                ->whereNotIlike('channel', '%cash%');
                        });
                });
            } else {
                $query->whereIlike('channel', '%'.$needle.'%');
            }
        }

        $payments = $query->orderBy('payment_date', 'desc')->get();

        if ($format === 'csv' || $request->input('export') === 'csv') {
            $csvFileName = "Laporan_Pemasukan_{$dateFrom}_{$dateTo}.csv";

            return response()->streamDownload(function () use ($payments) {
                $handle = fopen('php://output', 'w');
                // UTF-8 BOM untuk kompatibilitas Excel
                fwrite($handle, "\xEF\xBB\xBF");
                fputcsv($handle, [
                    'No Kuitansi',
                    'No Invoice',
                    'Tanggal Bayar',
                    'NIS',
                    'Nama Siswa',
                    'Kelas',
                    'Kanal Pembayaran',
                    'Referensi Eksternal',
                    'Nominal (Rp)',
                    'Diverifikasi Oleh',
                    'Waktu Verifikasi',
                ]);

                $sanitizeCsv = static function (?string $value): string {
                    $text = (string) ($value ?? '');
                    if ($text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                        return "'".$text;
                    }

                    return $text;
                };

                foreach ($payments as $p) {
                    $inv = $p->invoice;
                    $st = $inv?->student;
                    fputcsv($handle, [
                        $sanitizeCsv($p->receipt_number),
                        $sanitizeCsv($inv?->invoice_number),
                        $p->payment_date ? $p->payment_date->format('Y-m-d') : '',
                        $sanitizeCsv($st?->nis ?? ''),
                        $sanitizeCsv($st?->full_name ?? ''),
                        $sanitizeCsv($st?->classes?->first()?->name ?? ''),
                        $sanitizeCsv($p->channel ?? 'Transfer Bank'),
                        $sanitizeCsv($p->external_reference ?? '-'),
                        $p->amount,
                        $sanitizeCsv($p->verifiedBy?->name ?? 'Bendahara'),
                        $p->verified_at ? $p->verified_at->toDateTimeString() : '',
                    ]);
                }
                fclose($handle);
            }, $csvFileName, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$csvFileName}\"",
            ]);
        }

        $totalIncome = 0;
        $cashTotal = 0;
        $transferTotal = 0;
        $byChannel = [];
        $byCategory = [];
        $dailyMap = [];

        foreach ($payments as $p) {
            $amount = (int) $p->amount;
            $totalIncome += $amount;

            $channelLabel = trim((string) ($p->channel ?? '')) !== '' ? (string) $p->channel : 'Transfer Bank';
            $channelLower = strtolower($channelLabel);

            if (str_contains($channelLower, 'tunai') || str_contains($channelLower, 'cash')) {
                $cashTotal += $amount;
            } else {
                $transferTotal += $amount;
            }

            $byChannel[$channelLabel] = ($byChannel[$channelLabel] ?? 0) + $amount;

            // Alokasi ke kategori pos biaya (proporsional jika invoice memiliki >1 pos biaya)
            $items = $p->invoice?->items ?? collect();
            if ($items->isEmpty()) {
                $byCategory['lainnya'] = ($byCategory['lainnya'] ?? 0) + $amount;
            } else {
                $netTotal = $items->sum(fn ($it) => max(0, (int) $it->amount - (int) ($it->discount ?? 0)));
                if ($netTotal <= 0) {
                    $firstCat = $items->first()?->feeType?->category ?? 'lainnya';
                    $byCategory[$firstCat] = ($byCategory[$firstCat] ?? 0) + $amount;
                } else {
                    $allocated = 0;
                    $lastIdx = $items->count() - 1;
                    foreach ($items->values() as $idx => $item) {
                        $cat = $item->feeType?->category ?? 'lainnya';
                        $itemNet = max(0, (int) $item->amount - (int) ($item->discount ?? 0));
                        $share = ($idx === $lastIdx)
                            ? ($amount - $allocated)
                            : (int) round($amount * ($itemNet / $netTotal));
                        $allocated += $share;
                        $byCategory[$cat] = ($byCategory[$cat] ?? 0) + $share;
                    }
                }
            }

            $dateKey = $p->payment_date ? $p->payment_date->format('Y-m-d') : 'unknown';
            if (! isset($dailyMap[$dateKey])) {
                $dailyMap[$dateKey] = ['date' => $dateKey, 'total' => 0, 'count' => 0];
            }
            $dailyMap[$dateKey]['total'] += $amount;
            $dailyMap[$dateKey]['count']++;
        }

        krsort($dailyMap);
        $daily = array_values($dailyMap);

        return $this->successResponse([
            // Kompatibel langsung dengan IncomeReportSummary FE (/admin/finance/reports)
            'period_start' => $dateFrom,
            'period_end' => $dateTo,
            'total_income' => (int) $totalIncome,
            'payment_count' => $payments->count(),
            'by_method' => $byChannel,
            'by_channel' => $byChannel,
            'by_category' => $byCategory,
            'daily' => $daily,
            // Kompatibel penuh dengan struktur summary & payments eksisting
            'summary' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'total_payments_count' => $payments->count(),
                'total_income' => (int) $totalIncome,
                'cash_income' => (int) $cashTotal,
                'transfer_income' => (int) $transferTotal,
                'by_channel' => $byChannel,
            ],
            'payments' => $payments->map(fn ($p) => [
                'id' => $p->id,
                'receipt_number' => $p->receipt_number,
                'invoice_number' => $p->invoice?->invoice_number,
                'student_name' => $p->invoice?->student?->full_name,
                'nis' => $p->invoice?->student?->nis,
                'class_name' => $p->invoice?->student?->classes?->first()?->name ?? 'Belum ada kelas',
                'amount' => (int) $p->amount,
                'payment_method' => $p->channel ?? 'Transfer Bank',
                'channel' => $p->channel ?? 'Transfer Bank',
                'bank_name' => $p->channel ?? 'Transfer Bank',
                'payment_date' => $p->payment_date ? $p->payment_date->format('Y-m-d') : null,
                'verified_at' => $p->verified_at ? $p->verified_at->toIso8601String() : null,
                'verified_by' => $p->verifiedBy?->name ?? 'Bendahara',
            ]),
        ]);
    }
}
