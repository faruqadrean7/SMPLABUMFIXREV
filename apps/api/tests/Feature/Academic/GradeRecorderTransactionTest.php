<?php

namespace Tests\Feature\Academic;

use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradeHistory;
use App\Models\GradePeriod;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\GradeRecorder;
use Database\Seeders\TeacherDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GradeRecorderTransactionTest extends TestCase
{
    use RefreshDatabase;

    private GradeRecorder $recorder;

    private User $guru;

    private Student $student;

    private GradeComponent $component;

    private GradePeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        (new TeacherDemoSeeder)->run();

        $this->recorder = app(GradeRecorder::class);
        $this->guru = User::where('username', 'guru.mtk.staging')->firstOrFail();
        $class = SchoolClass::where('name', '7-A')->firstOrFail();
        $subject = Subject::where('code', 'MTK-7')->firstOrFail();
        $this->period = GradePeriod::where('status', 'open')->orderBy('month')->firstOrFail();
        $this->component = GradeComponent::firstOrCreate(
            [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'semester_id' => $this->period->semester_id,
                'code' => 'CONC-TGS',
            ],
            ['name' => 'Tugas Konkurensi', 'weight' => 25]
        );
        $this->student = Student::whereHas('classes', fn ($q) => $q->where('classes.id', $class->id))->firstOrFail();
    }

    public function test_t2b_conc_03_preserves_max_manual_auto_policy(): void
    {
        // Manual 85 lalu CBT 80 -> efektif tetap 85 (manual), auto tetap terekam 80
        $g1 = $this->recorder->recordManual(
            $this->student->id,
            $this->component->id,
            $this->period->id,
            85.0,
            $this->guru->id,
            'Input manual guru'
        );
        $g2 = $this->recorder->recordAuto(
            $this->student->id,
            $this->component->id,
            $this->period->id,
            80.0,
            GradeRecorder::SOURCE_CBT,
            $this->guru->id,
            null,
            'Sinkronisasi CBT'
        );

        $this->assertSame($g1->id, $g2->id);
        $this->assertEquals(85.0, (float) $g2->value);
        $this->assertEquals(85.0, (float) $g2->manual_value);
        $this->assertEquals(80.0, (float) $g2->auto_value);
        $this->assertSame(GradeRecorder::SOURCE_MANUAL, $g2->source);

        // CBT lebih tinggi (92) -> efektif naik ke 92 (cbt), manual tetap 85
        $g3 = $this->recorder->recordAuto(
            $this->student->id,
            $this->component->id,
            $this->period->id,
            92.0,
            GradeRecorder::SOURCE_CBT,
            $this->guru->id,
            null,
            'Remedial CBT'
        );

        $this->assertEquals(92.0, (float) $g3->value);
        $this->assertEquals(85.0, (float) $g3->manual_value);
        $this->assertEquals(92.0, (float) $g3->auto_value);
        $this->assertSame(GradeRecorder::SOURCE_CBT, $g3->source);

        // Nilai otomatis lebih rendah (75) tidak menurunkan auto_value 92
        $g4 = $this->recorder->recordAuto(
            $this->student->id,
            $this->component->id,
            $this->period->id,
            75.0,
            GradeRecorder::SOURCE_ASSIGNMENT,
            $this->guru->id,
            null,
            'Tugas susulan lebih rendah'
        );

        $this->assertEquals(92.0, (float) $g4->value);
        $this->assertEquals(92.0, (float) $g4->auto_value);
        $this->assertSame(4, GradeHistory::where('grade_id', $g4->id)->count());
    }

    public function test_t2b_conc_03_outer_transaction_rollback_reverts_grade_and_history_atomically(): void
    {
        $this->assertDatabaseMissing('grades', [
            'student_id' => $this->student->id,
            'component_id' => $this->component->id,
            'period_id' => $this->period->id,
        ]);

        $thrown = false;
        try {
            DB::transaction(function () {
                $this->recorder->recordManual(
                    $this->student->id,
                    $this->component->id,
                    $this->period->id,
                    88.0,
                    $this->guru->id,
                    'Akan di-rollback'
                );

                throw new \RuntimeException('Simulasi kegagalan setelah GradeRecorder');
            });
        } catch (\RuntimeException $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
        $this->assertSame(0, Grade::where('student_id', $this->student->id)
            ->where('component_id', $this->component->id)
            ->where('period_id', $this->period->id)
            ->count());
        $this->assertSame(0, GradeHistory::where('student_id', $this->student->id)
            ->where('component_id', $this->component->id)
            ->where('period_id', $this->period->id)
            ->count());
    }

    public function test_t2b_conc_03_nested_savepoint_rollback_preserves_prior_committed_grade(): void
    {
        $initial = $this->recorder->recordManual(
            $this->student->id,
            $this->component->id,
            $this->period->id,
            82.0,
            $this->guru->id,
            'Nilai awal'
        );

        DB::transaction(function () {
            try {
                DB::transaction(function () {
                    $this->recorder->recordAuto(
                        $this->student->id,
                        $this->component->id,
                        $this->period->id,
                        95.0,
                        GradeRecorder::SOURCE_CBT,
                        $this->guru->id,
                        null,
                        'Inner savepoint gagal'
                    );

                    throw new \RuntimeException('Rollback savepoint dalam');
                });
            } catch (\RuntimeException $e) {
                // Savepoint dalam dibatalkan; transaksi luar tetap berlanjut.
            }
        });

        $fresh = $initial->fresh();
        $this->assertEquals(82.0, (float) $fresh->value);
        $this->assertEquals(82.0, (float) $fresh->manual_value);
        $this->assertNull($fresh->auto_value);
        $this->assertSame(1, GradeHistory::where('grade_id', $fresh->id)->count());
    }
}
