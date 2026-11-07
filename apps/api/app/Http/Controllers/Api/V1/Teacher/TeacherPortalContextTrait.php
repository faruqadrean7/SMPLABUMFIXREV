<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\TimeSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

trait TeacherPortalContextTrait
{
    protected function getTeacher(Request $request): ?Teacher
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        return $user->teacher;
    }

    /**
     * Verifies that the teacher teaches or is homeroom of the specified class.
     */
    protected function verifyClassAccess(Teacher $teacher, string $classId): bool
    {
        if (! Str::isUuid($classId)) {
            return false;
        }

        $teaches = TeachingAssignment::where('teacher_id', $teacher->id)
            ->where('class_id', $classId)
            ->exists();

        $isHomeroom = SchoolClass::where('id', $classId)
            ->where('homeroom_teacher_id', $teacher->id)
            ->exists();

        $hasSchedule = Schedule::where('teacher_id', $teacher->id)
            ->where('class_id', $classId)
            ->exists();

        return $teaches || $isHomeroom || $hasSchedule;
    }

    /**
     * Verifies that the teacher is authorized to teach the specified subject (optionally scoped to class).
     */
    protected function verifySubjectAccess(Teacher $teacher, string $subjectId, ?string $classId = null): bool
    {
        if (! Str::isUuid($subjectId)) {
            return false;
        }

        $assignmentQuery = TeachingAssignment::where('teacher_id', $teacher->id)
            ->where('subject_id', $subjectId);

        $scheduleQuery = Schedule::where('teacher_id', $teacher->id)
            ->where('subject_id', $subjectId);

        if ($classId) {
            if (! Str::isUuid($classId)) {
                return false;
            }
            $assignmentQuery->where('class_id', $classId);
            $scheduleQuery->where('class_id', $classId);
        }

        return $assignmentQuery->exists() || $scheduleQuery->exists();
    }

    /**
     * Verifies that the teacher teaches the student or is homeroom teacher of the student's class.
     */
    protected function verifyStudentAccess(Teacher $teacher, string $studentId): bool
    {
        if (! Str::isUuid($studentId)) {
            return false;
        }

        $student = Student::find($studentId);
        if (! $student) {
            return false;
        }

        $studentClassIds = $student->classes()->pluck('classes.id');

        $isHomeroom = SchoolClass::whereIn('id', $studentClassIds)
            ->where('homeroom_teacher_id', $teacher->id)
            ->exists();

        if ($isHomeroom) {
            return true;
        }

        return TeachingAssignment::where('teacher_id', $teacher->id)
            ->whereIn('class_id', $studentClassIds)
            ->exists();
    }

    /**
     * Resolves an existing or creates an authorized schedule for the teacher, strictly scoped to their assignments.
     */
    protected function resolveScheduleForTeacher(Teacher $teacher, ?string $scheduleId = null, ?string $classId = null, ?string $subjectId = null): ?Schedule
    {
        // 1. Try by schedule_id if provided (MUST belong to this teacher)
        if ($scheduleId) {
            if (! Str::isUuid($scheduleId)) {
                return null;
            }

            $schedule = Schedule::where('id', $scheduleId)
                ->where('teacher_id', $teacher->id)
                ->first();

            if (! $schedule) {
                return null;
            }

            if ($classId && Str::isUuid($classId) && $schedule->class_id !== $classId) {
                return null;
            }

            if ($subjectId && Str::isUuid($subjectId) && $schedule->subject_id !== $subjectId) {
                return null;
            }

            return $schedule;
        }

        // 2. If schedule_id is not provided, both classId and subjectId must be valid UUIDs
        if (! $classId || ! Str::isUuid($classId) || ! $subjectId || ! Str::isUuid($subjectId)) {
            return null;
        }

        if (! $this->verifyClassAccess($teacher, $classId) || ! $this->verifySubjectAccess($teacher, $subjectId, $classId)) {
            return null;
        }

        $semester = Semester::where('is_active', true)->first() ?? Semester::first();

        $scheduleQuery = Schedule::where('teacher_id', $teacher->id)
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId);

        if ($semester) {
            $schedule = (clone $scheduleQuery)->where('semester_id', $semester->id)->first();
            if ($schedule) {
                return $schedule;
            }
        }

        $schedule = $scheduleQuery->first();
        if ($schedule) {
            return $schedule;
        }

        // Auto-provision schedule only for an actively verified teaching assignment
        $timeSlot = TimeSlot::first();
        if ($semester && $timeSlot) {
            return Schedule::firstOrCreate(
                [
                    'teacher_id' => $teacher->id,
                    'semester_id' => $semester->id,
                    'class_id' => $classId,
                    'subject_id' => $subjectId,
                    'time_slot_id' => $timeSlot->id,
                    'day_of_week' => now()->dayOfWeekIso,
                ]
            );
        }

        return null;
    }

    protected function getDayName(int $dayOfWeek): string
    {
        return match ($dayOfWeek) {
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            default => 'Minggu',
        };
    }

    /**
     * Resolves a student's effective learning program for teacher-facing rosters.
     * Priority: student.program_type -> class.program_type -> 'reguler'.
     *
     * @return array{type: string, label: string}
     */
    protected function resolveStudentProgram(Student $student, ?SchoolClass $class = null): array
    {
        $type = $student->program_type ?? $class?->program_type ?? 'reguler';

        $labels = [
            'reguler' => 'Reguler',
            'tahfidz' => 'Tahfidz',
            'icp' => 'ICP',
            'inklusi' => 'Inklusi',
        ];

        return [
            'type' => $type,
            'label' => $labels[$type] ?? 'Reguler',
        ];
    }
}
