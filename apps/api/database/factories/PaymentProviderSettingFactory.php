<?php

namespace Database\Factories;

use App\Models\PaymentProviderSetting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentProviderSetting>
 */
class PaymentProviderSettingFactory extends Factory
{
    protected $model = PaymentProviderSetting::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'name' => 'Bank Jatim Virtual Account',
            'instructions' => "1. Buka aplikasi m-Banking Bank Jatim\n2. Pilih menu Virtual Account\n3. Masukkan nomor VA yang tertera pada faktur\n4. Simpan bukti transfer dan unggah slip.",
            'info_url' => 'https://bankjatim.co.id/panduan-va',
            'is_active' => true,
        ];
    }
}
