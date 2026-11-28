<?php

namespace App\Services\Academic;

use App\Models\ProgressReport;
use App\Models\ReportCard;
use App\Services\SiteSettingService;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Carbon;

class ReportPdfRenderer
{
    public function __construct(
        protected SiteSettingService $siteSettingService
    ) {}

    /**
     * Render PDF resmi A4 E-Rapor Semester lengkap dengan kop SiteSetting, tabel UTF-8, & QR Code.
     */
    public function renderReportCard(ReportCard $report): string
    {
        $siteProfile = $this->siteSettingService->getSiteProfile();
        $snapshot = $report->data_snapshot ?? [];
        $verifyUrl = url('/verifikasi/'.$report->verify_token);
        $qrDataUri = $this->generateQrDataUri($verifyUrl);

        $pdf = Pdf::loadView('reports.report-card', [
            'report' => $report,
            'snapshot' => $snapshot,
            'siteProfile' => $siteProfile,
            'schoolHeader' => $this->resolveSchoolHeader($siteProfile, $snapshot),
            'verifyUrl' => $verifyUrl,
            'qrDataUri' => $qrDataUri,
            'issuedAtLabel' => $this->formatIssuedDate($report->updated_at ?? $report->created_at),
            'approverName' => $report->approver?->full_name
                ?? $report->approver?->name
                ?? $report->approver?->username
                ?? null,
        ])->setPaper('a4', 'portrait')->setOption([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
            'defaultFont' => 'Helvetica',
        ]);

        return $pdf->output(['compress' => 0]);
    }

    /**
     * Render PDF resmi A4 E-Progress Report Bulanan lengkap dengan kop SiteSetting, tabel UTF-8, & QR Code.
     */
    public function renderProgressReport(ProgressReport $report): string
    {
        $siteProfile = $this->siteSettingService->getSiteProfile();
        $snapshot = $report->data_snapshot ?? [];
        $verifyUrl = url('/verifikasi/'.$report->verify_token);
        $qrDataUri = $this->generateQrDataUri($verifyUrl);

        $pdf = Pdf::loadView('reports.progress-report', [
            'report' => $report,
            'snapshot' => $snapshot,
            'siteProfile' => $siteProfile,
            'schoolHeader' => $this->resolveSchoolHeader($siteProfile, $snapshot),
            'verifyUrl' => $verifyUrl,
            'qrDataUri' => $qrDataUri,
            'issuedAtLabel' => $this->formatIssuedDate($report->updated_at ?? $report->created_at),
            'approverName' => $report->approver?->full_name
                ?? $report->approver?->name
                ?? $report->approver?->username
                ?? null,
        ])->setPaper('a4', 'portrait')->setOption([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
            'defaultFont' => 'Helvetica',
        ]);

        return $pdf->output(['compress' => 0]);
    }

    protected function resolveSchoolHeader(array $siteProfile, array $snapshot): array
    {
        $identity = is_array($siteProfile['identity'] ?? null) ? $siteProfile['identity'] : [];
        $address = is_array($siteProfile['address'] ?? null) ? $siteProfile['address'] : [];
        $contact = is_array($siteProfile['contact'] ?? null) ? $siteProfile['contact'] : [];

        $schoolName = trim((string) ($identity['name'] ?? ''));
        if ($schoolName === '') {
            $schoolName = (string) ($snapshot['school_name'] ?? 'SMP Laboratorium UM');
        }

        $addressParts = array_filter([
            $address['street'] ?? null,
            $address['city'] ?? null,
            $address['postal_code'] ?? null,
        ]);

        return [
            'name' => $schoolName,
            'tagline' => (string) ($identity['tagline'] ?? 'P2LP Universitas Negeri Malang'),
            'address' => ! empty($addressParts) ? implode(', ', $addressParts) : 'Malang, Jawa Timur',
            'phone' => (string) ($contact['phone'] ?? ''),
            'email' => (string) ($contact['email'] ?? ''),
            'city' => (string) ($address['city'] ?? 'Kota Malang'),
            'logo_data_uri' => $this->resolveLocalLogoDataUri($identity['logo_url'] ?? null),
        ];
    }

    protected function resolveLocalLogoDataUri(?string $logoUrl): ?string
    {
        $candidates = [];
        if (is_string($logoUrl) && str_starts_with($logoUrl, '/')) {
            $candidates[] = public_path(ltrim($logoUrl, '/'));
        }
        $candidates[] = public_path('images/logo.png');
        $candidates[] = base_path('../web/public/images/logo.png');

        foreach ($candidates as $path) {
            if (is_string($path) && is_file($path) && is_readable($path)) {
                $mime = @mime_content_type($path) ?: 'image/png';
                $raw = @file_get_contents($path);
                if ($raw !== false) {
                    return 'data:'.$mime.';base64,'.base64_encode($raw);
                }
            }
        }

        return null;
    }

    protected function generateQrDataUri(string $payload): string
    {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => true,
            'scale' => 4,
            'imageTransparent' => false,
        ]);

        return (new QRCode($options))->render($payload);
    }

    protected function formatIssuedDate(mixed $date): string
    {
        if (! $date) {
            return date('d F Y');
        }

        return Carbon::parse($date)->locale('id')->translatedFormat('d F Y');
    }
}
