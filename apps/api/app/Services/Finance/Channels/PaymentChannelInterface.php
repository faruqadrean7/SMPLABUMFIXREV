<?php

namespace App\Services\Finance\Channels;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\UploadedFile;

interface PaymentChannelInterface
{
    /**
     * Kode unik identifier channel pembayaran (misal: 'manual_transfer', 'bank_giro', 'xendit_va', 'midtrans_va').
     */
    public function getCode(): string;

    /**
     * Nama label kanal pembayaran yang ramah pengguna.
     */
    public function getName(): string;

    /**
     * Menentukan apakah pembayaran melalui kanal ini memerlukan verifikasi manual oleh petugas TU.
     */
    public function requiresManualVerification(): bool;

    /**
     * Menentukan apakah berkas slip/bukti transfer wajib diunggah oleh pembayar.
     */
    public function requiresProofUpload(): bool;

    /**
     * Mengambil detail dan instruksi pembayaran untuk suatu faktur tagihan.
     */
    public function getPaymentInstructions(Invoice $invoice): array;

    /**
     * Memproses pengajuan pembayaran dan mengembalikan metadata channel yang terverifikasi.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function processSubmission(Invoice $invoice, array $data, ?UploadedFile $proofFile, User $actor): array;
}
