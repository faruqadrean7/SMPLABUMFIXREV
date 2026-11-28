<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\File;
use App\Models\Guest;
use App\Models\Notification;
use App\Models\User;
use App\Support\PaginationHelper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GuestService
{
    private const SIGNATURE_MAX_BYTES = 512000;

    private const ALLOWED_SIGNATURE_MIMES = ['image/png', 'image/jpeg', 'image/webp'];

    /**
     * BE-GUEST-01: Public register guest entry.
     * Tamu mengisi formulir kedatangan di resepsionis/kios digital.
     */
    public function registerGuest(array $data, Request $request): Guest
    {
        return DB::transaction(function () use ($data, $request) {
            $currentUser = $request->user();

            // P1-31: Cegah IDOR photo_file_id milik domain privat/pengguna lain dari endpoint publik
            $photoFileId = $data['photo_file_id'] ?? null;
            if (! empty($photoFileId)) {
                $photoFile = File::find($photoFileId);
                if (! $photoFile || ($photoFile->visibility !== 'publik' && $photoFile->bucket !== 'guest-photos')) {
                    throw new HttpException(403, 'Akses ditolak: Berkas foto tamu tidak valid atau bersifat privat.');
                }
            }

            $guest = Guest::create([
                'school_id' => $data['school_id'] ?? null,
                'target_user_id' => $data['target_user_id'] ?? null,
                'full_name' => trim($data['full_name']),
                'institution' => isset($data['institution']) ? trim($data['institution']) : null,
                'phone' => trim($data['phone']),
                'purpose' => trim($data['purpose']),
                'photo_file_id' => $photoFileId,
                'check_in' => Carbon::now(),
            ]);

            $signatureToken = Str::random(40);
            Cache::put(
                "guest_signature_token:{$guest->id}",
                hash('sha256', $signatureToken),
                Carbon::now()->addHours(12)
            );

            // Memicu notifikasi instan ke pihak tujuan jika target_user_id ditentukan
            if (! empty($guest->target_user_id)) {
                $targetUser = User::find($guest->target_user_id);
                if ($targetUser) {
                    $instansiText = $guest->institution ? " ({$guest->institution})" : '';
                    Notification::create([
                        'user_id' => $targetUser->id,
                        'notif_type' => 'guest.arrival',
                        'title' => 'Pemberitahuan Kedatangan Tamu',
                        'body' => "Tamu atas nama {$guest->full_name}{$instansiText} telah tiba di resepsionis untuk keperluan: {$guest->purpose}.",
                        'data' => [
                            'guest_id' => $guest->id,
                            'full_name' => $guest->full_name,
                            'institution' => $guest->institution,
                            'phone' => $guest->phone,
                            'purpose' => $guest->purpose,
                            'check_in' => $guest->check_in->toIso8601String(),
                        ],
                    ]);
                }
            }

            // Audit log
            $this->logAudit(
                $currentUser?->id,
                'guest.registered',
                'Guest',
                $guest->id,
                null,
                $guest->toArray(),
                $request
            );

            $freshGuest = $guest->fresh(['targetUser', 'photoFile']);
            $freshGuest->setAttribute('signature_token', $signatureToken);

            return $freshGuest;
        });
    }

    /**
     * BE-GUEST-01: List guests with filters, search, and scoping.
     */
    public function listGuests(array $filters, Request $request): LengthAwarePaginator
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        if (! $currentUser) {
            throw new HttpException(401, 'Unauthenticated');
        }

        // SEC-RBAC-01: Tolak pengguna tanpa permission baca buku tamu
        if (! $currentUser->hasRole('super_admin') && ! $currentUser->hasPermission('guest.book.read')) {
            throw new HttpException(403, 'Anda tidak memiliki hak akses untuk melihat daftar buku tamu');
        }

        $query = Guest::with(['targetUser', 'photoFile']);

        // Multi-tenant school isolation
        if (! empty($currentUser->school_id)) {
            $query->where(function ($q) use ($currentUser) {
                $q->where('school_id', $currentUser->school_id)->orWhereNull('school_id');
            });
        }

        // Filter search (nama, instansi, nomor telepon, keperluan)
        if (! empty($filters['q'])) {
            $q = trim($filters['q']);
            $query->where(function ($sub) use ($q) {
                $sub->where('full_name', 'like', "%{$q}%")
                    ->orWhere('institution', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('purpose', 'like', "%{$q}%");
            });
        }

        // Filter status (checked_in: belum keluar, checked_out: sudah keluar)
        $status = $filters['status'] ?? 'all';
        if ($status === 'checked_in') {
            $query->whereNull('check_out');
        } elseif ($status === 'checked_out') {
            $query->whereNotNull('check_out');
        }

        // Filter rentang tanggal kedatangan
        if (! empty($filters['start_date'])) {
            $query->whereDate('check_in', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->whereDate('check_in', '<=', $filters['end_date']);
        }

        // Filter spesifik target_user_id
        if (! empty($filters['target_user_id'])) {
            $query->where('target_user_id', $filters['target_user_id']);
        }

        $perPage = PaginationHelper::clamp($filters['per_page'] ?? null, 15);

        return $query->orderBy('check_in', 'desc')->paginate($perPage);
    }

    /**
     * BE-GUEST-01: Get guest visit detail.
     */
    public function getGuestDetail(string $id, Request $request): Guest
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        $guest = Guest::with(['targetUser', 'photoFile'])->findOrFail($id);

        if (! empty($currentUser->school_id) && ! empty($guest->school_id) && $guest->school_id !== $currentUser->school_id) {
            throw new HttpException(403, 'Anda tidak memiliki hak akses untuk melihat data kunjungan tamu ini');
        }

        // IDOR / Scoping check
        if (! $currentUser->hasRole('super_admin')
            && ! $currentUser->hasPermission('guest.book.read')
            && $guest->target_user_id !== $currentUser->id) {
            throw new HttpException(403, 'Anda tidak memiliki hak akses untuk melihat data kunjungan tamu ini');
        }

        return $guest;
    }

    /**
     * BE-GUEST-01: Checkout guest (mencatat jam keluar).
     */
    public function checkoutGuest(string $id, Request $request): Guest
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        $guest = Guest::with(['targetUser', 'photoFile'])->findOrFail($id);

        if (! empty($currentUser->school_id) && ! empty($guest->school_id) && $guest->school_id !== $currentUser->school_id) {
            throw new HttpException(403, 'Akses ditolak: Data kunjungan tamu berada di luar sekolah penugasan Anda.');
        }

        if ($guest->check_out !== null) {
            $formattedOut = $guest->check_out->translatedFormat('d F Y H:i');
            throw new HttpException(422, "Tamu {$guest->full_name} sudah melakukan checkout sebelumnya pada tanggal {$formattedOut} WIB.");
        }

        return DB::transaction(function () use ($guest, $currentUser, $request) {
            $oldData = $guest->toArray();
            $guest->check_out = Carbon::now();
            $guest->save();

            $this->logAudit(
                $currentUser->id,
                'guest.checked_out',
                'Guest',
                $guest->id,
                $oldData,
                $guest->toArray(),
                $request
            );

            return $guest->fresh(['targetUser', 'photoFile']);
        });
    }

    /**
     * BE-GUEST-03: Attach digital canvas signature with sha256 integrity hash.
     */
    public function attachSignature(string $id, array $data, Request $request): array
    {
        $guest = Guest::findOrFail($id);

        $currentUser = $request->user();
        if ($currentUser && ! empty($currentUser->school_id) && ! empty($guest->school_id) && $guest->school_id !== $currentUser->school_id) {
            throw new HttpException(403, 'Akses ditolak: Data kunjungan tamu berada di luar sekolah penugasan Anda.');
        }

        // P1-31: Verifikasi one-time signature_token bila sesi kunjungan diterbitkan via registrasi publik
        $tokenCacheKey = "guest_signature_token:{$guest->id}";
        $expectedTokenHash = Cache::get($tokenCacheKey);
        $providedToken = (string) ($data['signature_token'] ?? $request->header('X-Guest-Signature-Token', ''));

        if ($expectedTokenHash !== null) {
            if ($providedToken === '' || ! hash_equals((string) $expectedTokenHash, hash('sha256', $providedToken))) {
                throw new HttpException(403, 'Akses ditolak: Token sesi tanda tangan tamu tidak valid atau telah digunakan.');
            }
        } elseif ($providedToken !== '') {
            throw new HttpException(403, 'Akses ditolak: Token sesi tanda tangan tamu tidak dikenali.');
        }

        if ($guest->check_out !== null) {
            throw new HttpException(422, 'Tamu sudah melakukan checkout, tidak dapat menambahkan tanda tangan.');
        }

        if ($guest->signature_file_id !== null) {
            throw new HttpException(422, 'Kunjungan tamu ini sudah memiliki tanda tangan digital yang tercatat.');
        }

        $binary = null;

        if ($request->hasFile('signature_file')) {
            $uploaded = $request->file('signature_file');
            $binary = file_get_contents($uploaded->getRealPath());
        } elseif (! empty($data['signature_data'])) {
            $raw = trim($data['signature_data']);
            if (preg_match('/^data:image\/([a-zA-Z0-9+.-]+);base64,/', $raw, $matches)) {
                if (strtolower($matches[1]) === 'svg+xml') {
                    throw new HttpException(422, 'Format gambar SVG dilarang untuk tanda tangan digital.');
                }
                $raw = substr($raw, strpos($raw, ',') + 1);
            }
            $binary = base64_decode($raw, true);
        }

        if (empty($binary)) {
            throw new HttpException(422, 'Format berkas atau data canvas tanda tangan tidak valid.');
        }

        if (strlen($binary) > self::SIGNATURE_MAX_BYTES) {
            throw new HttpException(422, 'Ukuran data tanda tangan melebihi batas maksimal 500 KB.');
        }

        // P1-31: Verifikasi magic bytes asli via finfo (tolak HTML/SVG/payload non-gambar)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = (string) $finfo->buffer($binary);
        if (! in_array($detectedMime, self::ALLOWED_SIGNATURE_MIMES, true)) {
            throw new HttpException(422, 'Berkas tanda tangan wajib berupa citra raster sah (PNG, JPEG, atau WEBP).');
        }
        $mimeType = $detectedMime;

        $hash = hash('sha256', $binary);

        return DB::transaction(function () use ($guest, $binary, $mimeType, $hash, $request, $tokenCacheKey, $expectedTokenHash) {
            $ext = match ($mimeType) {
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
                default => 'png',
            };
            $storagePath = "guests/signatures/{$guest->id}-".time().".{$ext}";
            Storage::disk('local')->put($storagePath, $binary);

            $fileRecord = File::create([
                'school_id' => $guest->school_id,
                'owner_id' => $guest->id,
                'owner_type' => 'Guest',
                'bucket' => 'guest-signatures',
                'object_key' => $storagePath,
                'mime_type' => $mimeType,
                'size_bytes' => strlen($binary),
                'visibility' => 'privat',
                'checksum' => $hash,
            ]);

            $guest->update([
                'signature_file_id' => $fileRecord->id,
                'signature_hash' => $hash,
            ]);

            if ($expectedTokenHash !== null) {
                Cache::forget($tokenCacheKey);
            }

            $this->logAudit(
                $request->user()?->id,
                'guest.signature_attached',
                'Guest',
                $guest->id,
                null,
                [
                    'signature_file_id' => $fileRecord->id,
                    'signature_hash' => $hash,
                ],
                $request
            );

            return [
                'guest_id' => $guest->id,
                'full_name' => $guest->full_name,
                'signature_file_id' => $fileRecord->id,
                'signature_hash' => $hash,
                'integrity_verified' => true,
                'created_at' => $fileRecord->created_at?->toIso8601String(),
            ];
        });
    }

    /**
     * BE-GUEST-03: Get digital signature metadata and verify sha256 integrity.
     */
    public function getGuestSignature(string $id, Request $request): array
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        $guest = Guest::with(['signatureFile'])->findOrFail($id);

        if (! empty($currentUser->school_id) && ! empty($guest->school_id) && $guest->school_id !== $currentUser->school_id) {
            throw new HttpException(403, 'Anda tidak memiliki hak akses untuk melihat tanda tangan kunjungan tamu ini');
        }

        if (! $currentUser->hasRole('super_admin')
            && ! $currentUser->hasPermission('guest.book.read')
            && $guest->target_user_id !== $currentUser->id) {
            throw new HttpException(403, 'Anda tidak memiliki hak akses untuk melihat tanda tangan kunjungan tamu ini');
        }

        if (! $guest->signature_file_id || ! $guest->signatureFile) {
            throw new HttpException(404, 'Tanda tangan digital untuk tamu ini belum tersedia');
        }

        $signatureFile = $guest->signatureFile;
        $integrityVerified = false;

        if (Storage::disk('local')->exists($signatureFile->object_key)) {
            $content = Storage::disk('local')->get($signatureFile->object_key);
            $computedHash = hash('sha256', $content);
            $integrityVerified = hash_equals((string) $guest->signature_hash, $computedHash);
        }

        return [
            'guest_id' => $guest->id,
            'full_name' => $guest->full_name,
            'signature_file_id' => $signatureFile->id,
            'signature_hash' => $guest->signature_hash,
            'integrity_verified' => $integrityVerified,
            'mime_type' => $signatureFile->mime_type,
            'size_bytes' => $signatureFile->size_bytes,
            'object_key' => $signatureFile->object_key,
            'created_at' => $signatureFile->created_at?->toIso8601String(),
        ];
    }

    /**
     * Helper to write an audit log entry.
     */
    protected function logAudit(
        ?string $actorId,
        string $action,
        string $entity,
        ?string $entityId,
        ?array $oldValue,
        ?array $newValue,
        Request $request
    ): void {
        try {
            AuditLog::create([
                'actor_id' => $actorId,
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
