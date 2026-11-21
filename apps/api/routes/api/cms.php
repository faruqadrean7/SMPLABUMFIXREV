<?php

use App\Http\Controllers\Api\V1\AlbumController;
use App\Http\Controllers\Api\V1\BulletinController;
use App\Http\Controllers\Api\V1\DownloadController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\MediaItemController;
use App\Http\Controllers\Api\V1\NavigationMenuController;
use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\PostCategoryController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\PublicDirectoryController;
use App\Http\Controllers\Api\V1\SiteSettingController;
use Illuminate\Support\Facades\Route;

// =========================================================================
// BE-CMS-01: CMS - Pengaturan Situs & Profil Sekolah
// =========================================================================
Route::get('/public/site', [SiteSettingController::class, 'publicSite']);

Route::middleware(['auth:sanctum', 'permission:cms.site.manage'])->group(function () {
    Route::get('/site-settings', [SiteSettingController::class, 'show']);
    Route::put('/site-settings', [SiteSettingController::class, 'update']);
});

// =========================================================================
// BE-CMS-02: CMS - Berita & Pengumuman
// =========================================================================
Route::get('/public/posts', [PostController::class, 'publicIndex']);
Route::get('/public/posts/{slug}', [PostController::class, 'publicShow']);
Route::get('/post-categories', [PostCategoryController::class, 'index']);

Route::middleware(['auth:sanctum', 'permission:cms.post.manage'])->group(function () {
    // Categories Write
    Route::post('/post-categories', [PostCategoryController::class, 'store']);
    Route::get('/post-categories/{id}', [PostCategoryController::class, 'show']);
    Route::put('/post-categories/{id}', [PostCategoryController::class, 'update']);
    Route::delete('/post-categories/{id}', [PostCategoryController::class, 'destroy']);

    // Posts Write
    Route::get('/posts', [PostController::class, 'index']);
    Route::post('/posts', [PostController::class, 'store']);
    Route::get('/posts/{id}', [PostController::class, 'show']);
    Route::put('/posts/{id}', [PostController::class, 'update']);
    Route::delete('/posts/{id}', [PostController::class, 'destroy']);
});

// =========================================================================
// BE-CMS-03: CMS - Galeri & Mading Digital
// =========================================================================
Route::get('/public/albums', [AlbumController::class, 'publicIndex']);
Route::get('/public/albums/{id}', [AlbumController::class, 'publicShow']);
Route::get('/public/bulletins', [BulletinController::class, 'publicIndex']);
Route::get('/public/bulletins/{id}', [BulletinController::class, 'publicShow']);

Route::middleware(['auth:sanctum', 'permission:cms.gallery.manage'])->group(function () {
    // Albums
    Route::get('/albums', [AlbumController::class, 'index']);
    Route::post('/albums', [AlbumController::class, 'store']);
    Route::get('/albums/{id}', [AlbumController::class, 'show']);
    Route::put('/albums/{id}', [AlbumController::class, 'update']);
    Route::delete('/albums/{id}', [AlbumController::class, 'destroy']);

    // Media Items
    Route::post('/media', [MediaItemController::class, 'store']);
    Route::put('/media/{id}', [MediaItemController::class, 'update']);
    Route::delete('/media/{id}', [MediaItemController::class, 'destroy']);

    // Bulletins
    Route::get('/bulletins', [BulletinController::class, 'index']);
    Route::post('/bulletins', [BulletinController::class, 'store']);
    Route::get('/bulletins/{id}', [BulletinController::class, 'show']);
    Route::put('/bulletins/{id}', [BulletinController::class, 'update']);
    Route::delete('/bulletins/{id}', [BulletinController::class, 'destroy']);
});

// =========================================================================
// BE-CMS-04: CMS - Agenda & Kalender Kegiatan
// =========================================================================
Route::get('/public/events', [EventController::class, 'publicIndex']);
Route::get('/public/events/{id}', [EventController::class, 'publicShow']);

Route::middleware(['auth:sanctum', 'permission:cms.event.manage'])->group(function () {
    Route::get('/events', [EventController::class, 'index']);
    Route::post('/events', [EventController::class, 'store']);
    Route::get('/events/{id}', [EventController::class, 'show']);
    Route::put('/events/{id}', [EventController::class, 'update']);
    Route::delete('/events/{id}', [EventController::class, 'destroy']);
});

// =========================================================================
// BE-CMS-05: CMS - Direktori Guru & Staf Publik
// =========================================================================
Route::get('/public/teachers', [PublicDirectoryController::class, 'teachers']);
Route::get('/public/teachers/{id}', [PublicDirectoryController::class, 'showTeacher']);
Route::get('/public/staff', [PublicDirectoryController::class, 'staff']);

// =========================================================================
// BE-CMS-06: CMS - Pusat Unduhan
// =========================================================================
Route::get('/public/downloads', [DownloadController::class, 'publicIndex']);
Route::get('/public/downloads/{id}', [DownloadController::class, 'publicShow']);

Route::middleware(['auth:sanctum', 'permission:cms.download.manage'])->group(function () {
    Route::get('/downloads', [DownloadController::class, 'index']);
    Route::post('/downloads', [DownloadController::class, 'store']);
    Route::get('/downloads/{id}', [DownloadController::class, 'show']);
    Route::put('/downloads/{id}', [DownloadController::class, 'update']);
    Route::delete('/downloads/{id}', [DownloadController::class, 'destroy']);
});

// =========================================================================
// BE-CMS-07: CMS - Halaman Statis & Menu Navigasi
// =========================================================================
Route::get('/public/pages', [PageController::class, 'publicIndex']);
Route::get('/public/pages/{slug}', [PageController::class, 'publicShow']);
Route::get('/public/navigation-menu', [NavigationMenuController::class, 'publicShow']);

Route::middleware(['auth:sanctum', 'permission:cms.page.manage'])->group(function () {
    // Pages
    Route::get('/pages', [PageController::class, 'index']);
    Route::post('/pages', [PageController::class, 'store']);
    Route::get('/pages/{id}', [PageController::class, 'show']);
    Route::put('/pages/{id}', [PageController::class, 'update']);
    Route::delete('/pages/{id}', [PageController::class, 'destroy']);

    // Navigation Menu
    Route::get('/navigation-menu', [NavigationMenuController::class, 'show']);
    Route::put('/navigation-menu', [NavigationMenuController::class, 'update']);
});
