<?php

namespace Tests\Feature\Ntf;

use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationPreferenceRoleTest extends TestCase
{
    use RefreshDatabase;

    protected User $studentUser;

    protected User $teacherUser;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);

        $permBroadcast = Permission::firstOrCreate(['code' => 'notification.broadcast'], ['name' => 'Broadcast Notifikasi', 'module' => 'notification']);
        $roleAdmin->permissions()->syncWithoutDetaching([$permBroadcast->id]);

        $this->studentUser = User::create([
            'username' => 'siswa_gap11',
            'email' => 'siswa_gap11@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($roleStudent->id);

        $this->teacherUser = User::create([
            'username' => 'guru_gap11',
            'email' => 'guru_gap11@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher->id);

        $this->adminUser = User::create([
            'username' => 'admin_gap11',
            'email' => 'admin_gap11@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);
    }

    /**
     * GAP-11: 1. Endpoint /api/v1/me/notification-preferences mengembalikan preferensi sesuai scoping peran.
     */
    public function test_gap_11_get_me_notification_preferences_is_role_scoped(): void
    {
        // Siswa memanggil /me/notification-preferences
        Sanctum::actingAs($this->studentUser);
        $resStudent = $this->getJson('/api/v1/me/notification-preferences');
        $resStudent->assertStatus(200);

        $studentData = $resStudent->json('data');
        $studentTypes = collect($studentData)->pluck('notif_type')->toArray();

        // Siswa punya akses ke topik umum & akademik
        $this->assertContains('academic_announcement', $studentTypes);
        $this->assertContains('attendance_alert', $studentTypes);
        // Siswa TIDAK boleh melihat topik khusus guru
        $this->assertNotContains('teacher_agenda', $studentTypes);
        $this->assertNotContains('assignment_submissions', $studentTypes);

        // Guru memanggil /me/notification-preferences
        Sanctum::actingAs($this->teacherUser);
        $resTeacher = $this->getJson('/api/v1/me/notification-preferences');
        $resTeacher->assertStatus(200);

        $teacherData = $resTeacher->json('data');
        $teacherTypes = collect($teacherData)->pluck('notif_type')->toArray();

        // Guru memiliki akses ke topik guru
        $this->assertContains('teacher_agenda', $teacherTypes);
        $this->assertContains('assignment_submissions', $teacherTypes);
    }

    /**
     * GAP-11: 2. Validasi scoping peran menolak siswa mengatur topik guru (403 Forbidden).
     */
    public function test_gap_11_student_cannot_configure_teacher_topics(): void
    {
        Sanctum::actingAs($this->studentUser);

        // Siswa mencoba mengubah preferensi topik khusus guru -> HARUS ditolak 403
        $response = $this->putJson('/api/v1/me/notification-preferences', [
            'notif_type' => 'teacher_agenda',
            'channel_push' => false,
        ]);

        $response->assertStatus(403);

        // Siswa juga ditolak jika mengirim lewat format array preferences
        $responseBulk = $this->putJson('/api/v1/me/notification-preferences', [
            'preferences' => [
                ['notif_type' => 'academic_announcement', 'channel_push' => true],
                ['notif_type' => 'assignment_submissions', 'channel_push' => false],
            ],
        ]);

        $responseBulk->assertStatus(403);
    }

    /**
     * GAP-11: 3. Guru dapat mengatur preferensi topiknya sendiri secara sukses.
     */
    public function test_gap_11_teacher_can_configure_own_topics(): void
    {
        Sanctum::actingAs($this->teacherUser);

        $response = $this->putJson('/api/v1/me/notification-preferences', [
            'preferences' => [
                ['notif_type' => 'teacher_agenda', 'channel_push' => false, 'is_enabled' => false],
                ['notif_type' => 'assignment_submissions', 'channel_push' => true, 'is_enabled' => true],
            ],
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $this->teacherUser->id,
            'notif_type' => 'teacher_agenda',
            'is_enabled' => 0,
            'channel_push' => 0,
        ]);
    }

    /**
     * GAP-11: 4. Broadcast pengiriman notifikasi memfilter penerima yang menonaktifkan preferensi push.
     */
    public function test_gap_11_broadcast_filters_recipients_based_on_preferences(): void
    {
        // Siswa 2 tambahan
        $student2 = User::create([
            'username' => 'siswa_gap11_2',
            'email' => 'siswa_gap11_2@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $student2->roles()->attach(Role::where('code', 'student')->first()->id);

        // Siswa 1 mematikan notifikasi push untuk academic_announcement
        NotificationPreference::create([
            'user_id' => $this->studentUser->id,
            'notif_type' => 'academic_announcement',
            'channel_push' => false,
            'is_enabled' => false,
        ]);

        // Siswa 2 membiarkan default (aktif)

        // Admin melakukan broadcast bertopik academic_announcement ke seluruh siswa
        Sanctum::actingAs($this->adminUser);
        $response = $this->postJson('/api/v1/notifications/broadcast', [
            'target_type' => 'role',
            'target_role' => 'student',
            'notif_type' => 'academic_announcement',
            'title' => 'Pengumuman Penting Ujian Akhir',
            'body' => 'Ujian akhir semester akan dimulai pekan depan.',
        ]);

        $this->assertTrue(in_array($response->status(), [200, 201]), "Expected 200 or 201, got {$response->status()}");

        // Siswa 1 TIDAK boleh menerima notifikasi karena preferensi dinonaktifkan
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->studentUser->id,
            'notif_type' => 'academic_announcement',
            'title' => 'Pengumuman Penting Ujian Akhir',
        ]);

        // Siswa 2 menerima notifikasi karena preferensinya aktif
        $this->assertDatabaseHas('notifications', [
            'user_id' => $student2->id,
            'notif_type' => 'academic_announcement',
            'title' => 'Pengumuman Penting Ujian Akhir',
        ]);
    }
}
