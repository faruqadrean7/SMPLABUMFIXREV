<?php

namespace App\Services\Finance\Channels;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ManualGiroPaymentChannel implements PaymentChannelInterface
{
    public function getCode(): string
    {
        return 'manual_transfer';
    }

    public function getName(): string
    {
        return 'Transfer Bank / Rekening Giro Mitra Resmi Sekolah';
    }

    public function requiresManualVerification(): bool
    {
        return true;
    }

    public function requiresProofUpload(): bool
    {
        return true;
    }

    public function getPaymentInstructions(Invoice $invoice): array
    {
        return [
            'channel' => $this->getCode(),
            'name' => $this->getName(),
            'bank_name' => 'Bank Jatim / BNI Cabang Malang',
            'account_name' => 'SMP Laboratorium Universitas Negeri Malang',
            'account_number' => '015-100-2026',
            'notes' => "Harap cantumkan nomor tagihan ({$invoice->invoice_number}) pada berita transfer dan unggah foto/PDF bukti transfer resmi.",
            'manual_verification_required' => true,
        ];
    }

    public function processSubmission(Invoice $invoice, array $data, ?UploadedFile $proofFile, User $actor): array
    {
        if (! $proofFile) {
            throw ValidationException::withMessages([
                'proof_file' => ['Berkas bukti/slip pembayaran transfer wajib dilampirkan untuk verifikasi staf TU.'],
            ]);
        }

        return [
            'channel' => trim($data['channel'] ?? 'Transfer Bank (Giro Mitra)'),
            'external_reference' => trim($data['external_reference'] ?? ''),
            'initial_status' => 'menunggu_verifikasi',
        ];
    }
}
