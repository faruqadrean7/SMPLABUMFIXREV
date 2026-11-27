<?php

namespace Tests\Feature\Finance;

use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\Finance\InvoiceGeneratorService;
use App\Services\Finance\ReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinanceHardeningTier2ATest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $bendahara;

    private User $guardianUser;

    private Student $student;

    private FeeType $feeSpp;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->school = School::create([
            'name' => 'SMP Lab Keuangan',
            'npsn' => '20534001',
            'address' => 'Jl. Fiktif Keuangan No. 1',
            'phone' => '0341000011',
            'email' => 'fin@test.local',
        ]);

        $roleBendahara = Role::firstOrCreate(['code' => 'bendahara'], ['name' => 'Bendahara', 'is_system' => true]);
        foreach (['finance.report.view', 'finance.payment.verify', 'finance.invoice.manage', 'finance.fee.manage'] as $code) {
            $p = Permission::firstOrCreate(['code' => $code], ['description' => $code]);
            $roleBendahara->permissions()->syncWithoutDetaching([$p->id]);
        }

        $this->bendahara = User::create([
            'school_id' => $this->school->id,
            'username' => 'bendahara_t2a_'.uniqid(),
            'email' => 'bendahara_t2a_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->bendahara->roles()->attach($roleBendahara->id);

        $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Orang Tua', 'is_system' => true]);
        $this->guardianUser = User::create([
            'school_id' => $this->school->id,
            'username' => 'ortu_t2a_'.uniqid(),
            'email' => 'ortu_t2a_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->guardianUser->roles()->attach($roleGuardian->id);

        $guardian = Guardian::create([
            'user_id' => $this->guardianUser->id,
            'full_name' => 'Wali Fiktif Keuangan',
            'relationship' => 'ayah',
            'phone' => '081200001111',
        ]);

        $this->student = Student::create([
            'school_id' => $this->school->id,
            'nis' => 'NIS-FIN-001',
            'nisn' => '0099112233',
            'nik_enc' => '3573010101019001',
            'full_name' => 'Siswa Fiktif Keuangan',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-03-10',
            'religion' => 'Islam',
            'address' => 'Jl. Fiktif No. 9',
            'entry_date' => '2025-07-14',
            'status' => 'aktif',
        ]);
        $guardian->students()->attach($this->student->id, ['relationship' => 'ayah', 'is_primary' => true]);

        $this->feeSpp = FeeType::create([
            'school_id' => $this->school->id,
            'code' => 'SPP-T2A',
            'name' => 'SPP Bulanan',
            'category' => 'spp',
            'default_amount' => 500000,
            'is_recurring' => true,
            'is_active' => true,
        ]);
    }

    private function createInvoice(int $totalAmount = 500000, string $status = 'belum_bayar'): Invoice
    {
        $invoice = Invoice::create([
            'school_id' => $this->school->id,
            'invoice_number' => app(InvoiceGeneratorService::class)->generateInvoiceNumber(),
            'student_id' => $this->student->id,
            'period' => '2026-11',
            'due_date' => '2026-11-30',
            'total_amount' => $totalAmount,
            'paid_amount' => 0,
            'status' => $status,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'fee_type_id' => $this->feeSpp->id,
            'description' => 'SPP November',
            'amount' => $totalAmount,
            'discount' => 0,
        ]);

        return $invoice;
    }

    public function test_p0_24_income_report_uses_channel_without_sql_error_and_supports_legacy_params_and_csv(): void
    {
        $inv1 = $this->createInvoice(500000);
        $inv2 = $this->createInvoice(300000);

        Payment::create([
            'school_id' => $this->school->id,
            'invoice_id' => $inv1->id,
            'amount' => 500000,
            'channel' => 'Bank Jatim VA',
            'external_reference' => 'REF-CH-01',
            'payment_date' => '2026-11-10',
            'status' => 'terverifikasi',
            'receipt_number' => 'KWT/2026/11/0001',
            'submitted_by' => $this->guardianUser->id,
            'verified_by' => $this->bendahara->id,
            'verified_at' => now(),
        ]);

        Payment::create([
            'school_id' => $this->school->id,
            'invoice_id' => $inv2->id,
            'amount' => 300000,
            'channel' => 'Tunai Loket TU',
            'external_reference' => 'REF-CH-02',
            'payment_date' => '2026-11-12',
            'status' => 'terverifikasi',
            'receipt_number' => 'KWT/2026/11/0002',
            'submitted_by' => $this->guardianUser->id,
            'verified_by' => $this->bendahara->id,
            'verified_at' => now(),
        ]);

        // 1. Query memakai start_date/end_date (seperti FE reports/page.tsx)
        $resAll = $this->actingAs($this->bendahara, 'sanctum')
            ->getJson('/api/v1/finance/reports/income?start_date=2026-11-01&end_date=2026-11-30');

        $resAll->assertStatus(200)
            ->assertJsonPath('data.total_income', 800000)
            ->assertJsonPath('data.payment_count', 2)
            ->assertJsonPath('data.summary.total_income', 800000)
            ->assertJsonPath('data.summary.cash_income', 300000)
            ->assertJsonPath('data.summary.transfer_income', 500000)
            ->assertJsonPath('data.by_category.spp', 800000);

        // 2. Query filter legacy ?payment_method=transfer TIDAK lagi melempar SQL 500
        $resTransfer = $this->actingAs($this->bendahara, 'sanctum')
            ->getJson('/api/v1/finance/reports/income?date_from=2026-11-01&date_to=2026-11-30&payment_method=transfer');
        $resTransfer->assertStatus(200)
            ->assertJsonPath('data.summary.total_income', 500000)
            ->assertJsonPath('data.summary.total_payments_count', 1);

        // 3. Query filter baru ?channel=Bank+Jatim
        $resChannel = $this->actingAs($this->bendahara, 'sanctum')
            ->getJson('/api/v1/finance/reports/income?date_from=2026-11-01&date_to=2026-11-30&channel=Bank+Jatim');
        $resChannel->assertStatus(200)
            ->assertJsonPath('data.summary.total_income', 500000);

        // 4. Ekspor CSV berhasil 200 dengan kolom kanal nyata
        $resCsv = $this->actingAs($this->bendahara, 'sanctum')
            ->get('/api/v1/finance/reports/income?date_from=2026-11-01&date_to=2026-11-30&format=csv');
        $resCsv->assertStatus(200);
        $csvContent = $resCsv->streamedContent();
        $this->assertStringContainsString('Bank Jatim VA', $csvContent);
        $this->assertStringContainsString('Tunai Loket TU', $csvContent);
    }

    public function test_p0_25_verify_payment_blocks_cancelled_invoice_and_overpayment_and_rejected_slip(): void
    {
        // A. Slip pada invoice yang sudah dibatalkan wajib DITOLAK 422 saat verifikasi
        $cancelledInvoice = $this->createInvoice(500000);
        $pendingPayment = Payment::create([
            'school_id' => $this->school->id,
            'invoice_id' => $cancelledInvoice->id,
            'amount' => 500000,
            'channel' => 'BRI VA',
            'external_reference' => 'REF-CANCEL-01',
            'payment_date' => '2026-11-15',
            'status' => 'menunggu_verifikasi',
            'submitted_by' => $this->guardianUser->id,
        ]);

        // Bendahara membatalkan invoice
        $this->actingAs($this->bendahara, 'sanctum')
            ->postJson("/api/v1/finance/invoices/{$cancelledInvoice->id}/cancel", ['reason' => 'Salah tagih'])
            ->assertStatus(200);

        // Upaya verifikasi slip lama pada invoice batal wajib ditolak 422
        $this->actingAs($this->bendahara, 'sanctum')
            ->postJson("/api/v1/finance/payments/{$pendingPayment->id}/verify")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['invoice']);

        $pendingPayment->refresh();
        $this->assertSame('menunggu_verifikasi', $pendingPayment->status);
        $this->assertNull($pendingPayment->receipt_number);

        // B. Dua cicilan pending yang totalnya melebihi tagihan: yang kedua gagal saat submit ATAU saat verify
        $inv2 = $this->createInvoice(500000);
        $p1 = Payment::create([
            'school_id' => $this->school->id,
            'invoice_id' => $inv2->id,
            'amount' => 300000,
            'channel' => 'BCA VA',
            'external_reference' => 'REF-OVP-01',
            'payment_date' => '2026-11-15',
            'status' => 'menunggu_verifikasi',
            'submitted_by' => $this->guardianUser->id,
        ]);
        $p2 = Payment::create([
            'school_id' => $this->school->id,
            'invoice_id' => $inv2->id,
            'amount' => 300000,
            'channel' => 'BCA VA',
            'external_reference' => 'REF-OVP-02',
            'payment_date' => '2026-11-15',
            'status' => 'menunggu_verifikasi',
            'submitted_by' => $this->guardianUser->id,
        ]);

        $this->actingAs($this->bendahara, 'sanctum')
            ->postJson("/api/v1/finance/payments/{$p1->id}/verify")
            ->assertStatus(200);

        $this->actingAs($this->bendahara, 'sanctum')
            ->postJson("/api/v1/finance/payments/{$p2->id}/verify")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        // C. Submit slip baru yang bersama antrean pending melebihi total tagihan ditolak 422
        $inv3 = $this->createInvoice(500000);
        $this->actingAs($this->guardianUser, 'sanctum')
            ->postJson("/api/v1/me/invoices/{$inv3->id}/payments", [
                'amount' => 400000,
                'channel' => 'BCA Mobile',
                'external_reference' => 'REF-PEND-01',
                'payment_date' => '2026-11-16',
                'proof_file' => UploadedFile::fake()->create('slip1.jpg', 100, 'image/jpeg'),
            ])
            ->assertStatus(201);

        $this->actingAs($this->guardianUser, 'sanctum')
            ->postJson("/api/v1/me/invoices/{$inv3->id}/payments", [
                'amount' => 200000,
                'channel' => 'BCA Mobile',
                'external_reference' => 'REF-PEND-02',
                'payment_date' => '2026-11-16',
                'proof_file' => UploadedFile::fake()->create('slip2.jpg', 100, 'image/jpeg'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_p0_25_invoice_and_receipt_number_generators_produce_monotonic_unique_sequences(): void
    {
        $invGen = app(InvoiceGeneratorService::class);
        $rcpGen = app(ReceiptService::class);

        $invA = $this->createInvoice(100000);
        $invB = $this->createInvoice(100000);
        $this->assertNotSame($invA->invoice_number, $invB->invoice_number);
        $this->assertStringStartsWith('INV/'.date('Y/m').'/', $invA->invoice_number);

        $rcp1 = $rcpGen->generateReceiptNumber();
        Payment::create([
            'school_id' => $this->school->id,
            'invoice_id' => $invA->id,
            'amount' => 100000,
            'channel' => 'BRI',
            'external_reference' => 'REF-SEQ-01',
            'payment_date' => now()->toDateString(),
            'status' => 'terverifikasi',
            'submitted_by' => $this->guardianUser->id,
            'receipt_number' => $rcp1,
        ]);
        $rcp2 = $rcpGen->generateReceiptNumber();
        $this->assertNotSame($rcp1, $rcp2);
        $this->assertStringEndsWith('/0002', $rcp2);
    }
}
