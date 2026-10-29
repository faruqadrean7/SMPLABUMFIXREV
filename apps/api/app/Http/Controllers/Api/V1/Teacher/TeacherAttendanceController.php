<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Journal;
use App\Models\Schedule;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TeacherAttendanceController extends Controller
{
    use ApiResponse;
    use TeacherPortalContextTrait;

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
}
