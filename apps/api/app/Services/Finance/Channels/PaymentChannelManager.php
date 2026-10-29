<?php

namespace App\Services\Finance\Channels;

class PaymentChannelManager
{
    /**
     * Koleksi adapter channel yang terdaftar.
     *
     * @var array<string, PaymentChannelInterface>
     */
    protected array $channels = [];

    public function __construct()
    {
        // Daftarkan channel default v1 (Transfer Giro Mitra Sekolah + Verifikasi Slip Manual)
        $this->register(new ManualGiroPaymentChannel);
    }

    /**
     * Daftarkan adapter kanal pembayaran baru (misal: XenditVaPaymentChannel pada Fase 2).
     */
    public function register(PaymentChannelInterface $channel): void
    {
        $this->channels[$channel->getCode()] = $channel;
    }

    /**
     * Ambil adapter kanal berdasarkan kode identifier.
     */
    public function get(string $code = 'manual_transfer'): PaymentChannelInterface
    {
        return $this->channels[$code] ?? $this->channels['manual_transfer'];
    }

    /**
     * Daftar seluruh kanal pembayaran yang aktif saat ini.
     *
     * @return array<string, PaymentChannelInterface>
     */
    public function getAvailableChannels(): array
    {
        return $this->channels;
    }
}
