<?php

namespace App\Services\Sar;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Facility;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BookingService
{
    /**
     * BE-SAR-01: Cek ketersediaan fasilitas real-time.
     */
    public function checkAvailability(array $params, ?Request $request = null): array
    {
        $facilityId = $params['facility_id'];
        $facility = Facility::with('room')->findOrFail($facilityId);

        $user = $request?->user();
        if ($user && ! empty($user->school_id) && ! empty($facility->school_id) && $facility->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Fasilitas berada di luar sekolah penugasan Anda.');
        }

        // Validasi status kelayakan penggunaan fasilitas
        $isEligible = $facility->is_active && $facility->status === 'tersedia';

        $query = Booking::where('facility_id', $facilityId)
            ->where('status', 'disetujui')
            ->orderBy('start_at', 'asc');

        $isAvailable = $isEligible;
        $conflicts = [];

        if (! empty($params['start_at']) && ! empty($params['end_at'])) {
            $startAt = Carbon::parse($params['start_at']);
            $endAt = Carbon::parse($params['end_at']);

            $conflicts = (clone $query)->where('start_at', '<', $endAt)
                ->where('end_at', '>', $startAt)
                ->with(['requester:id,username'])
                ->get();

            if (! $conflicts->isEmpty()) {
                $isAvailable = false;
            }
        }

        $dayBookings = [];
        if (! empty($params['date'])) {
            $date = Carbon::parse($params['date']);
            $dayBookings = (clone $query)->whereDate('start_at', $date->toDateString())
                ->with(['requester:id,username'])
                ->get();
        }

        return [
            'facility' => [
                'id' => $facility->id,
                'name' => $facility->name,
                'code' => $facility->code,
                'facility_type' => $facility->facility_type,
                'capacity' => $facility->capacity,
                'requires_approval' => $facility->requires_approval,
                'is_active' => $facility->is_active,
                'status' => $facility->status,
                'is_eligible' => $isEligible,
            ],
            'is_available' => $isAvailable,
            'is_eligible' => $isEligible,
            'eligibility_message' => ! $isEligible
                ? 'Fasilitas saat ini nonaktif atau sedang dalam masa pemeliharaan sehingga tidak dapat dipesan.'
                : null,
            'conflicts' => $conflicts,
            'day_bookings' => $dayBookings,
        ];
    }

