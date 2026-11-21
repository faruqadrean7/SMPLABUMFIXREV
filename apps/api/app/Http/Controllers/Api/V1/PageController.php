<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StorePageRequest;
use App\Http\Requests\Cms\UpdatePageRequest;
use App\Services\PageService;
use App\Traits\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class PageController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PageService $service
    ) {}

    /**
     * BE-CMS-07: List pages (Admin)
     * GET /api/v1/pages
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $pages = $this->service->listPages($request, false);

            if ($pages instanceof LengthAwarePaginator) {
                return $this->paginatedResponse($pages);
            }

            return $this->successResponse($pages);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar halaman', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-07: List published pages (Public)
     * GET /api/v1/public/pages
     */
    public function publicIndex(Request $request): JsonResponse
    {
        try {
            $pages = $this->service->listPages($request, true);

            if ($pages instanceof LengthAwarePaginator) {
                return $this->paginatedResponse($pages);
            }

            return $this->successResponse($pages);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar halaman publik', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-07: Show page detail by ID (Admin)
     * GET /api/v1/pages/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $page = $this->service->getPage($id);

            return $this->successResponse($page);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Halaman tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil detail halaman', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-07: Show published page by slug (Public)
     * GET /api/v1/public/pages/{slug}
     */
    public function publicShow(string $slug): JsonResponse
    {
        try {
            $page = $this->service->getPublicPageBySlug($slug);

            return $this->successResponse($page);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Halaman tidak ditemukan atau belum diterbitkan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil detail halaman publik', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-07: Create page (Admin)
     * POST /api/v1/pages
     */
    public function store(StorePageRequest $request): JsonResponse
    {
        try {
            $page = $this->service->createPage($request->validated(), $request);

            return $this->successResponse($page, 201);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal membuat halaman baru', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-07: Update page (Admin)
     * PUT /api/v1/pages/{id}
     */
    public function update(UpdatePageRequest $request, string $id): JsonResponse
    {
        try {
            $page = $this->service->updatePage($id, $request->validated(), $request);

            return $this->successResponse($page);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Halaman tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui halaman', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-07: Delete page (Admin)
     * DELETE /api/v1/pages/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $this->service->deletePage($id, $request);

            return $this->successResponse(['message' => 'Halaman berhasil dihapus']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Halaman tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus halaman', 'INTERNAL_ERROR', 500);
        }
    }
}
