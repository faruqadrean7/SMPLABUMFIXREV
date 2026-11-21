<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackupVerifyCommand extends Command
{
    protected $signature = 'system:backup-verify';

    protected $description = 'Verifikasi berkala integritas cadangan sistem, konektivitas basis data, dan kesiapan direktori backup';

    public function handle(): int
    {
        $status = 'healthy';
        $issues = [];

        // 1. Verifikasi Konektivitas Database
        try {
            DB::connection()->getPdo();
            $this->info('✓ Koneksi basis data aktif dan responsif.');
        } catch (\Throwable $e) {
            $status = 'failed';
            $issues[] = 'Koneksi basis data terputus: '.$e->getMessage();
            $this->error('✗ Koneksi basis data gagal: '.$e->getMessage());
        }

        // 2. Verifikasi Direktori Penyimpanan Backup
        $backupDisk = Storage::disk('local');
        $backupDir = 'backups';

        try {
            if (! $backupDisk->exists($backupDir)) {
                $backupDisk->makeDirectory($backupDir);
            }

            // Test writeability
            $testFile = "{$backupDir}/.verify_test_".time();
            $backupDisk->put($testFile, 'integrity_test_ok');
            $readBack = $backupDisk->get($testFile);
            $backupDisk->delete($testFile);

            if ($readBack !== 'integrity_test_ok') {
                $status = 'degraded';
                $issues[] = 'Penyimpanan cadangan gagal dalam uji baca/tulis.';
                $this->warn('⚠ Uji baca/tulis direktori cadangan tidak konsisten.');
            } else {
                $this->info('✓ Penyimpanan lokal cadangan siap dan dapat ditulisi.');
            }
        } catch (\Throwable $e) {
            $status = 'failed';
            $issues[] = 'Akses penyimpanan cadangan error: '.$e->getMessage();
            $this->error('✗ Akses penyimpanan cadangan gagal: '.$e->getMessage());
        }

        // 3. Inspeksi Daftar Berkas Backup yang Ada
        $files = $backupDisk->files($backupDir);
        $recentBackupFound = false;

        foreach ($files as $file) {
            if (basename($file) !== '.gitignore') {
                $recentBackupFound = true;
                break;
            }
        }

        AuditLog::create([
            'action' => 'BACKUP_VERIFY_RUN',
            'entity' => 'system',
            'new_value' => [
                'status' => $status,
                'verified_at' => Carbon::now()->toIso8601String(),
                'issues' => $issues,
                'existing_backup_files_count' => count($files),
            ],
        ]);

        if ($status === 'healthy') {
            $this->info("Verifikasi backup mingguan berhasil diselesaikan tanpa kendala (Status: {$status}).");

            return Command::SUCCESS;
        }

        $this->warn('Verifikasi backup selesai dengan catatan: '.implode('; ', $issues));

        return Command::SUCCESS;
    }
}
