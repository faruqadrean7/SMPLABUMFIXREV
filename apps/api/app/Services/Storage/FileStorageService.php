<?php

namespace App\Services\Storage;

use App\Models\Announcement;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AuditLog;
use App\Models\File;
use App\Models\Guardian;
use App\Models\LearningMaterial;
use App\Models\LeaveRequest;
use App\Models\LibraryBook;
use App\Models\Partnership;
use App\Models\Payment;
use App\Models\ProgramMaterial;
use App\Models\ProgramTask;
use App\Models\ProgramTaskSubmission;
use App\Models\ProgressReport;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\StudentPoint;
use App\Models\Teacher;
use App\Models\TeacherSalarySlip;
use App\Models\TeachingAssignment;
use App\Models\TeachingMaterial;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\Trip;
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
        $ttlSeconds = max(30, min($ttlSeconds, 300));
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

        // 3. Berkas Slip Gaji Guru/Staf (sangat sensitif: hanya pemilik slip atau bendahara/HR)
        $salarySlip = TeacherSalarySlip::with('teacher')->where('file_id', $file->id)->first();
        if ($salarySlip) {
            if ($salarySlip->teacher && (string) $salarySlip->teacher->user_id === (string) $user->id) {
                return;
            }
            if ($user->hasRole(['bendahara', 'treasurer', 'admin_tu']) || $user->hasPermission('master.teacher.manage') || $user->hasPermission('finance.report.view')) {
                return;
            }
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mengakses slip gaji ini.');
        }

        // 4. Berkas Pendaftar PPDB (KK/Akta/Ijazah: hanya pendaftar pemilik atau panitia PPDB)
        $applicantDoc = ApplicantDocument::with('applicant')->where('file_id', $file->id)->first();
        if ($applicantDoc || $file->owner_type === 'Applicant') {
            $applicant = $applicantDoc?->applicant ?? ($file->owner_id ? Applicant::find($file->owner_id) : null);
            if ($applicant && (string) $applicant->user_id === (string) $user->id) {
                return;
            }
            if ($user->hasRole('ppdb_committee') || $user->hasPermission('ppdb.applicant.read') || $user->hasPermission('ppdb.applicant.verify')) {
                return;
            }
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mengakses dokumen PPDB ini.');
        }

        // 5. Bukti Pembayaran Keuangan (hanya bendahara/admin keuangan, pengaju, siswa pemilik tagihan, atau walinya)
        $payment = Payment::with('invoice.student')->where('proof_file_id', $file->id)->first();
        if ($payment) {
            if ($user->hasRole(['bendahara', 'treasurer']) || $user->hasPermission('finance.payment.verify') || $user->hasPermission('finance.invoice.manage') || $user->hasPermission('finance.report.view')) {
                return;
            }
            if ($payment->submitted_by && (string) $payment->submitted_by === (string) $user->id) {
                return;
            }
            $invoiceStudent = $payment->invoice?->student;
            if ($invoiceStudent) {
                if ((string) $invoiceStudent->user_id === (string) $user->id) {
                    return;
                }
                if ($user->hasRole('guardian')) {
                    $guardian = Guardian::where('user_id', $user->id)->first();
                    if ($guardian && $guardian->students()->where('students.id', $invoiceStudent->id)->exists()) {
                        return;
                    }
                }
            }
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mengakses bukti pembayaran ini.');
        }

        // 6. Foto Presensi Staf (hanya pemilik atau admin TU/HR)
        $staffAtt = StaffAttendance::where('photo_file_id', $file->id)->first();
        if ($staffAtt) {
            if ((string) $staffAtt->user_id === (string) $user->id) {
                return;
            }
            if ($user->hasRole('admin_tu') || $user->hasPermission('master.teacher.manage') || $user->hasPermission('academic.schedule.manage')) {
                return;
            }
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mengakses berkas presensi staf ini.');
        }

        // 7. FIX-GURU-07: Berkas milik / terkait siswa (perizinan, tugas, rapor, poin, program khusus)
        $studentId = null;
        if ($file->owner_type === 'Student' || $file->bucket === 'leave-attachments') {
            $studentId = $file->owner_id;
        }
        if (! $studentId) {
            $studentId = LeaveRequest::where('attachment_file_id', $file->id)->value('student_id')
                ?? AssignmentSubmission::where('file_id', $file->id)->value('student_id')
                ?? ProgramTaskSubmission::where('file_id', $file->id)->value('student_id')
                ?? ReportCard::where('file_id', $file->id)->value('student_id')
                ?? ProgressReport::where('file_id', $file->id)->value('student_id')
                ?? StudentPoint::where('evidence_file_id', $file->id)->value('student_id');
        }

        if ($studentId) {
            $student = Student::find($studentId);
            if ($student) {
                // a. Siswa pemilik berkas
                if ($user->hasRole('student') && (string) $student->user_id === (string) $user->id) {
                    return;
                }

                // b. Orang tua dari siswa bersangkutan
                if ($user->hasRole('guardian')) {
                    $guardian = Guardian::where('user_id', $user->id)->first();
                    if ($guardian && $guardian->students()->where('students.id', $student->id)->exists()) {
                        return;
                    }
                    throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang mengakses berkas siswa ini.');
                }

                // c. Guru & Wali Kelas: Hanya dapat mengakses bila mengajar atau menjadi wali kelas siswa bersangkutan (Anti-IDOR)
                if ($user->hasRole(['teacher', 'homeroom'])) {
                    $teacher = Teacher::where('user_id', $user->id)->first();
                    if ($teacher) {
                        $studentClassIds = $student->classes()->pluck('classes.id');
                        $isHomeroom = SchoolClass::whereIn('id', $studentClassIds)
                            ->where('homeroom_teacher_id', $teacher->id)
                            ->exists();
                        $teaches = TeachingAssignment::where('teacher_id', $teacher->id)
                            ->whereIn('class_id', $studentClassIds)
                            ->exists();

                        if ($isHomeroom || $teaches) {
                            return;
                        }
                    }
                    throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mengakses lampiran siswa di luar kelas wewenang Anda.');
                }

                // d. Guru BK (counselor) memiliki akses konseling seluruh siswa
                if ($user->hasRole('counselor')) {
                    return;
                }

                throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang mengakses berkas siswa ini.');
            }
        }

        // 8. Berkas materi/tugas/pengumuman akademik (hanya civitas/staf terkait, bukan wildcard umum)
        if (
            LearningMaterial::where('file_id', $file->id)->exists()
            || TeachingMaterial::where('file_id', $file->id)->exists()
            || Assignment::where('file_id', $file->id)->exists()
            || ProgramMaterial::where('file_id', $file->id)->exists()
            || ProgramTask::where('file_id', $file->id)->exists()
            || Announcement::where('attachment_file_id', $file->id)->exists()
            || Trip::where('guide_file_id', $file->id)->exists()
        ) {
            if ($user->hasRole(['teacher', 'homeroom', 'counselor', 'student', 'guardian', 'admin_tu', 'student_affairs'])) {
                return;
            }
        }

        // 9. Berkas operasional spesifik per modul (Sarpras/Tiket, Perpustakaan, Humas/MoU)
        $ticket = Ticket::where('photo_file_id', $file->id)->first();
        if ($ticket) {
            if ((string) $ticket->reporter_id === (string) $user->id || $user->hasRole(['facility_staff', 'admin_tu']) || $user->hasPermission('facility.asset.manage')) {
                return;
            }
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mengakses berkas tiket ini.');
        }

        if (TicketEvent::where('file_id', $file->id)->exists()) {
            if ($user->hasRole(['facility_staff', 'admin_tu']) || $user->hasPermission('facility.asset.manage')) {
                return;
            }
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mengakses lampiran tiket ini.');
        }

        if (LibraryBook::where('file_id', $file->id)->orWhere('cover_file_id', $file->id)->exists()) {
            if ($user->hasRole(['librarian', 'teacher', 'homeroom', 'student', 'admin_tu']) || $user->hasPermission('library.book.manage')) {
                return;
            }
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mengakses berkas perpustakaan ini.');
        }

        if (Partnership::where('mou_file_id', $file->id)->exists()) {
            if ($user->hasRole(['public_relations', 'admin_tu']) || $user->hasPermission('public_relations.manage')) {
                return;
            }
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mengakses dokumen kemitraan ini.');
        }

        // 10. Orang tua / Wali Murid: periksa apakah berkas milik anaknya
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

        // Deny-by-default: akses ditolak jika tidak memenuhi salah satu aturan eksplisit di atas
        throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mengakses berkas ini.');
    }
}
