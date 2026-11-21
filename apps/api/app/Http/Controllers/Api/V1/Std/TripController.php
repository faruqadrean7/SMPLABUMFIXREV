<?php

namespace App\Http\Controllers\Api\V1\Std;

use App\Http\Controllers\Controller;
use App\Http\Requests\Std\ListTripRequest;
use App\Http\Requests\Std\RegisterTripParticipantRequest;
use App\Http\Requests\Std\StoreTripRequest;
use App\Http\Requests\Std\SubmitTripConsentRequest;
use App\Http\Requests\Std\UpdateTripRequest;
use App\Services\Std\TripService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class TripController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TripService $service
    ) {}

    /**
     * BE-STD-04: Daftar agenda study trip / outing class.
     * GET /api/v1/trips
     */
    public function index(ListTripRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listTrips($request->validated());

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar study trip.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-STD-04: Detail study trip beserta panduan & rute.
     * GET /api/v1/trips/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $trip = $this->service->getTrip($id);

            return $this->successResponse($trip);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Agenda study trip tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail study trip.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-STD-04: Tambah agenda study trip baru.
     * POST /api/v1/trips
     */
    public function store(StoreTripRequest $request): JsonResponse
    {
        try {
            $trip = $this->service->createTrip($request->validated(), $request);

            return $this->successResponse($trip, 201);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menambahkan study trip baru.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-STD-04: Perbarui agenda study trip.
     * PUT /api/v1/trips/{id}
     */
    public function update(string $id, UpdateTripRequest $request): JsonResponse
    {
        try {
            $trip = $this->service->updateTrip($id, $request->validated(), $request);

            return $this->successResponse($trip);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Agenda study trip tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui study trip.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-STD-04: Hapus agenda study trip.
     * DELETE /api/v1/trips/{id}
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteTrip($id, $request);

            return $this->successResponse(['message' => 'Study trip berhasil dihapus.']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Agenda study trip tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus study trip.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-STD-04: Daftar peserta study trip.
     * GET /api/v1/trips/{id}/participants
     */
    public function indexParticipants(string $id): JsonResponse
    {
        try {
            $paginator = $this->service->listParticipants($id);

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar peserta.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-STD-04: Daftarkan siswa sebagai peserta study trip.
     * POST /api/v1/trips/{id}/participants
     */
    public function storeParticipant(string $id, RegisterTripParticipantRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['trip_id'] = $id;

            $participant = $this->service->registerParticipant($data, $request);

            return $this->successResponse($participant, 201);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Study trip atau data siswa tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mendaftarkan peserta study trip.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-STD-04: Batalkan kepesertaan siswa dari study trip.
     * DELETE /api/v1/trip-participants/{id}
     */
    public function destroyParticipant(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->removeParticipant($id, $request);

            return $this->successResponse(['message' => 'Peserta berhasil dihapus dari study trip.']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data peserta tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus peserta study trip.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-STD-04: Daftar persetujuan / izin orang tua per perjalanan.
     * GET /api/v1/trips/{id}/consents
     */
    public function indexConsents(string $id, Request $request): JsonResponse
    {
        try {
            $consents = $this->service->listConsents($id, $request);

            return $this->successResponse($consents);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Study trip tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat izin orang tua.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-STD-04: Kirim izin digital / persetujuan orang tua (setuju/tolak).
     * POST /api/v1/trips/{id}/consents
     */
    public function storeConsent(string $id, SubmitTripConsentRequest $request): JsonResponse
    {
        try {
            $consent = $this->service->submitConsent($id, $request->validated(), $request);

            return $this->successResponse($consent, 201);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Study trip tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengirim izin digital orang tua.', 'INTERNAL_ERROR', 500);
        }
    }
}
