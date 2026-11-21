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
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AcademicYearTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected Role $adminRole;

    protected Role $userRole;

    protected Permission $permManageAcademicYear;

    protected function setUp(): void
    {
        parent::setUp();

        // Permission
        $this->permManageAcademicYear = Permission::create([
            'code' => 'master.academic_year.manage',
            'description' => 'Mengelola tahun ajaran dan semester',
        ]);

        // Admin Role
        $this->adminRole = Role::create([
            'code' => 'super_admin',
            'name' => 'Super Administrator',
            'is_system' => true,
        ]);
        $this->adminRole->permissions()->attach([$this->permManageAcademicYear->id]);

        // Regular Role (no academic permissions)
        $this->userRole = Role::create([
            'code' => 'teacher',
            'name' => 'Guru',
            'is_system' => true,
        ]);

        // Users
        $this->adminUser = User::create([
            'username' => 'admin_academic',
            'email' => 'admin.academic@labschool.sch.id',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($this->adminRole->id);

        $this->regularUser = User::create([
            'username' => 'regular_teacher',
            'email' => 'teacher@labschool.sch.id',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $this->regularUser->roles()->attach($this->userRole->id);
    }

    /**
     * BE-MST-01: Unauthenticated requests are rejected with 401
     */
    public function test_be_mst_01_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/academic-years')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->getJson('/api/v1/academic-years/active')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->postJson('/api/v1/academic-years', [])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->getJson('/api/v1/semesters')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    /**
     * BE-MST-01: User without permission is forbidden (403)
     */
    public function test_be_mst_01_user_without_permission_is_forbidden(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/v1/academic-years');

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $response = $this->actingAs($this->regularUser)
            ->postJson('/api/v1/academic-years', [
                'name' => '2026/2027',
                'start_date' => '2026-07-15',
                'end_date' => '2027-06-20',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-MST-01: Authenticated user can view active period context
     */
    public function test_be_mst_01_authenticated_user_can_view_active_period(): void
    {
        // When none active
        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/v1/academic-years/active');

        $response->assertStatus(200)
            ->assertJsonPath('data.academicYear', null)
            ->assertJsonPath('data.semester', null);

        // Setup active year & semester
        $year = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-15',
            'end_date' => '2027-06-20',
            'is_active' => true,
        ]);

        $semester = Semester::create([
            'academic_year_id' => $year->id,
            'name' => 'Ganjil',
            'start_date' => '2026-07-15',
            'end_date' => '2026-12-20',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/v1/academic-years/active');

        $response->assertStatus(200)
            ->assertJsonPath('data.academicYear.id', $year->id)
            ->assertJsonPath('data.academicYear.name', '2026/2027')
            ->assertJsonPath('data.semester.id', $semester->id)
            ->assertJsonPath('data.semester.name', 'Ganjil');
    }

    /**
     * BE-MST-01: Create academic year and log audit
     */
    public function test_be_mst_01_can_create_academic_year_and_logs_audit(): void
    {
        $payload = [
            'name' => '2026/2027',
            'start_date' => '2026-07-15',
            'end_date' => '2027-06-20',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/academic-years', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', '2026/2027')
            ->assertJsonPath('data.is_active', true);

        $yearId = $response->json('data.id');

        $this->assertDatabaseHas('academic_years', [
            'id' => $yearId,
            'name' => '2026/2027',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'academic_year.created',
            'entity' => 'AcademicYear',
            'entity_id' => $yearId,
        ]);
    }

    /**
     * BE-MST-01: Validation errors on creating academic year
     */
    public function test_be_mst_01_create_academic_year_validation_errors(): void
    {
        // 1. Missing fields
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/academic-years', [])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details']]);

        // 2. End date before start date
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/academic-years', [
                'name' => '2026/2027',
                'start_date' => '2026-07-15',
                'end_date' => '2026-07-10',
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // 3. Duplicate name
        AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-15',
            'end_date' => '2027-06-20',
            'is_active' => false,
        ]);

        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/academic-years', [
                'name' => '2026/2027',
                'start_date' => '2026-07-15',
                'end_date' => '2027-06-20',
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /**
     * BE-MST-01: Enforces single active academic year upon creation & activation
     */
    public function test_be_mst_01_single_active_academic_year_enforced_upon_creation_and_activation(): void
    {
        // Year 1 created as active
        $year1 = AcademicYear::create([
            'name' => '2025/2026',
            'start_date' => '2025-07-15',
            'end_date' => '2026-06-20',
            'is_active' => true,
        ]);

        $this->assertTrue($year1->fresh()->is_active);

        // Year 2 created with is_active = true -> Year 1 deactivated
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/academic-years', [
                'name' => '2026/2027',
                'start_date' => '2026-07-15',
                'end_date' => '2027-06-20',
                'is_active' => true,
            ]);

        $response->assertStatus(201);
        $year2Id = $response->json('data.id');

        $this->assertFalse($year1->fresh()->is_active);
        $this->assertTrue(AcademicYear::find($year2Id)->is_active);

        // Activate Year 1 via PATCH -> Year 2 deactivated, Year 1 active
        $activateResp = $this->actingAs($this->adminUser)
            ->patchJson("/api/v1/academic-years/{$year1->id}/activate");

        $activateResp->assertStatus(200);

        $this->assertTrue($year1->fresh()->is_active);
        $this->assertFalse(AcademicYear::find($year2Id)->is_active);

        // Confirm year 2 data still exists and wasn't deleted
        $this->assertDatabaseHas('academic_years', [
            'id' => $year2Id,
            'name' => '2026/2027',
            'is_active' => false,
            'deleted_at' => null,
        ]);
    }

    /**
     * BE-MST-01: Active academic year cannot be deleted (400)
     */
    public function test_be_mst_01_cannot_delete_active_academic_year(): void
    {
        $activeYear = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-15',
            'end_date' => '2027-06-20',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/academic-years/{$activeYear->id}");

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');

        $this->assertDatabaseHas('academic_years', [
            'id' => $activeYear->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * BE-MST-01: Academic year with classes cannot be deleted (409)
     */
    public function test_be_mst_01_cannot_delete_academic_year_with_classes(): void
    {
        $inactiveYear = AcademicYear::create([
            'name' => '2024/2025',
            'start_date' => '2024-07-15',
            'end_date' => '2025-06-20',
            'is_active' => false,
        ]);

        $gradeLevel = GradeLevel::create([
            'name' => 'Kelas 10',
            'sort_order' => 1,
        ]);

        SchoolClass::create([
            'academic_year_id' => $inactiveYear->id,
            'grade_level_id' => $gradeLevel->id,
            'name' => 'X-A',
            'capacity' => 36,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/academic-years/{$inactiveYear->id}");

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'CONFLICT');

        $this->assertDatabaseHas('academic_years', [
            'id' => $inactiveYear->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * BE-MST-01: Inactive empty academic year can be deleted
     */
    public function test_be_mst_01_can_delete_inactive_empty_academic_year(): void
    {
        $inactiveYear = AcademicYear::create([
            'name' => '2023/2024',
            'start_date' => '2023-07-15',
            'end_date' => '2024-06-20',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/academic-years/{$inactiveYear->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('academic_years', [
            'id' => $inactiveYear->id,
        ]);
    }

    /**
     * BE-MST-01: Semesters CRUD and unique name per academic year
     */
    public function test_be_mst_01_semester_crud_and_uniqueness(): void
    {
        $year = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-15',
            'end_date' => '2027-06-20',
            'is_active' => true,
        ]);

        // 1. Create Semester Ganjil
        $resp = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/semesters', [
                'academic_year_id' => $year->id,
                'name' => 'Ganjil',
                'start_date' => '2026-07-15',
                'end_date' => '2026-12-20',
                'is_active' => true,
            ]);

        $resp->assertStatus(201)
            ->assertJsonPath('data.name', 'Ganjil');

        $semGanjilId = $resp->json('data.id');

        // 2. Reject duplicate semester name under the same academic year
        $dupResp = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/semesters', [
                'academic_year_id' => $year->id,
                'name' => 'Ganjil',
                'start_date' => '2026-07-15',
                'end_date' => '2026-12-20',
            ]);

        $dupResp->assertStatus(400)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');

        // 3. Update Semester
        $updateResp = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/semesters/{$semGanjilId}", [
                'name' => 'Semester 1 (Ganjil)',
            ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('data.name', 'Semester 1 (Ganjil)');
    }

    /**
     * BE-MST-01: Activating semester sets parent academic year to active and deactivates others
     */
    public function test_be_mst_01_activating_semester_enforces_single_active_and_activates_parent_academic_year(): void
    {
        $yearOld = AcademicYear::create([
            'name' => '2025/2026',
            'start_date' => '2025-07-15',
            'end_date' => '2026-06-20',
            'is_active' => true,
        ]);

        $semOld = Semester::create([
            'academic_year_id' => $yearOld->id,
            'name' => 'Genap',
            'start_date' => '2026-01-05',
            'end_date' => '2026-06-20',
            'is_active' => true,
        ]);

        $yearNew = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-15',
            'end_date' => '2027-06-20',
            'is_active' => false,
        ]);

        $semNew = Semester::create([
            'academic_year_id' => $yearNew->id,
            'name' => 'Ganjil',
            'start_date' => '2026-07-15',
            'end_date' => '2026-12-20',
            'is_active' => false,
        ]);

        // Activate semNew
        $resp = $this->actingAs($this->adminUser)
            ->patchJson("/api/v1/semesters/{$semNew->id}/activate");

        $resp->assertStatus(200);

        // Verify semNew is active and semOld is deactivated
        $this->assertTrue($semNew->fresh()->is_active);
        $this->assertFalse($semOld->fresh()->is_active);

        // Verify parent yearNew is active and yearOld is deactivated
        $this->assertTrue($yearNew->fresh()->is_active);
        $this->assertFalse($yearOld->fresh()->is_active);
    }

    /**
     * BE-MST-01: Active semester cannot be deleted (400)
     */
    public function test_be_mst_01_cannot_delete_active_semester(): void
    {
        $year = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-15',
            'end_date' => '2027-06-20',
            'is_active' => true,
        ]);

        $activeSem = Semester::create([
            'academic_year_id' => $year->id,
            'name' => 'Ganjil',
            'start_date' => '2026-07-15',
            'end_date' => '2026-12-20',
            'is_active' => true,
        ]);

        $resp = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/semesters/{$activeSem->id}");

        $resp->assertStatus(400)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');

        $this->assertDatabaseHas('semesters', [
            'id' => $activeSem->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * BE-MST-01: List academic years with search filter and semesters filter
     */
    public function test_be_mst_01_list_with_search_and_filters(): void
    {
        $year1 = AcademicYear::create([
            'name' => '2025/2026',
            'start_date' => '2025-07-15',
            'end_date' => '2026-06-20',
            'is_active' => false,
        ]);

        $year2 = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-15',
            'end_date' => '2027-06-20',
            'is_active' => true,
        ]);

        Semester::create([
            'academic_year_id' => $year1->id,
            'name' => 'Ganjil',
            'start_date' => '2025-07-15',
            'end_date' => '2025-12-20',
            'is_active' => false,
        ]);

        Semester::create([
            'academic_year_id' => $year2->id,
            'name' => 'Ganjil',
            'start_date' => '2026-07-15',
            'end_date' => '2026-12-20',
            'is_active' => true,
        ]);

        // Search academic years
        $respYears = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/academic-years?q=2026/2027');

        $respYears->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', '2026/2027');

        // Filter semesters by academic_year_id
        $respSemesters = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/semesters?academic_year_id={$year2->id}");

        $respSemesters->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.academic_year_id', $year2->id);
    }

    /**
     * BE-MST-01: Update academic year with uniqueness check on name
     */
    public function test_be_mst_01_update_academic_year_and_name_uniqueness(): void
    {
        $year1 = AcademicYear::create([
            'name' => '2024/2025',
            'start_date' => '2024-07-15',
            'end_date' => '2025-06-20',
            'is_active' => false,
        ]);

        $year2 = AcademicYear::create([
            'name' => '2025/2026',
            'start_date' => '2025-07-15',
            'end_date' => '2026-06-20',
            'is_active' => false,
        ]);

        // Attempt to update year2 with year1's name -> 400 VALIDATION_FAILED
        $resp = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/academic-years/{$year2->id}", [
                'name' => '2024/2025',
            ]);

        $resp->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // Update year2 with same existing name -> 200 OK
        $respSelf = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/academic-years/{$year2->id}", [
                'name' => '2025/2026',
                'start_date' => '2025-08-01',
            ]);

        $respSelf->assertStatus(200)
            ->assertJsonPath('data.name', '2025/2026');
    }

    /**
     * BE-MST-01: Semester validation errors
     */
    public function test_be_mst_01_semester_validation_errors(): void
    {
        $year = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-15',
            'end_date' => '2027-06-20',
            'is_active' => true,
        ]);

        // 1. Missing fields
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/semesters', [])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // 2. Non-existent academic year
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/semesters', [
                'academic_year_id' => '00000000-0000-0000-0000-000000000000',
                'name' => 'Ganjil',
                'start_date' => '2026-07-15',
                'end_date' => '2026-12-20',
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // 3. End date before start date
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/semesters', [
                'academic_year_id' => $year->id,
                'name' => 'Ganjil',
                'start_date' => '2026-07-15',
                'end_date' => '2026-07-01',
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /**
     * BE-MST-01: Cannot update semester to duplicate name within same academic year
     */
    public function test_be_mst_01_update_semester_duplicate_name_rejected(): void
    {
        $year = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-15',
            'end_date' => '2027-06-20',
            'is_active' => true,
        ]);

        $sem1 = Semester::create([
            'academic_year_id' => $year->id,
            'name' => 'Ganjil',
            'start_date' => '2026-07-15',
            'end_date' => '2026-12-20',
            'is_active' => true,
        ]);

        $sem2 = Semester::create([
            'academic_year_id' => $year->id,
            'name' => 'Genap',
            'start_date' => '2027-01-05',
            'end_date' => '2027-06-20',
            'is_active' => false,
        ]);

        $resp = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/semesters/{$sem2->id}", [
                'name' => 'Ganjil',
            ]);

        $resp->assertStatus(400)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');
    }

    /**
     * BE-MST-01: Cannot delete semester with teaching assignments (409)
     */
    public function test_be_mst_01_cannot_delete_semester_with_teaching_assignments(): void
    {
        $year = AcademicYear::create([
            'name' => '2024/2025',
            'start_date' => '2024-07-15',
            'end_date' => '2025-06-20',
            'is_active' => false,
        ]);

        $semester = Semester::create([
            'academic_year_id' => $year->id,
            'name' => 'Ganjil',
            'start_date' => '2024-07-15',
            'end_date' => '2024-12-20',
            'is_active' => false,
        ]);

        $gradeLevel = GradeLevel::create([
            'name' => 'Kelas 10',
            'sort_order' => 1,
        ]);

        $class = SchoolClass::create([
            'academic_year_id' => $year->id,
            'grade_level_id' => $gradeLevel->id,
            'name' => 'X-A',
            'capacity' => 36,
        ]);

        $teacherUser = User::create([
            'username' => 'guru_matematika',
            'email' => 'math@labschool.sch.id',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);

        $teacher = Teacher::create([
            'user_id' => $teacherUser->id,
            'nip' => '198501012010011001',
            'full_name' => 'Budi Santoso, M.Pd.',
            'status' => 'permanent',
        ]);

        $subject = Subject::create([
            'code' => 'MAT-10',
            'name' => 'Matematika Wajib',
            'subject_group' => 'A',
        ]);

        TeachingAssignment::create([
            'semester_id' => $semester->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'hours_per_week' => 4,
        ]);

        $resp = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/semesters/{$semester->id}");

        $resp->assertStatus(409)
            ->assertJsonPath('error.code', 'CONFLICT');

        $this->assertDatabaseHas('semesters', [
            'id' => $semester->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * BE-MST-01: Can delete inactive empty semester
     */
    public function test_be_mst_01_can_delete_inactive_empty_semester(): void
    {
        $year = AcademicYear::create([
            'name' => '2023/2024',
            'start_date' => '2023-07-15',
            'end_date' => '2024-06-20',
            'is_active' => false,
        ]);

        $semester = Semester::create([
            'academic_year_id' => $year->id,
            'name' => 'Pendek',
            'start_date' => '2024-06-21',
            'end_date' => '2024-07-10',
            'is_active' => false,
        ]);

        $resp = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/semesters/{$semester->id}");

        $resp->assertStatus(200);

        $this->assertSoftDeleted('semesters', [
            'id' => $semester->id,
        ]);
    }

    /**
     * BE-MST-01: Get detail returns 404 for non-existent entities
     */
    public function test_be_mst_01_get_detail_not_found(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';

        $this->actingAs($this->adminUser)
            ->getJson("/api/v1/academic-years/{$fakeId}")
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');

        $this->actingAs($this->adminUser)
            ->getJson("/api/v1/semesters/{$fakeId}")
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }
}
