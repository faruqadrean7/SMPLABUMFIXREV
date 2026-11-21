<?php

use App\Http\Controllers\Api\V1\PPDBAccountController;
use App\Http\Controllers\Api\V1\PPDBApplicationController;
use App\Http\Controllers\Api\V1\PPDBDocumentController;
use App\Http\Controllers\Api\V1\PPDBEnrollmentController;
use App\Http\Controllers\Api\V1\PPDBReceiptAndStatsController;
use App\Http\Controllers\Api\V1\PPDBRequirementController;
use App\Http\Controllers\Api\V1\PPDBResultController;
use App\Http\Controllers\Api\V1\PPDBSelectionController;
use App\Http\Controllers\Api\V1\PPDBTrackController;
use App\Http\Controllers\Api\V1\PPDBVerificationController;
use App\Http\Controllers\Api\V1\PPDBWaveController;
use Illuminate\Support\Facades\Route;

// =========================================================================
// BE-PPDB-01: PPDB - Konfigurasi Gelombang, Jalur & Syarat Publik
// =========================================================================
Route::get('/public/ppdb/waves', [PPDBWaveController::class, 'publicIndex']);
Route::get('/public/ppdb/waves/active', [PPDBWaveController::class, 'active']);
Route::get('/public/ppdb/waves/{id}', [PPDBWaveController::class, 'publicShow']);
Route::get('/public/ppdb/tracks', [PPDBTrackController::class, 'publicIndex']);
Route::get('/public/ppdb/tracks/{id}', [PPDBTrackController::class, 'publicShow']);

// =========================================================================
// BE-PPDB-07: PPDB - Pengumuman Hasil Seleksi Publik
// =========================================================================
Route::post('/public/ppdb/results/check', [PPDBResultController::class, 'check']);
Route::get('/public/ppdb/stats', [PPDBReceiptAndStatsController::class, 'stats']);

Route::middleware(['auth:sanctum', 'permission:ppdb.wave.manage'])->group(function () {
    // Waves
    Route::get('/ppdb/waves', [PPDBWaveController::class, 'index']);
    Route::post('/ppdb/waves', [PPDBWaveController::class, 'store']);
    Route::get('/ppdb/waves/{id}', [PPDBWaveController::class, 'show']);
    Route::put('/ppdb/waves/{id}', [PPDBWaveController::class, 'update']);
    Route::delete('/ppdb/waves/{id}', [PPDBWaveController::class, 'destroy']);

    // Tracks
    Route::get('/ppdb/tracks', [PPDBTrackController::class, 'index']);
    Route::post('/ppdb/tracks', [PPDBTrackController::class, 'store']);
    Route::get('/ppdb/tracks/{id}', [PPDBTrackController::class, 'show']);
    Route::put('/ppdb/tracks/{id}', [PPDBTrackController::class, 'update']);
    Route::delete('/ppdb/tracks/{id}', [PPDBTrackController::class, 'destroy']);

    // Requirements
    Route::get('/ppdb/requirements', [PPDBRequirementController::class, 'index']);
    Route::post('/ppdb/requirements', [PPDBRequirementController::class, 'store']);
    Route::get('/ppdb/requirements/{id}', [PPDBRequirementController::class, 'show']);
    Route::put('/ppdb/requirements/{id}', [PPDBRequirementController::class, 'update']);
    Route::delete('/ppdb/requirements/{id}', [PPDBRequirementController::class, 'destroy']);
});

// =========================================================================
// BE-PPDB-02: PPDB - Akun Calon Siswa & Verifikasi OTP
// =========================================================================
Route::post('/ppdb/register', [PPDBAccountController::class, 'register'])->middleware('throttle:60,1');
Route::post('/ppdb/verify-otp', [PPDBAccountController::class, 'verifyOtp'])->middleware('throttle:60,1');
Route::post('/ppdb/resend-otp', [PPDBAccountController::class, 'resendOtp'])->middleware('throttle:60,1');
Route::post('/ppdb/check-nik', [PPDBAccountController::class, 'checkNik'])->middleware('throttle:60,1');
Route::post('/ppdb/login', [PPDBAccountController::class, 'login'])->middleware('throttle:60,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/ppdb/me', [PPDBAccountController::class, 'me']);

    // =====================================================================
    // BE-PPDB-03: PPDB - Form Pendaftaran Bertahap
    // =====================================================================
    Route::get('/ppdb/applications', [PPDBApplicationController::class, 'index']);
    Route::post('/ppdb/applications', [PPDBApplicationController::class, 'store']);
    Route::get('/ppdb/applications/{id}', [PPDBApplicationController::class, 'show']);
    Route::patch('/ppdb/applications/{id}/steps/{step}', [PPDBApplicationController::class, 'updateStep']);
    Route::post('/ppdb/applications/{id}/submit', [PPDBApplicationController::class, 'submit']);

    // =====================================================================
    // BE-PPDB-04: PPDB - Upload Berkas Pendaftar
    // =====================================================================
    Route::get('/ppdb/applications/{id}/documents', [PPDBDocumentController::class, 'index']);
    Route::post('/ppdb/applications/{id}/documents', [PPDBDocumentController::class, 'store']);
    Route::delete('/ppdb/applications/{id}/documents/{documentId}', [PPDBDocumentController::class, 'destroy']);
    Route::get('/ppdb/applications/{id}/documents/{documentId}/download', [PPDBDocumentController::class, 'download']);

    // =====================================================================
    // BE-PPDB-05: PPDB - Verifikasi Berkas & Aplikasi
    // =====================================================================
    Route::middleware('permission:ppdb.applicant.verify')->group(function () {
        Route::patch('/ppdb/documents/{id}/verify', [PPDBVerificationController::class, 'verifyDocument']);
        Route::patch('/ppdb/applications/{id}/verify', [PPDBVerificationController::class, 'verifyApplication']);
    });

    // =====================================================================
    // BE-PPDB-06: PPDB - Seleksi & Ranking
    // =====================================================================
    Route::middleware('permission:ppdb.selection.run')->group(function () {
        Route::post('/ppdb/waves/{id}/rank', [PPDBSelectionController::class, 'rank']);
        Route::get('/ppdb/waves/{id}/rankings', [PPDBSelectionController::class, 'rankings']);
        Route::patch('/ppdb/applications/{id}/decision', [PPDBSelectionController::class, 'overrideDecision']);
        Route::post('/ppdb/waves/{id}/publish-results', [PPDBSelectionController::class, 'publishResults']);
        Route::post('/ppdb/waves/{id}/escalate-waitlist', [PPDBSelectionController::class, 'escalateWaitlist']);
    });

    // =====================================================================
    // BE-PPDB-08: PPDB - Daftar Ulang & Konversi ke Siswa
    // =====================================================================
    Route::post('/ppdb/applications/{id}/re-register', [PPDBEnrollmentController::class, 'reRegister']);
    Route::post('/ppdb/applications/{id}/enroll', [PPDBEnrollmentController::class, 'enroll'])
        ->middleware('permission:ppdb.applicant.verify');

    // =====================================================================
    // BE-PPDB-09: PPDB - Bukti Pendaftaran & Statistik
    // =====================================================================
    Route::get('/ppdb/applications/{id}/receipt', [PPDBReceiptAndStatsController::class, 'receipt']);
    Route::get('/ppdb/stats', [PPDBReceiptAndStatsController::class, 'stats']);
});
