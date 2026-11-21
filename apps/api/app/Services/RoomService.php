<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Room;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RoomService
{
    /**
     * BE-MST-07: Get list of valid room types.
     */
    public function getRoomTypes(): array
    {
        return Room::ROOM_TYPES;
    }

    /**
     * BE-MST-07: List rooms with pagination, search, and filters.
     */
    public function listRooms(Request $request): LengthAwarePaginator
    {
        $limit = min($request->integer('limit', 20), 100);
        $type = $request->query('room_type');
        $minCapacity = $request->query('min_capacity');
        $schoolId = $request->query('school_id');
        $q = trim((string) $request->query('q', $request->query('search', '')));

        $query = Room::withCount([
            'schedules',
            'examRooms',
            'facilities',
            'assets',
            'labEquipment',
        ]);

        if ($type) {
            $query->where('room_type', $type);
        }

        if ($minCapacity !== null && is_numeric($minCapacity)) {
            $query->where('capacity', '>=', (int) $minCapacity);
        }

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('code', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%");
            });
        }

        return $query->orderBy('code', 'asc')->paginate($limit);
    }

    /**
     * BE-MST-07: Get single room detail with related usages.
     */
    public function getRoom(string $id): Room
    {
        return Room::with([
            'facilities',
            'schedules.class',
            'schedules.subject',
            'schedules.teacher',
        ])->withCount([
            'schedules',
            'examRooms',
            'facilities',
            'assets',
            'labEquipment',
        ])->findOrFail($id);
    }

    /**
     * BE-MST-07: Create new room.
     */
    public function createRoom(array $data, Request $request): Room
    {
        return DB::transaction(function () use ($data, $request) {
            $room = Room::create([
                'school_id' => $data['school_id'] ?? $request->user()?->school_id,
                'code' => $data['code'],
                'name' => $data['name'],
                'room_type' => $data['room_type'],
                'capacity' => $data['capacity'] ?? 40,
            ]);

            $this->logAudit(
                $request->user()?->id,
                'room.created',
                'Room',
                $room->id,
                null,
                $room->toArray(),
                $request
            );

            return $room;
        });
    }

    /**
     * BE-MST-07: Update existing room.
     */
    public function updateRoom(string $id, array $data, Request $request): Room
    {
        return DB::transaction(function () use ($id, $data, $request) {
            $room = Room::findOrFail($id);
            $oldValue = $room->toArray();

            $room->update(array_filter([
                'code' => $data['code'] ?? null,
                'name' => $data['name'] ?? null,
                'room_type' => $data['room_type'] ?? null,
                'capacity' => $data['capacity'] ?? null,
                'school_id' => $data['school_id'] ?? null,
            ], fn ($val) => $val !== null));

            $this->logAudit(
                $request->user()?->id,
                'room.updated',
                'Room',
                $room->id,
                $oldValue,
                $room->toArray(),
                $request
            );

            return $room;
        });
    }

    /**
     * BE-MST-07: Soft delete room if no active dependencies exist.
     */
    public function deleteRoom(string $id, Request $request): void
    {
        $room = Room::findOrFail($id);

        if ($room->schedules()->exists()) {
            throw new HttpException(
                409,
                'Ruangan tidak dapat dihapus karena masih digunakan pada jadwal pelajaran terikat.',
                null,
                [],
                409
            );
        }

        if ($room->examRooms()->exists()) {
            throw new HttpException(
                409,
                'Ruangan tidak dapat dihapus karena masih terikat pada ruangan ujian CBT.',
                null,
                [],
                409
            );
        }

        if ($room->labEquipment()->exists()) {
            throw new HttpException(
                409,
                'Ruangan tidak dapat dihapus karena masih memiliki inventaris peralatan laboratorium.',
                null,
                [],
                409
            );
        }

        $oldValue = $room->toArray();
        $room->delete();

        $this->logAudit(
            $request->user()?->id,
            'room.deleted',
            'Room',
            $room->id,
            $oldValue,
            null,
            $request
        );
    }

    /**
     * Helper to write an audit log entry.
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
