<?php

use App\Http\Controllers\Api\V1\Rpt\ApprovalController;
use App\Http\Controllers\Api\V1\Rpt\DashboardController;
use App\Http\Controllers\Api\V1\Rpt\ReportController;
use App\Http\Controllers\Api\V1\Storage\FileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // =====================================================================
    // BE-RPT-01: Laporan Rekapitulasi (Kehadiran, Nilai, PPDB, Poin, dll)
    // =====================================================================
    Route::get('/reports/{type}/export', [ReportController::class, 'export'])
        ->middleware('permission:reports.view');
    Route::get('/reports/{type}', [ReportController::class, 'show'])
        ->middleware('permission:reports.view');

    // =====================================================================
    // BE-RPT-02: Dashboard Persona (Eksekutif, Guru, Siswa, Wali Murid)
    // =====================================================================
    Route::get('/dashboard/executive', [DashboardController::class, 'executive'])
        ->middleware('permission:dashboard.executive,executive.dashboard.view,principal');
    Route::get('/dashboard/teacher', [DashboardController::class, 'teacher']);
    Route::get('/dashboard/student', [DashboardController::class, 'student']);
    Route::get('/dashboard/guardian/{studentId?}', [DashboardController::class, 'guardian']);

    // =====================================================================
    // BE-RPT-02 / ADM-DASH-02: Antrean & Eksekusi Persetujuan Eksekutif
    // =====================================================================
    Route::get('/approvals/pending', [ApprovalController::class, 'pending'])
        ->middleware('permission:executive.approval.manage,principal');
    Route::post('/approvals/{id}/review', [ApprovalController::class, 'review'])
        ->middleware('permission:executive.approval.manage,principal');

    // =====================================================================
    // BE-FILE-01: Penyimpanan & Utilitas Berkas (Upload Bertanda Tangan)
    // =====================================================================
    Route::post('/files/presign', [FileController::class, 'presign']);
    Route::post('/files/confirm', [FileController::class, 'confirm']);
    Route::get('/files/{id}/url', [FileController::class, 'url']);
});
