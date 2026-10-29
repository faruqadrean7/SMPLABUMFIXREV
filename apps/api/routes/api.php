<?php

use App\Http\Controllers\Api\V1\AcademicYearController;
use App\Http\Controllers\Api\V1\AdminAuthController;
use App\Http\Controllers\Api\V1\AlbumController;
use App\Http\Controllers\Api\V1\AssignmentController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Bk\CounselingCaseController;
use App\Http\Controllers\Api\V1\Bk\CounselingReferralController;
use App\Http\Controllers\Api\V1\Bk\CounselingRequestController;
use App\Http\Controllers\Api\V1\Bk\CounselingSessionController;
use App\Http\Controllers\Api\V1\Bk\EarlyWarningController;
use App\Http\Controllers\Api\V1\BulletinController;
use App\Http\Controllers\Api\V1\Cbt\ExamGradingController;
use App\Http\Controllers\Api\V1\Cbt\ExamParticipantCardController;
use App\Http\Controllers\Api\V1\Cbt\ExamReportController;
use App\Http\Controllers\Api\V1\Cbt\ExamRoomTokenController;
use App\Http\Controllers\Api\V1\Cbt\ExamSessionController;
use App\Http\Controllers\Api\V1\Cbt\ExamSyncController;
use App\Http\Controllers\Api\V1\CharacterNoteController;
use App\Http\Controllers\Api\V1\ClassController;
use App\Http\Controllers\Api\V1\ComplaintController;
use App\Http\Controllers\Api\V1\DownloadController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\ExamController;
use App\Http\Controllers\Api\V1\GradeComponentController;
use App\Http\Controllers\Api\V1\GradeController;
use App\Http\Controllers\Api\V1\GradePeriodController;
use App\Http\Controllers\Api\V1\GuardianController;
use App\Http\Controllers\Api\V1\GuestController;
use App\Http\Controllers\Api\V1\Hum\AlumniController;
use App\Http\Controllers\Api\V1\Hum\ExchangeProgramController;
use App\Http\Controllers\Api\V1\Hum\PartnershipController;
use App\Http\Controllers\Api\V1\Hum\TracerSurveyController;
use App\Http\Controllers\Api\V1\JournalController;
use App\Http\Controllers\Api\V1\LeaveRequestController;
use App\Http\Controllers\Api\V1\MaterialController;
use App\Http\Controllers\Api\V1\MeChildController;
use App\Http\Controllers\Api\V1\MeFinanceController;
use App\Http\Controllers\Api\V1\Finance\FeeTypeController;
use App\Http\Controllers\Api\V1\Finance\PaymentProviderSettingController;
use App\Http\Controllers\Api\V1\Finance\InvoiceController;
use App\Http\Controllers\Api\V1\Finance\PaymentController;
use App\Http\Controllers\Api\V1\Finance\FinanceReportController;
use App\Http\Controllers\Api\V1\MediaItemController;
use App\Http\Controllers\Api\V1\MeStaffController;
use App\Http\Controllers\Api\V1\MeStudentController;
use App\Http\Controllers\Api\V1\MeTeacherController;
use App\Http\Controllers\Api\V1\NavigationMenuController;
use App\Http\Controllers\Api\V1\Ntf\AnnouncementController;
use App\Http\Controllers\Api\V1\Ntf\MessagingController;
use App\Http\Controllers\Api\V1\Ntf\NotificationController;
use App\Http\Controllers\Api\V1\Ntf\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\PostCategoryController;
use App\Http\Controllers\Api\V1\PostController;
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
use App\Http\Controllers\Api\V1\ProgressReportController;
use App\Http\Controllers\Api\V1\PromotionController;
use App\Http\Controllers\Api\V1\PublicDirectoryController;
use App\Http\Controllers\Api\V1\PublicVerificationController;
use App\Http\Controllers\Api\V1\QuestionBankController;
use App\Http\Controllers\Api\V1\QuestionController;
use App\Http\Controllers\Api\V1\RbacController;
use App\Http\Controllers\Api\V1\ReportCardController;
use App\Http\Controllers\Api\V1\ReportDescriptionTemplateController;
use App\Http\Controllers\Api\V1\RoomController;
use App\Http\Controllers\Api\V1\Rpt\ApprovalController;
use App\Http\Controllers\Api\V1\Rpt\DashboardController;
use App\Http\Controllers\Api\V1\Rpt\ReportController;
use App\Http\Controllers\Api\V1\Sar\AssetAuditController;
use App\Http\Controllers\Api\V1\Sar\AssetController;
use App\Http\Controllers\Api\V1\Sar\BookingController;
use App\Http\Controllers\Api\V1\Sar\FacilityController;
use App\Http\Controllers\Api\V1\Sar\LabController;
use App\Http\Controllers\Api\V1\Sar\LibraryController;
use App\Http\Controllers\Api\V1\Sar\TicketController;
use App\Http\Controllers\Api\V1\ScheduleController;
use App\Http\Controllers\Api\V1\SemesterController;
use App\Http\Controllers\Api\V1\SiteSettingController;
use App\Http\Controllers\Api\V1\StaffAttendanceController;
use App\Http\Controllers\Api\V1\StaffController;
use App\Http\Controllers\Api\V1\Std\AchievementController;
use App\Http\Controllers\Api\V1\Std\CompetitionController;
use App\Http\Controllers\Api\V1\Std\ExtracurricularController;
use App\Http\Controllers\Api\V1\Std\PointTypeController;
use App\Http\Controllers\Api\V1\Std\StudentPointController;
use App\Http\Controllers\Api\V1\Std\TripController;
use App\Http\Controllers\Api\V1\Storage\FileController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\SubjectController;
use App\Http\Controllers\Api\V1\TeacherController;
use App\Http\Controllers\Api\V1\TeachingAssignmentController;
use App\Http\Controllers\Api\V1\TeachingMaterialController;
use App\Http\Controllers\Api\V1\TimeSlotController;
use App\Http\Controllers\Api\V1\TwoFactorAuthController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::get('/user', [AuthController::class, 'me'])->middleware('auth:sanctum');
    Route::get('/health', function () {
        $dbStatus = 'ok';
        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            report($e);
            $dbStatus = 'unreachable';
        }

        $redisStatus = 'ok';
        try {
            Redis::ping();
        } catch (Throwable $e) {
            report($e);
            $redisStatus = 'unreachable';
        }

        return response()->json([
            'status' => ($dbStatus === 'ok' && $redisStatus === 'ok') ? 'ok' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'services' => [
                'database' => $dbStatus,
                'redis' => $redisStatus,
            ],
            'version' => '1.1.0',
        ]);
    });

    // BE-GRD-03: Verifikasi Dokumen Publik via Token QR
    Route::get('/public/verify/{token}', [PublicVerificationController::class, 'verify']);

    // =========================================================================
    // BE-FILE-01: Signed Upload & Signed Download Routes
    // =========================================================================
    Route::post('/files/upload-signed/{token}', [FileController::class, 'uploadSigned'])->name('files.upload.signed');
    Route::get('/files/{id}/download', [FileController::class, 'downloadSigned'])->name('files.download.signed');

    // =========================================================================
    // BE-AUTH-01: Autentikasi & Sesi
    // =========================================================================
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/admin/login', [AdminAuthController::class, 'login']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,15');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:60,1');

        // GAP-SEC-02 & BATCH 4B & 5C: Manajemen Otentikasi Dua Faktor (2FA / TOTP)
        Route::post('/2fa/setup', [TwoFactorAuthController::class, 'setup'])->middleware('throttle:10,15');
        Route::post('/2fa/verify', [TwoFactorAuthController::class, 'verify'])->middleware('throttle:5,15');
        Route::post('/2fa/challenge', [AdminAuthController::class, 'login'])->middleware('throttle:10,15');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/change-password', [AuthController::class, 'changePassword']);

            Route::post('/2fa/disable', [TwoFactorAuthController::class, 'disable']);

            // BE-AUTH-04: Manajemen Sesi Perangkat
            Route::get('/sessions', [AuthController::class, 'sessions']);
            Route::delete('/sessions/{id}', [AuthController::class, 'revokeSession']);
        });
    });

    // BE-AUTH-04: Registrasi Token Perangkat FCM
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/devices', [AuthController::class, 'registerDevice']);
    });

    // =========================================================================
    // GAP-08: Portal Self-Service Endpoints (/me/*) & Aliases
    // =========================================================================
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/salary-slips', [TeacherController::class, 'mySalarySlips']);
        Route::get('/salary-slips/{slipId}/download', [TeacherController::class, 'downloadMySalarySlip']);
        Route::get('/me/notifications', [NotificationController::class, 'index']);
        Route::match(['post', 'patch'], '/me/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::patch('/me/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

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
            Route::get('/notification-preferences', [NotificationPreferenceController::class, 'index']);
            Route::put('/notification-preferences', [NotificationPreferenceController::class, 'update']);
            Route::get('/student-dashboard', [MeStudentController::class, 'dashboard']);
            Route::get('/schedule', [MeStudentController::class, 'schedule']);
            Route::get('/grades', [MeStudentController::class, 'grades']);
            Route::get('/exams', [MeStudentController::class, 'exams']);
            Route::get('/tasks', [MeStudentController::class, 'tasks']);
            Route::post('/tasks/{id}/submit', [MeStudentController::class, 'submitTask']);
            Route::get('/counseling-requests', [MeStudentController::class, 'counselingRequests']);
            Route::post('/counseling-requests', [MeStudentController::class, 'storeCounselingRequest']);
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

    // =========================================================================
    // BE-AUTH-03: RBAC (Roles & Permissions)
    // =========================================================================
    Route::middleware(['auth:sanctum', 'permission:auth.role.manage'])->group(function () {
        Route::get('/permissions', [RbacController::class, 'permissions']);

        Route::get('/roles', [RbacController::class, 'index']);
        Route::post('/roles', [RbacController::class, 'store']);
        Route::get('/roles/{id}', [RbacController::class, 'show']);
        Route::put('/roles/{id}', [RbacController::class, 'update']);
        Route::delete('/roles/{id}', [RbacController::class, 'destroy']);

        Route::get('/users/{id}/roles', [RbacController::class, 'getUserRoles']);
        Route::put('/users/{id}/roles', [RbacController::class, 'assignUserRoles']);
    });

    // =========================================================================
    // BE-AUTH-02: Manajemen Akun Pengguna (ADM-USR-01 s/d ADM-USR-04)
    // =========================================================================
    Route::middleware(['auth:sanctum', 'permission:auth.user.read'])->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{id}', [UserController::class, 'show']);
    });

    Route::middleware(['auth:sanctum', 'permission:auth.user.write'])->group(function () {
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::patch('/users/{id}/status', [UserController::class, 'changeStatus']);
        Route::post('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
    });

    // =========================================================================
    // BE-AUTH-05: Audit Log (Read-only)
    // =========================================================================
    Route::middleware(['auth:sanctum', 'permission:audit.read'])->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
        Route::get('/audit-logs/{id}', [AuditLogController::class, 'show']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/audit-logs', [AuditLogController::class, 'storeTelemetry']);
    });

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
    // TCH-PORTAL: Portal Guru Mandiri (Self-Service JJM & Slip Gaji)
    // =========================================================================
    Route::middleware('auth:sanctum')->prefix('teacher/me')->group(function () {
        Route::get('/salary-slips', [TeacherController::class, 'mySalarySlips']);
        Route::get('/salary-slips/{slipId}/download', [TeacherController::class, 'downloadMySalarySlip']);
        Route::get('/teaching-recap', [TeacherController::class, 'myTeachingRecap']);
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

    // =========================================================================
    // BE-CMS-01: CMS - Pengaturan Situs & Profil Sekolah
    // =========================================================================
    Route::get('/public/site', [SiteSettingController::class, 'publicSite']);

    Route::middleware(['auth:sanctum', 'permission:cms.site.manage'])->group(function () {
        Route::get('/site-settings', [SiteSettingController::class, 'show']);
        Route::put('/site-settings', [SiteSettingController::class, 'update']);
    });

    // =========================================================================
    // BE-CMS-02: CMS - Berita & Pengumuman
    // =========================================================================
    Route::get('/public/posts', [PostController::class, 'publicIndex']);
    Route::get('/public/posts/{slug}', [PostController::class, 'publicShow']);
    Route::get('/post-categories', [PostCategoryController::class, 'index']);

    Route::middleware(['auth:sanctum', 'permission:cms.post.manage'])->group(function () {
        // Categories Write
        Route::post('/post-categories', [PostCategoryController::class, 'store']);
        Route::get('/post-categories/{id}', [PostCategoryController::class, 'show']);
        Route::put('/post-categories/{id}', [PostCategoryController::class, 'update']);
        Route::delete('/post-categories/{id}', [PostCategoryController::class, 'destroy']);

        // Posts Write
        Route::get('/posts', [PostController::class, 'index']);
        Route::post('/posts', [PostController::class, 'store']);
        Route::get('/posts/{id}', [PostController::class, 'show']);
        Route::put('/posts/{id}', [PostController::class, 'update']);
        Route::delete('/posts/{id}', [PostController::class, 'destroy']);
    });

    // =========================================================================
    // BE-CMS-03: CMS - Galeri & Mading Digital
    // =========================================================================
    Route::get('/public/albums', [AlbumController::class, 'publicIndex']);
    Route::get('/public/albums/{id}', [AlbumController::class, 'publicShow']);
    Route::get('/public/bulletins', [BulletinController::class, 'publicIndex']);
    Route::get('/public/bulletins/{id}', [BulletinController::class, 'publicShow']);

    Route::middleware(['auth:sanctum', 'permission:cms.gallery.manage'])->group(function () {
        // Albums
        Route::get('/albums', [AlbumController::class, 'index']);
        Route::post('/albums', [AlbumController::class, 'store']);
        Route::get('/albums/{id}', [AlbumController::class, 'show']);
        Route::put('/albums/{id}', [AlbumController::class, 'update']);
        Route::delete('/albums/{id}', [AlbumController::class, 'destroy']);

        // Media Items
        Route::post('/media', [MediaItemController::class, 'store']);
        Route::put('/media/{id}', [MediaItemController::class, 'update']);
        Route::delete('/media/{id}', [MediaItemController::class, 'destroy']);

        // Bulletins
        Route::get('/bulletins', [BulletinController::class, 'index']);
        Route::post('/bulletins', [BulletinController::class, 'store']);
        Route::get('/bulletins/{id}', [BulletinController::class, 'show']);
        Route::put('/bulletins/{id}', [BulletinController::class, 'update']);
        Route::delete('/bulletins/{id}', [BulletinController::class, 'destroy']);
    });

    // =========================================================================
    // BE-CMS-04: CMS - Agenda & Kalender Kegiatan
    // =========================================================================
    Route::get('/public/events', [EventController::class, 'publicIndex']);
    Route::get('/public/events/{id}', [EventController::class, 'publicShow']);

    Route::middleware(['auth:sanctum', 'permission:cms.event.manage'])->group(function () {
        Route::get('/events', [EventController::class, 'index']);
        Route::post('/events', [EventController::class, 'store']);
        Route::get('/events/{id}', [EventController::class, 'show']);
        Route::put('/events/{id}', [EventController::class, 'update']);
        Route::delete('/events/{id}', [EventController::class, 'destroy']);
    });

    // =========================================================================
    // BE-CMS-05: CMS - Direktori Guru & Staf Publik
    // =========================================================================
    Route::get('/public/teachers', [PublicDirectoryController::class, 'teachers']);
    Route::get('/public/teachers/{id}', [PublicDirectoryController::class, 'showTeacher']);
    Route::get('/public/staff', [PublicDirectoryController::class, 'staff']);

    // =========================================================================
    // BE-CMS-06: CMS - Pusat Unduhan
    // =========================================================================
    Route::get('/public/downloads', [DownloadController::class, 'publicIndex']);
    Route::get('/public/downloads/{id}', [DownloadController::class, 'publicShow']);

    Route::middleware(['auth:sanctum', 'permission:cms.download.manage'])->group(function () {
        Route::get('/downloads', [DownloadController::class, 'index']);
        Route::post('/downloads', [DownloadController::class, 'store']);
        Route::get('/downloads/{id}', [DownloadController::class, 'show']);
        Route::put('/downloads/{id}', [DownloadController::class, 'update']);
        Route::delete('/downloads/{id}', [DownloadController::class, 'destroy']);
    });

    // =========================================================================
    // BE-CMS-07: CMS - Halaman Statis & Menu Navigasi
    // =========================================================================
    Route::get('/public/pages', [PageController::class, 'publicIndex']);
    Route::get('/public/pages/{slug}', [PageController::class, 'publicShow']);
    Route::get('/public/navigation-menu', [NavigationMenuController::class, 'publicShow']);

    Route::middleware(['auth:sanctum', 'permission:cms.page.manage'])->group(function () {
        // Pages
        Route::get('/pages', [PageController::class, 'index']);
        Route::post('/pages', [PageController::class, 'store']);
        Route::get('/pages/{id}', [PageController::class, 'show']);
        Route::put('/pages/{id}', [PageController::class, 'update']);
        Route::delete('/pages/{id}', [PageController::class, 'destroy']);

        // Navigation Menu
        Route::get('/navigation-menu', [NavigationMenuController::class, 'show']);
        Route::put('/navigation-menu', [NavigationMenuController::class, 'update']);
    });

    // =========================================================================
    // BE-PPDB-01: PPDB - Konfigurasi Gelombang, Jalur & Syarat
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
        Route::patch('/complaints/{id}/status', [ComplaintController::class, 'updateStatus']);

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
        Route::patch('/grade-periods/{id}/lock', [GradePeriodController::class, 'lock']);
        Route::patch('/grade-periods/{id}/lock-period', [GradePeriodController::class, 'lock']);

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
        Route::post('/progress-reports/bulk-approve', [ProgressReportController::class, 'bulkApprove']);
        Route::patch('/progress-reports/bulk-approve', [ProgressReportController::class, 'bulkApprove']);
        Route::post('/progress-reports/generate', [ProgressReportController::class, 'generate']);
        Route::get('/progress-reports', [ProgressReportController::class, 'index']);
        Route::get('/progress-reports/{id}', [ProgressReportController::class, 'show']);
        Route::patch('/progress-reports/{id}/approve', [ProgressReportController::class, 'approve']);
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
        Route::post('/report-cards/bulk-approve', [ReportCardController::class, 'bulkApprove']);
        Route::patch('/report-cards/bulk-approve', [ReportCardController::class, 'bulkApprove']);
        Route::post('/report-cards/bulk-lock', [ReportCardController::class, 'bulkLock']);
        Route::patch('/report-cards/bulk-lock', [ReportCardController::class, 'bulkLock']);
        Route::post('/report-cards/generate', [ReportCardController::class, 'generate']);
        Route::get('/report-cards', [ReportCardController::class, 'index']);
        Route::get('/report-cards/{id}', [ReportCardController::class, 'show']);
        Route::patch('/report-cards/{id}/lock', [ReportCardController::class, 'lock']);
        Route::patch('/report-cards/{id}/approve', [ReportCardController::class, 'approve']);
        Route::get('/report-cards/{id}/pdf', [ReportCardController::class, 'pdf']);

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

        // Alias paket soal sesuai PRD /question-packages
        Route::get('/question-packages', [QuestionBankController::class, 'index'])
            ->middleware('permission:cbt.bank.write');
        Route::post('/question-packages', [QuestionBankController::class, 'store'])
            ->middleware('permission:cbt.bank.write');
        Route::get('/question-packages/{id}', [QuestionBankController::class, 'show'])
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
        Route::post('/exams/{id}/auto-grade', [ExamGradingController::class, 'autoGrade']);
        Route::get('/exams/{id}/essay-submissions', [ExamGradingController::class, 'essaySubmissions']);
        Route::post('/exams/{id}/grade-essay', [ExamGradingController::class, 'gradeEssay']);
        Route::get('/exams/{id}/results', [ExamGradingController::class, 'results']);
        Route::get('/exams/{id}/item-analysis', [ExamGradingController::class, 'itemAnalysis']);
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

        // =====================================================================
        // BE-STD-01: Kesiswaan - Poin Pelanggaran & Prestasi
        // =====================================================================
        // Master Jenis Poin
        Route::get('/point-types', [PointTypeController::class, 'indexTypes']);
        Route::post('/point-types', [PointTypeController::class, 'storeType']);
        Route::get('/point-types/{id}', [PointTypeController::class, 'showType']);
        Route::put('/point-types/{id}', [PointTypeController::class, 'updateType']);
        Route::delete('/point-types/{id}', [PointTypeController::class, 'destroyType']);

        // Master Ambang Batas Poin / Sanksi
        Route::get('/point-thresholds', [PointTypeController::class, 'indexThresholds']);
        Route::post('/point-thresholds', [PointTypeController::class, 'storeThreshold']);
        Route::get('/point-thresholds/{id}', [PointTypeController::class, 'showThreshold']);
        Route::put('/point-thresholds/{id}', [PointTypeController::class, 'updateThreshold']);
        Route::delete('/point-thresholds/{id}', [PointTypeController::class, 'destroyThreshold']);

        // Pencatatan Poin Siswa
        Route::get('/student-points', [StudentPointController::class, 'index']);
        Route::post('/student-points', [StudentPointController::class, 'store']);
        Route::get('/student-points/{id}', [StudentPointController::class, 'show']);
        Route::delete('/student-points/{id}', [StudentPointController::class, 'destroy']);

        // Ringkasan Poin Siswa
        Route::get('/students/{id}/points/summary', [StudentPointController::class, 'summary']);

        // =====================================================================
        // BE-STD-02: Kesiswaan - Ekstrakurikuler & OSIS
        // =====================================================================
        // Ekstrakurikuler
        Route::get('/extracurriculars', [ExtracurricularController::class, 'index']);
        Route::post('/extracurriculars', [ExtracurricularController::class, 'store']);
        Route::get('/extracurriculars/{id}', [ExtracurricularController::class, 'show']);
        Route::put('/extracurriculars/{id}', [ExtracurricularController::class, 'update']);
        Route::delete('/extracurriculars/{id}', [ExtracurricularController::class, 'destroy']);

        // Anggota Ekstrakurikuler (Pendaftaran & Penilaian)
        Route::get('/extracurricular-members', [ExtracurricularController::class, 'indexMembers']);
        Route::post('/extracurricular-members', [ExtracurricularController::class, 'registerMember']);
        Route::put('/extracurricular-members/{id}', [ExtracurricularController::class, 'updateMember']);
        Route::delete('/extracurricular-members/{id}', [ExtracurricularController::class, 'removeMember']);

        // Jurnal Kegiatan & Presensi
        Route::get('/extracurricular-journals', [ExtracurricularController::class, 'indexJournals']);
        Route::post('/extracurricular-journals', [ExtracurricularController::class, 'storeJournal']);
        Route::get('/extracurricular-journals/{id}', [ExtracurricularController::class, 'showJournal']);
        Route::delete('/extracurricular-journals/{id}', [ExtracurricularController::class, 'destroyJournal']);

        // Dokumen Kegiatan / Proposal / Laporan (OSIS & Ekskul)
        Route::get('/activity-documents', [ExtracurricularController::class, 'indexDocuments']);
        Route::post('/activity-documents', [ExtracurricularController::class, 'storeDocument']);
        Route::get('/activity-documents/{id}', [ExtracurricularController::class, 'showDocument']);
        Route::delete('/activity-documents/{id}', [ExtracurricularController::class, 'destroyDocument']);

        // =====================================================================
        // BE-STD-03: Kesiswaan - Lomba & Prestasi
        // =====================================================================
        // Kompetisi & Delegasi Lomba
        Route::get('/competitions', [CompetitionController::class, 'index']);
        Route::get('/competitions/summary', [CompetitionController::class, 'summary']);
        Route::post('/competitions', [CompetitionController::class, 'store']);
        Route::get('/competitions/{id}', [CompetitionController::class, 'show']);
        Route::put('/competitions/{id}', [CompetitionController::class, 'update']);
        Route::delete('/competitions/{id}', [CompetitionController::class, 'destroy']);

        Route::get('/competitions/{id}/entries', [CompetitionController::class, 'indexEntries']);
        Route::post('/competitions/{id}/entries', [CompetitionController::class, 'storeEntry']);
        Route::put('/competition-entries/{id}', [CompetitionController::class, 'updateEntry']);
        Route::delete('/competition-entries/{id}', [CompetitionController::class, 'destroyEntry']);

        // Prestasi & Galeri Piala
        Route::get('/achievements', [AchievementController::class, 'index']);
        Route::post('/achievements', [AchievementController::class, 'store']);
        Route::get('/achievements/{id}', [AchievementController::class, 'show']);
        Route::put('/achievements/{id}', [AchievementController::class, 'update']);
        Route::patch('/achievements/{id}/verify', [AchievementController::class, 'verify']);
        Route::delete('/achievements/{id}', [AchievementController::class, 'destroy']);

        // =====================================================================
        // BE-STD-04: Kesiswaan - Study Trip & Outing Class
        // =====================================================================
        // Master Perjalanan (Trips)
        Route::get('/trips', [TripController::class, 'index']);
        Route::post('/trips', [TripController::class, 'store']);
        Route::get('/trips/{id}', [TripController::class, 'show']);
        Route::put('/trips/{id}', [TripController::class, 'update']);
        Route::delete('/trips/{id}', [TripController::class, 'destroy']);

        // Peserta Perjalanan (Participants)
        Route::get('/trips/{id}/participants', [TripController::class, 'indexParticipants']);
        Route::post('/trips/{id}/participants', [TripController::class, 'storeParticipant']);
        Route::delete('/trip-participants/{id}', [TripController::class, 'destroyParticipant']);

        // Izin Digital Orang Tua (Parental Consents)
        Route::get('/trips/{id}/consents', [TripController::class, 'indexConsents']);
        Route::post('/trips/{id}/consents', [TripController::class, 'storeConsent']);

        // =====================================================================
        // BE-BK-01: Bimbingan & Konseling (Kasus, Sesi, dan Rujukan)
        // =====================================================================
        // Kasus Konseling
        Route::get('/counseling/cases', [CounselingCaseController::class, 'index']);
        Route::post('/counseling/cases', [CounselingCaseController::class, 'store']);
        Route::get('/counseling/cases/{id}', [CounselingCaseController::class, 'show']);
        Route::put('/counseling/cases/{id}', [CounselingCaseController::class, 'update']);
        Route::post('/counseling/cases/{id}/sessions', [CounselingCaseController::class, 'storeSession']);

        // Sesi Konseling
        Route::get('/counseling/sessions/{id}', [CounselingSessionController::class, 'show']);

        // Rujukan Konseling
        Route::get('/counseling/referrals', [CounselingReferralController::class, 'index']);
        Route::post('/counseling/referrals', [CounselingReferralController::class, 'store']);
        Route::patch('/counseling/referrals/{id}/status', [CounselingReferralController::class, 'updateStatus']);

        // =====================================================================
        // BE-BK-02: Deteksi Dini / Early Warning System
        // =====================================================================
        Route::get('/early-warnings/summary', [EarlyWarningController::class, 'summary']);
        Route::get('/early-warnings', [EarlyWarningController::class, 'index']);
        Route::post('/early-warnings/evaluate', [EarlyWarningController::class, 'evaluate']);
        Route::get('/early-warnings/{id}', [EarlyWarningController::class, 'show']);
        Route::patch('/early-warnings/{id}/handle', [EarlyWarningController::class, 'handle']);

        // =====================================================================
        // BE-BK-03: Permintaan Konseling (Counseling Requests)
        // =====================================================================
        Route::get('/counseling/requests', [CounselingRequestController::class, 'index']);
        Route::post('/counseling/requests', [CounselingRequestController::class, 'store']);
        Route::get('/counseling/requests/{id}', [CounselingRequestController::class, 'show']);
        Route::patch('/counseling/requests/{id}/schedule', [CounselingRequestController::class, 'schedule']);
        Route::patch('/counseling/requests/{id}/reject', [CounselingRequestController::class, 'reject']);
        Route::patch('/counseling/requests/{id}/complete', [CounselingRequestController::class, 'complete']);
        Route::delete('/counseling/requests/{id}', [CounselingRequestController::class, 'cancel']);

        // =====================================================================
        // BE-SAR-01: Sarana & Prasarana - Booking Fasilitas & Ruang
        // =====================================================================
        // Fasilitas
        Route::get('/facilities', [FacilityController::class, 'index']);
        Route::get('/facilities/{id}', [FacilityController::class, 'show']);
        Route::post('/facilities', [FacilityController::class, 'store']);
        Route::put('/facilities/{id}', [FacilityController::class, 'update']);
        Route::delete('/facilities/{id}', [FacilityController::class, 'destroy']);

        // Peminjaman & Cek Ketersediaan
        Route::get('/bookings/availability', [BookingController::class, 'availability']);
        Route::get('/bookings/summary', [BookingController::class, 'summary']);
        Route::get('/bookings', [BookingController::class, 'index']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::get('/bookings/{id}', [BookingController::class, 'show']);
        Route::patch('/bookings/{id}/decision', [BookingController::class, 'decision']);
        Route::delete('/bookings/{id}', [BookingController::class, 'cancel']);

        // =====================================================================
        // BE-SAR-02: Sarana & Prasarana - Aset & Inventaris QR
        // =====================================================================
        // Aset & Lookup QR
        Route::get('/assets/summary', [AssetController::class, 'summary']);
        Route::get('/assets', [AssetController::class, 'index']);
        Route::get('/assets/by-code/{code}', [AssetController::class, 'byCode']);
        Route::get('/assets/{id}/qr', [AssetController::class, 'qr']);
        Route::get('/assets/{id}', [AssetController::class, 'show']);
        Route::post('/assets', [AssetController::class, 'store']);
        Route::put('/assets/{id}', [AssetController::class, 'update']);
        Route::delete('/assets/{id}', [AssetController::class, 'destroy']);

        // Sesi Stok Opname Aset
        Route::get('/asset-audits', [AssetAuditController::class, 'index']);
        Route::get('/asset-audits/{id}', [AssetAuditController::class, 'show']);
        Route::post('/asset-audits', [AssetAuditController::class, 'store']);
        Route::patch('/asset-audits/{id}', [AssetAuditController::class, 'update']);

        // =====================================================================
        // BE-SAR-03: Sarana & Prasarana - Helpdesk Perbaikan Sarpras
        // =====================================================================
        Route::get('/tickets/summary', [TicketController::class, 'summary']);
        Route::get('/tickets/technicians', [TicketController::class, 'technicians']);
        Route::get('/tickets', [TicketController::class, 'index']);
        Route::post('/tickets', [TicketController::class, 'store']);
        Route::get('/tickets/{id}', [TicketController::class, 'show']);
        Route::post('/tickets/{id}/dispose', [TicketController::class, 'dispose']);
        Route::patch('/tickets/{id}/status', [TicketController::class, 'status']);
        Route::post('/tickets/{id}/comments', [TicketController::class, 'comment']);

        // =====================================================================
        // BE-SAR-04: Sarana & Prasarana - Perpustakaan & Alat Lab
        // =====================================================================
        // Perpustakaan: Buku & Ringkasan
        Route::get('/library/summary', [LibraryController::class, 'summary']);
        Route::get('/library/books', [LibraryController::class, 'indexBooks']);
        Route::post('/library/books', [LibraryController::class, 'storeBook']);
        Route::get('/library/books/{id}', [LibraryController::class, 'showBook']);
        Route::put('/library/books/{id}', [LibraryController::class, 'updateBook']);
        Route::delete('/library/books/{id}', [LibraryController::class, 'destroyBook']);

        // Perpustakaan: Peminjaman & Pengembalian
        Route::get('/library/loans', [LibraryController::class, 'indexLoans']);
        Route::post('/library/loans', [LibraryController::class, 'storeLoan']);
        Route::get('/library/loans/{id}', [LibraryController::class, 'showLoan']);
        Route::post('/library/loans/{id}/return', [LibraryController::class, 'returnLoan']);

        // Laboratorium: Alat Lab & Ringkasan
        Route::get('/lab-equipment/summary', [LabController::class, 'summary']);
        Route::get('/lab-equipment', [LabController::class, 'indexEquipment']);
        Route::post('/lab-equipment', [LabController::class, 'storeEquipment']);
        Route::get('/lab-equipment/{id}', [LabController::class, 'showEquipment']);
        Route::put('/lab-equipment/{id}', [LabController::class, 'updateEquipment']);
        Route::delete('/lab-equipment/{id}', [LabController::class, 'destroyEquipment']);

        // Laboratorium: Peminjaman & Pengembalian Alat Lab
        Route::get('/lab-loans', [LabController::class, 'indexLoans']);
        Route::post('/lab-loans', [LabController::class, 'storeLoan']);
        Route::get('/lab-loans/{id}', [LabController::class, 'showLoan']);
        Route::post('/lab-loans/{id}/return', [LabController::class, 'returnLoan']);

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

        // =====================================================================
        // BE-NTF-01: Notifikasi In-App & Push
        // =====================================================================
        // Registrasi Perangkat (Devices & Push Tokens)
        Route::delete('/devices/{token}', [NotificationController::class, 'destroyDevice']);

        // Inbox Notifikasi Pengguna & Deep Link
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/{id}', [NotificationController::class, 'show']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

        // Broadcast Notifikasi Tersegmentasi
        Route::post('/notifications/broadcast', [NotificationController::class, 'broadcast']);

        // =====================================================================
        // BE-NTF-02: WhatsApp & Email (Template Berparameter & Log Pengiriman)
        // =====================================================================
        // Template Notifikasi
        Route::get('/notification-templates', [MessagingController::class, 'indexTemplates']);
        Route::post('/notification-templates', [MessagingController::class, 'storeTemplate']);
        Route::get('/notification-templates/{id}', [MessagingController::class, 'showTemplate']);
        Route::put('/notification-templates/{id}', [MessagingController::class, 'updateTemplate']);
        Route::delete('/notification-templates/{id}', [MessagingController::class, 'destroyTemplate']);
        Route::post('/notification-templates/send', [MessagingController::class, 'send']);

        // Riwayat & Log Pengiriman
        Route::get('/notification-logs', [MessagingController::class, 'indexLogs']);
        Route::get('/notification-logs/{id}', [MessagingController::class, 'showLog']);
        Route::post('/notification-logs/{id}/retry', [MessagingController::class, 'retry']);

        // =====================================================================
        // BE-NTF-03: Pengumuman Terarah (Terjadwal & Pelacakan Pembaca)
        // =====================================================================
        Route::get('/announcements', [AnnouncementController::class, 'index']);
        Route::post('/announcements', [AnnouncementController::class, 'store']);
        Route::get('/announcements/{id}', [AnnouncementController::class, 'show']);
        Route::put('/announcements/{id}', [AnnouncementController::class, 'update']);
        Route::delete('/announcements/{id}', [AnnouncementController::class, 'destroy']);
        Route::post('/announcements/{id}/read', [AnnouncementController::class, 'markAsRead']);
        Route::get('/announcements/{id}/readers', [AnnouncementController::class, 'readers']);

        // =====================================================================
        // BE-NTF-04: Preferensi Notifikasi (Kanal & Jenis Notifikasi)
        // =====================================================================
        Route::get('/notification-preferences', [NotificationPreferenceController::class, 'index']);
        Route::put('/notification-preferences', [NotificationPreferenceController::class, 'update']);
        Route::get('/notification-preferences/types', [NotificationPreferenceController::class, 'types']);

        // =====================================================================
        // BE-RPT-01: Laporan Rekapitulasi (Kehadiran, Nilai, PPDB, Poin, dll)
        // =====================================================================
        Route::get('/reports/{type}/export', [ReportController::class, 'export']);
        Route::get('/reports/{type}', [ReportController::class, 'show']);

        // =====================================================================
        // BE-RPT-02: Dashboard Persona (Eksekutif, Guru, Siswa, Wali Murid)
        // =====================================================================
        Route::get('/dashboard/executive', [DashboardController::class, 'executive']);
        Route::get('/dashboard/teacher', [DashboardController::class, 'teacher']);
        Route::get('/dashboard/student', [DashboardController::class, 'student']);
        Route::get('/dashboard/guardian/{studentId?}', [DashboardController::class, 'guardian']);

        // =====================================================================
        // BE-RPT-02 / ADM-DASH-02: Antrean & Eksekusi Persetujuan Eksekutif
        // =====================================================================
        Route::get('/approvals/pending', [ApprovalController::class, 'pending']);
        Route::post('/approvals/{id}/review', [ApprovalController::class, 'review']);

        // =====================================================================
        // BE-FILE-01: Penyimpanan & Utilitas Berkas (Upload Bertanda Tangan)
        // =====================================================================
        Route::post('/files/presign', [FileController::class, 'presign']);
        Route::post('/files/confirm', [FileController::class, 'confirm']);
        Route::get('/files/{id}/url', [FileController::class, 'url']);

        // =====================================================================
        // GAP-FIN-01: Modul Keuangan Sekolah (Bendahara & Admin)
        // =====================================================================
        Route::prefix('finance')->group(function () {
            // Master Jenis Biaya (finance.fee.manage)
            Route::get('/fee-types', [FeeTypeController::class, 'index'])->middleware('permission:finance.fee.manage');
            Route::post('/fee-types', [FeeTypeController::class, 'store'])->middleware('permission:finance.fee.manage');
            Route::get('/fee-types/{id}', [FeeTypeController::class, 'show'])->middleware('permission:finance.fee.manage');
            Route::put('/fee-types/{id}', [FeeTypeController::class, 'update'])->middleware('permission:finance.fee.manage');
            Route::delete('/fee-types/{id}', [FeeTypeController::class, 'destroy'])->middleware('permission:finance.fee.manage');

            // Pengaturan Penyedia Pembayaran Pihak Ketiga (finance.fee.manage)
            Route::get('/settings/providers', [PaymentProviderSettingController::class, 'index'])->middleware('permission:finance.fee.manage');
            Route::post('/settings/providers', [PaymentProviderSettingController::class, 'store'])->middleware('permission:finance.fee.manage');
            Route::get('/settings/providers/{id}', [PaymentProviderSettingController::class, 'show'])->middleware('permission:finance.fee.manage');
            Route::put('/settings/providers/{id}', [PaymentProviderSettingController::class, 'update'])->middleware('permission:finance.fee.manage');
            Route::delete('/settings/providers/{id}', [PaymentProviderSettingController::class, 'destroy'])->middleware('permission:finance.fee.manage');

            // Tagihan / Invoices (finance.invoice.manage)
            Route::get('/invoices', [InvoiceController::class, 'index'])->middleware('permission:finance.invoice.manage');
            Route::post('/invoices', [InvoiceController::class, 'store'])->middleware('permission:finance.invoice.manage');
            Route::post('/invoices/generate-bulk', [InvoiceController::class, 'generateBulk'])->middleware('permission:finance.invoice.manage');
            Route::get('/invoices/{id}', [InvoiceController::class, 'show'])->middleware('permission:finance.invoice.manage');
            Route::post('/invoices/{id}/cancel', [InvoiceController::class, 'cancel'])->middleware('permission:finance.invoice.manage');

            // Pembayaran / Payments & Verifikasi (finance.payment.verify)
            Route::get('/payments', [PaymentController::class, 'index'])->middleware('permission:finance.payment.verify');
            Route::get('/payments/{id}', [PaymentController::class, 'show'])->middleware('permission:finance.payment.verify');
            Route::post('/payments/{id}/verify', [PaymentController::class, 'verify'])->middleware('permission:finance.payment.verify');
            Route::post('/payments/{id}/reject', [PaymentController::class, 'reject'])->middleware('permission:finance.payment.verify');
            Route::post('/payments/{id}/void', [PaymentController::class, 'void'])->middleware('permission:finance.payment.verify');
            Route::get('/payments/{id}/receipt', [PaymentController::class, 'receipt'])->middleware('permission:finance.payment.verify');

            // Laporan Keuangan (finance.report.view)
            Route::get('/reports/arrears', [FinanceReportController::class, 'arrears'])->middleware('permission:finance.report.view');
            Route::get('/reports/income', [FinanceReportController::class, 'income'])->middleware('permission:finance.report.view');
        });
    });

    // =========================================================================
    // BE-CBT-11: Mode Server Lokal & Sinkronisasi Paket Ujian Offline
    // =========================================================================
    Route::post('/cbt/sync/pull', [ExamSyncController::class, 'pull'])->middleware('throttle:15,1');
    Route::post('/cbt/sync/push', [ExamSyncController::class, 'push'])->middleware('throttle:15,1');
});
