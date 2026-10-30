<?php

namespace App\Http\Controllers\Api\V1\Cbt;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cbt\StoreQuestionMediaRequest;
use App\Services\Cbt\QuestionMediaService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class QuestionMediaController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected QuestionMediaService $mediaService
    ) {}

    /**
     * BE-CBT-12: Unggah media gambar butir soal CBT.
     * POST /api/v1/cbt/question-media
     */
    public function store(StoreQuestionMediaRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $bankId = (string) $request->validated('bank_id');
        $user = $request->user();

        $result = $this->mediaService->upload($file, $bankId, $user);

        return $this->successResponse($result, 201);
    }
}
