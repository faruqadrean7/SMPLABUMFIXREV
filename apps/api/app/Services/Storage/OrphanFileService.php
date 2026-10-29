<?php

namespace App\Services\Storage;

use App\Models\AuditLog;
use App\Models\File;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class OrphanFileService
{
    /**
     * Peta referensi kanonis relasi tabel bisnis ke files.id
     * Berdasarkan analisis skema migrasi SISTLABUM (32 relasi foreign key).
     */
    public const CANONICAL_REFERENCES = [
        ['table' => 'achievements', 'column' => 'certificate_file_id'],
        ['table' => 'achievements', 'column' => 'photo_file_id'],
        ['table' => 'activity_documents', 'column' => 'file_id'],
        ['table' => 'albums', 'column' => 'cover_file_id'],
        ['table' => 'announcements', 'column' => 'attachment_file_id'],
        ['table' => 'applicant_documents', 'column' => 'file_id'],
        ['table' => 'assignment_submissions', 'column' => 'file_id'],
        ['table' => 'assignments', 'column' => 'file_id'],
        ['table' => 'bulletins', 'column' => 'cover_file_id'],
        ['table' => 'bulletins', 'column' => 'file_id'],
        ['table' => 'downloads', 'column' => 'file_id'],
        ['table' => 'guests', 'column' => 'photo_file_id'],
        ['table' => 'guests', 'column' => 'signature_file_id'],
        ['table' => 'jobs', 'column' => 'result_file_id'],
        ['table' => 'learning_materials', 'column' => 'file_id'],
        ['table' => 'leave_requests', 'column' => 'attachment_file_id'],
        ['table' => 'library_books', 'column' => 'cover_file_id'],
        ['table' => 'library_books', 'column' => 'file_id'],
        ['table' => 'media_items', 'column' => 'file_id'],
        ['table' => 'partnerships', 'column' => 'mou_file_id'],
        ['table' => 'posts', 'column' => 'cover_file_id'],
        ['table' => 'progress_reports', 'column' => 'file_id'],
        ['table' => 'report_cards', 'column' => 'file_id'],
        ['table' => 'staff_attendances', 'column' => 'photo_file_id'],
        ['table' => 'student_points', 'column' => 'evidence_file_id'],
        ['table' => 'students', 'column' => 'photo_file_id'],
        ['table' => 'teacher_salary_slips', 'column' => 'file_id'],
        ['table' => 'teachers', 'column' => 'photo_file_id'],
        ['table' => 'teaching_materials', 'column' => 'file_id'],
        ['table' => 'ticket_events', 'column' => 'file_id'],
        ['table' => 'tickets', 'column' => 'photo_file_id'],
        ['table' => 'trips', 'column' => 'guide_file_id'],
    ];

    /**
     * Dapatkan semua referensi file, menggabungkan daftar kanonis
     * dan inspeksi dinamis basis data untuk mencegah keterlewatnya tabel baru.
     */
    public function discoverAllFileReferences(): array
    {
        $references = self::CANONICAL_REFERENCES;
        $indexed = [];

        foreach ($references as $ref) {
            $key = $ref['table'].'.'.$ref['column'];
            $indexed[$key] = $ref;
        }

        try {
            $tables = Schema::getTables();
            foreach ($tables as $t) {
                $tableName = $t['name'] ?? null;
                if (! $tableName || $tableName === 'files') {
                    continue;
                }

                // Cek foreign key dinamis jika didukung driver
                if (method_exists(Schema::class, 'getForeignKeys')) {
                    $foreignKeys = Schema::getForeignKeys($tableName);
                    foreach ($foreignKeys as $fk) {
                        if (($fk['foreign_table'] ?? '') === 'files') {
                            foreach ($fk['columns'] as $col) {
                                $key = $tableName.'.'.$col;
                                if (! isset($indexed[$key])) {
                                    $indexed[$key] = ['table' => $tableName, 'column' => $col];
                                }
                            }
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            Log::warning('Gagal melakukan inspeksi skema dinamis untuk referensi file: '.$e->getMessage());
        }

        return array_values($indexed);
    }

    /**
     * Periksa apakah suatu berkas sedang direferensikan oleh entitas mana pun.
     * Mengembalikan array [referenced => bool, references => string[]].
     */
    public function checkFileReferences(string $fileId, ?array $referenceMap = null): array
    {
        $referenceMap = $referenceMap ?? $this->discoverAllFileReferences();
        $matched = [];

        foreach ($referenceMap as $ref) {
            $table = $ref['table'];
            $column = $ref['column'];

            try {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $exists = DB::table($table)->where($column, $fileId)->exists();
                if ($exists) {
                    $matched[] = "{$table}.{$column}";
                }
            } catch (Throwable $e) {
                // Pendekatan konservatif: jika kueri tabel gagal, anggap file berpotensi aktif
                Log::error("Gagal memeriksa referensi file {$fileId} pada {$table}.{$column}: ".$e->getMessage());
                $matched[] = "{$table}.{$column} (ERROR_ASSUMED_REFERENCED)";
            }
        }

        return [
            'is_referenced' => count($matched) > 0,
            'references' => $matched,
        ];
    }

    /**
     * Pindai seluruh kandidat berkas yatim dengan ambang batas usia (default 24 jam).
     */
    public function scanOrphans(int $hoursThreshold = 24, int $limit = 500): array
    {
        $cutoffTime = Carbon::now()->subHours($hoursThreshold);
        $referenceMap = $this->discoverAllFileReferences();

        // 1. Hitung berkas yang dilewati karena baru diunggah (< 24 jam)
        $recentSkippedCount = File::where('created_at', '>=', $cutoffTime)->count();

        // 2. Ambil kandidat berkas lama (>= 24 jam)
        $candidates = File::where('created_at', '<', $cutoffTime)
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();

        $activeReferenced = [];
        $orphanCandidates = [];
        $totalBytesOrphan = 0;

        foreach ($candidates as $file) {
            $check = $this->checkFileReferences($file->id, $referenceMap);

            if ($check['is_referenced']) {
                $activeReferenced[] = [
                    'id' => $file->id,
                    'created_at' => $file->created_at?->toIso8601String(),
                    'references' => $check['references'],
                ];
            } else {
                $disk = ($file->visibility === 'publik' || $file->visibility === 'public') ? 'public' : 'local';
                $physicalExists = Storage::disk($disk)->exists($file->object_key);

                $orphanCandidates[] = [
                    'id' => $file->id,
                    'bucket' => $file->bucket,
                    'visibility' => $file->visibility,
                    'size_bytes' => $file->size_bytes,
                    'created_at' => $file->created_at?->toIso8601String(),
                    'age_hours' => round($file->created_at?->diffInHours(now()), 1),
                    'physical_exists' => $physicalExists,
                    'object_key_masked' => $this->maskObjectKey($file->object_key),
                ];
                $totalBytesOrphan += $file->size_bytes;
            }
        }

        return [
            'hours_threshold' => $hoursThreshold,
            'cutoff_time' => $cutoffTime->toIso8601String(),
            'total_registered_files' => File::count(),
            'recent_skipped_count' => $recentSkippedCount,
            'scanned_candidates_count' => $candidates->count(),
            'active_referenced_count' => count($activeReferenced),
            'orphan_candidates_count' => count($orphanCandidates),
            'total_orphan_bytes' => $totalBytesOrphan,
            'orphan_candidates' => $orphanCandidates,
            'reference_targets_checked_count' => count($referenceMap),
        ];
    }

    /**
     * Eksekusi pembersihan berkas yatim secara aman.
     * Menggunakan verifikasi ganda sebelum penghapusan fisik.
     */
    public function purgeOrphans(array $orphanFileIds, ?User $actor = null): array
    {
        $referenceMap = $this->discoverAllFileReferences();
        $purgedCount = 0;
        $freedBytes = 0;
        $failedCount = 0;
        $skippedSafelyCount = 0;
        $purgedIds = [];

        foreach ($orphanFileIds as $fileId) {
            $file = File::find($fileId);
            if (! $file) {
                continue;
            }

            // Keamanan Ganda: Cek ulang apakah ada entitas yang baru saja mengaitkan berkas
            $recheck = $this->checkFileReferences($file->id, $referenceMap);
            if ($recheck['is_referenced']) {
                $skippedSafelyCount++;
                Log::warning("Pembersihan berkas {$fileId} dibatalkan: berkas terdeteksi baru direferensikan oleh ".implode(', ', $recheck['references']));

                continue;
            }

            // Batasan Usia: Verifikasi ulang file sudah berusia >= 24 jam
            if ($file->created_at && $file->created_at->diffInHours(now()) < 24) {
                $skippedSafelyCount++;

                continue;
            }

            try {
                DB::transaction(function () use ($file, &$purgedCount, &$freedBytes, &$purgedIds) {
                    $disk = ($file->visibility === 'publik' || $file->visibility === 'public') ? 'public' : 'local';
                    $objectKey = $file->object_key;
                    $fileSize = $file->size_bytes;
                    $id = $file->id;

                    // 1. Hapus dari basis data terlebih dahulu (mencegah penghapusan fisik jika ada FK aktif yang menolak delete)
                    $file->delete();

                    // 2. Hapus dari penyimpanan fisik jika ada
                    if (Storage::disk($disk)->exists($objectKey)) {
                        Storage::disk($disk)->delete($objectKey);
                    }

                    $purgedCount++;
                    $freedBytes += $fileSize;
                    $purgedIds[] = $id;
                });
            } catch (Throwable $e) {
                $failedCount++;
                Log::error("Gagal menghapus berkas yatim {$file->id}: ".$e->getMessage());
            }
        }

        // Catat Audit Log untuk kepatuhan tata kelola
        AuditLog::create([
            'actor_id' => $actor?->id,
            'actor_role' => $actor?->roles?->first()?->code ?? 'system_cron',
            'action' => 'FILES_ORPHAN_PURGED',
            'entity' => 'files',
            'entity_id' => null,
            'new_value' => [
                'purged_count' => $purgedCount,
                'freed_bytes' => $freedBytes,
                'failed_count' => $failedCount,
                'skipped_safely_count' => $skippedSafelyCount,
            ],
            'ip' => request()->ip() ?? '127.0.0.1',
            'user_agent' => 'Console / OrphanFileService',
        ]);

        return [
            'purged_count' => $purgedCount,
            'freed_bytes' => $freedBytes,
            'failed_count' => $failedCount,
            'skipped_safely_count' => $skippedSafelyCount,
            'purged_ids' => $purgedIds,
        ];
    }

    /**
     * Samarkan nama objek kunci agar tidak membocorkan struktur internal di log/output.
     */
    protected function maskObjectKey(string $objectKey): string
    {
        $parts = explode('/', $objectKey);
        if (count($parts) <= 1) {
            return substr($objectKey, 0, 8).'...';
        }

        $filename = end($parts);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $prefix = array_slice($parts, 0, -1);

        return implode('/', $prefix).'/'.substr($filename, 0, 8).'...'.($ext ? '.'.$ext : '');
    }
}
