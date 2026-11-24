<?php

namespace Tests\Feature\Academic;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradePeriod;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\GradeRecorder;
use Database\Seeders\TeacherDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Proteksi nilai manual, komponen wajib, dan pemilih periode buku nilai.
 */
class GradebookProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $guru;

    protected Teacher $teacher;

    protected SchoolClass $class;

    protected Subject $subject;

    protected GradeComponent $tgs;

    protected GradePeriod $periode;

    protected Student $siswa;

    protected function setUp(): void
    {
        parent::setUp();

        (new TeacherDemoSeeder)->run();

        $this->guru = User::where('username', 'guru.mtk.staging')->firstOrFail();
        $this->teacher = Teacher::where('user_id', $this->guru->id)->firstOrFail();
        $this->class = SchoolClass::where('name', '7-A')->firstOrFail();
        $this->subject = Subject::where('code', 'MTK-7')->firstOrFail();

        // Seeder demo memakai kode S-01/STS/SAS; tambahkan komponen TGS untuk skenario tugas.
        $this->tgs = GradeComponent::firstOrCreate(
            [
                'class_id' => $this->class->id,
                'subject_id' => $this->subject->id,
                'semester_id' => GradePeriod::firstOrFail()->semester_id,
                'code' => 'TGS',
            ],
            ['name' => 'Tugas / Formatif', 'weight' => 25]
        );

        $this->periode = GradePeriod::where('status', 'open')->orderBy('month')->firstOrFail();

        $this->siswa = Student::whereHas('classes', fn ($q) => $q->where('classes.id', $this->class->id))->firstOrFail();
    }

    private function nilaiManual(float $value): void
    {
        $this->actingAs($this->guru, 'sanctum')
            ->postJson('/api/v1/me/teacher/grades/bulk', [
                'class_id' => $this->class->id,
                'subject_id' => $this->subject->id,
                'period_id' => $this->periode->id,
                'grades' => [[
                    'student_id' => $this->siswa->id,
                    'scores' => [$this->tgs->id => $value],
                ]],
            ])
            ->assertOk();
    }

    private function buatTugasDanNilai(float $skor, ?string $componentId = null): array
    {
        $assignment = Assignment::create([
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Tugas Uji '.Str::random(5),
            'due_at' => now()->addDays(3),
            'max_score' => 100,
            'grade_component_id' => $componentId ?? $this->tgs->id,
        ]);

        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->siswa->id,
            'submitted_at' => now(),
        ]);

        $res = $this->actingAs($this->guru, 'sanctum')
            ->patchJson("/api/v1/assignments/{$assignment->id}/submissions/{$submission->id}/grade", [
                'score' => $skor,
                'feedback' => 'Uji',
            ]);

        return [$assignment, $submission, $res];
    }

    private function nilai(): Grade
    {
        return Grade::where('student_id', $this->siswa->id)
            ->where('component_id', $this->tgs->id)
            ->where('period_id', $this->periode->id)
            ->firstOrFail();
    }

    // 1. Manual 90 + otomatis 87 -> efektif 90
    public function test_manual_lebih_tinggi_dari_otomatis_maka_manual_dipakai(): void
    {
        $this->nilaiManual(90);
        $this->buatTugasDanNilai(87)[2]->assertOk();

        $grade = $this->nilai();

        $this->assertEqualsWithDelta(90.0, (float) $grade->value, 0.01, 'Nilai efektif harus 90.');
        $this->assertEqualsWithDelta(90.0, (float) $grade->manual_value, 0.01);
        $this->assertEqualsWithDelta(87.0, (float) $grade->auto_value, 0.01, 'Nilai otomatis tetap tersimpan.');
        $this->assertSame('manual', $grade->source);
    }

    // 2. Manual 80 + otomatis 95 -> efektif 95
    public function test_otomatis_lebih_tinggi_dari_manual_maka_otomatis_dipakai(): void
    {
        $this->nilaiManual(80);
        $this->buatTugasDanNilai(95)[2]->assertOk();

        $grade = $this->nilai();

        $this->assertEqualsWithDelta(95.0, (float) $grade->value, 0.01);
        $this->assertEqualsWithDelta(80.0, (float) $grade->manual_value, 0.01);
        $this->assertSame('assignment', $grade->source);
    }

    // 3. Menilai tugas tanpa komponen -> ditolak
    public function test_menilai_tugas_tanpa_komponen_ditolak(): void
    {
        $assignment = Assignment::create([
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Tugas Lama Tanpa Komponen',
            'due_at' => now()->addDays(3),
            'max_score' => 100,
            'grade_component_id' => null,
        ]);

        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->siswa->id,
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->guru, 'sanctum')
            ->patchJson("/api/v1/assignments/{$assignment->id}/submissions/{$submission->id}/grade", [
                'score' => 88,
            ])
            ->assertStatus(422);

        // Tidak ada nilai yang ditulis untuk tugas tanpa komponen.
        $this->assertDatabaseMissing('grades', [
            'student_id' => $this->siswa->id,
            'component_id' => $this->tgs->id,
            'period_id' => $this->periode->id,
        ]);
    }

    // 4. Tugas dengan komponen TGS -> nilai masuk komponen TGS
    public function test_nilai_tugas_masuk_komponen_tgs(): void
    {
        $this->buatTugasDanNilai(91)[2]->assertOk();

        $grade = $this->nilai();

        $this->assertEqualsWithDelta(91.0, (float) $grade->value, 0.01);
        $this->assertSame('assignment', $grade->source);
        $this->assertSame($this->tgs->id, $grade->component_id);
    }

    // 5-6-7. Nilai tiap bulan tampil pada periode masing-masing
    public function test_nilai_setiap_periode_tampil_sesuai_pilihannya(): void
    {
        $recorder = app(GradeRecorder::class);
        $semesterId = $this->tgs->semester_id;

        $periodePerBulan = [];
        foreach ([7, 8, 9, 10, 11, 12] as $bulan) {
            $periodePerBulan[$bulan] = GradePeriod::firstOrCreate(
                ['semester_id' => $semesterId, 'month' => $bulan],
                ['status' => 'open']
            );

            $recorder->recordManual(
                studentId: $this->siswa->id,
                componentId: $this->tgs->id,
                periodId: $periodePerBulan[$bulan]->id,
                value: (float) (70 + $bulan),
                changedBy: $this->guru->id,
            );
        }

        foreach ($periodePerBulan as $bulan => $periode) {
            $res = $this->actingAs($this->guru, 'sanctum')
                ->getJson("/api/v1/me/teacher/grades?class_id={$this->class->id}&subject_id={$this->subject->id}&period_id={$periode->id}")
                ->assertOk();

            $row = collect($res->json('data.grades'))->firstWhere('student_id', $this->siswa->id);

            $this->assertEqualsWithDelta((float) (70 + $bulan), (float) $row['scores'][$this->tgs->id], 0.01,
                "Nilai bulan {$bulan} harus tampil pada periode bulan {$bulan}.");
            $this->assertSame($periode->id, $res->json('data.period.id'));
        }

        // 7. Berpindah periode tidak menghilangkan nilai periode lain
        $this->assertSame(6, Grade::where('student_id', $this->siswa->id)->where('component_id', $this->tgs->id)->count());
    }

    // 8. Riwayat tersimpan, data lama tidak hilang
    public function test_riwayat_perubahan_nilai_tersimpan(): void
    {
        $this->nilaiManual(90);
        $this->buatTugasDanNilai(87)[2]->assertOk();
        $this->nilaiManual(75);

        $grade = $this->nilai();
        $riwayat = $grade->histories()->get();

        $this->assertGreaterThanOrEqual(3, $riwayat->count(), 'Setiap perubahan harus tercatat.');
        $this->assertTrue($riwayat->contains(fn ($h) => (float) $h->new_value === 87.0), 'Nilai otomatis 87 tercatat di riwayat.');

        // Guru mengoreksi nilai manual menjadi 75; otomatis 87 lebih tinggi -> efektif 87.
        // Yang penting: nilai 90 sebelumnya TIDAK hilang dari riwayat.
        $this->assertEqualsWithDelta(87.0, (float) $grade->value, 0.01);
        $this->assertEqualsWithDelta(75.0, (float) $grade->manual_value, 0.01);
        $this->assertEqualsWithDelta(87.0, (float) $grade->auto_value, 0.01);
        $this->assertSame('assignment', $grade->source);
        $this->assertTrue($riwayat->contains(fn ($h) => (float) $h->new_value === 90.0), 'Nilai manual 90 tetap tercatat di riwayat.');
    }

    // Data lama: tugas tanpa komponen bisa ditautkan, tidak dihapus
    public function test_tugas_lama_tanpa_komponen_bisa_ditautkan(): void
    {
        $assignment = Assignment::create([
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Tugas Lama',
            'due_at' => now()->addDays(3),
            'max_score' => 100,
            'grade_component_id' => null,
        ]);

        $res = $this->actingAs($this->guru, 'sanctum')
            ->getJson('/api/v1/assignments/without-grade-component')
            ->assertOk();

        $ids = collect($res->json('data.assignments'))->pluck('id')->all();
        $this->assertContains($assignment->id, $ids, 'Tugas tanpa komponen harus terdaftar.');

        $this->actingAs($this->guru, 'sanctum')
            ->postJson("/api/v1/assignments/{$assignment->id}/link-grade-component", [
                'grade_component_id' => $this->tgs->id,
            ])
            ->assertOk();

        $this->assertSame($this->tgs->id, $assignment->fresh()->grade_component_id);
        $this->assertDatabaseHas('assignments', ['id' => $assignment->id]);
    }

    // Pemilih periode: daftar periode untuk halaman penilaian tugas
    public function test_daftar_periode_tersedia_untuk_guru(): void
    {
        $res = $this->actingAs($this->guru, 'sanctum')
            ->getJson('/api/v1/me/teacher/periods')
            ->assertOk();

        $rows = $res->json('data');

        $this->assertNotEmpty($rows, 'Daftar periode tidak boleh kosong.');
        $this->assertArrayHasKey('month', $rows[0]);
        $this->assertTrue(collect($rows)->contains(fn ($p) => $p['is_default'] === true), 'Harus ada satu periode default.');
    }

    // Pemilih periode: nilai tugas masuk ke periode yang dipilih guru
    public function test_nilai_tugas_masuk_periode_yang_dipilih(): void
    {
        $periodeLain = GradePeriod::firstOrCreate(
            ['semester_id' => $this->tgs->semester_id, 'month' => 9],
            ['status' => 'open']
        );
        // Seeder demo bisa membuat periode ini terkunci; buka agar bisa diuji.
        $periodeLain->update(['status' => 'open']);

        $assignment = Assignment::create([
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Tugas Periode Dipilih',
            'due_at' => now()->addDays(3),
            'max_score' => 100,
            'grade_component_id' => $this->tgs->id,
        ]);

        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->siswa->id,
            'submitted_at' => now(),
        ]);

        // Lewat endpoint portal guru (halaman penilaian tugas)
        $this->actingAs($this->guru, 'sanctum')
            ->postJson("/api/v1/me/teacher/submissions/{$submission->id}/grade", [
                'score' => 84,
                'feedback' => 'Uji periode',
                'period_id' => $periodeLain->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('grades', [
            'student_id' => $this->siswa->id,
            'component_id' => $this->tgs->id,
            'period_id' => $periodeLain->id,
        ]);

        // Periode default (bulan 7) tidak ikut terisi oleh penilaian ini
        $this->assertDatabaseMissing('grades', [
            'student_id' => $this->siswa->id,
            'component_id' => $this->tgs->id,
            'period_id' => $this->periode->id,
            'source' => 'assignment',
        ]);
    }

    // Komponen wajib saat membuat tugas
    public function test_membuat_tugas_tanpa_komponen_ditolak(): void
    {
        $this->actingAs($this->guru, 'sanctum')
            ->postJson('/api/v1/assignments', [
                'class_id' => $this->class->id,
                'subject_id' => $this->subject->id,
                'title' => 'Tugas Tanpa Komponen',
                'due_at' => now()->addDays(3)->toISOString(),
                'max_score' => 100,
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.0.field', 'grade_component_id');
    }
}
