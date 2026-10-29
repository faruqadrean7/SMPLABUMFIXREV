<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\AuditLog;
use App\Models\PPDBWave;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PPDBAccountService
{
    /**
     * BE-PPDB-02: Register applicant account & generate OTP.
     */
    public function register(array $data, Request $request): array
    {
        $email = strtolower(trim($data['email']));
        $nik = isset($data['nik']) ? trim($data['nik']) : null;

        // 1. Cek NIK jika disediakan: 1 NIK = 1 pendaftaran aktif
        if ($nik) {
            $nikCheck = $this->checkNikAvailability($nik);
            if (! $nikCheck['is_available']) {
                throw new HttpException(400, 'NIK ini sudah terdaftar dalam pendaftaran PPDB yang aktif');
            }
        }

        // 2. Transaksi pembuatan User dengan status pending
        $user = DB::transaction(function () use ($data, $email) {
            // Ambil atau buat role guest
            $roleGuest = Role::firstOrCreate(
                ['code' => 'guest'],
                [
                    'name' => 'Tamu / Calon Siswa',
                    'is_system' => true,
                ]
            );

            // Buat username unik
            $emailPrefix = explode('@', $email)[0];
            $baseUsername = Str::slug($emailPrefix, '_');
            $username = $baseUsername.'_'.random_int(1000, 9999);

            $user = User::create([
                'school_id' => $data['school_id'] ?? null,
                'username' => $username,
                'email' => $email,
                'phone' => $data['phone'],
                'password_hash' => Hash::make($data['password']),
                'status' => 'pending',
                'must_change_password' => false,
            ]);

            $user->roles()->attach($roleGuest->id);

            return $user;
        });

        // 3. Generate 6-digit OTP
        $otp = sprintf('%06d', random_int(100000, 999999));
        $cachePayload = [
            'otp' => $otp,
            'user_id' => $user->id,
            'email' => $email,
            'phone' => $data['phone'],
            'full_name' => $data['full_name'],
            'nik' => $nik,
            'created_at' => Carbon::now()->timestamp,
        ];

        // Simpan OTP di Cache selama 15 menit (900 detik)
        Cache::put('ppdb_otp:'.$email, $cachePayload, 900);
        // Cooldown 60 detik untuk permintaan kirim ulang
        Cache::put('ppdb_otp_cooldown:'.$email, true, 60);

        // 4. Log audit
        $this->logAudit(
            $user->id,
            'ppdb_applicant.registered',
            'User',
            $user->id,
            null,
            [
                'email' => $email,
                'phone' => $data['phone'],
                'full_name' => $data['full_name'],
            ],
            $request
        );

        $response = [
            'user_id' => $user->id,
            'email' => $user->email,
            'phone' => $user->phone,
            'full_name' => $data['full_name'],
            'status' => 'pending',
            'expires_in' => 900,
            'message' => 'Registrasi berhasil. Kode OTP verifikasi telah dikirimkan ke email/nomor Anda.',
        ];

        // Sertakan OTP pada environment local/testing untuk memudahkan verifikasi
        if (app()->environment('local', 'testing')) {
            $response['otp'] = $otp;
        }

        return $response;
    }

    /**
     * BE-PPDB-02: Verify OTP and activate user account.
     */
    public function verifyOtp(string $email, string $otp, Request $request): array
    {
        $normalizedEmail = strtolower(trim($email));
        $cacheKey = 'ppdb_otp:'.$normalizedEmail;
        $cached = Cache::get($cacheKey);

        if (! $cached || ! isset($cached['otp']) || $cached['otp'] !== $otp) {
            throw new HttpException(400, 'Kode OTP tidak valid atau sudah kedaluwarsa');
        }

        /** @var User $user */
        $user = User::findOrFail($cached['user_id']);

        // Aktifkan akun
        $user->status = 'active';
        $user->save();

        // Hapus OTP dari cache
        Cache::forget($cacheKey);
        Cache::forget('ppdb_otp_cooldown:'.$normalizedEmail);

        // Terbitkan token otentikasi Sanctum
        $token = $user->createToken('ppdb_applicant_token')->plainTextToken;

        // Log audit
        $this->logAudit(
            $user->id,
            'ppdb_applicant.otp_verified',
            'User',
            $user->id,
            ['status' => 'pending'],
            ['status' => 'active'],
            $request
        );

        $user->loadMissing('roles.permissions');

        return [
            'accessToken' => $token,
            'tokenType' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'roles' => $user->roles->pluck('code')->all(),
                'registered_name' => $cached['full_name'] ?? null,
                'nik' => $cached['nik'] ?? null,
            ],
            'message' => 'Verifikasi akun berhasil. Akun Anda telah aktif.',
        ];
    }

    /**
     * BE-PPDB-02: Resend OTP with cooldown.
     */
    public function resendOtp(string $email, Request $request): array
    {
        $normalizedEmail = strtolower(trim($email));
        $cooldownKey = 'ppdb_otp_cooldown:'.$normalizedEmail;

        if (Cache::has($cooldownKey)) {
            throw new HttpException(429, 'Silakan tunggu 60 detik sebelum meminta kode OTP kembali');
        }

        $user = User::where('email', $normalizedEmail)->first();
        if (! $user) {
            throw new HttpException(404, 'Akun dengan alamat email ini tidak ditemukan');
        }

        if ($user->status === 'active') {
            throw new HttpException(400, 'Akun ini sudah aktif dan terverifikasi');
        }

        // Ambil data cache lama jika ada
        $oldCache = Cache::get('ppdb_otp:'.$normalizedEmail, []);

        // Generate kode OTP baru
        $newOtp = sprintf('%06d', random_int(100000, 999999));
        $cachePayload = array_merge($oldCache, [
            'otp' => $newOtp,
            'user_id' => $user->id,
            'email' => $normalizedEmail,
            'created_at' => Carbon::now()->timestamp,
        ]);

        Cache::put('ppdb_otp:'.$normalizedEmail, $cachePayload, 900);
        Cache::put($cooldownKey, true, 60);

        // Log audit
        $this->logAudit(
            $user->id,
            'ppdb_applicant.otp_resent',
            'User',
            $user->id,
            null,
            ['email' => $normalizedEmail],
            $request
        );

        $response = [
            'email' => $user->email,
            'expires_in' => 900,
            'message' => 'Kode OTP baru berhasil dikirimkan.',
        ];

        if (app()->environment('local', 'testing')) {
            $response['otp'] = $newOtp;
        }

        return $response;
    }

    /**
     * BE-PPDB-02: Check 1 NIK = 1 pendaftaran aktif.
     */
    public function checkNikAvailability(string $nik, ?string $waveId = null): array
    {
        $query = Applicant::query()
            ->whereNotIn('status', ['rejected', 'cancelled']);

        if ($waveId) {
            $query->where('wave_id', $waveId);
        } else {
            // Ambil semua gelombang yang berstatus aktif atau rentang tanggal berjalan
            $activeWaveIds = PPDBWave::where('status', 'aktif')
                ->where('open_at', '<=', Carbon::now())
                ->where('close_at', '>=', Carbon::now())
                ->pluck('id');

            if ($activeWaveIds->isNotEmpty()) {
                $query->whereIn('wave_id', $activeWaveIds);
            }
        }

        $applicants = $query->get();

        $hasActiveRegistration = false;
        $matchedApplicant = null;

        foreach ($applicants as $applicant) {
            if ($applicant->nik_enc === $nik) {
                $hasActiveRegistration = true;
                $matchedApplicant = $applicant;
                break;
            }
        }

        return [
            'nik' => $nik,
            'is_available' => ! $hasActiveRegistration,
            'has_active_registration' => $hasActiveRegistration,
            'existing_registration_no' => $matchedApplicant ? $matchedApplicant->registration_no : null,
            'status' => $matchedApplicant ? $matchedApplicant->status : null,
        ];
    }

    /**
     * BE-PPDB-02: Applicant login by identifier (email, phone, registration_no) and password.
     */
    public function login(string $identifier, string $password, Request $request): array
    {
        /** @var AuthService $authService */
        $authService = app(AuthService::class);
        $authResult = $authService->login([
            'identifier' => $identifier,
            'password' => $password,
            'platform' => 'web_ppdb',
        ], $request);

        /** @var User $user */
        $user = User::findOrFail($authResult['user']['id']);
        $applicantProfile = $this->getApplicantProfile($user);

        return [
            'accessToken' => $authResult['tokens']['accessToken'],
            'tokenType' => $authResult['tokens']['tokenType'] ?? 'Bearer',
            'expiresIn' => $authResult['tokens']['expiresIn'] ?? 900,
            'refreshToken' => $authResult['tokens']['refreshToken'] ?? null,
            'user' => $authResult['user'],
            'applicant' => $applicantProfile,
        ];
    }

    /**
     * BE-PPDB-02: Get current applicant profile & applications.
     */
    public function getApplicantProfile(User $user): array
    {
        $user->loadMissing([
            'roles.permissions',
            'applicants.wave',
            'applicants.track.requirements',
            'applicants.parents',
            'applicants.documents',
        ]);

        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'roles' => $user->roles->pluck('code')->all(),
            'applications' => $user->applicants->map(function (Applicant $applicant) {
                return [
                    'id' => $applicant->id,
                    'registration_no' => $applicant->registration_no,
                    'full_name' => $applicant->full_name,
                    'birth_date' => $applicant->birth_date?->format('Y-m-d'),
                    'status' => $applicant->status,
                    'score' => $applicant->score,
                    'rank' => $applicant->rank,
                    'decision' => $applicant->decision,
                    'step_data' => $applicant->step_data,
                    'wave' => $applicant->wave ? [
                        'id' => $applicant->wave->id,
                        'name' => $applicant->wave->name,
                        'status' => $applicant->wave->status,
                    ] : null,
                    'track' => $applicant->track ? [
                        'id' => $applicant->track->id,
                        'code' => $applicant->track->code,
                        'name' => $applicant->track->name,
                        'requirements' => $applicant->track->requirements->map(function ($r) {
                            return [
                                'id' => $r->id,
                                'document_type' => $r->document_type,
                                'is_required' => (bool) $r->is_required,
                                'description' => $r->description,
                            ];
                        }),
                    ] : null,
                    'parents' => $applicant->parents->map(function ($p) {
                        return [
                            'id' => $p->id,
                            'parent_type' => $p->parent_type,
                            'name' => $p->name,
                            'occupation' => $p->occupation,
                            'income_range' => $p->income_range,
                            'phone' => $p->phone,
                        ];
                    }),
                    'documents' => $applicant->documents->map(function ($d) {
                        return [
                            'id' => $d->id,
                            'document_type' => $d->document_type,
                            'file_id' => $d->file_id,
                            'status' => $d->status,
                            'reject_reason' => $d->reject_reason,
                        ];
                    }),
                ];
            })->all(),
        ];
    }

    /**
     * Helper to write an audit log entry.
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
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
