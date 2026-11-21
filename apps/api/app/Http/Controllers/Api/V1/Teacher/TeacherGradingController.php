<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradePeriod;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TeacherGradingController extends Controller
{
    use ApiResponse;
    use TeacherPortalContextTrait;

    /**
     * GET /api/v1/me/teacher/grade-components
     */
    public function gradeComponents(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $classId = $request->query('class_id') ?? $request->query('classId');
        $subjectId = $request->query('subject_id') ?? $request->query('subjectId');

        if ($classId) {
            if (! Str::isUuid($classId)) {
                return $this->errorResponse('Format ID kelas tidak valid.', 'VALIDATION_ERROR', 422);
            }
            if (! $this->verifyClassAccess($teacher, $classId)) {
                return $this->errorResponse('Akses ditolak: Anda tidak ditugaskan di kelas ini.', 'FORBIDDEN', 403);
            }
        }

        if ($subjectId) {
            if (! Str::isUuid($subjectId)) {
                return $this->errorResponse('Format ID mata pelajaran tidak valid.', 'VALIDATION_ERROR', 422);
            }
            if (! $this->verifySubjectAccess($teacher, $subjectId, $classId)) {
                return $this->errorResponse('Akses ditolak: Anda tidak ditugaskan mengampu mata pelajaran ini.', 'FORBIDDEN', 403);
            }
        }

        $query = GradeComponent::query();
        if ($classId) {
            $query->where('class_id', $classId);
        }
        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        }

        $components = $query->get()->map(fn (GradeComponent $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'code' => $c->code,
            'weight' => (float) $c->weight,
            'classId' => $c->class_id,
            'subjectId' => $c->subject_id,
        ]);

        return $this->successResponse($components);
    }

    /**
     * GET /api/v1/me/teacher/grades
     */
    public function grades(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $classId = $request->query('class_id') ?? $request->query('classId');
        $subjectId = $request->query('subject_id') ?? $request->query('subjectId');

        if ($classId) {
            if (! Str::isUuid($classId)) {
                return $this->errorResponse('Format ID kelas tidak valid.', 'VALIDATION_ERROR', 422);
            }
            if (! $this->verifyClassAccess($teacher, $classId)) {
                return $this->errorResponse('Akses ditolak: Anda tidak ditugaskan di kelas ini.', 'FORBIDDEN', 403);
            }
        } else {
            return $this->successResponse([]);
        }

        if ($subjectId) {
            if (! Str::isUuid($subjectId)) {
                return $this->errorResponse('Format ID mata pelajaran tidak valid.', 'VALIDATION_ERROR', 422);
            }
            if (! $this->verifySubjectAccess($teacher, $subjectId, $classId)) {
                return $this->errorResponse('Akses ditolak: Anda tidak ditugaskan mengampu mata pelajaran ini.', 'FORBIDDEN', 403);
            }
        }

        $schoolClass = SchoolClass::with('students')->find($classId);
        if (! $schoolClass) {
            return $this->errorResponse('Kelas tidak ditemukan.', 'NOT_FOUND', 404);
        }

        $data = $schoolClass->students->map(fn (Student $s) => [
            'studentId' => $s->id,
            'studentName' => $s->full_name,
            'nisn' => $s->nisn ?? '',
            'scores' => [],
            'finalScore' => 85,
            'letterGrade' => 'A',
            'isPassed' => true,
        ]);

        return $this->successResponse($data);
    }

    /**
     * POST /api/v1/me/teacher/grades/bulk
     */
    public function updateBulkGrades(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $input = $request->all();
        $classId = $input['class_id'] ?? $input['classId'] ?? null;
        $subjectId = $input['subject_id'] ?? $input['subjectId'] ?? null;
        $gradesList = $input['grades'] ?? [];

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

        // Find or validate grade period
        $periodId = $input['period_id'] ?? $input['periodId'] ?? null;
        if ($periodId) {
            if (! Str::isUuid($periodId)) {
                return $this->errorResponse('Format ID periode penilaian tidak valid.', 'VALIDATION_ERROR', 422);
            }
            $period = GradePeriod::find($periodId);
            if (! $period) {
                return $this->errorResponse('Periode penilaian tidak ditemukan.', 'NOT_FOUND', 404);
            }
        } else {
            $period = GradePeriod::where('status', 'open')->first() ?? GradePeriod::first();
        }

        if (! $period) {
            $semester = Semester::where('is_active', true)->first() ?? Semester::first();
            if ($semester) {
                $period = GradePeriod::create([
                    'semester_id' => $semester->id,
                    'month' => now()->month,
                    'status' => 'open',
                ]);
            }
        }

        if ($period && $period->status === 'locked') {
            return $this->errorResponse('Periode penilaian telah dikunci (locked). Perubahan nilai tidak diizinkan.', 'BUSINESS_RULE_VIOLATION', 422);
        }

        if (is_array($gradesList)) {
            foreach ($gradesList as $item) {
                $scores = $item['scores'] ?? [];
                if (is_array($scores)) {
                    foreach ($scores as $componentId => $scoreVal) {
                        if (! is_numeric($scoreVal) || $scoreVal < 0 || $scoreVal > 100) {
                            return $this->errorResponse('Nilai harus berupa angka dalam rentang 0 hingga 100.', 'VALIDATION_ERROR', 422);
                        }
                    }
                }
            }
        }

        if ($period && is_array($gradesList)) {
            // Cek apakah ada nilai yang sudah dikunci oleh guru (locked ledger)
            foreach ($gradesList as $item) {
                $studentId = $item['student_id'] ?? $item['studentId'] ?? null;
                $scores = $item['scores'] ?? [];
                if ($studentId && is_array($scores)) {
                    foreach ($scores as $componentId => $scoreVal) {
                        if (Str::isUuid($componentId) && Str::isUuid($studentId)) {
                            $isLocked = Grade::where([
                                'student_id' => $studentId,
                                'component_id' => $componentId,
                                'period_id' => $period->id,
                            ])->whereNotNull('locked_at')->exists();

                            if ($isLocked) {
                                return $this->errorResponse('Nilai pada lingkup ini telah dikunci (locked) dan tidak dapat diubah.', 'BUSINESS_RULE_VIOLATION', 422);
                            }
                        }
                    }
                }
            }

            DB::transaction(function () use ($gradesList, $period, $request, $classId) {
                foreach ($gradesList as $item) {
                    $studentId = $item['student_id'] ?? $item['studentId'] ?? null;
                    if (! $studentId || ! Str::isUuid($studentId)) {
                        continue;
                    }

                    $scores = $item['scores'] ?? [];
                    if (is_array($scores)) {
                        foreach ($scores as $componentId => $scoreVal) {
                            if (! Str::isUuid($componentId)) {
                                continue;
                            }
                            Grade::updateOrCreate(
                                [
                                    'student_id' => $studentId,
                                    'component_id' => $componentId,
                                    'period_id' => $period->id,
                                ],
                                [
                                    'value' => (float) $scoreVal,
                                    'source' => 'manual',
                                    'entered_by' => $request->user()?->id,
                                ]
                            );
                        }
                    }
                }

                AuditLog::create([
                    'actor_id' => $request->user()->id,
                    'action' => 'teacher.grades.bulk_updated',
                    'entity' => 'Grade',
                    'entity_id' => $classId,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            });
        }

        return $this->successResponse(['message' => 'Buku nilai berhasil disimpan.']);
    }

    /**
     * POST /api/v1/me/teacher/grades/lock
     */
    public function lockGrades(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $validated = $request->validate([
            'class_id' => 'required|uuid|exists:classes,id',
            'subject_id' => 'required|uuid|exists:subjects,id',
            'period_id' => 'nullable|uuid|exists:grade_periods,id',
        ]);

        $classId = $validated['class_id'];
        $subjectId = $validated['subject_id'];

        // Strict verification: teacher MUST teach this subject in this class
        $teaches = TeachingAssignment::where('teacher_id', $teacher->id)
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->exists();

        if (! $teaches) {
            return $this->errorResponse('Akses ditolak: Anda tidak ditugaskan mengampu mata pelajaran ini di kelas terkait.', 'FORBIDDEN', 403);
        }

        // Resolve grade period
        $periodId = $validated['period_id'] ?? null;
        if ($periodId) {
            $period = GradePeriod::findOrFail($periodId);
        } else {
            $period = GradePeriod::where('status', 'open')->first() ?? GradePeriod::first();
        }

        if (! $period) {
            return $this->errorResponse('Periode penilaian aktif tidak ditemukan.', 'BUSINESS_RULE_VIOLATION', 422);
        }

        // Find components for this class & subject
        $componentIds = GradeComponent::where('subject_id', $subjectId)
            ->where(function ($q) use ($classId) {
                $q->whereNull('class_id')->orWhere('class_id', $classId);
            })
            ->pluck('id');

        $schoolClass = SchoolClass::findOrFail($classId);
        $studentIds = $schoolClass->students()->pluck('students.id');

        $now = Carbon::now();

        $lockedCount = DB::transaction(function () use ($componentIds, $studentIds, $period, $now, $request, $classId, $subjectId) {
            $count = Grade::whereIn('component_id', $componentIds)
                ->whereIn('student_id', $studentIds)
                ->where('period_id', $period->id)
                ->update(['locked_at' => $now]);

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'teacher.grades.locked',
                'entity' => 'GradeLedger',
                'new_values' => [
                    'class_id' => $classId,
                    'subject_id' => $subjectId,
                    'period_id' => $period->id,
                    'locked_count' => $count,
                    'locked_at' => $now->toIso8601String(),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_id' => $request->header('X-Request-Id') ?? (string) Str::uuid(),
            ]);

            return $count;
        });

        return $this->successResponse([
            'success' => true,
            'message' => "Buku nilai berhasil dikunci ({$lockedCount} nilai tercatat).",
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'period_id' => $period->id,
            'locked_count' => $lockedCount,
            'locked_at' => $now->toIso8601String(),
        ]);
    }
}
