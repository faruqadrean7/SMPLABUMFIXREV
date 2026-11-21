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
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExamLocalSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $otherUser;

    protected Student $student1;

    protected Student $student2;

    protected Exam $exam;

    protected ExamRoom $examRoom;

    protected Question $question1;

    protected Question $question2;

    protected string $syncToken = 'SECRET_NODE_TOKEN_XYZ';

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'admin'], ['name' => 'Admin', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $permManage = Permission::firstOrCreate(['code' => 'cbt.exam.manage'], ['description' => 'Kelola Ujian']);
        $roleAdmin->permissions()->syncWithoutDetaching([$permManage->id]);

        $this->adminUser = User::create([
            'username' => 'admin_cbt_sync',
            'email' => 'admin_sync@test.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->otherUser = User::create([
            'username' => 'other_sync',
            'email' => 'other_sync@test.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $subject = Subject::create([
            'code' => 'TIK10',
            'name' => 'Informatika Digital',
            'subject_group' => 'muatan_nasional',
        ]);

        $room = Room::create([
            'code' => 'LAB-TIK-1',
            'name' => 'Laboratorium TIK 1',
            'room_type' => 'lab',
            'capacity' => 30,
        ]);

        $bank = QuestionBank::create([
            'subject_id' => $subject->id,
            'owner_id' => $this->adminUser->id,
            'name' => 'Bank Informatika Offline Sync',
        ]);

        $this->question1 = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'pg',
            'body' => ['text' => 'Protokol pengiriman email standar adalah?'],
            'weight' => 1.0,
            'status' => 'aktif',
        ]);
        QuestionOption::create(['question_id' => $this->question1->id, 'label' => 'A', 'body' => ['text' => 'SMTP'], 'is_correct' => true]);
        QuestionOption::create(['question_id' => $this->question1->id, 'label' => 'B', 'body' => ['text' => 'POP3'], 'is_correct' => false]);

        $this->question2 = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'pg',
            'body' => ['text' => 'Singkatan dari URL adalah?'],
            'weight' => 1.0,
            'status' => 'aktif',
        ]);
        QuestionOption::create(['question_id' => $this->question2->id, 'label' => 'A', 'body' => ['text' => 'Uniform Resource Locator'], 'is_correct' => true]);

        $this->exam = Exam::create([
            'subject_id' => $subject->id,
            'created_by' => $this->adminUser->id,
            'title' => 'Ujian Akhir Semester Informatika',
            'exam_type' => 'SAS',
            'duration_min' => 90,
            'start_at' => Carbon::now()->subHour(),
            'end_at' => Carbon::now()->addHours(2),
            'passing_grade' => 75.0,
            'status' => 'berlangsung',
            'sync_node_token' => $this->syncToken,
        ]);

        ExamQuestion::create(['exam_id' => $this->exam->id, 'question_id' => $this->question1->id, 'sort_order' => 1]);
        ExamQuestion::create(['exam_id' => $this->exam->id, 'question_id' => $this->question2->id, 'sort_order' => 2]);

        $this->examRoom = ExamRoom::create([
            'exam_id' => $this->exam->id,
            'room_id' => $room->id,
            'proctor_id' => $this->adminUser->id,
            'session_no' => 1,
            'token' => 'TIK001',
            'token_expires_at' => Carbon::now()->addHours(2),
        ]);

        $u1 = User::create(['username' => 's1_sync', 'email' => 's1_sync@test.sch.id', 'password_hash' => Hash::make('password123'), 'status' => 'active']);
        $u1->roles()->attach($roleStudent->id);
        $this->student1 = Student::create([
            'user_id' => $u1->id,
            'full_name' => 'Fajar Sidik',
            'nis' => '4001',
            'nisn' => '0040010001',
            'nik_enc' => 'enc_4001',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);
        ExamParticipant::create(['exam_id' => $this->exam->id, 'student_id' => $this->student1->id, 'exam_room_id' => $this->examRoom->id]);

        $u2 = User::create(['username' => 's2_sync', 'email' => 's2_sync@test.sch.id', 'password_hash' => Hash::make('password123'), 'status' => 'active']);
        $u2->roles()->attach($roleStudent->id);
        $this->student2 = Student::create([
            'user_id' => $u2->id,
            'full_name' => 'Gita Gutawa',
            'nis' => '4002',
            'nisn' => '0040010002',
            'nik_enc' => 'enc_4002',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2010-02-02',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);
        ExamParticipant::create(['exam_id' => $this->exam->id, 'student_id' => $this->student2->id, 'exam_room_id' => $this->examRoom->id]);
    }

    public function test_be_cbt_11_admin_or_node_can_pull_exam_package_successfully(): void
    {
        // Pull menggunakan sync_token node lokal
        $response = $this->postJson('/api/v1/cbt/sync/pull', [
            'exam_id' => $this->exam->id,
            'node_id' => 'NODE-LAB-TIK-01',
            'sync_token' => $this->syncToken,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'success')
            ->assertJsonPath('data.node_id', 'NODE-LAB-TIK-01')
            ->assertJsonPath('data.exam.id', $this->exam->id);

        $this->assertNotEmpty($response->json('data.package_hash'));

        $questions = $response->json('data.questions');
        $this->assertCount(2, $questions);

        $participants = $response->json('data.participants');
        $this->assertCount(2, $participants);
        $this->assertEquals('Fajar Sidik', $participants[0]['student_name']);
        $this->assertNotEmpty($participants[0]['seed']);

        // Verifikasi log sinkronisasi tercatat
        $this->assertDatabaseHas('cbt_sync_logs', [
            'exam_id' => $this->exam->id,
            'node_id' => 'NODE-LAB-TIK-01',
            'sync_type' => 'pull',
            'status' => 'success',
        ]);
    }

    public function test_be_cbt_11_push_syncs_offline_sessions_and_answers_into_central(): void
    {
        $payload = [
            'exam_id' => $this->exam->id,
            'node_id' => 'NODE-LAB-TIK-01',
            'sync_token' => $this->syncToken,
            'sessions' => [
                [
                    'student_id' => $this->student1->id,
                    'started_at' => Carbon::now()->subMinutes(60)->toDateTimeString(),
                    'submitted_at' => Carbon::now()->subMinutes(10)->toDateTimeString(),
                    'seed' => 'seed_off_1',
                    'answers' => [
                        [
                            'question_id' => $this->question1->id,
                            'answer' => 'A',
                            'answered_at' => Carbon::now()->subMinutes(45)->toDateTimeString(),
                        ],
                        [
                            'question_id' => $this->question2->id,
                            'answer' => 'A',
                            'answered_at' => Carbon::now()->subMinutes(20)->toDateTimeString(),
                        ],
                    ],
                    'violations' => [
                        [
                            'violation_type' => 'blur',
                            'meta' => ['count' => 1],
                            'occurred_at' => Carbon::now()->subMinutes(30)->toDateTimeString(),
                        ],
                    ],
                ],
                [
                    'student_id' => $this->student2->id,
                    'started_at' => Carbon::now()->subMinutes(50)->toDateTimeString(),
                    'submitted_at' => Carbon::now()->subMinutes(5)->toDateTimeString(),
                    'seed' => 'seed_off_2',
                    'answers' => [
                        [
                            'question_id' => $this->question1->id,
                            'answer' => 'B',
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/cbt/sync/push', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'success')
            ->assertJsonPath('data.processed_sessions', 2)
            ->assertJsonPath('data.processed_answers', 3)
            ->assertJsonPath('data.conflicts', 0);

        // Verifikasi sesi siswa 1 terbuat di server pusat
        $this->assertDatabaseHas('exam_sessions', [
            'exam_id' => $this->exam->id,
            'student_id' => $this->student1->id,
            'status' => 'selesai',
        ]);

        // Verifikasi jawaban siswa 1 tersimpan
        $session1 = ExamSession::where('exam_id', $this->exam->id)->where('student_id', $this->student1->id)->first();
        $this->assertNotNull($session1);
        $this->assertDatabaseHas('exam_answers', [
            'session_id' => $session1->id,
            'question_id' => $this->question1->id,
            'answer' => json_encode('A'),
        ]);

        // Verifikasi log pelanggaran offline tercatat
        $this->assertDatabaseHas('exam_violations', [
            'session_id' => $session1->id,
            'violation_type' => 'blur',
        ]);

        // Verifikasi log sinkronisasi push tersimpan
        $this->assertDatabaseHas('cbt_sync_logs', [
            'exam_id' => $this->exam->id,
            'node_id' => 'NODE-LAB-TIK-01',
            'sync_type' => 'push',
            'records_count' => 2,
            'status' => 'success',
        ]);
    }

    public function test_be_cbt_11_push_is_idempotent_and_handles_duplicate_submissions(): void
    {
        $payload = [
            'exam_id' => $this->exam->id,
            'node_id' => 'NODE-LAB-TIK-01',
            'sync_token' => $this->syncToken,
            'sessions' => [
                [
                    'student_id' => $this->student1->id,
                    'started_at' => Carbon::now()->subMinutes(60)->toDateTimeString(),
                    'submitted_at' => Carbon::now()->subMinutes(10)->toDateTimeString(),
                    'answers' => [
                        [
                            'question_id' => $this->question1->id,
                            'answer' => 'A',
                        ],
                    ],
                ],
            ],
        ];

        // Push pertama
        $response1 = $this->postJson('/api/v1/cbt/sync/push', $payload);
        $response1->assertStatus(200);

        // Push kedua dengan payload yang sama (harus idempoten tanpa error)
        $response2 = $this->postJson('/api/v1/cbt/sync/push', $payload);
        $response2->assertStatus(200)
            ->assertJsonPath('data.status', 'success')
            ->assertJsonPath('data.processed_sessions', 1);

        // Jumlah sesi siswa tetap 1 (tidak duplikat)
        $sessionCount = ExamSession::where('exam_id', $this->exam->id)->where('student_id', $this->student1->id)->count();
        $this->assertEquals(1, $sessionCount);
    }

    public function test_be_cbt_11_pull_fails_with_invalid_token(): void
    {
        $response = $this->postJson('/api/v1/cbt/sync/pull', [
            'exam_id' => $this->exam->id,
            'node_id' => 'NODE-LAB-TIK-01',
            'sync_token' => 'SALAH_TOKEN_INVALID',
        ]);

        $response->assertStatus(403);
    }

    public function test_be_cbt_11_pull_fails_with_unauthorized_user(): void
    {
        $response = $this->actingAs($this->otherUser, 'sanctum')
            ->postJson('/api/v1/cbt/sync/pull', [
                'exam_id' => $this->exam->id,
                'node_id' => 'NODE-LAB-TIK-01',
            ]);

        $response->assertStatus(403);
    }
}
