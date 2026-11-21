<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    use HasUuids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'terbit';

    public const STATUS_ARCHIVED = 'arsip';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_ARCHIVED,
    ];

    public const VISIBILITY_PUBLIC = 'publik';

    public const VISIBILITY_INTERNAL = 'internal';

    public const VISIBILITIES = [
        self::VISIBILITY_PUBLIC,
        self::VISIBILITY_INTERNAL,
    ];

    protected $fillable = [
        'school_id',
        'category_id',
        'author_id',
        'cover_file_id',
        'slug',
        'title',
        'content',
        'status',
        'visibility',
        'views_count',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'views_count' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'category_id');
    }

    public function coverFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'cover_file_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublicView($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->where('visibility', self::VISIBILITY_PUBLIC)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
