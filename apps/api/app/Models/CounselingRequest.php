<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CounselingRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'student_id',
        'requested_by',
        'requester_role',
        'topic',
        'preferred_date',
        'preferred_time',
        'preferred_counselor_id',
        'status',
        'scheduled_at',
        'counselor_id',
        'notes',
        'rejection_reason',
    ];

    protected $casts = [
        'preferred_date' => 'date',
        'scheduled_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function preferredCounselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preferred_counselor_id');
    }

    public function counselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }
}
