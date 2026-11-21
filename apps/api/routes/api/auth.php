<?php

use App\Http\Controllers\Api\V1\AdminAuthController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PublicVerificationController;
use App\Http\Controllers\Api\V1\RbacController;
use App\Http\Controllers\Api\V1\Storage\FileController;
use App\Http\Controllers\Api\V1\TwoFactorAuthController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

Route::get('/user', [AuthController::class, 'me'])->middleware('auth:sanctum');

Route::get('/health', function () {
    $dbStatus = 'ok';
    try {
        DB::connection()->getPdo();
    } catch (Throwable $e) {
        report($e);
        $dbStatus = 'unreachable';
    }

    $redisStatus = 'ok';
    try {
        Redis::ping();
    } catch (Throwable $e) {
        report($e);
        $redisStatus = 'unreachable';
    }

    return response()->json([
        'status' => ($dbStatus === 'ok' && $redisStatus === 'ok') ? 'ok' : 'degraded',
        'timestamp' => now()->toIso8601String(),
        'services' => [
            'database' => $dbStatus,
            'redis' => $redisStatus,
        ],
        'version' => '1.1.0',
    ]);
});

// BE-GRD-03: Verifikasi Dokumen Publik via Token QR
Route::get('/public/verify/{token}', [PublicVerificationController::class, 'verify']);

// =========================================================================
// BE-FILE-01: Signed Upload & Signed Download Routes
// =========================================================================
Route::post('/files/upload-signed/{token}', [FileController::class, 'uploadSigned'])->name('files.upload.signed');
Route::get('/files/{id}/download', [FileController::class, 'downloadSigned'])->name('files.download.signed');

// =========================================================================
// BE-AUTH-01: Autentikasi & Sesi
// =========================================================================
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/admin/login', [AdminAuthController::class, 'login']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,15');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:60,1');

    // GAP-SEC-02 & BATCH 4B & 5C: Manajemen Otentikasi Dua Faktor (2FA / TOTP)
    Route::post('/2fa/setup', [TwoFactorAuthController::class, 'setup'])->middleware('throttle:10,15');
    Route::post('/2fa/verify', [TwoFactorAuthController::class, 'verify'])->middleware('throttle:5,15');
    Route::post('/2fa/challenge', [AdminAuthController::class, 'login'])->middleware('throttle:10,15');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);

        Route::post('/2fa/disable', [TwoFactorAuthController::class, 'disable']);

        // BE-AUTH-04: Manajemen Sesi Perangkat
        Route::get('/sessions', [AuthController::class, 'sessions']);
        Route::delete('/sessions/{id}', [AuthController::class, 'revokeSession']);
    });
});

// BE-AUTH-04: Registrasi Token Perangkat FCM
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/devices', [AuthController::class, 'registerDevice']);
});

// =========================================================================
// BE-AUTH-03: RBAC (Roles & Permissions)
// =========================================================================
Route::middleware(['auth:sanctum', 'permission:auth.role.manage'])->group(function () {
    Route::get('/permissions', [RbacController::class, 'permissions']);

    Route::get('/roles', [RbacController::class, 'index']);
    Route::post('/roles', [RbacController::class, 'store']);
    Route::get('/roles/{id}', [RbacController::class, 'show']);
    Route::put('/roles/{id}', [RbacController::class, 'update']);
    Route::delete('/roles/{id}', [RbacController::class, 'destroy']);

    Route::get('/users/{id}/roles', [RbacController::class, 'getUserRoles']);
    Route::put('/users/{id}/roles', [RbacController::class, 'assignUserRoles']);
});

// =========================================================================
// BE-AUTH-02: Manajemen Akun Pengguna (ADM-USR-01 s/d ADM-USR-04)
// =========================================================================
Route::middleware(['auth:sanctum', 'permission:auth.user.read'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'permission:auth.user.write'])->group(function () {
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::patch('/users/{id}/status', [UserController::class, 'changeStatus']);
    Route::post('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
});

// =========================================================================
// BE-AUTH-05 & SEC-02: Audit Log (Read-only & Protected Telemetry)
// =========================================================================
Route::middleware(['auth:sanctum', 'permission:audit.read'])->group(function () {
    Route::get('/audit-logs', [AuditLogController::class, 'index']);
    Route::get('/audit-logs/{id}', [AuditLogController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
    Route::post('/audit-logs', [AuditLogController::class, 'storeTelemetry']);
});
