<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PruneExpiredAuthTokensCommand extends Command
{
    protected $signature = 'auth:prune-tokens';

    protected $description = 'Hapus token refresh dan token reset sandi yang sudah kedaluwarsa';

    public function handle(): int
    {
        $cutoff = Carbon::now();

        $deletedRefresh = DB::table('refresh_tokens')
            ->where('expires_at', '<', $cutoff)
            ->delete();

        $deletedResets = DB::table('password_resets')
            ->where('expires_at', '<', $cutoff)
            ->delete();

        $this->info("Berhasil membersihkan {$deletedRefresh} refresh token dan {$deletedResets} token reset sandi kedaluwarsa.");

        return Command::SUCCESS;
    }
}
