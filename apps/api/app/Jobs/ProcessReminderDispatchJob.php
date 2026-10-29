<?php

namespace App\Jobs;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\Guardian;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\ReminderDispatch;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessReminderDispatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300];

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * Fencing token to protect against zombie workers and concurrent overwrites.
     */
    public ?string $claimToken = null;

    public function __construct(
        public string $dispatchId,
        ?string $claimToken = null
    ) {
        $this->claimToken = $claimToken;
    }

    public function handle(): void
    {
        $dispatch = ReminderDispatch::find($this->dispatchId);

        if (! $dispatch) {
            Log::warning("ReminderDispatch not found: {$this->dispatchId}");

            return;
        }

        // Idempotency: jika sudah dispatched atau di-skip, jangan proses ulang (terminal states)
        if (in_array($dispatch->status, [
            'dispatched',
            'skipped_by_preference',
            'skipped_stale_submitted',
            'skipped_stale_cancelled',
            'skipped_stale_recorded',
            'skipped_stale_status_changed',
            'skipped_stale_unenrolled',
            'skipped_stale_reassigned',
            'skipped_stale_relationship_changed',
            'skipped_tenant_mismatch',
        ])) {
            return;
        }

        if (! $this->claimToken) {
            $this->claimToken = (string) Str::uuid();
        }

        $now = Carbon::now();
        $claimExpiresAt = $now->copy()->addMinutes(15);

        // 1. ATOMIC CLAIM: Transisi status ke 'processing' dengan Fencing Claim Token
        // Mencegah dua worker memproses record yang sama secara bersamaan (race condition)
        $claimed = ReminderDispatch::where('id', $this->dispatchId)
            ->whereIn('status', ['pending', 'failed'])
            ->update([
                'status' => 'processing',
                'claim_token' => $this->claimToken,
                'claim_expires_at' => $claimExpiresAt,
                'updated_at' => $now,
            ]);

        if ($claimed === 0) {
            $isMine = ReminderDispatch::where('id', $this->dispatchId)
                ->where('status', 'processing')
                ->where('claim_token', $this->claimToken)
                ->exists();

            if (! $isMine) {
                // Sudah diklaim atau selesai oleh worker lain
                return;
            }
        }

        // Muat ulang record setelah klaim
        $dispatch->refresh();

        // 2. ISOLASI TENANT & RECIPIENT VALIDATION
        if ($dispatch->school_id) {
            $recipient = User::find($dispatch->recipient_id);
            if (! $recipient) {
                $this->markFailed('Recipient user record does not exist');

                return;
            }

            if ($recipient->school_id && $recipient->school_id !== $dispatch->school_id) {
                $this->markSkipped('skipped_tenant_mismatch', 'Tenant boundary mismatch between recipient and event');
                Log::warning("Tenant mismatch rejected: Recipient {$recipient->id} ({$recipient->school_id}) does not belong to school {$dispatch->school_id}");

                return;
            }
        }

        // 3. PRE-FLIGHT / STALE EVENT CHECK: Cegah pengingat basi / usang
        if ($this->isEventStale($dispatch)) {
            return;
        }

        // 4. EVALUASI PREFERENSI NOTIFIKASI
        $meta = $dispatch->metadata ?? [];
        $title = $meta['title'] ?? 'Pengingat Sistem';
        $body = $meta['body'] ?? '';
        $notifType = $meta['notif_type'] ?? 'system_alert';
        $deepLinkData = $meta['deep_link_data'] ?? null;

        $prefType = match ($dispatch->event_type) {
            'cbt_exam', 'lms_assignment' => 'academic_announcement',
            'att_recording', 'att_alpha' => 'attendance_alert',
            default => 'system_alert',
        };

        $preference = NotificationPreference::where('user_id', $dispatch->recipient_id)
            ->where('notif_type', $prefType)
            ->first();

        if ($preference && ! $preference->is_enabled) {
            $this->markSkipped('skipped_by_preference');

            return;
        }

        // 5. PENGIRIMAN IDEMPOTEN DALAM TRANSAKSI BASIS DATA DENGAN FENCING TOKEN
        try {
            DB::transaction(function () use ($dispatch, $notifType, $title, $body, $deepLinkData) {
                // Fencing check: pastikan dispatch masih dipegang oleh worker ini
                $current = ReminderDispatch::where('id', $dispatch->id)
                    ->where('claim_token', $this->claimToken)
                    ->where('status', 'processing')
                    ->lockForUpdate()
                    ->first();

                if (! $current) {
                    Log::warning("Fencing violation: Worker {$this->claimToken} lost claim on dispatch {$dispatch->id}. Aborting send.");

                    return;
                }

                // Pastikan tidak ada duplikasi record notifikasi jika terjadi crash sebelum update status
                $alreadyCreated = Notification::where('user_id', $dispatch->recipient_id)
                    ->where('notif_type', $notifType)
                    ->whereJsonContains('data->event_id', $dispatch->event_id)
                    ->whereJsonContains('data->milestone', $dispatch->milestone)
                    ->exists();

                if (! $alreadyCreated) {
                    $payloadData = array_merge($deepLinkData ?? [], [
                        'event_id' => $dispatch->event_id,
                        'milestone' => $dispatch->milestone,
                    ]);

                    Notification::create([
                        'user_id' => $dispatch->recipient_id,
                        'notif_type' => $notifType,
                        'title' => $title,
                        'body' => $body,
                        'data' => $payloadData,
                        'read_at' => null,
                    ]);
                }

                $current->update([
                    'status' => 'dispatched',
                    'claim_token' => null,
                    'claim_expires_at' => null,
                    'dispatched_at' => Carbon::now(),
                    'error_message' => null,
                ]);
            });

            Log::info("Reminder dispatched: ID {$dispatch->id}, event: {$dispatch->event_type}, milestone: {$dispatch->milestone}");
        } catch (\Throwable $e) {
            $this->markFailed($e->getMessage());
            Log::error("Failed to dispatch reminder ID {$dispatch->id}: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Tandai dispatch sebagai skipped dengan verifikasi fencing claim token.
     */
    protected function markSkipped(string $status, ?string $errorMessage = null): bool
    {
        $now = Carbon::now();
        $affected = ReminderDispatch::where('id', $this->dispatchId)
            ->where('claim_token', $this->claimToken)
            ->where('status', 'processing')
            ->update([
                'status' => $status,
                'claim_token' => null,
                'claim_expires_at' => null,
                'dispatched_at' => $now,
                'error_message' => $errorMessage,
                'updated_at' => $now,
            ]);

        return $affected > 0;
    }

    /**
     * Tandai dispatch sebagai failed dengan verifikasi fencing claim token.
     */
    protected function markFailed(string $errorMessage): bool
    {
        $now = Carbon::now();
        $affected = ReminderDispatch::where('id', $this->dispatchId)
            ->where('claim_token', $this->claimToken)
            ->update([
                'status' => 'failed',
                'claim_token' => null,
                'claim_expires_at' => null,
                'error_message' => $errorMessage,
                'updated_at' => $now,
            ]);

        return $affected > 0;
    }

    /**
     * Memeriksa apakah event sudah tidak relevan / berubah status / hubungan penerima berubah
     * sebelum notifikasi terkirim ke pengguna.
     */
    protected function isEventStale(ReminderDispatch $dispatch): bool
    {
        // A. LMS: jika siswa sudah mengumpulkan tugas atau tidak lagi terdaftar di kelas
        if ($dispatch->event_type === 'lms_assignment') {
            $assignment = Assignment::find($dispatch->event_id);
            if (! $assignment) {
                $this->markSkipped('skipped_stale_cancelled');

                return true;
            }

            $student = Student::where('user_id', $dispatch->recipient_id)->first();
            if ($student) {
                $hasSubmitted = AssignmentSubmission::where('assignment_id', $dispatch->event_id)
                    ->where('student_id', $student->id)
                    ->exists();

                if ($hasSubmitted) {
                    $this->markSkipped('skipped_stale_submitted');

                    return true;
                }

                // Cek keanggotaan kelas jika kelas memiliki relasi siswa
                if ($assignment->class_id) {
                    $classHasStudents = DB::table('class_students')
                        ->where('class_id', $assignment->class_id)
                        ->exists();

                    if ($classHasStudents) {
                        $isEnrolled = $student->classes()
                            ->where('classes.id', $assignment->class_id)
                            ->wherePivotNull('left_at')
                            ->exists();

                        if (! $isEnrolled) {
                            $this->markSkipped('skipped_stale_unenrolled');

                            return true;
                        }
                    }
                }
            }
        }

        // B. CBT: jika ujian dibatalkan, telah berakhir, atau peserta dikeluarkan dari ujian
        if ($dispatch->event_type === 'cbt_exam') {
            $exam = Exam::find($dispatch->event_id);
            if (! $exam || in_array($exam->status, ['cancelled', 'selesai', 'archived'])) {
                $this->markSkipped('skipped_stale_cancelled');

                return true;
            }

            $student = Student::where('user_id', $dispatch->recipient_id)->first();
            if ($student) {
                $hasParticipants = ExamParticipant::where('exam_id', $dispatch->event_id)->exists();
                if ($hasParticipants) {
                    $isParticipant = ExamParticipant::where('exam_id', $dispatch->event_id)
                        ->where('student_id', $student->id)
                        ->exists();

                    if (! $isParticipant) {
                        $this->markSkipped('skipped_stale_unenrolled');

                        return true;
                    }
                }
            }
        }

        // C. Presensi Harian: jika kelas sudah direkap atau wali kelas telah berganti
        if ($dispatch->event_type === 'att_recording') {
            $class = SchoolClass::with('homeroomTeacher')->find($dispatch->event_id);
            if (! $class) {
                $this->markSkipped('skipped_stale_cancelled');

                return true;
            }

            // Periksa apakah guru penerima masih menjadi wali kelas
            if ($class->homeroom_teacher_id) {
                $homeroomTeacher = $class->homeroomTeacher;
                if ($homeroomTeacher && $homeroomTeacher->user_id !== $dispatch->recipient_id) {
                    $this->markSkipped('skipped_stale_reassigned');

                    return true;
                }
            }

            $schoolTz = config('app.school_timezone', 'Asia/Jakarta');
            $today = Carbon::now($schoolTz)->toDateString();

            $hasAttendance = Attendance::whereDate('att_date', $today)
                ->whereHas('student.classes', fn ($q) => $q->where('classes.id', $dispatch->event_id))
                ->exists();

            if ($hasAttendance) {
                $this->markSkipped('skipped_stale_recorded');

                return true;
            }
        }

        // D. Presensi Alpha: jika data absensi dihapus, dikoreksi, atau hubungan wali murid tidak valid lagi
        if ($dispatch->event_type === 'att_alpha') {
            $att = Attendance::with('student.guardians')->find($dispatch->event_id);
            if (! $att || ! in_array($att->status, ['alpha', 'alpa', 'a'])) {
                $this->markSkipped('skipped_stale_status_changed');

                return true;
            }

            $student = $att->student;
            if (! $student) {
                $this->markSkipped('skipped_stale_cancelled');

                return true;
            }

            // Jika notifikasi ditujukan ke wali (bukan siswa sendiri), pastikan relasi wali masih ada
            if ($student->user_id !== $dispatch->recipient_id) {
                $isGuardian = Guardian::where('user_id', $dispatch->recipient_id)->exists();
                if ($isGuardian) {
                    $isLinkedGuardian = $student->guardians()
                        ->where('guardians.user_id', $dispatch->recipient_id)
                        ->exists();

                    if (! $isLinkedGuardian) {
                        $this->markSkipped('skipped_stale_relationship_changed');

                        return true;
                    }
                }
            }
        }

        return false;
    }
}
