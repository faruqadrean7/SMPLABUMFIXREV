<?php

namespace Tests\Feature\Teacher;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradePeriod;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\TeacherDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherGradeIntegrityFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $userMtk;

    protected Teacher $teacherMtk;

    protected User $userKurikulum;

    protected Teacher $teacherKurikulum;

    protected SchoolClass $class7A;

    protected SchoolClass $class7B;

    protected Subject $subjectMtk;

    protected Subject $subjectIpa;

    protected GradePeriod $openPeriod;

    protected GradePeriod $lockedPeriod;

    protected GradeComponent $componentMtk;

    protected Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        (new TeacherDemoSeeder)->run();

        $this->userMtk = User::where('username', 'guru.mtk.staging')->firstOrFail();
        $this->teacherMtk = Teacher::where('user_id', $this->userMtk->id)->firstOrFail();

        $this->userKurikulum = User::where('username', 'kurikulum.staging')->firstOrFail();
        $this->teacherKurikulum = Teacher::where('user_id', $this->userKurikulum->id)->firstOrFail();

        $this->class7A = SchoolClass::where('name', '7-A')->firstOrFail();
        $this->class7B = SchoolClass::where('name', '7-B')->firstOrFail();

        $this->subjectMtk = Subject::where('code', 'MTK-7')->firstOrFail();
        $this->subjectIpa = Subject::where('code', 'IPA-7')->firstOrFail();

        $this->openPeriod = GradePeriod::where('status', 'open')->firstOrFail();
        $this->lockedPeriod = GradePeriod::where('status', 'locked')->firstOrFail();

        $this->componentMtk = GradeComponent::where('subject_id', $this->subjectMtk->id)->firstOrFail();
        $this->student = Student::whereHas('classes', fn ($q) => $q->where('classes.id', $this->class7A->id))->firstOrFail();
    }

    /**
     * Jalur 1: POST /api/v1/grades (Single input)
     * Menolak periode locked (422), validasi rentang 0-100, scoping guru (403), audit_logs.
     */
    public function test_path_1_single_grade_input_integrity(): void
    {
        // 1. Ditolak jika periode terkunci (422)
        $lockedRes = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/grades', [
                'student_id' => $this->student->id,
                'component_id' => $this->componentMtk->id,
                'period_id' => $this->lockedPeriod->id,
                'value' => 85.0,
            ]);
        $lockedRes->assertStatus(422);

        // 2. Ditolak jika nilai di luar 0-100 (400 validation error)
        $rangeRes = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/grades', [
                'student_id' => $this->student->id,
                'component_id' => $this->componentMtk->id,
                'period_id' => $this->openPeriod->id,
                'value' => 110.0,
            ]);
        $rangeRes->assertStatus(400);

        // 3. Ditolak jika guru tidak mengampu mapel komponen ini (403)
        $componentIpa = GradeComponent::create([
            'subject_id' => $this->subjectIpa->id,
            'semester_id' => $this->openPeriod->semester_id,
            'class_id' => $this->class7B->id,
            'code' => 'IPA-F01',
            'name' => 'Formatif IPA',
            'weight' => 20,
        ]);

        $unauthRes = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/grades', [
                'student_id' => $this->student->id,
                'component_id' => $componentIpa->id,
                'period_id' => $this->openPeriod->id,
                'value' => 80.0,
            ]);
        $unauthRes->assertStatus(403);

        // 4. Sukses pada periode terbuka & tercatat di audit_logs
        $auditCountBefore = AuditLog::count();
        $okRes = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/grades', [
                'student_id' => $this->student->id,
                'component_id' => $this->componentMtk->id,
                'period_id' => $this->openPeriod->id,
                'value' => 88.0,
                'reason' => 'Input nilai formatif reguler',
            ]);
        $okRes->assertStatus(201);

        $this->assertGreaterThan($auditCountBefore, AuditLog::count());
    }

    /**
     * Jalur 2: POST /api/v1/grades/bulk (Standard academic bulk grade input)
     */
    public function test_path_2_bulk_grade_input_integrity(): void
    {
        // 1. Ditolak jika periode terkunci (422)
        $lockedRes = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/grades/bulk', [
                'component_id' => $this->componentMtk->id,
                'period_id' => $this->lockedPeriod->id,
                'grades' => [
                    ['student_id' => $this->student->id, 'value' => 90],
                ],
            ]);
        $lockedRes->assertStatus(422);

        // 2. Ditolak jika nilai tidak valid (400 validation error)
        $invalidRes = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/grades/bulk', [
                'component_id' => $this->componentMtk->id,
                'period_id' => $this->openPeriod->id,
                'grades' => [
                    ['student_id' => $this->student->id, 'value' => -5],
                ],
            ]);
        $invalidRes->assertStatus(400);

        // 3. Ditolak jika guru tidak mengampu kelas/mapel (403)
        $compIpa = GradeComponent::create([
            'subject_id' => $this->subjectIpa->id,
            'semester_id' => $this->openPeriod->semester_id,
            'class_id' => $this->class7B->id,
            'code' => 'IPA-BLK',
            'name' => 'Formatif IPA Bulk',
            'weight' => 20,
        ]);
        $unauthRes = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/grades/bulk', [
                'component_id' => $compIpa->id,
                'period_id' => $this->openPeriod->id,
                'grades' => [
                    ['student_id' => $this->student->id, 'value' => 85],
                ],
            ]);
        $unauthRes->assertStatus(403);
    }

    /**
     * Jalur 3: POST /api/v1/me/teacher/grades/bulk (Teacher portal spreadsheet input)
     */
    public function test_path_3_teacher_portal_bulk_grade_integrity(): void
    {
        // 1. Ditolak jika periode terkunci (422)
        $lockedRes = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/grades/bulk', [
                'class_id' => $this->class7A->id,
                'subject_id' => $this->subjectMtk->id,
                'period_id' => $this->lockedPeriod->id,
                'grades' => [
                    [
                        'student_id' => $this->student->id,
                        'scores' => [$this->componentMtk->id => 92],
                    ],
                ],
            ]);
        $lockedRes->assertStatus(422);

        // 2. Ditolak jika kelas tidak diampu (403)
        $unauthClassRes = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/grades/bulk', [
                'class_id' => $this->class7B->id,
                'subject_id' => $this->subjectMtk->id,
                'period_id' => $this->openPeriod->id,
                'grades' => [
                    [
                        'student_id' => $this->student->id,
                        'scores' => [$this->componentMtk->id => 92],
                    ],
                ],
            ]);
        $unauthClassRes->assertStatus(403);

        // 3. Ditolak jika rentang nilai tidak valid (422)
        $invalidScoreRes = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/grades/bulk', [
                'class_id' => $this->class7A->id,
                'subject_id' => $this->subjectMtk->id,
                'period_id' => $this->openPeriod->id,
                'grades' => [
                    [
                        'student_id' => $this->student->id,
                        'scores' => [$this->componentMtk->id => 125],
                    ],
                ],
            ]);
        $invalidScoreRes->assertStatus(422);

        // 4. Sukses & mencatat audit_logs
        $auditCountBefore = AuditLog::count();
        $okRes = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/grades/bulk', [
                'class_id' => $this->class7A->id,
                'subject_id' => $this->subjectMtk->id,
                'period_id' => $this->openPeriod->id,
                'grades' => [
                    [
                        'student_id' => $this->student->id,
                        'scores' => [$this->componentMtk->id => 92.5],
                    ],
                ],
            ]);
        $okRes->assertOk();
        $this->assertGreaterThan($auditCountBefore, AuditLog::count());
    }

    /**
     * Jalur 4: Penilaian tugas (LMS speed grader)
     */
    public function test_path_4_assignment_submission_grading_integrity(): void
    {
        $assignment = Assignment::where('teacher_id', $this->teacherMtk->id)->firstOrFail();
        $submission = AssignmentSubmission::where('assignment_id', $assignment->id)->firstOrFail();

        // 1. Guru lain mencoba menilai -> 403
        $unauthRes = $this->actingAs($this->userKurikulum)
            ->postJson("/api/v1/me/teacher/submissions/{$submission->id}/grade", [
                'score' => 80,
            ]);
        $unauthRes->assertStatus(403);

        // 2. Nilai di luar 0-100 -> 422
        $rangeRes = $this->actingAs($this->userMtk)
            ->postJson("/api/v1/me/teacher/submissions/{$submission->id}/grade", [
                'score' => 150,
            ]);
        $rangeRes->assertStatus(422);

        // 3. Penilaian sah mencatat audit log
        $auditBefore = AuditLog::count();
        $okRes = $this->actingAs($this->userMtk)
            ->postJson("/api/v1/me/teacher/submissions/{$submission->id}/grade", [
                'score' => 96,
                'feedback' => 'Sangat teliti',
            ]);
        $okRes->assertOk();
        $this->assertGreaterThan($auditBefore, AuditLog::count());
    }

    /**
     * Jalur 5: Publikasi nilai ujian CBT ke Buku Nilai (POST /api/v1/exams/{id}/publish-gradebook)
     */
    public function test_path_5_cbt_publish_gradebook_rejects_locked_period(): void
    {
        $exam = Exam::create([
            'school_id' => $this->userMtk->school_id,
            'subject_id' => $this->subjectMtk->id,
            'created_by' => $this->userMtk->id,
            'title' => 'Ujian Akhir CBT MTK',
            'exam_type' => 'sumatif',
            'duration_min' => 60,
            'start_at' => now()->subHours(2),
            'end_at' => now()->subHour(),
            'status' => 'selesai',
        ]);

        ExamSession::create([
            'exam_id' => $exam->id,
            'student_id' => $this->student->id,
            'status' => 'selesai',
            'score' => 88.0,
            'seed' => 123456,
            'started_at' => now()->subHour(),
            'finished_at' => now(),
        ]);

        // 1. Guru tanpa permission cbt.exam.manage ditolak 403
        $unauthRes = $this->actingAs($this->userMtk)
            ->postJson("/api/v1/exams/{$exam->id}/publish-gradebook", [
                'component_id' => $this->componentMtk->id,
                'period_id' => $this->lockedPeriod->id,
            ]);
        $unauthRes->assertStatus(403);

        // 2. Coba publikasi oleh kurikulum ke periode yang TERKUNCI (locked) -> ditolak 422
        $lockedRes = $this->actingAs($this->userKurikulum)
            ->postJson("/api/v1/exams/{$exam->id}/publish-gradebook", [
                'component_id' => $this->componentMtk->id,
                'period_id' => $this->lockedPeriod->id,
            ]);
        $lockedRes->assertStatus(422);
    }

    /**
     * Jalur 6: Penghapusan nilai (DELETE /api/v1/grades/{id})
     * Menolak periode terkunci dan mencatat audit_logs.
     */
    public function test_path_6_grade_deletion_respects_locked_period_and_logs_audit(): void
    {
        $delComp = GradeComponent::create([
            'subject_id' => $this->subjectMtk->id,
            'semester_id' => $this->openPeriod->semester_id,
            'code' => 'DEL-01',
            'name' => 'Komponen Uji Hapus',
            'weight' => 10,
        ]);

        // 1. Buat nilai pada periode terkunci
        $lockedGrade = Grade::create([
            'student_id' => $this->student->id,
            'component_id' => $delComp->id,
            'period_id' => $this->lockedPeriod->id,
            'value' => 80.0,
            'source' => 'manual',
            'entered_by' => $this->userMtk->id,
        ]);

        // Hapus nilai pada periode locked -> ditolak 422
        $delLockedRes = $this->actingAs($this->userMtk)
            ->deleteJson("/api/v1/grades/{$lockedGrade->id}");
        $delLockedRes->assertStatus(422);

        // 2. Buat nilai pada periode terbuka
        $openGrade = Grade::create([
            'student_id' => $this->student->id,
            'component_id' => $delComp->id,
            'period_id' => $this->openPeriod->id,
            'value' => 85.0,
            'source' => 'manual',
            'entered_by' => $this->userMtk->id,
        ]);

        $auditCountBefore = AuditLog::count();
        $delOpenRes = $this->actingAs($this->userMtk)
            ->deleteJson("/api/v1/grades/{$openGrade->id}");
        $delOpenRes->assertOk();

        $this->assertDatabaseMissing('grades', [
            'id' => $openGrade->id,
        ]);
        $this->assertGreaterThan($auditCountBefore, AuditLog::count());
    }
}
