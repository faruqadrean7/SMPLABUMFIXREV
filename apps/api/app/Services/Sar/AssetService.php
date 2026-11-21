<?php

namespace App\Services\Sar;

use App\Models\Asset;
use App\Models\AssetAudit;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AssetService
{
    /**
     * BE-SAR-02: Daftar aset berpaginasi.
     */
    public function listAssets(array $filters, ?Request $request = null): LengthAwarePaginator
    {
        $query = Asset::with('room')->orderBy('created_at', 'desc');

        $user = $request?->user();
        if ($user && ! empty($user->school_id)) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('school_id')->orWhere('school_id', $user->school_id);
            });
        }

        if (! empty($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['asset_condition'])) {
            $query->where('asset_condition', $filters['asset_condition']);
        }

        if (! empty($filters['search'])) {
            $kw = '%'.strtolower($filters['search']).'%';
            $query->where(function ($q) use ($kw) {
                $q->whereRaw('LOWER(name) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(asset_code) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(brand) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(model) LIKE ?', [$kw]);
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * BE-SAR-02: Detail aset berdasarkan ID.
     */
    public function getAsset(string $id, ?Request $request = null): Asset
    {
        $user = $request?->user();
        $asset = Asset::with([
            'room',
            'auditItems.audit.auditor:id,username',
            'tickets.reporter:id,username',
            'tickets.assignee:id,username',
        ])->findOrFail($id);

        if ($user && ! empty($user->school_id) && ! empty($asset->school_id) && $asset->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Aset berada di luar sekolah penugasan Anda.');
        }

        return $asset;
    }

    /**
     * BE-SAR-02: Scan / lookup aset berdasarkan kode QR/barcode.
     */
    public function getAssetByCode(string $code, ?Request $request = null): Asset
    {
        $user = $request?->user();
        $query = Asset::with([
            'room',
            'auditItems.audit.auditor:id,username',
            'tickets.reporter:id,username',
            'tickets.assignee:id,username',
        ])->where('asset_code', $code);

        if ($user && ! empty($user->school_id)) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('school_id')->orWhere('school_id', $user->school_id);
            });
        }

        return $query->firstOrFail();
    }

    /**
     * BE-SAR-02: Dapatkan data label QR code dan identitas fisik untuk pencetakan.
     */
    public function getAssetQr(string $id, Request $request): array
    {
        $asset = $this->getAsset($id, $request);

        $qrPayload = [
            'system' => 'SISTLABUM',
            'module' => 'SAR-02',
            'asset_id' => $asset->id,
            'code' => $asset->asset_code,
            'name' => $asset->name,
            'room' => $asset->room?->name ?? 'Belum ditentukan',
            'category' => $asset->category,
        ];

        return [
            'asset' => $asset,
            'qr_code' => $asset->asset_code,
            'qr_data_string' => $asset->asset_code,
            'qr_payload' => $qrPayload,
            'label_title' => 'INVENTARIS SEKOLAH',
            'school_id' => $asset->school_id,
            'printed_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * BE-SAR-02: Ringkasan metrik inventaris dan kondisi aset.
     */
    public function getSummary(Request $request): array
    {
        $user = $request->user();
        $query = Asset::query();

        if ($user && ! empty($user->school_id)) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('school_id')->orWhere('school_id', $user->school_id);
            });
        }

        $totalAssets = (clone $query)->count();
        $conditionBaik = (clone $query)->where('asset_condition', 'baik')->count();
        $conditionRusakRingan = (clone $query)->where('asset_condition', 'rusak_ringan')->count();
        $conditionRusakBerat = (clone $query)->where('asset_condition', 'rusak_berat')->count();

        // Kategori distribusi
        $categories = (clone $query)
            ->select('category', DB::raw('count(*) as count'))
            ->groupBy('category')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('count', 'category')
            ->toArray();

        // Sesi stok opname
        $auditQuery = AssetAudit::query();
        if ($user && ! empty($user->school_id)) {
            $auditQuery->where(function ($q) use ($user) {
                $q->whereNull('school_id')->orWhere('school_id', $user->school_id);
            });
        }
        $totalAudits = $auditQuery->count();
        $completedAudits = (clone $auditQuery)->where('status', 'selesai')->count();

        return [
            'total_assets' => $totalAssets,
            'condition_baik' => $conditionBaik,
            'condition_rusak_ringan' => $conditionRusakRingan,
            'condition_rusak_berat' => $conditionRusakBerat,
            'category_counts' => $categories,
            'total_audits' => $totalAudits,
            'completed_audits' => $completedAudits,
        ];
    }

    /**
     * BE-SAR-02: Registrasi aset baru.
     */
    public function createAsset(array $data, Request $request): Asset
    {
        $user = $request->user();
        $this->authorizeManage($user);

        $schoolId = $data['school_id'] ?? $user?->school_id ?? null;

        $asset = Asset::create([
            'school_id' => $schoolId,
            'room_id' => $data['room_id'] ?? null,
            'asset_code' => $data['asset_code'],
            'name' => $data['name'],
            'category' => $data['category'],
            'brand' => $data['brand'] ?? null,
            'model' => $data['model'] ?? null,
            'serial_number' => $data['serial_number'] ?? null,
            'asset_condition' => $data['asset_condition'] ?? 'baik',
            'acquired_at' => $data['acquired_at'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->logAudit($user?->id, 'asset.create', 'assets', $asset->id, null, $asset->toArray(), $request);

        return $asset->fresh('room');
    }

    /**
     * BE-SAR-02: Perbarui data aset.
     */
    public function updateAsset(string $id, array $data, Request $request): Asset
    {
        $user = $request->user();
        $this->authorizeManage($user);

        $asset = Asset::findOrFail($id);

        if ($user && ! empty($user->school_id) && ! empty($asset->school_id) && $asset->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang mengubah aset sekolah lain.');
        }

        $oldValues = $asset->toArray();

        $asset->update($data);

        $this->logAudit($user?->id, 'asset.update', 'assets', $asset->id, $oldValues, $asset->fresh()->toArray(), $request);

        return $asset->fresh(['room', 'auditItems.audit']);
    }

    /**
     * BE-SAR-02: Hapus data aset.
     */
    public function deleteAsset(string $id, Request $request): bool
    {
        $user = $request->user();
        $this->authorizeManage($user);

        $asset = Asset::findOrFail($id);

        if ($user && ! empty($user->school_id) && ! empty($asset->school_id) && $asset->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang menghapus aset sekolah lain.');
        }

        $oldValues = $asset->toArray();

        $asset->delete();

        $this->logAudit($user?->id, 'asset.delete', 'assets', $id, $oldValues, null, $request);

        return true;
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
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang mengelola data aset.');
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
