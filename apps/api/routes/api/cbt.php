<?php

use App\Http\Controllers\Api\V1\Cbt\ExamGradingController;
use App\Http\Controllers\Api\V1\Cbt\ExamParticipantCardController;
use App\Http\Controllers\Api\V1\Cbt\ExamReportController;
use App\Http\Controllers\Api\V1\Cbt\ExamRoomTokenController;
use App\Http\Controllers\Api\V1\Cbt\ExamSessionController;
use App\Http\Controllers\Api\V1\Cbt\ExamSyncController;
use App\Http\Controllers\Api\V1\ExamController;
use App\Http\Controllers\Api\V1\QuestionBankController;
use App\Http\Controllers\Api\V1\QuestionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // =====================================================================
    // BE-CBT-01: Bank Soal CBT
    // =====================================================================
    // Paket Bank Soal
    Route::get('/question-banks', [QuestionBankController::class, 'index'])
        ->middleware('permission:cbt.bank.write');
    Route::post('/question-banks', [QuestionBankController::class, 'store'])
        ->middleware('permission:cbt.bank.write');
    Route::get('/question-banks/{id}', [QuestionBankController::class, 'show'])
        ->middleware('permission:cbt.bank.write');
    Route::put('/question-banks/{id}', [QuestionBankController::class, 'update'])
        ->middleware('permission:cbt.bank.write');
    Route::delete('/question-banks/{id}', [QuestionBankController::class, 'destroy'])
        ->middleware('permission:cbt.bank.write');

    // Alias paket soal sesuai PRD /question-packages dengan permission & method yang sama
    Route::get('/question-packages', [QuestionBankController::class, 'index'])
        ->middleware('permission:cbt.bank.write');
    Route::post('/question-packages', [QuestionBankController::class, 'store'])
        ->middleware('permission:cbt.bank.write');
    Route::get('/question-packages/{id}', [QuestionBankController::class, 'show'])
        ->middleware('permission:cbt.bank.write');
    Route::put('/question-packages/{id}', [QuestionBankController::class, 'update'])
        ->middleware('permission:cbt.bank.write');
    Route::delete('/question-packages/{id}', [QuestionBankController::class, 'destroy'])
        ->middleware('permission:cbt.bank.write');

    // Butir Soal & Import Massal
    Route::get('/questions', [QuestionController::class, 'index'])
        ->middleware('permission:cbt.bank.write');
    Route::post('/questions', [QuestionController::class, 'store'])
        ->middleware('permission:cbt.bank.write');
    Route::get('/questions/{id}', [QuestionController::class, 'show'])
        ->middleware('permission:cbt.bank.write');
    Route::put('/questions/{id}', [QuestionController::class, 'update'])
        ->middleware('permission:cbt.bank.write');
    Route::delete('/questions/{id}', [QuestionController::class, 'destroy'])
        ->middleware('permission:cbt.bank.write');
    Route::post('/questions/import', [QuestionController::class, 'import'])
        ->middleware('permission:cbt.bank.write');

    // =====================================================================
    // BE-CBT-02: Pembuatan & Manajemen Ujian CBT
    // =====================================================================
    Route::get('/exams', [ExamController::class, 'index']);
    Route::post('/exams', [ExamController::class, 'store'])
        ->middleware('permission:cbt.exam.manage');
    Route::get('/exams/{id}', [ExamController::class, 'show']);
    Route::put('/exams/{id}', [ExamController::class, 'update'])
        ->middleware('permission:cbt.exam.manage');
    Route::delete('/exams/{id}', [ExamController::class, 'destroy'])
        ->middleware('permission:cbt.exam.manage');

    // Plotting Soal Ujian
    Route::get('/exams/{id}/questions', [ExamController::class, 'getQuestions'])
        ->middleware('permission:cbt.exam.manage');
    Route::post('/exams/{id}/questions', [ExamController::class, 'assignQuestions'])
        ->middleware('permission:cbt.exam.manage');

    // Plotting Ruang & Pengawas Ujian
    Route::get('/exams/{id}/rooms', [ExamController::class, 'getRooms'])
        ->middleware('permission:cbt.exam.manage');
    Route::post('/exams/{id}/rooms', [ExamController::class, 'assignRooms'])
        ->middleware('permission:cbt.exam.manage');

    // Plotting Peserta Ujian
    Route::get('/exams/{id}/participants', [ExamController::class, 'getParticipants'])
        ->middleware('permission:cbt.exam.manage');
    Route::post('/exams/{id}/participants', [ExamController::class, 'enrollParticipants'])
        ->middleware('permission:cbt.exam.manage');

    // Status Lifecycle Ujian
    Route::patch('/exams/{id}/status', [ExamController::class, 'updateStatus'])
        ->middleware('permission:cbt.exam.manage');

    // =====================================================================
    // BE-CBT-03: Token Ujian & Plotting Ruang CBT
    // =====================================================================
    // Token Ruang Ujian
    Route::post('/exam-rooms/{id}/regenerate-token', [ExamRoomTokenController::class, 'regenerate'])
        ->middleware('throttle:10,1');
    Route::get('/exam-rooms/{id}/token-status', [ExamRoomTokenController::class, 'status']);

    // Plotting & Auto-Distribusi Peserta Ujian
    Route::post('/exams/{id}/distribute-participants', [ExamParticipantCardController::class, 'distribute'])
        ->middleware('permission:cbt.exam.manage');

    // Cetak / Kartu Peserta Ujian
    Route::get('/exams/{id}/participant-cards', [ExamParticipantCardController::class, 'index'])
        ->middleware('permission:cbt.exam.manage');
    Route::get('/exams/{id}/participants/{participantId}/card-pdf', [ExamParticipantCardController::class, 'downloadPdf']);

    // =====================================================================
    // BE-CBT-04: Eksekusi Ujian Siswa (Live Session, Autosave, Anti-Cheat)
    // =====================================================================
    // Alur Pengerjaan Ujian oleh Siswa
    Route::post('/exams/{id}/start-session', [ExamSessionController::class, 'start']);
    Route::get('/exams/{id}/session-questions', [ExamSessionController::class, 'questions']);
    Route::post('/exams/{id}/save-answer', [ExamSessionController::class, 'saveAnswer']);
    Route::post('/exams/{id}/log-violation', [ExamSessionController::class, 'logViolation']);
    Route::post('/exams/{id}/violations', [ExamSessionController::class, 'logViolation']);
    Route::post('/exams/{id}/finish-session', [ExamSessionController::class, 'finish']);
    Route::get('/exams/{id}/review', [ExamSessionController::class, 'review']);

    // Pengawas / Proctor Mengelola Sesi Terkunci & Tambahan Waktu
    Route::post('/exam-sessions/{sessionId}/manage', [ExamSessionController::class, 'manage']);

    // =====================================================================
    // BE-CBT-06: Monitoring Live Sesi Ujian Pengawas (Real-time Status)
    // =====================================================================
    Route::get('/exams/{id}/monitor', [ExamSessionController::class, 'monitor']);

    // =====================================================================
    // BE-CBT-12: Heartbeat Sesi & Deteksi Anomali Perangkat/IP
    // =====================================================================
    Route::post('/exam-sessions/{sessionId}/heartbeat', [ExamSessionController::class, 'heartbeat']);
    Route::get('/exams/{id}/anomalies', [ExamSessionController::class, 'anomalies']);

    // =====================================================================
    // BE-CBT-05: Penilaian Otomatis, Grading Manual Uraian, & Item Analysis
    // =====================================================================
    Route::post('/exams/{id}/auto-grade', [ExamGradingController::class, 'autoGrade'])
        ->middleware('permission:cbt.exam.manage');
    Route::get('/exams/{id}/essay-submissions', [ExamGradingController::class, 'essaySubmissions'])
        ->middleware('permission:cbt.exam.manage,cbt.bank.write');
    Route::post('/exams/{id}/grade-essay', [ExamGradingController::class, 'gradeEssay'])
        ->middleware('permission:cbt.exam.manage,cbt.bank.write');
    Route::get('/exams/{id}/results', [ExamGradingController::class, 'results'])
        ->middleware('permission:cbt.exam.manage,cbt.bank.write');
    Route::get('/exams/{id}/item-analysis', [ExamGradingController::class, 'itemAnalysis'])
        ->middleware('permission:cbt.exam.manage,cbt.bank.write');
    Route::post('/exams/{id}/publish-gradebook', [ExamGradingController::class, 'publishGradebook'])
        ->middleware('permission:cbt.exam.manage');

    // =====================================================================
    // BE-CBT-09: Remedial & Pengayaan Otomatis Berbasis KKTP
    // =====================================================================
    Route::post('/exams/{id}/remedial', [ExamGradingController::class, 'createRemedial'])
        ->middleware('permission:cbt.exam.manage');

    // =====================================================================
    // BE-CBT-10: Berita Acara & Rekapitulasi Pelaksanaan Ujian (JSON & PDF)
    // =====================================================================
    Route::get('/exams/{id}/report', [ExamReportController::class, 'report']);
    Route::get('/exams/{id}/minutes/pdf', [ExamReportController::class, 'minutesPdf']);
});

// =========================================================================
// BE-CBT-11: Mode Server Lokal & Sinkronisasi Paket Ujian Offline
// =========================================================================
Route::post('/cbt/sync/pull', [ExamSyncController::class, 'pull'])->middleware('throttle:15,1');
Route::post('/cbt/sync/push', [ExamSyncController::class, 'push'])->middleware('throttle:15,1');
