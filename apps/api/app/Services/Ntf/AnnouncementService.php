<?php

namespace App\Services\Ntf;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\AuditLog;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AnnouncementService
{
    /**
     * BE-NTF-03: Daftar pengumuman terarah berdasarkan segmentasi audiens dan status terbit.
     */
    public function listAnnouncements(array $filters, Request $request): LengthAwarePaginator
    {
        $user = $request->user();
        $isStaff = $this->isStaffOrAdmin($user);

        $query = Announcement::with(['author:id,username', 'attachment']);

        if ($user) {
            $query->withExists(['reads as is_read' => fn ($q) => $q->where('user_id', $user->id)]);
        }

        // Filter visibilitas waktu terbit
        $publishedOnly = filter_var($filters['published_only'] ?? (! $isStaff), FILTER_VALIDATE_BOOLEAN);
        if ($publishedOnly) {
            $query->published();
        }

        // Segmentasi audiens untuk non-staff
        if (! $isStaff && $user) {
            $roleCodes = $user->roles()->pluck('code')->toArray();
            $classIds = Student::where('user_id', $user->id)->first()?->classes()->pluck('classes.id')->toArray() ?? [];
            $gradeLevelIds = ! empty($classIds)
                ? SchoolClass::whereIn('id', $classIds)->pluck('grade_level_id')->filter()->unique()->toArray()
                : [];

            $query->where(function ($sub) use ($user, $roleCodes, $classIds, $gradeLevelIds) {
                // 1. Author sendiri
                $sub->where('author_id', $user->id)
                    // 2. Ditujukan ke semua audiens
                    ->orWhere('target_type', 'semua');

                // 3. Berdasarkan role pengguna
                if (! empty($roleCodes)) {
                    foreach ($roleCodes as $code) {
                        $sub->orWhere(function ($q) use ($code) {
                            $q->where('target_type', 'role')
                                ->whereJsonContains('target_ids', $code);
                        });
                    }
                }

                // 4. Berdasarkan kelas siswa
                if (! empty($classIds)) {
                    foreach ($classIds as $cId) {
                        $sub->orWhere(function ($q) use ($cId) {
                            $q->where('target_type', 'kelas')
                                ->whereJsonContains('target_ids', $cId);
                        });
                    }
                }

                // 5. Berdasarkan tingkat/jenjang siswa
                if (! empty($gradeLevelIds)) {
                    foreach ($gradeLevelIds as $gId) {
                        $sub->orWhere(function ($q) use ($gId) {
                            $q->where('target_type', 'tingkat')
                                ->whereJsonContains('target_ids', $gId);
                        });
                    }
                }
            });
        }

        // Filter tambahan
        if (! empty($filters['target_type'])) {
            $query->where('target_type', $filters['target_type']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (isset($filters['is_pinned'])) {
            $query->where('is_pinned', filter_var($filters['is_pinned'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['is_read']) && $user) {
            $isRead = filter_var($filters['is_read'], FILTER_VALIDATE_BOOLEAN);
            if ($isRead) {
                $query->whereHas('reads', fn ($q) => $q->where('user_id', $user->id));
            } else {
                $query->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id));
            }
        }

        if (! empty($filters['search'])) {
            $kw = '%'.strtolower($filters['search']).'%';
            $query->where(function ($q) use ($kw) {
                $q->whereRaw('LOWER(title) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(body) LIKE ?', [$kw]);
            });
        }

        $query->orderBy('is_pinned', 'desc')
            ->orderBy('publish_at', 'desc')
            ->orderBy('created_at', 'desc');

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * BE-NTF-03: Ambil detail pengumuman dan cek hak visibilitas.
     */
    public function getAnnouncement(string $id, Request $request): Announcement
    {
        $user = $request->user();
        $isStaff = $this->isStaffOrAdmin($user);

        $announcement = Announcement::with(['author:id,username', 'attachment'])
            ->findOrFail($id);

        if (! $isStaff && $announcement->author_id !== $user?->id) {
            // Harus sudah terbit & belum kedaluwarsa
            $now = Carbon::now();
            if ($announcement->publish_at > $now || ($announcement->expires_at && $announcement->expires_at < $now)) {
                throw new HttpException(403, 'Akses ditolak: Pengumuman ini belum diterbitkan atau sudah kedaluwarsa.');
            }

            // Harus sesuai target segmentasi
            if (! $this->isUserTargeted($announcement, $user)) {
                throw new HttpException(403, 'Akses ditolak: Pengumuman ini tidak ditujukan untuk Anda.');
            }
        }

        $announcement->setAttribute('is_read', $announcement->isReadBy($user?->id));
        $announcement->setAttribute('readers_count', $announcement->reads()->count());

        return $announcement;
    }

    /**
     * BE-NTF-03: Buat pengumuman baru (staf/admin/guru).
     */
    public function createAnnouncement(array $data, Request $request): Announcement
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $announcement = Announcement::create([
            'school_id' => $data['school_id'] ?? null,
            'author_id' => $user->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'target_type' => $data['target_type'],
            'target_ids' => $data['target_ids'] ?? null,
            'attachment_file_id' => $data['attachment_file_id'] ?? null,
            'priority' => $data['priority'] ?? 'normal',
            'is_pinned' => $data['is_pinned'] ?? false,
            'publish_at' => $data['publish_at'] ?? Carbon::now(),
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        $this->logAudit($user?->id, 'announcement.create', 'announcements', $announcement->id, null, $announcement->toArray(), $request);

        return $announcement->fresh(['author', 'attachment']);
    }

    /**
     * BE-NTF-03: Perbarui pengumuman (pembuat atau staf/admin).
     */
    public function updateAnnouncement(string $id, array $data, Request $request): Announcement
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $announcement = Announcement::findOrFail($id);
        $this->authorizeAuthorOrAdmin($user, $announcement);

        $oldValues = $announcement->toArray();
        $announcement->update($data);

        $this->logAudit($user?->id, 'announcement.update', 'announcements', $announcement->id, $oldValues, $announcement->fresh()->toArray(), $request);

        return $announcement->fresh(['author', 'attachment']);
    }

    /**
     * BE-NTF-03: Hapus pengumuman.
     */
    public function deleteAnnouncement(string $id, Request $request): bool
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $announcement = Announcement::findOrFail($id);
        $this->authorizeAuthorOrAdmin($user, $announcement);

        $oldValues = $announcement->toArray();
        $announcement->delete();

        $this->logAudit($user?->id, 'announcement.delete', 'announcements', $id, $oldValues, null, $request);

        return true;
    }

    /**
     * BE-NTF-03: Tandai pengumuman telah dibaca oleh pengguna.
     */
    public function markAsRead(string $id, Request $request): array
    {
        $user = $request->user();
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        $announcement = Announcement::findOrFail($id);
        $isStaff = $this->isStaffOrAdmin($user);

        // Pastikan pengguna berhak melihat pengumuman ini
        if (! $isStaff && $announcement->author_id !== $user->id) {
            $now = Carbon::now();
            if ($announcement->publish_at > $now || ($announcement->expires_at && $announcement->expires_at < $now)) {
                throw new HttpException(403, 'Akses ditolak: Pengumuman ini belum diterbitkan atau sudah kedaluwarsa.');
            }
            if (! $this->isUserTargeted($announcement, $user)) {
                throw new HttpException(403, 'Akses ditolak: Pengumuman ini tidak ditujukan untuk Anda.');
            }
        }

        $now = Carbon::now();
        AnnouncementRead::updateOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => $user->id],
            ['read_at' => $now]
        );

        return [
            'announcement_id' => $announcement->id,
            'user_id' => $user->id,
            'is_read' => true,
            'read_at' => $now->toIso8601String(),
            'message' => 'Pengumuman berhasil ditandai telah dibaca.',
        ];
    }

