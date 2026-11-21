<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EarlyWarning extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'early_warnings';

    protected $fillable = [
        'student_id',
        'rule_code',
        'severity',
        'data',
        'status',
        'handled_by',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
