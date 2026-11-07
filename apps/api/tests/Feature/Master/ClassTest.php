<?php

namespace Tests\Feature\Master;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Major;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClassTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected Role $adminRole;

    protected Role $guestRole;

    protected Permission $permManageClass;

    protected AcademicYear $academicYear;

    protected GradeLevel $gradeLevel10;

    protected GradeLevel $gradeLevel11;

    protected Major $majorIpa;

    protected Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        // Permission
        $this->permManageClass = Permission::create([
            'code' => 'master.class.manage',
            'description' => 'Mengelola kelas dan rombel',
        ]);

        // Roles
        $this->adminRole = Role::create([
            'code' => 'super_admin',
            'name' => 'Super Administrator',
            'is_system' => true,
        ]);
        $this->adminRole->permissions()->attach([$this->permManageClass->id]);

        $this->guestRole = Role::create([
            'code' => 'guest',
            'name' => 'Tamu',
            'is_system' => false,
        ]);

        // Users
        $this->adminUser = User::create([
            'username' => 'admin_kurikulum',
            'email' => 'admin.kurikulum@labschool.sch.id',
            'password_hash' => Hash::make('AdminSecret123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($this->adminRole->id);

        $this->regularUser = User::create([
            'username' => 'guest_user',
            'email' => 'guest@labschool.sch.id',
            'password_hash' => Hash::make('GuestSecret123!'),
            'status' => 'active',
        ]);
        $this->regularUser->roles()->attach($this->guestRole->id);

        // Master records
        $this->academicYear = AcademicYear::create([
            'name' => '2024/2025',
            'start_date' => '2024-07-15',
            'end_date' => '2025-06-20',
            'is_active' => true,
        ]);

        $this->gradeLevel10 = GradeLevel::create([
            'name' => 'Kelas 10',
            'sort_order' => 10,
        ]);

        $this->gradeLevel11 = GradeLevel::create([
            'name' => 'Kelas 11',
            'sort_order' => 11,
        ]);

        $this->majorIpa = Major::create([
            'name' => 'MIPA',
        ]);

        $this->teacher = Teacher::create([
            'nip' => '198501012010011005',
            'full_name' => 'Drs. Supriyanto, M.Pd.',
            'status' => 'tetap',
        ]);
    }

    /**
     * BE-MST-05: Unauthenticated requests are rejected with 401
     */
    public function test_be_mst_05_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/classes')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->postJson('/api/v1/classes', [])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->getJson('/api/v1/grade-levels')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    /**
     * BE-MST-05: User without permission is forbidden (403)
     */
    public function test_be_mst_05_user_without_permission_is_forbidden(): void
    {
        $this->actingAs($this->regularUser)
            ->getJson('/api/v1/classes')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($this->regularUser)
            ->postJson('/api/v1/classes', [
                'name' => 'X-A',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-MST-05: Admin can create, update, and delete class with audit logging
     */
    public function test_be_mst_05_admin_can_create_update_delete_class_and_logs_audit(): void
    {
        // 1. Create Class
        $createResp = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/classes', [
                'academic_year_id' => $this->academicYear->id,
                'grade_level_id' => $this->gradeLevel10->id,
                'major_id' => $this->majorIpa->id,
                'homeroom_teacher_id' => $this->teacher->id,
                'name' => 'X-MIPA-1',
                'capacity' => 36,
            ]);

        $createResp->assertStatus(201)
            ->assertJsonPath('data.name', 'X-MIPA-1')
            ->assertJsonPath('data.capacity', 36);

        $classId = $createResp->json('data.id');

        $this->assertDatabaseHas('classes', [
            'id' => $classId,
            'name' => 'X-MIPA-1',
            'capacity' => 36,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'class.created',
            'entity' => 'SchoolClass',
            'entity_id' => $classId,
        ]);

        // 2. Update Class
        $updateResp = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/classes/{$classId}", [
                'name' => 'X-MIPA-1 Unggulan',
                'capacity' => 32,
            ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('data.name', 'X-MIPA-1 Unggulan')
            ->assertJsonPath('data.capacity', 32);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'class.updated',
            'entity' => 'SchoolClass',
            'entity_id' => $classId,
        ]);

        // 3. Delete Class (Soft delete)
        $deleteResp = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/classes/{$classId}");

        $deleteResp->assertStatus(200);

        $this->assertSoftDeleted('classes', [
            'id' => $classId,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'class.deleted',
            'entity' => 'SchoolClass',
            'entity_id' => $classId,
        ]);
    }

    public function test_be_mst_05_admin_can_set_and_update_class_program_type(): void
    {
        // Create dengan program_type icp
        $createResp = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/classes', [
                'academic_year_id' => $this->academicYear->id,
                'grade_level_id' => $this->gradeLevel10->id,
                'name' => 'VII-C ICP',
                'capacity' => 30,
                'program_type' => 'icp',
            ]);

        $createResp->assertStatus(201)->assertJsonPath('data.program_type', 'icp');
        $classId = $createResp->json('data.id');
        $this->assertDatabaseHas('classes', ['id' => $classId, 'program_type' => 'icp']);

        // Default reguler bila tidak dikirim
        $defaultResp = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/classes', [
                'academic_year_id' => $this->academicYear->id,
                'grade_level_id' => $this->gradeLevel10->id,
                'name' => 'VII-A Reguler',
                'capacity' => 32,
            ]);
        $defaultResp->assertStatus(201)->assertJsonPath('data.program_type', 'reguler');

        // Update ke tahfidz
        $this->actingAs($this->adminUser)
            ->putJson("/api/v1/classes/{$classId}", ['program_type' => 'tahfidz'])
            ->assertOk()
            ->assertJsonPath('data.program_type', 'tahfidz');
        $this->assertDatabaseHas('classes', ['id' => $classId, 'program_type' => 'tahfidz']);

        // Nilai ilegal ditolak
        $this->actingAs($this->adminUser)
            ->putJson("/api/v1/classes/{$classId}", ['program_type' => 'bilingual'])
            ->assertStatus(400)
            ->assertJsonValidationErrors(['program_type']);
    }

    /**
     * BE-MST-05: Class validation errors (missing fields, duplicate name per academic year)
     */
    public function test_be_mst_05_class_validation_errors(): void
    {
        // 1. Missing fields
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/classes', [])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // 2. Duplicate class name in same academic year
        SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->gradeLevel10->id,
            'name' => 'X-MIPA-2',
            'capacity' => 36,
        ]);

        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/classes', [
                'academic_year_id' => $this->academicYear->id,
                'grade_level_id' => $this->gradeLevel10->id,
                'name' => 'X-MIPA-2',
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /**
     * BE-MST-05: Cannot reduce capacity below current active student count
     */
    public function test_be_mst_05_cannot_reduce_capacity_below_active_students(): void
    {
        $class = SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->gradeLevel10->id,
            'name' => 'X-MIPA-3',
            'capacity' => 36,
        ]);

        $student = Student::create([
            'nis' => '30001',
            'nisn' => '0033441122',
            'nik_enc' => '3578010101080001',
            'full_name' => 'Siswa Kelas Tiga',
            'gender' => 'L',
            'birth_place' => 'Surabaya',
            'birth_date' => '2008-01-01',
            'status' => 'aktif',
            'entry_date' => '2024-07-15',
        ]);

        $class->students()->attach($student->id, [
            'id' => (string) Str::uuid(),
            'joined_at' => '2024-07-15',
            'left_at' => null,
        ]);

        // Attempt reducing capacity to 0
        $resp = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/classes/{$class->id}", [
                'capacity' => 0,
            ]);

        $resp->assertStatus(400);
    }

    /**
     * BE-MST-05: Cannot delete class with active students (409 CONFLICT)
     */
    public function test_be_mst_05_cannot_delete_class_with_active_students(): void
    {
        $class = SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->gradeLevel10->id,
            'name' => 'X-MIPA-4',
            'capacity' => 36,
        ]);

        $student = Student::create([
            'nis' => '30002',
            'nisn' => '0033441133',
            'nik_enc' => '3578010101080002',
            'full_name' => 'Siswa Aktif Terdaftar',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2008-02-02',
            'status' => 'aktif',
            'entry_date' => '2024-07-15',
        ]);

        $class->students()->attach($student->id, [
            'id' => (string) Str::uuid(),
            'joined_at' => '2024-07-15',
            'left_at' => null,
        ]);

        $resp = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/classes/{$class->id}");

        $resp->assertStatus(409)
            ->assertJsonPath('error.code', 'CONFLICT');

        $this->assertDatabaseHas('classes', [
            'id' => $class->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * BE-MST-05: Enroll students into class and enforce capacity
     */
    public function test_be_mst_05_enroll_students_and_enforce_capacity(): void
    {
        $class = SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->gradeLevel10->id,
            'name' => 'X-MIPA-Kecil',
            'capacity' => 2, // only 2 seats
        ]);

        $s1 = Student::create([
            'nis' => '30003',
            'nisn' => '0033441144',
            'nik_enc' => '3578010101080003',
            'full_name' => 'Siswa Satu',
            'gender' => 'L',
            'birth_place' => 'Surabaya',
            'birth_date' => '2008-03-03',
            'status' => 'aktif',
            'entry_date' => '2024-07-15',
        ]);

        $s2 = Student::create([
            'nis' => '30004',
            'nisn' => '0033441155',
            'nik_enc' => '3578010101080004',
            'full_name' => 'Siswa Dua',
            'gender' => 'P',
            'birth_place' => 'Surabaya',
            'birth_date' => '2008-04-04',
            'status' => 'aktif',
            'entry_date' => '2024-07-15',
        ]);

        $s3 = Student::create([
            'nis' => '30005',
            'nisn' => '0033441166',
            'nik_enc' => '3578010101080005',
            'full_name' => 'Siswa Tiga',
            'gender' => 'L',
            'birth_place' => 'Surabaya',
            'birth_date' => '2008-05-05',
            'status' => 'aktif',
            'entry_date' => '2024-07-15',
        ]);

        // Attempting to add 3 students to a class of capacity 2
        $respFail = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/classes/{$class->id}/students", [
                'student_ids' => [$s1->id, $s2->id, $s3->id],
            ]);

        $respFail->assertStatus(400)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');

        // Adding 2 students fits capacity
        $respSuccess = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/classes/{$class->id}/students", [
                'student_ids' => [$s1->id, $s2->id],
            ]);

        $respSuccess->assertStatus(201);

        $this->assertDatabaseHas('class_students', [
            'class_id' => $class->id,
            'student_id' => $s1->id,
            'left_at' => null,
        ]);

        $this->assertDatabaseHas('class_students', [
            'class_id' => $class->id,
            'student_id' => $s2->id,
            'left_at' => null,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'class.students_enrolled',
            'entity' => 'SchoolClass',
            'entity_id' => $class->id,
        ]);
    }

    /**
     * BE-MST-05: Enforce single active class per student in same academic year
     */
    public function test_be_mst_05_enforce_single_active_class_per_student_in_same_year(): void
    {
        $classA = SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->gradeLevel10->id,
            'name' => 'X-MIPA-A',
            'capacity' => 36,
        ]);

        $classB = SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->gradeLevel10->id,
            'name' => 'X-MIPA-B',
            'capacity' => 36,
        ]);

        $student = Student::create([
            'nis' => '30006',
            'nisn' => '0033441177',
            'nik_enc' => '3578010101080006',
            'full_name' => 'Siswa Bentrok Kelas',
            'gender' => 'L',
            'birth_place' => 'Surabaya',
            'birth_date' => '2008-06-06',
            'status' => 'aktif',
            'entry_date' => '2024-07-15',
        ]);

        // Enroll to Class A
        $this->actingAs($this->adminUser)
            ->postJson("/api/v1/classes/{$classA->id}/students", [
                'student_id' => $student->id,
            ])
            ->assertStatus(201);

        // Attempt enrolling same student to Class B in same year
        $respConflict = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/classes/{$classB->id}/students", [
                'student_id' => $student->id,
            ]);

        $respConflict->assertStatus(400)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');
    }

    /**
     * BE-MST-05: Transfer student between classes records departure and arrival
     */
    public function test_be_mst_05_transfer_student_between_classes(): void
    {
        $classOrigin = SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->gradeLevel10->id,
            'name' => 'X-MIPA-Asal',
            'capacity' => 36,
        ]);

        $classTarget = SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->gradeLevel10->id,
            'name' => 'X-MIPA-Tujuan',
            'capacity' => 36,
        ]);

        $student = Student::create([
            'nis' => '30007',
            'nisn' => '0033441188',
            'nik_enc' => '3578010101080007',
            'full_name' => 'Siswa Pindah Rombel',
            'gender' => 'P',
            'birth_place' => 'Surabaya',
            'birth_date' => '2008-07-07',
            'status' => 'aktif',
            'entry_date' => '2024-07-15',
        ]);

        $classOrigin->students()->attach($student->id, [
            'id' => (string) Str::uuid(),
            'joined_at' => '2024-07-15',
            'left_at' => null,
        ]);

        // Transfer student
        $transferResp = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/classes/{$classOrigin->id}/students/transfer", [
                'student_id' => $student->id,
                'target_class_id' => $classTarget->id,
                'transfer_date' => '2024-09-01',
                'reason' => 'Penyesuaian minat jurusan',
            ]);

        $transferResp->assertStatus(200);

        // Verify departure in origin class
        $this->assertDatabaseHas('class_students', [
            'class_id' => $classOrigin->id,
            'student_id' => $student->id,
            'left_at' => '2024-09-01',
        ]);

        // Verify active arrival in target class
        $this->assertDatabaseHas('class_students', [
            'class_id' => $classTarget->id,
            'student_id' => $student->id,
            'joined_at' => '2024-09-01',
            'left_at' => null,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'class.student_transferred',
            'entity' => 'SchoolClass',
        ]);
    }

    /**
     * BE-MST-05: Remove student from class sets left_at
     */
    public function test_be_mst_05_remove_student_from_class(): void
    {
        $class = SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->gradeLevel10->id,
            'name' => 'X-MIPA-Keluar',
            'capacity' => 36,
        ]);

        $student = Student::create([
            'nis' => '30008',
            'nisn' => '0033441199',
            'nik_enc' => '3578010101080008',
            'full_name' => 'Siswa Dikeluarkan',
            'gender' => 'L',
            'birth_place' => 'Surabaya',
            'birth_date' => '2008-08-08',
            'status' => 'aktif',
            'entry_date' => '2024-07-15',
        ]);

        $class->students()->attach($student->id, [
            'id' => (string) Str::uuid(),
            'joined_at' => '2024-07-15',
            'left_at' => null,
        ]);

        $resp = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/classes/{$class->id}/students/{$student->id}");

        $resp->assertStatus(200);

        // Active only query returns 0
        $respActive = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/classes/{$class->id}/students?active_only=true");

        $respActive->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // All history query returns 1
        $respAll = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/classes/{$class->id}/students?active_only=false");

        $respAll->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    /**
     * BE-MST-05: Reference endpoints for grade levels and majors
     */
    public function test_be_mst_05_reference_endpoints(): void
    {
        $respGrades = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/grade-levels');

        $respGrades->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $respMajors = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/majors');

        $respMajors->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'MIPA');
    }
}
