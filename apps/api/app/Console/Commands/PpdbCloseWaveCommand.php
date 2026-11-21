<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\PPDBWave;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PpdbCloseWaveCommand extends Command
{
    protected $signature = 'ppdb:close-wave';

    protected $description = 'Tutup gelombang pendaftaran PPDB yang telah melewati batas waktu penutupan (close_at)';

    public function handle(): int
    {
        $now = Carbon::now();

        $expiredWaves = PPDBWave::where('status', 'aktif')
            ->where('close_at', '<=', $now)
            ->get();

        $count = 0;
        foreach ($expiredWaves as $wave) {
            $wave->update(['status' => 'ditutup']);
            $count++;

            AuditLog::create([
                'school_id' => $wave->school_id,
                'action' => 'PPDB_WAVE_AUTO_CLOSED',
                'entity' => 'ppdb_waves',
                'entity_id' => $wave->id,
                'new_value' => [
                    'status' => 'ditutup',
                    'closed_at' => $now->toIso8601String(),
                ],
            ]);
        }

        $this->info("Berhasil menutup {$count} gelombang PPDB yang telah melewati batas waktu.");

        return Command::SUCCESS;
    }
}
