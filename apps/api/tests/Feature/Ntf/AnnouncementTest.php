<?php

namespace Tests\Feature\Ntf;

use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\GradeLevel;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $teacherUser;

    protected User $studentUser1;

    protected User $studentUser2;

    protected Student $student1;

    protected Student $student2;

    protected SchoolClass $classA;

    protected SchoolClass $classB;

    protected GradeLevel $grade10;

    protected GradeLevel $grade11;

    protected function setUp(): void
    {
        parent::setUp();

        // Roles
        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        // Users
        $this->adminUser = User::create([
            'username' => 'admin_anc',
            'email' => 'admin_anc@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->teacherUser = User::create([
            'username' => 'guru_anc',
            'email' => 'guru_anc@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher->id);

        $this->studentUser1 = User::create([
            'username' => 'siswa_anc_1',
            'email' => 'siswa1@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser1->roles()->attach($roleStudent->id);

        $this->studentUser2 = User::create([
            'username' => 'siswa_anc_2',
            'email' => 'siswa2@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser2->roles()->attach($roleStudent->id);

        // Academic Structure
        $academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $this->grade10 = GradeLevel::create([
            'name' => 'Kelas 10',
            'level' => 10,
        ]);

        $this->grade11 = GradeLevel::create([
            'name' => 'Kelas 11',
            'level' => 11,
        ]);

        $this->classA = SchoolClass::create([
            'academic_year_id' => $academicYear->id,
            'grade_level_id' => $this->grade10->id,
            'name' => '10-MIPA-1',
            'capacity' => 32,
        ]);

        $this->classB = SchoolClass::create([
            'academic_year_id' => $academicYear->id,
            'grade_level_id' => $this->grade11->id,
            'name' => '11-MIPA-1',
            'capacity' => 32,
        ]);

        // Students
        $this->student1 = Student::create([
            'user_id' => $this->studentUser1->id,
            'nis' => '10001',
            'nisn' => '0010001',
            'nik_enc' => '3578010101010001',
            'full_name' => 'Siswa Pertama',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2009-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'active',
        ]);

        $this->student2 = Student::create([
            'user_id' => $this->studentUser2->id,
            'nis' => '10002',
            'nisn' => '0010002',
            'nik_enc' => '3578010101010002',
            'full_name' => 'Siswa Kedua',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2009-02-02',
            'entry_date' => '2026-07-01',
            'status' => 'active',
        ]);

        DB::table('class_students')->insert([
            [
                'id' => (string) Str::uuid(),
                'class_id' => $this->classA->id,
                'student_id' => $this->student1->id,
                'joined_at' => '2026-07-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'class_id' => $this->classB->id,
                'student_id' => $this->student2->id,
                'joined_at' => '2026-07-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function test_be_ntf_03_unauthenticated_request_returns_401(): void
    {
        $res = $this->getJson('/api/v1/announcements');
        $res->assertStatus(401);
    }

    public function test_be_ntf_03_staff_can_create_update_and_delete_announcement(): void
    {
        Sanctum::actingAs($this->adminUser);

        // 1. Create
        $payload = [
            'title' => 'Upacara Hari Pahlawan Nasional',
            'body' => 'Diberitahukan kepada seluruh warga sekolah untuk mengenakan seragam upacara lengkap.',
            'target_type' => 'semua',
            'priority' => 'high',
            'is_pinned' => true,
            'publish_at' => Carbon::now()->subMinute()->toIso8601String(),
        ];

        $resCreate = $this->postJson('/api/v1/announcements', $payload);
        $resCreate->assertStatus(201)
            ->assertJsonPath('data.title', 'Upacara Hari Pahlawan Nasional')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.is_pinned', true)
            ->assertJsonPath('data.target_type', 'semua');

        $announcementId = $resCreate->json('data.id');
        $this->assertDatabaseHas('announcements', [
            'id' => $announcementId,
            'title' => 'Upacara Hari Pahlawan Nasional',
            'is_pinned' => 1,
        ]);

        // 2. Show Detail
        $resShow = $this->getJson('/api/v1/announcements/'.$announcementId);
        $resShow->assertStatus(200)
            ->assertJsonPath('data.id', $announcementId);

        // 3. Update
        $resUpdate = $this->putJson('/api/v1/announcements/'.$announcementId, [
            'title' => 'Upacara Peringatan Hari Pahlawan Nasional Terpadu',
            'priority' => 'urgent',
        ]);
        $resUpdate->assertStatus(200)
            ->assertJsonPath('data.title', 'Upacara Peringatan Hari Pahlawan Nasional Terpadu')
            ->assertJsonPath('data.priority', 'urgent');

        $this->assertDatabaseHas('announcements', [
            'id' => $announcementId,
            'priority' => 'urgent',
        ]);

        // 4. Delete
        $resDelete = $this->deleteJson('/api/v1/announcements/'.$announcementId);
        $resDelete->assertStatus(200);

        $this->assertDatabaseMissing('announcements', [
            'id' => $announcementId,
        ]);
    }

    public function test_be_ntf_03_regular_student_cannot_create_update_or_delete_announcement(): void
    {
        Sanctum::actingAs($this->studentUser1);

        $announcement = Announcement::create([
            'author_id' => $this->adminUser->id,
            'title' => 'Pengumuman Resmi Sekolah',
            'body' => 'Isi pengumuman resmi',
            'target_type' => 'semua',
            'publish_at' => Carbon::now()->subMinute(),
        ]);

        // Create -> 403
        $resCreate = $this->postJson('/api/v1/announcements', [
            'title' => 'Pengumuman dari Siswa',
            'body' => 'Tidak boleh buat pengumuman',
            'target_type' => 'semua',
        ]);
        $resCreate->assertStatus(403);

        // Update -> 403
        $resUpdate = $this->putJson('/api/v1/announcements/'.$announcement->id, [
            'title' => 'Diubah oleh Siswa',
        ]);
        $resUpdate->assertStatus(403);

        // Delete -> 403
        $resDelete = $this->deleteJson('/api/v1/announcements/'.$announcement->id);
        $resDelete->assertStatus(403);
    }

    public function test_be_ntf_03_announcements_targeted_to_all_are_visible_to_all_students(): void
    {
        Announcement::create([
            'author_id' => $this->adminUser->id,
            'title' => 'Libur Awal Semester',
            'body' => 'Pengumuman jadwal libur awal semester.',
            'target_type' => 'semua',
            'publish_at' => Carbon::now()->subHour(),
        ]);

        Sanctum::actingAs($this->studentUser1);
        $res1 = $this->getJson('/api/v1/announcements');
        $res1->assertStatus(200);
        $this->assertNotEmpty($res1->json('data'));
        $this->assertEquals('Libur Awal Semester', $res1->json('data.0.title'));
        $this->assertFalse($res1->json('data.0.is_read'));

        Sanctum::actingAs($this->studentUser2);
        $res2 = $this->getJson('/api/v1/announcements');
        $res2->assertStatus(200);
        $this->assertNotEmpty($res2->json('data'));
        $this->assertEquals('Libur Awal Semester', $res2->json('data.0.title'));
    }

    public function test_be_ntf_03_scheduled_announcement_not_visible_to_students_before_publish_time(): void
    {
        $futureAnnouncement = Announcement::create([
            'author_id' => $this->adminUser->id,
            'title' => 'Pengumuman Masa Depan',
            'body' => 'Belum saatnya tayang.',
            'target_type' => 'semua',
            'publish_at' => Carbon::now()->addDays(2),
        ]);

        // Student tries to view list -> empty
        Sanctum::actingAs($this->studentUser1);
        $resStudentList = $this->getJson('/api/v1/announcements');
        $resStudentList->assertStatus(200);
        $this->assertEmpty($resStudentList->json('data'));

        // Student tries to view detail -> 403
        $resStudentDetail = $this->getJson('/api/v1/announcements/'.$futureAnnouncement->id);
        $resStudentDetail->assertStatus(403);

        // Admin can view scheduled announcements
        Sanctum::actingAs($this->adminUser);
        $resAdminList = $this->getJson('/api/v1/announcements?published_only=false');
        $resAdminList->assertStatus(200);
        $this->assertNotEmpty($resAdminList->json('data'));
        $this->assertEquals('Pengumuman Masa Depan', $resAdminList->json('data.0.title'));
    }

    public function test_be_ntf_03_expired_announcement_not_visible_to_students(): void
    {
        $expiredAnnouncement = Announcement::create([
            'author_id' => $this->adminUser->id,
            'title' => 'Pengumuman Sudah Berakhir',
            'body' => 'Event sudah selesai.',
            'target_type' => 'semua',
            'publish_at' => Carbon::now()->subDays(5),
            'expires_at' => Carbon::now()->subDay(),
        ]);

        Sanctum::actingAs($this->studentUser1);

        $resList = $this->getJson('/api/v1/announcements');
        $resList->assertStatus(200);
        $this->assertEmpty($resList->json('data'));

        $resDetail = $this->getJson('/api/v1/announcements/'.$expiredAnnouncement->id);
        $resDetail->assertStatus(403);
    }

    public function test_be_ntf_03_announcement_targeted_to_class_is_only_visible_to_students_in_that_class(): void
    {
        $classAnnouncement = Announcement::create([
            'author_id' => $this->adminUser->id,
            'title' => 'Tugas Tambahan Khusus Kelas 10-MIPA-1',
            'body' => 'Bagi siswa kelas 10-MIPA-1 harap kumpulkan jurnal.',
            'target_type' => 'kelas',
            'target_ids' => [$this->classA->id],
            'publish_at' => Carbon::now()->subMinute(),
        ]);

        // Student 1 (in Class A) -> should see it
        Sanctum::actingAs($this->studentUser1);
        $resStudent1 = $this->getJson('/api/v1/announcements');
        $resStudent1->assertStatus(200);
        $this->assertCount(1, $resStudent1->json('data'));
        $this->assertEquals($classAnnouncement->id, $resStudent1->json('data.0.id'));

        $resStudent1Detail = $this->getJson('/api/v1/announcements/'.$classAnnouncement->id);
        $resStudent1Detail->assertStatus(200);

        // Student 2 (in Class B) -> should NOT see it
        Sanctum::actingAs($this->studentUser2);
        $resStudent2 = $this->getJson('/api/v1/announcements');
        $resStudent2->assertStatus(200);
        $this->assertEmpty($resStudent2->json('data'));

        $resStudent2Detail = $this->getJson('/api/v1/announcements/'.$classAnnouncement->id);
        $resStudent2Detail->assertStatus(403);
    }

    public function test_be_ntf_03_announcement_targeted_to_grade_level_is_visible_to_matching_grade(): void
    {
        $gradeAnnouncement = Announcement::create([
            'author_id' => $this->adminUser->id,
            'title' => 'Sosialisasi Penjurusan Kelas 10',
            'body' => 'Khusus seluruh siswa jenjang kelas 10.',
            'target_type' => 'tingkat',
            'target_ids' => [$this->grade10->id],
            'publish_at' => Carbon::now()->subMinute(),
        ]);

        // Student 1 (Grade 10) -> can view
        Sanctum::actingAs($this->studentUser1);
        $res1 = $this->getJson('/api/v1/announcements');
        $res1->assertStatus(200);
        $this->assertCount(1, $res1->json('data'));
        $this->assertEquals($gradeAnnouncement->id, $res1->json('data.0.id'));

        // Student 2 (Grade 11) -> cannot view
        Sanctum::actingAs($this->studentUser2);
        $res2 = $this->getJson('/api/v1/announcements');
        $res2->assertStatus(200);
        $this->assertEmpty($res2->json('data'));
    }

    public function test_be_ntf_03_announcement_targeted_to_role_is_only_visible_to_that_role(): void
    {
        $teacherAnnouncement = Announcement::create([
            'author_id' => $this->adminUser->id,
            'title' => 'Rapat Dewan Guru Akhir Pekan',
            'body' => 'Rapat evaluasi pembelajaran.',
            'target_type' => 'role',
            'target_ids' => ['teacher'],
            'publish_at' => Carbon::now()->subMinute(),
        ]);

        // Teacher -> can view
        Sanctum::actingAs($this->teacherUser);
        $resTeacher = $this->getJson('/api/v1/announcements');
        $resTeacher->assertStatus(200);
        $this->assertCount(1, $resTeacher->json('data'));
        $this->assertEquals($teacherAnnouncement->id, $resTeacher->json('data.0.id'));

        // Student -> cannot view
        Sanctum::actingAs($this->studentUser1);
        $resStudent = $this->getJson('/api/v1/announcements');
        $resStudent->assertStatus(200);
        $this->assertEmpty($resStudent->json('data'));
    }

    public function test_be_ntf_03_student_can_mark_announcement_as_read(): void
    {
        $announcement = Announcement::create([
            'author_id' => $this->adminUser->id,
            'title' => 'Pedoman Ketertiban Sekolah',
            'body' => 'Wajib dibaca oleh semua siswa.',
            'target_type' => 'semua',
            'publish_at' => Carbon::now()->subMinute(),
        ]);

        Sanctum::actingAs($this->studentUser1);

        // Initially unread
        $resListBefore = $this->getJson('/api/v1/announcements');
        $resListBefore->assertStatus(200)
            ->assertJsonPath('data.0.is_read', false);

        // Mark as Read
        $resRead = $this->postJson('/api/v1/announcements/'.$announcement->id.'/read');
        $resRead->assertStatus(200)
            ->assertJsonPath('data.is_read', true)
            ->assertJsonPath('data.message', 'Pengumuman berhasil ditandai telah dibaca.');

        $this->assertDatabaseHas('announcement_reads', [
            'announcement_id' => $announcement->id,
            'user_id' => $this->studentUser1->id,
        ]);

        // Check list after reading -> is_read = true
        $resListAfter = $this->getJson('/api/v1/announcements');
        $resListAfter->assertStatus(200)
            ->assertJsonPath('data.0.is_read', true);
    }

    public function test_be_ntf_03_filter_announcements_by_is_read_status(): void
    {
        $anc1 = Announcement::create([
            'author_id' => $this->adminUser->id,
            'title' => 'Pengumuman Satu (Sudah Dibaca)',
            'body' => 'Isi satu',
            'target_type' => 'semua',
            'publish_at' => Carbon::now()->subMinute(),
        ]);

        $anc2 = Announcement::create([
            'author_id' => $this->adminUser->id,
            'title' => 'Pengumuman Dua (Belum Dibaca)',
            'body' => 'Isi dua',
            'target_type' => 'semua',
            'publish_at' => Carbon::now()->subMinute(),
        ]);

        Sanctum::actingAs($this->studentUser1);

        // Read anc1
        $this->postJson('/api/v1/announcements/'.$anc1->id.'/read')->assertStatus(200);

        // Filter is_read=true
        $resRead = $this->getJson('/api/v1/announcements?is_read=true');
        $resRead->assertStatus(200);
        $this->assertCount(1, $resRead->json('data'));
        $this->assertEquals($anc1->id, $resRead->json('data.0.id'));

        // Filter is_read=false
        $resUnread = $this->getJson('/api/v1/announcements?is_read=false');
        $resUnread->assertStatus(200);
        $this->assertCount(1, $resUnread->json('data'));
        $this->assertEquals($anc2->id, $resUnread->json('data.0.id'));
    }

    public function test_be_ntf_03_staff_can_view_readers_list_but_regular_student_cannot(): void
    {
        $announcement = Announcement::create([
            'author_id' => $this->adminUser->id,
            'title' => 'Survei Evaluasi Akhir Semester',
            'body' => 'Mohon mengisi formulir evaluasi.',
            'target_type' => 'semua',
            'publish_at' => Carbon::now()->subMinute(),
        ]);

        // Student 1 reads it
        Sanctum::actingAs($this->studentUser1);
        $this->postJson('/api/v1/announcements/'.$announcement->id.'/read')->assertStatus(200);

        // Regular student tries to view readers list -> 403
        $resStudentReaders = $this->getJson('/api/v1/announcements/'.$announcement->id.'/readers');
        $resStudentReaders->assertStatus(403);

        // Admin views readers list -> 200 with statistics & readers
        Sanctum::actingAs($this->adminUser);
        $resAdminReaders = $this->getJson('/api/v1/announcements/'.$announcement->id.'/readers');
        $resAdminReaders->assertStatus(200)
            ->assertJsonPath('data.total_readers', 1)
            ->assertJsonPath('data.readers.0.user_id', $this->studentUser1->id)
            ->assertJsonPath('data.readers.0.username', 'siswa_anc_1');
    }

    public function test_be_ntf_03_validation_rules_enforce_target_type_and_publish_dates(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Invalid target_type
        $resInvalidType = $this->postJson('/api/v1/announcements', [
            'title' => 'Uji Validasi',
            'body' => 'Konten uji',
            'target_type' => 'invalid_type',
        ]);
        $resInvalidType->assertStatus(422)
            ->assertJsonValidationErrors(['target_type']);

        // Invalid expires_at (earlier than publish_at)
        $resInvalidDate = $this->postJson('/api/v1/announcements', [
            'title' => 'Uji Tanggal Kedaluwarsa',
            'body' => 'Konten uji tanggal',
            'target_type' => 'semua',
            'publish_at' => Carbon::now()->addDays(5)->toIso8601String(),
            'expires_at' => Carbon::now()->addDays(2)->toIso8601String(),
        ]);
        $resInvalidDate->assertStatus(422)
            ->assertJsonValidationErrors(['expires_at']);
    }
}
