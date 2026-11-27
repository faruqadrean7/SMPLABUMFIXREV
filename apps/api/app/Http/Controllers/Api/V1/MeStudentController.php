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
use App\Models\Extracurricular;
use App\Models\ExtracurricularMember;
use App\Models\Grade;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudentPoint;
use App\Models\StudentProgramProfile;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\LmsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
     * Resolusi program belajar efektif siswa.
     * Prioritas: student.program_type -> class.program_type -> 'reguler'.
     */
    protected function resolveProgramType(Student $student): string
    {
        $student->loadMissing('activeClasses');

        $studentProg = $student->program_type;
        $classProg = $student->activeClasses->first()?->program_type;

        if (empty($studentProg) || ($studentProg === 'reguler' && in_array($classProg, ['tahfidz', 'icp', 'inklusi'], true))) {
            return $classProg ?? 'reguler';
        }

        return $studentProg;
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

        $student->loadMissing(['classes.academicYear', 'programProfile']);
        $currentClass = $student->classes->first();

        // Resolusi program belajar (prioritas: student.program_type -> class.program_type -> 'reguler')
        $programType = $this->resolveProgramType($student);
        $programBadges = [
            'tahfidz' => "Siswa Program Tahfidz Al-Qur'an",
            'icp' => 'International Class Program (Cambridge)',
            'inklusi' => 'Siswa Program Inklusi',
            'reguler' => 'Siswa Program Reguler',
        ];
        $programBadge = $programBadges[$programType] ?? 'Siswa Program Reguler';
        $programProfile = $student->programProfile?->meta_data ?? [];

        // Presensi
        $totalAtt = Attendance::where('student_id', $student->id)->count();
        $presentAtt = Attendance::where('student_id', $student->id)->where('status', 'hadir')->count();
        // Tanpa catatan presensi, tingkat kehadiran TIDAK BOLEH diklaim 100%.
        $attendanceRate = $totalAtt > 0 ? round(($presentAtt / $totalAtt) * 100, 1) : null;

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
                'teacherName' => $s->teacher?->full_name ?? '-',
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
                'totalQuestions' => $p->exam?->questions()->count() ?? 0,
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
            'programType' => $programType,
            'programBadge' => $programBadge,
            'programProfile' => $programProfile,
            'photoFileId' => $student->photo_file_id,
            'photoUrl' => $student->photoFile?->url,
            'todaySchedule' => $todaySchedules,
            'upcomingExams' => $exams,
            'attendanceRate' => $attendanceRate,
            'totalAchievements' => $achievementsCount,
        ]);
    }

    /**
     * POST /api/v1/me/program/mood
     * Check-in kondisi perasaan / sensori harian (khususnya untuk siswa program Inklusi).
     */
    public function submitDailyMood(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);
        if (! $student) {
            return $this->errorResponse('Akses ditolak: Profil siswa tidak ditemukan.', 'FORBIDDEN', 403);
        }

        if ($this->resolveProgramType($student) !== 'inklusi') {
            return $this->errorResponse('Fitur check-in mood hanya untuk siswa program Inklusi.', 'FORBIDDEN', 403);
        }

        $validated = $request->validate([
            'mood' => ['required', 'string', 'in:happy,calm,tired,anxious'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $profile = StudentProgramProfile::firstOrNew(['student_id' => $student->id]);
        if (! $profile->exists) {
            $profile->program_type = 'inklusi';
        }

        $meta = $profile->meta_data ?? [];
        $todayKey = now()->toDateString();
        $moodEntry = [
            'mood' => $validated['mood'],
            'note' => $validated['note'] ?? null,
            'date' => $todayKey,
            'timestamp' => now()->toISOString(),
        ];
        $meta['daily_mood'] = $moodEntry;
        $history = is_array($meta['daily_mood_history'] ?? null) ? $meta['daily_mood_history'] : [];
        $history[$todayKey] = $moodEntry;
        $meta['daily_mood_history'] = $history;
        $profile->meta_data = $meta;
        $profile->save();

        app(AuditLogService::class)->record(
            action: 'portal.program.mood.checkin',
            entity: 'StudentProgramProfile',
            entityId: $profile->id,
            newValues: $meta['daily_mood'],
            request: $request,
            isAuthoritative: true
        );

        return $this->successResponse($meta['daily_mood'], 200, ['message' => 'Mood harian berhasil dicatat.']);
    }

    /**
     * POST /api/v1/me/program/mutabaah
     * Simpan catatan ibadah harian / mutabaah yaumiyah (khususnya untuk siswa program Tahfidz).
     */
    public function submitMutabaah(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);
        if (! $student) {
            return $this->errorResponse('Akses ditolak: Profil siswa tidak ditemukan.', 'FORBIDDEN', 403);
        }

        if ($this->resolveProgramType($student) !== 'tahfidz') {
            return $this->errorResponse('Fitur mutabaah yaumiyah hanya untuk siswa program Tahfidz.', 'FORBIDDEN', 403);
        }

        $validated = $request->validate([
            'sholat_fardhu' => ['required', 'integer', 'min:0', 'max:5'],
            'tilawah_pages' => ['required', 'integer', 'min:0', 'max:100'],
            'dhuha' => ['required', 'boolean'],
            'tahajud' => ['required', 'boolean'],
        ]);

        $profile = StudentProgramProfile::firstOrNew(['student_id' => $student->id]);
        if (! $profile->exists) {
            $profile->program_type = 'tahfidz';
        }

        $meta = $profile->meta_data ?? [];
        $todayKey = now()->toDateString();
        $mutabaahEntry = [
            'sholat_fardhu' => (int) $validated['sholat_fardhu'],
            'tilawah_pages' => (int) $validated['tilawah_pages'],
            'dhuha' => (bool) $validated['dhuha'],
            'tahajud' => (bool) $validated['tahajud'],
            'date' => $todayKey,
            'updated_at' => now()->toISOString(),
        ];
        $meta['mutabaah_today'] = $mutabaahEntry;
        $history = is_array($meta['mutabaah_history'] ?? null) ? $meta['mutabaah_history'] : [];
        $history[$todayKey] = $mutabaahEntry;
        $meta['mutabaah_history'] = $history;
        $profile->meta_data = $meta;
        $profile->save();

        app(AuditLogService::class)->record(
            action: 'portal.program.mutabaah.update',
            entity: 'StudentProgramProfile',
            entityId: $profile->id,
            newValues: $meta['mutabaah_today'],
            request: $request,
            isAuthoritative: true
        );

        return $this->successResponse($meta['mutabaah_today'], 200, ['message' => 'Checklist mutabaah berhasil disimpan.']);
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

        $student->loadMissing('classes');
        $classId = $student->classes->first()?->id;

        $grades = Grade::with(['component.subject'])
            ->where('student_id', $student->id)
            ->get()
            ->groupBy(fn ($g) => $g->component?->subject_id ?? 'unknown');

        // Guru pengampu nyata per (kelas, mapel). Dulu selalu "Guru Pengampu".
        $teacherBySubject = $classId
            ? TeachingAssignment::with('teacher')
                ->where('class_id', $classId)
                ->get()
                ->mapWithKeys(fn ($ta) => [$ta->subject_id => $ta->teacher?->full_name])
            : collect();

        $kktp = 75.0;

        $result = [];
        foreach ($grades as $subjectId => $items) {
            $first = $items->first();
            $subject = $first?->component?->subject;

            $byCode = [];
            $weighted = 0.0;
            $totalWeight = 0.0;
            foreach ($items as $grade) {
                $code = $grade->component?->code;
                $weight = (float) ($grade->component?->weight ?? 0);
                if ($code) {
                    $byCode[$code] = (float) $grade->value;
                }
                if ($weight > 0) {
                    $weighted += (float) $grade->value * $weight;
                    $totalWeight += $weight;
                }
            }

            $finalScore = $totalWeight > 0
                ? round($weighted / $totalWeight, 1)
                : round((float) ($items->avg('value') ?? 0), 1);

            $result[] = [
                'subjectId' => (string) $subjectId,
                'subjectName' => $subject?->name ?? 'Mata Pelajaran',
                'subjectCode' => $subject?->code ?? '',
                'teacherName' => $teacherBySubject[$subjectId] ?? '-',
                // Rincian nyata per komponen (TGS/UTS/UAS/SIKAP).
                'dailyAverage' => $byCode['TGS'] ?? null,
                'midExam' => $byCode['UTS'] ?? null,
                'finalExam' => $byCode['UAS'] ?? null,
                'attitude' => $byCode['SIKAP'] ?? null,
                'components' => $byCode,
                'finalScore' => $finalScore,
                'letterGrade' => $finalScore >= 85 ? 'A' : ($finalScore >= 75 ? 'B' : ($finalScore >= 65 ? 'C' : 'D')),
                'passingGrade' => $kktp,
                'isPassed' => $finalScore >= $kktp,
            ];
        }

        usort($result, fn ($a, $b) => strcmp($a['subjectName'], $b['subjectName']));

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

            $showResultMode = $exam?->show_result_mode ?? 'after_release';
            $canShowScore = false;
            if ($session && $session->status === 'selesai' && $session->score !== null) {
                if ($showResultMode === 'immediate') {
                    $canShowScore = true;
                } elseif ($showResultMode === 'after_release' && in_array($exam?->status, ['selesai', 'arsip'], true)) {
                    $canShowScore = true;
                }
            }

            return [
                'id' => $p->exam_id,
                'title' => $exam?->title ?? 'Ujian CBT',
                'subjectName' => $exam?->subject?->name ?? 'Mata Pelajaran',
                'durationMinutes' => $exam?->duration_min ?? 60,
                'totalQuestions' => $exam?->questions?->count() ?: 0,
                'startTime' => $exam?->start_at?->toISOString() ?? now()->toISOString(),
                'endTime' => $exam?->end_at?->toISOString() ?? now()->addHours(1)->toISOString(),
                'status' => $status,
                'roomName' => $p->examRoom?->room?->name ?? 'Lab Komputer 1',
                'kktp' => (float) ($exam?->passing_grade ?? 75),
                'requiresToken' => true,
                'score' => $canShowScore ? (float) $session->score : null,
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
     * Daftar permohonan konseling siswa atau orang tua/wali yang login.
     * P1-23: Orang tua hanya melihat permintaan yang diajukan sendiri (konsisten P0-18, tidak membaca curhat mandiri siswa).
     */
    public function counselingRequests(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 'UNAUTHORIZED', 401);
        }

        if ($user->student) {
            $student = $this->resolveCounselingSubject($request);
            if (! $student) {
                return $this->errorResponse('Akses ditolak: Profil siswa tidak ditemukan.', 'FORBIDDEN', 403);
            }

            $counselings = CounselingRequest::with(['counselor', 'preferredCounselor'])
                ->where('student_id', $student->id)
                ->orderBy('created_at', 'desc')
                ->get();
        } elseif ($user->guardian) {
            $requestedChildId = $request->input('student_id');
            if ($requestedChildId !== null && $requestedChildId !== '') {
                $student = $this->resolveCounselingSubject($request);
                if (! $student) {
                    return $this->errorResponse('Akses ditolak: Anda tidak berwenang mengakses permohonan konseling siswa ini.', 'FORBIDDEN', 403);
                }
                $wardIds = [$student->id];
            } else {
                $wardIds = $this->resolveGuardianWardIds($user);
                if (empty($wardIds)) {
                    return $this->errorResponse('Akses ditolak: Profil anak perwalian tidak ditemukan.', 'FORBIDDEN', 403);
                }
            }

            // P1-23: Guardian hanya boleh melihat permintaan yang diajukannya sendiri
            $counselings = CounselingRequest::with(['counselor', 'preferredCounselor'])
                ->whereIn('student_id', $wardIds)
                ->where('requested_by', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            return $this->errorResponse('Akses ditolak: Profil siswa tidak ditemukan.', 'FORBIDDEN', 403);
        }

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
                'student_id' => $c->student_id,
                'requester_role' => $c->requester_role,
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
     * Pengajuan permohonan konseling mandiri siswa atau konsultasi orang tua/wali.
     */
    public function storeCounselingRequest(Request $request): JsonResponse
    {
        $student = $this->resolveCounselingSubject($request);
        if (! $student) {
            return $this->errorResponse('Akses ditolak: Profil siswa tidak ditemukan.', 'FORBIDDEN', 403);
        }

        // Support both camelCase, snake_case, dan preferred_schedule dari portal orang tua
        if ($request->has('preferredDate') && ! $request->has('preferred_date')) {
            $request->merge(['preferred_date' => $request->input('preferredDate')]);
        }
        if ($request->has('preferredTime') && ! $request->has('preferred_time')) {
            $request->merge(['preferred_time' => $request->input('preferredTime')]);
        }
        if (! $request->filled('preferred_date') && ! $request->filled('preferred_time')) {
            $prefSchedule = $request->input('preferred_schedule') ?? $request->input('preferredSchedule');
            if (is_string($prefSchedule) && trim($prefSchedule) !== '') {
                $request->merge(['preferred_date' => trim($prefSchedule)]);
            }
        }

        // Support notes or topic as fallback for reason
        if (empty($request->input('reason'))) {
            if ($request->filled('notes')) {
                $request->merge(['reason' => $request->input('notes')]);
            } elseif ($request->filled('topic')) {
                $request->merge(['reason' => $request->input('topic')]);
            }
        }

        // If preferred_date is non-date text (e.g. "Tanggal fleksibel"), sanitize to avoid validation rejection
        $prefDateInput = $request->input('preferred_date');
        if ($prefDateInput && ! strtotime($prefDateInput)) {
            $request->merge(['preferred_date' => null]);
            if (! $request->input('preferred_time')) {
                $request->merge(['preferred_time' => (string) $prefDateInput]);
            }
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
            'requester_role' => ($request->user()->student ? 'student' : 'guardian'),
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

    /**
     * Resolve subjek konseling: siswa sendiri, atau anak milik wali (ortu).
     * Wali hanya dapat mengakses anak yang benar-benar miliknya pada sekolah yang sama (anti-IDOR),
     * opsional difilter student_id; default anak pertama.
     */
    protected function resolveCounselingSubject(Request $request): ?Student
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }
        if ($user->student) {
            $student = $user->student;
            if (! empty($user->school_id) && ! empty($student->school_id) && $student->school_id !== $user->school_id) {
                return null;
            }

            return $student;
        }
        $guardian = $user->guardian;
        if ($guardian) {
            $childId = $request->input('student_id');
            $query = $guardian->students();
            if (! empty($user->school_id)) {
                $query->where(function ($q) use ($user) {
                    $q->where('students.school_id', $user->school_id)
                        ->orWhereNull('students.school_id');
                });
            }

            if ($childId !== null && $childId !== '') {
                if (! is_string($childId) || ! Str::isUuid($childId)) {
                    return null;
                }

                return $query->where('students.id', $childId)->first();
            }

            return $query->first();
        }

        return null;
    }

    /**
     * Ambil daftar ID anak perwalian sah pada sekolah yang sama untuk guardian.
     *
     * @return array<int, string>
     */
    protected function resolveGuardianWardIds(User $user): array
    {
        $guardian = $user->guardian;
        if (! $guardian) {
            return [];
        }

        $query = $guardian->students();
        if (! empty($user->school_id)) {
            $query->where(function ($q) use ($user) {
                $q->where('students.school_id', $user->school_id)
                    ->orWhereNull('students.school_id');
            });
        }

        return $query->pluck('students.id')->all();
    }

    /**
     * GET /api/v1/me/points
     * Rekapitulasi poin karakter, kedisiplinan, dan prestasi siswa aktif.
     */
    public function points(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);
        if (! $student) {
            return $this->errorResponse('Akses ditolak: Profil siswa tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $studentPoints = StudentPoint::with(['pointType', 'recorder'])
            ->where('student_id', $student->id)
            ->orderBy('point_date', 'desc')
            ->get();

        $achievements = Achievement::where('student_id', $student->id)
            ->orderBy('achieved_at', 'desc')
            ->get();

        $items = collect();

        foreach ($achievements as $ach) {
            $dateStr = $ach->achieved_at ? $ach->achieved_at->format('d M Y') : $ach->created_at->format('d M Y');
            $items->push([
                'id' => 'ach-'.$ach->id,
                'student_id' => $student->id,
                'kind' => 'prestasi',
                'title' => $ach->title,
                'category' => $ach->rank_level ?: 'Prestasi & Kompetisi',
                'point_date' => $dateStr,
                'points' => (int) ($ach->points_awarded ?: 25),
                'note' => $ach->notes ?: 'Diverifikasi oleh tim kesiswaan SMP Labschool UM.',
                'recorder_name' => 'Tim Kesiswaan',
            ]);
        }

        foreach ($studentPoints as $sp) {
            $isViolation = ($sp->pointType?->kind === 'pelanggaran');
            $dateStr = $sp->point_date ? $sp->point_date->format('d M Y') : $sp->created_at->format('d M Y');
            $items->push([
                'id' => 'pt-'.$sp->id,
                'student_id' => $student->id,
                'kind' => $isViolation ? 'pelanggaran' : 'prestasi',
                'title' => $sp->pointType?->name ?: ($isViolation ? 'Pelanggaran Kedisiplinan' : 'Apresiasi Karakter'),
                'category' => ucfirst($sp->pointType?->severity ?: 'Disiplin'),
                'point_date' => $dateStr,
                'points' => (int) ($sp->pointType?->points ?: 0),
                'note' => $sp->notes ?: 'Tercatat dalam buku pembiasaan siswa.',
                'recorder_name' => $sp->recorder?->name ?: 'Guru Piket / Kesiswaan',
            ]);
        }

        $totalPrestasi = $items->where('kind', 'prestasi')->sum('points');
        $totalPelanggaran = $items->where('kind', 'pelanggaran')->sum('points');
        $balancePoints = $totalPrestasi - $totalPelanggaran;

        $statusDisiplin = match (true) {
            $totalPelanggaran >= 75 => 'SP3 - Konferensi Kasus',
            $totalPelanggaran >= 50 => 'SP2 - Peringatan 2',
            $totalPelanggaran >= 25 => 'SP1 - Peringatan 1',
            default => 'AMAN',
        };

        return $this->successResponse([
            'totalPrestasi' => $totalPrestasi,
            'totalPelanggaran' => $totalPelanggaran,
            'balancePoints' => $balancePoints,
            'statusDisiplin' => $statusDisiplin,
            'achievementsCount' => $achievements->count(),
            'items' => $items->values()->all(),
        ]);
    }

    /**
     * GET /api/v1/me/extracurriculars
     * Daftar kegiatan ekstrakurikuler yang diikuti serta katalog ekstrakurikuler sekolah.
     */
    public function extracurriculars(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);
        if (! $student) {
            return $this->errorResponse('Akses ditolak: Profil siswa tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $memberships = ExtracurricularMember::with(['extracurricular.coach'])
            ->where('student_id', $student->id)
            ->get();

        $joinedIds = $memberships->pluck('extracurricular_id')->all();

        $myExtracurriculars = $memberships->map(function (ExtracurricularMember $m) {
            $ekskul = $m->extracurricular;
            $sched = trim(($ekskul?->schedule_day ?? '').($ekskul?->schedule_time ? ', '.$ekskul->schedule_time : ''));

            return [
                'id' => $m->extracurricular_id,
                'memberId' => $m->id,
                'name' => $ekskul?->name ?? 'Ekstrakurikuler',
                'coach' => $ekskul?->coach?->full_name ?? 'Pembina Ekstrakurikuler',
                'schedule' => $sched ?: 'Jadwal diumumkan pembina',
                'room' => 'Ruang Kegiatan / Lapangan',
                'role' => ucfirst($m->role ?: 'Anggota Reguler'),
                'attendance_rate' => '100%',
                'badgeColor' => 'bg-blue-50 text-blue-900 border-blue-200',
                'finalGrade' => $m->final_grade,
            ];
        })->values()->all();

        $availableQuery = Extracurricular::with('coach')
            ->where('is_active', true);
        if (! empty($joinedIds)) {
            $availableQuery->whereNotIn('id', $joinedIds);
        }
        $available = $availableQuery->get();

        $availableExtracurriculars = $available->map(function (Extracurricular $e) {
            $memberCount = $e->members()->count();
            $remainingQuota = max(0, ($e->quota ?? 30) - $memberCount);
            $sched = trim(($e->schedule_day ?? '').($e->schedule_time ? ', '.$e->schedule_time : ''));

            return [
                'id' => $e->id,
                'name' => $e->name,
                'coach' => $e->coach?->full_name ?? 'Pembina Ekstrakurikuler',
                'schedule' => $sched ?: 'Sesuai Pengumuman',
                'quota' => 'Sisa '.$remainingQuota.' Kuota',
                'category' => $e->category ?: 'Pilihan',
                'description' => $e->description ?: '',
            ];
        })->values()->all();

        return $this->successResponse([
            'myExtracurriculars' => $myExtracurriculars,
            'availableExtracurriculars' => $availableExtracurriculars,
        ]);
    }

    /**
     * POST /api/v1/me/extracurriculars/register
     * Siswa mendaftar ke ekstrakurikuler pilihan.
     */
    public function registerExtracurricular(Request $request): JsonResponse
    {
        $student = $this->getStudent($request);
        if (! $student) {
            return $this->errorResponse('Akses ditolak: Profil siswa tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $validated = $request->validate([
            'extracurricular_id' => 'required|uuid|exists:extracurriculars,id',
        ]);

        $ekskul = Extracurricular::findOrFail($validated['extracurricular_id']);

        $existing = ExtracurricularMember::where('extracurricular_id', $ekskul->id)
            ->where('student_id', $student->id)
            ->first();

        if ($existing) {
            return $this->errorResponse('Anda sudah terdaftar pada ekstrakurikuler ini.', 'ALREADY_REGISTERED', 422);
        }

        $currentCount = $ekskul->members()->count();
        if ($currentCount >= ($ekskul->quota ?? 30)) {
            return $this->errorResponse('Mohon maaf, kuota peserta untuk ekstrakurikuler ini telah penuh.', 'QUOTA_FULL', 422);
        }

        $member = ExtracurricularMember::create([
            'extracurricular_id' => $ekskul->id,
            'student_id' => $student->id,
            'role' => 'anggota',
            'status' => 'aktif',
            'joined_at' => now()->toDateString(),
        ]);

        return $this->successResponse([
            'message' => 'Berhasil mendaftar ekstrakurikuler '.$ekskul->name,
            'member' => $member,
        ], 201);
    }
}
