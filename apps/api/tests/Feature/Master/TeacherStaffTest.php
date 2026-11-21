<?php

namespace Tests\Feature\Master;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Staff;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TeacherStaffTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected Role $adminRole;

    protected Role $guestRole;

    protected Permission $permManageTeacher;

    protected function setUp(): void
    {
        parent::setUp();

        // Permission
        $this->permManageTeacher = Permission::create([
            'code' => 'master.teacher.manage',
            'description' => 'Mengelola data guru dan staf',
        ]);

        // Admin Role
        $this->adminRole = Role::create([
            'code' => 'super_admin',
            'name' => 'Super Administrator',
            'is_system' => true,
        ]);
        $this->adminRole->permissions()->attach([$this->permManageTeacher->id]);

        // Guest Role without permissions
        $this->guestRole = Role::create([
            'code' => 'guest',
            'name' => 'Tamu',
            'is_system' => false,
        ]);

        // Users
        $this->adminUser = User::create([
            'username' => 'admin_sdm',
            'email' => 'admin.sdm@labschool.sch.id',
            'password_hash' => Hash::make('AdminSecret123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($this->adminRole->id);

        $this->regularUser = User::create([
            'username' => 'guest_sdm',
            'email' => 'guest.sdm@labschool.sch.id',
            'password_hash' => Hash::make('GuestSecret123!'),
            'status' => 'active',
        ]);
        $this->regularUser->roles()->attach($this->guestRole->id);
    }

    /**
     * BE-MST-03: Unauthenticated requests are rejected with 401
     */
    public function test_be_mst_03_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/teachers')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->postJson('/api/v1/teachers', [])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->getJson('/api/v1/staff')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->postJson('/api/v1/staff', [])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    /**
     * BE-MST-03: User without permission is forbidden (403)
     */
    public function test_be_mst_03_user_without_permission_is_forbidden(): void
    {
        $this->actingAs($this->regularUser)
            ->getJson('/api/v1/teachers')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($this->regularUser)
            ->postJson('/api/v1/teachers', [
                'full_name' => 'Guru Tanpa Izin',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($this->regularUser)
            ->getJson('/api/v1/staff')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($this->regularUser)
            ->postJson('/api/v1/staff', [
                'full_name' => 'Staf Tanpa Izin',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-MST-03: Admin can create teacher and logs audit
     */
    public function test_be_mst_03_can_create_teacher_and_logs_audit(): void
    {
        $payload = [
            'nip' => '198001012005011001',
            'nuptk' => '1234567890123456',
            'full_name' => 'Prof. Dr. Agus Salim, M.Pd.',
            'position' => 'Guru Matematika Utama',
            'status' => 'tetap',
        ];

        $resp = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/teachers', $payload);

        $resp->assertStatus(201)
            ->assertJsonPath('data.nip', '198001012005011001')
            ->assertJsonPath('data.full_name', 'Prof. Dr. Agus Salim, M.Pd.')
            ->assertJsonPath('data.status', 'tetap');

        $teacherId = $resp->json('data.id');

        $this->assertDatabaseHas('teachers', [
            'id' => $teacherId,
            'nip' => '198001012005011001',
            'full_name' => 'Prof. Dr. Agus Salim, M.Pd.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'teacher.created',
            'entity' => 'Teacher',
            'entity_id' => $teacherId,
        ]);
    }

    /**
     * BE-MST-03: Teacher validation errors (missing name, duplicate NIP, invalid status)
     */
    public function test_be_mst_03_teacher_validation_errors(): void
    {
        // 1. Missing full_name
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/teachers', [
                'nip' => '198001012005011002',
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // 2. Duplicate NIP
        Teacher::create([
            'nip' => '198001012005011001',
            'full_name' => 'Guru Eksis',
            'status' => 'tetap',
        ]);

        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/teachers', [
                'nip' => '198001012005011001',
                'full_name' => 'Guru NIP Duplikat',
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // 3. Invalid status
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/teachers', [
                'full_name' => 'Guru Status Salah',
                'status' => 'magang', // not tetap, honorer, kontrak
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /**
     * BE-MST-03: Update teacher and uniqueness checks
     */
    public function test_be_mst_03_update_teacher_and_uniqueness_checks(): void
    {
        $teacher1 = Teacher::create([
            'nip' => '198501012010011001',
            'full_name' => 'Guru Satu',
            'status' => 'tetap',
        ]);

        $teacher2 = Teacher::create([
            'nip' => '198501012010011002',
            'full_name' => 'Guru Dua',
            'status' => 'honorer',
        ]);

        // Attempt updating teacher2 with teacher1's NIP -> 400 VALIDATION_FAILED
        $this->actingAs($this->adminUser)
            ->putJson("/api/v1/teachers/{$teacher2->id}", [
                'nip' => '198501012010011001',
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // Update teacher2 with valid new position and status
        $resp = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/teachers/{$teacher2->id}", [
                'position' => 'Wakil Kepala Sekolah Bidang Kurikulum',
                'status' => 'tetap',
            ]);

        $resp->assertStatus(200)
            ->assertJsonPath('data.position', 'Wakil Kepala Sekolah Bidang Kurikulum')
            ->assertJsonPath('data.status', 'tetap');

        $this->assertDatabaseHas('teachers', [
            'id' => $teacher2->id,
            'position' => 'Wakil Kepala Sekolah Bidang Kurikulum',
            'status' => 'tetap',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'teacher.updated',
            'entity' => 'Teacher',
            'entity_id' => $teacher2->id,
        ]);
    }

    /**
     * BE-MST-03: Delete teacher soft deletes and logs audit
     */
    public function test_be_mst_03_can_delete_teacher_soft_deletes(): void
    {
        $teacher = Teacher::create([
            'nip' => '199001012015011001',
            'full_name' => 'Guru Hapus',
            'status' => 'kontrak',
        ]);

        $resp = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/teachers/{$teacher->id}");

        $resp->assertStatus(200);

        $this->assertSoftDeleted('teachers', [
            'id' => $teacher->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'teacher.deleted',
            'entity' => 'Teacher',
            'entity_id' => $teacher->id,
        ]);
    }

    /**
     * BE-MST-03: Cannot delete teacher with active teaching assignments (409)
     */
    public function test_be_mst_03_cannot_delete_teacher_with_active_teaching_assignments(): void
    {
        $teacher = Teacher::create([
            'nip' => '198501012010011009',
            'full_name' => 'Guru Mengajar Aktif',
            'status' => 'tetap',
        ]);

        $year = AcademicYear::create([
            'name' => '2024/2025',
            'start_date' => '2024-07-15',
            'end_date' => '2025-06-20',
            'is_active' => true,
        ]);

        $semester = Semester::create([
            'academic_year_id' => $year->id,
            'name' => 'Ganjil',
            'start_date' => '2024-07-15',
            'end_date' => '2024-12-20',
            'is_active' => true,
        ]);

        $gradeLevel = GradeLevel::create([
            'name' => 'Kelas 10',
            'sort_order' => 1,
        ]);

        $class = SchoolClass::create([
            'academic_year_id' => $year->id,
            'grade_level_id' => $gradeLevel->id,
            'name' => 'X-MIPA-1',
            'capacity' => 36,
        ]);

        $subject = Subject::create([
            'code' => 'FIS-10',
            'name' => 'Fisika',
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
            ->deleteJson("/api/v1/teachers/{$teacher->id}");

        $resp->assertStatus(409)
            ->assertJsonPath('error.code', 'CONFLICT');

        $this->assertDatabaseHas('teachers', [
            'id' => $teacher->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * BE-MST-03: List teachers with search and status filter
     */
    public function test_be_mst_03_list_teachers_with_search_and_filter(): void
    {
        Teacher::create([
            'nip' => '11111',
            'full_name' => 'Drs. Hendro Wibowo',
            'status' => 'tetap',
            'position' => 'Guru Biologi',
        ]);

        Teacher::create([
            'nip' => '22222',
            'full_name' => 'Rina Andriani, S.Pd.',
            'status' => 'honorer',
            'position' => 'Guru Seni Budaya',
        ]);

        // Search by name
        $respSearch = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/teachers?q=Hendro');

        $respSearch->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Drs. Hendro Wibowo');

        // Filter by status
        $respFilter = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/teachers?status=honorer');

        $respFilter->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Rina Andriani, S.Pd.');
    }

    /**
     * BE-MST-03: Import teachers from CSV
     */
    public function test_be_mst_03_import_teachers_success_from_csv(): void
    {
        $csvContent = "nip,nuptk,full_name,position,status\n"
            ."198001012005011050,1111222233334444,Guru Import A,Guru Bahasa Indonesia,tetap\n"
            ."198001012005011051,1111222233334445,Guru Import B,Guru Bahasa Inggris,honorer\n";

        $file = UploadedFile::fake()->createWithContent('teachers.csv', $csvContent);

        $resp = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/teachers/import', [
                'file' => $file,
            ]);

        $resp->assertStatus(201)
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.imported', 2)
            ->assertJsonPath('data.failed', 0);

        $this->assertDatabaseHas('teachers', [
            'nip' => '198001012005011050',
            'full_name' => 'Guru Import A',
        ]);

        $this->assertDatabaseHas('teachers', [
            'nip' => '198001012005011051',
            'full_name' => 'Guru Import B',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'teacher.imported',
            'entity' => 'Teacher',
        ]);
    }

    /**
     * BE-MST-03: Import teachers reports errors per row
     */
    public function test_be_mst_03_import_teachers_validation_errors_reported_per_row(): void
    {
        Teacher::create([
            'nip' => '198001012005011099',
            'full_name' => 'Guru Sudah Terdaftar',
            'status' => 'tetap',
        ]);

        $csvContent = "nip,nuptk,full_name,position,status\n"
            ."198001012005011099,1111222233334499,Guru Duplikat NIP,Guru Matematika,tetap\n" // row 2 duplicate nip
            .",,Guru Tanpa NIP Tapi Status Invalid,Guru Seni,outsourcing\n"; // row 3 invalid status

        $file = UploadedFile::fake()->createWithContent('invalid_teachers.csv', $csvContent);

        $resp = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/teachers/import', [
                'file' => $file,
            ]);

        $resp->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $details = $resp->json('error.details');
        $this->assertNotEmpty($details);
        $this->assertEquals(2, $details[0]['row']);
        $this->assertEquals(3, $details[1]['row']);
    }

    /**
     * BE-MST-03: Staff CRUD and audit logging
     */
    public function test_be_mst_03_can_create_update_delete_staff_and_logs_audit(): void
    {
        // 1. Create Staff
        $createResp = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/staff', [
                'full_name' => 'Bambang Sudarsono',
                'position' => 'Kepala Tata Usaha',
                'unit' => 'Tata Usaha',
            ]);

        $createResp->assertStatus(201)
            ->assertJsonPath('data.full_name', 'Bambang Sudarsono')
            ->assertJsonPath('data.unit', 'Tata Usaha');

        $staffId = $createResp->json('data.id');

        $this->assertDatabaseHas('staff', [
            'id' => $staffId,
            'full_name' => 'Bambang Sudarsono',
            'unit' => 'Tata Usaha',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'staff.created',
            'entity' => 'Staff',
            'entity_id' => $staffId,
        ]);

        // 2. Update Staff
        $updateResp = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/staff/{$staffId}", [
                'position' => 'Koordinator Administrasi Akademik',
            ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('data.position', 'Koordinator Administrasi Akademik');

        $this->assertDatabaseHas('staff', [
            'id' => $staffId,
            'position' => 'Koordinator Administrasi Akademik',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'staff.updated',
            'entity' => 'Staff',
            'entity_id' => $staffId,
        ]);

        // 3. Delete Staff (Soft delete)
        $deleteResp = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/staff/{$staffId}");

        $deleteResp->assertStatus(200);

        $this->assertSoftDeleted('staff', [
            'id' => $staffId,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'staff.deleted',
            'entity' => 'Staff',
            'entity_id' => $staffId,
        ]);
    }

    /**
     * BE-MST-03: List staff with unit filter and search
     */
    public function test_be_mst_03_list_staff_with_unit_filter_and_search(): void
    {
        Staff::create([
            'full_name' => 'Nurul Hidayah',
            'position' => 'Pustakawan',
            'unit' => 'Perpustakaan',
        ]);

        Staff::create([
            'full_name' => 'Joko Prasetyo',
            'position' => 'Teknisi Komputer',
            'unit' => 'Laboratorium',
        ]);

        // Filter by unit
        $respFilter = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/staff?unit=Perpustakaan');

        $respFilter->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Nurul Hidayah');

        // Search by name
        $respSearch = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/staff?q=Joko');

        $respSearch->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.position', 'Teknisi Komputer');
    }

    /**
     * BE-MST-03: Import staff from CSV
     */
    public function test_be_mst_03_import_staff_from_csv(): void
    {
        $csvContent = "full_name,position,unit\n"
            ."Staf Import Satu,Staf Administrasi,Tata Usaha\n"
            ."Staf Import Dua,Petugas Lab IPA,Laboratorium\n";

        $file = UploadedFile::fake()->createWithContent('staff.csv', $csvContent);

        $resp = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/staff/import', [
                'file' => $file,
            ]);

        $resp->assertStatus(201)
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.imported', 2)
            ->assertJsonPath('data.failed', 0);

        $this->assertDatabaseHas('staff', [
            'full_name' => 'Staf Import Satu',
            'unit' => 'Tata Usaha',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'staff.imported',
            'entity' => 'Staff',
        ]);
    }
}
