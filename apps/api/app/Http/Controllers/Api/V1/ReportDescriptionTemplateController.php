<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\StoreReportDescriptionTemplateRequest;
use App\Services\ReportCardService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ReportDescriptionTemplateController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ReportCardService $service
    ) {}

    /**
     * BE-GRD-04: Daftar template deskripsi capaian rapor.
     * GET /api/v1/report-description-templates
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $paginator = $this->service->listTemplates($request->all());

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat template deskripsi capaian.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GRD-04: Simpan atau perbarui template deskripsi capaian.
     * POST /api/v1/report-description-templates
     */
    public function store(StoreReportDescriptionTemplateRequest $request): JsonResponse
    {
        try {
            $template = $this->service->storeTemplate($request->validated(), $request);

            return $this->successResponse($template, 201);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'BAD_REQUEST', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menyimpan template deskripsi.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GRD-04: Hapus template deskripsi capaian.
     * DELETE /api/v1/report-description-templates/{id}
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteTemplate($id, $request);

            return $this->successResponse(['message' => 'Template deskripsi berhasil dihapus.']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Template deskripsi tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menghapus template deskripsi.', 'INTERNAL_ERROR', 500);
        }
    }
}
