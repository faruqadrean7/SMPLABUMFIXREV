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
use App\Services\GradeRecorder;
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
            $query->where(function ($q) use ($classId) {
                $q->where('class_id', $classId)->orWhereNull('class_id');
            });
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

        $schoolClass = SchoolClass::with(['students' => fn ($q) => $q->wherePivotNull('left_at')])->find($classId);
        if (! $schoolClass) {
            return $this->errorResponse('Kelas tidak ditemukan.', 'NOT_FOUND', 404);
        }

        $user = $request->user();
        if ($user && ! $user->hasRole('super_admin') && ! empty($user->school_id) && ! empty($schoolClass->school_id) && $schoolClass->school_id !== $user->school_id) {
            return $this->errorResponse('Akses ditolak: Kelas berada di luar sekolah penugasan Anda.', 'FORBIDDEN', 403);
        }

        // Buku nilai: kembalikan periode, komponen, dan NILAI SEBENARNYA.
        // Sebelumnya endpoint ini mengembalikan skor karangan (85/A untuk semua siswa).
        $semester = Semester::where('is_active', true)->first() ?? Semester::first();

        // Daftar SEMUA periode pada tahun ajaran aktif untuk pemilih periode di UI.
        $periods = $semester
            ? GradePeriod::where('semester_id', $semester->id)->orderBy('month')->get()
            : collect();

        $periodId = $request->query('period_id') ?? $request->query('periodId');

        $period = null;
        if ($periodId) {
            if (! Str::isUuid($periodId)) {
                return $this->errorResponse('Format ID periode tidak valid.', 'VALIDATION_ERROR', 422);
            }

            $period = $periods->firstWhere('id', $periodId);

            if (! $period) {
                return $this->errorResponse('Periode penilaian tidak ditemukan pada tahun ajaran aktif.', 'NOT_FOUND', 404);
            }
        } elseif ($periods->isNotEmpty()) {
            // Tanpa pilihan eksplisit: pakai periode terbuka paling awal (perilaku lama).
            $period = $periods->firstWhere('status', 'open') ?? $periods->first();
        }

        // P1-14: Sertakan komponen spesifik kelas sekaligus komponen global (class_id IS NULL)
        $components = GradeComponent::query()
            ->where(function ($q) use ($classId) {
                $q->where('class_id', $classId)->orWhereNull('class_id');
            })
            ->where('subject_id', $subjectId)
            ->when($semester, fn ($q) => $q->where('semester_id', $semester->id))
            ->orderBy('code')
            ->get();

        $weightByComponent = $components->pluck('weight', 'id');

        $rawGrades = Grade::query()
            ->whereIn('component_id', $components->pluck('id'))
            ->when($period, fn ($q) => $q->where('period_id', $period->id))
            ->get();

        $scoresByStudent = [];
        foreach ($rawGrades as $grade) {
            $scoresByStudent[$grade->student_id][$grade->component_id] = (float) $grade->value;
        }

        $kktp = 75.0;

        $rows = $schoolClass->students->map(function (Student $s) use ($components, $scoresByStudent, $kktp) {
            $scores = [];
            $weighted = 0.0;
            $totalWeight = 0.0;

            foreach ($components as $component) {
                $value = $scoresByStudent[$s->id][$component->id] ?? null;
                $scores[$component->id] = $value;

                if ($value !== null) {
                    $weight = (float) $component->weight;
                    $weighted += $value * $weight;
                    $totalWeight += $weight;
                }
            }

            $final = $totalWeight > 0 ? round($weighted / $totalWeight, 1) : 0.0;

            return [
                'student_id' => $s->id,
                'nis' => $s->nis ?? '',
                'name' => $s->full_name,
                'gender' => $s->gender === 'P' ? 'P' : 'L',
                'avatar_url' => $s->photoFile?->url ?? null,
                'scores' => $scores,
                'final_score' => $final,
                'is_tuntas' => $final >= $kktp,
                'notes' => null,
            ];
        })->values();

        $kategori = ['TGS' => 'formatif', 'UTS' => 'sumatif_tengah', 'UAS' => 'sumatif_akhir', 'SIKAP' => 'formatif'];

        return $this->successResponse([
            'periods' => $periods->map(fn ($p) => [
                'id' => $p->id,
                'name' => 'Periode Bulan '.$p->month,
                'month' => (int) $p->month,
                'status' => $p->status,
                'is_locked' => $p->status === 'locked',
                'is_selected' => $period && $p->id === $period->id,
            ])->values(),
            'period' => [
                'id' => $period?->id,
                'class_id' => $classId,
                'subject_id' => $subjectId,
                'name' => $period ? ('Periode Bulan '.$period->month) : 'Belum ada periode',
                'academic_year' => $semester?->academicYear?->name,
                'semester' => $semester?->name,
                'is_locked' => $period ? $period->status === 'locked' : false,
                'locked_at' => $period?->locked_at,
            ],
            'components' => $components->map(fn ($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'category' => $kategori[$c->code] ?? 'formatif',
                'weight_percent' => (float) $c->weight,
                'kktp' => $kktp,
            ])->values(),
            'grades' => $rows,
        ]);
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

        // P1-14: Bangun whitelist siswa aktif kelas & komponen sah (spesifik kelas atau global) anti-IDOR
        $schoolClass = SchoolClass::find($classId);
        if (! $schoolClass) {
            return $this->errorResponse('Kelas tidak ditemukan.', 'NOT_FOUND', 404);
        }
        $user = $request->user();
        if ($user && ! $user->hasRole('super_admin') && ! empty($user->school_id) && ! empty($schoolClass->school_id) && $schoolClass->school_id !== $user->school_id) {
            return $this->errorResponse('Akses ditolak: Kelas berada di luar sekolah penugasan Anda.', 'FORBIDDEN', 403);
        }

        $allowedStudentIds = DB::table('class_students')
            ->where('class_id', $classId)
            ->whereNull('left_at')
            ->pluck('student_id')
            ->all();
        $allowedStudentLookup = array_flip($allowedStudentIds);

        $allowedComponentIds = GradeComponent::query()
            ->where('subject_id', $subjectId)
            ->where(function ($q) use ($classId) {
                $q->where('class_id', $classId)->orWhereNull('class_id');
            })
            ->when($period?->semester_id, fn ($q) => $q->where('semester_id', $period->semester_id))
            ->pluck('id')
            ->all();
        $allowedComponentLookup = array_flip($allowedComponentIds);

        if (is_array($gradesList)) {
            foreach ($gradesList as $item) {
                $studentId = $item['student_id'] ?? $item['studentId'] ?? null;
                if ($studentId && Str::isUuid($studentId) && ! isset($allowedStudentLookup[$studentId])) {
                    return $this->errorResponse('Akses ditolak: Terdapat siswa yang tidak terdaftar aktif pada kelas ini.', 'FORBIDDEN', 403);
                }

                $scores = $item['scores'] ?? [];
                if (is_array($scores)) {
                    foreach ($scores as $componentId => $scoreVal) {
                        // Sel kosong (null atau string kosong) dari grid FE dilewati secara aman
                        if ($scoreVal === null || $scoreVal === '') {
                            continue;
                        }
                        if (! Str::isUuid($componentId) || ! isset($allowedComponentLookup[$componentId])) {
                            return $this->errorResponse('Akses ditolak: Komponen nilai berada di luar cakupan mata pelajaran, kelas, atau semester ini.', 'FORBIDDEN', 403);
                        }
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
                        if ($scoreVal === null || $scoreVal === '') {
                            continue;
                        }
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
                            if ($scoreVal === null || $scoreVal === '' || ! Str::isUuid($componentId)) {
                                continue;
                            }
                            // Nilai manual tidak boleh menurunkan nilai otomatis yang
                            // lebih tinggi (kebijakan nilai tertinggi menang).
                            app(GradeRecorder::class)->recordManual(
                                studentId: $studentId,
                                componentId: $componentId,
                                periodId: $period->id,
                                value: (float) $scoreVal,
                                changedBy: $request->user()?->id,
                                note: 'Input manual portal guru',
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
                'ip' => $request->ip(),
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
