<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Extracurricular extends Model
{
    use HasUuids;

    protected $table = 'extracurriculars';

    protected $fillable = [
        'school_id',
        'coach_id',
        'name',
        'category',
        'description',
        'quota',
        'schedule_day',
        'schedule_time',
        'is_active',
    ];

    protected $casts = [
        'quota' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'active_members_count',
        'available_quota',
    ];

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'coach_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ExtracurricularMember::class, 'extracurricular_id');
    }

    public function journals(): HasMany
    {
        return $this->hasMany(ExtracurricularJournal::class, 'extracurricular_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ActivityDocument::class, 'extracurricular_id');
    }

    public function getActiveMembersCountAttribute(): int
    {
        return (int) $this->members()->where('status', 'aktif')->count();
    }

    public function getAvailableQuotaAttribute(): int
    {
        return max(0, $this->quota - $this->active_members_count);
    }
}
