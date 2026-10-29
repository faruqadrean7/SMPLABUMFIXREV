<?php

namespace Tests\Feature\Teacher;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\GradeComponent;
use App\Models\GradePeriod;
use App\Models\Guardian;
use App\Models\Notification;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\TeacherDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherCrossRoleConsistencyFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $userMtk;

    protected Teacher $teacherMtk;

    protected User $userWalas;

    protected Teacher $teacherWalas;

    protected User $userKepsek;

    protected User $userStudent;

    protected Student $student;

    protected User $userGuardian;

    protected Guardian $guardian;

    protected SchoolClass $class7A;

    protected Subject $subjectMtk;

    protected Semester $semesterGanjil;

    protected GradePeriod $openPeriod;

    protected GradeComponent $componentMtk;

    protected function setUp(): void
    {
        parent::setUp();

        (new TeacherDemoSeeder)->run();

        $this->userMtk = User::where('username', 'guru.mtk.staging')->firstOrFail();
        $this->teacherMtk = Teacher::where('user_id', $this->userMtk->id)->firstOrFail();

        $this->userWalas = User::where('username', 'walas.7a.staging')->firstOrFail();
        $this->teacherWalas = Teacher::where('user_id', $this->userWalas->id)->firstOrFail();

        $this->class7A = SchoolClass::where('name', '7-A')->firstOrFail();
        $this->subjectMtk = Subject::where('code', 'MTK-7')->firstOrFail();
        $this->semesterGanjil = Semester::where('name', 'Ganjil')->firstOrFail();

        $this->openPeriod = GradePeriod::where('semester_id', $this->semesterGanjil->id)
            ->where('status', 'open')
            ->firstOrFail();

        $this->componentMtk = GradeComponent::firstOrCreate(
            [
                'subject_id' => $this->subjectMtk->id,
                'semester_id' => $this->semesterGanjil->id,
                'code' => 'MTK-CR-01',
            ],
            [
                'name' => 'Formatif Cross Role',
                'weight' => 20,
            ]
        );

        $this->student = Student::whereHas('classes', fn ($q) => $q->where('classes.id', $this->class7A->id))->firstOrFail();
        $this->userStudent = $this->student->user;

        // Kepsek
        $rolePrincipal = Role::firstOrCreate(['code' => 'principal'], ['name' => 'Kepala Sekolah', 'is_system' => true]);
        $this->userKepsek = User::firstOrCreate(
            ['username' => 'kepsek.crossrole'],
            [
                'school_id' => $this->class7A->school_id,
                'email' => 'kepsek.cr@sistlabum.sch.id',
                'password_hash' => bcrypt('password'),
                'status' => 'active',
            ]
        );
        $this->userKepsek->roles()->syncWithoutDetaching([$rolePrincipal->id]);

        // Guardian
        $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Wali Murid', 'is_system' => true]);
        $userOrtu = User::firstOrCreate(
            ['username' => 'ortu.crossrole'],
            [
                'school_id' => $this->class7A->school_id,
                'email' => 'ortu.cr@sistlabum.sch.id',
                'password_hash' => bcrypt('password'),
                'status' => 'active',
            ]
        );
        $userOrtu->roles()->syncWithoutDetaching([$roleGuardian->id]);

        $this->guardian = Guardian::firstOrCreate(
            ['user_id' => $userOrtu->id],
            [
                'full_name' => 'Orang Tua Siswa 7A',
                'phone' => '081234567888',
            ]
        );
        $this->guardian->students()->syncWithoutDetaching([
            $this->student->id => ['relationship' => 'ibu'],
        ]);
        $this->userGuardian = $userOrtu;
    }

    /**
     * Alur 1: Guru input nilai -> Siswa & Ortu melihat nilai yang sama di portal masing-masing.
     */
    public function test_flow_1_grade_input_reflected_in_student_and_guardian_portals(): void
    {
        $inputScore = 93.5;

        // 1. Guru input nilai via API
        $gradeRes = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/grades', [
                'student_id' => $this->student->id,
                'component_id' => $this->componentMtk->id,
                'period_id' => $this->openPeriod->id,
                'value' => $inputScore,
                'reason' => 'Penilaian ulangan harian bab 1',
            ]);
        $gradeRes->assertStatus(201);

        // 2. Siswa melihat nilainya di portal siswa (GET /api/v1/me/grades)
        $studentRes = $this->actingAs($this->userStudent)
            ->getJson('/api/v1/me/grades');
        $studentRes->assertOk();

        $studentGrades = $studentRes->json('data');
        $studentSubject = collect($studentGrades)->firstWhere('subjectId', $this->subjectMtk->id);
        $this->assertNotNull($studentSubject, 'Mata pelajaran matematika harus ada di daftar nilai siswa');

        // 3. Orang tua melihat nilai anak di portal wali (GET /api/v1/me/children/{childId}/grades)
        $guardianRes = $this->actingAs($this->userGuardian)
            ->getJson("/api/v1/me/children/{$this->student->id}/grades");
        $guardianRes->assertOk();

        $guardianGrades = $guardianRes->json('data');
        $guardianSubject = collect($guardianGrades)->firstWhere('subjectId', $this->subjectMtk->id);
        $this->assertNotNull($guardianSubject, 'Mata pelajaran matematika harus ada di portal orang tua');

        // Skor konsisten antara siswa dan orang tua
        $this->assertEquals(
            $studentSubject['finalScore'],
            $guardianSubject['finalScore'],
            'Nilai akhir di portal siswa dan portal wali harus konsisten'
        );
    }

    /**
     * Alur 2: Guru buat tugas -> Siswa kumpulkan -> Guru nilai via SpeedGrader -> Siswa lihat feedback.
     */
    public function test_flow_2_assignment_submission_grading_and_feedback(): void
    {
        // 1. Guru buat tugas
        $assignment = Assignment::create([
            'class_id' => $this->class7A->id,
            'subject_id' => $this->subjectMtk->id,
            'teacher_id' => $this->teacherMtk->id,
            'title' => 'Latihan Soal Aljabar Bersama',
            'description' => 'Kerjakan soal 1 sampai 10 di buku latihan.',
            'due_at' => now()->addDays(5),
            'max_score' => 100,
        ]);

        // 2. Siswa kumpulkan tugas dengan catatan pengerjaan
        $submissionRes = $this->actingAs($this->userStudent)
            ->postJson("/api/v1/me/tasks/{$assignment->id}/submit", [
                'notes' => 'Berikut jawaban tugas saya pada lembar terlampir.',
            ]);
        $submissionRes->assertStatus(201);

        $submission = AssignmentSubmission::where('assignment_id', $assignment->id)
            ->where('student_id', $this->student->id)
            ->firstOrFail();

        // 3. Guru beri penilaian & feedback via SpeedGrader
        $feedbackText = 'Penalaran aljabar sangat baik dan sistematis!';
        $givenScore = 96.0;

        $gradeRes = $this->actingAs($this->userMtk)
            ->postJson("/api/v1/me/teacher/submissions/{$submission->id}/grade", [
                'score' => $givenScore,
                'feedback' => $feedbackText,
            ]);
        $gradeRes->assertOk();

        // 4. Siswa melihat nilai & umpan balik guru di daftar tugasnya
        $studentTasksRes = $this->actingAs($this->userStudent)
            ->getJson('/api/v1/me/tasks');
        $studentTasksRes->assertOk();

        $tasks = $studentTasksRes->json('data');
        $targetTask = collect($tasks)->firstWhere('id', $assignment->id);
        $this->assertNotNull($targetTask, 'Tugas harus ditemukan di daftar tugas siswa');
        $this->assertEquals($givenScore, (float) $targetTask['score']);
        $this->assertEquals($feedbackText, $targetTask['teacherFeedback']);
        $this->assertEquals('dinilai', $targetTask['status']);
    }

    /**
     * Alur 3: Absensi kelas oleh guru -> Rekap harian di dashboard ortu.
     */
    public function test_flow_3_teacher_attendance_reflected_in_guardian_dashboard(): void
    {
        $today = now()->toDateString();
        Attendance::where('student_id', $this->student->id)->where('att_date', $today)->delete();

        // Guru catat absensi kelas via portal guru
        $attRes = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/attendance/sessions', [
                'class_id' => $this->class7A->id,
                'subject_id' => $this->subjectMtk->id,
                'date' => $today,
                'topic' => 'Bab 1 Aljabar Dasar',
                'attendees' => [
                    [
                        'student_id' => $this->student->id,
                        'status' => 'hadir',
                        'notes' => 'Hadir tepat waktu dan aktif',
                    ],
                ],
            ]);
        $attRes->assertStatus(201);

        // Ortu membuka rekap absensi anak
        $guardianAttRes = $this->actingAs($this->userGuardian)
            ->getJson("/api/v1/me/children/{$this->student->id}/attendance");
        $guardianAttRes->assertOk();

        $history = $guardianAttRes->json('data.history');
        $todayRecord = collect($history)->firstWhere('date', $today);
        $this->assertNotNull($todayRecord, 'Presensi hari ini harus muncul di portal orang tua');
        $this->assertEquals('present', $todayRecord['status']);
    }

    /**
     * Alur 4: Guru ajukan cuti -> Kepsek setujui -> Status cuti berubah & notifikasi masuk ke guru.
     */
    public function test_flow_4_teacher_leave_request_approval_and_notification(): void
    {
        // 1. Guru mengajukan izin cuti
        $leaveRes = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/leave-requests', [
                'leave_type' => 'izin',
                'start_date' => now()->addDays(3)->toDateString(),
                'end_date' => now()->addDays(4)->toDateString(),
                'reason' => 'Mengikuti workshop kurikulum Lab School',
            ]);
        $leaveRes->assertStatus(201);
        $leaveId = $leaveRes->json('data.id');
        $this->assertNotEmpty($leaveId);

        // 2. Kepsek menyetujui izin cuti
        $approvalRes = $this->actingAs($this->userKepsek)
            ->postJson("/api/v1/approvals/{$leaveId}/review", [
                'action' => 'approve',
                'notes' => 'Disetujui. Harap tinggalkan modul pembelajaran untuk kelas pengganti.',
            ]);
        $approvalRes->assertOk();

        // 3. Status cuti guru berubah menjadi disetujui (approved)
        $teacherLeavesRes = $this->actingAs($this->userMtk)
            ->getJson('/api/v1/me/teacher/leave-requests');
        $teacherLeavesRes->assertOk();

        $leaves = $teacherLeavesRes->json('data');
        $approvedLeave = collect($leaves)->firstWhere('id', $leaveId);
        $this->assertNotNull($approvedLeave, 'Permohonan cuti yang baru diajukan harus ada di daftar cuti');
        $this->assertEquals('approved', $approvedLeave['status']);

        // 4. Notifikasi in-app masuk ke akun guru
        $notif = Notification::where('user_id', $this->userMtk->id)
            ->where('notif_type', 'leave_request.decision')
            ->latest()
            ->first();

        $this->assertNotNull($notif, 'Notifikasi keputusan cuti harus terkirim ke guru');
        $this->assertStringContainsString('disetujui', strtolower($notif->title));
    }
}
