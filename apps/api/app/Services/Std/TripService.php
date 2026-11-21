<?php

namespace App\Services\Std;

use App\Models\AuditLog;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Trip;
use App\Models\TripConsent;
use App\Models\TripParticipant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TripService
{
    // =========================================================================
    // 1. MASTER STUDY TRIP / OUTING CLASS
    // =========================================================================

    public function listTrips(array $filters): LengthAwarePaginator
    {
        $query = Trip::with('guideFile')->orderBy('depart_date', 'desc');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $kw = '%'.strtolower($filters['search']).'%';
            $query->where(function ($q) use ($kw) {
                $q->whereRaw('LOWER(title) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(destination) LIKE ?', [$kw]);
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function getTrip(string $id): Trip
    {
        return Trip::with([
            'guideFile',
            'participants.student',
            'participants.consent.guardian',
        ])->findOrFail($id);
    }

    public function createTrip(array $data, Request $request): Trip
    {
        $user = $request->user();
        $this->authorizeTeacherOrStaff($user);

        $trip = Trip::create([
            'school_id' => $data['school_id'] ?? null,
            'title' => $data['title'],
            'destination' => $data['destination'] ?? null,
            'depart_date' => $data['depart_date'],
            'return_date' => $data['return_date'] ?? null,
            'route' => $data['route'] ?? null,
            'guide_file_id' => $data['guide_file_id'] ?? null,
            'description' => $data['description'] ?? null,
            'cost_notes' => $data['cost_notes'] ?? null,
            'status' => $data['status'] ?? 'direncanakan',
        ]);

        $this->logAudit($user?->id, 'trip.create', 'trips', $trip->id, null, $trip->toArray(), $request);

        return $trip->fresh('guideFile');
    }

    public function updateTrip(string $id, array $data, Request $request): Trip
    {
        $user = $request->user();
        $this->authorizeTeacherOrStaff($user);

        $trip = Trip::findOrFail($id);
        $oldValues = $trip->toArray();

        $trip->update($data);

        $this->logAudit($user?->id, 'trip.update', 'trips', $trip->id, $oldValues, $trip->fresh()->toArray(), $request);

        return $trip->fresh('guideFile');
    }

    public function deleteTrip(string $id, Request $request): bool
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $trip = Trip::findOrFail($id);

        if (in_array($trip->status, ['berlangsung', 'selesai'])) {
            throw new HttpException(422, 'Perjalanan yang sedang berlangsung atau sudah selesai tidak dapat dihapus.');
        }

        $oldValues = $trip->toArray();
        $trip->delete();

        $this->logAudit($user?->id, 'trip.delete', 'trips', $id, $oldValues, null, $request);

        return true;
    }

    // =========================================================================
    // 2. DAFTAR PESERTA PERJALANAN
    // =========================================================================

    public function listParticipants(string $tripId): LengthAwarePaginator
    {
        return TripParticipant::with(['student', 'consent.guardian'])
            ->where('trip_id', $tripId)
            ->orderBy('created_at', 'asc')
            ->paginate(30);
    }

    public function registerParticipant(array $data, Request $request): TripParticipant
    {
        $user = $request->user();
        $this->authorizeTeacherOrStaff($user);

        $trip = Trip::findOrFail($data['trip_id']);
        $student = Student::findOrFail($data['student_id']);

        $existing = TripParticipant::where('trip_id', $trip->id)
            ->where('student_id', $student->id)
            ->first();

        if ($existing) {
            throw new HttpException(422, 'Siswa ini sudah terdaftar sebagai peserta perjalanan.');
        }

        $participant = TripParticipant::create([
            'trip_id' => $trip->id,
            'student_id' => $student->id,
            'status' => 'terdaftar',
            'notes' => $data['notes'] ?? null,
        ]);

        $this->logAudit($user?->id, 'trip_participant.register', 'trip_participants', $participant->id, null, $participant->toArray(), $request);

        return $participant->fresh(['student', 'consent']);
    }

    public function removeParticipant(string $participantId, Request $request): bool
    {
        $user = $request->user();
        $this->authorizeTeacherOrStaff($user);

        $participant = TripParticipant::findOrFail($participantId);
        $oldValues = $participant->toArray();

        $participant->delete();

        $this->logAudit($user?->id, 'trip_participant.remove', 'trip_participants', $participantId, $oldValues, null, $request);

        return true;
    }

    // =========================================================================
    // 3. PERSETUJUAN ORANG TUA / IZIN DIGITAL (CONSENTS)
    // =========================================================================

    public function listConsents(string $tripId, Request $request): Collection
    {
        $user = $request->user();
        $trip = Trip::findOrFail($tripId);

        $query = TripConsent::with(['participant.student', 'guardian'])
            ->whereHas('participant', function ($q) use ($trip) {
                $q->where('trip_id', $trip->id);
            });

        // Anti-IDOR: Jika orang tua, hanya melihat persetujuan untuk anaknya
        if ($this->isParent($user)) {
            $guardian = Guardian::where('user_id', $user->id)->first();
            $query->where('guardian_id', $guardian?->id ?? '00000000-0000-0000-0000-000000000000');
        } elseif ($this->isStudent($user)) {
            $student = Student::where('user_id', $user->id)->first();
            $query->whereHas('participant', function ($q) use ($student) {
                $q->where('student_id', $student?->id ?? '00000000-0000-0000-0000-000000000000');
            });
        }

        return $query->get();
    }

    public function submitConsent(string $tripId, array $data, Request $request): TripConsent
    {
        $user = $request->user();
        $trip = Trip::findOrFail($tripId);

        $guardian = Guardian::where('user_id', $user->id)->first();
        if (! $guardian && ! $this->isStaffOrAdmin($user)) {
            throw new HttpException(403, 'Akses ditolak: Hanya orang tua / wali siswa yang berwenang memberikan persetujuan izin digital.');
        }

        // Tentukan student_id
        $studentId = $data['student_id'] ?? null;
        if ($guardian) {
            if ($studentId) {
                $isChild = $guardian->students()->where('students.id', $studentId)->exists();
                if (! $isChild) {
                    throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki akses izin untuk siswa ini.');
                }
            } else {
                $child = $guardian->students()->whereHas('studentPoints', function () {})->first()
                    ?? $guardian->students()->first();

                if (! $child) {
                    throw new HttpException(422, 'Tidak ada data siswa yang terhubung dengan akun wali ini.');
                }
                $studentId = $child->id;
            }
        }

        // Cari participant
        $participant = TripParticipant::where('trip_id', $trip->id)
            ->where('student_id', $studentId)
            ->first();

        if (! $participant) {
            throw new HttpException(422, 'Siswa belum terdaftar dalam daftar peserta perjalanan ini.');
        }

        $consent = TripConsent::updateOrCreate(
            ['trip_participant_id' => $participant->id],
            [
                'guardian_id' => $guardian?->id ?? Guardian::firstOrCreate(['user_id' => $user->id], ['full_name' => $user->username])->id,
                'decision' => $data['decision'],
                'decided_at' => Carbon::now(),
                'notes' => $data['notes'] ?? null,
            ]
        );

        $this->logAudit($user?->id, 'trip_consent.submit', 'trip_consents', $consent->id, null, $consent->toArray(), $request);

        return $consent->fresh(['participant.student', 'guardian']);
    }

    // =========================================================================
    // OTORISASI & AUDIT
    // =========================================================================

    public function isParent(?User $user): bool
    {
        return $user?->hasRole('parent') || $user?->hasRole('guardian');
    }

    public function isStudent(?User $user): bool
    {
        return $user?->hasRole('student') ?? false;
    }

    public function isStaffOrAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole('admin') ||
            $user->hasRole('super_admin') ||
            $user->hasRole('kesiswaan') ||
            $user->hasRole('student_affairs');
    }

    public function isTeacherOrStaff(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->isStaffOrAdmin($user) ||
            $user->hasRole('teacher') ||
            $user->hasRole('homeroom_teacher');
    }

    protected function authorizeTeacherOrStaff(?User $user): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        if (! $this->isTeacherOrStaff($user)) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang mengelola data kegiatan study trip.');
        }
    }

    protected function authorizeStaffOrAdmin(?User $user): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        if (! $this->isStaffOrAdmin($user)) {
            throw new HttpException(403, 'Akses ditolak: Hanya staf kesiswaan atau admin yang berwenang.');
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
            // Jangan gagalkan transaksi bila audit gagal
        }
    }
}
