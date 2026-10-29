<?php

namespace Tests\Feature\Std;

use App\Models\AcademicYear;
use App\Models\ActivityDocument;
use App\Models\Extracurricular;
use App\Models\ExtracurricularMember;
use App\Models\File;
use App\Models\ReportCard;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExtracurricularTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $kesiswaanUser;

    protected User $coachUser;

    protected User $otherTeacherUser;

    protected User $studentUser;

    protected User $otherStudentUser;

    protected Teacher $coachTeacher;

    protected Student $student;

    protected Student $otherStudent;

    protected Extracurricular $futsal;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleKesiswaan = Role::firstOrCreate(['code' => 'kesiswaan'], ['name' => 'Staf Kesiswaan', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $this->adminUser = User::create([
            'username' => 'admin_ekskul',
            'email' => 'admin_ekskul@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->kesiswaanUser = User::create([
            'username' => 'staff_kesiswaan_2',
            'email' => 'kesiswaan2@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->kesiswaanUser->roles()->attach($roleKesiswaan->id);

        $this->coachUser = User::create([
            'username' => 'pembina_futsal',
            'email' => 'pembina@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->coachUser->roles()->attach($roleTeacher->id);

        $this->otherTeacherUser = User::create([
            'username' => 'guru_lain',
            'email' => 'gurulain@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->otherTeacherUser->roles()->attach($roleTeacher->id);

        $this->studentUser = User::create([
            'username' => '20261005',
            'email' => 'doni_siswa@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($roleStudent->id);

        $this->otherStudentUser = User::create([
            'username' => '20261006',
            'email' => 'maya_siswa@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->otherStudentUser->roles()->attach($roleStudent->id);

        $this->coachTeacher = Teacher::create([
            'user_id' => $this->coachUser->id,
            'nip' => '198501012010011005',
            'full_name' => 'Agus Hendra, S.Pd',
            'gender' => 'L',
        ]);

        $this->student = Student::create([
            'user_id' => $this->studentUser->id,
            'nis' => '20261005',
            'nisn' => '0098765440',
            'nik_enc' => '3201010101010010',
            'full_name' => 'Doni Saputra',
            'gender' => 'L',
            'birth_place' => 'Surabaya',
            'birth_date' => '2009-02-14',
            'entry_date' => '2026-07-15',
            'status' => 'active',
        ]);

        $this->otherStudent = Student::create([
            'user_id' => $this->otherStudentUser->id,
            'nis' => '20261006',
            'nisn' => '0098765441',
            'nik_enc' => '3201010101010011',
            'full_name' => 'Maya Anggraeni',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2009-06-25',
            'entry_date' => '2026-07-15',
            'status' => 'active',
        ]);

        $this->futsal = Extracurricular::create([
            'name' => 'Futsal Prestasi',
            'category' => 'olahraga',
            'description' => 'Ekstrakurikuler futsal putra dan putri',
            'coach_id' => $this->coachTeacher->id,
            'quota' => 2,
            'schedule_day' => 'Jumat',
            'schedule_time' => '15:30 - 17:30',
            'is_active' => true,
        ]);
    }

    public function test_be_std_02_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/extracurriculars')->assertStatus(401);
        $this->getJson('/api/v1/extracurricular-members?extracurricular_id=1')->assertStatus(401);
        $this->getJson('/api/v1/extracurricular-journals?extracurricular_id=1')->assertStatus(401);
        $this->getJson('/api/v1/activity-documents')->assertStatus(401);
    }

    public function test_be_std_02_kesiswaan_admin_can_create_and_update_extracurricular(): void
    {
        $payload = [
            'name' => 'Robotik & AI Club',
            'category' => 'akademik',
            'description' => 'Klub robotika dan perakitan Arduino',
            'coach_id' => $this->coachTeacher->id,
            'quota' => 20,
            'schedule_day' => 'Sabtu',
            'schedule_time' => '09:00 - 12:00',
            'is_active' => true,
        ];

        $res = $this->actingAs($this->kesiswaanUser)
            ->postJson('/api/v1/extracurriculars', $payload);

        $res->assertStatus(201)
            ->assertJsonPath('data.name', 'Robotik & AI Club')
            ->assertJsonPath('data.quota', 20)
            ->assertJsonPath('data.available_quota', 20);

        $ekskulId = $res->json('data.id');

        // Update
        $updateRes = $this->actingAs($this->kesiswaanUser)
            ->putJson("/api/v1/extracurriculars/{$ekskulId}", [
                'quota' => 25,
                'schedule_day' => 'Minggu',
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.quota', 25)
            ->assertJsonPath('data.schedule_day', 'Minggu');
    }

    public function test_be_std_02_regular_student_cannot_create_extracurricular(): void
    {
        $this->actingAs($this->studentUser)
            ->postJson('/api/v1/extracurriculars', [
                'name' => 'Klub Gaming',
                'quota' => 10,
            ])
            ->assertStatus(403);
    }

    public function test_be_std_02_cannot_delete_extracurricular_with_active_members(): void
    {
        // Daftarkan anggota
        ExtracurricularMember::create([
            'extracurricular_id' => $this->futsal->id,
            'student_id' => $this->student->id,
            'status' => 'aktif',
            'role' => 'anggota',
        ]);

        $this->actingAs($this->kesiswaanUser)
            ->deleteJson("/api/v1/extracurriculars/{$this->futsal->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'HTTP_ERROR');
    }

    public function test_be_std_02_student_can_register_for_active_extracurricular_with_quota(): void
    {
        // Siswa Doni mendaftar sendiri
        $res = $this->actingAs($this->studentUser)
            ->postJson('/api/v1/extracurricular-members', [
                'extracurricular_id' => $this->futsal->id,
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.extracurricular_id', $this->futsal->id)
            ->assertJsonPath('data.student_id', $this->student->id)
            ->assertJsonPath('data.status', 'aktif')
            ->assertJsonPath('data.role', 'anggota');

        // Sisa kuota berkurang dari 2 menjadi 1
        $this->assertEquals(1, $this->futsal->fresh()->available_quota);
    }

    public function test_be_std_02_student_cannot_register_when_quota_is_full(): void
    {
        // Isi kuota penuh (quota = 2)
        ExtracurricularMember::create([
            'extracurricular_id' => $this->futsal->id,
            'student_id' => $this->student->id,
            'status' => 'aktif',
        ]);

        $thirdStudent = Student::create([
            'nis' => '20261007',
            'nisn' => '0098765449',
            'nik_enc' => '3201010101010099',
            'full_name' => 'Siswa Ketiga',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2009-01-01',
            'entry_date' => '2026-07-15',
            'status' => 'active',
        ]);

        ExtracurricularMember::create([
            'extracurricular_id' => $this->futsal->id,
            'student_id' => $thirdStudent->id,
            'status' => 'aktif',
        ]);

        // Siswa Maya mencoba mendaftar padahal kuota sudah 2/2
        $res = $this->actingAs($this->otherStudentUser)
            ->postJson('/api/v1/extracurricular-members', [
                'extracurricular_id' => $this->futsal->id,
            ]);

        $res->assertStatus(422)
            ->assertJsonPath('error.code', 'HTTP_ERROR');
    }

    public function test_be_std_02_student_cannot_register_twice_as_active_member(): void
    {
        ExtracurricularMember::create([
            'extracurricular_id' => $this->futsal->id,
            'student_id' => $this->student->id,
            'status' => 'aktif',
        ]);

        $this->actingAs($this->studentUser)
            ->postJson('/api/v1/extracurricular-members', [
                'extracurricular_id' => $this->futsal->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'HTTP_ERROR');
    }

    public function test_be_std_02_coach_can_update_member_role_and_final_grade_for_rapor(): void
    {
        $member = ExtracurricularMember::create([
            'extracurricular_id' => $this->futsal->id,
            'student_id' => $this->student->id,
            'status' => 'aktif',
            'role' => 'anggota',
        ]);

        $res = $this->actingAs($this->coachUser)
            ->putJson("/api/v1/extracurricular-members/{$member->id}", [
                'role' => 'ketua',
                'final_grade' => 'A',
            ]);

        $res->assertStatus(200)
            ->assertJsonPath('data.role', 'ketua')
            ->assertJsonPath('data.final_grade', 'A');

        $this->assertDatabaseHas('extracurricular_members', [
            'id' => $member->id,
            'role' => 'ketua',
            'final_grade' => 'A',
        ]);
    }

    public function test_be_std_02_student_can_leave_extracurricular(): void
    {
        $member = ExtracurricularMember::create([
            'extracurricular_id' => $this->futsal->id,
            'student_id' => $this->student->id,
            'status' => 'aktif',
        ]);

        // Siswa Doni keluar dari ekskul futsal
        $this->actingAs($this->studentUser)
            ->deleteJson("/api/v1/extracurricular-members/{$member->id}")
            ->assertStatus(200);

        $this->assertDatabaseHas('extracurricular_members', [
            'id' => $member->id,
            'status' => 'keluar',
        ]);

        // Kuota kembali bertambah
        $this->assertEquals(2, $this->futsal->fresh()->available_quota);
    }

    public function test_be_std_02_coach_can_record_journal_with_attendance(): void
    {
        ExtracurricularMember::create([
            'extracurricular_id' => $this->futsal->id,
            'student_id' => $this->student->id,
            'status' => 'aktif',
        ]);
        ExtracurricularMember::create([
            'extracurricular_id' => $this->futsal->id,
            'student_id' => $this->otherStudent->id,
            'status' => 'aktif',
        ]);

        $payload = [
            'extracurricular_id' => $this->futsal->id,
            'session_date' => Carbon::now()->toDateString(),
            'start_time' => '15:30',
            'end_time' => '17:30',
            'activity' => 'Latihan fisik sprint dan teknik dasar passing shooting futsal',
            'attendance' => [
                ['student_id' => $this->student->id, 'status' => 'hadir'],
                ['student_id' => $this->otherStudent->id, 'status' => 'izin'],
            ],
            'notes' => 'Semua siswa membawa sepatu sol karet',
        ];

        $res = $this->actingAs($this->coachUser)
            ->postJson('/api/v1/extracurricular-journals', $payload);

        $res->assertStatus(201)
            ->assertJsonPath('data.extracurricular_id', $this->futsal->id)
            ->assertJsonPath('data.activity', 'Latihan fisik sprint dan teknik dasar passing shooting futsal');

        $this->assertDatabaseHas('extracurricular_journals', [
            'extracurricular_id' => $this->futsal->id,
            'activity' => 'Latihan fisik sprint dan teknik dasar passing shooting futsal',
        ]);
    }

    public function test_be_std_02_non_coach_teacher_cannot_record_journal_for_another_ekskul(): void
    {
        $this->actingAs($this->otherTeacherUser)
            ->postJson('/api/v1/extracurricular-journals', [
                'extracurricular_id' => $this->futsal->id,
                'session_date' => Carbon::now()->toDateString(),
                'activity' => 'Mencoba mengisi jurnal orang lain',
            ])
            ->assertStatus(403);
    }

    public function test_be_std_02_user_can_upload_activity_document_proposal_and_laporan(): void
    {
        $file = File::create([
            'bucket' => 'local',
            'object_key' => 'docs/proposal_turnamen.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 2048,
            'visibility' => 'private',
        ]);

        $res = $this->actingAs($this->coachUser)
            ->postJson('/api/v1/activity-documents', [
                'extracurricular_id' => $this->futsal->id,
                'file_id' => $file->id,
                'doc_type' => 'proposal',
                'title' => 'Proposal Turnamen Futsal Cup Antar SMA 2026',
                'description' => 'Pengajuan anggaran dan susunan panitia OSIS dan ekskul futsal',
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.title', 'Proposal Turnamen Futsal Cup Antar SMA 2026')
            ->assertJsonPath('data.doc_type', 'proposal');

        $this->assertDatabaseHas('activity_documents', [
            'extracurricular_id' => $this->futsal->id,
            'file_id' => $file->id,
            'doc_type' => 'proposal',
        ]);
    }

    public function test_be_std_02_activity_document_uploader_or_admin_can_delete_document(): void
    {
        $file = File::create([
            'bucket' => 'local',
            'object_key' => 'docs/laporan_lpj.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 4096,
            'visibility' => 'private',
        ]);

        $doc = ActivityDocument::create([
            'extracurricular_id' => $this->futsal->id,
            'file_id' => $file->id,
            'doc_type' => 'laporan',
            'title' => 'LPJ Turnamen Futsal',
            'uploaded_by' => $this->coachUser->id,
        ]);

        // Siswa mencoba menghapus -> 403
        $this->actingAs($this->studentUser)
            ->deleteJson("/api/v1/activity-documents/{$doc->id}")
            ->assertStatus(403);

        // Uploader dapat menghapusnya -> 200
        $this->actingAs($this->coachUser)
            ->deleteJson("/api/v1/activity-documents/{$doc->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('activity_documents', ['id' => $doc->id]);
    }

    public function test_be_std_02_can_query_members_by_student_id_search_and_role(): void
    {
        ExtracurricularMember::create([
            'extracurricular_id' => $this->futsal->id,
            'student_id' => $this->student->id,
            'status' => 'aktif',
            'role' => 'ketua',
            'final_grade' => 'A',
        ]);

        ExtracurricularMember::create([
            'extracurricular_id' => $this->futsal->id,
            'student_id' => $this->otherStudent->id,
            'status' => 'aktif',
            'role' => 'anggota',
            'final_grade' => 'B',
        ]);

        // 1. Query by student_id directly without extracurricular_id
        $resByStudent = $this->actingAs($this->studentUser)
            ->getJson("/api/v1/extracurricular-members?student_id={$this->student->id}");

        $resByStudent->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.student_id', $this->student->id)
            ->assertJsonPath('data.0.role', 'ketua')
            ->assertJsonPath('data.0.final_grade', 'A');

        // 2. Query with search and role filter
        $resFiltered = $this->actingAs($this->coachUser)
            ->getJson("/api/v1/extracurricular-members?extracurricular_id={$this->futsal->id}&search=Maya&role=anggota");

        $resFiltered->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.student_id', $this->otherStudent->id)
            ->assertJsonPath('data.0.role', 'anggota');
    }

    public function test_be_std_02_journal_rejects_attendance_for_non_members(): void
    {
        // student is member, otherStudent is NOT member
        ExtracurricularMember::create([
            'extracurricular_id' => $this->futsal->id,
            'student_id' => $this->student->id,
            'status' => 'aktif',
        ]);

        $payload = [
            'extracurricular_id' => $this->futsal->id,
            'session_date' => Carbon::now()->toDateString(),
            'activity' => 'Latihan strategi',
            'attendance' => [
                ['student_id' => $this->student->id, 'status' => 'hadir'],
                ['student_id' => $this->otherStudent->id, 'status' => 'hadir'], // bukan anggota
            ],
        ];

        $res = $this->actingAs($this->coachUser)
            ->postJson('/api/v1/extracurricular-journals', $payload);

        $res->assertStatus(422)
            ->assertJsonPath('error.message', 'Presensi hanya dapat dicatat untuk anggota aktif pada ekstrakurikuler ini.');
    }

    public function test_be_std_02_cannot_update_final_grade_when_report_card_is_locked(): void
    {
        $member = ExtracurricularMember::create([
            'extracurricular_id' => $this->futsal->id,
            'student_id' => $this->student->id,
            'status' => 'aktif',
            'role' => 'anggota',
        ]);

        $ay = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);
        $sem = Semester::create([
            'academic_year_id' => $ay->id,
            'name' => 'Ganjil',
            'semester' => 'ganjil',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        ReportCard::create([
            'student_id' => $this->student->id,
            'semester_id' => $sem->id,
            'status' => 'locked',
            'locked_at' => now(),
            'verify_token' => Str::random(32),
            'data_snapshot' => ['extracurriculars' => []],
        ]);

        // Coach mencoba mengubah nilai saat rapor terkunci -> 422
        $res = $this->actingAs($this->coachUser)
            ->putJson("/api/v1/extracurricular-members/{$member->id}", [
                'final_grade' => 'A',
            ]);

        $res->assertStatus(422)
            ->assertJsonPath('error.message', 'Rapor semester siswa telah dikunci. Perubahan nilai ekstrakurikuler tidak diizinkan tanpa pembukaan kunci oleh administrator.');
    }

    public function test_be_std_02_unauthorized_teacher_cannot_upload_document_to_other_ekskul(): void
    {
        $file = File::create([
            'bucket' => 'local',
            'object_key' => 'docs/proposal.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'visibility' => 'private',
        ]);

        // otherTeacherUser bukan coach futsal dan bukan staf kesiswaan -> 403
        $this->actingAs($this->otherTeacherUser)
            ->postJson('/api/v1/activity-documents', [
                'extracurricular_id' => $this->futsal->id,
                'file_id' => $file->id,
                'doc_type' => 'proposal',
                'title' => 'Proposal Ilegal',
            ])
            ->assertStatus(403);
    }

    public function test_be_std_02_unauthorized_teacher_cannot_register_student_to_other_ekskul(): void
    {
        // otherTeacherUser mencoba mendaftarkan siswa ke futsal -> 403
        $this->actingAs($this->otherTeacherUser)
            ->postJson('/api/v1/extracurricular-members', [
                'extracurricular_id' => $this->futsal->id,
                'student_id' => $this->student->id,
            ])
            ->assertStatus(403);
    }
}
