<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PageService
{
    // =========================================================================
    // STATIC PAGES
    // =========================================================================

    /**
     * BE-CMS-07: List static pages.
     */
    public function listPages(Request $request, bool $isPublic = false): LengthAwarePaginator|Collection
    {
        $query = Page::query();

        if ($isPublic) {
            $query->publicView();
        } elseif ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        if ($schoolId = $request->query('school_id')) {
            $query->where('school_id', $schoolId);
        }

        $query->orderBy('created_at', 'desc');

        if ($request->query('all') === 'true' || $request->query('paginate') === 'false') {
            return $query->get();
        }

        $perPage = min((int) $request->query('per_page', 15), 100);

        return $query->paginate($perPage);
    }

    /**
     * BE-CMS-07: Get page by ID (Admin).
     */
    public function getPage(string $id): Page
    {
        return Page::findOrFail($id);
    }

    /**
     * BE-CMS-07: Get published page by slug (Public).
     */
    public function getPublicPageBySlug(string $slug): Page
    {
        return Page::publicView()->where('slug', $slug)->firstOrFail();
    }

    /**
     * BE-CMS-07: Create page with auto-slug and audit logging.
     */
    public function createPage(array $data, Request $request): Page
    {
        return DB::transaction(function () use ($data, $request) {
            $slug = ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['title']);
            $slug = $this->ensureUniqueSlug($slug);

            $page = Page::create([
                'school_id' => $data['school_id'] ?? $request->user()?->school_id,
                'title' => $data['title'],
                'slug' => $slug,
                'content' => $data['content'],
                'status' => $data['status'] ?? 'draft',
            ]);

            $this->logAudit(
                $request->user()?->id,
                'page.created',
                'Page',
                $page->id,
                null,
                $page->toArray(),
                $request
            );

            return $page;
        });
    }

    /**
     * BE-CMS-07: Update page.
     */
    public function updatePage(string $id, array $data, Request $request): Page
    {
        return DB::transaction(function () use ($id, $data, $request) {
            $page = Page::findOrFail($id);
            $oldValue = $page->toArray();

            if (isset($data['title'])) {
                $page->title = $data['title'];
            }

            if (! empty($data['slug'])) {
                $page->slug = $this->ensureUniqueSlug(Str::slug($data['slug']), $id);
            }

            if (isset($data['content'])) {
                $page->content = $data['content'];
            }

            if (isset($data['status'])) {
                $page->status = $data['status'];
            }

            if (isset($data['school_id'])) {
                $page->school_id = $data['school_id'];
            }

            $page->save();

            $this->logAudit(
                $request->user()?->id,
                'page.updated',
                'Page',
                $page->id,
                $oldValue,
                $page->fresh()->toArray(),
                $request
            );

            return $page->fresh();
        });
    }

    /**
     * BE-CMS-07: Delete page.
     */
    public function deletePage(string $id, Request $request): void
    {
        DB::transaction(function () use ($id, $request) {
            $page = Page::findOrFail($id);
            $oldValue = $page->toArray();

            $page->delete();

            $this->logAudit(
                $request->user()?->id,
                'page.deleted',
                'Page',
                $id,
                $oldValue,
                null,
                $request
            );
        });
    }

    // =========================================================================
    // NAVIGATION MENU
    // =========================================================================

    /**
     * BE-CMS-07: Get navigation menu with fallback defaults.
     */
    public function getNavigationMenu(): array
    {
        $setting = SiteSetting::where('setting_key', 'navigation_menu')->first();

        if ($setting && ! empty($setting->setting_value)) {
            return $setting->setting_value;
        }

        return $this->getDefaultNavigationMenu();
    }

    /**
     * BE-CMS-07: Update navigation menu.
     */
    public function updateNavigationMenu(array $items, Request $request): array
    {
        return DB::transaction(function () use ($items, $request) {
            $setting = SiteSetting::where('setting_key', 'navigation_menu')->first();
            $oldValue = $setting?->setting_value ?? $this->getDefaultNavigationMenu();

            $updatedSetting = SiteSetting::updateOrCreate(
                ['setting_key' => 'navigation_menu'],
                [
                    'school_id' => $request->user()?->school_id,
                    'setting_value' => $items,
                ]
            );

            $this->logAudit(
                $request->user()?->id,
                'navigation_menu.updated',
                'SiteSetting',
                $updatedSetting->id,
                $oldValue,
                $items,
                $request
            );

            return $items;
        });
    }

    /**
     * Ensure unique slug.
     */
    protected function ensureUniqueSlug(string $slug, ?string $ignoreId = null): string
    {
        $originalSlug = $slug;
        $count = 1;

        while (
            Page::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        return $slug;
    }

    /**
     * Default navigation menu items for Portal Sekolah.
     */
    protected function getDefaultNavigationMenu(): array
    {
        return [
            ['title' => 'Beranda', 'url' => '/', 'target' => '_self', 'is_active' => true],
            [
                'title' => 'Profil',
                'url' => '/profil',
                'target' => '_self',
                'is_active' => true,
                'children' => [
                    ['title' => 'Visi & Misi', 'url' => '/profil#visi-misi', 'target' => '_self', 'is_active' => true],
                    ['title' => 'Sambutan Kepala Sekolah', 'url' => '/profil#sambutan', 'target' => '_self', 'is_active' => true],
                    ['title' => 'Fasilitas', 'url' => '/profil#fasilitas', 'target' => '_self', 'is_active' => true],
                    ['title' => 'Guru & Staf', 'url' => '/guru', 'target' => '_self', 'is_active' => true],
                ],
            ],
            ['title' => 'Berita', 'url' => '/berita', 'target' => '_self', 'is_active' => true],
            ['title' => 'Agenda', 'url' => '/agenda', 'target' => '_self', 'is_active' => true],
            ['title' => 'Galeri', 'url' => '/galeri', 'target' => '_self', 'is_active' => true],
            ['title' => 'Unduhan', 'url' => '/unduhan', 'target' => '_self', 'is_active' => true],
            ['title' => 'Kontak', 'url' => '/kontak', 'target' => '_self', 'is_active' => true],
            ['title' => 'PPDB', 'url' => '/ppdb', 'target' => '_self', 'is_active' => true],
        ];
    }

    /**
     * Log action into audit_logs.
     */
    protected function logAudit(
        ?string $actorId,
        string $action,
        string $entity,
        string $entityId,
        ?array $oldValue,
        ?array $newValue,
        Request $request
    ): void {
        AuditLog::create([
            'actor_id' => $actorId,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
