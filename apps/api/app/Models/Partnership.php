<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partnership extends Model
{
    use HasUuids;

    protected $fillable = [
        'school_id',
        'partner_name',
        'mou_number',
        'partner_type',
        'contact_person',
        'phone',
        'email',
        'address',
        'scope',
        'valid_from',
        'valid_until',
        'status',
        'notes',
        'mou_file_id',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    protected $appends = [
        'is_expired',
        'days_remaining',
    ];

    public function mouFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'mou_file_id');
    }

    public function exchangePrograms(): HasMany
    {
        return $this->hasMany(ExchangeProgram::class, 'partnership_id');
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->valid_until ? Carbon::parse($this->valid_until)->endOfDay()->isPast() : false;
    }

    public function getDaysRemainingAttribute(): ?int
    {
        if (! $this->valid_until) {
            return null;
        }

        $now = Carbon::now()->startOfDay();
        $target = Carbon::parse($this->valid_until)->startOfDay();

        return (int) $now->diffInDays($target, false);
    }
}
