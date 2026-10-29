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
     * Mendukung ekspor format CSV.
     */
    public function income(Request $request): JsonResponse|StreamedResponse
    {
        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());
        $paymentMethod = $request->input('payment_method');
        $format = $request->input('format');

        $query = Payment::where('status', 'terverifikasi')
            ->whereBetween('payment_date', [$dateFrom, $dateTo])
            ->with(['invoice.student.classes', 'verifiedBy']);

        if ($paymentMethod) {
            $query->where('payment_method', $paymentMethod);
        }

        $payments = $query->orderBy('payment_date', 'desc')->get();

        if ($format === 'csv' || $request->input('export') === 'csv') {
            $csvFileName = "Laporan_Pemasukan_{$dateFrom}_{$dateTo}.csv";

            return response()->streamDownload(function () use ($payments) {
                $handle = fopen('php://output', 'w');
                // UTF-8 BOM untuk kompatibilitas Excel
                fputs($handle, "\xEF\xBB\xBF");
                fputcsv($handle, [
                    'No Kuitansi',
                    'No Invoice',
                    'Tanggal Bayar',
                    'NIS',
                    'Nama Siswa',
                    'Kelas',
                    'Metode',
                    'Bank Tujuan',
                    'Nominal (Rp)',
                    'Diverifikasi Oleh',
                    'Waktu Verifikasi',
                ]);

                foreach ($payments as $p) {
                    $inv = $p->invoice;
                    $st = $inv?->student;
                    fputcsv($handle, [
                        $p->receipt_number,
                        $inv?->invoice_number,
                        $p->payment_date ? $p->payment_date->format('Y-m-d') : '',
                        $st?->nis ?? '',
                        $st?->full_name ?? '',
                        $st?->classes?->first()?->name ?? '',
                        ucfirst($p->payment_method ?? 'transfer'),
                        $p->channel ?? '-',
                        $p->amount,
                        $p->verifiedBy?->name ?? 'Bendahara',
                        $p->verified_at ? $p->verified_at->toDateTimeString() : '',
                    ]);
                }
                fclose($handle);
            }, $csvFileName, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$csvFileName}\"",
            ]);
        }

        $totalIncome = $payments->sum('amount');
        $cashTotal = $payments->where('payment_method', 'tunai')->sum('amount');
        $transferTotal = $payments->where('payment_method', 'transfer')->sum('amount');

        return $this->successResponse([
            'summary' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'total_payments_count' => $payments->count(),
                'total_income' => (int) $totalIncome,
                'cash_income' => (int) $cashTotal,
                'transfer_income' => (int) $transferTotal,
            ],
            'payments' => $payments->map(fn ($p) => [
                'id' => $p->id,
                'receipt_number' => $p->receipt_number,
                'invoice_number' => $p->invoice?->invoice_number,
                'student_name' => $p->invoice?->student?->full_name,
                'nis' => $p->invoice?->student?->nis,
                'class_name' => $p->invoice?->student?->classes?->first()?->name ?? 'Belum ada kelas',
                'amount' => (int) $p->amount,
                'payment_method' => $p->payment_method,
                'channel' => $p->channel,
                'bank_name' => $p->channel,
                'payment_date' => $p->payment_date ? $p->payment_date->format('Y-m-d') : null,
                'verified_at' => $p->verified_at ? $p->verified_at->toIso8601String() : null,
                'verified_by' => $p->verifiedBy?->name ?? 'Bendahara',
            ]),
        ]);
    }
}
