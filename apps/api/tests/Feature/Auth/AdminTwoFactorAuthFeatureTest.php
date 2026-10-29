<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Api\V1\AdminAuthController;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\TwoFactorAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AdminTwoFactorAuthFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $bendaharaUser;

    protected User $tuUser;

    protected User $teacherUser;

    protected User $studentUser;

    protected User $guardianUser;

    protected Role $superAdminRole;

    protected Role $bendaharaRole;

    protected Role $tuRole;

    protected Role $teacherRole;

    protected Role $studentRole;

    protected Role $guardianRole;

    protected TwoFactorAuthService $twoFactorService;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->twoFactorService = app(TwoFactorAuthService::class);

        $this->superAdminRole = Role::firstOrCreate(
            ['code' => 'super_admin'],
            ['name' => 'Super Administrator', 'is_system' => true]
        );

        $this->bendaharaRole = Role::firstOrCreate(
            ['code' => 'bendahara'],
            ['name' => 'Bendahara', 'is_system' => true]
        );

        $this->tuRole = Role::firstOrCreate(
            ['code' => 'admin_tu'],
            ['name' => 'Admin Tata Usaha', 'is_system' => true]
        );

        $this->teacherRole = Role::firstOrCreate(
            ['code' => 'teacher'],
            ['name' => 'Guru', 'is_system' => true]
        );

        $this->studentRole = Role::firstOrCreate(
            ['code' => 'student'],
            ['name' => 'Siswa', 'is_system' => true]
        );

        $this->guardianRole = Role::firstOrCreate(
            ['code' => 'guardian'],
            ['name' => 'Orang Tua', 'is_system' => true]
        );

        $perm = Permission::firstOrCreate(
            ['code' => 'auth.user.read'],
            ['description' => 'Melihat user']
        );
        $this->superAdminRole->permissions()->syncWithoutDetaching([$perm->id]);

        $this->adminUser = User::create([
            'username' => 'admin_demo',
            'email' => 'admin_demo@labschool.sch.id',
            'password_hash' => Hash::make('AdminPass123!'),
            'status' => 'active',
            'must_change_password' => false,
        ]);
        $this->adminUser->roles()->attach($this->superAdminRole->id);

        $this->bendaharaUser = User::create([
            'username' => 'bendahara_demo',
            'email' => 'bendahara_demo@labschool.sch.id',
            'password_hash' => Hash::make('BendaharaPass123!'),
            'status' => 'active',
            'must_change_password' => false,
        ]);
        $this->bendaharaUser->roles()->attach($this->bendaharaRole->id);

        $this->tuUser = User::create([
            'username' => 'tu_demo',
            'email' => 'tu_demo@labschool.sch.id',
            'password_hash' => Hash::make('TuPass123!'),
            'status' => 'active',
            'must_change_password' => false,
        ]);
        $this->tuUser->roles()->attach($this->tuRole->id);

        $this->teacherUser = User::create([
            'username' => 'guru_demo',
            'email' => 'guru_demo@labschool.sch.id',
            'password_hash' => Hash::make('GuruPass123!'),
            'status' => 'active',
            'must_change_password' => false,
        ]);
        $this->teacherUser->roles()->attach($this->teacherRole->id);

        $this->studentUser = User::create([
            'username' => 'siswa_demo',
            'email' => 'siswa_demo@labschool.sch.id',
            'password_hash' => Hash::make('SiswaPass123!'),
            'status' => 'active',
            'must_change_password' => false,
        ]);
        $this->studentUser->roles()->attach($this->studentRole->id);

        $this->guardianUser = User::create([
            'username' => 'ortu_demo',
            'email' => 'ortu_demo@labschool.sch.id',
            'password_hash' => Hash::make('OrtuPass123!'),
            'status' => 'active',
            'must_change_password' => false,
        ]);
        $this->guardianUser->roles()->attach($this->guardianRole->id);
    }

    /**
     * BATCH 4B - A.1: POST /api/v1/auth/login MENOLAK akun shell admin dengan generik 401.
     */
    public function test_batch4b_auth_login_rejects_admin_shell_users_with_generic_401(): void
    {
        // 1. Super admin ditolak di endpoint civitas /api/v1/auth/login
        $resSuperAdmin = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'admin_demo',
            'password' => 'AdminPass123!',
        ]);
        $resSuperAdmin->assertStatus(401);
        $resSuperAdmin->assertJsonPath('error.message', 'Kredensial yang diberikan tidak cocok dengan data kami.');

        // 2. Bendahara ditolak di endpoint civitas /api/v1/auth/login
        $resBendahara = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'bendahara_demo',
            'password' => 'BendaharaPass123!',
        ]);
        $resBendahara->assertStatus(401);
        $resBendahara->assertJsonPath('error.message', 'Kredensial yang diberikan tidak cocok dengan data kami.');

        // 3. Admin TU ditolak di endpoint civitas /api/v1/auth/login
        $resTu = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'tu_demo',
            'password' => 'TuPass123!',
        ]);
        $resTu->assertStatus(401);
        $resTu->assertJsonPath('error.message', 'Kredensial yang diberikan tidak cocok dengan data kami.');

        // 4. Sebaliknya, akun civitas (Guru) dapat login normal di /api/v1/auth/login
        $resGuru = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'guru_demo',
            'password' => 'GuruPass123!',
        ]);
        $resGuru->assertStatus(200);
        $resGuru->assertJsonPath('data.user.username', 'guru_demo');
    }

    /**
     * BATCH 4B - A.1: POST /api/v1/auth/admin/login HANYA menerima peran admin shell; guru/siswa/ortu ditolak.
     */
    public function test_batch4b_admin_login_rejects_civitas_roles_with_generic_401(): void
    {
        // 1. Guru ditolak di login admin
        $resGuru = $this->postJson('/api/v1/auth/admin/login', [
            'identifier' => 'guru_demo',
            'password' => 'GuruPass123!',
        ]);
        $resGuru->assertStatus(401);
        $resGuru->assertJsonPath('error.message', 'Kredensial yang diberikan tidak cocok dengan data kami.');

        // 2. Siswa ditolak di login admin
        $resSiswa = $this->postJson('/api/v1/auth/admin/login', [
            'identifier' => 'siswa_demo',
            'password' => 'SiswaPass123!',
        ]);
        $resSiswa->assertStatus(401);
        $resSiswa->assertJsonPath('error.message', 'Kredensial yang diberikan tidak cocok dengan data kami.');

        // 3. Ortu ditolak di login admin
        $resOrtu = $this->postJson('/api/v1/auth/admin/login', [
            'identifier' => 'ortu_demo',
            'password' => 'OrtuPass123!',
        ]);
        $resOrtu->assertStatus(401);
        $resOrtu->assertJsonPath('error.message', 'Kredensial yang diberikan tidak cocok dengan data kami.');
    }

    /**
     * BATCH 4B - A.4: 2FA WAJIB untuk peran super admin dan bendahara (mfa_enrollment_required).
     */
    public function test_batch4b_super_admin_and_bendahara_without_2fa_require_mfa_enrollment(): void
    {
        // Super Admin tanpa 2FA harus dialihkan ke alur setup 2FA
        $resAdmin = $this->postJson('/api/v1/auth/admin/login', [
            'identifier' => 'admin_demo',
            'password' => 'AdminPass123!',
        ]);

        $resAdmin->assertStatus(200);
        $resAdmin->assertJson([
            'mfa_enrollment_required' => true,
        ]);
        $enrollmentToken = $resAdmin->json('enrollment_token');
        $this->assertNotEmpty($enrollmentToken);
        $this->assertNull($resAdmin->json('data.tokens')); // Tidak boleh terbit auth token sebelum 2FA aktif!

        // Bendahara tanpa 2FA juga wajib mfa_enrollment_required
        $resBendahara = $this->postJson('/api/v1/auth/admin/login', [
            'identifier' => 'bendahara_demo',
            'password' => 'BendaharaPass123!',
        ]);
        $resBendahara->assertStatus(200);
        $resBendahara->assertJson([
            'mfa_enrollment_required' => true,
        ]);

        // BATCH 5 - A.6: Kepala Sekolah (principal) tanpa 2FA juga wajib mfa_enrollment_required
        $rolePrincipal = Role::firstOrCreate(['code' => 'principal'], ['name' => 'Kepala Sekolah']);
        $principalUser = User::create([
            'username' => 'kepsek_demo',
            'email' => 'kepsek@smp-labum.sch.id',
            'password_hash' => Hash::make('KepsekPass123!'),
            'status' => 'active',
            'two_factor_confirmed_at' => null,
        ]);
        $principalUser->roles()->attach($rolePrincipal->id);

        $resKepsek = $this->postJson('/api/v1/auth/admin/login', [
            'identifier' => 'kepsek_demo',
            'password' => 'KepsekPass123!',
        ]);
        $resKepsek->assertStatus(200);
        $resKepsek->assertJson([
            'mfa_enrollment_required' => true,
        ]);
        $this->assertNotEmpty($resKepsek->json('enrollment_token'));
        $this->assertNull($resKepsek->json('data.tokens'));
    }

    /**
     * BATCH 4B - A.4: Setup 2FA menggunakan enrollment_token dan penyimpanan recovery codes sebagai HASH.
     */
    public function test_batch4b_2fa_enrollment_stores_recovery_codes_as_hashes(): void
    {
        // 1. Dapatkan enrollment token
        $loginRes = $this->postJson('/api/v1/auth/admin/login', [
            'identifier' => 'admin_demo',
            'password' => 'AdminPass123!',
        ]);
        $enrollmentToken = $loginRes->json('enrollment_token');

        // 2. Setup 2FA via enrollment_token
        $setupRes = $this->postJson('/api/v1/auth/2fa/setup', [
            'enrollment_token' => $enrollmentToken,
        ]);
        $setupRes->assertStatus(200);
        $secret = $setupRes->json('data.secret');
        $this->assertNotEmpty($secret);

        // 3. Konfirmasi 2FA via enrollment_token
        $validCode = $this->twoFactorService->calculateCode($secret);
        $verifyRes = $this->postJson('/api/v1/auth/2fa/verify', [
            'enrollment_token' => $enrollmentToken,
            'code' => $validCode,
        ]);
        $verifyRes->assertStatus(200);
        $plaintextRecoveryCodes = $verifyRes->json('data.recovery_codes');
        $this->assertCount(8, $plaintextRecoveryCodes);

        // 4. Verifikasi di DB bahwa kode pemulihan disimpan dalam bentuk HASH (bukan plaintext)
        $this->adminUser->refresh();
        $storedCodes = $this->adminUser->two_factor_recovery_codes;
        $this->assertCount(8, $storedCodes);
        $this->assertNotEquals($plaintextRecoveryCodes[0], $storedCodes[0]);
        $this->assertTrue(Hash::check($plaintextRecoveryCodes[0], $storedCodes[0]));
    }

    /**
     * BATCH 4B - A.2: Batasi percobaan mfa_token (maks 5 gagal lalu lockout 429).
     */
    public function test_batch4b_mfa_token_locks_out_after_5_failed_attempts(): void
    {
        $secret = $this->twoFactorService->generateSecretKey();
        $this->adminUser->two_factor_secret = $secret;
        $this->adminUser->two_factor_confirmed_at = now();
        $this->adminUser->save();

        $mfaToken = $this->twoFactorService->generateMfaToken($this->adminUser);

        // 5 kali salah masukkan kode TOTP
        for ($i = 0; $i < 5; $i++) {
            $res = $this->postJson('/api/v1/auth/admin/login', [
                'mfa_token' => $mfaToken,
                'totp_code' => '000000',
            ]);
            $res->assertStatus(401);
        }

        // Percobaan ke-6 harus dikunci (429)
        $lockedRes = $this->postJson('/api/v1/auth/admin/login', [
            'mfa_token' => $mfaToken,
            'totp_code' => '000000',
        ]);
        $lockedRes->assertStatus(429);
        $lockedRes->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED');
    }

    /**
     * BATCH 4B - A.2: Cegah replay kode TOTP yang sudah terpakai (simpan last used time-step).
     */
    public function test_batch4b_totp_code_cannot_be_replayed_in_same_or_earlier_timestep(): void
    {
        $secret = $this->twoFactorService->generateSecretKey();
        $this->adminUser->two_factor_secret = $secret;
        $this->adminUser->two_factor_confirmed_at = now();
        $this->adminUser->save();

        $validCode = $this->twoFactorService->calculateCode($secret);

        // Login pertama kali dengan kode TOTP -> Berhasil
        $mfaToken1 = $this->twoFactorService->generateMfaToken($this->adminUser);
        $res1 = $this->postJson('/api/v1/auth/admin/login', [
            'mfa_token' => $mfaToken1,
            'totp_code' => $validCode,
        ]);
        $res1->assertStatus(200);

        // Login kedua kali dengan kode TOTP YANG SAMA (Replay Attack) -> Harus DITOLAK
        $mfaToken2 = $this->twoFactorService->generateMfaToken($this->adminUser);
        $res2 = $this->postJson('/api/v1/auth/admin/login', [
            'mfa_token' => $mfaToken2,
            'totp_code' => $validCode,
        ]);
        $res2->assertStatus(401);
        $res2->assertJsonPath('error.code', 'INVALID_2FA_CODE');
    }

    /**
     * BATCH 4B - A.3: Rate limit admin login ketat (maks 5 gagal / 15 menit, pesan 429 generik).
     */
    public function test_batch4b_admin_login_rate_limiting_returns_generic_429(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $res = $this->postJson('/api/v1/auth/admin/login', [
                'identifier' => 'admin_demo',
                'password' => 'SalahPassword!',
            ]);
            $res->assertStatus(401);
        }

        // Percobaan ke-6 mengembalikan 429 generik
        $res429 = $this->postJson('/api/v1/auth/admin/login', [
            'identifier' => 'admin_demo',
            'password' => 'SalahPassword!',
        ]);
        $res429->assertStatus(429);
        $res429->assertJsonPath('error.message', 'Terlalu banyak percobaan masuk. Silakan coba lagi nanti.');
    }

    /**
     * BATCH 4B - A.6: Audit log mencatat login berhasil/gagal, aktivasi 2FA, dan recovery code.
     */
    public function test_batch4b_audit_logs_record_admin_security_events(): void
    {
        $secret = $this->twoFactorService->generateSecretKey();
        $this->adminUser->two_factor_secret = $secret;
        $this->adminUser->two_factor_confirmed_at = now();
        $rawRecoveryCode = 'ABCD-1234';
        $this->adminUser->two_factor_recovery_codes = [Hash::make($rawRecoveryCode)];
        $this->adminUser->save();

        // 1. Audit login gagal
        $this->postJson('/api/v1/auth/admin/login', [
            'identifier' => 'admin_demo',
            'password' => 'SalahPassword!',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'auth.admin_login_failed',
        ]);

        // 2. Audit login berhasil dengan recovery code
        $mfaToken = $this->twoFactorService->generateMfaToken($this->adminUser);
        $res = $this->postJson('/api/v1/auth/admin/login', [
            'mfa_token' => $mfaToken,
            'recovery_code' => $rawRecoveryCode,
        ]);
        $res->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'auth.2fa_recovery_code_used',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'auth.admin_login',
        ]);
    }

    /**
     * BATCH 5B - A.2: 2FA wajib untuk SEMUA peran shell admin (bukan hanya 3).
     */
    public function test_batch5b_mandatory_2fa_for_all_admin_shell_roles(): void
    {
        $adminRoles = AdminAuthController::ADMIN_ROLES;

        foreach ($adminRoles as $roleCode) {
            RateLimiter::clear('admin_login_attempts:'.sha1(strtolower("user_{$roleCode}").'|127.0.0.1'));
            RateLimiter::clear('admin_login_ip_attempts:127.0.0.1');

            $role = Role::firstOrCreate(['code' => $roleCode], ['name' => ucfirst($roleCode), 'is_system' => true]);
            $user = User::create([
                'username' => "user_{$roleCode}",
                'email' => "user_{$roleCode}@labschool.sch.id",
                'password_hash' => Hash::make('SecretPass123!'),
                'status' => 'active',
                'two_factor_confirmed_at' => null,
            ]);
            $user->roles()->attach($role->id);

            $res = $this->postJson('/api/v1/auth/admin/login', [
                'identifier' => "user_{$roleCode}",
                'password' => 'SecretPass123!',
            ]);

            $res->assertStatus(200);
            $res->assertJson([
                'mfa_enrollment_required' => true,
            ], "Role {$roleCode} gagal memicu mfa_enrollment_required");
            $this->assertNotEmpty($res->json('enrollment_token'), "Role {$roleCode} tidak menerima enrollment_token");
        }
    }
}
