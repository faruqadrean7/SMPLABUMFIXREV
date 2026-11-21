<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory, HasUuids;

    public const STATUSES = ['draft', 'terbit'];

    protected $fillable = [
        'school_id',
        'title',
        'slug',
        'content',
        'status',
    ];

    /**
     * Scope a query to only include published pages for public view.
     */
    public function scopePublicView($query)
    {
        return $query->where('status', 'terbit');
    }
}
