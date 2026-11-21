<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SubjectAssignmentService
{
    /**
     * BE-MST-06: List subjects with pagination and assignment count.
     */
    public function listSubjects(Request $request): LengthAwarePaginator
    {
        $limit = min($request->integer('limit', 20), 100);
        $group = $request->query('subject_group');
        $q = trim((string) $request->query('q', $request->query('search', '')));

        $query = Subject::withCount('teachingAssignments');

        if ($group) {
            $query->where('subject_group', $group);
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('code', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%");
            });
        }

        return $query->orderBy('code', 'asc')->paginate($limit);
    }

    /**
     * BE-MST-06: Get single subject detail.
     */
    public function getSubject(string $id): Subject
    {
        return Subject::with([
            'teachingAssignments.teacher',
            'teachingAssignments.class',
        ])->findOrFail($id);
    }

    /**
     * BE-MST-06: Create subject.
     */
    public function createSubject(array $data, Request $request): Subject
    {
        return DB::transaction(function () use ($data, $request) {
            $subject = Subject::create([
                'school_id' => $data['school_id'] ?? $request->user()?->school_id,
                'code' => $data['code'],
                'name' => $data['name'],
                'subject_group' => $data['subject_group'],
            ]);

            $this->logAudit(
                $request->user()?->id,
                'subject.created',
                'Subject',
                $subject->id,
                null,
                $subject->toArray(),
                $request
            );

            return $subject;
        });
    }

    /**
     * BE-MST-06: Update subject.
     */
    public function updateSubject(string $id, array $data, Request $request): Subject
    {
        return DB::transaction(function () use ($id, $data, $request) {
            $subject = Subject::findOrFail($id);
            $oldValue = $subject->toArray();

            $fields = ['code', 'name', 'subject_group'];
            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    $subject->{$field} = $data[$field];
                }
            }

            $subject->save();

            $this->logAudit(
                $request->user()?->id,
                'subject.updated',
                'Subject',
                $subject->id,
                $oldValue,
                $subject->toArray(),
                $request
            );

            return $subject;
        });
    }

    /**
     * BE-MST-06: Delete subject (Soft delete).
     */
    public function deleteSubject(string $id, Request $request): void
    {
        $subject = Subject::findOrFail($id);

        if ($subject->teachingAssignments()->exists()) {
            throw new HttpException(
                409,
                'Mata pelajaran tidak dapat dihapus karena masih memiliki penugasan guru terikat.',
                null,
                [],
                409
            );
        }

        $oldValue = $subject->toArray();
        $subject->delete();

        $this->logAudit(
            $request->user()?->id,
            'subject.deleted',
            'Subject',
            $subject->id,
            $oldValue,
            null,
            $request
        );
    }

    // =========================================================================
    // TEACHING ASSIGNMENTS
    // =========================================================================

    /**
     * BE-MST-06: List teaching assignments with filters and relations.
     */
    public function listTeachingAssignments(Request $request): LengthAwarePaginator
    {
        $limit = min($request->integer('limit', 20), 100);
        $semesterId = $request->query('semester_id');
        $classId = $request->query('class_id');
        $teacherId = $request->query('teacher_id');
        $subjectId = $request->query('subject_id');

        $query = TeachingAssignment::with([
            'semester',
            'class',
            'subject',
            'teacher',
        ]);

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        if ($classId) {
            $query->where('class_id', $classId);
        }

        if ($teacherId) {
            $query->where('teacher_id', $teacherId);
        }

        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        }

        return $query->orderBy('created_at', 'desc')->paginate($limit);
    }

    /**
     * BE-MST-06: Get single teaching assignment.
     */
    public function getTeachingAssignment(string $id): TeachingAssignment
    {
        return TeachingAssignment::with([
            'semester',
            'class',
            'subject',
            'teacher',
        ])->findOrFail($id);
    }

    /**
     * BE-MST-06: Create teaching assignment.
     * Enforces unique (semester_id, class_id, subject_id).
     */
    public function createTeachingAssignment(array $data, Request $request): TeachingAssignment
    {
        return DB::transaction(function () use ($data, $request) {
            $exists = TeachingAssignment::where('semester_id', $data['semester_id'])
                ->where('class_id', $data['class_id'])
                ->where('subject_id', $data['subject_id'])
                ->exists();

            if ($exists) {
                throw new HttpException(
                    409,
                    'Mata pelajaran ini sudah memiliki penugasan guru pada kelas dan semester yang dipilih.',
                    null,
                    [],
                    409
                );
            }

            $assignment = TeachingAssignment::create([
                'semester_id' => $data['semester_id'],
                'class_id' => $data['class_id'],
                'subject_id' => $data['subject_id'],
                'teacher_id' => $data['teacher_id'],
                'hours_per_week' => $data['hours_per_week'] ?? 2,
            ]);

            $this->logAudit(
                $request->user()?->id,
                'teaching_assignment.created',
                'TeachingAssignment',
                $assignment->id,
                null,
                $assignment->toArray(),
                $request
            );

            return $assignment;
        });
    }

    /**
     * BE-MST-06: Batch assign multiple teaching assignments.
     */
    public function batchAssign(array $data, Request $request): array
    {
        return DB::transaction(function () use ($data, $request) {
            $semesterId = $data['semester_id'];
            $items = $data['assignments'];
            $created = [];

            foreach ($items as $item) {
                $assignment = TeachingAssignment::updateOrCreate(
                    [
                        'semester_id' => $semesterId,
                        'class_id' => $item['class_id'],
                        'subject_id' => $item['subject_id'],
                    ],
                    [
                        'teacher_id' => $item['teacher_id'],
                        'hours_per_week' => $item['hours_per_week'] ?? 2,
                    ]
                );
                $created[] = $assignment;
            }

            $this->logAudit(
                $request->user()?->id,
                'teaching_assignment.batch_assigned',
                'TeachingAssignment',
                null,
                null,
                ['count' => count($created), 'semester_id' => $semesterId],
                $request
            );

            return [
                'total' => count($created),
                'assignments' => $created,
            ];
        });
    }

    /**
     * BE-MST-06: Update teaching assignment.
     */
    public function updateTeachingAssignment(string $id, array $data, Request $request): TeachingAssignment
    {
        return DB::transaction(function () use ($id, $data, $request) {
            $assignment = TeachingAssignment::findOrFail($id);
            $oldValue = $assignment->toArray();

            if (isset($data['teacher_id'])) {
                $assignment->teacher_id = $data['teacher_id'];
            }

            if (isset($data['hours_per_week'])) {
                $assignment->hours_per_week = $data['hours_per_week'];
            }

            $assignment->save();

            $this->logAudit(
                $request->user()?->id,
                'teaching_assignment.updated',
                'TeachingAssignment',
                $assignment->id,
                $oldValue,
                $assignment->toArray(),
                $request
            );

            return $assignment;
        });
    }

    /**
     * BE-MST-06: Delete teaching assignment.
     */
    public function deleteTeachingAssignment(string $id, Request $request): void
    {
        $assignment = TeachingAssignment::findOrFail($id);

        $hasSchedules = Schedule::where('semester_id', $assignment->semester_id)
            ->where('class_id', $assignment->class_id)
            ->where('subject_id', $assignment->subject_id)
            ->exists();

        if ($hasSchedules) {
            throw new HttpException(
                409,
                'Penugasan mengajar tidak dapat dihapus karena sudah memiliki jadwal pelajaran terikat.',
                null,
                [],
                409
            );
        }

        $oldValue = $assignment->toArray();
        $assignment->delete();

        $this->logAudit(
            $request->user()?->id,
            'teaching_assignment.deleted',
            'TeachingAssignment',
            $assignment->id,
            $oldValue,
            null,
            $request
        );
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
