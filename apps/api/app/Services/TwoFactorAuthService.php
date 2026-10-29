<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TwoFactorAuthService
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate standard 16-character Base32 secret key.
     */
    public function generateSecretKey(int $length = 16): string
    {
        $secret = '';
        $charsLen = strlen(self::BASE32_CHARS);
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_CHARS[random_int(0, $charsLen - 1)];
        }

        return $secret;
    }

    /**
     * Calculate 6-digit TOTP code for a given timestamp.
     */
    public function calculateCode(string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $timeSlice = (int) floor($timestamp / 30);

        $secretBinary = $this->base32Decode($secret);
        $timeBinary = pack('N*', 0).pack('N*', $timeSlice);

        $hash = hash_hmac('sha1', $timeBinary, $secretBinary, true);
        $offset = ord(substr($hash, -1)) & 0x0F;

        $truncatedHash = substr($hash, $offset, 4);
        $value = unpack('N', $truncatedHash)[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verify user code against secret with a 1-window discrepancy tolerance (+/- 30s).
     */
    public function verifyKey(string $secret, string $code, int $discrepancy = 1): bool
    {
        $cleanCode = preg_replace('/\s+/', '', $code);
        if (strlen($cleanCode) !== 6 || ! ctype_digit($cleanCode)) {
            return false;
        }

        $currentTime = time();
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calc = $this->calculateCode($secret, $currentTime + ($i * 30));
            if (hash_equals($calc, $cleanCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verify user code against secret AND prevent replay attack by checking last used time-step.
     */
    public function verifyKeyWithUser(User $user, string $code, int $discrepancy = 1): bool
    {
        if (! $user->two_factor_secret) {
            return false;
        }

        $cleanCode = preg_replace('/\s+/', '', $code);
        if (strlen($cleanCode) !== 6 || ! ctype_digit($cleanCode)) {
            return false;
        }

        $currentTime = time();
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $stepTime = $currentTime + ($i * 30);
            $timeSlice = (int) floor($stepTime / 30);
            $calc = $this->calculateCode($user->two_factor_secret, $stepTime);

            if (hash_equals($calc, $cleanCode)) {
                // Check if this time slice has already been used (prevent replay attack)
                if ($user->two_factor_last_step !== null && $timeSlice <= $user->two_factor_last_step) {
                    return false;
                }

                $user->two_factor_last_step = $timeSlice;
                $user->saveQuietly();

                return true;
            }
        }

        return false;
    }

    /**
     * Decode Base32 to binary.
     */
    private function base32Decode(string $b32): string
    {
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
        $buffer = 0;
        $bitsLeft = 0;
        $binary = '';

        for ($i = 0; $i < strlen($b32); $i++) {
            $val = strpos(self::BASE32_CHARS, $b32[$i]);
            if ($val === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $binary .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $binary;
    }

    /**
     * Generate emergency recovery codes (e.g. 8 codes formatted XXXX-XXXX).
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random(4).'-'.Str::random(4));
        }

        return $codes;
    }

    /**
     * Verify and consume an emergency recovery code (supports both hashed and plaintext stored codes).
     */
    public function verifyAndConsumeRecoveryCode(User $user, string $code): bool
    {
        $cleanCode = strtoupper(trim($code));
        $codes = $user->two_factor_recovery_codes ?? [];

        if (! is_array($codes) || empty($codes)) {
            return false;
        }

        foreach ($codes as $index => $storedCode) {
            if (Hash::check($cleanCode, $storedCode) || hash_equals((string) $storedCode, $cleanCode)) {
                unset($codes[$index]);
                $user->two_factor_recovery_codes = array_values($codes);
                $user->save();

                return true;
            }
        }

        return false;
    }

    /**
     * Generate temporary signed MFA token valid for 5 minutes.
     */
    public function generateMfaToken(User $user): string
    {
        return Crypt::encrypt([
            'user_id' => $user->id,
            'type' => 'mfa_challenge',
            'expires_at' => now()->addMinutes(5)->timestamp,
        ]);
    }

    /**
     * Decrypt and validate temporary MFA token.
     */
    public function validateMfaToken(string $mfaToken): ?User
    {
        try {
            $payload = Crypt::decrypt($mfaToken);
            if (! is_array($payload) || empty($payload['user_id']) || empty($payload['expires_at'])) {
                return null;
            }

            if ($payload['expires_at'] < time()) {
                return null;
            }

            return User::find($payload['user_id']);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Generate temporary signed MFA Enrollment token valid for 15 minutes.
     */
    public function generateEnrollmentToken(User $user): string
    {
        return Crypt::encrypt([
            'user_id' => $user->id,
            'type' => 'mfa_enrollment',
            'expires_at' => now()->addMinutes(15)->timestamp,
        ]);
    }

    /**
     * Decrypt and validate temporary MFA Enrollment token.
     */
    public function validateEnrollmentToken(string $enrollmentToken): ?User
    {
        try {
            $payload = Crypt::decrypt($enrollmentToken);
            if (! is_array($payload) || empty($payload['user_id']) || empty($payload['expires_at'])) {
                return null;
            }

            if (($payload['type'] ?? '') !== 'mfa_enrollment') {
                return null;
            }

            if ($payload['expires_at'] < time()) {
                return null;
            }

            return User::find($payload['user_id']);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Build OTP Auth URL for QR code generation.
     */
    public function getOtpAuthUrl(string $issuer, string $accountName, string $secret): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer).':'.rawurlencode($accountName)
            .'?secret='.$secret
            .'&issuer='.rawurlencode($issuer)
            .'&algorithm=SHA1&digits=6&period=30';
    }
}
