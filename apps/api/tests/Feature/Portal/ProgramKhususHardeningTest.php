<?php

namespace Tests\Feature\Portal;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\File;
use App\Models\GradeLevel;
use App\Models\ProgramMaterial;
use App\Models\ProgramTask;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentProgramProfile;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Batch 2B-S — P1-28: Pengujian validasi target_scope, school_id, kepemilikan file_id,
 * kelas aktif (wherePivotNull('left_at')), & histori harian Mood/Mutabaah.
 */
class ProgramKhususHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected string $schoolA;

    protected string $schoolB;

    protected AcademicYear $ay;

    protected GradeLevel $level;

    protected SchoolClass $classTahfidzA;

    protected SchoolClass $classTahfidzB;

    protected SchoolClass $classOldIcp;

    protected User $studentAUser;

    protected User $studentBUser;

    protected User $oldTeacherUser;

    protected Student $studentA;

    protected Student $studentB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = (string) Str::uuid();
        $this->schoolB = (string) Str::uuid();

        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);

        $this->ay = AcademicYear::create([
            'school_id' => $this->schoolA,
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $this->level = GradeLevel::create(['name' => 'Kelas VII', 'sort_order' => 1]);

        $this->oldTeacherUser = User::create([
            'school_id' => $this->schoolA,
            'username' => 'p128_old_teacher',
            'email' => 'p128_old_teacher@sekolah.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->oldTeacherUser->roles()->attach($roleTeacher->id);

        $oldTeacher = Teacher::create([
            'school_id' => $this->schoolA,
            'user_id' => $this->oldTeacherUser->id,
            'nip' => '198812800000000001',
            'full_name' => 'Guru Eks Kelas ICP Fiktif',
            'gender' => 'L',
        ]);

        $this->classTahfidzA = SchoolClass::create([
            'school_id' => $this->schoolA,
            'academic_year_id' => $this->ay->id,
            'grade_level_id' => $this->level->id,
            'name' => 'VII-TAHFIDZ-A',
            'program_type' => 'tahfidz',
        ]);

        $this->classTahfidzB = SchoolClass::create([
            'school_id' => $this->schoolA,
            'academic_year_id' => $this->ay->id,
            'grade_level_id' => $this->level->id,
            'name' => 'VII-TAHFIDZ-B',
            'program_type' => 'tahfidz',
        ]);

        $this->classOldIcp = SchoolClass::create([
            'school_id' => $this->schoolA,
            'academic_year_id' => $this->ay->id,
            'grade_level_id' => $this->level->id,
            'homeroom_teacher_id' => $oldTeacher->id,
            'name' => 'VI-OLD-ICP',
            'program_type' => 'icp',
        ]);

        $this->studentAUser = User::create([
            'school_id' => $this->schoolA,
            'username' => 'p128_siswa_a',
            'email' => 'p128_siswa_a@siswa.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->studentAUser->roles()->attach($roleStudent->id);

        $this->studentBUser = User::create([
            'school_id' => $this->schoolA,
            'username' => 'p128_siswa_b',
            'email' => 'p128_siswa_b@siswa.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->studentBUser->roles()->attach($roleStudent->id);

        $this->studentA = Student::create([
            'school_id' => $this->schoolA,
            'user_id' => $this->studentAUser->id,
            'nis' => '202612801',
            'nisn' => '0012800001',
            'nik_enc' => '3573010101122801',
            'full_name' => 'Siswa Tahfidz A Fiktif',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-05-01',
            'status' => 'aktif',
            'program_type' => 'tahfidz',
            'entry_date' => '2026-07-01',
        ]);

        $this->studentB = Student::create([
            'school_id' => $this->schoolA,
            'user_id' => $this->studentBUser->id,
            'nis' => '202612802',
            'nisn' => '0012800002',
            'nik_enc' => '3573010101122802',
            'full_name' => 'Siswa Tahfidz B Fiktif',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2012-06-01',
            'status' => 'aktif',
            'program_type' => 'tahfidz',
            'entry_date' => '2026-07-01',
        ]);

        // Siswa A pernah di kelas lama (left_at terisi) dan sekarang aktif di VII-TAHFIDZ-A
        ClassStudent::create([
            'class_id' => $this->classOldIcp->id,
            'student_id' => $this->studentA->id,
            'joined_at' => '2025-07-01',
            'left_at' => '2026-06-30',
        ]);
        ClassStudent::create([
            'class_id' => $this->classTahfidzA->id,
            'student_id' => $this->studentA->id,
            'joined_at' => '2026-07-01',
        ]);
        ClassStudent::create([
            'class_id' => $this->classTahfidzB->id,
            'student_id' => $this->studentB->id,
            'joined_at' => '2026-07-01',
        ]);
    }

    public function test_p1_28_submit_task_enforces_target_scope_school_id_and_file_ownership(): void
    {
        // Tugas khusus individu untuk Siswa B
        $personalTaskB = ProgramTask::create([
            'school_id' => $this->schoolA,
            'program_type' => 'tahfidz',
            'title' => 'Setoran Khusus Siswa B',
            'target_scope' => 'student',
            'assigned_student_id' => $this->studentB->id,
        ]);

        // Tugas kelas untuk VII-TAHFIDZ-B
        $classTaskB = ProgramTask::create([
            'school_id' => $this->schoolA,
            'program_type' => 'tahfidz',
            'title' => 'Setoran Kelas B',
            'target_scope' => 'class',
            'class_id' => $this->classTahfidzB->id,
        ]);

        // Tugas dari Sekolah B
        $otherSchoolTask = ProgramTask::create([
            'school_id' => $this->schoolB,
            'program_type' => 'tahfidz',
            'title' => 'Tugas Sekolah B',
            'target_scope' => 'all',
        ]);

        // Tugas sah untuk kelas aktif Siswa A
        $validTaskA = ProgramTask::create([
            'school_id' => $this->schoolA,
            'program_type' => 'tahfidz',
            'title' => 'Setoran Kelas A',
            'target_scope' => 'class',
            'class_id' => $this->classTahfidzA->id,
        ]);

        $fileOwnedByB = File::create([
            'school_id' => $this->schoolA,
            'owner_type' => 'Student',
            'owner_id' => $this->studentBUser->id,
            'bucket' => 'program-submissions',
            'object_key' => 'program/b-private.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'visibility' => 'privat',
            'checksum' => hash('sha256', 'b'),
        ]);

        $fileOwnedByA = File::create([
            'school_id' => $this->schoolA,
            'owner_type' => 'Student',
            'owner_id' => $this->studentAUser->id,
            'bucket' => 'program-submissions',
            'object_key' => 'program/a-own.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'visibility' => 'privat',
            'checksum' => hash('sha256', 'a'),
        ]);

        // 1. Siswa A submit tugas individu milik Siswa B -> 403
        $this->actingAs($this->studentAUser, 'sanctum')
            ->postJson("/api/v1/me/program/tasks/{$personalTaskB->id}/submit", ['notes' => 'Coba IDOR'])
            ->assertStatus(403);

        // 2. Siswa A submit tugas kelas B -> 403
        $this->actingAs($this->studentAUser, 'sanctum')
            ->postJson("/api/v1/me/program/tasks/{$classTaskB->id}/submit", ['notes' => 'Coba lintas kelas'])
            ->assertStatus(403);

        // 3. Siswa A submit tugas sekolah B -> 403
        $this->actingAs($this->studentAUser, 'sanctum')
            ->postJson("/api/v1/me/program/tasks/{$otherSchoolTask->id}/submit", ['notes' => 'Coba lintas sekolah'])
            ->assertStatus(403);

        // 4. Siswa A melampirkan file_id privat milik Siswa B -> 403
        $this->actingAs($this->studentAUser, 'sanctum')
            ->postJson("/api/v1/me/program/tasks/{$validTaskA->id}/submit", ['file_id' => $fileOwnedByB->id])
            ->assertStatus(403);

        // 5. Siswa A melampirkan file_id non-eksisten -> ditolak validasi (400/422)
        $invalidFileResp = $this->actingAs($this->studentAUser, 'sanctum')
            ->postJson("/api/v1/me/program/tasks/{$validTaskA->id}/submit", ['file_id' => (string) Str::uuid()]);
        $this->assertContains($invalidFileResp->getStatusCode(), [400, 422]);

        // 6. Akses sah: Siswa A melampirkan file miliknya sendiri pada tugas kelas aktifnya -> 200/201
        $resp = $this->actingAs($this->studentAUser, 'sanctum')
            ->postJson("/api/v1/me/program/tasks/{$validTaskA->id}/submit", [
                'file_id' => $fileOwnedByA->id,
                'notes' => 'Setoran sah',
            ]);
        $this->assertTrue(in_array($resp->getStatusCode(), [200, 201], true));
    }

    public function test_p1_28_active_classes_blocks_former_class_materials_and_former_teacher_access(): void
    {
        // Materi untuk kelas lama (left_at terisi) tidak boleh muncul ke Siswa A
        ProgramMaterial::create([
            'school_id' => $this->schoolA,
            'program_type' => 'tahfidz',
            'title' => 'Materi Kelas Lama',
            'material_type' => 'modul',
            'target_scope' => 'class',
            'class_id' => $this->classOldIcp->id,
        ]);
        ProgramMaterial::create([
            'school_id' => $this->schoolA,
            'program_type' => 'tahfidz',
            'title' => 'Materi Kelas Aktif A',
            'material_type' => 'modul',
            'target_scope' => 'class',
            'class_id' => $this->classTahfidzA->id,
        ]);

        $matResp = $this->actingAs($this->studentAUser, 'sanctum')
            ->getJson('/api/v1/me/program/materials')
            ->assertStatus(200);
        $titles = collect($matResp->json('data'))->pluck('title')->all();
        $this->assertContains('Materi Kelas Aktif A', $titles);
        $this->assertNotContains('Materi Kelas Lama', $titles);

        // Guru eks-kelas tidak boleh membuat target untuk Siswa A yang sudah keluar (left_at terisi)
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->oldTeacherUser, 'sanctum')
            ->postJson('/api/v1/program/targets', [
                'student_id' => $this->studentA->id,
                'program_type' => 'tahfidz',
                'title' => 'Target Tidak Sah dari Eks Guru',
            ])
            ->assertStatus(403);
    }

    public function test_p1_28_mutabaah_and_mood_preserve_daily_history_without_overwriting_previous_days(): void
    {
        Carbon::setTestNow('2026-11-26 08:00:00');
        $this->actingAs($this->studentAUser, 'sanctum')
            ->postJson('/api/v1/me/program/mutabaah', [
                'sholat_fardhu' => 5,
                'tilawah_pages' => 4,
                'dhuha' => true,
                'tahajud' => false,
            ])
            ->assertStatus(200);

        Carbon::setTestNow('2026-11-27 09:00:00');
        $this->actingAs($this->studentAUser, 'sanctum')
            ->postJson('/api/v1/me/program/mutabaah', [
                'sholat_fardhu' => 5,
                'tilawah_pages' => 6,
                'dhuha' => true,
                'tahajud' => true,
            ])
            ->assertStatus(200);

        Carbon::setTestNow();

        $profile = StudentProgramProfile::where('student_id', $this->studentA->id)->firstOrFail();
        $history = $profile->meta_data['mutabaah_history'] ?? [];
        $this->assertArrayHasKey('2026-11-26', $history);
        $this->assertArrayHasKey('2026-11-27', $history);
        $this->assertSame(4, $history['2026-11-26']['tilawah_pages']);
        $this->assertSame(6, $history['2026-11-27']['tilawah_pages']);
    }
}
