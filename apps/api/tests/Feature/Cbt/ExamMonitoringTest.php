<?php

namespace Tests\Feature\Cbt;

use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamParticipant;
use App\Models\ExamQuestion;
use App\Models\ExamRoom;
use App\Models\ExamSession;
use App\Models\ExamViolation;
use App\Models\GradeLevel;
use App\Models\Permission;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Role;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExamMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $proctorUser;

    protected User $otherUser;

    protected Exam $exam;

    protected ExamRoom $examRoom1;

    protected ExamRoom $examRoom2;

    protected Student $student1;

    protected Student $student2;

    protected Student $student3;

    protected ExamSession $session1;

    protected ExamSession $session2;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'admin'], ['name' => 'Admin', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $permManage = Permission::firstOrCreate(['code' => 'cbt.exam.manage'], ['description' => 'Kelola Ujian']);
        $permProctor = Permission::firstOrCreate(['code' => 'cbt.proctor.action'], ['description' => 'Aksi Proktor']);
        $roleAdmin->permissions()->syncWithoutDetaching([$permManage->id]);
        $roleTeacher->permissions()->syncWithoutDetaching([$permProctor->id]);

        $this->adminUser = User::create([
            'username' => 'admin_cbt_mon',
            'email' => 'admin_cbt_mon@test.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->proctorUser = User::create([
            'username' => 'proctor_mon',
            'email' => 'proctor_mon@test.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->proctorUser->roles()->attach($roleTeacher->id);

        $this->otherUser = User::create([
            'username' => 'other_mon',
            'email' => 'other_mon@test.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $academicYear = AcademicYear::create([
            'name' => '2026/2027 Ganjil',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $gradeLevel = GradeLevel::create([
            'name' => 'Kelas X',
            'level' => 10,
        ]);

        $subject = Subject::create([
            'code' => 'KIM10',
            'name' => 'Kimia Dasar',
            'subject_group' => 'muatan_nasional',
        ]);

        $room1 = Room::create([
            'code' => 'LAB-KOMP-1',
            'name' => 'Laboratorium Komputer 1',
            'room_type' => 'lab',
            'capacity' => 30,
        ]);

        $room2 = Room::create([
            'code' => 'LAB-KOMP-2',
            'name' => 'Laboratorium Komputer 2',
            'room_type' => 'lab',
            'capacity' => 30,
        ]);

        $bank = QuestionBank::create([
            'subject_id' => $subject->id,
            'owner_id' => $this->proctorUser->id,
            'name' => 'Bank Soal Kimia Monitoring',
        ]);

        $q1 = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'pg',
            'body' => ['text' => 'Lambang atom Emas adalah?'],
            'weight' => 1.0,
            'status' => 'aktif',
        ]);
        QuestionOption::create(['question_id' => $q1->id, 'label' => 'A', 'body' => ['text' => 'Au'], 'is_correct' => true]);
        QuestionOption::create(['question_id' => $q1->id, 'label' => 'B', 'body' => ['text' => 'Ag'], 'is_correct' => false]);

        $q2 = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'pg',
            'body' => ['text' => 'Rumus air adalah?'],
            'weight' => 1.0,
            'status' => 'aktif',
        ]);
        QuestionOption::create(['question_id' => $q2->id, 'label' => 'A', 'body' => ['text' => 'H2O'], 'is_correct' => true]);

        $this->exam = Exam::create([
            'subject_id' => $subject->id,
            'created_by' => $this->adminUser->id,
            'title' => 'Penilaian Akhir Semester Kimia',
            'exam_type' => 'SAS',
            'duration_min' => 90,
            'start_at' => Carbon::now()->subMinutes(10),
            'end_at' => Carbon::now()->addHours(2),
            'passing_grade' => 75.0,
            'status' => 'berlangsung',
        ]);

        ExamQuestion::create(['exam_id' => $this->exam->id, 'question_id' => $q1->id, 'sort_order' => 1]);
        ExamQuestion::create(['exam_id' => $this->exam->id, 'question_id' => $q2->id, 'sort_order' => 2]);

        $this->examRoom1 = ExamRoom::create([
            'exam_id' => $this->exam->id,
            'room_id' => $room1->id,
            'proctor_id' => $this->proctorUser->id,
            'session_no' => 1,
            'token' => 'KIM001',
            'token_expires_at' => Carbon::now()->addHours(2),
        ]);

        $this->examRoom2 = ExamRoom::create([
            'exam_id' => $this->exam->id,
            'room_id' => $room2->id,
            'proctor_id' => $this->adminUser->id,
            'session_no' => 1,
            'token' => 'KIM002',
            'token_expires_at' => Carbon::now()->addHours(2),
        ]);

        $userStudent1 = User::create([
            'username' => 'siswa1_mon',
            'email' => 'siswa1_mon@test.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $userStudent1->roles()->attach($roleStudent->id);
        $this->student1 = Student::create([
            'user_id' => $userStudent1->id,
            'full_name' => 'Ahmad Santoso',
            'nis' => '10011',
            'nisn' => '0010011001',
            'nik_enc' => 'enc_3578010101010001',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $userStudent2 = User::create([
            'username' => 'siswa2_mon',
            'email' => 'siswa2_mon@test.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $userStudent2->roles()->attach($roleStudent->id);
        $this->student2 = Student::create([
            'user_id' => $userStudent2->id,
            'full_name' => 'Budi Pratama',
            'nis' => '10012',
            'nisn' => '0010011002',
            'nik_enc' => 'enc_3578010101010002',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-02-02',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $userStudent3 = User::create([
            'username' => 'siswa3_mon',
            'email' => 'siswa3_mon@test.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $userStudent3->roles()->attach($roleStudent->id);
        $this->student3 = Student::create([
            'user_id' => $userStudent3->id,
            'full_name' => 'Citra Dewi',
            'nis' => '10013',
            'nisn' => '0010011003',
            'nik_enc' => 'enc_3578010101010003',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2010-03-03',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        ExamParticipant::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student1->id,
            'exam_room_id' => $this->examRoom1->id,
        ]);

        ExamParticipant::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student2->id,
            'exam_room_id' => $this->examRoom1->id,
        ]);

        ExamParticipant::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student3->id,
            'exam_room_id' => $this->examRoom2->id,
        ]);

        // Student 1: Mengerjakan, menjawab 1 soal
        $this->session1 = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student1->id,
            'status' => 'mengerjakan',
            'started_at' => Carbon::now()->subMinutes(15),
            'deadline_at' => Carbon::now()->addMinutes(75),
            'seed' => 'seed123',
        ]);

        ExamAnswer::create([
            'session_id' => $this->session1->id,
            'question_id' => $q1->id,
            'answer' => 'A',
            'answered_at' => Carbon::now()->subMinutes(5),
        ]);

        // Student 2: Terkunci karena pelanggaran
        $this->session2 = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student2->id,
            'status' => 'terkunci',
            'started_at' => Carbon::now()->subMinutes(20),
            'deadline_at' => Carbon::now()->addMinutes(70),
            'seed' => 'seed456',
            'locked_reason' => 'Keluar dari aplikasi',
        ]);

        ExamViolation::create([
            'session_id' => $this->session2->id,
            'violation_type' => 'blur',
            'meta' => ['count' => 3],
            'occurred_at' => Carbon::now()->subMinutes(2),
        ]);

        // Student 3: Belum mulai (tidak ada ExamSession)
    }

    public function test_be_cbt_06_proctor_can_get_live_monitoring_status(): void
    {
        $response = $this->actingAs($this->proctorUser, 'sanctum')
            ->getJson("/api/v1/exams/{$this->exam->id}/monitor");

        $response->assertStatus(200)
            ->assertJsonPath('data.exam_id', $this->exam->id)
            ->assertJsonPath('data.summary.total_participants', 2)
            ->assertJsonPath('data.summary.in_progress_count', 1)
            ->assertJsonPath('data.summary.locked_count', 1)
            ->assertJsonPath('data.summary.not_started_count', 0)
            ->assertJsonPath('data.summary.total_violations', 1);

        $participants = $response->json('data.participants');
        $this->assertCount(2, $participants);

        // Periksa data siswa 1
        $item1 = collect($participants)->firstWhere('student_id', $this->student1->id);
        $this->assertNotNull($item1);
        $this->assertEquals('mengerjakan', $item1['status']);
        $this->assertEquals(1, $item1['answered_questions']);
        $this->assertEquals(2, $item1['total_questions']);
        $this->assertEquals(50.0, $item1['progress_percentage']);
        $this->assertGreaterThan(0, $item1['remaining_seconds']);
    }

    public function test_be_cbt_06_monitoring_filters_by_room(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/exams/{$this->exam->id}/monitor?room_id={$this->examRoom2->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total_participants', 1)
            ->assertJsonPath('data.summary.not_started_count', 1);

        $participants = $response->json('data.participants');
        $this->assertCount(1, $participants);
        $this->assertEquals($this->student3->id, $participants[0]['student_id']);
    }

    public function test_be_cbt_06_monitoring_filters_by_status(): void
    {
        $response = $this->actingAs($this->proctorUser, 'sanctum')
            ->getJson("/api/v1/exams/{$this->exam->id}/monitor?status=terkunci");

        $response->assertStatus(200);
        $participants = $response->json('data.participants');
        $this->assertCount(1, $participants);
        $this->assertEquals($this->student2->id, $participants[0]['student_id']);
        $this->assertEquals('terkunci', $participants[0]['status']);
        $this->assertEquals(1, $participants[0]['violation_count']);
    }

    public function test_be_cbt_06_monitoring_filters_by_search_query(): void
    {
        $response = $this->actingAs($this->proctorUser, 'sanctum')
            ->getJson("/api/v1/exams/{$this->exam->id}/monitor?search=Santoso");

        $response->assertStatus(200);
        $participants = $response->json('data.participants');
        $this->assertCount(1, $participants);
        $this->assertEquals('Ahmad Santoso', $participants[0]['student_name']);
    }

    public function test_be_cbt_06_unauthorized_user_cannot_access_monitor(): void
    {
        $response = $this->actingAs($this->otherUser, 'sanctum')
            ->getJson("/api/v1/exams/{$this->exam->id}/monitor");

        $response->assertStatus(403);
    }

    public function test_be_cbt_06_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson("/api/v1/exams/{$this->exam->id}/monitor");

        $response->assertStatus(401);
    }

    public function test_be_cbt_06_non_existent_exam_returns_404(): void
    {
        $nonExistentId = '00000000-0000-0000-0000-000000000099';
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/exams/{$nonExistentId}/monitor");

        $response->assertStatus(404);
    }
}
