<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SchoolEvent extends Model
{
    use HasUuids;

    public const CATEGORIES = ['ujian', 'libur', 'event'];

    public const VISIBILITIES = ['publik', 'internal'];

    protected $table = 'events';

    protected $fillable = [
        'school_id',
        'title',
        'category',
        'visibility',
        'start_at',
        'end_at',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    /**
     * Scope a query to only include events visible to the public.
     */
    public function scopePublicView($query)
    {
        return $query->where('visibility', 'publik');
    }
}
