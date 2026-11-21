<?php

use App\Http\Controllers\Api\V1\Hum\AlumniController;
use App\Http\Controllers\Api\V1\Hum\ExchangeProgramController;
use App\Http\Controllers\Api\V1\Hum\PartnershipController;
use App\Http\Controllers\Api\V1\Hum\TracerSurveyController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // =====================================================================
    // BE-HUM-01: Humas & Kemitraan - Kerja Sama & Dokumen MoU
    // =====================================================================
    // Kemitraan (Partnerships & MoU)
    Route::get('/partnerships/expiring', [PartnershipController::class, 'expiring']);
    Route::get('/partnerships', [PartnershipController::class, 'index']);
    Route::post('/partnerships', [PartnershipController::class, 'store']);
    Route::get('/partnerships/{id}', [PartnershipController::class, 'show']);
    Route::put('/partnerships/{id}', [PartnershipController::class, 'update']);
    Route::delete('/partnerships/{id}', [PartnershipController::class, 'destroy']);

    // Program Pertukaran Pelajar (Exchange Programs)
    Route::get('/exchange-programs', [ExchangeProgramController::class, 'index']);
    Route::post('/exchange-programs', [ExchangeProgramController::class, 'store']);
    Route::get('/exchange-programs/{id}', [ExchangeProgramController::class, 'show']);
    Route::put('/exchange-programs/{id}', [ExchangeProgramController::class, 'update']);
    Route::delete('/exchange-programs/{id}', [ExchangeProgramController::class, 'destroy']);

    // Peserta Pertukaran Pelajar (Participants)
    Route::get('/exchange-programs/{id}/participants', [ExchangeProgramController::class, 'indexParticipants']);
    Route::post('/exchange-programs/{id}/participants', [ExchangeProgramController::class, 'storeParticipant']);
    Route::put('/exchange-participants/{participantId}', [ExchangeProgramController::class, 'updateParticipant']);
    Route::delete('/exchange-participants/{participantId}', [ExchangeProgramController::class, 'destroyParticipant']);

    // =====================================================================
    // BE-HUM-02: Humas & Kemitraan - Alumni & Tracer Study
    // =====================================================================
    // Direktori Alumni Terbatas
    Route::get('/alumni', [AlumniController::class, 'index']);
    Route::post('/alumni', [AlumniController::class, 'store']);
    Route::get('/alumni/{id}', [AlumniController::class, 'show']);
    Route::put('/alumni/{id}', [AlumniController::class, 'update']);
    Route::delete('/alumni/{id}', [AlumniController::class, 'destroy']);

    // Kuesioner Tracer Study & Respons
    Route::get('/tracer-surveys', [TracerSurveyController::class, 'index']);
    Route::post('/tracer-surveys', [TracerSurveyController::class, 'store']);
    Route::get('/tracer-surveys/{id}', [TracerSurveyController::class, 'show']);
    Route::put('/tracer-surveys/{id}', [TracerSurveyController::class, 'update']);
    Route::delete('/tracer-surveys/{id}', [TracerSurveyController::class, 'destroy']);
    Route::post('/tracer-surveys/{id}/responses', [TracerSurveyController::class, 'storeResponse']);
    Route::get('/tracer-surveys/{id}/stats', [TracerSurveyController::class, 'stats']);
});
