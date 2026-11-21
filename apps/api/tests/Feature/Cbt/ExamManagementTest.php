<?php

namespace Tests\Feature\Cbt;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\Exam;
use App\Models\ExamRoom;
use App\Models\GradeLevel;
use App\Models\Permission;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Role;
use App\Models\Room;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExamManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacherUser;

    protected User $adminUser;

    protected User $proctorUser;

    protected User $studentUser;

    protected Teacher $teacher;

    protected Subject $subjectMath;

    protected GradeLevel $gradeLevel10;

    protected SchoolClass $classA;

    protected Room $room1;

    protected Student $student1;

    protected Student $student2;

    protected QuestionBank $bank;

    protected Question $question1;

    protected Question $question2;

    protected function setUp(): void
    {
        parent::setUp();

        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $permExamManage = Permission::firstOrCreate(['code' => 'cbt.exam.manage'], ['description' => 'Mengelola jadwal dan sesi ujian CBT']);
        $permCbtWrite = Permission::firstOrCreate(['code' => 'cbt.bank.write'], ['description' => 'Mengelola bank soal']);

        $roleTeacher->permissions()->syncWithoutDetaching([$permExamManage->id, $permCbtWrite->id]);
        $roleAdmin->permissions()->syncWithoutDetaching([$permExamManage->id, $permCbtWrite->id]);

        $this->teacherUser = User::create([
            'username' => 'guru_exam',
            'email' => 'guru.exam@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher->id);

        $this->adminUser = User::create([
            'username' => 'admin_exam',
            'email' => 'admin.exam@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->proctorUser = User::create([
            'username' => 'pengawas_1',
            'email' => 'proctor1@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->proctorUser->roles()->attach($roleTeacher->id);

        $this->studentUser = User::create([
            'username' => 'student_exam',
            'email' => 'student.exam@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($roleStudent->id);

        $this->teacher = Teacher::create([
            'user_id' => $this->teacherUser->id,
            'nip' => '198901012012011004',
            'full_name' => 'Hasan Bisri, S.Pd',
            'status' => 'active',
        ]);

        $this->subjectMath = Subject::create([
            'code' => 'MAT-10',
            'name' => 'Matematika X',
            'subject_group' => 'Kelompok A',
        ]);

        $this->gradeLevel10 = GradeLevel::create([
            'name' => 'Kelas 10',
            'level' => 10,
        ]);

        $ay = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
        ]);

        $this->classA = SchoolClass::create([
            'academic_year_id' => $ay->id,
            'grade_level_id' => $this->gradeLevel10->id,
            'homeroom_teacher_id' => $this->teacher->id,
            'name' => 'X-MIPA-1',
            'capacity' => 36,
        ]);

        $this->room1 = Room::create([
            'code' => 'LAB-KOMP-1',
            'name' => 'Laboratorium Komputer 1',
            'room_type' => 'laboratorium',
            'capacity' => 40,
        ]);

        $this->student1 = Student::create([
            'user_id' => $this->studentUser->id,
            'nis' => '202611001',
            'nisn' => '0098765901',
            'nik_enc' => 'enc_3578010101010901',
            'full_name' => 'Bayu Wicaksono',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-05-05',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $userStudent2 = User::create([
            'username' => 'student_exam_2',
            'email' => 'student2.exam@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $userStudent2->roles()->attach($roleStudent->id);

        $this->student2 = Student::create([
            'user_id' => $userStudent2->id,
            'nis' => '202611002',
            'nisn' => '0098765902',
            'nik_enc' => 'enc_3578010101010902',
            'full_name' => 'Citra Lestari',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2010-06-06',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        ClassStudent::create([
            'class_id' => $this->classA->id,
            'student_id' => $this->student1->id,
            'joined_at' => Carbon::now()->subMonths(2),
        ]);

        ClassStudent::create([
            'class_id' => $this->classA->id,
            'student_id' => $this->student2->id,
            'joined_at' => Carbon::now()->subMonths(2),
        ]);

        $this->bank = QuestionBank::create([
            'name' => 'Bank Soal Semester Matematika',
            'subject_id' => $this->subjectMath->id,
            'grade_level_id' => $this->gradeLevel10->id,
            'owner_id' => $this->teacherUser->id,
        ]);

        $this->question1 = Question::create([
            'bank_id' => $this->bank->id,
            'question_type' => 'pg',
            'body' => ['content' => 'Soal Nomor 1: Nilai x dari 2x = 10 adalah?'],
            'weight' => 2.0,
            'version' => 1,
            'status' => 'aktif',
        ]);

        $this->question2 = Question::create([
            'bank_id' => $this->bank->id,
            'question_type' => 'pg',
            'body' => ['content' => 'Soal Nomor 2: Nilai x dari 3x = 15 adalah?'],
            'weight' => 2.0,
            'version' => 1,
            'status' => 'aktif',
        ]);
    }

    /**
     * BE-CBT-02: Pembuatan ujian reguler (SAS) dan ujian remedial dengan parent exam id.
     */
    public function test_be_cbt_02_create_exam_regular_and_remedial(): void
    {
        // 1. Buat Ujian Utama SAS
        $responseMain = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/exams', [
                'subject_id' => $this->subjectMath->id,
                'title' => 'Sumatif Akhir Semester (SAS) Matematika',
                'exam_type' => 'SAS',
                'duration_min' => 90,
                'start_at' => now()->addDays(2)->toIso8601String(),
                'end_at' => now()->addDays(2)->addHours(3)->toIso8601String(),
                'passing_grade' => 75.0,
                'shuffle_questions' => true,
                'shuffle_options' => true,
                'package_mode' => 'multi',
                'show_result_mode' => 'after_release',
                'status' => 'dijadwalkan',
            ]);

        $responseMain->assertStatus(201)
            ->assertJsonPath('data.title', 'Sumatif Akhir Semester (SAS) Matematika')
            ->assertJsonPath('data.exam_type', 'SAS')
            ->assertJsonPath('data.duration_min', 90)
            ->assertJsonPath('data.status', 'dijadwalkan');

        $mainExamId = $responseMain->json('data.id');

        // 2. Buat Ujian Remedial yang menautkan parent_exam_id
        $responseRemedial = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/exams', [
                'subject_id' => $this->subjectMath->id,
                'parent_exam_id' => $mainExamId,
                'title' => 'Remedial SAS Matematika',
                'exam_type' => 'remedial',
                'duration_min' => 60,
                'start_at' => now()->addDays(5)->toIso8601String(),
                'end_at' => now()->addDays(5)->addHours(2)->toIso8601String(),
                'passing_grade' => 75.0,
                'status' => 'draft',
            ]);

        $responseRemedial->assertStatus(201)
            ->assertJsonPath('data.parent_exam_id', $mainExamId)
            ->assertJsonPath('data.exam_type', 'remedial');
    }

    /**
     * BE-CBT-02: Asosiasi dan plotting butir soal dari bank soal beserta bobot dan kode paket A/B.
     */
    public function test_be_cbt_02_assign_questions_with_weights_and_package_codes(): void
    {
        $exam = Exam::create([
            'subject_id' => $this->subjectMath->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Ulangan Harian Bab 1',
            'exam_type' => 'harian',
            'duration_min' => 45,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(2),
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson("/api/v1/exams/{$exam->id}/questions", [
                'questions' => [
                    [
                        'question_id' => $this->question1->id,
                        'sort_order' => 1,
                        'weight_override' => 2.5,
                        'package_code' => 'A',
                    ],
                    [
                        'question_id' => $this->question2->id,
                        'sort_order' => 2,
                        'weight_override' => 3.0,
                        'package_code' => 'B',
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('exam_questions', [
            'exam_id' => $exam->id,
            'question_id' => $this->question1->id,
            'package_code' => 'A',
            'weight_override' => 2.5,
        ]);

        $this->assertDatabaseHas('exam_questions', [
            'exam_id' => $exam->id,
            'question_id' => $this->question2->id,
            'package_code' => 'B',
            'weight_override' => 3.0,
        ]);

        // Cek GET daftar soal ujian
        $responseList = $this->actingAs($this->teacherUser, 'sanctum')
            ->getJson("/api/v1/exams/{$exam->id}/questions");

        $responseList->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /**
     * BE-CBT-02: Plotting ruang ujian, sesi, pengawas, dan generate token ujian acak.
     */
    public function test_be_cbt_02_assign_exam_rooms_and_proctors_with_auto_generated_token(): void
    {
        $exam = Exam::create([
            'subject_id' => $this->subjectMath->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Ujian Tengah Semester',
            'exam_type' => 'STS',
            'duration_min' => 90,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(3),
            'status' => 'dijadwalkan',
        ]);

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson("/api/v1/exams/{$exam->id}/rooms", [
                'rooms' => [
                    [
                        'room_id' => $this->room1->id,
                        'proctor_id' => $this->proctorUser->id,
                        'session_no' => 1,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonCount(1, 'data');

        $examRoom = ExamRoom::where('exam_id', $exam->id)->first();
        $this->assertNotNull($examRoom);
        $this->assertEquals($this->room1->id, $examRoom->room_id);
        $this->assertEquals($this->proctorUser->id, $examRoom->proctor_id);
        $this->assertNotEmpty($examRoom->token);
        $this->assertEquals(6, strlen($examRoom->token));
    }

    /**
     * BE-CBT-02: Plotting pendaftaran peserta rombel kelas ke ruang ujian CBT.
     */
    public function test_be_cbt_02_enroll_class_students_to_exam_participants(): void
    {
        $exam = Exam::create([
            'subject_id' => $this->subjectMath->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Kuis Matematika Terjadwal',
            'exam_type' => 'kuis',
            'duration_min' => 30,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
            'status' => 'dijadwalkan',
        ]);

        $examRoom = ExamRoom::create([
            'exam_id' => $exam->id,
            'room_id' => $this->room1->id,
            'session_no' => 1,
            'token' => 'ABC123',
        ]);

        // Daftarkan seluruh murid rombel kelas X-MIPA-1
        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson("/api/v1/exams/{$exam->id}/participants", [
                'exam_room_id' => $examRoom->id,
                'class_id' => $this->classA->id,
                'package_code' => 'A',
            ]);

        $response->assertStatus(201)
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('exam_participants', [
            'exam_id' => $exam->id,
            'student_id' => $this->student1->id,
            'exam_room_id' => $examRoom->id,
            'package_code' => 'A',
        ]);

        $this->assertDatabaseHas('exam_participants', [
            'exam_id' => $exam->id,
            'student_id' => $this->student2->id,
            'exam_room_id' => $examRoom->id,
        ]);

        // Cek GET daftar peserta ujian
        $responseParticipants = $this->actingAs($this->teacherUser, 'sanctum')
            ->getJson("/api/v1/exams/{$exam->id}/participants");

        $responseParticipants->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /**
     * BE-CBT-02: Transisi status lifecycle ujian dan perlindungan penghapusan ujian aktif.
     */
    public function test_be_cbt_02_status_transitions_and_deletion_protection(): void
    {
        $exam = Exam::create([
            'subject_id' => $this->subjectMath->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Simulasi SAS Terjadwal',
            'exam_type' => 'SAS',
            'duration_min' => 90,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(2),
            'status' => 'draft',
        ]);

        // 1. Ubah status jadi 'dijadwalkan'
        $responseStatus = $this->actingAs($this->teacherUser, 'sanctum')
            ->patchJson("/api/v1/exams/{$exam->id}/status", [
                'status' => 'dijadwalkan',
            ]);

        $responseStatus->assertStatus(200)
            ->assertJsonPath('data.status', 'dijadwalkan');

        // 2. Ujian yang sudah dijadwalkan tidak boleh dihapus langsung
        $responseDeleteLocked = $this->actingAs($this->teacherUser, 'sanctum')
            ->deleteJson("/api/v1/exams/{$exam->id}");

        $responseDeleteLocked->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');

        // 3. Kembalikan ke 'draft' dan hapus
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->teacherUser, 'sanctum')
            ->patchJson("/api/v1/exams/{$exam->id}/status", [
                'status' => 'draft',
            ]);

        $responseDeleteDraft = $this->actingAs($this->teacherUser, 'sanctum')
            ->deleteJson("/api/v1/exams/{$exam->id}");

        $responseDeleteDraft->assertStatus(200);
        $this->assertDatabaseMissing('exams', ['id' => $exam->id]);
    }

    /**
     * BE-CBT-02: Pengguna tanpa wewenang (siswa) tidak dapat membuat atau mengubah ujian CBT.
     */
    public function test_be_cbt_02_unauthorized_user_cannot_manage_exams(): void
    {
        $response = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/exams', [
                'subject_id' => $this->subjectMath->id,
                'title' => 'Ujian Ilegal Siswa',
                'exam_type' => 'kuis',
                'duration_min' => 30,
                'start_at' => now()->toIso8601String(),
                'end_at' => now()->addHour()->toIso8601String(),
            ]);

        $response->assertStatus(403);
    }
}
