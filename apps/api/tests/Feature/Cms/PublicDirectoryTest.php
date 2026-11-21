<?php

namespace Tests\Feature\Cms;

use App\Models\AcademicYear;
use App\Models\File;
use App\Models\GradeLevel;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Staff;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PublicDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacherUser;

    protected File $photoFile;

    protected Semester $semester;

    protected SchoolClass $schoolClass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacherUser = User::create([
            'username' => 'guru_ahmad',
            'email' => 'ahmad.fauzi@labschool.sch.id',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);

        $this->photoFile = File::create([
            'bucket' => 'public-media',
            'object_key' => 'photos/teachers/ahmad.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 150000,
            'visibility' => 'publik',
        ]);

        $academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $this->semester = Semester::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'Ganjil 2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $gradeLevel = GradeLevel::create([
            'name' => 'Kelas X',
            'sort_order' => 1,
        ]);

        $this->schoolClass = SchoolClass::create([
            'academic_year_id' => $academicYear->id,
            'grade_level_id' => $gradeLevel->id,
            'name' => 'X-A',
            'capacity' => 36,
        ]);
    }

    /**
     * BE-CMS-05: Public can access teachers directory without authentication
     */
    public function test_be_cms_05_public_can_access_teachers_directory_without_auth(): void
    {
        Teacher::create([
            'full_name' => 'Dra. Hj. Siti Aminah, M.Pd.',
            'position' => 'Guru Bahasa Indonesia',
            'status' => 'aktif',
            'nip' => '197001011995012001',
            'nuptk' => '1122334455667788',
        ]);

        $response = $this->getJson('/api/v1/public/teachers');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.full_name', 'Dra. Hj. Siti Aminah, M.Pd.')
            ->assertJsonPath('data.0.position', 'Guru Bahasa Indonesia');
    }

    /**
     * BE-CMS-05: Sensitive fields (NIP, NUPTK, user_id, email, password) are NEVER exposed
     */
    public function test_be_cms_05_sensitive_fields_are_never_exposed_in_public_teacher_response(): void
    {
        $teacher = Teacher::create([
            'user_id' => $this->teacherUser->id,
            'nip' => '198505152010011012',
            'nuptk' => '9988776655443322',
            'full_name' => 'Ahmad Fauzi, S.Pd., M.Si.',
            'position' => 'Guru Matematika & Waka Kurikulum',
            'status' => 'aktif',
            'photo_file_id' => $this->photoFile->id,
        ]);

        $subject = Subject::create([
            'code' => 'MAT-WAJIB',
            'name' => 'Matematika Wajib',
            'subject_group' => 'umum',
        ]);

        TeachingAssignment::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->schoolClass->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'hours_per_week' => 4,
        ]);

        // 1. Check Index Response
        $indexResponse = $this->getJson('/api/v1/public/teachers');

        $indexResponse->assertStatus(200)
            ->assertJsonPath('data.0.id', $teacher->id)
            ->assertJsonPath('data.0.full_name', 'Ahmad Fauzi, S.Pd., M.Si.')
            ->assertJsonPath('data.0.position', 'Guru Matematika & Waka Kurikulum')
            ->assertJsonPath('data.0.photo.object_key', 'photos/teachers/ahmad.jpg')
            ->assertJsonPath('data.0.subjects.0.name', 'Matematika Wajib')
            // CRITICAL PRIVACY ASSERTIONS:
            ->assertJsonMissing([
                'nip' => '198505152010011012',
                'nuptk' => '9988776655443322',
                'user_id' => $this->teacherUser->id,
                'email' => 'ahmad.fauzi@labschool.sch.id',
                'password_hash' => $this->teacherUser->password_hash,
                'status' => 'aktif',
            ]);

        // 2. Check Detail Response
        $showResponse = $this->getJson("/api/v1/public/teachers/{$teacher->id}");

        $showResponse->assertStatus(200)
            ->assertJsonPath('data.id', $teacher->id)
            ->assertJsonPath('data.full_name', 'Ahmad Fauzi, S.Pd., M.Si.')
            ->assertJsonMissing([
                'nip' => '198505152010011012',
                'nuptk' => '9988776655443322',
                'user_id' => $this->teacherUser->id,
                'email' => 'ahmad.fauzi@labschool.sch.id',
            ]);
    }

    /**
     * BE-CMS-05: Inactive and soft-deleted teachers are hidden from public
     */
    public function test_be_cms_05_inactive_and_soft_deleted_teachers_are_hidden_from_public(): void
    {
        // Active
        $activeTeacher = Teacher::create([
            'full_name' => 'Guru Aktif',
            'position' => 'Guru Biologi',
            'status' => 'aktif',
        ]);

        // Cuti
        $cutiTeacher = Teacher::create([
            'full_name' => 'Guru Cuti Melahirkan',
            'position' => 'Guru Kimia',
            'status' => 'cuti',
        ]);

        // Pensiun
        $pensiunTeacher = Teacher::create([
            'full_name' => 'Guru Sudah Pensiun',
            'position' => 'Guru Sejarah',
            'status' => 'pensiun',
        ]);

        // Soft Deleted
        $deletedTeacher = Teacher::create([
            'full_name' => 'Guru Dihapus',
            'position' => 'Guru Fisika',
            'status' => 'aktif',
        ]);
        $deletedTeacher->delete();

        $response = $this->getJson('/api/v1/public/teachers');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $activeTeacher->id);

        // Showing inactive teacher returns 404
        $this->getJson("/api/v1/public/teachers/{$cutiTeacher->id}")
            ->assertStatus(404);

        $this->getJson("/api/v1/public/teachers/{$pensiunTeacher->id}")
            ->assertStatus(404);

        $this->getJson("/api/v1/public/teachers/{$deletedTeacher->id}")
            ->assertStatus(404);
    }

    /**
     * BE-CMS-05: Filters for teachers by name, position, and subject
     */
    public function test_be_cms_05_filters_for_teachers(): void
    {
        $teacherMath = Teacher::create([
            'full_name' => 'Budi Santoso, M.Pd.',
            'position' => 'Guru Matematika',
            'status' => 'aktif',
        ]);

        $teacherPhysics = Teacher::create([
            'full_name' => 'Dewi Lestari, M.Si.',
            'position' => 'Kepala Laboratorium Fisika',
            'status' => 'aktif',
        ]);

        $subjectMath = Subject::create([
            'code' => 'MAT',
            'name' => 'Matematika Peminatan',
            'subject_group' => 'peminatan',
        ]);

        $subjectPhys = Subject::create([
            'code' => 'FIS',
            'name' => 'Fisika Eksperimen',
            'subject_group' => 'peminatan',
        ]);

        TeachingAssignment::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->schoolClass->id,
            'teacher_id' => $teacherMath->id,
            'subject_id' => $subjectMath->id,
            'hours_per_week' => 4,
        ]);

        TeachingAssignment::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->schoolClass->id,
            'teacher_id' => $teacherPhysics->id,
            'subject_id' => $subjectPhys->id,
            'hours_per_week' => 4,
        ]);

        // Search by name
        $this->getJson('/api/v1/public/teachers?search=Budi')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.full_name', 'Budi Santoso, M.Pd.');

        // Filter by position
        $this->getJson('/api/v1/public/teachers?position=Laboratorium')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.full_name', 'Dewi Lestari, M.Si.');

        // Filter by subject name
        $this->getJson('/api/v1/public/teachers?subject=Fisika')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.full_name', 'Dewi Lestari, M.Si.');

        // Filter by subject_id
        $this->getJson("/api/v1/public/teachers?subject_id={$subjectMath->id}")
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.full_name', 'Budi Santoso, M.Pd.');
    }

    /**
     * BE-CMS-05: Public can access staff directory without sensitive fields
     */
    public function test_be_cms_05_public_can_access_staff_directory(): void
    {
        $staffUser = User::create([
            'username' => 'staf_tu',
            'email' => 'tu@labschool.sch.id',
            'password_hash' => Hash::make('TuSecret123!'),
            'status' => 'active',
        ]);

        Staff::create([
            'user_id' => $staffUser->id,
            'full_name' => 'Rahmat Hidayat, S.Kom.',
            'position' => 'Staf Tata Usaha & IT',
            'unit' => 'Tata Usaha',
        ]);

        Staff::create([
            'full_name' => 'Nurul Huda',
            'position' => 'Pustakawan Sekolah',
            'unit' => 'Perpustakaan',
        ]);

        // Index Staff
        $response = $this->getJson('/api/v1/public/staff');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonMissing([
                'user_id' => $staffUser->id,
                'email' => 'tu@labschool.sch.id',
                'password_hash' => $staffUser->password_hash,
            ]);

        // Filter by unit
        $this->getJson('/api/v1/public/staff?unit=Perpustakaan')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.full_name', 'Nurul Huda');

        // Search staff
        $this->getJson('/api/v1/public/staff?search=Rahmat')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.full_name', 'Rahmat Hidayat, S.Kom.');
    }
}
