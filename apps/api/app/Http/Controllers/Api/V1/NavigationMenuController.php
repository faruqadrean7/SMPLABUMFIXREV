<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\UpdateNavigationMenuRequest;
use App\Services\PageService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Throwable;

class NavigationMenuController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PageService $service
    ) {}

    /**
     * BE-CMS-07: Get navigation menu (Admin)
     * GET /api/v1/navigation-menu
     */
    public function show(): JsonResponse
    {
        try {
            $menu = $this->service->getNavigationMenu();

            return $this->successResponse($menu);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil struktur menu navigasi', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-07: Get navigation menu (Public)
     * GET /api/v1/public/navigation-menu
     */
    public function publicShow(): JsonResponse
    {
        try {
            $menu = $this->service->getNavigationMenu();

            return $this->successResponse($menu);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil menu navigasi publik', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-07: Update navigation menu (Admin)
     * PUT /api/v1/navigation-menu
     */
    public function update(UpdateNavigationMenuRequest $request): JsonResponse
    {
        try {
            $menu = $this->service->updateNavigationMenu($request->validated('items'), $request);

            return $this->successResponse($menu);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui menu navigasi', 'INTERNAL_ERROR', 500);
        }
    }
}
