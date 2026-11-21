<?php

namespace Tests\Feature\Rpt;

use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\Attendance;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradeLevel;
use App\Models\GradePeriod;
use App\Models\LibraryBook;
use App\Models\Loan;
use App\Models\PointType;
use App\Models\PPDBTrack;
use App\Models\PPDBWave;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentPoint;
use App\Models\Subject;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $studentUser;

    protected Student $student;

    protected SchoolClass $class;

    protected AcademicYear $academicYear;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $this->adminUser = User::create([
            'username' => 'admin_rpt',
            'email' => 'admin_rpt@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->studentUser = User::create([
            'username' => 'siswa_rpt',
            'email' => 'siswa_rpt@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($roleStudent->id);

        $this->academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $gradeLevel = GradeLevel::create([
            'name' => 'Kelas 10',
            'level' => 10,
        ]);

        $this->class = SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $gradeLevel->id,
            'name' => '10-MIPA-1',
            'capacity' => 36,
        ]);

        $this->student = Student::create([
            'user_id' => $this->studentUser->id,
            'nis' => '80001',
            'nisn' => '0080001',
            'nik_enc' => '3578010101018001',
            'full_name' => 'Ahmad Rapor',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2009-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'active',
        ]);

        DB::table('class_students')->insert([
            'id' => (string) Str::uuid(),
            'class_id' => $this->class->id,
            'student_id' => $this->student->id,
            'joined_at' => '2026-07-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_be_rpt_01_unauthenticated_request_returns_401(): void
    {
        $res1 = $this->getJson('/api/v1/reports/attendance');
        $res1->assertStatus(401);

        $res2 = $this->getJson('/api/v1/reports/attendance/export');
        $res2->assertStatus(401);
    }

    public function test_be_rpt_01_regular_student_cannot_access_reports(): void
    {
        Sanctum::actingAs($this->studentUser);

        $res = $this->getJson('/api/v1/reports/attendance');
        $res->assertStatus(403);

        $resExport = $this->getJson('/api/v1/reports/grades/export');
        $resExport->assertStatus(403);
    }

    public function test_be_rpt_01_invalid_report_type_returns_422(): void
    {
        Sanctum::actingAs($this->adminUser);

        $res = $this->getJson('/api/v1/reports/unknown_type');
        $res->assertStatus(422)
            ->assertJsonPath('error.code', 'HTTP_ERROR');
    }

    public function test_be_rpt_01_staff_can_view_attendance_report(): void
    {
        Sanctum::actingAs($this->adminUser);

        Attendance::create([
            'student_id' => $this->student->id,
            'att_date' => '2026-09-01',
            'status' => 'hadir',
            'source' => 'kelas',
        ]);
        Attendance::create([
            'student_id' => $this->student->id,
            'att_date' => '2026-09-02',
            'status' => 'hadir',
            'source' => 'kelas',
        ]);
        Attendance::create([
            'student_id' => $this->student->id,
            'att_date' => '2026-09-03',
            'status' => 'sakit',
            'source' => 'kelas',
            'note' => 'Demam flu',
        ]);

        $res = $this->getJson('/api/v1/reports/attendance');
        $res->assertStatus(200)
            ->assertJsonPath('data.summary.total_records', 3)
            ->assertJsonPath('data.summary.hadir_count', 2)
            ->assertJsonPath('data.summary.sakit_count', 1)
            ->assertJsonPath('data.summary.attendance_rate', 66.67);

        $this->assertNotEmpty($res->json('data.data'));
    }

    public function test_be_rpt_01_staff_can_filter_attendance_report_by_date_and_class(): void
    {
        Sanctum::actingAs($this->adminUser);

        Attendance::create([
            'student_id' => $this->student->id,
            'att_date' => '2026-08-10',
            'status' => 'hadir',
        ]);
        Attendance::create([
            'student_id' => $this->student->id,
            'att_date' => '2026-09-15',
            'status' => 'hadir',
        ]);

        $res = $this->getJson('/api/v1/reports/attendance?start_date=2026-09-01&end_date=2026-09-30&class_id='.$this->class->id);
        $res->assertStatus(200)
            ->assertJsonPath('data.summary.total_records', 1);
    }

    public function test_be_rpt_01_staff_can_view_grades_report(): void
    {
        Sanctum::actingAs($this->adminUser);

        $subject = Subject::create([
            'name' => 'Matematika',
            'code' => 'MTK',
            'subject_group' => 'wajib',
        ]);

        $sem = Semester::create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'Semester 1',
            'semester_type' => 'ganjil',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $period = GradePeriod::create([
            'semester_id' => $sem->id,
            'month' => 9,
            'status' => 'open',
        ]);

        $component = GradeComponent::create([
            'subject_id' => $subject->id,
            'semester_id' => $sem->id,
            'code' => 'UTS',
            'name' => 'Ujian Tengah Semester',
            'weight' => 30,
        ]);

        Grade::create([
            'student_id' => $this->student->id,
            'component_id' => $component->id,
            'period_id' => $period->id,
            'value' => 85.00,
            'source' => 'manual',
        ]);

        $res = $this->getJson('/api/v1/reports/grades');
        $res->assertStatus(200)
            ->assertJsonPath('data.summary.total_grades', 1)
            ->assertJsonPath('data.summary.average_score', 85)
            ->assertJsonPath('data.summary.passing_count', 1)
            ->assertJsonPath('data.summary.failing_count', 0);
    }

    public function test_be_rpt_01_staff_can_view_ppdb_report(): void
    {
        Sanctum::actingAs($this->adminUser);

        $wave = PPDBWave::create([
            'name' => 'Gelombang 1',
            'open_at' => '2026-01-01 00:00:00',
            'close_at' => '2026-03-31 23:59:59',
            'result_publish_at' => '2026-04-05 08:00:00',
            'status' => 'aktif',
        ]);

        $track = PPDBTrack::create([
            'wave_id' => $wave->id,
            'code' => 'PRESTASI',
            'name' => 'Jalur Prestasi',
            'quota' => 50,
        ]);

        Applicant::create([
            'wave_id' => $wave->id,
            'track_id' => $track->id,
            'registration_no' => 'REG-001',
            'nik_enc' => '3578010101010099',
            'full_name' => 'Calon Siswa 1',
            'birth_date' => '2010-05-05',
            'status' => 'verified',
            'score' => 92.5,
        ]);

        Applicant::create([
            'wave_id' => $wave->id,
            'track_id' => $track->id,
            'registration_no' => 'REG-002',
            'nik_enc' => '3578010101010098',
            'full_name' => 'Calon Siswa 2',
            'birth_date' => '2010-06-06',
            'status' => 'rejected',
            'score' => 60.0,
        ]);

        $res = $this->getJson('/api/v1/reports/ppdb');
        $res->assertStatus(200)
            ->assertJsonPath('data.summary.total_applicants', 2)
            ->assertJsonPath('data.summary.by_status.verified', 1)
            ->assertJsonPath('data.summary.by_status.rejected', 1)
            ->assertJsonPath('data.summary.average_score', 76.25);
    }

    public function test_be_rpt_01_staff_can_view_student_points_report(): void
    {
        Sanctum::actingAs($this->adminUser);

        $typePrestasi = PointType::create([
            'kind' => 'prestasi',
            'name' => 'Juara 1 Lomba Sains',
            'points' => 25,
            'severity' => 'tinggi',
        ]);

        $typePelanggaran = PointType::create([
            'kind' => 'pelanggaran',
            'name' => 'Terlambat Masuk Sekolah',
            'points' => 5,
            'severity' => 'rendah',
        ]);

        StudentPoint::create([
            'student_id' => $this->student->id,
            'point_type_id' => $typePrestasi->id,
            'recorded_by' => $this->adminUser->id,
            'point_date' => '2026-09-10',
            'notes' => 'Olimpiade Kota',
        ]);

        StudentPoint::create([
            'student_id' => $this->student->id,
            'point_type_id' => $typePelanggaran->id,
            'recorded_by' => $this->adminUser->id,
            'point_date' => '2026-09-12',
            'notes' => 'Terlambat 15 menit',
        ]);

        $res = $this->getJson('/api/v1/reports/student_points');
        $res->assertStatus(200)
            ->assertJsonPath('data.summary.total_events', 2)
            ->assertJsonPath('data.summary.total_prestasi_points', 25)
            ->assertJsonPath('data.summary.total_pelanggaran_points', 5);
    }

    public function test_be_rpt_01_staff_can_view_tickets_report(): void
    {
        Sanctum::actingAs($this->adminUser);

        Ticket::create([
            'reporter_id' => $this->adminUser->id,
            'description' => 'Proyektor Ruang 101 Mati',
            'priority' => 'tinggi',
            'status' => 'pending',
        ]);

        Ticket::create([
            'reporter_id' => $this->adminUser->id,
            'description' => 'Pintu Kamar Mandi Rusak',
            'priority' => 'sedang',
            'status' => 'selesai',
        ]);

        $res = $this->getJson('/api/v1/reports/tickets');
        $res->assertStatus(200)
            ->assertJsonPath('data.summary.total_tickets', 2)
            ->assertJsonPath('data.summary.pending_count', 1)
            ->assertJsonPath('data.summary.selesai_count', 1);
    }

    public function test_be_rpt_01_staff_can_view_loans_report(): void
    {
        Sanctum::actingAs($this->adminUser);

        $book = LibraryBook::create([
            'title' => 'Fisika Dasar Jilid 1',
            'isbn' => '978-602-0001-01',
            'author' => 'Halliday & Resnick',
            'stock' => 10,
        ]);

        Loan::create([
            'book_id' => $book->id,
            'borrower_id' => $this->studentUser->id,
            'loan_date' => '2026-09-01',
            'due_date' => '2026-09-08',
            'returned_at' => '2026-09-07',
            'fine_amount' => 0,
        ]);

        Loan::create([
            'book_id' => $book->id,
            'borrower_id' => $this->studentUser->id,
            'loan_date' => '2026-09-10',
            'due_date' => '2026-09-17',
            'returned_at' => null,
            'fine_amount' => 5000,
        ]);

        $res = $this->getJson('/api/v1/reports/loans');
        $res->assertStatus(200)
            ->assertJsonPath('data.summary.total_loans', 2)
            ->assertJsonPath('data.summary.returned_loans', 1)
            ->assertJsonPath('data.summary.active_loans', 1);
    }

    public function test_be_rpt_01_staff_can_export_report_as_csv(): void
    {
        Sanctum::actingAs($this->adminUser);

        Attendance::create([
            'student_id' => $this->student->id,
            'att_date' => '2026-09-01',
            'status' => 'hadir',
            'source' => 'kelas',
            'note' => 'Hadir tepat waktu',
        ]);

        $res = $this->get('/api/v1/reports/attendance/export?format=csv');
        $res->assertStatus(200);
        $res->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('LAPORAN REKAPITULASI: ATTENDANCE', $res->getContent());
        $this->assertStringContainsString('Ahmad Rapor', $res->getContent());
    }

    public function test_be_rpt_01_report_generator_applies_multi_tenant_isolation_per_school(): void
    {
        $schoolA = (string) Str::uuid();
        $schoolB = (string) Str::uuid();

        $this->adminUser->update(['school_id' => $schoolA]);
        $this->student->update(['school_id' => $schoolA]);

        // Student in School B
        $userStudentB = User::create([
            'username' => 'siswa_sekolah_b',
            'email' => 'siswa_b@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $studentB = Student::create([
            'user_id' => $userStudentB->id,
            'school_id' => $schoolB,
            'nis' => '88888',
            'nisn' => '0088888888',
            'nik_enc' => '3578010101018002',
            'full_name' => 'Budi Sekolah B',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2009-02-02',
            'entry_date' => '2026-07-01',
            'status' => 'active',
        ]);

        Attendance::create([
            'student_id' => $this->student->id,
            'att_date' => '2026-09-01',
            'status' => 'hadir',
        ]);

        Attendance::create([
            'student_id' => $studentB->id,
            'att_date' => '2026-09-01',
            'status' => 'hadir',
        ]);

        Sanctum::actingAs($this->adminUser);

        $res = $this->getJson('/api/v1/reports/attendance');
        $res->assertStatus(200);

        // Only 1 record from School A should be present
        $this->assertEquals(1, $res->json('data.summary.total_records'));
        $this->assertEquals(1, $res->json('data.meta.total'));
    }
}
