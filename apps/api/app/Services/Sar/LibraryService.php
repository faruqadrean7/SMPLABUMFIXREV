<?php

namespace App\Services\Sar;

use App\Models\AuditLog;
use App\Models\LibraryBook;
use App\Models\Loan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LibraryService
{
    /**
     * BE-SAR-04: Daftar buku perpustakaan berpaginasi.
     */
    public function listBooks(array $filters, ?Request $request = null): LengthAwarePaginator
    {
        $query = LibraryBook::with(['coverFile', 'file'])->orderBy('created_at', 'desc');

        $user = $request?->user();
        if ($user && ! empty($user->school_id)) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('school_id')->orWhere('school_id', $user->school_id);
            });
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['book_type'])) {
            $query->where('book_type', $filters['book_type']);
        }

        if (! empty($filters['search'])) {
            $kw = '%'.strtolower($filters['search']).'%';
            $query->where(function ($q) use ($kw) {
                $q->whereRaw('LOWER(title) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(author) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(publisher) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(isbn) LIKE ?', [$kw]);
            });
        }

        $paginator = $query->paginate((int) ($filters['per_page'] ?? 15));

        $paginator->getCollection()->transform(function (LibraryBook $book) {
            $book->append('available_stock');

            return $book;
        });

        return $paginator;
    }

    /**
     * BE-SAR-04: Detail buku perpustakaan.
     */
    public function getBook(string $id, ?Request $request = null): LibraryBook
    {
        $user = $request?->user();
        $book = LibraryBook::with(['coverFile', 'file', 'loans.borrower'])->findOrFail($id);

        if ($user && ! empty($user->school_id) && ! empty($book->school_id) && $book->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Buku perpustakaan berada di luar sekolah penugasan Anda.');
        }

        $book->append('available_stock');

        return $book;
    }

    /**
     * BE-SAR-04: Tambah buku baru ke katalog perpustakaan.
     */
    public function createBook(array $data, Request $request): LibraryBook
    {
        $user = $request->user();
        $this->authorizeManageLibrary($user);

        $book = LibraryBook::create([
            'school_id' => $data['school_id'] ?? $user?->school_id,
            'title' => $data['title'],
            'author' => $data['author'],
            'publisher' => $data['publisher'] ?? null,
            'publish_year' => $data['publish_year'] ?? null,
            'isbn' => $data['isbn'] ?? null,
            'category' => $data['category'] ?? null,
            'book_type' => $data['book_type'] ?? 'fisik',
            'stock' => $data['stock'] ?? 1,
            'cover_file_id' => $data['cover_file_id'] ?? null,
            'file_id' => $data['file_id'] ?? null,
            'location_shelf' => $data['location_shelf'] ?? null,
        ]);

        $this->logAudit($user?->id, 'library_book.create', 'library_books', $book->id, null, $book->toArray(), $request);

        $book->append('available_stock');

        return $book->fresh(['coverFile', 'file']);
    }

    /**
     * BE-SAR-04: Perbarui informasi buku perpustakaan.
     */
    public function updateBook(string $id, array $data, Request $request): LibraryBook
    {
        $user = $request->user();
        $this->authorizeManageLibrary($user);

        $book = LibraryBook::findOrFail($id);

        if ($user && ! empty($user->school_id) && ! empty($book->school_id) && $book->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Buku perpustakaan berada di luar sekolah penugasan Anda.');
        }

        $oldValues = $book->toArray();

        $book->update($data);

        $this->logAudit($user?->id, 'library_book.update', 'library_books', $book->id, $oldValues, $book->fresh()->toArray(), $request);

        $book->append('available_stock');

        return $book->fresh(['coverFile', 'file']);
    }

    /**
     * BE-SAR-04: Hapus buku dari katalog.
     */
    public function deleteBook(string $id, Request $request): bool
    {
        $user = $request->user();
        $this->authorizeManageLibrary($user);

        $book = LibraryBook::findOrFail($id);

        if ($user && ! empty($user->school_id) && ! empty($book->school_id) && $book->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Buku perpustakaan berada di luar sekolah penugasan Anda.');
        }

        if ($book->active_loans_count > 0) {
            throw new HttpException(422, 'Buku tidak dapat dihapus karena masih ada peminjaman aktif.');
        }

        $oldValues = $book->toArray();
        $book->delete();

        $this->logAudit($user?->id, 'library_book.delete', 'library_books', $id, $oldValues, null, $request);

        return true;
    }

    /**
     * BE-SAR-04: Daftar transaksi peminjaman buku.
     */
    public function listLoans(array $filters, Request $request): LengthAwarePaginator
    {
        $user = $request->user();
        $isLibrarian = $this->isLibrarianOrAdmin($user);

        $query = Loan::with(['book', 'borrower'])->orderBy('created_at', 'desc');

        if ($user && ! empty($user->school_id)) {
            $query->whereHas('book', function ($q) use ($user) {
                $q->whereNull('school_id')->orWhere('school_id', $user->school_id);
            });
        }

        if (! $isLibrarian) {
            $query->where('borrower_id', $user->id);
        } elseif (! empty($filters['borrower_id'])) {
            $query->where('borrower_id', $filters['borrower_id']);
        }

        if (! empty($filters['book_id'])) {
            $query->where('book_id', $filters['book_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * BE-SAR-04: Detail data peminjaman buku.
     */
    public function getLoan(string $id, Request $request): Loan
    {
        $user = $request->user();
        $loan = Loan::with(['book', 'borrower'])->findOrFail($id);

        if ($user && ! empty($user->school_id) && $loan->book && ! empty($loan->book->school_id) && $loan->book->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Transaksi peminjaman berada di luar sekolah penugasan Anda.');
        }

        if (! $this->isLibrarianOrAdmin($user) && $loan->borrower_id !== $user->id) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki akses ke peminjaman ini.');
        }

        return $loan;
    }

    /**
     * BE-SAR-04: Peminjaman buku baru.
     */
    public function createLoan(array $data, Request $request): Loan
    {
        $user = $request->user();
        $borrowerId = $data['borrower_id'] ?? $user->id;

        if ($borrowerId !== $user->id && ! $this->isLibrarianOrAdmin($user)) {
            throw new HttpException(403, 'Akses ditolak: Anda hanya dapat meminjam buku untuk diri sendiri.');
        }

        return DB::transaction(function () use ($data, $user, $borrowerId, $request) {
            $book = LibraryBook::lockForUpdate()->findOrFail($data['book_id']);

            if ($user && ! empty($user->school_id) && ! empty($book->school_id) && $book->school_id !== $user->school_id) {
                throw new HttpException(403, 'Akses ditolak: Buku perpustakaan berada di luar sekolah penugasan Anda.');
            }

            // Cegah peminjaman ganda untuk buku fisik yang sama bila masih aktif dipinjam peminjam
            $existingActive = Loan::where('book_id', $book->id)
                ->where('borrower_id', $borrowerId)
                ->where('status', 'dipinjam')
                ->lockForUpdate()
                ->exists();

            if ($existingActive) {
                throw new HttpException(422, 'Peminjam masih memiliki status peminjaman aktif untuk buku ini.');
            }

            if ($book->book_type === 'fisik' && $book->available_stock <= 0) {
                throw new HttpException(422, 'Stok buku tidak mencukupi untuk dipinjam.');
            }

            $loanDate = $data['loan_date'] ?? Carbon::now()->toDateString();
            $dueDate = $data['due_date'];

            $loan = Loan::create([
                'book_id' => $book->id,
                'borrower_id' => $borrowerId,
                'loan_date' => $loanDate,
                'due_date' => $dueDate,
                'status' => 'dipinjam',
                'fine_amount' => 0,
                'fine_paid' => false,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->logAudit($user?->id, 'loan.create', 'loans', $loan->id, null, $loan->toArray(), $request);

            return $loan->fresh(['book', 'borrower']);
        });
    }

    /**
     * BE-SAR-04: Pengembalian buku perpustakaan + kalkulasi denda otomatis.
     */
    public function returnLoan(string $id, array $data, Request $request): Loan
    {
        $user = $request->user();

        return DB::transaction(function () use ($id, $data, $user, $request) {
            $loan = Loan::with('book')->lockForUpdate()->findOrFail($id);

            if ($user && ! empty($user->school_id) && $loan->book && ! empty($loan->book->school_id) && $loan->book->school_id !== $user->school_id) {
                throw new HttpException(403, 'Akses ditolak: Transaksi peminjaman berada di luar sekolah penugasan Anda.');
            }

            if (! $this->isLibrarianOrAdmin($user) && $loan->borrower_id !== $user->id) {
                throw new HttpException(403, 'Akses ditolak: Anda tidak berwenang mengembalikan pinjaman ini.');
            }

            if ($loan->status === 'dikembalikan') {
                throw new HttpException(422, 'Peminjaman buku ini sudah dikembalikan sebelumnya.');
            }

            $oldValues = $loan->toArray();
            $returnedAt = ! empty($data['returned_at']) ? Carbon::parse($data['returned_at']) : Carbon::now();
            $dueDate = Carbon::parse($loan->due_date);

            $fineAmount = 0;
            if ($returnedAt->greaterThan($dueDate)) {
                $daysLate = $dueDate->diffInDays($returnedAt, false);
                if ($daysLate > 0) {
                    // Tarif denda Rp 1.000 per hari keterlambatan
                    $fineAmount = (int) ($daysLate * 1000);
                }
            }

            $finePaid = isset($data['fine_paid']) ? (bool) $data['fine_paid'] : ($fineAmount === 0);

            $loan->update([
                'returned_at' => $returnedAt->toDateString(),
                'status' => 'dikembalikan',
                'fine_amount' => $fineAmount,
                'fine_paid' => $finePaid,
                'notes' => $data['notes'] ?? $loan->notes,
            ]);

            $this->logAudit($user?->id, 'loan.return', 'loans', $loan->id, $oldValues, $loan->fresh()->toArray(), $request);

            return $loan->fresh(['book', 'borrower']);
        });
    }

    /**
     * BE-SAR-04: Ringkasan metrik dashboard perpustakaan.
     */
    public function getSummary(Request $request): array
    {
        $user = $request->user();
        $bookQuery = LibraryBook::query();
        $loanQuery = Loan::query();

        if ($user && ! empty($user->school_id)) {
            $bookQuery->where(function ($q) use ($user) {
                $q->whereNull('school_id')->orWhere('school_id', $user->school_id);
            });
            $loanQuery->whereHas('book', function ($q) use ($user) {
                $q->whereNull('school_id')->orWhere('school_id', $user->school_id);
            });
        }

        $totalBooks = (clone $bookQuery)->count();
        $totalStock = (int) (clone $bookQuery)->where('book_type', 'fisik')->sum('stock');
        $activeLoans = (clone $loanQuery)->where('status', 'dipinjam')->count();
        $overdueLoans = (clone $loanQuery)
            ->where('status', 'dipinjam')
            ->where('due_date', '<', Carbon::now()->toDateString())
            ->count();
        $unpaidFines = (int) (clone $loanQuery)->where('fine_paid', false)->sum('fine_amount');

        $categories = (clone $bookQuery)
            ->whereNotNull('category')
            ->groupBy('category')
            ->selectRaw('category, count(*) as count')
            ->pluck('count', 'category')
            ->toArray();

        $bookTypes = [
            'fisik' => (clone $bookQuery)->where('book_type', 'fisik')->count(),
            'digital' => (clone $bookQuery)->where('book_type', 'digital')->count(),
        ];

        return [
            'total_books' => $totalBooks,
            'total_stock' => $totalStock,
            'active_loans' => $activeLoans,
            'overdue_loans' => $overdueLoans,
            'total_fines_unpaid' => $unpaidFines,
            'category_counts' => $categories,
            'book_types' => $bookTypes,
        ];
    }

    public function isLibrarianOrAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole('admin') ||
            $user->hasRole('super_admin') ||
            $user->hasRole('librarian') ||
            $user->hasRole('pustakawan') ||
            $user->hasRole('facility_staff') ||
            $user->hasRole('sarpras') ||
            $user->hasPermission('library.book.manage') ||
            $user->hasPermission('library.loan.manage') ||
            $user->hasPermission('facility.asset.manage');
    }

    protected function authorizeManageLibrary(?User $user): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        if (! $this->isLibrarianOrAdmin($user)) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang mengelola perpustakaan.');
        }
    }

    protected function logAudit(?string $actorId, string $action, string $entity, ?string $entityId, ?array $oldValue, ?array $newValue, Request $request): void
    {
        try {
            AuditLog::create([
                'actor_id' => $actorId,
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Audit log failure must not fail transaction
        }
    }
}
