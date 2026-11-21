<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $table = 'leave_requests';

    protected $fillable = [
        'student_id',
        'requested_by',
        'leave_type',
        'start_date',
        'end_date',
        'reason',
        'attachment_file_id',
        'status',
        'decided_by',
        'decision_notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function attachmentFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'attachment_file_id');
    }

    public function decisionMaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
