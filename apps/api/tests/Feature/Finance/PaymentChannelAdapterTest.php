<?php

namespace Tests\Feature\Finance;

use App\Models\Invoice;
use App\Models\User;
use App\Services\Finance\Channels\ManualGiroPaymentChannel;
use App\Services\Finance\Channels\PaymentChannelInterface;
use App\Services\Finance\Channels\PaymentChannelManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PaymentChannelAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_be_fin_01_default_channel_is_manual_giro_mitra(): void
    {
        $manager = new PaymentChannelManager;
        $channel = $manager->get('manual_transfer');

        $this->assertInstanceOf(ManualGiroPaymentChannel::class, $channel);
        $this->assertSame('manual_transfer', $channel->getCode());
        $this->assertTrue($channel->requiresManualVerification());
        $this->assertTrue($channel->requiresProofUpload());
    }

    public function test_be_fin_01_manual_giro_returns_bank_giro_instructions(): void
    {
        $invoice = new Invoice([
            'invoice_number' => 'INV-2026-TEST-001',
            'total_amount' => 500000,
        ]);

        $channel = new ManualGiroPaymentChannel;
        $instructions = $channel->getPaymentInstructions($invoice);

        $this->assertSame('manual_transfer', $instructions['channel']);
        $this->assertStringContainsString('Bank Jatim / BNI', $instructions['bank_name']);
        $this->assertStringContainsString('015-100-2026', $instructions['account_number']);
        $this->assertStringContainsString('INV-2026-TEST-001', $instructions['notes']);
        $this->assertTrue($instructions['manual_verification_required']);
    }

    public function test_be_fin_01_payment_channel_can_be_extended_for_future_gateway(): void
    {
        $manager = new PaymentChannelManager;

        // Simulasikan adapter virtual account Midtrans/Xendit untuk Fase 2
        $futureVaChannel = new class implements PaymentChannelInterface
        {
            public function getCode(): string
            {
                return 'midtrans_va';
            }

            public function getName(): string
            {
                return 'Midtrans Virtual Account Otomatis';
            }

            public function requiresManualVerification(): bool
            {
                return false;
            }

            public function requiresProofUpload(): bool
            {
                return false;
            }

            public function getPaymentInstructions(Invoice $invoice): array
            {
                return ['va_number' => '98800123456789'];
            }

            public function processSubmission(Invoice $invoice, array $data, ?UploadedFile $proofFile, User $actor): array
            {
                return [
                    'channel' => 'Midtrans VA BNI',
                    'external_reference' => 'MIDTRANS-TX-001',
                    'initial_status' => 'menunggu_pembayaran',
                ];
            }
        };

        $manager->register($futureVaChannel);

        $resolved = $manager->get('midtrans_va');
        $this->assertSame('midtrans_va', $resolved->getCode());
        $this->assertFalse($resolved->requiresManualVerification());
        $this->assertCount(2, $manager->getAvailableChannels());
    }
}
