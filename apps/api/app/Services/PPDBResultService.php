<?php

namespace App\Services;

use App\Models\Applicant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PPDBResultService
{
    /**
     * BE-PPDB-07: Public check selection result with registration number & birth date.
     * Protected by rate limiting (max 10 attempts per minute per IP).
     */
    public function checkPublicResult(array $data, Request $request): array
    {
        $regNo = trim($data['registration_no']);
        $birthDate = trim($data['birth_date']);

        // 1. Rate Limiting anti-brute force (10 requests per minute)
        $rateLimitKey = 'ppdb_result_check:'.$request->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            throw new HttpException(
                429,
                "Terlalu banyak permintaan pengecekan hasil seleksi. Silakan coba kembali dalam {$seconds} detik."
            );
        }

        // 2. Find applicant
        $applicant = Applicant::with(['wave', 'track'])
            ->where('registration_no', $regNo)
            ->first();

        if (! $applicant || $applicant->birth_date?->format('Y-m-d') !== $birthDate) {
            RateLimiter::hit($rateLimitKey, 60);

            throw new HttpException(404, 'Data pendaftaran dengan nomor dan tanggal lahir tersebut tidak ditemukan');
        }

        // Clear rate limiter on valid match
        RateLimiter::clear($rateLimitKey);

        $wave = $applicant->wave;

        // 3. Time Gate: must be after result_publish_at
        if ($wave && Carbon::now()->lt($wave->result_publish_at)) {
            $publishTimeStr = $wave->result_publish_at->translatedFormat('d F Y H:i');

            throw new HttpException(
                400,
                "Pengumuman hasil seleksi belum dibuka. Hasil akan dipublikasikan pada tanggal {$publishTimeStr} WIB."
            );
        }

        // 4. Check if decision is ready
        if (empty($applicant->decision)) {
            throw new HttpException(
                400,
                'Hasil seleksi untuk pendaftaran ini sedang dalam proses finalisasi oleh panitia PPDB.'
            );
        }

        // 5. Instruction & next step guide
        $instruction = match ($applicant->decision) {
            'diterima' => 'Selamat! Anda dinyatakan DITERIMA. Silakan persiapkan berkas asli dan lakukan konfirmasi daftar ulang sesuai jadwal yang ditentukan.',
            'cadangan' => 'Anda berada dalam status CADANGAN (WAITLIST). Harap memantau berkala perkembangan kuota apabila ada peserta diterima yang tidak melakukan daftar ulang.',
            'ditolak' => 'Mohon maaf, Anda belum berhasil pada seleksi kali ini. Terima kasih atas partisipasi dan perjuangan Anda dalam PPDB sekolah kami.',
            default => 'Silakan menghubungi panitia PPDB untuk informasi tindak lanjut hasil seleksi Anda.',
        };

        $nextStep = match ($applicant->decision) {
            'diterima' => 're_registration',
            'cadangan' => 'waitlist_monitoring',
            default => 'closed',
        };

        return [
            'registration_no' => $applicant->registration_no,
            'full_name' => $applicant->full_name,
            'birth_date' => $applicant->birth_date?->format('Y-m-d'),
            'wave_id' => $wave?->id,
            'wave_name' => $wave?->name,
            'track_code' => $applicant->track?->code,
            'track_name' => $applicant->track?->name,
            'score' => $applicant->score,
            'rank' => $applicant->rank,
            'decision' => $applicant->decision,
            'status' => $applicant->status,
            'instruction' => $instruction,
            'next_step' => $nextStep,
            'published_at' => $wave?->result_publish_at?->toIso8601String(),
        ];
    }
}
