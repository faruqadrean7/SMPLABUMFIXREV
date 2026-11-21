<?php

namespace Tests\Feature\Cbt;

use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\ExamProctorAction;
use App\Models\ExamRoom;
use App\Models\ExamSession;
use App\Models\ExamViolation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\SiteSetting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExamReportMinutesTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $proctorUser;

    protected User $otherUser;

    protected Student $student1;

    protected Student $student2;

    protected Exam $exam;

    protected ExamRoom $examRoom;

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
            'username' => 'admin_cbt_rep',
            'email' => 'admin_rep@test.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->proctorUser = User::create([
            'username' => 'proctor_rep',
            'email' => 'proctor_rep@test.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->proctorUser->roles()->attach($roleTeacher->id);

        $this->otherUser = User::create([
            'username' => 'other_rep',
            'email' => 'other_rep@test.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        SiteSetting::create(['setting_key' => 'school_name', 'setting_value' => ['value' => 'SMA LAB UM MALANG']]);
        SiteSetting::create(['setting_key' => 'school_npsn', 'setting_value' => ['value' => '20533814']]);
        SiteSetting::create(['setting_key' => 'school_address', 'setting_value' => ['value' => 'Jl. Bromo No. 16']]);

        $subject = Subject::create([
            'code' => 'FIS10',
            'name' => 'Fisika Terapan',
            'subject_group' => 'muatan_nasional',
        ]);

        $room = Room::create([
            'code' => 'LAB-FIS',
            'name' => 'Laboratorium Fisika',
            'room_type' => 'lab',
            'capacity' => 30,
        ]);

        $this->exam = Exam::create([
            'subject_id' => $subject->id,
            'created_by' => $this->proctorUser->id,
            'title' => 'Asesmen Akhir Semester Fisika',
            'exam_type' => 'SAS',
            'duration_min' => 90,
            'start_at' => Carbon::now()->subHours(3),
            'end_at' => Carbon::now()->subHour(),
            'passing_grade' => 75.0,
            'status' => 'selesai',
        ]);

        $this->examRoom = ExamRoom::create([
            'exam_id' => $this->exam->id,
            'room_id' => $room->id,
            'proctor_id' => $this->proctorUser->id,
            'session_no' => 1,
            'token' => 'FIS001',
            'token_expires_at' => Carbon::now()->subHour(),
        ]);

        // Student 1 (Hadir, Nilai 80)
        $user1 = User::create(['username' => 's1_rep', 'email' => 's1_rep@test.sch.id', 'password_hash' => Hash::make('password123'), 'status' => 'active']);
        $user1->roles()->attach($roleStudent->id);
        $this->student1 = Student::create([
            'user_id' => $user1->id,
            'full_name' => 'Bintang Pratama',
            'nis' => '3001',
            'nisn' => '0030010001',
            'nik_enc' => 'enc_3001',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);
        ExamParticipant::create(['exam_id' => $this->exam->id, 'student_id' => $this->student1->id, 'exam_room_id' => $this->examRoom->id]);
        $session1 = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student1->id,
            'status' => 'selesai',
            'score' => 80.0,
            'seed' => 'seed_rep_1',
        ]);

        // Student 2 (Tidak Hadir - belum ada ExamSession)
        $user2 = User::create(['username' => 's2_rep', 'email' => 's2_rep@test.sch.id', 'password_hash' => Hash::make('password123'), 'status' => 'active']);
        $user2->roles()->attach($roleStudent->id);
        $this->student2 = Student::create([
            'user_id' => $user2->id,
            'full_name' => 'Dimas Anggara',
            'nis' => '3002',
            'nisn' => '0030010002',
            'nik_enc' => 'enc_3002',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-02-02',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);
        ExamParticipant::create(['exam_id' => $this->exam->id, 'student_id' => $this->student2->id, 'exam_room_id' => $this->examRoom->id]);

        // Catatan Pelanggaran
        ExamViolation::create([
            'session_id' => $session1->id,
            'violation_type' => 'blur',
            'meta' => ['count' => 1],
            'occurred_at' => Carbon::now()->subHours(2),
        ]);

        // Aksi Pengawas
        ExamProctorAction::create([
            'session_id' => $session1->id,
            'actor_id' => $this->proctorUser->id,
            'action' => 'extra_time',
            'reason' => 'Koneksi lokal sempat terputus 5 menit',
        ]);
    }

    public function test_be_cbt_10_proctor_can_get_comprehensive_exam_report(): void
    {
        $response = $this->actingAs($this->proctorUser, 'sanctum')
            ->getJson("/api/v1/exams/{$this->exam->id}/report");

        $response->assertStatus(200)
            ->assertJsonPath('data.exam.id', $this->exam->id)
            ->assertJsonPath('data.attendance_summary.total_allocated', 2)
            ->assertJsonPath('data.attendance_summary.present_count', 1)
            ->assertJsonPath('data.attendance_summary.absent_count', 1)
            ->assertJsonPath('data.attendance_summary.attendance_percentage', 50)
            ->assertJsonPath('data.score_statistics.highest_score', 80)
            ->assertJsonPath('data.score_statistics.lowest_score', 80)
            ->assertJsonPath('data.score_statistics.average_score', 80)
            ->assertJsonPath('data.score_statistics.pass_count', 1)
            ->assertJsonPath('data.score_statistics.fail_count', 0)
            ->assertJsonPath('data.score_statistics.pass_rate_percentage', 100);

        $participants = $response->json('data.participants');
        $this->assertCount(2, $participants);

        $presentStudent = collect($participants)->firstWhere('student_id', $this->student1->id);
        $this->assertEquals('hadir', $presentStudent['attendance_status']);
        $this->assertEquals(80.0, (float) $presentStudent['score']);
        $this->assertTrue($presentStudent['passed']);

        $absentStudent = collect($participants)->firstWhere('student_id', $this->student2->id);
        $this->assertEquals('tidak_hadir', $absentStudent['attendance_status']);
        $this->assertNull($absentStudent['score']);
    }

    public function test_be_cbt_10_report_filters_by_room(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/exams/{$this->exam->id}/report?room_id={$this->examRoom->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.rooms_recap.0.exam_room_id', $this->examRoom->id)
            ->assertJsonPath('data.rooms_recap.0.total_allocated', 2);
    }

    public function test_be_cbt_10_generates_official_minutes_html_document(): void
    {
        $response = $this->actingAs($this->proctorUser, 'sanctum')
            ->get("/api/v1/exams/{$this->exam->id}/minutes/pdf");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');

        $content = $response->getContent();
        $this->assertStringContainsString('BERITA ACARA PELAKSANAAN UJIAN BERBASIS KOMPUTER', $content);
        $this->assertStringContainsString('SMA LAB UM MALANG', $content);
        $this->assertStringContainsString('Fisika Terapan', $content);
        $this->assertStringContainsString('Dimas Anggara', $content); // Siswa tidak hadir
        $this->assertStringContainsString('proctor_rep', $content);
    }

    public function test_be_cbt_10_generates_minutes_json_format_if_requested(): void
    {
        $response = $this->actingAs($this->proctorUser, 'sanctum')
            ->getJson("/api/v1/exams/{$this->exam->id}/minutes/pdf?format=json");

        $response->assertStatus(200)
            ->assertJsonPath('data.exam.id', $this->exam->id)
            ->assertJsonPath('data.attendance_summary.present_count', 1);
    }

    public function test_be_cbt_10_unauthorized_user_cannot_access_report(): void
    {
        $response = $this->actingAs($this->otherUser, 'sanctum')
            ->getJson("/api/v1/exams/{$this->exam->id}/report");

        $response->assertStatus(403);
    }

    public function test_be_cbt_10_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson("/api/v1/exams/{$this->exam->id}/report");

        $response->assertStatus(401);
    }
}
