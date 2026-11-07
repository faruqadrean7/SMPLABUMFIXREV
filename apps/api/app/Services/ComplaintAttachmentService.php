<?php

namespace App\Services;

use App\Models\File;
use Carbon\Carbon;
use finfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ComplaintAttachmentService
{
    public const MAX_SIZE_BYTES = 5242880; // 5 MB

    public const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * BE-GUEST-04: Presign URL unggah lampiran foto pengaduan publik.
     */
    public function presignAttachment(array $data, Request $request): array
    {
        $filename = $data['filename'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = match ($data['mime_type']) {
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg',
            };
        }

        $token = (string) Str::uuid();
        $randomFileName = Str::uuid()->toString().'.'.$ext;
        $now = Carbon::now();
        $objectKey = "temp/complaints/{$now->year}/{$now->format('m')}/{$randomFileName}";
        $ttlSeconds = 900; // 15 menit
        $expiresAt = Carbon::now()->addSeconds($ttlSeconds);

        $cacheData = [
            'token' => $token,
            'bucket' => 'private',
            'object_key' => $objectKey,
            'mime_type' => $data['mime_type'],
            'size_bytes' => (int) $data['size_bytes'],
            'visibility' => 'privat',
            'purpose' => 'complaint_attachment',
            'uploaded' => false,
            'expires_at' => $expiresAt->toIso8601String(),
        ];

        Cache::put('file_presign:'.$token, $cacheData, $ttlSeconds);

        $uploadUrl = URL::temporarySignedRoute(
            'files.upload.signed',
            $expiresAt,
            ['token' => $token]
        );

        return [
            'token' => $token,
            'upload_url' => $uploadUrl,
            'method' => 'POST',
            'bucket' => 'private',
            'object_key' => $objectKey,
            'mime_type' => $data['mime_type'],
            'size_bytes' => (int) $data['size_bytes'],
            'max_size_bytes' => self::MAX_SIZE_BYTES,
            'expires_at' => $cacheData['expires_at'],
            'ttl_seconds' => $ttlSeconds,
        ];
    }

    /**
     * BE-GUEST-04: Konfirmasi unggahan lampiran, pembersihan total metadata via re-encode citra,
     * dan penyimpanan ke bucket privat terenkripsi.
     */
    public function confirmAttachment(string $token, Request $request): File
    {
        $cacheData = Cache::get('file_presign:'.$token);
        if (! $cacheData) {
            throw new HttpException(400, 'Sesi unggah lampiran tidak valid atau telah kedaluwarsa.');
        }

        $rawBytes = null;
        $tempKey = $cacheData['object_key'] ?? null;

        if ($request->hasFile('file')) {
            $uploaded = $request->file('file');
            $rawBytes = file_get_contents($uploaded->getRealPath());
        } elseif ($tempKey && Storage::disk('local')->exists($tempKey)) {
            $rawBytes = Storage::disk('local')->get($tempKey);
        }

        if (empty($rawBytes)) {
            throw new HttpException(400, 'Berkas fisik belum diunggah.');
        }

        $size = strlen($rawBytes);
        if ($size > self::MAX_SIZE_BYTES) {
            if ($tempKey && Storage::disk('local')->exists($tempKey)) {
                Storage::disk('local')->delete($tempKey);
            }
            Cache::forget('file_presign:'.$token);
            throw new HttpException(400, 'Ukuran berkas melebihi batas maksimum 5 MB.');
        }

        // AC3: Validasi MIME & Magic Bytes
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->buffer($rawBytes);

        if (! in_array($detectedMime, self::ALLOWED_MIMES, true)) {
            if ($tempKey && Storage::disk('local')->exists($tempKey)) {
                Storage::disk('local')->delete($tempKey);
            }
            Cache::forget('file_presign:'.$token);
            throw new HttpException(400, 'Format berkas tidak diizinkan. Hanya gambar JPG, PNG, atau WebP.');
        }

        $isJpeg = str_starts_with($rawBytes, "\xFF\xD8\xFF");
        $isPng = str_starts_with($rawBytes, "\x89PNG\r\n\x1a\n");
        $isWebp = str_starts_with($rawBytes, 'RIFF') && substr($rawBytes, 8, 4) === 'WEBP';

        if (! ($isJpeg || $isPng || $isWebp)) {
            if ($tempKey && Storage::disk('local')->exists($tempKey)) {
                Storage::disk('local')->delete($tempKey);
            }
            Cache::forget('file_presign:'.$token);
            throw new HttpException(400, 'Integritas berkas gambar tidak valid.');
        }

        // AC2: Re-encode citra untuk menghapus 100% metadata EXIF/GPS/XMP
        $cleanBytes = $this->reencodeImage($rawBytes, $detectedMime);

        // AC1: Simpan ke bucket privat dengan nama berkas acak
        $now = Carbon::now();
        $ext = match ($detectedMime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $finalFileName = Str::uuid()->toString().'.'.$ext;
        $finalObjectKey = "private/complaints/{$now->year}/{$now->format('m')}/{$finalFileName}";

        Storage::disk('local')->put($finalObjectKey, $cleanBytes);

        // Bersihkan berkas sementara & cache token
        if ($tempKey && Storage::disk('local')->exists($tempKey)) {
            Storage::disk('local')->delete($tempKey);
        }
        Cache::forget('file_presign:'.$token);

        // AC4: Catat ke tabel files tanpa menyimpan identitas pelapor/IP
        return File::create([
            'school_id' => null,
            'owner_id' => null,
            'owner_type' => 'complaint_pending',
            'bucket' => 'private',
            'object_key' => $finalObjectKey,
            'mime_type' => $detectedMime,
            'size_bytes' => strlen($cleanBytes),
            'visibility' => 'privat',
            'checksum' => hash('sha256', $cleanBytes),
        ]);
    }

    /**
     * Re-encode raster citra untuk membersihkan seluruh metadata EXIF/GPS.
     */
    protected function reencodeImage(string $rawBytes, string $mimeType): string
    {
        $gdImage = @imagecreatefromstring($rawBytes);
        if (! $gdImage) {
            throw new HttpException(400, 'Citra rusak atau tidak dapat diproses.');
        }

        ob_start();
        try {
            if ($mimeType === 'image/png') {
                imagealphablending($gdImage, false);
                imagesavealpha($gdImage, true);
                imagepng($gdImage, null, 8);
            } elseif ($mimeType === 'image/webp') {
                imagewebp($gdImage, null, 85);
            } else {
                imagejpeg($gdImage, null, 85);
            }
            $clean = ob_get_clean();
        } finally {
            if (PHP_VERSION_ID < 80500) {
                imagedestroy($gdImage);
            }
        }

        if (empty($clean)) {
            throw new HttpException(500, 'Gagal mengodekan ulang citra.');
        }

        return $clean;
    }
}
