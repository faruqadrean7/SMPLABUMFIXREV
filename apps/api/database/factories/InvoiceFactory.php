<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $amount = fake()->randomElement([350000, 500000, 750000, 1000000]);
        $year = date('Y');
        $month = date('m');

        return [
            'id' => (string) Str::uuid(),
            'invoice_number' => 'INV/'.$year.'/'.$month.'/'.strtoupper(fake()->unique()->bothify('####??')),
            'student_id' => Student::factory(),
            'academic_year_id' => null,
            'period' => $year.'-'.$month,
            'due_date' => now()->addDays(14)->toDateString(),
            'total_amount' => $amount,
            'paid_amount' => 0,
            'status' => 'belum_bayar',
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
