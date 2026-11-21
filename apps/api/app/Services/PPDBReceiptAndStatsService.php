<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\PPDBTrack;
use App\Models\PPDBWave;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PPDBReceiptAndStatsService
{
    /**
     * BE-PPDB-09: Get receipt data for applicant (IDOR protected).
     */
    public function getReceiptData(string $applicantId, Request $request): array
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        $applicant = Applicant::with(['wave', 'track', 'reRegistration', 'parents'])->findOrFail($applicantId);

        // IDOR Protection: only owner or staff with ppdb.applicant.read / ppdb.applicant.verify
        if ($applicant->user_id !== $currentUser->id
            && ! $currentUser->hasPermission('ppdb.applicant.read')
            && ! $currentUser->hasPermission('ppdb.applicant.verify')) {
            throw new HttpException(403, 'Anda tidak memiliki hak akses untuk melihat bukti pendaftaran ini');
        }

        // Must not be draft
        if ($applicant->status === 'draft') {
            throw new HttpException(422, 'Bukti pendaftaran belum dapat diterbitkan untuk formulir pendaftaran yang masih berupa draf');
        }

        $tokenPayload = $applicant->registration_no.'|'.$applicant->created_at->timestamp;
        $verificationToken = hash('sha256', $tokenPayload);
        $qrContent = 'PPDB-RECEIPT:'.$applicant->registration_no.':'.substr($verificationToken, 0, 16);
        $verificationUrl = url('/verifikasi/'.substr($verificationToken, 0, 32));

        $stepData = $applicant->step_data ?? [];
        $personal = $stepData['personal'] ?? [];

        return [
            'registration_no' => $applicant->registration_no,
            'applicant' => [
                'id' => $applicant->id,
                'full_name' => $applicant->full_name,
                'birth_date' => $applicant->birth_date?->format('Y-m-d'),
                'gender' => $personal['gender'] ?? ($personal['jenis_kelamin'] ?? 'L'),
                'status' => $applicant->status,
                'decision' => $applicant->decision,
                'score' => $applicant->score,
                'rank' => $applicant->rank,
            ],
            'wave' => [
                'id' => $applicant->wave?->id,
                'name' => $applicant->wave?->name,
            ],
            'track' => [
                'id' => $applicant->track?->id,
                'name' => $applicant->track?->name,
                'code' => $applicant->track?->code,
            ],
            'submission_date' => $applicant->created_at?->format('Y-m-d H:i:s'),
            'qr_content' => $qrContent,
            'verification_url' => $verificationUrl,
            'signature' => hash_hmac('sha256', $applicant->registration_no.'|'.$applicant->status, config('app.key')),
            'issued_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * BE-PPDB-09: Render PDF receipt document.
     */
    public function renderReceiptPdf(string $applicantId, Request $request): Response
    {
        $receipt = $this->getReceiptData($applicantId, $request);
        $pdfContent = $this->generatePdfString($receipt);
        $filename = 'bukti-pendaftaran-'.$receipt['registration_no'].'.pdf';

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => strlen($pdfContent),
        ]);
    }

    /**
     * BE-PPDB-09: Get PPDB statistics with optional filters.
     * Dapat diakses publik secara anonim tanpa memaparkan data sensitif (PII).
     */
    public function getStats(array $filters = []): array
    {
        $query = Applicant::query();

        if (! empty($filters['wave_id'])) {
            $query->where('wave_id', $filters['wave_id']);
        }
        if (! empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        // 1. Total applicants
        $totalApplicants = (clone $query)->count();
        $submittedApplicants = (clone $query)->where('status', '!=', 'draft')->count();

        // 2. By status
        $statusCounts = (clone $query)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // 3. By decision
        $decisionCounts = (clone $query)
            ->whereNotNull('decision')
            ->select('decision', DB::raw('count(*) as count'))
            ->groupBy('decision')
            ->pluck('count', 'decision')
            ->toArray();

        // 4. By track breakdown
        $tracksQuery = PPDBTrack::with('wave');
        if (! empty($filters['wave_id'])) {
            $tracksQuery->where('wave_id', $filters['wave_id']);
        }
        $tracks = $tracksQuery->get()->map(function ($track) use ($filters) {
            $trackAppQuery = Applicant::where('track_id', $track->id);
            if (! empty($filters['start_date'])) {
                $trackAppQuery->whereDate('created_at', '>=', $filters['start_date']);
            }
            if (! empty($filters['end_date'])) {
                $trackAppQuery->whereDate('created_at', '<=', $filters['end_date']);
            }

            $total = (clone $trackAppQuery)->count();
            $submitted = (clone $trackAppQuery)->where('status', '!=', 'draft')->count();
            $verified = (clone $trackAppQuery)->whereIn('status', ['verified', 'selected', 'waitlist', 'enrolled'])->count();
            $accepted = (clone $trackAppQuery)->where('decision', 'diterima')->count();
            $enrolled = (clone $trackAppQuery)->where('status', 'enrolled')->count();
            $fillPercentage = $track->quota > 0 ? round(($total / $track->quota) * 100, 2) : 0;

            return [
                'track_id' => $track->id,
                'wave_id' => $track->wave_id,
                'wave_name' => $track->wave?->name,
                'code' => $track->code,
                'name' => $track->name,
                'quota' => $track->quota,
                'total_applicants' => $total,
                'submitted_applicants' => $submitted,
                'verified_count' => $verified,
                'accepted_count' => $accepted,
                'enrolled_count' => $enrolled,
                'fill_percentage' => $fillPercentage,
            ];
        });

        // 5. Daily trend (per day)
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $dailyQuery = (clone $query)
                ->select(DB::raw("strftime('%Y-%m-%d', created_at) as date"), DB::raw('count(*) as count'))
                ->groupBy('date')
                ->orderBy('date', 'asc');
        } else {
            $dailyQuery = (clone $query)
                ->select(DB::raw("TO_CHAR(created_at, 'YYYY-MM-DD') as date"), DB::raw('count(*) as count'))
                ->groupBy(DB::raw("TO_CHAR(created_at, 'YYYY-MM-DD')"))
                ->orderBy('date', 'asc');
        }

        $byDay = $dailyQuery->get()->map(fn ($row) => [
            'date' => $row->date,
            'count' => (int) $row->count,
        ])->values()->toArray();

        // 6. Active wave summary
        $activeWave = PPDBWave::where('status', 'aktif')->first();
        $activeWaveSummary = $activeWave ? [
            'id' => $activeWave->id,
            'name' => $activeWave->name,
            'open_at' => $activeWave->open_at?->toIso8601String(),
            'close_at' => $activeWave->close_at?->toIso8601String(),
            'status' => $activeWave->status,
        ] : null;

        return [
            'summary' => [
                'total_applicants' => $totalApplicants,
                'submitted_applicants' => $submittedApplicants,
            ],
            'by_status' => $statusCounts,
            'by_decision' => $decisionCounts,
            'by_track' => $tracks->toArray(),
            'by_day' => $byDay,
            'active_wave' => $activeWaveSummary,
        ];
    }

    /**
     * Generate pure, valid PDF 1.4 binary content string.
     */
    protected function generatePdfString(array $receipt): string
    {
        $title = 'BUKTI PENDAFTARAN PPDB';
        $regNo = 'No. Registrasi: '.($receipt['registration_no'] ?? '-');
        $name = 'Nama Lengkap: '.($receipt['applicant']['full_name'] ?? '-');
        $track = 'Jalur: '.($receipt['track']['name'] ?? '-');
        $wave = 'Gelombang: '.($receipt['wave']['name'] ?? '-');
        $status = 'Status: '.strtoupper($receipt['applicant']['status'] ?? '-');
        $date = 'Waktu Cetak: '.Carbon::now()->format('d-m-Y H:i:s');
        $qr = 'Kode Verifikasi QR: '.($receipt['qr_content'] ?? '-');

        $lines = [$title, $regNo, $name, $track, $wave, $status, $date, $qr];

        $streamContent = "BT\n/F1 16 Tf\n50 750 Td\n(".addcslashes($title, '()\\').") Tj\n";
        $streamContent .= "/F1 11 Tf\n0 -30 Td\n";

        for ($i = 1; $i < count($lines); $i++) {
            $streamContent .= '('.addcslashes($lines[$i], '()\\').") Tj\n0 -20 Td\n";
        }
        $streamContent .= "ET\n";

        $streamLen = strlen($streamContent);

        $objects = [];
        $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";
        $objects[] = "4 0 obj\n<< /Length ".$streamLen." >>\nstream\n".$streamContent."endstream\nendobj\n";
        $objects[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= $obj;
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n".$xrefOffset."\n%%EOF\n";

        return $pdf;
    }
}
