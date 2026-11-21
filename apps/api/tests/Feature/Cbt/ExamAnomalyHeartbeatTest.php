<?php

namespace Tests\Feature\Cbt;

use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\ExamRoom;
use App\Models\ExamSession;
use App\Models\ExamViolation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExamAnomalyHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $proctorUser;

    protected User $studentUser;

    protected User $otherStudentUser;

    protected Student $student;

    protected Exam $exam;

    protected ExamRoom $examRoom;

    protected ExamSession $session;

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
            'username' => 'admin_cbt_ano',
            'email' => 'admin_cbt_ano@test.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->proctorUser = User::create([
            'username' => 'proctor_ano',
            'email' => 'proctor_ano@test.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->proctorUser->roles()->attach($roleTeacher->id);

        $this->studentUser = User::create([
            'username' => 'siswa_ano',
            'email' => 'siswa_ano@test.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($roleStudent->id);

        $this->otherStudentUser = User::create([
            'username' => 'siswa_other_ano',
            'email' => 'siswa_other_ano@test.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->otherStudentUser->roles()->attach($roleStudent->id);

        $this->student = Student::create([
            'user_id' => $this->studentUser->id,
            'full_name' => 'Doni Firmansyah',
            'nis' => '10021',
            'nisn' => '0010021001',
            'nik_enc' => 'enc_3578010101010021',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-04-04',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $subject = Subject::create([
            'code' => 'BIO10',
            'name' => 'Biologi Sel',
            'subject_group' => 'muatan_nasional',
        ]);

        $room = Room::create([
            'code' => 'LAB-BIO',
            'name' => 'Laboratorium Biologi',
            'room_type' => 'lab',
            'capacity' => 30,
        ]);

        $this->exam = Exam::create([
            'subject_id' => $subject->id,
            'created_by' => $this->adminUser->id,
            'title' => 'Ujian Tengah Semester Biologi',
            'exam_type' => 'STS',
            'duration_min' => 60,
            'start_at' => Carbon::now()->subMinutes(10),
            'end_at' => Carbon::now()->addHours(1),
            'passing_grade' => 75.0,
            'status' => 'berlangsung',
        ]);

        $this->examRoom = ExamRoom::create([
            'exam_id' => $this->exam->id,
            'room_id' => $room->id,
            'proctor_id' => $this->proctorUser->id,
            'session_no' => 1,
            'token' => 'BIO001',
            'token_expires_at' => Carbon::now()->addHours(1),
        ]);

        ExamParticipant::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'exam_room_id' => $this->examRoom->id,
        ]);

        $this->session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'status' => 'mengerjakan',
            'started_at' => Carbon::now()->subMinutes(10),
            'deadline_at' => Carbon::now()->addMinutes(50),
            'seed' => 'seed_ano_123',
            'ip_address' => '192.168.1.50',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0',
            'last_heartbeat_at' => Carbon::now()->subSeconds(30),
        ]);
    }

    public function test_be_cbt_12_student_can_send_heartbeat_successfully(): void
    {
        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exam-sessions/{$this->session->id}/heartbeat", [
                'screen_state' => 'fullscreen',
                'active_tab' => true,
                'device_fingerprint' => 'fp_abc123',
            ], [
                'REMOTE_ADDR' => '192.168.1.50',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.session_id', $this->session->id)
            ->assertJsonPath('data.status', 'mengerjakan')
            ->assertJsonPath('data.anomalies_detected', [])
            ->assertJsonPath('data.blur_count', 0);

        $this->session->refresh();
        $this->assertNotNull($this->session->last_heartbeat_at);
        $this->assertEquals('fp_abc123', $this->session->device_fingerprint);
    }

    public function test_be_cbt_12_detects_ip_change_anomaly_without_blocking_session(): void
    {
        // IP berubah dari 192.168.1.50 ke 10.0.0.99
        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exam-sessions/{$this->session->id}/heartbeat", [
                'screen_state' => 'fullscreen',
                'active_tab' => true,
            ], [
                'REMOTE_ADDR' => '10.0.0.99',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.session_id', $this->session->id)
            ->assertJsonPath('data.status', 'mengerjakan'); // Sesi tetap aktif, tidak diblokir!

        $this->assertContains('anomaly_ip_change', $response->json('data.anomalies_detected'));

        // Cek anomali tersimpan di database
        $this->assertDatabaseHas('exam_violations', [
            'session_id' => $this->session->id,
            'violation_type' => 'anomaly_ip_change',
        ]);
    }

    public function test_be_cbt_12_detects_device_user_agent_change(): void
    {
        // User Agent berubah dari Chrome ke Firefox
        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exam-sessions/{$this->session->id}/heartbeat", [
                'screen_state' => 'fullscreen',
                'active_tab' => true,
            ], [
                'REMOTE_ADDR' => '192.168.1.50',
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko/20100101 Firefox/115.0',
            ]);

        $response->assertStatus(200);
        $this->assertContains('anomaly_device_change', $response->json('data.anomalies_detected'));

        $this->assertDatabaseHas('exam_violations', [
            'session_id' => $this->session->id,
            'violation_type' => 'anomaly_device_change',
        ]);
    }

    public function test_be_cbt_12_detects_blur_or_tab_switch_anomaly(): void
    {
        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exam-sessions/{$this->session->id}/heartbeat", [
                'screen_state' => 'blur',
                'active_tab' => false,
            ], [
                'REMOTE_ADDR' => '192.168.1.50',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.blur_count', 1);

        $this->assertContains('blur', $response->json('data.anomalies_detected'));

        $this->session->refresh();
        $this->assertEquals(1, $this->session->blur_count);
    }

    public function test_be_cbt_12_detects_suspicious_time_gap_anomaly(): void
    {
        // Set last heartbeat 5 menit lalu (300 detik)
        $this->session->last_heartbeat_at = Carbon::now()->subSeconds(300);
        $this->session->save();

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exam-sessions/{$this->session->id}/heartbeat", [
                'screen_state' => 'fullscreen',
                'active_tab' => true,
            ], [
                'REMOTE_ADDR' => '192.168.1.50',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0',
            ]);

        $response->assertStatus(200);
        $this->assertContains('anomaly_suspicious_gap', $response->json('data.anomalies_detected'));

        $this->assertDatabaseHas('exam_violations', [
            'session_id' => $this->session->id,
            'violation_type' => 'anomaly_suspicious_gap',
        ]);
    }

    public function test_be_cbt_12_proctor_can_fetch_exam_anomalies_summary(): void
    {
        // Buat anomali sampel
        ExamViolation::create([
            'session_id' => $this->session->id,
            'violation_type' => 'anomaly_ip_change',
            'meta' => ['old' => '192.168.1.50', 'new' => '10.0.0.1'],
            'occurred_at' => Carbon::now(),
        ]);

        ExamViolation::create([
            'session_id' => $this->session->id,
            'violation_type' => 'blur',
            'meta' => ['count' => 1],
            'occurred_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->proctorUser, 'sanctum')
            ->getJson("/api/v1/exams/{$this->exam->id}/anomalies");

        $response->assertStatus(200)
            ->assertJsonPath('data.exam_id', $this->exam->id)
            ->assertJsonPath('data.summary.total_anomalies', 2)
            ->assertJsonPath('data.summary.ip_changes', 1)
            ->assertJsonPath('data.summary.blur_events', 1);

        $anomalies = $response->json('data.anomalies');
        $this->assertCount(2, $anomalies);
        $this->assertEquals('Doni Firmansyah', $anomalies[0]['student_name']);
    }

    public function test_be_cbt_12_heartbeat_fails_if_session_not_active(): void
    {
        $this->session->status = 'selesai';
        $this->session->save();

        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/exam-sessions/{$this->session->id}/heartbeat", [
                'screen_state' => 'fullscreen',
            ]);

        $response->assertStatus(422);
    }

    public function test_be_cbt_12_heartbeat_rejected_if_unauthorized_user(): void
    {
        $response = $this->actingAs($this->otherStudentUser, 'sanctum')
            ->postJson("/api/v1/exam-sessions/{$this->session->id}/heartbeat", [
                'screen_state' => 'fullscreen',
            ]);

        $response->assertStatus(403);
    }
}
