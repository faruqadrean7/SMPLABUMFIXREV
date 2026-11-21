<?php

namespace Tests\Feature\Backend;

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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Phase4MaintainabilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $teacherUser;

    protected Teacher $teacherModel;

    protected User $studentUser;

    protected SchoolClass $classModel;

    protected Subject $subjectModel;

    protected GradeLevel $gradeLevel;

    protected AcademicYear $academicYear;

    protected Semester $semester;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $permBankWrite = Permission::firstOrCreate(['code' => 'cbt.bank.write'], ['description' => 'Mengelola bank soal']);
        $roleAdmin->permissions()->syncWithoutDetaching([$permBankWrite->id]);
        $roleTeacher->permissions()->syncWithoutDetaching([$permBankWrite->id]);

        $this->adminUser = User::create([
            'username' => 'admin_phase4',
            'email' => 'admin.p4@labschool.sch.id',
            'password_hash' => Hash::make('AdminPass123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->teacherUser = User::create([
            'username' => 'teacher_phase4',
            'email' => 'teacher.p4@labschool.sch.id',
            'password_hash' => Hash::make('TeacherPass123!'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher->id);

        $this->teacherModel = Teacher::create([
            'user_id' => $this->teacherUser->id,
            'nip' => '198801012022011001',
            'full_name' => 'Budi Santoso, S.Pd',
            'status' => 'active',
        ]);

        $this->academicYear = AcademicYear::firstOrCreate(
            ['name' => '2024/2025'],
            [
                'start_date' => now()->startOfYear()->toDateString(),
                'end_date' => now()->endOfYear()->toDateString(),
                'is_active' => true,
            ]
        );

        $this->semester = Semester::firstOrCreate(
            ['name' => 'Ganjil', 'academic_year_id' => $this->academicYear->id],
            [
                'start_date' => now()->startOfYear()->toDateString(),
                'end_date' => now()->endOfYear()->toDateString(),
                'is_active' => true,
            ]
        );

        $this->gradeLevel = GradeLevel::firstOrCreate(
            ['name' => 'Kelas 7'],
            ['sort_order' => 7]
        );

        $this->classModel = SchoolClass::create([
            'name' => '7-A',
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->gradeLevel->id,
            'homeroom_teacher_id' => $this->teacherModel->id,
        ]);

        $this->subjectModel = Subject::create([
            'code' => 'MAT-7',
            'name' => 'Matematika 7',
            'subject_group' => 'muatan_nasional',
        ]);

        TeachingAssignment::create([
            'teacher_id' => $this->teacherModel->id,
            'class_id' => $this->classModel->id,
            'subject_id' => $this->subjectModel->id,
            'semester_id' => $this->semester->id,
        ]);

        $this->studentUser = User::create([
            'username' => 'student_phase4',
            'email' => 'student.p4@labschool.sch.id',
            'password_hash' => Hash::make('StudentPass123!'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($roleStudent->id);
    }

    /**
     * BE-02: Happy path verifikasi bahwa modular route files di-load dengan benar dan merespons 200.
     */
    public function test_be_02_modular_routes_load_and_respond_successfully(): void
    {
        // 1. Auth & health route (auth.php)
        $health = $this->getJson('/api/v1/health');
        $health->assertStatus(200)
            ->assertJsonPath('version', '1.1.0');

        // 2. Teacher self-service & portal route (portal.php)
        Sanctum::actingAs($this->teacherUser);
        $dashboard = $this->getJson('/api/v1/me/teacher/dashboard');
        $dashboard->assertStatus(200)
            ->assertJsonPath('data.teacherName', 'Budi Santoso, S.Pd');

        // 3. User profile unification (auth.php & portal.php)
        $me = $this->getJson('/api/v1/me');
        $me->assertStatus(200)
            ->assertJsonPath('data.user.email', 'teacher.p4@labschool.sch.id');

        $userProfile = $this->getJson('/api/v1/user');
        $userProfile->assertStatus(200)
            ->assertJsonPath('data.user.email', 'teacher.p4@labschool.sch.id');
    }

    /**
     * BE-02: Verifikasi pemecahan controller per sub-domain melayani request dengan tepat.
     */
    public function test_be_02_teacher_subdomain_controllers_handle_requests_cleanly(): void
    {
        Sanctum::actingAs($this->teacherUser);

        // TeacherDashboardController: classes
        $classesRes = $this->getJson('/api/v1/me/teacher/classes');
        $classesRes->assertStatus(200)
            ->assertJsonStructure(['data']);

        // TeacherAttendanceController: journals
        $journalsRes = $this->getJson('/api/v1/me/teacher/journals');
        $journalsRes->assertStatus(200)
            ->assertJsonStructure(['data']);

        // TeacherGradingController: grades
        $gradesRes = $this->getJson("/api/v1/me/teacher/grades?class_id={$this->classModel->id}&subject_id={$this->subjectModel->id}");
        $gradesRes->assertStatus(200)
            ->assertJsonStructure(['data']);

        // TeacherLmsController: materials
        $materialsRes = $this->getJson('/api/v1/me/teacher/materials');
        $materialsRes->assertStatus(200)
            ->assertJsonStructure(['data']);

        // TeacherExamController: exams
        $examsRes = $this->getJson('/api/v1/me/teacher/exams');
        $examsRes->assertStatus(200)
            ->assertJsonStructure(['data']);

        // TeacherLeaveController: self-attendance
        $attendanceRes = $this->getJson('/api/v1/me/teacher/self-attendance');
        $attendanceRes->assertStatus(200)
            ->assertJsonStructure(['data']);

        // TeacherHomeroomController: homeroom
        $homeroomRes = $this->getJson('/api/v1/me/teacher/homeroom');
        $homeroomRes->assertStatus(200)
            ->assertJsonPath('data.className', '7-A');
    }

    /**
     * BE-02: Endpoint modular tanpa kredensial otentikasi mengembalikan 401.
     */
    public function test_be_02_unauthenticated_request_to_modular_routes_returns_401(): void
    {
        $this->getJson('/api/v1/me')->assertStatus(401);
        $this->getJson('/api/v1/me/teacher/dashboard')->assertStatus(401);
        $this->getJson('/api/v1/notifications')->assertStatus(401);
        $this->getJson('/api/v1/me/notifications')->assertStatus(401);
    }

    /**
     * BE-02: User dengan role non-guru (siswa) mengakses portal guru ditolak 403.
     */
    public function test_be_02_student_access_to_teacher_portal_returns_403(): void
    {
        Sanctum::actingAs($this->studentUser);

        $response = $this->getJson('/api/v1/me/teacher/dashboard');

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-03: Harmonisasi alias rute memastikan permission & hasil konsisten.
     */
    public function test_be_03_route_aliases_and_duplicates_work_consistently_with_same_permissions(): void
    {
        Sanctum::actingAs($this->teacherUser);

        // 1. Alias /notifications vs /me/notifications
        $ntf1 = $this->getJson('/api/v1/notifications');
        $ntf1->assertStatus(200);

        $ntf2 = $this->getJson('/api/v1/me/notifications');
        $ntf2->assertStatus(200);

        // 2. Alias /question-banks vs /question-packages (kedua rute terproteksi permission cbt.bank.write)
        $qb1 = $this->getJson('/api/v1/question-banks');
        $qb1->assertStatus(200);

        $qb2 = $this->getJson('/api/v1/question-packages');
        $qb2->assertStatus(200);

        // Siswa tanpa permission cbt.bank.write ditolak 403 di kedua rute
        Sanctum::actingAs($this->studentUser);
        $this->getJson('/api/v1/question-banks')
            ->assertStatus(403);
        $this->getJson('/api/v1/question-packages')
            ->assertStatus(403);
    }

    /**
     * BE-03: Aturan batas bisnis — alias /me/notification-preferences memiliki sub-endpoint /types.
     */
    public function test_be_03_notification_preferences_alias_supports_types_subroute(): void
    {
        Sanctum::actingAs($this->teacherUser);

        $canonical = $this->getJson('/api/v1/notification-preferences/types');
        $canonical->assertStatus(200);

        $alias = $this->getJson('/api/v1/me/notification-preferences/types');
        $alias->assertStatus(200);
    }
}
