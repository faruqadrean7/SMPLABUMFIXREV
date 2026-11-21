<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Download extends Model
{
    use HasUuids;

    public const VISIBILITIES = ['publik', 'internal'];

    protected $fillable = [
        'school_id',
        'title',
        'category',
        'visibility',
        'file_id',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id');
    }

    /**
     * Scope a query to only include downloads visible to the public.
     */
    public function scopePublicView($query)
    {
        return $query->where('visibility', 'publik');
    }
}
