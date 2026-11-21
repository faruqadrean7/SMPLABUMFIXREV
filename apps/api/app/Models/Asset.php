<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use HasUuids;

    protected $fillable = [
        'school_id',
        'room_id',
        'asset_code',
        'name',
        'category',
        'brand',
        'model',
        'serial_number',
        'asset_condition',
        'acquired_at',
        'notes',
    ];

    protected $casts = [
        'acquired_at' => 'date',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function auditItems(): HasMany
    {
        return $this->hasMany(AssetAuditItem::class, 'asset_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'asset_id');
    }
}
