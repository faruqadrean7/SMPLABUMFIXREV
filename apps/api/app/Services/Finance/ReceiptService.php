<?php

namespace App\Services\Finance;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class ReceiptService
{
    /**
     * Konversi bilangan bulat nominal rupiah menjadi teks terbilang bahasa Indonesia.
     * Contoh: 1500000 => "Satu Juta Lima Ratus Ribu Rupiah"
     */
    public function terbilang(int $nominal): string
    {
        if ($nominal <= 0) {
            return 'Nol Rupiah';
        }

        return trim(preg_replace('/\s+/', ' ', $this->convertNumberToWords($nominal))).' Rupiah';
    }

    protected function convertNumberToWords(int $nominal): string
    {
        if ($nominal <= 0) {
            return '';
        }

        $angka = [
            '', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima',
            'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas',
        ];

        if ($nominal < 12) {
            return ' '.$angka[$nominal];
        } elseif ($nominal < 20) {
            return $this->convertNumberToWords($nominal - 10).' Belas';
        } elseif ($nominal < 100) {
            return $this->convertNumberToWords((int) ($nominal / 10)).' Puluh'.$this->convertNumberToWords($nominal % 10);
        } elseif ($nominal < 200) {
            return ' Seratus'.$this->convertNumberToWords($nominal - 100);
        } elseif ($nominal < 1000) {
            return $this->convertNumberToWords((int) ($nominal / 100)).' Ratus'.$this->convertNumberToWords($nominal % 100);
        } elseif ($nominal < 2000) {
            return ' Seribu'.$this->convertNumberToWords($nominal - 1000);
        } elseif ($nominal < 1000000) {
            return $this->convertNumberToWords((int) ($nominal / 1000)).' Ribu'.$this->convertNumberToWords($nominal % 1000);
        } elseif ($nominal < 1000000000) {
            return $this->convertNumberToWords((int) ($nominal / 1000000)).' Juta'.$this->convertNumberToWords($nominal % 1000000);
        } elseif ($nominal < 1000000000000) {
            return $this->convertNumberToWords((int) ($nominal / 1000000000)).' Miliar'.$this->convertNumberToWords($nominal % 1000000000);
        } else {
            return $this->convertNumberToWords((int) ($nominal / 1000000000000)).' Triliun'.$this->convertNumberToWords($nominal % 1000000000000);
        }
    }

    /**
     * Hasilkan nomor kuitansi unik: KWT/{YYYY}/{MM}/{SEQUENCE}
     * P0-25: Gunakan advisory transaction lock PostgreSQL & ekstraksi sequence numerik aman (tanpa tabrakan).
     */
    public function generateReceiptNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        $prefix = "KWT/{$year}/{$month}/";

        if (DB::getDriverName() === 'pgsql') {
            DB::selectOne('SELECT pg_advisory_xact_lock(?)', [crc32('finance_receipt_seq:'.$prefix)]);

            $row = DB::selectOne(
                "SELECT MAX(CAST(SPLIT_PART(receipt_number, '/', 4) AS INTEGER)) AS max_seq
                 FROM payments
                 WHERE receipt_number LIKE ? AND SPLIT_PART(receipt_number, '/', 4) ~ '^[0-9]+$'",
                [$prefix.'%']
            );
            $maxSeq = (int) ($row->max_seq ?? 0);
        } else {
            $lastReceipt = DB::table('payments')
                ->where('receipt_number', 'like', $prefix.'%')
                ->orderBy('receipt_number', 'desc')
                ->value('receipt_number');
            $parts = $lastReceipt ? explode('/', $lastReceipt) : [];
            $maxSeq = ! empty($parts) && ctype_digit((string) end($parts)) ? (int) end($parts) : 0;
        }

        $seq = $maxSeq + 1;
        do {
            $candidate = $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            $exists = DB::table('payments')->where('receipt_number', $candidate)->exists();
            if (! $exists) {
                return $candidate;
            }
            $seq++;
        } while ($exists);

        return $candidate;
    }

    /**
     * Bangun data terstruktur kuitansi resmi pembayaran tagihan.
     */
    public function buildReceiptData(Payment $payment): array
    {
        $payment->loadMissing([
            'invoice.student.classes',
            'invoice.items.feeType',
            'verifiedBy',
            'submittedBy',
        ]);

        $invoice = $payment->invoice;
        $student = $invoice?->student;
        $currentClass = $student?->classes?->first();

        $itemsBreakdown = $invoice?->items->map(function ($item) {
            return [
                'fee_code' => $item->feeType?->code,
                'fee_name' => $item->feeType?->name,
                'description' => $item->description,
                'amount' => $item->amount,
                'discount' => $item->discount,
                'net_amount' => $item->net_amount,
            ];
        })->toArray() ?? [];

        return [
            'receipt_number' => $payment->receipt_number,
            'status' => $payment->status,
            'payment_date' => $payment->payment_date ? $payment->payment_date->format('Y-m-d') : null,
            'verified_at' => $payment->verified_at ? $payment->verified_at->toIso8601String() : null,
            'amount' => (int) $payment->amount,
            'amount_words' => $this->terbilang((int) $payment->amount),
            'payment_method' => $payment->channel ?? 'Transfer Bank',
            'channel' => $payment->channel ?? 'Transfer Bank',
            'external_reference' => $payment->external_reference,
            'student' => [
                'id' => $student?->id,
                'name' => $student?->full_name,
                'nis' => $student?->nis,
                'nisn' => $student?->nisn,
                'class_name' => $currentClass?->name ?? 'Belum ada kelas',
            ],
            'invoice' => [
                'id' => $invoice?->id,
                'invoice_number' => $invoice?->invoice_number,
                'period' => $invoice?->period,
                'total_amount' => $invoice?->total_amount,
                'paid_amount' => $invoice?->paid_amount,
                'remaining_amount' => $invoice?->remaining_amount,
                'status' => $invoice?->status,
                'items' => $itemsBreakdown,
            ],
            'verifier' => [
                'id' => $payment->verifiedBy?->id,
                'name' => $payment->verifiedBy?->name ?? 'Bendahara Sekolah',
            ],
            'school_name' => 'SMP / SMA Laboratorium Universitas Negeri Malang',
            'issued_at' => now()->toIso8601String(),
        ];
    }
}
