<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\SubmitAssignmentRequest;
use App\Models\Achievement;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\ClassStudent;
use App\Models\CounselingRequest;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\ExamSession;
use App\Models\Grade;
use App\Models\Schedule;
use App\Models\Student;
use App\Services\LmsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeStudentController extends Controller
{
    use ApiResponse;

    protected function getStudent(Request $request): ?Student
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        return $user->student;
    }

    /**
     * GET /api/v1/me/student-dashboard
     * Ringkasan beranda siswa aktif.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);
        if (! $student) {
            return $this->errorResponse('Akses ditolak: Profil siswa tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $student->loadMissing(['classes.academicYear']);
        $currentClass = $student->classes->first();

        // Presensi
        $totalAtt = Attendance::where('student_id', $student->id)->count();
        $presentAtt = Attendance::where('student_id', $student->id)->where('status', 'hadir')->count();
        $attendanceRate = $totalAtt > 0 ? round(($presentAtt / $totalAtt) * 100, 1) : 100.0;

        // Jadwal hari ini (dayOfWeek: 1 = Senin, 7 = Minggu)
        $todayDayOfWeek = now()->dayOfWeekIso;
        $todaySchedules = [];
        if ($currentClass) {
            $schedules = Schedule::with(['subject', 'teacher', 'room', 'timeSlot'])
                ->where('class_id', $currentClass->id)
                ->where('day_of_week', $todayDayOfWeek)
                ->get();

            $todaySchedules = $schedules->map(fn (Schedule $s) => [
                'id' => $s->id,
                'dayOfWeek' => $s->day_of_week,
                'dayName' => $this->getDayName($s->day_of_week),
                'startTime' => $s->timeSlot?->start_time ? substr($s->timeSlot->start_time, 0, 5) : '07:00',
                'endTime' => $s->timeSlot?->end_time ? substr($s->timeSlot->end_time, 0, 5) : '08:20',
                'subjectName' => $s->subject?->name ?? 'Mata Pelajaran',
                'teacherName' => $s->teacher?->full_name ?? 'Guru Pengampu',
                'roomName' => $s->room?->name ?? ($currentClass->name ?? 'Ruang Kelas'),
            ])->values()->all();
        }

        // Ujian CBT mendatang
        $exams = ExamParticipant::with('exam.subject')
            ->where('student_id', $student->id)
            ->limit(5)
            ->get()
            ->map(fn (ExamParticipant $p) => [
                'id' => $p->exam_id,
                'title' => $p->exam?->title ?? 'Ujian CBT',
                'subjectName' => $p->exam?->subject?->name ?? 'Mata Pelajaran',
                'durationMinutes' => $p->exam?->duration_min ?? 60,
                'totalQuestions' => 30,
                'startTime' => $p->exam?->start_at?->toISOString() ?? now()->toISOString(),
                'endTime' => $p->exam?->end_at?->toISOString() ?? now()->addHours(1)->toISOString(),
                'status' => $p->exam?->status ?? 'scheduled',
            ])->values()->all();

        // Total prestasi
        $achievementsCount = Achievement::where('student_id', $student->id)->count();

        return $this->successResponse([
            'name' => $student->full_name,
            'nis' => $student->nis ?? '',
            'nisn' => $student->nisn ?? '',
            'className' => $currentClass?->name ?? 'Belum ada kelas',
            'photoFileId' => $student->photo_file_id,
            'photoUrl' => $student->photoFile?->url,
            'todaySchedule' => $todaySchedules,
            'upcomingExams' => $exams,
            'attendanceRate' => $attendanceRate,
            'totalAchievements' => $achievementsCount,
        ]);
    }

    /**
     * GET /api/v1/me/schedule
     * Jadwal pelajaran mingguan siswa aktif.
     */
    public function schedule(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);
        if (! $student) {
            return $this->errorResponse('Akses ditolak: Profil siswa tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $currentClass = $student->classes()->first();
        if (! $currentClass) {
            return $this->successResponse([]);
        }

        $schedules = Schedule::with(['subject', 'teacher', 'room', 'timeSlot'])
            ->where('class_id', $currentClass->id)
            ->orderBy('day_of_week')
            ->get();

        $data = $schedules->map(fn (Schedule $s) => [
            'id' => $s->id,
            'dayOfWeek' => $s->day_of_week,
            'dayName' => $this->getDayName($s->day_of_week),
            'startTime' => $s->timeSlot?->start_time ? substr($s->timeSlot->start_time, 0, 5) : '07:00',
            'endTime' => $s->timeSlot?->end_time ? substr($s->timeSlot->end_time, 0, 5) : '08:20',
            'subjectName' => $s->subject?->name ?? 'Mata Pelajaran',
            'teacherName' => $s->teacher?->full_name ?? 'Guru Pengampu',
            'roomName' => $s->room?->name ?? ($currentClass->name ?? 'Ruang Kelas'),
        ]);

        return $this->successResponse($data);
    }

    /**
     * GET /api/v1/me/grades
     * Nilai pembelajaran siswa aktif.
     */
    public function grades(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);
        if (! $student) {
            return $this->errorResponse('Akses ditolak: Profil siswa tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $grades = Grade::with(['component.subject'])
            ->where('student_id', $student->id)
            ->get()
            ->groupBy(fn ($g) => $g->component?->subject_id ?? 'unknown');

        $result = [];
        foreach ($grades as $subjectId => $items) {
            $first = $items->first();
            $subject = $first?->component?->subject;
            $avgScore = round($items->avg('value') ?? 0, 1);

            $result[] = [
                'subjectId' => (string) $subjectId,
                'subjectName' => $subject?->name ?? 'Mata Pelajaran',
                'subjectCode' => $subject?->code ?? '',
                'teacherName' => 'Guru Pengampu',
                'finalScore' => $avgScore,
                'letterGrade' => $avgScore >= 85 ? 'A' : ($avgScore >= 75 ? 'B' : 'C'),
                'isPassed' => $avgScore >= 75,
            ];
        }

        return $this->successResponse($result);
    }

    /**
     * GET /api/v1/me/exams
     * Jadwal ujian CBT siswa.
     */
    public function exams(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);
        if (! $student) {
            return $this->errorResponse('Akses ditolak: Profil siswa tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $participants = ExamParticipant::with(['exam.subject', 'exam.questions', 'examRoom.room'])
            ->where('student_id', $student->id)
            ->get();

        $examIds = $participants->pluck('exam_id')->filter()->unique();
        $sessions = ExamSession::where('student_id', $student->id)
            ->whereIn('exam_id', $examIds)
            ->get()
            ->keyBy('exam_id');

        $data = $participants->map(function (ExamParticipant $p) use ($sessions) {
            $exam = $p->exam;
            $session = $sessions->get($p->exam_id);

            if ($session && $session->status === 'selesai') {
                $status = 'selesai';
            } elseif ($session && $session->status === 'mengerjakan') {
                $status = 'aktif';
            } elseif ($exam?->status === Exam::STATUS_IN_PROGRESS || $exam?->status === 'aktif' || $exam?->status === 'berlangsung') {
                $status = 'aktif';
            } elseif ($exam?->status === Exam::STATUS_SCHEDULED || $exam?->status === 'terjadwal' || $exam?->status === 'dijadwalkan') {
                $status = 'terjadwal';
            } elseif ($exam?->status === Exam::STATUS_FINISHED) {
                $status = 'selesai';
            } else {
                $status = $exam?->status ?? 'aktif';
            }

            return [
                'id' => $p->exam_id,
                'title' => $exam?->title ?? 'Ujian CBT',
                'subjectName' => $exam?->subject?->name ?? 'Mata Pelajaran',
                'durationMinutes' => $exam?->duration_min ?? 60,
                'totalQuestions' => $exam?->questions?->count() ?: 30,
                'startTime' => $exam?->start_at?->toISOString() ?? now()->toISOString(),
                'endTime' => $exam?->end_at?->toISOString() ?? now()->addHours(1)->toISOString(),
                'status' => $status,
                'roomName' => $p->examRoom?->room?->name ?? 'Lab Komputer 1',
                'kktp' => (float) ($exam?->passing_grade ?? 75),
                'tokenRequired' => $p->examRoom?->token ?? 'LAB-CBT',
                'score' => $session?->score !== null ? (float) $session->score : null,
            ];
        });

        return $this->successResponse($data);
    }

    /**
     * GET /api/v1/me/tasks
     * Daftar tugas KBM siswa beserta status pengumpulan.
     */
    public function tasks(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);
        if (! $student) {
            return $this->errorResponse('Akses ditolak: Profil siswa tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $studentClassIds = ClassStudent::where('student_id', $student->id)->pluck('class_id');
        $assignments = Assignment::with(['subject', 'teacher', 'file'])
            ->whereIn('class_id', $studentClassIds)
            ->orderBy('due_at', 'desc')
            ->get();

        $assignmentIds = $assignments->pluck('id');
        $submissions = AssignmentSubmission::with('file')
            ->where('student_id', $student->id)
            ->whereIn('assignment_id', $assignmentIds)
            ->get()
            ->keyBy('assignment_id');

        $result = $assignments->map(function (Assignment $a) use ($submissions) {
            $sub = $submissions->get($a->id);
            $status = 'aktif';
            if ($sub) {
                $status = $sub->score !== null ? 'dinilai' : 'dikumpulkan';
            }

            $dueDate = $a->due_at?->format('Y-m-d') ?? '';
            $dueTime = $a->due_at?->format('H:i') ?? '';

            return [
                'id' => $a->id,
                'title' => $a->title,
                'subjectName' => $a->subject?->name ?? 'Mata Pelajaran',
                'dueDate' => $dueDate,
                'dueTime' => $dueTime,
                'status' => $status,
                'description' => $a->description ?? '',
                'teacherName' => $a->teacher?->full_name ?? 'Guru Pengampu',
                'maxScore' => (float) ($a->max_score ?? 100),
                'score' => $sub?->score !== null ? (float) $sub->score : null,
                'teacherFeedback' => $sub?->feedback,
                'submission' => $sub ? [
                    'id' => $sub->id,
                    'file_name' => $sub->file?->object_key ? basename($sub->file->object_key) : 'Berkas Terlampir',
                    'submitted_at' => $sub->submitted_at?->toISOString() ?? $sub->created_at?->toISOString(),
                    'notes' => $sub->notes,
                ] : null,
            ];
        });

        return $this->successResponse($result);
    }

    /**
     * POST /api/v1/me/tasks/{id}/submit
     * Siswa mengumpulkan tugas KBM.
     */
    public function submitTask(string $id, SubmitAssignmentRequest $request, LmsService $service): JsonResponse
    {
        $student = $this->getStudent($request);
        if (! $student) {
            return $this->errorResponse('Akses ditolak: Profil siswa tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $submission = $service->submitAssignment($id, $request->validated(), $request);

        return $this->successResponse([
            'success' => true,
            'message' => 'Tugas berhasil dikumpulkan.',
            'submission' => $submission,
        ], 201);
    }

    /**
     * GET /api/v1/me/counseling-requests
     * Daftar permohonan konseling siswa yang login.
     */
    public function counselingRequests(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);
        if (! $student) {
            return $this->errorResponse('Akses ditolak: Profil siswa tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $counselings = CounselingRequest::with(['counselor', 'preferredCounselor'])
            ->where('student_id', $student->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $counselings->map(function (CounselingRequest $c) {
            $prefSchedule = '';
            if ($c->preferred_date) {
                $prefSchedule = $c->preferred_date->format('Y-m-d');
            }
            if ($c->preferred_time) {
                $prefSchedule = trim($prefSchedule.' '.$c->preferred_time);
            }

            return [
                'id' => $c->id,
                'topic' => $c->topic,
                'category' => 'Pengembangan Diri',
                'requestDate' => $c->created_at?->format('Y-m-d') ?? now()->toDateString(),
                'preferredSchedule' => $prefSchedule ?: 'Fleksibel',
                'counselorName' => $c->counselor?->name ?? ($c->preferredCounselor?->name ?? 'Guru BK'),
                'status' => match ($c->status) {
                    'pending' => 'menunggu',
                    'scheduled' => 'dijadwalkan',
                    'completed' => 'selesai',
                    'rejected', 'cancelled' => 'ditolak',
                    default => 'menunggu',
                },
                'notes' => $c->notes,
                'scheduledTime' => $c->scheduled_at?->toISOString(),
                'roomName' => 'Ruang BK',
            ];
        });

        return $this->successResponse($data);
    }

    /**
     * POST /api/v1/me/counseling-requests
     * Pengajuan permohonan konseling mandiri siswa.
     */
    public function storeCounselingRequest(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);
        if (! $student) {
            return $this->errorResponse('Akses ditolak: Profil siswa tidak ditemukan.', 'FORBIDDEN', 403);
        }

        // Support both camelCase and snake_case
        if ($request->has('preferredDate') && ! $request->has('preferred_date')) {
            $request->merge(['preferred_date' => $request->input('preferredDate')]);
        }
        if ($request->has('preferredTime') && ! $request->has('preferred_time')) {
            $request->merge(['preferred_time' => $request->input('preferredTime')]);
        }

        $validated = $request->validate([
            'topic' => 'nullable|string|max:200',
            'reason' => 'required|string|max:1000',
            'preferred_date' => 'nullable|date',
            'preferred_time' => 'nullable|string|max:100',
            'is_urgent' => 'nullable|boolean',
        ]);

        $topic = (isset($validated['topic']) && trim($validated['topic']) !== '')
            ? trim($validated['topic'])
            : mb_substr($validated['reason'], 0, 200);

        $counseling = CounselingRequest::create([
            'student_id' => $student->id,
            'requested_by' => $request->user()->id,
            'requester_role' => 'student',
            'topic' => $topic,
            'notes' => $validated['reason'] ?? null,
            'preferred_date' => $validated['preferred_date'] ?? null,
            'preferred_time' => $validated['preferred_time'] ?? null,
            'status' => 'pending',
        ]);

        $counselingData = $counseling->toArray();
        $counselingData['category'] = $request->input('category', 'Pengembangan Diri');
        $counselingData['requestDate'] = $counseling->created_at?->format('Y-m-d') ?? now()->toDateString();
        $prefDateStr = '';
        if ($counseling->preferred_date) {
            $prefDateStr = is_string($counseling->preferred_date) ? substr($counseling->preferred_date, 0, 10) : $counseling->preferred_date->format('Y-m-d');
        }
        $counselingData['preferredSchedule'] = trim($prefDateStr.' '.($counseling->preferred_time ?? ''));
        $counselingData['counselorName'] = 'Guru BK';
        $counselingData['notes'] = $counseling->notes;

        return $this->successResponse($counselingData, 201);
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
}
