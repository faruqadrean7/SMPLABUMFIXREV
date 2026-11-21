<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\UpdateSiteSettingsRequest;
use App\Services\SiteSettingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Throwable;

class SiteSettingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SiteSettingService $service
    ) {}

    /**
     * BE-CMS-01: Public school site profile
     * GET /api/v1/public/site
     */
    public function publicSite(): JsonResponse
    {
        try {
            $profile = $this->service->getSiteProfile();

            return $this->successResponse($profile);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil data profil situs sekolah', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-01: Get site settings for admin panel
     * GET /api/v1/site-settings
     */
    public function show(): JsonResponse
    {
        try {
            $profile = $this->service->getSiteProfile();

            return $this->successResponse($profile);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil pengaturan situs', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CMS-01: Update site settings
     * PUT /api/v1/site-settings
     */
    public function update(UpdateSiteSettingsRequest $request): JsonResponse
    {
        try {
            $profile = $this->service->updateSiteSettings($request->validated(), $request);

            return $this->successResponse($profile);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui pengaturan situs', 'INTERNAL_ERROR', 500);
        }
    }
}
