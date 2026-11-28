<?php

namespace App\Services\Finance\Channels;

use App\Models\Invoice;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ManualGiroPaymentChannel implements PaymentChannelInterface
{
    public const SETTING_KEY = 'finance_manual_giro';

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
        $settingQuery = SiteSetting::where('setting_key', self::SETTING_KEY);
        if (! empty($invoice->school_id)) {
            $setting = (clone $settingQuery)->where('school_id', $invoice->school_id)->first()
                ?? (clone $settingQuery)->whereNull('school_id')->first()
                ?? $settingQuery->first();
        } else {
            $setting = (clone $settingQuery)->whereNull('school_id')->first() ?? $settingQuery->first();
        }

        $configured = is_array($setting?->setting_value) ? $setting->setting_value : [];

        $bankName = $configured['bank_name']
            ?? config('services.manual_giro.bank_name', 'Bank Jatim / BNI Cabang Malang');
        $accountName = $configured['account_name']
            ?? config('services.manual_giro.account_name', 'SMP Laboratorium Universitas Negeri Malang');
        $accountNumber = $configured['account_number']
            ?? config('services.manual_giro.account_number', '015-100-2026');
        $notesTemplate = $configured['notes']
            ?? "Harap cantumkan nomor tagihan ({$invoice->invoice_number}) pada berita transfer dan unggah foto/PDF bukti transfer resmi.";

        return [
            'channel' => $this->getCode(),
            'name' => $this->getName(),
            'bank_name' => $bankName,
            'account_name' => $accountName,
            'account_number' => $accountNumber,
            'notes' => str_replace('{invoice_number}', (string) $invoice->invoice_number, $notesTemplate),
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
