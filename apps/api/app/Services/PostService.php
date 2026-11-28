<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Post;
use App\Models\PostCategory;
use App\Support\PaginationHelper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PostService
{
    // =========================================================================
    // POST CATEGORIES
    // =========================================================================

    /**
     * BE-CMS-02: List all post categories with post counts.
     */
    public function listCategories(Request $request): Collection
    {
        $schoolId = $request->query('school_id');

        $query = PostCategory::withCount('posts')->orderBy('name', 'asc');

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        return $query->get();
    }

    /**
     * BE-CMS-02: Get single category.
     */
    public function getCategory(string $id): PostCategory
    {
        return PostCategory::withCount('posts')->findOrFail($id);
    }

    /**
     * BE-CMS-02: Create category.
     */
    public function createCategory(array $data, Request $request): PostCategory
    {
        return DB::transaction(function () use ($data, $request) {
            $slug = ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['name']);

            // Ensure unique slug
            $originalSlug = $slug;
            $count = 1;
            while (PostCategory::where('slug', $slug)->exists()) {
                $slug = "{$originalSlug}-{$count}";
                $count++;
            }

            $category = PostCategory::create([
                'school_id' => $data['school_id'] ?? $request->user()?->school_id,
                'name' => $data['name'],
                'slug' => $slug,
            ]);

            $this->logAudit(
                $request->user()?->id,
                'post_category.created',
                'PostCategory',
                $category->id,
                null,
                $category->toArray(),
                $request
            );

            return $category;
        });
    }

    /**
     * BE-CMS-02: Update category.
     */
    public function updateCategory(string $id, array $data, Request $request): PostCategory
    {
        return DB::transaction(function () use ($id, $data, $request) {
            $category = PostCategory::findOrFail($id);
            $oldValue = $category->toArray();

            if (isset($data['name'])) {
                $category->name = $data['name'];
            }

            if (! empty($data['slug'])) {
                $category->slug = Str::slug($data['slug']);
            }

            if (isset($data['school_id'])) {
                $category->school_id = $data['school_id'];
            }

            $category->save();

            $this->logAudit(
                $request->user()?->id,
                'post_category.updated',
                'PostCategory',
                $category->id,
                $oldValue,
                $category->toArray(),
                $request
            );

            return $category;
        });
    }

    /**
     * BE-CMS-02: Delete category (prevent deletion if posts exist).
     */
    public function deleteCategory(string $id, Request $request): void
    {
        $category = PostCategory::findOrFail($id);

        if ($category->posts()->exists()) {
            throw new HttpException(
                409,
                'Kategori tidak dapat dihapus karena masih digunakan oleh berita/artikel aktif.',
                null,
                [],
                409
            );
        }

        $oldValue = $category->toArray();
        $category->delete();

        $this->logAudit(
            $request->user()?->id,
            'post_category.deleted',
            'PostCategory',
            $category->id,
            $oldValue,
            null,
            $request
        );
    }

    // =========================================================================
    // POSTS (ADMIN)
    // =========================================================================

    /**
     * BE-CMS-02: List posts for admin management.
     */
    public function listAdminPosts(Request $request): LengthAwarePaginator
    {
        $limit = PaginationHelper::clamp($request->query('limit'), 20);
        $status = $request->query('status');
        $categoryId = $request->query('category_id');
        $visibility = $request->query('visibility');
        $q = trim((string) $request->query('q', $request->query('search', '')));

        $query = Post::with([
            'category',
            'author:id,username,email',
            'coverFile',
        ]);

        $user = $request->user();
        if ($user && ! empty($user->school_id)) {
            $query->where(function ($q) use ($user) {
                $q->where('school_id', $user->school_id)->orWhereNull('school_id');
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($visibility) {
            $query->where('visibility', $visibility);
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('content', 'like', "%{$q}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($limit);
    }

    /**
     * BE-CMS-02: Get post detail for admin.
     */
    public function getAdminPost(string $id, ?Request $request = null): Post
    {
        $post = Post::with([
            'category',
            'author:id,username,email',
            'coverFile',
        ])->where(function ($query) use ($id) {
            $query->where('id', $id)->orWhere('slug', $id);
        })->firstOrFail();

        $user = $request?->user();
        if ($user && ! empty($user->school_id) && ! empty($post->school_id) && $post->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Berita/artikel berada di luar sekolah penugasan Anda.');
        }

        return $post;
    }

    /**
     * BE-CMS-02: Create post.
     */
    public function createPost(array $data, Request $request): Post
    {
        return DB::transaction(function () use ($data, $request) {
            $slug = ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['title']);
            $slug = $this->ensureUniqueSlug($slug);

            $status = $data['status'] ?? Post::STATUS_DRAFT;
            $publishedAt = $data['published_at'] ?? null;

            if ($status === Post::STATUS_PUBLISHED && empty($publishedAt)) {
                $publishedAt = now();
            }

            $post = Post::create([
                'school_id' => $request->user()?->school_id ?? ($data['school_id'] ?? null),
                'category_id' => $data['category_id'] ?? null,
                'author_id' => $request->user()->id,
                'cover_file_id' => $data['cover_file_id'] ?? null,
                'slug' => $slug,
                'title' => $data['title'],
                'content' => $this->sanitizeHtmlContent((string) $data['content']),
                'status' => $status,
                'visibility' => $data['visibility'] ?? Post::VISIBILITY_PUBLIC,
                'published_at' => $publishedAt,
            ]);

            $this->logAudit(
                $request->user()?->id,
                'post.created',
                'Post',
                $post->id,
                null,
                $post->toArray(),
                $request
            );

            return $post;
        });
    }

    /**
     * BE-CMS-02: Update post.
     */
    public function updatePost(string $id, array $data, Request $request): Post
    {
        return DB::transaction(function () use ($id, $data, $request) {
            $post = Post::findOrFail($id);
            $user = $request->user();
            if ($user && ! empty($user->school_id) && ! empty($post->school_id) && $post->school_id !== $user->school_id) {
                throw new HttpException(403, 'Akses ditolak: Berita/artikel berada di luar sekolah penugasan Anda.');
            }
            $oldValue = $post->toArray();

            if (! empty($data['slug'])) {
                $data['slug'] = $this->ensureUniqueSlug(Str::slug($data['slug']), $post->id);
            } elseif (isset($data['title']) && $data['title'] !== $post->title && empty($post->slug)) {
                $data['slug'] = $this->ensureUniqueSlug(Str::slug($data['title']), $post->id);
            }

            // Auto-set published_at if publishing for first time
            if (isset($data['status']) && $data['status'] === Post::STATUS_PUBLISHED && empty($post->published_at) && empty($data['published_at'])) {
                $data['published_at'] = now();
            }

            $updatePayload = [];
            foreach (['title', 'slug', 'status', 'visibility'] as $field) {
                if (array_key_exists($field, $data) && $data[$field] !== null) {
                    $updatePayload[$field] = $data[$field];
                }
            }
            if (array_key_exists('content', $data) && $data['content'] !== null) {
                $updatePayload['content'] = $this->sanitizeHtmlContent((string) $data['content']);
            }
            // P1-31: Izinkan menghapus relasi opsional ke null secara eksplisit
            foreach (['category_id', 'cover_file_id', 'published_at'] as $nullableField) {
                if (array_key_exists($nullableField, $data)) {
                    $updatePayload[$nullableField] = $data[$nullableField];
                }
            }

            $post->update($updatePayload);

            $this->logAudit(
                $request->user()?->id,
                'post.updated',
                'Post',
                $post->id,
                $oldValue,
                $post->toArray(),
                $request
            );

            return $post;
        });
    }

    /**
     * BE-CMS-02: Delete post.
     */
    public function deletePost(string $id, Request $request): void
    {
        $post = Post::findOrFail($id);
        $user = $request->user();
        if ($user && ! empty($user->school_id) && ! empty($post->school_id) && $post->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Berita/artikel berada di luar sekolah penugasan Anda.');
        }
        $oldValue = $post->toArray();

        $post->delete();

        $this->logAudit(
            $request->user()?->id,
            'post.deleted',
            'Post',
            $post->id,
            $oldValue,
            null,
            $request
        );
    }

    // =========================================================================
    // POSTS (PUBLIC)
    // =========================================================================

    /**
     * BE-CMS-02: List public posts (only published, public visibility, published_at <= now).
     */
    public function listPublicPosts(Request $request): LengthAwarePaginator
    {
        $limit = PaginationHelper::clamp($request->query('limit'), 10, 1, 50);
        $categorySlug = $request->query('category_slug');
        $categoryId = $request->query('category_id');
        $q = trim((string) $request->query('q', $request->query('search', '')));

        $query = Post::publicView()->with([
            'category:id,name,slug',
            'author:id,username',
            'coverFile',
        ]);

        if ($categorySlug) {
            $query->whereHas('category', function ($sub) use ($categorySlug) {
                $sub->where('slug', $categorySlug);
            });
        } elseif ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('content', 'like', "%{$q}%");
            });
        }

        return $query->orderBy('published_at', 'desc')->paginate($limit);
    }

    /**
     * BE-CMS-02: Get public post detail and increment views count.
     */
    public function getPublicPost(string $slugOrId): Post
    {
        $post = Post::publicView()->with([
            'category:id,name,slug',
            'author:id,username',
            'coverFile',
        ])->where(function ($query) use ($slugOrId) {
            $query->where('slug', $slugOrId);
            if (Str::isUuid($slugOrId)) {
                $query->orWhere('id', $slugOrId);
            }
        })->firstOrFail();

        $post->increment('views_count');

        return $post;
    }

    /**
     * Ensure unique slug with incremental suffix.
     */
    protected function ensureUniqueSlug(string $slug, ?string $ignoreId = null): string
    {
        $original = $slug;
        $count = 1;

        while (Post::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = "{$original}-{$count}";
            $count++;
        }

        return $slug;
    }

    /**
     * Helper to write an audit log entry.
     */
    protected function logAudit(
        ?string $actorId,
        string $action,
        string $entity,
        ?string $entityId,
        ?array $oldValue,
        ?array $newValue,
        Request $request
    ): void {
        try {
            AuditLog::create([
                'actor_id' => $actorId,
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Sanitize HTML content for CMS posts against stored XSS payloads.
     */
    protected function sanitizeHtmlContent(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $clean = preg_replace(
            [
                '#<\s*(script|iframe|object|embed|svg|style|applet|form)\b[^>]*>.*?<\s*/\s*\1\s*>#is',
                '#<\s*/?\s*(script|iframe|object|embed|svg|style|applet|meta|base|link)\b[^>]*>#is',
                '/\s+on[a-z0-9_-]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/iu',
                '/\b(href|src|action|formaction|poster|data)\s*=\s*(["\']?)\s*(?:javascript|vbscript|data\s*:\s*text\/html)[^"\'>\s]*\2/iu',
            ],
            [
                '',
                '',
                '',
                '$1="#"',
            ],
            $html
        );

        return is_string($clean) ? $clean : strip_tags($html);
    }
}
