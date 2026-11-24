<?php

namespace Tests\Feature\Guardian;

use App\Models\CharacterNote;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradePeriod;
use App\Models\Guardian;
use App\Models\ReportCard;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\TeacherDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Portal orang tua harus menampilkan data nyata.
 *
 * Temuan yang dikunci di sini:
 *  - rapor ortu selalu kosong walau snapshot berisi nilai (kunci 'subjects', bukan 'grades')
 *  - nilai formatif/sumatif/STS diisi satu angka rata-rata yang sama
 *  - guru pengampu selalu "Guru Pengampu" (placeholder)
 *  - tahun ajaran hardcode "2024/2025"
 *  - catatan karakter mengarang lima aspek bernilai "baik"
 */
class GuardianPortalReadTest extends TestCase
{
    use RefreshDatabase;

    protected User $ortu;

    protected Guardian $guardian;

    protected Student $student;

    protected SchoolClass $class;

    protected Subject $subject;

    protected Semester $semester;

    protected GradePeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        (new TeacherDemoSeeder)->run();

        $this->class = SchoolClass::where('name', '7-A')->firstOrFail();
        $this->subject = Subject::where('code', 'MTK-7')->firstOrFail();
        $this->semester = Semester::where('name', 'Ganjil')->firstOrFail();
        $this->period = GradePeriod::where('semester_id', $this->semester->id)->where('status', 'open')->firstOrFail();
        $this->student = Student::whereHas('classes', fn ($q) => $q->where('classes.id', $this->class->id))->firstOrFail();

        $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Wali Murid', 'is_system' => true]);

        $this->ortu = User::firstOrCreate(
            ['username' => 'ortu.portal.read'],
            [
                'school_id' => $this->class->school_id,
                'email' => 'ortu.read@sistlabum.sch.id',
                'password_hash' => bcrypt('password'),
                'status' => 'active',
            ]
        );
        $this->ortu->roles()->syncWithoutDetaching([$roleGuardian->id]);

        $this->guardian = Guardian::firstOrCreate(
            ['user_id' => $this->ortu->id],
            ['full_name' => 'Wali Murid Uji', 'phone' => '081200000001']
        );

        $this->guardian->students()->syncWithoutDetaching([
            $this->student->id => ['relationship' => 'ayah', 'is_primary' => true],
        ]);
    }

    public function test_rapor_membaca_nilai_dari_snapshot_subjects(): void
    {
        ReportCard::create([
            'id' => (string) Str::uuid7(),
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
            'status' => 'draft',
            'verify_token' => Str::random(40),
            'data_snapshot' => [
                'student' => ['full_name' => $this->student->full_name],
                'subjects' => [
                    ['subject_id' => $this->subject->id, 'subject_code' => 'MTK', 'subject_name' => 'Matematika',
                        'final_score' => 84.5, 'level' => 'tuntas', 'competency_description' => 'Baik'],
                    ['subject_id' => (string) Str::uuid7(), 'subject_code' => 'IPA', 'subject_name' => 'Ilmu Pengetahuan Alam',
                        'final_score' => 78.0, 'level' => 'tuntas', 'competency_description' => 'Cukup'],
                ],
                'homeroom_note' => 'Pertahankan prestasi.',
            ],
        ]);

        $res = $this->actingAs($this->ortu, 'sanctum')
            ->getJson("/api/v1/me/children/{$this->student->id}/report-cards")
            ->assertOk();

        $data = $res->json('data');

        $this->assertCount(2, $data['grades'], 'Rapor harus memuat 2 mapel dari snapshot.');
        $this->assertSame('Matematika', $data['grades'][0]['subject_name']);
        $this->assertEqualsWithDelta(84.5, $data['grades'][0]['final_score'], 0.01);
        $this->assertEqualsWithDelta(162.5, (float) $data['totalScore'], 0.01);
        $this->assertEqualsWithDelta(81.25, (float) $data['averageScore'], 0.01);
        $this->assertNotSame('2024/2025', $data['academicYear'], 'Tahun ajaran tidak boleh hardcode 2024/2025.');
    }

    public function test_nilai_memecah_komponen_dan_menyebut_guru_pengampu_sebenarnya(): void
    {
        $teacher = Teacher::whereHas('user', fn ($q) => $q->where('username', 'guru.mtk.staging'))->firstOrFail();

        $komponen = [
            'TGS' => [30, 90.0],
            'UTS' => [30, 70.0],
            'UAS' => [40, 80.0],
        ];

        foreach ($komponen as $code => [$weight, $value]) {
            $component = GradeComponent::firstOrCreate(
                ['subject_id' => $this->subject->id, 'semester_id' => $this->semester->id, 'code' => $code, 'class_id' => $this->class->id],
                ['name' => $code, 'weight' => $weight]
            );

            Grade::updateOrCreate(
                ['student_id' => $this->student->id, 'component_id' => $component->id, 'period_id' => $this->period->id],
                ['id' => (string) Str::uuid7(), 'value' => $value, 'source' => 'manual']
            );
        }

        $res = $this->actingAs($this->ortu, 'sanctum')
            ->getJson("/api/v1/me/children/{$this->student->id}/grades")
            ->assertOk();

        $row = collect($res->json('data'))->firstWhere('subject_id', $this->subject->id);
        $this->assertNotNull($row, 'Mapel MTK harus muncul.');

        $this->assertEqualsWithDelta(90.0, (float) $row['dailyAverage'], 0.01, 'Formatif = nilai TGS.');
        $this->assertEqualsWithDelta(70.0, (float) $row['midExam'], 0.01, 'STS = nilai UTS.');
        $this->assertEqualsWithDelta(80.0, (float) $row['finalExam'], 0.01, 'Sumatif = nilai UAS.');
        // Seeder menambah komponen lain, jadi nilai akhir = rata-rata berbobot seluruh komponen.
        // Yang penting: BUKAN angka yang sama dengan formatif/STS/sumatif (bug lama).
        $this->assertNotEqualsWithDelta((float) $row['dailyAverage'], (float) $row['finalScore'], 0.01);
        $this->assertGreaterThan(70.0, (float) $row['finalScore']);
        $this->assertLessThan(90.0, (float) $row['finalScore']);
        $this->assertNotSame('Guru Pengampu', $row['teacherName'], 'Nama guru pengampu harus nyata.');
        $this->assertSame($teacher->full_name, $row['teacherName']);
    }

    public function test_catatan_karakter_tidak_mengarang_penilaian(): void
    {
        CharacterNote::create([
            'id' => (string) Str::uuid7(),
            'student_id' => $this->student->id,
            'period_id' => $this->period->id,
            'scale_data' => [],
            'author_id' => User::where('username', 'walas.7a.staging')->firstOrFail()->id,
            'homeroom_note' => 'Anak aktif di kelas.',
        ]);

        $res = $this->actingAs($this->ortu, 'sanctum')
            ->getJson("/api/v1/me/children/{$this->student->id}/character-notes")
            ->assertOk();

        $data = $res->json('data');

        $this->assertSame([], $data['ratings'], 'Tanpa scale_data, tidak boleh ada rating karangan.');
        $this->assertSame('Anak aktif di kelas.', $data['homeroomNote']);
        $this->assertNotSame('2024/2025', $data['academicYear']);
    }
}
