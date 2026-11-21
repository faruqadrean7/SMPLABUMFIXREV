<?php

namespace App\Services\Ntf;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class NotificationService
{
    // =========================================================================
    // 1. REGISTRASI & PENGELOLAAN PERANGKAT (DEVICES & PUSH TOKENS)
    // =========================================================================

    public function registerDevice(User $user, array $data): Device
    {
        $token = $data['fcm_token'] ?? $data['token'] ?? null;

        return Device::updateOrCreate(
            [
                'user_id' => $user->id,
                'fcm_token' => $token,
            ],
            [
                'platform' => $data['platform'],
                'last_seen_at' => Carbon::now(),
            ]
        );
    }

    public function removeDevice(User $user, string $token): bool
    {
        return (bool) Device::where('user_id', $user->id)
            ->where('fcm_token', $token)
            ->delete();
    }

    // =========================================================================
    // 2. INBOX NOTIFIKASI PENGGUNA (IN-APP & DEEP LINK)
    // =========================================================================

    public function getUserNotifications(User $user, array $filters): array
    {
        $query = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        if (isset($filters['is_read'])) {
            $isRead = filter_var($filters['is_read'], FILTER_VALIDATE_BOOLEAN);
            if ($isRead) {
                $query->whereNotNull('read_at');
            } else {
                $query->whereNull('read_at');
            }
        }

        if (! empty($filters['notif_type'])) {
            $query->where('notif_type', $filters['notif_type']);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
        $paginator = $query->paginate($perPage);

        $unreadCount = Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return [
            'paginator' => $paginator,
            'unread_count' => $unreadCount,
        ];
    }

    public function getNotification(User $user, string $id): Notification
    {
        return Notification::where('user_id', $user->id)->findOrFail($id);
    }

    public function markAsRead(User $user, string $id): Notification
    {
        $notification = Notification::where('user_id', $user->id)->findOrFail($id);

        if (! $notification->read_at) {
            $notification->update(['read_at' => Carbon::now()]);
        }

        return $notification->fresh();
    }

    public function markAllAsRead(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => Carbon::now()]);
    }

    public function deleteNotification(User $user, string $id): bool
    {
        $notification = Notification::where('user_id', $user->id)->findOrFail($id);

        return (bool) $notification->delete();
    }

    // =========================================================================
    // 3. BROADCAST NOTIFIKASI (ROLE / KELAS / USER)
    // =========================================================================

    public function broadcast(array $data, Request $request): array
    {
        $user = $request->user();
        $this->authorizeBroadcast($user);

        $targetType = $data['target_type'];
        $userIds = collect();

        $schoolId = $user->school_id;
        $isSuperAdmin = $user->hasRole('super_admin');

        if ($targetType === 'all') {
            $query = User::where('status', 'active');
            if ($schoolId && ! $isSuperAdmin) {
                $query->where('school_id', $schoolId);
            }
            $userIds = $query->pluck('id');
        } elseif ($targetType === 'role') {
            $roleCode = $data['target_role'];
            $query = User::whereHas('roles', fn ($q) => $q->where('code', $roleCode))
                ->where('status', 'active');
            if ($schoolId && ! $isSuperAdmin) {
                $query->where('school_id', $schoolId);
            }
            $userIds = $query->pluck('id');
        } elseif ($targetType === 'class') {
            $classId = $data['target_class_id'];
            $query = Student::whereHas('classes', fn ($q) => $q->where('classes.id', $classId))
                ->whereNotNull('user_id');
            if ($schoolId && ! $isSuperAdmin) {
                $query->where('school_id', $schoolId);
            }
            $userIds = $query->pluck('user_id');
        } elseif ($targetType === 'users') {
            $query = User::whereIn('id', $data['user_ids']);
            if ($schoolId && ! $isSuperAdmin) {
                $query->where('school_id', $schoolId);
            }
            $userIds = $query->pluck('id');
        }

        $userIds = $userIds->filter()->unique()->values();

        if ($userIds->isEmpty()) {
            throw new HttpException(422, 'Tidak ada pengguna penerima yang ditemukan untuk kriteria target tersebut.');
        }

        $now = Carbon::now();
        $notifType = $data['notif_type'] ?? 'system_broadcast';
        $title = $data['title'];
        $body = $data['body'];
        $customData = $data['data'] ?? null;
        $jsonData = $customData ? json_encode($customData) : null;

        // GAP-11: Filter out recipients who disabled this notification type or push channel
        $disabledUserIds = NotificationPreference::whereIn('user_id', $userIds)
            ->where('notif_type', $notifType)
            ->where(function ($q) {
                $q->where('is_enabled', false)
                    ->orWhere('channel_push', false);
            })
            ->pluck('user_id');

        $activeRecipientIds = $userIds->reject(fn ($id) => $disabledUserIds->contains($id))->values();

        $insertRows = [];
        foreach ($activeRecipientIds as $recipientId) {
            $insertRows[] = [
                'id' => (string) Str::uuid(),
                'user_id' => $recipientId,
                'notif_type' => $notifType,
                'title' => $title,
                'body' => $body,
                'data' => $jsonData,
                'read_at' => null,
                'created_at' => $now,
            ];
        }

        // Insert notifications in chunks
        foreach (array_chunk($insertRows, 500) as $chunk) {
            Notification::insert($chunk);
        }

        $deviceCount = Device::whereIn('user_id', $activeRecipientIds)
            ->whereNotNull('fcm_token')
            ->count();

        $this->logAudit($user?->id, 'notification.broadcast', 'notifications', null, null, [
            'target_type' => $targetType,
            'title' => $title,
            'recipient_count' => count($activeRecipientIds),
            'device_count' => $deviceCount,
        ], $request);

        return [
            'sent_count' => count($activeRecipientIds),
            'device_count' => $deviceCount,
            'target_type' => $targetType,
            'title' => $title,
        ];
    }

    // =========================================================================
    // 4. OTORISASI & AUDIT
    // =========================================================================

    public function authorizeBroadcast(?User $user): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        if (
            ! $user->hasPermission('notification.broadcast') &&
            ! $user->hasRole(['super_admin', 'admin', 'admin_tu', 'principal', 'humas', 'kesiswaan'])
        ) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mengirim broadcast notifikasi.');
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
            // Jangan gagalkan transaksi
        }
    }
}
