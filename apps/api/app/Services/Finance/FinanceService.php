<?php

namespace App\Services\Finance;

use App\Models\FeeType;
use App\Models\File;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinanceService
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected InvoiceGeneratorService $invoiceGeneratorService,
        protected ReceiptService $receiptService
    ) {}

    /**
     * Buat tagihan tunggal untuk seorang siswa.
     */
    public function createInvoice(array $data, User $actor): Invoice
    {
        $student = Student::with(['user', 'guardians.user'])->findOrFail($data['student_id']);

        return DB::transaction(function () use ($data, $student, $actor) {
            $invoiceNumber = $this->invoiceGeneratorService->generateInvoiceNumber();

            $itemsData = $data['items'] ?? [];
            if (empty($itemsData)) {
                throw ValidationException::withMessages([
                    'items' => ['Tagihan wajib memiliki minimal satu rincian item biaya.'],
                ]);
            }

            $totalAmount = 0;
            foreach ($itemsData as $item) {
                $itemAmount = (int) ($item['amount'] ?? 0);
                $itemDiscount = (int) ($item['discount'] ?? 0);
                $totalAmount += max(0, $itemAmount - $itemDiscount);
            }

            $invoice = Invoice::create([
                'school_id' => $student->school_id,
                'invoice_number' => $invoiceNumber,
                'student_id' => $student->id,
                'academic_year_id' => $data['academic_year_id'] ?? null,
                'period' => $data['period'] ?? null,
                'due_date' => $data['due_date'],
                'total_amount' => $totalAmount,
                'paid_amount' => 0,
                'status' => 'belum_bayar',
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($itemsData as $item) {
                $feeType = FeeType::findOrFail($item['fee_type_id']);
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'fee_type_id' => $feeType->id,
                    'description' => $item['description'] ?? $feeType->name,
                    'amount' => (int) $item['amount'],
                    'discount' => (int) ($item['discount'] ?? 0),
                ]);
            }

            $this->auditLogService->record(
                action: 'finance.invoice.create',
                entity: 'Invoice',
                entityId: $invoice->id,
                oldValues: null,
                newValues: [
                    'invoice_number' => $invoice->invoice_number,
                    'student_id' => $student->id,
                    'total_amount' => $totalAmount,
                ],
                reason: 'Penerbitan tagihan manual',
                actor: $actor
            );

            // Notifikasi ke siswa & ortu
            if ($student->user) {
                Notification::create([
                    'user_id' => $student->user->id,
                    'notif_type' => 'finance_invoice',
                    'title' => 'Tagihan Baru: '.$invoice->invoice_number,
                    'body' => 'Tagihan sebesar Rp '.number_format($totalAmount, 0, ',', '.')." telah diterbitkan. Jatuh tempo: {$invoice->due_date->format('Y-m-d')}.",
                    'data' => [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'total_amount' => $totalAmount,
                    ],
                ]);
            }

            foreach ($student->guardians as $guardian) {
                if ($guardian->user) {
                    Notification::create([
                        'user_id' => $guardian->user->id,
                        'notif_type' => 'finance_invoice',
                        'title' => 'Tagihan Baru untuk '.$student->full_name,
                        'body' => 'Tagihan sebesar Rp '.number_format($totalAmount, 0, ',', '.')." untuk {$student->full_name} telah diterbitkan. Jatuh tempo: {$invoice->due_date->format('Y-m-d')}.",
                        'data' => [
                            'invoice_id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'student_id' => $student->id,
                            'total_amount' => $totalAmount,
                        ],
                    ]);
                }
            }

            return $invoice->load(['items.feeType', 'student']);
        });
    }

    /**
     * Batalkan tagihan.
     */
    public function cancelInvoice(Invoice $invoice, string $reason, User $actor): Invoice
    {
        if ($invoice->payments()->where('status', 'terverifikasi')->exists()) {
            throw ValidationException::withMessages([
                'status' => ['Tagihan yang memiliki pembayaran terverifikasi tidak dapat dibatalkan secara langsung. Void terlebih dahulu pembayaran terkait.'],
            ]);
        }

        $oldStatus = $invoice->status;
        $invoice->update([
            'status' => 'dibatalkan',
            'notes' => trim(($invoice->notes ?? '')." [Dibatalkan oleh {$actor->name}: {$reason}]"),
        ]);

        $this->auditLogService->record(
            action: 'finance.invoice.cancel',
            entity: 'Invoice',
            entityId: $invoice->id,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'dibatalkan'],
            reason: $reason,
            actor: $actor
        );

        return $invoice->fresh();
    }

    /**
     * Ajukan / catat pembayaran tagihan (oleh Ortu, Siswa, atau Admin).
     */
    public function submitPayment(Invoice $invoice, array $data, ?UploadedFile $proofFile, User $actor): Payment
    {
        if (in_array($invoice->status, ['lunas', 'dibatalkan'])) {
            throw ValidationException::withMessages([
                'invoice' => ["Tagihan berstatus '{$invoice->status}' tidak dapat menerima pembayaran baru."],
            ]);
        }

        $amount = (int) $data['amount'];
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Nominal pembayaran harus lebih besar dari 0.'],
            ]);
        }

        $remaining = $invoice->remaining_amount;
        if ($amount > $remaining) {
            throw ValidationException::withMessages([
                'amount' => [
                    'Jumlah pembayaran (Rp '.number_format($amount, 0, ',', '.').') melebihi sisa tagihan (Rp '.number_format($remaining, 0, ',', '.').').',
                ],
            ]);
        }

        // REVISI BATCH 3B: external_reference wajib & unik untuk pembayaran aktif
        $extRef = trim($data['external_reference'] ?? '');
        if (empty($extRef)) {
            throw ValidationException::withMessages([
                'external_reference' => ['Nomor referensi transaksi pihak ketiga wajib diisi.'],
            ]);
        }

        $duplicateExists = Payment::where('external_reference', $extRef)
            ->whereIn('status', ['menunggu_verifikasi', 'terverifikasi'])
            ->exists();

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'external_reference' => ['Nomor referensi transaksi ini sudah pernah diajukan dan sedang dalam antrean verifikasi atau sudah terverifikasi.'],
            ]);
        }

        // Berkas bukti slip pembayaran WAJIB
        if (! $proofFile) {
            throw ValidationException::withMessages([
                'proof_file' => ['Berkas bukti/slip pembayaran transfer wajib dilampirkan.'],
            ]);
        }

        return DB::transaction(function () use ($invoice, $data, $proofFile, $actor, $amount, $extRef) {
            // Validasi ukuran maks 2MB
            if ($proofFile->getSize() > 2 * 1024 * 1024) {
                throw ValidationException::withMessages([
                    'proof_file' => ['Ukuran berkas bukti pembayaran maksimal 2 MB.'],
                ]);
            }

            $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
            $clientMime = $proofFile->getMimeType();
            if (! in_array($clientMime, $allowedMimes)) {
                throw ValidationException::withMessages([
                    'proof_file' => ['Format berkas bukti tidak didukung. Harap unggah PDF, JPG, PNG, atau WEBP.'],
                ]);
            }

            $path = $proofFile->store("finance/payment_proofs/{$invoice->id}", 'local');
            $checksum = hash_file('sha256', $proofFile->getRealPath());

            $fileRecord = File::create([
                'school_id' => $invoice->school_id,
                'owner_id' => $invoice->student_id,
                'owner_type' => 'Student',
                'bucket' => 'payment-proofs',
                'object_key' => $path,
                'mime_type' => $clientMime ?: 'application/octet-stream',
                'size_bytes' => $proofFile->getSize(),
                'visibility' => 'privat',
                'checksum' => $checksum,
            ]);

            $channel = trim($data['channel'] ?? 'Transfer Bank');

            $payment = Payment::create([
                'school_id' => $invoice->school_id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'channel' => $channel,
                'external_reference' => $extRef,
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'proof_file_id' => $fileRecord->id,
                'status' => 'menunggu_verifikasi',
                'submitted_by' => $actor->id,
            ]);

            $this->auditLogService->record(
                action: 'finance.payment.submit',
                entity: 'Payment',
                entityId: $payment->id,
                oldValues: null,
                newValues: [
                    'invoice_id' => $invoice->id,
                    'amount' => $amount,
                    'channel' => $channel,
                    'external_reference' => $extRef,
                    'status' => 'menunggu_verifikasi',
                ],
                reason: 'Pengajuan slip pembayaran pihak ketiga',
                actor: $actor
            );

            return $payment->load(['proofFile', 'submittedBy']);
        });
    }

    /**
     * Verifikasi pembayaran oleh bendahara / admin (menerbitkan kuitansi).
     */
    public function verifyPayment(Payment $payment, ?string $notes, User $verifier): Payment
    {
        if ($payment->status === 'terverifikasi') {
            throw ValidationException::withMessages([
                'payment' => ['Pembayaran ini sudah terverifikasi sebelumnya.'],
            ]);
        }

        if ($payment->status === 'void') {
            throw ValidationException::withMessages([
                'payment' => ['Pembayaran yang telah di-void tidak dapat diverifikasi kembali.'],
            ]);
        }

        return DB::transaction(function () use ($payment, $notes, $verifier) {
            $invoice = $payment->invoice;

            // Validasi overpayment terhadap sisa tagihan saat verifikasi
            $currentVerifiedSum = (int) $invoice->payments()
                ->where('status', 'terverifikasi')
                ->where('id', '!=', $payment->id)
                ->sum('amount');

            if (($currentVerifiedSum + $payment->amount) > $invoice->total_amount) {
                throw ValidationException::withMessages([
                    'amount' => ['Verifikasi pembayaran ini akan mengakibatkan total pembayaran melebihi nilai tagihan.'],
                ]);
            }

            // Terbitkan nomor kuitansi resmi jika belum ada
            $receiptNumber = $payment->receipt_number ?: $this->receiptService->generateReceiptNumber();

            $oldStatus = $payment->status;
            $payment->update([
                'status' => 'terverifikasi',
                'verified_by' => $verifier->id,
                'verified_at' => now(),
                'receipt_number' => $receiptNumber,
                'rejection_reason' => null,
            ]);

            // Hitung ulang akumulasi status invoice
            $invoice->recalculatePaidAmountAndStatus();

            $this->auditLogService->record(
                action: 'finance.payment.verify',
                entity: 'Payment',
                entityId: $payment->id,
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status' => 'terverifikasi',
                    'receipt_number' => $receiptNumber,
                    'verified_by' => $verifier->id,
                ],
                reason: $notes ?? 'Verifikasi pembayaran bendahara',
                actor: $verifier
            );

            // Kirim notifikasi ke pengaju pembayaran & siswa/ortu
            $student = $invoice->student;
            $nominalFormatted = number_format($payment->amount, 0, ',', '.');

            $notifTitle = "Pembayaran Terverifikasi: {$receiptNumber}";
            $notifBody = "Pembayaran sebesar Rp {$nominalFormatted} untuk tagihan {$invoice->invoice_number} telah diverifikasi. Kuitansi resmi telah terbit.";

            if ($payment->submittedBy) {
                Notification::create([
                    'user_id' => $payment->submittedBy->id,
                    'notif_type' => 'finance_payment',
                    'title' => $notifTitle,
                    'body' => $notifBody,
                    'data' => [
                        'payment_id' => $payment->id,
                        'invoice_id' => $invoice->id,
                        'receipt_number' => $receiptNumber,
                    ],
                ]);
            }

            if ($student?->user && $student->user->id !== $payment->submitted_by) {
                Notification::create([
                    'user_id' => $student->user->id,
                    'notif_type' => 'finance_payment',
                    'title' => $notifTitle,
                    'body' => $notifBody,
                    'data' => [
                        'payment_id' => $payment->id,
                        'invoice_id' => $invoice->id,
                        'receipt_number' => $receiptNumber,
                    ],
                ]);
            }

            return $payment->fresh(['invoice.student', 'verifiedBy', 'proofFile']);
        });
    }

    /**
     * Tolak pembayaran yang menunggu verifikasi (dengan alasan wajib).
     */
    public function rejectPayment(Payment $payment, string $reason, User $verifier): Payment
    {
        if ($payment->status !== 'menunggu_verifikasi') {
            throw ValidationException::withMessages([
                'payment' => ["Hanya pembayaran berstatus 'menunggu_verifikasi' yang dapat ditolak."],
            ]);
        }

        return DB::transaction(function () use ($payment, $reason, $verifier) {
            $oldStatus = $payment->status;
            $payment->update([
                'status' => 'ditolak',
                'verified_by' => $verifier->id,
                'verified_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $payment->invoice->recalculatePaidAmountAndStatus();

            $this->auditLogService->record(
                action: 'finance.payment.reject',
                entity: 'Payment',
                entityId: $payment->id,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => 'ditolak', 'rejection_reason' => $reason],
                reason: $reason,
                actor: $verifier
            );

            // Notifikasi penolakan
            if ($payment->submittedBy) {
                Notification::create([
                    'user_id' => $payment->submittedBy->id,
                    'notif_type' => 'finance_payment',
                    'title' => "Pembayaran Ditolak: Tagihan {$payment->invoice?->invoice_number}",
                    'body' => 'Pengajuan pembayaran sebesar Rp '.number_format($payment->amount, 0, ',', '.')." ditolak oleh bendahara. Alasan: {$reason}",
                    'data' => [
                        'payment_id' => $payment->id,
                        'invoice_id' => $payment->invoice_id,
                        'reason' => $reason,
                    ],
                ]);
            }

            return $payment->fresh();
        });
    }

    /**
     * Batalkan pembayaran yang telah terverifikasi (Void dengan alasan wajib).
     */
    public function voidPayment(Payment $payment, string $reason, User $verifier): Payment
    {
        if ($payment->status !== 'terverifikasi') {
            throw ValidationException::withMessages([
                'payment' => ["Hanya pembayaran yang berstatus 'terverifikasi' yang dapat di-void."],
            ]);
        }

        return DB::transaction(function () use ($payment, $reason, $verifier) {
            $oldStatus = $payment->status;
            $payment->update([
                'status' => 'void',
                'verified_by' => $verifier->id,
                'verified_at' => now(),
                'rejection_reason' => $reason,
            ]);

            // Hitung ulang akumulasi nominal terbayar invoice
            $payment->invoice->recalculatePaidAmountAndStatus();

            $this->auditLogService->record(
                action: 'finance.payment.void',
                entity: 'Payment',
                entityId: $payment->id,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => 'void', 'rejection_reason' => $reason],
                reason: $reason,
                actor: $verifier
            );

            return $payment->fresh(['invoice']);
        });
    }
}
