<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintEvent extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'complaint_events';

    protected $fillable = [
        'complaint_id',
        'actor_id',
        'event_type',
        'response_text',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class, 'complaint_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    // Compatibility accessors
    public function getNotesAttribute(): string
    {
        return (string) $this->response_text;
    }

    public function setNotesAttribute($value): void
    {
        $this->attributes['response_text'] = $value;
    }

    public function getActionAttribute(): string
    {
        return (string) $this->event_type;
    }

    public function setActionAttribute($value): void
    {
        $this->attributes['event_type'] = $value;
    }
}
