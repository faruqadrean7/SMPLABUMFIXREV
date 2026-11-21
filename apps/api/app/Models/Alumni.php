<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Alumni extends Model
{
    use HasUuids;

    protected $table = 'alumni';

    protected $fillable = [
        'student_id',
        'graduation_year',
        'current_status',
        'next_institution',
        'study_program',
        'occupation',
        'company_name',
        'contact',
        'phone',
        'email',
        'linkedin_url',
        'address',
        'is_public',
    ];

    protected $casts = [
        'graduation_year' => 'integer',
        'is_public' => 'boolean',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function tracerResponses(): HasMany
    {
        return $this->hasMany(TracerResponse::class, 'alumni_id');
    }
}
