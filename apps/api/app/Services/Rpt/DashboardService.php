<?php

namespace App\Services\Rpt;

use App\Models\Achievement;
use App\Models\Asset;
use App\Models\Attendance;
use App\Models\Booking;
use App\Models\CharacterNote;
use App\Models\CounselingCase;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamParticipant;
use App\Models\ExamSession;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\Guardian;
use App\Models\Loan;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\StudentPoint;
use App\Models\Teacher;
use App\Models\TeacherSalarySlip;
use App\Models\TeachingAssignment;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DashboardService
{
    /**
     * BE-RPT-02: Dashboard Eksekutif untuk Pimpinan / Kepala Sekolah.
     * Metrik agregat di-cache 1-5 menit, kecuali metrik CBT Live (real-time).
     */
    public function getExecutiveDashboard(Request $request): array
    {
        $user = $request->user();
        $this->authorizeExecutive($user);
        $schoolId = $user?->school_id;

        // 1. CBT Live (Real-Time, TIDAK di-cache)
        $now = Carbon::now();
        $activeSessionQuery = ExamSession::whereIn('status', ['mengerjakan', 'in_progress']);
        $ongoingExamsQuery = Exam::where(function ($q) {
            $q->whereIn('status', ['berlangsung', 'dijadwalkan', 'published']);
        })->where(function ($q) use ($now) {
            $q->where('start_at', '<=', $now)->where('end_at', '>=', $now);
        });

        if ($schoolId) {
            $activeSessionQuery->whereHas('exam', fn ($q) => $q->where('school_id', $schoolId));
            $ongoingExamsQuery->where('school_id', $schoolId);
        }

        $activeRoomsQuery = ExamSession::whereIn('exam_sessions.status', ['mengerjakan', 'in_progress'])
            ->join('exams', 'exam_sessions.exam_id', '=', 'exams.id');
        if ($schoolId) {
            $activeRoomsQuery->where('exams.school_id', $schoolId);
        }

        $cbtLive = [
            'active_students_count' => $activeSessionQuery->count(),
            'ongoing_exams_count' => $ongoingExamsQuery->count(),
            'active_rooms_count' => $activeRoomsQuery->distinct('exams.id')->count('exams.id'),
        ];

        // 2. Metrik Agregat Ter-cache (Cache 2 Menit / 120 Detik)
        $cacheKey = 'dashboard_executive_metrics_'.($schoolId ?? 'global');
        $cachedMetrics = Cache::remember($cacheKey, 120, function () use ($schoolId) {
            $today = Carbon::today()->toDateString();

            // A. Kehadiran Hari Ini (Siswa & Guru/Staf)
            $studentAttsQuery = Attendance::whereDate('att_date', Carbon::today());
            if ($schoolId) {
                $studentAttsQuery->whereHas('student', fn ($q) => $q->where('school_id', $schoolId));
            }
            $studentAtts = $studentAttsQuery
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            $totalStudentAtt = array_sum($studentAtts);
            $studentHadir = $studentAtts['hadir'] ?? 0;

            $staffAttsQuery = StaffAttendance::whereDate('att_date', Carbon::today());
            if ($schoolId) {
                $staffAttsQuery->whereHas('user', fn ($q) => $q->where('school_id', $schoolId));
            }
            $staffAtts = $staffAttsQuery
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            $totalStaffAtt = array_sum($staffAtts);
            $staffHadir = $staffAtts['hadir'] ?? 0;

            $attendanceToday = [
                'date' => $today,
                'students' => [
                    'total_recorded' => $totalStudentAtt,
                    'hadir' => $studentHadir,
                    'izin' => $studentAtts['izin'] ?? 0,
                    'sakit' => $studentAtts['sakit'] ?? 0,
                    'alpa' => $studentAtts['alpa'] ?? 0,
                    'terlambat' => $studentAtts['terlambat'] ?? 0,
                    'rate' => $totalStudentAtt > 0 ? round(($studentHadir / $totalStudentAtt) * 100, 2) : 0,
                ],
                'staff' => [
                    'total_recorded' => $totalStaffAtt,
                    'hadir' => $staffHadir,
                    'terlambat' => $staffAtts['terlambat'] ?? 0,
                    'izin' => $staffAtts['izin'] ?? 0,
                    'rate' => $totalStaffAtt > 0 ? round(($staffHadir / $totalStaffAtt) * 100, 2) : 0,
                ],
            ];

            // B. Ketercapaian Silabus & Pengajaran
            $schedulesQuery = Schedule::query();
            if ($schoolId) {
                $schedulesQuery->whereHas('teacher', fn ($q) => $q->where('school_id', $schoolId));
            }
            $totalSchedules = (clone $schedulesQuery)->count();
            $schedulesWithJournals = (clone $schedulesQuery)->has('journals')->count();
            $syllabusCompletionRate = $totalSchedules > 0
                ? round(($schedulesWithJournals / $totalSchedules) * 100, 2)
                : 0;

            $syllabusCompletion = [
                'total_schedules' => $totalSchedules,
                'active_with_journals' => $schedulesWithJournals,
                'completion_rate' => $syllabusCompletionRate,
            ];

            // C. Persentase Guru Tuntas Nilai
            $componentsQuery = GradeComponent::query();
            if ($schoolId) {
                $componentsQuery->whereHas('subject', fn ($q) => $q->where('school_id', $schoolId));
            }
            $totalComponents = (clone $componentsQuery)->count();
            $componentsWithGrades = (clone $componentsQuery)->whereHas('grades')->count();
            $gradingCompletionRate = $totalComponents > 0
                ? round(($componentsWithGrades / $totalComponents) * 100, 2)
                : 0;

            $teacherGradingCompletion = [
                'total_components' => $totalComponents,
                'completed_components' => $componentsWithGrades,
                'completion_rate' => $gradingCompletionRate,
            ];

            // D. Rerata Nilai per Angkatan / Tingkat
            $gradesByLevelQuery = DB::table('grades')
                ->join('students', 'grades.student_id', '=', 'students.id')
                ->join('class_students', 'students.id', '=', 'class_students.student_id')
                ->join('classes', 'class_students.class_id', '=', 'classes.id')
                ->join('grade_levels', 'classes.grade_level_id', '=', 'grade_levels.id')
                ->select('grade_levels.id as grade_level_id', 'grade_levels.name', 'grade_levels.sort_order', DB::raw('avg(grades.value) as avg_value'), DB::raw('count(grades.id) as count'));
            if ($schoolId) {
                $gradesByLevelQuery->where('students.school_id', $schoolId);
            }
            $gradesByLevel = $gradesByLevelQuery
                ->groupBy('grade_levels.id', 'grade_levels.name', 'grade_levels.sort_order')
                ->orderBy('grade_levels.sort_order')
                ->get()
                ->map(fn ($row) => [
                    'grade_level_id' => $row->grade_level_id,
                    'level_name' => $row->name,
                    'sort_order' => (int) $row->sort_order,
                    'average_score' => round((float) $row->avg_value, 2),
                    'grades_count' => (int) $row->count,
                ])
                ->values()
                ->all();

            // E. Kasus Kesiswaan Tindak Lanjut
            $counselingQuery = CounselingCase::where('status', '!=', 'selesai');
            $violationsQuery = StudentPoint::whereHas('pointType', fn ($q) => $q->where('kind', 'pelanggaran'))
                ->where('point_date', '>=', Carbon::now()->subDays(30)->toDateString());
            if ($schoolId) {
                $counselingQuery->whereHas('student', fn ($q) => $q->where('school_id', $schoolId));
                $violationsQuery->whereHas('student', fn ($q) => $q->where('school_id', $schoolId));
            }
            $activeCounselingCases = $counselingQuery->count();
            $recentViolations = $violationsQuery->count();

            $studentAffairsFollowUp = [
                'active_counseling_cases' => $activeCounselingCases,
                'recent_violations_count' => $recentViolations,
            ];

            // F. Status Sarpras Kritis
            $ticketsQuery = Ticket::where(function ($q) {
                $q->where('priority', 'darurat')
                    ->orWhere(fn ($sub) => $sub->where('priority', 'tinggi')->where('status', 'pending'));
            });
            $assetsQuery = Asset::where('asset_condition', 'rusak_berat');
            if ($schoolId) {
                $ticketsQuery->where('school_id', $schoolId);
                $assetsQuery->where('school_id', $schoolId);
            }
            $criticalTickets = $ticketsQuery->count();
            $damagedAssets = $assetsQuery->count();

            $infrastructureStatus = [
                'critical_tickets_count' => $criticalTickets,
                'heavily_damaged_assets' => $damagedAssets,
            ];

            // G. Master Data & Pending Verifications Summary (Untuk ADM-DASH-01 & ADM-DASH-02)
            $studentsQuery = Student::query();
            $teachersQuery = Teacher::query();
            $classesQuery = SchoolClass::query();
            if ($schoolId) {
                $studentsQuery->where('school_id', $schoolId);
                $teachersQuery->where('school_id', $schoolId);
                $classesQuery->where('school_id', $schoolId);
            }

            $pendingAchievements = Achievement::where('verification_status', 'pending');
            $pendingBookings = Booking::where('status', 'menunggu');
            if ($schoolId) {
                $pendingAchievements->whereHas('student', fn ($q) => $q->where('school_id', $schoolId));
                $pendingBookings->whereHas('facility', fn ($q) => $q->where('school_id', $schoolId));
            }

            $masterSummary = [
                'total_students' => $studentsQuery->count(),
                'total_teachers' => $teachersQuery->count(),
                'total_classes' => $classesQuery->count(),
                'pending_verifications' => [
                    'achievements' => $pendingAchievements->count(),
                    'facility_bookings' => $pendingBookings->count(),
                    'total' => $pendingAchievements->count() + $pendingBookings->count(),
                ],
            ];

            return [
                'attendance_today' => $attendanceToday,
                'syllabus_completion' => $syllabusCompletion,
                'teacher_grading_completion' => $teacherGradingCompletion,
                'grades_by_grade_level' => $gradesByLevel,
                'student_affairs_follow_up' => $studentAffairsFollowUp,
                'infrastructure_status' => $infrastructureStatus,
                'master_summary' => $masterSummary,
                'cached_at' => Carbon::now()->toIso8601String(),
            ];
        });

        return array_merge([
            'cbt_live' => $cbtLive,
        ], $cachedMetrics);
    }

    /**
     * BE-RPT-02 / GAP-E: Dashboard Guru.
     */
    public function getTeacherDashboard(Request $request): array
    {
        $user = $request->user();
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        $teacher = Teacher::where('user_id', $user->id)->first();
        if (! $teacher && ! $user->hasRole(['super_admin', 'admin'])) {
            throw new HttpException(403, 'Akses ditolak: Profil guru tidak ditemukan.');
        }

        $teacherId = $teacher?->id;
        $dayNames = [
            1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
            5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
        ];
        $todayDayIso = Carbon::now()->dayOfWeekIso;
        $todayDay = $dayNames[$todayDayIso] ?? 'Senin';

        $assignments = $teacherId
            ? TeachingAssignment::where('teacher_id', $teacherId)
                ->with(['schoolClass' => fn ($q) => $q->withCount('students'), 'schoolClass.students', 'subject'])
                ->get()
            : collect([]);

        $todaySchedules = $teacherId
            ? Schedule::where('teacher_id', $teacherId)
                ->where('day_of_week', $todayDayIso)
                ->with(['schoolClass', 'subject', 'timeSlot'])
                ->get()
            : collect([]);

        $pendingJournals = $teacherId
            ? Schedule::where('teacher_id', $teacherId)
                ->whereDoesntHave('journals', fn ($q) => $q->whereDate('session_date', Carbon::today()))
                ->count()
            : 0;

        // GAP-E: Esai CBT menunggu koreksi (uraian) untuk ujian yang dibuat oleh guru atau mapel yang diampunya
        $teacherSubjectIds = $assignments->pluck('subject_id')->unique()->filter()->values()->all();
        $teacherExamIds = Exam::where(function ($q) use ($user, $teacherSubjectIds) {
            $q->where('created_by', $user->id);
            if (! empty($teacherSubjectIds)) {
                $q->orWhereIn('subject_id', $teacherSubjectIds);
            }
        })->pluck('id')->all();

        $pendingEssayGradingCount = ! empty($teacherExamIds)
            ? ExamAnswer::whereHas('session', function ($sq) use ($teacherExamIds) {
                $sq->whereIn('exam_id', $teacherExamIds)->where('status', 'selesai');
            })
                ->whereHas('question', function ($qq) {
                    $qq->where('question_type', 'uraian');
                })
                ->whereNull('score')
                ->count()
            : 0;

        // GAP-E: Ringkasan progres penilaian per kelas & mata pelajaran (Preload tanpa N+1)
        $classIds = $assignments->pluck('class_id')->unique()->filter()->values()->all();
        $allComponents = (! empty($classIds) && ! empty($teacherSubjectIds))
            ? GradeComponent::whereIn('class_id', $classIds)
                ->whereIn('subject_id', $teacherSubjectIds)
                ->withCount('grades')
                ->get()
                ->groupBy(fn ($c) => $c->class_id.'_'.$c->subject_id)
            : collect([]);

        $gradingProgress = $assignments->map(function ($ta) use ($allComponents) {
            $classId = $ta->class_id;
            $subjectId = $ta->subject_id;
            $studentCount = $ta->schoolClass?->students_count ?? $ta->schoolClass?->students?->count() ?? 0;

            $components = $allComponents->get($classId.'_'.$subjectId) ?? collect([]);

            $totalComponents = $components->count();
            $completedComponents = 0;
            $componentList = [];

            foreach ($components as $comp) {
                $actualCount = $comp->grades_count;
                $isComplete = $studentCount > 0 ? ($actualCount >= $studentCount) : ($actualCount > 0);
                if ($isComplete) {
                    $completedComponents++;
                }
                $componentList[] = [
                    'id' => $comp->id,
                    'name' => $comp->name,
                    'code' => $comp->code,
                    'grades_count' => $actualCount,
                    'expected_count' => $studentCount,
                    'is_completed' => $isComplete,
                ];
            }

            return [
                'class_id' => $classId,
                'class_name' => $ta->schoolClass?->name,
                'subject_id' => $subjectId,
                'subject_name' => $ta->subject?->name,
                'student_count' => $studentCount,
                'total_components' => $totalComponents,
                'completed_components' => $completedComponents,
                'completion_rate' => $totalComponents > 0 ? round(($completedComponents / $totalComponents) * 100, 2) : 0,
                'components' => $componentList,
            ];
        })->values()->all();

        $totalHours = (int) $assignments->sum('hours_per_week');
        $targetHours = 24;

        $latestSalarySlip = $teacherId ? TeacherSalarySlip::where('teacher_id', $teacherId)
            ->where('status', '!=', 'draft')
            ->orderBy('period_year', 'desc')
            ->orderBy('period_month', 'desc')
            ->with('file')
            ->first() : null;

        $salarySlipsCount = $teacherId ? TeacherSalarySlip::where('teacher_id', $teacherId)->count() : 0;

        return [
            'teacher' => [
                'id' => $teacher?->id,
                'name' => $teacher?->full_name ?? $user->username,
                'nip' => $teacher?->nip,
            ],
            'teaching_classes_count' => $assignments->count(),
            'teaching_hours_recap' => [
                'total_hours' => $totalHours,
                'target_hours' => $targetHours,
                'is_target_met' => $totalHours >= $targetHours,
                'difference' => $totalHours - $targetHours,
            ],
            'latest_salary_slip' => $latestSalarySlip,
            'salary_slips_count' => $salarySlipsCount,
            'today_day' => $todayDay,
            'today_schedules' => $todaySchedules,
            'pending_journals_count' => $pendingJournals,
            'pending_essay_grading_count' => $pendingEssayGradingCount,
            'grading_progress' => $gradingProgress,
            'quick_actions' => [
                'submit_journal' => '/api/v1/journals',
                'grade_essay' => '/api/v1/exams',
                'facility_booking' => '/api/v1/bookings',
                'ticket_helpdesk' => '/api/v1/tickets',
            ],
        ];
    }

    /**
     * BE-RPT-02 / GAP-C: Dashboard Siswa.
     */
    public function getStudentDashboard(Request $request): array
    {
        $user = $request->user();
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        $student = Student::where('user_id', $user->id)->first();
        if (! $student && ! $user->hasRole(['super_admin', 'admin'])) {
            throw new HttpException(403, 'Akses ditolak: Profil siswa tidak ditemukan.');
        }

        $studentId = $student?->id;
        $activeClass = $student?->classes()
            ->wherePivotNull('left_at')
            ->whereHas('academicYear', fn ($q) => $q->where('is_active', true))
            ->first() ?? $student?->classes()->wherePivotNull('left_at')->first() ?? $student?->classes()->first();

        // 1. Presensi Hari Ini
        $todayAttendance = $studentId
            ? Attendance::where('student_id', $studentId)
                ->whereDate('att_date', Carbon::today())
                ->orderBy('created_at', 'desc')
                ->first()
            : null;

        $allAtts = $studentId
            ? Attendance::where('student_id', $studentId)
                ->orderBy('created_at', 'desc')
                ->get()
                ->unique('att_date')
            : collect([]);
        $totalAtt = $allAtts->count();
        $hadirAtt = $allAtts->where('status', 'hadir')->count();
        $attendanceRate = $totalAtt > 0 ? round(($hadirAtt / $totalAtt) * 100, 2) : 100.0;

        // GAP-C: Rekap Kehadiran Bulan Berjalan (Deduplikasi per tanggal unik)
        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth = Carbon::now()->endOfMonth()->toDateString();

        $monthAttsRecords = $studentId
            ? Attendance::where('student_id', $studentId)
                ->whereBetween('att_date', [$startOfMonth, $endOfMonth])
                ->orderBy('created_at', 'desc')
                ->get()
                ->unique('att_date')
            : collect([]);

        $monthAtts = $monthAttsRecords->groupBy('status')->map->count()->toArray();

        $monthTotal = $monthAttsRecords->count();
        $monthHadir = ($monthAtts['hadir'] ?? 0) + ($monthAtts['terlambat'] ?? 0);
        $monthRate = $monthTotal > 0 ? round(($monthHadir / $monthTotal) * 100, 2) : 100.0;

        $currentMonthAttendance = [
            'month' => Carbon::now()->translatedFormat('F Y'),
            'start_date' => $startOfMonth,
            'end_date' => $endOfMonth,
            'total_recorded' => $monthTotal,
            'hadir' => $monthAtts['hadir'] ?? 0,
            'izin' => $monthAtts['izin'] ?? 0,
            'sakit' => $monthAtts['sakit'] ?? 0,
            'alpa' => $monthAtts['alpa'] ?? 0,
            'terlambat' => $monthAtts['terlambat'] ?? 0,
            'rate' => $monthRate,
        ];

        // GAP-C: Jadwal Pelajaran Besok
        $dayNames = [
            1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
            5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
        ];
        $tomorrowDayIso = Carbon::now()->addDay()->dayOfWeekIso;
        $tomorrowDay = $dayNames[$tomorrowDayIso] ?? 'Senin';

        $tomorrowSchedules = ($activeClass)
            ? Schedule::where('class_id', $activeClass->id)
                ->where('day_of_week', $tomorrowDayIso)
                ->with(['subject', 'teacher', 'room', 'timeSlot'])
                ->get()
            : collect([]);

        // Poin Prestasi & Pelanggaran
        $prestasiPoints = $studentId
            ? (int) StudentPoint::where('student_id', $studentId)
                ->whereHas('pointType', fn ($q) => $q->where('kind', 'prestasi'))
                ->join('point_types', 'student_points.point_type_id', '=', 'point_types.id')
                ->sum('point_types.points')
            : 0;

        $pelanggaranPoints = $studentId
            ? (int) StudentPoint::where('student_id', $studentId)
                ->whereHas('pointType', fn ($q) => $q->where('kind', 'pelanggaran'))
                ->join('point_types', 'student_points.point_type_id', '=', 'point_types.id')
                ->sum('point_types.points')
            : 0;

        $unreturnedLoans = Loan::where('borrower_id', $user->id)
            ->whereNull('returned_at')
            ->count();

        // GAP-C: Ujian Hari Ini & Ujian Mendatang (dengan indikasi kebutuhan token)
        $now = Carbon::now();
        $studentExamIds = $studentId
            ? ExamParticipant::where('student_id', $studentId)->pluck('exam_id')->toArray()
            : [];

        $examsQuery = Exam::whereIn('id', $studentExamIds)
            ->whereIn('status', ['dijadwalkan', 'berlangsung', 'published'])
            ->where('end_at', '>=', $now)
            ->with(['subject', 'rooms'])
            ->orderBy('start_at', 'asc')
            ->get();

        $todayExams = [];

        $studentSessions = $studentId && ! empty($studentExamIds)
            ? ExamSession::whereIn('exam_id', $studentExamIds)
                ->where('student_id', $studentId)
                ->orderBy('created_at', 'desc')
                ->get()
                ->keyBy('exam_id')
            : collect([]);

        foreach ($examsQuery as $ex) {
            $start = $ex->start_at ? Carbon::parse($ex->start_at) : null;
            $end = $ex->end_at ? Carbon::parse($ex->end_at) : null;
            $isToday = $start ? $start->isToday() : false;
            $isOngoing = ($start && $end && $now->between($start, $end)) || ($ex->status === 'berlangsung');

            $requiresToken = $ex->rooms->contains(fn ($r) => ! empty($r->token));

            $session = $studentSessions->get($ex->id);
            $sessionStatus = $session?->status ?? 'belum_mulai';

            $examData = [
                'id' => $ex->id,
                'title' => $ex->title,
                'exam_type' => $ex->exam_type,
                'subject_name' => $ex->subject?->name,
                'start_at' => $ex->start_at?->toIso8601String(),
                'end_at' => $ex->end_at?->toIso8601String(),
                'duration_min' => $ex->duration_min,
                'is_today' => $isToday,
                'is_ongoing' => $isOngoing,
                'requires_token' => $requiresToken,
                'session_status' => $sessionStatus,
            ];

            if ($isToday || $isOngoing) {
                $todayExams[] = $examData;
            }
        }

        return [
            'student' => [
                'id' => $student?->id,
                'name' => $student?->full_name ?? $user->username,
                'nis' => $student?->nis,
                'class' => $activeClass?->name,
            ],
            'attendance' => [
                'today_status' => $todayAttendance?->status ?? 'belum_tercatat',
                'today_note' => $todayAttendance?->note,
                'rate' => $attendanceRate,
                'total_recorded' => $totalAtt,
            ],
            'current_month_attendance' => $currentMonthAttendance,
            'tomorrow_day' => $tomorrowDay,
            'tomorrow_schedules' => $tomorrowSchedules,
            'points' => [
                'prestasi' => $prestasiPoints,
                'pelanggaran' => $pelanggaranPoints,
                'balance' => $prestasiPoints - $pelanggaranPoints,
            ],
            'library' => [
                'unreturned_loans_count' => $unreturnedLoans,
            ],
            'today_exams' => $todayExams,
            'upcoming_exams' => $examsQuery->take(5)->map(function ($ex) {
                $item = clone $ex;
                $item->unsetRelation('rooms');
                $item->makeHidden(['sync_node_token']);

                return $item;
            })->values(),
            'quick_menus' => [
                'download_progress_report' => '/api/v1/progress-reports',
                'exam_cards' => '/api/v1/exams',
                'points_history' => '/api/v1/student-points',
            ],
        ];
    }

    /**
     * BE-RPT-02 / GAP-D: Dashboard Wali Murid / Orang Tua (Anti-IDOR Check).
     */
    public function getGuardianDashboard(?string $studentId, Request $request): array
    {
        $user = $request->user();
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        if (empty($studentId)) {
            $studentId = $request->query('student_id');
        }

        $isAdmin = $user->hasRole(['super_admin', 'admin', 'principal']);
        if (empty($studentId)) {
            if ($isAdmin) {
                $student = Student::with(['classes.homeroomTeacher.user'])->firstOrFail();
                $studentId = $student->id;
            } else {
                $guardian = Guardian::where('user_id', $user->id)->first();
                if (! $guardian) {
                    throw new HttpException(403, 'Akses ditolak: Akun Anda tidak terdaftar sebagai wali murid.');
                }
                $firstChild = $guardian->students()->first();
                if (! $firstChild) {
                    throw new HttpException(404, 'Tidak ada data siswa yang terhubung dengan akun wali murid ini.');
                }
                $studentId = $firstChild->id;
            }
        }

        $student = Student::with(['classes.homeroomTeacher.user'])->findOrFail($studentId);

        // Anti-IDOR: Periksa relasi kepemilikan anak asuh
        if (! $isAdmin) {
            $guardian = Guardian::where('user_id', $user->id)->first();
            if (! $guardian) {
                throw new HttpException(403, 'Akses ditolak: Akun Anda tidak terdaftar sebagai wali murid.');
            }

            $isChild = $guardian->students()->where('students.id', $studentId)->exists();
            if (! $isChild) {
                throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang mengakses data siswa ini.');
            }
        }

        $todayAttendance = Attendance::where('student_id', $studentId)->whereDate('att_date', Carbon::today())->first();

        $attCounts = Attendance::where('student_id', $studentId)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $latestGrades = Grade::where('student_id', $studentId)
            ->with(['component'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $prestasiPoints = (int) StudentPoint::where('student_id', $studentId)
            ->whereHas('pointType', fn ($q) => $q->where('kind', 'prestasi'))
            ->join('point_types', 'student_points.point_type_id', '=', 'point_types.id')
            ->sum('point_types.points');

        $pelanggaranPoints = (int) StudentPoint::where('student_id', $studentId)
            ->whereHas('pointType', fn ($q) => $q->where('kind', 'pelanggaran'))
            ->join('point_types', 'student_points.point_type_id', '=', 'point_types.id')
            ->sum('point_types.points');

        // GAP-D: Catatan Karakter Terbaru dari Wali Kelas (Bukan konseling privat!)
        $latestCharacterNote = CharacterNote::with(['period', 'author'])
            ->where('student_id', $studentId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        $characterNoteData = $latestCharacterNote ? [
            'id' => $latestCharacterNote->id,
            'period_name' => $latestCharacterNote->period?->month ? 'Bulan ke-'.$latestCharacterNote->period?->month : 'Periode Penilaian',
            'homeroom_note' => $latestCharacterNote->homeroom_note,
            'scale_data' => $latestCharacterNote->scale_data,
            'author_name' => $latestCharacterNote->author?->username,
            'created_at' => $latestCharacterNote->created_at?->toIso8601String(),
        ] : null;

        // GAP-D: Kontak Wali Kelas
        $activeClass = $student->classes()
            ->wherePivotNull('left_at')
            ->whereHas('academicYear', fn ($q) => $q->where('is_active', true))
            ->first() ?? $student->classes()->wherePivotNull('left_at')->first() ?? $student->classes()->first();
        $homeroomTeacher = $activeClass?->homeroomTeacher;
        $homeroomUser = $homeroomTeacher?->user;
        $homeroomPhone = $homeroomTeacher?->phone ?? $homeroomUser?->phone;

        $homeroomTeacherContact = $homeroomTeacher ? [
            'teacher_id' => $homeroomTeacher->id,
            'name' => $homeroomTeacher->full_name,
            'nip' => $homeroomTeacher->nip,
            'phone' => $homeroomPhone,
            'email' => $homeroomUser?->email,
        ] : null;

        // GAP-D: Kontak Guru BK (Ditugaskan pada kasus anak atau konselor sekolah)
        $activeCase = CounselingCase::where('student_id', $studentId)
            ->with('counselor.teacher')
            ->whereNotNull('counselor_id')
            ->first();

        $counselorUser = $activeCase?->counselor
            ?? User::whereHas('roles', function ($q) {
                $q->whereIn('code', ['counselor', 'guru_bk', 'bk']);
            })->with('teacher')->first();
        $counselorPhone = $counselorUser?->teacher?->phone ?? $counselorUser?->phone;

        $counselorContact = $counselorUser ? [
            'user_id' => $counselorUser->id,
            'name' => $counselorUser->teacher?->full_name ?? $counselorUser->username,
            'phone' => $counselorPhone,
            'email' => $counselorUser->email,
        ] : null;

        return [
            'student' => [
                'id' => $student->id,
                'name' => $student->full_name,
                'nis' => $student->nis,
                'class' => $activeClass?->name,
            ],
            'attendance_today' => [
                'status' => $todayAttendance?->status ?? 'belum_tercatat',
                'note' => $todayAttendance?->note,
            ],
            'attendance_summary' => [
                'hadir' => $attCounts['hadir'] ?? 0,
                'izin' => $attCounts['izin'] ?? 0,
                'sakit' => $attCounts['sakit'] ?? 0,
                'alpa' => $attCounts['alpa'] ?? 0,
                'terlambat' => $attCounts['terlambat'] ?? 0,
            ],
            'latest_grades' => $latestGrades,
            'character_points' => [
                'prestasi' => $prestasiPoints,
                'pelanggaran' => $pelanggaranPoints,
                'balance' => $prestasiPoints - $pelanggaranPoints,
            ],
            'latest_character_note' => $characterNoteData,
            'homeroom_teacher_contact' => $homeroomTeacherContact,
            'counselor_contact' => $counselorContact,
            'payment_administration' => [
                'status' => 'not_available',
                'notes' => 'Modul pembayaran/administrasi keuangan saat ini berstatus out-of-scope (menunggu kebijakan sekolah).',
            ],
            'quick_actions' => [
                'download_progress_report_pdf' => '/api/v1/progress-reports',
                'contact_homeroom_url' => $this->formatWhatsAppUrl($homeroomPhone),
                'contact_counselor_url' => $this->formatWhatsAppUrl($counselorPhone),
            ],
        ];
    }

    protected function authorizeExecutive(?User $user): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        $allowed = $user->hasRole(['super_admin', 'admin', 'principal', 'admin_tu', 'kurikulum', 'kesiswaan', 'student_affairs']) ||
            $user->hasPermission('dashboard.executive') ||
            $user->hasPermission('executive.dashboard.view');

        if (! $allowed) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang mengakses Dashboard Eksekutif.');
        }
    }

    /**
     * Normalisasi nomor telepon dan buat tautan resmi WhatsApp dengan kode negara Indonesia (62).
     */
    protected function formatWhatsAppUrl(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (empty($digits)) {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        return 'https://wa.me/'.$digits;
    }
}
