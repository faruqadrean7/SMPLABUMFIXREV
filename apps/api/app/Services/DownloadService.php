<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Download;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DownloadService
{
    /**
     * BE-CMS-06: List downloads with category, visibility, and search filters.
     */
    public function listDownloads(Request $request, bool $isPublic = false): LengthAwarePaginator|Collection
    {
        $query = Download::with('file');

        if ($isPublic) {
            $query->publicView();
        } elseif ($visibility = $request->query('visibility')) {
            $query->where('visibility', $visibility);
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($search = $request->query('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        if ($schoolId = $request->query('school_id')) {
            $query->where('school_id', $schoolId);
        }

        $query->orderBy('created_at', 'desc');

        if ($request->query('all') === 'true' || $request->query('paginate') === 'false') {
            return $query->get();
        }

        $perPage = min((int) $request->query('per_page', 15), 100);

        return $query->paginate($perPage);
    }

    /**
     * BE-CMS-06: Get single download item.
     */
    public function getDownload(string $id, bool $isPublic = false): Download
    {
        $query = Download::with('file');

        if ($isPublic) {
            $query->publicView();
        }

        return $query->findOrFail($id);
    }

    /**
     * BE-CMS-06: Create download item.
     */
    public function createDownload(array $data, Request $request): Download
    {
        return DB::transaction(function () use ($data, $request) {
            $download = Download::create([
                'school_id' => $data['school_id'] ?? $request->user()?->school_id,
                'title' => $data['title'],
                'category' => $data['category'],
                'visibility' => $data['visibility'],
                'file_id' => $data['file_id'],
            ]);

            $this->logAudit(
                $request->user()?->id,
                'download.created',
                'Download',
                $download->id,
                null,
                $download->toArray(),
                $request
            );

            return $download->load('file');
        });
    }

    /**
     * BE-CMS-06: Update download item.
     */
    public function updateDownload(string $id, array $data, Request $request): Download
    {
        return DB::transaction(function () use ($id, $data, $request) {
            $download = Download::findOrFail($id);
            $oldValue = $download->toArray();

            if (isset($data['title'])) {
                $download->title = $data['title'];
            }

            if (isset($data['category'])) {
                $download->category = $data['category'];
            }

            if (isset($data['visibility'])) {
                $download->visibility = $data['visibility'];
            }

            if (isset($data['file_id'])) {
                $download->file_id = $data['file_id'];
            }

            if (isset($data['school_id'])) {
                $download->school_id = $data['school_id'];
            }

            $download->save();

            $this->logAudit(
                $request->user()?->id,
                'download.updated',
                'Download',
                $download->id,
                $oldValue,
                $download->fresh()->toArray(),
                $request
            );

            return $download->fresh(['file']);
        });
    }

    /**
     * BE-CMS-06: Delete download item.
     */
    public function deleteDownload(string $id, Request $request): void
    {
        DB::transaction(function () use ($id, $request) {
            $download = Download::findOrFail($id);
            $oldValue = $download->toArray();

            $download->delete();

            $this->logAudit(
                $request->user()?->id,
                'download.deleted',
                'Download',
                $id,
                $oldValue,
                null,
                $request
            );
        });
    }

    /**
     * Log action into audit_logs.
     */
    protected function logAudit(
        ?string $actorId,
        string $action,
        string $entity,
        string $entityId,
        ?array $oldValue,
        ?array $newValue,
        Request $request
    ): void {
        AuditLog::create([
            'actor_id' => $actorId,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
