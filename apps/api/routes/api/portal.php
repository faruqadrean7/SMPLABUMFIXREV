<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MeChildController;
use App\Http\Controllers\Api\V1\MeFinanceController;
use App\Http\Controllers\Api\V1\MeStaffController;
use App\Http\Controllers\Api\V1\MeStudentController;
use App\Http\Controllers\Api\V1\MeStudentProgramController;
use App\Http\Controllers\Api\V1\MeTeacherController;
use App\Http\Controllers\Api\V1\Teacher\TeacherAttendanceController;
use App\Http\Controllers\Api\V1\Teacher\TeacherDashboardController;
use App\Http\Controllers\Api\V1\Teacher\TeacherExamController;
use App\Http\Controllers\Api\V1\Teacher\TeacherGradingController;
use App\Http\Controllers\Api\V1\Teacher\TeacherHomeroomController;
use App\Http\Controllers\Api\V1\Teacher\TeacherLeaveController;
use App\Http\Controllers\Api\V1\Teacher\TeacherLmsController;
use App\Http\Controllers\Api\V1\TeacherController;
use App\Http\Controllers\Api\V1\TeacherProgramController;
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
        Route::get('/{child}/points', [MeChildController::class, 'points']);
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
        Route::get('/points', [MeStudentController::class, 'points']);
        Route::get('/extracurriculars', [MeStudentController::class, 'extracurriculars']);
        Route::post('/extracurriculars/register', [MeStudentController::class, 'registerExtracurricular']);
        Route::post('/program/mood', [MeStudentController::class, 'submitDailyMood']);
        Route::post('/program/mutabaah', [MeStudentController::class, 'submitMutabaah']);
        // BE-PROG-01: Program Khusus (Tahfidz, ICP, Inklusi)
        Route::get('/program/materials', [MeStudentProgramController::class, 'materials']);
        Route::get('/program/targets', [MeStudentProgramController::class, 'targets']);
        Route::get('/program/tasks', [MeStudentProgramController::class, 'tasks']);
        Route::post('/program/tasks/{id}/submit', [MeStudentProgramController::class, 'submitTask']);
        // Keuangan (GAP-FIN-01)
        Route::get('/invoices', [MeFinanceController::class, 'myInvoices']);
        Route::get('/invoices/{id}', [MeFinanceController::class, 'showInvoice']);
        Route::post('/invoices/{id}/payments', [MeFinanceController::class, 'submitPayment']);
        Route::get('/payments/{id}/receipt', [MeFinanceController::class, 'getReceipt']);
        Route::get('/payment-providers', [MeFinanceController::class, 'paymentProviders']);
    });

    // Portal Guru (BE-02: De-monolith MeTeacherController ke Sub-Domain Controllers)
    Route::prefix('me/teacher')->group(function () {
        // Sub-domain 1: Dashboard, Jadwal & Kelas
        Route::get('/dashboard', [TeacherDashboardController::class, 'dashboard']);
        Route::get('/subjects', [TeacherDashboardController::class, 'subjects']);
        Route::get('/classes', [TeacherDashboardController::class, 'classes']);
        Route::get('/schedules', [TeacherDashboardController::class, 'schedules']);
        Route::get('/classes/{classId}/students', [TeacherDashboardController::class, 'classStudents']);

        // Sub-domain 2: Presensi & Jurnal KBM
        Route::get('/attendance/sessions', [TeacherAttendanceController::class, 'attendanceSessions']);
        Route::post('/attendance/sessions', [TeacherAttendanceController::class, 'recordAttendanceSession']);
        Route::get('/journals', [TeacherAttendanceController::class, 'journals']);
        Route::post('/journals', [TeacherAttendanceController::class, 'storeJournal']);

        // Sub-domain 3: Buku Nilai & Penguncian Ledger
        Route::get('/grade-components', [TeacherGradingController::class, 'gradeComponents']);
        Route::get('/grades', [TeacherGradingController::class, 'grades']);
        Route::post('/grades/bulk', [TeacherGradingController::class, 'updateBulkGrades']);
        Route::post('/grades/lock', [TeacherGradingController::class, 'lockGrades']);

        // Sub-domain 4: LMS (Materi & Tugas)
        Route::get('/materials', [TeacherLmsController::class, 'materials']);
        Route::post('/materials', [TeacherLmsController::class, 'storeMaterial']);
        Route::get('/assignments', [TeacherLmsController::class, 'assignments']);
        Route::post('/assignments', [TeacherLmsController::class, 'storeAssignment']);
        Route::get('/assignments/{id}', [TeacherLmsController::class, 'showAssignment']);
        Route::get('/assignments/{id}/submissions', [TeacherLmsController::class, 'submissions']);
        Route::post('/submissions/{id}/grade', [TeacherLmsController::class, 'gradeSubmission']);
        // Pemilih periode penilaian (dipakai halaman penilaian tugas)
        Route::get('/periods', [TeacherLmsController::class, 'periods']);

        // Sub-domain 5: Ujian & Bank Soal CBT
        Route::get('/exams', [TeacherExamController::class, 'exams']);
        Route::post('/exams', [TeacherExamController::class, 'storeExam']);
        Route::get('/question-banks', [TeacherExamController::class, 'questionBanks']);
        Route::post('/question-banks', [TeacherExamController::class, 'storeQuestionBank']);
        Route::get('/question-banks/{id}', [TeacherExamController::class, 'showQuestionBank']);
        Route::put('/question-banks/{id}', [TeacherExamController::class, 'updateQuestionBank']);
        Route::delete('/question-banks/{id}', [TeacherExamController::class, 'destroyQuestionBank']);
        Route::post('/question-banks/{id}/questions', [TeacherExamController::class, 'storeQuestion']);
        Route::get('/questions', [TeacherExamController::class, 'questions']);
        Route::post('/questions', [TeacherExamController::class, 'storeDirectQuestion']);
        Route::get('/questions/{id}', [TeacherExamController::class, 'showQuestion']);
        Route::put('/questions/{id}', [TeacherExamController::class, 'updateQuestion']);
        Route::delete('/questions/{id}', [TeacherExamController::class, 'destroyQuestion']);

        // Sub-domain 6: Perizinan & Presensi Mandiri Guru
        Route::get('/leave-requests', [TeacherLeaveController::class, 'leaveRequests']);
        Route::post('/leave-requests', [TeacherLeaveController::class, 'storeLeaveRequest']);
        Route::get('/self-attendance', [TeacherLeaveController::class, 'selfAttendance']);
        Route::post('/self-attendance', [TeacherLeaveController::class, 'recordSelfAttendance']);

        // Sub-domain 7: Wali Kelas, Poin, Konseling, Sarpras & Tiket
        Route::get('/homeroom', [TeacherHomeroomController::class, 'homeroom']);
        Route::get('/homeroom/leave-requests', [TeacherHomeroomController::class, 'homeroomLeaveRequests']);
        Route::patch('/homeroom/leave-requests/{id}/decision', [TeacherHomeroomController::class, 'decideHomeroomLeaveRequest']);
        Route::get('/student-points', [TeacherHomeroomController::class, 'studentPoints']);
        Route::post('/student-points', [TeacherHomeroomController::class, 'storeStudentPoint']);
        Route::get('/counseling-referrals', [TeacherHomeroomController::class, 'counselingReferrals']);
        Route::post('/counseling-referrals', [TeacherHomeroomController::class, 'storeCounselingReferral']);
        Route::get('/facilities', [TeacherHomeroomController::class, 'facilities']);
        Route::get('/facility-bookings', [TeacherHomeroomController::class, 'facilityBookings']);
        Route::post('/facility-bookings', [TeacherHomeroomController::class, 'storeFacilityBooking']);
        Route::get('/tickets', [TeacherHomeroomController::class, 'tickets']);
        Route::post('/tickets', [TeacherHomeroomController::class, 'storeTicket']);
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

    // =========================================================================
    // BE-PROG-02: API Pengelolaan Program Khusus Guru Pembina & Admin (P1)
    // =========================================================================
    Route::prefix('program')->group(function () {
        // Materi Program
        Route::get('/materials', [TeacherProgramController::class, 'indexMaterials']);
        Route::post('/materials', [TeacherProgramController::class, 'storeMaterial']);
        Route::patch('/materials/{id}', [TeacherProgramController::class, 'updateMaterial']);
        Route::delete('/materials/{id}', [TeacherProgramController::class, 'destroyMaterial']);

        // Target Capaian Program
        Route::get('/targets', [TeacherProgramController::class, 'indexTargets']);
        Route::post('/targets', [TeacherProgramController::class, 'storeTarget']);
        Route::patch('/targets/{id}', [TeacherProgramController::class, 'updateTarget']);
        Route::delete('/targets/{id}', [TeacherProgramController::class, 'destroyTarget']);

        // Tugas Program
        Route::get('/tasks', [TeacherProgramController::class, 'indexTasks']);
        Route::post('/tasks', [TeacherProgramController::class, 'storeTask']);
        Route::patch('/tasks/{id}', [TeacherProgramController::class, 'updateTask']);
        Route::delete('/tasks/{id}', [TeacherProgramController::class, 'destroyTask']);

        // Submissions & Penilaian
        Route::get('/tasks/{id}/submissions', [TeacherProgramController::class, 'taskSubmissions']);
        Route::patch('/submissions/{id}', [TeacherProgramController::class, 'gradeSubmission']);
    });
});

// TCH-PORTAL: Portal Guru Mandiri (Self-Service JJM & Slip Gaji)
Route::middleware('auth:sanctum')->prefix('teacher/me')->group(function () {
    Route::get('/salary-slips', [TeacherController::class, 'mySalarySlips']);
    Route::get('/salary-slips/{slipId}/download', [TeacherController::class, 'downloadMySalarySlip']);
    Route::get('/teaching-recap', [TeacherController::class, 'myTeachingRecap']);
});
