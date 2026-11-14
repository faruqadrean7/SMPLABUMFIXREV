<?php

namespace Tests\Feature\Backend;

use App\Models\FeeType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Services\Finance\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase2BackendP1Test extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $studentUser;

    protected Student $studentModel;

    protected FeeType $feeType;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $permStudentRead = Permission::firstOrCreate(['code' => 'master.student.read'], ['description' => 'Melihat data siswa']);
        $permStudentWrite = Permission::firstOrCreate(['code' => 'master.student.write'], ['description' => 'Mengelola data siswa']);
        $roleAdmin->permissions()->syncWithoutDetaching([$permStudentRead->id, $permStudentWrite->id]);

        $this->adminUser = User::create([
            'username' => 'admin_phase2',
            'email' => 'admin.p2@labschool.sch.id',
            'password_hash' => Hash::make('AdminPass123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->studentUser = User::create([
            'username' => 'student_phase2',
            'email' => 'student.p2@labschool.sch.id',
            'password_hash' => Hash::make('StudentPass123!'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($roleStudent->id);

        $this->studentModel = Student::create([
            'user_id' => $this->studentUser->id,
            'nis' => '990001',
            'nisn' => '0099000001',
            'full_name' => 'Siswa Uji Fase 2',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-01-01',
            'status' => 'aktif',
            'entry_date' => Carbon::now()->subYear(),
            'nik_enc' => 'NIK-TEST-990001',
        ]);

        $this->feeType = FeeType::create([
            'name' => 'SPP Bulanan Fase 2',
            'code' => 'SPP-P2',
            'category' => 'spp',
            'amount' => 500000,
            'is_active' => true,
        ]);
    }

    /**
     * BE-01: Pastikan error 403 atau 422 pada StudentController tidak tersamarkan menjadi HTTP 500.
     */
    public function test_be_01_student_controller_handles_http_exceptions_without_500(): void
    {
        // 1. Permintaan tanpa izin master.student.read pada /students/export
        $responseForbidden = $this->actingAs($this->studentUser, 'sanctum')
            ->getJson('/api/v1/students/export');

        $this->assertSame(403, $responseForbidden->status());
        $this->assertSame('FORBIDDEN', $responseForbidden->json('error.code'));
        $this->assertNotEmpty($responseForbidden->headers->get('X-Request-Id'));

        // 2. Request detail siswa tidak ada mengembalikan 404 (bukan 500)
        $nonExistentId = (string) Str::uuid();
        $responseNotFound = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/students/{$nonExistentId}");

        $this->assertSame(404, $responseNotFound->status());
        $this->assertSame('NOT_FOUND', $responseNotFound->json('error.code'));
    }

    /**
     * BE-01: Pastikan endpoint status siswa dan modul master lainnya mengembalikan kode kanonis (404/422), bukan 500.
     */
    public function test_be_01_student_status_and_master_controllers_handle_exceptions_cleanly(): void
    {
        // 1. Status siswa pada UUID non-existent mengembalikan 404
        $fakeUuid = (string) Str::uuid();
        $resNotFound = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/students/{$fakeUuid}/status", [
                'status' => 'lulus',
                'effective_date' => '2026-11-04',
                'reason' => 'Telah menyelesaikan pendidikan',
            ]);

        $this->assertSame(404, $resNotFound->status());
        $this->assertSame('NOT_FOUND', $resNotFound->json('error.code'));

        // 2. Status siswa validasi gagal mengembalikan 400 VALIDATION_FAILED (bukan 500)
        $resInvalid = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/students/{$this->studentModel->id}/status", [
                'status' => 'status_tidak_dikenal',
                'effective_date' => '2026-11-04',
            ]);

        $this->assertSame(400, $resInvalid->status());
        $this->assertSame('VALIDATION_FAILED', $resInvalid->json('error.code'));

        // 3. TeacherController show non-existent mengembalikan 404 (bukan 500)
        $resTeacherNotFound = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/teachers/{$fakeUuid}");

        $this->assertSame(404, $resTeacherNotFound->status());
        $this->assertSame('NOT_FOUND', $resTeacherNotFound->json('error.code'));

        // 4. GuardianController show non-existent mengembalikan 404 (bukan 500)
        $resGuardianNotFound = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/guardians/{$fakeUuid}");

        $this->assertSame(404, $resGuardianNotFound->status());
        $this->assertSame('NOT_FOUND', $resGuardianNotFound->json('error.code'));

        // 5. UserController show non-existent mengembalikan 404 (bukan 500)
        $resUserNotFound = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/users/{$fakeUuid}");

        $this->assertSame(404, $resUserNotFound->status());
        $this->assertSame('NOT_FOUND', $resUserNotFound->json('error.code'));
    }

    /**
     * BE-04: Transaksi rekonsiliasi payment verification, void, dan recalculation status invoice di FinanceService.
     */
    public function test_be_04_payment_verification_and_void_recalculates_invoice_in_transaction(): void
    {
        $financeService = app(FinanceService::class);

        // Buat tagihan Rp 600.000
        $invoice = Invoice::create([
            'invoice_number' => 'INV-TX-001',
            'student_id' => $this->studentModel->id,
            'issue_date' => Carbon::today(),
            'due_date' => Carbon::today()->addDays(14),
            'total_amount' => 600000,
            'paid_amount' => 0,
            'status' => 'belum_bayar',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'fee_type_id' => $this->feeType->id,
            'description' => 'SPP Pembayaran Bertransaksi',
            'amount' => 600000,
        ]);

        // Pembayaran tahap 1: Rp 300.000 (status awal menunggu verifikasi)
        $payment1 = Payment::create([
            'invoice_id' => $invoice->id,
            'submitted_by' => $this->studentUser->id,
            'payment_method' => 'transfer_bank',
            'amount' => 300000,
            'status' => 'menunggu_verifikasi',
            'payment_date' => Carbon::today(),
            'external_reference' => 'EXT-TX-001',
        ]);

        // 1. Verifikasi pembayaran tahap 1
        $verifiedPayment1 = $financeService->verifyPayment($payment1, 'Verifikasi tahap 1', $this->adminUser);

        $this->assertSame('terverifikasi', $verifiedPayment1->status);
        $this->assertNotEmpty($verifiedPayment1->receipt_number);

        // Verifikasi invoice berubah ke 'sebagian' dengan paid_amount 300.000
        $invoice->refresh();
        $this->assertSame(300000, (int) $invoice->paid_amount);
        $this->assertSame('sebagian', $invoice->status);

        // Pembayaran tahap 2: Rp 300.000
        $payment2 = Payment::create([
            'invoice_id' => $invoice->id,
            'submitted_by' => $this->studentUser->id,
            'payment_method' => 'transfer_bank',
            'amount' => 300000,
            'status' => 'menunggu_verifikasi',
            'payment_date' => Carbon::today(),
            'external_reference' => 'EXT-TX-002',
        ]);

        // 2. Verifikasi pembayaran tahap 2 -> invoice lunas
        $financeService->verifyPayment($payment2, 'Verifikasi tahap 2 pelunasan', $this->adminUser);

        $invoice->refresh();
        $this->assertSame(600000, (int) $invoice->paid_amount);
        $this->assertSame('lunas', $invoice->status);

        // 3. Void pembayaran tahap 2 -> invoice kembali ke 'sebagian'
        $financeService->voidPayment($payment2, 'Koreksi kesalahan transfer bank', $this->adminUser);

        $invoice->refresh();
        $this->assertSame(300000, (int) $invoice->paid_amount);
        $this->assertSame('sebagian', $invoice->status);

        // 4. Void pembayaran tahap 1 -> invoice kembali ke 'belum_bayar'
        $financeService->voidPayment($payment1, 'Koreksi pembatalan tahap 1', $this->adminUser);

        $invoice->refresh();
        $this->assertSame(0, (int) $invoice->paid_amount);
        $this->assertSame('belum_bayar', $invoice->status);
    }

    /**
     * BE-04: Deteksi drift tagihan dengan dry-run (tidak mengubah DB).
     */
    public function test_be_04_reconcile_command_detects_drift_in_dry_run(): void
    {
        // Buat tagihan Rp 500.000
        $invoice = Invoice::create([
            'invoice_number' => 'INV-DRIFT-001',
            'student_id' => $this->studentModel->id,
            'issue_date' => Carbon::today(),
            'due_date' => Carbon::today()->addDays(14),
            'total_amount' => 500000,
            'paid_amount' => 0,
            'status' => 'belum_bayar',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'fee_type_id' => $this->feeType->id,
            'description' => 'SPP P2 Bulan Ini',
            'amount' => 500000,
        ]);

        // Pembayaran terverifikasi penuh Rp 500.000
        Payment::create([
            'invoice_id' => $invoice->id,
            'submitted_by' => $this->studentUser->id,
            'payment_method' => 'transfer_bank',
            'amount' => 500000,
            'status' => 'terverifikasi',
            'verified_at' => Carbon::now(),
            'payment_date' => Carbon::today(),
            'external_reference' => 'EXT-P2-001',
        ]);

        // Simulasikan data drift di DB: status sengaja tetap 'belum_bayar' dan paid_amount = 0
        $invoice->update([
            'paid_amount' => 0,
            'status' => 'belum_bayar',
        ]);

        // Jalankan perintah rekonsiliasi dalam mode dry-run
        $this->artisan('finance:reconcile', [
            '--invoice' => $invoice->id,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('[DRIFT DETECTED] Invoice #INV-DRIFT-001')
            ->expectsOutputToContain('Drift ditemukan & dilaporkan: 1')
            ->assertExitCode(0);

        // Pastikan DB tidak berubah karena dry-run
        $invoice->refresh();
        $this->assertSame(0, (int) $invoice->paid_amount);
        $this->assertSame('belum_bayar', $invoice->status);
    }

    /**
     * BE-04: Eksekusi rekonsiliasi memperbaiki data drift secara idempoten.
     */
    public function test_be_04_reconcile_command_fixes_drift_idempotently(): void
    {
        // Buat tagihan Rp 500.000
        $invoice = Invoice::create([
            'invoice_number' => 'INV-DRIFT-002',
            'student_id' => $this->studentModel->id,
            'issue_date' => Carbon::today(),
            'due_date' => Carbon::today()->addDays(14),
            'total_amount' => 500000,
            'paid_amount' => 0,
            'status' => 'belum_bayar',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'fee_type_id' => $this->feeType->id,
            'description' => 'SPP P2 Bulan Ini',
            'amount' => 500000,
        ]);

        // Pembayaran terverifikasi separuh Rp 200.000
        Payment::create([
            'invoice_id' => $invoice->id,
            'submitted_by' => $this->studentUser->id,
            'payment_method' => 'transfer_bank',
            'amount' => 200000,
            'status' => 'terverifikasi',
            'verified_at' => Carbon::now(),
            'payment_date' => Carbon::today(),
            'external_reference' => 'EXT-P2-002',
        ]);

        // 1. Eksekusi rekonsiliasi untuk memperbaiki data
        $this->artisan('finance:reconcile', [
            '--invoice' => $invoice->id,
        ])
            ->expectsOutputToContain('[DRIFT DETECTED]')
            ->expectsOutputToContain('Drift ditemukan & diperbaiki: 1')
            ->assertExitCode(0);

        // Verifikasi DB telah terupdate
        $invoice->refresh();
        $this->assertSame(200000, (int) $invoice->paid_amount);
        $this->assertSame('sebagian', $invoice->status);

        // 2. Eksekusi kedua untuk memastikan idempoten (0 drift)
        $this->artisan('finance:reconcile', [
            '--invoice' => $invoice->id,
        ])
            ->doesntExpectOutputToContain('[DRIFT DETECTED]')
            ->expectsOutputToContain('Drift ditemukan & diperbaiki: 0')
            ->assertExitCode(0);

        $invoice->refresh();
        $this->assertSame(200000, (int) $invoice->paid_amount);
        $this->assertSame('sebagian', $invoice->status);
    }
}
