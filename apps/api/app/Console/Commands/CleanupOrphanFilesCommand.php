<?php

namespace App\Console\Commands;

use App\Services\Storage\OrphanFileService;
use Illuminate\Console\Command;

class CleanupOrphanFilesCommand extends Command
{
    protected $signature = 'files:cleanup-orphans
                            {--hours=24 : Ambang batas usia berkas minimal yang dapat dianggap yatim (dalam jam, default 24)}
                            {--limit=500 : Batas maksimal berkas yang dipindai dalam satu proses}
                            {--execute : Eksekusi penghapusan berkas fisik dan data (default adalah dry-run laporan)}
                            {--force : Lewati konfirmasi interaktif saat eksekusi}';

    protected $description = 'Pindai dan bersihkan berkas upload yang tidak lagi direferensikan oleh entitas mana pun secara aman';

    public function __construct(
        protected OrphanFileService $orphanService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $limit = max(1, (int) $this->option('limit'));
        $isExecute = (bool) $this->option('execute');
        $isForce = (bool) $this->option('force');

        $this->info('=================================================================');
        $this->info(' SISTLABUM - Audit & Pembersihan Berkas Upload Yatim (Orphan)');
        $this->info('=================================================================');
        $this->comment('Mode Operasi: '.($isExecute ? 'EKSEKUSI PENGHAPUSAN AKTUAL' : 'DRY-RUN (LAPORAN SAJA, TIDAK MENGHAPUS)'));
        $this->line("Ambang batas usia : minimal {$hours} jam");
        $this->line("Batas pemindaian   : {$limit} berkas");
        $this->line('Memindai entitas basis data & penyimpanan...');

        $scan = $this->orphanService->scanOrphans($hours, $limit);

        $this->newLine();
        $this->info('--- Ringkasan Pemindaian ---');
        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Total Berkas Terdaftar di Basis Data', $scan['total_registered_files']],
                ['Target Tabel & Kolom Referensi yang Diperiksa', $scan['reference_targets_checked_count']],
                ['Berkas Baru Dilewati (< '.$hours.' jam)', $scan['recent_skipped_count']],
                ['Kandidat Berkas Lama Diperiksa (>= '.$hours.' jam)', $scan['scanned_candidates_count']],
                ['Berkas Aktif Terbukti Direferensikan', $scan['active_referenced_count']],
                ['Berkas Yatim (Orphan) Ditemukan', $scan['orphan_candidates_count']],
                ['Estimasi Ruang Penyimpanan Dapat Dipulihkan', $this->formatBytes($scan['total_orphan_bytes'])],
            ]
        );

        if ($scan['orphan_candidates_count'] === 0) {
            $this->info('✓ Tidak ditemukan berkas yatim lama. Kondisi penyimpanan bersih.');

            return self::SUCCESS;
        }

        // Tampilkan 10 kandidat teratas tanpa membocorkan PII
        $displayCandidates = array_slice($scan['orphan_candidates'], 0, 10);
        $rows = array_map(function ($c) {
            return [
                $c['id'],
                $c['bucket'],
                $c['visibility'],
                $this->formatBytes($c['size_bytes']),
                $c['age_hours'].' jam',
                $c['physical_exists'] ? 'Ada di Disk' : 'Hilang dari Disk',
            ];
        }, $displayCandidates);

        $this->newLine();
        $this->info('Daftar 10 Kandidat Berkas Yatim Teratas:');
        $this->table(
            ['File ID (UUID)', 'Bucket', 'Visibilitas', 'Ukuran', 'Usia', 'Status Fisik'],
            $rows
        );

        if (! $isExecute) {
            $this->newLine();
            $this->warn('⚠ MODE DRY-RUN AKTIF: Tidak ada berkas fisik maupun baris data yang dihapus.');
            $this->line('Untuk melakukan pembersihan aktual, jalankan perintah dengan opsi --execute:');
            $this->comment("  php artisan files:cleanup-orphans --hours={$hours} --execute");

            return self::SUCCESS;
        }

        // Mode Eksekusi Aktual
        if (! $isForce) {
            if (! $this->confirm("Apakah Anda yakin ingin menghapus {$scan['orphan_candidates_count']} berkas yatim secara permanen?")) {
                $this->warn('Operasi pembersihan dibatalkan oleh pengguna.');

                return self::SUCCESS;
            }
        }

        $this->newLine();
        $this->info('Mengeksekusi pembersihan berkas yatim dengan verifikasi ganda...');

        $orphanIds = array_column($scan['orphan_candidates'], 'id');
        $purgeResult = $this->orphanService->purgeOrphans($orphanIds);

        $this->table(
            ['Hasil Eksekusi', 'Jumlah'],
            [
                ['Berkas Berhasil Dihapus', $purgeResult['purged_count']],
                ['Ruang Penyimpanan Dipulihkan', $this->formatBytes($purgeResult['freed_bytes'])],
                ['Berkas Dilewati demi Keamanan (Terdeteksi Baru Terikat)', $purgeResult['skipped_safely_count']],
                ['Berkas Gagal Dihapus', $purgeResult['failed_count']],
            ]
        );

        $this->info('✓ Eksekusi pembersihan selesai dan dicatat dalam audit logs.');

        return self::SUCCESS;
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }
}
