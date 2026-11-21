<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Album extends Model
{
    use HasUuids;

    protected $fillable = [
        'school_id',
        'title',
        'cover_file_id',
    ];

    public function cover(): BelongsTo
    {
        return $this->belongsTo(File::class, 'cover_file_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MediaItem::class, 'album_id');
    }
}
