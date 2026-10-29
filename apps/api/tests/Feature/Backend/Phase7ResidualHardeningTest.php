<?php

namespace Tests\Feature\Backend;

use App\Models\Announcement;
use App\Models\EarlyWarning;
use App\Models\Extracurricular;
use App\Models\Permission;
use App\Models\PointType;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentPoint;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Phase7ResidualHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $teacherUser;

    protected User $counselorUser;

    protected User $kesiswaanUser;

    protected User $studentUser;

    protected Student $studentModel;

    protected EarlyWarning $earlyWarningModel;

    protected PointType $pointType;

    protected Extracurricular $ekskulModel;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleCounselor = Role::firstOrCreate(['code' => 'counselor'], ['name' => 'Guru BK', 'is_system' => true]);
        $roleKesiswaan = Role::firstOrCreate(['code' => 'kesiswaan'], ['name' => 'Staf Kesiswaan', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        // Permissions
        $permBkRead = Permission::firstOrCreate(['code' => 'counseling.case.read'], ['description' => 'Melihat kasus konseling BK']);
        $permBkManage = Permission::firstOrCreate(['code' => 'bk.counseling.manage'], ['description' => 'Mengelola catatan BK']);
        $permPointWrite = Permission::firstOrCreate(['code' => 'student.point.write'], ['description' => 'Mencatat poin pelanggaran']);
        $permPointRead = Permission::firstOrCreate(['code' => 'student.point.read'], ['description' => 'Melihat poin pelanggaran']);
        $permBroadcast = Permission::firstOrCreate(['code' => 'notification.broadcast'], ['description' => 'Kirim broadcast pengumuman']);

        $roleCounselor->permissions()->syncWithoutDetaching([$permBkRead->id, $permBkManage->id]);
        $roleTeacher->permissions()->syncWithoutDetaching([$permPointWrite->id, $permPointRead->id, $permBroadcast->id]);
        $roleKesiswaan->permissions()->syncWithoutDetaching([$permPointWrite->id, $permPointRead->id, $permBroadcast->id]);
        $roleStudent->permissions()->syncWithoutDetaching([$permPointRead->id]);

        $this->adminUser = User::create([
            'username' => 'admin_phase7',
            'email' => 'admin.p7@labschool.sch.id',
            'password_hash' => Hash::make('AdminPass123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->teacherUser = User::create([
            'username' => 'teacher_phase7',
            'email' => 'teacher.p7@labschool.sch.id',
            'password_hash' => Hash::make('TeacherPass123!'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher->id);

        $this->counselorUser = User::create([
            'username' => 'counselor_phase7',
            'email' => 'counselor.p7@labschool.sch.id',
            'password_hash' => Hash::make('CounselorPass123!'),
            'status' => 'active',
        ]);
        $this->counselorUser->roles()->attach($roleCounselor->id);

        $this->kesiswaanUser = User::create([
            'username' => 'kesiswaan_phase7',
            'email' => 'kesiswaan.p7@labschool.sch.id',
            'password_hash' => Hash::make('KesiswaanPass123!'),
            'status' => 'active',
        ]);
        $this->kesiswaanUser->roles()->attach($roleKesiswaan->id);

        $this->studentUser = User::create([
            'username' => 'student_phase7',
            'email' => 'student.p7@labschool.sch.id',
            'password_hash' => Hash::make('StudentPass123!'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($roleStudent->id);

        $this->studentModel = Student::create([
            'user_id' => $this->studentUser->id,
            'nis' => '10293847',
            'nisn' => '0012345678',
            'nik_enc' => '3578010101010001',
            'full_name' => 'Siswa Pengujian P7',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2009-01-01',
            'religion' => 'Islam',
            'address' => 'Jl. Semarang No. 5 Malang',
            'status' => 'aktif',
            'entry_date' => '2024-07-15',
        ]);

        $this->pointType = PointType::create([
            'kind' => 'pelanggaran',
            'name' => 'Keterlambatan Masuk Kelas',
            'points' => 5,
        ]);

        $this->earlyWarningModel = EarlyWarning::create([
            'student_id' => $this->studentModel->id,
            'rule_code' => 'ATTENDANCE_LOW',
            'severity' => 'sedang',
            'data' => ['alpa_count' => 3],
            'status' => 'open',
        ]);

        $this->ekskulModel = Extracurricular::create([
            'name' => 'Basket Laboratorium P7',
            'category' => 'olahraga',
            'is_active' => true,
        ]);
    }

    // =========================================================================
    // TUGAS 1: Endpoint Early Warnings (BK)
    // =========================================================================

    public function test_tugas_1_early_warnings_endpoints_reject_student_with_403(): void
    {
        Sanctum::actingAs($this->studentUser);

        // GET /early-warnings/summary
        $this->getJson('/api/v1/early-warnings/summary')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // GET /early-warnings
        $this->getJson('/api/v1/early-warnings')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // GET /early-warnings/{id}
        $this->getJson("/api/v1/early-warnings/{$this->earlyWarningModel->id}")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_tugas_1_early_warnings_endpoints_allow_counselor_with_200(): void
    {
        Sanctum::actingAs($this->counselorUser);

        // GET /early-warnings/summary
        $this->getJson('/api/v1/early-warnings/summary')
            ->assertStatus(200)
            ->assertJsonStructure(['data']);

        // GET /early-warnings
        $this->getJson('/api/v1/early-warnings')
            ->assertStatus(200)
            ->assertJsonStructure(['data']);

        // GET /early-warnings/{id}
        $this->getJson("/api/v1/early-warnings/{$this->earlyWarningModel->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $this->earlyWarningModel->id);
    }

    // =========================================================================
    // TUGAS 1: Announcements Mutasi
    // =========================================================================

    public function test_tugas_1_announcement_mutations_reject_student_with_403(): void
    {
        Sanctum::actingAs($this->studentUser);

        // POST /announcements
        $this->postJson('/api/v1/announcements', [
            'title' => 'Pengumuman Ilegal Siswa',
            'body' => 'Isi pengumuman',
            'target_type' => 'semua',
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // Dummy announcement for update/delete
        $announcement = Announcement::create([
            'author_id' => $this->teacherUser->id,
            'title' => 'Pengumuman Guru P7',
            'body' => 'Harap berkumpul di aula',
            'target_type' => 'semua',
            'priority' => 'normal',
            'is_pinned' => false,
            'publish_at' => Carbon::now(),
        ]);

        // PUT /announcements/{id}
        $this->putJson("/api/v1/announcements/{$announcement->id}", [
            'title' => 'Pengumuman Modifikasi Siswa',
            'body' => 'Modifikasi isi',
            'target_type' => 'semua',
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // DELETE /announcements/{id}
        $this->deleteJson("/api/v1/announcements/{$announcement->id}")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_tugas_1_announcement_mutations_allow_authorized_staff(): void
    {
        Sanctum::actingAs($this->teacherUser);

        // POST /announcements
        $res = $this->postJson('/api/v1/announcements', [
            'title' => 'Pengumuman Resmi Guru',
            'body' => 'Pengumuman resmi dari guru matematika',
            'target_type' => 'semua',
        ]);
        $res->assertStatus(201)
            ->assertJsonPath('data.title', 'Pengumuman Resmi Guru');

        $announcementId = $res->json('data.id');

        // PUT /announcements/{id}
        $this->putJson("/api/v1/announcements/{$announcementId}", [
            'title' => 'Pengumuman Resmi Guru Terupdate',
            'body' => 'Revisi isi pengumuman',
            'target_type' => 'semua',
        ])->assertStatus(200)
            ->assertJsonPath('data.title', 'Pengumuman Resmi Guru Terupdate');

        // DELETE /announcements/{id}
        $this->deleteJson("/api/v1/announcements/{$announcementId}")
            ->assertStatus(200);
    }

    // =========================================================================
    // TUGAS 1: Student Points Mutasi
    // =========================================================================

    public function test_tugas_1_student_points_mutations_reject_student_with_403(): void
    {
        Sanctum::actingAs($this->studentUser);

        // POST /student-points
        $this->postJson('/api/v1/student-points', [
            'student_id' => $this->studentModel->id,
            'point_type_id' => $this->pointType->id,
            'point_date' => Carbon::now()->toDateString(),
            'notes' => 'Catatan ilegal',
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $sp = StudentPoint::create([
            'student_id' => $this->studentModel->id,
            'point_type_id' => $this->pointType->id,
            'point_date' => Carbon::now()->toDateString(),
            'recorded_by' => $this->teacherUser->id,
            'notes' => 'Catatan test',
        ]);

        // DELETE /student-points/{id}
        $this->deleteJson("/api/v1/student-points/{$sp->id}")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_tugas_1_student_points_mutations_allow_authorized_staff(): void
    {
        Sanctum::actingAs($this->kesiswaanUser);

        // POST /student-points
        $res = $this->postJson('/api/v1/student-points', [
            'student_id' => $this->studentModel->id,
            'point_type_id' => $this->pointType->id,
            'point_date' => Carbon::now()->toDateString(),
            'notes' => 'Terlambat upacara bendera',
        ]);
        $res->assertStatus(201);

        $pointId = $res->json('data.point.id');

        // DELETE /student-points/{id}
        $this->deleteJson("/api/v1/student-points/{$pointId}")
            ->assertStatus(200);
    }

    // =========================================================================
    // TUGAS 1: Extracurriculars & Competitions CRUD Guard
    // =========================================================================

    public function test_tugas_1_extracurricular_and_competition_creation_rejects_student_with_403(): void
    {
        Sanctum::actingAs($this->studentUser);

        // POST /extracurriculars
        $this->postJson('/api/v1/extracurriculars', [
            'name' => 'Ekskul Liar Siswa',
            'category' => 'kesenian',
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // POST /competitions
        $this->postJson('/api/v1/competitions', [
            'name' => 'Olimpiade Sains Liar',
            'level' => 'Kota',
            'held_at' => Carbon::now()->addDays(5)->toDateString(),
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // POST /trips
        $this->postJson('/api/v1/trips', [
            'title' => 'Study Trip Liar Siswa',
            'depart_date' => Carbon::now()->addDays(10)->toDateString(),
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_tugas_1_extracurricular_and_competition_creation_allows_staff(): void
    {
        Sanctum::actingAs($this->kesiswaanUser);

        // POST /extracurriculars
        $resEkskul = $this->postJson('/api/v1/extracurriculars', [
            'name' => 'Robotik Laboratorium P7',
            'category' => 'sains',
            'is_active' => true,
        ]);
        $resEkskul->assertStatus(201)
            ->assertJsonPath('data.name', 'Robotik Laboratorium P7');

        // POST /competitions
        $resComp = $this->postJson('/api/v1/competitions', [
            'name' => 'Olimpiade Matematika Nasional UM 2026',
            'level' => 'Nasional',
            'held_at' => Carbon::now()->addDays(20)->toDateString(),
        ]);
        $resComp->assertStatus(201)
            ->assertJsonPath('data.name', 'Olimpiade Matematika Nasional UM 2026');
    }

    // =========================================================================
    // TUGAS 2: Verifikasi & Pembuktian Middleware RequirePermission Multi-Argumen
    // =========================================================================

    /**
     * TUGAS 2: Buktikan bahwa user dengan HANYA role 'counselor' (0 permission terlampir)
     * lolos rute dengan middleware permission:counseling.case.read,bk.counseling.manage,counselor,principal.
     */
    public function test_tugas_2_user_with_only_role_name_and_zero_permissions_succeeds(): void
    {
        // Buat role terisolasi tanpa permission sama sekali
        $roleBareCounselor = Role::create([
            'code' => 'counselor_bare',
            'name' => 'Konselor Murni Tanpa Permission',
            'is_system' => false,
        ]);

        $bareCounselorUser = User::create([
            'username' => 'bare_counselor_p7',
            'email' => 'bare.counselor@labschool.sch.id',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        // Pasangkan role 'counselor' resmi tetapi copot semua permission dari role tersebut untuk pengujian
        $roleTestCounselor = Role::firstOrCreate(['code' => 'counselor']);
        $bareCounselorUser->roles()->attach($roleTestCounselor->id);

        // Pastikan role test counselor tidak memiliki permission 'counseling.case.read' khusus test ini
        $roleTestCounselor->permissions()->detach();

        Sanctum::actingAs($bareCounselorUser);

        // Endpoint GET /early-warnings/summary memiliki guard:
        // middleware('permission:counseling.case.read,bk.counseling.manage,counselor,principal')
        $response = $this->getJson('/api/v1/early-warnings/summary');

        // Karena user memiliki role 'counselor', middleware RequirePermission harus meloloskannya (status 200)
        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    /**
     * TUGAS 2: Buktikan bahwa user yang TIDAK memiliki role 'counselor'/'principal'
     * dan TIDAK memiliki permission terkait ditolak dengan 403 FORBIDDEN.
     */
    public function test_tugas_2_user_without_role_and_without_permission_is_rejected_403(): void
    {
        $genericRole = Role::create([
            'code' => 'generic_guest_role',
            'name' => 'Pengguna Umum',
            'is_system' => false,
        ]);

        $genericUser = User::create([
            'username' => 'generic_user_p7',
            'email' => 'generic.user@labschool.sch.id',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $genericUser->roles()->attach($genericRole->id);

        Sanctum::actingAs($genericUser);

        $response = $this->getJson('/api/v1/early-warnings/summary');

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Anda tidak memiliki izin untuk mengakses resource ini');
    }

    /**
     * TUGAS 2: Buktikan bahwa user dengan role acak TETAPI memiliki salah satu permission
     * dalam daftar multi-argumen tetap lolos (semantik OR / any-of).
     */
    public function test_tugas_2_user_with_arbitrary_role_having_matching_permission_succeeds_any_of_or(): void
    {
        $customRole = Role::create([
            'code' => 'auditor_bk_external',
            'name' => 'Auditor BK Eksternal',
            'is_system' => false,
        ]);

        // Pasangkan hanya 1 permission: counseling.case.read
        $permBkRead = Permission::firstOrCreate(['code' => 'counseling.case.read']);
        $customRole->permissions()->attach($permBkRead->id);

        $auditorUser = User::create([
            'username' => 'auditor_p7',
            'email' => 'auditor.bk@labschool.sch.id',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $auditorUser->roles()->attach($customRole->id);

        Sanctum::actingAs($auditorUser);

        // Guard rute: permission:counseling.case.read,bk.counseling.manage,counselor,principal
        // Auditor BK tidak punya role counselor, tapi punya permission counseling.case.read
        $response = $this->getJson('/api/v1/early-warnings/summary');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    /**
     * TUGAS 2: Permintaan tanpa autentikasi ditolak 401 UNAUTHENTICATED.
     */
    public function test_tugas_2_unauthenticated_request_is_rejected_401(): void
    {
        $this->getJson('/api/v1/early-warnings/summary')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }
}
