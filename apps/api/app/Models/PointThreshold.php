<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PointThreshold extends Model
{
    use HasUuids;

    protected $table = 'point_thresholds';

    protected $fillable = [
        'school_id',
        'min_points',
        'action',
        'notify_roles',
    ];

    protected $casts = [
        'min_points' => 'integer',
        'notify_roles' => 'array',
    ];
}
