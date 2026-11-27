<?php

namespace Tests\Feature\Cbt;

use App\Models\Exam;
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
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Batch 2B-S — P1-11: Pengujian isolasi kepemilikan ujian, soal, kunci, token ruang,
 * pengawas lintas ruang, filter ?status=draft siswa, dan validasi push sync CBT.
 */
class CbtOwnershipAndScopeHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected string $schoolA;

    protected string $schoolB;

    protected User $teacherAUser;

    protected User $teacherBUser;

    protected User $proctorRoom1User;

    protected User $cbtOperatorSchoolA;

    protected User $adminSchoolB;

    protected User $studentUser;

    protected Student $student1;

    protected Student $student2;

    protected Student $unenrolledStudent;

    protected Subject $subjectA;

    protected Subject $subjectB;

    protected QuestionBank $bankA;

    protected Question $questionA;

    protected Question $foreignQuestion;

    protected Exam $examA;

    protected Exam $draftExamA;

    protected ExamRoom $examRoom1;

    protected ExamRoom $examRoom2;

    protected ExamParticipant $participant1;

    protected ExamParticipant $participant2;

    protected ExamSession $sessionRoom2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = (string) Str::uuid();
        $this->schoolB = (string) Str::uuid();

        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleCbtOp = Role::firstOrCreate(['code' => 'cbt_operator'], ['name' => 'Operator CBT', 'is_system' => true]);
        $roleAdminKur = Role::firstOrCreate(['code' => 'admin_kurikulum'], ['name' => 'Admin Kurikulum', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $permExamManage = Permission::firstOrCreate(['code' => 'cbt.exam.manage'], ['description' => 'Kelola Ujian CBT']);
        $permBankWrite = Permission::firstOrCreate(['code' => 'cbt.bank.write'], ['description' => 'Kelola Bank Soal']);

        // Sama seperti DatabaseSeeder: guru memegang cbt.exam.manage & cbt.bank.write
        $roleTeacher->permissions()->syncWithoutDetaching([$permExamManage->id, $permBankWrite->id]);
        $roleCbtOp->permissions()->syncWithoutDetaching([$permExamManage->id, $permBankWrite->id]);
        $roleAdminKur->permissions()->syncWithoutDetaching([$permExamManage->id, $permBankWrite->id]);

        $this->teacherAUser = $this->makeUser('p111_guru_a', $roleTeacher, $this->schoolA);
        $this->teacherBUser = $this->makeUser('p111_guru_b', $roleTeacher, $this->schoolA);
        $this->proctorRoom1User = $this->makeUser('p111_proctor_r1', $roleTeacher, $this->schoolA);
        $this->cbtOperatorSchoolA = $this->makeUser('p111_cbtop_a', $roleCbtOp, $this->schoolA);
        $this->adminSchoolB = $this->makeUser('p111_admin_b', $roleAdminKur, $this->schoolB);
        $this->studentUser = $this->makeUser('p111_siswa', $roleStudent, $this->schoolA);

        Teacher::create([
            'school_id' => $this->schoolA,
            'user_id' => $this->teacherAUser->id,
            'nip' => '198701012012011001',
            'full_name' => 'Guru A Pemilik Ujian',
            'gender' => 'L',
        ]);
        Teacher::create([
            'school_id' => $this->schoolA,
            'user_id' => $this->teacherBUser->id,
            'nip' => '198701012012011002',
            'full_name' => 'Guru B Bukan Pemilik',
            'gender' => 'P',
        ]);

        $this->subjectA = Subject::create([
            'school_id' => $this->schoolA,
            'code' => 'MTK111',
            'name' => 'Matematika CBT',
            'subject_group' => 'wajib',
        ]);
        $this->subjectB = Subject::create([
            'school_id' => $this->schoolA,
            'code' => 'BIO111',
            'name' => 'Biologi CBT',
            'subject_group' => 'wajib',
        ]);

        $this->bankA = QuestionBank::create([
            'school_id' => $this->schoolA,
            'subject_id' => $this->subjectA->id,
            'owner_id' => $this->teacherAUser->id,
            'name' => 'Bank Soal Rahasia Guru A',
        ]);
        $bankB = QuestionBank::create([
            'school_id' => $this->schoolA,
            'subject_id' => $this->subjectB->id,
            'owner_id' => $this->teacherBUser->id,
            'name' => 'Bank Soal Guru B',
        ]);

        $this->questionA = Question::create([
            'bank_id' => $this->bankA->id,
            'question_type' => 'pg',
            'body' => ['text' => 'Berapa 2 + 2?'],
            'weight' => 1.0,
        ]);
        QuestionOption::create([
            'question_id' => $this->questionA->id,
            'label' => 'A',
            'body' => ['text' => '4'],
            'is_correct' => true,
        ]);

        $this->foreignQuestion = Question::create([
            'bank_id' => $bankB->id,
            'question_type' => 'pg',
            'body' => ['text' => 'Soal Biologi Asing'],
            'weight' => 1.0,
        ]);

        $this->examA = Exam::create([
            'school_id' => $this->schoolA,
            'subject_id' => $this->subjectA->id,
            'created_by' => $this->teacherAUser->id,
            'title' => 'Ujian Matematika Aktif Guru A',
            'exam_type' => 'uts',
            'duration_min' => 60,
            'start_at' => now()->subMinutes(10),
            'end_at' => now()->addHour(),
            'status' => 'berlangsung',
        ]);

        $this->draftExamA = Exam::create([
            'school_id' => $this->schoolA,
            'subject_id' => $this->subjectA->id,
            'created_by' => $this->teacherAUser->id,
            'title' => 'Ujian Draft Rahasia',
            'exam_type' => 'uas',
            'duration_min' => 90,
            'start_at' => now()->addDays(5),
            'end_at' => now()->addDays(5)->addHours(2),
            'status' => 'draft',
        ]);

        ExamQuestion::create([
            'exam_id' => $this->examA->id,
            'question_id' => $this->questionA->id,
            'order_no' => 1,
        ]);

        $room1 = Room::create(['school_id' => $this->schoolA, 'code' => 'LAB-1', 'name' => 'Lab Komputer 1', 'room_type' => 'lab', 'capacity' => 30]);
        $room2 = Room::create(['school_id' => $this->schoolA, 'code' => 'LAB-2', 'name' => 'Lab Komputer 2', 'room_type' => 'lab', 'capacity' => 30]);

        $this->examRoom1 = ExamRoom::create([
            'exam_id' => $this->examA->id,
            'room_id' => $room1->id,
            'proctor_id' => $this->proctorRoom1User->id,
            'session_no' => 1,
            'token' => 'TOK111',
            'token_expires_at' => now()->addHour(),
        ]);

        $this->examRoom2 = ExamRoom::create([
            'exam_id' => $this->examA->id,
            'room_id' => $room2->id,
            'proctor_id' => $this->teacherAUser->id,
            'session_no' => 2,
            'token' => 'TOK222',
            'token_expires_at' => now()->addHour(),
        ]);

        $this->student1 = Student::create([
            'school_id' => $this->schoolA,
            'user_id' => $this->studentUser->id,
            'nis' => '202611101',
            'nisn' => '0011100001',
            'nik_enc' => '3573010101121101',
            'full_name' => 'Siswa Peserta Ruang 1',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-05-01',
            'status' => 'aktif',
            'entry_date' => '2026-07-01',
        ]);
        $this->student2 = Student::create([
            'school_id' => $this->schoolA,
            'nis' => '202611102',
            'nisn' => '0011100002',
            'nik_enc' => '3573010101121102',
            'full_name' => 'Siswa Peserta Ruang 2',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2012-06-01',
            'status' => 'aktif',
            'entry_date' => '2026-07-01',
        ]);
        $this->unenrolledStudent = Student::create([
            'school_id' => $this->schoolA,
            'nis' => '202611103',
            'nisn' => '0011100003',
            'nik_enc' => '3573010101121103',
            'full_name' => 'Siswa Bukan Peserta',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-07-01',
            'status' => 'aktif',
            'entry_date' => '2026-07-01',
        ]);

        $this->participant1 = ExamParticipant::create([
            'exam_id' => $this->examA->id,
            'student_id' => $this->student1->id,
            'exam_room_id' => $this->examRoom1->id,
        ]);
        $this->participant2 = ExamParticipant::create([
            'exam_id' => $this->examA->id,
            'student_id' => $this->student2->id,
            'exam_room_id' => $this->examRoom2->id,
        ]);

        $this->sessionRoom2 = ExamSession::create([
            'exam_id' => $this->examA->id,
            'student_id' => $this->student2->id,
            'status' => 'mengerjakan',
            'started_at' => now()->subMinutes(5),
            'deadline_at' => now()->addMinutes(55),
            'ends_at' => now()->addMinutes(55),
            'seed' => 'seed-r2',
        ]);
    }

    protected function makeUser(string $username, Role $role, string $schoolId): User
    {
        $user = User::create([
            'school_id' => $schoolId,
            'username' => $username,
            'email' => "{$username}@sekolah.sch.id",
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_p1_11_non_owner_teacher_with_cbt_exam_manage_cannot_access_other_teachers_exam_secrets(): void
    {
        // 1. GET /exams/{id} oleh Guru B: tidak boleh memuat soal, peserta, atau token ruang
        $showResp = $this->actingAs($this->teacherBUser, 'sanctum')
            ->getJson("/api/v1/exams/{$this->examA->id}")
            ->assertStatus(200);
        $this->assertStringNotContainsString('TOK111', $showResp->getContent());
        $this->assertStringNotContainsString('Berapa 2 + 2?', $showResp->getContent());
        $this->assertNull($showResp->json('data.questions'));
        $this->assertNull($showResp->json('data.participants'));

        // 2. Seluruh endpoint manajemen & laporan ujian Guru A -> 403 bagi Guru B
        $this->actingAs($this->teacherBUser, 'sanctum')->getJson("/api/v1/exams/{$this->examA->id}/questions")->assertStatus(403);
        $this->actingAs($this->teacherBUser, 'sanctum')->getJson("/api/v1/exams/{$this->examA->id}/rooms")->assertStatus(403);
        $this->actingAs($this->teacherBUser, 'sanctum')->getJson("/api/v1/exams/{$this->examA->id}/participants")->assertStatus(403);
        $this->actingAs($this->teacherBUser, 'sanctum')->getJson("/api/v1/exams/{$this->examA->id}/participant-cards")->assertStatus(403);
        $this->actingAs($this->teacherBUser, 'sanctum')->postJson("/api/v1/exams/{$this->examA->id}/distribute-participants", ['mode' => 'balanced'])->assertStatus(403);
        $this->actingAs($this->teacherBUser, 'sanctum')->getJson("/api/v1/exams/{$this->examA->id}/participants/{$this->participant1->id}/card-pdf")->assertStatus(403);
        $this->actingAs($this->teacherBUser, 'sanctum')->postJson("/api/v1/exam-rooms/{$this->examRoom1->id}/regenerate-token")->assertStatus(403);
        $this->actingAs($this->teacherBUser, 'sanctum')->getJson("/api/v1/exams/{$this->examA->id}/monitor")->assertStatus(403);
        $this->actingAs($this->teacherBUser, 'sanctum')->getJson("/api/v1/exams/{$this->examA->id}/anomalies")->assertStatus(403);
        $this->actingAs($this->teacherBUser, 'sanctum')->getJson("/api/v1/exams/{$this->examA->id}/report")->assertStatus(403);
        $this->actingAs($this->teacherBUser, 'sanctum')->postJson('/api/v1/cbt/sync/pull', [
            'exam_id' => $this->examA->id,
            'node_id' => 'node-unauthorized',
        ])->assertStatus(403);
        $this->actingAs($this->teacherBUser, 'sanctum')->getJson("/api/v1/question-banks/{$this->bankA->id}")->assertStatus(403);

        // 3. Pemilik ujian (Guru A) & Operator CBT sekolah yang sama tetap 200 OK
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->teacherAUser, 'sanctum')->getJson("/api/v1/exams/{$this->examA->id}/questions")->assertStatus(200);
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->cbtOperatorSchoolA, 'sanctum')->getJson("/api/v1/exams/{$this->examA->id}/questions")->assertStatus(200);

        // 4. Isolasi lintas sekolah: Admin Sekolah B tidak bisa mengakses ujian Sekolah A
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->adminSchoolB, 'sanctum')->getJson("/api/v1/exams/{$this->examA->id}/questions")->assertStatus(403);
        $this->actingAs($this->adminSchoolB, 'sanctum')->getJson("/api/v1/exams/{$this->examA->id}")->assertStatus(403);
    }

    public function test_p1_11_proctor_with_cbt_exam_manage_remains_isolated_to_assigned_room(): void
    {
        // Proctor Ruang 1 boleh monitor Ruang 1 & regenerate token Ruang 1
        $this->actingAs($this->proctorRoom1User, 'sanctum')
            ->getJson("/api/v1/exams/{$this->examA->id}/monitor?room_id={$this->examRoom1->id}")
            ->assertStatus(200);

        $this->actingAs($this->proctorRoom1User, 'sanctum')
            ->postJson("/api/v1/exam-rooms/{$this->examRoom1->id}/regenerate-token")
            ->assertStatus(200);

        // Proctor Ruang 1 TIDAK boleh monitor/regenerate/manage Ruang 2
        $this->actingAs($this->proctorRoom1User, 'sanctum')
            ->getJson("/api/v1/exams/{$this->examA->id}/monitor?room_id={$this->examRoom2->id}")
            ->assertStatus(403);

        $this->actingAs($this->proctorRoom1User, 'sanctum')
            ->postJson("/api/v1/exam-rooms/{$this->examRoom2->id}/regenerate-token")
            ->assertStatus(403);

        $this->actingAs($this->proctorRoom1User, 'sanctum')
            ->postJson("/api/v1/exam-sessions/{$this->sessionRoom2->id}/manage", [
                'action' => 'force_submit',
                'reason' => 'Coba lintas ruang',
            ])
            ->assertStatus(403);
    }

    public function test_p1_11_student_cannot_bypass_draft_filter_and_sync_push_validates_participants_and_questions(): void
    {
        // 1. Siswa mengirim ?status=draft tidak boleh melihat ujian draft
        $listResp = $this->actingAs($this->studentUser, 'sanctum')
            ->getJson('/api/v1/exams?status=draft')
            ->assertStatus(200);
        $this->assertCount(0, $listResp->json('data'));

        // 2. Push sync dengan student_id bukan peserta ujian ditolak 422
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->teacherAUser, 'sanctum')
            ->postJson('/api/v1/cbt/sync/push', [
                'exam_id' => $this->examA->id,
                'node_id' => 'node-lab-1',
                'sessions' => [
                    [
                        'student_id' => $this->unenrolledStudent->id,
                        'answers' => [['question_id' => $this->questionA->id, 'answer' => 'A']],
                    ],
                ],
            ])
            ->assertStatus(422);

        // 3. Push sync dengan question_id asing (bukan milik ujian) ditolak 422
        $this->actingAs($this->teacherAUser, 'sanctum')
            ->postJson('/api/v1/cbt/sync/push', [
                'exam_id' => $this->examA->id,
                'node_id' => 'node-lab-1',
                'sessions' => [
                    [
                        'student_id' => $this->student1->id,
                        'answers' => [['question_id' => $this->foreignQuestion->id, 'answer' => 'A']],
                    ],
                ],
            ])
            ->assertStatus(422);

        // 4. Push sync sah mengisi deadline_at dan ends_at konsisten
        $this->actingAs($this->teacherAUser, 'sanctum')
            ->postJson('/api/v1/cbt/sync/push', [
                'exam_id' => $this->examA->id,
                'node_id' => 'node-lab-1',
                'sessions' => [
                    [
                        'student_id' => $this->student1->id,
                        'answers' => [['question_id' => $this->questionA->id, 'answer' => 'A']],
                    ],
                ],
            ])
            ->assertStatus(200);

        $syncedSession = ExamSession::where('exam_id', $this->examA->id)
            ->where('student_id', $this->student1->id)
            ->firstOrFail();
        $this->assertNotNull($syncedSession->ends_at);
        $this->assertEquals($syncedSession->deadline_at?->toIso8601String(), $syncedSession->ends_at?->toIso8601String());
    }
}
