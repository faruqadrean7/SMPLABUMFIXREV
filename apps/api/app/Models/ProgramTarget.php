<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgramTarget extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'program_targets';

    protected $fillable = [
        'school_id',
        'student_id',
        'program_type',
        'title',
        'domain',
        'target_value',
        'current_value',
        'period',
        'status',
        'set_by',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function setter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }
}
