<?php

namespace App\Services\Storage;

use App\Models\AuditLog;
use App\Models\File;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class OrphanFileService
{
    /**
     * Peta referensi kanonis relasi tabel bisnis ke files.id
     * Mencakup seluruh foreign key dan kolom UUID non-FK (Program Khusus, Schools, Finance, CBT Import).
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
        ['table' => 'payments', 'column' => 'proof_file_id'],
        ['table' => 'posts', 'column' => 'cover_file_id'],
        ['table' => 'program_materials', 'column' => 'file_id'],
        ['table' => 'program_task_submissions', 'column' => 'file_id'],
        ['table' => 'program_tasks', 'column' => 'file_id'],
        ['table' => 'progress_reports', 'column' => 'file_id'],
        ['table' => 'question_import_jobs', 'column' => 'source_file_id'],
        ['table' => 'report_cards', 'column' => 'file_id'],
        ['table' => 'schools', 'column' => 'logo_file_id'],
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
     * Peta relasi polimorfik berdasarkan files.owner_type -> tabel pemilik.
     */
    public const POLYMORPHIC_REFERENCES = [
        'complaint' => ['table' => 'complaints', 'column' => 'id'],
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
     * Periksa apakah suatu berkas sedang direferensikan oleh entitas mana pun
     * (FK kanonis, kolom UUID non-FK, relasi polimorfik owner_type, serta referensi URL di site_settings/CMS).
     * Mengembalikan array [is_referenced => bool, references => string[]].
     */
    public function checkFileReferences(string $fileId, ?array $referenceMap = null, ?File $fileModel = null): array
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

        // Cek relasi polimorfik & referensi URL string pada site_settings/CMS
        $file = $fileModel ?? File::find($fileId);
        if ($file) {
            foreach ($this->checkPolymorphicAndUrlReferences($file) as $extraRef) {
                $matched[] = $extraRef;
            }
        }

        return [
            'is_referenced' => count($matched) > 0,
            'references' => $matched,
        ];
    }

    /**
     * Periksa referensi polimorfik (mis. files.owner_type = 'complaint') dan referensi URL/UUID pada site_settings & konten CMS.
     *
     * @return string[]
     */
    protected function checkPolymorphicAndUrlReferences(File $file): array
    {
        $matched = [];

        // 1. Relasi polimorfik (contoh: Complaint::attachments via owner_type = 'complaint')
        $ownerType = $file->owner_type;
        if ($ownerType && isset(self::POLYMORPHIC_REFERENCES[$ownerType]) && $file->owner_id) {
            $poly = self::POLYMORPHIC_REFERENCES[$ownerType];
            try {
                if (Schema::hasTable($poly['table'])) {
                    $exists = DB::table($poly['table'])
                        ->where($poly['column'], $file->owner_id)
                        ->exists();
                    if ($exists) {
                        $matched[] = "{$poly['table']}.polymorphic({$ownerType})";
                    }
                }
            } catch (Throwable $e) {
                Log::error("Gagal memeriksa referensi polimorfik {$ownerType} untuk file {$file->id}: ".$e->getMessage());
                $matched[] = "{$poly['table']}.polymorphic({$ownerType}) (ERROR_ASSUMED_REFERENCED)";
            }
        }

        // 2. Referensi URL atau UUID di site_settings.setting_value (logo_url, favicon_url, principal_photo_url, dll.)
        try {
            if (Schema::hasTable('site_settings')) {
                $objectKey = (string) ($file->object_key ?? '');
                $fileId = (string) $file->id;
                $settingsValues = DB::table('site_settings')->pluck('setting_value');
                foreach ($settingsValues as $rawVal) {
                    $strVal = is_string($rawVal) ? $rawVal : (string) json_encode($rawVal);
                    if (
                        ($fileId !== '' && str_contains($strVal, $fileId))
                        || ($objectKey !== '' && str_contains($strVal, $objectKey))
                    ) {
                        $matched[] = 'site_settings.setting_value';
                        break;
                    }
                }
            }
        } catch (Throwable $e) {
            Log::error("Gagal memeriksa referensi site_settings untuk file {$file->id}: ".$e->getMessage());
            $matched[] = 'site_settings.setting_value (ERROR_ASSUMED_REFERENCED)';
        }

        // 3. Referensi object_key tertanam di konten CMS publik (posts.content, pages.content)
        if (($file->visibility === 'publik' || $file->visibility === 'public') && ! empty($file->object_key)) {
            foreach (['posts', 'pages'] as $cmsTable) {
                try {
                    if (Schema::hasTable($cmsTable) && Schema::hasColumn($cmsTable, 'content')) {
                        $embedded = DB::table($cmsTable)
                            ->where('content', 'like', '%'.$file->object_key.'%')
                            ->exists();
                        if ($embedded) {
                            $matched[] = "{$cmsTable}.content(embedded_url)";
                        }
                    }
                } catch (Throwable $e) {
                    Log::error("Gagal memeriksa referensi {$cmsTable}.content untuk file {$file->id}: ".$e->getMessage());
                    $matched[] = "{$cmsTable}.content (ERROR_ASSUMED_REFERENCED)";
                }
            }
        }

        return $matched;
    }

    /**
     * Pindai seluruh kandidat berkas yatim dengan ambang batas usia (default 24 jam).
     * Menggunakan batch WHERE IN per tabel referensi agar terhindar dari N+1 query.
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

        // 3. Kumpulkan seluruh referensi aktif dalam mode batch (1 query per tabel, bukan N*M)
        $batchRefMap = $this->buildBatchReferenceMap($candidates, $referenceMap);

        $activeReferenced = [];
        $orphanCandidates = [];
        $totalBytesOrphan = 0;

        foreach ($candidates as $file) {
            $refs = $batchRefMap[(string) $file->id] ?? [];

            if (! empty($refs)) {
                $activeReferenced[] = [
                    'id' => $file->id,
                    'created_at' => $file->created_at?->toIso8601String(),
                    'references' => $refs,
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
            'reference_targets_checked_count' => count($referenceMap) + count(self::POLYMORPHIC_REFERENCES),
        ];
    }

    /**
     * Bangun peta [file_id => string[] references] secara batch untuk seluruh kandidat.
     *
     * @param  Collection<int, File>  $candidates
     * @return array<string, string[]>
     */
    protected function buildBatchReferenceMap($candidates, array $referenceMap): array
    {
        $map = [];
        if ($candidates->isEmpty()) {
            return $map;
        }

        $candidateIds = $candidates->pluck('id')->map(fn ($id) => (string) $id)->all();

        // 1. Batch query kolom referensi tabel (1 query WHERE IN per tabel.kolom)
        foreach ($referenceMap as $ref) {
            $table = $ref['table'];
            $column = $ref['column'];

            try {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $foundIds = DB::table($table)
                    ->whereIn($column, $candidateIds)
                    ->distinct()
                    ->pluck($column);

                foreach ($foundIds as $foundId) {
                    if ($foundId !== null) {
                        $map[(string) $foundId][] = "{$table}.{$column}";
                    }
                }
            } catch (Throwable $e) {
                // Pendekatan konservatif: bila gagal cek tabel, tandai semua kandidat sebagai berpotensi aktif
                Log::error("Gagal batch-check referensi pada {$table}.{$column}: ".$e->getMessage());
                foreach ($candidateIds as $cid) {
                    $map[$cid][] = "{$table}.{$column} (ERROR_ASSUMED_REFERENCED)";
                }
            }
        }

        // 2. Batch query relasi polimorfik (files.owner_type = 'complaint', dst.)
        foreach (self::POLYMORPHIC_REFERENCES as $ownerType => $poly) {
            $polyCandidates = $candidates->filter(
                fn (File $f) => $f->owner_type === $ownerType && ! empty($f->owner_id)
            );
            if ($polyCandidates->isEmpty()) {
                continue;
            }

            $ownerIds = $polyCandidates->pluck('owner_id')->map(fn ($id) => (string) $id)->unique()->values()->all();

            try {
                if (Schema::hasTable($poly['table'])) {
                    $existingOwners = DB::table($poly['table'])
                        ->whereIn($poly['column'], $ownerIds)
                        ->pluck($poly['column'])
                        ->map(fn ($id) => (string) $id)
                        ->flip();

                    foreach ($polyCandidates as $f) {
                        if ($existingOwners->has((string) $f->owner_id)) {
                            $map[(string) $f->id][] = "{$poly['table']}.polymorphic({$ownerType})";
                        }
                    }
                }
            } catch (Throwable $e) {
                Log::error("Gagal batch-check referensi polimorfik {$ownerType}: ".$e->getMessage());
                foreach ($polyCandidates as $f) {
                    $map[(string) $f->id][] = "{$poly['table']}.polymorphic({$ownerType}) (ERROR_ASSUMED_REFERENCED)";
                }
            }
        }

        // 3. Batch check referensi URL/UUID di site_settings (1 query total)
        try {
            if (Schema::hasTable('site_settings')) {
                $allSettingsBlob = DB::table('site_settings')
                    ->pluck('setting_value')
                    ->map(fn ($v) => is_string($v) ? $v : (string) json_encode($v))
                    ->implode("\n");

                if ($allSettingsBlob !== '') {
                    foreach ($candidates as $f) {
                        $fid = (string) $f->id;
                        $objKey = (string) ($f->object_key ?? '');
                        if (
                            ($fid !== '' && str_contains($allSettingsBlob, $fid))
                            || ($objKey !== '' && str_contains($allSettingsBlob, $objKey))
                        ) {
                            $map[$fid][] = 'site_settings.setting_value';
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            Log::error('Gagal batch-check referensi site_settings: '.$e->getMessage());
            foreach ($candidateIds as $cid) {
                $map[$cid][] = 'site_settings.setting_value (ERROR_ASSUMED_REFERENCED)';
            }
        }

        // 4. Cek object_key tertanam di konten CMS publik hanya untuk kandidat publik yang belum punya referensi
        $unmatchedPublic = $candidates->filter(
            fn (File $f) => empty($map[(string) $f->id])
                && ($f->visibility === 'publik' || $f->visibility === 'public')
                && ! empty($f->object_key)
        );

        if ($unmatchedPublic->isNotEmpty()) {
            foreach (['posts', 'pages'] as $cmsTable) {
                try {
                    if (! Schema::hasTable($cmsTable) || ! Schema::hasColumn($cmsTable, 'content')) {
                        continue;
                    }

                    foreach ($unmatchedPublic as $f) {
                        $embedded = DB::table($cmsTable)
                            ->where('content', 'like', '%'.$f->object_key.'%')
                            ->exists();
                        if ($embedded) {
                            $map[(string) $f->id][] = "{$cmsTable}.content(embedded_url)";
                        }
                    }
                } catch (Throwable $e) {
                    Log::error("Gagal batch-check referensi {$cmsTable}.content: ".$e->getMessage());
                    foreach ($unmatchedPublic as $f) {
                        $map[(string) $f->id][] = "{$cmsTable}.content (ERROR_ASSUMED_REFERENCED)";
                    }
                }
            }
        }

        return $map;
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
