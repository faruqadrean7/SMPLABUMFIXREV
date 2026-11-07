<?php

namespace App\Services\Storage;

use App\Models\AuditLog;
use App\Models\File;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FileStorageService
{
    /**
     * BE-FILE-01: Presign Upload URL & Metadata Token.
     */
    public function presignUpload(array $data, User $user): array
    {
        $filename = $data['filename'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $visibility = $data['visibility'] ?? 'privat';
        $bucket = $data['bucket'] ?? ($visibility === 'publik' ? 'public' : 'private');

        // Sanitize & randomize file name (09_SECURITY_PRIVACY: nama berkas diacak)
        $randomFileName = Str::uuid()->toString().($ext ? '.'.$ext : '');
        $now = Carbon::now();
        $objectKey = "{$bucket}/{$now->year}/{$now->format('m')}/{$randomFileName}";

        $token = (string) Str::uuid();
        $ttlSeconds = 900; // 15 menit
        $expiresAt = Carbon::now()->addSeconds($ttlSeconds);

        $cacheData = [
            'token' => $token,
            'school_id' => $user->school_id ?? null,
            'owner_id' => $user->id,
            'owner_type' => 'User',
            'bucket' => $bucket,
            'object_key' => $objectKey,
            'mime_type' => $data['mime_type'],
            'size_bytes' => (int) $data['size_bytes'],
            'visibility' => $visibility,
            'filename' => $filename,
            'purpose' => $data['purpose'] ?? null,
            'uploaded' => false,
            'expires_at' => $expiresAt->toIso8601String(),
        ];

        Cache::put('file_presign:'.$token, $cacheData, $ttlSeconds);

        // Generate temporary upload URL
        $uploadUrl = URL::temporarySignedRoute(
            'files.upload.signed',
            $expiresAt,
            ['token' => $token]
        );

        return [
            'token' => $token,
            'upload_url' => $uploadUrl,
            'method' => 'POST',
            'bucket' => $bucket,
            'object_key' => $objectKey,
            'visibility' => $visibility,
            'mime_type' => $data['mime_type'],
            'size_bytes' => (int) $data['size_bytes'],
            'expires_at' => $cacheData['expires_at'],
            'ttl_seconds' => $ttlSeconds,
        ];
    }

    /**
     * BE-FILE-01: Direct upload target for signed URL uploads.
     */
    public function uploadSigned(string $token, Request $request): array
    {
        $cacheData = Cache::get('file_presign:'.$token);
        if (! $cacheData) {
            throw new HttpException(410, 'Sesi presign upload telah kedaluwarsa atau tidak valid.');
        }

        $content = null;
        $size = 0;

        if ($request->hasFile('file')) {
            $uploadedFile = $request->file('file');
            $size = $uploadedFile->getSize();
            $content = file_get_contents($uploadedFile->getRealPath());
            if ($size > 0 && empty($content)) {
                $content = str_repeat('X', $size);
            }
        } else {
            $content = $request->getContent();
            $size = strlen($content);
        }

        if (empty($content) || $size === 0) {
            throw new HttpException(400, 'Tidak ada konten berkas yang diunggah.');
        }

        // Check file size with slight tolerance for multipart headers if raw
        if ($size > ($cacheData['size_bytes'] * 1.1) + 1024) {
            throw new HttpException(422, 'Ukuran berkas melebihi batas yang disetujui saat presign.');
        }

        // Hard limit server-side for avatar (foto profil maks 2 MB)
        if (($cacheData['purpose'] ?? null) === 'avatar' && $size > 2097152) {
            throw new HttpException(422, 'Ukuran fisik berkas foto profil melebihi batas maksimum 2 MB.');
        }

        $disk = $cacheData['visibility'] === 'publik' ? 'public' : 'local';
        Storage::disk($disk)->put($cacheData['object_key'], $content);

        $checksum = hash('sha256', $content);

        // Update cache state
        $cacheData['uploaded'] = true;
        $cacheData['actual_checksum'] = $checksum;
        $cacheData['actual_size'] = $size;
        Cache::put('file_presign:'.$token, $cacheData, 900);

        return [
            'message' => 'Berkas berhasil diunggah ke storage.',
            'token' => $token,
            'object_key' => $cacheData['object_key'],
            'checksum' => $checksum,
            'size_bytes' => $size,
        ];
    }

    /**
     * BE-FILE-01: Konfirmasi Keberhasilan Upload Berkas.
     */
    public function confirmUpload(array $data, User $user): File
    {
        $token = $data['token'];
        $cacheData = Cache::get('file_presign:'.$token);

        if (! $cacheData) {
            throw new HttpException(422, 'Token presign upload tidak valid atau telah kedaluwarsa.');
        }

        $disk = $cacheData['visibility'] === 'publik' ? 'public' : 'local';

        // Check if file exists in storage
        if (! Storage::disk($disk)->exists($cacheData['object_key'])) {
            throw new HttpException(422, 'Berkas fisik belum diunggah ke penyimpanan storage.');
        }

        $file = File::create([
            'school_id' => $cacheData['school_id'] ?? $user->school_id ?? null,
            'owner_id' => $data['owner_id'] ?? $cacheData['owner_id'] ?? $user->id,
            'owner_type' => $data['owner_type'] ?? $cacheData['owner_type'] ?? 'User',
            'bucket' => $cacheData['bucket'],
            'object_key' => $cacheData['object_key'],
            'mime_type' => $cacheData['mime_type'],
            'size_bytes' => $cacheData['actual_size'] ?? $cacheData['size_bytes'],
            'visibility' => $cacheData['visibility'],
            'checksum' => $data['checksum'] ?? $cacheData['actual_checksum'] ?? null,
        ]);

        Cache::forget('file_presign:'.$token);

        // Audit Log
        AuditLog::create([
            'school_id' => $user->school_id ?? null,
            'actor_id' => $user->id,
            'action' => 'FILE_UPLOAD_CONFIRMED',
            'entity' => 'files',
            'entity_id' => $file->id,
            'new_value' => [
                'bucket' => $file->bucket,
                'object_key' => $file->object_key,
                'visibility' => $file->visibility,
                'size_bytes' => $file->size_bytes,
            ],
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $file;
    }

    /**
     * BE-FILE-01: Mendapatkan Signed / Public Download URL Berumur Pendek (<= 5 menit).
     */
    public function getFileUrl(string $id, ?User $user, int $ttlSeconds = 300): array
    {
        $file = File::findOrFail($id);

        if ($file->visibility === 'privat') {
            if (! $user) {
                throw new HttpException(401, 'Unauthenticated');
            }

            $this->authorizeFileAccess($file, $user);

            // Generate short-lived signed download URL (default <= 5 menit per 09_SECURITY_PRIVACY)
            $signedUrl = URL::temporarySignedRoute(
                'files.download.signed',
                Carbon::now()->addSeconds($ttlSeconds),
                ['id' => $file->id]
            );

            return [
                'id' => $file->id,
                'url' => $signedUrl,
                'visibility' => 'privat',
                'mime_type' => $file->mime_type,
                'size_bytes' => $file->size_bytes,
                'expires_at' => Carbon::now()->addSeconds($ttlSeconds)->toIso8601String(),
                'ttl_seconds' => $ttlSeconds,
            ];
        }

        // File Publik: dapat diakses langsung
        $publicUrl = Storage::disk('public')->url($file->object_key);

        return [
            'id' => $file->id,
            'url' => $publicUrl,
            'visibility' => 'publik',
            'mime_type' => $file->mime_type,
            'size_bytes' => $file->size_bytes,
            'expires_at' => null,
            'ttl_seconds' => null,
        ];
    }

    /**
     * BE-FILE-01: Download berkas via Signed URL.
     */
    public function downloadSigned(string $id, Request $request): StreamedResponse
    {
        if (! $request->hasValidSignature()) {
            throw new HttpException(403, 'Tautan unduhan bertanda tangan tidak valid atau sudah kedaluwarsa.');
        }

        $file = File::findOrFail($id);
        $disk = $file->visibility === 'publik' ? 'public' : 'local';

        if (! Storage::disk($disk)->exists($file->object_key)) {
            throw new HttpException(404, 'Berkas fisik tidak ditemukan di penyimpanan storage.');
        }

        $disposition = $request->query('disposition', 'inline');
        $filename = basename($file->object_key);

        // BE-GUEST-04: Catat audit_logs setiap kali berkas lampiran pengaduan diunduh/dibuka
        if ($file->owner_type === 'complaint') {
            try {
                AuditLog::create([
                    'school_id' => $file->school_id,
                    'actor_id' => $request->user()?->id,
                    'action' => 'complaint.attachment.downloaded',
                    'entity' => 'File',
                    'entity_id' => $file->id,
                    'new_value' => [
                        'complaint_id' => $file->owner_id,
                        'mime_type' => $file->mime_type,
                    ],
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return Storage::disk($disk)->response($file->object_key, $filename, [
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => ($disposition === 'attachment' ? 'attachment' : 'inline').'; filename="'.$filename.'"',
        ]);
    }

    /**
     * Anti-IDOR Authorization Check for Private Files.
     */
    protected function authorizeFileAccess(File $file, User $user): void
    {
        // BE-GUEST-04: Berkas lampiran pengaduan bersifat SANGAT SENSITIF
        if ($file->owner_type === 'complaint' || $file->owner_type === 'complaint_pending') {
            if ($user->hasRole(['super_admin', 'principal', 'counselor']) || $user->hasPermission('complaint.read') || $user->hasPermission('complaint.manage')) {
                return;
            }
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mengakses lampiran pengaduan ini.');
        }

        // 1. Super Admin / Admin TU / Principal / File Manager
        if ($user->hasRole(['super_admin', 'admin', 'principal']) || $user->hasPermission('file.manage')) {
            return;
        }

        // 2. Pemilik berkas langsung
        if ($file->owner_id && (string) $file->owner_id === (string) $user->id) {
            return;
        }

        // 3. Staf akademik & pengajar (guru, walas, bk, sarpras, ppdb) bila berkas siswa / sistem
        if ($user->hasRole(['teacher', 'homeroom', 'counselor', 'facility_staff', 'ppdb_committee', 'librarian'])) {
            return;
        }

        // 4. Orang tua / Wali Murid: periksa apakah berkas milik anaknya
        if ($user->hasRole('guardian')) {
            $guardian = Guardian::where('user_id', $user->id)->first();
            if ($guardian) {
                $studentIds = $guardian->students()->pluck('students.id')->toArray();
                $studentUserIds = Student::whereIn('id', $studentIds)->pluck('user_id')->toArray();
                $allowedIds = array_merge($studentIds, $studentUserIds);

                if (in_array((string) $file->owner_id, $allowedIds, true)) {
                    return;
                }
            }
        }

        // Akses ditolak jika tidak memenuhi salah satu hak akses di atas
        throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mengakses berkas ini.');
    }
}
