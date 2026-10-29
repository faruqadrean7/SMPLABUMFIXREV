<?php

namespace Tests\Feature\Master;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected User $studentUser1;

    protected User $studentUser2;

    protected Student $student1;

    protected Student $student2;

    protected Role $adminRole;

    protected Role $studentRole;

    protected Permission $permReadStudent;

    protected Permission $permWriteStudent;

    protected function setUp(): void
    {
        parent::setUp();

        // Permissions
        $this->permReadStudent = Permission::create([
            'code' => 'master.student.read',
            'description' => 'Melihat data siswa',
        ]);
        $this->permWriteStudent = Permission::create([
            'code' => 'master.student.write',
            'description' => 'Mengubah data siswa',
        ]);

        // Roles
        $this->adminRole = Role::create([
            'code' => 'super_admin',
            'name' => 'Super Administrator',
            'is_system' => true,
        ]);
        $this->adminRole->permissions()->attach([
            $this->permReadStudent->id,
            $this->permWriteStudent->id,
        ]);

        $this->studentRole = Role::create([
            'code' => 'student',
            'name' => 'Siswa',
            'is_system' => true,
        ]);

        $regularRole = Role::create([
            'code' => 'guest',
            'name' => 'Tamu',
            'is_system' => false,
        ]);

        // Users
        $this->adminUser = User::create([
            'username' => 'admin_tu',
            'email' => 'admin.tu@labschool.sch.id',
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
        $this->regularUser->roles()->attach($regularRole->id);

        // Student 1 User and Record
        $this->studentUser1 = User::create([
            'username' => 'siswa1',
            'email' => 'siswa1@labschool.sch.id',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $this->studentUser1->roles()->attach($this->studentRole->id);

        $this->student1 = Student::create([
            'user_id' => $this->studentUser1->id,
            'nis' => '10001',
            'nisn' => '0011223344',
            'nik_enc' => '3578010101080001',
            'full_name' => 'Ahmad Dahlan',
            'gender' => 'L',
            'birth_place' => 'Surabaya',
            'birth_date' => '2008-05-10',
            'religion' => 'Islam',
            'address' => 'Jl. Ketintang No. 10',
            'status' => 'aktif',
            'entry_date' => '2024-07-15',
        ]);

        // Student 2 User and Record
        $this->studentUser2 = User::create([
            'username' => 'siswa2',
            'email' => 'siswa2@labschool.sch.id',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $this->studentUser2->roles()->attach($this->studentRole->id);

        $this->student2 = Student::create([
            'user_id' => $this->studentUser2->id,
            'nis' => '10002',
            'nisn' => '0011223355',
            'nik_enc' => '3578010202080002',
            'full_name' => 'Siti Fatimah',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2008-08-12',
            'religion' => 'Islam',
            'address' => 'Jl. Ijen No. 5',
            'status' => 'aktif',
            'entry_date' => '2024-07-15',
        ]);
    }

    /**
     * BE-MST-02: Unauthenticated requests are rejected with 401
     */
    public function test_be_mst_02_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/students')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->postJson('/api/v1/students', [])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->getJson('/api/v1/students/export')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->postJson('/api/v1/students/import', [])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    /**
     * BE-MST-02: User without permission cannot write or export (403)
     */
    public function test_be_mst_02_user_without_permission_cannot_write_or_export(): void
    {
        // Regular user cannot create student
        $this->actingAs($this->regularUser)
            ->postJson('/api/v1/students', [
                'nis' => '99999',
                'nisn' => '9999999999',
                'nik' => '3578010101089999',
                'full_name' => 'Budi Santoso',
                'gender' => 'L',
                'birth_place' => 'Jakarta',
                'birth_date' => '2008-01-01',
                'entry_date' => '2024-07-15',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // Regular user cannot export students
        $this->actingAs($this->regularUser)
            ->getJson('/api/v1/students/export')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-MST-02: Admin can create student and initial status history is recorded
     */
    public function test_be_mst_02_can_create_student_and_initial_status_history_is_logged(): void
    {
        $payload = [
            'nis' => '10003',
            'nisn' => '0011223366',
            'nik' => '3578010101080003',
            'full_name' => 'Dewi Sartika',
            'gender' => 'P',
            'birth_place' => 'Bandung',
            'birth_date' => '2008-03-20',
            'religion' => 'Islam',
            'address' => 'Jl. Merdeka No. 1',
            'entry_date' => '2024-07-15',
            'status' => 'aktif',
        ];

        $resp = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/students', $payload);

        $resp->assertStatus(201)
            ->assertJsonPath('data.nis', '10003')
            ->assertJsonPath('data.full_name', 'Dewi Sartika')
            ->assertJsonPath('data.status', 'aktif');

        $studentId = $resp->json('data.id');

        // Verify Student created
        $this->assertDatabaseHas('students', [
            'id' => $studentId,
            'nis' => '10003',
            'nisn' => '0011223366',
            'status' => 'aktif',
        ]);

        // Verify initial status history recorded
        $this->assertDatabaseHas('student_status_history', [
            'student_id' => $studentId,
            'status' => 'aktif',
            'reason' => 'Pendaftaran siswa baru',
        ]);

        // Verify audit log recorded
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'student.created',
            'entity' => 'Student',
            'entity_id' => $studentId,
        ]);

        // Check NIK encryption in DB: raw database column is encrypted, not plaintext
        $rawStudent = DB::table('students')->where('id', $studentId)->first();
        $this->assertNotEquals('3578010101080003', $rawStudent->nik_enc);
    }

    /**
     * BE-MST-02: Validation errors on student creation
     */
    public function test_be_mst_02_create_student_validation_errors(): void
    {
        // 1. Missing required fields
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/students', [])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details']]);

        // 2. Invalid NIK format (must be 16 digits)
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/students', [
                'nis' => '10004',
                'nisn' => '0011223377',
                'nik' => '12345', // only 5 digits
                'full_name' => 'Test Siswa',
                'gender' => 'L',
                'birth_place' => 'Surabaya',
                'birth_date' => '2008-01-01',
                'entry_date' => '2024-07-15',
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // 3. Duplicate NIS
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/students', [
                'nis' => '10001', // already used by student1
                'nisn' => '0011223399',
                'nik' => '3578010101080009',
                'full_name' => 'Duplicate NIS',
                'gender' => 'L',
                'birth_place' => 'Surabaya',
                'birth_date' => '2008-01-01',
                'entry_date' => '2024-07-15',
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // 4. Duplicate NISN
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/students', [
                'nis' => '10099',
                'nisn' => '0011223344', // already used by student1
                'nik' => '3578010101080009',
                'full_name' => 'Duplicate NISN',
                'gender' => 'L',
                'birth_place' => 'Surabaya',
                'birth_date' => '2008-01-01',
                'entry_date' => '2024-07-15',
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // 5. Invalid gender
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/students', [
                'nis' => '10098',
                'nisn' => '0011223398',
                'nik' => '3578010101080098',
                'full_name' => 'Invalid Gender',
                'gender' => 'X',
                'birth_place' => 'Surabaya',
                'birth_date' => '2008-01-01',
                'entry_date' => '2024-07-15',
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /**
     * BE-MST-02: Admin can update student and change status with history
     */
    public function test_be_mst_02_admin_can_update_student_and_change_status(): void
    {
        $updateResp = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/students/{$this->student1->id}", [
                'full_name' => 'Ahmad Dahlan Al-Mansyur',
                'status' => 'pindah',
                'status_reason' => 'Pindah domisili orang tua ke Jakarta',
                'status_effective_date' => '2025-01-10',
            ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('data.full_name', 'Ahmad Dahlan Al-Mansyur')
            ->assertJsonPath('data.status', 'pindah');

        $this->assertDatabaseHas('students', [
            'id' => $this->student1->id,
            'full_name' => 'Ahmad Dahlan Al-Mansyur',
            'status' => 'pindah',
        ]);

        $this->assertDatabaseHas('student_status_history', [
            'student_id' => $this->student1->id,
            'status' => 'pindah',
            'reason' => 'Pindah domisili orang tua ke Jakarta',
            'effective_date' => '2025-01-10 00:00:00',
        ]);
    }

    /**
     * BE-MST-02: Admin can change status via dedicated endpoint
     */
    public function test_be_mst_02_admin_can_change_status_via_dedicated_endpoint(): void
    {
        $resp = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/students/{$this->student2->id}/status", [
                'status' => 'lulus',
                'reason' => 'Lulus dari jenjang SMA',
                'effective_date' => '2026-06-25',
            ]);

        $resp->assertStatus(200)
            ->assertJsonPath('data.status', 'lulus');

        $this->assertDatabaseHas('students', [
            'id' => $this->student2->id,
            'status' => 'lulus',
            'graduated_at' => '2026-06-25 00:00:00',
        ]);

        $this->assertDatabaseHas('student_status_history', [
            'student_id' => $this->student2->id,
            'status' => 'lulus',
            'reason' => 'Lulus dari jenjang SMA',
            'effective_date' => '2026-06-25 00:00:00',
        ]);
    }

    /**
     * BE-MST-02: Get status history
     */
    public function test_be_mst_02_get_status_history(): void
    {
        StudentStatusHistory::create([
            'student_id' => $this->student1->id,
            'status' => 'aktif',
            'reason' => 'Pendaftaran siswa baru',
            'effective_date' => '2024-07-15',
        ]);

        StudentStatusHistory::create([
            'student_id' => $this->student1->id,
            'status' => 'pindah',
            'reason' => 'Pindah sekolah',
            'effective_date' => '2025-01-10',
        ]);

        $resp = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/students/{$this->student1->id}/status-history");

        $resp->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.status', 'pindah')
            ->assertJsonPath('data.1.status', 'aktif');
    }

    /**
     * BE-MST-02: Admin can delete student (soft delete)
     */
    public function test_be_mst_02_can_delete_student_soft_deletes(): void
    {
        $resp = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/students/{$this->student2->id}");

        $resp->assertStatus(200);

        $this->assertSoftDeleted('students', [
            'id' => $this->student2->id,
        ]);
    }

    /**
     * BE-MST-02: IDOR Protection: Student can only view their own profile
     */
    public function test_be_mst_02_idor_protection_student_can_only_view_self(): void
    {
        // Student 1 accesses own profile -> 200 OK
        $this->actingAs($this->studentUser1)
            ->getJson("/api/v1/students/{$this->student1->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $this->student1->id);

        // Student 1 attempts to access Student 2's profile -> 403 FORBIDDEN
        $this->actingAs($this->studentUser1)
            ->getJson("/api/v1/students/{$this->student2->id}")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-MST-02: List students with search and filters
     */
    public function test_be_mst_02_list_students_with_search_and_filters(): void
    {
        // 1. Search by name
        $respName = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/students?q=Ahmad');

        $respName->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nis', '10001');

        // 2. Search by NIS
        $respNis = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/students?q=10002');

        $respNis->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Siti Fatimah');

        // 3. Filter by class_id
        $year = AcademicYear::create([
            'name' => '2024/2025',
            'start_date' => '2024-07-15',
            'end_date' => '2025-06-20',
            'is_active' => true,
        ]);

        $grade = GradeLevel::create([
            'name' => 'Kelas 10',
            'sort_order' => 1,
        ]);

        $class = SchoolClass::create([
            'academic_year_id' => $year->id,
            'grade_level_id' => $grade->id,
            'name' => 'X-MIPA-1',
            'capacity' => 36,
        ]);

        $this->student1->classes()->attach($class->id, [
            'id' => (string) Str::uuid(),
            'joined_at' => '2024-07-15',
        ]);

        $respClass = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/students?class_id={$class->id}");

        $respClass->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->student1->id);
    }

    /**
     * BE-MST-02: Import students successfully from CSV
     */
    public function test_be_mst_02_import_students_success_from_csv(): void
    {
        $csvContent = "nis,nisn,nik,full_name,gender,birth_place,birth_date,religion,address,entry_date\n"
            ."10010,0011223310,3578010101080010,Siswa Import Satu,L,Surabaya,2008-01-10,Islam,Jl. Melati 1,2024-07-15\n"
            ."10011,0011223311,3578010101080011,Siswa Import Dua,P,Malang,2008-02-15,Kristen,Jl. Mawar 2,2024-07-15\n";

        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $resp = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/students/import', [
                'file' => $file,
            ]);

        $resp->assertStatus(201)
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.imported', 2)
            ->assertJsonPath('data.failed', 0);

        $this->assertDatabaseHas('students', [
            'nis' => '10010',
            'full_name' => 'Siswa Import Satu',
        ]);

        $this->assertDatabaseHas('students', [
            'nis' => '10011',
            'full_name' => 'Siswa Import Dua',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'student.imported',
            'entity' => 'Student',
        ]);
    }

    /**
     * BE-MST-02: Import students reports errors per row if validation fails
     */
    public function test_be_mst_02_import_students_validation_errors_reported_per_row(): void
    {
        $csvContent = "nis,nisn,nik,full_name,gender,birth_place,birth_date,religion,address,entry_date\n"
            ."10001,0011223312,3578010101080012,Siswa Duplicate NIS,L,Surabaya,2008-01-10,Islam,Jl. Melati 1,2024-07-15\n" // NIS 10001 already exists
            ."10013,0011223313,99999,Siswa Invalid NIK,P,Malang,2008-02-15,Kristen,Jl. Mawar 2,2024-07-15\n"; // NIK 99999 invalid

        $file = UploadedFile::fake()->createWithContent('invalid_students.csv', $csvContent);

        $resp = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/students/import', [
                'file' => $file,
            ]);

        $resp->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $details = $resp->json('error.details');
        $this->assertNotEmpty($details);

        // Verify row numbers reported
        $this->assertEquals(2, $details[0]['row']);
        $this->assertEquals(3, $details[1]['row']);

        // Verify no duplicate record created
        $this->assertDatabaseMissing('students', [
            'nis' => '10013',
        ]);
    }

    /**
     * BE-MST-02: Export students to CSV stream and write audit log
     */
    public function test_be_mst_02_export_students_to_csv_and_logs_audit(): void
    {
        $resp = $this->actingAs($this->adminUser)
            ->get('/api/v1/students/export');

        $resp->assertStatus(200);
        $resp->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $resp->streamedContent();

        $this->assertStringContainsString('NIS,NISN', $content);
        $this->assertStringContainsString('Nama Lengkap', $content);
        $this->assertStringContainsString('10001', $content);
        $this->assertStringContainsString('Ahmad Dahlan', $content);
        $this->assertStringContainsString('10002', $content);
        $this->assertStringContainsString('Siti Fatimah', $content);

        // Verify export audit log
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'student.exported',
            'entity' => 'Student',
        ]);
    }

    /**
     * BE-MST-02: Filter students without class in specific academic year
     */
    public function test_be_mst_02_filter_students_without_class(): void
    {
        $ay = AcademicYear::create([
            'name' => '2024/2025',
            'semester' => 'ganjil',
            'start_date' => '2024-07-15',
            'end_date' => '2024-12-20',
            'is_active' => true,
        ]);

        $gl = GradeLevel::create([
            'level' => 10,
            'name' => 'Kelas 10',
            'order' => 1,
        ]);

        $class = SchoolClass::create([
            'academic_year_id' => $ay->id,
            'grade_level_id' => $gl->id,
            'name' => 'X-A',
            'capacity' => 36,
        ]);

        // Attach student1 to class, student2 remains without class
        $class->students()->attach($this->student1->id, [
            'id' => (string) Str::uuid(),
            'joined_at' => '2024-07-15',
            'left_at' => null,
        ]);

        // Query with without_class=true
        $resp = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/students?without_class=true&academic_year_id={$ay->id}");

        $resp->assertStatus(200);
        $data = $resp->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->student2->id, $data[0]['id']);
    }
}
