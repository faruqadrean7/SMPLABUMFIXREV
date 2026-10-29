<?php

use App\Http\Controllers\Api\V1\AcademicYearController;
use App\Http\Controllers\Api\V1\ClassController;
use App\Http\Controllers\Api\V1\GuardianController;
use App\Http\Controllers\Api\V1\PromotionController;
use App\Http\Controllers\Api\V1\RoomController;
use App\Http\Controllers\Api\V1\SemesterController;
use App\Http\Controllers\Api\V1\StaffController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\SubjectController;
use App\Http\Controllers\Api\V1\TeacherController;
use App\Http\Controllers\Api\V1\TeachingAssignmentController;
use Illuminate\Support\Facades\Route;

// =========================================================================
// BE-MST-01: Master Data - Tahun Ajaran & Semester
// =========================================================================
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/academic-years/active', [AcademicYearController::class, 'active']);
});

Route::middleware(['auth:sanctum', 'permission:master.academic_year.manage'])->group(function () {
    Route::get('/academic-years', [AcademicYearController::class, 'index']);
    Route::post('/academic-years', [AcademicYearController::class, 'store']);
    Route::get('/academic-years/{id}', [AcademicYearController::class, 'show']);
    Route::put('/academic-years/{id}', [AcademicYearController::class, 'update']);
    Route::patch('/academic-years/{id}/activate', [AcademicYearController::class, 'activate']);
    Route::delete('/academic-years/{id}', [AcademicYearController::class, 'destroy']);

    Route::get('/semesters', [SemesterController::class, 'index']);
    Route::post('/semesters', [SemesterController::class, 'store']);
    Route::get('/semesters/{id}', [SemesterController::class, 'show']);
    Route::put('/semesters/{id}', [SemesterController::class, 'update']);
    Route::patch('/semesters/{id}/activate', [SemesterController::class, 'activate']);
    Route::delete('/semesters/{id}', [SemesterController::class, 'destroy']);
});

// =========================================================================
// BE-MST-02: Master Data - Siswa
// =========================================================================
Route::middleware('auth:sanctum')->group(function () {
    // Export: master.student.read
    Route::get('/students/export', [StudentController::class, 'export'])
        ->middleware('permission:master.student.read');

    // Write actions: master.student.write
    Route::post('/students/import', [StudentController::class, 'import'])
        ->middleware('permission:master.student.write');
    Route::post('/students', [StudentController::class, 'store'])
        ->middleware('permission:master.student.write');
    Route::put('/students/{id}', [StudentController::class, 'update'])
        ->middleware('permission:master.student.write');
    Route::delete('/students/{id}', [StudentController::class, 'destroy'])
        ->middleware('permission:master.student.write');
    Route::post('/students/{id}/status', [StudentController::class, 'changeStatus'])
        ->middleware('permission:master.student.write');

    // Scoped listing & detail (Service enforces data scoping & IDOR)
    Route::get('/students', [StudentController::class, 'index']);
    Route::get('/students/{id}', [StudentController::class, 'show']);
    Route::get('/students/{id}/status-history', [StudentController::class, 'statusHistory']);
});

// =========================================================================
// BE-MST-03: Master Data - Guru & Staf
// =========================================================================
Route::middleware(['auth:sanctum', 'permission:master.teacher.manage'])->group(function () {
    // Teachers
    Route::post('/teachers/import', [TeacherController::class, 'import']);
    Route::get('/teachers', [TeacherController::class, 'index']);
    Route::post('/teachers', [TeacherController::class, 'store']);
    Route::get('/teachers/{id}', [TeacherController::class, 'show']);
    Route::put('/teachers/{id}', [TeacherController::class, 'update']);
    Route::delete('/teachers/{id}', [TeacherController::class, 'destroy']);
    Route::get('/teachers/{id}/teaching-recap', [TeacherController::class, 'teachingRecap']);

    // Teacher Salary Slips (Admin TU / Keuangan)
    Route::get('/teachers/{id}/salary-slips', [TeacherController::class, 'listSalarySlips']);
    Route::post('/teachers/{id}/salary-slips', [TeacherController::class, 'storeSalarySlip']);
    Route::get('/teachers/{id}/salary-slips/{slipId}', [TeacherController::class, 'showSalarySlip']);
    Route::get('/teachers/{id}/salary-slips/{slipId}/download', [TeacherController::class, 'downloadSalarySlip']);
    Route::delete('/teachers/{id}/salary-slips/{slipId}', [TeacherController::class, 'destroySalarySlip']);

    // Staff
    Route::post('/staff/import', [StaffController::class, 'import']);
    Route::get('/staff', [StaffController::class, 'index']);
    Route::post('/staff', [StaffController::class, 'store']);
    Route::get('/staff/{id}', [StaffController::class, 'show']);
    Route::put('/staff/{id}', [StaffController::class, 'update']);
    Route::delete('/staff/{id}', [StaffController::class, 'destroy']);
});

