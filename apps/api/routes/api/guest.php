<?php

use App\Http\Controllers\Api\V1\ComplaintController;
use App\Http\Controllers\Api\V1\GuestController;
use Illuminate\Support\Facades\Route;

// =========================================================================
// BE-GUEST-01 & BE-GUEST-03: Buku Tamu Digital (Public Registration & Signature)
// =========================================================================
Route::post('/public/guests', [GuestController::class, 'register'])->middleware('throttle:60,1');
Route::post('/public/guests/{id}/signature', [GuestController::class, 'uploadSignature'])->middleware('throttle:60,1');

// =========================================================================
// BE-GUEST-02: Aspirasi & Whistleblowing (Public Submission & Tracking)
// =========================================================================
Route::post('/public/complaints', [ComplaintController::class, 'submit'])->middleware('throttle:60,1');
Route::get('/public/complaints/{code}', [ComplaintController::class, 'track']);

Route::middleware('auth:sanctum')->group(function () {
    // =====================================================================
    // BE-GUEST-01 & BE-GUEST-03: Buku Tamu Digital (Staff / Admin Operations)
    // =====================================================================
    Route::get('/guests', [GuestController::class, 'index'])
        ->middleware('permission:guest.book.read');
    Route::get('/guests/{id}', [GuestController::class, 'show']);
    Route::get('/guests/{id}/signature', [GuestController::class, 'getSignature']);
    Route::patch('/guests/{id}/checkout', [GuestController::class, 'checkout'])
        ->middleware('permission:guest.book.manage');

    // =====================================================================
    // BE-GUEST-02: Aspirasi & Whistleblowing (Staff / Admin Operations)
    // =====================================================================
    Route::get('/complaints', [ComplaintController::class, 'index']);
    Route::get('/complaints/{id}', [ComplaintController::class, 'show']);
    Route::patch('/complaints/{id}/status', [ComplaintController::class, 'updateStatus'])
        ->middleware('permission:complaint.manage');
});
