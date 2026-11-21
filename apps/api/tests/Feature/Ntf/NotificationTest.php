<?php

namespace Tests\Feature\Ntf;

use App\Models\AcademicYear;
use App\Models\Device;
use App\Models\GradeLevel;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $teacherUser;

    protected User $studentUser1;

    protected User $studentUser2;

    protected User $studentUser3;

    protected Student $student1;

    protected Student $student2;

    protected Student $student3;

    protected SchoolClass $classA;

    protected SchoolClass $classB;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $permBroadcast = Permission::firstOrCreate(
            ['code' => 'notification.broadcast'],
            ['description' => 'Kirim broadcast pengumuman dan notifikasi']
        );
        $roleAdmin->permissions()->syncWithoutDetaching([$permBroadcast->id]);

        // Users
        $this->adminUser = User::create([
            'username' => 'admin_notif',
            'email' => 'admin_notif@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->teacherUser = User::create([
            'username' => 'guru_notif',
            'email' => 'guru_notif@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher->id);

        $this->studentUser1 = User::create([
            'username' => 'siswa_notif_1',
            'email' => 'siswa_ntf1@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser1->roles()->attach($roleStudent->id);

        $this->studentUser2 = User::create([
            'username' => 'siswa_notif_2',
            'email' => 'siswa_ntf2@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser2->roles()->attach($roleStudent->id);

        $this->studentUser3 = User::create([
            'username' => 'siswa_notif_3',
            'email' => 'siswa_ntf3@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser3->roles()->attach($roleStudent->id);

        // Students
        $this->student1 = Student::create([
            'user_id' => $this->studentUser1->id,
            'nis' => '20263001',
            'nisn' => '0091122331',
            'nik_enc' => '3201010101010301',
            'full_name' => 'Bintang Pratama',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2009-03-10',
            'entry_date' => '2026-07-15',
            'status' => 'active',
        ]);

        $this->student2 = Student::create([
            'user_id' => $this->studentUser2->id,
            'nis' => '20263002',
            'nisn' => '0091122332',
            'nik_enc' => '3201010101010302',
            'full_name' => 'Citra Kirana',
            'gender' => 'P',
            'birth_place' => 'Surabaya',
            'birth_date' => '2009-08-14',
            'entry_date' => '2026-07-15',
            'status' => 'active',
        ]);

        $this->student3 = Student::create([
            'user_id' => $this->studentUser3->id,
            'nis' => '20263003',
            'nisn' => '0091122333',
            'nik_enc' => '3201010101010303',
            'full_name' => 'Danang Wicaksono',
            'gender' => 'L',
            'birth_place' => 'Kediri',
            'birth_date' => '2009-12-01',
            'entry_date' => '2026-07-15',
            'status' => 'active',
        ]);

        // Classes: Class A (student 1 & 2), Class B (student 3)
        $ay = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
        ]);

        $gl = GradeLevel::create([
            'name' => 'Kelas 10',
            'level' => 10,
        ]);

        $this->classA = SchoolClass::create([
            'academic_year_id' => $ay->id,
            'grade_level_id' => $gl->id,
            'name' => 'X-MIPA-1',
            'capacity' => 36,
        ]);

        $this->classB = SchoolClass::create([
            'academic_year_id' => $ay->id,
            'grade_level_id' => $gl->id,
            'name' => 'X-MIPA-2',
            'capacity' => 36,
        ]);

        DB::table('class_students')->insert([
            ['id' => (string) Str::uuid(), 'class_id' => $this->classA->id, 'student_id' => $this->student1->id, 'joined_at' => '2026-07-15', 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'class_id' => $this->classA->id, 'student_id' => $this->student2->id, 'joined_at' => '2026-07-15', 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'class_id' => $this->classB->id, 'student_id' => $this->student3->id, 'joined_at' => '2026-07-15', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_be_ntf_01_unauthenticated_request_returns_401(): void
    {
        $this->postJson('/api/v1/devices', [])->assertStatus(401);
        $this->deleteJson('/api/v1/devices/token123')->assertStatus(401);
        $this->getJson('/api/v1/notifications')->assertStatus(401);
        $this->getJson('/api/v1/notifications/00000000-0000-0000-0000-000000000000')->assertStatus(401);
        $this->patchJson('/api/v1/notifications/read-all')->assertStatus(401);
        $this->postJson('/api/v1/notifications/broadcast', [])->assertStatus(401);
    }

    public function test_be_ntf_01_user_can_register_and_unregister_device_token(): void
    {
        $payload = [
            'platform' => 'android',
            'fcm_token' => 'fcm_sample_token_student1_android_xyz',
        ];

        // 1. Register device
        $response = $this->actingAs($this->studentUser1)
            ->postJson('/api/v1/devices', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.message', 'Perangkat berhasil didaftarkan')
            ->assertJsonPath('data.device.platform', 'android');

        $this->assertDatabaseHas('devices', [
            'user_id' => $this->studentUser1->id,
            'fcm_token' => 'fcm_sample_token_student1_android_xyz',
            'platform' => 'android',
        ]);

        // 2. Unregister device
        $deleteResponse = $this->actingAs($this->studentUser1)
            ->deleteJson('/api/v1/devices/fcm_sample_token_student1_android_xyz');

        $deleteResponse->assertStatus(200);
        $this->assertDatabaseMissing('devices', [
            'fcm_token' => 'fcm_sample_token_student1_android_xyz',
        ]);
    }

    public function test_be_ntf_01_user_can_view_notification_inbox_with_unread_count(): void
    {
        // Create 2 unread and 1 read notifications for Student 1
        Notification::create([
            'user_id' => $this->studentUser1->id,
            'notif_type' => 'academic',
            'title' => 'Nilai Ulangan Harian Fisika',
            'body' => 'Nilai ulangan harian Bab Kinematika telah diumumkan.',
            'read_at' => null,
        ]);

        Notification::create([
            'user_id' => $this->studentUser1->id,
            'notif_type' => 'academic',
            'title' => 'Tugas Matematika Baru',
            'body' => 'Guru matematika mengunggah tugas baru di portal LMS.',
            'read_at' => null,
        ]);

        Notification::create([
            'user_id' => $this->studentUser1->id,
            'notif_type' => 'system',
            'title' => 'Pemeliharaan Sistem Selesai',
            'body' => 'Server telah selesai diperbarui.',
            'read_at' => Carbon::now()->subDay(),
        ]);

        // Access inbox
        $response = $this->actingAs($this->studentUser1)
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.unread_count', 2);

        // Filter unread only
        $unreadResponse = $this->actingAs($this->studentUser1)
            ->getJson('/api/v1/notifications?is_read=false');

        $unreadResponse->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_be_ntf_01_user_can_view_single_notification_with_deep_link(): void
    {
        $notif = Notification::create([
            'user_id' => $this->studentUser1->id,
            'notif_type' => 'library_loan',
            'title' => 'Pengingat Pengembalian Buku',
            'body' => 'Buku Fisika Dasar harus dikembalikan besok.',
            'data' => [
                'screen' => 'LibraryLoanDetail',
                'loan_id' => '01a12345-6789-abcd-ef01-23456789abcd',
                'url' => '/library/loans/01a12345-6789-abcd-ef01-23456789abcd',
            ],
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->studentUser1)
            ->getJson("/api/v1/notifications/{$notif->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $notif->id)
            ->assertJsonPath('data.title', 'Pengingat Pengembalian Buku')
            ->assertJsonPath('data.data.screen', 'LibraryLoanDetail')
            ->assertJsonPath('data.data.loan_id', '01a12345-6789-abcd-ef01-23456789abcd');
    }

    public function test_be_ntf_01_anti_idor_user_cannot_view_or_modify_other_user_notification(): void
    {
        $notifStudent1 = Notification::create([
            'user_id' => $this->studentUser1->id,
            'notif_type' => 'personal',
            'title' => 'Peringatan BK',
            'body' => 'Panggilan bimbingan konseling pribadi.',
            'read_at' => null,
        ]);

        // Student 2 tries to view Student 1's notification -> 404
        $viewResponse = $this->actingAs($this->studentUser2)
            ->getJson("/api/v1/notifications/{$notifStudent1->id}");
        $viewResponse->assertStatus(404);

        // Student 2 tries to mark Student 1's notification as read -> 404
        $readResponse = $this->actingAs($this->studentUser2)
            ->patchJson("/api/v1/notifications/{$notifStudent1->id}/read");
        $readResponse->assertStatus(404);

        // Student 2 tries to delete Student 1's notification -> 404
        $deleteResponse = $this->actingAs($this->studentUser2)
            ->deleteJson("/api/v1/notifications/{$notifStudent1->id}");
        $deleteResponse->assertStatus(404);
    }

    public function test_be_ntf_01_user_can_mark_notification_as_read(): void
    {
        $notif = Notification::create([
            'user_id' => $this->studentUser1->id,
            'notif_type' => 'system',
            'title' => 'Update Jadwal Kuliah Tamu',
            'body' => 'Jadwal dimajukan ke pukul 09:00.',
            'read_at' => null,
        ]);

        $this->assertNull($notif->read_at);

        $response = $this->actingAs($this->studentUser1)
            ->patchJson("/api/v1/notifications/{$notif->id}/read");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_read', true);

        $this->assertNotNull($notif->fresh()->read_at);
    }

    public function test_be_ntf_01_user_can_mark_all_notifications_as_read(): void
    {
        Notification::create([
            'user_id' => $this->studentUser1->id,
            'notif_type' => 'info',
            'title' => 'Info 1',
            'body' => 'Pesan 1',
            'read_at' => null,
        ]);

        Notification::create([
            'user_id' => $this->studentUser1->id,
            'notif_type' => 'info',
            'title' => 'Info 2',
            'body' => 'Pesan 2',
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->studentUser1)
            ->patchJson('/api/v1/notifications/read-all');

        $response->assertStatus(200)
            ->assertJsonPath('data.updated_count', 2);

        $unreadCount = Notification::where('user_id', $this->studentUser1->id)->whereNull('read_at')->count();
        $this->assertEquals(0, $unreadCount);
    }

    public function test_be_ntf_01_user_can_delete_notification(): void
    {
        $notif = Notification::create([
            'user_id' => $this->studentUser1->id,
            'notif_type' => 'info',
            'title' => 'Notif Hapus',
            'body' => 'Akan dihapus',
        ]);

        $response = $this->actingAs($this->studentUser1)
            ->deleteJson("/api/v1/notifications/{$notif->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('notifications', ['id' => $notif->id]);
    }

    public function test_be_ntf_01_staff_can_broadcast_notification_to_role(): void
    {
        // Register devices for Student 1 and Student 2
        Device::create([
            'user_id' => $this->studentUser1->id,
            'platform' => 'android',
            'fcm_token' => 'fcm_token_s1',
        ]);
        Device::create([
            'user_id' => $this->studentUser2->id,
            'platform' => 'ios',
            'fcm_token' => 'fcm_token_s2',
        ]);

        $payload = [
            'target_type' => 'role',
            'target_role' => 'student',
            'title' => 'Pengumuman Libur Hari Raya',
            'body' => 'Kegiatan belajar mengajar ditiadakan mulai tanggal 15 hingga 20.',
            'notif_type' => 'announcement',
            'data' => [
                'screen' => 'AnnouncementsScreen',
                'action' => 'open_calendar',
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/notifications/broadcast', $payload);

        // 3 active students
        $response->assertStatus(201)
            ->assertJsonPath('data.target_type', 'role')
            ->assertJsonPath('data.sent_count', 3)
            ->assertJsonPath('data.device_count', 2);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->studentUser1->id,
            'title' => 'Pengumuman Libur Hari Raya',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->studentUser2->id,
            'title' => 'Pengumuman Libur Hari Raya',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->studentUser3->id,
            'title' => 'Pengumuman Libur Hari Raya',
        ]);
    }

    public function test_be_ntf_01_staff_can_broadcast_notification_to_class(): void
    {
        // Broadcast to Class A (student 1 and student 2)
        $payload = [
            'target_type' => 'class',
            'target_class_id' => $this->classA->id,
            'title' => 'Perubahan Ruang Kelas X-MIPA-1',
            'body' => 'Jam ke-3 dan ke-4 berpindah ke Lab Komputer 2.',
            'notif_type' => 'schedule_change',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/notifications/broadcast', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.target_type', 'class')
            ->assertJsonPath('data.sent_count', 2);

        // Students in Class A received the notification
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->studentUser1->id,
            'title' => 'Perubahan Ruang Kelas X-MIPA-1',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->studentUser2->id,
            'title' => 'Perubahan Ruang Kelas X-MIPA-1',
        ]);

        // Student 3 in Class B did NOT receive the notification
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->studentUser3->id,
            'title' => 'Perubahan Ruang Kelas X-MIPA-1',
        ]);
    }

    public function test_be_ntf_01_staff_can_broadcast_notification_to_specific_users(): void
    {
        $payload = [
            'target_type' => 'users',
            'user_ids' => [$this->studentUser1->id, $this->teacherUser->id],
            'title' => 'Panggilan Ruang Kepala Sekolah',
            'body' => 'Dimohon menemui Kepala Sekolah setelah jam istirahat pertama.',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/notifications/broadcast', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.sent_count', 2);

        $this->assertDatabaseHas('notifications', ['user_id' => $this->studentUser1->id, 'title' => 'Panggilan Ruang Kepala Sekolah']);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->teacherUser->id, 'title' => 'Panggilan Ruang Kepala Sekolah']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $this->studentUser2->id, 'title' => 'Panggilan Ruang Kepala Sekolah']);
    }

    public function test_be_ntf_01_regular_student_cannot_broadcast_notification(): void
    {
        $payload = [
            'target_type' => 'all',
            'title' => 'Pesan Spam Siswa',
            'body' => 'Halo semuanya',
        ];

        $response = $this->actingAs($this->studentUser1)
            ->postJson('/api/v1/notifications/broadcast', $payload);

        $response->assertStatus(403);
    }

    public function test_be_ntf_01_broadcast_validation_errors_on_invalid_target_or_empty_recipients(): void
    {
        // 1. Missing target_type, title, body
        $res1 = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/notifications/broadcast', []);

        $res1->assertStatus(422)
            ->assertJsonValidationErrors(['target_type', 'title', 'body']);

        // 2. target_type = 'role' but target_role missing
        $res2 = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/notifications/broadcast', [
                'target_type' => 'role',
                'title' => 'Test',
                'body' => 'Test',
            ]);

        $res2->assertStatus(422)
            ->assertJsonValidationErrors(['target_role']);

        // 3. target_type = 'class' but target_class_id missing
        $res3 = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/notifications/broadcast', [
                'target_type' => 'class',
                'title' => 'Test',
                'body' => 'Test',
            ]);

        $res3->assertStatus(422)
            ->assertJsonValidationErrors(['target_class_id']);
    }

    public function test_be_ntf_01_broadcast_tenant_isolation_prevents_broadcasting_to_other_schools(): void
    {
        $schoolAId = (string) Str::uuid();
        $schoolBId = (string) Str::uuid();

        // Admin of School A
        $adminSchoolA = User::create([
            'username' => 'admin_school_a',
            'email' => 'admin_a@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
            'school_id' => $schoolAId,
        ]);
        $roleAdmin = Role::where('code', 'super_admin')->first();
        $adminSchoolA->roles()->attach($roleAdmin->id);

        // Student of School A
        $userStudentA = User::create([
            'username' => 'student_school_a',
            'email' => 'student_a@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
            'school_id' => $schoolAId,
        ]);
        $roleStudent = Role::where('code', 'student')->first();
        $userStudentA->roles()->attach($roleStudent->id);

        // Student of School B
        $userStudentB = User::create([
            'username' => 'student_school_b',
            'email' => 'student_b@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
            'school_id' => $schoolBId,
        ]);
        $userStudentB->roles()->attach($roleStudent->id);

        // School A admin broadcasts to role 'student'
        // For school-scoped admin (not super_admin), let's create a school admin role
        $roleSchoolAdmin = Role::firstOrCreate(['code' => 'admin_tu'], ['name' => 'Admin TU', 'is_system' => true]);
        $permBroadcast = Permission::where('code', 'notification.broadcast')->first();
        $roleSchoolAdmin->permissions()->syncWithoutDetaching([$permBroadcast->id]);

        $tuAdminA = User::create([
            'username' => 'tu_school_a',
            'email' => 'tu_a@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
            'school_id' => $schoolAId,
        ]);
        $tuAdminA->roles()->attach($roleSchoolAdmin->id);

        $response = $this->actingAs($tuAdminA)
            ->postJson('/api/v1/notifications/broadcast', [
                'target_type' => 'role',
                'target_role' => 'student',
                'title' => 'Pengumuman Sekolah A',
                'body' => 'Khusus siswa Sekolah A.',
            ]);

        $response->assertStatus(201);
        $this->assertEquals(1, $response->json('data.sent_count'));

        // Notification received by Student A
        $this->assertDatabaseHas('notifications', [
            'user_id' => $userStudentA->id,
            'title' => 'Pengumuman Sekolah A',
        ]);

        // Notification NOT received by Student B of School B
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $userStudentB->id,
            'title' => 'Pengumuman Sekolah A',
        ]);
    }
}