// =========================================================================
// BE-MST-04: Master Data - Orang Tua / Wali & Relasi
// =========================================================================
Route::middleware('auth:sanctum')->group(function () {
    // Scoped viewing (Guardians only view themselves, admin sees all)
    Route::get('/guardians', [GuardianController::class, 'index']);
    Route::get('/guardians/{id}', [GuardianController::class, 'show']);
    Route::get('/students/{id}/guardians', [GuardianController::class, 'studentGuardians']);

    // Write operations: permission:master.student.write
    Route::post('/guardians', [GuardianController::class, 'store'])
        ->middleware('permission:master.student.write');
    Route::put('/guardians/{id}', [GuardianController::class, 'update'])
        ->middleware('permission:master.student.write');
    Route::delete('/guardians/{id}', [GuardianController::class, 'destroy'])
        ->middleware('permission:master.student.write');

    // Student-Guardian linking operations
    Route::post('/students/{id}/guardians', [GuardianController::class, 'attachStudentGuardian'])
        ->middleware('permission:master.student.write');
    Route::put('/students/{id}/guardians/{guardianId}', [GuardianController::class, 'updateStudentGuardian'])
        ->middleware('permission:master.student.write');
    Route::delete('/students/{id}/guardians/{guardianId}', [GuardianController::class, 'detachStudentGuardian'])
        ->middleware('permission:master.student.write');
});

// =========================================================================
// BE-MST-05: Master Data - Kelas & Rombel
// =========================================================================
Route::middleware('auth:sanctum')->group(function () {
    // Reference Lookups
    Route::get('/grade-levels', [ClassController::class, 'gradeLevels']);
    Route::get('/majors', [ClassController::class, 'majors']);

    Route::middleware('permission:master.class.manage')->group(function () {
        // Classes CRUD
        Route::get('/classes', [ClassController::class, 'index']);
        Route::post('/classes', [ClassController::class, 'store']);
        Route::get('/classes/{id}', [ClassController::class, 'show']);
        Route::put('/classes/{id}', [ClassController::class, 'update']);
        Route::delete('/classes/{id}', [ClassController::class, 'destroy']);

        // Class Rosters & Transfers
        Route::get('/classes/{id}/students', [ClassController::class, 'students']);
        Route::post('/classes/{id}/students', [ClassController::class, 'addStudents']);
        Route::post('/classes/{id}/students/transfer', [ClassController::class, 'transferStudent']);
        Route::delete('/classes/{id}/students/{studentId}', [ClassController::class, 'removeStudent']);
    });
});

// =========================================================================
// BE-MST-06: Master Data - Mata Pelajaran & Penugasan Guru
// =========================================================================
Route::middleware(['auth:sanctum', 'permission:master.subject.manage'])->group(function () {
    // Subjects
    Route::get('/subjects', [SubjectController::class, 'index']);
    Route::post('/subjects', [SubjectController::class, 'store']);
    Route::get('/subjects/{id}', [SubjectController::class, 'show']);
    Route::put('/subjects/{id}', [SubjectController::class, 'update']);
    Route::delete('/subjects/{id}', [SubjectController::class, 'destroy']);

    // Teaching Assignments
    Route::get('/teaching-assignments', [TeachingAssignmentController::class, 'index']);
    Route::post('/teaching-assignments/batch', [TeachingAssignmentController::class, 'batch']);
    Route::post('/teaching-assignments', [TeachingAssignmentController::class, 'store']);
    Route::get('/teaching-assignments/{id}', [TeachingAssignmentController::class, 'show']);
    Route::put('/teaching-assignments/{id}', [TeachingAssignmentController::class, 'update']);
    Route::delete('/teaching-assignments/{id}', [TeachingAssignmentController::class, 'destroy']);
});

// =========================================================================
// BE-MST-07: Master Data - Ruang & Fasilitas Dasar
// =========================================================================
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/rooms/types', [RoomController::class, 'types']);

    Route::middleware('permission:master.room.manage')->group(function () {
        Route::get('/rooms', [RoomController::class, 'index']);
        Route::post('/rooms', [RoomController::class, 'store']);
        Route::get('/rooms/{id}', [RoomController::class, 'show']);
        Route::put('/rooms/{id}', [RoomController::class, 'update']);
        Route::delete('/rooms/{id}', [RoomController::class, 'destroy']);
    });
});

// =========================================================================
// BE-MST-08: Master Data - Kenaikan Kelas & Kelulusan
// =========================================================================
Route::middleware(['auth:sanctum', 'permission:master.promotion.manage'])->group(function () {
    Route::post('/promotions/preview', [PromotionController::class, 'preview']);
    Route::post('/promotions/execute', [PromotionController::class, 'execute']);
    Route::post('/promotions/rollback', [PromotionController::class, 'rollback']);
});
