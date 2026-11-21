<?php

namespace App\Services;

use App\Models\Album;
use App\Models\AuditLog;
use App\Models\Bulletin;
use App\Models\MediaItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GalleryService
{
    // =========================================================================
    // ALBUMS
    // =========================================================================

    /**
     * BE-CMS-03: List albums with search, school filter, and item counts.
     */
    public function listAlbums(Request $request): LengthAwarePaginator
    {
        $perPage = min((int) $request->query('per_page', 15), 100);
        $search = $request->query('search');
        $schoolId = $request->query('school_id');

        $query = Album::with(['cover'])->withCount('items');

        if ($search) {
            $query->where('title', 'like', "%{$search}%");
        }

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * BE-CMS-03: Get single album detail with media items.
     */
    public function getAlbum(string $id): Album
    {
        return Album::with(['cover', 'items.file'])->withCount('items')->findOrFail($id);
    }

    /**
     * BE-CMS-03: Create album.
     */
    public function createAlbum(array $data, Request $request): Album
    {
        return DB::transaction(function () use ($data, $request) {
            $album = Album::create([
                'school_id' => $data['school_id'] ?? $request->user()?->school_id,
                'title' => $data['title'],
                'cover_file_id' => $data['cover_file_id'] ?? null,
            ]);

            $this->logAudit(
                $request->user()?->id,
                'album.created',
                'Album',
                $album->id,
                null,
                $album->toArray(),
                $request
            );

            return $album->load(['cover'])->loadCount('items');
        });
    }

    /**
     * BE-CMS-03: Update album.
     */
    public function updateAlbum(string $id, array $data, Request $request): Album
    {
        return DB::transaction(function () use ($id, $data, $request) {
            $album = Album::findOrFail($id);
            $oldValue = $album->toArray();

            if (isset($data['title'])) {
                $album->title = $data['title'];
            }

            if (array_key_exists('cover_file_id', $data)) {
                $album->cover_file_id = $data['cover_file_id'];
            }

            if (isset($data['school_id'])) {
                $album->school_id = $data['school_id'];
            }

            $album->save();

            $this->logAudit(
                $request->user()?->id,
                'album.updated',
                'Album',
                $album->id,
                $oldValue,
                $album->fresh()->toArray(),
                $request
            );

            return $album->fresh(['cover'])->loadCount('items');
        });
    }

    /**
     * BE-CMS-03: Delete album.
     */
    public function deleteAlbum(string $id, Request $request): void
    {
        DB::transaction(function () use ($id, $request) {
            $album = Album::findOrFail($id);
            $oldValue = $album->toArray();

            $album->delete();

            $this->logAudit(
                $request->user()?->id,
                'album.deleted',
                'Album',
                $id,
                $oldValue,
                null,
                $request
            );
        });
    }

    // =========================================================================
    // MEDIA ITEMS
    // =========================================================================

    /**
     * BE-CMS-03: Add media item to album.
     */
    public function createMediaItem(array $data, Request $request): MediaItem
    {
        return DB::transaction(function () use ($data, $request) {
            $mediaItem = MediaItem::create([
                'album_id' => $data['album_id'],
                'file_id' => $data['file_id'],
                'media_type' => $data['media_type'],
                'caption' => $data['caption'] ?? null,
            ]);

            $this->logAudit(
                $request->user()?->id,
                'media_item.created',
                'MediaItem',
                $mediaItem->id,
                null,
                $mediaItem->toArray(),
                $request
            );

            return $mediaItem->load('file');
        });
    }

    /**
     * BE-CMS-03: Update media item.
     */
    public function updateMediaItem(string $id, array $data, Request $request): MediaItem
    {
        return DB::transaction(function () use ($id, $data, $request) {
            $mediaItem = MediaItem::findOrFail($id);
            $oldValue = $mediaItem->toArray();

            if (isset($data['media_type'])) {
                $mediaItem->media_type = $data['media_type'];
            }

            if (array_key_exists('caption', $data)) {
                $mediaItem->caption = $data['caption'];
            }

            $mediaItem->save();

            $this->logAudit(
                $request->user()?->id,
                'media_item.updated',
                'MediaItem',
                $mediaItem->id,
                $oldValue,
                $mediaItem->fresh()->toArray(),
                $request
            );

            return $mediaItem->fresh(['file']);
        });
    }

    /**
     * BE-CMS-03: Delete media item.
     */
    public function deleteMediaItem(string $id, Request $request): void
    {
        DB::transaction(function () use ($id, $request) {
            $mediaItem = MediaItem::findOrFail($id);
            $oldValue = $mediaItem->toArray();

            $mediaItem->delete();

            $this->logAudit(
                $request->user()?->id,
                'media_item.deleted',
                'MediaItem',
                $id,
                $oldValue,
                null,
                $request
            );
        });
    }

    // =========================================================================
    // BULLETINS (MADING DIGITAL)
    // =========================================================================

    /**
     * BE-CMS-03: List bulletins.
     */
    public function listBulletins(Request $request): LengthAwarePaginator
    {
        $perPage = min((int) $request->query('per_page', 15), 100);
        $search = $request->query('search');
        $schoolId = $request->query('school_id');

        $query = Bulletin::with(['file', 'cover']);

        if ($search) {
            $query->where('title', 'like', "%{$search}%");
        }

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * BE-CMS-03: Get single bulletin.
     */
    public function getBulletin(string $id): Bulletin
    {
        return Bulletin::with(['file', 'cover'])->findOrFail($id);
    }

    /**
     * BE-CMS-03: Create bulletin.
     */
    public function createBulletin(array $data, Request $request): Bulletin
    {
        return DB::transaction(function () use ($data, $request) {
            $bulletin = Bulletin::create([
                'school_id' => $data['school_id'] ?? $request->user()?->school_id,
                'title' => $data['title'],
                'file_id' => $data['file_id'],
                'cover_file_id' => $data['cover_file_id'] ?? null,
            ]);

            $this->logAudit(
                $request->user()?->id,
                'bulletin.created',
                'Bulletin',
                $bulletin->id,
                null,
                $bulletin->toArray(),
                $request
            );

            return $bulletin->load(['file', 'cover']);
        });
    }

    /**
     * BE-CMS-03: Update bulletin.
     */
    public function updateBulletin(string $id, array $data, Request $request): Bulletin
    {
        return DB::transaction(function () use ($id, $data, $request) {
            $bulletin = Bulletin::findOrFail($id);
            $oldValue = $bulletin->toArray();

            if (isset($data['title'])) {
                $bulletin->title = $data['title'];
            }

            if (isset($data['file_id'])) {
                $bulletin->file_id = $data['file_id'];
            }

            if (array_key_exists('cover_file_id', $data)) {
                $bulletin->cover_file_id = $data['cover_file_id'];
            }

            if (isset($data['school_id'])) {
                $bulletin->school_id = $data['school_id'];
            }

            $bulletin->save();

            $this->logAudit(
                $request->user()?->id,
                'bulletin.updated',
                'Bulletin',
                $bulletin->id,
                $oldValue,
                $bulletin->fresh()->toArray(),
                $request
            );

            return $bulletin->fresh(['file', 'cover']);
        });
    }

    /**
     * BE-CMS-03: Delete bulletin.
     */
    public function deleteBulletin(string $id, Request $request): void
    {
        DB::transaction(function () use ($id, $request) {
            $bulletin = Bulletin::findOrFail($id);
            $oldValue = $bulletin->toArray();

            $bulletin->delete();

            $this->logAudit(
                $request->user()?->id,
                'bulletin.deleted',
                'Bulletin',
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
