<?php

namespace Tests\Feature\Academic;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\StaffAttendance;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $teacher1User;

    protected User $teacher2User;

    protected Teacher $teacher1;

    protected Teacher $teacher2;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        // Roles & Permissions
        $roleAdmin = Role::firstOrCreate(['code' => 'admin_tu'], ['name' => 'Admin TU', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);

        $permAttendanceRead = Permission::firstOrCreate(['code' => 'academic.attendance.read'], ['description' => 'Melihat presensi']);
        $permAttendanceWrite = Permission::firstOrCreate(['code' => 'academic.attendance.write'], ['description' => 'Mencatat presensi']);
        $permScheduleManage = Permission::firstOrCreate(['code' => 'academic.schedule.manage'], ['description' => 'Kelola jadwal']);

        $roleAdmin->permissions()->syncWithoutDetaching([$permAttendanceRead->id, $permAttendanceWrite->id, $permScheduleManage->id]);
        $roleTeacher->permissions()->syncWithoutDetaching([$permAttendanceRead->id, $permAttendanceWrite->id]);

        $this->adminUser = User::create([
            'username' => 'admintu',
            'email' => 'admintu@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->teacher1User = User::create([
            'username' => 'guru1',
            'email' => 'guru1@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacher1User->roles()->attach($roleTeacher->id);
        $this->teacher1 = Teacher::create([
            'user_id' => $this->teacher1User->id,
            'nip' => '198001012005011001',
            'full_name' => 'Guru Satu, S.Pd.',
            'status' => 'tetap',
        ]);

        $this->teacher2User = User::create([
            'username' => 'guru2',
            'email' => 'guru2@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacher2User->roles()->attach($roleTeacher->id);
        $this->teacher2 = Teacher::create([
            'user_id' => $this->teacher2User->id,
            'nip' => '198202022006022002',
            'full_name' => 'Guru Dua, M.Pd.',
            'status' => 'tetap',
        ]);

        // Default SiteSetting untuk GPS Sekolah & Toleransi Radius
        SiteSetting::create([
            'setting_key' => 'staff_attendance_gps',
            'setting_value' => [
                'latitude' => -7.9625000,
                'longitude' => 112.6175000,
                'radius_meters' => 150,
                'late_time' => '07:15:00',
            ],
        ]);
    }

    public function test_be_acd_05_staff_can_check_in_successfully(): void
    {
        $token = $this->teacher1User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/staff-attendances/check-in', [
                'notes' => 'Hadir tepat waktu',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.user_id', $this->teacher1User->id)
            ->assertJsonPath('data.notes', 'Hadir tepat waktu');

        $this->assertTrue(
            StaffAttendance::where('user_id', $this->teacher1User->id)
                ->whereDate('att_date', Carbon::now()->format('Y-m-d'))
                ->exists()
        );

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->teacher1User->id,
            'action' => 'staff_attendance.check_in',
        ]);
    }

    public function test_be_acd_05_staff_can_check_in_with_valid_gps_within_radius(): void
    {
        $token = $this->teacher1User->createToken('test')->plainTextToken;

        // Lokasi berjarak ~50 meter dari sekolah (-7.9625, 112.6175)
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/staff-attendances/check-in', [
                'lat' => -7.9627000,
                'lng' => 112.6176000,
                'notes' => 'Check in di gerbang sekolah',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.lat', '-7.9627000')
            ->assertJsonPath('data.lng', '112.6176000');
    }

    public function test_be_acd_05_staff_check_in_rejected_when_outside_radius_422(): void
    {
        $token = $this->teacher1User->createToken('test')->plainTextToken;

        // Lokasi di luar radius (> 1 km dari lokasi sekolah)
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/staff-attendances/check-in', [
                'lat' => -7.9800000,
                'lng' => 112.6300000,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');
    }

    public function test_be_acd_05_staff_can_check_in_with_selfie_upload(): void
    {
        $token = $this->teacher1User->createToken('test')->plainTextToken;

        $selfie = UploadedFile::fake()->create('selfie.jpg', 100, 'image/jpeg');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/staff-attendances/check-in', [
                'photo_file' => $selfie,
                'notes' => 'Selfie presensi',
            ], ['Accept' => 'application/json']);

        $response->assertStatus(201);
        $data = $response->json('data');

        $this->assertNotNull($data['photo_file_id']);
        $this->assertDatabaseHas('files', [
            'id' => $data['photo_file_id'],
            'owner_id' => $this->teacher1User->id,
            'bucket' => 'staff-attendance',
        ]);
    }

    public function test_be_acd_05_duplicate_check_in_rejected_422(): void
    {
        $today = Carbon::now()->format('Y-m-d');

        StaffAttendance::create([
            'user_id' => $this->teacher1User->id,
            'att_date' => $today,
            'check_in' => Carbon::now()->subHour(),
            'status' => 'hadir',
        ]);

        $token = $this->teacher1User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/staff-attendances/check-in', [
                'notes' => 'Check in lagi',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');
    }

    public function test_be_acd_05_staff_can_check_out_after_check_in(): void
    {
        $today = Carbon::now()->format('Y-m-d');

        $att = StaffAttendance::create([
            'user_id' => $this->teacher1User->id,
            'att_date' => $today,
            'check_in' => Carbon::now()->subHours(8),
            'status' => 'hadir',
        ]);

        $token = $this->teacher1User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/staff-attendances/check-out', [
                'notes' => 'Pulang jam kerja selesai',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $att->id);

        $att->refresh();
        $this->assertNotNull($att->check_out);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->teacher1User->id,
            'action' => 'staff_attendance.check_out',
        ]);
    }

    public function test_be_acd_05_staff_cannot_check_out_without_check_in_422(): void
    {
        $token = $this->teacher1User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/staff-attendances/check-out', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');
    }

    public function test_be_acd_05_staff_cannot_double_check_out_422(): void
    {
        $today = Carbon::now()->format('Y-m-d');

        StaffAttendance::create([
            'user_id' => $this->teacher1User->id,
            'att_date' => $today,
            'check_in' => Carbon::now()->subHours(8),
            'check_out' => Carbon::now()->subMinutes(10),
            'status' => 'hadir',
        ]);

        $token = $this->teacher1User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/staff-attendances/check-out', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');
    }

    public function test_be_acd_05_staff_today_status_returns_current_attendance(): void
    {
        $token = $this->teacher1User->createToken('test')->plainTextToken;

        // Sebelum check-in: return null data
        $resBefore = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/staff-attendances/today');
        $resBefore->assertStatus(200)
            ->assertJsonPath('data', null);

        // Lakukan check-in
        StaffAttendance::create([
            'user_id' => $this->teacher1User->id,
            'att_date' => Carbon::now()->format('Y-m-d'),
            'check_in' => Carbon::now(),
            'status' => 'hadir',
        ]);

        // Setelah check-in: return attendance object
        $resAfter = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/staff-attendances/today');
        $resAfter->assertStatus(200)
            ->assertJsonPath('data.user_id', $this->teacher1User->id)
            ->assertJsonPath('data.status', 'hadir');
    }

    public function test_be_acd_05_staff_attendance_history_scoping_non_admin_vs_admin(): void
    {
        $today = Carbon::now()->format('Y-m-d');

        StaffAttendance::create([
            'user_id' => $this->teacher1User->id,
            'att_date' => $today,
            'check_in' => Carbon::now(),
            'status' => 'hadir',
        ]);

        StaffAttendance::create([
            'user_id' => $this->teacher2User->id,
            'att_date' => $today,
            'check_in' => Carbon::now(),
            'status' => 'hadir',
        ]);

        $tokenTeacher1 = $this->teacher1User->createToken('test')->plainTextToken;
        $tokenAdmin = $this->adminUser->createToken('test')->plainTextToken;

        // Guru 1 hanya melihat rekor miliknya (1 data)
        $resTeacher = $this->withHeader('Authorization', "Bearer {$tokenTeacher1}")
            ->getJson('/api/v1/staff-attendances');
        $resTeacher->assertStatus(200);
        $this->assertCount(1, $resTeacher->json('data'));
        $this->app['auth']->forgetGuards();

        // Admin dapat melihat seluruh staf (2 data)
        $resAdmin = $this->withHeader('Authorization', "Bearer {$tokenAdmin}")
            ->getJson('/api/v1/staff-attendances');
        $resAdmin->assertStatus(200);
        $this->assertCount(2, $resAdmin->json('data'));

        // Admin dapat memfilter spesifik Guru 2
        $resFilter = $this->withHeader('Authorization', "Bearer {$tokenAdmin}")
            ->getJson("/api/v1/staff-attendances?user_id={$this->teacher2User->id}");
        $resFilter->assertStatus(200);
        $this->assertCount(1, $resFilter->json('data'));
        $this->assertEquals($this->teacher2User->id, $resFilter->json('data.0.user_id'));
    }

    public function test_be_acd_05_staff_summary_and_attendance_percentage(): void
    {
        $currentMonth = Carbon::now()->format('Y-m');

        // Buat 4 rekor kehadiran untuk Teacher 1: 3 hadir, 1 terlambat -> 100% kehadiran
        StaffAttendance::create(['user_id' => $this->teacher1User->id, 'att_date' => "{$currentMonth}-01", 'check_in' => Carbon::now(), 'status' => 'hadir']);
        StaffAttendance::create(['user_id' => $this->teacher1User->id, 'att_date' => "{$currentMonth}-02", 'check_in' => Carbon::now(), 'status' => 'hadir']);
        StaffAttendance::create(['user_id' => $this->teacher1User->id, 'att_date' => "{$currentMonth}-03", 'check_in' => Carbon::now(), 'status' => 'terlambat']);
        StaffAttendance::create(['user_id' => $this->teacher1User->id, 'att_date' => "{$currentMonth}-04", 'check_in' => Carbon::now(), 'status' => 'hadir']);

        // Buat 2 rekor untuk Teacher 2: 1 hadir, 1 alfa -> 50% kehadiran
        StaffAttendance::create(['user_id' => $this->teacher2User->id, 'att_date' => "{$currentMonth}-01", 'check_in' => Carbon::now(), 'status' => 'hadir']);
        StaffAttendance::create(['user_id' => $this->teacher2User->id, 'att_date' => "{$currentMonth}-02", 'check_in' => null, 'status' => 'alfa']);

        $tokenTeacher1 = $this->teacher1User->createToken('test')->plainTextToken;
        $tokenAdmin = $this->adminUser->createToken('test')->plainTextToken;

        // Teacher 1 melihat ringkasan miliknya sendiri
        $resSummary = $this->withHeader('Authorization', "Bearer {$tokenTeacher1}")
            ->getJson("/api/v1/staff-attendances/summary?month={$currentMonth}");
        $resSummary->assertStatus(200)
            ->assertJsonPath('data.total_recorded_days', 4)
            ->assertJsonPath('data.summary.hadir', 3)
            ->assertJsonPath('data.summary.terlambat', 1);
        $this->assertEquals(100.0, (float) $resSummary->json('data.attendance_percentage'));

        // Teacher 1 dilarang mengintip summary Teacher 2 (403 Forbidden)
        $resForbidden = $this->withHeader('Authorization', "Bearer {$tokenTeacher1}")
            ->getJson("/api/v1/staff-attendances/summary?user_id={$this->teacher2User->id}");
        $resForbidden->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->app['auth']->forgetGuards();

        // Admin dapat melihat ringkasan Teacher 2
        $resAdminSummary = $this->withHeader('Authorization', "Bearer {$tokenAdmin}")
            ->getJson("/api/v1/staff-attendances/summary?month={$currentMonth}&user_id={$this->teacher2User->id}");
        $resAdminSummary->assertStatus(200)
            ->assertJsonPath('data.total_recorded_days', 2)
            ->assertJsonPath('data.summary.hadir', 1)
            ->assertJsonPath('data.summary.alfa', 1);
        $this->assertEquals(50.0, (float) $resAdminSummary->json('data.attendance_percentage'));
    }

    public function test_be_acd_05_admin_can_manually_record_staff_attendance(): void
    {
        $token = $this->adminUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/staff-attendances', [
                'user_id' => $this->teacher1User->id,
                'att_date' => '2026-08-15',
                'status' => 'izin',
                'notes' => 'Izin dinas luar MGMP Matematika',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.user_id', $this->teacher1User->id)
            ->assertJsonPath('data.status', 'izin')
            ->assertJsonPath('data.notes', 'Izin dinas luar MGMP Matematika');

        $this->assertDatabaseHas('staff_attendances', [
            'user_id' => $this->teacher1User->id,
            'status' => 'izin',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'staff_attendance.manual_recorded',
        ]);
    }

    public function test_be_acd_05_non_admin_cannot_manually_record_staff_attendance_403(): void
    {
        $token = $this->teacher1User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/staff-attendances', [
                'user_id' => $this->teacher2User->id,
                'att_date' => '2026-08-16',
                'status' => 'hadir',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_be_acd_05_manual_record_fails_if_already_exists_422(): void
    {
        StaffAttendance::create([
            'user_id' => $this->teacher1User->id,
            'att_date' => '2026-08-17',
            'status' => 'hadir',
        ]);

        $token = $this->adminUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/staff-attendances', [
                'user_id' => $this->teacher1User->id,
                'att_date' => '2026-08-17',
                'status' => 'sakit',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');
    }

    public function test_be_acd_05_admin_can_correct_staff_attendance_with_reason_and_audit(): void
    {
        $att = StaffAttendance::create([
            'user_id' => $this->teacher1User->id,
            'att_date' => '2026-08-18',
            'status' => 'alfa',
            'notes' => 'Tidak hadir tanpa kabar',
        ]);

        $token = $this->adminUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/staff-attendances/{$att->id}", [
                'status' => 'hadir',
                'check_in' => '2026-08-18 07:10:00',
                'reason' => 'Alat scan RFID offline, hadir tepat waktu terkonfirmasi oleh piket',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'hadir');

        $att->refresh();
        $this->assertEquals('hadir', $att->status);
        $this->assertStringContainsString('Koreksi oleh admintu', $att->notes);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'staff_attendance.corrected',
        ]);
    }

    public function test_be_acd_05_non_admin_cannot_correct_staff_attendance_403(): void
    {
        $att = StaffAttendance::create([
            'user_id' => $this->teacher1User->id,
            'att_date' => '2026-08-19',
            'status' => 'terlambat',
        ]);

        $token = $this->teacher1User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/staff-attendances/{$att->id}", [
                'status' => 'hadir',
                'reason' => 'Ingin ubah sendiri jadi hadir',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_be_acd_05_can_get_and_update_attendance_settings(): void
    {
        $token = $this->adminUser->createToken('test')->plainTextToken;

        // Get settings
        $resGet = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/staff-attendances/settings');
        $resGet->assertStatus(200)
            ->assertJsonPath('data.latitude', -7.9625)
            ->assertJsonPath('data.radius_meters', 150);

        // Update settings
        $resPut = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/staff-attendances/settings', [
                'latitude' => -7.9630000,
                'longitude' => 112.6180000,
                'radius_meters' => 200,
                'late_time' => '07:30:00',
            ]);
        $resPut->assertStatus(200)
            ->assertJsonPath('data.radius_meters', 200)
            ->assertJsonPath('data.late_time', '07:30:00');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'staff_attendance.settings_updated',
        ]);
    }

    /**
     * BATCH 5E-1 BUG FIX: Search keyword on staff attendance should not crash 500 when querying staff relations
     */
    public function test_be_acd_05_list_staff_attendances_with_search_keyword_works_without_sql_error(): void
    {
        $token = $this->adminUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/staff-attendances?q=Matematika&page=1&per_page=15');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['page', 'limit', 'total', 'totalPages'],
            ]);
    }
}
