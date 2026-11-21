<?php

namespace Tests\Feature\Cbt;

use App\Models\Exam;
use App\Models\Role;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\CbtStagingLoadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CbtLoadCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_dry_run_does_not_delete_any_records(): void
    {
        $this->seed(CbtStagingLoadSeeder::class);

        $examCountBefore = Exam::count();
        $studentCountBefore = Student::count();

        $this->artisan('cbt:load-cleanup', ['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN: No records were modified or deleted.')
            ->assertSuccessful();

        $this->assertEquals($examCountBefore, Exam::count());
        $this->assertEquals($studentCountBefore, Student::count());
    }

    public function test_safety_guard_blocks_execution_in_production_environment(): void
    {
        $this->seed(CbtStagingLoadSeeder::class);

        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('cbt:load-cleanup', ['--force' => true])
            ->expectsOutputToContain('CRITICAL SAFETY VIOLATION: cbt:load-cleanup is strictly forbidden in the production environment!')
            ->assertFailed();

        $this->assertDatabaseHas('exams', [
            'title' => 'Ujian Biologi Uji Beban 500 Siswa',
        ]);
    }

    public function test_force_cleanup_removes_staging_data_while_preserving_non_test_data(): void
    {
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        // Non-test production-like records
        $realSubject = Subject::create([
            'code' => 'KIM-PROD',
            'name' => 'Kimia Produksi',
            'subject_group' => 'umum',
        ]);

        $realUser = User::create([
            'username' => 'budi_santoso',
            'email' => 'budi.santoso@labschool.sch.id',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $realUser->roles()->attach($roleStudent->id);

        $realStudent = Student::create([
            'user_id' => $realUser->id,
            'nisn' => '9988776655',
            'nis' => '12345',
            'nik_enc' => '3578019988776655',
            'full_name' => 'Budi Santoso Siswa Resmi',
            'gender' => 'L',
            'birth_place' => 'Surabaya',
            'birth_date' => '2008-05-10',
            'status' => 'aktif',
            'entry_date' => '2024-07-15',
        ]);

        $realExam = Exam::create([
            'title' => 'Ujian Akhir Semester Kimia Resmi',
            'subject_id' => $realSubject->id,
            'created_by' => $realUser->id,
            'exam_type' => 'ujian_akhir',
            'duration_min' => 90,
            'start_at' => now(),
            'end_at' => now()->addDays(2),
            'status' => 'aktif',
            'passing_grade' => 75.0,
        ]);

        // Seed staging load test data
        $this->seed(CbtStagingLoadSeeder::class);

        // Execute force cleanup
        $this->artisan('cbt:load-cleanup', ['--force' => true])
            ->expectsOutputToContain('CBT staging load test records cleaned up successfully.')
            ->assertSuccessful();

        // Assert staging records are gone
        $this->assertDatabaseMissing('exams', [
            'title' => 'Ujian Biologi Uji Beban 500 Siswa',
        ]);
        $this->assertDatabaseMissing('subjects', [
            'code' => 'BIO-LOAD',
        ]);
        $this->assertDatabaseMissing('students', [
            'full_name' => 'Siswa Uji Beban 1',
        ]);
        $this->assertDatabaseMissing('users', [
            'username' => 'student1',
        ]);

        // Assert non-test data remains 100% intact
        $this->assertDatabaseHas('subjects', [
            'id' => $realSubject->id,
            'code' => 'KIM-PROD',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $realUser->id,
            'username' => 'budi_santoso',
            'email' => 'budi.santoso@labschool.sch.id',
        ]);
        $this->assertDatabaseHas('students', [
            'id' => $realStudent->id,
            'full_name' => 'Budi Santoso Siswa Resmi',
        ]);
        $this->assertDatabaseHas('exams', [
            'id' => $realExam->id,
            'title' => 'Ujian Akhir Semester Kimia Resmi',
        ]);
    }

    public function test_cleanup_preserves_non_test_students_with_similar_names_or_emails(): void
    {
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        // Akun non-pengujian dengan pola email mirip
        $similarUser1 = User::create([
            'username' => 'student_pres',
            'email' => 'student.president@labschool.sch.id',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $similarUser1->roles()->attach($roleStudent->id);

        $similarStudent1 = Student::create([
            'user_id' => $similarUser1->id,
            'nisn' => '1111222233',
            'nis' => '9901',
            'nik_enc' => '3578011111222233',
            'full_name' => 'Siswa Presiden OSIS Resmi',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2008-01-01',
            'status' => 'aktif',
            'entry_date' => '2024-07-15',
        ]);

        $similarUser2 = User::create([
            'username' => 'student1000',
            'email' => 'student1000@labschool.sch.id',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $similarUser2->roles()->attach($roleStudent->id);

        $similarStudent2 = Student::create([
            'user_id' => $similarUser2->id,
            'nisn' => '4444555566',
            'nis' => '9902',
            'nik_enc' => '3578014444555566',
            'full_name' => 'Siswa Nomor Seribu Resmi',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2008-02-02',
            'status' => 'aktif',
            'entry_date' => '2024-07-15',
        ]);

        // Seed staging load test data
        $this->seed(CbtStagingLoadSeeder::class);

        // Eksekusi pembersihan
        $this->artisan('cbt:load-cleanup', ['--force' => true])
            ->assertSuccessful();

        // Pastikan akun siswa resmi yang mirip TIDAK terhapus
        $this->assertDatabaseHas('users', ['id' => $similarUser1->id, 'email' => 'student.president@labschool.sch.id']);
        $this->assertDatabaseHas('students', ['id' => $similarStudent1->id, 'full_name' => 'Siswa Presiden OSIS Resmi']);
        $this->assertDatabaseHas('users', ['id' => $similarUser2->id, 'email' => 'student1000@labschool.sch.id']);
        $this->assertDatabaseHas('students', ['id' => $similarStudent2->id, 'full_name' => 'Siswa Nomor Seribu Resmi']);

        // Pastikan data load test tetap terhapus
        $this->assertDatabaseMissing('users', ['username' => 'student1']);
        $this->assertDatabaseMissing('students', ['full_name' => 'Siswa Uji Beban 1']);
    }
}
