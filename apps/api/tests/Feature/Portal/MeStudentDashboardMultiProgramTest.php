<?php

namespace Tests\Feature\Portal;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\GradeLevel;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentProgramProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MeStudentDashboardMultiProgramTest extends TestCase
{
    use RefreshDatabase;

    protected AcademicYear $academicYear;

    protected GradeLevel $gradeLevel;

    protected Role $studentRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->studentRole = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

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

    private function createStudentWithProgram(string $programType, array $metaData = []): array
    {
        $unique = (string) rand(10000, 99999);
        $user = User::create([
            'username' => 'siswa_'.$programType.'_'.$unique,
            'email' => 'siswa_'.$programType.'_'.$unique.'@labschool.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $user->roles()->attach($this->studentRole->id);

        $student = Student::create([
            'user_id' => $user->id,
            'nis' => $unique,
            'nisn' => '00'.$unique,
            'nik_enc' => '35730101'.str_pad($unique, 8, '0', STR_PAD_LEFT),
            'full_name' => 'Siswa '.ucfirst($programType).' '.$unique,
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-05-10',
            'status' => 'aktif',
            'entry_date' => '2024-07-15',
            'program_type' => $programType,
        ]);

        $class = SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->gradeLevel->id,
            'name' => 'VII-'.strtoupper($programType),
            'capacity' => 30,
            'program_type' => $programType,
        ]);

        ClassStudent::create([
            'class_id' => $class->id,
            'student_id' => $student->id,
            'joined_at' => '2024-07-15',
        ]);

        if (! empty($metaData)) {
            StudentProgramProfile::create([
                'student_id' => $student->id,
                'program_type' => $programType,
                'meta_data' => $metaData,
            ]);
        }

        return [$user, $student, $class];
    }

    /**
     * BE-PORTAL-STUDENT-02B: Dashboard merespons data spesifik untuk Siswa Tahfidz.
     */
    public function test_student_dashboard_returns_tahfidz_data(): void
    {
        [$user, $student] = $this->createStudentWithProgram('tahfidz', [
            'target_juz' => 3,
            'current_juz' => 2,
            'current_surah' => 'Al-Mulk: 1-30',
            'last_tasmi_date' => '2026-10-28',
            'last_tasmi_grade' => 'Mumtaz',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me/student-dashboard');

        $response->assertOk()
            ->assertJsonPath('data.programType', 'tahfidz')
            ->assertJsonPath('data.programBadge', "Siswa Program Tahfidz Al-Qur'an")
            ->assertJsonPath('data.programProfile.target_juz', 3)
            ->assertJsonPath('data.programProfile.last_tasmi_grade', 'Mumtaz');
    }

    /**
     * BE-PORTAL-STUDENT-02B: Dashboard merespons data spesifik untuk Siswa ICP (Cambridge).
     */
    public function test_student_dashboard_returns_icp_data(): void
    {
        [$user, $student] = $this->createStudentWithProgram('icp', [
            'cambridge_candidate_id' => 'ID-MAL-042',
            'checkpoint_readiness' => [
                'english' => ['score' => 4.8, 'status' => 'Ready'],
                'math' => ['score' => 5.2, 'status' => 'Exceeding'],
            ],
            'vocab_of_day' => [
                'word' => 'Photosynthesis',
            ],
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me/student-dashboard');

        $response->assertOk()
            ->assertJsonPath('data.programType', 'icp')
            ->assertJsonPath('data.programBadge', 'International Class Program (Cambridge)')
            ->assertJsonPath('data.programProfile.cambridge_candidate_id', 'ID-MAL-042')
            ->assertJsonPath('data.programProfile.vocab_of_day.word', 'Photosynthesis');
    }

    /**
     * BE-PORTAL-STUDENT-02B: Dashboard merespons data spesifik untuk Siswa Inklusi.
     */
    public function test_student_dashboard_returns_inklusi_data(): void
    {
        [$user, $student] = $this->createStudentWithProgram('inklusi', [
            'gpk' => [
                'name' => 'Ibu Ratna Dewi, S.Pd (GPK)',
                'contact_phone' => '081234567890',
            ],
            'ppi_milestones' => [
                ['id' => 'm1', 'title' => 'Menyelesaikan modul membaca', 'is_completed' => true],
            ],
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me/student-dashboard');

        $response->assertOk()
            ->assertJsonPath('data.programType', 'inklusi')
            ->assertJsonPath('data.programBadge', 'Siswa Program Inklusi')
            ->assertJsonPath('data.programProfile.gpk.name', 'Ibu Ratna Dewi, S.Pd (GPK)')
            ->assertJsonPath('data.programProfile.ppi_milestones.0.is_completed', true);
    }

    /**
     * BE-PORTAL-STUDENT-02B: Siswa Inklusi dapat mengirimkan check-in suasana hati (mood).
     */
    public function test_student_inklusi_can_submit_daily_mood(): void
    {
        [$user, $student] = $this->createStudentWithProgram('inklusi');

        $payload = [
            'mood' => 'happy',
            'note' => 'Siap belajar dengan ceria',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/program/mood', $payload);

        $response->assertOk()
            ->assertJsonPath('data.mood', 'happy')
            ->assertJsonPath('data.note', 'Siap belajar dengan ceria');

        $this->assertDatabaseHas('student_program_profiles', [
            'student_id' => $student->id,
            'program_type' => 'inklusi',
        ]);

        // Aksi sensitif harus terekam pada audit_logs (AGENTS.md §4 & §6.5).
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'portal.program.mood.checkin',
            'entity' => 'StudentProgramProfile',
            'actor_id' => $user->id,
        ]);
    }

    /**
     * BE-PORTAL-STUDENT-02B: Validasi penolakan mood tidak valid.
     */
    public function test_submit_mood_validates_input(): void
    {
        [$user] = $this->createStudentWithProgram('inklusi');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/program/mood', [
                'mood' => 'marah_sekali', // illegal mood
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['mood']);
    }

    /**
     * BE-PORTAL-STUDENT-02B: Siswa Tahfidz dapat mengirimkan checklist mutaba'ah ibadah.
     */
    public function test_student_tahfidz_can_submit_mutabaah(): void
    {
        [$user, $student] = $this->createStudentWithProgram('tahfidz');

        $payload = [
            'sholat_fardhu' => 5,
            'tilawah_pages' => 6,
            'dhuha' => true,
            'tahajud' => false,
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/me/program/mutabaah', $payload);

        $response->assertOk()
            ->assertJsonPath('data.sholat_fardhu', 5)
            ->assertJsonPath('data.tilawah_pages', 6)
            ->assertJsonPath('data.dhuha', true)
            ->assertJsonPath('data.tahajud', false);

        $this->assertDatabaseHas('student_program_profiles', [
            'student_id' => $student->id,
            'program_type' => 'tahfidz',
        ]);

        // Aksi sensitif harus terekam pada audit_logs (AGENTS.md §4 & §6.5).
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'portal.program.mutabaah.update',
            'entity' => 'StudentProgramProfile',
            'actor_id' => $user->id,
        ]);
    }

    /**
     * BE-PORTAL-STUDENT-02B: Endpoint program menolak akses guest (401).
     */
    public function test_program_endpoints_require_authentication(): void
    {
        $this->postJson('/api/v1/me/program/mood', ['mood' => 'happy'])
            ->assertStatus(401);

        $this->postJson('/api/v1/me/program/mutabaah', ['sholat_fardhu' => 5])
            ->assertStatus(401);
    }
}
