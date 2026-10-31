<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MeChildController;
use App\Http\Controllers\Api\V1\MeFinanceController;
use App\Http\Controllers\Api\V1\MeStaffController;
use App\Http\Controllers\Api\V1\MeStudentController;
use App\Http\Controllers\Api\V1\MeTeacherController;
use App\Http\Controllers\Api\V1\TeacherController;
use Illuminate\Support\Facades\Route;

// =========================================================================
// GAP-08 & PORTAL: Self-Service Endpoints (/me/*)
// =========================================================================
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/salary-slips', [TeacherController::class, 'mySalarySlips']);
    Route::get('/salary-slips/{slipId}/download', [TeacherController::class, 'downloadMySalarySlip']);

    // Portal Orang Tua
    Route::prefix('me/children')->group(function () {
        Route::get('/', [MeChildController::class, 'index']);
        Route::get('/{child}', [MeChildController::class, 'show']);
        Route::get('/{child}/attendance', [MeChildController::class, 'attendance']);
        Route::get('/{child}/grades', [MeChildController::class, 'grades']);
        Route::get('/{child}/report-cards', [MeChildController::class, 'reportCards']);
        Route::get('/{child}/character-notes', [MeChildController::class, 'characterNotes']);
        Route::get('/{child}/progress-reports', [MeChildController::class, 'progressReports']);
        Route::get('/{child}/invoices', [MeFinanceController::class, 'childInvoices']);
    });

    // Portal Siswa
    Route::prefix('me')->group(function () {
        Route::get('/student-dashboard', [MeStudentController::class, 'dashboard']);
        Route::get('/schedule', [MeStudentController::class, 'schedule']);
        Route::get('/grades', [MeStudentController::class, 'grades']);
        Route::get('/exams', [MeStudentController::class, 'exams']);
        Route::get('/tasks', [MeStudentController::class, 'tasks']);
        Route::post('/tasks/{id}/submit', [MeStudentController::class, 'submitTask']);
        Route::get('/counseling-requests', [MeStudentController::class, 'counselingRequests']);
        Route::post('/counseling-requests', [MeStudentController::class, 'storeCounselingRequest']);
        Route::post('/program/mood', [MeStudentController::class, 'submitDailyMood']);
        Route::post('/program/mutabaah', [MeStudentController::class, 'submitMutabaah']);
        // Keuangan (GAP-FIN-01)
        Route::get('/invoices', [MeFinanceController::class, 'myInvoices']);
        Route::get('/invoices/{id}', [MeFinanceController::class, 'showInvoice']);
        Route::post('/invoices/{id}/payments', [MeFinanceController::class, 'submitPayment']);
        Route::get('/payments/{id}/receipt', [MeFinanceController::class, 'getReceipt']);
        Route::get('/payment-providers', [MeFinanceController::class, 'paymentProviders']);
    });

    // Portal Guru
    Route::prefix('me/teacher')->group(function () {
        Route::get('/dashboard', [MeTeacherController::class, 'dashboard']);
        Route::get('/subjects', [MeTeacherController::class, 'subjects']);
        Route::get('/assignments/{id}', [MeTeacherController::class, 'showAssignment']);
        Route::get('/classes', [MeTeacherController::class, 'classes']);
        Route::get('/schedules', [MeTeacherController::class, 'schedules']);
        Route::get('/classes/{classId}/students', [MeTeacherController::class, 'classStudents']);
        Route::get('/attendance/sessions', [MeTeacherController::class, 'attendanceSessions']);
        Route::post('/attendance/sessions', [MeTeacherController::class, 'recordAttendanceSession']);
        Route::get('/journals', [MeTeacherController::class, 'journals']);
        Route::post('/journals', [MeTeacherController::class, 'storeJournal']);
        Route::get('/grade-components', [MeTeacherController::class, 'gradeComponents']);
        Route::get('/grades', [MeTeacherController::class, 'grades']);
        Route::post('/grades/bulk', [MeTeacherController::class, 'updateBulkGrades']);
        Route::get('/materials', [MeTeacherController::class, 'materials']);
        Route::post('/materials', [MeTeacherController::class, 'storeMaterial']);
        Route::get('/assignments', [MeTeacherController::class, 'assignments']);
        Route::post('/assignments', [MeTeacherController::class, 'storeAssignment']);
        Route::get('/assignments/{id}/submissions', [MeTeacherController::class, 'submissions']);
        Route::post('/submissions/{id}/grade', [MeTeacherController::class, 'gradeSubmission']);
        Route::get('/exams', [MeTeacherController::class, 'exams']);
        Route::post('/exams', [MeTeacherController::class, 'storeExam']);
        Route::get('/question-banks', [MeTeacherController::class, 'questionBanks']);
        Route::post('/question-banks', [MeTeacherController::class, 'storeQuestionBank']);
        Route::get('/question-banks/{id}', [MeTeacherController::class, 'showQuestionBank']);
        Route::put('/question-banks/{id}', [MeTeacherController::class, 'updateQuestionBank']);
        Route::delete('/question-banks/{id}', [MeTeacherController::class, 'destroyQuestionBank']);
        Route::post('/question-banks/{id}/questions', [MeTeacherController::class, 'storeQuestion']);
        Route::get('/questions', [MeTeacherController::class, 'questions']);
        Route::post('/questions', [MeTeacherController::class, 'storeDirectQuestion']);
        Route::get('/questions/{id}', [MeTeacherController::class, 'showQuestion']);
        Route::put('/questions/{id}', [MeTeacherController::class, 'updateQuestion']);
        Route::delete('/questions/{id}', [MeTeacherController::class, 'destroyQuestion']);
        Route::get('/leave-requests', [MeTeacherController::class, 'leaveRequests']);
        Route::post('/leave-requests', [MeTeacherController::class, 'storeLeaveRequest']);
        Route::get('/self-attendance', [MeTeacherController::class, 'selfAttendance']);
        Route::post('/self-attendance', [MeTeacherController::class, 'recordSelfAttendance']);
        Route::get('/homeroom', [MeTeacherController::class, 'homeroom']);

        // Remediasi Kesenjangan Portal Guru (Fitur A - E)
        Route::get('/homeroom/leave-requests', [MeTeacherController::class, 'homeroomLeaveRequests']);
        Route::patch('/homeroom/leave-requests/{id}/decision', [MeTeacherController::class, 'decideHomeroomLeaveRequest']);
        Route::post('/student-points', [MeTeacherController::class, 'storeStudentPoint']);
        Route::post('/counseling-referrals', [MeTeacherController::class, 'storeCounselingReferral']);
        Route::get('/facility-bookings', [MeTeacherController::class, 'facilityBookings']);
        Route::post('/facility-bookings', [MeTeacherController::class, 'storeFacilityBooking']);
        Route::post('/tickets', [MeTeacherController::class, 'storeTicket']);
        Route::post('/grades/lock', [MeTeacherController::class, 'lockGrades']);
    });

    // Portal Staf
    Route::prefix('me/staff')->group(function () {
        Route::get('/dashboard', [MeStaffController::class, 'dashboard']);
        Route::get('/self-attendance', [MeStaffController::class, 'selfAttendance']);
        Route::post('/self-attendance', [MeStaffController::class, 'recordSelfAttendance']);
        Route::get('/leave-requests', [MeStaffController::class, 'leaveRequests']);
        Route::post('/leave-requests', [MeStaffController::class, 'storeLeaveRequest']);
        Route::get('/tickets', [MeStaffController::class, 'tickets']);
    });
});

// TCH-PORTAL: Portal Guru Mandiri (Self-Service JJM & Slip Gaji)
Route::middleware('auth:sanctum')->prefix('teacher/me')->group(function () {
    Route::get('/salary-slips', [TeacherController::class, 'mySalarySlips']);
    Route::get('/salary-slips/{slipId}/download', [TeacherController::class, 'downloadMySalarySlip']);
    Route::get('/teaching-recap', [TeacherController::class, 'myTeachingRecap']);
});
