<?php

namespace Database\Factories;

use App\Models\FeeType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        $amount = fake()->randomElement([250000, 350000, 500000]);

        return [
            'id' => (string) Str::uuid(),
            'invoice_id' => Invoice::factory(),
            'fee_type_id' => FeeType::factory(),
            'description' => 'Pembayaran ' . fake()->words(2, true),
            'amount' => $amount,
            'discount' => 0,
        ];
    }
}
