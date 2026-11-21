<?php

namespace App\Http\Controllers\Api\V1\Hum;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hum\ListTracerSurveyRequest;
use App\Http\Requests\Hum\StoreTracerSurveyRequest;
use App\Http\Requests\Hum\SubmitTracerResponseRequest;
use App\Http\Requests\Hum\UpdateTracerSurveyRequest;
use App\Services\Hum\AlumniService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class TracerSurveyController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AlumniService $service
    ) {}

    /**
     * BE-HUM-02: Daftar kuesioner tracer study.
     * GET /api/v1/tracer-surveys
     */
    public function index(ListTracerSurveyRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listSurveys($request->validated(), $request->user());

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar survei tracer.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-HUM-02: Detail instrumen kuesioner tracer study.
     * GET /api/v1/tracer-surveys/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $survey = $this->service->getSurvey($id);

            return $this->successResponse($survey);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Kuesioner tracer study tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat kuesioner tracer.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-HUM-02: Buat kuesioner tracer study baru.
     * POST /api/v1/tracer-surveys
     */
    public function store(StoreTracerSurveyRequest $request): JsonResponse
    {
        try {
            $survey = $this->service->createSurvey($request->validated(), $request);

            return $this->successResponse($survey, 201);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal membuat kuesioner tracer study baru.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-HUM-02: Perbarui kuesioner tracer study.
     * PUT /api/v1/tracer-surveys/{id}
     */
    public function update(string $id, UpdateTracerSurveyRequest $request): JsonResponse
    {
        try {
            $survey = $this->service->updateSurvey($id, $request->validated(), $request);

            return $this->successResponse($survey);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Kuesioner tracer study tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui kuesioner tracer study.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-HUM-02: Hapus kuesioner tracer study.
     * DELETE /api/v1/tracer-surveys/{id}
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteSurvey($id, $request);

            return $this->successResponse(['message' => 'Kuesioner tracer study berhasil dihapus.']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Kuesioner tracer study tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus kuesioner tracer study.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-HUM-02: Pengisian respons kuesioner tracer study oleh alumni.
     * POST /api/v1/tracer-surveys/{id}/responses
     */
    public function storeResponse(string $id, SubmitTracerResponseRequest $request): JsonResponse
    {
        try {
            $response = $this->service->submitResponse($id, $request->validated(), $request);

            return $this->successResponse($response, 201);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Kuesioner tracer study tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengirimkan respons kuesioner tracer.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-HUM-02: Rekapitulasi statistik respons kuesioner tracer study.
     * GET /api/v1/tracer-surveys/{id}/stats
     */
    public function stats(string $id, Request $request): JsonResponse
    {
        try {
            $stats = $this->service->getSurveyStats($id, $request);

            return $this->successResponse($stats);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Kuesioner tracer study tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat statistik kuesioner tracer.', 'INTERNAL_ERROR', 500);
        }
    }
}
