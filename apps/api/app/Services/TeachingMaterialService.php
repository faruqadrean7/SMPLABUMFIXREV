<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\File;
use App\Models\TeachingMaterial;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TeachingMaterialService
{
    /**
     * BE-ACD-06: Mengambil daftar perangkat ajar dengan filter & paginasi.
     */
    public function listTeachingMaterials(array $filters, Request $request): LengthAwarePaginator
    {
        $query = TeachingMaterial::with(['gradeLevel', 'subject', 'uploader', 'file', 'versions.file'])
            ->whereNull('parent_id') // Hanya tampilkan berkas utama/induk di daftar direktori
            ->orderBy('created_at', 'desc');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['material_type'])) {
            $query->where('material_type', $filters['material_type']);
        }

        if (! empty($filters['grade_level_id'])) {
            $query->where('grade_level_id', $filters['grade_level_id']);
        }

        if (! empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (! empty($filters['uploaded_by'])) {
            $query->where('uploaded_by', $filters['uploaded_by']);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);

        return $query->paginate($perPage);
    }

    /**
     * BE-ACD-06: Mengunggah perangkat ajar baru.
     */
    public function createTeachingMaterial(array $data, Request $request): TeachingMaterial
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        $fileId = $data['file_id'] ?? null;

        if ($request->hasFile('file')) {
            $uploaded = $request->file('file');
            $storagePath = $uploaded->store("teaching-materials/{$currentUser->id}", 'local');
            $checksum = hash_file('sha256', $uploaded->getRealPath());

            $fileRecord = File::create([
                'owner_id' => $currentUser->id,
                'owner_type' => 'User',
                'bucket' => 'teaching-materials',
                'object_key' => $storagePath,
                'mime_type' => $uploaded->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $uploaded->getSize(),
                'visibility' => 'publik',
                'checksum' => $checksum,
            ]);

            $fileId = $fileRecord->id;
        }

        if (! $fileId) {
            throw new HttpException(422, 'Berkas perangkat ajar wajib diunggah.');
        }

        return DB::transaction(function () use ($data, $currentUser, $fileId, $request) {
            $version = (int) ($data['version'] ?? 1);

            $material = TeachingMaterial::create([
                'title' => trim($data['title']),
                'description' => isset($data['description']) ? trim($data['description']) : null,
                'material_type' => $data['material_type'],
                'grade_level_id' => $data['grade_level_id'] ?? null,
                'subject_id' => $data['subject_id'] ?? null,
                'uploaded_by' => $currentUser->id,
                'file_id' => $fileId,
                'version' => $version,
            ]);

            $this->logAudit(
                $currentUser->id,
                'teaching_material.created',
                'TeachingMaterial',
                $material->id,
                null,
                $material->toArray(),
                $request
            );

            return $material->load(['gradeLevel', 'subject', 'uploader', 'file']);
        });
    }

    /**
     * BE-ACD-06: Mengambil detail perangkat ajar beserta versi riwayatnya.
     */
    public function getTeachingMaterialDetail(string $id): TeachingMaterial
    {
        return TeachingMaterial::with([
            'gradeLevel',
            'subject',
            'uploader',
            'file',
            'parent',
            'versions.file',
            'versions.uploader',
        ])->findOrFail($id);
    }

    /**
     * BE-ACD-06: Mengunggah versi baru (revisi) dari perangkat ajar.
     */
    public function createVersion(string $id, array $data, Request $request): TeachingMaterial
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        $original = TeachingMaterial::findOrFail($id);
        $rootParent = $original->parent_id !== null ? $original->parent : $original;

        $fileId = $data['file_id'] ?? null;

        if ($request->hasFile('file')) {
            $uploaded = $request->file('file');
            $storagePath = $uploaded->store("teaching-materials/{$currentUser->id}", 'local');
            $checksum = hash_file('sha256', $uploaded->getRealPath());

            $fileRecord = File::create([
                'owner_id' => $currentUser->id,
                'owner_type' => 'User',
                'bucket' => 'teaching-materials',
                'object_key' => $storagePath,
                'mime_type' => $uploaded->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $uploaded->getSize(),
                'visibility' => 'publik',
                'checksum' => $checksum,
            ]);

            $fileId = $fileRecord->id;
        }

        if (! $fileId) {
            throw new HttpException(422, 'Berkas revisi perangkat ajar wajib diunggah.');
        }

        return DB::transaction(function () use ($rootParent, $data, $currentUser, $fileId, $request) {
            $maxVersion = TeachingMaterial::where('id', $rootParent->id)
                ->orWhere('parent_id', $rootParent->id)
                ->max('version');

            $nextVersion = isset($data['version']) ? (int) $data['version'] : ($maxVersion + 1);

            $newVersion = TeachingMaterial::create([
                'title' => ! empty($data['title']) ? trim($data['title']) : $rootParent->title,
                'description' => isset($data['description']) ? trim($data['description']) : $rootParent->description,
                'material_type' => $rootParent->material_type,
                'grade_level_id' => $rootParent->grade_level_id,
                'subject_id' => $rootParent->subject_id,
                'uploaded_by' => $currentUser->id,
                'file_id' => $fileId,
                'version' => $nextVersion,
                'parent_id' => $rootParent->id,
            ]);

            $this->logAudit(
                $currentUser->id,
                'teaching_material.version_created',
                'TeachingMaterial',
                $newVersion->id,
                null,
                $newVersion->toArray(),
                $request
            );

            return $newVersion->load(['gradeLevel', 'subject', 'uploader', 'file', 'parent']);
        });
    }

    /**
     * BE-ACD-06: Menghapus perangkat ajar.
     */
    public function deleteTeachingMaterial(string $id, Request $request): bool
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $material = TeachingMaterial::findOrFail($id);

        $isAdmin = $currentUser->hasRole('super_admin')
            || $currentUser->hasRole('admin_tu')
            || $currentUser->hasPermission('academic.schedule.manage')
            || $currentUser->roles()->where('code', 'like', 'admin%')->exists();

        // Otorisasi: hanya pemilik (uploader) atau admin yang berhak menghapus
        if (! $isAdmin && $material->uploaded_by !== $currentUser->id) {
            throw new HttpException(403, 'Anda tidak memiliki hak akses untuk menghapus perangkat ajar ini.');
        }

        return DB::transaction(function () use ($material, $currentUser, $request) {
            $oldData = $material->toArray();

            $material->delete();

            $this->logAudit(
                $currentUser->id,
                'teaching_material.deleted',
                'TeachingMaterial',
                $material->id,
                $oldData,
                null,
                $request
            );

            return true;
        });
    }

    /**
     * Catat log audit keamanan.
     */
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
