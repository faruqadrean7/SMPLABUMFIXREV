<?php

namespace Tests\Feature\Cbt;

use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\ExamQuestion;
use App\Models\ExamRoom;
use App\Models\ExamSession;
use App\Models\ExamViolation;
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

class ExamProctorRoomIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $proctorRoom1;

    protected User $proctorRoom2;

    protected Exam $exam;

    protected ExamRoom $examRoom1;

    protected ExamRoom $examRoom2;

    protected Student $studentRoom1;

    protected Student $studentRoom2;

    protected ExamSession $sessionRoom1;

    protected ExamSession $sessionRoom2;

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
            'username' => 'admin_iso',
            'email' => 'admin_iso@test.sch.id',
            'password_hash' => Hash::make('secret123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->proctorRoom1 = User::create([
            'username' => 'proctor_room1',
            'email' => 'proctor_room1@test.sch.id',
            'password_hash' => Hash::make('secret123'),
            'status' => 'active',
        ]);
        $this->proctorRoom1->roles()->attach($roleTeacher->id);

        $this->proctorRoom2 = User::create([
            'username' => 'proctor_room2',
            'email' => 'proctor_room2@test.sch.id',
            'password_hash' => Hash::make('secret123'),
            'status' => 'active',
        ]);
        $this->proctorRoom2->roles()->attach($roleTeacher->id);

        $subject = Subject::create([
            'code' => 'MAT-ISO',
            'name' => 'Matematika Terapan',
            'subject_group' => 'muatan_nasional',
        ]);

        $room1 = Room::create([
            'code' => 'R-LAB-1',
            'name' => 'Lab Komputer 1',
            'room_type' => 'lab',
            'capacity' => 20,
        ]);

        $room2 = Room::create([
            'code' => 'R-LAB-2',
            'name' => 'Lab Komputer 2',
            'room_type' => 'lab',
            'capacity' => 20,
        ]);

        $bank = QuestionBank::create([
            'subject_id' => $subject->id,
            'owner_id' => $this->adminUser->id,
            'name' => 'Bank Soal Isolasi',
        ]);

        $q = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'pg',
            'body' => ['text' => '2 + 2 = ?'],
            'weight' => 1.0,
            'status' => 'aktif',
        ]);
        QuestionOption::create(['question_id' => $q->id, 'label' => 'A', 'body' => ['text' => '4'], 'is_correct' => true]);

        $this->exam = Exam::create([
            'subject_id' => $subject->id,
            'created_by' => $this->adminUser->id,
            'title' => 'Ujian Tengah Semester',
            'exam_type' => 'UTS',
            'duration_min' => 60,
            'start_at' => Carbon::now()->subMinutes(10),
            'end_at' => Carbon::now()->addHours(2),
            'passing_grade' => 75.0,
            'status' => 'berlangsung',
        ]);

        ExamQuestion::create(['exam_id' => $this->exam->id, 'question_id' => $q->id, 'sort_order' => 1]);

        $this->examRoom1 = ExamRoom::create([
            'exam_id' => $this->exam->id,
            'room_id' => $room1->id,
            'proctor_id' => $this->proctorRoom1->id,
            'session_no' => 1,
            'token' => 'TOK001',
            'token_expires_at' => Carbon::now()->addHours(2),
        ]);

        $this->examRoom2 = ExamRoom::create([
            'exam_id' => $this->exam->id,
            'room_id' => $room2->id,
            'proctor_id' => $this->proctorRoom2->id,
            'session_no' => 1,
            'token' => 'TOK002',
            'token_expires_at' => Carbon::now()->addHours(2),
        ]);

        // Student 1 in Room 1
        $userStudent1 = User::create([
            'username' => 'mhs_room1',
            'email' => 'mhs_room1@test.sch.id',
            'password_hash' => Hash::make('secret123'),
            'status' => 'active',
        ]);
        $userStudent1->roles()->attach($roleStudent->id);
        $this->studentRoom1 = Student::create([
            'user_id' => $userStudent1->id,
            'full_name' => 'Siswa Ruang Satu',
            'nis' => '11001',
            'nisn' => '0011001001',
            'nik_enc' => 'enc_nik_1',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);
        ExamParticipant::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->studentRoom1->id,
            'exam_room_id' => $this->examRoom1->id,
        ]);
        $this->sessionRoom1 = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->studentRoom1->id,
            'status' => 'terkunci',
            'locked_reason' => 'Perpindahan jendela terdeteksi',
            'started_at' => Carbon::now()->subMinutes(15),
            'deadline_at' => Carbon::now()->addMinutes(45),
            'seed' => 'seed1',
        ]);

        // Student 2 in Room 2
        $userStudent2 = User::create([
            'username' => 'mhs_room2',
            'email' => 'mhs_room2@test.sch.id',
            'password_hash' => Hash::make('secret123'),
            'status' => 'active',
        ]);
        $userStudent2->roles()->attach($roleStudent->id);
        $this->studentRoom2 = Student::create([
            'user_id' => $userStudent2->id,
            'full_name' => 'Siswa Ruang Dua',
            'nis' => '11002',
            'nisn' => '0011001002',
            'nik_enc' => 'enc_nik_2',
            'gender' => 'P',
            'birth_place' => 'Surabaya',
            'birth_date' => '2010-02-02',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);
        ExamParticipant::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->studentRoom2->id,
            'exam_room_id' => $this->examRoom2->id,
        ]);
        $this->sessionRoom2 = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->studentRoom2->id,
            'status' => 'terkunci',
            'locked_reason' => 'Perpindahan jendela terdeteksi',
            'started_at' => Carbon::now()->subMinutes(15),
            'deadline_at' => Carbon::now()->addMinutes(45),
            'seed' => 'seed2',
        ]);
    }

    public function test_p1_sec_authz_01_proctor_cannot_monitor_unassigned_rooms(): void
    {
        // Proctor Room 1 tries to query Room 2 explicitly
        $response = $this->actingAs($this->proctorRoom1, 'sanctum')
            ->getJson("/api/v1/exams/{$this->exam->id}/monitor?room_id={$this->examRoom2->id}");

        $response->assertStatus(403)
            ->assertJsonPath('error.message', 'Anda tidak berwenang memantau ruangan ujian ini.');
    }

    public function test_p1_sec_authz_01_proctor_only_sees_participants_in_assigned_room(): void
    {
        // Proctor Room 1 calls monitor without room filter
        $response = $this->actingAs($this->proctorRoom1, 'sanctum')
            ->getJson("/api/v1/exams/{$this->exam->id}/monitor");

        $response->assertOk()
            ->assertJsonPath('data.summary.total_participants', 1)
            ->assertJsonPath('data.participants.0.student_id', $this->studentRoom1->id)
            ->assertJsonPath('data.participants.0.student_name', 'Siswa Ruang Satu');

        // Proctor Room 2 calls monitor
        $response2 = $this->actingAs($this->proctorRoom2, 'sanctum')
            ->getJson("/api/v1/exams/{$this->exam->id}/monitor");

        $response2->assertOk()
            ->assertJsonPath('data.summary.total_participants', 1)
            ->assertJsonPath('data.participants.0.student_id', $this->studentRoom2->id)
            ->assertJsonPath('data.participants.0.student_name', 'Siswa Ruang Dua');
    }

    public function test_p1_sec_authz_01_admin_can_monitor_all_rooms_and_filter_any(): void
    {
        // Admin sees all 2 participants across rooms
        $responseAll = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/exams/{$this->exam->id}/monitor");

        $responseAll->assertOk()
            ->assertJsonPath('data.summary.total_participants', 2);

        // Admin filters Room 1
        $responseR1 = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/exams/{$this->exam->id}/monitor?room_id={$this->examRoom1->id}");

        $responseR1->assertOk()
            ->assertJsonPath('data.summary.total_participants', 1)
            ->assertJsonPath('data.participants.0.student_id', $this->studentRoom1->id);

        // Admin filters Room 2
        $responseR2 = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/exams/{$this->exam->id}/monitor?room_id={$this->examRoom2->id}");

        $responseR2->assertOk()
            ->assertJsonPath('data.summary.total_participants', 1)
            ->assertJsonPath('data.participants.0.student_id', $this->studentRoom2->id);
    }

    public function test_p1_sec_authz_01_proctor_cannot_manage_or_unlock_session_in_unassigned_room(): void
    {
        // Proctor Room 1 tries to unlock Student in Room 2
        $response = $this->actingAs($this->proctorRoom1, 'sanctum')
            ->postJson("/api/v1/exam-sessions/{$this->sessionRoom2->id}/manage", [
                'action' => 'unlock',
                'extra_minutes' => 10,
                'reason' => 'Upaya buka kunci lintas ruangan',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.message', 'Anda tidak berwenang mengelola peserta di luar ruangan penugasan Anda.');

        $this->sessionRoom2->refresh();
        $this->assertEquals('terkunci', $this->sessionRoom2->status);
    }

    public function test_p1_sec_authz_01_proctor_can_manage_and_unlock_session_in_assigned_room(): void
    {
        // Proctor Room 1 unlocks Student in Room 1
        $response = $this->actingAs($this->proctorRoom1, 'sanctum')
            ->postJson("/api/v1/exam-sessions/{$this->sessionRoom1->id}/manage", [
                'action' => 'unlock',
                'extra_minutes' => 15,
                'reason' => 'Buka kunci sah oleh pengawas ruang 1',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'mengerjakan')
            ->assertJsonPath('data.extra_minutes', 15);

        $this->sessionRoom1->refresh();
        $this->assertEquals('mengerjakan', $this->sessionRoom1->status);
    }

    public function test_p1_sec_authz_01_proctor_only_sees_anomalies_for_assigned_room(): void
    {
        // Create violation for Room 1
        ExamViolation::create([
            'session_id' => $this->sessionRoom1->id,
            'violation_type' => 'anomaly_ip_change',
            'meta' => ['info' => 'anomaly in room 1'],
            'occurred_at' => Carbon::now(),
        ]);

        // Create violation for Room 2
        ExamViolation::create([
            'session_id' => $this->sessionRoom2->id,
            'violation_type' => 'blur',
            'meta' => ['info' => 'anomaly in room 2'],
            'occurred_at' => Carbon::now(),
        ]);

        // Proctor 1 should only see violation for student 1
        $resProctor1 = $this->actingAs($this->proctorRoom1, 'sanctum')
            ->getJson("/api/v1/exams/{$this->exam->id}/anomalies");

        $resProctor1->assertOk()
            ->assertJsonPath('data.summary.total_anomalies', 1)
            ->assertJsonPath('data.anomalies.0.student_id', $this->studentRoom1->id);

        // Proctor 2 should only see violation for student 2
        $resProctor2 = $this->actingAs($this->proctorRoom2, 'sanctum')
            ->getJson("/api/v1/exams/{$this->exam->id}/anomalies");

        $resProctor2->assertOk()
            ->assertJsonPath('data.summary.total_anomalies', 1)
            ->assertJsonPath('data.anomalies.0.student_id', $this->studentRoom2->id);

        // Admin sees both violations
        $resAdmin = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/exams/{$this->exam->id}/anomalies");

        $resAdmin->assertOk()
            ->assertJsonPath('data.summary.total_anomalies', 2);
    }
}
