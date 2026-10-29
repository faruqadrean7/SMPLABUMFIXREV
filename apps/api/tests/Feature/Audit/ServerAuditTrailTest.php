<?php

namespace Tests\Feature\Audit;

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\ClassStudent;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradeLevel;
use App\Models\GradePeriod;
use App\Models\Permission;
use App\Models\ReportCard;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class ServerAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $teacherUser;

    protected Teacher $teacher;

    protected Student $student;

    protected SchoolClass $class;

    protected Semester $semester;

    protected GradePeriod $period;

    protected GradeComponent $component;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $teacherRole = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $permGrade = Permission::firstOrCreate(['code' => 'academic.grade.write'], ['name' => 'Tulis Nilai', 'module' => 'academic']);
        $permReport = Permission::firstOrCreate(['code' => 'academic.report.generate'], ['name' => 'Kelola Rapor', 'module' => 'academic']);
        $teacherRole->permissions()->syncWithoutDetaching([$permGrade->id]);
        $adminRole->permissions()->syncWithoutDetaching([$permGrade->id, $permReport->id]);

        $this->adminUser = User::create([
            'username' => 'admin_audit',
            'email' => 'admin_audit@sekolah.sch.id',
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($adminRole->id);

        $this->teacherUser = User::create([
            'username' => 'guru_audit',
            'email' => 'guru_audit@sekolah.sch.id',
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($teacherRole->id);

        $this->teacher = Teacher::create([
            'user_id' => $this->teacherUser->id,
            'nip' => '198501012010011001',
            'full_name' => 'Budi Santoso, M.Pd.',
            'status' => 'aktif',
        ]);

        $academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $this->semester = Semester::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'Semester Ganjil 2026/2027',
            'start_date' => '2026-07-15',
            'end_date' => '2026-12-20',
            'is_active' => true,
        ]);

        $gradeLevel = GradeLevel::create([
            'name' => 'Kelas 7',
            'order' => 7,
        ]);

        $this->class = SchoolClass::create([
            'grade_level_id' => $gradeLevel->id,
            'name' => '7-A',
            'academic_year_id' => $this->semester->academic_year_id ?? null,
            'homeroom_teacher_id' => $this->teacher->id,
            'capacity' => 32,
        ]);

        $studentUser = User::create([
            'username' => 'siswa_audit',
            'email' => 'siswa_audit@sekolah.sch.id',
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $this->student = Student::create([
            'user_id' => $studentUser->id,
            'nis' => '10001',
            'nisn' => '0012345678',
            'nik_enc' => '3578010101010001',
            'full_name' => 'Ahmad Siswa',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-05-10',
            'religion' => 'Islam',
            'address' => 'Jl. Veteran No. 1 Malang',
            'entry_date' => '2026-07-15',
            'status' => 'aktif',
        ]);

        ClassStudent::create([
            'class_id' => $this->class->id,
            'student_id' => $this->student->id,
            'joined_at' => '2026-07-15',
        ]);

        $subject = Subject::create([
            'code' => 'MAT-7',
            'name' => 'Matematika Kelas 7',
            'subject_group' => 'A',
        ]);

        TeachingAssignment::create([
            'teacher_id' => $this->teacher->id,
            'subject_id' => $subject->id,
            'class_id' => $this->class->id,
            'semester_id' => $this->semester->id,
            'academic_year_id' => $this->semester->academic_year_id ?? null,
        ]);

        $this->period = GradePeriod::create([
            'semester_id' => $this->semester->id,
            'month' => 8,
            'status' => 'open',
        ]);

        $this->component = GradeComponent::create([
            'subject_id' => $subject->id,
            'semester_id' => $this->semester->id,
            'class_id' => $this->class->id,
            'code' => 'UH-1',
            'name' => 'Ulangan Harian 1',
            'weight' => 20,
        ]);
    }

    /**
     * GAP-06: 1. Ubah Nilai menghasilkan audit otoritatif di server dengan actor dari auth, diff, dan reason.
     */
    public function test_gap_06_grade_update_creates_authoritative_server_audit(): void
    {
        // Nilai awal
        $grade = Grade::create([
            'student_id' => $this->student->id,
            'component_id' => $this->component->id,
            'period_id' => $this->period->id,
            'value' => 75.0,
            'source' => 'manual',
            'entered_by' => $this->teacherUser->id,
        ]);

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->withHeaders(['X-Request-Id' => 'req-audit-test-123'])
            ->postJson('/api/v1/grades', [
                'student_id' => $this->student->id,
                'component_id' => $this->component->id,
                'period_id' => $this->period->id,
                'value' => 88.5,
                'reason' => 'Koreksi kesalahan penilaian tugas mandiri siswa',
                // Coba memalsukan actor_id lewat payload (harus diabaikan server)
                'actor_id' => '00000000-0000-0000-0000-000000000000',
            ]);

        $this->assertTrue(in_array($response->status(), [200, 201]), "Expected 200 or 201, got {$response->status()}");

        // Verifikasi audit log dibuat di server
        $audit = AuditLog::where('action', 'academic.grade.update')
            ->orWhere('action', 'grade.entered')
            ->where('entity_id', $grade->id)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($audit, 'Audit log untuk perubahan nilai harus dicatat oleh server.');
        $this->assertEquals($this->teacherUser->id, $audit->actor_id, 'actor_id harus diambil dari pengguna yang sedang login, bukan dari payload.');
        $this->assertEquals('teacher', $audit->actor_role);
        $this->assertEquals('req-audit-test-123', $audit->request_id);
        $this->assertEquals('Koreksi kesalahan penilaian tugas mandiri siswa', $audit->reason);
        $this->assertTrue((bool) $audit->is_authoritative);
    }

    /**
     * GAP-06: 1b. Perubahan nilai yang sudah ada WAJIB menyertakan alasan (reason).
     */
    public function test_gap_06_grade_update_requires_reason_for_existing_grades(): void
    {
        // Nilai awal
        Grade::create([
            'student_id' => $this->student->id,
            'component_id' => $this->component->id,
            'period_id' => $this->period->id,
            'value' => 75.0,
            'source' => 'manual',
            'entered_by' => $this->teacherUser->id,
        ]);

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/grades', [
                'student_id' => $this->student->id,
                'component_id' => $this->component->id,
                'period_id' => $this->period->id,
                'value' => 90.0,
                // Tanpa reason!
            ]);

        $this->assertTrue(in_array($response->status(), [400, 422]), "Expected 400 or 422, got {$response->status()}");
        $response->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /**
     * GAP-06: 2. Kunci / Buka Rapor (ReportCardService): Buka kunci WAJIB alasan, audit ditulis dalam transaksi.
     */
    public function test_gap_06_report_card_unlock_requires_reason(): void
    {
        $report = ReportCard::create([
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
            'status' => 'terkunci',
            'data_snapshot' => ['grades' => []],
            'verify_token' => Str::random(32),
            'locked_at' => now(),
        ]);

        // Coba buka kunci tanpa alasan -> tolak 422
        $responseFail = $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/v1/report-cards/{$report->id}/lock", [
                'is_locked' => false,
            ]);

        $responseFail->assertStatus(422);

        // Buka kunci dengan alasan -> sukses 200 dan menghasilkan audit
        $responseSuccess = $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/v1/report-cards/{$report->id}/lock", [
                'is_locked' => false,
                'reason' => 'Perbaikan nilai susulan oleh dewan guru',
            ]);

        $responseSuccess->assertStatus(200);

        $audit = AuditLog::where('entity_id', $report->id)
            ->where(function ($q) {
                $q->where('action', 'academic.report_card.unlock')
                    ->orWhere('action', 'report_card.unlock');
            })
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals('Perbaikan nilai susulan oleh dewan guru', $audit->reason);
        $this->assertEquals($this->adminUser->id, $audit->actor_id);
    }

    /**
     * GAP-06: 3. Persetujuan Eksekutif: Penolakan WAJIB alasan dan mencatat audit di server.
     */
    public function test_gap_06_approval_rejection_requires_reason(): void
    {
        $report = ReportCard::create([
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
            'status' => 'menunggu_pengesahan',
            'data_snapshot' => ['grades' => []],
            'verify_token' => Str::random(32),
        ]);

        // Tolak tanpa alasan -> tolak 422
        $responseFail = $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/v1/report-cards/{$report->id}/approve", [
                'status' => 'ditolak',
            ]);

        $responseFail->assertStatus(422);

        // Tolak dengan catatan/alasan -> sukses
        $responseSuccess = $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/v1/report-cards/{$report->id}/approve", [
                'status' => 'ditolak',
                'note' => 'Capaian ekstrakurikuler belum lengkap',
            ]);

        $responseSuccess->assertStatus(200);

        $audit = AuditLog::where('entity_id', $report->id)
            ->where('action', 'like', '%approve%')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals($this->adminUser->id, $audit->actor_id);
    }

    /**
     * GAP-06: 4. Penetapan Role: Menghasilkan audit otoritatif di server dengan rekaman diff old vs new.
     */
    public function test_gap_06_role_assignment_creates_authoritative_server_audit(): void
    {
        $targetUser = User::create([
            'username' => 'target_user_audit',
            'email' => 'target_audit@sekolah.sch.id',
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);
        $roleTeacher = Role::where('code', 'teacher')->first();
        $roleHomeroom = Role::firstOrCreate(['code' => 'homeroom'], ['name' => 'Wali Kelas', 'is_system' => true]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/v1/users/{$targetUser->id}/roles", [
                'roles' => [
                    ['role_id' => $roleTeacher->id],
                    ['role_id' => $roleHomeroom->id, 'scope_class_id' => $this->class->id],
                ],
                'reason' => 'Penugasan wali kelas tahun ajaran baru',
            ]);

        $response->assertStatus(200);

        $audit = AuditLog::where('entity_id', $targetUser->id)
            ->where(function ($q) {
                $q->where('action', 'auth.role.assign')
                    ->orWhere('action', 'user.roles_assigned');
            })
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals($this->adminUser->id, $audit->actor_id);
        $this->assertEquals('Penugasan wali kelas tahun ajaran baru', $audit->reason);
        $this->assertContains('teacher', $audit->new_values['roles']);
        $this->assertContains('homeroom', $audit->new_values['roles']);
    }

    /**
     * GAP-06: 5. Ekspor data massal menghasilkan log audit di server.
     */
    public function test_gap_06_bulk_data_export_creates_server_audit(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->get('/api/v1/reports/attendance/export');

        $response->assertStatus(200);

        $audit = AuditLog::where('action', 'like', '%export%')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals($this->adminUser->id, $audit->actor_id);
        $this->assertEquals('Report_attendance', $audit->entity);
    }

    /**
     * GAP-06: 6. Klien dilarang memalsukan 5 aksi sensitif lewat POST /api/v1/audit-logs.
     */
    public function test_gap_06_client_cannot_post_sensitive_actions_to_audit_logs(): void
    {
        // Mencoba mengirim aksi sensitif dari frontend -> HARUS ditolak 422
        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/audit-logs', [
                'action' => 'academic.grade.update',
                'entity' => 'Grade',
                'entity_id' => '1234',
                'old_values' => ['score' => 60],
                'new_values' => ['score' => 100],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'UNPROCESSABLE_ENTITY');

        // Mengirim telemetri UI yang sah -> diizinkan dan ditandai tidak otoritatif
        $telemetryResponse = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/audit-logs', [
                'action' => 'ui.telemetry.button_click',
                'entity' => 'GradeModal',
                'new_values' => ['button' => 'edit_grade'],
            ]);

        $telemetryResponse->assertStatus(200);

        $telemetryAudit = AuditLog::where('action', 'ui.telemetry.button_click')->first();
        $this->assertNotNull($telemetryAudit);
        $this->assertFalse((bool) $telemetryAudit->is_authoritative, 'Audit dari klien harus ditandai tidak otoritatif.');
    }

    /**
     * GAP-06: 7. Audit log bersifat append-only: tidak boleh ada route PUT/DELETE pada /audit-logs/{id}.
     */
    public function test_gap_06_audit_logs_cannot_be_updated_or_deleted(): void
    {
        $audit = AuditLog::create([
            'actor_id' => $this->adminUser->id,
            'action' => 'system.test',
            'entity' => 'System',
            'created_at' => now(),
        ]);

        $putResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/v1/audit-logs/{$audit->id}", ['action' => 'modified.action']);
        $this->assertTrue(in_array($putResponse->status(), [404, 405]));

        $deleteResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/v1/audit-logs/{$audit->id}");
        $this->assertTrue(in_array($deleteResponse->status(), [404, 405]));
    }

    /**
     * GAP-06: 8. Redaksi data sensitif (password, token, dll.) pada audit log.
     */
    public function test_gap_06_sensitive_fields_are_redacted_in_audit(): void
    {
        $auditService = app(AuditLogService::class);
        $request = Request::create('/test', 'POST', [], [], [], [
            'HTTP_X_REQUEST_ID' => 'test-redact-1',
        ]);
        $request->setUserResolver(fn () => $this->adminUser);

        $audit = $auditService->record(
            action: 'test.sensitive_action',
            entity: 'User',
            entityId: $this->adminUser->id,
            oldValues: ['password_hash' => '$2y$12$secret123', 'email' => 'admin@test.com'],
            newValues: ['password' => 'newSecret456', 'token' => 'plain_text_token_abc'],
            reason: 'Testing redaction',
            request: $request
        );

        $this->assertEquals('[REDACTED]', $audit->old_values['password_hash']);
        $this->assertEquals('admin@test.com', $audit->old_values['email']);
        $this->assertEquals('[REDACTED]', $audit->new_values['password']);
        $this->assertEquals('[REDACTED]', $audit->new_values['token']);
    }
}
