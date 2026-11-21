<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CbtSyncLog extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $table = 'cbt_sync_logs';

    protected $fillable = [
        'school_id',
        'exam_id',
        'node_id',
        'sync_type',
        'package_hash',
        'records_count',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'records_count' => 'integer',
            'meta' => 'array',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
}
