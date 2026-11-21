<?php

namespace App\Http\Controllers\Api\V1\Hum;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hum\ListExchangeProgramRequest;
use App\Http\Requests\Hum\RegisterExchangeParticipantRequest;
use App\Http\Requests\Hum\StoreExchangeProgramRequest;
use App\Http\Requests\Hum\UpdateExchangeParticipantRequest;
use App\Http\Requests\Hum\UpdateExchangeProgramRequest;
use App\Services\Hum\PartnershipService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ExchangeProgramController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PartnershipService $service
    ) {}

    /**
     * BE-HUM-01: Daftar program pertukaran pelajar / guru.
     * GET /api/v1/exchange-programs
     */
    public function index(ListExchangeProgramRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listExchangePrograms($request->validated());

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar program pertukaran.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-HUM-01: Detail program pertukaran beserta daftar siswa peserta.
     * GET /api/v1/exchange-programs/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $program = $this->service->getExchangeProgram($id);

            return $this->successResponse($program);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Program pertukaran tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail program pertukaran.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-HUM-01: Buat program pertukaran pelajar baru.
     * POST /api/v1/exchange-programs
     */
    public function store(StoreExchangeProgramRequest $request): JsonResponse
    {
        try {
            $program = $this->service->createExchangeProgram($request->validated(), $request);

            return $this->successResponse($program, 201);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Mitra kerja sama tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menambahkan program pertukaran baru.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-HUM-01: Perbarui data program pertukaran.
     * PUT /api/v1/exchange-programs/{id}
     */
    public function update(string $id, UpdateExchangeProgramRequest $request): JsonResponse
    {
        try {
            $program = $this->service->updateExchangeProgram($id, $request->validated(), $request);

            return $this->successResponse($program);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Program pertukaran tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui program pertukaran.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-HUM-01: Hapus program pertukaran.
     * DELETE /api/v1/exchange-programs/{id}
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteExchangeProgram($id, $request);

            return $this->successResponse(['message' => 'Program pertukaran berhasil dihapus.']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Program pertukaran tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus program pertukaran.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-HUM-01: Daftar peserta pertukaran pelajar per program.
     * GET /api/v1/exchange-programs/{id}/participants
     */
    public function indexParticipants(string $id): JsonResponse
    {
        try {
            $participants = $this->service->listParticipants($id);

            return $this->successResponse($participants);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Program pertukaran tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat peserta pertukaran.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-HUM-01: Daftarkan siswa ke program pertukaran.
     * POST /api/v1/exchange-programs/{id}/participants
     */
    public function storeParticipant(string $id, RegisterExchangeParticipantRequest $request): JsonResponse
    {
        try {
            $participant = $this->service->registerParticipant($id, $request->validated(), $request);

            return $this->successResponse($participant, 201);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Program pertukaran atau data siswa tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mendaftarkan peserta ke program pertukaran.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-HUM-01: Perbarui status / evaluasi peserta pertukaran.
     * PUT /api/v1/exchange-participants/{participantId}
     */
    public function updateParticipant(string $participantId, UpdateExchangeParticipantRequest $request): JsonResponse
    {
        try {
            $participant = $this->service->updateParticipant($participantId, $request->validated(), $request);

            return $this->successResponse($participant);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data peserta pertukaran tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui status peserta pertukaran.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-HUM-01: Hapus / batalkan kepesertaan siswa dari program pertukaran.
     * DELETE /api/v1/exchange-participants/{participantId}
     */
    public function destroyParticipant(string $participantId, Request $request): JsonResponse
    {
        try {
            $this->service->removeParticipant($participantId, $request);

            return $this->successResponse(['message' => 'Peserta berhasil dihapus dari program pertukaran.']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data peserta pertukaran tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus peserta pertukaran.', 'INTERNAL_ERROR', 500);
        }
    }
}
