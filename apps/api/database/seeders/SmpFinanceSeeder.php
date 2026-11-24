<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Keuangan dasar agar modul tagihan bisa dipakai harian.
 *
 * 1. fee_types  : SPP (bulanan), Uang Kegiatan, Seragam, Uang Gedung
 * 2. invoices   : tagihan SPP Juli–November 2026 untuk setiap siswa
 *                 + 1 tagihan Uang Kegiatan per siswa
 * 3. payments   : sebagian tagihan dibayar penuh / sebagian, sisanya menunggak
 *
 * Nominal sengaja sederhana dan konsisten agar mudah diperiksa.
 * Idempoten: invoice dengan nomor yang sama dilewati.
 *
 *   php artisan db:seed --class=SmpFinanceSeeder
 */
class SmpFinanceSeeder extends Seeder
{
    private const SCHOOL_ID = '00000000-0000-0000-0000-000000000001';

    /** kode => [nama, kategori, nominal, berulang] */
    private const FEE = [
        'SPP'      => ['SPP Bulanan', 'spp', 350000, true],
        'KEGIATAN' => ['Uang Kegiatan Semester', 'kegiatan', 500000, false],
        'SERAGAM'  => ['Seragam Sekolah', 'seragam', 750000, false],
        'GEDUNG'   => ['Uang Gedung (dapat diangsur)', 'gedung', 1500000, false],
    ];

    /** Bulan SPP yang ditagihkan. */
    private const BULAN = [7, 8, 9, 10, 11];

