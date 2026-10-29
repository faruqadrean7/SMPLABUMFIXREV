<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable, SoftDeletes;

    /**
     * Sumber kebenaran tunggal peran yang diizinkan mengakses Shell Admin Back-Office.
     */
    public const ADMIN_SHELL_ROLES = [
        'super_admin',
        'admin_tu',
        'principal',
        'kepala_sekolah',
        'vice_principal',
        'counselor',
        'facility_staff',
        'ppdb_committee',
        'librarian',
        'student_affairs',
        'public_relations',
        'bendahara',
        'treasurer',
        'operator_cms',
        'admin_kurikulum',
        'kurikulum',
    ];

    /**
     * Sumber kebenaran tunggal peran yang wajib mengaktifkan 2FA / TOTP.
     */
    public const MANDATORY_2FA_ROLES = self::ADMIN_SHELL_ROLES;

    /**
     * Pemetaan alias peran lama/kanonis untuk kompatibilitas mundur.
     */
    public const ROLE_ALIASES = [
        'admin' => 'admin_tu',
        'kepala_sekolah' => 'principal',
        'treasurer' => 'bendahara',
        'admin_kurikulum' => 'kurikulum',
        'guru_bk' => 'counselor',
        'sarpras' => 'facility_staff',
        'panitia_ppdb' => 'ppdb_committee',
        'kesiswaan' => 'student_affairs',
        'humas' => 'public_relations',
    ];

    protected $fillable = [
        'school_id',
        'username',
        'email',
        'phone',
        'password_hash',
        'status',
        'must_change_password',
        'last_login_at',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'two_factor_last_step',
    ];

    protected $hidden = [
        'password_hash',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
            'password_hash' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_step' => 'integer',
        ];
    }

    /**
     * Override default password column name for Laravel authentication.
     */
    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getNameAttribute(): string
    {
        return $this->teacher?->full_name
            ?? $this->student?->full_name
            ?? $this->guardian?->full_name
            ?? $this->staff?->full_name
            ?? $this->username;
    }

    // Profil Relasi
    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    public function guardian(): HasOne
    {
        return $this->hasOne(Guardian::class);
    }

    public function staff(): HasOne
    {
        return $this->hasOne(Staff::class);
    }

    public function applicant(): HasOne
    {
        return $this->hasOne(Applicant::class);
    }

    public function applicants(): HasMany
    {
        return $this->hasMany(Applicant::class);
    }

    // RBAC
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
            ->withPivot('scope_class_id');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function hasRole(string|array $roles): bool
    {
        $this->loadMissing('roles');
        $roleCodes = (array) $roles;

        $normalizedCodes = [];
        foreach ($roleCodes as $code) {
            $normalizedCodes[] = $code;
            if (isset(self::ROLE_ALIASES[$code])) {
                $normalizedCodes[] = self::ROLE_ALIASES[$code];
            }
            $flipped = array_search($code, self::ROLE_ALIASES, true);
            if ($flipped !== false) {
                $normalizedCodes[] = $flipped;
            }
        }

        return $this->roles->contains(function ($role) use ($normalizedCodes) {
            return in_array($role->code, $normalizedCodes, true);
        });
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        $this->loadMissing('roles.permissions');

        return $this->roles->flatMap->permissions->contains('code', $permission);
    }

    public function getAllPermissions(): array
    {
        if ($this->hasRole('super_admin')) {
            return Permission::pluck('code')->all();
        }

        return $this->roles->flatMap->permissions->pluck('code')->unique()->values()->all();
    }

    public function flushPermissionCache(): void
    {
        cache()->forget("user_perm_cache:{$this->id}");
    }

    public function getProfileSummaryAttribute(): ?array
    {
        return $this->getProfileSummary();
    }

    public function getProfileSummary(): ?array
    {
        if ($this->relationLoaded('student') && $this->student) {
            $this->student->loadMissing(['classes', 'photoFile']);
            $activeClass = $this->student->classes->first();

            return [
                'type' => 'student',
                'id' => $this->student->id,
                'name' => $this->student->full_name,
                'nis' => $this->student->nis,
                'nisn' => $this->student->nisn,
                'gender' => $this->student->gender,
                'status' => $this->student->status,
                'className' => $activeClass?->name,
                'classId' => $activeClass?->id,
                'photo_file_id' => $this->student->photo_file_id,
                'photo_url' => $this->student->photoFile?->url,
                'photoUrl' => $this->student->photoFile?->url,
            ];
        }

        if ($this->relationLoaded('teacher') && $this->teacher) {
            $this->teacher->loadMissing('photoFile');

            return [
                'type' => 'teacher',
                'id' => $this->teacher->id,
                'name' => $this->teacher->full_name,
                'nip' => $this->teacher->nip,
                'nuptk' => $this->teacher->nuptk,
                'position' => $this->teacher->position,
                'status' => $this->teacher->status,
                'photo_file_id' => $this->teacher->photo_file_id,
                'photo_url' => $this->teacher->photoFile?->url,
                'photoUrl' => $this->teacher->photoFile?->url,
            ];
        }

        if ($this->relationLoaded('guardian') && $this->guardian) {
            $this->guardian->loadMissing(['students.classes', 'students.photoFile']);
            $children = $this->guardian->students ? $this->guardian->students->map(function ($s) {
                $activeClass = $s->classes->first();

                return [
                    'id' => $s->id,
                    'name' => $s->full_name,
                    'nis' => $s->nis,
                    'nisn' => $s->nisn,
                    'gender' => $s->gender,
                    'className' => $activeClass?->name,
                    'classId' => $activeClass?->id,
                    'photo_file_id' => $s->photo_file_id,
                    'photo_url' => $s->photoFile?->url,
                    'photoUrl' => $s->photoFile?->url,
                ];
            })->all() : [];

            return [
                'type' => 'guardian',
                'id' => $this->guardian->id,
                'name' => $this->guardian->full_name,
                'phone' => $this->guardian->phone,
                'children' => $children,
            ];
        }

        if ($this->relationLoaded('staff') && $this->staff) {
            return [
                'type' => 'staff',
                'name' => $this->staff->full_name,
                'position' => $this->staff->position,
                'unit' => $this->staff->unit,
            ];
        }

        if ($this->relationLoaded('applicant') && $this->applicant) {
            return [
                'type' => 'applicant',
                'name' => $this->applicant->full_name,
                'registration_no' => $this->applicant->registration_no,
                'status' => $this->applicant->status,
            ];
        }

        return null;
    }
}
