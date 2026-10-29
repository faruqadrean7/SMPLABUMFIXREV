<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasNikBlindIndex;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use Auditable, HasFactory, HasNikBlindIndex, HasUuids, SoftDeletes;

    protected $fillable = [
        'school_id',
        'user_id',
        'nis',
        'nisn',
        'nik_enc',
        'nik_hash',
        'full_name',
        'gender',
        'birth_place',
        'birth_date',
        'religion',
        'address',
        'photo_file_id',
        'status',
        'entry_date',
        'graduated_at',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'entry_date' => 'date',
            'graduated_at' => 'date',
            'nik_enc' => 'encrypted', // Enkripsi at-rest otomatis via Laravel crypt
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photoFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'photo_file_id');
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'student_guardians', 'student_id', 'guardian_id')
            ->withPivot(['relationship', 'is_primary'])
            ->withTimestamps();
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'class_students', 'student_id', 'class_id')
            ->withPivot(['joined_at', 'left_at'])
            ->withTimestamps();
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(StudentStatusHistory::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function characterNotes(): HasMany
    {
        return $this->hasMany(CharacterNote::class);
    }

    public function progressReports(): HasMany
    {
        return $this->hasMany(ProgressReport::class);
    }

    public function reportCards(): HasMany
    {
        return $this->hasMany(ReportCard::class);
    }

    public function studentPoints(): HasMany
    {
        return $this->hasMany(StudentPoint::class);
    }

    public function counselingCases(): HasMany
    {
        return $this->hasMany(CounselingCase::class);
    }

    public function earlyWarnings(): HasMany
    {
        return $this->hasMany(EarlyWarning::class);
    }

    public function alumni(): HasOne
    {
        return $this->hasOne(Alumni::class);
    }

    public function getNameAttribute(): string
    {
        return $this->full_name ?? '';
    }
}
