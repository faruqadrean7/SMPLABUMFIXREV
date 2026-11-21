<?php

namespace Tests\Feature\Ppdb;

use App\Models\Applicant;
use App\Models\PPDBTrack;
use App\Models\PPDBWave;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PPDBAccountTest extends TestCase
{
    use RefreshDatabase;

    protected Role $guestRole;

    protected string $schoolId;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->schoolId = Str::uuid()->toString();

        $this->guestRole = Role::firstOrCreate(
            ['code' => 'guest'],
            [
                'name' => 'Tamu / Calon Siswa',
                'is_system' => true,
            ]
        );
    }

    public function test_be_ppdb_02_applicant_can_register_successfully_and_receives_otp(): void
    {
        $payload = [
            'full_name' => 'Budi Santoso',
            'email' => 'budi.santoso@example.com',
            'phone' => '081234567890',
            'password' => 'BudiRahasia123!',
            'password_confirmation' => 'BudiRahasia123!',
        ];

        $response = $this->postJson('/api/v1/ppdb/register', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.email', 'budi.santoso@example.com')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonStructure(['data' => ['user_id', 'email', 'phone', 'otp', 'expires_in']]);

        $userId = $response->json('data.user_id');

        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'email' => 'budi.santoso@example.com',
            'status' => 'pending',
        ]);

        $this->assertTrue(Cache::has('ppdb_otp:budi.santoso@example.com'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ppdb_applicant.registered',
            'entity' => 'User',
            'entity_id' => $userId,
        ]);
    }

    public function test_be_ppdb_02_register_fails_with_duplicate_email(): void
    {
        User::create([
            'username' => 'existing_user',
            'email' => 'existing@example.com',
            'phone' => '081111111111',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);

        $payload = [
            'full_name' => 'User Duplikat',
            'email' => 'existing@example.com',
            'phone' => '082222222222',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ];

        $response = $this->postJson('/api/v1/ppdb/register', $payload);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_be_ppdb_02_register_fails_if_nik_already_registered_in_active_wave(): void
    {
        $wave = PPDBWave::create([
            'school_id' => $this->schoolId,
            'name' => 'Gelombang Aktif PPDB',
            'open_at' => Carbon::now()->subDays(2),
            'close_at' => Carbon::now()->addDays(10),
            'result_publish_at' => Carbon::now()->addDays(15),
            'status' => 'aktif',
        ]);

        $track = PPDBTrack::create([
            'wave_id' => $wave->id,
            'code' => 'ZONASI',
            'name' => 'Jalur Zonasi',
            'quota' => 50,
        ]);

        $existingUser = User::create([
            'username' => 'applicant_one',
            'email' => 'applicant1@example.com',
            'phone' => '081234000001',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);

        Applicant::create([
            'wave_id' => $wave->id,
            'track_id' => $track->id,
            'user_id' => $existingUser->id,
            'registration_no' => 'REG-2026-0001',
            'nik_enc' => '3579012345670001',
            'full_name' => 'Anak Pertama',
            'birth_date' => '2010-01-01',
            'status' => 'submitted',
        ]);

        // Attempt register with same NIK
        $payload = [
            'full_name' => 'Anak Kedua',
            'email' => 'applicant2@example.com',
            'phone' => '081234000002',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'nik' => '3579012345670001',
        ];

        $response = $this->postJson('/api/v1/ppdb/register', $payload);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.message', 'NIK ini sudah terdaftar dalam pendaftaran PPDB yang aktif');
    }

    public function test_be_ppdb_02_verify_otp_fails_with_invalid_or_expired_code(): void
    {
        $regRes = $this->postJson('/api/v1/ppdb/register', [
            'full_name' => 'Siti Nurhaliza',
            'email' => 'siti@example.com',
            'phone' => '081234567800',
            'password' => 'SitiRahasia123!',
            'password_confirmation' => 'SitiRahasia123!',
        ]);
        $regRes->assertStatus(201);

        // Wrong code
        $verifyRes = $this->postJson('/api/v1/ppdb/verify-otp', [
            'email' => 'siti@example.com',
            'otp' => '000000',
        ]);

        $verifyRes->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_OTP');
    }

    public function test_be_ppdb_02_verify_otp_succeeds_activates_account_and_returns_token(): void
    {
        $regRes = $this->postJson('/api/v1/ppdb/register', [
            'full_name' => 'Citra Lestari',
            'email' => 'citra@example.com',
            'phone' => '081234567811',
            'password' => 'CitraRahasia123!',
            'password_confirmation' => 'CitraRahasia123!',
        ]);

        $otp = $regRes->json('data.otp');
        $userId = $regRes->json('data.user_id');

        $verifyRes = $this->postJson('/api/v1/ppdb/verify-otp', [
            'email' => 'citra@example.com',
            'otp' => $otp,
        ]);

        $verifyRes->assertOk()
            ->assertJsonPath('data.user.status', 'active')
            ->assertJsonStructure(['data' => ['accessToken', 'tokenType', 'user']]);

        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'status' => 'active',
        ]);

        $this->assertFalse(Cache::has('ppdb_otp:citra@example.com'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ppdb_applicant.otp_verified',
            'entity' => 'User',
            'entity_id' => $userId,
        ]);
    }

    public function test_be_ppdb_02_resend_otp_enforces_cooldown_and_provides_new_code(): void
    {
        $regRes = $this->postJson('/api/v1/ppdb/register', [
            'full_name' => 'Doni Kusuma',
            'email' => 'doni@example.com',
            'phone' => '081234567822',
            'password' => 'DoniRahasia123!',
            'password_confirmation' => 'DoniRahasia123!',
        ]);
        $oldOtp = $regRes->json('data.otp');

        // Immediate resend should be blocked by 60s cooldown (429)
        $resendBlocked = $this->postJson('/api/v1/ppdb/resend-otp', [
            'email' => 'doni@example.com',
        ]);
        $resendBlocked->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMITED');

        // Simulate cooldown expiry
        Cache::forget('ppdb_otp_cooldown:doni@example.com');

        $resendSuccess = $this->postJson('/api/v1/ppdb/resend-otp', [
            'email' => 'doni@example.com',
        ]);
        $resendSuccess->assertOk()
            ->assertJsonStructure(['data' => ['email', 'expires_in', 'message']]);

        $cached = Cache::get('ppdb_otp:doni@example.com');
        $this->assertNotNull($cached);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ppdb_applicant.otp_resent',
            'entity' => 'User',
        ]);
    }

    public function test_be_ppdb_02_check_nik_detects_active_and_available_nik(): void
    {
        $wave = PPDBWave::create([
            'school_id' => $this->schoolId,
            'name' => 'Gelombang 1 Berjalan',
            'open_at' => Carbon::now()->subDays(1),
            'close_at' => Carbon::now()->addDays(5),
            'result_publish_at' => Carbon::now()->addDays(10),
            'status' => 'aktif',
        ]);

        $track = PPDBTrack::create([
            'wave_id' => $wave->id,
            'code' => 'PRESTASI',
            'name' => 'Jalur Prestasi',
            'quota' => 20,
        ]);

        // 1. Unregistered NIK
        $resAvailable = $this->postJson('/api/v1/ppdb/check-nik', [
            'nik' => '3579012345679999',
            'wave_id' => $wave->id,
        ]);
        $resAvailable->assertOk()
            ->assertJsonPath('data.is_available', true)
            ->assertJsonPath('data.has_active_registration', false);

        // 2. Register applicant with this NIK
        $user = User::create([
            'username' => 'active_applicant',
            'email' => 'active@example.com',
            'phone' => '081234567999',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);

        Applicant::create([
            'wave_id' => $wave->id,
            'track_id' => $track->id,
            'user_id' => $user->id,
            'registration_no' => 'REG-2026-9999',
            'nik_enc' => '3579012345679999',
            'full_name' => 'Peserta Aktif',
            'birth_date' => '2010-09-09',
            'status' => 'verified',
        ]);

        // 3. Now check again
        $resUnavailable = $this->postJson('/api/v1/ppdb/check-nik', [
            'nik' => '3579012345679999',
            'wave_id' => $wave->id,
        ]);
        $resUnavailable->assertOk()
            ->assertJsonPath('data.is_available', false)
            ->assertJsonPath('data.has_active_registration', true)
            ->assertJsonPath('data.existing_registration_no', 'REG-2026-9999');
    }

    public function test_be_ppdb_02_authenticated_applicant_can_access_me(): void
    {
        // Unauthenticated
        $this->getJson('/api/v1/ppdb/me')->assertUnauthorized();

        // Authenticated applicant
        $user = User::create([
            'username' => 'applicant_budi',
            'email' => 'applicant.budi@example.com',
            'phone' => '081234567000',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $user->roles()->attach($this->guestRole->id);

        $wave = PPDBWave::create([
            'school_id' => $this->schoolId,
            'name' => 'Gelombang 1',
            'open_at' => Carbon::now()->subDays(1),
            'close_at' => Carbon::now()->addDays(5),
            'result_publish_at' => Carbon::now()->addDays(10),
            'status' => 'aktif',
        ]);

        $track = PPDBTrack::create([
            'wave_id' => $wave->id,
            'code' => 'REGULER',
            'name' => 'Jalur Reguler',
            'quota' => 50,
        ]);

        $applicant = Applicant::create([
            'wave_id' => $wave->id,
            'track_id' => $track->id,
            'user_id' => $user->id,
            'registration_no' => 'REG-2026-1234',
            'nik_enc' => '3579012345671234',
            'full_name' => 'Budi Santoso',
            'birth_date' => '2010-03-20',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/ppdb/me');
        $response->assertOk()
            ->assertJsonPath('data.email', 'applicant.budi@example.com')
            ->assertJsonPath('data.applications.0.registration_no', 'REG-2026-1234')
            ->assertJsonPath('data.applications.0.wave.name', 'Gelombang 1')
            ->assertJsonPath('data.applications.0.track.name', 'Jalur Reguler');
    }

    public function test_be_ppdb_02_applicant_can_login_with_email_and_receives_token_and_profile(): void
    {
        $user = User::create([
            'username' => 'applicant_login_test',
            'email' => 'login.test@example.com',
            'phone' => '081233334444',
            'password_hash' => Hash::make('PasswordKu123!'),
            'status' => 'active',
        ]);
        $user->roles()->attach($this->guestRole->id);

        $response = $this->postJson('/api/v1/ppdb/login', [
            'identifier' => 'login.test@example.com',
            'password' => 'PasswordKu123!',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'accessToken',
                    'tokenType',
                    'expiresIn',
                    'user' => ['id', 'email', 'phone', 'status'],
                    'applicant' => ['id', 'email', 'applications'],
                ],
            ])
            ->assertJsonPath('data.user.email', 'login.test@example.com');
    }

    public function test_be_ppdb_02_applicant_can_login_with_registration_no(): void
    {
        $user = User::create([
            'username' => 'applicant_reg_login',
            'email' => 'reg.login@example.com',
            'phone' => '081255556666',
            'password_hash' => Hash::make('RegSecret123!'),
            'status' => 'active',
        ]);
        $user->roles()->attach($this->guestRole->id);

        $wave = PPDBWave::create([
            'school_id' => $this->schoolId,
            'name' => 'Gelombang 1',
            'open_at' => Carbon::now()->subDays(1),
            'close_at' => Carbon::now()->addDays(5),
            'result_publish_at' => Carbon::now()->addDays(10),
            'status' => 'aktif',
        ]);

        $track = PPDBTrack::create([
            'wave_id' => $wave->id,
            'code' => 'PRESTASI',
            'name' => 'Jalur Prestasi',
            'quota' => 25,
        ]);

        Applicant::create([
            'wave_id' => $wave->id,
            'track_id' => $track->id,
            'user_id' => $user->id,
            'registration_no' => 'REG-2026-8888',
            'nik_enc' => '3579012345678888',
            'full_name' => 'Siswa Jalur Prestasi',
            'birth_date' => '2010-04-12',
            'status' => 'draft',
        ]);

        // Login using registration_no instead of email
        $response = $this->postJson('/api/v1/ppdb/login', [
            'identifier' => 'REG-2026-8888',
            'password' => 'RegSecret123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.email', 'reg.login@example.com')
            ->assertJsonPath('data.applicant.applications.0.registration_no', 'REG-2026-8888');
    }

    public function test_be_ppdb_02_applicant_can_login_with_phone(): void
    {
        $user = User::create([
            'username' => 'applicant_phone_login',
            'email' => 'phone.login@example.com',
            'phone' => '081277778888',
            'password_hash' => Hash::make('PhoneSecret123!'),
            'status' => 'active',
        ]);
        $user->roles()->attach($this->guestRole->id);

        $response = $this->postJson('/api/v1/ppdb/login', [
            'identifier' => '081277778888',
            'password' => 'PhoneSecret123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.email', 'phone.login@example.com');
    }

    public function test_be_ppdb_02_login_fails_with_invalid_credentials(): void
    {
        User::create([
            'username' => 'applicant_wrong_pw',
            'email' => 'wrong.pw@example.com',
            'phone' => '081299990000',
            'password_hash' => Hash::make('CorrectPassword123!'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/ppdb/login', [
            'identifier' => 'wrong.pw@example.com',
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    public function test_be_ppdb_02_login_fails_when_user_is_pending_unverified(): void
    {
        User::create([
            'username' => 'applicant_pending_login',
            'email' => 'pending.user@example.com',
            'phone' => '081200001111',
            'password_hash' => Hash::make('PendingSecret123!'),
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/v1/ppdb/login', [
            'identifier' => 'pending.user@example.com',
            'password' => 'PendingSecret123!',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'ACCOUNT_INACTIVE');
    }
}
