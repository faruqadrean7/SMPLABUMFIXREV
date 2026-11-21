<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabLoan extends Model
{
    use HasUuids;

    protected $table = 'lab_loans';

    protected $fillable = [
        'equipment_id',
        'borrower_id',
        'loan_date',
        'due_date',
        'quantity',
        'returned_at',
        'status',
        'purpose',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'loan_date' => 'date',
        'due_date' => 'date',
        'returned_at' => 'date',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(LabEquipment::class, 'equipment_id');
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrower_id');
    }
}
