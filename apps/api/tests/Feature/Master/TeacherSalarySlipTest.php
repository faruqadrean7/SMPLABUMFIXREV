<?php

namespace Tests\Feature\Master;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSalarySlip;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeacherSalarySlipTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $teacherUser;

    protected User $otherTeacherUser;

    protected Teacher $teacher;

    protected Teacher $otherTeacher;

    protected Role $adminRole;

    protected Role $teacherRole;

    protected Permission $permManageTeacher;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->permManageTeacher = Permission::create([
            'code' => 'master.teacher.manage',
            'description' => 'Mengelola data guru dan staf',
        ]);

        $this->adminRole = Role::create([
            'code' => 'super_admin',
            'name' => 'Super Administrator',
            'is_system' => true,
        ]);
        $this->adminRole->permissions()->attach([$this->permManageTeacher->id]);

        $this->teacherRole = Role::create([
            'code' => 'teacher',
            'name' => 'Guru Mata Pelajaran',
            'is_system' => false,
        ]);

        $this->adminUser = User::create([
            'username' => 'admin_keuangan',
            'email' => 'finance@labschool.sch.id',
            'password_hash' => Hash::make('AdminSecret123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($this->adminRole->id);

        $this->teacherUser = User::create([
            'username' => 'budi_santoso',
            'email' => 'budi@labschool.sch.id',
            'password_hash' => Hash::make('TeacherSecret123!'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($this->teacherRole->id);

        $this->otherTeacherUser = User::create([
            'username' => 'siti_aminah',
            'email' => 'siti@labschool.sch.id',
            'password_hash' => Hash::make('TeacherSecret123!'),
            'status' => 'active',
        ]);
        $this->otherTeacherUser->roles()->attach($this->teacherRole->id);

        $this->teacher = Teacher::create([
            'user_id' => $this->teacherUser->id,
            'nip' => '198503152009041001',
            'full_name' => 'Budi Santoso, S.Kom., M.T.',
            'position' => 'Guru Informatika',
            'status' => 'tetap',
        ]);

        $this->otherTeacher = Teacher::create([
            'user_id' => $this->otherTeacherUser->id,
            'nip' => '198807202010122002',
            'full_name' => 'Siti Aminah, M.Pd.',
            'position' => 'Guru Bahasa Inggris',
            'status' => 'tetap',
        ]);
    }

    public function test_admin_can_upload_salary_slip_with_pdf_file(): void
    {
        $fakePdf = UploadedFile::fake()->create('slip_gaji_oktober.pdf', 150, 'application/pdf');

        $response = $this->actingAs($this->adminUser)
            ->post("/api/v1/teachers/{$this->teacher->id}/salary-slips", [
                'period_month' => 10,
                'period_year' => 2026,
                'payment_date' => '2026-10-10',
                'basic_salary' => 4500000,
                'allowances' => 1500000,
                'deductions' => 200000,
                'net_salary' => 5800000,
                'notes' => 'Transfer Bank Jatim Syariah',
                'file' => $fakePdf,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.period_month', 10);
        $response->assertJsonPath('data.period_year', 2026);
        $response->assertJsonPath('data.net_salary', '5800000.00');
        $this->assertNotNull($response->json('data.file_id'));

        $this->assertDatabaseHas('teacher_salary_slips', [
            'teacher_id' => $this->teacher->id,
            'period_month' => 10,
            'period_year' => 2026,
            'net_salary' => 5800000,
        ]);
    }

    public function test_admin_cannot_upload_duplicate_salary_slip_for_same_period(): void
    {
        TeacherSalarySlip::create([
            'teacher_id' => $this->teacher->id,
            'period_month' => 10,
            'period_year' => 2026,
            'basic_salary' => 4500000,
            'net_salary' => 4500000,
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/teachers/{$this->teacher->id}/salary-slips", [
                'period_month' => 10,
                'period_year' => 2026,
                'basic_salary' => 4500000,
            ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_list_and_download_teacher_salary_slip(): void
    {
        $fakePdf = UploadedFile::fake()->create('slip_september.pdf', 100, 'application/pdf');

        $uploadRes = $this->actingAs($this->adminUser)
            ->post("/api/v1/teachers/{$this->teacher->id}/salary-slips", [
                'period_month' => 9,
                'period_year' => 2026,
                'basic_salary' => 4000000,
                'file' => $fakePdf,
            ]);
        $slipId = $uploadRes->json('data.id');

        // List
        $listRes = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/teachers/{$this->teacher->id}/salary-slips");
        $listRes->assertStatus(200);
        $this->assertCount(1, $listRes->json('data'));

        // Download
        $downloadRes = $this->actingAs($this->adminUser)
            ->get("/api/v1/teachers/{$this->teacher->id}/salary-slips/{$slipId}/download");
        $downloadRes->assertStatus(200);
    }

    public function test_admin_can_delete_salary_slip(): void
    {
        $slip = TeacherSalarySlip::create([
            'teacher_id' => $this->teacher->id,
            'period_month' => 8,
            'period_year' => 2026,
            'basic_salary' => 4000000,
            'net_salary' => 4000000,
            'created_by' => $this->adminUser->id,
        ]);

        $res = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/teachers/{$this->teacher->id}/salary-slips/{$slip->id}");

        $res->assertStatus(200);
        $this->assertSoftDeleted('teacher_salary_slips', ['id' => $slip->id]);
    }

    public function test_teacher_can_view_and_download_own_salary_slips(): void
    {
        $fakePdf = UploadedFile::fake()->create('slip_oktober_budi.pdf', 120, 'application/pdf');

        $uploadRes = $this->actingAs($this->adminUser)
            ->post("/api/v1/teachers/{$this->teacher->id}/salary-slips", [
                'period_month' => 10,
                'period_year' => 2026,
                'basic_salary' => 5000000,
                'net_salary' => 5000000,
                'file' => $fakePdf,
            ]);
        $slipId = $uploadRes->json('data.id');

        // Teacher self-service
        $mySlipsRes = $this->actingAs($this->teacherUser)
            ->getJson('/api/v1/teacher/me/salary-slips');

        $mySlipsRes->assertStatus(200);
        $this->assertCount(1, $mySlipsRes->json('data'));
        $mySlipsRes->assertJsonPath('data.0.period_month', 10);

        // Download own
        $dlRes = $this->actingAs($this->teacherUser)
            ->get("/api/v1/teacher/me/salary-slips/{$slipId}/download");
        $dlRes->assertStatus(200);
    }

    public function test_teacher_cannot_access_or_download_other_teachers_salary_slip_preventing_idor(): void
    {
        $fakePdf = UploadedFile::fake()->create('slip_budi.pdf', 100, 'application/pdf');

        $uploadRes = $this->actingAs($this->adminUser)
            ->post("/api/v1/teachers/{$this->teacher->id}/salary-slips", [
                'period_month' => 10,
                'period_year' => 2026,
                'basic_salary' => 5000000,
                'net_salary' => 5000000,
                'file' => $fakePdf,
            ]);
        $slipId = $uploadRes->json('data.id');

        // Other teacher attempts to list Budi's slips -> 403 Forbidden
        $idorListRes = $this->actingAs($this->otherTeacherUser)
            ->getJson("/api/v1/teachers/{$this->teacher->id}/salary-slips");
        $idorListRes->assertStatus(403);

        // Other teacher attempts to download Budi's slip -> 403 Forbidden
        $idorDlRes = $this->actingAs($this->otherTeacherUser)
            ->get("/api/v1/teachers/{$this->teacher->id}/salary-slips/{$slipId}/download");
        $idorDlRes->assertStatus(403);
    }

    public function test_teaching_hours_recap_calculates_correct_jjm_and_certification_status(): void
    {
        $academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'aktif',
        ]);

        $semester = Semester::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'Ganjil 2026/2027',
            'semester' => 'ganjil',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'status' => 'aktif',
        ]);

        $gradeLevel = GradeLevel::create([
            'level' => 7,
            'name' => 'Kelas 7',
        ]);

        $classA = SchoolClass::create([
            'academic_year_id' => $academicYear->id,
            'grade_level_id' => $gradeLevel->id,
            'name' => 'VII-A',
        ]);

        $classB = SchoolClass::create([
            'academic_year_id' => $academicYear->id,
            'grade_level_id' => $gradeLevel->id,
            'name' => 'VII-B',
        ]);

        $subject = Subject::create([
            'code' => 'INF-07',
            'name' => 'Informatika',
            'subject_group' => 'wajib',
        ]);

        // Assignment 1: 12 JP
        TeachingAssignment::create([
            'semester_id' => $semester->id,
            'class_id' => $classA->id,
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher->id,
            'hours_per_week' => 12,
        ]);

        // Assignment 2: 14 JP -> Total = 26 JP (>= 24 JP target met)
        TeachingAssignment::create([
            'semester_id' => $semester->id,
            'class_id' => $classB->id,
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher->id,
            'hours_per_week' => 14,
        ]);

        $recapRes = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/teachers/{$this->teacher->id}/teaching-recap");

        $recapRes->assertStatus(200);
        $recapRes->assertJsonPath('data.total_hours', 26);
        $recapRes->assertJsonPath('data.target_hours', 24);
        $recapRes->assertJsonPath('data.is_target_met', true);
        $recapRes->assertJsonPath('data.difference', 2);
        $this->assertCount(2, $recapRes->json('data.assignments'));

        // Teacher self-service recap
        $myRecapRes = $this->actingAs($this->teacherUser)
            ->getJson('/api/v1/teacher/me/teaching-recap');
        $myRecapRes->assertStatus(200);
        $myRecapRes->assertJsonPath('data.total_hours', 26);
    }
}
