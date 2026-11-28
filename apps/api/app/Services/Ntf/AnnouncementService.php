<?php

namespace App\Services\Ntf;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\AuditLog;
use App\Models\ClassStudent;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Support\PaginationHelper;
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
        $isSchoolAdmin = $this->isSchoolAnnouncementAdmin($user);
        $isTeacher = $this->isTeacherOnly($user);

        $query = Announcement::with(['author:id,username', 'attachment']);

        // P0-20: Isolasi multi-tenant berdasarkan school_id pengguna
        if (! empty($user?->school_id)) {
            $query->where(function ($q) use ($user) {
                $q->where('school_id', $user->school_id)->orWhereNull('school_id');
            });
        }

        if ($user) {
            $query->withExists(['reads as is_read' => fn ($q) => $q->where('user_id', $user->id)]);
        }

        // Filter visibilitas waktu terbit: non-admin hanya melihat terbitan (kecuali guru melihat draf miliknya sendiri)
        $publishedOnly = filter_var($filters['published_only'] ?? (! $isSchoolAdmin), FILTER_VALIDATE_BOOLEAN);
        if (! $isSchoolAdmin) {
            if (! $publishedOnly && $isTeacher && $user) {
                $query->where(function ($q) use ($user) {
                    $q->published()->orWhere('author_id', $user->id);
                });
            } else {
                $query->published();
            }
        } elseif ($publishedOnly) {
            $query->published();
        }

        // Segmentasi audiens untuk non-admin (siswa, wali murid, maupun guru)
        if (! $isSchoolAdmin && $user) {
            $roleCodes = $user->roles()->pluck('code')->toArray();
            $context = $this->resolveUserStudentContexts($user);
            $classIds = $context['class_ids'];
            $gradeLevelIds = $context['grade_level_ids'];
            $teacherClassIds = $isTeacher ? $this->resolveTeacherClassIds($user) : [];

            $query->where(function ($sub) use ($user, $roleCodes, $classIds, $gradeLevelIds, $teacherClassIds) {
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

                // 4. Berdasarkan kelas siswa/perwalian/binaan guru
                $visibleClassIds = array_values(array_unique(array_merge($classIds, $teacherClassIds)));
                if (! empty($visibleClassIds)) {
                    foreach ($visibleClassIds as $cId) {
                        $sub->orWhere(function ($q) use ($cId) {
                            $q->where('target_type', 'kelas')
                                ->whereJsonContains('target_ids', $cId);
                        });
                    }
                }

                // 5. Berdasarkan tingkat/jenjang siswa/perwalian
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
            $kw = '%'.trim((string) $filters['search']).'%';
            $query->where(function ($q) use ($kw) {
                $q->whereIlike('title', $kw)
                    ->orWhereIlike('body', $kw);
            });
        }

        $query->orderBy('is_pinned', 'desc')
            ->orderBy('publish_at', 'desc')
            ->orderBy('created_at', 'desc');

        return $query->paginate(PaginationHelper::clamp($filters['per_page'] ?? null, 15));
    }

    /**
     * BE-NTF-03: Ambil detail pengumuman dan cek hak visibilitas.
     */
    public function getAnnouncement(string $id, Request $request): Announcement
    {
        $user = $request->user();
        $isSchoolAdmin = $this->isSchoolAnnouncementAdmin($user);

        $announcement = Announcement::with(['author:id,username', 'attachment'])
            ->findOrFail($id);

        $this->assertSameSchool($user, $announcement->school_id);

        if (! $isSchoolAdmin && $announcement->author_id !== $user?->id) {
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
     * BE-NTF-03: Buat pengumuman baru (staf/admin/guru sesuai batas cakupan).
     */
    public function createAnnouncement(array $data, Request $request): Announcement
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);
        $this->assertTeacherTargetScope($user, $data['target_type'], $data['target_ids'] ?? null);

        // P0-20: Jangan pernah mempercayai school_id dari payload klien; gunakan school_id pengguna terautentikasi
        $announcement = Announcement::create([
            'school_id' => $user->school_id,
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
        $this->assertSameSchool($user, $announcement->school_id);
        $this->authorizeAuthorOrAdmin($user, $announcement);
        $this->assertTeacherTargetScope(
            $user,
            $data['target_type'] ?? $announcement->target_type,
            array_key_exists('target_ids', $data) ? $data['target_ids'] : $announcement->target_ids
        );

        unset($data['school_id']);
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
        $this->assertSameSchool($user, $announcement->school_id);
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
        $this->assertSameSchool($user, $announcement->school_id);
        $isSchoolAdmin = $this->isSchoolAnnouncementAdmin($user);

        // Pastikan pengguna berhak melihat pengumuman ini
        if (! $isSchoolAdmin && $announcement->author_id !== $user->id) {
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
        $this->assertSameSchool($user, $announcement->school_id);
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

    protected function assertSameSchool(?User $user, ?string $resourceSchoolId): void
    {
        if (! empty($user?->school_id) && ! empty($resourceSchoolId) && $resourceSchoolId !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Pengumuman ini berada di luar sekolah penugasan Anda.');
        }
    }

    public function isSchoolAnnouncementAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin', 'admin_tu', 'principal', 'kepala_sekolah', 'humas', 'kesiswaan']) ||
            $user->hasPermission('notification.broadcast');
    }

    public function isTeacherOnly(?User $user): bool
    {
        return (bool) ($user && ! $this->isSchoolAnnouncementAdmin($user) && $user->hasRole('teacher'));
    }

    public function isStaffOrAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->isSchoolAnnouncementAdmin($user) || $user->hasRole('teacher');
    }

    protected function resolveTeacherClassIds(User $user): array
    {
        $teacher = Teacher::where('user_id', $user->id)->first();
        if (! $teacher) {
            return [];
        }

        $homeroomIds = SchoolClass::where('homeroom_teacher_id', $teacher->id)
            ->pluck('id')
            ->toArray();

        $teachingIds = TeachingAssignment::where('teacher_id', $teacher->id)
            ->pluck('class_id')
            ->toArray();

        return array_values(array_unique(array_merge($homeroomIds, $teachingIds)));
    }

    protected function assertTeacherTargetScope(?User $user, string $targetType, mixed $targetIds): void
    {
        if (! $user || ! $this->isTeacherOnly($user)) {
            return;
        }

        if ($targetType !== 'kelas') {
            throw new HttpException(403, 'Akses ditolak: Guru hanya dapat mengirim pengumuman untuk kelas yang diampu atau perwaliannya.');
        }

        $requestedClassIds = array_values(array_filter(array_map('strval', (array) $targetIds)));
        $allowedClassIds = $this->resolveTeacherClassIds($user);

        if (empty($requestedClassIds) || ! empty(array_diff($requestedClassIds, $allowedClassIds))) {
            throw new HttpException(403, 'Akses ditolak: Anda hanya dapat membuat pengumuman untuk kelas ajar atau kelas perwalian Anda sendiri.');
        }
    }

    protected function resolveUserStudentContexts(User $user): array
    {
        $studentIds = Student::where('user_id', $user->id)->pluck('id')->toArray();

        $guardian = Guardian::where('user_id', $user->id)->first();
        if ($guardian) {
            $wardIds = $guardian->students()->pluck('students.id')->toArray();
            $studentIds = array_values(array_unique(array_merge($studentIds, $wardIds)));
        }

        if (empty($studentIds)) {
            return ['class_ids' => [], 'grade_level_ids' => []];
        }

        $classIds = ClassStudent::whereIn('student_id', $studentIds)
            ->whereNull('left_at')
            ->pluck('class_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $gradeLevelIds = ! empty($classIds)
            ? SchoolClass::whereIn('id', $classIds)->pluck('grade_level_id')->filter()->unique()->values()->toArray()
            : [];

        return [
            'class_ids' => $classIds,
            'grade_level_ids' => $gradeLevelIds,
        ];
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

        $isAdmin = $user->hasRole(['super_admin', 'admin', 'admin_tu', 'principal', 'kepala_sekolah']);
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

        $context = $this->resolveUserStudentContexts($user);
        $visibleClassIds = $context['class_ids'];
        if ($user->hasRole('teacher')) {
            $visibleClassIds = array_values(array_unique(array_merge($visibleClassIds, $this->resolveTeacherClassIds($user))));
        }

        if ($announcement->target_type === 'kelas') {
            return ! empty(array_intersect($targetIds, $visibleClassIds));
        }

        if ($announcement->target_type === 'tingkat') {
            return ! empty(array_intersect($targetIds, $context['grade_level_ids']));
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
