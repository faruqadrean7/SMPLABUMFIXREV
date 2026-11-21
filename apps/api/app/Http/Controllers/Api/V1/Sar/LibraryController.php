<?php

namespace App\Http\Controllers\Api\V1\Sar;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sar\ListLibraryBookRequest;
use App\Http\Requests\Sar\ListLoanRequest;
use App\Http\Requests\Sar\ReturnLoanRequest;
use App\Http\Requests\Sar\StoreLibraryBookRequest;
use App\Http\Requests\Sar\StoreLoanRequest;
use App\Http\Requests\Sar\UpdateLibraryBookRequest;
use App\Services\Sar\LibraryService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class LibraryController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected LibraryService $service
    ) {}

    /**
     * BE-SAR-04: Ringkasan metrik dashboard perpustakaan.
     * GET /api/v1/library/summary
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $summary = $this->service->getSummary($request);

            return $this->successResponse($summary);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat ringkasan perpustakaan.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-04: Daftar buku perpustakaan.
     * GET /api/v1/library/books
     */
    public function indexBooks(ListLibraryBookRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listBooks($request->validated(), $request);

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat katalog buku.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-04: Detail buku perpustakaan.
     * GET /api/v1/library/books/{id}
     */
    public function showBook(string $id, Request $request): JsonResponse
    {
        try {
            $book = $this->service->getBook($id, $request);

            return $this->successResponse($book);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Buku tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail buku.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-04: Tambah buku baru ke perpustakaan.
     * POST /api/v1/library/books
     */
    public function storeBook(StoreLibraryBookRequest $request): JsonResponse
    {
        try {
            $book = $this->service->createBook($request->validated(), $request);

            return $this->successResponse($book, 201);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menambahkan buku baru.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-04: Perbarui informasi buku perpustakaan.
     * PUT /api/v1/library/books/{id}
     */
    public function updateBook(string $id, UpdateLibraryBookRequest $request): JsonResponse
    {
        try {
            $book = $this->service->updateBook($id, $request->validated(), $request);

            return $this->successResponse($book);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Buku tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui data buku.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-04: Hapus buku dari katalog.
     * DELETE /api/v1/library/books/{id}
     */
    public function destroyBook(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteBook($id, $request);

            return $this->successResponse(['message' => 'Buku berhasil dihapus.']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Buku tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus buku.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-04: Daftar transaksi peminjaman buku.
     * GET /api/v1/library/loans
     */
    public function indexLoans(ListLoanRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->listLoans($request->validated(), $request);

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar peminjaman buku.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-04: Detail data peminjaman buku.
     * GET /api/v1/library/loans/{id}
     */
    public function showLoan(string $id, Request $request): JsonResponse
    {
        try {
            $loan = $this->service->getLoan($id, $request);

            return $this->successResponse($loan);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data peminjaman tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail peminjaman.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-04: Peminjaman buku baru.
     * POST /api/v1/library/loans
     */
    public function storeLoan(StoreLoanRequest $request): JsonResponse
    {
        try {
            $loan = $this->service->createLoan($request->validated(), $request);

            return $this->successResponse($loan, 201);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Buku tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengajukan peminjaman buku.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-SAR-04: Pengembalian buku perpustakaan.
     * POST /api/v1/library/loans/{id}/return
     */
    public function returnLoan(string $id, ReturnLoanRequest $request): JsonResponse
    {
        try {
            $loan = $this->service->returnLoan($id, $request->validated(), $request);

            return $this->successResponse($loan);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data peminjaman tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'HTTP_ERROR', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memproses pengembalian buku.', 'INTERNAL_ERROR', 500);
        }
    }
}
