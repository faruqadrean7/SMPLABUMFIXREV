<?php

namespace App\Services\Bk;

use App\Models\AuditLog;
use App\Models\CounselingCase;
use App\Models\CounselingRequest;
use App\Models\CounselingSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CounselingRequestService
{
    /**
     * BE-BK-03: Ambil daftar permintaan konseling dengan filter dan scoping peranan.
     */
    public function listRequests(array $filters, Request $request): LengthAwarePaginator
    {
        $user = $request->user();
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        $query = CounselingRequest::with(['student.classes', 'requester', 'preferredCounselor', 'counselor'])
            ->orderBy('created_at', 'desc');

        if (! empty($user->school_id)) {
            $query->whereHas('student', fn ($sq) => $sq->where('school_id', $user->school_id));
        }

        // Scoping peranan
        if ($this->isCounselorOrAdminOrPrincipal($user)) {
            // Counselor, Admin, Kepsek dapat melihat semua permintaan di sekolahnya
            if (! empty($filters['student_id'])) {
                $query->where('student_id', $filters['student_id']);
            }
        } elseif ($this->isStudentUser($user)) {
            $studentId = $user->student?->id;
            $query->where(function ($q) use ($studentId, $user) {
                if ($studentId) {
                    $q->where('student_id', $studentId);
                }
                $q->orWhere('requested_by', $user->id);
            });
        } elseif ($this->isGuardianUser($user)) {
            $guardianStudentIds = $user->guardian?->students()->pluck('students.id')->toArray() ?? [];
            $query->where(function ($q) use ($guardianStudentIds, $user) {
                if (! empty($guardianStudentIds)) {
                    $q->whereIn('student_id', $guardianStudentIds);
                }
                $q->orWhere('requested_by', $user->id);
            });
        } elseif ($this->isTeacherUser($user)) {
            // Wali kelas hanya dapat melihat siswa di kelasnya
            $homeroomClassIds = SchoolClass::whereHas('homeroomTeacher', fn ($hq) => $hq->where('user_id', $user->id))
                ->pluck('id');

            $query->whereHas('student.classes', function ($cq) use ($homeroomClassIds) {
                $cq->whereIn('classes.id', $homeroomClassIds);
            });
        } else {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang melihat permintaan konseling.');
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('preferred_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('preferred_date', '<=', $filters['to_date']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * BE-BK-03: Ambil detail permintaan konseling dengan pemeriksaan hak akses anti-IDOR.
     */
    public function getRequest(string $id, Request $request): CounselingRequest
    {
        $user = $request->user();
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        $counselingRequest = CounselingRequest::with(['student.classes', 'requester', 'preferredCounselor', 'counselor'])
            ->findOrFail($id);

        $this->authorizeRequestAccess($user, $counselingRequest);

        return $counselingRequest;
    }

    /**
     * BE-BK-03: Buat permintaan konseling baru (oleh siswa, orang tua/wali, atau BK).
     */
    public function createRequest(array $data, Request $request): CounselingRequest
    {
        $user = $request->user();
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        $student = Student::findOrFail($data['student_id']);

        if (! empty($user->school_id) && ! empty($student->school_id) && $student->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Siswa berada di luar sekolah penugasan Anda.');
        }

        $requesterRole = 'other';

        // Validasi anti-IDOR
        if ($this->isStudentUser($user)) {
            $requesterRole = 'student';
            $studentId = $user->student?->id;
            if (! $studentId || $studentId !== $data['student_id']) {
                throw new HttpException(403, 'Akses ditolak: Anda hanya dapat mengajukan permintaan konseling untuk diri Anda sendiri.');
            }
        } elseif ($this->isGuardianUser($user)) {
            $requesterRole = 'guardian';
            $isOwnWard = $user->guardian?->students()->where('students.id', $data['student_id'])->exists();
            if (! $isOwnWard) {
                throw new HttpException(403, 'Akses ditolak: Anda hanya dapat mengajukan permintaan konseling untuk anak yang terdaftar di bawah perwalian Anda.');
            }
        } elseif ($this->isCounselorOrAdmin($user)) {
            $requesterRole = 'counselor';
        } elseif ($this->isTeacherUser($user)) {
            $requesterRole = 'teacher';
        }

        // Cegah spamming/duplikasi permintaan yang masih aktif
        $existingActive = CounselingRequest::where('student_id', $data['student_id'])
            ->where('requested_by', $user->id)
            ->whereIn('status', ['pending', 'scheduled'])
            ->where('topic', $data['topic'])
            ->first();

        if ($existingActive) {
            throw new HttpException(422, 'Permintaan konseling serupa untuk siswa ini masih dalam status aktif (menunggu jadwal atau telah dijadwalkan).');
        }

        $counselingRequest = CounselingRequest::create([
            'student_id' => $data['student_id'],
            'requested_by' => $user->id,
            'requester_role' => $requesterRole,
            'topic' => $data['topic'],
            'preferred_date' => $data['preferred_date'] ?? null,
            'preferred_time' => $data['preferred_time'] ?? null,
            'preferred_counselor_id' => $data['preferred_counselor_id'] ?? null,
            'status' => 'pending',
            'notes' => $data['notes'] ?? null,
        ]);

        $this->logAudit(
            $user->id,
            'counseling_request.create',
            'counseling_requests',
            $counselingRequest->id,
            null,
            [
                'student_id' => $counselingRequest->student_id,
                'requester_role' => $counselingRequest->requester_role,
                'status' => $counselingRequest->status,
            ],
            $request
        );

        return $counselingRequest->fresh(['student.classes', 'requester', 'preferredCounselor', 'counselor']);
    }

    /**
     * BE-BK-03: Guru BK menjadwalkan permintaan konseling.
     */
    public function scheduleRequest(string $id, array $data, Request $request): CounselingRequest
    {
        $user = $request->user();
        $this->authorizeCounselorOrAdmin($user);

        $counselingRequest = CounselingRequest::with(['student.classes', 'requester', 'preferredCounselor', 'counselor'])->findOrFail($id);
        $this->authorizeRequestAccess($user, $counselingRequest);

        if (in_array($counselingRequest->status, ['completed', 'cancelled'])) {
            throw new HttpException(422, "Permintaan konseling dengan status {$counselingRequest->status} tidak dapat dijadwalkan ulang.");
        }

        $oldValues = $counselingRequest->toArray();

        $counselingRequest->status = 'scheduled';
        $counselingRequest->scheduled_at = Carbon::parse($data['scheduled_at']);
        $counselingRequest->counselor_id = $data['counselor_id'] ?? $user->id;
        if (! empty($data['notes'])) {
            $counselingRequest->notes = $data['notes'];
        }
        $counselingRequest->save();

        $this->logAudit(
            $user->id,
            'counseling_request.schedule',
            'counseling_requests',
            $counselingRequest->id,
            ['status' => $oldValues['status'], 'scheduled_at' => $oldValues['scheduled_at']],
            ['status' => 'scheduled', 'scheduled_at' => $counselingRequest->scheduled_at, 'counselor_id' => $counselingRequest->counselor_id],
            $request
        );

        return $counselingRequest->fresh(['student.classes', 'requester', 'preferredCounselor', 'counselor']);
    }

    /**
     * BE-BK-03: Guru BK menolak permintaan konseling dengan alasan.
     */
    public function rejectRequest(string $id, array $data, Request $request): CounselingRequest
    {
        $user = $request->user();
        $this->authorizeCounselorOrAdmin($user);

        $counselingRequest = CounselingRequest::with(['student.classes', 'requester', 'preferredCounselor', 'counselor'])->findOrFail($id);
        $this->authorizeRequestAccess($user, $counselingRequest);

        if (in_array($counselingRequest->status, ['completed', 'cancelled'])) {
            throw new HttpException(422, "Permintaan konseling dengan status {$counselingRequest->status} tidak dapat ditolak.");
        }

        $oldStatus = $counselingRequest->status;
        $counselingRequest->status = 'rejected';
        $counselingRequest->rejection_reason = $data['rejection_reason'];
        $counselingRequest->save();

        $this->logAudit(
            $user->id,
            'counseling_request.reject',
            'counseling_requests',
            $counselingRequest->id,
            ['status' => $oldStatus],
            ['status' => 'rejected', 'rejection_reason' => $data['rejection_reason']],
            $request
        );

        return $counselingRequest->fresh(['student.classes', 'requester', 'preferredCounselor', 'counselor']);
    }

    /**
     * BE-BK-03: Batalkan permintaan konseling oleh pemohon atau admin.
     */
    public function cancelRequest(string $id, Request $request): CounselingRequest
    {
        $user = $request->user();
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        $counselingRequest = CounselingRequest::with(['student.classes', 'requester', 'preferredCounselor', 'counselor'])->findOrFail($id);
        $this->authorizeRequestAccess($user, $counselingRequest);

        // Hanya pemohon atau BK/Admin yang boleh membatalkan
        $isRequester = $counselingRequest->requested_by === $user->id;
        $isCounselorOrAdmin = $this->isCounselorOrAdmin($user);

        if (! $isRequester && ! $isCounselorOrAdmin) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak berwenang membatalkan permintaan konseling ini.');
        }

        if ($counselingRequest->status === 'completed') {
            throw new HttpException(422, 'Permintaan konseling yang sudah selesai tidak dapat dibatalkan.');
        }

        $oldStatus = $counselingRequest->status;
        $counselingRequest->status = 'cancelled';
        $counselingRequest->save();

        $this->logAudit(
            $user->id,
            'counseling_request.cancel',
            'counseling_requests',
            $counselingRequest->id,
            ['status' => $oldStatus],
            ['status' => 'cancelled'],
            $request
        );

        return $counselingRequest->fresh(['student.classes', 'requester', 'preferredCounselor', 'counselor']);
    }

    /**
     * BE-BK-03: Guru BK menyelesaikan permintaan konseling dan opsional mengintegrasikannya ke Kasus BK.
     */
    public function completeRequest(string $id, array $data, Request $request): CounselingRequest
    {
        $user = $request->user();
        $this->authorizeCounselorOrAdmin($user);

        $counselingRequest = CounselingRequest::with(['student.classes', 'requester', 'preferredCounselor', 'counselor'])->findOrFail($id);
        $this->authorizeRequestAccess($user, $counselingRequest);

        if ($counselingRequest->status !== 'scheduled') {
            throw new HttpException(422, "Hanya permintaan berstatus 'scheduled' yang dapat diselesaikan.");
        }

        $oldStatus = $counselingRequest->status;
        $counselingRequest->status = 'completed';
        if (! empty($data['notes'])) {
            $counselingRequest->notes = $data['notes'];
        }
        $counselingRequest->save();

        // Integrasi alur: Buat kasus konseling resmi dan sesi konseling jika diminta
        if (! empty($data['create_case'])) {
            $category = $data['case_category'] ?? 'Pribadi';
            $summary = $data['case_summary'] ?? ($counselingRequest->topic.': '.($data['notes'] ?? 'Tindak lanjut permintaan konseling'));

            $case = CounselingCase::create([
                'student_id' => $counselingRequest->student_id,
                'counselor_id' => $user->id,
                'category' => $category,
                'status' => 'aktif',
                'summary_enc' => $summary,
            ]);

            if (! empty($data['session_notes'])) {
                CounselingSession::create([
                    'case_id' => $case->id,
                    'session_date' => Carbon::now()->toDateString(),
                    'notes_enc' => $data['session_notes'],
                    'follow_up' => $data['follow_up'] ?? null,
                ]);
            }
        }

        $this->logAudit(
            $user->id,
            'counseling_request.complete',
            'counseling_requests',
            $counselingRequest->id,
            ['status' => $oldStatus],
            ['status' => 'completed'],
            $request
        );

        return $counselingRequest->fresh(['student.classes', 'requester', 'preferredCounselor', 'counselor']);
    }

    /**
     * Otorisasi akses detail permintaan konseling.
     */
    protected function authorizeRequestAccess(User $user, CounselingRequest $counselingRequest): void
    {
        if (! empty($user->school_id) && ! empty($counselingRequest->student?->school_id) && $counselingRequest->student->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Permintaan konseling berada di luar sekolah penugasan Anda.');
        }

        if ($this->isCounselorOrAdminOrPrincipal($user)) {
            return;
        }

        if ($this->isStudentUser($user)) {
            if ($counselingRequest->student_id === $user->student?->id || $counselingRequest->requested_by === $user->id) {
                return;
            }
        }

        if ($this->isGuardianUser($user)) {
            $isOwnWard = $user->guardian?->students()->where('students.id', $counselingRequest->student_id)->exists();
            if ($isOwnWard || $counselingRequest->requested_by === $user->id) {
                return;
            }
        }

        if ($this->isTeacherUser($user)) {
            $isHomeroom = SchoolClass::whereHas('homeroomTeacher', fn ($hq) => $hq->where('user_id', $user->id))
                ->whereHas('students', fn ($sq) => $sq->where('students.id', $counselingRequest->student_id))
                ->exists();

            if ($isHomeroom) {
                return;
            }
        }

        throw new HttpException(403, 'Akses ditolak: Anda tidak berwenang mengakses permintaan konseling ini.');
    }

    protected function isCounselorOrAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
            return true;
        }

        return $user->hasRole('counselor') || $user->hasRole('bk') || $user->hasRole('guru_bk');
    }

    protected function isCounselorOrAdminOrPrincipal(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->isCounselorOrAdmin($user)) {
            return true;
        }

        return $user->hasRole('principal') || $user->hasRole('kepala_sekolah');
    }

    protected function isStudentUser(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->student()->exists() || $user->hasRole('student') || $user->hasRole('siswa');
    }

    protected function isGuardianUser(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->guardian()->exists() || $user->hasRole('guardian') || $user->hasRole('wali') || $user->hasRole('orang_tua');
    }

    protected function isTeacherUser(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->teacher()->exists() || $user->hasRole('teacher') || $user->hasRole('guru');
    }

    protected function authorizeCounselorOrAdmin(?User $user): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        if (! $this->isCounselorOrAdmin($user)) {
            throw new HttpException(403, 'Akses ditolak: Hanya Guru BK atau Administrator yang berwenang memproses permintaan ini.');
        }
    }

    protected function logAudit(
        ?string $actorId,
        string $action,
        string $entity,
        ?string $entityId,
        ?array $oldValue,
        ?array $newValue,
        Request $request
    ): void {
        try {
            AuditLog::create([
                'actor_id' => $actorId,
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Audit log failure must not fail the main transaction
        }
    }
}
