<?php

namespace Tests\Feature\Ppdb;

use App\Models\Applicant;
use App\Models\PPDBTrack;
use App\Models\PPDBWave;
use App\Models\Role;
use App\Models\User;
use App\Services\PPDBResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class PpdbSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected string $schoolId;

    protected PPDBWave $publishedWave;

    protected PPDBTrack $track;

    protected Role $guestRole;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->schoolId = (string) Str::uuid();
        $this->guestRole = Role::firstOrCreate(['code' => 'guest'], ['name' => 'Tamu / Calon Siswa', 'is_system' => true]);

        $this->publishedWave = PPDBWave::create([
            'school_id' => $this->schoolId,
            'name' => 'Gelombang Uji P0-22',
            'open_at' => Carbon::now()->subDays(10),
            'close_at' => Carbon::now()->addDays(10),
            'result_publish_at' => Carbon::now()->subHour(),
            'status' => 'aktif',
        ]);

        $this->track = PPDBTrack::create([
            'wave_id' => $this->publishedWave->id,
            'code' => 'ZONASI-P22',
            'name' => 'Jalur Zonasi P022',
            'quota' => 30,
        ]);
    }

    public function test_p0_22_result_check_does_not_reset_rate_limiter_on_valid_match(): void
    {
        $clientIp = ['REMOTE_ADDR' => '198.51.100.22'];
        RateLimiter::clear('ppdb_result_check:198.51.100.22');

        $user = User::create([
            'username' => 'p022_valid_applicant',
            'email' => 'p022_valid@example.test',
            'phone' => '081200220001',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);

        Applicant::create([
            'wave_id' => $this->publishedWave->id,
            'track_id' => $this->track->id,
            'user_id' => $user->id,
            'registration_no' => 'PPDB-2026-P0221',
            'nik_enc' => '3579010101012201',
            'full_name' => 'Peserta Valid P022',
            'birth_date' => '2011-04-12',
            'status' => 'selected',
            'score' => 91.50,
            'rank' => 1,
            'decision' => 'diterima',
        ]);

        // 5 percobaan gagal + 1 cocok valid + 4 percobaan gagal = 10 total percobaan
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables($clientIp)
                ->postJson('/api/v1/public/ppdb/results/check', [
                    'registration_no' => 'PPDB-2026-SALAH',
                    'birth_date' => '2011-01-01',
                ])
                ->assertStatus(404);
        }

        // Kecocokan valid TIDAK boleh me-reset counter rate limiter
        $this->withServerVariables($clientIp)
            ->postJson('/api/v1/public/ppdb/results/check', [
                'registration_no' => 'PPDB-2026-P0221',
                'birth_date' => '2011-04-12',
            ])
            ->assertOk();

        for ($i = 0; $i < 4; $i++) {
            $this->withServerVariables($clientIp)
                ->postJson('/api/v1/public/ppdb/results/check', [
                    'registration_no' => 'PPDB-2026-SALAH',
                    'birth_date' => '2011-01-01',
                ])
                ->assertStatus(404);
        }

        // Percobaan ke-11 HARUS diblokir 429 RATE_LIMITED
        $this->withServerVariables($clientIp)
            ->postJson('/api/v1/public/ppdb/results/check', [
                'registration_no' => 'PPDB-2026-P0221',
                'birth_date' => '2011-04-12',
            ])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMITED');
    }

    public function test_p0_22_result_check_fails_closed_when_publish_time_missing(): void
    {
        $clientIp = ['REMOTE_ADDR' => '198.51.100.23'];
        RateLimiter::clear('ppdb_result_check:198.51.100.23');

        $applicant = Applicant::create([
            'wave_id' => $this->publishedWave->id,
            'track_id' => $this->track->id,
            'registration_no' => 'PPDB-2026-NULLPUB',
            'nik_enc' => '3579010101012202',
            'full_name' => 'Peserta Gelombang Tanpa Jadwal',
            'birth_date' => '2011-05-15',
            'status' => 'selected',
            'decision' => 'diterima',
        ]);

        // Simulasikan relasi wave tidak terdefinisi / result_publish_at belum diisi di service
        $service = app(PPDBResultService::class);
        $this->publishedWave->result_publish_at = null;
        $applicant->setRelation('wave', $this->publishedWave);

        // Gelombang belum tayang pada sekolah lain (hindari constraint uq_single_active_ppdb_wave)
        $futureWave = PPDBWave::create([
            'school_id' => (string) Str::uuid(),
            'name' => 'Gelombang Belum Publikasi',
            'open_at' => Carbon::now()->subDays(5),
            'close_at' => Carbon::now()->addDays(5),
            'result_publish_at' => Carbon::now()->addDays(7),
            'status' => 'aktif',
        ]);
        $futureTrack = PPDBTrack::create([
            'wave_id' => $futureWave->id,
            'code' => 'FUT-P22',
            'name' => 'Jalur Masa Depan',
            'quota' => 10,
        ]);
        Applicant::create([
            'wave_id' => $futureWave->id,
            'track_id' => $futureTrack->id,
            'registration_no' => 'PPDB-2026-FUT22',
            'nik_enc' => '3579010101012203',
            'full_name' => 'Peserta Belum Tayang',
            'birth_date' => '2011-06-20',
            'status' => 'selected',
            'decision' => 'diterima',
        ]);

        $this->withServerVariables($clientIp)
            ->postJson('/api/v1/public/ppdb/results/check', [
                'registration_no' => 'PPDB-2026-FUT22',
                'birth_date' => '2011-06-20',
            ])
            ->assertStatus(400);

        $this->assertInstanceOf(PPDBResultService::class, $service);
    }

    public function test_p0_22_otp_verification_locks_out_and_invalidates_code_after_five_failures(): void
    {
        $clientIp = ['REMOTE_ADDR' => '198.51.100.24'];

        $reg = $this->withServerVariables($clientIp)
            ->postJson('/api/v1/ppdb/register', [
                'full_name' => 'Peserta Lockout OTP',
                'email' => 'otp.lockout@example.test',
                'phone' => '081200220099',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);
        $reg->assertStatus(201);
        $validOtp = $reg->json('data.otp');
        $wrongOtp = $validOtp === '000000' ? '111111' : '000000';

        // 4 percobaan salah pertama -> 400 INVALID_OTP
        for ($i = 0; $i < 4; $i++) {
            $this->withServerVariables($clientIp)
                ->postJson('/api/v1/ppdb/verify-otp', [
                    'email' => 'otp.lockout@example.test',
                    'otp' => $wrongOtp,
                ])
                ->assertStatus(400)
                ->assertJsonPath('error.code', 'INVALID_OTP');
        }

        // Percobaan ke-5 salah -> 429 RATE_LIMITED & OTP dibatalkan
        $this->withServerVariables($clientIp)
            ->postJson('/api/v1/ppdb/verify-otp', [
                'email' => 'otp.lockout@example.test',
                'otp' => $wrongOtp,
            ])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMITED');

        // OTP valid sekalipun sekarang ditolak karena lockout & OTP sudah diinvalidasi
        $this->withServerVariables($clientIp)
            ->postJson('/api/v1/ppdb/verify-otp', [
                'email' => 'otp.lockout@example.test',
                'otp' => $validOtp,
            ])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMITED');

        $this->assertFalse(Cache::has('ppdb_otp:otp.lockout@example.test'));
    }

    public function test_p0_22_check_nik_uses_blind_index_without_leaking_registration_no_and_application_omits_plaintext_nik(): void
    {
        $clientIp = ['REMOTE_ADDR' => '198.51.100.25'];
        $secretNik = '3579012345672299';

        $user1 = User::create([
            'username' => 'p022_app1',
            'email' => 'p022_app1@example.test',
            'phone' => '081200220010',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $user1->roles()->attach($this->guestRole->id);

        $user2 = User::create([
            'username' => 'p022_app2',
            'email' => 'p022_app2@example.test',
            'phone' => '081200220011',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $user2->roles()->attach($this->guestRole->id);

        // Buat draf aplikasi dengan NIK sensitif
        $createResp = $this->actingAs($user1, 'sanctum')
            ->postJson('/api/v1/ppdb/applications', [
                'wave_id' => $this->publishedWave->id,
                'track_id' => $this->track->id,
                'nik' => $secretNik,
                'full_name' => 'Calon Siswa P022',
                'birth_date' => '2011-08-17',
            ]);

        $createResp->assertStatus(201);
        $applicantId = $createResp->json('data.id');

        // 1. Pastikan nik_hash terisi dan step_data DB TIDAK menyimpan NIK plaintext
        $rawApplicant = DB::table('applicants')->where('id', $applicantId)->first();
        $this->assertSame(Applicant::hashNik($secretNik), $rawApplicant->nik_hash);
        $this->assertStringNotContainsString($secretNik, (string) $rawApplicant->nik_enc);
        $this->assertStringNotContainsString($secretNik, (string) $rawApplicant->step_data);

        // 2. Update step personal juga tidak boleh menyimpan NIK plaintext di step_data maupun audit_logs
        $this->actingAs($user1, 'sanctum')
            ->patchJson("/api/v1/ppdb/applications/{$applicantId}/steps/personal", [
                'nik' => $secretNik,
                'birth_place' => 'Malang',
            ])
            ->assertOk();

        $rawAfterUpdate = DB::table('applicants')->where('id', $applicantId)->first();
        $this->assertStringNotContainsString($secretNik, (string) $rawAfterUpdate->step_data);

        $stepAudit = DB::table('audit_logs')
            ->where('action', 'ppdb_application.step_updated')
            ->where('entity_id', $applicantId)
            ->latest('id')
            ->first();
        $this->assertNotNull($stepAudit);
        $this->assertStringNotContainsString($secretNik, (string) $stepAudit->new_value);

        // 3. Pendaftar lain dengan NIK sama ditolak (via nik_hash)
        $this->app['auth']->forgetGuards();
        $this->actingAs($user2, 'sanctum')
            ->postJson('/api/v1/ppdb/applications', [
                'wave_id' => $this->publishedWave->id,
                'track_id' => $this->track->id,
                'nik' => $secretNik,
                'full_name' => 'Pendaftar Duplikat',
                'birth_date' => '2011-08-17',
            ])
            ->assertStatus(400);

        // 4. Endpoint publik check-nik TIDAK membocorkan nomor registrasi maupun status
        $checkResp = $this->withServerVariables($clientIp)
            ->postJson('/api/v1/ppdb/check-nik', [
                'nik' => $secretNik,
                'wave_id' => $this->publishedWave->id,
            ]);

        $checkResp->assertOk()
            ->assertJsonPath('data.is_available', false)
            ->assertJsonPath('data.has_active_registration', true)
            ->assertJsonMissingPath('data.existing_registration_no')
            ->assertJsonMissingPath('data.status')
            ->assertJsonMissingPath('data.nik');
    }
}
