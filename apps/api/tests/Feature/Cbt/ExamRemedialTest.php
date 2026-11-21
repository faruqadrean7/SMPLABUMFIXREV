<?php

namespace Tests\Feature\Cbt;

use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamParticipant;
use App\Models\ExamQuestion;
use App\Models\ExamRoom;
use App\Models\ExamSession;
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

class ExamRemedialTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $teacherUser;

    protected User $otherUser;

    protected Student $studentPassed;

    protected Student $studentFailed;

    protected Student $studentUnstarted;

    protected Exam $parentExam;

    protected Question $qParent;

    protected Question $qRemedial;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'admin'], ['name' => 'Admin', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $permManage = Permission::firstOrCreate(['code' => 'cbt.exam.manage'], ['description' => 'Kelola Ujian']);
        $roleAdmin->permissions()->syncWithoutDetaching([$permManage->id]);
        $roleTeacher->permissions()->syncWithoutDetaching([$permManage->id]);

        $this->adminUser = User::create([
            'username' => 'admin_cbt_rem',
            'email' => 'admin_rem@test.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->teacherUser = User::create([
            'username' => 'guru_rem',
            'email' => 'guru_rem@test.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher->id);

        $this->otherUser = User::create([
            'username' => 'other_rem',
            'email' => 'other_rem@test.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $subject = Subject::create([
            'code' => 'MAT10',
            'name' => 'Matematika Umum',
            'subject_group' => 'muatan_nasional',
        ]);

        $room = Room::create([
            'code' => 'R-101',
            'name' => 'Ruang Teori 101',
            'room_type' => 'kelas',
            'capacity' => 36,
        ]);

        $bank = QuestionBank::create([
            'subject_id' => $subject->id,
            'owner_id' => $this->teacherUser->id,
            'name' => 'Bank Matematika Remedial',
        ]);

        $this->qParent = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'pg',
            'body' => ['text' => 'Berapa 10 + 15?'],
            'weight' => 1.0,
            'status' => 'aktif',
        ]);
        QuestionOption::create(['question_id' => $this->qParent->id, 'label' => 'A', 'body' => ['text' => '25'], 'is_correct' => true]);
        QuestionOption::create(['question_id' => $this->qParent->id, 'label' => 'B', 'body' => ['text' => '30'], 'is_correct' => false]);

        $this->qRemedial = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'pg',
            'body' => ['text' => 'Berapa 5 x 5?'],
            'weight' => 1.0,
            'status' => 'aktif',
        ]);
        QuestionOption::create(['question_id' => $this->qRemedial->id, 'label' => 'A', 'body' => ['text' => '25'], 'is_correct' => true]);

        // Ujian Induk dengan KKTP = 75.0
        $this->parentExam = Exam::create([
            'subject_id' => $subject->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Sumatif Tengah Semester Matematika',
            'exam_type' => 'STS',
            'duration_min' => 60,
            'start_at' => Carbon::now()->subDays(2),
            'end_at' => Carbon::now()->subDays(1),
            'passing_grade' => 75.0,
            'status' => 'selesai',
        ]);

        ExamQuestion::create([
            'exam_id' => $this->parentExam->id,
            'question_id' => $this->qParent->id,
            'sort_order' => 1,
        ]);

        $parentRoom = ExamRoom::create([
            'exam_id' => $this->parentExam->id,
            'room_id' => $room->id,
            'proctor_id' => $this->teacherUser->id,
            'session_no' => 1,
            'token' => 'MAT001',
            'token_expires_at' => Carbon::now()->subDays(1),
        ]);

        // Siswa 1: Lulus (Skor 90)
        $user1 = User::create(['username' => 's1_rem', 'email' => 's1_rem@test.sch.id', 'password_hash' => Hash::make('password123'), 'status' => 'active']);
        $user1->roles()->attach($roleStudent->id);
        $this->studentPassed = Student::create([
            'user_id' => $user1->id,
            'full_name' => 'Siswa Pintar Lulus',
            'nis' => '2001',
            'nisn' => '0020010001',
            'nik_enc' => 'enc_nik_2001',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);
        ExamParticipant::create(['exam_id' => $this->parentExam->id, 'student_id' => $this->studentPassed->id, 'exam_room_id' => $parentRoom->id]);
        ExamSession::create([
            'exam_id' => $this->parentExam->id,
            'student_id' => $this->studentPassed->id,
            'status' => 'selesai',
            'score' => 90.0,
            'seed' => 'seed1',
        ]);

        // Siswa 2: Tidak Lulus (Skor 50)
        $user2 = User::create(['username' => 's2_rem', 'email' => 's2_rem@test.sch.id', 'password_hash' => Hash::make('password123'), 'status' => 'active']);
        $user2->roles()->attach($roleStudent->id);
        $this->studentFailed = Student::create([
            'user_id' => $user2->id,
            'full_name' => 'Siswa Perlu Remedial',
            'nis' => '2002',
            'nisn' => '0020010002',
            'nik_enc' => 'enc_nik_2002',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-02-02',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);
        ExamParticipant::create(['exam_id' => $this->parentExam->id, 'student_id' => $this->studentFailed->id, 'exam_room_id' => $parentRoom->id]);
        ExamSession::create([
            'exam_id' => $this->parentExam->id,
            'student_id' => $this->studentFailed->id,
            'status' => 'selesai',
            'score' => 50.0,
            'seed' => 'seed2',
        ]);

        // Siswa 3: Terdaftar tapi belum ujian
        $user3 = User::create(['username' => 's3_rem', 'email' => 's3_rem@test.sch.id', 'password_hash' => Hash::make('password123'), 'status' => 'active']);
        $user3->roles()->attach($roleStudent->id);
        $this->studentUnstarted = Student::create([
            'user_id' => $user3->id,
            'full_name' => 'Siswa Belum Ujian',
            'nis' => '2003',
            'nisn' => '0020010003',
            'nik_enc' => 'enc_nik_2003',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2010-03-03',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);
        ExamParticipant::create(['exam_id' => $this->parentExam->id, 'student_id' => $this->studentUnstarted->id, 'exam_room_id' => $parentRoom->id]);
    }

    public function test_be_cbt_09_can_create_remedial_exam_automatically_for_students_below_kktp(): void
    {
        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->parentExam->id}/remedial", [
                'start_at' => Carbon::now()->addDay()->toDateTimeString(),
                'end_at' => Carbon::now()->addDays(2)->toDateTimeString(),
                'max_remedial_score' => 75.0,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.parent_exam_id', $this->parentExam->id)
            ->assertJsonPath('data.exam_type', 'remedial')
            ->assertJsonPath('data.max_remedial_score', '75.00');

        $remedialId = $response->json('data.id');
        $participants = $response->json('data.participants');

        // Otomatis memilih siswa 2 (skor 50) dan siswa 3 (belum ujian)
        $this->assertCount(2, $participants);
        $studentIds = collect($participants)->pluck('student_id')->all();
        $this->assertContains($this->studentFailed->id, $studentIds);
        $this->assertContains($this->studentUnstarted->id, $studentIds);
        $this->assertNotContains($this->studentPassed->id, $studentIds);

        $this->assertDatabaseHas('exams', [
            'id' => $remedialId,
            'parent_exam_id' => $this->parentExam->id,
            'exam_type' => 'remedial',
        ]);
    }

    public function test_be_cbt_09_remedial_exam_can_use_alternative_questions(): void
    {
        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->parentExam->id}/remedial", [
                'title' => 'Remedial Matematika Paket Alternatif',
                'start_at' => Carbon::now()->addDay()->toDateTimeString(),
                'end_at' => Carbon::now()->addDays(2)->toDateTimeString(),
                'question_ids' => [$this->qRemedial->id],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Remedial Matematika Paket Alternatif');

        $questions = $response->json('data.questions');
        $this->assertCount(1, $questions);
        $this->assertEquals($this->qRemedial->id, $questions[0]['question_id']);
    }

    public function test_be_cbt_09_remedial_exam_fails_if_no_students_need_remedial(): void
    {
        // Ubah skor siswa 2 dan buat sesi untuk siswa 3 agar semuanya lulus (> 75)
        ExamSession::where('exam_id', $this->parentExam->id)->update(['score' => 85.0]);
        ExamSession::create([
            'exam_id' => $this->parentExam->id,
            'student_id' => $this->studentUnstarted->id,
            'status' => 'selesai',
            'score' => 95.0,
            'seed' => 'seed3',
        ]);

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->parentExam->id}/remedial", [
                'start_at' => Carbon::now()->addDay()->toDateTimeString(),
                'end_at' => Carbon::now()->addDays(2)->toDateTimeString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');
    }

    public function test_be_cbt_09_remedial_exam_enforces_manual_target_students_validation(): void
    {
        $fakeStudentId = '00000000-0000-0000-0000-000000000099';

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->parentExam->id}/remedial", [
                'start_at' => Carbon::now()->addDay()->toDateTimeString(),
                'end_at' => Carbon::now()->addDays(2)->toDateTimeString(),
                'target_student_ids' => [$fakeStudentId],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');
    }

    public function test_be_cbt_09_remedial_exam_caps_score_at_max_remedial_score(): void
    {
        // Buat ujian remedial dengan max_remedial_score = 75.0
        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->parentExam->id}/remedial", [
                'start_at' => Carbon::now()->addDay()->toDateTimeString(),
                'end_at' => Carbon::now()->addDays(2)->toDateTimeString(),
                'max_remedial_score' => 75.0,
            ]);

        $remedialId = $response->json('data.id');

        // Siswa menjawab benar 100% pada remedial
        $session = ExamSession::create([
            'exam_id' => $remedialId,
            'student_id' => $this->studentFailed->id,
            'status' => 'selesai',
            'seed' => 'seed_rem',
        ]);

        ExamAnswer::create([
            'session_id' => $session->id,
            'question_id' => $this->qParent->id,
            'answer' => 'A', // Jawaban benar bernilai 1.0 (100%)
            'answered_at' => Carbon::now(),
        ]);

        // Jalankan auto-grade pada remedial
        $gradeResponse = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson("/api/v1/exams/{$remedialId}/auto-grade");

        $gradeResponse->assertStatus(200);

        // Nilai siswa harus dibatasi maksimal 75.0 (bukan 100)
        $session->refresh();
        $this->assertEquals(75.0, (float) $session->score);
    }

    public function test_be_cbt_09_unauthorized_user_cannot_create_remedial(): void
    {
        $response = $this->actingAs($this->otherUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->parentExam->id}/remedial", [
                'start_at' => Carbon::now()->addDay()->toDateTimeString(),
                'end_at' => Carbon::now()->addDays(2)->toDateTimeString(),
            ]);

        $response->assertStatus(403);
    }
}
