<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitionEntry extends Model
{
    use HasUuids;

    protected $table = 'competition_entries';

    protected $fillable = [
        'competition_id',
        'student_id',
        'coach_teacher_id',
        'status',
        'result',
        'achievement_id',
        'notes',
    ];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class, 'competition_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'coach_teacher_id');
    }

    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class, 'achievement_id');
    }
}
