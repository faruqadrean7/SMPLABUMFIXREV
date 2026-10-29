<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherDashboardController extends Controller
{
    use ApiResponse;
    use TeacherPortalContextTrait;

    /**
     * GET /api/v1/me/teacher/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $today = now()->dayOfWeekIso;
        $todaySchedules = Schedule::with(['class', 'subject', 'timeSlot'])
            ->where('teacher_id', $teacher->id)
            ->where('day_of_week', $today)
            ->get();

        $classesCount = TeachingAssignment::where('teacher_id', $teacher->id)
            ->distinct('class_id')
            ->count('class_id');

        return $this->successResponse([
            'teacherName' => $teacher->full_name,
            'nip' => $teacher->nip ?? '',
            'photoFileId' => $teacher->photo_file_id,
            'photoUrl' => $teacher->photoFile?->url,
            'assignedClassesCount' => $classesCount,
            'todaySchedulesCount' => $todaySchedules->count(),
            'pendingJournalsCount' => 0,
        ]);
    }

    /**
     * GET /api/v1/me/teacher/classes
     */
    public function classes(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $assignedClassIds = TeachingAssignment::where('teacher_id', $teacher->id)
            ->pluck('class_id')
            ->merge(SchoolClass::where('homeroom_teacher_id', $teacher->id)->pluck('id'))
            ->unique();

        $classes = SchoolClass::withCount('students')
            ->with('gradeLevel')
            ->whereIn('id', $assignedClassIds)
            ->get();

        $data = $classes->map(fn (SchoolClass $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'gradeLevel' => $c->gradeLevel?->sort_order ?? 7,
            'studentCount' => $c->students_count,
        ]);

        return $this->successResponse($data);
    }

    /**
     * GET /api/v1/me/teacher/schedules
     */
    public function schedules(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $schedules = Schedule::with(['class', 'subject', 'room', 'timeSlot'])
            ->where('teacher_id', $teacher->id)
            ->orderBy('day_of_week')
            ->get();

        $data = $schedules->map(fn (Schedule $s) => [
            'id' => $s->id,
            'dayOfWeek' => $s->day_of_week,
            'dayName' => $this->getDayName($s->day_of_week),
            'startTime' => $s->timeSlot?->start_time ? substr($s->timeSlot->start_time, 0, 5) : '07:00',
            'endTime' => $s->timeSlot?->end_time ? substr($s->timeSlot->end_time, 0, 5) : '08:20',
            'classId' => $s->class_id,
            'className' => $s->class?->name ?? 'Kelas',
            'subjectId' => $s->subject_id,
            'subjectName' => $s->subject?->name ?? 'Mata Pelajaran',
            'roomName' => $s->room?->name ?? 'Ruang Kelas',
            'isAttendanceRecorded' => false,
        ]);

        return $this->successResponse($data);
    }

    /**
     * GET /api/v1/me/teacher/classes/{classId}/students
     */
    public function classStudents(Request $request, string $classId): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        if (! $this->verifyClassAccess($teacher, $classId)) {
            return $this->errorResponse('Akses ditolak: Anda tidak ditugaskan di kelas ini.', 'FORBIDDEN', 403);
        }

        $schoolClass = SchoolClass::with('students')->find($classId);
        if (! $schoolClass) {
            return $this->errorResponse('Kelas tidak ditemukan.', 'NOT_FOUND', 404);
        }

        $data = $schoolClass->students->map(fn (Student $s) => [
            'studentId' => $s->id,
            'studentName' => $s->full_name,
            'nisn' => $s->nisn ?? '',
            'nis' => $s->nis ?? '',
            'status' => 'present',
            'notes' => null,
        ]);

        return $this->successResponse($data);
    }
}
