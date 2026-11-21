<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class File extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'school_id',
        'owner_id',
        'owner_type',
        'bucket',
        'object_key',
        'mime_type',
        'size_bytes',
        'visibility',
        'checksum',
    ];

    /**
     * Atribut tambahan yang otomatis disertakan dalam serialisasi array/JSON.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'url',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    /**
     * INT-03: URL publik untuk berkas dengan visibilitas publik.
     *
     * Aturan keamanan & integritas:
     * 1. Berkas privat SELALU mengembalikan null (harus melalui signed URL / authorized route).
     * 2. Berkas tanpa object_key mengembalikan null.
     * 3. Jika object_key sudah berupa URL absolut (http/https), kembalikan langsung.
     * 4. Jika terjadi kegagalan pada driver storage, tangani secara aman dan kembalikan null.
     */
    public function getUrlAttribute(): ?string
    {
        if (empty($this->object_key)) {
            return null;
        }

        // Keamanan: File privat TIDAK BOLEH mengekspos URL publik langsung
        if ($this->visibility !== 'publik' && $this->visibility !== 'public') {
            return null;
        }

        if (Str::startsWith($this->object_key, ['http://', 'https://'])) {
            return $this->object_key;
        }

        try {
            return Storage::disk('public')->url($this->object_key);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
