<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    use Auditable, HasFactory, HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'dijadwalkan';

    public const STATUS_IN_PROGRESS = 'berlangsung';

    public const STATUS_FINISHED = 'selesai';

    public const STATUS_CANCELLED = 'dibatalkan';

    public const STATUS_ARCHIVED = 'arsip';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SCHEDULED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_FINISHED,
        self::STATUS_CANCELLED,
        self::STATUS_ARCHIVED,
    ];

    protected $fillable = [
        'school_id',
        'subject_id',
        'created_by',
        'parent_exam_id',
        'title',
        'exam_type',
        'duration_min',
        'start_at',
        'end_at',
        'passing_grade',
        'shuffle_questions',
        'shuffle_options',
        'package_mode',
        'show_result_mode',
        'status',
        'max_remedial_score',
        'sync_node_token',
    ];

    protected $hidden = [
        'sync_node_token',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'passing_grade' => 'decimal:2',
            'max_remedial_score' => 'decimal:2',
            'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parentExam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'parent_exam_id');
    }

    public function remedialExams(): HasMany
    {
        return $this->hasMany(Exam::class, 'parent_exam_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(ExamRoom::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ExamParticipant::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ExamSession::class);
    }

    public function itemAnalyses(): HasMany
    {
        return $this->hasMany(ExamItemAnalysis::class);
    }
}
