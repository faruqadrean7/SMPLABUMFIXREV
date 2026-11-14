<?php

namespace Tests\Feature\Backend;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase4RouteHarmonizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacherUser;

    protected Teacher $teacher;

    protected User $unauthorizedUser;

    protected User $authorizedCbtUser;

    protected User $authorizedGradeUser;

    protected AcademicYear $academicYear;

    protected Semester $semester;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Roles
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);

        // 2. Permissions
        $permCbtBankWrite = Permission::firstOrCreate(['code' => 'cbt.bank.write'], ['description' => 'Mengelola bank soal']);
        $permGradeWrite = Permission::firstOrCreate(['code' => 'academic.grade.write'], ['description' => 'Menginput nilai']);

        // 3. Academic Year & Semester
        $this->academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-15',
            'end_date' => '2027-06-20',
            'is_active' => true,
        ]);

        $this->semester = Semester::create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'Ganjil',
            'start_date' => '2026-07-15',
            'end_date' => '2026-12-20',
            'is_active' => true,
        ]);

        // 4. Teacher User & Model
        $this->teacherUser = User::create([
            'username' => 'guru_p4',
            'email' => 'guru.p4@labschool.sch.id',
            'password_hash' => Hash::make('password'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher->id);

        $this->teacher = Teacher::create([
            'user_id' => $this->teacherUser->id,
            'nip' => '198501012010011001',
            'full_name' => 'Guru Penguji Phase 4',
            'gender' => 'L',
            'status' => 'tetap',
        ]);

        $gradeLevel = GradeLevel::firstOrCreate(['name' => 'Kelas VII'], ['sort_order' => 7]);
        SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $gradeLevel->id,
            'homeroom_teacher_id' => $this->teacher->id,
            'name' => 'VII-A',
            'capacity' => 32,
        ]);

        // 5. Unauthorized User (Student with no management permissions)
        $this->unauthorizedUser = User::create([
            'username' => 'siswa_p4',
            'email' => 'siswa.p4@labschool.sch.id',
            'password_hash' => Hash::make('password'),
            'status' => 'active',
        ]);
        $this->unauthorizedUser->roles()->attach($roleStudent->id);

        // 6. Authorized CBT User
        $this->authorizedCbtUser = User::create([
            'username' => 'cbt_admin_p4',
            'email' => 'cbt.p4@labschool.sch.id',
            'password_hash' => Hash::make('password'),
            'status' => 'active',
        ]);
        $roleCbt = Role::create(['code' => 'cbt_manager', 'name' => 'CBT Manager', 'is_system' => false]);
        $roleCbt->permissions()->attach($permCbtBankWrite->id);
        $this->authorizedCbtUser->roles()->attach($roleCbt->id);

        // 7. Authorized Grade User
        $this->authorizedGradeUser = User::create([
            'username' => 'grade_admin_p4',
            'email' => 'grade.p4@labschool.sch.id',
            'password_hash' => Hash::make('password'),
            'status' => 'active',
        ]);
        $roleGrade = Role::create(['code' => 'grade_manager', 'name' => 'Grade Manager', 'is_system' => false]);
        $roleGrade->permissions()->attach($permGradeWrite->id);
        $this->authorizedGradeUser->roles()->attach($roleGrade->id);
    }

    /**
     * BE-02: Memastikan rute me/teacher modular diarahkan langsung ke controller sub-domain.
     */
    public function test_b_e_02_modular_teacher_portal_routes_resolve_and_return_json(): void
    {
        $this->actingAs($this->teacherUser);

        // Sub-domain 1: Dashboard, Jadwal & Kelas
        $resDashboard = $this->getJson('/api/v1/me/teacher/dashboard');
        $resDashboard->assertOk()->assertJsonStructure(['data']);

        $resSubjects = $this->getJson('/api/v1/me/teacher/subjects');
        $resSubjects->assertOk()->assertJsonStructure(['data']);

        $resClasses = $this->getJson('/api/v1/me/teacher/classes');
        $resClasses->assertOk()->assertJsonStructure(['data']);

        $resSchedules = $this->getJson('/api/v1/me/teacher/schedules');
        $resSchedules->assertOk()->assertJsonStructure(['data']);

        // Sub-domain 2: Presensi & Jurnal KBM
        $resAttendance = $this->getJson('/api/v1/me/teacher/attendance/sessions');
        $resAttendance->assertOk()->assertJsonStructure(['data']);

        $resJournals = $this->getJson('/api/v1/me/teacher/journals');
        $resJournals->assertOk()->assertJsonStructure(['data']);

        // Sub-domain 3: Buku Nilai
        $resComponents = $this->getJson('/api/v1/me/teacher/grade-components');
        $resComponents->assertOk()->assertJsonStructure(['data']);

        // Sub-domain 4: LMS (Materi & Tugas)
        $resMaterials = $this->getJson('/api/v1/me/teacher/materials');
        $resMaterials->assertOk()->assertJsonStructure(['data']);

        $resAssignments = $this->getJson('/api/v1/me/teacher/assignments');
        $resAssignments->assertOk()->assertJsonStructure(['data']);

        // Sub-domain 6: Perizinan
        $resLeave = $this->getJson('/api/v1/me/teacher/leave-requests');
        $resLeave->assertOk()->assertJsonStructure(['data']);

        // Sub-domain 7: Wali Kelas
        $resHomeroom = $this->getJson('/api/v1/me/teacher/homeroom');
        $resHomeroom->assertOk()->assertJsonStructure(['data']);
    }

    /**
     * BE-03: Paritas alias CBT: /question-banks dan /question-packages memiliki permission yang identik.
     */
    public function test_b_e_03_cbt_question_banks_and_question_packages_alias_parity(): void
    {
        // 1. Tanpa permission cbt.bank.write -> Keduanya wajib 403 Forbidden
        $this->actingAs($this->unauthorizedUser);

        $this->getJson('/api/v1/question-banks')
            ->assertStatus(403);

        $this->getJson('/api/v1/question-packages')
            ->assertStatus(403);

        $this->postJson('/api/v1/question-banks', ['title' => 'Bank Soal Test'])
            ->assertStatus(403);

        $this->postJson('/api/v1/question-packages', ['title' => 'Paket Soal Test'])
            ->assertStatus(403);

        // 2. Dengan permission cbt.bank.write -> Keduanya wajib 200 OK dengan format seragam
        $this->actingAs($this->authorizedCbtUser);

        $resBanks = $this->getJson('/api/v1/question-banks');
        $resBanks->assertOk()->assertJsonStructure(['data']);

        $resPackages = $this->getJson('/api/v1/question-packages');
        $resPackages->assertOk()->assertJsonStructure(['data']);
    }

    /**
     * BE-03: Paritas alias Nilai: /grades/bulk dan /grades/batch memiliki proteksi identik.
     */
    public function test_b_e_03_grades_bulk_and_batch_alias_parity(): void
    {
        // Tanpa permission academic.grade.write -> Keduanya wajib 403 Forbidden
        $this->actingAs($this->unauthorizedUser);

        $this->postJson('/api/v1/grades/bulk', ['grades' => []])
            ->assertStatus(403);

        $this->postJson('/api/v1/grades/batch', ['grades' => []])
            ->assertStatus(403);
    }

    /**
     * BE-03: Paritas alias Penguncian Periode: /grade-periods/{id}/lock dan /lock-period.
     */
    public function test_b_e_03_grade_periods_lock_and_lock_period_alias_parity(): void
    {
        $dummyId = '00000000-0000-0000-0000-000000000000';

        // Tanpa permission academic.grade.write -> Keduanya wajib 403 Forbidden
        $this->actingAs($this->unauthorizedUser);

        $this->patchJson("/api/v1/grade-periods/{$dummyId}/lock")
            ->assertStatus(403);

        $this->patchJson("/api/v1/grade-periods/{$dummyId}/lock-period")
            ->assertStatus(403);
    }

    /**
     * BE-03: Paritas alias Notifikasi: /notifications vs /me/notifications dan preferensi.
     */
    public function test_b_e_03_notifications_and_me_notifications_alias_parity(): void
    {
        $this->actingAs($this->teacherUser);

        // Inbox Notifikasi
        $resNotif1 = $this->getJson('/api/v1/notifications');
        $resNotif1->assertOk()->assertJsonStructure(['data']);

        $resNotif2 = $this->getJson('/api/v1/me/notifications');
        $resNotif2->assertOk()->assertJsonStructure(['data']);

        // Preferensi Notifikasi
        $resPref1 = $this->getJson('/api/v1/notification-preferences');
        $resPref1->assertOk()->assertJsonStructure(['data']);

        $resPref2 = $this->getJson('/api/v1/me/notification-preferences');
        $resPref2->assertOk()->assertJsonStructure(['data']);
    }

    /**
     * BE-03: Paritas alias Modul Ajar: /teaching-materials vs /learning-modules.
     */
    public function test_b_e_03_teaching_materials_and_learning_modules_alias_parity(): void
    {
        $this->actingAs($this->teacherUser);

        $resTeach = $this->getJson('/api/v1/teaching-materials');
        $resTeach->assertOk()->assertJsonStructure(['data']);

        $resLearn = $this->getJson('/api/v1/learning-modules');
        $resLearn->assertOk()->assertJsonStructure(['data']);
    }

    /**
     * BE-03: Konsistensi endpoint profil pengguna autentikasi: /me, /auth/me, dan /user.
     */
    public function test_b_e_03_me_and_auth_me_and_user_profile_consistency(): void
    {
        $this->actingAs($this->teacherUser);

        $resMe = $this->getJson('/api/v1/me');
        $resMe->assertOk();

        $resAuthMe = $this->getJson('/api/v1/auth/me');
        $resAuthMe->assertOk();

        $resUser = $this->getJson('/api/v1/user');
        $resUser->assertOk();

        // Seluruh endpoint wajib mengembalikan akun pengguna yang sama
        $this->assertEquals($this->teacherUser->email, $resMe->json('data.user.email'));
        $this->assertEquals($this->teacherUser->email, $resAuthMe->json('data.user.email'));
        $this->assertEquals($this->teacherUser->email, $resUser->json('data.user.email'));
    }
}
