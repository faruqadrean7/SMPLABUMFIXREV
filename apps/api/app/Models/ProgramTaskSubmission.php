<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgramTaskSubmission extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'program_task_submissions';

    protected $fillable = [
        'task_id',
        'student_id',
        'file_id',
        'link',
        'notes',
        'status',
        'score',
        'feedback',
        'submitted_at',
        'graded_by',
        'graded_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
            'score' => 'float',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProgramTask::class, 'task_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id');
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
