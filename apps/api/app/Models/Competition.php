<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competition extends Model
{
    use HasUuids;

    protected $table = 'competitions';

    protected $fillable = [
        'school_id',
        'name',
        'organizer',
        'level',
        'category',
        'held_at',
        'registration_deadline',
        'status',
        'description',
    ];

    protected $casts = [
        'held_at' => 'date',
        'registration_deadline' => 'date',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(CompetitionEntry::class, 'competition_id');
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(Achievement::class, 'competition_id');
    }
}
