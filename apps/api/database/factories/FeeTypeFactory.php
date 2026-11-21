<?php

namespace Database\Factories;

use App\Models\FeeType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FeeType>
 */
class FeeTypeFactory extends Factory
{
    protected $model = FeeType::class;

    public function definition(): array
    {
        $categories = ['spp', 'gedung', 'seragam', 'kegiatan', 'lainnya'];
        $category = fake()->randomElement($categories);

        return [
            'id' => (string) Str::uuid(),
            'code' => strtoupper(fake()->unique()->bothify('FEE-###??')),
            'name' => 'Biaya '.fake()->words(2, true),
            'category' => $category,
            'default_amount' => fake()->randomElement([250000, 350000, 500000, 1000000, 1500000]),
            'is_recurring' => $category === 'spp',
            'is_active' => true,
            'description' => fake()->sentence(),
        ];
    }
}
