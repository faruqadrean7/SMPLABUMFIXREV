<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamItemAnalysis extends Model
{
    use HasFactory, HasUuids;

    const CREATED_AT = 'calculated_at';

    const UPDATED_AT = null;

    protected $table = 'exam_item_analysis';

    protected $fillable = [
        'exam_id',
        'question_id',
        'difficulty_index',
        'discrimination_index',
        'distractor_stats',
    ];

    protected function casts(): array
    {
        return [
            'difficulty_index' => 'decimal:3',
            'discrimination_index' => 'decimal:3',
            'distractor_stats' => 'array',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
