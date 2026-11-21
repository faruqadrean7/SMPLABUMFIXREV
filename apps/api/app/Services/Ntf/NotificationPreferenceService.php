<?php

namespace App\Services\Ntf;

use App\Models\AuditLog;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class NotificationPreferenceService
{
    /**
     * BE-NTF-04: Ambil seluruh konfigurasi preferensi notifikasi milik user.
     * Menggabungkan data tersimpan di DB dengan nilai default katalog jika belum pernah diatur.
     */
    public function getUserPreferences(User $user): array
    {
        $user->loadMissing('roles');
        $userRoleCodes = $user->roles->pluck('code')->all();
        $isSuperAdmin = in_array('super_admin', $userRoleCodes, true);

        $existingRecords = NotificationPreference::where('user_id', $user->id)
            ->get()
            ->keyBy('notif_type');

        $result = [];
        foreach (NotificationPreference::NOTIFICATION_TYPES as $type => $meta) {
            $allowedRoles = $meta['allowed_roles'] ?? [];
            if (! $isSuperAdmin && ! empty($allowedRoles) && empty(array_intersect($userRoleCodes, $allowedRoles))) {
                // Sembunyikan topik yang tidak berhak diakses oleh peran pengguna (GAP-11)
                continue;
            }

            $pref = $existingRecords->get($type);
            $isEnabled = $pref ? (bool) $pref->is_enabled : true;

            $result[] = [
                'notif_type' => $type,
                'topic' => $type,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'supported_channels' => $meta['supported_channels'],
                'is_enabled' => $isEnabled,
                'enabled' => $isEnabled,
                'channel_push' => $pref ? (bool) $pref->channel_push : true,
                'channel_wa' => $pref ? (bool) $pref->channel_wa : true,
                'channel_email' => $pref ? (bool) $pref->channel_email : true,
            ];
        }

        return $result;
    }

    /**
     * BE-NTF-04 / GAP-11: Perbarui preferensi notifikasi user dengan validasi scoping peran.
     */
    public function updateUserPreferences(User $user, array $data, Request $request): array
    {
        $itemsToUpdate = [];

        if (! empty($data['preferences']) && is_array($data['preferences'])) {
            $itemsToUpdate = $data['preferences'];
        } elseif (! empty($data['notif_type']) || ! empty($data['topic'])) {
            $itemsToUpdate = [$data];
        }

        if (empty($itemsToUpdate)) {
            throw new HttpException(422, 'Tidak ada data preferensi notifikasi yang dikirimkan.');
        }

        $user->loadMissing('roles');
        $userRoleCodes = $user->roles->pluck('code')->all();
        $isSuperAdmin = in_array('super_admin', $userRoleCodes, true);

        // Validasi scoping role untuk setiap item sebelum melakukan pembaruan
        foreach ($itemsToUpdate as $item) {
            $type = $item['topic'] ?? $item['notif_type'] ?? null;
            if (! $type) {
                continue;
            }

            $meta = NotificationPreference::NOTIFICATION_TYPES[$type] ?? null;
            if (! $meta) {
                throw new HttpException(422, "Jenis notifikasi '{$type}' tidak valid.");
            }

            $allowedRoles = $meta['allowed_roles'] ?? [];
            if (! $isSuperAdmin && ! empty($allowedRoles) && empty(array_intersect($userRoleCodes, $allowedRoles))) {
                throw new HttpException(403, "Akses ditolak: Peran Anda tidak memiliki wewenang untuk mengatur preferensi topik '{$type}'.");
            }
        }

        foreach ($itemsToUpdate as $item) {
            $type = $item['topic'] ?? $item['notif_type'] ?? null;
            if (! $type) {
                continue;
            }

            $updatePayload = [];
            if (array_key_exists('is_enabled', $item)) {
                $updatePayload['is_enabled'] = (bool) $item['is_enabled'];
            } elseif (array_key_exists('enabled', $item)) {
                $updatePayload['is_enabled'] = (bool) $item['enabled'];
            }

            if (array_key_exists('channel_push', $item)) {
                $updatePayload['channel_push'] = (bool) $item['channel_push'];
            }
            if (array_key_exists('channel_wa', $item)) {
                $updatePayload['channel_wa'] = (bool) $item['channel_wa'];
            }
            if (array_key_exists('channel_email', $item)) {
                $updatePayload['channel_email'] = (bool) $item['channel_email'];
            }

            NotificationPreference::updateOrCreate(
                ['user_id' => $user->id, 'notif_type' => $type],
                $updatePayload
            );
        }

        $this->logAudit($user->id, 'notification_preference.update', 'notification_preferences', null, null, [
            'updated_types' => collect($itemsToUpdate)->map(fn ($i) => $i['topic'] ?? $i['notif_type'])->values()->toArray(),
        ], $request);

        return $this->getUserPreferences($user);
    }

    /**
     * BE-NTF-04: Katalog jenis notifikasi yang tersedia dan kanal yang didukung.
     */
    public function getCatalog(): array
    {
        $catalog = [];
        foreach (NotificationPreference::NOTIFICATION_TYPES as $type => $info) {
            $catalog[] = [
                'type' => $type,
                'label' => $info['label'],
                'description' => $info['description'],
                'supported_channels' => $info['supported_channels'],
            ];
        }

        return $catalog;
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
