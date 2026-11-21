<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StorePostRequest;
use App\Http\Requests\Cms\UpdatePostRequest;
use App\Services\PostService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class PostController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PostService $service
    ) {}

    /**
     * BE-CMS-02: Public posts list
     * GET /api/v1/public/posts
     */
    public function publicIndex(Request $request): JsonResponse
    {
        try {
            $posts = $this->service->listPublicPosts($request);

            return $this->paginatedResponse($posts);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar berita publik', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-02: Public post detail by slug
     * GET /api/v1/public/posts/{slug}
     */
    public function publicShow(string $slug): JsonResponse
    {
        try {
            $post = $this->service->getPublicPost($slug);

            return $this->successResponse($post);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Berita tidak ditemukan atau belum diterbitkan', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil data berita', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-02: Admin posts list
     * GET /api/v1/posts
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $posts = $this->service->listAdminPosts($request);

            return $this->paginatedResponse($posts);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar berita admin', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-02: Create new post
     * POST /api/v1/posts
     */
    public function store(StorePostRequest $request): JsonResponse
    {
        try {
            $post = $this->service->createPost($request->validated(), $request);

            return $this->successResponse($post, 201);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menambahkan berita baru', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-02: Show post detail (Admin)
     * GET /api/v1/posts/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $post = $this->service->getAdminPost($id, $request);

            return $this->successResponse($post);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Berita tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil detail berita', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-02: Update post
     * PUT /api/v1/posts/{id}
     */
    public function update(UpdatePostRequest $request, string $id): JsonResponse
    {
        try {
            $post = $this->service->updatePost($id, $request->validated(), $request);

            return $this->successResponse($post);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Berita tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui berita', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-02: Delete post
     * DELETE /api/v1/posts/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $this->service->deletePost($id, $request);

            return $this->successResponse(['message' => 'Berita berhasil dihapus']);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Berita tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus berita', 'INTERNAL_ERROR', 500);
        }
    }
}
