<?php

namespace Tests\Feature\Cbt;

use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\ExamQuestion;
use App\Models\ExamRoom;
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

class ExamSyncSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected Exam $exam;

    protected ExamRoom $examRoom;

    protected Student $student;

    protected string $syncToken = 'SECURE_NODE_SYNC_TOKEN_2026';

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::flush();

        $roleAdmin = Role::firstOrCreate(['code' => 'admin'], ['name' => 'Admin', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $this->adminUser = User::create([
            'username' => 'admin_sync_sec',
            'email' => 'admin_sync_sec@test.sch.id',
            'password_hash' => Hash::make('secret123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $subject = Subject::create([
            'code' => 'TIK-SYNC',
            'name' => 'Teknologi Informasi',
            'subject_group' => 'muatan_nasional',
        ]);

        $room = Room::create([
            'code' => 'LAB-SYNC',
            'name' => 'Lab Komputer Sync',
            'room_type' => 'lab',
            'capacity' => 30,
        ]);

        $bank = QuestionBank::create([
            'subject_id' => $subject->id,
            'owner_id' => $this->adminUser->id,
            'name' => 'Bank Soal Sync Security',
        ]);

        $q = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'pg',
            'body' => ['text' => 'Apa itu IP address?'],
            'weight' => 1.0,
            'status' => 'aktif',
        ]);
        QuestionOption::create(['question_id' => $q->id, 'label' => 'A', 'body' => ['text' => 'Protokol internet'], 'is_correct' => true]);

        $this->exam = Exam::create([
            'subject_id' => $subject->id,
            'created_by' => $this->adminUser->id,
            'title' => 'Ujian TIK Offline Sync',
            'exam_type' => 'UTS',
            'duration_min' => 60,
            'start_at' => Carbon::now()->subMinutes(10),
            'end_at' => Carbon::now()->addHours(2),
            'passing_grade' => 75.0,
            'status' => 'berlangsung',
            'sync_node_token' => $this->syncToken,
        ]);

        ExamQuestion::create(['exam_id' => $this->exam->id, 'question_id' => $q->id, 'sort_order' => 1]);

        $this->examRoom = ExamRoom::create([
            'exam_id' => $this->exam->id,
            'room_id' => $room->id,
            'proctor_id' => $this->adminUser->id,
            'session_no' => 1,
            'token' => 'TKNSYC',
            'token_expires_at' => Carbon::now()->addHours(2),
        ]);

        $userStudent = User::create([
            'username' => 'siswa_sync',
            'email' => 'siswa_sync@test.sch.id',
            'password_hash' => Hash::make('secret123'),
            'status' => 'active',
        ]);
        $userStudent->roles()->attach($roleStudent->id);

        $this->student = Student::create([
            'user_id' => $userStudent->id,
            'full_name' => 'Siswa Sync',
            'nis' => '12001',
            'nisn' => '0012001001',
            'nik_enc' => 'enc_nik_sync',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        ExamParticipant::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'exam_room_id' => $this->examRoom->id,
        ]);
    }

    public function test_p1_sec_authz_02_sync_pull_succeeds_with_valid_token_unauthenticated(): void
    {
        $response = $this->postJson('/api/v1/cbt/sync/pull', [
            'exam_id' => $this->exam->id,
            'node_id' => 'NODE-SERVER-01',
            'sync_token' => $this->syncToken,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.exam.id', $this->exam->id)
            ->assertJsonPath('data.exam.title', $this->exam->title);
    }

    public function test_p1_sec_authz_02_sync_pull_fails_with_invalid_token(): void
    {
        $response = $this->postJson('/api/v1/cbt/sync/pull', [
            'exam_id' => $this->exam->id,
            'node_id' => 'NODE-SERVER-01',
            'sync_token' => 'WRONG_INVALID_TOKEN',
        ]);

        $response->assertStatus(403);
    }

    public function test_p1_sec_authz_02_sync_pull_fails_without_token_or_auth(): void
    {
        $response = $this->postJson('/api/v1/cbt/sync/pull', [
            'exam_id' => $this->exam->id,
            'node_id' => 'NODE-SERVER-01',
        ]);

        $response->assertStatus(403);
    }

    public function test_p1_sec_authz_02_sync_push_validates_payload(): void
    {
        // Missing sessions array
        $response = $this->postJson('/api/v1/cbt/sync/push', [
            'exam_id' => $this->exam->id,
            'node_id' => 'NODE-SERVER-01',
            'sync_token' => $this->syncToken,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sessions']);
    }

    public function test_p1_sec_authz_02_sync_pull_rate_limiting_throttles_excessive_calls(): void
    {
        // 15 requests are allowed per minute. The 16th should return 429 Too Many Requests.
        for ($i = 0; $i < 15; $i++) {
            $res = $this->postJson('/api/v1/cbt/sync/pull', [
                'exam_id' => $this->exam->id,
                'node_id' => 'NODE-SERVER-01',
                'sync_token' => $this->syncToken,
            ]);
            $res->assertOk();
        }

        // 16th request must be throttled
        $throttled = $this->postJson('/api/v1/cbt/sync/pull', [
            'exam_id' => $this->exam->id,
            'node_id' => 'NODE-SERVER-01',
            'sync_token' => $this->syncToken,
        ]);

        $throttled->assertStatus(429);
    }
}
