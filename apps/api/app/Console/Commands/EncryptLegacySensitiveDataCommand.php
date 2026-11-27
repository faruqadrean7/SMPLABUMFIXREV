<?php

namespace App\Console\Commands;

use App\Casts\SafeEncryptedCast;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P0-18: Backfill enkripsi non-destruktif untuk data lama BK & Pengaduan.
 *
 * Mode default adalah DRY-RUN (tidak mengubah data). Gunakan --execute untuk
 * mengenkripsi baris plaintext lama secara idempoten.
 */
class EncryptLegacySensitiveDataCommand extends Command
{
    protected $signature = 'security:encrypt-legacy-sensitive-data
                            {--execute : Eksekusi enkripsi baris plaintext lama (default adalah dry-run)}
                            {--force : Lewati konfirmasi interaktif saat eksekusi}';

    protected $description = 'Pindai dan enkripsi data sensitif lama (pengaduan & konseling BK) secara non-destruktif dengan default dry-run';

    /**
     * Target tabel dan kolom sensitif yang menggunakan SafeEncryptedCast.
     *
     * @var array<string, string[]>
     */
    private const TARGETS = [
        'complaints' => ['content'],
        'counseling_requests' => ['topic', 'notes', 'rejection_reason'],
        'counseling_sessions' => ['follow_up'],
    ];

    public function handle(): int
    {
        $isExecute = (bool) $this->option('execute');
        $isForce = (bool) $this->option('force');

        $this->info('=================================================================');
        $this->info(' SISTLABUM - Backfill Enkripsi Data Sensitif Lama (P0-18)');
        $this->info('=================================================================');
        $this->comment('Mode Operasi: '.($isExecute ? 'EKSEKUSI ENKRIPSI AKTUAL' : 'DRY-RUN (LAPORAN SAJA, TIDAK MENGUBAH DATA)'));

        $summaryRows = [];
        $totalPlaintextFields = 0;

        foreach (self::TARGETS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $scanned = 0;
            $alreadyEncrypted = 0;
            $plaintextFields = 0;

            DB::table($table)->select(array_merge(['id'], $columns))->orderBy('id')->chunk(200, function ($rows) use (
                $columns,
                &$scanned,
                &$alreadyEncrypted,
                &$plaintextFields
            ) {
                foreach ($rows as $row) {
                    $scanned++;
                    foreach ($columns as $col) {
                        $val = $row->{$col};
                        if ($val === null || $val === '') {
                            continue;
                        }
                        if (SafeEncryptedCast::isEncrypted((string) $val)) {
                            $alreadyEncrypted++;
                        } else {
                            $plaintextFields++;
                        }
                    }
                }
            });

            $totalPlaintextFields += $plaintextFields;
            $summaryRows[] = [$table, implode(', ', $columns), $scanned, $alreadyEncrypted, $plaintextFields];
        }

        $this->table(
            ['Tabel', 'Kolom Target', 'Baris Dipindai', 'Field Sudah Terenkripsi', 'Field Masih Plaintext'],
            $summaryRows
        );

        if ($totalPlaintextFields === 0) {
            $this->info('Seluruh data sensitif sudah terenkripsi. Tidak ada tindakan yang diperlukan.');

            return self::SUCCESS;
        }

        if (! $isExecute) {
            $this->warn('MODE DRY-RUN AKTIF: Tidak ada baris database yang diubah.');
            $this->comment('Untuk mengeksekusi enkripsi backfill, jalankan: php artisan security:encrypt-legacy-sensitive-data --execute');

            return self::SUCCESS;
        }

        if (! $isForce && ! $this->confirm("Enkripsi {$totalPlaintextFields} field plaintext lama secara non-destruktif sekarang?")) {
            $this->warn('Eksekusi dibatalkan.');

            return self::SUCCESS;
        }

        $encryptedCount = 0;

        foreach (self::TARGETS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)->select(array_merge(['id'], $columns))->orderBy('id')->chunk(200, function ($rows) use (
                $table,
                $columns,
                &$encryptedCount
            ) {
                foreach ($rows as $row) {
                    $updates = [];
                    foreach ($columns as $col) {
                        $val = $row->{$col};
                        if ($val === null || $val === '') {
                            continue;
                        }
                        $strVal = (string) $val;
                        if (! SafeEncryptedCast::isEncrypted($strVal)) {
                            $updates[$col] = Crypt::encryptString($strVal);
                            $encryptedCount++;
                        }
                    }

                    if (! empty($updates)) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                    }
                }
            });
        }

        $this->info("Selesai: {$encryptedCount} field plaintext berhasil dienkripsi secara aman (idempoten, 0 data hilang).");

        return self::SUCCESS;
    }
}
