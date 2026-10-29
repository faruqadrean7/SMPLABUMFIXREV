<?php

namespace Tests\Feature\Bk;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\CounselingCase;
use App\Models\CounselingReferral;
use App\Models\CounselingSession;
use App\Models\GradeLevel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CounselingCaseAndSessionTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $counselorUser;

    protected User $principalUser;

    protected User $homeroomTeacherUser;

    protected User $studentUser;

    protected Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleCounselor = Role::firstOrCreate(['code' => 'counselor'], ['name' => 'Guru BK', 'is_system' => true]);
        $rolePrincipal = Role::firstOrCreate(['code' => 'principal'], ['name' => 'Kepala Sekolah', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $permBk = Permission::firstOrCreate(['code' => 'bk.counseling.manage'], ['description' => 'Mengelola catatan bimbingan konseling']);
        $roleCounselor->permissions()->syncWithoutDetaching([$permBk->id]);
        $roleAdmin->permissions()->syncWithoutDetaching([$permBk->id]);

        $this->adminUser = User::create([
            'username' => 'admin_bk',
            'email' => 'admin_bk@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->counselorUser = User::create([
            'username' => 'guru_bk',
            'email' => 'counselor@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->counselorUser->roles()->attach($roleCounselor->id);

        $this->principalUser = User::create([
            'username' => 'kepsek',
            'email' => 'kepsek@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->principalUser->roles()->attach($rolePrincipal->id);

        $this->homeroomTeacherUser = User::create([
            'username' => 'walas_1',
            'email' => 'walas@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->homeroomTeacherUser->roles()->attach($roleTeacher->id);

        $this->studentUser = User::create([
            'username' => '20261001',
            'email' => 'siswa@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($roleStudent->id);

        $teacherCounselor = Teacher::create([
            'user_id' => $this->counselorUser->id,
            'nip' => '198203032008011003',
            'full_name' => 'Dra. Rahmawati, M.Pd (Konselor)',
            'gender' => 'P',
        ]);

        $teacherHomeroom = Teacher::create([
            'user_id' => $this->homeroomTeacherUser->id,
            'nip' => '198404042009021004',
            'full_name' => 'Bambang Sudarsono, S.Pd',
            'gender' => 'L',
        ]);

        $ay = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $gradeLevel = GradeLevel::create([
            'name' => 'Kelas 10',
            'level' => 10,
        ]);

        $classA = SchoolClass::create([
            'academic_year_id' => $ay->id,
            'grade_level_id' => $gradeLevel->id,
            'homeroom_teacher_id' => $teacherHomeroom->id,
            'name' => '10-A',
            'capacity' => 36,
        ]);

        $this->student = Student::create([
            'user_id' => $this->studentUser->id,
            'full_name' => 'Ahmad Faiz Pratama',
            'nis' => '20261001',
            'nisn' => '0012345678',
            'nik_enc' => 'enc_3578010101010001',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        ClassStudent::create([
            'class_id' => $classA->id,
            'student_id' => $this->student->id,
            'joined_at' => '2026-07-15',
        ]);
    }

    /**
     * BE-BK-01: Guru BK dapat membuat kasus konseling dan ringkasan tersimpan terenkripsi at-rest.
     */
    public function test_be_bk_01_counselor_can_create_case_with_at_rest_encryption(): void
    {
        $this->app['auth']->forgetGuards();

        $secretSummary = 'Siswa mengalami kecemasan sosial dan penurunan motivasi belajar drastis.';

        $response = $this->actingAs($this->counselorUser, 'sanctum')
            ->postJson('/api/v1/counseling/cases', [
                'student_id' => $this->student->id,
                'category' => 'Pribadi',
                'status' => 'aktif',
                'summary' => $secretSummary,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.category', 'Pribadi')
            ->assertJsonPath('data.status', 'aktif')
            ->assertJsonPath('data.summary_enc', $secretSummary);

        $caseId = $response->json('data.id');

        // Pastikan di database level RAW SQL, teks tidak tersimpan plaintext melainkan terenkripsi
        $rawRecord = DB::table('counseling_cases')->where('id', $caseId)->first();
        $this->assertNotEquals($secretSummary, $rawRecord->summary_enc);
        $this->assertStringNotContainsString('kecemasan sosial', $rawRecord->summary_enc);

        // Pastikan aksi tercatat di audit log dan tidak membocorkan teks sensitif
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->counselorUser->id,
            'action' => 'bk_case.create',
            'entity_id' => $caseId,
        ]);
        $auditCase = DB::table('audit_logs')->where('action', 'bk_case.create')->where('entity_id', $caseId)->first();
        $this->assertStringNotContainsString('kecemasan sosial', $auditCase->new_value);
    }

    /**
     * BE-BK-01: Guru BK dapat menambahkan sesi konseling dengan catatan terenkripsi.
     */
    public function test_be_bk_01_counselor_can_add_counseling_session(): void
    {
        $case = CounselingCase::create([
            'student_id' => $this->student->id,
            'counselor_id' => $this->counselorUser->id,
            'category' => 'Belajar',
            'status' => 'aktif',
            'summary_enc' => 'Kendala fokus di kelas',
        ]);

        $this->app['auth']->forgetGuards();

        $privateNotes = 'Sesi 1: Eksplorasi akar permasalahan keluarga yang mempengaruhi fokus belajar siswa.';

        $response = $this->actingAs($this->counselorUser, 'sanctum')
            ->postJson("/api/v1/counseling/cases/{$case->id}/sessions", [
                'session_date' => '2026-10-12',
                'notes' => $privateNotes,
                'follow_up' => 'Konseling lanjutan minggu depan dengan ortu',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.case_id', $case->id)
            ->assertJsonPath('data.notes_enc', $privateNotes);

        $sessionId = $response->json('data.id');

        // RAW verification: Notes terenkripsi
        $rawSession = DB::table('counseling_sessions')->where('id', $sessionId)->first();
        $this->assertNotEquals($privateNotes, $rawSession->notes_enc);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->counselorUser->id,
            'action' => 'bk_session.create',
            'entity_id' => $sessionId,
        ]);
        $auditSession = DB::table('audit_logs')->where('action', 'bk_session.create')->where('entity_id', $sessionId)->first();
        $this->assertStringNotContainsString('Eksplorasi akar permasalahan', $auditSession->new_value);
    }

    /**
     * BE-BK-01: Akses baca kasus dan sesi konseling dicatat ke audit log (Security & Privacy Requirement).
     */
    public function test_be_bk_01_reading_case_and_session_triggers_read_audit_log(): void
    {
        $case = CounselingCase::create([
            'student_id' => $this->student->id,
            'counselor_id' => $this->counselorUser->id,
            'category' => 'Sosial',
            'status' => 'aktif',
            'summary_enc' => 'Ringkasan rahasia',
        ]);

        $session = CounselingSession::create([
            'case_id' => $case->id,
            'session_date' => '2026-10-12',
            'notes_enc' => 'Catatan sesi privat rahasia',
        ]);

        $this->app['auth']->forgetGuards();

        // 1. Baca detail kasus
        $resCase = $this->actingAs($this->counselorUser, 'sanctum')
            ->getJson("/api/v1/counseling/cases/{$case->id}");

        $resCase->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->counselorUser->id,
            'action' => 'bk_case.read',
            'entity_id' => $case->id,
        ]);

        // 2. Baca detail sesi
        $resSession = $this->actingAs($this->counselorUser, 'sanctum')
            ->getJson("/api/v1/counseling/sessions/{$session->id}");

        $resSession->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->counselorUser->id,
            'action' => 'bk_session.read',
            'entity_id' => $session->id,
        ]);
    }

    /**
     * BE-BK-01: Kepala Sekolah dapat melihat daftar kasus tetapi isi catatan privat disamarkan (masking).
     */
    public function test_be_bk_01_principal_sees_masked_private_counseling_notes(): void
    {
        $case = CounselingCase::create([
            'student_id' => $this->student->id,
            'counselor_id' => $this->counselorUser->id,
            'category' => 'Pribadi',
            'status' => 'aktif',
            'summary_enc' => 'Rahasia medis/psikologis mendalam',
        ]);

        CounselingSession::create([
            'case_id' => $case->id,
            'session_date' => '2026-10-12',
            'notes_enc' => 'Catatan sensitif wawancara mendalam',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->principalUser, 'sanctum')
            ->getJson("/api/v1/counseling/cases/{$case->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.category', 'Pribadi')
            ->assertJsonPath('data.summary_enc', '[Ringkasan Sensitif Disamarkan untuk Kepala Sekolah]')
            ->assertJsonPath('data.sessions.0.notes_enc', '[Catatan Sesi Konseling Privat Disamarkan]');
    }

    /**
     * BE-BK-01: Guru mata pelajaran / wali kelas dilarang mengakses kasus privat BK langsung (403 Forbidden).
     */
    public function test_be_bk_01_regular_teacher_forbidden_to_access_private_bk_cases(): void
    {
        $case = CounselingCase::create([
            'student_id' => $this->student->id,
            'counselor_id' => $this->counselorUser->id,
            'category' => 'Pribadi',
            'status' => 'aktif',
            'summary_enc' => 'Data privat',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->homeroomTeacherUser, 'sanctum')
            ->getJson("/api/v1/counseling/cases/{$case->id}");

        $response->assertStatus(403);
    }

    /**
     * BE-BK-01: Guru dapat membuat rujukan kasus ke BK dan memantau status rujukan.
     */
    public function test_be_bk_01_teacher_can_create_referral_and_track_status(): void
    {
        $this->app['auth']->forgetGuards();

        // 1. Wali kelas membuat rujukan ke BK
        $responseCreate = $this->actingAs($this->homeroomTeacherUser, 'sanctum')
            ->postJson('/api/v1/counseling/referrals', [
                'student_id' => $this->student->id,
                'reason' => 'Siswa sering tertidur di kelas dan terlihat murung selama 1 minggu terakhir.',
            ]);

        $responseCreate->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $referralId = $responseCreate->json('data.id');

        // 2. Guru memantau daftar rujukannya
        $responseList = $this->actingAs($this->homeroomTeacherUser, 'sanctum')
            ->getJson('/api/v1/counseling/referrals');

        $responseList->assertStatus(200)
            ->assertJsonPath('data.0.id', $referralId);

        // 3. Guru BK memperbarui status rujukan menjadi 'diterima'
        $this->app['auth']->forgetGuards();
        $responseUpdate = $this->actingAs($this->counselorUser, 'sanctum')
            ->patchJson("/api/v1/counseling/referrals/{$referralId}/status", [
                'status' => 'diterima',
            ]);

        $responseUpdate->assertStatus(200)
            ->assertJsonPath('data.status', 'diterima');
    }

    /**
     * BE-BK-01: Siswa dilarang keras mengakses API manajemen kasus BK (403 Forbidden).
     */
    public function test_be_bk_01_student_forbidden_to_access_counseling_cases(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->getJson('/api/v1/counseling/cases');

        $response->assertStatus(403);
    }

    /**
     * BE-BK-01: Siswa dan Orang Tua dilarang keras mengakses dan membuat rujukan kasus BK (403 Forbidden).
     */
    public function test_be_bk_01_student_and_guardian_forbidden_to_access_or_create_referrals(): void
    {
        $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Wali Murid', 'is_system' => true]);
        $guardianUser = User::create([
            'username' => 'wali_test_bk',
            'email' => 'wali_bk@gmail.com',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $guardianUser->roles()->attach($roleGuardian->id);

        $this->app['auth']->forgetGuards();

        // 1. Siswa mencoba melihat rujukan -> 403
        $this->actingAs($this->studentUser, 'sanctum')
            ->getJson('/api/v1/counseling/referrals')
            ->assertStatus(403);

        // 2. Siswa mencoba membuat rujukan -> 403
        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/counseling/referrals', [
                'student_id' => $this->student->id,
                'reason' => 'Mencoba rujukan dari akun siswa',
            ])
            ->assertStatus(403);

        // 3. Orang tua mencoba melihat rujukan -> 403
        $this->actingAs($guardianUser, 'sanctum')
            ->getJson('/api/v1/counseling/referrals')
            ->assertStatus(403);

        // 4. Orang tua mencoba membuat rujukan -> 403
        $this->actingAs($guardianUser, 'sanctum')
            ->postJson('/api/v1/counseling/referrals', [
                'student_id' => $this->student->id,
                'reason' => 'Mencoba rujukan dari akun wali murid',
            ])
            ->assertStatus(403);
    }

    /**
     * BE-BK-01: Guru pemegang hak input disiplin poin tetap DILARANG membaca kasus/sesi privat BK (403).
     */
    public function test_be_bk_01_teacher_with_discipline_permission_cannot_access_private_cases_or_sessions(): void
    {
        // Guru memiliki permission std.point.create & std.point.view
        $permPoint = Permission::firstOrCreate(['code' => 'std.point.create'], ['description' => 'Input poin pelanggaran']);
        $this->homeroomTeacherUser->roles()->first()?->permissions()->syncWithoutDetaching([$permPoint->id]);

        $case = CounselingCase::create([
            'student_id' => $this->student->id,
            'counselor_id' => $this->counselorUser->id,
            'category' => 'Pribadi',
            'status' => 'aktif',
            'summary_enc' => 'Kasus sensitif siswa',
        ]);

        $session = CounselingSession::create([
            'case_id' => $case->id,
            'session_date' => '2026-10-14',
            'notes_enc' => 'Catatan sesi mendalam',
        ]);

        $this->app['auth']->forgetGuards();

        // 1. Coba akses detail kasus
        $this->actingAs($this->homeroomTeacherUser, 'sanctum')
            ->getJson("/api/v1/counseling/cases/{$case->id}")
            ->assertStatus(403);

        // 2. Coba akses detail sesi
        $this->actingAs($this->homeroomTeacherUser, 'sanctum')
            ->getJson("/api/v1/counseling/sessions/{$session->id}")
            ->assertStatus(403);
    }

    /**
     * BE-BK-01: Isolasi multi-sekolah mencegah pembacaan dan pembuatan kasus konseling lintas sekolah.
     */
    public function test_be_bk_01_cross_school_access_is_forbidden(): void
    {
        $schoolA = (string) Str::uuid();
        $schoolB = (string) Str::uuid();

        $this->counselorUser->update(['school_id' => $schoolA]);
        $this->student->update(['school_id' => $schoolB]);

        $caseOtherSchool = CounselingCase::create([
            'student_id' => $this->student->id,
            'counselor_id' => $this->counselorUser->id,
            'category' => 'Belajar',
            'status' => 'aktif',
            'summary_enc' => 'Siswa sekolah B',
        ]);

        $this->app['auth']->forgetGuards();

        // 1. Konselor sekolah A dilarang mengakses detail kasus siswa sekolah B
        $this->actingAs($this->counselorUser, 'sanctum')
            ->getJson("/api/v1/counseling/cases/{$caseOtherSchool->id}")
            ->assertStatus(403);

        // 2. Konselor sekolah A dilarang membuat kasus untuk siswa sekolah B
        $this->actingAs($this->counselorUser, 'sanctum')
            ->postJson('/api/v1/counseling/cases', [
                'student_id' => $this->student->id,
                'category' => 'Belajar',
                'summary' => 'Percobaan kasus lintas sekolah',
            ])
            ->assertStatus(403);
    }

    /**
     * BE-BK-01: List kasus mencatat audit log dan menyamarkan summary, detail mengembalikan summary terdekripsi.
     */
    public function test_be_bk_01_listing_cases_records_audit_log_and_masks_sensitive_summaries(): void
    {
        $secretSummary = 'Ringkasan rahasia catatan konseling mendalam';

        $case = CounselingCase::create([
            'student_id' => $this->student->id,
            'counselor_id' => $this->counselorUser->id,
            'category' => 'Pribadi',
            'status' => 'aktif',
            'summary_enc' => $secretSummary,
        ]);

        $this->app['auth']->forgetGuards();

        // 1. Panggilan list /counseling/cases
        $resList = $this->actingAs($this->counselorUser, 'sanctum')
            ->getJson('/api/v1/counseling/cases');

        $resList->assertStatus(200);
        $this->assertEquals('[Tersimpan Terenkripsi - Buka Detail Kasus]', $resList->json('data.0.summary_enc'));

        // Pastikan aksi list dicatat di audit log
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->counselorUser->id,
            'action' => 'bk_case.list',
        ]);

        // 2. Panggilan detail /counseling/cases/{id} membuka teks terdekripsi
        $resDetail = $this->actingAs($this->counselorUser, 'sanctum')
            ->getJson("/api/v1/counseling/cases/{$case->id}");

        $resDetail->assertStatus(200);
        $this->assertEquals($secretSummary, $resDetail->json('data.summary_enc'));
    }

    /**
     * BE-BK-01: Kasus yang telah berstatus selesai (closed) tidak dapat ditambahkan sesi baru (422).
     */
    public function test_be_bk_01_cannot_add_session_to_closed_case(): void
    {
        $case = CounselingCase::create([
            'student_id' => $this->student->id,
            'counselor_id' => $this->counselorUser->id,
            'category' => 'Karir',
            'status' => 'selesai',
            'summary_enc' => 'Kasus telah selesai',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->counselorUser, 'sanctum')
            ->postJson("/api/v1/counseling/cases/{$case->id}/sessions", [
                'session_date' => '2026-10-14',
                'notes' => 'Mencoba tambah sesi pada kasus yang sudah ditutup',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-BK-01: Rujukan yang telah berstatus terminal (ditolak/selesai) tidak dapat diregresi ke status pending (422).
     */
    public function test_be_bk_01_referral_terminal_status_cannot_revert_to_pending(): void
    {
        $referral = CounselingReferral::create([
            'student_id' => $this->student->id,
            'referred_by' => $this->homeroomTeacherUser->id,
            'reason' => 'Perilaku menarik diri di kelas',
            'status' => 'ditolak',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->counselorUser, 'sanctum')
            ->patchJson("/api/v1/counseling/referrals/{$referral->id}/status", [
                'status' => 'pending',
                'notes' => 'Mencoba reaktivasi status ke pending',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-BK-01: Pencegahan pembuatan rujukan duplikat pending untuk siswa yang sama dari pengaju yang sama (422).
     */
    public function test_be_bk_01_cannot_create_duplicate_pending_referral(): void
    {
        $this->app['auth']->forgetGuards();

        // 1. Rujukan pertama berhasil dibuat
        $this->actingAs($this->homeroomTeacherUser, 'sanctum')
            ->postJson('/api/v1/counseling/referrals', [
                'student_id' => $this->student->id,
                'reason' => 'Sering terlambat dan tidak fokus',
            ])
            ->assertStatus(201);

        // 2. Rujukan kedua dengan data sama saat yang pertama masih pending ditolak
        $responseDuplicate = $this->actingAs($this->homeroomTeacherUser, 'sanctum')
            ->postJson('/api/v1/counseling/referrals', [
                'student_id' => $this->student->id,
                'reason' => 'Sering terlambat dan tidak fokus',
            ]);

        $responseDuplicate->assertStatus(422)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }
}
