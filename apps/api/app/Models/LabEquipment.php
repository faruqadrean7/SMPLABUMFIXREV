<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabEquipment extends Model
{
    use HasUuids;

    protected $table = 'lab_equipment';

    protected $fillable = [
        'school_id',
        'room_id',
        'code',
        'name',
        'category',
        'stock',
        'condition',
        'notes',
    ];

    protected $casts = [
        'stock' => 'integer',
    ];

    protected $appends = [
        'available_stock',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(LabLoan::class, 'equipment_id');
    }

    /**
     * Jumlah unit alat lab yang sedang aktif dipinjam.
     */
    public function getActiveLoansQuantityAttribute(): int
    {
        return (int) $this->loans()->where('status', 'dipinjam')->sum('quantity');
    }

    /**
     * Jumlah unit alat lab yang siap dipinjam.
     */
    public function getAvailableStockAttribute(): int
    {
        return max(0, $this->stock - $this->active_loans_quantity);
    }
}
