<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\ClassStudent;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradeLevel;
use App\Models\GradePeriod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Academic\AttendanceAggregator;
use App\Services\ProgressReportService;
use App\Services\ReportCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportWeightAndAttendanceTier2ATest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $adminUser;

    private Student $student;

    private Subject $subject;

    private Semester $sem1;

    private Semester $sem2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'SMP Lab Akademik Tier 2A',
            'npsn' => '20534014',
            'address' => 'Jl. Fiktif Akademik',
            'phone' => '0341000014',
            'email' => 'acad2a@test.local',
        ]);

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        foreach (['report.card.manage', 'grade.manage', 'bk.early_warning.manage'] as $code) {
            $p = Permission::firstOrCreate(['code' => $code], ['description' => $code]);
            $roleAdmin->permissions()->syncWithoutDetaching([$p->id]);
        }

        $this->adminUser = User::create([
            'school_id' => $this->school->id,
            'username' => 'admin_acad_t2a_'.uniqid(),
            'email' => 'admin_acad_t2a_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $teacher = Teacher::create([
            'school_id' => $this->school->id,
            'user_id' => $this->adminUser->id,
            'nip' => '198501012010011001',
            'full_name' => 'Guru Fiktif Akademik',
            'gender' => 'L',
            'status' => 'aktif',
        ]);

        $ay = AcademicYear::create([
            'school_id' => $this->school->id,
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $this->sem1 = Semester::create([
            'academic_year_id' => $ay->id,
            'name' => 'Ganjil',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $this->sem2 = Semester::create([
            'academic_year_id' => $ay->id,
            'name' => 'Genap',
            'start_date' => '2027-01-01',
            'end_date' => '2027-06-30',
            'is_active' => false,
        ]);

        $gl = GradeLevel::create([
            'school_id' => $this->school->id,
            'level_number' => 7,
            'name' => 'Kelas 7',
        ]);

        $class = SchoolClass::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $ay->id,
            'grade_level_id' => $gl->id,
            'name' => '7A',
            'homeroom_teacher_id' => $teacher->id,
        ]);

        $this->student = Student::create([
            'school_id' => $this->school->id,
            'nis' => 'NIS-AC2A-01',
            'nisn' => '0055667788',
            'nik_enc' => '3573010101120222',
            'full_name' => 'Siswa Fiktif Rapor',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2012-06-06',
            'religion' => 'Islam',
            'address' => 'Jl. Fiktif Rapor',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);
        ClassStudent::create([
            'class_id' => $class->id,
            'student_id' => $this->student->id,
            'joined_at' => '2026-07-01',
        ]);

        $this->subject = Subject::create([
            'school_id' => $this->school->id,
            'code' => 'IPA-T2A',
            'name' => 'Ilmu Pengetahuan Alam',
            'subject_group' => 'wajib',
        ]);
    }

    public function test_p0_14_report_card_averages_each_component_across_months_before_applying_weight_and_blocks_cross_semester_leak(): void
    {
        // Komponen Tugas (bobot 40) & SAS (bobot 60) di Semester 1
        $compTugas = GradeComponent::create([
            'subject_id' => $this->subject->id,
            'semester_id' => $this->sem1->id,
            'code' => 'TUGAS',
            'name' => 'Tugas Harian',
            'weight' => 40,
        ]);
        $compSas = GradeComponent::create([
            'subject_id' => $this->subject->id,
            'semester_id' => $this->sem1->id,
            'code' => 'SAS',
            'name' => 'SAS',
            'weight' => 60,
        ]);

        // 5 periode bulanan di Semester 1 (Agustus - Desember): semua Tugas = 80
        foreach ([8, 9, 10, 11, 12] as $month) {
            $period = GradePeriod::create([
                'semester_id' => $this->sem1->id,
                'month' => $month,
                'status' => 'open',
            ]);
            Grade::create([
                'student_id' => $this->student->id,
                'component_id' => $compTugas->id,
                'period_id' => $period->id,
                'value' => 80.0,
                'recorded_by' => $this->adminUser->id,
            ]);

            if ($month === 12) {
                Grade::create([
                    'student_id' => $this->student->id,
                    'component_id' => $compSas->id,
                    'period_id' => $period->id,
                    'value' => 100.0,
                    'recorded_by' => $this->adminUser->id,
                ]);
            }
        }

        // Nilai pada periode Semester 2 (walau komponen menunjuk sem1) TIDAK BOLEH bocor ke rapor Semester 1!
        $sem2Period = GradePeriod::create([
            'semester_id' => $this->sem2->id,
            'month' => 2,
            'status' => 'open',
        ]);
        Grade::create([
            'student_id' => $this->student->id,
            'component_id' => $compTugas->id,
            'period_id' => $sem2Period->id,
            'value' => 10.0,
            'recorded_by' => $this->adminUser->id,
        ]);

        $snapshot = app(ReportCardService::class)->compileSemesterSnapshot($this->student, $this->sem1);
        $ipa = collect($snapshot['subjects'])->firstWhere('subject_id', $this->subject->id);

        // (80 * 40 + 100 * 60) / (40 + 60) = 92.00 (bukan 84.62 dan tanpa kebocoran nilai 10.0 Semester 2)
        $this->assertNotNull($ipa);
        $this->assertEquals(92.0, $ipa['final_score']);
    }

    public function test_p0_26_attendance_aggregator_prevents_gate_plus_class_inflation_and_year_leak_and_counts_terlambat(): void
    {
        // Hari 1 (2026-08-10): 1 gerbang alpa + 5 sesi kelas alpa => WAJIB dihitung TEPAT 1 hari alpa!
        Attendance::create([
            'student_id' => $this->student->id,
            'att_date' => '2026-08-10',
            'status' => 'alpa',
            'source' => 'gerbang',
            'recorded_by' => $this->adminUser->id,
        ]);
        for ($i = 0; $i < 5; $i++) {
            Attendance::create([
                'student_id' => $this->student->id,
                'att_date' => '2026-08-10',
                'status' => 'alpa',
                'source' => 'kelas',
                'recorded_by' => $this->adminUser->id,
            ]);
        }

        // Hari 2 (2026-08-11): gerbang terlambat => hadir+terlambat dihitung hadir secara persentase, dan terlambat = 1
        Attendance::create([
            'student_id' => $this->student->id,
            'att_date' => '2026-08-11',
            'status' => 'terlambat',
            'source' => 'gerbang',
            'recorded_by' => $this->adminUser->id,
        ]);

        // Hari 3 (2026-08-12): 3 sesi kelas hadir tanpa gerbang => WAJIB dihitung TEPAT 1 hari hadir
        for ($i = 0; $i < 3; $i++) {
            Attendance::create([
                'student_id' => $this->student->id,
                'att_date' => '2026-08-12',
                'status' => 'hadir',
                'source' => 'kelas',
                'recorded_by' => $this->adminUser->id,
            ]);
        }

        // Data Agustus TAHUN LALU (2025-08-10) => TIDAK BOLEH masuk ke laporan perkembangan Agustus 2026
        Attendance::create([
            'student_id' => $this->student->id,
            'att_date' => '2025-08-10',
            'status' => 'alpa',
            'source' => 'gerbang',
            'recorded_by' => $this->adminUser->id,
        ]);

        // 1. Verifikasi ReportCardService
        $rcSnapshot = app(ReportCardService::class)->compileSemesterSnapshot($this->student, $this->sem1);
        $this->assertSame(1, $rcSnapshot['attendance']['alpa']);
        $this->assertSame(1, $rcSnapshot['attendance']['terlambat']);
        $this->assertSame(1, $rcSnapshot['attendance']['hadir']);
        $this->assertSame(3, $rcSnapshot['attendance']['total_recorded']);
        $this->assertEquals(66.67, $rcSnapshot['attendance']['percentage']);

        // 2. Verifikasi ProgressReportService (Agustus 2026 tidak tercampur Agustus 2025)
        $periodAug = GradePeriod::create([
            'semester_id' => $this->sem1->id,
            'month' => 8,
            'status' => 'open',
        ]);
        $prSnapshot = app(ProgressReportService::class)->compileSnapshot($this->student, $periodAug);
        $this->assertSame(1, $prSnapshot['attendance']['alpa']);
        $this->assertSame(1, $prSnapshot['attendance']['terlambat']);
        $this->assertSame(1, $prSnapshot['attendance']['hadir']);
        $this->assertSame(3, $prSnapshot['attendance']['total_recorded']);
        $this->assertEquals(66.67, $prSnapshot['attendance']['percentage']);

        // 3. Verifikasi AttendanceAggregator pada skenario 1 gerbang + 6 kelas pada tanggal baru (EarlyWarning consistency)
        $recentDate = now()->subDays(2)->toDateString();
        Attendance::create([
            'student_id' => $this->student->id,
            'att_date' => $recentDate,
            'status' => 'alpa',
            'source' => 'gerbang',
            'recorded_by' => $this->adminUser->id,
        ]);
        for ($i = 0; $i < 6; $i++) {
            Attendance::create([
                'student_id' => $this->student->id,
                'att_date' => $recentDate,
                'status' => 'alpa',
                'source' => 'kelas',
                'recorded_by' => $this->adminUser->id,
            ]);
        }

        $ewSummary = app(AttendanceAggregator::class)->summarizeDaily(
            Attendance::where('student_id', $this->student->id)->where('att_date', $recentDate)->get(),
            1
        );
        $this->assertSame(1, $ewSummary['alpa']);
        $this->assertSame(1, $ewSummary['total_recorded']);
    }
}
