<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TracerSurvey extends Model
{
    use HasUuids;

    protected $fillable = [
        'school_id',
        'title',
        'description',
        'questions',
        'target_graduation_year',
        'start_date',
        'end_date',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'target_graduation_year' => 'integer',
        'questions' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'responses_count',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(TracerResponse::class, 'survey_id');
    }

    public function getResponsesCountAttribute(): int
    {
        return $this->responses()->count();
    }
}
