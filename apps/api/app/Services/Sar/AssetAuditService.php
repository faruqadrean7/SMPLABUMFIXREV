<?php

namespace App\Services\Sar;

use App\Models\Asset;
use App\Models\AssetAudit;
use App\Models\AssetAuditItem;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AssetAuditService
{
    /**
     * BE-SAR-02: Daftar sesi stok opname aset.
     */
    public function listAudits(array $filters, ?Request $request = null): LengthAwarePaginator
    {
        $query = AssetAudit::with(['auditor:id,username', 'room'])
            ->withCount('items')
            ->orderBy('audit_date', 'desc');

        $user = $request?->user();
        if ($user && ! empty($user->school_id)) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('school_id')->orWhere('school_id', $user->school_id);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('audit_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('audit_date', '<=', $filters['to_date']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * BE-SAR-02: Detail sesi audit aset.
     */
    public function getAudit(string $id, ?Request $request = null): AssetAudit
    {
        $user = $request?->user();
        $audit = AssetAudit::with(['auditor:id,username', 'room', 'items.asset.room'])->findOrFail($id);

        if ($user && ! empty($user->school_id) && ! empty($audit->school_id) && $audit->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Sesi opname aset berada di luar sekolah penugasan Anda.');
        }

        return $audit;
    }

    /**
     * BE-SAR-02: Buat sesi stok opname baru.
     */
    public function createAudit(array $data, Request $request): AssetAudit
    {
        $user = $request->user();
        $this->authorizeManage($user);

        $status = $data['status'] ?? 'selesai';
        $schoolId = $data['school_id'] ?? $user?->school_id ?? null;

        $audit = DB::transaction(function () use ($data, $user, $status, $schoolId) {
            $audit = AssetAudit::create([
                'school_id' => $schoolId,
                'room_id' => $data['room_id'] ?? null,
                'title' => $data['title'],
                'audit_date' => $data['audit_date'],
                'auditor_id' => $user->id,
                'status' => $status,
                'notes' => $data['notes'] ?? null,
            ]);

            if (! empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    AssetAuditItem::create([
                        'audit_id' => $audit->id,
                        'asset_id' => $item['asset_id'],
                        'is_present' => $item['is_present'] ?? true,
                        'found_condition' => $item['found_condition'],
                        'notes' => $item['notes'] ?? null,
                    ]);

                    // Jika status audit selesai, sinkronkan kondisi aset terkini
                    if ($status === 'selesai') {
                        Asset::where('id', $item['asset_id'])->update([
                            'asset_condition' => $item['found_condition'],
                        ]);
                    }
                }
            }

            return $audit;
        });

        $this->logAudit($user->id, 'asset_audit.create', 'asset_audits', $audit->id, null, $audit->toArray(), $request);

        return $audit->fresh(['auditor:id,username', 'room', 'items.asset']);
    }

    /**
     * BE-SAR-02: Perbarui sesi stok opname dan itemnya.
     */
    public function updateAudit(string $id, array $data, Request $request): AssetAudit
    {
        $user = $request->user();
        $this->authorizeManage($user);

        $audit = AssetAudit::findOrFail($id);

        if ($user && ! empty($user->school_id) && ! empty($audit->school_id) && $audit->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang mengubah sesi opname sekolah lain.');
        }

        $oldValues = $audit->toArray();

        $audit = DB::transaction(function () use ($audit, $data) {
            $audit->update([
                'title' => $data['title'] ?? $audit->title,
                'audit_date' => $data['audit_date'] ?? $audit->audit_date,
                'room_id' => array_key_exists('room_id', $data) ? $data['room_id'] : $audit->room_id,
                'status' => $data['status'] ?? $audit->status,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $audit->notes,
            ]);

            if (isset($data['items']) && is_array($data['items'])) {
                // Hapus item lama dan ganti dengan item baru
                $audit->items()->delete();

                foreach ($data['items'] as $item) {
                    AssetAuditItem::create([
                        'audit_id' => $audit->id,
                        'asset_id' => $item['asset_id'],
                        'is_present' => $item['is_present'] ?? true,
                        'found_condition' => $item['found_condition'],
                        'notes' => $item['notes'] ?? null,
                    ]);

                    // Jika status audit telah selesai, sinkronkan kondisi aset terkini
                    if ($audit->status === 'selesai') {
                        Asset::where('id', $item['asset_id'])->update([
                            'asset_condition' => $item['found_condition'],
                        ]);
                    }
                }
            } elseif ($audit->status === 'selesai') {
                // Sinkronkan dari item yang sudah tersimpan
                foreach ($audit->items as $item) {
                    Asset::where('id', $item->asset_id)->update([
                        'asset_condition' => $item->found_condition,
                    ]);
                }
            }

            return $audit;
        });

        $this->logAudit($user->id, 'asset_audit.update', 'asset_audits', $audit->id, $oldValues, $audit->fresh()->toArray(), $request);

        return $audit->fresh(['auditor:id,username', 'room', 'items.asset']);
    }

    protected function authorizeManage(?User $user): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        $canManage = $user->hasRole('admin') ||
            $user->hasRole('super_admin') ||
            $user->hasRole('facility_staff') ||
            $user->hasRole('sarpras') ||
            $user->hasPermission('facility.asset.manage') ||
            $user->hasPermission('facility.booking.manage');

        if (! $canManage) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang mengelola sesi stok opname aset.');
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
