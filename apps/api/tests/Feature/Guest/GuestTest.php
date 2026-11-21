<?php

namespace Tests\Feature\Guest;

use App\Models\Guest;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\GuestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class GuestTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $staffUser;

    protected User $teacherUser;

    protected User $otherTeacherUser;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Roles & Permissions setup
        $roleAdmin = Role::firstOrCreate(['code' => 'admin_tu'], ['name' => 'Admin Tata Usaha', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);

        $permRead = Permission::firstOrCreate(['code' => 'guest.book.read'], ['description' => 'Melihat buku tamu']);
        $permManage = Permission::firstOrCreate(['code' => 'guest.book.manage'], ['description' => 'Mengelola buku tamu']);

        $roleAdmin->permissions()->syncWithoutDetaching([$permRead->id, $permManage->id]);

        // 2. Admin User
        $this->adminUser = User::create([
            'username' => 'admintu',
            'email' => 'tu@sekolah.sch.id',
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        // 3. Staff with only read permission
        $roleStaffReader = Role::firstOrCreate(['code' => 'staff_reader'], ['name' => 'Staff Reader', 'is_system' => false]);
        $roleStaffReader->permissions()->syncWithoutDetaching([$permRead->id]);

        $this->staffUser = User::create([
            'username' => 'staffread',
            'email' => 'staffread@sekolah.sch.id',
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);
        $this->staffUser->roles()->attach($roleStaffReader->id);

        // 4. Teacher User (Destination target)
        $this->teacherUser = User::create([
            'username' => 'gurubudi',
            'email' => 'budi@sekolah.sch.id',
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher->id);

        // 5. Other Teacher User
        $this->otherTeacherUser = User::create([
            'username' => 'gurusiti',
            'email' => 'siti@sekolah.sch.id',
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);
        $this->otherTeacherUser->roles()->attach($roleTeacher->id);
    }

    /**
     * BE-GUEST-01: Public guest registration succeeds.
     */
    public function test_be_guest_01_public_guest_registration_success(): void
    {
        $payload = [
            'full_name' => 'Drs. Hendro Wibowo',
            'institution' => 'Dinas Pendidikan Kota Malang',
            'phone' => '081234567890',
            'purpose' => 'Koordinasi evaluasi kurikulum dan akreditasi sekolah',
        ];

        $response = $this->postJson('/api/v1/public/guests', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.full_name', 'Drs. Hendro Wibowo')
            ->assertJsonPath('data.institution', 'Dinas Pendidikan Kota Malang')
            ->assertJsonPath('data.phone', '081234567890')
            ->assertJsonPath('data.purpose', 'Koordinasi evaluasi kurikulum dan akreditasi sekolah');

        $this->assertDatabaseHas('guests', [
            'full_name' => 'Drs. Hendro Wibowo',
            'phone' => '081234567890',
        ]);

        $guest = Guest::where('full_name', 'Drs. Hendro Wibowo')->first();
        $this->assertNotNull($guest->check_in);
        $this->assertNull($guest->check_out);
    }

    /**
     * BE-GUEST-01: Public guest registration with target_user_id triggers instant notification.
     */
    public function test_be_guest_01_registration_with_target_user_triggers_notification(): void
    {
        $payload = [
            'full_name' => 'Ibu Ratna Kumala',
            'institution' => 'Orang Tua Calon Siswa',
            'phone' => '081987654321',
            'purpose' => 'Konsultasi minat bakat siswa',
            'target_user_id' => $this->teacherUser->id,
        ];

        $response = $this->postJson('/api/v1/public/guests', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.target_user_id', $this->teacherUser->id);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->teacherUser->id,
            'notif_type' => 'guest.arrival',
            'title' => 'Pemberitahuan Kedatangan Tamu',
        ]);

        $notification = Notification::where('user_id', $this->teacherUser->id)->first();
        $this->assertStringContainsString('Ratna Kumala', $notification->body);
        $this->assertStringContainsString('Konsultasi minat bakat siswa', $notification->body);
    }

    /**
     * BE-GUEST-01: Registration fails when required fields are missing.
     */
    public function test_be_guest_01_registration_validation_fails_on_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/public/guests', [
            'institution' => 'Instansi Tanpa Nama',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /**
     * BE-GUEST-01: Staff can list guests with search and status filtering.
     */
    public function test_be_guest_01_staff_can_list_guests_with_filters(): void
    {
        // 1. Guest checked in
        Guest::create([
            'full_name' => 'Bambang Pamungkas',
            'institution' => 'PT Trans Media',
            'phone' => '081111111111',
            'purpose' => 'Liputan kegiatan sekolah',
            'check_in' => Carbon::now()->subHours(2),
            'check_out' => null,
        ]);

        // 2. Guest checked out
        Guest::create([
            'full_name' => 'Sri Wahyuni',
            'institution' => 'Puskesmas Klojen',
            'phone' => '082222222222',
            'purpose' => 'Penyuluhan kesehatan remaja',
            'check_in' => Carbon::now()->subHours(4),
            'check_out' => Carbon::now()->subHour(),
        ]);

        // List all
        $allResponse = $this->actingAs($this->staffUser)
            ->getJson('/api/v1/guests');

        $allResponse->assertStatus(200)
            ->assertJsonPath('meta.total', 2);

        // Filter status checked_in only
        $checkedInResponse = $this->actingAs($this->staffUser)
            ->getJson('/api/v1/guests?status=checked_in');

        $checkedInResponse->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.full_name', 'Bambang Pamungkas');

        // Filter status checked_out only
        $checkedOutResponse = $this->actingAs($this->staffUser)
            ->getJson('/api/v1/guests?status=checked_out');

        $checkedOutResponse->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.full_name', 'Sri Wahyuni');

        // Search q filter
        $searchResponse = $this->actingAs($this->staffUser)
            ->getJson('/api/v1/guests?q=Puskesmas');

        $searchResponse->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.institution', 'Puskesmas Klojen');
    }

    /**
     * BE-GUEST-01: Staff can view guest detail.
     */
    public function test_be_guest_01_staff_can_view_guest_detail(): void
    {
        $guest = Guest::create([
            'full_name' => 'Prof. Dr. Ir. Mulyono',
            'institution' => 'Institut Teknologi',
            'phone' => '081333444555',
            'purpose' => 'Kuliah tamu robotic',
            'target_user_id' => $this->teacherUser->id,
            'check_in' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->staffUser)
            ->getJson("/api/v1/guests/{$guest->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $guest->id)
            ->assertJsonPath('data.full_name', 'Prof. Dr. Ir. Mulyono')
            ->assertJsonPath('data.target_user.id', $this->teacherUser->id);
    }

    /**
     * BE-GUEST-01: Staff can checkout guest.
     */
    public function test_be_guest_01_staff_can_checkout_guest(): void
    {
        $guest = Guest::create([
            'full_name' => 'Tamu Siap Pulang',
            'phone' => '081234567899',
            'purpose' => 'Kunjungan singkat',
            'check_in' => Carbon::now()->subMinutes(45),
            'check_out' => null,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->patchJson("/api/v1/guests/{$guest->id}/checkout");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $guest->id);

        $this->assertNotNull($guest->fresh()->check_out);

        // Audit log created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'guest.checked_out',
            'entity' => 'Guest',
            'entity_id' => $guest->id,
        ]);
    }

    /**
     * BE-GUEST-01: Cannot checkout already checked-out guest.
     */
    public function test_be_guest_01_cannot_checkout_already_checked_out_guest(): void
    {
        $checkedOutTime = Carbon::now()->subHour();
        $guest = Guest::create([
            'full_name' => 'Tamu Sudah Pulang',
            'phone' => '081234567899',
            'purpose' => 'Kunjungan pagi',
            'check_in' => Carbon::now()->subHours(2),
            'check_out' => $checkedOutTime,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->patchJson("/api/v1/guests/{$guest->id}/checkout");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'UNPROCESSABLE_ENTITY');

        $this->assertStringContainsString('sudah melakukan checkout sebelumnya', $response->json('error.message'));
    }

    /**
     * BE-GUEST-01: Unauthenticated user cannot access guest list.
     */
    public function test_be_guest_01_unauthenticated_user_cannot_access_guest_list(): void
    {
        $response = $this->getJson('/api/v1/guests');

        $response->assertStatus(401);
    }

    /**
     * BE-GUEST-01: Unauthorized user without permission cannot checkout guest.
     */
    public function test_be_guest_01_unauthorized_user_cannot_checkout_guest(): void
    {
        $guest = Guest::create([
            'full_name' => 'Tamu Biasa',
            'phone' => '081234567899',
            'purpose' => 'Kunjungan',
            'check_in' => Carbon::now(),
            'check_out' => null,
        ]);

        // staffUser only has guest.book.read, not guest.book.manage
        $response = $this->actingAs($this->staffUser)
            ->patchJson("/api/v1/guests/{$guest->id}/checkout");

        $response->assertStatus(403);
    }

    /**
     * BE-GUEST-01 / SEC-RBAC-01: Regular teacher without guest.book.read is rejected with 403.
     */
    public function test_be_guest_01_sec_rbac_01_teacher_without_permission_is_denied(): void
    {
        $response = $this->actingAs($this->teacherUser)
            ->getJson('/api/v1/guests');

        $response->assertStatus(403);
    }

    /**
     * BE-GUEST-01 / SEC-RBAC-01: Student user is rejected from guest list with 403.
     */
    public function test_be_guest_01_sec_rbac_01_student_is_denied_access(): void
    {
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $studentUser = User::create([
            'username' => 'siswa1',
            'email' => 'siswa1@sekolah.sch.id',
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);
        $studentUser->roles()->attach($roleStudent->id);

        $response = $this->actingAs($studentUser)
            ->getJson('/api/v1/guests');

        $response->assertStatus(403);
    }

    /**
     * BE-GUEST-01 / SEC-RBAC-01: Guardian user is rejected from guest list with 403.
     */
    public function test_be_guest_01_sec_rbac_01_guardian_is_denied_access(): void
    {
        $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Wali Murid', 'is_system' => true]);
        $guardianUser = User::create([
            'username' => 'wali1',
            'email' => 'wali1@sekolah.sch.id',
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);
        $guardianUser->roles()->attach($roleGuardian->id);

        $response = $this->actingAs($guardianUser)
            ->getJson('/api/v1/guests');

        $response->assertStatus(403);
    }

    /**
     * BE-GUEST-01 / SEC-RBAC-01: Authorized teacher with guest.book.read can view and filter assigned guests.
     */
    public function test_be_guest_01_sec_rbac_01_authorized_teacher_can_filter_assigned_guests(): void
    {
        $permRead = Permission::where('code', 'guest.book.read')->first();
        $this->teacherUser->roles()->first()->permissions()->syncWithoutDetaching([$permRead->id]);

        // Guest for Budi
        Guest::create([
            'full_name' => 'Tamu untuk Pak Budi',
            'phone' => '081111111111',
            'purpose' => 'Konsultasi',
            'target_user_id' => $this->teacherUser->id,
            'check_in' => Carbon::now(),
        ]);

        // Guest for Siti
        Guest::create([
            'full_name' => 'Tamu untuk Bu Siti',
            'phone' => '082222222222',
            'purpose' => 'Konsultasi',
            'target_user_id' => $this->otherTeacherUser->id,
            'check_in' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->getJson('/api/v1/guests?target_user_id='.$this->teacherUser->id);

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.full_name', 'Tamu untuk Pak Budi');
    }

    /**
     * BE-GUEST-01 / SEC-RBAC-01: Direct GuestService call throws 403 HttpException when unauthorized.
     */
    public function test_be_guest_01_sec_rbac_01_direct_service_call_rejects_unauthorized_user(): void
    {
        $service = app(GuestService::class);
        $request = Request::create('/api/v1/guests', 'GET');
        $request->setUserResolver(fn () => $this->teacherUser);

        try {
            $service->listGuests([], $request);
            $this->fail('Expected HttpException was not thrown');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }
    }

    /**
     * BE-GUEST-01 / MULTI-TENANCY: School isolation prevents viewing or checkout across different schools.
     */
    public function test_be_guest_01_tenant_isolation_prevents_viewing_or_checkout_across_schools(): void
    {
        $schoolAId = '11111111-1111-1111-1111-111111111111';
        $schoolBId = '22222222-2222-2222-2222-222222222222';

        $this->adminUser->update(['school_id' => $schoolAId]);

        $guestSchoolB = Guest::create([
            'school_id' => $schoolBId,
            'full_name' => 'Tamu Sekolah B',
            'phone' => '089999999999',
            'purpose' => 'Kunjungan studi banding',
            'check_in' => Carbon::now(),
        ]);

        // Attempt get detail
        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/guests/{$guestSchoolB->id}");

        $response->assertStatus(403);

        // Attempt checkout
        $checkoutResponse = $this->actingAs($this->adminUser)
            ->patchJson("/api/v1/guests/{$guestSchoolB->id}/checkout");

        $checkoutResponse->assertStatus(403);
    }
}
