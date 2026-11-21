<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderDispatch extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'reminder_dispatches';

    protected $fillable = [
        'school_id',
        'event_type',
        'event_id',
        'recipient_id',
        'milestone',
        'channel',
        'status',
        'claim_token',
        'claim_expires_at',
        'dispatched_at',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'dispatched_at' => 'datetime',
        'claim_expires_at' => 'datetime',
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function scopeForEvent(Builder $query, string $eventType, string $eventId, ?string $milestone = null): Builder
    {
        $query->where('event_type', $eventType)
            ->where('event_id', $eventId);

        if ($milestone !== null) {
            $query->where('milestone', $milestone);
        }

        return $query;
    }

    public function scopeForRecipient(Builder $query, string $recipientId): Builder
    {
        return $query->where('recipient_id', $recipientId);
    }
}
