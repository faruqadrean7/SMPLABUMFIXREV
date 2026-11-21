<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripConsent extends Model
{
    use HasUuids;

    protected $table = 'trip_consents';

    protected $fillable = [
        'trip_participant_id',
        'guardian_id',
        'decision',
        'decided_at',
        'notes',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(TripParticipant::class, 'trip_participant_id');
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'guardian_id');
    }
}
