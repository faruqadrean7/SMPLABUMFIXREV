<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bulletin extends Model
{
    use HasUuids;

    protected $fillable = [
        'school_id',
        'title',
        'file_id',
        'cover_file_id',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(File::class, 'cover_file_id');
    }

    public function coverFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'cover_file_id');
    }
}
