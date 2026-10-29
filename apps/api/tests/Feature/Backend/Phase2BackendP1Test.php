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
