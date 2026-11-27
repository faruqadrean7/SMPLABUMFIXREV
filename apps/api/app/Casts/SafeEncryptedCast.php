<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Enkripsi at-rest backward-compatible untuk kolom sensitif (BK & Pengaduan).
 *
 * - Saat membaca (`get`): mencoba dekripsi; jika baris lama masih plaintext,
 *   kembalikan nilai plaintext apa adanya tanpa DecryptException (0 data hilang).
 * - Saat menulis (`set`): mengenkripsi plaintext dan mencegah double-encryption.
 */
class SafeEncryptedCast implements CastsAttributes
{
    public static function isEncrypted(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $raw = (string) $value;

        try {
            return Crypt::decryptString($raw);
        } catch (DecryptException) {
            // Backward-compatibility: baris data lama sebelum migrasi masih plaintext
            return $raw;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = (string) $value;
        if ($raw === '') {
            return '';
        }

        if (self::isEncrypted($raw)) {
            return $raw;
        }

        return Crypt::encryptString($raw);
    }
}
