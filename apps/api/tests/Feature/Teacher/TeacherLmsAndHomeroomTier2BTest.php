<?php

namespace Tests\Feature\Teacher;

use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\ClassStudent;
use App\Models\File;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradeLevel;
use App\Models\GradePeriod;
use App\Models\Permission;
use App\Models\ReportCard;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeacherLmsAndHomeroomTier2BTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $teacherUser;

    private Teacher $teacher;

    private User $studentUser;

    private Student $student;

    private SchoolClass $class;

    private Subject $subject;

    private Semester $semester;

    private GradePeriod $period;

    private GradeComponent $component;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $this->school = School::create([
            'name' => 'SMP Lab Tier2B6',
            'npsn' => '20534366',
            'address' => 'Jl. Fiktif T2B6 No. 6',
            'phone' => '0341000066',
            'email' => 't2b6@test.local',
        ]);

        $teacherRole = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $studentRole = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        foreach (['academic.assignment.manage', 'academic.grade.write', 'academic.material.manage'] as $permCode) {
            $perm = Permission::firstOrCreate(['code' => $permCode], ['description' => $permCode]);
            $teacherRole->permissions()->syncWithoutDetaching([$perm->id]);
        }

        $this->teacherUser = User::create([
            'school_id' => $this->school->id,
            'username' => 'guru_t2b6_'.uniqid(),
            'email' => 'guru_t2b6_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($teacherRole->id);

        $this->teacher = Teacher::create([
            'user_id' => $this->teacherUser->id,
            'nip' => '198601012026011066',
            'full_name' => 'Guru LMS Tier2B6',
            'status' => 'aktif',
        ]);

        $this->studentUser = User::create([
            'school_id' => $this->school->id,
            'username' => 'siswa_t2b6_'.uniqid(),
            'email' => 'siswa_t2b6_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($studentRole->id);

        $this->student = Student::create([
            'school_id' => $this->school->id,
            'user_id' => $this->studentUser->id,
            'nis' => 'T2B6-001',
            'nisn' => '002266001',
            'nik_enc' => 'enc_t2b6_1',
            'full_name' => 'Siswa LMS T2B6',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2011-04-04',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $ay = AcademicYear::create([
            'school_id' => $this->school->id,
            'name' => '2026/2027 T2B6',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
        ]);
        $this->semester = Semester::create([
            'academic_year_id' => $ay->id,
            'name' => 'Semester Ganjil T2B6',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);
        $this->period = GradePeriod::create([
            'semester_id' => $this->semester->id,
            'month' => 11,
            'status' => 'open',
        ]);

        $gl = GradeLevel::create(['name' => 'Kelas 9 T2B6', 'sort_order' => 9]);
        $this->class = SchoolClass::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $ay->id,
            'grade_level_id' => $gl->id,
            'homeroom_teacher_id' => $this->teacher->id,
            'name' => 'IX-T2B6',
            'capacity' => 30,
        ]);

        ClassStudent::create([
            'class_id' => $this->class->id,
            'student_id' => $this->student->id,
            'joined_at' => '2026-07-01',
        ]);

        $this->subject = Subject::create([
            'code' => 'INF-T2B6',
            'name' => 'Informatika',
            'subject_group' => 'wajib',
        ]);

        TeachingAssignment::create([
            'teacher_id' => $this->teacher->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'semester_id' => $this->semester->id,
        ]);

        $this->component = GradeComponent::create([
            'subject_id' => $this->subject->id,
            'semester_id' => $this->semester->id,
            'class_id' => $this->class->id,
            'code' => 'TGS-INF',
            'name' => 'Tugas Praktikum',
            'weight' => 50.0,
        ]);
    }

    public function test_p0_16_rem_01_submissions_returns_zero_score_is_late_notes_and_signed_file_after_multipart_submit(): void
    {
        $assignment = Assignment::create([
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'grade_component_id' => $this->component->id,
            'title' => 'Laporan Praktikum T2B6',
            'description' => 'Unggah laporan PDF',
            'due_at' => Carbon::now()->subHour(), // sudah lewat tenggat -> terlambat
            'max_score' => 100,
        ]);

        $pdfFile = UploadedFile::fake()->createWithContent(
            'laporan_siswa.pdf',
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF"
        );

        // Siswa upload berkas multipart nyata ke POST /api/v1/me/tasks/{id}/submit
        $submitRes = $this->actingAs($this->studentUser, 'sanctum')
            ->post('/api/v1/me/tasks/'.$assignment->id.'/submit', [
                'file' => $pdfFile,
                'notes' => 'Catatan pengerjaan siswa',
                'drive_link' => 'https://drive.google.com/file/d/contoh',
            ]);

        $submitRes->assertStatus(201);
        $submissionId = $submitRes->json('data.submission.id');

        // Guru memberi nilai 0 (skor valid!)
        AssignmentSubmission::where('id', $submissionId)->update(['score' => 0, 'feedback' => 'Belum sesuai instruksi']);

        // Guru melihat submissions
        $this->app['auth']->forgetGuards();
        $subsRes = $this->actingAs($this->teacherUser, 'sanctum')
            ->getJson("/api/v1/me/teacher/assignments/{$assignment->id}/submissions");

        $subsRes->assertStatus(200)
            ->assertJsonPath('data.0.score', 0)
            ->assertJsonPath('data.0.status', 'graded')
            ->assertJsonPath('data.0.is_late', true)
            ->assertJsonPath('data.0.drive_link', 'https://drive.google.com/file/d/contoh')
            ->assertJsonPath('data.0.file.mime_type', 'application/pdf');

        $downloadUrl = $subsRes->json('data.0.file.download_url');
        $this->assertNotEmpty($downloadUrl);
        $this->assertStringContainsString('/api/v1/files/', (string) $downloadUrl);

        // Pastikan URL unduhan bertanda tangan benar-benar mengunduh berkas oleh guru (200 OK)
        $parsedPath = parse_url($downloadUrl, PHP_URL_PATH).'?'.parse_url($downloadUrl, PHP_URL_QUERY);
        $dlRes = $this->get($parsedPath);
        $dlRes->assertStatus(200);
    }

    public function test_p0_16_rem_02_homeroom_returns_real_attendance_grade_and_report_lock_aggregates(): void
    {
        // Presensi: 4 hari hadir, 1 hari sakit => 80.0% kehadiran, 1 sakit, 0 izin, 0 alpa
        for ($d = 1; $d <= 4; $d++) {
            Attendance::create([
                'student_id' => $this->student->id,
                'att_date' => '2026-08-0'.$d,
                'source' => 'gerbang',
                'status' => 'hadir',
            ]);
        }
        Attendance::create([
            'student_id' => $this->student->id,
            'att_date' => '2026-08-05',
            'source' => 'gerbang',
            'status' => 'sakit',
        ]);

        // Nilai: 72 & 80 => rata-rata 76.0
        Grade::create([
            'student_id' => $this->student->id,
            'component_id' => $this->component->id,
            'period_id' => $this->period->id,
            'value' => 72.0,
            'source' => 'manual',
            'entered_by' => $this->teacherUser->id,
        ]);
        $comp2 = GradeComponent::create([
            'subject_id' => $this->subject->id,
            'semester_id' => $this->semester->id,
            'class_id' => $this->class->id,
            'code' => 'SAS-INF',
            'name' => 'SAS',
            'weight' => 50.0,
        ]);
        Grade::create([
            'student_id' => $this->student->id,
            'component_id' => $comp2->id,
            'period_id' => $this->period->id,
            'value' => 80.0,
            'source' => 'manual',
            'entered_by' => $this->teacherUser->id,
        ]);

        ReportCard::create([
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
            'data_snapshot' => ['student' => ['full_name' => $this->student->full_name]],
            'status' => 'terkunci',
            'verify_token' => 't2b6-locked-token',
        ]);

        $res = $this->actingAs($this->teacherUser, 'sanctum')
            ->getJson('/api/v1/me/teacher/homeroom');

        $res->assertStatus(200)
            ->assertJsonPath('data.semester', 'Semester Ganjil T2B6')
            ->assertJsonPath('data.isReportCardLocked', true)
            ->assertJsonPath('data.attendanceRecap.averageRate', 80)
            ->assertJsonPath('data.attendanceRecap.sickDays', 1)
            ->assertJsonPath('data.attendanceRecap.leaveDays', 0)
            ->assertJsonPath('data.attendanceRecap.unexcusedDays', 0)
            ->assertJsonPath('data.students.0.attendanceRate', 80)
            ->assertJsonPath('data.students.0.averageScore', 76);
    }

    public function test_p1_10_teacher_material_upload_stores_real_pdf_and_returns_valid_dates_and_download_url(): void
    {
        $pdfFile = UploadedFile::fake()->createWithContent(
            'modul_informatika_bab1.pdf',
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF"
        );

        $uploadRes = $this->actingAs($this->teacherUser, 'sanctum')
            ->post('/api/v1/me/teacher/materials', [
                'title' => 'Modul Algoritma Dasar',
                'subject_id' => $this->subject->id,
                'description' => 'Pembelajaran algoritma pemrograman',
                'category' => 'modul_ajar',
                'version' => 'v2.0',
                'file' => $pdfFile,
            ]);

        $uploadRes->assertStatus(201)
            ->assertJsonPath('data.title', 'Modul Algoritma Dasar')
            ->assertJsonPath('data.category', 'modul_ajar')
            ->assertJsonPath('data.version', 'v2.0')
            ->assertJsonPath('data.file.mime_type', 'application/pdf');

        $this->assertNotEmpty($uploadRes->json('data.created_at'));
        $this->assertNotEmpty($uploadRes->json('data.createdAt'));
        $objectKey = $uploadRes->json('data.file.object_key');
        $this->assertNotEquals('materials/default-modul.pdf', $objectKey);
        Storage::disk('local')->assertExists($objectKey);

        $downloadUrl = $uploadRes->json('data.file.download_url');
        $this->assertNotEmpty($downloadUrl);

        // Siswa yang terdaftar di kelas mapel juga bisa mengunduh materi via GET signed URL
        $fileId = $uploadRes->json('data.file.id');
        $this->app['auth']->forgetGuards();
        $urlRes = $this->actingAs($this->studentUser, 'sanctum')
            ->getJson("/api/v1/files/{$fileId}/url");
        $urlRes->assertStatus(200);

        $dlPath = parse_url($urlRes->json('data.url'), PHP_URL_PATH).'?'.parse_url($urlRes->json('data.url'), PHP_URL_QUERY);
        $this->get($dlPath)->assertStatus(200);

        // File ID milik user lain tidak boleh di-attach oleh guru -> 403
        $otherFile = File::create([
            'school_id' => $this->school->id,
            'owner_id' => $this->studentUser->id,
            'owner_type' => 'Student',
            'bucket' => 'teaching-materials',
            'object_key' => 'private/other.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 500,
            'visibility' => 'privat',
        ]);

        $this->app['auth']->forgetGuards();
        $idorRes = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/me/teacher/materials', [
                'title' => 'Materi Bajakan',
                'subject_id' => $this->subject->id,
                'file_id' => $otherFile->id,
            ]);
        $idorRes->assertStatus(403);
    }
}
