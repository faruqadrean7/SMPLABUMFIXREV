<?php

namespace App\Services\Rpt;

use App\Models\Applicant;
use App\Models\Attendance;
use App\Models\Grade;
use App\Models\Loan;
use App\Models\StudentPoint;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReportService
{
    public const VALID_TYPES = [
        'attendance',
        'grades',
        'ppdb',
        'student_points',
        'tickets',
        'loans',
    ];

    /**
     * BE-RPT-01: Generate data rekapitulasi terstruktur.
     */
    public function generateReport(string $type, array $filters, Request $request): array
    {
        $user = $request->user();
        $this->authorizeStaff($user);
        $this->validateReportType($type);
        $schoolId = $user?->school_id;

        return match ($type) {
            'attendance' => $this->generateAttendanceReport($filters, false, $schoolId),
            'grades' => $this->generateGradesReport($filters, false, $schoolId),
            'ppdb' => $this->generatePpdbReport($filters, false, $schoolId),
            'student_points' => $this->generateStudentPointsReport($filters, false, $schoolId),
            'tickets' => $this->generateTicketsReport($filters, false, $schoolId),
            'loans' => $this->generateLoansReport($filters, false, $schoolId),
        };
    }

    /**
     * BE-RPT-01: Ekspor berkas laporan rekapitulasi (CSV/Excel).
     */
    public function exportReport(string $type, array $filters, Request $request): array
    {
        $user = $request->user();
        $this->authorizeStaff($user);
        $this->validateReportType($type);
        $schoolId = $user?->school_id;

        $reportData = match ($type) {
            'attendance' => $this->generateAttendanceReport($filters, true, $schoolId),
            'grades' => $this->generateGradesReport($filters, true, $schoolId),
            'ppdb' => $this->generatePpdbReport($filters, true, $schoolId),
            'student_points' => $this->generateStudentPointsReport($filters, true, $schoolId),
            'tickets' => $this->generateTicketsReport($filters, true, $schoolId),
            'loans' => $this->generateLoansReport($filters, true, $schoolId),
        };

        $csvContent = $this->buildCsv($type, $reportData['summary'], $reportData['rows']);
        $filename = 'rekap_'.$type.'_'.Carbon::now()->format('Ymd_His').'.csv';

        app(AuditLogService::class)->record(
            action: 'report.export',
            entity: 'Report_'.$type,
            entityId: null,
            oldValues: null,
            newValues: [
                'type' => $type,
                'filters' => $filters,
                'summary' => $reportData['summary'],
            ],
            reason: $request->query('reason') ?? 'Ekspor data laporan massal',
            request: $request,
            isAuthoritative: true
        );

        return [
            'filename' => $filename,
            'content' => $csvContent,
            'summary' => $reportData['summary'],
        ];
    }

    // =========================================================================
    // 1. REKAP KEHADIRAN (ATTENDANCE)
    // =========================================================================

    protected function generateAttendanceReport(array $filters, bool $forExport = false, ?string $schoolId = null): array
    {
        $query = Attendance::with(['student']);

        if ($schoolId) {
            $query->whereHas('student', fn ($q) => $q->where('school_id', $schoolId));
        }
        if (! empty($filters['start_date'])) {
            $query->where('att_date', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->where('att_date', '<=', $filters['end_date']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['class_id'])) {
            $query->whereHas('student.classes', fn ($q) => $q->where('classes.id', $filters['class_id']));
        }

        $allStatuses = (clone $query)->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $total = array_sum($allStatuses);
        $hadir = $allStatuses['hadir'] ?? 0;
        $attendanceRate = $total > 0 ? round(($hadir / $total) * 100, 2) : 0;

        $summary = [
            'total_records' => $total,
            'hadir_count' => $hadir,
            'izin_count' => $allStatuses['izin'] ?? 0,
            'sakit_count' => $allStatuses['sakit'] ?? 0,
            'alpa_count' => $allStatuses['alpa'] ?? 0,
            'terlambat_count' => $allStatuses['terlambat'] ?? 0,
            'attendance_rate' => $attendanceRate,
        ];

        $query->orderBy('att_date', 'desc');

        if ($forExport) {
            $rows = $query->limit(5000)->get()->map(fn ($a) => [
                'date' => (string) $a->att_date,
                'student_name' => $a->student?->full_name ?? '-',
                'status' => $a->status,
                'source' => $a->source,
                'note' => $a->note ?? '-',
            ])->toArray();

            return ['summary' => $summary, 'rows' => $rows];
        }

        $paginator = $query->paginate((int) ($filters['per_page'] ?? 20));

        return [
            'type' => 'attendance',
            'summary' => $summary,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    // =========================================================================
    // 2. REKAP NILAI (GRADES)
    // =========================================================================

    protected function generateGradesReport(array $filters, bool $forExport = false, ?string $schoolId = null): array
    {
        $query = Grade::with(['student', 'component']);

        if ($schoolId) {
            $query->whereHas('student', fn ($q) => $q->where('school_id', $schoolId));
        }
        if (! empty($filters['class_id'])) {
            $query->whereHas('student.classes', fn ($q) => $q->where('classes.id', $filters['class_id']));
        }

        $stats = (clone $query)->select(
            DB::raw('count(*) as total'),
            DB::raw('avg(value) as average'),
            DB::raw('max(value) as highest'),
            DB::raw('min(value) as lowest')
        )->first();

        $passing = (clone $query)->where('value', '>=', 75)->count();
        $total = $stats?->total ?? 0;
        $failing = $total - $passing;

        $summary = [
            'total_grades' => (int) $total,
            'average_score' => $stats?->average !== null ? round((float) $stats->average, 2) : 0,
            'highest_score' => $stats?->highest !== null ? (float) $stats->highest : 0,
            'lowest_score' => $stats?->lowest !== null ? (float) $stats->lowest : 0,
            'passing_count' => $passing,
            'failing_count' => $failing,
        ];

        $query->orderBy('created_at', 'desc');

        if ($forExport) {
            $rows = $query->limit(5000)->get()->map(fn ($g) => [
                'student_name' => $g->student?->full_name ?? '-',
                'component_name' => $g->component?->name ?? '-',
                'value' => (float) $g->value,
                'source' => $g->source,
            ])->toArray();

            return ['summary' => $summary, 'rows' => $rows];
        }

        $paginator = $query->paginate((int) ($filters['per_page'] ?? 20));

        return [
            'type' => 'grades',
            'summary' => $summary,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    // =========================================================================
    // 3. REKAP PPDB (ADMISSIONS)
    // =========================================================================

    protected function generatePpdbReport(array $filters, bool $forExport = false, ?string $schoolId = null): array
    {
        $query = Applicant::with(['wave', 'track']);

        if ($schoolId) {
            $query->whereHas('wave', fn ($q) => $q->where('school_id', $schoolId));
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        $statusCounts = (clone $query)->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $avgScore = (clone $query)->whereNotNull('score')->avg('score');

        $summary = [
            'total_applicants' => array_sum($statusCounts),
            'by_status' => $statusCounts,
            'average_score' => $avgScore !== null ? round((float) $avgScore, 2) : 0,
        ];

        $query->orderBy('created_at', 'desc');

        if ($forExport) {
            $rows = $query->limit(5000)->get()->map(fn ($a) => [
                'registration_no' => $a->registration_no,
                'full_name' => $a->full_name,
                'status' => $a->status,
                'wave_name' => $a->wave?->name ?? '-',
                'track_name' => $a->track?->name ?? '-',
                'score' => $a->score ?? '-',
            ])->toArray();

            return ['summary' => $summary, 'rows' => $rows];
        }

        $paginator = $query->paginate((int) ($filters['per_page'] ?? 20));

        return [
            'type' => 'ppdb',
            'summary' => $summary,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    // =========================================================================
    // 4. REKAP POIN KESISWAAN (STUDENT POINTS)
    // =========================================================================

    protected function generateStudentPointsReport(array $filters, bool $forExport = false, ?string $schoolId = null): array
    {
        $query = StudentPoint::with(['student', 'pointType']);

        if ($schoolId) {
            $query->whereHas('student', fn ($q) => $q->where('school_id', $schoolId));
        }
        if (! empty($filters['start_date'])) {
            $query->where('point_date', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->where('point_date', '<=', $filters['end_date']);
        }
        if (! empty($filters['class_id'])) {
            $query->whereHas('student.classes', fn ($q) => $q->where('classes.id', $filters['class_id']));
        }

        $pointsByKind = (clone $query)->join('point_types', 'student_points.point_type_id', '=', 'point_types.id')
            ->select('point_types.kind', DB::raw('sum(point_types.points) as total_points'), DB::raw('count(*) as count'))
            ->groupBy('point_types.kind')
            ->get()
            ->keyBy('kind');

        $summary = [
            'total_events' => (clone $query)->count(),
            'total_prestasi_points' => (int) ($pointsByKind['prestasi']?->total_points ?? 0),
            'total_pelanggaran_points' => (int) ($pointsByKind['pelanggaran']?->total_points ?? 0),
            'prestasi_events' => (int) ($pointsByKind['prestasi']?->count ?? 0),
            'pelanggaran_events' => (int) ($pointsByKind['pelanggaran']?->count ?? 0),
        ];

        $query->orderBy('point_date', 'desc');

        if ($forExport) {
            $rows = $query->limit(5000)->get()->map(fn ($p) => [
                'date' => (string) $p->point_date,
                'student_name' => $p->student?->full_name ?? '-',
                'kind' => $p->pointType?->kind ?? '-',
                'point_name' => $p->pointType?->name ?? '-',
                'points' => $p->pointType?->points ?? 0,
                'notes' => $p->notes ?? '-',
            ])->toArray();

            return ['summary' => $summary, 'rows' => $rows];
        }

        $paginator = $query->paginate((int) ($filters['per_page'] ?? 20));

        return [
            'type' => 'student_points',
            'summary' => $summary,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    // =========================================================================
    // 5. REKAP TIKET SARPRAS (TICKETS)
    // =========================================================================

    protected function generateTicketsReport(array $filters, bool $forExport = false, ?string $schoolId = null): array
    {
        $query = Ticket::with(['reporter', 'assignee']);

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        $statusCounts = (clone $query)->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $summary = [
            'total_tickets' => array_sum($statusCounts),
            'pending_count' => $statusCounts['pending'] ?? 0,
            'diproses_count' => $statusCounts['diproses'] ?? 0,
            'selesai_count' => $statusCounts['selesai'] ?? 0,
        ];

        $query->orderBy('created_at', 'desc');

        if ($forExport) {
            $rows = $query->limit(5000)->get()->map(fn ($t) => [
                'id' => $t->id,
                'description' => $t->description,
                'priority' => $t->priority,
                'status' => $t->status,
                'reporter' => $t->reporter?->username ?? '-',
                'created_at' => $t->created_at?->toIso8601String(),
            ])->toArray();

            return ['summary' => $summary, 'rows' => $rows];
        }

        $paginator = $query->paginate((int) ($filters['per_page'] ?? 20));

        return [
            'type' => 'tickets',
            'summary' => $summary,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    // =========================================================================
    // 6. REKAP PEMINJAMAN (LOANS)
    // =========================================================================

    protected function generateLoansReport(array $filters, bool $forExport = false, ?string $schoolId = null): array
    {
        $query = Loan::with(['book', 'borrower']);

        if ($schoolId) {
            $query->whereHas('book', fn ($q) => $q->where('school_id', $schoolId));
        }
        if (! empty($filters['start_date'])) {
            $query->where('loan_date', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->where('loan_date', '<=', $filters['end_date']);
        }

        $now = Carbon::now()->toDateString();
        $total = (clone $query)->count();
        $returned = (clone $query)->whereNotNull('returned_at')->count();
        $active = (clone $query)->whereNull('returned_at')->count();
        $overdue = (clone $query)->whereNull('returned_at')->where('due_date', '<', $now)->count();
        $totalFines = (clone $query)->sum('fine_amount');

        $summary = [
            'total_loans' => $total,
            'active_loans' => $active,
            'returned_loans' => $returned,
            'overdue_loans' => $overdue,
            'total_fines' => (int) $totalFines,
        ];

        $query->orderBy('loan_date', 'desc');

        if ($forExport) {
            $rows = $query->limit(5000)->get()->map(fn ($l) => [
                'borrower' => $l->borrower?->username ?? '-',
                'book_title' => $l->book?->title ?? '-',
                'loan_date' => (string) $l->loan_date,
                'due_date' => (string) $l->due_date,
                'returned_at' => $l->returned_at ? (string) $l->returned_at : 'Belum Kembali',
                'fine_amount' => (int) $l->fine_amount,
            ])->toArray();

            return ['summary' => $summary, 'rows' => $rows];
        }

        $paginator = $query->paginate((int) ($filters['per_page'] ?? 20));

        return [
            'type' => 'loans',
            'summary' => $summary,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    // =========================================================================
    // HELPER & CSV BUILDER
    // =========================================================================

    protected function buildCsv(string $type, array $summary, array $rows): string
    {
        $output = fopen('php://temp', 'r+');

        // Ringkasan Laporan
        fputcsv($output, ['=== LAPORAN REKAPITULASI: '.strtoupper($type).' ===']);
        fputcsv($output, ['Dicetak Pada', Carbon::now()->toDateTimeString()]);
        foreach ($summary as $key => $val) {
            if (is_array($val)) {
                $val = json_encode($val);
            }
            fputcsv($output, [ucwords(str_replace('_', ' ', $key)), $val]);
        }
        fputcsv($output, []); // Baris kosong

        // Rincian Data
        if (! empty($rows)) {
            $headers = array_keys($rows[0]);
            fputcsv($output, array_map(fn ($h) => strtoupper(str_replace('_', ' ', $h)), $headers));

            foreach ($rows as $row) {
                fputcsv($output, array_values($row));
            }
        } else {
            fputcsv($output, ['Tidak ada data rincian untuk periode ini.']);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv ?: '';
    }

    public function isStaffOrAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin', 'teacher', 'admin_tu', 'principal', 'kesiswaan', 'humas', 'kurikulum', 'student_affairs', 'public_relations']) ||
            $user->hasPermission('reports.view');
    }

    protected function authorizeStaff(?User $user): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        if (! $this->isStaffOrAdmin($user)) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang melihat laporan rekapitulasi.');
        }
    }

    protected function validateReportType(string $type): void
    {
        if (! in_array($type, self::VALID_TYPES, true)) {
            $validList = implode(', ', self::VALID_TYPES);
            throw new HttpException(422, "Tipe laporan '{$type}' tidak didukung. Tipe yang valid: {$validList}.");
        }
    }
}
