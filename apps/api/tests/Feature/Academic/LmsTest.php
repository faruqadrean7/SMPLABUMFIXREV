<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ClassStudent;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\LearningMaterial;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LmsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $teacher1User;

    protected User $teacher2User;

    protected User $student1User;

    protected User $student2User;

    protected User $guardianUser;

    protected Teacher $teacher1;

    protected Teacher $teacher2;

    protected Student $student1;

    protected Student $student2;

    protected SchoolClass $classA;

    protected SchoolClass $classB;

    protected Subject $subjectMath;

    protected Semester $semester1;

    protected GradeComponent $gradeComponent;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $roleAdmin = Role::firstOrCreate(['code' => 'admin_kurikulum'], ['name' => 'Admin Kurikulum', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Wali Murid', 'is_system' => true]);

        // Users
        $this->adminUser = User::create([
            'username' => 'adminkurikulum',
            'email' => 'kurikulum@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->teacher1User = User::create([
            'username' => 'gurubudi',
            'email' => 'budi@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacher1User->roles()->attach($roleTeacher->id);

        $this->teacher2User = User::create([
            'username' => 'gurusiti',
            'email' => 'siti@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacher2User->roles()->attach($roleTeacher->id);

        $this->student1User = User::create([
            'username' => 'siswa1',
            'email' => 'siswa1@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->student1User->roles()->attach($roleStudent->id);

        $this->student2User = User::create([
            'username' => 'siswa2',
            'email' => 'siswa2@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->student2User->roles()->attach($roleStudent->id);

        $this->guardianUser = User::create([
            'username' => 'walimurid',
            'email' => 'wali@keluarga.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->guardianUser->roles()->attach($roleGuardian->id);

        // Teacher Models
        $this->teacher1 = Teacher::create([
            'user_id' => $this->teacher1User->id,
            'nip' => '198001012005011001',
            'full_name' => 'Budi Walas, M.Pd.',
            'status' => 'tetap',
        ]);

        $this->teacher2 = Teacher::create([
            'user_id' => $this->teacher2User->id,
            'nip' => '198502022006022002',
            'full_name' => 'Siti Guru, S.Pd.',
            'status' => 'tetap',
        ]);

        // Academic Year & Semester
        $academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-15',
            'end_date' => '2027-06-20',
            'is_active' => true,
        ]);

        $this->semester1 = Semester::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'Ganjil 2026/2027',
            'semester_type' => 'ganjil',
            'start_date' => '2026-07-15',
            'end_date' => '2026-12-20',
            'is_active' => true,
        ]);

        $gradeLevel = GradeLevel::create(['name' => 'Kelas 7', 'sort_order' => 7]);

        // Classes
        $this->classA = SchoolClass::create([
            'academic_year_id' => $academicYear->id,
            'grade_level_id' => $gradeLevel->id,
            'homeroom_teacher_id' => $this->teacher1->id,
            'name' => 'VII-A',
            'capacity' => 32,
        ]);

        $this->classB = SchoolClass::create([
            'academic_year_id' => $academicYear->id,
            'grade_level_id' => $gradeLevel->id,
            'homeroom_teacher_id' => $this->teacher2->id,
            'name' => 'VII-B',
            'capacity' => 32,
        ]);

        // Subjects
        $this->subjectMath = Subject::create([
            'code' => 'MAT-7',
            'name' => 'Matematika',
            'subject_group' => 'umum',
        ]);

        // Grade Component
        $this->gradeComponent = GradeComponent::create([
            'subject_id' => $this->subjectMath->id,
            'semester_id' => $this->semester1->id,
            'class_id' => $this->classA->id,
            'code' => 'TUGAS',
            'name' => 'Tugas Harian',
            'weight' => 20.0,
        ]);

        // Students
        $this->student1 = Student::create([
            'user_id' => $this->student1User->id,
            'nis' => '202600001',
            'nisn' => '0012345678',
            'nik_enc' => 'enc_3578010101010001',
            'full_name' => 'Aditya Siswa A',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-05-15',
            'status' => 'aktif',
            'entry_date' => '2026-07-15',
        ]);
        ClassStudent::create([
            'class_id' => $this->classA->id,
            'student_id' => $this->student1->id,
            'joined_at' => Carbon::now(),
        ]);

        $this->student2 = Student::create([
            'user_id' => $this->student2User->id,
            'nis' => '202600002',
            'nisn' => '0012345679',
            'nik_enc' => 'enc_3578010101010002',
            'full_name' => 'Bella Siswa B',
            'gender' => 'P',
            'birth_place' => 'Surabaya',
            'birth_date' => '2012-08-20',
            'status' => 'aktif',
            'entry_date' => '2026-07-15',
        ]);
        ClassStudent::create([
            'class_id' => $this->classB->id,
            'student_id' => $this->student2->id,
            'joined_at' => Carbon::now(),
        ]);

        // Guardian linked to Student 1
        $guardian = Guardian::create([
            'user_id' => $this->guardianUser->id,
            'nik_enc' => 'enc_3578010101010099',
            'full_name' => 'Bapak Aditya',
            'phone' => '081234567890',
        ]);
        $guardian->students()->attach($this->student1->id, ['relationship' => 'ayah']);
    }

    public function test_be_acd_07_teacher_can_create_learning_material_and_students_can_view(): void
    {
        $tokenTeacher = $this->teacher1User->createToken('test')->plainTextToken;
        $fakeFile = UploadedFile::fake()->create('materi-aljabar.pdf', 1024, 'application/pdf');

        $response = $this->withHeader('Authorization', "Bearer {$tokenTeacher}")
            ->post('/api/v1/materials', [
                'class_id' => $this->classA->id,
                'subject_id' => $this->subjectMath->id,
                'title' => 'Pengenalan Variabel dan Konstanta',
                'description' => 'Materi bab 1 bentuk aljabar',
                'file' => $fakeFile,
            ], ['Accept' => 'application/json']);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Pengenalan Variabel dan Konstanta')
            ->assertJsonPath('data.class_id', $this->classA->id);

        $materialId = $response->json('data.id');

        // Siswa 1 (anggota kelas VII-A) melihat daftar materi
        $tokenStudent1 = $this->student1User->createToken('test')->plainTextToken;
        $this->app['auth']->forgetGuards();

        $resList = $this->withHeader('Authorization', "Bearer {$tokenStudent1}")
            ->getJson('/api/v1/materials');

        $resList->assertStatus(200);
        $this->assertCount(1, $resList->json('data'));
        $this->assertEquals('Pengenalan Variabel dan Konstanta', $resList->json('data.0.title'));

        // Siswa 1 melihat detail materi
        $resDetail = $this->withHeader('Authorization', "Bearer {$tokenStudent1}")
            ->getJson("/api/v1/materials/{$materialId}");
        $resDetail->assertStatus(200)
            ->assertJsonPath('data.id', $materialId);
    }

    public function test_be_acd_07_student_outside_class_cannot_view_material_detail_403(): void
    {
        $material = LearningMaterial::create([
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'title' => 'Materi Rahasia Kelas VII-A',
        ]);

        // Siswa 2 dari kelas VII-B mencoba mengakses detail materi kelas VII-A
        $tokenStudent2 = $this->student2User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$tokenStudent2}")
            ->getJson("/api/v1/materials/{$material->id}");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_be_acd_07_teacher_can_create_assignment_with_due_date(): void
    {
        $tokenTeacher = $this->teacher1User->createToken('test')->plainTextToken;
        $fakeFile = UploadedFile::fake()->create('soal-tugas.pdf', 2048, 'application/pdf');
        $dueAt = Carbon::now()->addDays(5)->toDateTimeString();

        $response = $this->withHeader('Authorization', "Bearer {$tokenTeacher}")
            ->post('/api/v1/assignments', [
                'class_id' => $this->classA->id,
                'subject_id' => $this->subjectMath->id,
                'title' => 'Latihan 1: Operasi Aljabar',
                'description' => 'Kerjakan nomor 1 sampai 10 di lembar kerja',
                'due_at' => $dueAt,
                'max_score' => 100,
                'grade_component_id' => $this->gradeComponent->id,
                'file' => $fakeFile,
            ], ['Accept' => 'application/json']);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Latihan 1: Operasi Aljabar')
            ->assertJsonPath('data.grade_component_id', $this->gradeComponent->id);

        $assignmentId = $response->json('data.id');

        $this->assertDatabaseHas('assignments', [
            'id' => $assignmentId,
            'title' => 'Latihan 1: Operasi Aljabar',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->teacher1User->id,
            'action' => 'assignment.created',
        ]);
    }

    public function test_be_acd_07_student_in_class_can_submit_assignment(): void
    {
        $assignment = Assignment::create([
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'title' => 'Tugas Aljabar 1',
            'due_at' => Carbon::now()->addDays(3),
        ]);

        $tokenStudent1 = $this->student1User->createToken('test')->plainTextToken;
        $submissionFile = UploadedFile::fake()->create('jawaban-aditya.pdf', 1024, 'application/pdf');

        $response = $this->withHeader('Authorization', "Bearer {$tokenStudent1}")
            ->post("/api/v1/assignments/{$assignment->id}/submissions", [
                'file' => $submissionFile,
                'notes' => 'Berikut lembar jawaban saya Pak',
            ], ['Accept' => 'application/json']);

        $response->assertStatus(201)
            ->assertJsonPath('data.assignment_id', $assignment->id)
            ->assertJsonPath('data.student_id', $this->student1->id)
            ->assertJsonPath('data.notes', 'Berikut lembar jawaban saya Pak');

        $this->assertDatabaseHas('assignment_submissions', [
            'assignment_id' => $assignment->id,
            'student_id' => $this->student1->id,
        ]);
    }

    public function test_be_acd_07_student_outside_class_cannot_submit_assignment_403(): void
    {
        $assignment = Assignment::create([
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'title' => 'Tugas Khusus Kelas A',
            'due_at' => Carbon::now()->addDays(2),
        ]);

        // Siswa 2 di kelas B mencoba mengumpulkan ke tugas kelas A
        $tokenStudent2 = $this->student2User->createToken('test')->plainTextToken;
        $file = UploadedFile::fake()->create('jawaban-bella.pdf', 512, 'application/pdf');

        $response = $this->withHeader('Authorization', "Bearer {$tokenStudent2}")
            ->post("/api/v1/assignments/{$assignment->id}/submissions", [
                'file' => $file,
            ], ['Accept' => 'application/json']);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_be_acd_07_teacher_can_grade_submission_with_score_and_feedback(): void
    {
        $assignment = Assignment::create([
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'title' => 'Tugas Penilaian 1',
            'due_at' => Carbon::now()->addDays(2),
        ]);

        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->student1->id,
            'notes' => 'Selesai dikerjakan',
            'submitted_at' => Carbon::now(),
        ]);

        $tokenTeacher = $this->teacher1User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$tokenTeacher}")
            ->patchJson("/api/v1/assignments/{$assignment->id}/submissions/{$submission->id}/grade", [
                'score' => 88.50,
                'feedback' => 'Bagus sekali, pemahaman konsep aljabar sangat baik.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.score', '88.50')
            ->assertJsonPath('data.feedback', 'Bagus sekali, pemahaman konsep aljabar sangat baik.');

        $this->assertDatabaseHas('assignment_submissions', [
            'id' => $submission->id,
            'score' => 88.50,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->teacher1User->id,
            'action' => 'assignment.graded',
        ]);
    }

    public function test_be_acd_07_grading_syncs_to_grades_table_when_grade_component_linked(): void
    {
        $assignment = Assignment::create([
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'title' => 'Tugas Yang Terhubung Nilai',
            'due_at' => Carbon::now()->addDays(2),
            'grade_component_id' => $this->gradeComponent->id,
        ]);

        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->student1->id,
            'submitted_at' => Carbon::now(),
        ]);

        $tokenTeacher = $this->teacher1User->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$tokenTeacher}")
            ->patchJson("/api/v1/assignments/{$assignment->id}/submissions/{$submission->id}/grade", [
                'score' => 95.00,
                'feedback' => 'Sempurna',
            ]);

        // Verifikasi tersinkronisasi ke tabel grades
        $this->assertDatabaseHas('grades', [
            'student_id' => $this->student1->id,
            'component_id' => $this->gradeComponent->id,
            'value' => 95.00,
            'source' => 'manual',
            'entered_by' => $this->teacher1User->id,
        ]);
    }

    public function test_be_acd_07_other_teacher_cannot_grade_or_delete_assignment_403(): void
    {
        $assignment = Assignment::create([
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'title' => 'Tugas Guru Budi',
            'due_at' => Carbon::now()->addDays(2),
        ]);

        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->student1->id,
            'submitted_at' => Carbon::now(),
        ]);

        // Guru Siti (teacher2) mencoba menilai tugas Guru Budi
        $tokenTeacher2 = $this->teacher2User->createToken('test')->plainTextToken;

        $resGrade = $this->withHeader('Authorization', "Bearer {$tokenTeacher2}")
            ->patchJson("/api/v1/assignments/{$assignment->id}/submissions/{$submission->id}/grade", [
                'score' => 70,
            ]);
        $resGrade->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // Guru Siti mencoba menghapus tugas Guru Budi
        $resDelete = $this->withHeader('Authorization', "Bearer {$tokenTeacher2}")
            ->deleteJson("/api/v1/assignments/{$assignment->id}");
        $resDelete->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_be_acd_07_student_cannot_resubmit_after_graded_422(): void
    {
        $assignment = Assignment::create([
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'title' => 'Tugas Sudah Dinilai',
            'due_at' => Carbon::now()->addDays(2),
        ]);

        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->student1->id,
            'score' => 90.00,
            'submitted_at' => Carbon::now()->subDay(),
        ]);

        $tokenStudent1 = $this->student1User->createToken('test')->plainTextToken;
        $file = UploadedFile::fake()->create('revisi-dilarang.pdf', 512, 'application/pdf');

        $response = $this->withHeader('Authorization', "Bearer {$tokenStudent1}")
            ->post("/api/v1/assignments/{$assignment->id}/submissions", [
                'file' => $file,
            ], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');
    }

    public function test_be_acd_07_parent_can_view_child_class_assignments(): void
    {
        Assignment::create([
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'title' => 'Tugas Rumah Matematika',
            'due_at' => Carbon::now()->addDays(3),
        ]);

        // Orang tua anak di kelas VII-A
        $tokenGuardian = $this->guardianUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$tokenGuardian}")
            ->getJson('/api/v1/assignments');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Tugas Rumah Matematika', $response->json('data.0.title'));
    }

    public function test_be_acd_07_teacher_can_update_assignment_and_other_teacher_forbidden(): void
    {
        $assignment = Assignment::create([
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'title' => 'Judul Tugas Awal',
            'description' => 'Deskripsi lama',
            'due_at' => Carbon::now()->addDays(5),
            'max_score' => 100,
        ]);

        $tokenTeacher1 = $this->teacher1User->createToken('test')->plainTextToken;
        $tokenTeacher2 = $this->teacher2User->createToken('test')->plainTextToken;

        // Guru 2 mencoba mengedit -> 403
        $resForbidden = $this->withHeader('Authorization', "Bearer {$tokenTeacher2}")
            ->putJson("/api/v1/assignments/{$assignment->id}", [
                'title' => 'Judul Diubah Hacker',
            ]);
        $resForbidden->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // Guru 1 (pembuat) mengedit -> 200 OK
        $this->app['auth']->forgetGuards();
        $resSuccess = $this->withHeader('Authorization', "Bearer {$tokenTeacher1}")
            ->putJson("/api/v1/assignments/{$assignment->id}", [
                'title' => 'Judul Tugas Diperbarui',
                'description' => 'Deskripsi baru yang direvisi',
                'max_score' => 90,
            ]);
        $resSuccess->assertStatus(200)
            ->assertJsonPath('data.title', 'Judul Tugas Diperbarui')
            ->assertJsonPath('data.max_score', '90.00');

        $this->assertDatabaseHas('assignments', [
            'id' => $assignment->id,
            'title' => 'Judul Tugas Diperbarui',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->teacher1User->id,
            'action' => 'assignment.updated',
        ]);
    }

    public function test_be_acd_07_teacher_can_update_learning_material(): void
    {
        $material = LearningMaterial::create([
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'title' => 'Materi Awal',
            'description' => 'Penjelasan awal',
        ]);

        $tokenTeacher1 = $this->teacher1User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$tokenTeacher1}")
            ->putJson("/api/v1/materials/{$material->id}", [
                'title' => 'Materi Bab 2 (Revisi)',
                'description' => 'Penjelasan lengkap materi bab 2',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Materi Bab 2 (Revisi)')
            ->assertJsonPath('data.description', 'Penjelasan lengkap materi bab 2');

        $this->assertDatabaseHas('learning_materials', [
            'id' => $material->id,
            'title' => 'Materi Bab 2 (Revisi)',
        ]);
    }

    public function test_be_acd_07_late_submission_is_flagged_correctly(): void
    {
        // Tugas yang tenggat waktunya sudah lewat kemarin
        $assignment = Assignment::create([
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'title' => 'Tugas Tenggat Kemarin',
            'due_at' => Carbon::now()->subDay(),
        ]);

        $tokenStudent1 = $this->student1User->createToken('test')->plainTextToken;
        $file = UploadedFile::fake()->create('tugas-telat.pdf', 512, 'application/pdf');

        $response = $this->withHeader('Authorization', "Bearer {$tokenStudent1}")
            ->post("/api/v1/assignments/{$assignment->id}/submissions", [
                'file' => $file,
                'notes' => 'Maaf terlambat mengumpulkan Pak',
            ], ['Accept' => 'application/json']);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_late', true)
            ->assertJsonPath('data.submission_timing', 'terlambat')
            ->assertJsonPath('data.status', 'menunggu_penilaian');

        $submissionId = $response->json('data.id');

        // Guru menilai tugas tersebut
        $this->app['auth']->forgetGuards();
        $tokenTeacher = $this->teacher1User->createToken('test')->plainTextToken;
        $resGrade = $this->withHeader('Authorization', "Bearer {$tokenTeacher}")
            ->patchJson("/api/v1/assignments/{$assignment->id}/submissions/{$submissionId}/grade", [
                'score' => 75,
                'feedback' => 'Dikurangi poin karena telat mengumpulkan.',
            ]);

        $resGrade->assertStatus(200)
            ->assertJsonPath('data.status', 'dinilai')
            ->assertJsonPath('data.is_late', true)
            ->assertJsonPath('data.submission_timing', 'terlambat');
    }
}
