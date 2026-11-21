<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExchangeProgram extends Model
{
    use HasUuids;

    protected $fillable = [
        'partnership_id',
        'name',
        'type',
        'destination_country',
        'destination_institution',
        'quota',
        'description',
        'start_date',
        'end_date',
        'pic_teacher_id',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'quota' => 'integer',
    ];

    protected $appends = [
        'participants_count',
    ];

    public function partnership(): BelongsTo
    {
        return $this->belongsTo(Partnership::class, 'partnership_id');
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'pic_teacher_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ExchangeParticipant::class, 'program_id');
    }

    public function getParticipantsCountAttribute(): int
    {
        return $this->participants()->count();
    }
}
