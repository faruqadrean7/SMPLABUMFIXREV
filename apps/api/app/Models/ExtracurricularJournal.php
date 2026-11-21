<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtracurricularJournal extends Model
{
    use HasUuids;

    protected $table = 'extracurricular_journals';

    protected $fillable = [
        'extracurricular_id',
        'session_date',
        'start_time',
        'end_time',
        'activity',
        'attendance',
        'notes',
    ];

    protected $casts = [
        'session_date' => 'date',
        'attendance' => 'array',
    ];

    public function extracurricular(): BelongsTo
    {
        return $this->belongsTo(Extracurricular::class, 'extracurricular_id');
    }
}