    public function run(): void
    {
        $now = now();
        $year = DB::table('academic_years')->where('is_active', true)->first()
            ?? DB::table('academic_years')->orderByDesc('start_date')->first();

        if (! $year) {
            throw new \RuntimeException('Tidak ada academic_years. Jalankan SmpDataSeeder dulu.');
        }

        $feeIds = $this->seedFeeTypes($now);
        $students = DB::table('students')->whereNull('deleted_at')->orderBy('nis')->get(['id', 'nis', 'school_id', 'user_id']);

        // payments.submitted_by menunjuk ke users.id, bukan students.id.
        // Pakai akun siswa; kalau belum punya akun, pakai bendahara/superadmin.
        $fallbackUserId = DB::table('users')->where('username', 'bendahara')->value('id')
            ?? DB::table('users')->where('username', 'superadmin')->value('id');

        if ($students->isEmpty()) {
            throw new \RuntimeException('Tidak ada siswa. Jalankan SmpDataSeeder dulu.');
        }

        $created = 0;
        $paid = 0;

        foreach ($students as $index => $student) {
            // --- SPP per bulan ---
            foreach (self::BULAN as $bulan) {
                $period = sprintf('%04d-%02d', 2026, $bulan);
                $number = 'INV/SPP/'.$period.'/'.$student->nis;

                if (DB::table('invoices')->where('invoice_number', $number)->exists()) {
                    continue;
                }

                // Pola pembayaran: 60% lunas, 25% sebagian, 15% menunggak.
                $nasib = $index % 20;
                [$status, $paidAmount] = match (true) {
                    $nasib < 12 => ['lunas', 350000],
                    $nasib < 17 => ['sebagian', 150000],
                    default => ['belum_bayar', 0],
                };

                $invoiceId = (string) Str::uuid7();
                DB::table('invoices')->insert([
                    'id' => $invoiceId,
                    'school_id' => $student->school_id ?? self::SCHOOL_ID,
                    'invoice_number' => $number,
                    'student_id' => $student->id,
                    'academic_year_id' => $year->id,
                    'period' => $period,
                    'due_date' => Carbon::create(2026, $bulan, 10)->format('Y-m-d'),
                    'total_amount' => 350000,
                    'paid_amount' => $paidAmount,
                    'status' => $status,
                    'notes' => 'SPP bulan '.$bulan.' 2026',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('invoice_items')->insert([
                    'id' => (string) Str::uuid7(),
                    'invoice_id' => $invoiceId,
                    'fee_type_id' => $feeIds['SPP'],
                    'description' => 'SPP bulan '.$bulan.' 2026',
                    'amount' => 350000,
                    'discount' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($paidAmount > 0) {
                    DB::table('payments')->insert([
                        'id' => (string) Str::uuid7(),
                        'school_id' => $student->school_id ?? self::SCHOOL_ID,
                        'invoice_id' => $invoiceId,
                        'amount' => $paidAmount,
                        'payment_date' => Carbon::create(2026, $bulan, 8)->format('Y-m-d'),
                        'proof_file_id' => null,
                        'status' => 'terverifikasi',
                        'submitted_by' => $student->user_id ?? $fallbackUserId,
                        'verified_by' => null,
                        'verified_at' => $now,
                        'rejection_reason' => null,
                        'receipt_number' => 'KWT/'.$period.'/'.$student->nis,
                        'created_at' => $now,
                        'updated_at' => $now,
                        'channel' => 'tunai',
                        'external_reference' => 'SEED-'.$period.'-'.$student->nis,
                    ]);
                    $paid++;
                }

                $created++;
            }

            // --- Uang kegiatan (sekali per semester) ---
            $number = 'INV/KEGIATAN/2026-GANJIL/'.$student->nis;
            if (! DB::table('invoices')->where('invoice_number', $number)->exists()) {
                $lunas = $index % 3 === 0;
                $invoiceId = (string) Str::uuid7();

                DB::table('invoices')->insert([
                    'id' => $invoiceId,
                    'school_id' => $student->school_id ?? self::SCHOOL_ID,
                    'invoice_number' => $number,
                    'student_id' => $student->id,
                    'academic_year_id' => $year->id,
                    'period' => '2026-GANJIL',
                    'due_date' => Carbon::create(2026, 9, 30)->format('Y-m-d'),
                    'total_amount' => 500000,
                    'paid_amount' => $lunas ? 500000 : 0,
                    'status' => $lunas ? 'lunas' : 'belum_bayar',
                    'notes' => 'Uang kegiatan semester Ganjil 2026/2027',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('invoice_items')->insert([
                    'id' => (string) Str::uuid7(),
                    'invoice_id' => $invoiceId,
                    'fee_type_id' => $feeIds['KEGIATAN'],
                    'description' => 'Uang kegiatan semester Ganjil',
                    'amount' => 500000,
                    'discount' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($lunas) {
                    DB::table('payments')->insert([
                        'id' => (string) Str::uuid7(),
                        'school_id' => $student->school_id ?? self::SCHOOL_ID,
                        'invoice_id' => $invoiceId,
                        'amount' => 500000,
                        'payment_date' => Carbon::create(2026, 9, 15)->format('Y-m-d'),
                        'proof_file_id' => null,
                        'status' => 'terverifikasi',
                        'submitted_by' => $student->user_id ?? $fallbackUserId,
                        'verified_by' => null,
                        'verified_at' => $now,
                        'rejection_reason' => null,
                        'receipt_number' => 'KWT/KEG/'.$student->nis,
                        'created_at' => $now,
                        'updated_at' => $now,
                        'channel' => 'transfer',
                        'external_reference' => 'SEED-KEG-'.$student->nis,
                    ]);
                    $paid++;
                }

                $created++;
            }
        }

        $this->command?->info("Keuangan: {$created} tagihan, {$paid} pembayaran tercatat.");
        $this->command?->line('  Jenis tagihan: '.implode(', ', array_keys(self::FEE)));
    }

    /** @return array<string, string> kode => id */
    private function seedFeeTypes($now): array
    {
        $out = [];
        foreach (self::FEE as $code => [$name, $category, $amount, $recurring]) {
            $row = DB::table('fee_types')->where('code', $code)->where('school_id', self::SCHOOL_ID)->first();

            if (! $row) {
                $id = (string) Str::uuid7();
                DB::table('fee_types')->insert([
                    'id' => $id,
                    'school_id' => self::SCHOOL_ID,
                    'code' => $code,
                    'name' => $name,
                    'category' => $category,
                    'default_amount' => $amount,
                    'is_recurring' => $recurring,
                    'is_active' => true,
                    'description' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $row = DB::table('fee_types')->where('id', $id)->first();
            }

            $out[$code] = $row->id;
        }

        $this->command?->info('  '.count($out).' jenis tagihan siap.');

        return $out;
    }
}
