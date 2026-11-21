<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\StaffAttendance;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherLeaveController extends Controller
{
    use ApiResponse;
    use TeacherPortalContextTrait;

    /**
     * GET /api/v1/me/teacher/leave-requests
     */
    public function leaveRequests(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $leaves = LeaveRequest::where('requested_by', $request->user()->id)
            ->latest()
            ->get();

        $data = $leaves->map(fn (LeaveRequest $l) => [
            'id' => $l->id,
            'leaveType' => $l->leave_type,
            'startDate' => $l->start_date?->format('Y-m-d') ?? (string) $l->start_date,
            'endDate' => $l->end_date?->format('Y-m-d') ?? (string) $l->end_date,
            'daysCount' => Carbon::parse($l->start_date)->diffInDays(Carbon::parse($l->end_date)) + 1,
            'reason' => $l->reason,
            'status' => match ($l->status) {
                'disetujui' => 'approved',
                'ditolak' => 'rejected',
                default => 'pending',
            },
            'reviewNote' => $l->decision_notes,
            'createdAt' => $l->created_at?->toISOString() ?? now()->toISOString(),
        ]);

        return $this->successResponse($data);
    }

    /**
     * POST /api/v1/me/teacher/leave-requests
     */
    public function storeLeaveRequest(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $validated = $request->validate([
            'leave_type' => 'nullable|string',
            'leaveType' => 'nullable|string',
            'start_date' => 'nullable|date',
            'startDate' => 'nullable|date',
            'end_date' => 'nullable|date',
            'endDate' => 'nullable|date',
            'reason' => 'required|string|max:1000',
        ]);

        $rawType = $validated['leave_type'] ?? ($validated['leaveType'] ?? 'izin');
        $leaveType = match ($rawType) {
            'sick', 'Sakit' => 'sakit',
            'duty', 'Dispensasi', 'dispen' => 'dispen',
            'annual', 'family', 'Izin', 'izin' => 'izin',
            default => $rawType,
        };
        $startDate = $validated['start_date'] ?? ($validated['startDate'] ?? now()->toDateString());
        $endDate = $validated['end_date'] ?? ($validated['endDate'] ?? now()->toDateString());

        $leave = LeaveRequest::create([
            'student_id' => null,
            'requested_by' => $request->user()->id,
            'leave_type' => $leaveType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => $validated['reason'],
            'status' => 'menunggu',
        ]);

        return $this->successResponse($leave, 201);
    }

    /**
     * GET /api/v1/me/teacher/self-attendance
     */
    public function selfAttendance(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $today = now()->toDateString();
        $record = StaffAttendance::where('user_id', $request->user()->id)
            ->whereDate('att_date', $today)
            ->first();

        $workHours = StaffAttendance::where('user_id', $request->user()->id)
            ->whereMonth('att_date', now()->month)
            ->whereYear('att_date', now()->year)
            ->count() * 8;

        return $this->successResponse([
            'todayDate' => $today,
            'checkInTime' => $record?->check_in?->format('H:i:s'),
            'checkOutTime' => $record?->check_out?->format('H:i:s'),
            'status' => $record ? ($record->status ?? 'present') : 'not_checked_in',
            'workHoursThisMonth' => $workHours,
            'targetWorkHours' => 160,
            'attendancePercentage' => 95.0,
        ]);
    }

    /**
     * POST /api/v1/me/teacher/self-attendance
     */
    public function recordSelfAttendance(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $validated = $request->validate([
            'action' => 'required|in:check_in,check_out',
        ]);

        $today = now()->toDateString();
        $record = StaffAttendance::where('user_id', $request->user()->id)
            ->whereDate('att_date', $today)
            ->first();

        if (! $record) {
            $record = new StaffAttendance([
                'user_id' => $request->user()->id,
                'att_date' => $today,
            ]);
        }

        if ($validated['action'] === 'check_in') {
            $record->check_in = now();
            $record->status = 'hadir';
        } else {
            $record->check_out = now();
        }
        $record->save();

        return $this->successResponse([
            'success' => true,
            'message' => 'Presensi berhasil dicatat.',
            'timestamp' => now()->format('H:i:s'),
        ]);
    }
}
