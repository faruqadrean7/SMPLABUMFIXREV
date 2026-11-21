<?php

namespace Tests\Feature\Ppdb;

use App\Models\Applicant;
use App\Models\PPDBTrack;
use App\Models\PPDBWave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class PPDBResultTest extends TestCase
{
    use RefreshDatabase;

    protected string $schoolId;

    protected PPDBWave $publishedWave;

    protected PPDBWave $unPublishedWave;

    protected PPDBTrack $track;

    protected Applicant $acceptedApplicant;

    protected Applicant $waitlistApplicant;

    protected Applicant $rejectedApplicant;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('ppdb_result_check:127.0.0.1');

        $this->schoolId = Str::uuid()->toString();

        // 1. Published Wave (Published 1 hour ago)
        $this->publishedWave = PPDBWave::create([
            'school_id' => $this->schoolId,
            'name' => 'Gelombang 1 Reguler 2026',
            'open_at' => Carbon::now()->subDays(15),
            'close_at' => Carbon::now()->subDays(5),
            'result_publish_at' => Carbon::now()->subHours(1),
            'status' => 'selesai',
        ]);

        // 2. Unpublished Wave (Publishing in 2 days)
        $this->unPublishedWave = PPDBWave::create([
            'school_id' => $this->schoolId,
            'name' => 'Gelombang 2 Mandiri 2026',
            'open_at' => Carbon::now()->subDays(5),
            'close_at' => Carbon::now()->subDays(1),
            'result_publish_at' => Carbon::now()->addDays(2),
            'status' => 'aktif',
        ]);

        $this->track = PPDBTrack::create([
            'wave_id' => $this->publishedWave->id,
            'code' => 'ZONASI',
            'name' => 'Jalur Zonasi',
            'quota' => 20,
        ]);

        // Users
        $user1 = User::create([
            'username' => 'siswa_diterima',
            'email' => 'diterima@example.com',
            'phone' => '081234567010',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);

        $user2 = User::create([
            'username' => 'siswa_cadangan',
            'email' => 'cadangan@example.com',
            'phone' => '081234567020',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);

        $user3 = User::create([
            'username' => 'siswa_ditolak',
            'email' => 'ditolak@example.com',
            'phone' => '081234567030',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);

        // 3. Applicants in published wave
        $this->acceptedApplicant = Applicant::create([
            'wave_id' => $this->publishedWave->id,
            'track_id' => $this->track->id,
            'user_id' => $user1->id,
            'registration_no' => 'PPDB-2026-00101',
            'nik_enc' => '3579012345670101',
            'full_name' => 'Aditya Nugraha',
            'birth_date' => '2010-03-14',
            'status' => 'selected',
            'score' => 96.50,
            'rank' => 1,
            'decision' => 'diterima',
        ]);

        $this->waitlistApplicant = Applicant::create([
            'wave_id' => $this->publishedWave->id,
            'track_id' => $this->track->id,
            'user_id' => $user2->id,
            'registration_no' => 'PPDB-2026-00102',
            'nik_enc' => '3579012345670102',
            'full_name' => 'Bagus Santosa',
            'birth_date' => '2010-06-20',
            'status' => 'waitlist',
            'score' => 82.00,
            'rank' => 21,
            'decision' => 'cadangan',
        ]);

        $this->rejectedApplicant = Applicant::create([
            'wave_id' => $this->publishedWave->id,
            'track_id' => $this->track->id,
            'user_id' => $user3->id,
            'registration_no' => 'PPDB-2026-00103',
            'nik_enc' => '3579012345670103',
            'full_name' => 'Cahya Ramadhani',
            'birth_date' => '2010-09-05',
            'status' => 'rejected',
            'score' => 70.00,
            'rank' => 35,
            'decision' => 'ditolak',
        ]);
    }

    public function test_be_ppdb_07_public_can_check_published_result_successfully(): void
    {
        $payload = [
            'registration_no' => 'PPDB-2026-00101',
            'birth_date' => '2010-03-14',
        ];

        $response = $this->postJson('/api/v1/public/ppdb/results/check', $payload);

        $response->assertOk()
            ->assertJsonPath('data.registration_no', 'PPDB-2026-00101')
            ->assertJsonPath('data.full_name', 'Aditya Nugraha')
            ->assertJsonPath('data.decision', 'diterima')
            ->assertJsonPath('data.rank', 1)
            ->assertJsonPath('data.next_step', 're_registration')
            ->assertJsonStructure([
                'data' => [
                    'registration_no',
                    'full_name',
                    'birth_date',
                    'wave_name',
                    'track_name',
                    'score',
                    'rank',
                    'decision',
                    'status',
                    'instruction',
                    'next_step',
                    'published_at',
                ],
            ]);
    }

    public function test_be_ppdb_07_check_result_fails_before_publish_time(): void
    {
        $trackUnpublished = PPDBTrack::create([
            'wave_id' => $this->unPublishedWave->id,
            'code' => 'MANDIRI',
            'name' => 'Jalur Mandiri',
            'quota' => 30,
        ]);

        $appUnpublished = Applicant::create([
            'wave_id' => $this->unPublishedWave->id,
            'track_id' => $trackUnpublished->id,
            'registration_no' => 'PPDB-2026-00999',
            'nik_enc' => '3579012345670999',
            'full_name' => 'Siswa Belum Diumumkan',
            'birth_date' => '2010-01-01',
            'status' => 'selected',
            'score' => 95.0,
            'rank' => 1,
            'decision' => 'diterima',
        ]);

        $response = $this->postJson('/api/v1/public/ppdb/results/check', [
            'registration_no' => 'PPDB-2026-00999',
            'birth_date' => '2010-01-01',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_be_ppdb_07_check_result_fails_when_registration_no_or_birth_date_incorrect(): void
    {
        // 1. Wrong registration number
        $res1 = $this->postJson('/api/v1/public/ppdb/results/check', [
            'registration_no' => 'PPDB-2026-99999',
            'birth_date' => '2010-03-14',
        ]);
        $res1->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');

        // 2. Correct reg no, wrong birth date
        $res2 = $this->postJson('/api/v1/public/ppdb/results/check', [
            'registration_no' => 'PPDB-2026-00101',
            'birth_date' => '2010-03-15', // Should be 2010-03-14
        ]);
        $res2->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_be_ppdb_07_check_result_returns_appropriate_instruction_for_waitlist_and_rejected(): void
    {
        // 1. Waitlist applicant
        $resWaitlist = $this->postJson('/api/v1/public/ppdb/results/check', [
            'registration_no' => 'PPDB-2026-00102',
            'birth_date' => '2010-06-20',
        ]);

        $resWaitlist->assertOk()
            ->assertJsonPath('data.decision', 'cadangan')
            ->assertJsonPath('data.status', 'waitlist')
            ->assertJsonPath('data.next_step', 'waitlist_monitoring');

        // 2. Rejected applicant
        $resRejected = $this->postJson('/api/v1/public/ppdb/results/check', [
            'registration_no' => 'PPDB-2026-00103',
            'birth_date' => '2010-09-05',
        ]);

        $resRejected->assertOk()
            ->assertJsonPath('data.decision', 'ditolak')
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.next_step', 'closed');
    }

    public function test_be_ppdb_07_rate_limiting_blocks_repeated_failed_attempts(): void
    {
        RateLimiter::clear('ppdb_result_check:127.0.0.1');

        // 10 failed attempts
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/public/ppdb/results/check', [
                'registration_no' => 'PPDB-INVALID',
                'birth_date' => '2010-01-01',
            ])->assertStatus(404);
        }

        // 11th attempt must be blocked by rate limiter (429)
        $blockedRes = $this->postJson('/api/v1/public/ppdb/results/check', [
            'registration_no' => 'PPDB-INVALID',
            'birth_date' => '2010-01-01',
        ]);

        $blockedRes->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMITED');
    }
}
