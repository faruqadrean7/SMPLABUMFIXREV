<?php

namespace Tests\Feature\Ppdb;

use App\Models\Applicant;
use App\Models\Permission;
use App\Models\PPDBRequirement;
use App\Models\PPDBTrack;
use App\Models\PPDBWave;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PPDBConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected Role $adminRole;

    protected Role $guestRole;

    protected Permission $permManageWave;

    protected string $schoolId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolId = Str::uuid()->toString();

        $this->permManageWave = Permission::create([
            'code' => 'ppdb.wave.manage',
            'description' => 'Mengelola konfigurasi gelombang, jalur, dan syarat PPDB',
        ]);

        $this->adminRole = Role::create([
            'code' => 'super_admin',
            'name' => 'Super Administrator',
            'is_system' => true,
        ]);
        $this->adminRole->permissions()->attach([$this->permManageWave->id]);

        $this->guestRole = Role::create([
            'code' => 'guest',
            'name' => 'Tamu',
            'is_system' => false,
        ]);

        $this->adminUser = User::create([
            'username' => 'admin_ppdb',
            'email' => 'admin.ppdb@labschool.sch.id',
            'password_hash' => Hash::make('AdminSecret123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($this->adminRole->id);

        $this->regularUser = User::create([
            'username' => 'guest_user',
            'email' => 'guest@labschool.sch.id',
            'password_hash' => Hash::make('GuestSecret123!'),
            'status' => 'active',
        ]);
        $this->regularUser->roles()->attach($this->guestRole->id);
    }

    public function test_be_ppdb_01_public_can_list_and_view_waves_and_tracks(): void
    {
        $wave = PPDBWave::create([
            'school_id' => $this->schoolId,
            'name' => 'Gelombang 1 Reguler 2026/2027',
            'open_at' => Carbon::now()->subDays(5),
            'close_at' => Carbon::now()->addDays(20),
            'result_publish_at' => Carbon::now()->addDays(25),
            'status' => 'aktif',
        ]);

        $track = PPDBTrack::create([
            'wave_id' => $wave->id,
            'code' => 'PRESTASI-AKAD',
            'name' => 'Jalur Prestasi Akademik',
            'quota' => 50,
            'score_formula' => ['rapor' => 0.6, 'prestasi' => 0.4],
        ]);

        $req = PPDBRequirement::create([
            'track_id' => $track->id,
            'document_type' => 'kartu_keluarga',
            'is_required' => true,
            'description' => 'Fotokopi KK dilegalisir',
        ]);

        // Public index waves
        $resWaves = $this->getJson('/api/v1/public/ppdb/waves');
        $resWaves->assertOk()
            ->assertJsonPath('data.0.id', $wave->id);

        // Public show wave
        $resWaveShow = $this->getJson('/api/v1/public/ppdb/waves/'.$wave->id);
        $resWaveShow->assertOk()
            ->assertJsonPath('data.name', 'Gelombang 1 Reguler 2026/2027');

        // Public index tracks
        $resTracks = $this->getJson('/api/v1/public/ppdb/tracks?wave_id='.$wave->id);
        $resTracks->assertOk()
            ->assertJsonPath('data.0.id', $track->id);

        // Public show track
        $resTrackShow = $this->getJson('/api/v1/public/ppdb/tracks/'.$track->id);
        $resTrackShow->assertOk()
            ->assertJsonPath('data.code', 'PRESTASI-AKAD')
            ->assertJsonPath('data.requirements.0.id', $req->id);
    }

    public function test_be_ppdb_01_public_can_get_active_wave(): void
    {
        // 1. No active wave
        $resEmpty = $this->getJson('/api/v1/public/ppdb/waves/active');
        $resEmpty->assertOk()
            ->assertJsonPath('data', null);

        // 2. Create active wave within current time
        $activeWave = PPDBWave::create([
            'school_id' => $this->schoolId,
            'name' => 'Gelombang Aktif Berjalan',
            'open_at' => Carbon::now()->subDays(2),
            'close_at' => Carbon::now()->addDays(10),
            'result_publish_at' => Carbon::now()->addDays(15),
            'status' => 'aktif',
        ]);

        $resActive = $this->getJson('/api/v1/public/ppdb/waves/active');
        $resActive->assertOk()
            ->assertJsonPath('data.id', $activeWave->id)
            ->assertJsonPath('data.name', 'Gelombang Aktif Berjalan');
    }

    public function test_be_ppdb_01_unauthenticated_user_cannot_manage_ppdb(): void
    {
        $this->postJson('/api/v1/ppdb/waves', [])->assertUnauthorized();
        $this->postJson('/api/v1/ppdb/tracks', [])->assertUnauthorized();
        $this->postJson('/api/v1/ppdb/requirements', [])->assertUnauthorized();
    }

    public function test_be_ppdb_01_unauthorized_user_cannot_manage_ppdb(): void
    {
        $this->actingAs($this->regularUser);

        $this->postJson('/api/v1/ppdb/waves', [])->assertForbidden();
        $this->postJson('/api/v1/ppdb/tracks', [])->assertForbidden();
        $this->postJson('/api/v1/ppdb/requirements', [])->assertForbidden();
    }

    public function test_be_ppdb_01_wave_validation_rejects_invalid_date_order(): void
    {
        $this->actingAs($this->adminUser);

        // close_at <= open_at
        $res1 = $this->postJson('/api/v1/ppdb/waves', [
            'name' => 'Gelombang Invalid Tanggal 1',
            'open_at' => '2026-06-10 08:00:00',
            'close_at' => '2026-06-05 17:00:00',
            'result_publish_at' => '2026-06-20 10:00:00',
            'status' => 'draft',
        ]);
        $res1->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // result_publish_at < close_at
        $res2 = $this->postJson('/api/v1/ppdb/waves', [
            'name' => 'Gelombang Invalid Tanggal 2',
            'open_at' => '2026-06-01 08:00:00',
            'close_at' => '2026-06-20 17:00:00',
            'result_publish_at' => '2026-06-15 10:00:00',
            'status' => 'draft',
        ]);
        $res2->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_be_ppdb_01_admin_can_crud_waves_and_logs_audit(): void
    {
        $this->actingAs($this->adminUser);

        // 1. Create
        $resCreate = $this->postJson('/api/v1/ppdb/waves', [
            'school_id' => $this->schoolId,
            'name' => 'Gelombang 1 2026',
            'open_at' => '2026-05-01 08:00:00',
            'close_at' => '2026-05-20 17:00:00',
            'result_publish_at' => '2026-05-25 10:00:00',
            'status' => 'draft',
        ]);

        $resCreate->assertStatus(201)
            ->assertJsonPath('data.name', 'Gelombang 1 2026')
            ->assertJsonPath('data.status', 'draft');

        $waveId = $resCreate->json('data.id');

        $this->assertDatabaseHas('ppdb_waves', [
            'id' => $waveId,
            'name' => 'Gelombang 1 2026',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ppdb_wave.created',
            'entity' => 'PPDBWave',
            'entity_id' => $waveId,
        ]);

        // 2. Show
        $this->getJson('/api/v1/ppdb/waves/'.$waveId)
            ->assertOk()
            ->assertJsonPath('data.name', 'Gelombang 1 2026');

        // 3. Update
        $resUpdate = $this->putJson('/api/v1/ppdb/waves/'.$waveId, [
            'name' => 'Gelombang 1 2026 (Diperbarui)',
            'open_at' => '2026-05-01 08:00:00',
            'close_at' => '2026-05-22 17:00:00',
            'result_publish_at' => '2026-05-27 10:00:00',
            'status' => 'aktif',
        ]);

        $resUpdate->assertOk()
            ->assertJsonPath('data.name', 'Gelombang 1 2026 (Diperbarui)')
            ->assertJsonPath('data.status', 'aktif');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ppdb_wave.updated',
            'entity' => 'PPDBWave',
            'entity_id' => $waveId,
        ]);

        // 4. Delete
        $resDelete = $this->deleteJson('/api/v1/ppdb/waves/'.$waveId);
        $resDelete->assertOk();

        $this->assertDatabaseMissing('ppdb_waves', ['id' => $waveId]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ppdb_wave.deleted',
            'entity' => 'PPDBWave',
            'entity_id' => $waveId,
        ]);
    }

    public function test_be_ppdb_01_admin_can_crud_tracks_and_enforces_unique_code_per_wave(): void
    {
        $this->actingAs($this->adminUser);

        $wave1 = PPDBWave::create([
            'school_id' => $this->schoolId,
            'name' => 'Gelombang 1',
            'open_at' => Carbon::now()->subDays(5),
            'close_at' => Carbon::now()->addDays(10),
            'result_publish_at' => Carbon::now()->addDays(15),
            'status' => 'aktif',
        ]);

        $wave2 = PPDBWave::create([
            'school_id' => $this->schoolId,
            'name' => 'Gelombang 2',
            'open_at' => Carbon::now()->addDays(20),
            'close_at' => Carbon::now()->addDays(40),
            'result_publish_at' => Carbon::now()->addDays(45),
            'status' => 'draft',
        ]);

        // 1. Create track in Wave 1
        $resTrack1 = $this->postJson('/api/v1/ppdb/tracks', [
            'wave_id' => $wave1->id,
            'code' => 'REGULER',
            'name' => 'Jalur Reguler Tes',
            'quota' => 100,
            'score_formula' => ['cbt' => 0.7, 'interview' => 0.3],
        ]);
        $resTrack1->assertStatus(201)
            ->assertJsonPath('data.code', 'REGULER');

        $trackId = $resTrack1->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ppdb_track.created',
            'entity_id' => $trackId,
        ]);

        // 2. Duplicate code in Wave 1 must fail
        $resDuplicate = $this->postJson('/api/v1/ppdb/tracks', [
            'wave_id' => $wave1->id,
            'code' => 'REGULER',
            'name' => 'Jalur Reguler Duplikat',
            'quota' => 50,
        ]);
        $resDuplicate->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // 3. Same code in Wave 2 must succeed
        $resTrackWave2 = $this->postJson('/api/v1/ppdb/tracks', [
            'wave_id' => $wave2->id,
            'code' => 'REGULER',
            'name' => 'Jalur Reguler Gelombang 2',
            'quota' => 80,
        ]);
        $resTrackWave2->assertStatus(201);

        // 4. Update track
        $resUpdate = $this->putJson('/api/v1/ppdb/tracks/'.$trackId, [
            'name' => 'Jalur Reguler Tes CBT Diperbarui',
            'quota' => 120,
        ]);
        $resUpdate->assertOk()
            ->assertJsonPath('data.quota', 120);

        // 5. Delete track
        $this->deleteJson('/api/v1/ppdb/tracks/'.$trackId)
            ->assertOk();

        $this->assertDatabaseMissing('ppdb_tracks', ['id' => $trackId]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ppdb_track.deleted',
            'entity_id' => $trackId,
        ]);
    }

    public function test_be_ppdb_01_admin_can_crud_requirements(): void
    {
        $this->actingAs($this->adminUser);

        $wave = PPDBWave::create([
            'school_id' => $this->schoolId,
            'name' => 'Gelombang 1',
            'open_at' => Carbon::now()->subDays(5),
            'close_at' => Carbon::now()->addDays(10),
            'result_publish_at' => Carbon::now()->addDays(15),
            'status' => 'aktif',
        ]);

        $track = PPDBTrack::create([
            'wave_id' => $wave->id,
            'code' => 'AFIRMASI',
            'name' => 'Jalur Afirmasi',
            'quota' => 30,
        ]);

        // 1. Create requirement
        $resReq = $this->postJson('/api/v1/ppdb/requirements', [
            'track_id' => $track->id,
            'document_type' => 'kartu_kip',
            'is_required' => true,
            'description' => 'Scan Kartu Indonesia Pintar asli',
        ]);

        $resReq->assertStatus(201)
            ->assertJsonPath('data.document_type', 'kartu_kip')
            ->assertJsonPath('data.is_required', true);

        $reqId = $resReq->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ppdb_requirement.created',
            'entity_id' => $reqId,
        ]);

        // 2. Show requirement
        $this->getJson('/api/v1/ppdb/requirements/'.$reqId)
            ->assertOk()
            ->assertJsonPath('data.document_type', 'kartu_kip');

        // 3. Update requirement
        $this->putJson('/api/v1/ppdb/requirements/'.$reqId, [
            'is_required' => false,
            'description' => 'Scan Kartu KIP atau SKTM',
        ])->assertOk()
            ->assertJsonPath('data.is_required', false);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ppdb_requirement.updated',
            'entity_id' => $reqId,
        ]);

        // 4. Delete requirement
        $this->deleteJson('/api/v1/ppdb/requirements/'.$reqId)
            ->assertOk();

        $this->assertDatabaseMissing('ppdb_requirements', ['id' => $reqId]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ppdb_requirement.deleted',
            'entity_id' => $reqId,
        ]);
    }

    public function test_be_ppdb_01_cannot_delete_wave_or_track_with_existing_applicants(): void
    {
        $this->actingAs($this->adminUser);

        $wave = PPDBWave::create([
            'school_id' => $this->schoolId,
            'name' => 'Gelombang Ada Pendaftar',
            'open_at' => Carbon::now()->subDays(5),
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

        // Create an applicant registered in this wave & track
        Applicant::create([
            'wave_id' => $wave->id,
            'track_id' => $track->id,
            'user_id' => $this->regularUser->id,
            'registration_no' => 'PPDB-2026-0001',
            'nik_enc' => '3579012345670001',
            'full_name' => 'Ahmad Fulan',
            'birth_date' => '2010-05-15',
            'status' => 'submitted',
        ]);

        // Try to delete track -> 409 CONFLICT
        $resDeleteTrack = $this->deleteJson('/api/v1/ppdb/tracks/'.$track->id);
        $resDeleteTrack->assertStatus(409)
            ->assertJsonPath('error.code', 'CONFLICT')
            ->assertJsonPath('error.message', 'Jalur pendaftaran tidak dapat dihapus karena sudah memiliki pendaftar.');

        // Try to delete wave -> 409 CONFLICT
        $resDeleteWave = $this->deleteJson('/api/v1/ppdb/waves/'.$wave->id);
        $resDeleteWave->assertStatus(409)
            ->assertJsonPath('error.code', 'CONFLICT')
            ->assertJsonPath('error.message', 'Gelombang tidak dapat dihapus karena sudah memiliki pendaftar.');

        // Verify entities still exist
        $this->assertDatabaseHas('ppdb_tracks', ['id' => $track->id]);
        $this->assertDatabaseHas('ppdb_waves', ['id' => $wave->id]);
    }
}
