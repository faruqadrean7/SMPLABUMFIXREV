<?php

namespace App\Http\Controllers\Api\V1\Std;

use App\Http\Controllers\Controller;
use App\Http\Requests\Std\ListActivityDocumentRequest;
use App\Http\Requests\Std\ListExtracurricularRequest;
use App\Http\Requests\Std\RegisterExtracurricularMemberRequest;
use App\Http\Requests\Std\StoreActivityDocumentRequest;
use App\Http\Requests\Std\StoreExtracurricularJournalRequest;
use App\Http\Requests\Std\StoreExtracurricularRequest;
use App\Http\Requests\Std\UpdateExtracurricularMemberRequest;
use App\Http\Requests\Std\UpdateExtracurricularRequest;
use App\Services\Std\ExtracurricularService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ExtracurricularController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ExtracurricularService $service
    ) {}

    // =========================================================================
    // 1. EKSTRAKURIKULER
    // =========================================================================

    public function index(ListExtracurricularRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listExtracurriculars($request->validated());

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar ekstrakurikuler.', 'INTERNAL_ERROR', 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $ekskul = $this->service->getExtracurricular($id);

            return $this->successResponse($ekskul);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Ekstrakurikuler tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail ekstrakurikuler.', 'INTERNAL_ERROR', 500);
        }
    }

    public function store(StoreExtracurricularRequest $request): JsonResponse
    {
        try {
            $ekskul = $this->service->createExtracurricular($request->validated(), $request);

            return $this->successResponse($ekskul, 201);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menambahkan ekstrakurikuler baru.', 'INTERNAL_ERROR', 500);
        }
    }

    public function update(string $id, UpdateExtracurricularRequest $request): JsonResponse
    {
        try {
            $ekskul = $this->service->updateExtracurricular($id, $request->validated(), $request);

            return $this->successResponse($ekskul);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Ekstrakurikuler tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui data ekstrakurikuler.', 'INTERNAL_ERROR', 500);
        }
    }

    public function destroy(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteExtracurricular($id, $request);

            return $this->successResponse(['message' => 'Ekstrakurikuler berhasil dihapus.']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Ekstrakurikuler tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus ekstrakurikuler.', 'INTERNAL_ERROR', 500);
        }
    }

    // =========================================================================
    // 2. ANGGOTA & PENDAFTARAN EKSKUL
    // =========================================================================

    public function indexMembers(Request $request): JsonResponse
    {
        try {
            $ekskulId = $request->query('extracurricular_id');
            $studentId = $request->query('student_id');
            if (! $ekskulId && ! $studentId) {
                return $this->errorResponse('Parameter extracurricular_id atau student_id wajib disertakan.', 'VALIDATION_FAILED', 422);
            }

            $paginator = $this->service->listMembers($ekskulId, $request->all());

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar anggota ekstrakurikuler.', 'INTERNAL_ERROR', 500);
        }
    }

    public function registerMember(RegisterExtracurricularMemberRequest $request): JsonResponse
    {
        try {
            $member = $this->service->registerMember($request->validated(), $request);

            return $this->successResponse($member, 201);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Ekstrakurikuler atau data siswa tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mendaftar ekstrakurikuler.', 'INTERNAL_ERROR', 500);
        }
    }

    public function updateMember(string $id, UpdateExtracurricularMemberRequest $request): JsonResponse
    {
        try {
            $member = $this->service->updateMember($id, $request->validated(), $request);

            return $this->successResponse($member);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data keanggotaan ekstrakurikuler tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui data keanggotaan ekstrakurikuler.', 'INTERNAL_ERROR', 500);
        }
    }

    public function removeMember(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->removeMember($id, $request);

            return $this->successResponse(['message' => 'Anggota berhasil dikeluarkan dari ekstrakurikuler.']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data keanggotaan ekstrakurikuler tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengeluarkan anggota ekstrakurikuler.', 'INTERNAL_ERROR', 500);
        }
    }

    // =========================================================================
    // 3. JURNAL KEGIATAN & PRESENSI
    // =========================================================================

    public function indexJournals(Request $request): JsonResponse
    {
        try {
            $ekskulId = $request->query('extracurricular_id');
            if (! $ekskulId) {
                return $this->errorResponse('Parameter extracurricular_id wajib disertakan.', 'VALIDATION_FAILED', 422);
            }

            $paginator = $this->service->listJournals($ekskulId);

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat jurnal kegiatan.', 'INTERNAL_ERROR', 500);
        }
    }

    public function showJournal(string $id): JsonResponse
    {
        try {
            $journal = $this->service->getJournal($id);

            return $this->successResponse($journal);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Jurnal kegiatan tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail jurnal kegiatan.', 'INTERNAL_ERROR', 500);
        }
    }

    public function storeJournal(StoreExtracurricularJournalRequest $request): JsonResponse
    {
        try {
            $journal = $this->service->createJournal($request->validated(), $request);

            return $this->successResponse($journal, 201);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Ekstrakurikuler tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mencatat jurnal kegiatan.', 'INTERNAL_ERROR', 500);
        }
    }

    public function destroyJournal(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteJournal($id, $request);

            return $this->successResponse(['message' => 'Jurnal kegiatan berhasil dihapus.']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Jurnal kegiatan tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus jurnal kegiatan.', 'INTERNAL_ERROR', 500);
        }
    }

    // =========================================================================
    // 4. REPOSITORI DOKUMEN PROPOSAL & LAPORAN
    // =========================================================================

    public function indexDocuments(ListActivityDocumentRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listDocuments($request->validated());

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat dokumen kegiatan.', 'INTERNAL_ERROR', 500);
        }
    }

    public function showDocument(string $id): JsonResponse
    {
        try {
            $doc = $this->service->getDocument($id);

            return $this->successResponse($doc);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Dokumen kegiatan tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail dokumen.', 'INTERNAL_ERROR', 500);
        }
    }

    public function storeDocument(StoreActivityDocumentRequest $request): JsonResponse
    {
        try {
            $doc = $this->service->createDocument($request->validated(), $request);

            return $this->successResponse($doc, 201);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Ekstrakurikuler atau berkas tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengunggah dokumen kegiatan.', 'INTERNAL_ERROR', 500);
        }
    }

    public function destroyDocument(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteDocument($id, $request);

            return $this->successResponse(['message' => 'Dokumen kegiatan berhasil dihapus.']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Dokumen kegiatan tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus dokumen kegiatan.', 'INTERNAL_ERROR', 500);
        }
    }
}
