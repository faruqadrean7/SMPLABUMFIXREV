<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaItem extends Model
{
    use HasUuids;

    public const MEDIA_TYPES = ['image', 'video'];

    protected $fillable = [
        'album_id',
        'file_id',
        'media_type',
        'caption',
    ];

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class, 'album_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id');
    }
}
