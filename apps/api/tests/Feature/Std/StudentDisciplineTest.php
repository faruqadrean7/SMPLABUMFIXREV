<?php

namespace Tests\Feature\Std;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\Permission;
use App\Models\PointThreshold;
use App\Models\PointType;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentPoint;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentDisciplineTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $kesiswaanUser;

    protected User $teacherUser;

    protected User $counselorUser;

    protected User $studentUser;

    protected User $otherStudentUser;

    protected User $parentUser;

    protected Student $student;

    protected Student $otherStudent;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleKesiswaan = Role::firstOrCreate(['code' => 'kesiswaan'], ['name' => 'Staf Kesiswaan', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleCounselor = Role::firstOrCreate(['code' => 'counselor'], ['name' => 'Guru BK', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $roleParent = Role::firstOrCreate(['code' => 'parent'], ['name' => 'Orang Tua / Wali', 'is_system' => true]);

        $permWrite = Permission::firstOrCreate(['code' => 'student.point.write'], ['description' => 'Mencatat poin pelanggaran/prestasi']);
        $permRead = Permission::firstOrCreate(['code' => 'student.point.read'], ['description' => 'Melihat poin pelanggaran/prestasi']);

        $roleAdmin->permissions()->syncWithoutDetaching([$permWrite->id, $permRead->id]);
        $roleKesiswaan->permissions()->syncWithoutDetaching([$permWrite->id, $permRead->id]);
        $roleCounselor->permissions()->syncWithoutDetaching([$permWrite->id, $permRead->id]);
        $roleTeacher->permissions()->syncWithoutDetaching([$permWrite->id, $permRead->id]);

        $this->adminUser = User::create([
            'username' => 'admin_std',
            'email' => 'admin_std@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->kesiswaanUser = User::create([
            'username' => 'staff_kesiswaan',
            'email' => 'kesiswaan@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->kesiswaanUser->roles()->attach($roleKesiswaan->id);

        $this->teacherUser = User::create([
            'username' => 'guru_budi',
            'email' => 'guru_budi@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher->id);

        $this->counselorUser = User::create([
            'username' => 'guru_bk_siti',
            'email' => 'bk_siti@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->counselorUser->roles()->attach($roleCounselor->id);

        $this->studentUser = User::create([
            'username' => '20261001',
            'email' => 'fajar_siswa@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($roleStudent->id);

        $this->otherStudentUser = User::create([
            'username' => '20261002',
            'email' => 'rina_siswa@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->otherStudentUser->roles()->attach($roleStudent->id);

        $this->parentUser = User::create([
            'username' => 'ortu_fajar',
            'email' => 'ortu_fajar@gmail.com',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->parentUser->roles()->attach($roleParent->id);

        $this->student = Student::create([
            'user_id' => $this->studentUser->id,
            'nis' => '20261001',
            'nisn' => '0098765431',
            'nik_enc' => '3201010101010001',
            'full_name' => 'Ahmad Fajar',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2009-05-12',
            'entry_date' => '2026-07-15',
            'status' => 'active',
        ]);

        $this->otherStudent = Student::create([
            'user_id' => $this->otherStudentUser->id,
            'nis' => '20261002',
            'nisn' => '0098765432',
            'nik_enc' => '3201010101010002',
            'full_name' => 'Rina Marlina',
            'gender' => 'P',
            'birth_place' => 'Jakarta',
            'birth_date' => '2009-08-20',
            'entry_date' => '2026-07-15',
            'status' => 'active',
        ]);

        $guardian = Guardian::create([
            'user_id' => $this->parentUser->id,
            'full_name' => 'Hendra Setiawan (Ayah Fajar)',
            'phone' => '081234567890',
        ]);

        $this->student->guardians()->attach($guardian->id, [
            'relationship' => 'ayah',
            'is_primary' => true,
        ]);
    }

    public function test_be_std_01_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/point-types')->assertStatus(401);
        $this->getJson('/api/v1/point-thresholds')->assertStatus(401);
        $this->getJson('/api/v1/student-points')->assertStatus(401);
        $this->getJson("/api/v1/students/{$this->student->id}/points/summary")->assertStatus(401);
    }

    public function test_be_std_01_kesiswaan_admin_can_manage_point_types(): void
    {
        $res = $this->actingAs($this->kesiswaanUser)
            ->postJson('/api/v1/point-types', [
                'kind' => 'pelanggaran',
                'name' => 'Terlambat Masuk Sekolah (>15 menit)',
                'points' => 5,
                'severity' => 'rendah',
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.name', 'Terlambat Masuk Sekolah (>15 menit)')
            ->assertJsonPath('data.kind', 'pelanggaran')
            ->assertJsonPath('data.points', 5);

        $typeId = $res->json('data.id');

        // Update
        $updateRes = $this->actingAs($this->kesiswaanUser)
            ->putJson("/api/v1/point-types/{$typeId}", [
                'points' => 10,
                'severity' => 'sedang',
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.points', 10)
            ->assertJsonPath('data.severity', 'sedang');

        // List
        $listRes = $this->actingAs($this->kesiswaanUser)
            ->getJson('/api/v1/point-types?kind=pelanggaran');

        $listRes->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_be_std_01_regular_user_cannot_create_point_types(): void
    {
        $this->actingAs($this->studentUser)
            ->postJson('/api/v1/point-types', [
                'kind' => 'pelanggaran',
                'name' => 'Ilegal Rule',
                'points' => 5,
            ])
            ->assertStatus(403);
    }

    public function test_be_std_01_cannot_delete_point_type_with_recorded_points(): void
    {
        $pointType = PointType::create([
            'kind' => 'pelanggaran',
            'name' => 'Merokok di Lingkungan Sekolah',
            'points' => 50,
            'severity' => 'berat',
        ]);

        StudentPoint::create([
            'student_id' => $this->student->id,
            'point_type_id' => $pointType->id,
            'recorded_by' => $this->teacherUser->id,
            'point_date' => Carbon::now()->toDateString(),
        ]);

        $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/point-types/{$pointType->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'HTTP_ERROR');
    }

    public function test_be_std_01_can_delete_point_type_without_recorded_points(): void
    {
        $pointType = PointType::create([
            'kind' => 'prestasi',
            'name' => 'Juara 1 Lomba Puisi',
            'points' => 20,
            'severity' => 'sedang',
        ]);

        $this->actingAs($this->kesiswaanUser)
            ->deleteJson("/api/v1/point-types/{$pointType->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('point_types', ['id' => $pointType->id]);
    }

    public function test_be_std_01_admin_can_manage_point_thresholds(): void
    {
        $res = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/point-thresholds', [
                'min_points' => 25,
                'action' => 'Peringatan Tertulis 1 (SP1)',
                'notify_roles' => ['homeroom_teacher', 'counselor'],
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.min_points', 25)
            ->assertJsonPath('data.action', 'Peringatan Tertulis 1 (SP1)');

        $thresholdId = $res->json('data.id');

        // Update
        $this->actingAs($this->adminUser)
            ->putJson("/api/v1/point-thresholds/{$thresholdId}", [
                'action' => 'Peringatan Tertulis 1 & Pembinaan Wali Kelas',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.action', 'Peringatan Tertulis 1 & Pembinaan Wali Kelas');

        // List
        $this->actingAs($this->adminUser)
            ->getJson('/api/v1/point-thresholds')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_be_std_01_teacher_and_bk_can_record_student_points(): void
    {
        $violationType = PointType::create([
            'kind' => 'pelanggaran',
            'name' => 'Tidak Menggunakan Seragam Lengkap',
            'points' => 5,
            'severity' => 'rendah',
        ]);

        $res = $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/student-points', [
                'student_id' => $this->student->id,
                'point_type_id' => $violationType->id,
                'point_date' => Carbon::now()->toDateString(),
                'notes' => 'Tidak memakai dasi dan ikat pinggang pada upacara',
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.point.student_id', $this->student->id)
            ->assertJsonPath('data.point.recorded_by', $this->teacherUser->id)
            ->assertJsonPath('data.cumulative_violation_points', 5);

        $this->assertDatabaseHas('student_points', [
            'student_id' => $this->student->id,
            'point_type_id' => $violationType->id,
            'recorded_by' => $this->teacherUser->id,
        ]);
    }

    public function test_be_std_01_student_cannot_record_points(): void
    {
        $violationType = PointType::create([
            'kind' => 'pelanggaran',
            'name' => 'Bermain Game Saat KBM',
            'points' => 10,
        ]);

        $this->actingAs($this->studentUser)
            ->postJson('/api/v1/student-points', [
                'student_id' => $this->student->id,
                'point_type_id' => $violationType->id,
            ])
            ->assertStatus(403);
    }

    public function test_be_std_01_recording_violation_accumulates_points_and_triggers_threshold_notification(): void
    {
        // Setup ambang batas
        $threshold = PointThreshold::create([
            'min_points' => 30,
            'action' => 'Panggilan Orang Tua ke Sekolah',
            'notify_roles' => ['counselor'],
        ]);

        $violationHeavy = PointType::create([
            'kind' => 'pelanggaran',
            'name' => 'Terlibat Perkelahian / Tawuran',
            'points' => 35,
            'severity' => 'berat',
        ]);

        $res = $this->actingAs($this->counselorUser)
            ->postJson('/api/v1/student-points', [
                'student_id' => $this->student->id,
                'point_type_id' => $violationHeavy->id,
                'notes' => 'Terlibat tawuran antar pelajar di luar gerbang sekolah',
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.cumulative_violation_points', 35)
            ->assertJsonPath('data.triggered_threshold.id', $threshold->id)
            ->assertJsonPath('data.triggered_threshold.action', 'Panggilan Orang Tua ke Sekolah');

        // Pastikan notifikasi terkirim ke user dengan role counselor
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->counselorUser->id,
            'notif_type' => 'student_violation_threshold',
        ]);
    }

    public function test_be_std_01_summary_calculates_violation_achievement_and_net_points_accurately(): void
    {
        $v1 = PointType::create(['kind' => 'pelanggaran', 'name' => 'Terlambat', 'points' => 10]);
        $v2 = PointType::create(['kind' => 'pelanggaran', 'name' => 'Atribut Kurang', 'points' => 15]);
        $a1 = PointType::create(['kind' => 'prestasi', 'name' => 'Juara 1 OSN Matematika', 'points' => 50]);

        StudentPoint::create([
            'student_id' => $this->student->id,
            'point_type_id' => $v1->id,
            'recorded_by' => $this->teacherUser->id,
            'point_date' => Carbon::now()->subDays(3)->toDateString(),
        ]);

        StudentPoint::create([
            'student_id' => $this->student->id,
            'point_type_id' => $v2->id,
            'recorded_by' => $this->teacherUser->id,
            'point_date' => Carbon::now()->subDays(1)->toDateString(),
        ]);

        StudentPoint::create([
            'student_id' => $this->student->id,
            'point_type_id' => $a1->id,
            'recorded_by' => $this->teacherUser->id,
            'point_date' => Carbon::now()->toDateString(),
        ]);

        // Ambang batas sanksi
        PointThreshold::create([
            'min_points' => 20,
            'action' => 'Peringatan Lisan & Konseling BK',
            'notify_roles' => ['counselor'],
        ]);

        $res = $this->actingAs($this->teacherUser)
            ->getJson("/api/v1/students/{$this->student->id}/points/summary");

        $res->assertStatus(200)
            ->assertJsonPath('data.total_violation_points', 25)
            ->assertJsonPath('data.total_achievement_points', 50)
            ->assertJsonPath('data.net_points', 25) // 50 - 25 = 25
            ->assertJsonPath('data.violation_count', 2)
            ->assertJsonPath('data.achievement_count', 1)
            ->assertJsonPath('data.active_sanction.action', 'Peringatan Lisan & Konseling BK');
    }

    public function test_be_std_01_student_can_only_view_own_summary_and_points_anti_idor(): void
    {
        // Siswa Fajar melihat summary dirinya sendiri -> 200 OK
        $this->actingAs($this->studentUser)
            ->getJson("/api/v1/students/{$this->student->id}/points/summary")
            ->assertStatus(200);

        // Siswa Fajar mencoba melihat summary siswa lain (Rina) -> 403 Forbidden
        $this->actingAs($this->studentUser)
            ->getJson("/api/v1/students/{$this->otherStudent->id}/points/summary")
            ->assertStatus(403);
    }

    public function test_be_std_01_parent_can_view_child_summary_anti_idor(): void
    {
        // Orang tua Fajar melihat summary Fajar -> 200 OK
        $this->actingAs($this->parentUser)
            ->getJson("/api/v1/students/{$this->student->id}/points/summary")
            ->assertStatus(200);

        // Orang tua Fajar mencoba melihat summary Rina -> 403 Forbidden
        $this->actingAs($this->parentUser)
            ->getJson("/api/v1/students/{$this->otherStudent->id}/points/summary")
            ->assertStatus(403);
    }

    public function test_be_std_01_validation_errors_when_recording_points_with_invalid_data(): void
    {
        $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/student-points', [
                'student_id' => 'not-a-valid-uuid',
                'point_type_id' => 'not-a-valid-uuid',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['student_id', 'point_type_id']);
    }

    public function test_be_std_01_delete_point_record_only_allowed_by_recorder_or_admin(): void
    {
        $violationType = PointType::create([
            'kind' => 'pelanggaran',
            'name' => 'Terlambat',
            'points' => 5,
        ]);

        $point = StudentPoint::create([
            'student_id' => $this->student->id,
            'point_type_id' => $violationType->id,
            'recorded_by' => $this->teacherUser->id,
            'point_date' => Carbon::now()->toDateString(),
        ]);

        // Counselor mencoba hapus catatan guru lain (bukan admin/kesiswaan) -> 403
        $this->actingAs($this->counselorUser)
            ->deleteJson("/api/v1/student-points/{$point->id}")
            ->assertStatus(403);

        // Guru yang mencatat dapat menghapusnya -> 200
        $this->actingAs($this->teacherUser)
            ->deleteJson("/api/v1/student-points/{$point->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('student_points', ['id' => $point->id]);
    }

    public function test_be_std_01_filter_points_by_class_and_search_query(): void
    {
        $academicYear = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]
        );
        $gradeLevel = GradeLevel::firstOrCreate(['name' => 'Kelas 10'], ['sort_order' => 10]);
        $classA = SchoolClass::create([
            'name' => 'X-IPA-1',
            'academic_year_id' => $academicYear->id,
            'grade_level_id' => $gradeLevel->id,
        ]);
        $classB = SchoolClass::create([
            'name' => 'X-IPA-2',
            'academic_year_id' => $academicYear->id,
            'grade_level_id' => $gradeLevel->id,
        ]);

        ClassStudent::create(['class_id' => $classA->id, 'student_id' => $this->student->id, 'joined_at' => Carbon::now()->toDateString()]);
        ClassStudent::create(['class_id' => $classB->id, 'student_id' => $this->otherStudent->id, 'joined_at' => Carbon::now()->toDateString()]);

        $type = PointType::create([
            'kind' => 'pelanggaran',
            'name' => 'Atribut Tidak Lengkap',
            'points' => 5,
        ]);

        StudentPoint::create([
            'student_id' => $this->student->id,
            'point_type_id' => $type->id,
            'recorded_by' => $this->teacherUser->id,
            'point_date' => Carbon::now()->toDateString(),
        ]);
        StudentPoint::create([
            'student_id' => $this->otherStudent->id,
            'point_type_id' => $type->id,
            'recorded_by' => $this->teacherUser->id,
            'point_date' => Carbon::now()->toDateString(),
        ]);

        // Filter by classA -> hanya student 1
        $resClass = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/student-points?class_id={$classA->id}");
        $resClass->assertStatus(200)
            ->assertJsonPath('data.0.student_id', $this->student->id);
        $this->assertCount(1, $resClass->json('data'));

        // Search by name "Fajar" -> hanya student 1
        $resSearch = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/student-points?search=Fajar');
        $resSearch->assertStatus(200);
        $this->assertCount(1, $resSearch->json('data'));
    }
}
