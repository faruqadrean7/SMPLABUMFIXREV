<?php

namespace App\Services\Hum;

use App\Models\AuditLog;
use App\Models\ExchangeParticipant;
use App\Models\ExchangeProgram;
use App\Models\Partnership;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PartnershipService
{
    // =========================================================================
    // 1. MASTER KERJA SAMA & DOKUMEN MOU
    // =========================================================================

    public function listPartnerships(array $filters, ?Request $request = null): LengthAwarePaginator
    {
        $query = Partnership::with('mouFile')
            ->orderBy('valid_until', 'asc');

        $user = $request?->user();
        if ($user && ! empty($user->school_id)) {
            $query->where(function ($q) use ($user) {
                $q->where('school_id', $user->school_id)->orWhereNull('school_id');
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['partner_type'])) {
            $query->where('partner_type', $filters['partner_type']);
        }

        if (! empty($filters['expiring_within_days'])) {
            $days = (int) $filters['expiring_within_days'];
            $now = Carbon::now()->startOfDay();
            $future = Carbon::now()->addDays($days)->endOfDay();

            $query->whereBetween('valid_until', [$now, $future])
                ->where('status', 'aktif');
        }

        if (! empty($filters['search'])) {
            $kw = '%'.strtolower($filters['search']).'%';
            $query->where(function ($q) use ($kw) {
                $q->whereRaw('LOWER(partner_name) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(mou_number) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(contact_person) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(scope) LIKE ?', [$kw]);
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function getPartnership(string $id, ?Request $request = null): Partnership
    {
        $partnership = Partnership::with([
            'mouFile',
            'exchangePrograms.participants.student',
        ])->findOrFail($id);

        $user = $request?->user();
        if ($user && ! empty($user->school_id) && ! empty($partnership->school_id) && $partnership->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Kemitraan berada di luar sekolah penugasan Anda.');
        }

        return $partnership;
    }

    public function createPartnership(array $data, Request $request): Partnership
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        // Otomatis tentukan status bila tanggal berakhir sudah lewat
        $validUntil = Carbon::parse($data['valid_until'])->endOfDay();
        $status = $data['status'] ?? ($validUntil->isPast() ? 'kedaluwarsa' : 'aktif');

        $partnership = Partnership::create([
            'school_id' => $data['school_id'] ?? $user?->school_id ?? null,
            'partner_name' => $data['partner_name'],
            'mou_number' => $data['mou_number'] ?? null,
            'partner_type' => $data['partner_type'] ?? 'instansi',
            'contact_person' => $data['contact_person'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'scope' => $data['scope'],
            'valid_from' => $data['valid_from'],
            'valid_until' => $data['valid_until'],
            'status' => $status,
            'notes' => $data['notes'] ?? null,
            'mou_file_id' => $data['mou_file_id'] ?? null,
        ]);

        $this->logAudit($user?->id, 'partnership.create', 'partnerships', $partnership->id, null, $partnership->toArray(), $request);

        return $partnership->fresh('mouFile');
    }

    public function updatePartnership(string $id, array $data, Request $request): Partnership
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $partnership = Partnership::findOrFail($id);

        if ($user && ! empty($user->school_id) && ! empty($partnership->school_id) && $partnership->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Kemitraan berada di luar sekolah penugasan Anda.');
        }

        $oldValues = $partnership->toArray();

        $partnership->update($data);

        $this->logAudit($user?->id, 'partnership.update', 'partnerships', $partnership->id, $oldValues, $partnership->fresh()->toArray(), $request);

        return $partnership->fresh('mouFile');
    }

    public function deletePartnership(string $id, Request $request): bool
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $partnership = Partnership::findOrFail($id);

        if ($user && ! empty($user->school_id) && ! empty($partnership->school_id) && $partnership->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Kemitraan berada di luar sekolah penugasan Anda.');
        }

        if ($partnership->exchangePrograms()->whereIn('status', ['dibuka', 'berjalan'])->exists()) {
            throw new HttpException(422, 'Tidak dapat menghapus kemitraan yang memiliki program pertukaran aktif atau sedang berjalan.');
        }

        $oldValues = $partnership->toArray();
        $partnership->delete();

        $this->logAudit($user?->id, 'partnership.delete', 'partnerships', $id, $oldValues, null, $request);

        return true;
    }

    /**
     * Dapatkan daftar kemitraan yang akan kedaluwarsa dalam X hari (untuk notifikasi/scheduler).
     */
    public function getExpiringMoUs(int $withinDays = 30): Collection
    {
        $now = Carbon::now()->startOfDay();
        $future = Carbon::now()->addDays($withinDays)->endOfDay();

        return Partnership::where('status', 'aktif')
            ->whereBetween('valid_until', [$now, $future])
            ->orderBy('valid_until', 'asc')
            ->get();
    }

    // =========================================================================
    // 2. PROGRAM PERTUKARAN PELAJAR (EXCHANGE PROGRAMS)
    // =========================================================================

    public function listExchangePrograms(array $filters): LengthAwarePaginator
    {
        $query = ExchangeProgram::with(['partnership', 'pic'])
            ->orderBy('start_date', 'desc');

        if (! empty($filters['partnership_id'])) {
            $query->where('partnership_id', $filters['partnership_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['search'])) {
            $kw = '%'.strtolower($filters['search']).'%';
            $query->where(function ($q) use ($kw) {
                $q->whereRaw('LOWER(name) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(destination_institution) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(destination_country) LIKE ?', [$kw]);
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function getExchangeProgram(string $id): ExchangeProgram
    {
        return ExchangeProgram::with([
            'partnership.mouFile',
            'pic',
            'participants.student',
        ])->findOrFail($id);
    }

    public function createExchangeProgram(array $data, Request $request): ExchangeProgram
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $partnership = Partnership::findOrFail($data['partnership_id']);

        // Validasi: MoU mitra tidak boleh kedaluwarsa / dihentikan
        if ($partnership->status === 'kedaluwarsa' || $partnership->status === 'dihentikan') {
            throw new HttpException(422, 'Tidak dapat membuat program pertukaran dengan mitra yang masa berlaku MoU-nya telah berakhir atau dihentikan.');
        }

        $program = ExchangeProgram::create([
            'partnership_id' => $partnership->id,
            'name' => $data['name'],
            'type' => $data['type'] ?? 'pelajar',
            'destination_country' => $data['destination_country'] ?? null,
            'destination_institution' => $data['destination_institution'] ?? null,
            'quota' => $data['quota'] ?? null,
            'description' => $data['description'] ?? null,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'pic_teacher_id' => $data['pic_teacher_id'] ?? null,
            'status' => $data['status'] ?? 'dibuka',
        ]);

        $this->logAudit($user?->id, 'exchange_program.create', 'exchange_programs', $program->id, null, $program->toArray(), $request);

        return $program->fresh(['partnership', 'pic']);
    }

    public function updateExchangeProgram(string $id, array $data, Request $request): ExchangeProgram
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $program = ExchangeProgram::findOrFail($id);
        $oldValues = $program->toArray();

        if (! empty($data['partnership_id']) && $data['partnership_id'] !== $program->partnership_id) {
            $partnership = Partnership::findOrFail($data['partnership_id']);
            if ($partnership->status === 'kedaluwarsa' || $partnership->status === 'dihentikan') {
                throw new HttpException(422, 'Kemitraan yang dipilih berstatus tidak aktif atau kedaluwarsa.');
            }
        }

        $program->update($data);

        $this->logAudit($user?->id, 'exchange_program.update', 'exchange_programs', $program->id, $oldValues, $program->fresh()->toArray(), $request);

        return $program->fresh(['partnership', 'pic']);
    }

    public function deleteExchangeProgram(string $id, Request $request): bool
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $program = ExchangeProgram::findOrFail($id);

        if ($program->status === 'berjalan') {
            throw new HttpException(422, 'Program pertukaran yang sedang berjalan tidak dapat dihapus.');
        }

        $oldValues = $program->toArray();
        $program->delete();

        $this->logAudit($user?->id, 'exchange_program.delete', 'exchange_programs', $id, $oldValues, null, $request);

        return true;
    }

    // =========================================================================
    // 3. PESERTA PERTUKARAN PELAJAR (PARTICIPANTS)
    // =========================================================================

    public function listParticipants(string $programId): Collection
    {
        ExchangeProgram::findOrFail($programId);

        return ExchangeParticipant::with('student')
            ->where('program_id', $programId)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function registerParticipant(string $programId, array $data, Request $request): ExchangeParticipant
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $program = ExchangeProgram::findOrFail($programId);
        $student = Student::findOrFail($data['student_id']);

        if ($program->status === 'selesai' || $program->status === 'dibatalkan') {
            throw new HttpException(422, 'Tidak dapat mendaftarkan siswa pada program pertukaran yang sudah selesai atau dibatalkan.');
        }

        // Cek kuota
        if ($program->quota !== null && $program->participants()->count() >= $program->quota) {
            throw new HttpException(422, 'Kuota peserta program pertukaran ini sudah penuh.');
        }

        // Cek duplikasi
        $existing = ExchangeParticipant::where('program_id', $program->id)
            ->where('student_id', $student->id)
            ->first();

        if ($existing) {
            throw new HttpException(422, 'Siswa ini sudah terdaftar dalam program pertukaran tersebut.');
        }

        $participant = ExchangeParticipant::create([
            'program_id' => $program->id,
            'student_id' => $student->id,
            'status' => $data['status'] ?? 'terdaftar',
            'passport_number' => $data['passport_number'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->logAudit($user?->id, 'exchange_participant.register', 'exchange_participants', $participant->id, null, $participant->toArray(), $request);

        return $participant->fresh('student');
    }

    public function updateParticipant(string $participantId, array $data, Request $request): ExchangeParticipant
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $participant = ExchangeParticipant::findOrFail($participantId);
        $oldValues = $participant->toArray();

        $participant->update($data);

        $this->logAudit($user?->id, 'exchange_participant.update', 'exchange_participants', $participant->id, $oldValues, $participant->fresh()->toArray(), $request);

        return $participant->fresh('student');
    }

    public function removeParticipant(string $participantId, Request $request): bool
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $participant = ExchangeParticipant::findOrFail($participantId);
        $oldValues = $participant->toArray();

        $participant->delete();

        $this->logAudit($user?->id, 'exchange_participant.remove', 'exchange_participants', $participantId, $oldValues, null, $request);

        return true;
    }

    // =========================================================================
    // 4. OTORISASI & AUDIT
    // =========================================================================

    public function isStaffOrAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole('admin') ||
            $user->hasRole('super_admin') ||
            $user->hasRole('admin_tu') ||
            $user->hasRole('principal') ||
            $user->hasRole('humas') ||
            $user->hasRole('public_relations');
    }

    protected function authorizeStaffOrAdmin(?User $user): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        if (! $this->isStaffOrAdmin($user)) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang mengelola data kemitraan dan pertukaran pelajar.');
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
            // Abaikan kegagalan audit agar tidak mengganggu transaksi
        }
    }
}
