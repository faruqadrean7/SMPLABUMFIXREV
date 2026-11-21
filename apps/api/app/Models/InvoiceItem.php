<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'invoice_items';

    protected $fillable = [
        'invoice_id',
        'fee_type_id',
        'description',
        'amount',
        'discount',
    ];

    protected $casts = [
        'amount' => 'integer',
        'discount' => 'integer',
    ];

    protected $appends = [
        'net_amount',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class, 'fee_type_id');
    }

    public function getNetAmountAttribute(): int
    {
        return max(0, (int) $this->amount - (int) $this->discount);
    }
}
