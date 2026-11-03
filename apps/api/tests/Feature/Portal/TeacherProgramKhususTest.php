<?php

namespace Tests\Feature\Portal;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\GradeLevel;
use App\Models\ProgramTask;
use App\Models\ProgramTaskSubmission;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TeacherProgramKhususTest extends TestCase
{
    use RefreshDatabase;

    protected AcademicYear $academicYear;

    protected GradeLevel $gradeLevel;

    protected Role $teacherRole;

    protected Role $studentRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacherRole = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
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

    private function createTeacher(string $suffix = ''): array
    {
        $unique = (string) rand(10000, 99999).$suffix;
        $user = User::create([
            'username' => 'guru_'.$unique,
            'email' => 'guru_'.$unique.'@labschool.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $user->roles()->attach($this->teacherRole->id);

        $teacher = Teacher::create([
            'user_id' => $user->id,
            'nip' => '19850101'.$unique,
            'nuptk' => '12345678'.$unique,
            'full_name' => 'Guru Pengajar '.$unique,
            'position' => 'Guru Pembina',
            'status' => 'aktif',
        ]);

        return ['user' => $user, 'teacher' => $teacher];
    }

    private function createClassWithProgram(string $name, string $programType, ?Teacher $homeroomTeacher = null): SchoolClass
    {
        return SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->gradeLevel->id,
            'name' => $name,
            'capacity' => 30,
            'program_type' => $programType,
            'homeroom_teacher_id' => $homeroomTeacher?->id,
        ]);
    }

    private function createStudentInClass(SchoolClass $class, string $programType): array
    {
        $unique = (string) rand(10000, 99999);
        $user = User::create([
            'username' => 'siswa_'.$unique,
            'email' => 'siswa_'.$unique.'@labschool.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $user->roles()->attach($this->studentRole->id);

        $student = Student::create([
            'user_id' => $user->id,
            'nis' => $unique,
            'nisn' => '00'.$unique,
            'nik_enc' => '35730101'.str_pad($unique, 8, '0', STR_PAD_LEFT),
            'full_name' => 'Siswa '.$unique,
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-05-10',
            'status' => 'aktif',
            'entry_date' => '2024-07-15',
            'program_type' => $programType,
            'class_id' => $class->id,
        ]);

        ClassStudent::create([
            'class_id' => $class->id,
            'student_id' => $student->id,
            'academic_year_id' => $this->academicYear->id,
            'status' => 'active',
            'joined_at' => '2024-07-15',
        ]);

        return ['user' => $user, 'student' => $student];
    }

    // =========================================================================
    // AC1: HAK AKSES & ANTI-IDOR (GURU HANYA BISA KELOLA KELAS/SISWA BINAANNYA)
    // =========================================================================

    public function test_be_prog_02_ac1_teacher_can_create_material_for_assigned_class(): void
    {
        $teacherData = $this->createTeacher('1');
        $classTahfidz = $this->createClassWithProgram('VII-Tahfidz-A', 'tahfidz', $teacherData['teacher']);

        $payload = [
            'program_type' => 'tahfidz',
            'title' => 'Kaidah Tajwid Makhraj Huruf',
            'material_type' => 'tajwid',
            'class_id' => $classTahfidz->id,
            'description' => 'Materi tajwid untuk halaqah kelas VII',
        ];

        $response = $this->actingAs($teacherData['user'])
            ->postJson('/api/v1/program/materials', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('program_materials', [
            'title' => 'Kaidah Tajwid Makhraj Huruf',
            'class_id' => $classTahfidz->id,
            'created_by' => $teacherData['user']->id,
        ]);
    }

    public function test_be_prog_02_ac1_anti_idor_teacher_cannot_create_material_for_unassigned_class(): void
    {
        $teacherDataA = $this->createTeacher('A');
        $teacherDataB = $this->createTeacher('B');

        $classTaughtByA = $this->createClassWithProgram('VII-Tahfidz-1', 'tahfidz', $teacherDataA['teacher']);
        $classTaughtByB = $this->createClassWithProgram('VII-Tahfidz-2', 'tahfidz', $teacherDataB['teacher']);

        // Guru A mencoba membuat materi untuk kelas yang dibina Guru B
        $payload = [
            'program_type' => 'tahfidz',
            'title' => 'Materi Ilegal Cross-Class',
            'material_type' => 'tajwid',
            'class_id' => $classTaughtByB->id,
        ];

        $response = $this->actingAs($teacherDataA['user'])
            ->postJson('/api/v1/program/materials', $payload);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_be_prog_02_ac1_anti_idor_teacher_cannot_create_target_for_student_outside_their_class(): void
    {
        $teacherData = $this->createTeacher('ICP');
        $classIcp = $this->createClassWithProgram('VII-ICP-1', 'icp', $teacherData['teacher']);
        $classTahfidz = $this->createClassWithProgram('VII-Tahfidz-1', 'tahfidz');

        $studentTahfidz = $this->createStudentInClass($classTahfidz, 'tahfidz');

        // Guru pembina ICP mencoba menambahkan target ke siswa Tahfidz (bukan binaannya)
        $payload = [
            'student_id' => $studentTahfidz['student']->id,
            'program_type' => 'icp',
            'title' => 'Band Score Checkpoint 5.0',
            'domain' => 'english',
            'target_value' => '5.0',
        ];

        $response = $this->actingAs($teacherData['user'])
            ->postJson('/api/v1/program/targets', $payload);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_be_prog_02_ac1_teacher_can_create_target_for_their_student(): void
    {
        $teacherData = $this->createTeacher('Inklusi');
        $classInklusi = $this->createClassWithProgram('VII-Inklusi-1', 'inklusi', $teacherData['teacher']);
        $studentInklusi = $this->createStudentInClass($classInklusi, 'inklusi');

        $payload = [
            'student_id' => $studentInklusi['student']->id,
            'program_type' => 'inklusi',
            'title' => 'Latihan Motorik Halus Mandiri',
            'domain' => 'motorik',
            'target_value' => '20 menit',
            'period' => 'Semester Ganjil 2026/2027',
            'status' => 'aktif',
        ];

        $response = $this->actingAs($teacherData['user'])
            ->postJson('/api/v1/program/targets', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('program_targets', [
            'student_id' => $studentInklusi['student']->id,
            'title' => 'Latihan Motorik Halus Mandiri',
            'set_by' => $teacherData['user']->id,
        ]);
    }

    // =========================================================================
    // AC2: SETIAP AKSI MENULIS AUDIT_LOGS
    // =========================================================================

    public function test_be_prog_02_ac2_create_update_delete_material_records_audit_logs(): void
    {
        $teacherData = $this->createTeacher('Audit');
        $class = $this->createClassWithProgram('VII-Tahfidz-Audit', 'tahfidz', $teacherData['teacher']);

        // 1. Create
        $createRes = $this->actingAs($teacherData['user'])
            ->postJson('/api/v1/program/materials', [
                'program_type' => 'tahfidz',
                'title' => 'Audio Murottal Juz 30',
                'material_type' => 'murottal',
                'class_id' => $class->id,
            ]);
        $createRes->assertStatus(201);
        $materialId = $createRes->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'program.material.create',
            'entity_id' => $materialId,
            'actor_id' => $teacherData['user']->id,
        ]);

        // 2. Update
        $updateRes = $this->actingAs($teacherData['user'])
            ->patchJson("/api/v1/program/materials/{$materialId}", [
                'title' => 'Audio Murottal Juz 30 (Revisi Qari)',
            ]);
        $updateRes->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'program.material.update',
            'entity_id' => $materialId,
            'actor_id' => $teacherData['user']->id,
        ]);

        // 3. Delete
        $deleteRes = $this->actingAs($teacherData['user'])
            ->deleteJson("/api/v1/program/materials/{$materialId}");
        $deleteRes->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'program.material.delete',
            'entity_id' => $materialId,
            'actor_id' => $teacherData['user']->id,
        ]);
    }

    public function test_be_prog_02_ac2_create_task_records_audit_logs(): void
    {
        $teacherData = $this->createTeacher('TaskAudit');
        $class = $this->createClassWithProgram('VII-ICP-Audit', 'icp', $teacherData['teacher']);

        $response = $this->actingAs($teacherData['user'])
            ->postJson('/api/v1/program/tasks', [
                'program_type' => 'icp',
                'title' => 'Essay Photosynthesis Experiment',
                'instructions' => 'Write 150 words laboratory analysis',
                'class_id' => $class->id,
                'due_at' => '2026-11-20 12:00:00',
            ]);

        $response->assertStatus(201);
        $taskId = $response->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'program.task.create',
            'entity_id' => $taskId,
            'actor_id' => $teacherData['user']->id,
        ]);
    }

    // =========================================================================
    // AC3: VALIDASI DTO (ENUM & TIPE DATA INVALID MENGHASILKAN 422)
    // =========================================================================

    public function test_be_prog_02_ac3_validation_error_on_invalid_material_type(): void
    {
        $teacherData = $this->createTeacher('Val1');
        $class = $this->createClassWithProgram('VII-Tahfidz-Val', 'tahfidz', $teacherData['teacher']);

        $response = $this->actingAs($teacherData['user'])
            ->postJson('/api/v1/program/materials', [
                'program_type' => 'tahfidz',
                'title' => 'Materi Validasi',
                'material_type' => 'invalid_material_type_xyz',
                'class_id' => $class->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['material_type']);
    }

    public function test_be_prog_02_ac3_validation_error_on_invalid_target_status(): void
    {
        $teacherData = $this->createTeacher('Val2');
        $class = $this->createClassWithProgram('VII-Inklusi-Val', 'inklusi', $teacherData['teacher']);
        $student = $this->createStudentInClass($class, 'inklusi');

        $response = $this->actingAs($teacherData['user'])
            ->postJson('/api/v1/program/targets', [
                'student_id' => $student['student']->id,
                'program_type' => 'inklusi',
                'title' => 'Target Status Invalid',
                'status' => 'status_tidak_dikenal',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    public function test_be_prog_02_ac3_validation_error_on_invalid_grading_score(): void
    {
        $teacherData = $this->createTeacher('GradingVal');
        $class = $this->createClassWithProgram('VII-Tahfidz-Grade', 'tahfidz', $teacherData['teacher']);
        $student = $this->createStudentInClass($class, 'tahfidz');

        $task = ProgramTask::create([
            'program_type' => 'tahfidz',
            'title' => 'Tugas Setoran Al-Mulk',
            'class_id' => $class->id,
            'created_by' => $teacherData['user']->id,
        ]);

        $submission = ProgramTaskSubmission::create([
            'task_id' => $task->id,
            'student_id' => $student['student']->id,
            'notes' => 'Rekaman sudah ada di Drive',
            'status' => 'dikumpulkan',
        ]);

        // Nilai melebihi 100
        $response = $this->actingAs($teacherData['user'])
            ->patchJson("/api/v1/program/submissions/{$submission->id}", [
                'score' => 150, // max 100
                'feedback' => 'Bagus sekali',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['score']);
    }

    // =========================================================================
    // AC4: AUTHENTICATION & ROLE ACCESS CONTROL
    // =========================================================================

    public function test_be_prog_02_ac4_unauthenticated_and_student_forbidden(): void
    {
        // 1. Tanpa token -> 401
        $guestRes = $this->postJson('/api/v1/program/materials', [
            'program_type' => 'tahfidz',
            'title' => 'Materi Tamu',
            'material_type' => 'panduan',
        ]);
        $guestRes->assertStatus(401);

        // 2. Akun siswa biasa -> 403
        $class = $this->createClassWithProgram('VII-Tahfidz-Std', 'tahfidz');
        $student = $this->createStudentInClass($class, 'tahfidz');

        $studentRes = $this->actingAs($student['user'])
            ->postJson('/api/v1/program/materials', [
                'program_type' => 'tahfidz',
                'title' => 'Materi Dibuat Siswa',
                'material_type' => 'panduan',
            ]);
        $studentRes->assertStatus(403);
    }

    // =========================================================================
    // AC5: PENILAIAN SUBMISSION DENGAN CATATAN FEEDBACK & AUDIT LOG
    // =========================================================================

    public function test_be_prog_02_ac5_teacher_can_grade_submission_with_feedback_and_audit(): void
    {
        $teacherData = $this->createTeacher('Grader');
        $class = $this->createClassWithProgram('VII-ICP-Grade', 'icp', $teacherData['teacher']);
        $student = $this->createStudentInClass($class, 'icp');

        $task = ProgramTask::create([
            'program_type' => 'icp',
            'title' => 'Checkpoint Science Report',
            'class_id' => $class->id,
            'created_by' => $teacherData['user']->id,
        ]);

        $submission = ProgramTaskSubmission::create([
            'task_id' => $task->id,
            'student_id' => $student['student']->id,
            'link' => 'https://drive.google.com/file/report-science.pdf',
            'notes' => 'Laporan uji lab fotosintesis',
            'status' => 'dikumpulkan',
        ]);

        // Guru menilai submission
        $response = $this->actingAs($teacherData['user'])
            ->patchJson("/api/v1/program/submissions/{$submission->id}", [
                'score' => 92.5,
                'feedback' => 'Analisis data fotosintesis sangat terstruktur. Perhatikan ejaan ilmiah.',
                'status' => 'dinilai',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.score', 92.5);
        $response->assertJsonPath('data.status', 'dinilai');

        $this->assertDatabaseHas('program_task_submissions', [
            'id' => $submission->id,
            'score' => 92.5,
            'status' => 'dinilai',
            'graded_by' => $teacherData['user']->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'program.submission.grade',
            'entity_id' => $submission->id,
            'actor_id' => $teacherData['user']->id,
        ]);
    }
}
