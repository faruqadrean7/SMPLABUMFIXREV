<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AuditLog;
use App\Models\File;
use App\Models\GradeComponent;
use App\Models\GradePeriod;
use App\Models\TeachingMaterial;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TeacherLmsController extends Controller
{
    use ApiResponse;
    use TeacherPortalContextTrait;

    /**
     * GET /api/v1/me/teacher/materials
     */
    public function materials(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $materials = TeachingMaterial::with(['subject', 'file'])
            ->where('uploaded_by', $request->user()->id)
            ->latest()
            ->get();

        $data = $materials->map(fn (TeachingMaterial $m) => [
            'id' => $m->id,
            'title' => $m->title,
            'subjectId' => $m->subject_id ?? '',
            'subject_id' => $m->subject_id ?? '',
            'subjectName' => $m->subject?->name ?? 'Mata Pelajaran',
            'classIds' => [],
            'classNames' => [],
            'fileUrl' => $m->file?->url,
            'description' => $m->description ?? '',
            'createdAt' => $m->created_at?->toISOString() ?? now()->toISOString(),
            'downloadsCount' => 0,
        ]);

        return $this->successResponse($data);
    }

    /**
     * POST /api/v1/me/teacher/materials
     */
    public function storeMaterial(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $input = $request->all();
        $title = $input['title'] ?? 'Modul Ajar';
        $subjectId = $input['subject_id'] ?? $input['subjectId'] ?? null;
        $description = $input['description'] ?? '';
        $fileId = $input['file_id'] ?? $input['fileId'] ?? null;
        $materialType = $input['material_type'] ?? $input['materialType'] ?? 'modul';

        if (! $subjectId) {
            return $this->errorResponse('ID mata pelajaran wajib diisi.', 'VALIDATION_ERROR', 422);
        }

        if (! Str::isUuid($subjectId)) {
            return $this->errorResponse('Format ID mata pelajaran tidak valid.', 'VALIDATION_ERROR', 422);
        }

        if (! $this->verifySubjectAccess($teacher, $subjectId)) {
            return $this->errorResponse('Akses ditolak: Anda tidak ditugaskan mengampu mata pelajaran ini.', 'FORBIDDEN', 403);
        }

        // Ensure a valid file exists for foreign key constraint
        if (! $fileId || ! Str::isUuid($fileId)) {
            $file = File::firstOrCreate(
                ['object_key' => 'materials/default-modul.pdf'],
                [
                    'bucket' => 'academic',
                    'mime_type' => 'application/pdf',
                    'size_bytes' => 1024,
                    'visibility' => 'publik',
                ]
            );
            $fileId = $file->id;
        }

        $material = TeachingMaterial::create([
            'title' => $title,
            'subject_id' => $subjectId,
            'uploaded_by' => $request->user()->id,
            'file_id' => $fileId,
            'material_type' => $materialType,
            'description' => $description,
            'version' => 1,
        ]);

        $material->load(['subject', 'file']);

        return $this->successResponse([
            'id' => $material->id,
            'title' => $material->title,
            'subjectId' => $material->subject_id ?? '',
            'subject_id' => $material->subject_id ?? '',
            'subjectName' => $material->subject?->name ?? 'Mata Pelajaran',
            'classIds' => [],
            'classNames' => [],
            'fileUrl' => $material->file?->url ?? '/documents/modul-pembelajaran.pdf',
            'description' => $material->description ?? '',
            'createdAt' => $material->created_at?->toISOString() ?? now()->toISOString(),
            'downloadsCount' => 0,
        ], 201);
    }

    /**
     * GET /api/v1/me/teacher/assignments
     */
    public function assignments(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $assignments = Assignment::with(['class', 'subject'])
            ->where('teacher_id', $teacher->id)
            ->withCount('submissions')
            ->latest()
            ->get();

        $data = $assignments->map(fn (Assignment $a) => [
            'id' => $a->id,
            'title' => $a->title,
            'subjectId' => $a->subject_id,
            'subject_id' => $a->subject_id,
            'subjectName' => $a->subject?->name ?? 'Mata Pelajaran',
            'classId' => $a->class_id,
            'class_id' => $a->class_id,
            'className' => $a->class?->name ?? 'Kelas',
            'dueDate' => $a->due_at?->toISOString() ?? now()->addDays(7)->toISOString(),
            'due_at' => $a->due_at?->toISOString() ?? now()->addDays(7)->toISOString(),
            'totalStudents' => $a->class?->students()->count() ?? 32,
            'submittedCount' => $a->submissions_count,
            'gradedCount' => 0,
            'description' => $a->description ?? '',
            'createdAt' => $a->created_at?->toISOString() ?? now()->toISOString(),
        ]);

        return $this->successResponse($data);
    }

    /**
     * POST /api/v1/me/teacher/assignments
     */
    public function storeAssignment(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $input = $request->all();
        $title = $input['title'] ?? 'Tugas Pembelajaran';
        $classId = $input['class_id'] ?? $input['classId'] ?? null;
        $subjectId = $input['subject_id'] ?? $input['subjectId'] ?? null;
        $description = $input['description'] ?? '';
        $dueAt = $input['due_at'] ?? $input['dueDate'] ?? $input['due_date'] ?? now()->addDays(7);

        if (! $classId || ! $subjectId) {
            return $this->errorResponse('ID kelas dan ID mata pelajaran wajib diisi.', 'VALIDATION_ERROR', 422);
        }

        if (! Str::isUuid($classId) || ! Str::isUuid($subjectId)) {
            return $this->errorResponse('Format ID kelas atau mata pelajaran tidak valid.', 'VALIDATION_ERROR', 422);
        }

        if (! $this->verifyClassAccess($teacher, $classId)) {
            return $this->errorResponse('Akses ditolak: Anda tidak ditugaskan di kelas ini.', 'FORBIDDEN', 403);
        }

        if (! $this->verifySubjectAccess($teacher, $subjectId, $classId)) {
            return $this->errorResponse('Akses ditolak: Anda tidak ditugaskan mengampu mata pelajaran ini di kelas terkait.', 'FORBIDDEN', 403);
        }

        $assignment = Assignment::create([
            'teacher_id' => $teacher->id,
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'title' => $title,
            'description' => $description,
            'due_at' => $dueAt,
        ]);

        $assignment->load(['class', 'subject']);

        return $this->successResponse([
            'id' => $assignment->id,
            'title' => $assignment->title,
            'subjectId' => $assignment->subject_id,
            'subject_id' => $assignment->subject_id,
            'subjectName' => $assignment->subject?->name ?? 'Mata Pelajaran',
            'classId' => $assignment->class_id,
            'class_id' => $assignment->class_id,
            'className' => $assignment->class?->name ?? 'Kelas',
            'dueDate' => $assignment->due_at?->toISOString() ?? now()->addDays(7)->toISOString(),
            'due_at' => $assignment->due_at?->toISOString() ?? now()->addDays(7)->toISOString(),
            'totalStudents' => $assignment->class?->students()->count() ?? 0,
            'submittedCount' => 0,
            'gradedCount' => 0,
            'description' => $assignment->description ?? '',
            'createdAt' => $assignment->created_at?->toISOString() ?? now()->toISOString(),
        ], 201);
    }

    /**
     * GET /api/v1/me/teacher/assignments/{id}/submissions
     */
    public function submissions(Request $request, string $id): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $assignment = Assignment::where('id', $id)
            ->where('teacher_id', $teacher->id)
            ->first();

        if (! $assignment) {
            return $this->errorResponse('Tugas tidak ditemukan atau bukan milik Anda.', 'FORBIDDEN', 403);
        }

        $submissions = AssignmentSubmission::with('student')
            ->where('assignment_id', $id)
            ->get();

        $data = $submissions->map(fn (AssignmentSubmission $sub) => [
            'id' => $sub->id,
            'studentId' => $sub->student_id,
            'studentName' => $sub->student?->full_name ?? 'Siswa',
            'nisn' => $sub->student?->nisn ?? '',
            'submittedAt' => $sub->submitted_at?->toISOString() ?? now()->toISOString(),
            'score' => $sub->score ? (float) $sub->score : null,
            'feedback' => $sub->feedback,
            'status' => $sub->score !== null ? 'graded' : 'pending',
        ]);

        return $this->successResponse($data);
    }

    /**
     * POST /api/v1/me/teacher/submissions/{id}/grade
     */
    public function gradeSubmission(Request $request, string $id): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $submission = AssignmentSubmission::with('assignment')->find($id);
        if (! $submission || $submission->assignment?->teacher_id !== $teacher->id) {
            return $this->errorResponse('Pengumpulan tugas tidak ditemukan atau bukan tugas yang Anda ampu.', 'FORBIDDEN', 403);
        }

        $validated = $request->validate([
            'score' => 'required|numeric|min:0|max:100',
            'feedback' => 'nullable|string',
        ]);

        if ($submission->assignment && $submission->assignment->grade_component_id) {
            $comp = GradeComponent::find($submission->assignment->grade_component_id);
            if ($comp) {
                $period = GradePeriod::where('semester_id', $comp->semester_id)
                    ->where('month', now()->month)
                    ->first();
                if ($period && $period->status === 'locked') {
                    return $this->errorResponse('Periode penilaian telah dikunci (locked). Penilaian tidak dapat disimpan.', 'BUSINESS_RULE_VIOLATION', 422);
                }
            }
        }

        $oldData = $submission->toArray();
        $submission->update([
            'score' => $validated['score'],
            'feedback' => $validated['feedback'] ?? null,
        ]);

        AuditLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'assignment.graded',
            'entity' => 'AssignmentSubmission',
            'entity_id' => $submission->id,
            'old_value' => $oldData,
            'new_value' => $submission->toArray(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->successResponse(['message' => 'Nilai dan feedback berhasil diberikan.']);
    }
}
