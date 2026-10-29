<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * Mengisi kolom `nik_hash` (HMAC-SHA256 deterministik) setiap kali `nik_enc`
 * berubah, agar deteksi duplikat & pencarian berdasarkan NIK dimungkinkan
 * tanpa menyimpan NIK dalam bentuk plaintext. Dipasangkan dengan partial-unique
 * index pada migrasi constraint (PostgreSQL).
 */
trait HasNikBlindIndex
{
    public static function bootHasNikBlindIndex(): void
    {
        static::saving(function (Model $model) {
            try {
                if ($model->isDirty('nik_enc') && ! empty($model->nik_enc)) {
                    $model->nik_hash = static::hashNik((string) $model->nik_enc);
                }
            } catch (\Throwable $e) {
                // Jangan hentikan proses simpan bila hashing gagal.
            }
        });
    }

    public static function hashNik(string $nik): string
    {
        $key = (string) (env('NIK_HASH_KEY') ?: config('app.key'));

        return hash_hmac('sha256', trim($nik), $key);
    }
}
