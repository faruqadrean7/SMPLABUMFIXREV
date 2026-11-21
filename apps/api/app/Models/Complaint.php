<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Complaint extends Model
{
    use HasUuids;

    protected $table = 'complaints';

    protected $fillable = [
        'school_id',
        'reporter_user_id',
        'ticket_code',
        'category',
        'content',
        'is_anonymous',
        'status',
        'assigned_to',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ComplaintEvent::class, 'complaint_id')->orderBy('created_at', 'asc');
    }

    /**
     * BE-GUEST-04: Lampiran foto pengaduan di bucket privat.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(File::class, 'owner_id')->where('owner_type', 'complaint');
    }

    // Compatibility accessors
    public function getTicketNumberAttribute(): string
    {
        return (string) $this->ticket_code;
    }

    public function setTicketNumberAttribute($value): void
    {
        $this->attributes['ticket_code'] = $value;
    }

    public function getDescriptionAttribute(): string
    {
        return (string) $this->content;
    }

    public function setDescriptionAttribute($value): void
    {
        $this->attributes['content'] = $value;
    }
}
