<?php

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\FeeType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherSalarySlip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase1SecurityP0Test extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $studentUser;

    protected User $teacherA;

    protected User $teacherB;

    protected Teacher $teacherModelA;

    protected Teacher $teacherModelB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        // Create core roles
        $roleSuperAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Administrator', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);

        // Create core permissions
        $permissions = [
            'notification.broadcast',
            'dashboard.executive',
            'executive.dashboard.view',
            'reports.view',
            'executive.approval.manage',
            'student.point.read',
            'student.point.write',
            'academic.grade.read',
            'academic.grade.write',
            'academic.report.generate',
            'cbt.bank.write',
            'cbt.exam.manage',
            'cbt.proctor.action',
            'counseling.case.read',
            'counseling.case.write',
            'bk.counseling.manage',
            'complaint.read',
            'complaint.manage',
            'master.teacher.manage',
        ];

        foreach ($permissions as $code) {
            Permission::firstOrCreate(['code' => $code], ['description' => $code]);
        }

        // Users
        $this->superAdmin = User::create([
            'username' => 'superadmin_sec',
            'email' => 'superadmin_sec@labschool.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->superAdmin->roles()->attach($roleSuperAdmin->id);

        $this->studentUser = User::create([
            'username' => 'student_sec',
            'email' => 'student_sec@labschool.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($roleStudent->id);

        $this->teacherA = User::create([
            'username' => 'teacher_a',
            'email' => 'teacher_a@labschool.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->teacherA->roles()->attach($roleTeacher->id);

        $this->teacherB = User::create([
            'username' => 'teacher_b',
            'email' => 'teacher_b@labschool.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->teacherB->roles()->attach($roleTeacher->id);

        $this->teacherModelA = Teacher::create([
            'user_id' => $this->teacherA->id,
            'nip' => '198501012010011001',
            'full_name' => 'Guru A, S.Pd',
            'gender' => 'L',
        ]);

        $this->teacherModelB = Teacher::create([
            'user_id' => $this->teacherB->id,
            'nip' => '198602022010022002',
            'full_name' => 'Guru B, S.Pd',
            'gender' => 'P',
        ]);
    }

    /**
     * SEC-01: Unauthenticated requests to P0 endpoints return 401.
     */
    public function test_sec_01_unauthenticated_requests_to_p0_routes_return_401(): void
    {
        $this->postJson('/api/v1/notifications/broadcast', [])->assertStatus(401);
        $this->getJson('/api/v1/dashboard/executive')->assertStatus(401);
        $this->getJson('/api/v1/reports/attendance')->assertStatus(401);
        $this->getJson('/api/v1/approvals/pending')->assertStatus(401);
        $this->getJson('/api/v1/counseling/cases')->assertStatus(401);
        $this->postJson('/api/v1/counseling/cases', [])->assertStatus(401);
        $this->getJson('/api/v1/complaints')->assertStatus(401);
        $this->postJson('/api/v1/point-types', [])->assertStatus(401);
        $this->postJson('/api/v1/progress-reports/generate', [])->assertStatus(401);
        $this->postJson('/api/v1/report-cards/generate', [])->assertStatus(401);
    }

    /**
     * SEC-01: Unauthorized role (student) receives 403 on critical management endpoints.
     */
    public function test_sec_01_student_is_denied_access_403_on_p0_routes(): void
    {
        // 1. Notifications broadcast & templates & logs
        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/notifications/broadcast', ['title' => 'Test', 'body' => 'Test'])
            ->assertStatus(403);

        $this->actingAs($this->studentUser, 'sanctum')
            ->getJson('/api/v1/notification-templates')
            ->assertStatus(403);

        $this->actingAs($this->studentUser, 'sanctum')
            ->getJson('/api/v1/notification-logs')
            ->assertStatus(403);

        // 2. Executive Dashboard, Reports, Approvals
        $this->actingAs($this->studentUser, 'sanctum')
            ->getJson('/api/v1/dashboard/executive')
            ->assertStatus(403);

        $this->actingAs($this->studentUser, 'sanctum')
            ->getJson('/api/v1/reports/attendance')
            ->assertStatus(403);

        $this->actingAs($this->studentUser, 'sanctum')
            ->getJson('/api/v1/approvals/pending')
            ->assertStatus(403);

        // 3. Counseling BK & Early Warnings
        $this->actingAs($this->studentUser, 'sanctum')
            ->getJson('/api/v1/counseling/cases')
            ->assertStatus(403);

        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/counseling/cases', ['title' => 'Test'])
            ->assertStatus(403);

        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/early-warnings/evaluate', [])
            ->assertStatus(403);

        // 4. Complaints / Whistleblowing management
        $this->actingAs($this->studentUser, 'sanctum')
            ->getJson('/api/v1/complaints')
            ->assertStatus(403);

        $this->actingAs($this->studentUser, 'sanctum')
            ->patchJson('/api/v1/complaints/dummy-id/status', ['status' => 'diproses'])
            ->assertStatus(403);

        // 5. Point types & thresholds configuration
        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/point-types', ['name' => 'Test'])
            ->assertStatus(403);

        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/point-thresholds', ['threshold' => 10])
            ->assertStatus(403);

        // 6. Grade period locking & report cards generation
        $this->actingAs($this->studentUser, 'sanctum')
            ->patchJson('/api/v1/grade-periods/dummy-id/lock', [])
            ->assertStatus(403);

        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/report-cards/generate', [])
            ->assertStatus(403);

        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/progress-reports/generate', [])
            ->assertStatus(403);

        // 7. CBT exam results, item analysis, auto grade
        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/exams/dummy-id/auto-grade', [])
            ->assertStatus(403);

        $this->actingAs($this->studentUser, 'sanctum')
            ->getJson('/api/v1/exams/dummy-id/results')
            ->assertStatus(403);

        $this->actingAs($this->studentUser, 'sanctum')
            ->getJson('/api/v1/exams/dummy-id/item-analysis')
            ->assertStatus(403);
    }

    /**
     * SEC-01: Authorized super_admin can access protected P0 routes.
     */
    public function test_sec_01_super_admin_can_access_p0_routes(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/dashboard/executive')
            ->assertOk();

        $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/notification-templates')
            ->assertOk();

        $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/point-types')
            ->assertOk();

        $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/complaints')
            ->assertOk();
    }

    /**
     * SEC-02: Telemetry endpoint validates inputs, rejects spoofed sensitive actions, tags source=client.
     */
    public function test_sec_02_telemetry_validates_inputs_and_tags_client_source(): void
    {
        // 1. Valid telemetry with ui. prefix -> success and tags source=client
        $response = $this->actingAs($this->teacherA, 'sanctum')
            ->postJson('/api/v1/audit-logs', [
                'action' => 'ui.sidebar.click',
                'entity' => 'NavigationSidebar',
                'new_values' => ['target' => '/dashboard'],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.source', 'client')
            ->assertJsonPath('data.is_authoritative', false);

        $log = AuditLog::where('action', 'ui.sidebar.click')->first();
        $this->assertNotNull($log);
        $this->assertFalse((bool) $log->is_authoritative);
        $this->assertEquals('client', $log->new_value['source'] ?? null);

        // 2. Spoofed sensitive action -> rejected with 422
        $spoofedResponse = $this->actingAs($this->teacherA, 'sanctum')
            ->postJson('/api/v1/audit-logs', [
                'action' => 'academic.grade.update',
                'entity' => 'Grade',
                'new_values' => ['score' => 100],
            ]);

        $spoofedResponse->assertStatus(422)
            ->assertJsonPath('error.code', 'UNPROCESSABLE_ENTITY');

        // 3. Spoofed sensitive entity -> rejected with 422
        $spoofedEntityResponse = $this->actingAs($this->teacherA, 'sanctum')
            ->postJson('/api/v1/audit-logs', [
                'action' => 'ui.click',
                'entity' => 'UserRole',
                'new_values' => ['role' => 'super_admin'],
            ]);

        $spoofedEntityResponse->assertStatus(422)
            ->assertJsonPath('error.code', 'UNPROCESSABLE_ENTITY');

        // 4. Missing action or invalid prefix -> rejected with 422
        $invalidActionResponse = $this->actingAs($this->teacherA, 'sanctum')
            ->postJson('/api/v1/audit-logs', [
                'action' => 'system.core.reboot',
                'entity' => 'System',
            ]);

        $invalidActionResponse->assertStatus(422)
            ->assertJsonPath('error.code', 'UNPROCESSABLE_ENTITY');
    }

    /**
     * SEC-03: IDOR protection on teacher salary slip download.
     */
    public function test_sec_03_teacher_cannot_download_other_teacher_salary_slip_403(): void
    {
        $slipB = TeacherSalarySlip::create([
            'teacher_id' => $this->teacherModelB->id,
            'period_month' => 10,
            'period_year' => 2026,
            'basic_salary' => 4000000,
            'net_salary' => 4500000,
            'status' => 'published',
            'created_by' => $this->superAdmin->id,
        ]);

        // Teacher A attempts to download Teacher B's salary slip -> MUST return 403
        $response = $this->actingAs($this->teacherA, 'sanctum')
            ->getJson("/api/v1/salary-slips/{$slipB->id}/download");

        $response->assertStatus(403);

        // Student attempts to download Teacher B's salary slip -> MUST return 403
        $studentResponse = $this->actingAs($this->studentUser, 'sanctum')
            ->getJson("/api/v1/salary-slips/{$slipB->id}/download");

        $studentResponse->assertStatus(403);
    }

    /**
     * SEC-03: IDOR protection on payment receipt.
     */
    public function test_sec_03_user_cannot_access_other_user_payment_receipt_403(): void
    {
        $studentUserA = User::create([
            'username' => 'student_a',
            'email' => 'student_a@labschool.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $studentUserA->roles()->attach(Role::where('code', 'student')->first()->id);

        $studentModelA = Student::create([
            'user_id' => $studentUserA->id,
            'nis' => '10001',
            'nisn' => '0011223344',
            'nik_enc' => '3171010101010001',
            'full_name' => 'Siswa A',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2009-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $feeType = FeeType::create([
            'code' => 'SPP-2026',
            'name' => 'SPP Bulanan 2026',
            'type' => 'spp',
            'default_amount' => 350000,
            'is_active' => true,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-2026-TEST-001',
            'student_id' => $studentModelA->id,
            'fee_type_id' => $feeType->id,
            'period' => '2026-10',
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(10),
            'total_amount' => 350000,
            'paid_amount' => 350000,
            'status' => 'lunas',
        ]);

        $paymentA = Payment::create([
            'invoice_id' => $invoice->id,
            'payment_method' => 'bank_transfer',
            'external_reference' => 'EXT-REF-TEST-001',
            'payment_date' => Carbon::now()->toDateString(),
            'amount' => 350000,
            'status' => 'terverifikasi',
            'submitted_by' => $studentUserA->id,
            'receipt_number' => 'RCP-2026-0001',
            'verified_at' => Carbon::now(),
            'verified_by' => $this->superAdmin->id,
        ]);

        // Student A accesses own receipt -> OK 200
        $responseA = $this->actingAs($studentUserA, 'sanctum')
            ->getJson("/api/v1/me/payments/{$paymentA->id}/receipt");

        $responseA->assertOk()
            ->assertJsonPath('data.receipt_number', 'RCP-2026-0001');

        // Student B (different student) attempts to access Student A's receipt -> MUST return 403
        $studentUserB = User::create([
            'username' => 'student_b',
            'email' => 'student_b@labschool.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $studentUserB->roles()->attach(Role::where('code', 'student')->first()->id);

        $responseB = $this->actingAs($studentUserB, 'sanctum')
            ->getJson("/api/v1/me/payments/{$paymentA->id}/receipt");

        $responseB->assertStatus(403);

        // Non-existent payment ID -> returns 404
        $notFoundResponse = $this->actingAs($studentUserA, 'sanctum')
            ->getJson('/api/v1/me/payments/00000000-0000-0000-0000-000000000000/receipt');

        $notFoundResponse->assertStatus(404);
    }
}
