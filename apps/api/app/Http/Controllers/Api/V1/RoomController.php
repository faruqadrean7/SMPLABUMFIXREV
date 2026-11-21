<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreRoomRequest;
use App\Http\Requests\Master\UpdateRoomRequest;
use App\Services\RoomService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class RoomController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected RoomService $service
    ) {}

    /**
     * BE-MST-07: Get Room Types Reference
     * GET /api/v1/rooms/types
     */
    public function types(): JsonResponse
    {
        return $this->successResponse($this->service->getRoomTypes());
    }

    /**
     * BE-MST-07: List Rooms
     * GET /api/v1/rooms
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $rooms = $this->service->listRooms($request);

            return $this->paginatedResponse($rooms);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar ruangan', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-07: Create Room
     * POST /api/v1/rooms
     */
    public function store(StoreRoomRequest $request): JsonResponse
    {
        try {
            $room = $this->service->createRoom($request->validated(), $request);

            return $this->successResponse($room, 201);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menambahkan ruangan baru', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-07: Show Room Detail
     * GET /api/v1/rooms/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $room = $this->service->getRoom($id);

            return $this->successResponse($room);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Ruangan tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil data ruangan', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-07: Update Room
     * PUT /api/v1/rooms/{id}
     */
    public function update(UpdateRoomRequest $request, string $id): JsonResponse
    {
        try {
            $room = $this->service->updateRoom($id, $request->validated(), $request);

            return $this->successResponse($room);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Ruangan tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui data ruangan', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-MST-07: Delete Room (Soft delete)
     * DELETE /api/v1/rooms/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $this->service->deleteRoom($id, $request);

            return $this->successResponse(['message' => 'Ruangan berhasil dihapus']);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Ruangan tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus ruangan', 'INTERNAL_ERROR', 500);
        }
    }
}
