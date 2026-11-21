<?php

namespace App\Services;

use App\Models\Staff;
use App\Models\Teacher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class PublicDirectoryService
{
    /**
     * BE-CMS-05: List public teachers with strict privacy whitelisting.
     */
    public function listPublicTeachers(Request $request): LengthAwarePaginator|Collection
    {
        $query = Teacher::query()
            ->where('status', 'aktif')
            ->with([
                'photoFile',
                'teachingAssignments.subject',
            ]);

        if ($search = $request->query('search')) {
            $query->where('full_name', 'like', "%{$search}%");
        }

        if ($position = $request->query('position')) {
            $query->where('position', 'like', "%{$position}%");
        }

        if ($subjectId = $request->query('subject_id')) {
            $query->whereHas('teachingAssignments', function ($sub) use ($subjectId) {
                $sub->where('subject_id', $subjectId);
            });
        } elseif ($subjectName = $request->query('subject')) {
            $query->whereHas('teachingAssignments.subject', function ($sub) use ($subjectName) {
                $sub->where('name', 'like', "%{$subjectName}%");
            });
        }

        if ($schoolId = $request->query('school_id')) {
            $query->where('school_id', $schoolId);
        }

        $query->orderBy('full_name', 'asc');

        if ($request->query('all') === 'true' || $request->query('paginate') === 'false') {
            return $query->get()->map(fn (Teacher $teacher) => $this->transformTeacher($teacher));
        }

        $perPage = min((int) $request->query('per_page', 15), 100);

        return $query->paginate($perPage)->through(fn (Teacher $teacher) => $this->transformTeacher($teacher));
    }

    /**
     * BE-CMS-05: Get single public teacher detail with strict whitelisting.
     */
    public function getPublicTeacher(string $id): array
    {
        $teacher = Teacher::query()
            ->where('status', 'aktif')
            ->with([
                'photoFile',
                'teachingAssignments.subject',
            ])
            ->findOrFail($id);

        return $this->transformTeacher($teacher);
    }

    /**
     * BE-CMS-05: List public staff with strict privacy whitelisting.
     */
    public function listPublicStaff(Request $request): LengthAwarePaginator|Collection
    {
        $query = Staff::query();

        if ($search = $request->query('search')) {
            $query->where('full_name', 'like', "%{$search}%");
        }

        if ($position = $request->query('position')) {
            $query->where('position', 'like', "%{$position}%");
        }

        if ($unit = $request->query('unit')) {
            $query->where('unit', 'like', "%{$unit}%");
        }

        if ($schoolId = $request->query('school_id')) {
            $query->where('school_id', $schoolId);
        }

        $query->orderBy('full_name', 'asc');

        if ($request->query('all') === 'true' || $request->query('paginate') === 'false') {
            return $query->get()->map(fn (Staff $staff) => $this->transformStaff($staff));
        }

        $perPage = min((int) $request->query('per_page', 15), 100);

        return $query->paginate($perPage)->through(fn (Staff $staff) => $this->transformStaff($staff));
    }

    /**
     * Strictly format public teacher data - exclude all sensitive columns.
     */
    public function transformTeacher(Teacher $teacher): array
    {
        $subjects = $teacher->teachingAssignments
            ->map(fn ($assignment) => $assignment->subject)
            ->filter()
            ->unique('id')
            ->values()
            ->map(fn ($subject) => [
                'id' => $subject->id,
                'code' => $subject->code,
                'name' => $subject->name,
            ])
            ->all();

        return [
            'id' => $teacher->id,
            'full_name' => $teacher->full_name,
            'position' => $teacher->position,
            'photo' => $teacher->photoFile ? [
                'id' => $teacher->photoFile->id,
                'object_key' => $teacher->photoFile->object_key,
                'mime_type' => $teacher->photoFile->mime_type,
            ] : null,
            'subjects' => $subjects,
        ];
    }

    /**
     * Strictly format public staff data - exclude internal/user fields.
     */
    public function transformStaff(Staff $staff): array
    {
        return [
            'id' => $staff->id,
            'full_name' => $staff->full_name,
            'position' => $staff->position,
            'unit' => $staff->unit,
        ];
    }
}
