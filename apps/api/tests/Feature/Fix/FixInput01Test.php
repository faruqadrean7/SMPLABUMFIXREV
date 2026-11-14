<?php

namespace Tests\Feature\Fix;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * FIX-INPUT-01: Verifikasi perbaikan kegagalan simpan input form guru dan siswa.
 */
class FixInput01Test extends TestCase
{
    use RefreshDatabase;

    protected User $teacherUser;

    protected Teacher $teacher;

    protected SchoolClass $class;

    protected SchoolClass $class2;

    protected Subject $subject;

    protected Semester $semester;

    protected TimeSlot $slot1;

    protected TimeSlot $slot2;

    protected User $studentUser;

    protected Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $teacherRole = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $studentRole = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $ay = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $this->semester = Semester::create([
            'academic_year_id' => $ay->id,
            'name' => 'Ganjil',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $gradeLevel = GradeLevel::create([
            'name' => 'Kelas 8',
            'sort_order' => 8,
        ]);

        $this->class = SchoolClass::create([
            'academic_year_id' => $ay->id,
            'grade_level_id' => $gradeLevel->id,
            'name' => '8-A',
            'capacity' => 32,
        ]);

        $this->class2 = SchoolClass::create([
            'academic_year_id' => $ay->id,
            'grade_level_id' => $gradeLevel->id,
            'name' => '8-B',
            'capacity' => 32,
        ]);

        $this->subject = Subject::create([
            'code' => 'IPA-8',
            'name' => 'Ilmu Pengetahuan Alam',
            'subject_group' => 'wajib',
        ]);

        $this->teacherUser = User::create([
            'username' => 'guru.siti',
            'email' => 'guru.siti@labum.sch.id',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($teacherRole->id);

        $this->teacher = Teacher::create([
            'user_id' => $this->teacherUser->id,
            'nip' => '198701012010012001',
            'full_name' => 'Siti Aminah, M.Pd.',
            'status' => 'aktif',
        ]);

        // Teaching assignments for both classes
        TeachingAssignment::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'hours_per_week' => 4,
        ]);

        TeachingAssignment::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->class2->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'hours_per_week' => 4,
        ]);

        $this->slot1 = TimeSlot::create([
            'name' => 'Jam ke-1',
            'start_time' => '07:00',
            'end_time' => '08:20',
            'day_type' => 'reguler',
        ]);

        $this->slot2 = TimeSlot::create([
            'name' => 'Jam ke-2',
            'start_time' => '08:20',
            'end_time' => '09:40',
            'day_type' => 'reguler',
        ]);

        // Student setup
        $this->studentUser = User::create([
            'username' => 'siswa.budi',
            'email' => 'siswa.budi@labum.sch.id',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($studentRole->id);

        $this->student = Student::create([
            'user_id' => $this->studentUser->id,
            'nis' => '26101',
            'nisn' => '0099101001',
            'nik_enc' => '3578010101090101',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-08-10',
            'religion' => 'Islam',
            'address' => 'Jl. Veteran No. 1, Malang',
            'entry_date' => '2026-07-01',
            'full_name' => 'Budi Santoso',
            'status' => 'aktif',
        ]);
    }

    /**
     * AC1: Guru menyimpan jurnal tanpa schedule_id langsung mencari slot kosong tanpa clash SQL 23505.
     */
    public function test_fix_input_01_teacher_journal_auto_provisions_without_clashing(): void
    {
        $currentDay = now()->dayOfWeekIso;

        // Occupy slot1 on currentDay for class 1
        Schedule::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'time_slot_id' => $this->slot1->id,
            'day_of_week' => $currentDay,
        ]);

        // Now teacher creates journal for class 2 without schedule_id
        // Previously this crashed with SQL 23505 uq_schedule_teacher_clash because it blindly picked slot1!
        $res = $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/me/teacher/journals', [
                'class_id' => $this->class2->id,
                'subject_id' => $this->subject->id,
                'topic' => 'Sistem Ekskresi Manusia',
                'method' => 'Diskusi & Eksperimen',
                'notes' => 'Siswa antusias mempelajari organ ekskresi',
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.topic', 'Sistem Ekskresi Manusia');

        // Confirm new schedule was provisioned on slot2 (not colliding with slot1)
        $this->assertDatabaseHas('schedules', [
            'semester_id' => $this->semester->id,
            'class_id' => $this->class2->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'time_slot_id' => $this->slot2->id,
        ]);

        // Confirm journal was created
        $this->assertDatabaseHas('journals', [
            'teacher_id' => $this->teacher->id,
            'topic' => 'Sistem Ekskresi Manusia',
        ]);
    }

    /**
     * AC2: Permohonan konseling siswa menerima field notes / topic sebagai fallback reason.
     */
    public function test_fix_input_01_student_counseling_accepts_notes_as_reason_fallback(): void
    {
        $res = $this->actingAs($this->studentUser)
            ->postJson('/api/v1/me/counseling-requests', [
                'topic' => 'Konsultasi Minat Bakat SMA',
                'category' => 'Pengembangan Diri',
                'preferred_date' => 'Tanggal fleksibel',
                'preferred_time' => '10:00 - 11:00 WIB',
                'notes' => 'Ingin diskusi penjurusan IPA atau IPS setelah lulus SMP.',
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.topic', 'Konsultasi Minat Bakat SMA');

        $this->assertDatabaseHas('counseling_requests', [
            'student_id' => $this->student->id,
            'topic' => 'Konsultasi Minat Bakat SMA',
            'notes' => 'Ingin diskusi penjurusan IPA atau IPS setelah lulus SMP.',
            'status' => 'pending',
        ]);
    }

    /**
     * AC2: Validasi gagal 422 bila topic dan notes / reason sama sekali kosong.
     */
    public function test_fix_input_01_student_counseling_validation_failure_when_empty(): void
    {
        $res = $this->actingAs($this->studentUser)
            ->postJson('/api/v1/me/counseling-requests', [
                'topic' => '',
                'notes' => '',
            ]);

        $res->assertStatus(422);
    }

    /**
     * AC2: Permohonan konseling tanpa otentikasi ditolak 401.
     */
    public function test_fix_input_01_student_counseling_unauthenticated_rejected(): void
    {
        $res = $this->postJson('/api/v1/me/counseling-requests', [
            'topic' => 'Konsultasi',
            'notes' => 'Catatan',
        ]);

        $res->assertStatus(401);
    }
}
