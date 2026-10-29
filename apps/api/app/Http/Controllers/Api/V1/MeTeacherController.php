<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Teacher\TeacherAttendanceController;
use App\Http\Controllers\Api\V1\Teacher\TeacherDashboardController;
use App\Http\Controllers\Api\V1\Teacher\TeacherExamController;
use App\Http\Controllers\Api\V1\Teacher\TeacherGradingController;
use App\Http\Controllers\Api\V1\Teacher\TeacherHomeroomController;
use App\Http\Controllers\Api\V1\Teacher\TeacherLeaveController;
use App\Http\Controllers\Api\V1\Teacher\TeacherLmsController;
use App\Http\Controllers\Api\V1\Teacher\TeacherPortalContextTrait;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeTeacherController extends Controller
{
    use ApiResponse;
    use TeacherPortalContextTrait;

    // =========================================================================
    // SUB-DOMAIN 1: DASHBOARD & JADWAL
    // =========================================================================

    public function dashboard(Request $request): JsonResponse
    {
        return app(TeacherDashboardController::class)->dashboard($request);
    }

    public function classes(Request $request): JsonResponse
    {
        return app(TeacherDashboardController::class)->classes($request);
    }

    public function schedules(Request $request): JsonResponse
    {
        return app(TeacherDashboardController::class)->schedules($request);
    }

    public function classStudents(Request $request, string $classId): JsonResponse
    {
        return app(TeacherDashboardController::class)->classStudents($request, $classId);
    }

    // =========================================================================
    // SUB-DOMAIN 2: PRESENSI & JURNAL KBM
    // =========================================================================

    public function attendanceSessions(Request $request): JsonResponse
    {
        return app(TeacherAttendanceController::class)->attendanceSessions($request);
    }

    public function recordAttendanceSession(Request $request): JsonResponse
    {
        return app(TeacherAttendanceController::class)->recordAttendanceSession($request);
    }

    public function journals(Request $request): JsonResponse
    {
        return app(TeacherAttendanceController::class)->journals($request);
    }

    public function storeJournal(Request $request): JsonResponse
    {
        return app(TeacherAttendanceController::class)->storeJournal($request);
    }

    // =========================================================================
    // SUB-DOMAIN 3: BUKU NILAI & PENGUNCIAN LEDGER
    // =========================================================================

    public function gradeComponents(Request $request): JsonResponse
    {
        return app(TeacherGradingController::class)->gradeComponents($request);
    }

    public function grades(Request $request): JsonResponse
    {
        return app(TeacherGradingController::class)->grades($request);
    }

    public function updateBulkGrades(Request $request): JsonResponse
    {
        return app(TeacherGradingController::class)->updateBulkGrades($request);
    }

    public function lockGrades(Request $request): JsonResponse
    {
        return app(TeacherGradingController::class)->lockGrades($request);
    }

    // =========================================================================
    // SUB-DOMAIN 4: LMS (MATERI & TUGAS)
    // =========================================================================

    public function materials(Request $request): JsonResponse
    {
        return app(TeacherLmsController::class)->materials($request);
    }

    public function storeMaterial(Request $request): JsonResponse
    {
        return app(TeacherLmsController::class)->storeMaterial($request);
    }

    public function assignments(Request $request): JsonResponse
    {
        return app(TeacherLmsController::class)->assignments($request);
    }

    public function storeAssignment(Request $request): JsonResponse
    {
        return app(TeacherLmsController::class)->storeAssignment($request);
    }

    public function submissions(Request $request, string $id): JsonResponse
    {
        return app(TeacherLmsController::class)->submissions($request, $id);
    }

    public function gradeSubmission(Request $request, string $id): JsonResponse
    {
        return app(TeacherLmsController::class)->gradeSubmission($request, $id);
    }

    // =========================================================================
    // SUB-DOMAIN 5: UJIAN & BANK SOAL
    // =========================================================================

    public function exams(Request $request): JsonResponse
    {
        return app(TeacherExamController::class)->exams($request);
    }

    public function questionBanks(Request $request): JsonResponse
    {
        return app(TeacherExamController::class)->questionBanks($request);
    }

    public function storeQuestionBank(Request $request): JsonResponse
    {
        return app(TeacherExamController::class)->storeQuestionBank($request);
    }

    public function showQuestionBank(Request $request, string $id): JsonResponse
    {
        return app(TeacherExamController::class)->showQuestionBank($request, $id);
    }

    public function updateQuestionBank(Request $request, string $id): JsonResponse
    {
        return app(TeacherExamController::class)->updateQuestionBank($request, $id);
    }

    public function destroyQuestionBank(Request $request, string $id): JsonResponse
    {
        return app(TeacherExamController::class)->destroyQuestionBank($request, $id);
    }

    public function storeQuestion(Request $request, string $id): JsonResponse
    {
        return app(TeacherExamController::class)->storeQuestion($request, $id);
    }

    public function showQuestion(Request $request, string $id): JsonResponse
    {
        return app(TeacherExamController::class)->showQuestion($request, $id);
    }

    public function updateQuestion(Request $request, string $id): JsonResponse
    {
        return app(TeacherExamController::class)->updateQuestion($request, $id);
    }

    public function destroyQuestion(Request $request, string $id): JsonResponse
    {
        return app(TeacherExamController::class)->destroyQuestion($request, $id);
    }

    public function storeExam(Request $request): JsonResponse
    {
        return app(TeacherExamController::class)->storeExam($request);
    }

    public function questions(Request $request): JsonResponse
    {
        return app(TeacherExamController::class)->questions($request);
    }

    public function storeDirectQuestion(Request $request): JsonResponse
    {
        return app(TeacherExamController::class)->storeDirectQuestion($request);
    }

    // =========================================================================
    // SUB-DOMAIN 6: PERIZINAN & PRESENSI MANDIRI GURU
    // =========================================================================

    public function leaveRequests(Request $request): JsonResponse
    {
        return app(TeacherLeaveController::class)->leaveRequests($request);
    }

    public function storeLeaveRequest(Request $request): JsonResponse
    {
        return app(TeacherLeaveController::class)->storeLeaveRequest($request);
    }

    public function selfAttendance(Request $request): JsonResponse
    {
        return app(TeacherLeaveController::class)->selfAttendance($request);
    }

    public function recordSelfAttendance(Request $request): JsonResponse
    {
        return app(TeacherLeaveController::class)->recordSelfAttendance($request);
    }

    // =========================================================================
    // SUB-DOMAIN 7: WALI KELAS, POIN, RUJUKAN, SARPRAS
    // =========================================================================

    public function homeroom(Request $request): JsonResponse
    {
        return app(TeacherHomeroomController::class)->homeroom($request);
    }

    public function homeroomLeaveRequests(Request $request): JsonResponse
    {
        return app(TeacherHomeroomController::class)->homeroomLeaveRequests($request);
    }

    public function decideHomeroomLeaveRequest(Request $request, string $id): JsonResponse
    {
        return app(TeacherHomeroomController::class)->decideHomeroomLeaveRequest($request, $id);
    }

    public function storeStudentPoint(Request $request): JsonResponse
    {
        return app(TeacherHomeroomController::class)->storeStudentPoint($request);
    }

    public function storeCounselingReferral(Request $request): JsonResponse
    {
        return app(TeacherHomeroomController::class)->storeCounselingReferral($request);
    }

    public function facilityBookings(Request $request): JsonResponse
    {
        return app(TeacherHomeroomController::class)->facilityBookings($request);
    }

    public function storeFacilityBooking(Request $request): JsonResponse
    {
        return app(TeacherHomeroomController::class)->storeFacilityBooking($request);
    }

    public function storeTicket(Request $request): JsonResponse
    {
        return app(TeacherHomeroomController::class)->storeTicket($request);
    }
}
