<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ClassStudent;
use App\Models\GradeLevel;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PromotionService
{
    /**
     * BE-MST-08: Preview student promotions and graduations.
     */
    public function previewPromotions(array $data): array
    {
        $sourceAcademicYearId = $data['source_academic_year_id'];
        $sourceClassId = $data['source_class_id'] ?? null;
        $defaultTargetClassId = $data['default_target_class_id'] ?? null;

        // 1. Highest grade level (for graduation auto-detection)
        $maxGradeSort = GradeLevel::max('sort_order');

        // 2. Query source classes
        $classesQuery = SchoolClass::with(['gradeLevel'])->where('academic_year_id', $sourceAcademicYearId);
        if ($sourceClassId) {
            $classesQuery->where('id', $sourceClassId);
        }
        $classes = $classesQuery->get();

        if ($classes->isEmpty()) {
            throw new HttpException(404, 'Tidak ada rombongan belajar yang ditemukan pada tahun ajaran asal.', null, [], 404);
        }

        // 3. Pre-load overrides map by student_id
        $overrides = [];
        if (! empty($data['overrides'])) {
            foreach ($data['overrides'] as $ov) {
                $overrides[$ov['student_id']] = $ov;
            }
        }

        // 4. Default target class model if specified
        $defaultTargetClass = $defaultTargetClassId ? SchoolClass::find($defaultTargetClassId) : null;

        // 5. Collect students and preview items
        $previewItems = [];
        $targetClassCounts = []; // target_class_id => count
        $warnings = [];

        $totalPromote = 0;
        $totalRetain = 0;
        $totalGraduate = 0;
        $totalDropOut = 0;

        foreach ($classes as $class) {
            $isFinalGrade = ($maxGradeSort !== null && $class->gradeLevel?->sort_order === $maxGradeSort);

            // Active students in this class
            $activeStudents = $class->students()
                ->wherePivotNull('left_at')
                ->orderBy('full_name', 'asc')
                ->get();

            foreach ($activeStudents as $student) {
                $hasOverride = isset($overrides[$student->id]);
                $override = $hasOverride ? $overrides[$student->id] : null;

                // Determine action
                if ($hasOverride && ! empty($override['action'])) {
                    $action = $override['action'];
                } else {
                    $action = $isFinalGrade ? 'graduate' : 'promote';
                }

                // Determine target class
                $targetClassId = null;
                $targetClassName = null;

                if (in_array($action, ['promote', 'retain'])) {
                    if ($hasOverride && ! empty($override['target_class_id'])) {
                        $targetClassId = $override['target_class_id'];
                        $targetClassModel = SchoolClass::find($targetClassId);
                        $targetClassName = $targetClassModel?->name;
                    } elseif ($defaultTargetClass) {
                        $targetClassId = $defaultTargetClass->id;
                        $targetClassName = $defaultTargetClass->name;
                    }
                }

                $status = 'valid';
                $message = null;

                // Status check
                if ($student->status !== 'aktif') {
                    $status = 'warning';
                    $message = "Status siswa saat ini '{$student->status}' (bukan aktif).";
                    $warnings[] = "Siswa {$student->full_name} ({$student->nis}) saat ini berstatus '{$student->status}'.";
                }

                // Missing target class warning for promote/retain
                if (in_array($action, ['promote', 'retain']) && ! $targetClassId) {
                    $status = 'warning';
                    $message = 'Kelas tujuan belum ditentukan.';
                    $warnings[] = "Siswa {$student->full_name} ({$action}) belum memiliki kelas tujuan.";
                }

                // Tally target class count for capacity simulation
                if ($targetClassId) {
                    $targetClassCounts[$targetClassId] = ($targetClassCounts[$targetClassId] ?? 0) + 1;
                }

                // Increment action counters
                match ($action) {
                    'promote' => $totalPromote++,
                    'retain' => $totalRetain++,
                    'graduate' => $totalGraduate++,
                    'drop_out' => $totalDropOut++,
                    default => null,
                };

                $previewItems[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->full_name,
                    'nis' => $student->nis,
                    'nisn' => $student->nisn,
                    'current_class_id' => $class->id,
                    'current_class_name' => $class->name,
                    'action' => $action,
                    'target_class_id' => $targetClassId,
                    'target_class_name' => $targetClassName,
                    'notes' => $override['notes'] ?? null,
                    'status' => $status,
                    'message' => $message,
                ];
            }
        }

        // 6. Simulate target class capacities
        foreach ($targetClassCounts as $tcId => $incomingCount) {
            $tClass = SchoolClass::find($tcId);
            if ($tClass) {
                $currentActive = $tClass->students()->wherePivotNull('left_at')->count();
                $available = $tClass->capacity - $currentActive;

                if ($incomingCount > $available) {
                    $warnings[] = "Kapasitas kelas tujuan '{$tClass->name}' tidak mencukupi (Tersisa {$available} kursi, dialokasikan {$incomingCount} siswa).";
                }
            }
        }

        return [
            'summary' => [
                'total_students' => count($previewItems),
                'total_promote' => $totalPromote,
                'total_retain' => $totalRetain,
                'total_graduate' => $totalGraduate,
                'total_drop_out' => $totalDropOut,
                'has_warnings' => ! empty($warnings),
                'warnings' => array_values(array_unique($warnings)),
            ],
            'students' => $previewItems,
        ];
    }

    /**
     * BE-MST-08: Execute student promotions and graduations.
     */
    public function executePromotions(array $data, Request $request): array
    {
        return DB::transaction(function () use ($data, $request) {
            $effectiveDate = $data['effective_date'];
            $promotions = $data['promotions'];

            // 1. Capacity pre-check for all target classes
            $targetCounts = [];
            foreach ($promotions as $p) {
                if (in_array($p['action'], ['promote', 'retain']) && ! empty($p['target_class_id'])) {
                    $tcId = $p['target_class_id'];
                    $targetCounts[$tcId] = ($targetCounts[$tcId] ?? 0) + 1;
                }
            }

            foreach ($targetCounts as $tcId => $incoming) {
                $targetClass = SchoolClass::findOrFail($tcId);
                $currentActive = $targetClass->students()->wherePivotNull('left_at')->count();
                $available = $targetClass->capacity - $currentActive;

                if ($incoming > $available) {
                    throw new HttpException(
                        400,
                        "Kapasitas kelas '{$targetClass->name}' tidak mencukupi (Tersisa {$available} kursi, diminta mendaftarkan {$incoming} siswa).",
                        null,
                        [],
                        400
                    );
                }
            }

            $promoted = 0;
            $retained = 0;
            $graduated = 0;
            $droppedOut = 0;

            foreach ($promotions as $item) {
                $studentId = $item['student_id'];
                $currentClassId = $item['current_class_id'];
                $action = $item['action'];
                $targetClassId = $item['target_class_id'] ?? null;
                $notes = $item['notes'] ?? null;

                $student = Student::findOrFail($studentId);

                // Close active enrollment in current class
                ClassStudent::where('class_id', $currentClassId)
                    ->where('student_id', $studentId)
                    ->whereNull('left_at')
                    ->update(['left_at' => $effectiveDate]);

                if (in_array($action, ['promote', 'retain'])) {
                    if (! $targetClassId) {
                        throw new HttpException(
                            400,
                            "Siswa '{$student->full_name}' harus memiliki target_class_id untuk aksi '{$action}'.",
                            null,
                            [],
                            400
                        );
                    }

                    // Create new class enrollment
                    ClassStudent::create([
                        'class_id' => $targetClassId,
                        'student_id' => $studentId,
                        'joined_at' => $effectiveDate,
                    ]);

                    if ($action === 'promote') {
                        $promoted++;
                    } else {
                        $retained++;
                    }
                } elseif ($action === 'graduate') {
                    // Update status to 'lulus'
                    $student->update(['status' => 'lulus']);

                    StudentStatusHistory::create([
                        'student_id' => $studentId,
                        'status' => 'lulus',
                        'reason' => $notes ?? 'Kelulusan akhir jenjang',
                        'effective_date' => $effectiveDate,
                    ]);

                    $graduated++;
                } elseif ($action === 'drop_out') {
                    // Update status to 'keluar'
                    $student->update(['status' => 'keluar']);

                    StudentStatusHistory::create([
                        'student_id' => $studentId,
                        'status' => 'keluar',
                        'reason' => $notes ?? 'Mutasi / keluar',
                        'effective_date' => $effectiveDate,
                    ]);

                    $droppedOut++;
                }
            }

            $summary = [
                'total_processed' => count($promotions),
                'total_promoted' => $promoted,
                'total_retained' => $retained,
                'total_graduated' => $graduated,
                'total_dropped_out' => $droppedOut,
                'effective_date' => $effectiveDate,
            ];

            $this->logAudit(
                $request->user()?->id,
                'promotion.executed',
                'Promotion',
                null,
                null,
                $summary,
                $request
            );

            return [
                'message' => 'Proses kenaikan kelas dan kelulusan berhasil dieksekusi.',
                'summary' => $summary,
            ];
        });
    }

    /**
     * BE-MST-08: Rollback executed promotions or graduations.
     */
    public function rollbackPromotions(array $data, Request $request): array
    {
        return DB::transaction(function () use ($data, $request) {
            $studentIds = $data['student_ids'];
            $reason = $data['reason'];
            $rolledBackCount = 0;

            foreach ($studentIds as $studentId) {
                $student = Student::findOrFail($studentId);

                // 1. Remove newer active class enrollment (if promoted/retained)
                $latestActiveEnrollment = ClassStudent::where('student_id', $studentId)
                    ->whereNull('left_at')
                    ->latest('joined_at')
                    ->first();

                // 2. Find previous closed enrollment
                $previousClosedEnrollment = ClassStudent::where('student_id', $studentId)
                    ->whereNotNull('left_at')
                    ->latest('left_at')
                    ->first();

                if ($latestActiveEnrollment && $previousClosedEnrollment) {
                    $latestActiveEnrollment->delete();
                    $previousClosedEnrollment->update(['left_at' => null]);
                } elseif ($previousClosedEnrollment && in_array($student->status, ['lulus', 'keluar'])) {
                    // Reopen previous class enrollment
                    $previousClosedEnrollment->update(['left_at' => null]);
                }

                // 3. Reset student status back to 'aktif' if graduated or dropped out
                if (in_array($student->status, ['lulus', 'keluar'])) {
                    $student->update(['status' => 'aktif']);

                    StudentStatusHistory::create([
                        'student_id' => $studentId,
                        'status' => 'aktif',
                        'reason' => 'Rollback proses kelulusan/mutasi: '.$reason,
                        'effective_date' => now()->toDateString(),
                    ]);
                }

                $rolledBackCount++;
            }

            $summary = [
                'total_rolled_back' => $rolledBackCount,
                'reason' => $reason,
            ];

            $this->logAudit(
                $request->user()?->id,
                'promotion.rolled_back',
                'Promotion',
                null,
                null,
                $summary,
                $request
            );

            return [
                'message' => 'Pembatalan proses kenaikan kelas / kelulusan berhasil.',
                'summary' => $summary,
            ];
        });
    }

    /**
     * Helper to write an audit log entry.
     */
    protected function logAudit(
        ?string $actorId,
        string $action,
        string $entity,
        ?string $entityId,
        ?array $oldValue,
        ?array $newValue,
        Request $request
    ): void {
        try {
            AuditLog::create([
                'actor_id' => $actorId,
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