    /**
     * BE-NTF-03: Daftar pengguna yang telah membaca pengumuman (pelacakan dibaca).
     */
    public function listReaders(string $id, Request $request): array
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $announcement = Announcement::findOrFail($id);
        $this->authorizeAuthorOrAdmin($user, $announcement);

        $reads = AnnouncementRead::where('announcement_id', $id)
            ->with('user:id,username,email')
            ->orderBy('read_at', 'desc')
            ->get();

        return [
            'announcement_id' => $announcement->id,
            'title' => $announcement->title,
            'total_readers' => $reads->count(),
            'readers' => $reads->map(fn ($r) => [
                'user_id' => $r->user_id,
                'username' => $r->user?->username,
                'email' => $r->user?->email,
                'read_at' => $r->read_at?->toIso8601String(),
            ]),
        ];
    }

    // =========================================================================
    // HELPER & OTORISASI
    // =========================================================================

    public function isStaffOrAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin', 'teacher', 'admin_tu', 'principal', 'humas', 'kesiswaan']) ||
            $user->hasPermission('notification.broadcast');
    }

    protected function authorizeStaffOrAdmin(?User $user): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        if (! $this->isStaffOrAdmin($user)) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang mengelola pengumuman.');
        }
    }

    protected function authorizeAuthorOrAdmin(?User $user, Announcement $announcement): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        $isAdmin = $user->hasRole(['super_admin', 'admin', 'admin_tu', 'principal']);
        if (! $isAdmin && $announcement->author_id !== $user->id) {
            throw new HttpException(403, 'Akses ditolak: Anda hanya dapat mengubah atau melihat analitik pengumuman yang Anda buat sendiri.');
        }
    }

    protected function isUserTargeted(Announcement $announcement, ?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($announcement->target_type === 'semua') {
            return true;
        }

        $targetIds = (array) ($announcement->target_ids ?? []);

        if ($announcement->target_type === 'role') {
            $userRoles = $user->roles()->pluck('code')->toArray();

            return ! empty(array_intersect($targetIds, $userRoles));
        }

        if ($announcement->target_type === 'kelas') {
            $student = Student::where('user_id', $user->id)->first();
            if (! $student) {
                return false;
            }
            $studentClassIds = $student->classes()->pluck('classes.id')->toArray();

            return ! empty(array_intersect($targetIds, $studentClassIds));
        }

        if ($announcement->target_type === 'tingkat') {
            $student = Student::where('user_id', $user->id)->first();
            if (! $student) {
                return false;
            }
            $studentClassIds = $student->classes()->pluck('classes.id')->toArray();
            $studentGradeLevelIds = SchoolClass::whereIn('id', $studentClassIds)->pluck('grade_level_id')->filter()->unique()->toArray();

            return ! empty(array_intersect($targetIds, $studentGradeLevelIds));
        }

        return false;
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
            // Jangan gagalkan transaksi bila audit gagal
        }
    }
}
