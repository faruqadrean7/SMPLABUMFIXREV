<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\CounselingReferral;
use App\Models\Facility;
use App\Models\LeaveRequest;
use App\Models\PointType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentPoint;
use App\Models\Ticket;
use App\Services\AcademicAttendanceService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TeacherHomeroomController extends Controller
{
    use ApiResponse;
    use TeacherPortalContextTrait;

    /**
     * GET /api/v1/me/teacher/homeroom
     */
    public function homeroom(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $homeroomClass = $teacher->homeroomClasses()
            ->with(['students', 'academicYear'])
            ->first();

        if (! $homeroomClass) {
            return $this->errorResponse('Akses ditolak: Anda bukan wali kelas pada periode ini.', 'FORBIDDEN', 403);
        }

        $students = $homeroomClass->students->map(function (Student $s, $idx) use ($homeroomClass) {
            $program = $this->resolveStudentProgram($s, $homeroomClass);

            return [
                'id' => $s->id,
                'name' => $s->full_name,
                'nisn' => $s->nisn ?? '',
                'nis' => $s->nis ?? '',
                'programType' => $program['type'],
                'programLabel' => $program['label'],
                'attendanceRate' => 98.0,
                'averageScore' => 86.5,
                'rank' => $idx + 1,
            ];
        });

        return $this->successResponse([
            'class_id' => $homeroomClass->id,
            'classId' => $homeroomClass->id,
            'className' => $homeroomClass->name,
            'totalStudents' => $students->count(),
            'academicYear' => $homeroomClass->academicYear?->name ?? '2024/2025',
            'semester' => 'Ganjil',
            'attendanceRecap' => [
                'averageRate' => 98.0,
                'sickDays' => 2,
                'leaveDays' => 1,
                'unexcusedDays' => 0,
            ],
            'characterRecapCount' => $students->count(),
            'isReportCardLocked' => false,
            'students' => $students,
        ]);
    }

    /**
     * GET /api/v1/me/teacher/homeroom/leave-requests
     */
    public function homeroomLeaveRequests(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $homeroomClassIds = SchoolClass::where('homeroom_teacher_id', $teacher->id)->pluck('id');

        $classId = $request->query('class_id', $request->query('classId'));
        if ($classId) {
            if (! Str::isUuid($classId) || ! $homeroomClassIds->contains($classId)) {
                return $this->errorResponse('Akses ditolak: Anda bukan wali kelas untuk kelas ini.', 'FORBIDDEN', 403);
            }
            $targetClassIds = collect([$classId]);
        } else {
            if ($homeroomClassIds->isEmpty()) {
                return $this->paginatedResponse(new LengthAwarePaginator([], 0, 15));
            }
            $targetClassIds = $homeroomClassIds;
        }

        $query = LeaveRequest::with(['student.classes', 'attachmentFile', 'decisionMaker', 'requester'])
            ->whereNotNull('student_id')
            ->whereHas('student.classes', fn ($q) => $q->whereIn('classes.id', $targetClassIds));

        if ($request->filled('status')) {
            $status = $request->query('status');
            $canonicalStatus = match ($status) {
                'approved' => 'disetujui',
                'rejected' => 'ditolak',
                'pending' => 'menunggu',
                default => $status,
            };
            $query->where('status', $canonicalStatus);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('start_date', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('end_date', '<=', $request->query('date_to'));
        }

        if ($request->filled('q')) {
            $keyword = trim($request->query('q'));
            $query->whereHas('student', function ($sub) use ($keyword) {
                $sub->where('full_name', 'like', "%{$keyword}%")
                    ->orWhere('nis', 'like', "%{$keyword}%");
            });
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $paginator = $query->latest('created_at')->paginate($perPage);

        return $this->paginatedResponse($paginator);
    }

    /**
     * PATCH /api/v1/me/teacher/homeroom/leave-requests/{id}/decision
     */
    public function decideHomeroomLeaveRequest(Request $request, string $id): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $leave = LeaveRequest::with(['student.classes'])->find($id);
        if (! $leave) {
            return $this->errorResponse('Data permohonan izin tidak ditemukan.', 'NOT_FOUND', 404);
        }

        // Verify teacher is homeroom for this student's class
        $isWalas = SchoolClass::where('homeroom_teacher_id', $teacher->id)
            ->whereHas('students', fn ($q) => $q->where('students.id', $leave->student_id))
            ->exists();

        if (! $isWalas) {
            return $this->errorResponse('Akses ditolak: Hanya wali kelas dari siswa bersangkutan yang berhak memutuskan izin.', 'FORBIDDEN', 403);
        }

        if ($leave->status !== 'menunggu') {
            return $this->errorResponse('Permohonan izin ini sudah diputuskan sebelumnya dan tidak dapat diubah kembali.', 'BUSINESS_RULE_VIOLATION', 422);
        }

        $validated = $request->validate([
            'decision' => 'nullable|in:approved,rejected,disetujui,ditolak',
            'status' => 'nullable|in:approved,rejected,disetujui,ditolak',
            'decision_notes' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        $rawDecision = $validated['decision'] ?? ($validated['status'] ?? null);
        if (! $rawDecision) {
            return $this->errorResponse('Keputusan permohonan izin wajib diisi (approved atau rejected).', 'VALIDATION_ERROR', 422);
        }

        $canonicalStatus = match ($rawDecision) {
            'approved', 'disetujui' => 'disetujui',
            'rejected', 'ditolak' => 'ditolak',
            default => $rawDecision,
        };

        $notes = $validated['decision_notes'] ?? ($validated['notes'] ?? null);

        $decidedLeave = app(AcademicAttendanceService::class)->decideLeaveRequest(
            $leave->id,
            [
                'status' => $canonicalStatus,
                'decision_notes' => $notes,
            ],
            $request
        );

        return $this->successResponse($decidedLeave);
    }

    /**
     * POST /api/v1/me/teacher/student-points
     */
    public function storeStudentPoint(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $validated = $request->validate([
            'student_id' => 'required|uuid|exists:students,id',
            'point_type_id' => 'nullable|uuid|exists:point_types,id',
            'type' => 'nullable|string|in:prestasi,pelanggaran',
            'kind' => 'nullable|string|in:prestasi,pelanggaran',
            'category' => 'nullable|string|max:100',
            'points' => 'nullable|integer',
            'notes' => 'nullable|string|max:1000',
            'description' => 'nullable|string|max:1000',
            'point_date' => 'nullable|date',
            'date' => 'nullable|date',
            'evidence_file_id' => 'nullable|uuid|exists:files,id',
            'idempotency_key' => 'nullable|string|max:100',
        ]);

        $studentId = $validated['student_id'];

        if (! $this->verifyStudentAccess($teacher, $studentId)) {
            return $this->errorResponse('Akses ditolak: Anda tidak memiliki wewenang mengajar atau menjadi wali kelas untuk siswa ini.', 'FORBIDDEN', 403);
        }

        $notes = $validated['notes'] ?? ($validated['description'] ?? 'Catatan poin siswa');
        $pointDate = $validated['point_date'] ?? ($validated['date'] ?? now()->toDateString());
        $pointTypeId = $validated['point_type_id'] ?? null;

        if (! $pointTypeId) {
            $kind = $validated['type'] ?? ($validated['kind'] ?? 'pelanggaran');
            $pointType = PointType::where('kind', $kind)->first();
            if (! $pointType) {
                $pointType = PointType::create([
                    'school_id' => $request->user()->school_id ?? '00000000-0000-0000-0000-000000000001',
                    'kind' => $kind,
                    'name' => $validated['category'] ?? ($kind === 'prestasi' ? 'Prestasi Siswa' : 'Pelanggaran Disiplin'),
                    'points' => $validated['points'] ?? ($kind === 'prestasi' ? 10 : 5),
                    'severity' => 'ringan',
                ]);
            }
            $pointTypeId = $pointType->id;
        } else {
            $pointType = PointType::findOrFail($pointTypeId);
        }

        $idempotencyKey = $request->header('X-Idempotency-Key')
            ?? $request->header('Idempotency-Key')
            ?? ($validated['idempotency_key'] ?? null);

        // Normalize payload representation for tamper & mismatch detection
        $payloadHash = hash('sha256', json_encode([
            'student_id' => $studentId,
            'point_type_id' => $pointTypeId,
            'point_date' => $pointDate,
            'notes' => $notes,
            'evidence_file_id' => $validated['evidence_file_id'] ?? null,
        ]));

        if ($idempotencyKey) {
            $cacheKey = "idemp:student_point:{$request->user()->id}:{$idempotencyKey}";
            $lockKey = "lock:idemp:student_point:{$request->user()->id}:{$idempotencyKey}";

            try {
                // 1. Check existing idempotent cache entry before acquiring lock
                $cached = Cache::get($cacheKey);
                if ($cached) {
                    $cachedData = is_array($cached) ? $cached : ['point_id' => $cached, 'payload_hash' => $payloadHash];
                    if (isset($cachedData['payload_hash']) && $cachedData['payload_hash'] !== $payloadHash) {
                        return $this->errorResponse(
                            'Idempotency-Key telah digunakan untuk request dengan parameter yang berbeda.',
                            'IDEMPOTENCY_CONFLICT',
                            422
                        );
                    }
                    $cachedPoint = StudentPoint::with(['student', 'pointType'])->find($cachedData['point_id']);
                    if ($cachedPoint) {
                        return response()->json([
                            'status' => 'success',
                            'data' => $cachedPoint,
                            'meta' => ['idempotent_replayed' => true],
                        ], 200);
                    }
                }

                // 2. Acquire atomic lock for parallel request concurrency protection
                $lock = Cache::lock($lockKey, 10);
                $acquired = $lock->block(3);
                if (! $acquired) {
                    return $this->errorResponse(
                        'Permintaan serupa sedang diproses secara bersamaan. Silakan tunggu.',
                        'CONCURRENT_REQUEST',
                        409
                    );
                }
            } catch (LockTimeoutException $e) {
                return $this->errorResponse(
                    'Permintaan serupa sedang diproses secara bersamaan. Silakan tunggu.',
                    'CONCURRENT_REQUEST',
                    409
                );
            } catch (\Throwable $e) {
                report($e);

                return $this->errorResponse(
                    'Layanan idempotensi tidak dapat diakses untuk menjamin integritas transaksi.',
                    'IDEMPOTENCY_UNAVAILABLE',
                    503
                );
            }

            try {
                // Double-checked locking pattern: re-check cache inside acquired lock
                $cached = Cache::get($cacheKey);
                if ($cached) {
                    $cachedData = is_array($cached) ? $cached : ['point_id' => $cached, 'payload_hash' => $payloadHash];
                    if (isset($cachedData['payload_hash']) && $cachedData['payload_hash'] !== $payloadHash) {
                        return $this->errorResponse(
                            'Idempotency-Key telah digunakan untuk request dengan parameter yang berbeda.',
                            'IDEMPOTENCY_CONFLICT',
                            422
                        );
                    }
                    $cachedPoint = StudentPoint::with(['student', 'pointType'])->find($cachedData['point_id']);
                    if ($cachedPoint) {
                        return response()->json([
                            'status' => 'success',
                            'data' => $cachedPoint,
                            'meta' => ['idempotent_replayed' => true],
                        ], 200);
                    }
                }

                $studentPoint = DB::transaction(function () use ($studentId, $pointType, $request, $validated, $pointDate, $notes) {
                    $point = StudentPoint::create([
                        'student_id' => $studentId,
                        'point_type_id' => $pointType->id,
                        'recorded_by' => $request->user()->id,
                        'evidence_file_id' => $validated['evidence_file_id'] ?? null,
                        'point_date' => $pointDate,
                        'notes' => $notes,
                    ]);

                    AuditLog::create([
                        'actor_id' => $request->user()->id,
                        'action' => 'teacher.student_point.created',
                        'entity' => 'StudentPoint',
                        'entity_id' => $point->id,
                        'new_values' => $point->toArray(),
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'request_id' => $request->header('X-Request-Id') ?? (string) Str::uuid(),
                    ]);

                    return $point;
                });

                // Persist idempotency record with payload hash (24h TTL)
                Cache::put($cacheKey, [
                    'point_id' => $studentPoint->id,
                    'payload_hash' => $payloadHash,
                    'created_at' => now()->toIso8601String(),
                ], now()->addHours(24));

                return $this->successResponse($studentPoint->load(['student', 'pointType']), 201);
            } finally {
                $lock->release();
            }
        } else {
            // Anti-double-click / short retry deduplication window (2s)
            $fingerprint = hash('sha256', "{$request->user()->id}:{$studentId}:{$pointTypeId}:{$pointDate}:{$notes}");
            $debounceKey = "debounce:student_point:{$fingerprint}";
            $debouncedPointId = Cache::get($debounceKey);
            if ($debouncedPointId) {
                $debouncedPoint = StudentPoint::with(['student', 'pointType'])->find($debouncedPointId);
                if ($debouncedPoint) {
                    return response()->json([
                        'status' => 'success',
                        'data' => $debouncedPoint,
                        'meta' => ['debounced_replayed' => true],
                    ], 200);
                }
            }

            $studentPoint = DB::transaction(function () use ($studentId, $pointType, $request, $validated, $pointDate, $notes) {
                $point = StudentPoint::create([
                    'student_id' => $studentId,
                    'point_type_id' => $pointType->id,
                    'recorded_by' => $request->user()->id,
                    'evidence_file_id' => $validated['evidence_file_id'] ?? null,
                    'point_date' => $pointDate,
                    'notes' => $notes,
                ]);

                AuditLog::create([
                    'actor_id' => $request->user()->id,
                    'action' => 'teacher.student_point.created',
                    'entity' => 'StudentPoint',
                    'entity_id' => $point->id,
                    'new_values' => $point->toArray(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'request_id' => $request->header('X-Request-Id') ?? (string) Str::uuid(),
                ]);

                return $point;
            });

            Cache::put($debounceKey, $studentPoint->id, now()->addSeconds(2));

            return $this->successResponse($studentPoint->load(['student', 'pointType']), 201);
        }
    }

    /**
     * POST /api/v1/me/teacher/counseling-referrals
     */
    public function storeCounselingReferral(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $validated = $request->validate([
            'student_id' => 'required|uuid|exists:students,id',
            'reason' => 'required|string|min:5|max:2000',
            'priority' => 'nullable|string|in:biasa,segera,normal,tinggi',
            'notes' => 'nullable|string|max:1000',
        ]);

        $studentId = $validated['student_id'];

        if (! $this->verifyStudentAccess($teacher, $studentId)) {
            return $this->errorResponse('Akses ditolak: Anda tidak memiliki wewenang mengajar atau menjadi wali kelas untuk siswa ini.', 'FORBIDDEN', 403);
        }

        $reason = $validated['reason'];
        if (! empty($validated['priority'])) {
            $reason = "[Prioritas: {$validated['priority']}] ".$reason;
        }

        $existingPending = CounselingReferral::where('student_id', $studentId)
            ->where('referred_by', $request->user()->id)
            ->where('status', 'pending')
            ->where('reason', $reason)
            ->first();

        if ($existingPending) {
            return $this->errorResponse('Rujukan serupa untuk siswa ini masih berstatus pending dan sedang menunggu tindak lanjut BK.', 'BUSINESS_RULE_VIOLATION', 422);
        }

        $referral = DB::transaction(function () use ($studentId, $request, $reason) {
            $ref = CounselingReferral::create([
                'student_id' => $studentId,
                'referred_by' => $request->user()->id,
                'reason' => $reason,
                'status' => 'pending',
            ]);

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'teacher.counseling_referral.created',
                'entity' => 'CounselingReferral',
                'entity_id' => $ref->id,
                'new_values' => $ref->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_id' => $request->header('X-Request-Id') ?? (string) Str::uuid(),
            ]);

            return $ref;
        });

        return $this->successResponse($referral->load(['student']), 201);
    }

    /**
     * GET /api/v1/me/teacher/facility-bookings
     */
    public function facilityBookings(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $query = Booking::with(['facility', 'items.facility'])
            ->where('requested_by', $request->user()->id);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $bookings = $query->latest('start_at')->get();

        $data = $bookings->map(fn (Booking $b) => [
            'id' => $b->id,
            'facility_id' => $b->facility_id,
            'facility_name' => $b->facility?->name ?? 'Fasilitas',
            'facility' => $b->facility?->name ?? 'Fasilitas',
            'start_at' => $b->start_at?->toISOString() ?? (string) $b->start_at,
            'end_at' => $b->end_at?->toISOString() ?? (string) $b->end_at,
            'date' => $b->start_at?->toDateString(),
            'time_slot' => $b->start_at && $b->end_at ? $b->start_at->format('H:i').' - '.$b->end_at->format('H:i') : '',
            'purpose' => $b->purpose,
            'participant_count' => $b->attendees_count ?? 1,
            'attendees_count' => $b->attendees_count ?? 1,
            'status' => $b->status,
            'decision_notes' => $b->decision_notes,
            'notes' => $b->decision_notes ?? '',
            'created_at' => $b->created_at?->toISOString(),
        ]);

        return $this->successResponse($data);
    }

    /**
     * POST /api/v1/me/teacher/facility-bookings
     */
    public function storeFacilityBooking(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $validated = $request->validate([
            'facility_id' => 'required|uuid|exists:facilities,id',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'purpose' => 'required|string|max:1000',
            'attendees_count' => 'nullable|integer|min:1',
            'participant_count' => 'nullable|integer|min:1',
        ]);

        $startAt = Carbon::parse($validated['start_at']);
        $endAt = Carbon::parse($validated['end_at']);
        $facilityId = $validated['facility_id'];

        $facility = Facility::find($facilityId);
        if (! $facility || ! ($facility->is_active ?? true)) {
            return $this->errorResponse('Fasilitas tidak tersedia untuk dipinjam.', 'BUSINESS_RULE_VIOLATION', 422);
        }

        // Concurrency / Schedule Conflict Check
        $hasConflict = Booking::where('facility_id', $facilityId)
            ->whereIn('status', ['menunggu', 'disetujui', 'approved', 'pending'])
            ->where(function ($q) use ($startAt, $endAt) {
                $q->where('start_at', '<', $endAt)
                    ->where('end_at', '>', $startAt);
            })
            ->exists();

        if ($hasConflict) {
            return $this->errorResponse('Fasilitas sudah dipesan pada rentang waktu tersebut.', 'CONFLICT', 409);
        }

        $attendees = $validated['attendees_count'] ?? ($validated['participant_count'] ?? 1);

        $booking = DB::transaction(function () use ($request, $facility, $startAt, $endAt, $validated, $attendees) {
            $b = Booking::create([
                'school_id' => $request->user()->school_id ?? '00000000-0000-0000-0000-000000000001',
                'facility_id' => $facility->id,
                'requested_by' => $request->user()->id,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'purpose' => $validated['purpose'],
                'attendees_count' => $attendees,
                'status' => 'menunggu',
            ]);

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'teacher.booking.created',
                'entity' => 'Booking',
                'entity_id' => $b->id,
                'new_values' => $b->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_id' => $request->header('X-Request-Id') ?? (string) Str::uuid(),
            ]);

            return $b;
        });

        return $this->successResponse($booking->load('facility'), 201);
    }

    /**
     * POST /api/v1/me/teacher/tickets
     */
    public function storeTicket(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:200',
            'subject' => 'nullable|string|max:200',
            'category' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'description' => 'required|string|max:2500',
            'priority' => 'nullable|string|in:rendah,sedang,tinggi,darurat,normal',
            'room_id' => 'nullable|uuid|exists:rooms,id',
            'asset_id' => 'nullable|uuid|exists:assets,id',
            'photo_file_id' => 'nullable|uuid|exists:files,id',
        ]);

        $rawPriority = $validated['priority'] ?? 'sedang';
        $priority = match ($rawPriority) {
            'normal' => 'sedang',
            default => $rawPriority,
        };

        $title = $validated['title'] ?? ($validated['subject'] ?? 'Laporan Gangguan Sarpras');
        if (! empty($validated['category'])) {
            $title = "[{$validated['category']}] {$title}";
        }

        $desc = $validated['description'];
        if (! empty($validated['location'])) {
            $desc = "Lokasi: {$validated['location']}\n".$desc;
        }

        $ticket = DB::transaction(function () use ($request, $title, $desc, $priority, $validated) {
            $ticketNumber = 'TKT-'.date('Ymd').'-'.strtoupper(Str::random(4));

            $t = Ticket::create([
                'school_id' => $request->user()->school_id ?? '00000000-0000-0000-0000-000000000001',
                'ticket_number' => $ticketNumber,
                'title' => Str::limit($title, 200, ''),
                'reporter_id' => $request->user()->id,
                'room_id' => $validated['room_id'] ?? null,
                'asset_id' => $validated['asset_id'] ?? null,
                'description' => $desc,
                'priority' => $priority,
                'status' => 'pending',
                'photo_file_id' => $validated['photo_file_id'] ?? null,
            ]);

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'teacher.ticket.created',
                'entity' => 'Ticket',
                'entity_id' => $t->id,
                'new_values' => $t->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_id' => $request->header('X-Request-Id') ?? (string) Str::uuid(),
            ]);

            return $t;
        });

        return $this->successResponse($ticket, 201);
    }
}
