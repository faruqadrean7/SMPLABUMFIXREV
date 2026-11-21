<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;

class Admin2faResetCommand extends Command
{
    protected $signature = 'admin:2fa-reset {identifier : Username atau email akun admin} {--force : Lewati dialog konfirmasi}';

    protected $description = 'Reset otentikasi dua faktor (2FA) untuk akun admin agar dapat melakukan setup ulang.';

    public function handle(): int
    {
        $identifier = (string) $this->argument('identifier');

        $user = User::where('username', $identifier)
            ->orWhere('email', $identifier)
            ->first();

        if (! $user) {
            $this->error("Akun '{$identifier}' tidak ditemukan.");

            return self::FAILURE;
        }

        if (! $user->two_factor_secret && ! $user->two_factor_confirmed_at) {
            $this->warn("Akun '{$identifier}' saat ini belum memiliki konfigurasi 2FA yang aktif.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Apakah Anda yakin ingin mereset 2FA untuk akun '{$user->username}' ({$user->email})?")) {
            $this->info('Operasi dibatalkan.');

            return self::SUCCESS;
        }

        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->two_factor_last_step = null;
        $user->save();

        try {
            AuditLog::create([
                'actor_id' => null,
                'action' => 'auth.admin_2fa_reset_by_cli',
                'entity' => 'User',
                'entity_id' => $user->id,
                'old_value' => ['two_factor_confirmed' => true],
                'new_value' => ['two_factor_confirmed' => false, 'trigger' => 'cli_reset'],
                'ip' => '127.0.0.1',
                'user_agent' => 'artisan-cli',
            ]);
        } catch (\Throwable) {
            // Ignore audit log failure
        }

        $this->newLine();
        $this->info("2FA untuk akun '{$user->username}' berhasil direset.");
        $this->line('Pada percobaan login berikutnya melalui portal admin, akun ini akan otomatis diarahkan ke alur setup 2FA baru (menampilkan QR code / kunci rahasia baru).');
        $this->newLine();

        return self::SUCCESS;
    }
}
