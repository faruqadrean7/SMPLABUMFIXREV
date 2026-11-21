<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    use HasUuids;

    protected $fillable = [
        'school_id',
        'author_id',
        'title',
        'body',
        'target_type',
        'target_ids',
        'attachment_file_id',
        'priority',
        'is_pinned',
        'publish_at',
        'expires_at',
    ];

    protected $casts = [
        'target_ids' => 'array',
        'is_pinned' => 'boolean',
        'publish_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(File::class, 'attachment_file_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class, 'announcement_id');
    }

    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_reads', 'announcement_id', 'user_id')
            ->withPivot('read_at');
    }

    /**
     * Scope untuk pengumuman yang sudah terbit dan belum kedaluwarsa.
     */
    public function scopePublished(Builder $query): Builder
    {
        $now = Carbon::now();

        return $query->where('publish_at', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', $now);
            });
    }

    /**
     * Cek apakah user tertentu sudah membaca pengumuman ini.
     */
    public function isReadBy(?string $userId): bool
    {
        if (! $userId) {
            return false;
        }

        return $this->reads()->where('user_id', $userId)->exists();
    }
}
