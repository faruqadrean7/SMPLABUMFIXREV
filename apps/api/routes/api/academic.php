<?php

use App\Http\Controllers\Api\V1\AssignmentController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\CharacterNoteController;
use App\Http\Controllers\Api\V1\GradeComponentController;
use App\Http\Controllers\Api\V1\GradeController;
use App\Http\Controllers\Api\V1\GradePeriodController;
use App\Http\Controllers\Api\V1\JournalController;
use App\Http\Controllers\Api\V1\LeaveRequestController;
use App\Http\Controllers\Api\V1\MaterialController;
use App\Http\Controllers\Api\V1\ProgressReportController;
use App\Http\Controllers\Api\V1\ReportCardController;
use App\Http\Controllers\Api\V1\ReportDescriptionTemplateController;
use App\Http\Controllers\Api\V1\ScheduleController;
use App\Http\Controllers\Api\V1\StaffAttendanceController;
use App\Http\Controllers\Api\V1\TeachingMaterialController;
use App\Http\Controllers\Api\V1\TimeSlotController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // =====================================================================
    // BE-ACD-01: Jadwal Pelajaran & Deteksi Bentrok
    // =====================================================================
    // Time Slots
    Route::get('/time-slots', [TimeSlotController::class, 'index']);
    Route::get('/time-slots/{id}', [TimeSlotController::class, 'show']);
    Route::post('/time-slots', [TimeSlotController::class, 'store'])
        ->middleware('permission:academic.schedule.manage');
    Route::put('/time-slots/{id}', [TimeSlotController::class, 'update'])
        ->middleware('permission:academic.schedule.manage');
    Route::delete('/time-slots/{id}', [TimeSlotController::class, 'destroy'])
        ->middleware('permission:academic.schedule.manage');

    // Schedules
    Route::get('/schedules', [ScheduleController::class, 'index']);
    Route::post('/schedules/conflicts/check', [ScheduleController::class, 'checkConflicts']);
    Route::get('/schedules/{id}', [ScheduleController::class, 'show']);
    Route::post('/schedules', [ScheduleController::class, 'store'])
        ->middleware('permission:academic.schedule.manage');
    Route::put('/schedules/{id}', [ScheduleController::class, 'update'])
        ->middleware('permission:academic.schedule.manage');
    Route::delete('/schedules/{id}', [ScheduleController::class, 'destroy'])
        ->middleware('permission:academic.schedule.manage');

    // =====================================================================
    // BE-ACD-02: Jurnal Mengajar
    // =====================================================================
    Route::get('/journals', [JournalController::class, 'index']);
    Route::get('/journals/{id}', [JournalController::class, 'show']);
    Route::post('/journals', [JournalController::class, 'store'])
        ->middleware('permission:academic.journal.write');
    Route::put('/journals/{id}', [JournalController::class, 'update'])
        ->middleware('permission:academic.journal.write');
    Route::delete('/journals/{id}', [JournalController::class, 'destroy'])
        ->middleware('permission:academic.journal.write');

    // =====================================================================
    // BE-ACD-03: Presensi Siswa & Permohonan Izin
    // =====================================================================
    // Presensi Sesi & Gerbang
    Route::get('/attendances', [AttendanceController::class, 'index']);
    Route::get('/attendances/recap', [AttendanceController::class, 'recap']);
    Route::post('/attendances', [AttendanceController::class, 'storeSession'])
        ->middleware('permission:academic.attendance.write');
    Route::post('/attendances/daily', [AttendanceController::class, 'storeDaily'])
        ->middleware('permission:academic.attendance.write');

    // Permohonan Izin / Sakit (BE-ACD-04)
    Route::get('/leave-requests', [LeaveRequestController::class, 'index']);
    Route::post('/leave-requests', [LeaveRequestController::class, 'store']);
    Route::get('/leave-requests/{id}', [LeaveRequestController::class, 'show']);
    Route::patch('/leave-requests/{id}/status', [LeaveRequestController::class, 'updateStatus']);
    Route::patch('/leave-requests/{id}/decision', [LeaveRequestController::class, 'decision']);

    // =====================================================================
    // BE-ACD-05: Presensi Guru & Staf
    // =====================================================================
    Route::get('/staff-attendances/today', [StaffAttendanceController::class, 'today']);
    Route::post('/staff-attendances/check-in', [StaffAttendanceController::class, 'checkIn']);
    Route::post('/staff-attendances/check-out', [StaffAttendanceController::class, 'checkOut']);
    Route::get('/staff-attendances/summary', [StaffAttendanceController::class, 'summary']);
    Route::get('/staff-attendances/settings', [StaffAttendanceController::class, 'getSettings']);
    Route::put('/staff-attendances/settings', [StaffAttendanceController::class, 'updateSettings']);
    Route::get('/staff-attendances', [StaffAttendanceController::class, 'index']);
    Route::post('/staff-attendances', [StaffAttendanceController::class, 'store']);
    Route::patch('/staff-attendances/{id}', [StaffAttendanceController::class, 'correct']);

    // =====================================================================
    // BE-ACD-06: Bank Modul Ajar & Perangkat
    // =====================================================================
    Route::get('/teaching-materials', [TeachingMaterialController::class, 'index']);
    Route::post('/teaching-materials', [TeachingMaterialController::class, 'store']);
    Route::get('/teaching-materials/{id}', [TeachingMaterialController::class, 'show']);
    Route::post('/teaching-materials/{id}/versions', [TeachingMaterialController::class, 'createVersion']);
    Route::delete('/teaching-materials/{id}', [TeachingMaterialController::class, 'destroy']);

    // Alias Blueprint ADM-ACD-06 (/learning-modules)
    Route::get('/learning-modules', [TeachingMaterialController::class, 'index']);
    Route::post('/learning-modules', [TeachingMaterialController::class, 'store']);
    Route::get('/learning-modules/{id}', [TeachingMaterialController::class, 'show']);
    Route::post('/learning-modules/{id}/versions', [TeachingMaterialController::class, 'createVersion']);
    Route::delete('/learning-modules/{id}', [TeachingMaterialController::class, 'destroy']);

    // =====================================================================
    // BE-ACD-07: Tugas & Materi Pembelajaran (LMS Ringan)
    // =====================================================================
    // Materi Pembelajaran
    Route::get('/materials', [MaterialController::class, 'index']);
    Route::post('/materials', [MaterialController::class, 'store']);
    Route::get('/materials/{id}', [MaterialController::class, 'show']);
    Route::put('/materials/{id}', [MaterialController::class, 'update']);
    Route::delete('/materials/{id}', [MaterialController::class, 'destroy']);

    // Tugas Pembelajaran
    Route::get('/assignments', [AssignmentController::class, 'index']);
    Route::post('/assignments', [AssignmentController::class, 'store']);
    Route::get('/assignments/{id}', [AssignmentController::class, 'show']);
    Route::put('/assignments/{id}', [AssignmentController::class, 'update']);
    Route::delete('/assignments/{id}', [AssignmentController::class, 'destroy']);

    // Pengumpulan & Penilaian Tugas
    Route::get('/assignments/{id}/submissions', [AssignmentController::class, 'submissions']);
    Route::post('/assignments/{id}/submissions', [AssignmentController::class, 'submit']);
    Route::patch('/assignments/{assignment_id}/submissions/{submission_id}/grade', [AssignmentController::class, 'grade']);

    // =====================================================================
    // BE-GRD-01: Komponen & Buku Nilai Bulanan
    // =====================================================================
    // Komponen Nilai
    Route::get('/grade-components', [GradeComponentController::class, 'index']);
    Route::post('/grade-components', [GradeComponentController::class, 'store'])
        ->middleware('permission:academic.grade.write');
    Route::put('/grade-components/{id}', [GradeComponentController::class, 'update'])
        ->middleware('permission:academic.grade.write');
    Route::delete('/grade-components/{id}', [GradeComponentController::class, 'destroy'])
        ->middleware('permission:academic.grade.write');

    // Periode Penilaian
    Route::get('/grade-periods', [GradePeriodController::class, 'index']);
    Route::patch('/grade-periods/{id}/lock', [GradePeriodController::class, 'lock'])
        ->middleware('permission:academic.grade.write');
    Route::patch('/grade-periods/{id}/lock-period', [GradePeriodController::class, 'lock'])
        ->middleware('permission:academic.grade.write');

    // Buku Nilai & Input Nilai (Satuan, Spreadsheet Matrix & Massal)
    Route::get('/grades', [GradeController::class, 'index']);
    Route::get('/grades/matrix', [GradeController::class, 'matrix']);
    Route::post('/grades', [GradeController::class, 'store'])
        ->middleware('permission:academic.grade.write');
    Route::post('/grades/bulk', [GradeController::class, 'bulk'])
        ->middleware('permission:academic.grade.write');
    Route::post('/grades/batch', [GradeController::class, 'bulk'])
        ->middleware('permission:academic.grade.write');
    Route::delete('/grades/{id}', [GradeController::class, 'destroy'])
        ->middleware('permission:academic.grade.write');

    // =====================================================================
    // BE-GRD-02: Catatan Karakter Bulanan
    // =====================================================================
    Route::get('/character-notes', [CharacterNoteController::class, 'index']);
    Route::get('/character-notes/matrix', [CharacterNoteController::class, 'matrix']);
    Route::get('/character-notes/class-overview', [CharacterNoteController::class, 'matrix']);
    Route::post('/character-notes', [CharacterNoteController::class, 'store'])
        ->middleware('permission:academic.grade.write');
    Route::post('/character-notes/bulk', [CharacterNoteController::class, 'bulk'])
        ->middleware('permission:academic.grade.write');
    Route::post('/character-notes/batch', [CharacterNoteController::class, 'bulk'])
        ->middleware('permission:academic.grade.write');
    Route::get('/character-notes/{id}', [CharacterNoteController::class, 'show']);
    Route::delete('/character-notes/{id}', [CharacterNoteController::class, 'destroy'])
        ->middleware('permission:academic.grade.write');

    // =====================================================================
    // BE-GRD-03: E-Progress Report Bulanan
    // =====================================================================
    Route::get('/progress-reports/class-status', [ProgressReportController::class, 'classStatus']);
    Route::get('/progress-reports/preview', [ProgressReportController::class, 'preview']);
    Route::post('/progress-reports/bulk-approve', [ProgressReportController::class, 'bulkApprove'])
        ->middleware('permission:academic.report.generate');
    Route::patch('/progress-reports/bulk-approve', [ProgressReportController::class, 'bulkApprove'])
        ->middleware('permission:academic.report.generate');
    Route::post('/progress-reports/generate', [ProgressReportController::class, 'generate'])
        ->middleware('permission:academic.report.generate,academic.grade.write');
    Route::get('/progress-reports', [ProgressReportController::class, 'index']);
    Route::get('/progress-reports/{id}', [ProgressReportController::class, 'show']);
    Route::patch('/progress-reports/{id}/approve', [ProgressReportController::class, 'approve'])
        ->middleware('permission:academic.report.generate');
    Route::get('/progress-reports/{id}/pdf', [ProgressReportController::class, 'pdf']);

    // =====================================================================
    // BE-GRD-04: E-Rapor Semester & Template Deskripsi Capaian
    // =====================================================================
    Route::get('/report-description-templates', [ReportDescriptionTemplateController::class, 'index']);
    Route::post('/report-description-templates', [ReportDescriptionTemplateController::class, 'store'])
        ->middleware('permission:academic.grade.write');
    Route::delete('/report-description-templates/{id}', [ReportDescriptionTemplateController::class, 'destroy'])
        ->middleware('permission:academic.grade.write');

    Route::get('/report-cards/class-status', [ReportCardController::class, 'classStatus']);
    Route::get('/report-cards/preview', [ReportCardController::class, 'preview']);
    Route::post('/report-cards/bulk-approve', [ReportCardController::class, 'bulkApprove'])
        ->middleware('permission:academic.report.generate');
    Route::patch('/report-cards/bulk-approve', [ReportCardController::class, 'bulkApprove'])
        ->middleware('permission:academic.report.generate');
    Route::post('/report-cards/bulk-lock', [ReportCardController::class, 'bulkLock'])
        ->middleware('permission:academic.report.generate,academic.grade.write');
    Route::patch('/report-cards/bulk-lock', [ReportCardController::class, 'bulkLock'])
        ->middleware('permission:academic.report.generate,academic.grade.write');
    Route::post('/report-cards/generate', [ReportCardController::class, 'generate'])
        ->middleware('permission:academic.report.generate,academic.grade.write');
    Route::get('/report-cards', [ReportCardController::class, 'index']);
    Route::get('/report-cards/{id}', [ReportCardController::class, 'show']);
    Route::patch('/report-cards/{id}/lock', [ReportCardController::class, 'lock'])
        ->middleware('permission:academic.report.generate,academic.grade.write');
    Route::patch('/report-cards/{id}/approve', [ReportCardController::class, 'approve'])
        ->middleware('permission:academic.report.generate');
    Route::get('/report-cards/{id}/pdf', [ReportCardController::class, 'pdf']);
});
