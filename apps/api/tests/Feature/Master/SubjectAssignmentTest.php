<?php

namespace Tests\Feature\Master;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SubjectAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected Role $adminRole;

    protected Role $guestRole;

    protected Permission $permManageSubject;

    protected AcademicYear $academicYear;

    protected Semester $semester;

    protected GradeLevel $gradeLevel;

    protected SchoolClass $class;

    protected Teacher $teacher1;

    protected Teacher $teacher2;

    protected function setUp(): void
    {
        parent::setUp();

        // Permissions
        $this->permManageSubject = Permission::create([
            'code' => 'master.subject.manage',
            'description' => 'Mengelola mata pelajaran dan penugasan guru',
        ]);

        // Roles
        $this->adminRole = Role::create([
            'code' => 'super_admin',
            'name' => 'Super Administrator',
            'is_system' => true,
        ]);
        $this->adminRole->permissions()->attach([$this->permManageSubject->id]);

        $this->guestRole = Role::create([
            'code' => 'guest',
            'name' => 'Tamu',
            'is_system' => false,
        ]);

        // Users
        $this->adminUser = User::create([
            'username' => 'admin_kurikulum',
            'email' => 'kurikulum@labschool.sch.id',
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

        // Academic Year & Semester
        $this->academicYear = AcademicYear::create([
            'name' => '2024/2025',
            'start_date' => '2024-07-15',
            'end_date' => '2025-06-20',
            'is_active' => true,
        ]);

        $this->semester = Semester::create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'Ganjil',
            'semester_type' => 'ganjil',
            'start_date' => '2024-07-15',
            'end_date' => '2024-12-20',
            'is_active' => true,
        ]);

        // Grade Level & Class
        $this->gradeLevel = GradeLevel::create([
            'name' => 'Kelas 10',
            'sort_order' => 10,
        ]);

        $this->class = SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->gradeLevel->id,
            'name' => 'X-MIPA-1',
            'capacity' => 36,
        ]);

        // Teachers
        $this->teacher1 = Teacher::create([
            'nip' => '198001012005011001',
            'full_name' => 'Budi Santoso, S.Pd.',
            'status' => 'tetap',
        ]);

        $this->teacher2 = Teacher::create([
            'nip' => '198202022006022002',
            'full_name' => 'Siti Rahmawati, M.Pd.',
            'status' => 'tetap',
        ]);
    }

    /**
     * BE-MST-06: Unauthenticated requests are rejected with 401
     */
    public function test_be_mst_06_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/subjects')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->postJson('/api/v1/subjects', [])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->getJson('/api/v1/subjects/00000000-0000-0000-0000-000000000000')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->putJson('/api/v1/subjects/00000000-0000-0000-0000-000000000000', [])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->deleteJson('/api/v1/subjects/00000000-0000-0000-0000-000000000000')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->getJson('/api/v1/teaching-assignments')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->postJson('/api/v1/teaching-assignments', [])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->postJson('/api/v1/teaching-assignments/batch', [])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->getJson('/api/v1/teaching-assignments/00000000-0000-0000-0000-000000000000')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->putJson('/api/v1/teaching-assignments/00000000-0000-0000-0000-000000000000', [])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->deleteJson('/api/v1/teaching-assignments/00000000-0000-0000-0000-000000000000')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    /**
     * BE-MST-06: User without permission is forbidden (403)
     */
    public function test_be_mst_06_user_without_permission_is_forbidden(): void
    {
        $this->actingAs($this->regularUser)
            ->getJson('/api/v1/subjects')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($this->regularUser)
            ->postJson('/api/v1/subjects', [
                'code' => 'MAT-10',
                'name' => 'Matematika Wajib',
                'subject_group' => 'Kelompok A',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($this->regularUser)
            ->getJson('/api/v1/teaching-assignments')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($this->regularUser)
            ->postJson('/api/v1/teaching-assignments', [])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-MST-06: Subject validation fails on missing fields or duplicate code
     */
    public function test_be_mst_06_subject_validation_fails_on_missing_fields_or_duplicate_code(): void
    {
        // 1. Missing required fields
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/subjects', [])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // 2. Create initial subject
        Subject::create([
            'code' => 'MAT-10',
            'name' => 'Matematika Wajib 10',
            'subject_group' => 'Kelompok A',
        ]);

        // 3. Duplicate code on create
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/subjects', [
                'code' => 'MAT-10',
                'name' => 'Matematika Lain',
                'subject_group' => 'Kelompok A',
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // 4. Duplicate code on update
        $subject2 = Subject::create([
            'code' => 'FIS-10',
            'name' => 'Fisika 10',
            'subject_group' => 'Kelompok B',
        ]);

        $this->actingAs($this->adminUser)
            ->putJson("/api/v1/subjects/{$subject2->id}", [
                'code' => 'MAT-10',
                'name' => 'Fisika Modif',
                'subject_group' => 'Kelompok B',
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /**
     * BE-MST-06: Admin can create, update, and get subject detail with audit logs
     */
    public function test_be_mst_06_admin_can_create_update_and_get_subject_with_audit_logs(): void
    {
        // 1. Create Subject
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/subjects', [
                'code' => 'BIO-10',
                'name' => 'Biologi Kelas 10',
                'subject_group' => 'Kelompok B (Peminatan)',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'BIO-10')
            ->assertJsonPath('data.name', 'Biologi Kelas 10')
            ->assertJsonPath('data.subject_group', 'Kelompok B (Peminatan)');

        $subjectId = $response->json('data.id');

        $this->assertDatabaseHas('subjects', [
            'id' => $subjectId,
            'code' => 'BIO-10',
            'name' => 'Biologi Kelas 10',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'subject.created',
            'entity' => 'Subject',
            'entity_id' => $subjectId,
        ]);

        // 2. Get Subject Detail
        $this->actingAs($this->adminUser)
            ->getJson("/api/v1/subjects/{$subjectId}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $subjectId)
            ->assertJsonPath('data.code', 'BIO-10');

        // 3. Update Subject
        $updateResp = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/subjects/{$subjectId}", [
                'code' => 'BIO-10-REV',
                'name' => 'Biologi Dasar 10',
                'subject_group' => 'Kelompok A (Umum)',
            ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('data.code', 'BIO-10-REV')
            ->assertJsonPath('data.name', 'Biologi Dasar 10')
            ->assertJsonPath('data.subject_group', 'Kelompok A (Umum)');

        $this->assertDatabaseHas('subjects', [
            'id' => $subjectId,
            'code' => 'BIO-10-REV',
            'name' => 'Biologi Dasar 10',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'subject.updated',
            'entity' => 'Subject',
            'entity_id' => $subjectId,
        ]);
    }

    /**
     * BE-MST-06: Admin can list subjects with pagination and search/group filters
     */
    public function test_be_mst_06_admin_can_list_subjects_with_pagination_and_filters(): void
    {
        Subject::create(['code' => 'MAT-10', 'name' => 'Matematika Wajib', 'subject_group' => 'Kelompok A']);
        Subject::create(['code' => 'BIN-10', 'name' => 'Bahasa Indonesia', 'subject_group' => 'Kelompok A']);
        Subject::create(['code' => 'FIS-10', 'name' => 'Fisika Peminatan', 'subject_group' => 'Kelompok B']);

        // Search by keyword
        $respSearch = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/subjects?search=Matematika');

        $respSearch->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'MAT-10');

        // Filter by subject group
        $respGroup = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/subjects?subject_group=Kelompok B');

        $respGroup->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'FIS-10');
    }

    /**
     * BE-MST-06: Cannot delete subject with existing teaching assignments (409 CONFLICT)
     */
    public function test_be_mst_06_cannot_delete_subject_with_teaching_assignments(): void
    {
        $subject = Subject::create([
            'code' => 'KIM-10',
            'name' => 'Kimia 10',
            'subject_group' => 'Kelompok B',
        ]);

        TeachingAssignment::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher1->id,
            'hours_per_week' => 3,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/subjects/{$subject->id}");

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'CONFLICT')
            ->assertJsonPath('error.message', 'Mata pelajaran tidak dapat dihapus karena masih memiliki penugasan guru terikat.');

        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * BE-MST-06: Admin can delete subject without assignments (Soft Delete)
     */
    public function test_be_mst_06_admin_can_delete_subject_without_assignments(): void
    {
        $subject = Subject::create([
            'code' => 'GEO-10',
            'name' => 'Geografi 10',
            'subject_group' => 'Kelompok C',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/subjects/{$subject->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'Mata pelajaran berhasil dihapus');

        $this->assertSoftDeleted('subjects', [
            'id' => $subject->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'subject.deleted',
            'entity' => 'Subject',
            'entity_id' => $subject->id,
        ]);
    }

    /**
     * BE-MST-06: Teaching assignment validation fails on invalid or missing fields
     */
    public function test_be_mst_06_teaching_assignment_validation_fails_on_invalid_or_missing_fields(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/teaching-assignments', [])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/teaching-assignments', [
                'semester_id' => 'not-a-uuid',
                'class_id' => 'not-a-uuid',
                'subject_id' => 'not-a-uuid',
                'teacher_id' => 'not-a-uuid',
                'hours_per_week' => 0, // min 1
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /**
     * BE-MST-06: Admin can create teaching assignment and enforces unique constraint
     */
    public function test_be_mst_06_admin_can_create_teaching_assignment_and_enforces_unique_constraint(): void
    {
        $subject = Subject::create([
            'code' => 'SEJ-10',
            'name' => 'Sejarah Indonesia',
            'subject_group' => 'Kelompok A',
        ]);

        // 1. First assignment succeeds
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/teaching-assignments', [
                'semester_id' => $this->semester->id,
                'class_id' => $this->class->id,
                'subject_id' => $subject->id,
                'teacher_id' => $this->teacher1->id,
                'hours_per_week' => 2,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.semester_id', $this->semester->id)
            ->assertJsonPath('data.class_id', $this->class->id)
            ->assertJsonPath('data.subject_id', $subject->id)
            ->assertJsonPath('data.teacher_id', $this->teacher1->id)
            ->assertJsonPath('data.hours_per_week', 2);

        $assignmentId = $response->json('data.id');

        $this->assertDatabaseHas('teaching_assignments', [
            'id' => $assignmentId,
            'semester_id' => $this->semester->id,
            'class_id' => $this->class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher1->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'teaching_assignment.created',
            'entity' => 'TeachingAssignment',
            'entity_id' => $assignmentId,
        ]);

        // 2. Second assignment with same semester, class, and subject fails with 409 CONFLICT
        $duplicateResponse = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/teaching-assignments', [
                'semester_id' => $this->semester->id,
                'class_id' => $this->class->id,
                'subject_id' => $subject->id,
                'teacher_id' => $this->teacher2->id,
                'hours_per_week' => 4,
            ]);

        $duplicateResponse->assertStatus(409)
            ->assertJsonPath('error.code', 'CONFLICT')
            ->assertJsonPath('error.message', 'Mata pelajaran ini sudah memiliki penugasan guru pada kelas dan semester yang dipilih.');
    }

    /**
     * BE-MST-06: Admin can batch assign teaching assignments
     */
    public function test_be_mst_06_admin_can_batch_assign_teaching_assignments(): void
    {
        $subject1 = Subject::create(['code' => 'MAT-10', 'name' => 'Matematika', 'subject_group' => 'Kelompok A']);
        $subject2 = Subject::create(['code' => 'FIS-10', 'name' => 'Fisika', 'subject_group' => 'Kelompok B']);

        $payload = [
            'semester_id' => $this->semester->id,
            'assignments' => [
                [
                    'class_id' => $this->class->id,
                    'subject_id' => $subject1->id,
                    'teacher_id' => $this->teacher1->id,
                    'hours_per_week' => 4,
                ],
                [
                    'class_id' => $this->class->id,
                    'subject_id' => $subject2->id,
                    'teacher_id' => $this->teacher2->id,
                    'hours_per_week' => 3,
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/teaching-assignments/batch', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.total', 2);

        $this->assertDatabaseHas('teaching_assignments', [
            'semester_id' => $this->semester->id,
            'class_id' => $this->class->id,
            'subject_id' => $subject1->id,
            'teacher_id' => $this->teacher1->id,
            'hours_per_week' => 4,
        ]);

        $this->assertDatabaseHas('teaching_assignments', [
            'semester_id' => $this->semester->id,
            'class_id' => $this->class->id,
            'subject_id' => $subject2->id,
            'teacher_id' => $this->teacher2->id,
            'hours_per_week' => 3,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'teaching_assignment.batch_assigned',
            'entity' => 'TeachingAssignment',
        ]);
    }

    /**
     * BE-MST-06: Admin can list and filter teaching assignments
     */
    public function test_be_mst_06_admin_can_list_and_filter_teaching_assignments(): void
    {
        $subject1 = Subject::create(['code' => 'MAT-10', 'name' => 'Matematika', 'subject_group' => 'Kelompok A']);
        $subject2 = Subject::create(['code' => 'FIS-10', 'name' => 'Fisika', 'subject_group' => 'Kelompok B']);

        TeachingAssignment::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->class->id,
            'subject_id' => $subject1->id,
            'teacher_id' => $this->teacher1->id,
            'hours_per_week' => 4,
        ]);

        TeachingAssignment::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->class->id,
            'subject_id' => $subject2->id,
            'teacher_id' => $this->teacher2->id,
            'hours_per_week' => 3,
        ]);

        // Filter by teacher_id
        $respTeacher = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/teaching-assignments?teacher_id={$this->teacher1->id}");

        $respTeacher->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.subject.code', 'MAT-10');

        // Filter by subject_id
        $respSubject = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/teaching-assignments?subject_id={$subject2->id}");

        $respSubject->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.teacher.nip', $this->teacher2->nip);
    }

    /**
     * BE-MST-06: Admin can update teaching assignment
     */
    public function test_be_mst_06_admin_can_update_teaching_assignment(): void
    {
        $subject = Subject::create(['code' => 'ING-10', 'name' => 'Bahasa Inggris', 'subject_group' => 'Kelompok A']);

        $assignment = TeachingAssignment::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher1->id,
            'hours_per_week' => 2,
        ]);

        // Update teacher & hours_per_week
        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/teaching-assignments/{$assignment->id}", [
                'teacher_id' => $this->teacher2->id,
                'hours_per_week' => 4,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.teacher_id', $this->teacher2->id)
            ->assertJsonPath('data.hours_per_week', 4);

        $this->assertDatabaseHas('teaching_assignments', [
            'id' => $assignment->id,
            'teacher_id' => $this->teacher2->id,
            'hours_per_week' => 4,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'teaching_assignment.updated',
            'entity' => 'TeachingAssignment',
            'entity_id' => $assignment->id,
        ]);
    }

    /**
     * BE-MST-06: Cannot delete teaching assignment with existing schedules (409 CONFLICT)
     */
    public function test_be_mst_06_cannot_delete_teaching_assignment_with_existing_schedules(): void
    {
        $subject = Subject::create(['code' => 'EKO-10', 'name' => 'Ekonomi', 'subject_group' => 'Kelompok C']);

        $assignment = TeachingAssignment::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher1->id,
            'hours_per_week' => 2,
        ]);

        $timeSlot = TimeSlot::create([
            'name' => 'Jam 1',
            'start_time' => '07:00',
            'end_time' => '07:45',
        ]);

        Schedule::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher1->id,
            'time_slot_id' => $timeSlot->id,
            'day_of_week' => 1,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/teaching-assignments/{$assignment->id}");

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'CONFLICT')
            ->assertJsonPath('error.message', 'Penugasan mengajar tidak dapat dihapus karena sudah memiliki jadwal pelajaran terikat.');

        $this->assertDatabaseHas('teaching_assignments', [
            'id' => $assignment->id,
        ]);
    }

    /**
     * BE-MST-06: Admin can delete teaching assignment without schedules
     */
    public function test_be_mst_06_admin_can_delete_teaching_assignment_without_schedules(): void
    {
        $subject = Subject::create(['code' => 'SOS-10', 'name' => 'Sosiologi', 'subject_group' => 'Kelompok C']);

        $assignment = TeachingAssignment::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher1->id,
            'hours_per_week' => 2,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/teaching-assignments/{$assignment->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'Penugasan mengajar berhasil dihapus');

        $this->assertDatabaseMissing('teaching_assignments', [
            'id' => $assignment->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'teaching_assignment.deleted',
            'entity' => 'TeachingAssignment',
            'entity_id' => $assignment->id,
        ]);
    }
}
