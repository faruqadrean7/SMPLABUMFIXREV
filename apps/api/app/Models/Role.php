<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Role extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'school_id',
        'code',
        'name',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * T2B-CONC-02: Pastikan system role tersedia secara aman terhadap race condition PostgreSQL.
     *
     * Menggunakan insertOrIgnore (ON CONFLICT DO NOTHING di PostgreSQL) sehingga
     * konflik concurrent insert pada UNIQUE(code) tidak melempar SQLSTATE[23505]
     * maupun membatalkan transaksi PostgreSQL aktif (SQLSTATE[25P02]).
     * Role yang sudah ada beserta nama dan permission-nya tetap dipertahankan.
     */
    public static function ensureSystemRole(string $code, string $name, ?string $schoolId = null): self
    {
        $existing = static::where('code', $code)->first();
        if ($existing !== null) {
            return $existing;
        }

        $now = now();

        DB::table('roles')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'school_id' => $schoolId,
            'code' => $code,
            'name' => $name,
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return static::where('code', $code)->firstOrFail();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions', 'role_id', 'permission_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_id')
            ->withPivot('scope_class_id');
    }
}
