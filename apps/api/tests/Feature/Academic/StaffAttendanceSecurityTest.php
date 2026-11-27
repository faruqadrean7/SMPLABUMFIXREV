<?php

namespace Tests\Feature\Academic;

use App\Models\File;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\StaffAttendance;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffAttendanceSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacherUser;

    protected User $otherTeacherUser;

    protected User $studentUser;

    protected User $guardianUser;

    protected User $guestUser;

    protected function setUp(): void
    {
        parent::setUp();

        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Wali Murid', 'is_system' => true]);
        $roleGuest = Role::firstOrCreate(['code' => 'guest'], ['name' => 'Tamu PPDB', 'is_system' => true]);

        $this->teacherUser = $this->makeUser('p010_guru1', $roleTeacher);
        $this->otherTeacherUser = $this->makeUser('p010_guru2', $roleTeacher);
        $this->studentUser = $this->makeUser('p010_siswa', $roleStudent);
        $this->guardianUser = $this->makeUser('p010_ortu', $roleGuardian);
        $this->guestUser = $this->makeUser('p010_guest', $roleGuest);

        Teacher::create([
            'user_id' => $this->teacherUser->id,
            'nip' => '198601012012011010',
            'full_name' => 'Guru Uji P010',
            'status' => 'tetap',
        ]);

        SiteSetting::create([
            'setting_key' => 'staff_attendance_gps',
            'setting_value' => [
                'latitude' => -7.9625000,
                'longitude' => 112.6175000,
                'radius_meters' => 150,
                'late_time' => '07:15:00',
                'require_gps' => true,
            ],
        ]);
    }

    private function makeUser(string $username, Role $role): User
    {
        $user = User::create([
            'username' => $username,
            'email' => "{$username}@example.test",
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_p0_10_non_staff_roles_blocked_403_across_staff_attendance_endpoints(): void
    {
        $nonStaffActors = [
            'student' => $this->studentUser,
            'guardian' => $this->guardianUser,
            'guest' => $this->guestUser,
        ];

        foreach ($nonStaffActors as $label => $actor) {
            $this->app['auth']->forgetGuards();
            $this->actingAs($actor, 'sanctum')
                ->postJson('/api/v1/staff-attendances/check-in', [
                    'lat' => -7.9625000,
                    'lng' => 112.6175000,
                ])
                ->assertStatus(403, "{$label} seharusnya ditolak check-in.");

            $this->app['auth']->forgetGuards();
            $this->actingAs($actor, 'sanctum')
                ->postJson('/api/v1/staff-attendances/check-out', [
                    'lat' => -7.9625000,
                    'lng' => 112.6175000,
                ])
                ->assertStatus(403, "{$label} seharusnya ditolak check-out.");

            $this->app['auth']->forgetGuards();
            $this->actingAs($actor, 'sanctum')
                ->getJson('/api/v1/staff-attendances/today')
                ->assertStatus(403, "{$label} seharusnya ditolak akses today.");

            $this->app['auth']->forgetGuards();
            $this->actingAs($actor, 'sanctum')
                ->getJson('/api/v1/staff-attendances/settings')
                ->assertStatus(403, "{$label} seharusnya ditolak akses settings.");

            $this->app['auth']->forgetGuards();
            $this->actingAs($actor, 'sanctum')
                ->getJson('/api/v1/staff-attendances')
                ->assertStatus(403, "{$label} seharusnya ditolak akses history.");

            $this->app['auth']->forgetGuards();
            $this->actingAs($actor, 'sanctum')
                ->getJson('/api/v1/staff-attendances/summary')
                ->assertStatus(403, "{$label} seharusnya ditolak akses summary.");
        }

        $this->assertDatabaseCount('staff_attendances', 0);
    }

    public function test_p0_10_omitting_gps_coordinates_rejected_422_when_geofencing_active(): void
    {
        // Check-in tanpa lat/lng -> 422
        $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/staff-attendances/check-in', [
                'notes' => 'Mencoba bypass geofencing',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');

        // Check-in hanya dengan lat (tanpa lng) -> 422
        $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/staff-attendances/check-in', [
                'lat' => -7.9625000,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');

        // Check-in dengan koordinat sah dalam radius -> 201
        $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/staff-attendances/check-in', [
                'lat' => -7.9625500,
                'lng' => 112.6175500,
            ])
            ->assertStatus(201);

        // Check-out tanpa lat/lng -> 422
        $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/staff-attendances/check-out', [
                'notes' => 'Mencoba bypass geofencing pulang',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');

        // Check-out di dalam radius -> 200
        $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/staff-attendances/check-out', [
                'lat' => -7.9625500,
                'lng' => 112.6175500,
            ])
            ->assertStatus(200);
    }

    public function test_p0_10_check_in_without_gps_allowed_only_when_admin_disables_require_gps(): void
    {
        SiteSetting::where('setting_key', 'staff_attendance_gps')->update([
            'setting_value' => [
                'latitude' => -7.9625000,
                'longitude' => 112.6175000,
                'radius_meters' => 150,
                'late_time' => '07:15:00',
                'require_gps' => false,
            ],
        ]);

        $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/staff-attendances/check-in', [
                'notes' => 'Presensi saat geofencing dinonaktifkan admin',
            ])
            ->assertStatus(201);
    }

    public function test_p0_10_check_in_rejects_foreign_photo_file_id_with_403(): void
    {
        $foreignFile = File::create([
            'owner_id' => $this->otherTeacherUser->id,
            'owner_type' => 'User',
            'bucket' => 'staff-attendance',
            'object_key' => 'staff-attendance/other-user-selfie.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'visibility' => 'privat',
            'checksum' => 'chk-other-selfie',
        ]);

        $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/staff-attendances/check-in', [
                'lat' => -7.9625000,
                'lng' => 112.6175000,
                'photo_file_id' => $foreignFile->id,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // Foto milik sendiri diterima 201
        $ownFile = File::create([
            'owner_id' => $this->teacherUser->id,
            'owner_type' => 'User',
            'bucket' => 'staff-attendance',
            'object_key' => 'staff-attendance/own-selfie.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'visibility' => 'privat',
            'checksum' => 'chk-own-selfie',
        ]);

        $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/staff-attendances/check-in', [
                'lat' => -7.9625000,
                'lng' => 112.6175000,
                'photo_file_id' => $ownFile->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.photo_file_id', $ownFile->id);

        $this->assertTrue(
            StaffAttendance::where('user_id', $this->teacherUser->id)
                ->whereDate('att_date', Carbon::now()->format('Y-m-d'))
                ->exists()
        );
    }
}
