<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    use HasUuids;

    protected $fillable = [
        'school_id',
        'room_id',
        'code',
        'name',
        'location',
        'description',
        'facility_type',
        'capacity',
        'requires_approval',
        'is_active',
        'status',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'requires_approval' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'facility_id');
    }

    public function bookingItems(): HasMany
    {
        return $this->hasMany(BookingItem::class, 'facility_id');
    }
}
