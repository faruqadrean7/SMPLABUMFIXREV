<?php

namespace App\Models;

use App\Casts\SafeEncryptedCast;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CounselingSession extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'counseling_sessions';

    protected $fillable = [
        'case_id',
        'session_date',
        'notes_enc',
        'follow_up',
    ];

    protected $casts = [
        'session_date' => 'date',
        'notes_enc' => 'encrypted',
        'follow_up' => SafeEncryptedCast::class,
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(CounselingCase::class, 'case_id');
    }
}
