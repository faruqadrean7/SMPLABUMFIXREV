<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PointType extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'point_types';

    protected $fillable = [
        'school_id',
        'kind',
        'name',
        'points',
        'severity',
    ];

    protected $casts = [
        'points' => 'integer',
    ];

    public function studentPoints(): HasMany
    {
        return $this->hasMany(StudentPoint::class);
    }
}
