<?php

namespace App\Services;

use App\Models\AdminTrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TrustedDeviceService
{
    /**
     * Masa berlaku perangkat tepercaya (hari). Default 30, dapat diatur via ADMIN_TRUSTED_DEVICE_DAYS.
     */
    public function trustDays(): int
    {
        $days = (int) env('ADMIN_TRUSTED_DEVICE_DAYS', 30);

        return $days > 0 ? $days : 30;
    }

    /**
     * Terbitkan token perangkat tepercaya baru dan simpan hash-nya.
     * Mengembalikan token mentah (hanya ditampilkan sekali ke klien).
     *
     * @return array{token: string, expires_at: string}
     */
    public function issue(User $user, ?Request $request = null): array
    {
        $raw = Str::random(64);
        $expiresAt = Carbon::now()->addDays($this->trustDays());

        AdminTrustedDevice::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $raw),
            'label' => $request?->userAgent() ? Str::limit((string) $request->userAgent(), 115, '') : null,
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'last_used_at' => Carbon::now(),
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $raw,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * Validasi token perangkat tepercaya untuk user tertentu.
     * Jika valid, perbarui last_used_at dan kembalikan true.
     */
    public function isTrusted(User $user, ?string $rawToken, ?Request $request = null): bool
    {
        if (! $rawToken || strlen($rawToken) < 32) {
            return false;
        }

        $device = AdminTrustedDevice::query()
            ->where('user_id', $user->id)
            ->where('token_hash', hash('sha256', $rawToken))
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (! $device) {
            return false;
        }

        $device->forceFill([
            'last_used_at' => Carbon::now(),
            'ip' => $request?->ip() ?? $device->ip,
        ])->save();

        return true;
    }

    /**
     * Cabut semua perangkat tepercaya milik user (mis. saat ganti password / "logout semua perangkat").
     */
    public function revokeAll(User $user): int
    {
        return AdminTrustedDevice::where('user_id', $user->id)->delete();
    }
}
