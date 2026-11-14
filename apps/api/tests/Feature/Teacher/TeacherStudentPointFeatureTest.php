<?php

namespace Tests\Feature\Teacher;

use App\Models\ClassStudent;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\TeacherDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherStudentPointFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $userMtk;

    protected Teacher $teacherMtk;

    protected SchoolClass $class7A;

    protected SchoolClass $class7B;

    protected Student $student7A;

    protected Student $student7B;

    protected function setUp(): void
    {
        parent::setUp();

        (new TeacherDemoSeeder)->run();

        $this->userMtk = User::where('username', 'guru.mtk.staging')->firstOrFail();
        $this->teacherMtk = Teacher::where('user_id', $this->userMtk->id)->firstOrFail();

        $this->class7A = SchoolClass::where('name', '7-A')->firstOrFail();
        $this->class7B = SchoolClass::where('name', '7-B')->firstOrFail();

        $this->student7A = $this->class7A->students()->firstOrFail();

        $userStudentB = User::create([
            'username' => 'siswa.7b.point.test',
            'email' => 'siswa.7b.point@smp-labum.sch.id',
            'password_hash' => bcrypt('Secret123!'),
            'status' => 'active',
        ]);
        $this->student7B = Student::create([
            'school_id' => $this->class7B->school_id ?? '00000000-0000-0000-0000-000000000001',
            'user_id' => $userStudentB->id,
            'nisn' => '9998887771',
            'nis' => '7B001',
            'nik_enc' => '3578010101019999',
            'full_name' => 'Dewi Lestari 7B',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2012-05-10',
            'status' => 'aktif',
            'entry_date' => '2026-07-01',
        ]);
        ClassStudent::create([
            'class_id' => $this->class7B->id,
            'student_id' => $this->student7B->id,
            'joined_at' => now(),
        ]);
    }

    /**
     * AC1: Menyimpan poin memanggil POST /me/teacher/student-points.
     */
    public function test_FIX_GURU_09_teacher_can_record_student_point_happy_path(): void
    {
        $response = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/student-points', [
                'student_id' => $this->student7A->id,
                'type' => 'prestasi',
                'category' => 'Juara 1 OSN Matematika',
                'points' => 25,
                'notes' => 'Meraih medali emas tingkat kota',
                'date' => now()->toDateString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.student_id', $this->student7A->id);

        $this->assertDatabaseHas('student_points', [
            'student_id' => $this->student7A->id,
            'recorded_by' => $this->userMtk->id,
            'notes' => 'Meraih medali emas tingkat kota',
        ]);
    }

    /**
     * AC1: Poin yang dicatat dapat dibaca kembali via GET /me/teacher/student-points (persist setelah refresh).
     */
    public function test_FIX_GURU_09_teacher_can_list_student_points_persisted(): void
    {
        // 1. Simpan poin
        $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/student-points', [
                'student_id' => $this->student7A->id,
                'type' => 'prestasi',
                'category' => 'Keaktifan Kelas',
                'points' => 10,
                'notes' => 'Siswa sangat aktif dalam diskusi kelompok',
                'date' => now()->toDateString(),
            ])
            ->assertStatus(201);

        // 2. Query kembali (simulasi refresh page / tab)
        $listResponse = $this->actingAs($this->userMtk)
            ->getJson('/api/v1/me/teacher/student-points?class_id=' . $this->class7A->id);

        $listResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'student_id',
                        'point_date',
                        'notes',
                        'student' => ['id', 'full_name'],
                        'point_type' => ['id', 'name', 'kind', 'points'],
                    ],
                ],
            ]);

        $this->assertNotEmpty($listResponse->json('data'));
        $this->assertEquals($this->student7A->id, $listResponse->json('data.0.student_id'));
    }

    /**
     * AC2: Validasi gagal menampilkan pesan per-field dari server.
     */
    public function test_FIX_GURU_09_validation_failure_returns_per_field_errors(): void
    {
        $response = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/student-points', [
                'student_id' => 'not-a-valid-uuid',
                'type' => 'invalid_type',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['student_id', 'type']);
    }

    /**
     * AC3: Guru tidak dapat mencatat poin untuk siswa di luar cakupan ajar / binaan (IDOR).
     */
    public function test_FIX_GURU_09_idor_teacher_cannot_record_point_for_foreign_student(): void
    {
        $response = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/student-points', [
                'student_id' => $this->student7B->id,
                'type' => 'pelanggaran',
                'notes' => 'Mencoba mencatat siswa kelas lain',
            ]);

        $response->assertStatus(403);
    }

    /**
     * AC3: Guru tidak dapat membaca poin kelas di luar wewenang (IDOR).
     */
    public function test_FIX_GURU_09_idor_teacher_cannot_list_points_for_foreign_class(): void
    {
        $response = $this->actingAs($this->userMtk)
            ->getJson('/api/v1/me/teacher/student-points?class_id=' . $this->class7B->id);

        $response->assertStatus(403);
    }

    /**
     * AC4: Permintaan tanpa autentikasi menghasilkan 401.
     */
    public function test_FIX_GURU_09_unauthenticated_request_returns_401(): void
    {
        $this->postJson('/api/v1/me/teacher/student-points', [])
            ->assertStatus(401);

        $this->getJson('/api/v1/me/teacher/student-points')
            ->assertStatus(401);
    }
}
