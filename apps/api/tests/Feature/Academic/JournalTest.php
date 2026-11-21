<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\GradeLevel;
use App\Models\Journal;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\SiteSetting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class JournalTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $teacher1User;

    protected User $teacher2User;

    protected Teacher $teacher1;

    protected Teacher $teacher2;

    protected Schedule $schedule1;

    protected Schedule $schedule2;

    protected SchoolClass $classA;

    protected Subject $subjectMath;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Roles & Permissions
        $roleAdmin = Role::firstOrCreate(['code' => 'admin_kurikulum'], ['name' => 'Admin Kurikulum', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);

        $permScheduleManage = Permission::firstOrCreate(['code' => 'academic.schedule.manage'], ['description' => 'Mengelola jadwal pelajaran']);
        $permJournalRead = Permission::firstOrCreate(['code' => 'academic.journal.read'], ['description' => 'Melihat jurnal mengajar']);
        $permJournalWrite = Permission::firstOrCreate(['code' => 'academic.journal.write'], ['description' => 'Mengisi jurnal mengajar']);

        $roleAdmin->permissions()->syncWithoutDetaching([$permScheduleManage->id, $permJournalRead->id, $permJournalWrite->id]);
        $roleTeacher->permissions()->syncWithoutDetaching([$permJournalRead->id, $permJournalWrite->id]);

        // 2. Users
        $this->adminUser = User::create([
            'username' => 'adminkurikulum',
            'email' => 'kurikulum@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->teacher1User = User::create([
            'username' => 'gurubudi',
            'email' => 'budi@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacher1User->roles()->attach($roleTeacher->id);

        $this->teacher2User = User::create([
            'username' => 'gurusiti',
            'email' => 'siti@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacher2User->roles()->attach($roleTeacher->id);

        // 3. Teachers linked to users
        $this->teacher1 = Teacher::create([
            'user_id' => $this->teacher1User->id,
            'nip' => '198001012005011001',
            'full_name' => 'Budi Santoso, M.Pd.',
            'status' => 'tetap',
        ]);

        $this->teacher2 = Teacher::create([
            'user_id' => $this->teacher2User->id,
            'nip' => '198502022006022002',
            'full_name' => 'Siti Aminah, S.Pd.',
            'status' => 'tetap',
        ]);

        // 4. Academic Master
        $academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-15',
            'end_date' => '2027-06-20',
            'is_active' => true,
        ]);

        $semester = Semester::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'Ganjil',
            'semester_type' => 'ganjil',
            'start_date' => '2026-07-15',
            'end_date' => '2026-12-20',
            'is_active' => true,
        ]);

        $gradeLevel = GradeLevel::create([
            'name' => 'Kelas 10',
            'sort_order' => 10,
        ]);

        $this->classA = SchoolClass::create([
            'academic_year_id' => $academicYear->id,
            'grade_level_id' => $gradeLevel->id,
            'name' => 'X-A',
            'capacity' => 36,
        ]);

        $this->subjectMath = Subject::create([
            'code' => 'MAT-10',
            'name' => 'Matematika Wajib',
            'subject_group' => 'umum',
        ]);

        $room = Room::create([
            'code' => 'R101',
            'name' => 'Ruang 101',
            'room_type' => 'kelas',
            'capacity' => 36,
        ]);

        $slot1 = TimeSlot::create([
            'name' => 'Jam ke-1',
            'start_time' => '07:00',
            'end_time' => '07:45',
            'day_type' => 'reguler',
        ]);

        $slot2 = TimeSlot::create([
            'name' => 'Jam ke-2',
            'start_time' => '07:45',
            'end_time' => '08:30',
            'day_type' => 'reguler',
        ]);

        // 5. Schedules
        $this->schedule1 = Schedule::create([
            'semester_id' => $semester->id,
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'room_id' => $room->id,
            'time_slot_id' => $slot1->id,
            'day_of_week' => 1,
        ]);

        $this->schedule2 = Schedule::create([
            'semester_id' => $semester->id,
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher2->id,
            'room_id' => $room->id,
            'time_slot_id' => $slot2->id,
            'day_of_week' => 1,
        ]);
    }

    public function test_be_acd_02_assigned_teacher_can_create_journal(): void
    {
        $token = $this->teacher1User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/journals', [
                'schedule_id' => $this->schedule1->id,
                'session_date' => Carbon::now()->format('Y-m-d'),
                'topic' => 'Sistem Persamaan Linier Tiga Variabel (SPLTV)',
                'method' => 'Problem Based Learning & Diskusi Kelompok',
                'notes' => 'Peserta didik antusias menyelesaikan soal cerita konseptual.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.schedule_id', $this->schedule1->id)
            ->assertJsonPath('data.teacher_id', $this->teacher1->id)
            ->assertJsonPath('data.topic', 'Sistem Persamaan Linier Tiga Variabel (SPLTV)');

        $this->assertDatabaseHas('journals', [
            'schedule_id' => $this->schedule1->id,
            'teacher_id' => $this->teacher1->id,
            'topic' => 'Sistem Persamaan Linier Tiga Variabel (SPLTV)',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'journal.created',
            'entity' => 'Journal',
        ]);
    }

    public function test_be_acd_02_other_teacher_cannot_create_journal_for_unassigned_schedule_403(): void
    {
        // Teacher 2 tries to create journal for Schedule 1 (assigned to Teacher 1)
        $token = $this->teacher2User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/journals', [
                'schedule_id' => $this->schedule1->id,
                'session_date' => Carbon::now()->format('Y-m-d'),
                'topic' => 'Materi Tidak Sah',
                'method' => 'Ceramah',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_be_acd_02_admin_can_create_journal_for_any_schedule(): void
    {
        $token = $this->adminUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/journals', [
                'schedule_id' => $this->schedule1->id,
                'session_date' => Carbon::now()->format('Y-m-d'),
                'topic' => 'Diinput oleh Admin Kurikulum Pengganti',
                'method' => 'Asesmen Mandiri',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.schedule_id', $this->schedule1->id);
    }

    public function test_be_acd_02_cannot_create_duplicate_journal_for_same_schedule_and_date(): void
    {
        $today = Carbon::now()->format('Y-m-d');

        // First journal created
        Journal::create([
            'schedule_id' => $this->schedule1->id,
            'teacher_id' => $this->teacher1->id,
            'session_date' => $today,
            'topic' => 'Topik Pertama',
            'method' => 'Diskusi',
        ]);

        $token = $this->teacher1User->createToken('test')->plainTextToken;

        // Second journal for same schedule and date fails
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/journals', [
                'schedule_id' => $this->schedule1->id,
                'session_date' => $today,
                'topic' => 'Topik Kedua (Duplikat)',
                'method' => 'Ceramah',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'UNPROCESSABLE_ENTITY');
    }

    public function test_be_acd_02_teacher_can_update_journal_within_allowed_days(): void
    {
        // Journal created today (within default 3 days)
        $journal = Journal::create([
            'schedule_id' => $this->schedule1->id,
            'teacher_id' => $this->teacher1->id,
            'session_date' => Carbon::now()->format('Y-m-d'),
            'topic' => 'Topik Awal',
            'method' => 'Diskusi',
        ]);

        $token = $this->teacher1User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/journals/{$journal->id}", [
                'topic' => 'Topik Revisi Terbaru',
                'notes' => 'Catatan revisi tambahan.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.topic', 'Topik Revisi Terbaru');

        $this->assertDatabaseHas('journals', [
            'id' => $journal->id,
            'topic' => 'Topik Revisi Terbaru',
        ]);
    }

    public function test_be_acd_02_teacher_cannot_update_journal_after_allowed_days_expired_422(): void
    {
        // Journal with session date 5 days ago (default limit is 3 days)
        $journal = Journal::create([
            'schedule_id' => $this->schedule1->id,
            'teacher_id' => $this->teacher1->id,
            'session_date' => Carbon::now()->subDays(5)->format('Y-m-d'),
            'topic' => 'Topik Lampau',
            'method' => 'Ceramah',
        ]);

        $token = $this->teacher1User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/journals/{$journal->id}", [
                'topic' => 'Mencoba Update Kedaluwarsa',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'UNPROCESSABLE_ENTITY');
    }

    public function test_be_acd_02_admin_can_override_and_update_expired_journal(): void
    {
        // Journal 10 days ago
        $journal = Journal::create([
            'schedule_id' => $this->schedule1->id,
            'teacher_id' => $this->teacher1->id,
            'session_date' => Carbon::now()->subDays(10)->format('Y-m-d'),
            'topic' => 'Topik Sangat Lampau',
            'method' => 'Diskusi',
        ]);

        $token = $this->adminUser->createToken('test')->plainTextToken;

        // Admin override succeeds
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/journals/{$journal->id}", [
                'topic' => 'Diperbarui oleh Kurikulum dengan Izin Khusus',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.topic', 'Diperbarui oleh Kurikulum dengan Izin Khusus');
    }

    public function test_be_acd_02_respects_custom_site_setting_for_max_edit_days(): void
    {
        // Set setting to 14 days
        SiteSetting::create([
            'setting_key' => 'journal_edit_max_days',
            'setting_value' => ['days' => 14],
        ]);

        // Journal 7 days ago (passes because maxDays is 14)
        $journal = Journal::create([
            'schedule_id' => $this->schedule1->id,
            'teacher_id' => $this->teacher1->id,
            'session_date' => Carbon::now()->subDays(7)->format('Y-m-d'),
            'topic' => 'Topik 7 Hari Lalu',
            'method' => 'Diskusi',
        ]);

        $token = $this->teacher1User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/journals/{$journal->id}", [
                'topic' => 'Topik Diperbarui Berkat Setting 14 Hari',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.topic', 'Topik Diperbarui Berkat Setting 14 Hari');
    }

    public function test_be_acd_02_teacher_can_view_their_journals_and_detail_with_attendances(): void
    {
        $journal = Journal::create([
            'schedule_id' => $this->schedule1->id,
            'teacher_id' => $this->teacher1->id,
            'session_date' => Carbon::now()->format('Y-m-d'),
            'topic' => 'Pembahasan Matriks',
            'method' => 'Eksplorasi',
        ]);

        // Create student and attendance
        $studentUser = User::create([
            'username' => 'siswa1',
            'email' => 'siswa1@sekolah.sch.id',
            'password_hash' => Hash::make('secret'),
            'status' => 'active',
        ]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'nis' => '202600001',
            'nisn' => '0012345678',
            'nik_enc' => 'enc_3578010101010001',
            'full_name' => 'Aditya Pratama',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2008-05-15',
            'status' => 'aktif',
            'entry_date' => '2026-07-15',
        ]);

        Attendance::create([
            'journal_id' => $journal->id,
            'student_id' => $student->id,
            'att_date' => Carbon::now()->format('Y-m-d'),
            'status' => 'hadir',
            'source' => 'jurnal',
        ]);

        $token = $this->teacher1User->createToken('test')->plainTextToken;

        // Detail endpoint
        $resDetail = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/journals/{$journal->id}");

        $resDetail->assertStatus(200)
            ->assertJsonPath('data.id', $journal->id)
            ->assertJsonPath('data.topic', 'Pembahasan Matriks')
            ->assertJsonPath('data.attendances.0.student.full_name', 'Aditya Pratama');
    }

    public function test_be_acd_02_filter_journals_by_class_subject_and_date_range(): void
    {
        Journal::create([
            'schedule_id' => $this->schedule1->id,
            'teacher_id' => $this->teacher1->id,
            'session_date' => '2026-08-01',
            'topic' => 'Sesi Agustus',
            'method' => 'Diskusi',
        ]);

        Journal::create([
            'schedule_id' => $this->schedule1->id,
            'teacher_id' => $this->teacher1->id,
            'session_date' => '2026-09-01',
            'topic' => 'Sesi September',
            'method' => 'Diskusi',
        ]);

        $token = $this->teacher1User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/journals?start_date=2026-08-01&end_date=2026-08-15');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Sesi Agustus', $response->json('data.0.topic'));
    }

    public function test_be_acd_02_teacher_can_delete_journal_within_allowed_days(): void
    {
        $journal = Journal::create([
            'schedule_id' => $this->schedule1->id,
            'teacher_id' => $this->teacher1->id,
            'session_date' => Carbon::now()->format('Y-m-d'),
            'topic' => 'Jurnal untuk Dihapus',
            'method' => 'Ceramah',
        ]);

        $token = $this->teacher1User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/journals/{$journal->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('journals', ['id' => $journal->id]);
    }

    public function test_be_acd_02_unauthorized_user_cannot_access_journals_403_or_401(): void
    {
        // 401 unauthenticated
        $this->getJson('/api/v1/journals')->assertStatus(401);

        // Regular user without permissions
        $guestRole = Role::firstOrCreate(['code' => 'guest'], ['name' => 'Guest']);
        $guestUser = User::create([
            'username' => 'tamu',
            'email' => 'tamu@sekolah.sch.id',
            'password_hash' => Hash::make('secret'),
            'status' => 'active',
        ]);
        $guestUser->roles()->attach($guestRole->id);

        $token = $guestUser->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/journals', [
                'schedule_id' => $this->schedule1->id,
                'session_date' => Carbon::now()->format('Y-m-d'),
                'topic' => 'Coba Ilegal',
                'method' => 'Ceramah',
            ])->assertStatus(403);
    }
}