    /**
     * BE-SAR-01: Ambil daftar peminjaman fasilitas dengan peranan, multi-tenant scoping, dan privacy masking.
     */
    public function listBookings(array $filters, Request $request): LengthAwarePaginator
    {
        $user = $request->user();
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        $isManager = $this->isManager($user);
        $isCalendarView = ($filters['view'] ?? null) === 'calendar';

        $query = Booking::with(['facility.room', 'requester', 'decider', 'items.facility'])
            ->orderBy('start_at', 'desc');

        // Multi-tenant school isolation
        if (! empty($user->school_id)) {
            $query->where(function ($q) use ($user) {
                $q->where('school_id', $user->school_id)
                    ->orWhereHas('facility', fn ($fq) => $fq->where('school_id', $user->school_id)->orWhereNull('school_id'));
            });
        }

        // Pengguna non-manager hanya melihat miliknya sendiri kecuali pada tampilan kalender
        if (! $isManager && ! $isCalendarView) {
            $query->where('requested_by', $user->id);
        }

        if (! empty($filters['facility_id'])) {
            $query->where('facility_id', $filters['facility_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('start_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('start_at', '<=', $filters['to_date']);
        }

        if (! empty($filters['search'])) {
            $kw = '%'.strtolower($filters['search']).'%';
            $query->where(function ($q) use ($kw) {
                $q->whereRaw('LOWER(purpose) LIKE ?', [$kw])
                    ->orWhereHas('facility', fn ($fq) => $fq->whereRaw('LOWER(name) LIKE ?', [$kw]));
            });
        }

        $paginator = $query->paginate((int) ($filters['per_page'] ?? 15));

        // Privacy masking untuk tampilan kalender oleh pengguna umum (non-manager & bukan pemohon)
        if (! $isManager && $isCalendarView) {
            $paginator->getCollection()->transform(function ($booking) use ($user) {
                if ($booking->requested_by !== $user->id) {
                    $booking->purpose = '[Kegiatan Terjadwal]';
                    $booking->decision_notes = null;
                }

                return $booking;
            });
        }

        return $paginator;
    }

    /**
     * BE-SAR-01: Ambil detail peminjaman dengan validasi akses dan multi-tenant anti-IDOR.
     */
    public function getBooking(string $id, Request $request): Booking
    {
        $user = $request->user();
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        $booking = Booking::with(['facility.room', 'requester', 'decider', 'items.facility'])
            ->findOrFail($id);

        if (! empty($user->school_id)) {
            $schoolMatches = ($booking->school_id === $user->school_id)
                || ($booking->facility?->school_id === $user->school_id)
                || empty($booking->school_id);

            if (! $schoolMatches) {
                throw new HttpException(403, 'Akses ditolak: Peminjaman berada di luar sekolah penugasan Anda.');
            }
        }

        if (! $this->isManager($user) && $booking->requested_by !== $user->id) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki akses melihat detail peminjaman ini.');
        }

        return $booking;
    }

    /**
     * BE-SAR-01: Pengajuan peminjaman fasilitas dengan validasi kelayakan dan deteksi bentrok jadwal.
     */
    public function createBooking(array $data, Request $request): Booking
    {
        $user = $request->user();
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        $facility = Facility::findOrFail($data['facility_id']);

        // Multi-tenant check
        if (! empty($user->school_id) && ! empty($facility->school_id) && $facility->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Fasilitas berada di luar sekolah penugasan Anda.');
        }

        // Validasi kelayakan: Fasilitas nonaktif atau pemeliharaan dilarang dipesan
        if (! $facility->is_active || $facility->status !== 'tersedia') {
            throw new HttpException(422, 'Fasilitas sedang nonaktif atau dalam masa pemeliharaan sehingga tidak dapat dipesan.');
        }

        $startAt = Carbon::parse($data['start_at']);
        $endAt = Carbon::parse($data['end_at']);

        if ($startAt->gte($endAt)) {
            throw new HttpException(422, 'Waktu mulai harus lebih awal dari waktu selesai peminjaman.');
        }

        $initialStatus = $facility->requires_approval ? 'diajukan' : 'disetujui';
        $schoolId = $facility->school_id ?? $user->school_id ?? null;

        $booking = DB::transaction(function () use ($facility, $user, $startAt, $endAt, $data, $initialStatus, $schoolId) {
            // Deteksi bentrok jadwal dengan peminjaman yang telah disetujui (pessimistic lock)
            $conflict = Booking::where('facility_id', $facility->id)
                ->where('status', 'disetujui')
                ->where('start_at', '<', $endAt)
                ->where('end_at', '>', $startAt)
                ->lockForUpdate()
                ->exists();

            if ($conflict) {
                throw new HttpException(422, 'Fasilitas tidak tersedia pada jadwal yang dipilih karena telah disetujui peminjaman lain (bentrok jadwal).');
            }

            $booking = Booking::create([
                'school_id' => $schoolId,
                'facility_id' => $facility->id,
                'requested_by' => $user->id,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'purpose' => $data['purpose'],
                'attendees_count' => $data['attendees_count'] ?? null,
                'status' => $initialStatus,
                'decided_by' => $initialStatus === 'disetujui' ? $user->id : null,
            ]);

            if (! empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    BookingItem::create([
                        'booking_id' => $booking->id,
                        'facility_id' => $item['facility_id'],
                        'quantity' => $item['quantity'] ?? 1,
                        'notes' => $item['notes'] ?? null,
                    ]);
                }
            }

            return $booking;
        });

        $this->logAudit($user->id, 'booking.create', 'bookings', $booking->id, null, $booking->toArray(), $request);

        return $booking->fresh(['facility.room', 'requester', 'items.facility']);
    }

    /**
     * BE-SAR-01: Keputusan persetujuan, penolakan, atau penyelesaian oleh staf Sarpras/Admin.
     */
    public function decideBooking(string $id, array $data, Request $request): Booking
    {
        $user = $request->user();
        $this->authorizeManage($user);

        $oldStatus = null;
        $booking = DB::transaction(function () use ($id, $data, $user, &$oldStatus) {
            // SEC-CONC-01: Pessimistic locking untuk mencegah persetujuan ganda secara bersamaan
            $booking = Booking::lockForUpdate()->findOrFail($id);

            if (! empty($user->school_id)) {
                $schoolMatches = ($booking->school_id === $user->school_id)
                    || ($booking->facility?->school_id === $user->school_id)
                    || empty($booking->school_id);

                if (! $schoolMatches) {
                    throw new HttpException(403, 'Akses ditolak: Peminjaman berada di luar sekolah penugasan Anda.');
                }
            }

            if (in_array($booking->status, ['selesai', 'dibatalkan'])) {
                throw new HttpException(422, "Peminjaman dengan status {$booking->status} tidak dapat diproses lagi.");
            }

            $targetStatus = $data['status'];

            // Jika status sekarang ditolak, melarang pengubahan langsung kecuali diajukan ulang
            if ($booking->status === 'ditolak' && in_array($targetStatus, ['disetujui', 'selesai'])) {
                throw new HttpException(422, 'Peminjaman yang telah ditolak tidak dapat langsung disetujui atau diselesaikan.');
            }

            // Jika menyelesaikan peminjaman, harus berstatus disetujui terlebih dahulu
            if ($targetStatus === 'selesai' && $booking->status !== 'disetujui') {
                throw new HttpException(422, 'Hanya peminjaman dengan status disetujui yang dapat ditandai selesai.');
            }

            // Jika disetujui, pastikan kembali tidak ada konflik jadwal yang mendahului dengan lock
            if ($targetStatus === 'disetujui') {
                $conflict = Booking::where('facility_id', $booking->facility_id)
                    ->where('id', '!=', $booking->id)
                    ->where('status', 'disetujui')
                    ->where('start_at', '<', $booking->end_at)
                    ->where('end_at', '>', $booking->start_at)
                    ->lockForUpdate()
                    ->exists();

                if ($conflict) {
                    throw new HttpException(422, 'Tidak dapat menyetujui peminjaman karena fasilitas telah dibooking jadwal lain pada jam tersebut.');
                }
            }

            $oldStatus = $booking->status;
            $booking->status = $targetStatus;
            $booking->decided_by = $user->id;
            $booking->decision_notes = $data['decision_notes'] ?? $booking->decision_notes;
            $booking->save();

            return $booking;
        });

        $this->logAudit(
            $user->id,
            'booking.decide',
            'bookings',
            $booking->id,
            ['status' => $oldStatus],
            ['status' => $booking->status, 'decision_notes' => $booking->decision_notes, 'decided_by' => $user->id],
            $request
        );

        return $booking->fresh(['facility.room', 'requester', 'decider', 'items.facility']);
    }

