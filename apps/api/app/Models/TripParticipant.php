<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TripParticipant extends Model
{
    use HasUuids;

    protected $table = 'trip_participants';

    protected $fillable = [
        'trip_id',
        'student_id',
        'status',
        'notes',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'trip_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function consent(): HasOne
    {
        return $this->hasOne(TripConsent::class, 'trip_participant_id');
    }
}
