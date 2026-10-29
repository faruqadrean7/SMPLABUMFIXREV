<?php

namespace App\Services\Std;

use App\Models\ActivityDocument;
use App\Models\AuditLog;
use App\Models\Extracurricular;
use App\Models\ExtracurricularJournal;
use App\Models\ExtracurricularMember;
use App\Models\ReportCard;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExtracurricularService
{
    // =========================================================================
    // 1. MASTER EKSTRAKURIKULER
    // =========================================================================

    public function listExtracurriculars(array $filters): LengthAwarePaginator
    {
        $query = Extracurricular::with('coach')->orderBy('name', 'asc');

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (! empty($filters['coach_id'])) {
            $query->where('coach_id', $filters['coach_id']);
        }

        if (! empty($filters['search'])) {
            $kw = '%'.strtolower($filters['search']).'%';
            $query->where(function ($q) use ($kw) {
                $q->whereRaw('LOWER(name) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(category) LIKE ?', [$kw]);
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function getExtracurricular(string $id): Extracurricular
    {
        return Extracurricular::with(['coach', 'members.student', 'journals', 'documents.file'])->findOrFail($id);
    }

    public function createExtracurricular(array $data, Request $request): Extracurricular
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $ekskul = Extracurricular::create([
            'school_id' => $data['school_id'] ?? null,
            'coach_id' => $data['coach_id'] ?? null,
            'name' => $data['name'],
            'category' => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
            'quota' => (int) ($data['quota'] ?? 30),
            'schedule_day' => $data['schedule_day'] ?? null,
            'schedule_time' => $data['schedule_time'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->logAudit($user?->id, 'extracurricular.create', 'extracurriculars', $ekskul->id, null, $ekskul->toArray(), $request);

        return $ekskul->fresh('coach');
    }

    public function updateExtracurricular(string $id, array $data, Request $request): Extracurricular
    {
        $user = $request->user();
        $ekskul = Extracurricular::findOrFail($id);

        $this->authorizeManageEkskul($user, $ekskul);

        $oldValues = $ekskul->toArray();
        $ekskul->update($data);

        $this->logAudit($user?->id, 'extracurricular.update', 'extracurriculars', $ekskul->id, $oldValues, $ekskul->fresh()->toArray(), $request);

        return $ekskul->fresh('coach');
    }

    public function deleteExtracurricular(string $id, Request $request): bool
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $ekskul = Extracurricular::findOrFail($id);

        if ($ekskul->active_members_count > 0) {
            throw new HttpException(422, 'Ekstrakurikuler tidak dapat dihapus karena masih memiliki anggota aktif.');
        }

        $oldValues = $ekskul->toArray();
        $ekskul->delete();

        $this->logAudit($user?->id, 'extracurricular.delete', 'extracurriculars', $id, $oldValues, null, $request);

        return true;
    }

    // =========================================================================
    // 2. PENDAFTARAN ANGGOTA & PENILAIAN RAPOR
    // =========================================================================

    public function listMembers(?string $extracurricularId, array $filters): LengthAwarePaginator
    {
        $query = ExtracurricularMember::with(['student.classes', 'extracurricular.coach'])
            ->orderBy('created_at', 'desc');

        if (! empty($extracurricularId)) {
            $query->where('extracurricular_id', $extracurricularId);
        }

        if (! empty($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (! empty($filters['search'])) {
            $kw = '%'.strtolower($filters['search']).'%';
            $query->whereHas('student', function ($q) use ($kw) {
                $q->whereRaw('LOWER(full_name) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(nis) LIKE ?', [$kw]);
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function registerMember(array $data, Request $request): ExtracurricularMember
    {
        $user = $request->user();

        return DB::transaction(function () use ($data, $user, $request) {
            $ekskul = Extracurricular::where('id', $data['extracurricular_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! $ekskul->is_active) {
                throw new HttpException(422, 'Pendaftaran ditutup: Ekstrakurikuler ini sedang tidak aktif.');
            }

            // Tentukan student_id dan validasi otorisasi
            $studentId = $data['student_id'] ?? null;
            if ($this->isStudent($user)) {
                $student = Student::where('user_id', $user->id)->firstOrFail();
                $studentId = $student->id;
            } else {
                $this->authorizeManageEkskul($user, $ekskul);
                if (! $studentId) {
                    throw new HttpException(422, 'ID siswa wajib disertakan.');
                }
            }

            // Cek kuota aktif secara atomik
            $activeCount = ExtracurricularMember::where('extracurricular_id', $ekskul->id)
                ->where('status', 'aktif')
                ->count();

            if ($activeCount >= $ekskul->quota) {
                throw new HttpException(422, 'Kuota pendaftaran ekstrakurikuler ini telah penuh.');
            }

            // Cek apakah sudah terdaftar
            $existing = ExtracurricularMember::where('extracurricular_id', $ekskul->id)
                ->where('student_id', $studentId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->status === 'aktif') {
                    throw new HttpException(422, 'Siswa sudah terdaftar aktif di ekstrakurikuler ini.');
                }

                // Aktifkan kembali
                $oldValues = $existing->toArray();
                $existing->update([
                    'status' => 'aktif',
                    'role' => $data['role'] ?? $existing->role,
                    'joined_at' => Carbon::now()->toDateString(),
                ]);

                $this->logAudit($user?->id, 'extracurricular_member.rejoin', 'extracurricular_members', $existing->id, $oldValues, $existing->fresh()->toArray(), $request);

                return $existing->fresh(['extracurricular', 'student']);
            }

            $member = ExtracurricularMember::create([
                'extracurricular_id' => $ekskul->id,
                'student_id' => $studentId,
                'role' => $data['role'] ?? 'anggota',
                'status' => 'aktif',
                'joined_at' => Carbon::now()->toDateString(),
            ]);

            $this->logAudit($user?->id, 'extracurricular_member.register', 'extracurricular_members', $member->id, null, $member->toArray(), $request);

            return $member->fresh(['extracurricular', 'student']);
        });
    }

    public function updateMember(string $memberId, array $data, Request $request): ExtracurricularMember
    {
        $user = $request->user();
        $member = ExtracurricularMember::with('extracurricular.coach')->findOrFail($memberId);

        $this->authorizeManageEkskul($user, $member->extracurricular);

        // Jika mengubah nilai rapor, pastikan rapor semester siswa belum dikunci
        if (isset($data['final_grade']) && ! ($user?->hasRole('super_admin') ?? false)) {
            $isLocked = ReportCard::where('student_id', $member->student_id)
                ->where('status', 'locked')
                ->exists();

            if ($isLocked) {
                throw new HttpException(422, 'Rapor semester siswa telah dikunci. Perubahan nilai ekstrakurikuler tidak diizinkan tanpa pembukaan kunci oleh administrator.');
            }
        }

        $oldValues = $member->toArray();
        $member->update($data);

        $this->logAudit($user?->id, 'extracurricular_member.update', 'extracurricular_members', $member->id, $oldValues, $member->fresh()->toArray(), $request);

        return $member->fresh(['extracurricular', 'student']);
    }

    public function removeMember(string $memberId, Request $request): bool
    {
        $user = $request->user();
        $member = ExtracurricularMember::with('student')->findOrFail($memberId);

        // Boleh dilakukan oleh siswa bersangkutan (keluar) atau pembina/staf
        $isSelf = $this->isStudent($user) && $member->student?->user_id === $user->id;
        if (! $isSelf && ! $this->canManageEkskul($user, $member->extracurricular)) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang mengeluarkan anggota ini.');
        }

        $oldValues = $member->toArray();
        $member->update(['status' => 'keluar']);

        $this->logAudit($user?->id, 'extracurricular_member.remove', 'extracurricular_members', $memberId, $oldValues, $member->fresh()->toArray(), $request);

        return true;
    }

    // =========================================================================
    // 3. JURNAL KEGIATAN & PRESENSI
    // =========================================================================

    public function listJournals(string $extracurricularId): LengthAwarePaginator
    {
        return ExtracurricularJournal::where('extracurricular_id', $extracurricularId)
            ->orderBy('session_date', 'desc')
            ->paginate(15);
    }

    public function getJournal(string $id): ExtracurricularJournal
    {
        return ExtracurricularJournal::with('extracurricular')->findOrFail($id);
    }

    public function createJournal(array $data, Request $request): ExtracurricularJournal
    {
        $user = $request->user();
        $ekskul = Extracurricular::findOrFail($data['extracurricular_id']);

        $this->authorizeManageEkskul($user, $ekskul);

        // Validasi bahwa seluruh siswa yang dicatat dalam attendance adalah anggota aktif ekskul ini
        if (! empty($data['attendance'])) {
            $studentIds = collect($data['attendance'])->pluck('student_id')->unique()->all();
            $validMemberCount = ExtracurricularMember::where('extracurricular_id', $ekskul->id)
                ->where('status', 'aktif')
                ->whereIn('student_id', $studentIds)
                ->count();

            if ($validMemberCount !== count($studentIds)) {
                throw new HttpException(422, 'Presensi hanya dapat dicatat untuk anggota aktif pada ekstrakurikuler ini.');
            }
        }

        $journal = ExtracurricularJournal::create([
            'extracurricular_id' => $ekskul->id,
            'session_date' => $data['session_date'],
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'activity' => $data['activity'],
            'attendance' => $data['attendance'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->logAudit($user?->id, 'extracurricular_journal.create', 'extracurricular_journals', $journal->id, null, $journal->toArray(), $request);

        return $journal->fresh('extracurricular');
    }

    public function deleteJournal(string $id, Request $request): bool
    {
        $user = $request->user();
        $journal = ExtracurricularJournal::with('extracurricular')->findOrFail($id);

        $this->authorizeManageEkskul($user, $journal->extracurricular);

        $oldValues = $journal->toArray();
        $journal->delete();

        $this->logAudit($user?->id, 'extracurricular_journal.delete', 'extracurricular_journals', $id, $oldValues, null, $request);

        return true;
    }

    // =========================================================================
    // 4. REPOSITORI DOKUMEN PROPOSAL & LAPORAN KEGIATAN
    // =========================================================================

    public function listDocuments(array $filters): LengthAwarePaginator
    {
        $query = ActivityDocument::with(['extracurricular', 'file', 'uploader'])
            ->orderBy('created_at', 'desc');

        if (! empty($filters['extracurricular_id'])) {
            $query->where('extracurricular_id', $filters['extracurricular_id']);
        }

        if (! empty($filters['doc_type'])) {
            $query->where('doc_type', $filters['doc_type']);
        }

        if (! empty($filters['search'])) {
            $kw = '%'.strtolower($filters['search']).'%';
            $query->where(function ($q) use ($kw) {
                $q->whereRaw('LOWER(title) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$kw]);
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function getDocument(string $id): ActivityDocument
    {
        return ActivityDocument::with(['extracurricular', 'file', 'uploader'])->findOrFail($id);
    }

    public function createDocument(array $data, Request $request): ActivityDocument
    {
        $user = $request->user();
        $ekskul = Extracurricular::findOrFail($data['extracurricular_id']);
        $this->authorizeManageEkskul($user, $ekskul);

        $doc = ActivityDocument::create([
            'extracurricular_id' => $data['extracurricular_id'],
            'file_id' => $data['file_id'],
            'doc_type' => $data['doc_type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'uploaded_by' => $user?->id,
        ]);

        $this->logAudit($user?->id, 'activity_document.create', 'activity_documents', $doc->id, null, $doc->toArray(), $request);

        return $doc->fresh(['extracurricular', 'file', 'uploader']);
    }

    public function deleteDocument(string $id, Request $request): bool
    {
        $user = $request->user();
        $doc = ActivityDocument::findOrFail($id);

        $canDelete = $this->isStaffOrAdmin($user) || $doc->uploaded_by === $user?->id;
        if (! $canDelete) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang menghapus dokumen ini.');
        }

        $oldValues = $doc->toArray();
        $doc->delete();

        $this->logAudit($user?->id, 'activity_document.delete', 'activity_documents', $id, $oldValues, null, $request);

        return true;
    }

    // =========================================================================
    // OTORISASI & HELPERS
    // =========================================================================

    public function isStudent(?User $user): bool
    {
        return $user?->hasRole('student') ?? false;
    }

    public function isStaffOrAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole('admin') ||
            $user->hasRole('super_admin') ||
            $user->hasRole('kesiswaan') ||
            $user->hasRole('student_affairs');
    }

    public function canManageEkskul(?User $user, Extracurricular $ekskul): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->isStaffOrAdmin($user)) {
            return true;
        }

        return $ekskul->coach?->user_id === $user->id;
    }

    protected function authorizeStaffOrAdmin(?User $user): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        if (! $this->isStaffOrAdmin($user)) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang mengelola data ekstrakurikuler.');
        }
    }

    protected function authorizeManageEkskul(?User $user, Extracurricular $ekskul): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        if (! $this->canManageEkskul($user, $ekskul)) {
            throw new HttpException(403, 'Akses ditolak: Hanya pembina ekstrakurikuler ini atau staf kesiswaan yang berwenang.');
        }
    }

    protected function logAudit(?string $actorId, string $action, string $entity, ?string $entityId, ?array $oldValue, ?array $newValue, Request $request): void
    {
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
            // Audit log failure must not fail the transaction
        }
    }
}
