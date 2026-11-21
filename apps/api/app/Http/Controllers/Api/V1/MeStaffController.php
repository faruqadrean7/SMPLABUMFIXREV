<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\Ticket;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeStaffController extends Controller
{
    use ApiResponse;

    protected function getStaff(Request $request): ?Staff
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        return $user->staff;
    }

    /**
     * GET /api/v1/me/staff/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $staff = $this->getStaff($request);

        $today = now()->toDateString();
        $record = StaffAttendance::where('user_id', $user->id)
            ->where('att_date', $today)
            ->first();

        $workHours = StaffAttendance::where('user_id', $user->id)
            ->whereMonth('att_date', now()->month)
            ->whereYear('att_date', now()->year)
            ->count() * 8;

        $pendingLeaves = LeaveRequest::where('requested_by', $user->id)
            ->where('status', 'menunggu')
            ->count();

        $openTickets = Ticket::where('reporter_id', $user->id)
            ->whereIn('status', ['pending', 'diproses'])
            ->count();

        $resolvedTickets = Ticket::where('reporter_id', $user->id)
            ->where('status', 'selesai')
            ->count();

        return $this->successResponse([
            'staffName' => $staff?->full_name ?? $user->username,
            'department' => $staff?->unit ?? 'Tata Usaha & Operasional',
            'todayAttendance' => [
                'todayDate' => $today,
                'checkInTime' => $record?->check_in?->format('H:i:s'),
                'checkOutTime' => $record?->check_out?->format('H:i:s'),
                'status' => $record ? ($record->status ?? 'present') : 'not_checked_in',
                'workHoursThisMonth' => $workHours,
                'targetWorkHours' => 160,
                'attendancePercentage' => 96.0,
            ],
            'pendingLeavesCount' => $pendingLeaves,
            'openTicketsCount' => $openTickets,
            'resolvedTicketsCount' => $resolvedTickets,
            'recentAnnouncements' => [],
        ]);
    }

    /**
     * GET /api/v1/me/staff/self-attendance
     */
    public function selfAttendance(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = now()->toDateString();

        $record = StaffAttendance::where('user_id', $user->id)
            ->where('att_date', $today)
            ->first();

        $workHours = StaffAttendance::where('user_id', $user->id)
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
            'attendancePercentage' => 96.0,
        ]);
    }

    /**
     * POST /api/v1/me/staff/self-attendance
     */
    public function recordSelfAttendance(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'action' => 'required|in:check_in,check_out',
        ]);

        $today = now()->toDateString();
        $record = StaffAttendance::where('user_id', $user->id)
            ->whereDate('att_date', $today)
            ->first();

        if (! $record) {
            $record = new StaffAttendance([
                'user_id' => $user->id,
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
            'message' => 'Presensi kerja berhasil dicatat.',
            'timestamp' => now()->format('H:i:s'),
        ]);
    }

    /**
     * GET /api/v1/me/staff/leave-requests
     */
    public function leaveRequests(Request $request): JsonResponse
    {
        $user = $request->user();

        $leaves = LeaveRequest::where('requested_by', $user->id)
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
     * POST /api/v1/me/staff/leave-requests
     */
    public function storeLeaveRequest(Request $request): JsonResponse
    {
        $user = $request->user();

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
            'requested_by' => $user->id,
            'leave_type' => $leaveType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => $validated['reason'],
            'status' => 'menunggu',
        ]);

        return $this->successResponse($leave, 201);
    }

    /**
     * GET /api/v1/me/staff/tickets
     * Hanya menampilkan tiket yang dilaporkan oleh staf yang login.
     */
    public function tickets(Request $request): JsonResponse
    {
        $user = $request->user();

        $tickets = Ticket::where('reporter_id', $user->id)
            ->latest()
            ->get();

        $data = $tickets->map(fn (Ticket $t) => [
            'id' => $t->id,
            'ticket_number' => $t->ticket_number,
            'code' => $t->ticket_number,
            'title' => $t->title,
            'category' => 'Sarpras',
            'location' => 'Area Sekolah',
            'description' => $t->description ?? '',
            'priority' => $t->priority ?? 'sedang',
            'status' => $t->status ?? 'pending',
            'reportedAt' => $t->created_at?->toISOString() ?? now()->toISOString(),
            'updatedAt' => $t->updated_at?->toISOString(),
            'resolutionNotes' => null,
        ]);

        return $this->successResponse($data);
    }
}
