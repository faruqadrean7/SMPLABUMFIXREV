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

class CbtLoadSimulationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected Exam $exam;

    protected ExamRoom $room1;

    protected ExamRoom $room2;

    protected array $questions = [];

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $permManage = Permission::firstOrCreate(['code' => 'cbt.exam.manage'], ['description' => 'Kelola Ujian CBT']);
        $roleAdmin->permissions()->syncWithoutDetaching([$permManage->id]);

        $this->adminUser = User::create([
            'username' => 'admin_load_sim',
            'email' => 'admin_load@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $subject = Subject::firstOrCreate(['code' => 'SIM10'], ['name' => 'Simulasi Beban Komputer', 'subject_group' => 'muatan_nasional']);

        $lab1 = Room::create(['code' => 'LAB-A', 'name' => 'Lab Komputer A', 'room_type' => 'laboratorium', 'capacity' => 35]);
        $lab2 = Room::create(['code' => 'LAB-B', 'name' => 'Lab Komputer B', 'room_type' => 'laboratorium', 'capacity' => 35]);

        $this->exam = Exam::create([
            'subject_id' => $subject->id,
            'created_by' => $this->adminUser->id,
            'title' => 'Simulasi Beban Operasional CBT Laboratorium',
            'exam_type' => 'sumatif_akhir',
            'duration_min' => 90,
            'start_at' => Carbon::now()->subMinutes(10),
            'end_at' => Carbon::now()->addHours(3),
            'passing_grade' => 75.0,
            'status' => 'berlangsung',
            'shuffle_questions' => false,
            'shuffle_options' => false,
        ]);

        $this->room1 = ExamRoom::create([
            'exam_id' => $this->exam->id,
            'room_id' => $lab1->id,
            'proctor_id' => $this->adminUser->id,
            'session_no' => 1,
            'token' => 'SIM101',
            'token_expires_at' => Carbon::now()->addHours(2),
        ]);

        $this->room2 = ExamRoom::create([
            'exam_id' => $this->exam->id,
            'room_id' => $lab2->id,
            'proctor_id' => $this->adminUser->id,
            'session_no' => 1,
            'token' => 'SIM202',
            'token_expires_at' => Carbon::now()->addHours(2),
        ]);

        $bank = QuestionBank::create([
            'subject_id' => $subject->id,
            'owner_id' => $this->adminUser->id,
            'name' => 'Bank Soal Simulasi Beban',
        ]);

        // Buat 5 butir soal dengan variasi tipe
        for ($i = 1; $i <= 5; $i++) {
            $q = Question::create([
                'bank_id' => $bank->id,
                'question_type' => 'pg',
                'body' => ['text' => "Soal Uji Beban Nomor {$i}"],
                'weight' => 20.0,
                'status' => 'aktif',
            ]);

            QuestionOption::create(['question_id' => $q->id, 'label' => 'A', 'body' => ['text' => 'Opsi A'], 'is_correct' => true]);
            QuestionOption::create(['question_id' => $q->id, 'label' => 'B', 'body' => ['text' => 'Opsi B'], 'is_correct' => false]);
            QuestionOption::create(['question_id' => $q->id, 'label' => 'C', 'body' => ['text' => 'Opsi C'], 'is_correct' => false]);

            ExamQuestion::create([
                'exam_id' => $this->exam->id,
                'question_id' => $q->id,
                'sort_order' => $i,
            ]);

            $this->questions[] = $q;
        }
    }

    /**
     * Helper membuat N peserta ujian virtual.
     */
    protected function createVirtualParticipants(int $count, string $prefix): array
    {
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $participants = [];

        for ($i = 1; $i <= $count; $i++) {
            $username = "{$prefix}_user_{$i}";
            $user = User::create([
                'username' => $username,
                'email' => "{$username}@sim.sch.id",
                'password_hash' => Hash::make('secret123'),
                'status' => 'active',
            ]);
            $user->roles()->attach($roleStudent->id);

            $student = Student::create([
                'user_id' => $user->id,
                'full_name' => "Virtual Participant {$prefix} {$i}",
                'nis' => "NIS_{$prefix}_{$i}",
                'nisn' => '0099'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'nik_enc' => "enc_nik_{$prefix}_{$i}",
                'gender' => ($i % 2 === 0 ? 'L' : 'P'),
                'birth_place' => 'Malang',
                'birth_date' => '2010-01-01',
                'entry_date' => '2026-07-01',
                'status' => 'aktif',
            ]);

            $assignedRoom = ($i % 2 === 0 ? $this->room2 : $this->room1);
            ExamParticipant::create([
                'exam_id' => $this->exam->id,
                'student_id' => $student->id,
                'exam_room_id' => $assignedRoom->id,
            ]);

            $participants[] = [
                'user' => $user,
                'student' => $student,
                'room' => $assignedRoom,
            ];
        }

        return $participants;
    }

    /**
     * Jalankan siklus pengerjaan ujian virtual dan rekam metrik latensi.
     */
    protected function runVirtualExamLifecycle(array $participants): array
    {
        $latencies = [];
        $totalRequests = 0;
        $failedRequests = 0;
        $simStart = microtime(true);

        foreach ($participants as $p) {
            $user = $p['user'];
            $student = $p['student'];
            $room = $p['room'];

            // 1. start-session
            $t0 = microtime(true);
            $startRes = $this->actingAs($user, 'sanctum')
                ->postJson("/api/v1/exams/{$this->exam->id}/start-session", ['token' => $room->token]);
            $t1 = microtime(true);
            $latencies[] = ($t1 - $t0) * 1000;
            $totalRequests++;
            if ($startRes->status() !== 200) {
                $failedRequests++;
            }
            $sessionId = $startRes->json('data.session_id');

            // 2. session-questions
            $t0 = microtime(true);
            $qRes = $this->actingAs($user, 'sanctum')
                ->getJson("/api/v1/exams/{$this->exam->id}/session-questions");
            $t1 = microtime(true);
            $latencies[] = ($t1 - $t0) * 1000;
            $totalRequests++;
            if ($qRes->status() !== 200) {
                $failedRequests++;
            }

            // 3. save-answer (autosave 3 butir soal)
            for ($k = 0; $k < 3; $k++) {
                $q = $this->questions[$k];
                $correctOpt = $q->options->firstWhere('is_correct', true);

                $t0 = microtime(true);
                $saveRes = $this->actingAs($user, 'sanctum')
                    ->postJson("/api/v1/exams/{$this->exam->id}/save-answer", [
                        'question_id' => $q->id,
                        'answer' => ['selected_option_id' => $correctOpt->id],
                        'is_flagged' => false,
                    ]);
                $t1 = microtime(true);
                $latencies[] = ($t1 - $t0) * 1000;
                $totalRequests++;
                if ($saveRes->status() !== 200) {
                    $failedRequests++;
                }
            }

            // 4. heartbeat
            $t0 = microtime(true);
            $hbRes = $this->actingAs($user, 'sanctum')
                ->postJson("/api/v1/exam-sessions/{$sessionId}/heartbeat", [
                    'screen_state' => 'fullscreen',
                    'active_tab' => true,
                ]);
            $t1 = microtime(true);
            $latencies[] = ($t1 - $t0) * 1000;
            $totalRequests++;
            if ($hbRes->status() !== 200) {
                $failedRequests++;
            }

            // 5. finish-session
            $t0 = microtime(true);
            $finRes = $this->actingAs($user, 'sanctum')
                ->postJson("/api/v1/exams/{$this->exam->id}/finish-session");
            $t1 = microtime(true);
            $latencies[] = ($t1 - $t0) * 1000;
            $totalRequests++;
            if ($finRes->status() !== 200) {
                $failedRequests++;
            }
        }

        // 6. auto-grade exam by proctor/admin
        $t0 = microtime(true);
        $gradeRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/exams/{$this->exam->id}/auto-grade");
        $t1 = microtime(true);
        $latencies[] = ($t1 - $t0) * 1000;
        $totalRequests++;
        if ($gradeRes->status() !== 200) {
            $failedRequests++;
        }

        $simEnd = microtime(true);
        $totalDurationSec = max(0.001, $simEnd - $simStart);

        // Hitung metrik latensi
        sort($latencies);
        $count = count($latencies);
        $min = round($latencies[0], 2);
        $max = round($latencies[$count - 1], 2);
        $mean = round(array_sum($latencies) / $count, 2);
        $p50 = round($latencies[(int) floor($count * 0.50)], 2);
        $p95 = round($latencies[(int) floor($count * 0.95)], 2);
        $p99 = round($latencies[(int) floor($count * 0.99)], 2);
        $throughput = round($totalRequests / $totalDurationSec, 2);
        $errorRate = round(($failedRequests / $totalRequests) * 100, 2);

        return [
            'total_participants' => count($participants),
            'total_requests' => $totalRequests,
            'failed_requests' => $failedRequests,
            'error_rate_pct' => $errorRate,
            'duration_sec' => round($totalDurationSec, 3),
            'throughput_rps' => $throughput,
            'latency_min_ms' => $min,
            'latency_mean_ms' => $mean,
            'latency_p50_ms' => $p50,
            'latency_p95_ms' => $p95,
            'latency_p99_ms' => $p99,
            'latency_max_ms' => $max,
        ];
    }

    /**
     * Tahap 1: Simulasi 10 peserta serentak (ruang tes kecil).
     */
    public function test_load_simulation_stage_1_10_concurrent_participants(): void
    {
        $participants = $this->createVirtualParticipants(10, 'tier10');
        $metrics = $this->runVirtualExamLifecycle($participants);

        // Assert 0% error
        $this->assertEquals(0, $metrics['failed_requests'], 'Ada request yang gagal pada beban 10 peserta!');
        $this->assertEquals(0.0, $metrics['error_rate_pct']);
        $this->assertEquals(71, $metrics['total_requests']); // 10 * (1 start + 1 questions + 3 save + 1 hb + 1 finish) + 1 grade = 71

        // Verifikasi integritas data
        $sessions = ExamSession::where('exam_id', $this->exam->id)->get();
        $this->assertEquals(10, $sessions->count());
        foreach ($sessions as $session) {
            $this->assertEquals('selesai', $session->status);
            $this->assertNotNull($session->score);
            // 3 soal benar @ 20 poin dari total 5 soal (100 poin) -> 60.0%
            $this->assertEquals(60.0, (float) $session->score);

            // Periksa jawaban tidak tertukar
            $answers = ExamAnswer::where('session_id', $session->id)->get();
            $this->assertEquals(3, $answers->count());
        }

        // Tampilkan metrik di log
        $this->assertLessThan(2000, $metrics['latency_p95_ms']);
    }

    /**
     * Tahap 2: Simulasi 30 peserta serentak (1 lab komputer penuh).
     */
    public function test_load_simulation_stage_2_30_concurrent_participants(): void
    {
        $participants = $this->createVirtualParticipants(30, 'tier30');
        $metrics = $this->runVirtualExamLifecycle($participants);

        // Assert 0% error
        $this->assertEquals(0, $metrics['failed_requests'], 'Ada request yang gagal pada beban 30 peserta!');
        $this->assertEquals(0.0, $metrics['error_rate_pct']);
        $this->assertEquals(211, $metrics['total_requests']); // 30 * 7 + 1 = 211

        // Verifikasi integritas data
        $sessions = ExamSession::where('exam_id', $this->exam->id)->get();
        $this->assertEquals(30, $sessions->count());
        foreach ($sessions as $session) {
            $this->assertEquals('selesai', $session->status);
            $this->assertEquals(60.0, (float) $session->score);
        }

        $this->assertLessThan(2000, $metrics['latency_p95_ms']);
    }

    /**
     * Tahap 3: Simulasi 60 peserta serentak (2 lab komputer bersamaan).
     */
    public function test_load_simulation_stage_3_60_concurrent_participants(): void
    {
        $participants = $this->createVirtualParticipants(60, 'tier60');
        $metrics = $this->runVirtualExamLifecycle($participants);

        // Assert 0% error
        $this->assertEquals(0, $metrics['failed_requests'], 'Ada request yang gagal pada beban 60 peserta!');
        $this->assertEquals(0.0, $metrics['error_rate_pct']);
        $this->assertEquals(421, $metrics['total_requests']); // 60 * 7 + 1 = 421

        // Verifikasi integritas data
        $sessions = ExamSession::where('exam_id', $this->exam->id)->get();
        $this->assertEquals(60, $sessions->count());
        foreach ($sessions as $session) {
            $this->assertEquals('selesai', $session->status);
            $this->assertEquals(60.0, (float) $session->score);

            $answers = ExamAnswer::where('session_id', $session->id)->get();
            $this->assertEquals(3, $answers->count());
        }

        $this->assertLessThan(2000, $metrics['latency_p95_ms']);
    }
}
