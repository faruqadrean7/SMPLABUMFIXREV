<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamSession extends Model
{
    use Auditable, HasFactory, HasUuids;

    public const STATUS_NOT_STARTED = 'belum';

    public const STATUS_IN_PROGRESS = 'mengerjakan';

    public const STATUS_FINISHED = 'selesai';

    public const STATUS_LOCKED = 'terkunci';

    public const STATUSES = [
        self::STATUS_NOT_STARTED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_FINISHED,
        self::STATUS_LOCKED,
    ];

    protected $table = 'exam_sessions';

    protected $fillable = [
        'exam_id',
        'student_id',
        'status',
        'started_at',
        'ends_at',
        'deadline_at',
        'submitted_at',
        'extra_minutes',
        'seed',
        'score',
        'locked_reason',
        'last_heartbeat_at',
        'ip_address',
        'user_agent',
        'device_fingerprint',
        'blur_count',
        'violation_count',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
            'deadline_at' => 'datetime',
            'submitted_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'score' => 'decimal:2',
            'extra_minutes' => 'integer',
            'blur_count' => 'integer',
            'violation_count' => 'integer',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class, 'session_id');
    }

    public function violations(): HasMany
    {
        return $this->hasMany(ExamViolation::class, 'session_id');
    }

    public function proctorActions(): HasMany
    {
        return $this->hasMany(ExamProctorAction::class, 'session_id');
    }
}
