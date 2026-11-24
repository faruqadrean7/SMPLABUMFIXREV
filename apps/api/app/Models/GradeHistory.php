<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat perubahan nilai — append only, tidak pernah menimpa data lama.
 */
class GradeHistory extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'grade_id',
        'student_id',
        'component_id',
        'period_id',
        'old_value',
        'new_value',
        'old_source',
        'new_source',
        'change_source',
        'changed_by',
        'note',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_value' => 'decimal:2',
            'new_value' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
