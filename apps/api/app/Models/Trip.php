<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trip extends Model
{
    use HasUuids;

    protected $table = 'trips';

    protected $fillable = [
        'school_id',
        'title',
        'destination',
        'depart_date',
        'return_date',
        'route',
        'guide_file_id',
        'description',
        'cost_notes',
        'status',
    ];

    protected $casts = [
        'depart_date' => 'date',
        'return_date' => 'date',
        'route' => 'array',
    ];

    protected $appends = [
        'participants_count',
        'consents_count',
    ];

    public function guideFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'guide_file_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TripParticipant::class, 'trip_id');
    }

    public function getParticipantsCountAttribute(): int
    {
        return (int) $this->participants()->count();
    }

    public function getConsentsCountAttribute(): int
    {
        return (int) $this->participants()->whereHas('consent', function ($q) {
            $q->where('decision', 'setuju');
        })->count();
    }
}
