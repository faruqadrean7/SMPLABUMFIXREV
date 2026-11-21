<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentSubmission extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'assignment_id',
        'student_id',
        'file_id',
        'notes',
        'score',
        'feedback',
        'submitted_at',
    ];

    protected $appends = ['is_late', 'status', 'submission_timing'];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'submitted_at' => 'datetime',
        ];
    }

    public function getIsLateAttribute(): bool
    {
        if (! $this->submitted_at) {
            return false;
        }

        $dueAt = $this->relationLoaded('assignment') ? $this->assignment?->due_at : null;
        if (! $dueAt) {
            $dueAt = Assignment::where('id', $this->assignment_id)->value('due_at');
        }

        return $dueAt ? $this->submitted_at->isAfter($dueAt) : false;
    }

    public function getStatusAttribute(): string
    {
        return $this->score !== null ? 'dinilai' : 'menunggu_penilaian';
    }

    public function getSubmissionTimingAttribute(): string
    {
        return $this->getIsLateAttribute() ? 'terlambat' : 'tepat_waktu';
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }
}
