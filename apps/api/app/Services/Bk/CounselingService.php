<?php

namespace App\Services\Bk;

use App\Models\AuditLog;
use App\Models\CounselingCase;
use App\Models\CounselingReferral;
use App\Models\CounselingSession;
use App\Models\Student;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CounselingService
{
    /**
     * BE-BK-01: Ambil daftar kasus bimbingan konseling.
     * Hanya guru BK (counselor), Kepala Sekolah, dan Administrator.
     */
    public function listCases(array $filters, Request $request): LengthAwarePaginator
    {
        $user = $request->user();
        $this->authorizeCounselorOrAdminOrPrincipal($user);

        $query = CounselingCase::with(['student.classes', 'counselor.teacher'])
            ->withCount('sessions')
            ->orderBy('created_at', 'desc');

        if (! empty($user->school_id)) {
            $query->whereHas('student', fn ($sq) => $sq->where('school_id', $user->school_id));
        }

        if (! empty($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $kw = '%'.strtolower($filters['search']).'%';
            $query->whereHas('student', function ($sq) use ($kw) {
                $sq->whereRaw('LOWER(full_name) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(nis) LIKE ?', [$kw]);
            });
        }

        $paginator = $query->paginate((int) ($filters['per_page'] ?? 15));

        // Masking ringkasan kasus pada tampilan daftar demi prinsip minimisasi data & privasi
        $isPrincipalOnly = $this->isPrincipalOnly($user);
        $paginator->getCollection()->transform(function ($case) use ($isPrincipalOnly) {
            if ($isPrincipalOnly) {
                $case->summary_enc = '[Ringkasan Sensitif Disamarkan untuk Kepala Sekolah]';
            } else {
                $case->summary_enc = '[Tersimpan Terenkripsi - Buka Detail Kasus]';
            }

            return $case;
        });

        // Audit log akses daftar kasus BK
        $this->logAudit(
            $user->id,
            'bk_case.list',
            'counseling_cases',
            null,
            null,
            [
                'filters' => array_intersect_key($filters, array_flip(['student_id', 'category', 'status', 'search', 'page', 'per_page'])),
                'total_results' => $paginator->total(),
            ],
            $request
        );

        return $paginator;
    }

    /**
     * BE-BK-01: Buat kasus konseling baru (hanya BK/counselor atau admin).
     */
    public function createCase(array $data, Request $request): CounselingCase
    {
        $user = $request->user();
        $this->authorizeCounselorOrAdmin($user);

        if (! empty($user->school_id)) {
            $student = Student::findOrFail($data['student_id']);
            if (! empty($student->school_id) && $student->school_id !== $user->school_id) {
                throw new HttpException(403, 'Akses ditolak: Siswa tidak terdaftar pada sekolah yang sama.');
            }
        }

        $case = CounselingCase::create([
            'student_id' => $data['student_id'],
            'counselor_id' => $user->id,
            'category' => $data['category'],
            'status' => $data['status'] ?? 'aktif',
            'summary_enc' => $data['summary'], // Terenkripsi at-rest melalui cast encrypted
        ]);

        $this->logAudit(
            $user->id,
            'bk_case.create',
            'counseling_cases',
            $case->id,
            null,
            ['student_id' => $case->student_id, 'category' => $case->category, 'status' => $case->status],
            $request
        );

        return $case->load(['student.classes', 'counselor.teacher']);
    }

    /**
     * BE-BK-01: Lihat detail kasus konseling beserta log audit baca.
     */
    public function getCase(string $id, Request $request): CounselingCase
    {
        $user = $request->user();
        $this->authorizeCounselorOrAdminOrPrincipal($user);

        $case = CounselingCase::with(['student.classes', 'counselor.teacher', 'sessions'])->findOrFail($id);

        if (! empty($user->school_id) && ! empty($case->student?->school_id) && $case->student->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Kasus konseling ini berada di luar sekolah penugasan Anda.');
        }

        // Security Audit Log: Catat setiap akses baca data privat BK
        $this->logAudit(
            $user->id,
            'bk_case.read',
            'counseling_cases',
            $case->id,
            null,
            ['action' => 'read_sensitive_case', 'student_id' => $case->student_id],
            $request
        );

        // Masking untuk kepala sekolah
        if ($this->isPrincipalOnly($user)) {
            $case->summary_enc = '[Ringkasan Sensitif Disamarkan untuk Kepala Sekolah]';
            foreach ($case->sessions as $s) {
                $s->notes_enc = '[Catatan Sesi Konseling Privat Disamarkan]';
            }
        }

        return $case;
    }

    /**
     * BE-BK-01: Perbarui status / ringkasan kasus konseling.
     */
    public function updateCase(string $id, array $data, Request $request): CounselingCase
    {
        $user = $request->user();
        $this->authorizeCounselorOrAdmin($user);

        $case = CounselingCase::findOrFail($id);

        if (! empty($data['category'])) {
            $case->category = $data['category'];
        }

        if (! empty($data['status'])) {
            $case->status = $data['status'];
        }

        if (! empty($data['summary'])) {
            $case->summary_enc = $data['summary'];
        }

        $case->save();

        $this->logAudit(
            $user->id,
            'bk_case.update',
            'counseling_cases',
            $case->id,
            null,
            ['status' => $case->status, 'category' => $case->category],
            $request
        );

        return $case->fresh(['student.classes', 'counselor.teacher', 'sessions']);
    }

    /**
     * BE-BK-01: Tambah sesi konseling pada kasus.
     */
    public function createSession(string $caseId, array $data, Request $request): CounselingSession
    {
        $user = $request->user();
        $this->authorizeCounselorOrAdmin($user);

        $case = CounselingCase::findOrFail($caseId);

        if ($case->status === 'selesai') {
            throw new HttpException(422, 'Tidak dapat menambahkan sesi pada kasus konseling yang telah selesai atau ditutup. Buka kembali kasus (ubah status ke aktif) terlebih dahulu.');
        }

        $session = CounselingSession::create([
            'case_id' => $case->id,
            'session_date' => $data['session_date'],
            'notes_enc' => $data['notes'], // Terenkripsi at-rest
            'follow_up' => $data['follow_up'] ?? null,
        ]);

        $this->logAudit(
            $user->id,
            'bk_session.create',
            'counseling_sessions',
            $session->id,
            null,
            ['case_id' => $case->id, 'session_date' => $session->session_date],
            $request
        );

        return $session;
    }

    /**
     * BE-BK-01: Lihat detail sesi konseling (beserta audit baca).
     */
    public function getSession(string $id, Request $request): CounselingSession
    {
        $user = $request->user();
        $this->authorizeCounselorOrAdminOrPrincipal($user);

        $session = CounselingSession::with('case.student')->findOrFail($id);

        $this->logAudit(
            $user->id,
            'bk_session.read',
            'counseling_sessions',
            $session->id,
            null,
            ['action' => 'read_session_notes', 'case_id' => $session->case_id],
            $request
        );

        if ($this->isPrincipalOnly($user)) {
            $session->notes_enc = '[Catatan Sesi Konseling Privat Disamarkan]';
        }

        return $session;
    }

    /**
     * BE-BK-01: Daftar rujukan konseling (dapat dilihat oleh guru perujuk atau BK).
     */
    public function listReferrals(array $filters, Request $request): LengthAwarePaginator
    {
        $user = $request->user();
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        // Siswa dan orang tua tidak berwenang mengakses rujukan internal guru-BK
        if ($user->hasRole('student') || $user->hasRole('siswa') || $user->hasRole('guardian') || $user->hasRole('wali_murid') || $user->hasRole('ortu')) {
            throw new HttpException(403, 'Akses ditolak: Siswa dan orang tua tidak memiliki wewenang mengakses rujukan guru BK.');
        }

        $query = CounselingReferral::with(['student.classes', 'referrer.teacher'])
            ->orderBy('created_at', 'desc');

        if (! empty($user->school_id)) {
            $query->whereHas('student', fn ($sq) => $sq->where('school_id', $user->school_id));
        }

        // Jika guru biasa, hanya tampilkan rujukan yang dia buat sendiri
        if (! $this->isCounselorOrAdmin($user) && ! $this->isPrincipal($user)) {
            $query->where('referred_by', $user->id);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $paginator = $query->paginate((int) ($filters['per_page'] ?? 15));

        if ($this->isCounselorOrAdmin($user) || $this->isPrincipal($user)) {
            $this->logAudit(
                $user->id,
                'bk_referral.list',
                'counseling_referrals',
                null,
                null,
                [
                    'total' => $paginator->total(),
                    'filters' => array_intersect_key($filters, array_flip(['status', 'page', 'per_page'])),
                ],
                $request
            );
        }

        return $paginator;
    }

    /**
     * BE-BK-01: Buat rujukan konseling dari guru mata pelajaran / wali kelas ke BK.
     */
    public function createReferral(array $data, Request $request): CounselingReferral
    {
        $user = $request->user();
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        // Pastikan bukan siswa atau orang tua
        if ($user->hasRole('student') || $user->hasRole('siswa') || $user->hasRole('guardian') || $user->hasRole('wali_murid') || $user->hasRole('ortu')) {
            throw new HttpException(403, 'Akses ditolak: Siswa dan orang tua tidak memiliki wewenang membuat rujukan kasus BK.');
        }

        if (! empty($user->school_id)) {
            $student = Student::findOrFail($data['student_id']);
            if (! empty($student->school_id) && $student->school_id !== $user->school_id) {
                throw new HttpException(403, 'Akses ditolak: Siswa tidak terdaftar pada sekolah yang sama.');
            }
        }

        // Pencegahan duplikasi rujukan pending serupa
        $existingPending = CounselingReferral::where('student_id', $data['student_id'])
            ->where('referred_by', $user->id)
            ->where('status', 'pending')
            ->where('reason', $data['reason'])
            ->first();
        if ($existingPending) {
            throw new HttpException(422, 'Rujukan serupa untuk siswa ini masih berstatus pending dan sedang menunggu tindak lanjut BK.');
        }

        $referral = CounselingReferral::create([
            'student_id' => $data['student_id'],
            'referred_by' => $user->id,
            'reason' => $data['reason'],
            'status' => 'pending',
        ]);

        $this->logAudit(
            $user->id,
            'bk_referral.create',
            'counseling_referrals',
            $referral->id,
            null,
            ['student_id' => $referral->student_id, 'referred_by' => $user->id],
            $request
        );

        return $referral->load(['student.classes', 'referrer.teacher']);
    }

    /**
     * BE-BK-01: Perbarui status rujukan konseling (diterima, ditolak, selesai) oleh BK.
     */
    public function updateReferralStatus(string $id, array $data, Request $request): CounselingReferral
    {
        $user = $request->user();
        $this->authorizeCounselorOrAdmin($user);

        $referral = CounselingReferral::findOrFail($id);

        if (in_array($referral->status, ['ditolak', 'selesai']) && $data['status'] === 'pending') {
            throw new HttpException(422, "Status rujukan konseling sudah final ({$referral->status}) dan tidak dapat dikembalikan ke status pending.");
        }

        $oldStatus = $referral->status;
        $referral->status = $data['status'];
        $referral->save();

        $this->logAudit(
            $user->id,
            'bk_referral.update_status',
            'counseling_referrals',
            $referral->id,
            ['status' => $oldStatus],
            ['status' => $referral->status, 'notes' => $data['notes'] ?? null],
            $request
        );

        return $referral->fresh(['student.classes', 'referrer.teacher']);
    }

    protected function isCounselorOrAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
            return true;
        }

        if ($user->hasRole('counselor') || $user->hasRole('bk') || $user->hasRole('guru_bk')) {
            return true;
        }

        return $user->hasPermission('bk.counseling.manage') || $user->hasPermission('counseling.case.write');
    }

    protected function isPrincipal(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole('kepala_sekolah') || $user->hasRole('principal') || $user->hasPermission('report.principal.approve') || $user->hasPermission('counseling.case.read');
    }

    protected function isPrincipalOnly(?User $user): bool
    {
        return $this->isPrincipal($user) && ! $this->isCounselorOrAdmin($user);
    }

    protected function authorizeCounselorOrAdmin(?User $user): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        if (! $this->isCounselorOrAdmin($user)) {
            throw new HttpException(403, 'Akses ditolak: Hanya Guru BK (Counselor) atau Administrator yang berwenang mengelola rekam konseling.');
        }
    }

    protected function authorizeCounselorOrAdminOrPrincipal(?User $user): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        if ($this->isCounselorOrAdmin($user) || $this->isPrincipal($user)) {
            return;
        }

        throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang mengakses data bimbingan konseling privat.');
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
            report($e);
        }
    }
}
