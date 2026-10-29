<?php

use App\Http\Controllers\Api\V1\Ntf\AnnouncementController;
use App\Http\Controllers\Api\V1\Ntf\MessagingController;
use App\Http\Controllers\Api\V1\Ntf\NotificationController;
use App\Http\Controllers\Api\V1\Ntf\NotificationPreferenceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // =====================================================================
    // BE-NTF-01: Notifikasi In-App & Push
    // =====================================================================
    // Registrasi Perangkat (Devices & Push Tokens)
    Route::delete('/devices/{token}', [NotificationController::class, 'destroyDevice']);

    // Inbox Notifikasi Pengguna & Deep Link
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/{id}', [NotificationController::class, 'show']);
    Route::match(['post', 'patch'], '/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    // Alias /me/notifications (Harmonisasi BE-03)
    Route::get('/me/notifications', [NotificationController::class, 'index']);
    Route::match(['post', 'patch'], '/me/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::patch('/me/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/me/notifications/{id}', [NotificationController::class, 'destroy']);

    // Broadcast Notifikasi Tersegmentasi
    Route::post('/notifications/broadcast', [NotificationController::class, 'broadcast'])
        ->middleware('permission:notification.broadcast,ntf.broadcast');

    // =====================================================================
    // BE-NTF-02: WhatsApp & Email (Template Berparameter & Log Pengiriman)
    // =====================================================================
    // Template Notifikasi
    Route::get('/notification-templates', [MessagingController::class, 'indexTemplates'])
        ->middleware('permission:notification.broadcast,ntf.broadcast');
    Route::post('/notification-templates', [MessagingController::class, 'storeTemplate'])
        ->middleware('permission:notification.broadcast,ntf.broadcast');
    Route::get('/notification-templates/{id}', [MessagingController::class, 'showTemplate'])
        ->middleware('permission:notification.broadcast,ntf.broadcast');
    Route::put('/notification-templates/{id}', [MessagingController::class, 'updateTemplate'])
        ->middleware('permission:notification.broadcast,ntf.broadcast');
    Route::delete('/notification-templates/{id}', [MessagingController::class, 'destroyTemplate'])
        ->middleware('permission:notification.broadcast,ntf.broadcast');
    Route::post('/notification-templates/send', [MessagingController::class, 'send'])
        ->middleware('permission:notification.broadcast,ntf.broadcast');

    // Riwayat & Log Pengiriman
    Route::get('/notification-logs', [MessagingController::class, 'indexLogs'])
        ->middleware('permission:notification.broadcast,ntf.broadcast');
    Route::get('/notification-logs/{id}', [MessagingController::class, 'showLog'])
        ->middleware('permission:notification.broadcast,ntf.broadcast');
    Route::post('/notification-logs/{id}/retry', [MessagingController::class, 'retry'])
        ->middleware('permission:notification.broadcast,ntf.broadcast');

    // =====================================================================
    // BE-NTF-03: Pengumuman Terarah (Terjadwal & Pelacakan Pembaca)
    // =====================================================================
    Route::get('/announcements', [AnnouncementController::class, 'index']);
    Route::post('/announcements', [AnnouncementController::class, 'store'])
        ->middleware('permission:notification.broadcast,super_admin,admin,teacher,admin_tu,principal,humas,kesiswaan');
    Route::get('/announcements/{id}', [AnnouncementController::class, 'show']);
    Route::put('/announcements/{id}', [AnnouncementController::class, 'update'])
        ->middleware('permission:notification.broadcast,super_admin,admin,teacher,admin_tu,principal,humas,kesiswaan');
    Route::delete('/announcements/{id}', [AnnouncementController::class, 'destroy'])
        ->middleware('permission:notification.broadcast,super_admin,admin,teacher,admin_tu,principal,humas,kesiswaan');
    Route::post('/announcements/{id}/read', [AnnouncementController::class, 'markAsRead']);
    Route::get('/announcements/{id}/readers', [AnnouncementController::class, 'readers']);

    // =====================================================================
    // BE-NTF-04: Preferensi Notifikasi (Kanal & Jenis Notifikasi)
    // =====================================================================
    Route::get('/notification-preferences', [NotificationPreferenceController::class, 'index']);
    Route::put('/notification-preferences', [NotificationPreferenceController::class, 'update']);
    Route::get('/notification-preferences/types', [NotificationPreferenceController::class, 'types']);

    // Alias /me/notification-preferences (Harmonisasi BE-03)
    Route::get('/me/notification-preferences', [NotificationPreferenceController::class, 'index']);
    Route::put('/me/notification-preferences', [NotificationPreferenceController::class, 'update']);
    Route::get('/me/notification-preferences/types', [NotificationPreferenceController::class, 'types']);
});
