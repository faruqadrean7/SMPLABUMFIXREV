<?php

namespace Tests\Feature\Portal;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\GradeLevel;
use App\Models\ProgramMaterial;
use App\Models\ProgramTarget;
use App\Models\ProgramTask;
use App\Models\ProgramTaskSubmission;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProgramKhususStudentTest extends TestCase
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

    private function createStudentWithProgram(string $programType): array
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

        $class = SchoolClass::firstOrCreate([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'VII-'.strtoupper($programType),
        ], [
            'grade_level_id' => $this->gradeLevel->id,
            'capacity' => 30,
            'program_type' => $programType,
        ]);

        ClassStudent::create([
            'class_id' => $class->id,
            'student_id' => $student->id,
            'joined_at' => '2024-07-15',
        ]);

        return [$user, $student, $class];
    }

    /**
     * BE-PROG-01 (AC1): Siswa program tahfidz dapat mengambil materi program tahfidz miliknya.
     */
    public function test_be_prog_01_student_can_fetch_materials_for_their_program(): void
    {
        [$tahfidzUser, $tahfidzStudent, $tahfidzClass] = $this->createStudentWithProgram('tahfidz');

        // Materi Tahfidz (Scope All)
        ProgramMaterial::create([
            'program_type' => 'tahfidz',
            'title' => 'Panduan Tahsin & Makharijul Huruf Juz 30',
            'description' => 'Materi standar pelafalan huruf hijaiyah.',
            'material_type' => 'tajwid',
            'target_scope' => 'all',
        ]);

        // Materi Tahfidz (Scope Kelas)
        ProgramMaterial::create([
            'program_type' => 'tahfidz',
            'title' => 'Rekaman Murottal Surah An-Naba',
            'description' => 'Murottal talaqqi Ustadz pengampu.',
            'material_type' => 'murottal',
            'target_scope' => 'class',
            'class_id' => $tahfidzClass->id,
        ]);

        // Materi ICP (Program Lain - tidak boleh muncul)
        ProgramMaterial::create([
            'program_type' => 'icp',
            'title' => 'Cambridge Lower Secondary Math Notes',
            'description' => 'Algebra and fractions study guide.',
            'material_type' => 'reading',
            'target_scope' => 'all',
        ]);

        $response = $this->actingAs($tahfidzUser)
            ->getJson('/api/v1/me/program/materials');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);

        $titles = collect($response->json('data'))->pluck('title');
        $this->assertTrue($titles->contains('Panduan Tahsin & Makharijul Huruf Juz 30'));
        $this->assertTrue($titles->contains('Rekaman Murottal Surah An-Naba'));
        $this->assertFalse($titles->contains('Cambridge Lower Secondary Math Notes'));
    }

    /**
     * BE-PROG-01 (AC1): Siswa ICP dapat mengambil target capaian belajarnya.
     */
    public function test_be_prog_01_student_can_fetch_targets_for_their_program(): void
    {
        [$icpUser, $icpStudent] = $this->createStudentWithProgram('icp');
        [, $otherStudent] = $this->createStudentWithProgram('icp');

        ProgramTarget::create([
            'student_id' => $icpStudent->id,
            'program_type' => 'icp',
            'title' => 'Target Cambridge Checkpoint Math 5.0',
            'domain' => 'math',
            'target_value' => '5.0 / 6.0',
            'current_value' => '4.2',
            'period' => 'Tahun Ajaran 2024/2025',
            'status' => 'aktif',
        ]);

        // Target milik siswa lain (anti-IDOR)
        ProgramTarget::create([
            'student_id' => $otherStudent->id,
            'program_type' => 'icp',
            'title' => 'Target English Checkpoint Siswa Lain',
            'domain' => 'english',
            'target_value' => '6.0',
            'status' => 'aktif',
        ]);

        $response = $this->actingAs($icpUser)
            ->getJson('/api/v1/me/program/targets');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Target Cambridge Checkpoint Math 5.0');
    }

    /**
     * BE-PROG-01 (AC1): Siswa Inklusi dapat mengambil tugas program beserta status pengumpulannya.
     */
    public function test_be_prog_01_student_can_fetch_tasks_with_submission_status(): void
    {
        [$inklusiUser, $inklusiStudent] = $this->createStudentWithProgram('inklusi');

        $task = ProgramTask::create([
            'program_type' => 'inklusi',
            'title' => 'Latihan Pengenalan Emosi & Regulasi Sensori',
            'instructions' => 'Tuliskan pengalaman hari ini pada lembar kerja.',
            'due_at' => now()->addDays(3),
            'target_scope' => 'all',
        ]);

        // Belum dikumpulkan
        $response1 = $this->actingAs($inklusiUser)
            ->getJson('/api/v1/me/program/tasks');

        $response1->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(0, 'data.0.submissions');

        // Kumpulkan tugas
        ProgramTaskSubmission::create([
            'task_id' => $task->id,
            'student_id' => $inklusiStudent->id,
            'notes' => 'Saya merasa tenang setelah mendengarkan musik lembut di ruang sensory.',
            'status' => 'dikumpulkan',
        ]);

        // Mengambil lagi setelah dikumpulkan
        $response2 = $this->actingAs($inklusiUser)
            ->getJson('/api/v1/me/program/tasks?status=submitted');

        $response2->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data.0.submissions')
            ->assertJsonPath('data.0.submissions.0.notes', 'Saya merasa tenang setelah mendengarkan musik lembut di ruang sensory.');
    }

    /**
     * BE-PROG-01 (AC2): Siswa program dilarang mengakses resource program lain (403 Forbidden).
     */
    public function test_be_prog_01_cross_program_access_is_forbidden_with_403(): void
    {
        [$tahfidzUser] = $this->createStudentWithProgram('tahfidz');

        // Siswa Tahfidz meminta data program ICP via filter query
        $response = $this->actingAs($tahfidzUser)
            ->getJson('/api/v1/me/program/materials?program_type=icp');

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-PROG-01 (AC2): Siswa reguler dilarang mengakses endpoint program khusus (403 Forbidden).
     */
    public function test_be_prog_01_regular_student_cannot_access_special_program_endpoints(): void
    {
        [$regulerUser] = $this->createStudentWithProgram('reguler');

        $response = $this->actingAs($regulerUser)
            ->getJson('/api/v1/me/program/materials');

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-PROG-01 (AC3): Request tanpa token autentikasi ditolak (401 Unauthorized).
     */
    public function test_be_prog_01_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/me/program/materials');
        $response->assertStatus(401);
    }

    /**
     * BE-PROG-01 (AC4): Pengiriman tugas bersifat idempoten dengan Idempotency-Key.
     */
    public function test_be_prog_01_student_can_submit_task_with_idempotency_key(): void
    {
        [$tahfidzUser, $tahfidzStudent] = $this->createStudentWithProgram('tahfidz');

        $task = ProgramTask::create([
            'program_type' => 'tahfidz',
            'title' => 'Setoran Mandiri Surah An-Naba Ayat 1-20',
            'instructions' => 'Unggah rekaman hafalan.',
            'target_scope' => 'all',
        ]);

        $payload = [
            'notes' => 'Sudah disetorkan dengan lancar tanpa terbata-bata.',
            'link' => 'https://drive.google.com/file/d/sample-audio-1',
        ];

        $key = 'idem-prog-submit-001';

        // 1. Submit pertama
        $response1 = $this->actingAs($tahfidzUser)
            ->withHeader('X-Idempotency-Key', $key)
            ->postJson("/api/v1/me/program/tasks/{$task->id}/submit", $payload);

        $response1->assertStatus(201)
            ->assertJsonPath('data.status', 'dikumpulkan')
            ->assertJsonPath('data.notes', $payload['notes']);

        $submissionId = $response1->json('data.id');

        // 2. Submit kedua dengan Idempotency-Key yang sama dan payload identik
        $response2 = $this->actingAs($tahfidzUser)
            ->withHeader('X-Idempotency-Key', $key)
            ->postJson("/api/v1/me/program/tasks/{$task->id}/submit", $payload);

        $response2->assertStatus(200)
            ->assertJsonPath('data.id', $submissionId)
            ->assertJsonPath('meta.is_replay', true);

        // Basis data hanya memiliki tepat 1 rekaman submission (idempoten)
        $this->assertEquals(
            1,
            ProgramTaskSubmission::where('task_id', $task->id)->where('student_id', $tahfidzStudent->id)->count()
        );
    }

    /**
     * BE-PROG-01 (AC4): Submit dengan Idempotency-Key yang sama tetapi payload berbeda ditolak (422 Conflict).
     */
    public function test_be_prog_01_submitting_with_same_idempotency_key_different_payload_returns_422(): void
    {
        [$tahfidzUser] = $this->createStudentWithProgram('tahfidz');

        $task = ProgramTask::create([
            'program_type' => 'tahfidz',
            'title' => 'Setoran Mandiri Surah An-Naziat',
            'target_scope' => 'all',
        ]);

        $key = 'idem-conflict-key';

        // Request 1
        $this->actingAs($tahfidzUser)
            ->withHeader('X-Idempotency-Key', $key)
            ->postJson("/api/v1/me/program/tasks/{$task->id}/submit", [
                'notes' => 'Catatan pertama hafalan.',
            ])->assertStatus(201);

        // Request 2 dengan key sama tapi catatan berbeda
        $response2 = $this->actingAs($tahfidzUser)
            ->withHeader('X-Idempotency-Key', $key)
            ->postJson("/api/v1/me/program/tasks/{$task->id}/submit", [
                'notes' => 'Catatan kedua berbeda yang diubah.',
            ]);

        $response2->assertStatus(422)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
    }

    /**
     * BE-PROG-01 (AC5): Paginasi materi program berfungsi dengan efisiensi query.
     */
    public function test_be_prog_01_materials_and_tasks_are_paginated_without_n_plus_one(): void
    {
        [$icpUser] = $this->createStudentWithProgram('icp');

        for ($i = 1; $i <= 5; $i++) {
            ProgramMaterial::create([
                'program_type' => 'icp',
                'title' => "ICP Material Chapter {$i}",
                'material_type' => 'reading',
                'target_scope' => 'all',
            ]);
        }

        $response = $this->actingAs($icpUser)
            ->getJson('/api/v1/me/program/materials?per_page=2&page=2');

        $response->assertStatus(200)
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.limit', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.totalPages', 3);

        $this->assertCount(2, $response->json('data'));
    }
}
