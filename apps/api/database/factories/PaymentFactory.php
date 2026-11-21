<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $amount = fake()->randomElement([250000, 350000, 500000]);

        return [
            'id' => (string) Str::uuid(),
            'invoice_id' => Invoice::factory(),
            'amount' => $amount,
            'channel' => fake()->randomElement(['Bank Jatim', 'BCA', 'Mandiri', 'BSI']),
            'external_reference' => 'TRX-'.Str::upper(Str::random(10)),
            'payment_date' => now()->toDateString(),
            'proof_file_id' => null,
            'status' => 'menunggu_verifikasi',
            'submitted_by' => User::factory(),
            'verified_by' => null,
            'verified_at' => null,
            'rejection_reason' => null,
            'receipt_number' => null,
        ];
    }
}
