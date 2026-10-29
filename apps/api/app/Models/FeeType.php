<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeType extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $table = 'fee_types';

    protected $fillable = [
        'school_id',
        'code',
        'name',
        'category',
        'default_amount',
        'is_recurring',
        'is_active',
        'description',
    ];

    protected $casts = [
        'default_amount' => 'integer',
        'is_recurring' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'fee_type_id');
    }
}
