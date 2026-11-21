<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CounselingCase extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'counseling_cases';

    protected $fillable = [
        'student_id',
        'counselor_id',
        'category',
        'status',
        'summary_enc',
    ];

    protected $casts = [
        'summary_enc' => 'encrypted',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function counselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CounselingSession::class, 'case_id');
    }
}
