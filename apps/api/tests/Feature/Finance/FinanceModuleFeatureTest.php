<?php

namespace Tests\Feature\Finance;

use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentProviderSetting;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\Finance\InvoiceGeneratorService;
use App\Services\Finance\ReceiptService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinanceModuleFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $bendahara;
    protected User $guru;
    protected User $guardianUserA;
    protected User $guardianUserB;
    protected User $studentUserA;
    protected User $studentUserB;

    protected Guardian $guardianA;
    protected Guardian $guardianB;
    protected Student $studentA;
    protected Student $studentB;
    protected SchoolClass $class7A;
    protected FeeType $feeTypeSpp;
    protected PaymentProviderSetting $providerSetting;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->seed(DatabaseSeeder::class);

        // 1. Roles
        $roleSuperAdmin = Role::where('code', 'super_admin')->first();
        $roleBendahara = Role::where('code', 'bendahara')->first();
        $roleTeacher = Role::where('code', 'teacher')->first();
        $roleGuardian = Role::where('code', 'guardian')->first();
        $roleStudent = Role::where('code', 'student')->first();

        // 2. Users
        $this->superAdmin = User::create([
            'id' => (string) Str::uuid(),
            'username' => 'admin_super_fin',
            'email' => 'admin@labum.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->superAdmin->roles()->sync([$roleSuperAdmin->id]);

        $this->bendahara = User::create([
            'id' => (string) Str::uuid(),
            'username' => 'bendahara_fin',
            'email' => 'bendahara@labum.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->bendahara->roles()->sync([$roleBendahara->id]);

        $this->guru = User::create([
            'id' => (string) Str::uuid(),
            'username' => 'guru_fin',
            'email' => 'guru@labum.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->guru->roles()->sync([$roleTeacher->id]);

        $this->guardianUserA = User::create([
            'id' => (string) Str::uuid(),
            'username' => 'wali_a_fin',
            'email' => 'wali.a@gmail.com',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->guardianUserA->roles()->sync([$roleGuardian->id]);

        $this->guardianUserB = User::create([
            'id' => (string) Str::uuid(),
            'username' => 'wali_b_fin',
            'email' => 'wali.b@gmail.com',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->guardianUserB->roles()->sync([$roleGuardian->id]);

        $this->studentUserA = User::create([
            'id' => (string) Str::uuid(),
            'username' => 'siswa_a_fin',
            'email' => 'siswa.a@student.labum.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUserA->roles()->sync([$roleStudent->id]);

        $this->studentUserB = User::create([
            'id' => (string) Str::uuid(),
            'username' => 'siswa_b_fin',
            'email' => 'siswa.b@student.labum.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUserB->roles()->sync([$roleStudent->id]);

        // 3. Kelas, Tahun Ajaran & Jenjang
        $academicYear = \App\Models\AcademicYear::create([
            'id' => (string) Str::uuid(),
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $gradeLevel = \App\Models\GradeLevel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Kelas 7',
            'sort_order' => 7,
        ]);

        $this->class7A = SchoolClass::create([
            'id' => (string) Str::uuid(),
            'academic_year_id' => $academicYear->id,
            'grade_level_id' => $gradeLevel->id,
            'name' => '7-A',
            'is_active' => true,
        ]);

        // 4. Siswa & Guardian Entities
        $this->studentA = Student::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->studentUserA->id,
            'nis' => '2026001',
            'nisn' => '0012345678',
            'nik_enc' => '3579010101010001',
            'full_name' => 'Ahmad Santoso',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-05-12',
            'entry_date' => '2024-07-15',
            'status' => 'aktif',
        ]);
        $this->studentA->classes()->attach($this->class7A->id, [
            'id' => (string) Str::uuid(),
            'joined_at' => now()->toDateString(),
        ]);

        $this->studentB = Student::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->studentUserB->id,
            'nis' => '2026002',
            'nisn' => '0012345679',
            'nik_enc' => '3579010101010002',
            'full_name' => 'Budi Pratama',
            'gender' => 'L',
            'birth_place' => 'Surabaya',
            'birth_date' => '2010-08-20',
            'entry_date' => '2024-07-15',
            'status' => 'aktif',
        ]);
        $this->studentB->classes()->attach($this->class7A->id, [
            'id' => (string) Str::uuid(),
            'joined_at' => now()->toDateString(),
        ]);

        $this->guardianA = Guardian::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->guardianUserA->id,
            'full_name' => 'Bapak Ahmad',
            'phone' => '08123456701',
        ]);
        $this->guardianA->students()->attach($this->studentA->id, [
            'relationship' => 'ayah',
            'is_primary' => true,
        ]);

        $this->guardianB = Guardian::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->guardianUserB->id,
            'full_name' => 'Bapak Budi',
            'phone' => '08123456702',
        ]);
        $this->guardianB->students()->attach($this->studentB->id, [
            'relationship' => 'ayah',
            'is_primary' => true,
        ]);

        // 5. Fee Type & Bank Account
        $this->feeTypeSpp = FeeType::create([
            'id' => (string) Str::uuid(),
            'code' => 'SPP-SMP',
            'name' => 'SPP Bulanan SMP',
            'category' => 'spp',
            'default_amount' => 500000,
            'is_recurring' => true,
            'is_active' => true,
        ]);

        $this->providerSetting = PaymentProviderSetting::create([
            'id' => (string) Str::uuid(),
            'name' => 'Bank Jatim Virtual Account',
            'instructions' => 'Transfer lewat ATM atau m-Banking Bank Jatim ke nomor VA yang tertera.',
            'info_url' => 'https://bankjatim.co.id/panduan-va',
            'is_active' => true,
        ]);
    }

    /**
     * UNIT & BUSINESS LOGIC: Terbilang rupiah tepat dan presisi
     */
    public function test_receipt_service_terbilang_rupiah(): void
    {
        $receiptService = app(ReceiptService::class);

        $this->assertEquals('Nol Rupiah', $receiptService->terbilang(0));
        $this->assertEquals('Lima Ratus Ribu Rupiah', $receiptService->terbilang(500000));
        $this->assertEquals('Satu Juta Lima Ratus Ribu Rupiah', $receiptService->terbilang(1500000));
        $this->assertEquals('Dua Belas Juta Tiga Ratus Empat Puluh Lima Ribu Enam Ratus Tujuh Puluh Delapan Rupiah', $receiptService->terbilang(12345678));
    }

    /**
     * BUSINESS LOGIC: Idempotensi generate tagihan massal
     */
    public function test_bulk_invoice_generation_is_idempotent(): void
    {
        $generator = app(InvoiceGeneratorService::class);

        $params = [
            'fee_type_id' => $this->feeTypeSpp->id,
            'period' => '2026-10',
            'due_date' => '2026-10-15',
            'target_type' => 'class',
            'class_id' => $this->class7A->id,
            'amount' => 500000,
        ];

        // Eksekusi 1: Membuat 2 tagihan untuk Student A & B
        $res1 = $generator->generateBulk($params, $this->bendahara);
        $this->assertEquals(2, $res1['created_count']);
        $this->assertEquals(0, $res1['skipped_count']);
        $this->assertEquals(2, Invoice::where('period', '2026-10')->count());

        // Eksekusi 2 (dengan parameter sama): Idempoten, 0 tagihan baru dibuat, 2 dilewati
        $res2 = $generator->generateBulk($params, $this->bendahara);
        $this->assertEquals(0, $res2['created_count']);
        $this->assertEquals(2, $res2['skipped_count']);
        $this->assertEquals(2, Invoice::where('period', '2026-10')->count());
    }

    /**
     * BUSINESS LOGIC: Perhitungan status invoice & tolak overpayment
     */
    public function test_invoice_status_calculation_and_overpayment_rejection(): void
    {
        $invoice = Invoice::create([
            'id' => (string) Str::uuid(),
            'invoice_number' => 'INV/2026/10/0001',
            'student_id' => $this->studentA->id,
            'period' => '2026-10',
            'due_date' => '2026-10-20',
            'total_amount' => 500000,
            'paid_amount' => 0,
            'status' => 'belum_bayar',
        ]);

        InvoiceItem::create([
            'id' => (string) Str::uuid(),
            'invoice_id' => $invoice->id,
            'fee_type_id' => $this->feeTypeSpp->id,
            'description' => 'SPP Oktober 2026',
            'amount' => 500000,
            'discount' => 0,
        ]);

        $this->assertEquals(500000, $invoice->remaining_amount);
        $this->assertEquals('belum_bayar', $invoice->status);

        // 1. Coba bayar melebihi sisa tagihan (Overpayment -> DITOLAK 422)
        $dummySlip = UploadedFile::fake()->create('slip_bayar.jpg', 300, 'image/jpeg');

        $resOver = $this->actingAs($this->guardianUserA)
            ->postJson("/api/v1/me/invoices/{$invoice->id}/payments", [
                'amount' => 600000, // Melebihi 500.000
                'channel' => 'Bank Jatim VA',
                'external_reference' => 'TRX-OVER-01',
                'payment_date' => '2026-10-15',
                'proof_file' => $dummySlip,
            ]);

        $resOver->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        // 2. Pembayaran sebagian (Rp 200.000)
        $resPart = $this->actingAs($this->guardianUserA)
            ->postJson("/api/v1/me/invoices/{$invoice->id}/payments", [
                'amount' => 200000,
                'channel' => 'Bank Jatim VA',
                'external_reference' => 'TRX-REF-001',
                'payment_date' => '2026-10-15',
                'proof_file' => UploadedFile::fake()->create('slip_part1.png', 400, 'image/png'),
            ]);

        $resPart->assertStatus(201);
        $paymentId1 = $resPart->json('data.id');

        // Sebelum diverifikasi, invoice masih belum_bayar
        $invoice->refresh();
        $this->assertEquals('belum_bayar', $invoice->status);
        $this->assertEquals(0, $invoice->paid_amount);

        // Bendahara verifikasi pembayaran 1
        $resVerify1 = $this->actingAs($this->bendahara)
            ->postJson("/api/v1/finance/payments/{$paymentId1}/verify", [
                'notes' => 'Bukti mutasi bank valid',
            ]);
        $resVerify1->assertStatus(200);

        // Invoice menjadi status 'sebagian' dengan terbayar 200.000, sisa 300.000
        $invoice->refresh();
        $this->assertEquals('sebagian', $invoice->status);
        $this->assertEquals(200000, $invoice->paid_amount);
        $this->assertEquals(300000, $invoice->remaining_amount);

        // 3. Pelunasan sisa tagihan (Rp 300.000)
        $resFinal = $this->actingAs($this->guardianUserA)
            ->postJson("/api/v1/me/invoices/{$invoice->id}/payments", [
                'amount' => 300000,
                'channel' => 'BSI Virtual Account',
                'external_reference' => 'TRX-REF-002',
                'payment_date' => '2026-10-16',
                'proof_file' => UploadedFile::fake()->create('slip_part2.pdf', 500, 'application/pdf'),
            ]);
        $resFinal->assertStatus(201);
        $paymentId2 = $resFinal->json('data.id');

        // Bendahara verifikasi pembayaran 2
        $resVerify2 = $this->actingAs($this->bendahara)
            ->postJson("/api/v1/finance/payments/{$paymentId2}/verify");
        $resVerify2->assertStatus(200);

        // Invoice lunas
        $invoice->refresh();
        $this->assertEquals('lunas', $invoice->status);
        $this->assertEquals(500000, $invoice->paid_amount);
        $this->assertEquals(0, $invoice->remaining_amount);
    }

    /**
     * SECURITY & ANTI-IDOR:
     * - Ortu A TIDAK BISA mengakses daftar tagihan anak Ortu B (403).
     * - Ortu A TIDAK BISA melihat invoice milik anak Ortu B (403).
     * - Siswa A TIDAK BISA melihat invoice milik Siswa B (403).
     */
    public function test_authorization_matrix_and_idor_prevention(): void
    {
        $invoiceB = Invoice::create([
            'id' => (string) Str::uuid(),
            'invoice_number' => 'INV/2026/10/0002',
            'student_id' => $this->studentB->id,
            'period' => '2026-10',
            'due_date' => '2026-10-25',
            'total_amount' => 500000,
            'paid_amount' => 0,
            'status' => 'belum_bayar',
        ]);

        // 1. Ortu A mencoba akses /me/children/{childB}/invoices -> 403 Forbidden
        $resChildIdor = $this->actingAs($this->guardianUserA)
            ->getJson("/api/v1/me/children/{$this->studentB->id}/invoices");
        $resChildIdor->assertStatus(403);

        // 2. Ortu A mencoba akses /me/invoices/{invoiceB} -> 403 Forbidden
        $resInvIdor = $this->actingAs($this->guardianUserA)
            ->getJson("/api/v1/me/invoices/{$invoiceB->id}");
        $resInvIdor->assertStatus(403);

        // 3. Siswa A mencoba akses invoice Siswa B -> 403 Forbidden
        $resStudentIdor = $this->actingAs($this->studentUserA)
            ->getJson("/api/v1/me/invoices/{$invoiceB->id}");
        $resStudentIdor->assertStatus(403);

        // 4. Guru biasa tanpa izin finance.* mencoba akses admin finance -> 403
        $resTeacher = $this->actingAs($this->guru)
            ->getJson('/api/v1/finance/invoices');
        $resTeacher->assertStatus(403);

        // 5. Bendahara sah BISA mengakses
        $resBendahara = $this->actingAs($this->bendahara)
            ->getJson('/api/v1/finance/invoices');
        $resBendahara->assertStatus(200);

        // 6. Ortu B sah BISA mengakses anaknya sendiri
        $resValid = $this->actingAs($this->guardianUserB)
            ->getJson("/api/v1/me/children/{$this->studentB->id}/invoices");
        $resValid->assertStatus(200);
    }

    /**
     * BUSINESS & AUDIT LOGIC:
     * - Pembayaran terverifikasi menerbitkan Kuitansi resmi KWT/...
     * - Pembayaran terverifikasi TIDAK BISA diedit/dihapus, hanya bisa di-void dengan alasan wajib.
     * - Void mengembalikan sisa tagihan pada invoice.
     */
    public function test_payment_verification_receipt_generation_and_void(): void
    {
        $invoice = Invoice::create([
            'id' => (string) Str::uuid(),
            'invoice_number' => 'INV/2026/10/0003',
            'student_id' => $this->studentA->id,
            'period' => '2026-10',
            'due_date' => '2026-10-30',
            'total_amount' => 500000,
            'paid_amount' => 0,
            'status' => 'belum_bayar',
        ]);

        $payment = Payment::create([
            'id' => (string) Str::uuid(),
            'invoice_id' => $invoice->id,
            'amount' => 500000,
            'channel' => 'Bank Jatim VA',
            'external_reference' => 'TRX-KWT-001',
            'payment_date' => '2026-10-10',
            'status' => 'menunggu_verifikasi',
            'submitted_by' => $this->guardianUserA->id,
        ]);

        // Verifikasi oleh bendahara
        $resVerify = $this->actingAs($this->bendahara)
            ->postJson("/api/v1/finance/payments/{$payment->id}/verify");

        $resVerify->assertStatus(200);
        $receiptNumber = $resVerify->json('data.receipt_number');
        $this->assertNotNull($receiptNumber);
        $this->assertStringStartsWith('KWT/', $receiptNumber);

        // Ortu & Siswa dapat mengambil kuitansi resmi
        $resReceipt = $this->actingAs($this->guardianUserA)
            ->getJson("/api/v1/me/payments/{$payment->id}/receipt");

        $resReceipt->assertStatus(200)
            ->assertJsonPath('data.receipt_number', $receiptNumber)
            ->assertJsonPath('data.amount_words', 'Lima Ratus Ribu Rupiah')
            ->assertJsonPath('data.student.name', 'Ahmad Santoso');

        // Invoice berstatus 'lunas'
        $invoice->refresh();
        $this->assertEquals('lunas', $invoice->status);

        // Lakukan VOID karena salah transfer / pembatalan resmi
        $resVoid = $this->actingAs($this->bendahara)
            ->postJson("/api/v1/finance/payments/{$payment->id}/void", [
                'reason' => 'Koreksi transaksi: dana diretur oleh bank pengirim',
            ]);

        $resVoid->assertStatus(200)
            ->assertJsonPath('data.status', 'void')
            ->assertJsonPath('data.rejection_reason', 'Koreksi transaksi: dana diretur oleh bank pengirim');

        // Invoice kembali menjadi 'belum_bayar'
        $invoice->refresh();
        $this->assertEquals('belum_bayar', $invoice->status);
        $this->assertEquals(0, $invoice->paid_amount);
        $this->assertEquals(500000, $invoice->remaining_amount);
    }

    /**
     * CONSOLE COMMAND: Pengingat tagihan jatuh tempo H-3
     */
    public function test_due_date_reminder_command(): void
    {
        // Buat invoice dengan jatuh tempo H-3 (3 hari dari sekarang)
        $targetDueDate = Carbon::today()->addDays(3)->toDateString();

        Invoice::create([
            'id' => (string) Str::uuid(),
            'invoice_number' => 'INV/2026/10/REM-01',
            'student_id' => $this->studentA->id,
            'period' => '2026-10',
            'due_date' => $targetDueDate,
            'total_amount' => 500000,
            'paid_amount' => 0,
            'status' => 'belum_bayar',
        ]);

        $exitCode = Artisan::call('finance:send-due-reminders', ['--days' => 3]);
        $this->assertEquals(0, $exitCode);

        // Pastikan notifikasi tersimpan
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->studentUserA->id,
            'notif_type' => 'finance_reminder',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->guardianUserA->id,
            'notif_type' => 'finance_reminder',
        ]);
    }

    /**
     * REVISI BATCH 3B:
     * - Slip dengan external_reference ganda ditolak saat status aktif (menunggu/terverifikasi).
     * - Slip yang berstatus 'ditolak' bisa diunggah ulang menggunakan external_reference yang sama.
     */
    public function test_duplicate_active_external_reference_is_rejected_and_rejected_slip_can_be_reuploaded(): void
    {
        $invoice = Invoice::create([
            'id' => (string) Str::uuid(),
            'invoice_number' => 'INV/2026/10/EXT-01',
            'student_id' => $this->studentA->id,
            'period' => '2026-10',
            'due_date' => '2026-10-30',
            'total_amount' => 500000,
            'paid_amount' => 0,
            'status' => 'belum_bayar',
        ]);

        $payload = [
            'amount' => 500000,
            'channel' => 'Bank Mandiri Livin',
            'external_reference' => 'LIVIN-REF-99999',
            'payment_date' => '2026-10-20',
            'proof_file' => UploadedFile::fake()->create('slip_mandiri.jpg', 300, 'image/jpeg'),
        ];

        // 1. Unggah pertama kali -> Berhasil (Status: menunggu_verifikasi)
        $resFirst = $this->actingAs($this->guardianUserA)
            ->postJson("/api/v1/me/invoices/{$invoice->id}/payments", $payload);
        $resFirst->assertStatus(201);
        $paymentId = $resFirst->json('data.id');

        // 2. Coba unggah ulang dengan nomor external_reference yang sama saat status masih menunggu_verifikasi -> DITOLAK 422
        $resDup = $this->actingAs($this->guardianUserA)
            ->postJson("/api/v1/me/invoices/{$invoice->id}/payments", $payload);
        $resDup->assertStatus(422)
            ->assertJsonValidationErrors(['external_reference']);

        // 3. Bendahara menolak slip pertama (misal karena gambar blur / tidak jelas)
        $resReject = $this->actingAs($this->bendahara)
            ->postJson("/api/v1/finance/payments/{$paymentId}/reject", [
                'reason' => 'Gambar slip blur dan tanggal transaksi tidak terbaca',
            ]);
        $resReject->assertStatus(200);

        // 4. Setelah ditolak, Ortu diperbolehkan mengunggah ulang slip dengan external_reference yang sama (misal foto yang lebih jelas)
        $resReupload = $this->actingAs($this->guardianUserA)
            ->postJson("/api/v1/me/invoices/{$invoice->id}/payments", [
                'amount' => 500000,
                'channel' => 'Bank Mandiri Livin',
                'external_reference' => 'LIVIN-REF-99999',
                'payment_date' => '2026-10-20',
                'proof_file' => UploadedFile::fake()->create('slip_mandiri_clear.png', 400, 'image/png'),
            ]);
        $resReupload->assertStatus(201);
    }

    /**
     * REVISI BATCH 3B: Siswa hanya dapat melihat tagihan (read-only) dan DILARANG mengunggah slip (403).
     */
    public function test_student_cannot_upload_payment_slip(): void
    {
        $invoice = Invoice::create([
            'id' => (string) Str::uuid(),
            'invoice_number' => 'INV/2026/10/STUDENT-RO-01',
            'student_id' => $this->studentA->id,
            'period' => '2026-10',
            'due_date' => '2026-10-30',
            'total_amount' => 500000,
            'paid_amount' => 0,
            'status' => 'belum_bayar',
        ]);

        $res = $this->actingAs($this->studentUserA)
            ->postJson("/api/v1/me/invoices/{$invoice->id}/payments", [
                'amount' => 500000,
                'channel' => 'BCA Mobile',
                'external_reference' => 'BCA-REF-11111',
                'payment_date' => '2026-10-20',
                'proof_file' => UploadedFile::fake()->create('slip.jpg', 200, 'image/jpeg'),
            ]);

        $res->assertStatus(403);
    }

    /**
     * REVISI BATCH 3B: Ortu A tidak bisa mengunggah slip untuk tagihan anak Ortu B (Anti-IDOR 403).
     */
    public function test_parent_cannot_upload_payment_slip_for_other_child(): void
    {
        $invoiceB = Invoice::create([
            'id' => (string) Str::uuid(),
            'invoice_number' => 'INV/2026/10/CHILD-B-01',
            'student_id' => $this->studentB->id,
            'period' => '2026-10',
            'due_date' => '2026-10-30',
            'total_amount' => 500000,
            'paid_amount' => 0,
            'status' => 'belum_bayar',
        ]);

        $res = $this->actingAs($this->guardianUserA)
            ->postJson("/api/v1/me/invoices/{$invoiceB->id}/payments", [
                'amount' => 500000,
                'channel' => 'BSI Mobile',
                'external_reference' => 'BSI-REF-22222',
                'payment_date' => '2026-10-20',
                'proof_file' => UploadedFile::fake()->create('slip.jpg', 200, 'image/jpeg'),
            ]);

        $res->assertStatus(403);
    }
}
