<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>E-Progress Report — {{ $snapshot['student']['full_name'] ?? 'Siswa' }}</title>
    <style>
        @page { margin: 16mm 15mm; }
        body { font-family: Helvetica, 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #1e293b; line-height: 1.4; }
        .kop-table { width: 100%; border-bottom: 2.5px solid #0a3d62; padding-bottom: 8px; margin-bottom: 12px; }
        .kop-logo { width: 58px; vertical-align: middle; }
        .kop-logo img { width: 52px; height: 52px; object-fit: contain; }
        .kop-text { text-align: center; }
        .kop-title { font-size: 13pt; font-weight: bold; color: #0a3d62; text-transform: uppercase; margin: 0; }
        .kop-tagline { font-size: 8.5pt; color: #475569; margin: 2px 0; }
        .kop-meta { font-size: 8pt; color: #64748b; margin: 0; }
        .doc-heading { text-align: center; margin: 8px 0 12px; }
        .doc-heading h2 { font-size: 11pt; margin: 0; color: #0f172a; text-transform: uppercase; }
        .doc-heading p { font-size: 8.5pt; margin: 2px 0 0; color: #475569; }
        .ident-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 9pt; }
        .ident-table td { padding: 3px 6px; vertical-align: top; }
        .ident-label { width: 18%; color: #475569; }
        .ident-val { width: 32%; font-weight: bold; color: #0f172a; }
        .section-title { font-size: 9.5pt; font-weight: bold; color: #0a3d62; margin: 12px 0 5px; text-transform: uppercase; }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 8.5pt; }
        .data-table th, .data-table td { border: 1px solid #cbd5e1; padding: 5px 6px; vertical-align: top; }
        .data-table th { background-color: #f1f5f9; color: #0f172a; font-weight: bold; text-align: left; }
        .text-center { text-align: center !important; }
        .note-box { border: 1px solid #cbd5e1; background: #f8fafc; padding: 7px 9px; border-radius: 4px; font-size: 8.5pt; }
        .ttd-table { width: 100%; border-collapse: collapse; margin-top: 18px; page-break-inside: avoid; }
        .ttd-table td { width: 33.3%; text-align: center; vertical-align: top; padding: 4px 6px; font-size: 8.5pt; }
        .qr-box { border: 1px solid #cbd5e1; background: #f8fafc; padding: 6px; border-radius: 4px; display: inline-block; }
        .qr-img { width: 76px; height: 76px; }
        .token-text { font-size: 6.5pt; color: #475569; word-break: break-all; margin-top: 3px; }
        .ttd-space { height: 52px; }
        .ttd-name { font-weight: bold; text-decoration: underline; color: #0f172a; }
    </style>
</head>
<body>
@php
    $student = $snapshot['student'] ?? [];
    $period = $snapshot['period'] ?? [];
    $att = $snapshot['attendance'] ?? [];
    $char = $snapshot['character'] ?? [];
    $grades = $snapshot['grades'] ?? [];
@endphp

<table class="kop-table">
    <tr>
        @if(!empty($schoolHeader['logo_data_uri']))
            <td class="kop-logo">
                <img src="{{ $schoolHeader['logo_data_uri'] }}" alt="Logo Sekolah">
            </td>
        @endif
        <td class="kop-text">
            <p class="kop-title">{{ $schoolHeader['name'] }}</p>
            <p class="kop-tagline">{{ $schoolHeader['tagline'] }}</p>
            <p class="kop-meta">
                {{ $schoolHeader['address'] }}
                @if(!empty($schoolHeader['phone'])) | Telp. {{ $schoolHeader['phone'] }} @endif
                @if(!empty($schoolHeader['email'])) | {{ $schoolHeader['email'] }} @endif
            </p>
        </td>
    </tr>
</table>

<div class="doc-heading">
    <h2>E-PROGRESS REPORT BULANAN PESERTA DIDIK</h2>
    <p>Status Pengesahan: <strong>{{ strtoupper((string) $report->status) }}</strong></p>
</div>

<table class="ident-table">
    <tr>
        <td class="ident-label">Nama Siswa</td>
        <td class="ident-val">: {{ $student['full_name'] ?? '-' }}</td>
        <td class="ident-label">Kelas</td>
        <td class="ident-val">: {{ $student['class_name'] ?? '-' }}</td>
    </tr>
    <tr>
        <td class="ident-label">NIS / NISN</td>
        <td class="ident-val">: {{ $student['nis'] ?? '-' }} / {{ $student['nisn'] ?? '-' }}</td>
        <td class="ident-label">Periode Bulan</td>
        <td class="ident-val">: {{ $period['month'] ?? '-' }} ({{ $period['semester_name'] ?? '-' }})</td>
    </tr>
</table>

<div class="section-title">A. Ringkasan Nilai Mata Pelajaran</div>
<table class="data-table">
    <thead>
        <tr>
            <th style="width: 6%;" class="text-center">No</th>
            <th style="width: 64%;">Mata Pelajaran</th>
            <th style="width: 30%;" class="text-center">Skor Akhir Sementara</th>
        </tr>
    </thead>
    <tbody>
        @forelse($grades as $idx => $g)
            <tr>
                <td class="text-center">{{ $idx + 1 }}</td>
                <td><strong>{{ $g['subject_name'] ?? 'Mata Pelajaran' }}</strong></td>
                <td class="text-center"><strong>{{ $g['final_score'] ?? 0 }}</strong></td>
            </tr>
        @empty
            <tr>
                <td colspan="3" class="text-center">Belum ada ringkasan nilai pada periode bulan ini.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="section-title">B. Rekap Presensi Bulanan</div>
<table class="data-table">
    <thead>
        <tr>
            <th class="text-center">Hadir</th>
            <th class="text-center">Sakit</th>
            <th class="text-center">Izin</th>
            <th class="text-center">Alpa</th>
            <th class="text-center">Persentase Kehadiran</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="text-center">{{ $att['hadir'] ?? 0 }}</td>
            <td class="text-center">{{ $att['sakit'] ?? 0 }}</td>
            <td class="text-center">{{ $att['izin'] ?? 0 }}</td>
            <td class="text-center">{{ $att['alpa'] ?? 0 }}</td>
            <td class="text-center"><strong>{{ $att['percentage'] ?? 0 }}%</strong></td>
        </tr>
    </tbody>
</table>

<div class="section-title">C. Dimensi Karakter &amp; Catatan Wali Kelas</div>
<table class="data-table">
    <thead>
        <tr>
            <th style="width: 45%;">Dimensi Profil Pelajar Pancasila</th>
            <th style="width: 55%;">Capaian</th>
        </tr>
    </thead>
    <tbody>
        @if(!empty($char['scale_data']) && is_array($char['scale_data']))
            @foreach($char['scale_data'] as $dim => $val)
                <tr>
                    <td>{{ ucwords(str_replace('_', ' ', (string) $dim)) }}</td>
                    <td>{{ is_string($val) ? ucwords(str_replace('_', ' ', $val)) : (string) $val }}</td>
                </tr>
            @endforeach
        @else
            <tr>
                <td colspan="2" class="text-center">Belum ada catatan dimensi karakter.</td>
            </tr>
        @endif
    </tbody>
</table>

<div class="note-box">
    <strong>Catatan Wali Kelas:</strong><br>
    {{ $char['homeroom_note'] ?? '-' }}
</div>

<table class="ttd-table">
    <tr>
        <td>
            Mengetahui,<br>
            Orang Tua / Wali Murid
            <div class="ttd-space"></div>
            <div class="ttd-name">( ...................................... )</div>
        </td>
        <td>
            <div class="qr-box">
                <img class="qr-img" src="{{ $qrDataUri }}" alt="QR Verifikasi Progress Report">
                <div class="token-text">Token: {{ $report->verify_token }}</div>
            </div>
            <div style="font-size:7pt;color:#64748b;margin-top:3px;">Verifikasi: {{ $verifyUrl }}</div>
        </td>
        <td>
            {{ $schoolHeader['city'] }}, {{ $issuedAtLabel }}<br>
            Kepala Sekolah {{ $schoolHeader['name'] }}
            <div class="ttd-space"></div>
            <div class="ttd-name">{{ $approverName ?: '( Menunggu Pengesahan )' }}</div>
        </td>
    </tr>
</table>
</body>
</html>
