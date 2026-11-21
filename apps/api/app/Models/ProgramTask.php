<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgramTask extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'program_tasks';

    protected $fillable = [
        'school_id',
        'program_type',
        'title',
        'instructions',
        'due_at',
        'file_id',
        'meta',
        'created_by',
        'class_id',
        'target_scope',
        'assigned_student_id',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function assignedStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'assigned_student_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ProgramTaskSubmission::class, 'task_id');
    }
}
