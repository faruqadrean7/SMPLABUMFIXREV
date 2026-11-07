<?php

namespace Tests\Feature\Portal;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentProgramProfile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentProgramProfileTest extends TestCase
{
    use RefreshDatabase;

    protected AcademicYear $academicYear;
    protected GradeLevel $gradeLevel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->academicYear = AcademicYear::create([
            'name' => '2024/2025',
            'start_date' => '2024-07-15',
            'end_date' => '2025-06-20',
            'is_active' => true,
        ]);

        $this->gradeLevel = GradeLevel::create([
            'name' => 'Kelas VII',
            'sort_order' => 1,
        ]);
    }

    private function createStudent(string $programType = 'reguler', ?string $nis = null): Student
    {
        $unique = $nis ?? (string) rand(10000, 99999);
        $user = User::create([
            'username' => 'siswa_' . $unique,
            'email' => 'siswa_' . $unique . '@labschool.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);

        return Student::create([
            'user_id' => $user->id,
            'nis' => $unique,
            'nisn' => '00' . $unique,
            'nik_enc' => '35730101' . str_pad($unique, 8, '0', STR_PAD_LEFT),
            'full_name' => 'Siswa ' . ucfirst($programType) . ' ' . $unique,
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-05-10',
            'status' => 'aktif',
            'entry_date' => '2024-07-15',
            'program_type' => $programType,
        ]);
    }

    /**
     * BE-PORTAL-STUDENT-02A: Siswa dapat dibuat dengan 4 jenis program belajar resmi.
     */
    public function test_can_create_student_with_all_valid_program_types(): void
    {
        $programs = ['reguler', 'tahfidz', 'icp', 'inklusi'];

        foreach ($programs as $prog) {
            $student = $this->createStudent($prog);
            $this->assertDatabaseHas('students', [
                'id' => $student->id,
                'program_type' => $prog,
            ]);
            $this->assertEquals($prog, $student->fresh()->program_type);
        }
    }

    /**
     * BE-PORTAL-STUDENT-02A: Nilai default program_type pada student adalah reguler.
     */
    public function test_default_student_program_type_is_reguler(): void
    {
        $unique = (string) rand(10000, 99999);
        $user = User::create([
            'username' => 'siswa_def_' . $unique,
            'email' => 'siswa_def_' . $unique . '@labschool.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'nis' => $unique,
            'nisn' => '00' . $unique,
            'nik_enc' => '35730101' . str_pad($unique, 8, '0', STR_PAD_LEFT),
            'full_name' => 'Siswa Default ' . $unique,
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-05-10',
            'status' => 'aktif',
            'entry_date' => '2024-07-15',
        ]);

        $this->assertEquals('reguler', $student->fresh()->program_type);
    }

    /**
     * BE-PORTAL-STUDENT-02A: Kelas dapat memiliki atribut program_type.
     */
    public function test_can_create_class_with_program_type(): void
    {
        $class = SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->gradeLevel->id,
            'name' => 'VII-Tahfidz-1',
            'capacity' => 30,
            'program_type' => 'tahfidz',
        ]);

        $this->assertDatabaseHas('classes', [
            'id' => $class->id,
            'program_type' => 'tahfidz',
        ]);
        $this->assertEquals('tahfidz', $class->fresh()->program_type);
    }

    /**
     * BE-PORTAL-STUDENT-02A: StudentProgramProfile dapat disimpan dan berelasi dengan Student.
     */
    public function test_student_program_profile_association_and_meta_data(): void
    {
        $student = $this->createStudent('tahfidz');

        $profile = StudentProgramProfile::create([
            'student_id' => $student->id,
            'program_type' => 'tahfidz',
            'meta_data' => [
                'target_juz' => 3,
                'current_juz' => 2,
                'current_surah' => 'Al-Mulk: 1-30',
                'last_tasmi_date' => '2026-10-28',
                'last_tasmi_grade' => 'Mumtaz',
                'tasmi_evaluator' => 'Ustadz Ahmad, S.Pd.I',
                'mutabaah_today' => [
                    'sholat_fardhu' => 5,
                    'tilawah_pages' => 4,
                    'dhuha' => true,
                    'tahajud' => false,
                ],
            ],
        ]);

        $this->assertDatabaseHas('student_program_profiles', [
            'id' => $profile->id,
            'student_id' => $student->id,
            'program_type' => 'tahfidz',
        ]);

        // Verifikasi relasi
        $freshStudent = $student->fresh(['programProfile']);
        $this->assertNotNull($freshStudent->programProfile);
        $this->assertEquals('tahfidz', $freshStudent->programProfile->program_type);
        $this->assertIsArray($freshStudent->programProfile->meta_data);
        $this->assertEquals(3, $freshStudent->programProfile->meta_data['target_juz']);
        $this->assertEquals('Mumtaz', $freshStudent->programProfile->meta_data['last_tasmi_grade']);
    }

    /**
     * BE-PORTAL-STUDENT-02A: Check constraint menolak program_type tidak valid.
     */
    public function test_invalid_program_type_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        $unique = (string) rand(10000, 99999);
        $user = User::create([
            'username' => 'siswa_inv_' . $unique,
            'email' => 'siswa_inv_' . $unique . '@labschool.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);

        Student::create([
            'user_id' => $user->id,
            'nis' => $unique,
            'nisn' => '00' . $unique,
            'nik_enc' => '35730101' . str_pad($unique, 8, '0', STR_PAD_LEFT),
            'full_name' => 'Siswa Invalid',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-05-10',
            'status' => 'aktif',
            'entry_date' => '2024-07-15',
            'program_type' => 'program_fiktif',
        ]);
    }
}
