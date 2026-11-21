<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TwoFactorAuthService;
use Illuminate\Console\Command;

class Admin2faCodeCommand extends Command
{
    protected $signature = 'admin:2fa-code {identifier : Username atau email akun admin} {--uri : Tampilkan juga otpauth:// URI untuk authenticator}';

    protected $description = 'Cetak kode 2FA (TOTP) yang berlaku saat ini untuk akun admin. Berguna untuk login pertama sebelum "perangkat tepercaya" aktif.';

    public function handle(TwoFactorAuthService $twoFactor): int
    {
        $identifier = (string) $this->argument('identifier');

        $user = User::where('username', $identifier)
            ->orWhere('email', $identifier)
            ->first();

        if (! $user) {
            $this->error("Akun '{$identifier}' tidak ditemukan.");

            return self::FAILURE;
        }

        if (! $user->two_factor_secret) {
            $this->warn("Akun '{$identifier}' belum mengaktifkan 2FA (tidak ada secret). Login pertama akan memulai enrollment yang menampilkan QR.");

            return self::FAILURE;
        }

        $code = $twoFactor->calculateCode($user->two_factor_secret);
        $secondsLeft = 30 - (time() % 30);

        $this->newLine();
        $this->info("Kode 2FA untuk {$identifier}: {$code}");
        $this->line("Berlaku ~{$secondsLeft} detik lagi. Masukkan ke layar verifikasi 2FA.");

        if ($this->option('uri')) {
            $uri = $twoFactor->getOtpAuthUrl('SISTLABUM', $user->username, $user->two_factor_secret);
            $this->newLine();
            $this->line('otpauth URI (tempel ke Google Authenticator / Authy):');
            $this->line($uri);
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
