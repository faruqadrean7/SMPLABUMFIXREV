<?php

use App\Http\Controllers\Api\V1\Bk\CounselingCaseController;
use App\Http\Controllers\Api\V1\Bk\CounselingReferralController;
use App\Http\Controllers\Api\V1\Bk\CounselingRequestController;
use App\Http\Controllers\Api\V1\Bk\CounselingSessionController;
use App\Http\Controllers\Api\V1\Bk\EarlyWarningController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // =====================================================================
    // BE-BK-01: Bimbingan & Konseling (Kasus, Sesi, dan Rujukan)
    // =====================================================================
    // Kasus Konseling
    Route::get('/counseling/cases', [CounselingCaseController::class, 'index'])
        ->middleware('permission:counseling.case.read,bk.counseling.manage,counselor,principal');
    Route::post('/counseling/cases', [CounselingCaseController::class, 'store'])
        ->middleware('permission:counseling.case.write,bk.counseling.manage,counselor');
    Route::get('/counseling/cases/{id}', [CounselingCaseController::class, 'show'])
        ->middleware('permission:counseling.case.read,bk.counseling.manage,counselor,principal');
    Route::put('/counseling/cases/{id}', [CounselingCaseController::class, 'update'])
        ->middleware('permission:counseling.case.write,bk.counseling.manage,counselor');
    Route::post('/counseling/cases/{id}/sessions', [CounselingCaseController::class, 'storeSession'])
        ->middleware('permission:counseling.case.write,bk.counseling.manage,counselor');

    // Sesi Konseling
    Route::get('/counseling/sessions/{id}', [CounselingSessionController::class, 'show'])
        ->middleware('permission:counseling.case.read,bk.counseling.manage,counselor,principal');

    // Rujukan Konseling
    Route::get('/counseling/referrals', [CounselingReferralController::class, 'index'])
        ->middleware('permission:counseling.case.read,bk.counseling.manage,counselor,principal,teacher');
    Route::post('/counseling/referrals', [CounselingReferralController::class, 'store']);
    Route::patch('/counseling/referrals/{id}/status', [CounselingReferralController::class, 'updateStatus'])
        ->middleware('permission:counseling.case.write,bk.counseling.manage,counselor');

    // =====================================================================
    // BE-BK-02: Deteksi Dini / Early Warning System
    // =====================================================================
    Route::get('/early-warnings/summary', [EarlyWarningController::class, 'summary'])
        ->middleware('permission:counseling.case.read,bk.counseling.manage,counselor,principal,teacher,homeroom');
    Route::get('/early-warnings', [EarlyWarningController::class, 'index'])
        ->middleware('permission:counseling.case.read,bk.counseling.manage,counselor,principal,teacher,homeroom');
    Route::post('/early-warnings/evaluate', [EarlyWarningController::class, 'evaluate'])
        ->middleware('permission:bk.counseling.manage,counselor');
    Route::get('/early-warnings/{id}', [EarlyWarningController::class, 'show'])
        ->middleware('permission:counseling.case.read,bk.counseling.manage,counselor,principal,teacher,homeroom');
    Route::patch('/early-warnings/{id}/handle', [EarlyWarningController::class, 'handle'])
        ->middleware('permission:bk.counseling.manage,counselor');

    // =====================================================================
    // BE-BK-03: Permintaan Konseling (Counseling Requests)
    // =====================================================================
    Route::get('/counseling/requests', [CounselingRequestController::class, 'index']);
    Route::post('/counseling/requests', [CounselingRequestController::class, 'store']);
    Route::get('/counseling/requests/{id}', [CounselingRequestController::class, 'show']);
    Route::patch('/counseling/requests/{id}/schedule', [CounselingRequestController::class, 'schedule'])
        ->middleware('permission:bk.counseling.manage,counselor');
    Route::patch('/counseling/requests/{id}/reject', [CounselingRequestController::class, 'reject'])
        ->middleware('permission:bk.counseling.manage,counselor');
    Route::patch('/counseling/requests/{id}/complete', [CounselingRequestController::class, 'complete'])
        ->middleware('permission:bk.counseling.manage,counselor');
    Route::delete('/counseling/requests/{id}', [CounselingRequestController::class, 'cancel']);
});
