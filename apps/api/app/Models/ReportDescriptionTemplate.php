<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportDescriptionTemplate extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'report_description_templates';

    protected $fillable = [
        'subject_id',
        'level',
        'kktp_threshold',
        'min_score',
        'max_score',
        'template_text',
    ];

    protected function casts(): array
    {
        return [
            'kktp_threshold' => 'float',
            'min_score' => 'float',
            'max_score' => 'float',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
