<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kode OTP PPDB SMP Laboratorium UM</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f1f5f9;
            margin: 0;
            padding: 24px;
            color: #1e293b;
        }
        .container {
            max-width: 520px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }
        .header {
            background-color: #1e3a8a;
            color: #ffffff;
            padding: 24px 32px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .header p {
            margin: 4px 0 0 0;
            font-size: 13px;
            opacity: 0.85;
        }
        .content {
            padding: 32px;
        }
        .greeting {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .text {
            font-size: 14px;
            line-height: 1.6;
            color: #475569;
            margin-bottom: 24px;
        }
        .otp-box {
            background: #eff6ff;
            border: 2px dashed #3b82f6;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 24px 0;
        }
        .otp-label {
            font-size: 12px;
            color: #1e40af;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }
        .otp-code {
            font-size: 32px;
            font-weight: 800;
            letter-spacing: 8px;
            color: #1e3a8a;
            margin: 0;
            font-family: monospace;
        }
        .expiry-note {
            font-size: 12px;
            color: #64748b;
            text-align: center;
            margin-top: 8px;
        }
        .security-note {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 12px 16px;
            border-radius: 4px;
            font-size: 12px;
            color: #92400e;
            margin-top: 24px;
            line-height: 1.5;
        }
        .footer {
            border-top: 1px solid #f1f5f9;
            padding: 20px 32px;
            font-size: 11px;
            color: #94a3b8;
            text-align: center;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>SMP Laboratorium UM</h1>
            <p>Penerimaan Peserta Didik Baru (PPDB)</p>
        </div>
        <div class="content">
            <div class="greeting">Halo, {{ $fullName }}!</div>
            <p class="text">
                Terima kasih telah mendaftar di Portal PPDB SMP Laboratorium Universitas Negeri Malang. 
                Gunakan kode verifikasi berikut untuk mengaktifkan akun pendaftaran Anda:
            </p>
            
            <div class="otp-box">
                <div class="otp-label">Kode Verifikasi (OTP)</div>
                <div class="otp-code">{{ $otp }}</div>
                <div class="expiry-note">Berlaku selama 15 menit</div>
            </div>

            <div class="security-note">
                <strong>Peringatan Keamanan:</strong> Jangan bagikan kode OTP ini kepada siapa pun, termasuk panitia sekolah. Panitia tidak pernah meminta kode OTP calon siswa.
            </div>
        </div>
        <div class="footer">
            Email ini dikirim secara otomatis oleh Sistem Informasi Sekolah Terpadu (SISTLABUM).<br>
            Jika Anda tidak merasa mendaftar di PPDB SMP Laboratorium UM, abaikan email ini.
        </div>
    </div>
</body>
</html>
