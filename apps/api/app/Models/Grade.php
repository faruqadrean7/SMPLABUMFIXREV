<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grade extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $fillable = [
        'student_id',
        'component_id',
        'period_id',
        'exam_id',
        'value',
        'manual_value',
        'auto_value',
        'auto_source',
        'source',
        'entered_by',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'manual_value' => 'decimal:2',
            'auto_value' => 'decimal:2',
            'locked_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Riwayat perubahan nilai (append only).
     */
    public function histories(): HasMany
    {
        return $this->hasMany(GradeHistory::class)->orderByDesc('created_at');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(GradeComponent::class, 'component_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(GradePeriod::class, 'period_id');
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function enterer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
