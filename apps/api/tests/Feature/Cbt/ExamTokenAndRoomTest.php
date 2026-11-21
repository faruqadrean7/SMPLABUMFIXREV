<?php

namespace Tests\Feature\Cbt;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\ExamRoom;
use App\Models\GradeLevel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExamTokenAndRoomTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $teacherProctor1;

    protected User $teacherProctor2;

    protected User $studentUser;

    protected Exam $exam;

    protected Room $room1;

    protected Room $room2;

    protected ExamRoom $examRoom1;

    protected ExamRoom $examRoom2;

    protected Student $student1;

    protected Student $student2;

    protected Student $student3;

    protected Student $student4;

    protected function setUp(): void
    {
        parent::setUp();

        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $permManage = Permission::firstOrCreate(['code' => 'cbt.exam.manage'], ['description' => 'Mengelola jadwal dan sesi ujian CBT']);
        $roleAdmin->permissions()->syncWithoutDetaching([$permManage->id]);

        $this->adminUser = User::create([
            'username' => 'admin_cbt',
            'email' => 'admin_cbt@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->teacherProctor1 = User::create([
            'username' => 'proctor_1',
            'email' => 'proctor_1@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacherProctor1->roles()->attach($roleTeacher->id);

        $this->teacherProctor2 = User::create([
            'username' => 'proctor_2',
            'email' => 'proctor_2@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacherProctor2->roles()->attach($roleTeacher->id);

        $this->studentUser = User::create([
            'username' => 'student_user',
            'email' => 'student_user@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($roleStudent->id);

        $teacher1 = Teacher::create([
            'user_id' => $this->teacherProctor1->id,
            'nip' => '198001012005011001',
            'full_name' => 'Guru Pengawas Satu, M.Pd',
            'gender' => 'L',
        ]);

        $teacher2 = Teacher::create([
            'user_id' => $this->teacherProctor2->id,
            'nip' => '198202022006022002',
            'full_name' => 'Guru Pengawas Dua, S.Pd',
            'gender' => 'P',
        ]);

        $academicYear = AcademicYear::create([
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
            'academic_year_id' => $academicYear->id,
            'grade_level_id' => $gradeLevel->id,
            'homeroom_teacher_id' => $teacher1->id,
            'name' => '10-A',
            'capacity' => 36,
        ]);

        $subject = Subject::create([
            'name' => 'Matematika Peminatan',
            'code' => 'MTK-MINAT',
            'subject_group' => 'muatan_nasional',
        ]);

        $this->room1 = Room::create([
            'name' => 'Lab Komputer 1',
            'code' => 'LAB-KOMP-1',
            'room_type' => 'laboratorium',
            'capacity' => 2,
        ]);

        $this->room2 = Room::create([
            'name' => 'Lab Komputer 2',
            'code' => 'LAB-KOMP-2',
            'room_type' => 'laboratorium',
            'capacity' => 2,
        ]);

        $this->exam = Exam::create([
            'subject_id' => $subject->id,
            'created_by' => $this->adminUser->id,
            'title' => 'Sumatif Akhir Semester (SAS) Matematika',
            'exam_type' => 'sas',
            'duration_min' => 90,
            'start_at' => Carbon::now()->addDays(2),
            'end_at' => Carbon::now()->addDays(2)->addHours(3),
            'passing_grade' => 75.0,
            'shuffle_questions' => true,
            'shuffle_options' => true,
            'status' => 'draft',
        ]);

        $this->examRoom1 = ExamRoom::create([
            'exam_id' => $this->exam->id,
            'room_id' => $this->room1->id,
            'proctor_id' => $this->teacherProctor1->id,
            'session_no' => 1,
            'token' => 'ABCDEF',
            'token_expires_at' => Carbon::now()->addMinutes(30),
        ]);

        $this->examRoom2 = ExamRoom::create([
            'exam_id' => $this->exam->id,
            'room_id' => $this->room2->id,
            'proctor_id' => $this->teacherProctor2->id,
            'session_no' => 1,
            'token' => 'GHIJKL',
            'token_expires_at' => Carbon::now()->addMinutes(30),
        ]);

        $this->student1 = Student::create([
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

        $this->student2 = Student::create([
            'full_name' => 'Budi Santoso',
            'nis' => '20261002',
            'nisn' => '0012345679',
            'nik_enc' => 'enc_3578010101010002',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-02-02',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $this->student3 = Student::create([
            'full_name' => 'Citra Lestari',
            'nis' => '20261003',
            'nisn' => '0012345680',
            'nik_enc' => 'enc_3578010101010003',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2010-03-03',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $this->student4 = Student::create([
            'full_name' => 'Dewi Anggraini',
            'nis' => '20261004',
            'nisn' => '0012345681',
            'nik_enc' => 'enc_3578010101010004',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2010-04-04',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        // Hubungkan siswa ke kelas
        ClassStudent::create([
            'class_id' => $classA->id,
            'student_id' => $this->student1->id,
            'joined_at' => '2026-07-15',
        ]);
        ClassStudent::create([
            'class_id' => $classA->id,
            'student_id' => $this->student2->id,
            'joined_at' => '2026-07-15',
        ]);
        ClassStudent::create([
            'class_id' => $classA->id,
            'student_id' => $this->student3->id,
            'joined_at' => '2026-07-15',
        ]);
        ClassStudent::create([
            'class_id' => $classA->id,
            'student_id' => $this->student4->id,
            'joined_at' => '2026-07-15',
        ]);
    }

    /**
     * BE-CBT-03: Regenerate token oleh Pengawas Ruang yang bertugas berhasil.
     */
    public function test_be_cbt_03_proctor_can_regenerate_token_for_assigned_room(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->teacherProctor1, 'sanctum')
            ->postJson("/api/v1/exam-rooms/{$this->examRoom1->id}/regenerate-token", [
                'validity_minutes' => 45,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'exam_id',
                    'token',
                    'token_expires_at',
                    'session_no',
                ],
            ]);

        $newToken = $response->json('data.token');
        $this->assertNotEmpty($newToken);
        $this->assertEquals(6, strlen($newToken));
        $this->assertNotEquals('ABCDEF', $newToken);

        $this->examRoom1->refresh();
        $this->assertEquals($newToken, $this->examRoom1->token);
        $this->assertNotNull($this->examRoom1->token_expires_at);
    }

    /**
     * BE-CBT-03: Admin dengan permission cbt.exam.manage bisa regenerate token ruang mana saja.
     */
    public function test_be_cbt_03_admin_can_regenerate_token_for_any_room(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/exam-rooms/{$this->examRoom2->id}/regenerate-token", [
                'validity_minutes' => 60,
            ]);

        $response->assertStatus(200);

        $newToken = $response->json('data.token');
        $this->assertNotEmpty($newToken);
        $this->assertEquals(6, strlen($newToken));
        $this->assertNotEquals('GHIJKL', $newToken);
    }

    /**
     * BE-CBT-03: Pengawas ruang lain ditolak saat coba regenerate token ruang yang bukan tugasnya (IDOR check).
     */
    public function test_be_cbt_03_other_proctor_forbidden_to_regenerate_token(): void
    {
        $this->app['auth']->forgetGuards();

        // teacherProctor1 mencoba regenerate token untuk examRoom2 (yang diawasi teacherProctor2)
        $response = $this->actingAs($this->teacherProctor1, 'sanctum')
            ->postJson("/api/v1/exam-rooms/{$this->examRoom2->id}/regenerate-token");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-CBT-03: Validasi durasi masa berlaku token (min 5, max 1440 menit).
     */
    public function test_be_cbt_03_regenerate_token_validates_validity_minutes(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->teacherProctor1, 'sanctum')
            ->postJson("/api/v1/exam-rooms/{$this->examRoom1->id}/regenerate-token", [
                'validity_minutes' => 2, // Kurang dari 5
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['validity_minutes']);
    }

    /**
     * BE-CBT-03: Cek status token ruang ujian saat masih aktif.
     */
    public function test_be_cbt_03_check_token_status_active(): void
    {
        $this->examRoom1->update([
            'token' => 'ACT123',
            'token_expires_at' => Carbon::now()->addMinutes(20),
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->teacherProctor1, 'sanctum')
            ->getJson("/api/v1/exam-rooms/{$this->examRoom1->id}/token-status");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.is_expired', false)
            ->assertJsonPath('data.token', 'ACT123');

        $this->assertGreaterThan(0, $response->json('data.remaining_seconds'));
    }

    /**
     * BE-CBT-03: Cek status token ruang ujian saat sudah kedaluwarsa.
     */
    public function test_be_cbt_03_check_token_status_expired(): void
    {
        $this->examRoom1->update([
            'token' => 'OLD123',
            'token_expires_at' => Carbon::now()->subMinutes(5),
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->teacherProctor1, 'sanctum')
            ->getJson("/api/v1/exam-rooms/{$this->examRoom1->id}/token-status");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'expired')
            ->assertJsonPath('data.is_expired', true)
            ->assertJsonPath('data.remaining_seconds', 0);
    }

    /**
     * BE-CBT-03: Auto-distribusi peserta ujian merata (balanced mode) ke ruang-ruang ujian.
     */
    public function test_be_cbt_03_auto_distribute_participants_balanced_mode(): void
    {
        // Daftarkan 4 siswa ke ujian (awalnya semua di examRoom1)
        ExamParticipant::create(['exam_id' => $this->exam->id, 'student_id' => $this->student1->id, 'exam_room_id' => $this->examRoom1->id]);
        ExamParticipant::create(['exam_id' => $this->exam->id, 'student_id' => $this->student2->id, 'exam_room_id' => $this->examRoom1->id]);
        ExamParticipant::create(['exam_id' => $this->exam->id, 'student_id' => $this->student3->id, 'exam_room_id' => $this->examRoom1->id]);
        ExamParticipant::create(['exam_id' => $this->exam->id, 'student_id' => $this->student4->id, 'exam_room_id' => $this->examRoom1->id]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/distribute-participants", [
                'mode' => 'balanced',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.total_participants', 4)
            ->assertJsonPath('data.distributed_count', 4);

        // Periksa alokasi 4 peserta terbagi ke 2 ruangan: masing-masing 2 peserta
        $room1Count = ExamParticipant::where('exam_id', $this->exam->id)
            ->where('exam_room_id', $this->examRoom1->id)
            ->count();
        $room2Count = ExamParticipant::where('exam_id', $this->exam->id)
            ->where('exam_room_id', $this->examRoom2->id)
            ->count();

        $this->assertEquals(2, $room1Count);
        $this->assertEquals(2, $room2Count);
    }

    /**
     * BE-CBT-03: Auto-distribusi peserta ujian mode kapasitas (fill_capacity).
     */
    public function test_be_cbt_03_auto_distribute_participants_fill_capacity_mode(): void
    {
        ExamParticipant::create(['exam_id' => $this->exam->id, 'student_id' => $this->student1->id, 'exam_room_id' => $this->examRoom1->id]);
        ExamParticipant::create(['exam_id' => $this->exam->id, 'student_id' => $this->student2->id, 'exam_room_id' => $this->examRoom1->id]);
        ExamParticipant::create(['exam_id' => $this->exam->id, 'student_id' => $this->student3->id, 'exam_room_id' => $this->examRoom1->id]);

        $this->app['auth']->forgetGuards();

        // Gunakan custom_capacity = 2 per ruang
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/distribute-participants", [
                'mode' => 'fill_capacity',
                'custom_capacity' => 2,
            ]);

        $response->assertStatus(200);

        // Room 1 terisi 2 peserta, Room 2 terisi sisa 1 peserta
        $room1Count = ExamParticipant::where('exam_id', $this->exam->id)
            ->where('exam_room_id', $this->examRoom1->id)
            ->count();
        $room2Count = ExamParticipant::where('exam_id', $this->exam->id)
            ->where('exam_room_id', $this->examRoom2->id)
            ->count();

        $this->assertEquals(2, $room1Count);
        $this->assertEquals(1, $room2Count);
    }

    /**
     * BE-CBT-03: Daftar kartu peserta ujian dengan filter search dan pagination.
     */
    public function test_be_cbt_03_list_participant_cards_with_filter(): void
    {
        ExamParticipant::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student1->id,
            'exam_room_id' => $this->examRoom1->id,
            'package_code' => 'PAKET-A',
        ]);
        ExamParticipant::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student2->id,
            'exam_room_id' => $this->examRoom2->id,
            'package_code' => 'PAKET-B',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/exams/{$this->exam->id}/participant-cards?search=Faiz");

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.student.full_name', 'Ahmad Faiz Pratama');
    }

    /**
     * BE-CBT-03: Unduh PDF kartu peserta ujian berhasil dengan header PDF stream.
     */
    public function test_be_cbt_03_download_participant_card_pdf(): void
    {
        $participant = ExamParticipant::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student1->id,
            'exam_room_id' => $this->examRoom1->id,
            'package_code' => 'PAKET-A',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->get("/api/v1/exams/{$this->exam->id}/participants/{$participant->id}/card-pdf");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-1.4', $response->getContent());
        $this->assertStringContainsString('KARTU PESERTA UJIAN BERBASIS KOMPUTER', $response->getContent());
        $this->assertStringContainsString('20261001', $response->getContent());
    }

    /**
     * BE-CBT-03: Distribusi peserta ditolak jika ujian tidak memiliki ruang ujian terdaftar.
     */
    public function test_be_cbt_03_distribute_participants_fails_if_no_rooms_available(): void
    {
        $emptyExam = Exam::create([
            'subject_id' => $this->exam->subject_id,
            'created_by' => $this->adminUser->id,
            'title' => 'Ujian Tanpa Ruang',
            'exam_type' => 'kuis',
            'duration_min' => 60,
            'start_at' => Carbon::now()->addDays(2),
            'end_at' => Carbon::now()->addDays(2)->addHours(2),
            'passing_grade' => 70,
            'status' => 'draft',
        ]);

        ExamParticipant::create([
            'exam_id' => $emptyExam->id,
            'student_id' => $this->student1->id,
            'exam_room_id' => $this->examRoom1->id,
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/exams/{$emptyExam->id}/distribute-participants");

        $response->assertStatus(422)
            ->assertJsonPath('error.message', 'Tidak ada ruang ujian yang tersedia untuk didistribusikan.');
    }

    /**
     * SEC-CBT: Siswa dilarang mengintip status token ruang ujian secara langsung.
     */
    public function test_student_cannot_access_token_status_endpoint(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->getJson("/api/v1/exam-rooms/{$this->examRoom1->id}/token-status");

        $response->assertStatus(403);
    }

    /**
     * SEC-CBT: Siswa dilarang melihat daftar kartu peserta ujian seluruh kelas.
     */
    public function test_student_cannot_list_all_participant_cards(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->getJson("/api/v1/exams/{$this->exam->id}/participant-cards");

        $response->assertStatus(403);
    }

    /**
     * SEC-CBT / IDOR: Siswa dilarang mengunduh kartu ujian milik siswa lain.
     */
    public function test_student_cannot_download_other_student_card_pdf_idor(): void
    {
        $this->student1->update(['user_id' => $this->studentUser->id]);

        $participant2 = ExamParticipant::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student2->id,
            'exam_room_id' => $this->examRoom2->id,
            'package_code' => 'PAKET-B',
        ]);

        $this->app['auth']->forgetGuards();

        // Siswa 1 mencoba mengunduh kartu ujian milik siswa 2
        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->get("/api/v1/exams/{$this->exam->id}/participants/{$participant2->id}/card-pdf");

        $response->assertStatus(403);
    }

    /**
     * SEC-CBT: Siswa berhak mengunduh kartu ujian milik dirinya sendiri.
     */
    public function test_student_can_download_own_card_pdf(): void
    {
        $this->student1->update(['user_id' => $this->studentUser->id]);

        $participant1 = ExamParticipant::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student1->id,
            'exam_room_id' => $this->examRoom1->id,
            'package_code' => 'PAKET-A',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->get("/api/v1/exams/{$this->exam->id}/participants/{$participant1->id}/card-pdf");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }

    /**
     * SEC-CBT: Rate limit proteksi regenerasi token ujian maksimal 10 request per menit.
     */
    public function test_regenerate_token_endpoint_is_rate_limited(): void
    {
        $this->app['auth']->forgetGuards();

        for ($i = 0; $i < 10; $i++) {
            $response = $this->actingAs($this->adminUser, 'sanctum')
                ->postJson("/api/v1/exam-rooms/{$this->examRoom1->id}/regenerate-token", [
                    'validity_minutes' => 30,
                ]);
            $response->assertStatus(200);
        }

        // Request ke-11 harus diblokir oleh throttle:10,1 (429 Too Many Requests)
        $rateLimitedResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/exam-rooms/{$this->examRoom1->id}/regenerate-token", [
                'validity_minutes' => 30,
            ]);

        $rateLimitedResponse->assertStatus(429);
    }
}
