<?php

namespace App\Services\Ntf;

use App\Jobs\ProcessReminderDispatchJob;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\ReminderDispatch;
use App\Models\SchoolClass;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReminderService
{
    /**
     * Jalankan seluruh scheduler pengingat otomatis (CBT, LMS, ATT).
     */
    public function dispatchAllReminders(?string $schoolId = null, bool $async = false): array
    {
        $cbt = $this->dispatchCbtExamReminders($schoolId, $async);
        $lms = $this->dispatchLmsAssignmentReminders($schoolId, $async);
        $attendance = $this->dispatchAttendanceReminders($schoolId, $async);

        $totalDispatched = ($cbt['dispatched'] ?? 0) + ($lms['dispatched'] ?? 0) + ($attendance['dispatched'] ?? 0);
        $totalSkipped = ($cbt['skipped'] ?? 0) + ($lms['skipped'] ?? 0) + ($attendance['skipped'] ?? 0);

        try {
            AuditLog::create([
                'action' => 'REMINDER_ENGINE_DISPATCH_ALL',
                'entity' => 'reminder_dispatches',
                'new_value' => [
                    'school_id' => $schoolId,
                    'total_dispatched' => $totalDispatched,
                    'total_skipped' => $totalSkipped,
                    'cbt' => $cbt,
                    'lms' => $lms,
                    'attendance' => $attendance,
                ],
            ]);
        } catch (\Throwable $e) {
            // Keep going if audit logging fails
        }

        return [
            'cbt' => $cbt,
            'lms' => $lms,
            'attendance' => $attendance,
            'total_dispatched' => $totalDispatched,
            'total_skipped' => $totalSkipped,
        ];
    }

    /**
     * Dispatch pengingat ujian CBT mendatang untuk peserta terdaftar.
     * Milestones: 24h & 1h sebelum ujian mulai.
     * Keamanan: Tanpa token ujian & tanpa kunci jawaban.
     */
    public function dispatchCbtExamReminders(?string $schoolId = null, bool $async = false): array
    {
        $schoolTz = config('app.school_timezone', 'Asia/Jakarta');
        $now = Carbon::now();
        $dispatched = 0;
        $skipped = 0;

        $milestones = [
            '24h' => [
                'from' => $now->copy(),
                'to' => $now->copy()->addHours(24),
            ],
            '1h' => [
                'from' => $now->copy(),
                'to' => $now->copy()->addHours(1),
            ],
        ];

        foreach ($milestones as $milestone => $window) {
            $examQuery = Exam::query()
                ->whereIn('status', ['scheduled', 'active'])
                ->whereBetween('start_at', [$window['from'], $window['to']])
                ->with(['subject', 'participants.student.user']);

            if ($schoolId) {
                $examQuery->where('school_id', $schoolId);
            }

            $exams = $examQuery->get();

            foreach ($exams as $exam) {
                $subjectName = $exam->subject?->name ?? 'Mata Pelajaran';
                $startFormatted = $exam->start_at->copy()->timezone($schoolTz)->translatedFormat('d M Y H:i');

                foreach ($exam->participants as $participant) {
                    $student = $participant->student;
                    if (! $student || ! $student->user_id) {
                        continue;
                    }

                    // Hanya peserta ujian dari sekolah yang bersangkutan jika school_id dispesifikasi
                    if ($schoolId && $student->school_id && $student->school_id !== $schoolId) {
                        continue;
                    }

                    $title = "Pengingat Ujian CBT: {$exam->title}";
                    $body = $milestone === '1h'
                        ? "Ujian {$exam->title} ({$subjectName}) akan dimulai dalam 1 jam lagi ({$startFormatted} WIB). Pastikan perangkat dan koneksi Anda siap."
                        : "Ujian {$exam->title} ({$subjectName}) dijadwalkan pada {$startFormatted} WIB. Silakan persiapkan materi Anda.";

                    // Payload aman tanpa token/kunci jawaban
                    $deepLinkData = [
                        'exam_id' => $exam->id,
                        'exam_title' => $exam->title,
                        'start_at' => $exam->start_at->toIso8601String(),
                        'deep_link' => '/portal/siswa/cbt',
                    ];

                    $result = $this->createAndDispatch(
                        schoolId: $exam->school_id ?? $student->school_id,
                        eventType: 'cbt_exam',
                        eventId: $exam->id,
                        recipientId: $student->user_id,
                        milestone: $milestone,
                        title: $title,
                        body: $body,
                        notifType: 'academic_announcement',
                        deepLinkData: $deepLinkData,
                        async: $async
                    );

                    if ($result) {
                        $dispatched++;
                    } else {
                        $skipped++;
                    }
                }
            }
        }

        return [
            'dispatched' => $dispatched,
            'skipped' => $skipped,
        ];
    }

    /**
     * Dispatch pengingat tenggat tugas LMS untuk siswa yang belum mengumpulkan.
     * Milestones: 24h & 3h sebelum deadline.
     */
    public function dispatchLmsAssignmentReminders(?string $schoolId = null, bool $async = false): array
    {
        $schoolTz = config('app.school_timezone', 'Asia/Jakarta');
        $now = Carbon::now();
        $dispatched = 0;
        $skipped = 0;

        $milestones = [
            '24h' => [
                'from' => $now->copy(),
                'to' => $now->copy()->addHours(24),
            ],
            '3h' => [
                'from' => $now->copy(),
                'to' => $now->copy()->addHours(3),
            ],
        ];

        foreach ($milestones as $milestone => $window) {
            $assignmentQuery = Assignment::query()
                ->whereBetween('due_at', [$window['from'], $window['to']])
                ->with(['class.students.user', 'subject']);

            if ($schoolId) {
                $assignmentQuery->whereHas('class', fn ($q) => $q->where('school_id', $schoolId));
            }

            $assignments = $assignmentQuery->get();

            foreach ($assignments as $assignment) {
                $schoolClass = $assignment->class;
                if (! $schoolClass) {
                    continue;
                }

                $subjectName = $assignment->subject?->name ?? 'Mata Pelajaran';
                $dueFormatted = $assignment->due_at->copy()->timezone($schoolTz)->translatedFormat('d M Y H:i');

                // Dapatkan ID siswa yang sudah mengumpulkan tugas
                $submittedStudentIds = AssignmentSubmission::where('assignment_id', $assignment->id)
                    ->pluck('student_id')
                    ->all();

                // Dapatkan siswa aktif di kelas yang belum mengumpulkan
                $students = $schoolClass->students()
                    ->wherePivotNull('left_at')
                    ->whereNotIn('students.id', $submittedStudentIds)
                    ->whereNotNull('user_id')
                    ->get();

                foreach ($students as $student) {
                    if ($schoolId && $student->school_id && $student->school_id !== $schoolId) {
                        continue;
                    }

                    $title = "Pengingat Tenggat Tugas: {$assignment->title}";
                    $body = $milestone === '3h'
                        ? "Tenggat tugas '{$assignment->title}' ({$subjectName}) tersisa kurang dari 3 jam ({$dueFormatted} WIB). Segera selesaikan dan kumpulkan tugas Anda."
                        : "Tugas '{$assignment->title}' ({$subjectName}) akan jatuh tempo besok pada {$dueFormatted} WIB. Jangan lupa kumpulkan tepat waktu.";

                    $deepLinkData = [
                        'assignment_id' => $assignment->id,
                        'assignment_title' => $assignment->title,
                        'due_at' => $assignment->due_at->toIso8601String(),
                        'deep_link' => '/portal/siswa/tugas',
                    ];

                    $result = $this->createAndDispatch(
                        schoolId: $schoolClass->school_id ?? $student->school_id,
                        eventType: 'lms_assignment',
                        eventId: $assignment->id,
                        recipientId: $student->user_id,
                        milestone: $milestone,
                        title: $title,
                        body: $body,
                        notifType: 'academic_announcement',
                        deepLinkData: $deepLinkData,
                        async: $async
                    );

                    if ($result) {
                        $dispatched++;
                    } else {
                        $skipped++;
                    }
                }
            }
        }

        return [
            'dispatched' => $dispatched,
            'skipped' => $skipped,
        ];
    }

    /**
     * Dispatch pengingat presensi:
     * 1. Pengingat batas waktu penginputan presensi harian untuk guru wali kelas (daily cutoff).
     * 2. Peringatan ketidakhadiran tanpa keterangan (alpha) hari ini untuk wali murid / siswa.
     */
    public function dispatchAttendanceReminders(?string $schoolId = null, bool $async = false): array
    {
        $schoolTz = config('app.school_timezone', 'Asia/Jakarta');
        $today = Carbon::now($schoolTz)->toDateString();
        $todayFormatted = Carbon::now($schoolTz)->translatedFormat('d M Y');
        $dispatched = 0;
        $skipped = 0;

        // 1. Pengingat Wali Kelas bila belum ada presensi siswa untuk kelasnya hari ini
        $classQuery = SchoolClass::query()
            ->whereNotNull('homeroom_teacher_id')
            ->with(['homeroomTeacher.user']);

        if ($schoolId) {
            $classQuery->where('school_id', $schoolId);
        }

        $classes = $classQuery->get();

        foreach ($classes as $class) {
            $teacher = $class->homeroomTeacher;
            if (! $teacher || ! $teacher->user_id) {
                continue;
            }

            // Periksa apakah kelas sudah memiliki catatan presensi hari ini
            $hasAttendanceToday = Attendance::whereDate('att_date', $today)
                ->whereHas('student.classes', fn ($q) => $q->where('classes.id', $class->id))
                ->exists();

            if (! $hasAttendanceToday) {
                $title = "Pengingat Presensi: Kelas {$class->name}";
                $body = "Presensi harian untuk kelas {$class->name} belum dicatat pada hari ini ({$todayFormatted}). Mohon segera rekap kehadiran siswa.";
                $deepLinkData = [
                    'class_id' => $class->id,
                    'class_name' => $class->name,
                    'date' => $today,
                    'deep_link' => '/portal/guru/presensi',
                ];

                $result = $this->createAndDispatch(
                    schoolId: $class->school_id ?? $teacher->school_id,
                    eventType: 'att_recording',
                    eventId: $class->id,
                    recipientId: $teacher->user_id,
                    milestone: "daily_cutoff_{$today}",
                    title: $title,
                    body: $body,
                    notifType: 'attendance_alert',
                    deepLinkData: $deepLinkData,
                    async: $async
                );

                if ($result) {
                    $dispatched++;
                } else {
                    $skipped++;
                }
            }
        }

        // 2. Notifikasi Alpha (ketidakhadiran tanpa keterangan) untuk wali murid / siswa
        $alphaQuery = Attendance::query()
            ->whereDate('att_date', $today)
            ->whereIn('status', ['alpha', 'alpa', 'a'])
            ->with(['student.guardians.user', 'student.user']);

        if ($schoolId) {
            $alphaQuery->whereHas('student', fn ($q) => $q->where('school_id', $schoolId));
        }

        $alphaAttendances = $alphaQuery->get();

        foreach ($alphaAttendances as $attendance) {
            $student = $attendance->student;
            if (! $student) {
                continue;
            }

            $guardians = $student->guardians->filter(fn ($g) => $g->user_id !== null);

            $recipients = $guardians->isNotEmpty()
                ? $guardians->pluck('user_id')
                : ($student->user_id ? collect([$student->user_id]) : collect());

            foreach ($recipients as $recipientId) {
                $title = "Pemberitahuan Presensi: {$student->full_name}";
                $body = "Siswa {$student->full_name} tercatat tidak hadir tanpa keterangan (Alpha) pada hari ini ({$todayFormatted}). Mohon hubungi pihak sekolah bila terdapat kekeliruan.";

                $deepLinkData = [
                    'student_id' => $student->id,
                    'attendance_id' => $attendance->id,
                    'date' => $today,
                    'deep_link' => '/portal/wali/presensi',
                ];

                $result = $this->createAndDispatch(
                    schoolId: $student->school_id,
                    eventType: 'att_alpha',
                    eventId: $attendance->id,
                    recipientId: $recipientId,
                    milestone: "alpha_{$today}",
                    title: $title,
                    body: $body,
                    notifType: 'attendance_alert',
                    deepLinkData: $deepLinkData,
                    async: $async
                );

                if ($result) {
                    $dispatched++;
                } else {
                    $skipped++;
                }
            }
        }

        return [
            'dispatched' => $dispatched,
            'skipped' => $skipped,
        ];
    }

    /**
     * Memastikan keunikan di level database dan melakukan dispatch job.
     */
    protected function createAndDispatch(
        ?string $schoolId,
        string $eventType,
        string $eventId,
        string $recipientId,
        string $milestone,
        string $title,
        string $body,
        string $notifType,
        ?array $deepLinkData,
        bool $async
    ): ?ReminderDispatch {
        // Cek keberadaan terlebih dahulu untuk mencegah SQL exception yang dapat membatalkan transaksi pada PostgreSQL
        $exists = ReminderDispatch::where('event_type', $eventType)
            ->where('event_id', $eventId)
            ->where('recipient_id', $recipientId)
            ->where('milestone', $milestone)
            ->exists();

        if ($exists) {
            Log::debug("Skipping duplicate reminder: {$eventType}/{$eventId}/{$recipientId}/{$milestone}");

            return null;
        }

        try {
            $dispatch = DB::transaction(function () use ($schoolId, $eventType, $eventId, $recipientId, $milestone, $title, $body, $notifType, $deepLinkData) {
                return ReminderDispatch::create([
                    'school_id' => $schoolId,
                    'event_type' => $eventType,
                    'event_id' => $eventId,
                    'recipient_id' => $recipientId,
                    'milestone' => $milestone,
                    'channel' => 'in_app',
                    'status' => 'pending',
                    'claim_token' => null,
                    'claim_expires_at' => null,
                    'metadata' => [
                        'title' => $title,
                        'body' => $body,
                        'notif_type' => $notifType,
                        'deep_link_data' => $deepLinkData,
                    ],
                ]);
            });
        } catch (\Throwable $e) {
            // Uniqueness violation pada [event_type, event_id, recipient_id, milestone]
            // Berarti sudah pernah dikirim atau sedang dalam antrean
            Log::debug("Skipping duplicate reminder: {$eventType}/{$eventId}/{$recipientId}/{$milestone}");

            return null;
        }

        if ($async) {
            ProcessReminderDispatchJob::dispatch($dispatch->id);
        } else {
            $job = new ProcessReminderDispatchJob($dispatch->id);
            $job->handle();
        }

        return $dispatch->fresh();
    }

    /**
     * Observability: Statistik metrik dispatch per status & event_type.
     */
    public function getStats(?string $schoolId = null): array
    {
        $query = ReminderDispatch::query();
        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        $total = (clone $query)->count();
        $byStatus = (clone $query)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $byEventType = (clone $query)
            ->selectRaw('event_type, count(*) as count')
            ->groupBy('event_type')
            ->pluck('count', 'event_type')
            ->toArray();

        $now = Carbon::now();
        $stuckCount = (clone $query)
            ->where('status', 'processing')
            ->where(function ($q) use ($now) {
                $q->where('updated_at', '<', $now->copy()->subMinutes(15))
                    ->orWhere(function ($q2) use ($now) {
                        $q2->whereNotNull('claim_expires_at')
                            ->where('claim_expires_at', '<', $now);
                    });
            })
            ->count();

        $failedCount = (clone $query)
            ->where('status', 'failed')
            ->count();

        return [
            'total' => $total,
            'by_status' => $byStatus,
            'by_event_type' => $byEventType,
            'stuck_processing' => $stuckCount,
            'failed' => $failedCount,
        ];
    }

    /**
     * Recovery: Jalankan ulang dispatch yang gagal.
     * Hanya mengambil error yang memenuhi syarat retry (tidak mengulang kesalahan permanen).
     */
    public function retryFailedDispatches(?string $schoolId = null, int $limit = 50, bool $async = false): int
    {
        $query = ReminderDispatch::where('status', 'failed')
            ->where(function ($q) {
                $q->whereNull('error_message')
                    ->orWhere(function ($q2) {
                        $q2->where('error_message', 'not like', '%Recipient user record does not exist%')
                            ->where('error_message', 'not like', '%Tenant boundary mismatch%');
                    });
            });

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        $failedIds = $query->limit($limit)->pluck('id')->all();
        $retried = 0;

        foreach ($failedIds as $id) {
            // Atomic update status kembali ke pending
            $affected = ReminderDispatch::where('id', $id)
                ->where('status', 'failed')
                ->update([
                    'status' => 'pending',
                    'claim_token' => null,
                    'claim_expires_at' => null,
                    'error_message' => null,
                    'updated_at' => Carbon::now(),
                ]);

            if ($affected > 0) {
                if ($async) {
                    ProcessReminderDispatchJob::dispatch($id);
                } else {
                    $job = new ProcessReminderDispatchJob($id);
                    $job->handle();
                }
                $retried++;
            }
        }

        return $retried;
    }

    /**
     * Recovery: Pulihkan dispatch yang macet di status 'processing' (misal worker crash/OOM).
     * Menggunakan timeout 15 menit konsisten dengan batas kerja worker.
     */
    public function recoverStuckProcessing(int $stuckMinutes = 15, bool $async = false): int
    {
        $threshold = Carbon::now()->subMinutes($stuckMinutes);
        $stuckQuery = ReminderDispatch::where('status', 'processing')
            ->where(function ($q) use ($threshold) {
                $q->where('updated_at', '<', $threshold)
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('claim_expires_at')
                            ->where('claim_expires_at', '<', Carbon::now());
                    });
            });

        $stuckIds = $stuckQuery->pluck('id')->all();
        $recovered = 0;

        foreach ($stuckIds as $id) {
            // Atomic update: hanya ubah jika record masih berstatus 'processing'
            $affected = ReminderDispatch::where('id', $id)
                ->where('status', 'processing')
                ->update([
                    'status' => 'pending',
                    'claim_token' => null,
                    'claim_expires_at' => null,
                    'error_message' => "Recovered from stuck processing (exceeded {$stuckMinutes}m threshold)",
                    'updated_at' => Carbon::now(),
                ]);

            if ($affected > 0) {
                if ($async) {
                    ProcessReminderDispatchJob::dispatch($id);
                } else {
                    $job = new ProcessReminderDispatchJob($id);
                    $job->handle();
                }
                $recovered++;
            }
        }

        return $recovered;
    }
}
