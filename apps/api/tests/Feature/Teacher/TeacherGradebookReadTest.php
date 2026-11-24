<?php

namespace Tests\Feature\Teacher;

use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradePeriod;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\TeacherDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Buku nilai guru harus menampilkan nilai SEBENARNYA.
 *
 * Sebelumnya GET /me/teacher/grades mengembalikan array siswa dengan skor
 * karangan (finalScore 85, letterGrade A) dan tanpa komponen/periode, sehingga
 * halaman Buku Nilai tampak normal padahal isinya palsu.
 */
class TeacherGradebookReadTest extends TestCase
{
    use RefreshDatabase;

    protected User $guru;

    protected Teacher $teacher;

    protected SchoolClass $class;

    protected Subject $subject;

    protected GradeComponent $component;

    protected GradePeriod $period;

    protected Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        (new TeacherDemoSeeder)->run();

        $this->guru = User::where('username', 'guru.mtk.staging')->firstOrFail();
        $this->teacher = Teacher::where('user_id', $this->guru->id)->firstOrFail();
        $this->class = SchoolClass::where('name', '7-A')->firstOrFail();
        $this->subject = Subject::where('code', 'MTK-7')->firstOrFail();
        $this->period = GradePeriod::where('status', 'open')->firstOrFail();

        $this->component = GradeComponent::where('subject_id', $this->subject->id)
            ->where('class_id', $this->class->id)
            ->firstOrFail();

        $this->student = Student::whereHas('classes', fn ($q) => $q->where('classes.id', $this->class->id))
            ->firstOrFail();
    }

    public function test_buku_nilai_mengembalikan_periode_komponen_dan_nilai_nyata(): void
    {
        // Seeder demo sudah membuat nilai; selaraskan baris yang sama.
        Grade::updateOrCreate(
            [
                'student_id' => $this->student->id,
                'component_id' => $this->component->id,
                'period_id' => $this->period->id,
            ],
            [
                'id' => (string) Str::uuid7(),
                'value' => 92.5,
                'source' => 'manual',
                'entered_by' => $this->guru->id,
            ]
        );

        $res = $this->actingAs($this->guru, 'sanctum')
            ->getJson("/api/v1/me/teacher/grades?class_id={$this->class->id}&subject_id={$this->subject->id}")
            ->assertOk();

        $res->assertJsonStructure([
            'data' => [
                'period' => ['id', 'name', 'is_locked'],
                'components',
                'grades',
            ],
        ]);

        $data = $res->json('data');

        $this->assertNotEmpty($data['components'], 'Komponen nilai harus terkirim.');
        $this->assertSame($this->period->id, $data['period']['id']);

        $row = collect($data['grades'])->firstWhere('student_id', $this->student->id);
        $this->assertNotNull($row, 'Siswa harus muncul di baris buku nilai.');
        $this->assertEqualsWithDelta(92.5, (float) $row['scores'][$this->component->id], 0.01);
        $this->assertEqualsWithDelta(92.5, (float) $row['final_score'], 0.01);
        $this->assertTrue($row['is_tuntas']);
    }

    public function test_nilai_tidak_dikarang_untuk_siswa_tanpa_nilai(): void
    {
        // Siswa baru di kelas ini, tanpa satu pun nilai.
        $baru = Student::create([
            'id' => (string) Str::uuid7(),
            'full_name' => 'Siswa Tanpa Nilai',
            'nis' => '9999001',
            'nisn' => '9999000001',
            'nik_enc' => 'enc_9999001',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2013-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        DB::table('class_students')->insert([
            'id' => (string) Str::uuid7(),
            'class_id' => $this->class->id,
            'student_id' => $baru->id,
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $res = $this->actingAs($this->guru, 'sanctum')
            ->getJson("/api/v1/me/teacher/grades?class_id={$this->class->id}&subject_id={$this->subject->id}")
            ->assertOk();

        $row = collect($res->json('data.grades'))->firstWhere('student_id', $baru->id);

        $this->assertNotNull($row, 'Siswa baru harus muncul di buku nilai.');
        $this->assertEqualsWithDelta(0.0, (float) $row['final_score'], 0.01, 'Tanpa nilai, final_score harus 0 — bukan 85.');
        $this->assertFalse($row['is_tuntas']);
        $this->assertNull($row['scores'][$this->component->id]);
    }
}
