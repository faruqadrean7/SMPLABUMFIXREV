<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\File;
use App\Models\SiteSetting;
use App\Models\StaffAttendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StaffAttendanceService
{
    /**
     * Default koordinat sekolah (Laboratorium UM / Malang) jika belum ada di SiteSetting.
     */
    protected const DEFAULT_SCHOOL_LAT = -7.9625000;

    protected const DEFAULT_SCHOOL_LNG = 112.6175000;

    protected const DEFAULT_RADIUS_METERS = 150;

    protected const DEFAULT_LATE_TIME = '07:15:00';

    /**
     * BE-ACD-05: Mendapatkan status presensi staf hari ini.
     */
    public function getTodayAttendance(Request $request): ?StaffAttendance
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $today = Carbon::now()->format('Y-m-d');

        return StaffAttendance::with(['photoFile', 'user'])
            ->where('user_id', $currentUser->id)
            ->whereDate('att_date', $today)
            ->first();
    }

    /**
     * BE-ACD-05: Check-in staf (opsional GPS radius & selfie).
     */
    public function checkIn(array $data, Request $request): StaffAttendance
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $today = Carbon::now()->format('Y-m-d');
        $now = Carbon::now();

        // Cek double check-in
        $existing = StaffAttendance::where('user_id', $currentUser->id)
            ->whereDate('att_date', $today)
            ->first();

        if ($existing && $existing->check_in !== null) {
            throw new HttpException(422, 'Anda sudah melakukan check-in presensi hari ini.');
        }

        // Validasi GPS Radius bila koordinat disediakan
        if (isset($data['lat'], $data['lng'])) {
            $this->validateGpsRadius((float) $data['lat'], (float) $data['lng']);
        }

        // Unggah foto selfie jika ada
        $photoFileId = $data['photo_file_id'] ?? null;
        if ($request->hasFile('photo_file')) {
            $uploaded = $request->file('photo_file');
            $storagePath = $uploaded->store("staff-attendance/{$currentUser->id}", 'local');
            $checksum = hash_file('sha256', $uploaded->getRealPath());

            $fileRecord = File::create([
                'owner_id' => $currentUser->id,
                'owner_type' => 'User',
                'bucket' => 'staff-attendance',
                'object_key' => $storagePath,
                'mime_type' => $uploaded->getMimeType() ?: 'image/jpeg',
                'size_bytes' => $uploaded->getSize(),
                'visibility' => 'privat',
                'checksum' => $checksum,
            ]);

            $photoFileId = $fileRecord->id;
        }

        // Tentukan status kehadiran (hadir / terlambat)
        $lateThreshold = $this->getLateTimeThreshold($today);
        $status = $now->gt($lateThreshold) ? 'terlambat' : 'hadir';

        return DB::transaction(function () use ($existing, $currentUser, $today, $now, $data, $photoFileId, $status, $request) {
            $attendance = $existing ?? new StaffAttendance([
                'user_id' => $currentUser->id,
                'att_date' => $today,
            ]);

            $attendance->check_in = $now;
            $attendance->lat = $data['lat'] ?? $attendance->lat;
            $attendance->lng = $data['lng'] ?? $attendance->lng;
            $attendance->photo_file_id = $photoFileId ?? $attendance->photo_file_id;
            $attendance->status = $status;
            if (! empty($data['notes'])) {
                $attendance->notes = $data['notes'];
            }
            $attendance->save();

            $this->logAudit(
                $currentUser->id,
                'staff_attendance.check_in',
                'StaffAttendance',
                $attendance->id,
                null,
                $attendance->toArray(),
                $request
            );

            return $attendance->load(['photoFile', 'user']);
        });
    }

    /**
     * BE-ACD-05: Check-out staf.
     */
    public function checkOut(array $data, Request $request): StaffAttendance
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $today = Carbon::now()->format('Y-m-d');
        $now = Carbon::now();

        $attendance = StaffAttendance::where('user_id', $currentUser->id)
            ->whereDate('att_date', $today)
            ->first();

        if (! $attendance || $attendance->check_in === null) {
            throw new HttpException(422, 'Anda belum melakukan check-in hari ini.');
        }

        if ($attendance->check_out !== null) {
            throw new HttpException(422, 'Anda sudah melakukan check-out hari ini.');
        }

        // Validasi GPS Radius bila koordinat disediakan pada check-out
        if (isset($data['lat'], $data['lng'])) {
            $this->validateGpsRadius((float) $data['lat'], (float) $data['lng']);
        }

        return DB::transaction(function () use ($attendance, $now, $data, $currentUser, $request) {
            $oldData = $attendance->toArray();

            $attendance->check_out = $now;
            if (! empty($data['notes'])) {
                $attendance->notes = trim(($attendance->notes ? $attendance->notes.' | ' : '').$data['notes']);
            }
            $attendance->save();

            $this->logAudit(
                $currentUser->id,
                'staff_attendance.check_out',
                'StaffAttendance',
                $attendance->id,
                $oldData,
                $attendance->toArray(),
                $request
            );

            return $attendance->load(['photoFile', 'user']);
        });
    }

    /**
     * BE-ACD-05: List riwayat presensi staf dengan filter dan otorisasi.
     */
    public function listAttendances(array $filters, Request $request): LengthAwarePaginator
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $isAdmin = $this->checkIsAdmin($currentUser);

        $query = StaffAttendance::with(['user.teacher', 'user.staff', 'photoFile'])->orderBy('att_date', 'desc');

        if (! $isAdmin) {
            // Guru/staf biasa hanya bisa melihat miliknya sendiri
            $query->where('user_id', $currentUser->id);
        } else {
            if (! empty($filters['user_id'])) {
                $query->where('user_id', $filters['user_id']);
            }
        }

        if (! empty($filters['q'])) {
            $keyword = trim($filters['q']);
            $query->whereHas('user', function ($uq) use ($keyword) {
                $uq->where('username', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhereHas('teacher', fn ($tq) => $tq->where('full_name', 'like', "%{$keyword}%")->orWhere('nip', 'like', "%{$keyword}%"))
                    ->orWhereHas('staff', fn ($sq) => $sq->where('full_name', 'like', "%{$keyword}%")->orWhere('position', 'like', "%{$keyword}%"));
            });
        }

        if (! empty($filters['date_from'])) {
            $query->where('att_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('att_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);

        return $query->paginate($perPage);
    }

    /**
     * BE-ACD-05: Rekap dan persentase kehadiran staf untuk dashboard.
     */
    public function getSummary(array $filters, Request $request): array
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $isAdmin = $this->checkIsAdmin($currentUser);

        // IDOR Check: jika bukan admin tapi meminta summary user lain
        if (! $isAdmin && ! empty($filters['user_id']) && $filters['user_id'] !== $currentUser->id) {
            throw new HttpException(403, 'Anda tidak memiliki hak akses melihat rekap presensi staf lain.');
        }

        $query = StaffAttendance::query();

        if (! $isAdmin) {
            $query->where('user_id', $currentUser->id);
        } else {
            if (! empty($filters['user_id'])) {
                $query->where('user_id', $filters['user_id']);
            }
        }

        if (! empty($filters['month'])) {
            // Format YYYY-MM
            $query->where('att_date', 'like', $filters['month'].'%');
        } elseif (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            if (! empty($filters['date_from'])) {
                $query->where('att_date', '>=', $filters['date_from']);
            }
            if (! empty($filters['date_to'])) {
                $query->where('att_date', '<=', $filters['date_to']);
            }
        } else {
            // Default bulan berjalan
            $currentMonth = Carbon::now()->format('Y-m');
            $query->where('att_date', 'like', $currentMonth.'%');
        }

        $records = $query->get();
        $totalDays = $records->count();

        $hadir = $records->where('status', 'hadir')->count();
        $terlambat = $records->where('status', 'terlambat')->count();
        $izin = $records->where('status', 'izin')->count();
        $sakit = $records->where('status', 'sakit')->count();
        $alfa = $records->where('status', 'alfa')->count();

        $totalPresent = $hadir + $terlambat;
        $attendancePercentage = $totalDays > 0 ? round(($totalPresent / $totalDays) * 100, 1) : 0.0;

        return [
            'total_recorded_days' => $totalDays,
            'summary' => [
                'hadir' => $hadir,
                'terlambat' => $terlambat,
                'izin' => $izin,
                'sakit' => $sakit,
                'alfa' => $alfa,
            ],
            'total_present' => $totalPresent,
            'attendance_percentage' => $attendancePercentage,
            'filters' => $filters,
        ];
    }

    /**
     * Hitung jarak dua titik koordinat bumi dengan formula Haversine (hasil dalam meter).
     */
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // Radius bumi dalam meter

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Validasi apakah koordinat berada di dalam radius sekolah.
     */
    protected function validateGpsRadius(float $lat, float $lng): void
    {
        $gpsSetting = SiteSetting::where('setting_key', 'staff_attendance_gps')->first();
        $schoolLat = self::DEFAULT_SCHOOL_LAT;
        $schoolLng = self::DEFAULT_SCHOOL_LNG;
        $maxRadius = self::DEFAULT_RADIUS_METERS;

        if ($gpsSetting && is_array($gpsSetting->setting_value)) {
            $schoolLat = (float) ($gpsSetting->setting_value['latitude'] ?? $schoolLat);
            $schoolLng = (float) ($gpsSetting->setting_value['longitude'] ?? $schoolLng);
            $maxRadius = (int) ($gpsSetting->setting_value['radius_meters'] ?? $maxRadius);
        }

        $distance = $this->calculateDistance($lat, $lng, $schoolLat, $schoolLng);

        if ($distance > $maxRadius) {
            $roundedDist = round($distance);
            throw new HttpException(
                422,
                "Lokasi Anda berada di luar radius presensi sekolah ({$roundedDist}m dari batas maksimal {$maxRadius}m)."
            );
        }
    }

    /**
     * Waktu batas toleransi keterlambatan hari ini.
     */
    protected function getLateTimeThreshold(string $date): Carbon
    {
        $gpsSetting = SiteSetting::where('setting_key', 'staff_attendance_gps')->first();
        $lateTime = self::DEFAULT_LATE_TIME;

        if ($gpsSetting && is_array($gpsSetting->setting_value) && ! empty($gpsSetting->setting_value['late_time'])) {
            $lateTime = $gpsSetting->setting_value['late_time'];
        }

        return Carbon::parse("{$date} {$lateTime}");
    }

    /**
     * BE-ACD-05: Manual record presensi staf oleh Admin.
     */
    public function manualStore(array $data, Request $request): StaffAttendance
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        if (! $this->checkIsAdmin($currentUser)) {
            throw new HttpException(403, 'Hanya administrator yang berhak mencatat presensi staf secara manual.');
        }

        $userId = $data['user_id'];
        $attDate = $data['att_date'];

        $existing = StaffAttendance::where('user_id', $userId)
            ->whereDate('att_date', $attDate)
            ->first();

        if ($existing) {
            throw new HttpException(422, 'Catatan presensi untuk pegawai tersebut pada tanggal ini sudah ada. Gunakan fitur koreksi bila ingin mengubah.');
        }

        return DB::transaction(function () use ($data, $userId, $attDate, $currentUser, $request) {
            $attendance = StaffAttendance::create([
                'user_id' => $userId,
                'att_date' => $attDate,
                'status' => $data['status'],
                'check_in' => $data['check_in'] ?? null,
                'check_out' => $data['check_out'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->logAudit(
                $currentUser->id,
                'staff_attendance.manual_recorded',
                'StaffAttendance',
                $attendance->id,
                null,
                $attendance->toArray(),
                $request
            );

            return $attendance->load(['user.teacher', 'user.staff', 'photoFile']);
        });
    }

    /**
     * BE-ACD-05: Koreksi presensi staf oleh Admin berwenang.
     */
    public function correct(string $id, array $data, Request $request): StaffAttendance
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        if (! $this->checkIsAdmin($currentUser)) {
            throw new HttpException(403, 'Hanya administrator yang berhak melakukan koreksi presensi staf.');
        }

        $attendance = StaffAttendance::findOrFail($id);

        return DB::transaction(function () use ($attendance, $data, $currentUser, $request) {
            $oldData = $attendance->toArray();

            $attendance->status = $data['status'];
            if (array_key_exists('check_in', $data)) {
                $attendance->check_in = $data['check_in'];
            }
            if (array_key_exists('check_out', $data)) {
                $attendance->check_out = $data['check_out'];
            }

            $reason = trim($data['reason']);
            $correctionNote = "[Koreksi oleh {$currentUser->username}]: {$reason}";
            $attendance->notes = trim(($attendance->notes ? $attendance->notes.' | ' : '').$correctionNote);
            $attendance->save();

            $this->logAudit(
                $currentUser->id,
                'staff_attendance.corrected',
                'StaffAttendance',
                $attendance->id,
                $oldData,
                $attendance->toArray(),
                $request
            );

            return $attendance->load(['user.teacher', 'user.staff', 'photoFile']);
        });
    }

    /**
     * BE-ACD-05: Mengambil konfigurasi GPS dan jam batas presensi staf.
     */
    public function getSettings(): array
    {
        $gpsSetting = SiteSetting::where('setting_key', 'staff_attendance_gps')->first();

        $latitude = self::DEFAULT_SCHOOL_LAT;
        $longitude = self::DEFAULT_SCHOOL_LNG;
        $radiusMeters = self::DEFAULT_RADIUS_METERS;
        $lateTime = self::DEFAULT_LATE_TIME;

        if ($gpsSetting && is_array($gpsSetting->setting_value)) {
            $latitude = (float) ($gpsSetting->setting_value['latitude'] ?? $latitude);
            $longitude = (float) ($gpsSetting->setting_value['longitude'] ?? $longitude);
            $radiusMeters = (int) ($gpsSetting->setting_value['radius_meters'] ?? $radiusMeters);
            $lateTime = (string) ($gpsSetting->setting_value['late_time'] ?? $lateTime);
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius_meters' => $radiusMeters,
            'late_time' => $lateTime,
        ];
    }

    /**
     * BE-ACD-05: Memperbarui konfigurasi GPS dan jam batas presensi staf (Admin).
     */
    public function updateSettings(array $data, Request $request): array
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        if (! $this->checkIsAdmin($currentUser)) {
            throw new HttpException(403, 'Hanya administrator yang berhak mengubah konfigurasi presensi staf.');
        }

        $setting = SiteSetting::updateOrCreate(
            ['setting_key' => 'staff_attendance_gps'],
            [
                'setting_value' => [
                    'latitude' => (float) $data['latitude'],
                    'longitude' => (float) $data['longitude'],
                    'radius_meters' => (int) $data['radius_meters'],
                    'late_time' => $data['late_time'],
                ],
            ]
        );

        $this->logAudit(
            $currentUser->id,
            'staff_attendance.settings_updated',
            'SiteSetting',
            $setting->id,
            null,
            $setting->setting_value,
            $request
        );

        return $this->getSettings();
    }

    /**
     * Cek apakah user adalah administrator atau kepala sekolah.
     */
    protected function checkIsAdmin(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->hasRole('admin_tu')
            || $user->hasRole('principal')
            || $user->hasPermission('academic.schedule.manage')
            || $user->hasPermission('master.teacher.manage')
            || $user->roles()->where('code', 'like', 'admin%')->exists();
    }

    /**
     * Catat log audit.
     */
    protected function logAudit(
        ?string $actorId,
        string $action,
        string $entity,
        ?string $entityId,
        ?array $oldValue,
        ?array $newValue,
        Request $request
    ): void {
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
            report($e);
        }
    }
}
