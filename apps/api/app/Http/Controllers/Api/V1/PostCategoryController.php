<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StorePostCategoryRequest;
use App\Http\Requests\Cms\UpdatePostCategoryRequest;
use App\Services\PostService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class PostCategoryController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PostService $service
    ) {}

    /**
     * BE-CMS-02: List all post categories
     * GET /api/v1/post-categories
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $categories = $this->service->listCategories($request);

            return $this->successResponse($categories);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar kategori berita', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-02: Create post category
     * POST /api/v1/post-categories
     */
    public function store(StorePostCategoryRequest $request): JsonResponse
    {
        try {
            $category = $this->service->createCategory($request->validated(), $request);

            return $this->successResponse($category, 201);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menambahkan kategori berita baru', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-02: Show category detail
     * GET /api/v1/post-categories/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $category = $this->service->getCategory($id);

            return $this->successResponse($category);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Kategori berita tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil data kategori berita', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-02: Update category
     * PUT /api/v1/post-categories/{id}
     */
    public function update(UpdatePostCategoryRequest $request, string $id): JsonResponse
    {
        try {
            $category = $this->service->updateCategory($id, $request->validated(), $request);

            return $this->successResponse($category);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Kategori berita tidak ditemukan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui kategori berita', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-02: Delete category
     * DELETE /api/v1/post-categories/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $this->service->deleteCategory($id, $request);

            return $this->successResponse(['message' => 'Kategori berita berhasil dihapus']);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Kategori berita tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                400 => 'BUSINESS_RULE_VIOLATION',
                409 => 'CONFLICT',
                default => 'INTERNAL_ERROR',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus kategori berita', 'INTERNAL_ERROR', 500);
        }
    }
}
