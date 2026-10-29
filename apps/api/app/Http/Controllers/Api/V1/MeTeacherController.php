<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\CounselingReferral;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\ExamQuestion;
use App\Models\ExamRoom;
use App\Models\Facility;
use App\Models\File;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradePeriod;
use App\Models\Journal;
use App\Models\LeaveRequest;
use App\Models\PointType;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\StudentPoint;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\TeachingMaterial;
use App\Models\Ticket;
use App\Models\TimeSlot;
use App\Services\AcademicAttendanceService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MeTeacherController extends Controller
{
    use ApiResponse;

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

    /**
     * GET /api/v1/me/teacher/attendance/sessions
     */
    public function attendanceSessions(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $journals = Journal::with(['schedule.class', 'schedule.subject', 'attendances'])
            ->where('teacher_id', $teacher->id)
            ->latest('session_date')
            ->paginate(15);

        return $this->paginatedResponse($journals);
    }

    /**
     * POST /api/v1/me/teacher/attendance/sessions
     */
    public function recordAttendanceSession(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $input = $request->all();
        $classId = $input['class_id'] ?? $input['classId'] ?? null;
        $subjectId = $input['subject_id'] ?? $input['subjectId'] ?? null;
        $scheduleId = $input['schedule_id'] ?? $input['scheduleId'] ?? null;
        $sessionDate = $input['session_date'] ?? $input['sessionDate'] ?? $input['date'] ?? now()->toDateString();
        $sessionTopic = $input['session_topic'] ?? $input['sessionTopic'] ?? $input['topic'] ?? 'Pembelajaran Tatap Muka';
        $attendeesRaw = $input['attendees'] ?? [];

        // Strict authorization and validation without fallbacks
        if ($scheduleId) {
            if (! Str::isUuid($scheduleId)) {
                return $this->errorResponse('Format ID jadwal tidak valid.', 'VALIDATION_ERROR', 422);
            }
            $schedule = Schedule::where('id', $scheduleId)->where('teacher_id', $teacher->id)->first();
            if (! $schedule) {
                return $this->errorResponse('Jadwal mengajar tidak ditemukan atau bukan milik Anda.', 'FORBIDDEN', 403);
            }
            if ($classId && Str::isUuid($classId) && $schedule->class_id !== $classId) {
                return $this->errorResponse('Jadwal tidak sesuai dengan kelas yang dipilih.', 'VALIDATION_ERROR', 422);
            }
            if ($subjectId && Str::isUuid($subjectId) && $schedule->subject_id !== $subjectId) {
                return $this->errorResponse('Jadwal tidak sesuai dengan mata pelajaran yang dipilih.', 'VALIDATION_ERROR', 422);
            }
        } else {
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

            $schedule = $this->resolveScheduleForTeacher($teacher, null, $classId, $subjectId);
            if (! $schedule) {
                return $this->errorResponse('Jadwal kelas tidak ditemukan.', 'NOT_FOUND', 404);
            }
        }

        $normalizedAttendees = [];
        if (is_array($attendeesRaw)) {
            foreach ($attendeesRaw as $att) {
                $studentId = $att['student_id'] ?? $att['studentId'] ?? null;
                if (! $studentId || ! Str::isUuid($studentId)) {
                    continue;
                }
                $statusRaw = strtolower((string) ($att['status'] ?? 'present'));
                $mappedStatus = match ($statusRaw) {
                    'present', 'hadir' => 'hadir',
                    'sick', 'sakit' => 'sakit',
                    'permission', 'izin' => 'izin',
                    'late', 'terlambat' => 'terlambat',
                    default => 'alpa',
                };
                $notes = $att['notes'] ?? $att['note'] ?? null;

                $normalizedAttendees[] = [
                    'student_id' => $studentId,
                    'status' => $mappedStatus,
                    'notes' => $notes,
                ];
            }
        }

        DB::transaction(function () use ($teacher, $schedule, $sessionDate, $sessionTopic, $normalizedAttendees, $request) {
            $journal = Journal::create([
                'schedule_id' => $schedule->id,
                'teacher_id' => $teacher->id,
                'session_date' => $sessionDate,
                'topic' => $sessionTopic ?: 'Pembelajaran Tatap Muka',
                'method' => 'Tatap Muka',
            ]);

            foreach ($normalizedAttendees as $att) {
                Attendance::updateOrCreate(
                    [
                        'journal_id' => $journal->id,
                        'student_id' => $att['student_id'],
                    ],
                    [
                        'att_date' => $sessionDate,
                        'status' => $att['status'],
                        'source' => 'guru',
                        'note' => $att['notes'],
                        'recorded_by' => $request->user()?->id,
                    ]
                );
            }
        });

        return $this->successResponse(['message' => 'Presensi KBM berhasil disimpan.'], 201);
    }

    /**
     * GET /api/v1/me/teacher/journals
     */
    public function journals(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $journals = Journal::with(['schedule.class', 'schedule.subject'])
            ->where('teacher_id', $teacher->id)
            ->latest('session_date')
            ->get();

        $data = $journals->map(fn (Journal $j) => [
            'id' => $j->id,
            'scheduleId' => $j->schedule_id,
            'schedule_id' => $j->schedule_id,
            'classId' => $j->schedule?->class_id ?? '',
            'class_id' => $j->schedule?->class_id ?? '',
            'className' => $j->schedule?->class?->name ?? 'Kelas',
            'subjectId' => $j->schedule?->subject_id ?? '',
            'subject_id' => $j->schedule?->subject_id ?? '',
            'subjectName' => $j->schedule?->subject?->name ?? 'Mata Pelajaran',
            'date' => $j->session_date?->format('Y-m-d') ?? (string) $j->session_date,
            'session_date' => $j->session_date?->format('Y-m-d') ?? (string) $j->session_date,
            'timeSlot' => '07:00 - 08:20',
            'lessonTopic' => $j->topic ?? '',
            'topic' => $j->topic ?? '',
            'competenceIndicator' => $j->notes ?? '',
            'notes' => $j->notes ?? '',
            'studentAbsentees' => [],
            'learningObstacles' => '',
            'isLocked' => false,
            'createdAt' => $j->created_at?->toISOString() ?? now()->toISOString(),
        ]);

        return $this->successResponse($data);
    }

    /**
     * POST /api/v1/me/teacher/journals
     */
    public function storeJournal(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        // Support both snake_case and camelCase payloads
        $scheduleId = $request->input('schedule_id', $request->input('scheduleId'));
        $classId = $request->input('class_id', $request->input('classId'));
        $subjectId = $request->input('subject_id', $request->input('subjectId'));
        $sessionDate = $request->input('session_date', $request->input('sessionDate', $request->input('date', now()->toDateString())));
        $topic = $request->input('topic', $request->input('lesson_topic', $request->input('lessonTopic', 'Pembelajaran Tatap Muka')));
        $notes = $request->input('notes', $request->input('competence_indicator', $request->input('competenceIndicator', '')));
        $learningObstacles = $request->input('learning_obstacles', $request->input('learningObstacles', ''));
        $method = $request->input('method', 'Tatap Muka');

        if (! empty($learningObstacles)) {
            $notes = trim($notes."\nCatatan Kendala: ".$learningObstacles);
        }

        // Strict authorization and validation without fallbacks
        if ($scheduleId) {
            if (! Str::isUuid($scheduleId)) {
                return $this->errorResponse('Format ID jadwal tidak valid.', 'VALIDATION_ERROR', 422);
            }
            $schedule = Schedule::where('id', $scheduleId)->where('teacher_id', $teacher->id)->first();
            if (! $schedule) {
                return $this->errorResponse('Jadwal pembelajaran tidak ditemukan atau bukan milik Anda.', 'FORBIDDEN', 403);
            }
            if ($classId && Str::isUuid($classId) && $schedule->class_id !== $classId) {
                return $this->errorResponse('Jadwal tidak sesuai dengan kelas yang dipilih.', 'VALIDATION_ERROR', 422);
            }
            if ($subjectId && Str::isUuid($subjectId) && $schedule->subject_id !== $subjectId) {
                return $this->errorResponse('Jadwal tidak sesuai dengan mata pelajaran yang dipilih.', 'VALIDATION_ERROR', 422);
            }
            if (! $this->verifyClassAccess($teacher, $schedule->class_id)) {
                return $this->errorResponse('Akses ditolak: Anda tidak ditugaskan di kelas ini.', 'FORBIDDEN', 403);
            }
            if (! $this->verifySubjectAccess($teacher, $schedule->subject_id, $schedule->class_id)) {
                return $this->errorResponse('Akses ditolak: Anda tidak ditugaskan mengampu mata pelajaran ini di kelas terkait.', 'FORBIDDEN', 403);
            }
        } else {
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

            $schedule = $this->resolveScheduleForTeacher($teacher, null, $classId, $subjectId);
            if (! $schedule) {
                return $this->errorResponse('Jadwal pembelajaran tidak ditemukan atau belum disiapkan.', 'NOT_FOUND', 404);
            }
        }

        $journal = Journal::create([
            'teacher_id' => $teacher->id,
            'schedule_id' => $schedule->id,
            'session_date' => $sessionDate,
            'topic' => $topic ?: 'Topik KBM',
            'method' => $method ?: 'Tatap Muka',
            'notes' => $notes ?: null,
        ]);

        $journal->load(['schedule.class', 'schedule.subject']);

        return $this->successResponse([
            'id' => $journal->id,
            'scheduleId' => $journal->schedule_id,
            'schedule_id' => $journal->schedule_id,
            'classId' => $journal->schedule?->class_id ?? $classId ?? '',
            'class_id' => $journal->schedule?->class_id ?? $classId ?? '',
            'className' => $journal->schedule?->class?->name ?? 'Kelas',
            'subjectId' => $journal->schedule?->subject_id ?? $subjectId ?? '',
            'subject_id' => $journal->schedule?->subject_id ?? $subjectId ?? '',
            'subjectName' => $journal->schedule?->subject?->name ?? 'Mata Pelajaran',
            'date' => $journal->session_date?->format('Y-m-d') ?? (string) $journal->session_date,
            'session_date' => $journal->session_date?->format('Y-m-d') ?? (string) $journal->session_date,
            'timeSlot' => '07:00 - 08:20',
            'lessonTopic' => $journal->topic,
            'topic' => $journal->topic,
            'competenceIndicator' => $journal->notes ?? '',
            'notes' => $journal->notes ?? '',
            'studentAbsentees' => [],
            'learningObstacles' => $learningObstacles,
            'isLocked' => false,
            'createdAt' => $journal->created_at?->toISOString() ?? now()->toISOString(),
        ], 201);
    }

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

    /**
     * GET /api/v1/me/teacher/exams
     */
    public function exams(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $exams = Exam::with(['subject', 'rooms'])
            ->where('created_by', $request->user()->id)
            ->latest()
            ->get();

        $data = $exams->map(fn (Exam $e) => [
            'id' => $e->id,
            'title' => $e->title,
            'subjectName' => $e->subject?->name ?? 'Mata Pelajaran',
            'classNames' => [],
            'durationMinutes' => $e->duration_min ?? 60,
            'totalQuestions' => 30,
            'startTime' => $e->start_at?->toISOString() ?? now()->toISOString(),
            'endTime' => $e->end_at?->toISOString() ?? now()->addHours(2)->toISOString(),
            'isProctor' => false,
            'status' => $e->status ?? 'scheduled',
        ]);

        return $this->successResponse($data);
    }

    /**
     * GET /api/v1/me/teacher/question-banks
     */
    public function questionBanks(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $taughtSubjectIds = TeachingAssignment::where('teacher_id', $teacher->id)->pluck('subject_id');

        $banks = QuestionBank::with(['subject', 'gradeLevel'])
            ->withCount('questions')
            ->where(function ($q) use ($request, $taughtSubjectIds) {
                $q->where('owner_id', $request->user()->id)
                    ->orWhereIn('subject_id', $taughtSubjectIds);
            })
            ->latest()
            ->get();

        return $this->successResponse($banks);
    }

    /**
     * POST /api/v1/me/teacher/question-banks
     */
    public function storeQuestionBank(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject_id' => 'required|uuid',
            'grade_level_id' => 'nullable|uuid',
        ]);

        if (! $this->verifySubjectAccess($teacher, $validated['subject_id'])) {
            return $this->errorResponse('Akses ditolak: Anda tidak ditugaskan mengampu mata pelajaran ini.', 'FORBIDDEN', 403);
        }

        $bank = QuestionBank::create([
            'school_id' => $request->user()->school_id ?? '00000000-0000-0000-0000-000000000001',
            'subject_id' => $validated['subject_id'],
            'grade_level_id' => $validated['grade_level_id'] ?? null,
            'owner_id' => $request->user()->id,
            'name' => $validated['name'],
        ]);

        $bank->load(['subject', 'gradeLevel']);

        return $this->successResponse($bank, 201);
    }

    /**
     * GET /api/v1/me/teacher/question-banks/{id}
     */
    public function showQuestionBank(Request $request, string $id): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $bank = QuestionBank::with(['subject', 'gradeLevel', 'questions.options'])->find($id);
        if (! $bank) {
            return $this->errorResponse('Bank soal tidak ditemukan.', 'NOT_FOUND', 404);
        }

        $taughtSubjectIds = TeachingAssignment::where('teacher_id', $teacher->id)->pluck('subject_id');
        if ($bank->owner_id !== $request->user()->id && ! $taughtSubjectIds->contains($bank->subject_id)) {
            return $this->errorResponse('Akses ditolak: Anda tidak memiliki wewenang pada bank soal ini.', 'FORBIDDEN', 403);
        }

        return $this->successResponse($bank);
    }

    /**
     * PUT /api/v1/me/teacher/question-banks/{id}
     */
    public function updateQuestionBank(Request $request, string $id): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $bank = QuestionBank::find($id);
        if (! $bank) {
            return $this->errorResponse('Bank soal tidak ditemukan.', 'NOT_FOUND', 404);
        }

        if ($bank->owner_id !== $request->user()->id) {
            return $this->errorResponse('Akses ditolak: Anda bukan pemilik bank soal ini.', 'FORBIDDEN', 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'grade_level_id' => 'nullable|uuid',
        ]);

        $bank->update($validated);

        return $this->successResponse($bank);
    }

    /**
     * DELETE /api/v1/me/teacher/question-banks/{id}
     */
    public function destroyQuestionBank(Request $request, string $id): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $bank = QuestionBank::find($id);
        if (! $bank) {
            return $this->errorResponse('Bank soal tidak ditemukan.', 'NOT_FOUND', 404);
        }

        if ($bank->owner_id !== $request->user()->id) {
            return $this->errorResponse('Akses ditolak: Anda bukan pemilik bank soal ini.', 'FORBIDDEN', 403);
        }

        $questionIds = $bank->questions()->pluck('id');
        $isUsedInActiveExam = ExamQuestion::whereIn('question_id', $questionIds)
            ->whereHas('exam', function ($q) {
                $q->whereIn('status', [Exam::STATUS_SCHEDULED, Exam::STATUS_IN_PROGRESS, Exam::STATUS_FINISHED, 'dijadwalkan', 'berlangsung', 'selesai']);
            })
            ->exists();

        if ($isUsedInActiveExam) {
            return $this->errorResponse('Bank soal tidak dapat dihapus karena butir soal telah digunakan dalam ujian.', 'BUSINESS_RULE_VIOLATION', 422);
        }

        DB::transaction(function () use ($bank, $questionIds) {
            QuestionOption::whereIn('question_id', $questionIds)->delete();
            $bank->questions()->delete();
            $bank->delete();
        });

        return $this->successResponse(['message' => 'Bank soal berhasil dihapus.']);
    }

    /**
     * POST /api/v1/me/teacher/question-banks/{id}/questions
     */
    public function storeQuestion(Request $request, string $id): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $bank = QuestionBank::find($id);
        if (! $bank) {
            return $this->errorResponse('Bank soal tidak ditemukan.', 'NOT_FOUND', 404);
        }

        $taughtSubjectIds = TeachingAssignment::where('teacher_id', $teacher->id)->pluck('subject_id');
        if ($bank->owner_id !== $request->user()->id && ! $taughtSubjectIds->contains($bank->subject_id)) {
            return $this->errorResponse('Akses ditolak: Anda tidak memiliki wewenang pada bank soal ini.', 'FORBIDDEN', 403);
        }

        $validated = $request->validate([
            'question_type' => 'required|in:pg,pg_kompleks,bs,menjodohkan,isian,uraian',
            'body' => 'required|array',
            'cognitive_level' => 'nullable|string|max:10',
            'difficulty' => 'nullable|integer|min:1|max:5',
            'weight' => 'nullable|numeric|min:0',
            'explanation' => 'nullable|string',
            'options' => 'nullable|array',
        ]);

        $question = DB::transaction(function () use ($bank, $validated) {
            $q = Question::create([
                'bank_id' => $bank->id,
                'question_type' => $validated['question_type'],
                'body' => $validated['body'],
                'cognitive_level' => $validated['cognitive_level'] ?? 'C2',
                'difficulty' => $validated['difficulty'] ?? 2,
                'weight' => $validated['weight'] ?? 1.0,
                'explanation' => $validated['explanation'] ?? null,
                'version' => 1,
                'status' => 'draft',
            ]);

            if (! empty($validated['options']) && is_array($validated['options'])) {
                foreach ($validated['options'] as $opt) {
                    QuestionOption::create([
                        'question_id' => $q->id,
                        'label' => $opt['label'] ?? null,
                        'body' => $opt['body'] ?? null,
                        'is_correct' => $opt['is_correct'] ?? false,
                        'match_key' => $opt['match_key'] ?? null,
                    ]);
                }
            }

            return $q->load('options');
        });

        return $this->successResponse($question, 201);
    }

    /**
     * GET /api/v1/me/teacher/questions/{id}
     */
    public function showQuestion(Request $request, string $id): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $question = Question::with(['bank', 'options'])->find($id);
        if (! $question || ! $question->bank) {
            return $this->errorResponse('Soal tidak ditemukan.', 'NOT_FOUND', 404);
        }

        $taughtSubjectIds = TeachingAssignment::where('teacher_id', $teacher->id)->pluck('subject_id');
        if ($question->bank->owner_id !== $request->user()->id && ! $taughtSubjectIds->contains($question->bank->subject_id)) {
            return $this->errorResponse('Akses ditolak: Anda tidak memiliki wewenang untuk melihat soal ini.', 'FORBIDDEN', 403);
        }

        return $this->successResponse($question);
    }

    /**
     * PUT /api/v1/me/teacher/questions/{id}
     */
    public function updateQuestion(Request $request, string $id): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $question = Question::with('bank')->find($id);
        if (! $question || ! $question->bank) {
            return $this->errorResponse('Soal tidak ditemukan.', 'NOT_FOUND', 404);
        }

        if ($question->bank->owner_id !== $request->user()->id) {
            return $this->errorResponse('Akses ditolak: Anda bukan pemilik bank soal ini.', 'FORBIDDEN', 403);
        }

        $isUsedInActiveExam = ExamQuestion::where('question_id', $question->id)
            ->whereHas('exam', function ($q) {
                $q->whereIn('status', [Exam::STATUS_SCHEDULED, Exam::STATUS_IN_PROGRESS, Exam::STATUS_FINISHED, 'dijadwalkan', 'berlangsung', 'selesai']);
            })
            ->exists();

        if ($isUsedInActiveExam) {
            return $this->errorResponse('Soal tidak dapat diubah karena sedang atau telah digunakan dalam sesi ujian.', 'BUSINESS_RULE_VIOLATION', 422);
        }

        $validated = $request->validate([
            'body' => 'nullable|array',
            'cognitive_level' => 'nullable|string|max:10',
            'difficulty' => 'nullable|integer|min:1|max:5',
            'weight' => 'nullable|numeric|min:0',
            'explanation' => 'nullable|string',
            'options' => 'nullable|array',
        ]);

        $question = DB::transaction(function () use ($question, $validated) {
            $question->update(array_filter([
                'body' => $validated['body'] ?? null,
                'cognitive_level' => $validated['cognitive_level'] ?? null,
                'difficulty' => $validated['difficulty'] ?? null,
                'weight' => $validated['weight'] ?? null,
                'explanation' => $validated['explanation'] ?? null,
            ]));

            if (isset($validated['options']) && is_array($validated['options'])) {
                $question->options()->delete();
                foreach ($validated['options'] as $opt) {
                    QuestionOption::create([
                        'question_id' => $question->id,
                        'label' => $opt['label'] ?? null,
                        'body' => $opt['body'] ?? null,
                        'is_correct' => $opt['is_correct'] ?? false,
                        'match_key' => $opt['match_key'] ?? null,
                    ]);
                }
            }

            return $question->load('options');
        });

        return $this->successResponse($question);
    }

    /**
     * DELETE /api/v1/me/teacher/questions/{id}
     */
    public function destroyQuestion(Request $request, string $id): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $question = Question::with('bank')->find($id);
        if (! $question || ! $question->bank) {
            return $this->errorResponse('Soal tidak ditemukan.', 'NOT_FOUND', 404);
        }

        if ($question->bank->owner_id !== $request->user()->id) {
            return $this->errorResponse('Akses ditolak: Anda bukan pemilik bank soal ini.', 'FORBIDDEN', 403);
        }

        $isUsedInActiveExam = ExamQuestion::where('question_id', $question->id)
            ->whereHas('exam', function ($q) {
                $q->whereIn('status', [Exam::STATUS_SCHEDULED, Exam::STATUS_IN_PROGRESS, Exam::STATUS_FINISHED, 'dijadwalkan', 'berlangsung', 'selesai']);
            })
            ->exists();

        if ($isUsedInActiveExam) {
            return $this->errorResponse('Soal tidak dapat diubah karena sedang atau telah digunakan dalam sesi ujian.', 'BUSINESS_RULE_VIOLATION', 422);
        }

        DB::transaction(function () use ($question) {
            $question->options()->delete();
            $question->delete();
        });

        return $this->successResponse(['message' => 'Soal berhasil dihapus.']);
    }

    /**
     * POST /api/v1/me/teacher/exams
     */
    public function storeExam(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subject_id' => 'required|uuid',
            'class_ids' => 'required|array|min:1',
            'class_ids.*' => 'required|uuid',
            'bank_id' => 'required|uuid',
            'duration_min' => 'required|integer|min:5|max:300',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'passing_grade' => 'nullable|numeric|min:0|max:100',
            'exam_type' => 'nullable|string|in:formatif,sumatif,sts,sas,kuis',
        ]);

        if (! $this->verifySubjectAccess($teacher, $validated['subject_id'])) {
            return $this->errorResponse('Akses ditolak: Anda tidak ditugaskan mengampu mata pelajaran ini.', 'FORBIDDEN', 403);
        }

        foreach ($validated['class_ids'] as $classId) {
            if (! $this->verifyClassAccess($teacher, $classId)) {
                return $this->errorResponse("Akses ditolak: Anda tidak ditugaskan di kelas {$classId}.", 'FORBIDDEN', 403);
            }
        }

        $bank = QuestionBank::find($validated['bank_id']);
        if (! $bank) {
            return $this->errorResponse('Bank soal tidak ditemukan.', 'NOT_FOUND', 404);
        }

        $exam = DB::transaction(function () use ($validated, $bank, $request) {
            $exam = Exam::create([
                'school_id' => $request->user()->school_id ?? '00000000-0000-0000-0000-000000000001',
                'subject_id' => $validated['subject_id'],
                'created_by' => $request->user()->id,
                'title' => $validated['title'],
                'exam_type' => $validated['exam_type'] ?? 'formatif',
                'duration_min' => $validated['duration_min'],
                'start_at' => $validated['start_at'],
                'end_at' => $validated['end_at'],
                'passing_grade' => $validated['passing_grade'] ?? 75.0,
                'status' => 'dijadwalkan',
                'shuffle_questions' => true,
                'shuffle_options' => true,
            ]);

            // Assign questions from bank
            $questions = $bank->questions()->get();
            foreach ($questions as $idx => $q) {
                ExamQuestion::create([
                    'exam_id' => $exam->id,
                    'question_id' => $q->id,
                    'sort_order' => $idx + 1,
                    'weight_override' => $q->weight ?? 1.0,
                ]);
            }

            // Create initial exam room
            $room = Room::first();
            $examRoom = ExamRoom::create([
                'exam_id' => $exam->id,
                'room_id' => $room?->id,
                'proctor_id' => $request->user()->id,
                'session_no' => 1,
                'token' => strtoupper(Str::random(6)),
                'token_expires_at' => $exam->end_at,
            ]);

            // Assign participants from the selected classes
            $students = Student::whereHas('classes', function ($q) use ($validated) {
                $q->whereIn('classes.id', $validated['class_ids']);
            })->get();

            foreach ($students as $stu) {
                ExamParticipant::firstOrCreate([
                    'exam_id' => $exam->id,
                    'student_id' => $stu->id,
                    'exam_room_id' => $examRoom->id,
                ]);
            }

            return $exam;
        });

        $exam->load(['subject', 'questions.question']);

        return $this->successResponse($exam, 201);
    }

    /**
     * GET /api/v1/me/teacher/leave-requests
     */
    public function leaveRequests(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $leaves = LeaveRequest::where('requested_by', $request->user()->id)
            ->latest()
            ->get();

        $data = $leaves->map(fn (LeaveRequest $l) => [
            'id' => $l->id,
            'leaveType' => $l->leave_type,
            'startDate' => $l->start_date?->format('Y-m-d') ?? (string) $l->start_date,
            'endDate' => $l->end_date?->format('Y-m-d') ?? (string) $l->end_date,
            'daysCount' => Carbon::parse($l->start_date)->diffInDays(Carbon::parse($l->end_date)) + 1,
            'reason' => $l->reason,
            'status' => match ($l->status) {
                'disetujui' => 'approved',
                'ditolak' => 'rejected',
                default => 'pending',
            },
            'reviewNote' => $l->decision_notes,
            'createdAt' => $l->created_at?->toISOString() ?? now()->toISOString(),
        ]);

        return $this->successResponse($data);
    }

    /**
     * POST /api/v1/me/teacher/leave-requests
     */
    public function storeLeaveRequest(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $validated = $request->validate([
            'leave_type' => 'nullable|string',
            'leaveType' => 'nullable|string',
            'start_date' => 'nullable|date',
            'startDate' => 'nullable|date',
            'end_date' => 'nullable|date',
            'endDate' => 'nullable|date',
            'reason' => 'required|string|max:1000',
        ]);

        $rawType = $validated['leave_type'] ?? ($validated['leaveType'] ?? 'izin');
        $leaveType = match ($rawType) {
            'sick', 'Sakit' => 'sakit',
            'duty', 'Dispensasi', 'dispen' => 'dispen',
            'annual', 'family', 'Izin', 'izin' => 'izin',
            default => $rawType,
        };
        $startDate = $validated['start_date'] ?? ($validated['startDate'] ?? now()->toDateString());
        $endDate = $validated['end_date'] ?? ($validated['endDate'] ?? now()->toDateString());

        $leave = LeaveRequest::create([
            'student_id' => null,
            'requested_by' => $request->user()->id,
            'leave_type' => $leaveType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => $validated['reason'],
            'status' => 'menunggu',
        ]);

        return $this->successResponse($leave, 201);
    }

    /**
     * GET /api/v1/me/teacher/self-attendance
     */
    public function selfAttendance(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $today = now()->toDateString();
        $record = StaffAttendance::where('user_id', $request->user()->id)
            ->whereDate('att_date', $today)
            ->first();

        $workHours = StaffAttendance::where('user_id', $request->user()->id)
            ->whereMonth('att_date', now()->month)
            ->whereYear('att_date', now()->year)
            ->count() * 8;

        return $this->successResponse([
            'todayDate' => $today,
            'checkInTime' => $record?->check_in?->format('H:i:s'),
            'checkOutTime' => $record?->check_out?->format('H:i:s'),
            'status' => $record ? ($record->status ?? 'present') : 'not_checked_in',
            'workHoursThisMonth' => $workHours,
            'targetWorkHours' => 160,
            'attendancePercentage' => 95.0,
        ]);
    }

    /**
     * POST /api/v1/me/teacher/self-attendance
     */
    public function recordSelfAttendance(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $validated = $request->validate([
            'action' => 'required|in:check_in,check_out',
        ]);

        $today = now()->toDateString();
        $record = StaffAttendance::where('user_id', $request->user()->id)
            ->whereDate('att_date', $today)
            ->first();

        if (! $record) {
            $record = new StaffAttendance([
                'user_id' => $request->user()->id,
                'att_date' => $today,
            ]);
        }

        if ($validated['action'] === 'check_in') {
            $record->check_in = now();
            $record->status = 'hadir';
        } else {
            $record->check_out = now();
        }
        $record->save();

        return $this->successResponse([
            'success' => true,
            'message' => 'Presensi berhasil dicatat.',
            'timestamp' => now()->format('H:i:s'),
        ]);
    }

    /**
     * GET /api/v1/me/teacher/homeroom
     */
    public function homeroom(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $homeroomClass = $teacher->homeroomClasses()
            ->with(['students', 'academicYear'])
            ->first();

        if (! $homeroomClass) {
            return $this->errorResponse('Akses ditolak: Anda bukan wali kelas pada periode ini.', 'FORBIDDEN', 403);
        }

        $students = $homeroomClass->students->map(fn (Student $s, $idx) => [
            'id' => $s->id,
            'name' => $s->full_name,
            'nisn' => $s->nisn ?? '',
            'nis' => $s->nis ?? '',
            'attendanceRate' => 98.0,
            'averageScore' => 86.5,
            'rank' => $idx + 1,
        ]);

        return $this->successResponse([
            'class_id' => $homeroomClass->id,
            'classId' => $homeroomClass->id,
            'className' => $homeroomClass->name,
            'totalStudents' => $students->count(),
            'academicYear' => $homeroomClass->academicYear?->name ?? '2024/2025',
            'semester' => 'Ganjil',
            'attendanceRecap' => [
                'averageRate' => 98.0,
                'sickDays' => 2,
                'leaveDays' => 1,
                'unexcusedDays' => 0,
            ],
            'characterRecapCount' => $students->count(),
            'isReportCardLocked' => false,
            'students' => $students,
        ]);
    }

    private function getDayName(int $dayOfWeek): string
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

    // =========================================================================
    // FITUR A: PERIZINAN SISWA OLEH WALI KELAS
    // =========================================================================

    /**
     * GET /api/v1/me/teacher/homeroom/leave-requests
     */
    public function homeroomLeaveRequests(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $homeroomClassIds = SchoolClass::where('homeroom_teacher_id', $teacher->id)->pluck('id');

        $classId = $request->query('class_id', $request->query('classId'));
        if ($classId) {
            if (! Str::isUuid($classId) || ! $homeroomClassIds->contains($classId)) {
                return $this->errorResponse('Akses ditolak: Anda bukan wali kelas untuk kelas ini.', 'FORBIDDEN', 403);
            }
            $targetClassIds = collect([$classId]);
        } else {
            if ($homeroomClassIds->isEmpty()) {
                return $this->paginatedResponse(new LengthAwarePaginator([], 0, 15));
            }
            $targetClassIds = $homeroomClassIds;
        }

        $query = LeaveRequest::with(['student.classes', 'attachmentFile', 'decisionMaker', 'requester'])
            ->whereNotNull('student_id')
            ->whereHas('student.classes', fn ($q) => $q->whereIn('classes.id', $targetClassIds));

        if ($request->filled('status')) {
            $status = $request->query('status');
            $canonicalStatus = match ($status) {
                'approved' => 'disetujui',
                'rejected' => 'ditolak',
                'pending' => 'menunggu',
                default => $status,
            };
            $query->where('status', $canonicalStatus);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('start_date', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('end_date', '<=', $request->query('date_to'));
        }

        if ($request->filled('q')) {
            $keyword = trim($request->query('q'));
            $query->whereHas('student', function ($sub) use ($keyword) {
                $sub->where('full_name', 'like', "%{$keyword}%")
                    ->orWhere('nis', 'like', "%{$keyword}%");
            });
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $paginator = $query->latest('created_at')->paginate($perPage);

        return $this->paginatedResponse($paginator);
    }

    /**
     * PATCH /api/v1/me/teacher/homeroom/leave-requests/{id}/decision
     */
    public function decideHomeroomLeaveRequest(Request $request, string $id): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $leave = LeaveRequest::with(['student.classes'])->find($id);
        if (! $leave) {
            return $this->errorResponse('Data permohonan izin tidak ditemukan.', 'NOT_FOUND', 404);
        }

        // Verify teacher is homeroom for this student's class
        $isWalas = SchoolClass::where('homeroom_teacher_id', $teacher->id)
            ->whereHas('students', fn ($q) => $q->where('students.id', $leave->student_id))
            ->exists();

        if (! $isWalas) {
            return $this->errorResponse('Akses ditolak: Hanya wali kelas dari siswa bersangkutan yang berhak memutuskan izin.', 'FORBIDDEN', 403);
        }

        if ($leave->status !== 'menunggu') {
            return $this->errorResponse('Permohonan izin ini sudah diputuskan sebelumnya dan tidak dapat diubah kembali.', 'BUSINESS_RULE_VIOLATION', 422);
        }

        $validated = $request->validate([
            'decision' => 'nullable|in:approved,rejected,disetujui,ditolak',
            'status' => 'nullable|in:approved,rejected,disetujui,ditolak',
            'decision_notes' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        $rawDecision = $validated['decision'] ?? ($validated['status'] ?? null);
        if (! $rawDecision) {
            return $this->errorResponse('Keputusan permohonan izin wajib diisi (approved atau rejected).', 'VALIDATION_ERROR', 422);
        }

        $canonicalStatus = match ($rawDecision) {
            'approved', 'disetujui' => 'disetujui',
            'rejected', 'ditolak' => 'ditolak',
            default => $rawDecision,
        };

        $notes = $validated['decision_notes'] ?? ($validated['notes'] ?? null);

        $decidedLeave = app(AcademicAttendanceService::class)->decideLeaveRequest(
            $leave->id,
            [
                'status' => $canonicalStatus,
                'decision_notes' => $notes,
            ],
            $request
        );

        return $this->successResponse($decidedLeave);
    }

    // =========================================================================
    // FITUR B: POIN KARAKTER DAN RUJUKAN BK
    // =========================================================================

    /**
     * POST /api/v1/me/teacher/student-points
     */
    public function storeStudentPoint(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $validated = $request->validate([
            'student_id' => 'required|uuid|exists:students,id',
            'point_type_id' => 'nullable|uuid|exists:point_types,id',
            'type' => 'nullable|string|in:prestasi,pelanggaran',
            'kind' => 'nullable|string|in:prestasi,pelanggaran',
            'category' => 'nullable|string|max:100',
            'points' => 'nullable|integer',
            'notes' => 'nullable|string|max:1000',
            'description' => 'nullable|string|max:1000',
            'point_date' => 'nullable|date',
            'date' => 'nullable|date',
            'evidence_file_id' => 'nullable|uuid|exists:files,id',
            'idempotency_key' => 'nullable|string|max:100',
        ]);

        $studentId = $validated['student_id'];

        if (! $this->verifyStudentAccess($teacher, $studentId)) {
            return $this->errorResponse('Akses ditolak: Anda tidak memiliki wewenang mengajar atau menjadi wali kelas untuk siswa ini.', 'FORBIDDEN', 403);
        }

        $notes = $validated['notes'] ?? ($validated['description'] ?? 'Catatan poin siswa');
        $pointDate = $validated['point_date'] ?? ($validated['date'] ?? now()->toDateString());
        $pointTypeId = $validated['point_type_id'] ?? null;

        if (! $pointTypeId) {
            $kind = $validated['type'] ?? ($validated['kind'] ?? 'pelanggaran');
            $pointType = PointType::where('kind', $kind)->first();
            if (! $pointType) {
                $pointType = PointType::create([
                    'school_id' => $request->user()->school_id ?? '00000000-0000-0000-0000-000000000001',
                    'kind' => $kind,
                    'name' => $validated['category'] ?? ($kind === 'prestasi' ? 'Prestasi Siswa' : 'Pelanggaran Disiplin'),
                    'points' => $validated['points'] ?? ($kind === 'prestasi' ? 10 : 5),
                    'severity' => 'ringan',
                ]);
            }
            $pointTypeId = $pointType->id;
        } else {
            $pointType = PointType::findOrFail($pointTypeId);
        }

        $idempotencyKey = $request->header('X-Idempotency-Key')
            ?? $request->header('Idempotency-Key')
            ?? ($validated['idempotency_key'] ?? null);

        // Normalize payload representation for tamper & mismatch detection
        $payloadHash = hash('sha256', json_encode([
            'student_id' => $studentId,
            'point_type_id' => $pointTypeId,
            'point_date' => $pointDate,
            'notes' => $notes,
            'evidence_file_id' => $validated['evidence_file_id'] ?? null,
        ]));

        if ($idempotencyKey) {
            $cacheKey = "idemp:student_point:{$request->user()->id}:{$idempotencyKey}";
            $lockKey = "lock:idemp:student_point:{$request->user()->id}:{$idempotencyKey}";

            try {
                // 1. Check existing idempotent cache entry before acquiring lock
                $cached = Cache::get($cacheKey);
                if ($cached) {
                    $cachedData = is_array($cached) ? $cached : ['point_id' => $cached, 'payload_hash' => $payloadHash];
                    if (isset($cachedData['payload_hash']) && $cachedData['payload_hash'] !== $payloadHash) {
                        return $this->errorResponse(
                            'Idempotency-Key telah digunakan untuk request dengan parameter yang berbeda.',
                            'IDEMPOTENCY_CONFLICT',
                            422
                        );
                    }
                    $cachedPoint = StudentPoint::with(['student', 'pointType'])->find($cachedData['point_id']);
                    if ($cachedPoint) {
                        return response()->json([
                            'status' => 'success',
                            'data' => $cachedPoint,
                            'meta' => ['idempotent_replayed' => true],
                        ], 200);
                    }
                }

                // 2. Acquire atomic lock for parallel request concurrency protection
                $lock = Cache::lock($lockKey, 10);
                $acquired = $lock->block(3);
                if (! $acquired) {
                    return $this->errorResponse(
                        'Permintaan serupa sedang diproses secara bersamaan. Silakan tunggu.',
                        'CONCURRENT_REQUEST',
                        409
                    );
                }
            } catch (LockTimeoutException $e) {
                return $this->errorResponse(
                    'Permintaan serupa sedang diproses secara bersamaan. Silakan tunggu.',
                    'CONCURRENT_REQUEST',
                    409
                );
            } catch (\Throwable $e) {
                report($e);

                return $this->errorResponse(
                    'Layanan idempotensi tidak dapat diakses untuk menjamin integritas transaksi.',
                    'IDEMPOTENCY_UNAVAILABLE',
                    503
                );
            }

            try {
                // Double-checked locking pattern: re-check cache inside acquired lock
                $cached = Cache::get($cacheKey);
                if ($cached) {
                    $cachedData = is_array($cached) ? $cached : ['point_id' => $cached, 'payload_hash' => $payloadHash];
                    if (isset($cachedData['payload_hash']) && $cachedData['payload_hash'] !== $payloadHash) {
                        return $this->errorResponse(
                            'Idempotency-Key telah digunakan untuk request dengan parameter yang berbeda.',
                            'IDEMPOTENCY_CONFLICT',
                            422
                        );
                    }
                    $cachedPoint = StudentPoint::with(['student', 'pointType'])->find($cachedData['point_id']);
                    if ($cachedPoint) {
                        return response()->json([
                            'status' => 'success',
                            'data' => $cachedPoint,
                            'meta' => ['idempotent_replayed' => true],
                        ], 200);
                    }
                }

                $studentPoint = DB::transaction(function () use ($studentId, $pointType, $request, $validated, $pointDate, $notes) {
                    $point = StudentPoint::create([
                        'student_id' => $studentId,
                        'point_type_id' => $pointType->id,
                        'recorded_by' => $request->user()->id,
                        'evidence_file_id' => $validated['evidence_file_id'] ?? null,
                        'point_date' => $pointDate,
                        'notes' => $notes,
                    ]);

                    AuditLog::create([
                        'actor_id' => $request->user()->id,
                        'action' => 'teacher.student_point.created',
                        'entity' => 'StudentPoint',
                        'entity_id' => $point->id,
                        'new_values' => $point->toArray(),
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'request_id' => $request->header('X-Request-Id') ?? (string) Str::uuid(),
                    ]);

                    return $point;
                });

                // Persist idempotency record with payload hash (24h TTL)
                Cache::put($cacheKey, [
                    'point_id' => $studentPoint->id,
                    'payload_hash' => $payloadHash,
                    'created_at' => now()->toIso8601String(),
                ], now()->addHours(24));

                return $this->successResponse($studentPoint->load(['student', 'pointType']), 201);
            } finally {
                $lock->release();
            }
        } else {
            // Anti-double-click / short retry deduplication window (2s)
            $fingerprint = hash('sha256', "{$request->user()->id}:{$studentId}:{$pointTypeId}:{$pointDate}:{$notes}");
            $debounceKey = "debounce:student_point:{$fingerprint}";
            $debouncedPointId = Cache::get($debounceKey);
            if ($debouncedPointId) {
                $debouncedPoint = StudentPoint::with(['student', 'pointType'])->find($debouncedPointId);
                if ($debouncedPoint) {
                    return response()->json([
                        'status' => 'success',
                        'data' => $debouncedPoint,
                        'meta' => ['debounced_replayed' => true],
                    ], 200);
                }
            }

            $studentPoint = DB::transaction(function () use ($studentId, $pointType, $request, $validated, $pointDate, $notes) {
                $point = StudentPoint::create([
                    'student_id' => $studentId,
                    'point_type_id' => $pointType->id,
                    'recorded_by' => $request->user()->id,
                    'evidence_file_id' => $validated['evidence_file_id'] ?? null,
                    'point_date' => $pointDate,
                    'notes' => $notes,
                ]);

                AuditLog::create([
                    'actor_id' => $request->user()->id,
                    'action' => 'teacher.student_point.created',
                    'entity' => 'StudentPoint',
                    'entity_id' => $point->id,
                    'new_values' => $point->toArray(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'request_id' => $request->header('X-Request-Id') ?? (string) Str::uuid(),
                ]);

                return $point;
            });

            Cache::put($debounceKey, $studentPoint->id, now()->addSeconds(2));

            return $this->successResponse($studentPoint->load(['student', 'pointType']), 201);
        }
    }

    /**
     * POST /api/v1/me/teacher/counseling-referrals
     */
    public function storeCounselingReferral(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $validated = $request->validate([
            'student_id' => 'required|uuid|exists:students,id',
            'reason' => 'required|string|min:5|max:2000',
            'priority' => 'nullable|string|in:biasa,segera,normal,tinggi',
            'notes' => 'nullable|string|max:1000',
        ]);

        $studentId = $validated['student_id'];

        if (! $this->verifyStudentAccess($teacher, $studentId)) {
            return $this->errorResponse('Akses ditolak: Anda tidak memiliki wewenang mengajar atau menjadi wali kelas untuk siswa ini.', 'FORBIDDEN', 403);
        }

        $reason = $validated['reason'];
        if (! empty($validated['priority'])) {
            $reason = "[Prioritas: {$validated['priority']}] ".$reason;
        }

        $existingPending = CounselingReferral::where('student_id', $studentId)
            ->where('referred_by', $request->user()->id)
            ->where('status', 'pending')
            ->where('reason', $reason)
            ->first();

        if ($existingPending) {
            return $this->errorResponse('Rujukan serupa untuk siswa ini masih berstatus pending dan sedang menunggu tindak lanjut BK.', 'BUSINESS_RULE_VIOLATION', 422);
        }

        $referral = DB::transaction(function () use ($studentId, $request, $reason) {
            $ref = CounselingReferral::create([
                'student_id' => $studentId,
                'referred_by' => $request->user()->id,
                'reason' => $reason,
                'status' => 'pending',
            ]);

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'teacher.counseling_referral.created',
                'entity' => 'CounselingReferral',
                'entity_id' => $ref->id,
                'new_values' => $ref->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_id' => $request->header('X-Request-Id') ?? (string) Str::uuid(),
            ]);

            return $ref;
        });

        return $this->successResponse($referral->load(['student']), 201);
    }

    // =========================================================================
    // FITUR C: PEMINJAMAN FASILITAS DAN TIKET SARPRAS
    // =========================================================================

    /**
     * GET /api/v1/me/teacher/facility-bookings
     */
    public function facilityBookings(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $query = Booking::with(['facility', 'items.facility'])
            ->where('requested_by', $request->user()->id);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $bookings = $query->latest('start_at')->get();

        $data = $bookings->map(fn (Booking $b) => [
            'id' => $b->id,
            'facility_id' => $b->facility_id,
            'facility_name' => $b->facility?->name ?? 'Fasilitas',
            'facility' => $b->facility?->name ?? 'Fasilitas',
            'start_at' => $b->start_at?->toISOString() ?? (string) $b->start_at,
            'end_at' => $b->end_at?->toISOString() ?? (string) $b->end_at,
            'date' => $b->start_at?->toDateString(),
            'time_slot' => $b->start_at && $b->end_at ? $b->start_at->format('H:i').' - '.$b->end_at->format('H:i') : '',
            'purpose' => $b->purpose,
            'participant_count' => $b->attendees_count ?? 1,
            'attendees_count' => $b->attendees_count ?? 1,
            'status' => $b->status,
            'decision_notes' => $b->decision_notes,
            'notes' => $b->decision_notes ?? '',
            'created_at' => $b->created_at?->toISOString(),
        ]);

        return $this->successResponse($data);
    }

    /**
     * POST /api/v1/me/teacher/facility-bookings
     */
    public function storeFacilityBooking(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $validated = $request->validate([
            'facility_id' => 'required|uuid|exists:facilities,id',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'purpose' => 'required|string|max:1000',
            'attendees_count' => 'nullable|integer|min:1',
            'participant_count' => 'nullable|integer|min:1',
        ]);

        $startAt = Carbon::parse($validated['start_at']);
        $endAt = Carbon::parse($validated['end_at']);
        $facilityId = $validated['facility_id'];

        $facility = Facility::find($facilityId);
        if (! $facility || ! ($facility->is_active ?? true)) {
            return $this->errorResponse('Fasilitas tidak tersedia untuk dipinjam.', 'BUSINESS_RULE_VIOLATION', 422);
        }

        // Concurrency / Schedule Conflict Check
        $hasConflict = Booking::where('facility_id', $facilityId)
            ->whereIn('status', ['menunggu', 'disetujui', 'approved', 'pending'])
            ->where(function ($q) use ($startAt, $endAt) {
                $q->where('start_at', '<', $endAt)
                    ->where('end_at', '>', $startAt);
            })
            ->exists();

        if ($hasConflict) {
            return $this->errorResponse('Fasilitas sudah dipesan pada rentang waktu tersebut.', 'CONFLICT', 409);
        }

        $attendees = $validated['attendees_count'] ?? ($validated['participant_count'] ?? 1);

        $booking = DB::transaction(function () use ($request, $facility, $startAt, $endAt, $validated, $attendees) {
            $b = Booking::create([
                'school_id' => $request->user()->school_id ?? '00000000-0000-0000-0000-000000000001',
                'facility_id' => $facility->id,
                'requested_by' => $request->user()->id,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'purpose' => $validated['purpose'],
                'attendees_count' => $attendees,
                'status' => 'menunggu',
            ]);

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'teacher.booking.created',
                'entity' => 'Booking',
                'entity_id' => $b->id,
                'new_values' => $b->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_id' => $request->header('X-Request-Id') ?? (string) Str::uuid(),
            ]);

            return $b;
        });

        return $this->successResponse($booking->load('facility'), 201);
    }

    /**
     * POST /api/v1/me/teacher/tickets
     */
    public function storeTicket(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:200',
            'subject' => 'nullable|string|max:200',
            'category' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'description' => 'required|string|max:2500',
            'priority' => 'nullable|string|in:rendah,sedang,tinggi,darurat,normal',
            'room_id' => 'nullable|uuid|exists:rooms,id',
            'asset_id' => 'nullable|uuid|exists:assets,id',
            'photo_file_id' => 'nullable|uuid|exists:files,id',
        ]);

        $rawPriority = $validated['priority'] ?? 'sedang';
        $priority = match ($rawPriority) {
            'normal' => 'sedang',
            default => $rawPriority,
        };

        $title = $validated['title'] ?? ($validated['subject'] ?? 'Laporan Gangguan Sarpras');
        if (! empty($validated['category'])) {
            $title = "[{$validated['category']}] {$title}";
        }

        $desc = $validated['description'];
        if (! empty($validated['location'])) {
            $desc = "Lokasi: {$validated['location']}\n".$desc;
        }

        $ticket = DB::transaction(function () use ($request, $title, $desc, $priority, $validated) {
            $ticketNumber = 'TKT-'.date('Ymd').'-'.strtoupper(Str::random(4));

            $t = Ticket::create([
                'school_id' => $request->user()->school_id ?? '00000000-0000-0000-0000-000000000001',
                'ticket_number' => $ticketNumber,
                'title' => Str::limit($title, 200, ''),
                'reporter_id' => $request->user()->id,
                'room_id' => $validated['room_id'] ?? null,
                'asset_id' => $validated['asset_id'] ?? null,
                'description' => $desc,
                'priority' => $priority,
                'status' => 'pending',
                'photo_file_id' => $validated['photo_file_id'] ?? null,
            ]);

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'teacher.ticket.created',
                'entity' => 'Ticket',
                'entity_id' => $t->id,
                'new_values' => $t->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_id' => $request->header('X-Request-Id') ?? (string) Str::uuid(),
            ]);

            return $t;
        });

        return $this->successResponse($ticket, 201);
    }

    // =========================================================================
    // FITUR D: BANK SOAL MANDIRI GURU
    // =========================================================================

    /**
     * GET /api/v1/me/teacher/questions
     */
    public function questions(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $taughtSubjectIds = TeachingAssignment::where('teacher_id', $teacher->id)->pluck('subject_id');

        $query = Question::with(['bank.subject', 'options'])
            ->whereHas('bank', function ($b) use ($request, $taughtSubjectIds) {
                $b->where('owner_id', $request->user()->id)
                    ->orWhereIn('subject_id', $taughtSubjectIds);
            });

        if ($request->filled('subject_id')) {
            $subjectId = $request->query('subject_id');
            if (! Str::isUuid($subjectId)) {
                return $this->errorResponse('Format ID mata pelajaran tidak valid.', 'VALIDATION_ERROR', 422);
            }

            // Verify teacher has access to this subject
            $hasAccess = $taughtSubjectIds->contains($subjectId) ||
                QuestionBank::where('subject_id', $subjectId)->where('owner_id', $request->user()->id)->exists();

            if (! $hasAccess) {
                return $this->errorResponse('Akses ditolak: Anda tidak memiliki wewenang untuk mata pelajaran ini.', 'FORBIDDEN', 403);
            }

            $query->whereHas('bank', fn ($b) => $b->where('subject_id', $subjectId));
        }

        if ($request->filled('cognitive_level')) {
            $query->where('cognitive_level', $request->query('cognitive_level'));
        }

        if ($request->filled('type') || $request->filled('question_type')) {
            $rawType = $request->query('type') ?? $request->query('question_type');
            $type = match ($rawType) {
                'benar_salah' => 'bs',
                default => $rawType,
            };
            $query->where('question_type', $type);
        }

        if ($request->filled('difficulty')) {
            $diff = $request->query('difficulty');
            $diffVal = match ($diff) {
                'mudah' => 1,
                'sedang' => 2,
                'sulit' => 3,
                default => is_numeric($diff) ? (int) $diff : null,
            };
            if ($diffVal !== null) {
                $query->where('difficulty', $diffVal);
            }
        }

        if ($request->filled('q') || $request->filled('search')) {
            $search = trim($request->query('q') ?? $request->query('search'));
            $query->where('body', 'like', "%{$search}%");
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $paginator = $query->latest()->paginate($perPage);

        return $this->paginatedResponse($paginator);
    }

    /**
     * POST /api/v1/me/teacher/questions
     */
    public function storeDirectQuestion(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $validated = $request->validate([
            'subject_id' => 'nullable|uuid|exists:subjects,id',
            'bank_id' => 'nullable|uuid|exists:question_banks,id',
            'question_type' => 'required|in:pg,pg_kompleks,bs,benar_salah,menjodohkan,isian,uraian',
            'body' => 'nullable',
            'question_text' => 'nullable|string',
            'cognitive_level' => 'nullable|string|max:10',
            'difficulty' => 'nullable',
            'weight' => 'nullable|numeric|min:0',
            'points' => 'nullable|numeric|min:0',
            'explanation' => 'nullable|string',
            'options' => 'nullable|array',
        ]);

        $bank = null;
        if (! empty($validated['bank_id'])) {
            $bank = QuestionBank::find($validated['bank_id']);
            if (! $bank) {
                return $this->errorResponse('Bank soal tidak ditemukan.', 'NOT_FOUND', 404);
            }

            $taughtSubjectIds = TeachingAssignment::where('teacher_id', $teacher->id)->pluck('subject_id');
            if ($bank->owner_id !== $request->user()->id && ! $taughtSubjectIds->contains($bank->subject_id)) {
                return $this->errorResponse('Akses ditolak: Anda tidak memiliki wewenang pada bank soal ini.', 'FORBIDDEN', 403);
            }
        } else {
            $subjectId = $validated['subject_id'] ?? null;
            if (! $subjectId) {
                return $this->errorResponse('Wajib menyertakan bank_id atau subject_id.', 'VALIDATION_ERROR', 422);
            }

            if (! $this->verifySubjectAccess($teacher, $subjectId)) {
                return $this->errorResponse('Akses ditolak: Anda tidak ditugaskan mengampu mata pelajaran ini.', 'FORBIDDEN', 403);
            }

            $bank = QuestionBank::firstOrCreate(
                [
                    'owner_id' => $request->user()->id,
                    'subject_id' => $subjectId,
                ],
                [
                    'name' => 'Bank Soal Mandiri',
                    'school_id' => $request->user()->school_id ?? '00000000-0000-0000-0000-000000000001',
                ]
            );
        }

        // Normalize body
        $body = $validated['body'] ?? null;
        if (is_string($body)) {
            $body = ['text' => $body];
        } elseif (! is_array($body) && ! empty($validated['question_text'])) {
            $body = ['text' => $validated['question_text']];
        } elseif (! is_array($body)) {
            $body = ['text' => 'Soal'];
        }

        $rawType = $validated['question_type'];
        $questionType = match ($rawType) {
            'benar_salah' => 'bs',
            default => $rawType,
        };

        $rawDiff = $validated['difficulty'] ?? 2;
        $difficulty = match ($rawDiff) {
            'mudah' => 1,
            'sedang' => 2,
            'sulit' => 3,
            default => is_numeric($rawDiff) ? (int) $rawDiff : 2,
        };

        $weight = $validated['weight'] ?? ($validated['points'] ?? 1.0);

        $question = DB::transaction(function () use ($bank, $questionType, $body, $validated, $difficulty, $weight) {
            $q = Question::create([
                'bank_id' => $bank->id,
                'question_type' => $questionType,
                'body' => $body,
                'cognitive_level' => $validated['cognitive_level'] ?? 'MOTS',
                'difficulty' => $difficulty,
                'weight' => $weight,
                'explanation' => $validated['explanation'] ?? null,
                'version' => 1,
                'status' => 'draft',
            ]);

            if (! empty($validated['options']) && is_array($validated['options'])) {
                foreach ($validated['options'] as $opt) {
                    $optBody = $opt['body'] ?? ($opt['text'] ?? null);
                    if (is_string($optBody)) {
                        $optBody = ['text' => $optBody];
                    }
                    QuestionOption::create([
                        'question_id' => $q->id,
                        'label' => $opt['label'] ?? null,
                        'body' => $optBody,
                        'is_correct' => $opt['is_correct'] ?? false,
                        'match_key' => $opt['match_key'] ?? null,
                    ]);
                }
            }

            return $q->load('options');
        });

        return $this->successResponse($question, 201);
    }

    // =========================================================================
    // FITUR E: PENGUNCIAN LEDGER NILAI GURU
    // =========================================================================

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