    /**
     * BE-SAR-01: Batalkan peminjaman oleh pemohon atau staf sarpras.
     */
    public function cancelBooking(string $id, Request $request): Booking
    {
        $user = $request->user();
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        $booking = Booking::findOrFail($id);

        if (! empty($user->school_id)) {
            $schoolMatches = ($booking->school_id === $user->school_id)
                || ($booking->facility?->school_id === $user->school_id)
                || empty($booking->school_id);

            if (! $schoolMatches) {
                throw new HttpException(403, 'Akses ditolak: Peminjaman berada di luar sekolah penugasan Anda.');
            }
        }

        $isOwner = $booking->requested_by === $user->id;
        $isManager = $this->isManager($user);

        if (! $isOwner && ! $isManager) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak berwenang membatalkan peminjaman ini.');
        }

        if (in_array($booking->status, ['selesai', 'dibatalkan'])) {
            throw new HttpException(422, "Peminjaman dengan status {$booking->status} tidak dapat dibatalkan.");
        }

        $oldStatus = $booking->status;
        $booking->status = 'dibatalkan';
        $booking->save();

        $this->logAudit(
            $user->id,
            'booking.cancel',
            'bookings',
            $booking->id,
            ['status' => $oldStatus],
            ['status' => 'dibatalkan'],
            $request
        );

        return $booking->fresh(['facility.room', 'requester', 'decider', 'items.facility']);
    }

    /**
     * BE-SAR-01: Ringkasan metrik statistik operasional peminjaman fasilitas.
     */
    public function getSummary(Request $request): array
    {
        $user = $request->user();
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        $facQuery = Facility::query();
        $bookQuery = Booking::query();

        if (! empty($user->school_id)) {
            $facQuery->where(function ($q) use ($user) {
                $q->where('school_id', $user->school_id)->orWhereNull('school_id');
            });
            $bookQuery->where(function ($q) use ($user) {
                $q->where('school_id', $user->school_id)
                    ->orWhereHas('facility', fn ($fq) => $fq->where('school_id', $user->school_id)->orWhereNull('school_id'));
            });
        }

        $now = Carbon::now();
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();

        $totalFacilities = (clone $facQuery)->count();
        $activeFacilities = (clone $facQuery)->where('is_active', true)->where('status', 'tersedia')->count();
        $totalBookings = (clone $bookQuery)->count();
        $pendingBookings = (clone $bookQuery)->where('status', 'diajukan')->count();
        $approvedBookings = (clone $bookQuery)->where('status', 'disetujui')->count();
        $rejectedBookings = (clone $bookQuery)->where('status', 'ditolak')->count();
        $completedBookings = (clone $bookQuery)->where('status', 'selesai')->count();
        $cancelledBookings = (clone $bookQuery)->where('status', 'dibatalkan')->count();

        $todayBookings = (clone $bookQuery)
            ->where('status', 'disetujui')
            ->where('start_at', '<=', $todayEnd)
            ->where('end_at', '>=', $todayStart)
            ->count();

        return [
            'total_facilities' => $totalFacilities,
            'active_facilities' => $activeFacilities,
            'total_bookings' => $totalBookings,
            'pending_bookings' => $pendingBookings,
            'approved_bookings' => $approvedBookings,
            'rejected_bookings' => $rejectedBookings,
            'completed_bookings' => $completedBookings,
            'cancelled_bookings' => $cancelledBookings,
            'today_bookings' => $todayBookings,
        ];
    }

    protected function isManager(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole('admin') ||
            $user->hasRole('super_admin') ||
            $user->hasRole('facility_staff') ||
            $user->hasRole('sarpras') ||
            $user->hasPermission('facility.booking.manage') ||
            $user->hasPermission('facility.asset.manage');
    }

    protected function authorizeManage(?User $user): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        if (! $this->isManager($user)) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang mengelola persetujuan booking fasilitas.');
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
            // Audit log must not fail transaction
        }
    }
}
