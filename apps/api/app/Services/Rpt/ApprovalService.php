<?php

namespace App\Services\Rpt;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\ReportCard;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ApprovalService
{
    /**
     * Authorize user to view executive pending approvals.
     */
    protected function authorizeViewApprovals(?User $user): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        $allowed = $user->hasRole(['super_admin', 'admin', 'principal', 'admin_tu', 'kurikulum', 'kesiswaan', 'student_affairs']) ||
            $user->hasPermission('executive.approval.manage') ||
            $user->hasPermission('dashboard.executive') ||
            $user->hasPermission('executive.dashboard.view');

        if (! $allowed) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang melihat antrean persetujuan eksekutif.');
        }
    }

    /**
     * Authorize user to execute executive approval decisions (approve/reject).
     */
    protected function authorizeManageApprovals(?User $user): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        $allowed = $user->hasRole(['super_admin', 'admin', 'principal']) ||
            $user->hasPermission('executive.approval.manage');

        if (! $allowed) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang mengambil keputusan persetujuan eksekutif.');
        }
    }

    /**
     * ADM-DASH-02: Get aggregated pending approvals for executive.
     */
    public function getPendingApprovals(Request $request): array
    {
        $user = $request->user();
        $this->authorizeViewApprovals($user);

        $schoolId = $user?->school_id;
        $items = [];

        // 1. Leave Requests (Izin & Cuti) - SEC-APPR-03: Include canonical status 'menunggu'
        $leaveQuery = LeaveRequest::with(['student', 'requester'])
            ->whereIn('status', ['menunggu', 'diajukan', 'pending'])
            ->latest();

        if ($schoolId) {
            $leaveQuery->where(function ($q) use ($schoolId) {
                $q->whereHas('student', fn ($s) => $s->where('school_id', $schoolId))
                    ->orWhereHas('requester', fn ($r) => $r->where('school_id', $schoolId));
            });
        }

        foreach ($leaveQuery->take(25)->get() as $leave) {
            $applicantName = $leave->student?->full_name ?? $leave->requester?->name ?? 'Pemohon Izin';
            $applicantRole = $leave->student ? 'Siswa' : 'Tenaga Pendidik / Staf';
            $startDate = $leave->start_date ? Carbon::parse($leave->start_date)->format('Y-m-d') : '';
            $endDate = $leave->end_date ? Carbon::parse($leave->end_date)->format('Y-m-d') : '';
            $dateRange = $startDate === $endDate ? $startDate : "{$startDate} s.d. {$endDate}";

            $items[] = [
                'id' => (string) $leave->id,
                'code' => 'APP-LEAVE-'.strtoupper(substr(str_replace('-', '', (string) $leave->id), 0, 6)),
                'category' => 'leave_request',
                'title' => 'Permohonan Izin / Cuti: '.$applicantName,
                'requesterName' => $applicantName,
                'requesterRole' => $applicantRole,
                'requester_name' => $applicantName,
                'requester_role' => $applicantRole,
                'details' => trim(($leave->reason ? $leave->reason.' ' : '').($dateRange ? "({$dateRange})" : '')),
                'submittedAt' => $leave->created_at?->toIso8601String() ?? Carbon::now()->toIso8601String(),
                'submitted_at' => $leave->created_at?->toIso8601String() ?? Carbon::now()->toIso8601String(),
                'status' => 'pending',
            ];
        }

        // 2. Facility Bookings (Peminjaman Fasilitas Sarpras)
        $bookingQuery = Booking::with(['facility', 'requester'])
            ->whereIn('status', ['diajukan', 'pending'])
            ->latest();

        if ($schoolId) {
            $bookingQuery->where('school_id', $schoolId);
        }

        foreach ($bookingQuery->take(25)->get() as $booking) {
            $facilityName = $booking->facility?->name ?? 'Fasilitas Sekolah';
            $applicantName = $booking->requester?->name ?? 'Pemohon Fasilitas';
            $startAt = $booking->start_at ? Carbon::parse($booking->start_at)->format('Y-m-d H:i') : '';

            $items[] = [
                'id' => (string) $booking->id,
                'code' => 'APP-SARPRAS-'.strtoupper(substr(str_replace('-', '', (string) $booking->id), 0, 6)),
                'category' => 'facility_purchase',
                'title' => 'Peminjaman Fasilitas: '.$facilityName,
                'requesterName' => $applicantName,
                'requesterRole' => 'Pengguna Sarpras',
                'requester_name' => $applicantName,
                'requester_role' => 'Pengguna Sarpras',
                'details' => trim(($booking->purpose ? $booking->purpose.' ' : '').($startAt ? "({$startAt})" : '')),
                'submittedAt' => $booking->created_at?->toIso8601String() ?? Carbon::now()->toIso8601String(),
                'submitted_at' => $booking->created_at?->toIso8601String() ?? Carbon::now()->toIso8601String(),
                'status' => 'pending',
            ];
        }

        // 3. Report Cards (Pengesahan E-Rapor Semester) - SEC-APPR-04: Exclude 'draft', filter strictly locked cards
        $reportQuery = ReportCard::with(['student', 'semester'])
            ->whereIn('status', ['terkunci', 'dikunci'])
            ->whereNull('approved_by')
            ->latest();

        if ($schoolId) {
            $reportQuery->whereHas('student', fn ($s) => $s->where('school_id', $schoolId));
        }

        foreach ($reportQuery->take(25)->get() as $report) {
            $studentName = $report->student?->full_name ?? 'Siswa';
            $semesterName = $report->semester?->name ?? 'Semester Berjalan';

            $items[] = [
                'id' => (string) $report->id,
                'code' => 'APP-RAPOR-'.strtoupper(substr(str_replace('-', '', (string) $report->id), 0, 6)),
                'category' => 'report_card',
                'title' => 'Pengesahan E-Rapor Siswa: '.$studentName,
                'requesterName' => 'Wali Kelas',
                'requesterRole' => 'Wali Kelas',
                'requester_name' => 'Wali Kelas',
                'requester_role' => 'Wali Kelas',
                'details' => "Pengajuan kunci dan cetak nilai e-rapor semester {$semesterName}.",
                'submittedAt' => $report->locked_at?->toIso8601String() ?? $report->created_at?->toIso8601String() ?? Carbon::now()->toIso8601String(),
                'submitted_at' => $report->locked_at?->toIso8601String() ?? $report->created_at?->toIso8601String() ?? Carbon::now()->toIso8601String(),
                'status' => 'pending',
            ];
        }

        // Sort all items by submittedAt descending
        usort($items, function ($a, $b) {
            return strcmp($b['submittedAt'], $a['submittedAt']);
        });

        return $items;
    }

    /**
     * ADM-DASH-02: Review (approve or reject) a pending approval item.
     */
    public function reviewApproval(string $id, array $payload, Request $request): array
    {
        $user = $request->user();
        $this->authorizeManageApprovals($user);

        $action = strtolower($payload['action'] ?? 'approve');
        $isApprove = in_array($action, ['approve', 'disetujui'], true);
        $notes = $payload['notes'] ?? $payload['rejectionReason'] ?? null;
        $now = Carbon::now();

        return DB::transaction(function () use ($id, $user, $isApprove, $notes, $now, $request) {
            // SEC-APPR-06: Pessimistic locking via lockForUpdate()
            // 1. Check LeaveRequest
            $leave = LeaveRequest::where('id', $id)->lockForUpdate()->first();
            if ($leave) {
                // SEC-APPR-07: Tenant isolation and school scoping
                if (! empty($user->school_id)) {
                    $leave->loadMissing(['student', 'requester']);
                    $studentSchoolId = $leave->student?->school_id;
                    $requesterSchoolId = $leave->requester?->school_id;

                    $matchesSchool = ($studentSchoolId === $user->school_id)
                        || ($requesterSchoolId === $user->school_id)
                        || (empty($studentSchoolId) && empty($requesterSchoolId));

                    if (! $matchesSchool) {
                        throw new HttpException(403, 'Akses ditolak: Permohonan izin berada di luar sekolah penugasan Anda.');
                    }
                }

                // SEC-APPR-01: State machine integrity check
                if (! in_array($leave->status, ['menunggu', 'diajukan', 'pending'], true)) {
                    throw new HttpException(422, 'Permohonan izin ini telah diputuskan sebelumnya dan tidak dapat diubah kembali.');
                }

                $oldStatus = $leave->status;
                $newStatus = $isApprove ? 'disetujui' : 'ditolak';

                $leave->update([
                    'status' => $newStatus,
                    'decided_by' => $user->id,
                    'decision_notes' => $notes ?? ($isApprove ? 'Disetujui oleh Kepala Sekolah / Pimpinan' : 'Ditolak'),
                ]);

                // SEC-APPR-05: Synchronize daily attendance records for approved student leave
                if ($isApprove && ! empty($leave->student_id)) {
                    $startDate = Carbon::parse($leave->start_date);
                    $endDate = Carbon::parse($leave->end_date);
                    $attStatus = match ($leave->leave_type) {
                        'sakit' => 'sakit',
                        'dispen' => 'izin',
                        default => 'izin',
                    };

                    for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                        Attendance::updateOrCreate(
                            [
                                'student_id' => $leave->student_id,
                                'att_date' => $date->format('Y-m-d'),
                                'source' => 'gerbang',
                            ],
                            [
                                'status' => $attStatus,
                                'note' => "Pengajuan {$leave->leave_type} disetujui: {$leave->reason}".($notes ? " (Catatan Pimpinan: {$notes})" : ''),
                                'recorded_by' => $user->id,
                            ]
                        );
                    }
                }

                // SEC-APPR-05: In-app notification to requester
                if (! empty($leave->requested_by)) {
                    $leave->loadMissing(['student', 'requester']);
                    $statusText = $isApprove ? 'disetujui' : 'ditolak';
                    $notesText = $notes ? " Catatan: {$notes}" : '';
                    $applicantName = $leave->student?->full_name ?? $leave->requester?->name ?? 'Pemohon';
                    $startDateStr = $leave->start_date ? Carbon::parse($leave->start_date)->format('d/m/Y') : '-';
                    $endDateStr = $leave->end_date ? Carbon::parse($leave->end_date)->format('d/m/Y') : '-';

                    Notification::create([
                        'user_id' => $leave->requested_by,
                        'notif_type' => 'leave_request.decision',
                        'title' => "Permohonan Izin {$applicantName} Telah {$statusText}",
                        'body' => "Permohonan izin ({$leave->leave_type}) untuk tanggal {$startDateStr} s.d. {$endDateStr} telah {$statusText} oleh pimpinan.{$notesText}",
                        'data' => [
                            'leave_request_id' => $leave->id,
                            'student_id' => $leave->student_id,
                            'status' => $newStatus,
                            'decision_notes' => $notes,
                        ],
                    ]);
                }

                // SEC-APPR-08: AuditLog with correct 'ip' field
                AuditLog::create([
                    'school_id' => $user->school_id,
                    'actor_id' => $user->id,
                    'actor_role' => $user->roles?->first()?->code ?? 'principal',
                    'action' => 'executive.approval.decision',
                    'entity' => 'leave_requests',
                    'entity_id' => (string) $leave->id,
                    'old_values' => ['status' => $oldStatus],
                    'new_values' => ['status' => $newStatus, 'decision_notes' => $notes],
                    'reason' => $notes ?? ($isApprove ? 'Persetujuan pimpinan' : 'Penolakan pimpinan'),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'request_id' => $request->header('X-Request-Id') ?? (string) Str::uuid(),
                ]);

                return [
                    'success' => true,
                    'message' => $isApprove ? 'Permohonan izin berhasil disetujui.' : 'Permohonan izin berhasil ditolak.',
                    'reviewedAt' => $now->toIso8601String(),
                    'reviewed_at' => $now->toIso8601String(),
                ];
            }

            // 2. Check Booking
            $booking = Booking::where('id', $id)->lockForUpdate()->first();
            if ($booking) {
                // SEC-APPR-07: Tenant isolation and school scoping
                if (! empty($user->school_id)) {
                    $booking->loadMissing('facility');
                    $facilitySchoolId = $booking->facility?->school_id;
                    $bookingSchoolId = $booking->school_id;

                    $matchesSchool = ($bookingSchoolId === $user->school_id)
                        || ($facilitySchoolId === $user->school_id)
                        || (empty($bookingSchoolId) && empty($facilitySchoolId));

                    if (! $matchesSchool) {
                        throw new HttpException(403, 'Akses ditolak: Peminjaman berada di luar sekolah penugasan Anda.');
                    }
                }

                // SEC-APPR-01: State machine integrity check
                if (in_array($booking->status, ['selesai', 'dibatalkan'], true)) {
                    throw new HttpException(422, "Peminjaman dengan status {$booking->status} tidak dapat diproses lagi.");
                }
                if ($booking->status === 'ditolak' && $isApprove) {
                    throw new HttpException(422, 'Peminjaman yang telah ditolak tidak dapat langsung disetujui.');
                }
                if ($booking->status === 'disetujui' && $isApprove) {
                    throw new HttpException(422, 'Peminjaman ini sudah disetujui sebelumnya.');
                }
                if (! in_array($booking->status, ['diajukan', 'pending'], true) && ! ($booking->status === 'disetujui' && ! $isApprove)) {
                    throw new HttpException(422, "Peminjaman dengan status {$booking->status} tidak dalam antrean persetujuan.");
                }

                // SEC-APPR-02: Overlap conflict check for facility booking
                if ($isApprove) {
                    $conflict = Booking::where('facility_id', $booking->facility_id)
                        ->where('id', '!=', $booking->id)
                        ->where('status', 'disetujui')
                        ->where('start_at', '<', $booking->end_at)
                        ->where('end_at', '>', $booking->start_at)
                        ->lockForUpdate()
                        ->exists();

                    if ($conflict) {
                        throw new HttpException(422, 'Tidak dapat menyetujui peminjaman karena fasilitas telah dibooking jadwal lain pada jam tersebut.');
                    }
                }

                $oldStatus = $booking->status;
                $newStatus = $isApprove ? 'disetujui' : 'ditolak';

                $booking->update([
                    'status' => $newStatus,
                    'decided_by' => $user->id,
                    'decision_notes' => $notes ?? ($isApprove ? 'Disetujui oleh Kepala Sekolah / Pimpinan' : 'Ditolak'),
                ]);

                // SEC-APPR-08: AuditLog with correct 'ip' field
                AuditLog::create([
                    'school_id' => $user->school_id,
                    'actor_id' => $user->id,
                    'actor_role' => $user->roles?->first()?->code ?? 'principal',
                    'action' => 'executive.approval.decision',
                    'entity' => 'bookings',
                    'entity_id' => (string) $booking->id,
                    'old_values' => ['status' => $oldStatus],
                    'new_values' => ['status' => $newStatus, 'decision_notes' => $notes],
                    'reason' => $notes ?? ($isApprove ? 'Persetujuan pimpinan' : 'Penolakan pimpinan'),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'request_id' => $request->header('X-Request-Id') ?? (string) Str::uuid(),
                ]);

                return [
                    'success' => true,
                    'message' => $isApprove ? 'Pengajuan sarpras berhasil disetujui.' : 'Pengajuan sarpras berhasil ditolak.',
                    'reviewedAt' => $now->toIso8601String(),
                    'reviewed_at' => $now->toIso8601String(),
                ];
            }

            // 3. Check ReportCard
            $reportCard = ReportCard::where('id', $id)->lockForUpdate()->first();
            if ($reportCard) {
                // SEC-APPR-07: Tenant isolation and school scoping
                if (! empty($user->school_id)) {
                    $reportCard->loadMissing('student');
                    $studentSchoolId = $reportCard->student?->school_id;
                    $matchesSchool = ($studentSchoolId === $user->school_id) || empty($studentSchoolId);

                    if (! $matchesSchool) {
                        throw new HttpException(403, 'Akses ditolak: E-rapor berada di luar sekolah penugasan Anda.');
                    }
                }

                // SEC-APPR-01 & SEC-APPR-04: State machine integrity check
                if ($reportCard->status === 'draft') {
                    throw new HttpException(422, 'E-Rapor masih berstatus draf dan belum dikunci oleh kurikulum untuk pengesahan.');
                }
                if (in_array($reportCard->status, ['disetujui', 'terbit'], true)) {
                    throw new HttpException(422, 'E-Rapor ini telah disahkan atau diterbitkan sebelumnya.');
                }
                if (! in_array($reportCard->status, ['terkunci', 'dikunci'], true) || ! empty($reportCard->approved_by)) {
                    throw new HttpException(422, 'E-Rapor tidak dalam status menunggu pengesahan.');
                }

                $oldStatus = $reportCard->status;
                $newStatus = $isApprove ? 'disetujui' : 'draft';

                $reportCard->update([
                    'status' => $newStatus,
                    'approved_by' => $isApprove ? $user->id : null,
                ]);

                // SEC-APPR-08: AuditLog with correct 'ip' field
                AuditLog::create([
                    'school_id' => $user->school_id,
                    'actor_id' => $user->id,
                    'actor_role' => $user->roles?->first()?->code ?? 'principal',
                    'action' => 'executive.approval.decision',
                    'entity' => 'report_cards',
                    'entity_id' => (string) $reportCard->id,
                    'old_values' => ['status' => $oldStatus],
                    'new_values' => ['status' => $newStatus],
                    'reason' => $notes ?? ($isApprove ? 'Persetujuan pimpinan' : 'Penolakan / revisi pimpinan'),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'request_id' => $request->header('X-Request-Id') ?? (string) Str::uuid(),
                ]);

                return [
                    'success' => true,
                    'message' => $isApprove ? 'E-Rapor berhasil disahkan.' : 'E-Rapor ditolak dan dikembalikan ke draf.',
                    'reviewedAt' => $now->toIso8601String(),
                    'reviewed_at' => $now->toIso8601String(),
                ];
            }

            throw new HttpException(404, 'Data pengajuan persetujuan tidak ditemukan.');
        });
    }
}
