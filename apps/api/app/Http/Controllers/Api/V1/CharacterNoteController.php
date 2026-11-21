<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\BulkStoreCharacterNoteRequest;
use App\Http\Requests\Academic\StoreCharacterNoteRequest;
use App\Services\CharacterNoteService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class CharacterNoteController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CharacterNoteService $service
    ) {}

    /**
     * BE-GRD-02: Daftar catatan karakter bulanan.
     * GET /api/v1/character-notes
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $paginator = $this->service->listCharacterNotes($request->all(), $request);

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil daftar catatan karakter', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GRD-02: Simpan atau perbarui catatan karakter satuan.
     * POST /api/v1/character-notes
     */
    public function store(StoreCharacterNoteRequest $request): JsonResponse
    {
        try {
            $note = $this->service->storeCharacterNote($request->validated(), $request);

            return $this->successResponse($note, 201);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                422 => 'BUSINESS_RULE_VIOLATION',
                403 => 'FORBIDDEN',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menyimpan catatan karakter', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GRD-02: Input massal catatan karakter rombel kelas.
     * POST /api/v1/character-notes/bulk
     */
    public function bulk(BulkStoreCharacterNoteRequest $request): JsonResponse
    {
        try {
            $result = $this->service->bulkStoreCharacterNotes($request->validated(), $request);

            return $this->successResponse($result, 201);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                422 => 'BUSINESS_RULE_VIOLATION',
                403 => 'FORBIDDEN',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menyimpan catatan karakter massal', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GRD-02 / ADM-GRD-02: Tampilan massal catatan karakter satu rombel kelas.
     * GET /api/v1/character-notes/matrix
     */
    public function matrix(Request $request): JsonResponse
    {
        try {
            $data = $this->service->getClassCharacterNotes($request->all(), $request);

            return $this->successResponse($data);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                422 => 'BUSINESS_RULE_VIOLATION',
                403 => 'FORBIDDEN',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat tampilan massal catatan karakter', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GRD-02: Detail catatan karakter.
     * GET /api/v1/character-notes/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $note = $this->service->getCharacterNoteDetail($id, $request);

            return $this->successResponse($note);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Catatan karakter tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengambil detail catatan karakter', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-GRD-02: Hapus catatan karakter.
     * DELETE /api/v1/character-notes/{id}
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteCharacterNote($id, $request);

            return $this->successResponse(['deleted' => true, 'message' => 'Catatan karakter berhasil dihapus']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Catatan karakter tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                422 => 'BUSINESS_RULE_VIOLATION',
                403 => 'FORBIDDEN',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menghapus catatan karakter', 'INTERNAL_ERROR', 500);
        }
    }
}
