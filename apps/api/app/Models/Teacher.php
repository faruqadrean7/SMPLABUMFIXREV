<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'school_id',
        'user_id',
        'nip',
        'nuptk',
        'full_name',
        'position',
        'status',
        'photo_file_id',
    ];

    protected $appends = [
        'total_teaching_hours',
    ];

    public function getTotalTeachingHoursAttribute(): int
    {
        if ($this->relationLoaded('teachingAssignments')) {
            return (int) $this->teachingAssignments->sum('hours_per_week');
        }

        return (int) $this->teachingAssignments()->sum('hours_per_week');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photoFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'photo_file_id');
    }

    public function homeroomClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'homeroom_teacher_id');
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function journals(): HasMany
    {
        return $this->hasMany(Journal::class);
    }

    public function salarySlips(): HasMany
    {
        return $this->hasMany(TeacherSalarySlip::class)->orderBy('period_year', 'desc')->orderBy('period_month', 'desc');
    }
}
