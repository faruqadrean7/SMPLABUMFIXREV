<?php

namespace Tests\Feature\Integration;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\ExamQuestion;
use App\Models\ExamRoom;
use App\Models\GradeComponent;
use App\Models\GradeLevel;
use App\Models\GradePeriod;
use App\Models\Guardian;
use App\Models\Journal;
use App\Models\LearningMaterial;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Role;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiIntegrationReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $teacher1User;

    protected User $teacher2User;

    protected User $student1User;

    protected User $student2User;

    protected User $guardian1User;

    protected Teacher $teacher1;

    protected Teacher $teacher2;

    protected Student $student1;

    protected Student $student2;

    protected Guardian $guardian1;

    protected SchoolClass $classA;

    protected SchoolClass $classB;

    protected Subject $subjectMath;

    protected Subject $subjectBio;

    protected Semester $semester1;

    protected AcademicYear $academicYear;

    protected Room $room101;

    protected TimeSlot $timeSlot1;

    protected GradePeriod $gradePeriod1;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Roles & Permissions
        $roleAdmin = Role::firstOrCreate(['code' => 'admin_kurikulum'], ['name' => 'Admin Kurikulum', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Wali Murid', 'is_system' => true]);

        $permSchedManage = Permission::firstOrCreate(['code' => 'academic.schedule.manage'], ['description' => 'Kelola jadwal']);
        $permStudentRead = Permission::firstOrCreate(['code' => 'master.student.read'], ['description' => 'Baca siswa']);
        $permStudentWrite = Permission::firstOrCreate(['code' => 'master.student.write'], ['description' => 'Kelola siswa']);
        $permGradeWrite = Permission::firstOrCreate(['code' => 'academic.grade.write'], ['description' => 'Input nilai']);
        $permAttendanceWrite = Permission::firstOrCreate(['code' => 'academic.attendance.write'], ['description' => 'Input presensi']);
        $permAttendanceRead = Permission::firstOrCreate(['code' => 'academic.attendance.read'], ['description' => 'Baca presensi']);
        $permJournalWrite = Permission::firstOrCreate(['code' => 'academic.journal.write'], ['description' => 'Isi jurnal']);
        $permLmsWrite = Permission::firstOrCreate(['code' => 'academic.lms.write'], ['description' => 'Unggah materi']);
        $permExamManage = Permission::firstOrCreate(['code' => 'cbt.exam.manage'], ['description' => 'Kelola ujian CBT']);
        $permSubjectManage = Permission::firstOrCreate(['code' => 'master.subject.manage'], ['description' => 'Kelola mapel']);
        $permRoomManage = Permission::firstOrCreate(['code' => 'master.room.manage'], ['description' => 'Kelola ruangan']);

        $roleAdmin->permissions()->syncWithoutDetaching([
            $permSchedManage->id,
            $permStudentRead->id,
            $permStudentWrite->id,
            $permGradeWrite->id,
            $permAttendanceWrite->id,
            $permAttendanceRead->id,
            $permJournalWrite->id,
            $permLmsWrite->id,
            $permExamManage->id,
            $permSubjectManage->id,
            $permRoomManage->id,
        ]);

        $roleTeacher->permissions()->syncWithoutDetaching([
            $permGradeWrite->id,
            $permAttendanceWrite->id,
            $permAttendanceRead->id,
            $permJournalWrite->id,
            $permLmsWrite->id,
        ]);

        $roleStudent->permissions()->syncWithoutDetaching([]);
        $roleGuardian->permissions()->syncWithoutDetaching([$permAttendanceRead->id]);

        // 2. Users
        $this->adminUser = User::create([
            'username' => 'admin_integ',
            'email' => 'admin_integ@labschool.sch.id',
            'password_hash' => Hash::make('AdminPass123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->teacher1User = User::create([
            'username' => 'teacher_budi',
            'email' => 'budi@labschool.sch.id',
            'password_hash' => Hash::make('TeacherPass123!'),
            'status' => 'active',
        ]);
        $this->teacher1User->roles()->attach($roleTeacher->id);

        $this->teacher2User = User::create([
            'username' => 'teacher_siti',
            'email' => 'siti@labschool.sch.id',
            'password_hash' => Hash::make('TeacherPass123!'),
            'status' => 'active',
        ]);
        $this->teacher2User->roles()->attach($roleTeacher->id);

        $this->student1User = User::create([
            'username' => 'student_ani',
            'email' => 'ani@labschool.sch.id',
            'password_hash' => Hash::make('StudentPass123!'),
            'status' => 'active',
        ]);
        $this->student1User->roles()->attach($roleStudent->id);

        $this->student2User = User::create([
            'username' => 'student_bambang',
            'email' => 'bambang@labschool.sch.id',
            'password_hash' => Hash::make('StudentPass123!'),
            'status' => 'active',
        ]);
        $this->student2User->roles()->attach($roleStudent->id);

        $this->guardian1User = User::create([
            'username' => 'guardian_joko',
            'email' => 'joko@keluarga.sch.id',
            'password_hash' => Hash::make('GuardianPass123!'),
            'status' => 'active',
        ]);
        $this->guardian1User->roles()->attach($roleGuardian->id);

        // 3. Profiles
        $this->teacher1 = Teacher::create([
            'user_id' => $this->teacher1User->id,
            'nip' => '198001012005011001',
            'full_name' => 'Budi Santoso, M.Pd.',
            'status' => 'tetap',
        ]);

        $this->teacher2 = Teacher::create([
            'user_id' => $this->teacher2User->id,
            'nip' => '198502022006022002',
            'full_name' => 'Siti Aminah, S.Pd.',
            'status' => 'tetap',
        ]);

        $this->student1 = Student::create([
            'user_id' => $this->student1User->id,
            'nis' => '10001',
            'nisn' => '0011223344',
            'nik_enc' => '3578010011223344',
            'full_name' => 'Ani Lestari',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2009-03-15',
            'status' => 'aktif',
            'entry_date' => '2024-07-15',
        ]);

        $this->student2 = Student::create([
            'user_id' => $this->student2User->id,
            'nis' => '10002',
            'nisn' => '0011223355',
            'nik_enc' => '3578010011223355',
            'full_name' => 'Bambang Pamungkas',
            'gender' => 'L',
            'birth_place' => 'Surabaya',
            'birth_date' => '2009-05-20',
            'status' => 'aktif',
            'entry_date' => '2024-07-15',
        ]);

        $this->guardian1 = Guardian::create([
            'user_id' => $this->guardian1User->id,
            'full_name' => 'Joko Lestari',
            'phone' => '081234567890',
        ]);

        DB::table('student_guardians')->insert([
            'student_id' => $this->student1->id,
            'guardian_id' => $this->guardian1->id,
            'relationship' => 'ayah',
            'is_primary' => true,
        ]);

        // 4. Academic Foundation
        $this->academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-15',
            'end_date' => '2027-06-20',
            'is_active' => true,
        ]);

        $this->semester1 = Semester::create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'Ganjil',
            'semester_type' => 'ganjil',
            'start_date' => '2026-07-15',
            'end_date' => '2026-12-20',
            'is_active' => true,
        ]);

        $gradeLevel = GradeLevel::create(['name' => 'Kelas 7', 'sort_order' => 7]);

        $this->classA = SchoolClass::create([
            'name' => 'VII-A',
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $gradeLevel->id,
            'homeroom_teacher_id' => $this->teacher1->id,
            'capacity' => 32,
            'grade_level' => 7,
        ]);

        $this->classB = SchoolClass::create([
            'name' => 'VII-B',
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $gradeLevel->id,
            'homeroom_teacher_id' => $this->teacher2->id,
            'capacity' => 32,
            'grade_level' => 7,
        ]);

        ClassStudent::create([
            'class_id' => $this->classA->id,
            'student_id' => $this->student1->id,
            'joined_at' => Carbon::now()->subMonths(2),
        ]);

        ClassStudent::create([
            'class_id' => $this->classB->id,
            'student_id' => $this->student2->id,
            'joined_at' => Carbon::now()->subMonths(2),
        ]);

        $this->subjectMath = Subject::create([
            'code' => 'MAT-01',
            'name' => 'Matematika',
            'subject_group' => 'umum',
        ]);

        $this->subjectBio = Subject::create([
            'code' => 'BIO-01',
            'name' => 'Biologi',
            'subject_group' => 'umum',
        ]);

        $this->room101 = Room::create([
            'code' => 'R-101',
            'name' => 'Ruang Teori 101',
            'room_type' => 'kelas',
            'capacity' => 36,
        ]);

        $this->timeSlot1 = TimeSlot::create([
            'name' => 'Jam Ke-1',
            'start_time' => '07:00',
            'end_time' => '07:45',
            'day_type' => 'reguler',
        ]);

        TeachingAssignment::create([
            'teacher_id' => $this->teacher1->id,
            'subject_id' => $this->subjectMath->id,
            'class_id' => $this->classA->id,
            'semester_id' => $this->semester1->id,
            'hours_per_week' => 4,
        ]);

        $this->gradePeriod1 = GradePeriod::create([
            'semester_id' => $this->semester1->id,
            'name' => 'Penilaian Tengah Semester 1',
            'month' => 9,
            'year' => 2026,
            'status' => 'open',
        ]);
    }

    /**
     * Flow 1: Admin membuat data master -> Data terbaca oleh role yang berhak (Web/Mobile).
     */
    public function test_flow_1_admin_creates_master_data_readable_by_authorized_roles(): void
    {
        // 1. Guest tanpa autentikasi ditolak 401
        $this->getJson('/api/v1/academic-years/active')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        // 2. Login Guru via API Auth
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'teacher_budi',
            'password' => 'TeacherPass123!',
        ]);
        $loginRes->assertStatus(200)
            ->assertJsonPath('data.user.username', 'teacher_budi');

        $teacherToken = $loginRes->json('data.tokens.accessToken');
        $this->assertNotEmpty($teacherToken);

        // 3. Guru membaca data periode aktif (terbuka bagi pengguna terautentikasi)
        auth()->forgetGuards();
        $activeYearRes = $this->withHeader('Authorization', "Bearer {$teacherToken}")
            ->getJson('/api/v1/academic-years/active');
        $activeYearRes->assertStatus(200)
            ->assertJsonPath('data.academicYear.name', '2026/2027');

        // 4. Guru tidak berhak mengelola master mata pelajaran (403 Forbidden)
        auth()->forgetGuards();
        $unauthSubjects = $this->withHeader('Authorization', "Bearer {$teacherToken}")
            ->getJson('/api/v1/subjects');
        $unauthSubjects->assertStatus(403);

        // 5. Admin yang berwenang membaca data master mata pelajaran dan ruangan
        $adminToken = $this->adminUser->createToken('web')->plainTextToken;
        auth()->forgetGuards();
        $subjectsRes = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->getJson('/api/v1/subjects');
        $subjectsRes->assertStatus(200);
        $this->assertGreaterThanOrEqual(2, count($subjectsRes->json('data')));

        auth()->forgetGuards();
        $roomsRes = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->getJson('/api/v1/rooms');
        $roomsRes->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($roomsRes->json('data')));
    }

    /**
     * Flow 2: Admin mengelola siswa/kelas -> Mobile menerima data sesuai hak akses & bebas IDOR.
     */
    public function test_flow_2_admin_manages_student_and_class_mobile_receives_data_with_proper_scoping(): void
    {
        $studentToken = $this->student1User->createToken('mobile')->plainTextToken;
        $guardianToken = $this->guardian1User->createToken('mobile')->plainTextToken;

        // 1. Siswa 1 membaca profilnya sendiri -> 200 OK
        $myProfile = $this->withHeader('Authorization', "Bearer {$studentToken}")
            ->getJson("/api/v1/students/{$this->student1->id}");
        $myProfile->assertStatus(200)
            ->assertJsonPath('data.full_name', 'Ani Lestari')
            ->assertJsonPath('data.nis', '10001');

        // 2. Siswa 1 mencoba membaca profil Siswa 2 (IDOR Attack) -> 403 Forbidden
        $idorAttempt = $this->withHeader('Authorization', "Bearer {$studentToken}")
            ->getJson("/api/v1/students/{$this->student2->id}");
        $idorAttempt->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // 3. Wali Murid membaca profil anaknya (Siswa 1) -> 200 OK
        $childProfile = $this->withHeader('Authorization', "Bearer {$guardianToken}")
            ->getJson("/api/v1/students/{$this->student1->id}");
        $childProfile->assertStatus(200)
            ->assertJsonPath('data.full_name', 'Ani Lestari');

        // 4. Wali Murid mencoba membaca profil anak orang lain (Siswa 2) -> 403 Forbidden
        $otherChild = $this->withHeader('Authorization', "Bearer {$guardianToken}")
            ->getJson("/api/v1/students/{$this->student2->id}");
        $otherChild->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * Flow 3: Guru mengubah presensi dan nilai -> Perubahan tersimpan & terlihat hanya oleh pihak yang berhak.
     */
    public function test_flow_3_teacher_records_attendance_and_grades_visible_to_authorized_parties(): void
    {
        $teacherToken = $this->teacher1User->createToken('web')->plainTextToken;
        $unassignedTeacherToken = $this->teacher2User->createToken('web')->plainTextToken;
        $studentToken = $this->student1User->createToken('mobile')->plainTextToken;
        $student2Token = $this->student2User->createToken('mobile')->plainTextToken;

        // 1. Guru membuat jadwal & jurnal kelas
        $schedule = Schedule::create([
            'semester_id' => $this->semester1->id,
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'time_slot_id' => $this->timeSlot1->id,
            'room_id' => $this->room101->id,
            'day_of_week' => 1,
        ]);

        $journal = Journal::create([
            'schedule_id' => $schedule->id,
            'teacher_id' => $this->teacher1->id,
            'session_date' => Carbon::now()->format('Y-m-d'),
            'topic' => 'Persamaan Aljabar Linier',
            'method' => 'tatap_muka',
            'status' => 'terlaksana',
        ]);

        // 2. Guru mencatat presensi sesi kelas untuk Siswa 1
        $attRes = $this->withHeader('Authorization', "Bearer {$teacherToken}")
            ->postJson('/api/v1/attendances', [
                'journal_id' => $journal->id,
                'attendances' => [
                    [
                        'student_id' => $this->student1->id,
                        'status' => 'hadir',
                        'note' => 'Aktif bertanya di kelas',
                    ],
                ],
            ]);
        $attRes->assertStatus(201)
            ->assertJsonPath('data.total_recorded', 1);

        // 3. Guru membuat komponen nilai dan input nilai untuk Siswa 1
        $comp = GradeComponent::create([
            'subject_id' => $this->subjectMath->id,
            'semester_id' => $this->semester1->id,
            'class_id' => $this->classA->id,
            'code' => 'UH-ALJABAR',
            'name' => 'Ulangan Harian Aljabar',
            'weight' => 20.0,
        ]);

        $gradeRes = $this->withHeader('Authorization', "Bearer {$teacherToken}")
            ->postJson('/api/v1/grades', [
                'student_id' => $this->student1->id,
                'component_id' => $comp->id,
                'period_id' => $this->gradePeriod1->id,
                'value' => 92.50,
            ]);
        $gradeRes->assertStatus(201)
            ->assertJsonPath('data.value', '92.50');

        // 4. Guru yang tidak ditugaskan (Teacher 2) ditolak saat menginput nilai kelas A
        auth()->forgetGuards();
        $unassignedRes = $this->withHeader('Authorization', "Bearer {$unassignedTeacherToken}")
            ->postJson('/api/v1/grades', [
                'student_id' => $this->student1->id,
                'component_id' => $comp->id,
                'period_id' => $this->gradePeriod1->id,
                'value' => 80.00,
            ]);
        $unassignedRes->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // 5. Siswa 1 melihat presensi dan nilainya sendiri di mobile
        auth()->forgetGuards();
        $studentAtt = $this->withHeader('Authorization', "Bearer {$studentToken}")
            ->getJson('/api/v1/attendances');
        $studentAtt->assertStatus(200);
        $this->assertCount(1, $studentAtt->json('data'));
        $this->assertEquals($this->student1->id, $studentAtt->json('data.0.student_id'));

        auth()->forgetGuards();
        $studentGrades = $this->withHeader('Authorization', "Bearer {$studentToken}")
            ->getJson('/api/v1/grades');
        $studentGrades->assertStatus(200);
        $this->assertCount(1, $studentGrades->json('data'));
        $this->assertEquals('92.50', $studentGrades->json('data.0.value'));

        // 6. Siswa 2 (kelas B) tidak melihat nilai Siswa 1
        auth()->forgetGuards();
        $student2Grades = $this->withHeader('Authorization', "Bearer {$student2Token}")
            ->getJson('/api/v1/grades');
        $student2Grades->assertStatus(200);
        $this->assertCount(0, $student2Grades->json('data'));
    }

    /**
     * Flow 4: Siswa membaca jadwal, materi pembelajaran, dan notifikasi (Inbox).
     */
    public function test_flow_4_student_reads_schedule_learning_material_grades_and_notifications(): void
    {
        $studentToken = $this->student1User->createToken('mobile')->plainTextToken;
        $student2Token = $this->student2User->createToken('mobile')->plainTextToken;
        $teacherToken = $this->teacher1User->createToken('web')->plainTextToken;

        // 1. Siswa membaca jadwal kelasnya
        Schedule::create([
            'semester_id' => $this->semester1->id,
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'time_slot_id' => $this->timeSlot1->id,
            'room_id' => $this->room101->id,
            'day_of_week' => 1,
        ]);

        $schedRes = $this->withHeader('Authorization', "Bearer {$studentToken}")
            ->getJson("/api/v1/schedules?class_id={$this->classA->id}");
        $schedRes->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($schedRes->json('data')));

        // 2. Guru mengunggah materi pelajaran untuk Kelas A
        LearningMaterial::create([
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'title' => 'Modul 1: Operasi Aljabar',
            'description' => 'Materi pengantar aljabar untuk kelas VII-A',
        ]);

        // Siswa 1 di Kelas A melihat materi tersebut
        $materialRes1 = $this->withHeader('Authorization', "Bearer {$studentToken}")
            ->getJson('/api/v1/materials');
        $materialRes1->assertStatus(200);
        $this->assertCount(1, $materialRes1->json('data'));
        $this->assertEquals('Modul 1: Operasi Aljabar', $materialRes1->json('data.0.title'));

        // Siswa 2 di Kelas B tidak melihat materi Kelas A
        auth()->forgetGuards();
        $materialRes2 = $this->withHeader('Authorization', "Bearer {$student2Token}")
            ->getJson('/api/v1/materials');
        $materialRes2->assertStatus(200);
        $this->assertCount(0, $materialRes2->json('data'));

        // 3. Notifikasi dikirimkan ke Siswa 1 dan dibaca di mobile
        $notif = Notification::create([
            'user_id' => $this->student1User->id,
            'notif_type' => 'academic.material_published',
            'title' => 'Materi Baru Tersedia',
            'body' => 'Pak Budi telah mengunggah Modul 1: Operasi Aljabar.',
            'read_at' => null,
        ]);

        auth()->forgetGuards();
        $inboxRes = $this->withHeader('Authorization', "Bearer {$studentToken}")
            ->getJson('/api/v1/notifications');
        $inboxRes->assertStatus(200)
            ->assertJsonPath('meta.unread_count', 1);

        $readRes = $this->withHeader('Authorization', "Bearer {$studentToken}")
            ->patchJson("/api/v1/notifications/{$notif->id}/read");
        $readRes->assertStatus(200);

        $notif->refresh();
        $this->assertNotNull($notif->read_at);
    }

    /**
     * Flow 5: Siklus lengkap CBT di mobile (Start -> Questions -> Autosave -> Heartbeat -> Finish).
     */
    public function test_flow_5_cbt_end_to_end_lifecycle_start_questions_autosave_heartbeat_and_finish(): void
    {
        $studentToken = $this->student1User->createToken('mobile')->plainTextToken;

        // 1. Admin menyiapkan ujian CBT
        $exam = Exam::create([
            'title' => 'Ujian Tengah Semester Matematika',
            'subject_id' => $this->subjectMath->id,
            'created_by' => $this->adminUser->id,
            'exam_type' => 'ujian_tengah',
            'duration_min' => 60,
            'start_at' => Carbon::now()->subMinutes(10),
            'end_at' => Carbon::now()->addDays(1),
            'status' => 'aktif',
            'passing_grade' => 75.0,
        ]);

        $examRoom = ExamRoom::create([
            'exam_id' => $exam->id,
            'room_id' => $this->room101->id,
            'proctor_id' => $this->teacher1User->id,
            'session_no' => 1,
            'token' => 'MAT123',
            'token_expires_at' => Carbon::now()->addDays(1),
        ]);

        ExamParticipant::create([
            'exam_id' => $exam->id,
            'student_id' => $this->student1->id,
            'exam_room_id' => $examRoom->id,
        ]);

        $bank = QuestionBank::create([
            'name' => 'Bank Soal UTS Matematika',
            'subject_id' => $this->subjectMath->id,
            'owner_id' => $this->adminUser->id,
        ]);

        $question = Question::create([
            'bank_id' => $bank->id,
            'cp_code' => 'CP-MAT-01',
            'question_type' => 'pg',
            'body' => ['text' => 'Berapakah 15 x 6?'],
            'weight' => 10.0,
        ]);

        QuestionOption::create([
            'question_id' => $question->id,
            'label' => 'A',
            'body' => ['text' => '90'],
            'is_correct' => true, // Rahasia kunci jawaban
        ]);

        QuestionOption::create([
            'question_id' => $question->id,
            'label' => 'B',
            'body' => ['text' => '85'],
            'is_correct' => false,
        ]);

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'question_id' => $question->id,
            'sort_order' => 1,
        ]);

        // 2. Siswa memasukkan token dan memulai sesi
        $startRes = $this->withHeader('Authorization', "Bearer {$studentToken}")
            ->postJson("/api/v1/exams/{$exam->id}/start-session", ['token' => 'MAT123']);
        $startRes->assertStatus(200)
            ->assertJsonPath('data.status', 'mengerjakan');

        $sessionId = $startRes->json('data.session_id');
        $this->assertNotEmpty($sessionId);

        // 3. Siswa memuat butir soal (Verifikasi kunci jawaban disanitasi)
        $qRes = $this->withHeader('Authorization', "Bearer {$studentToken}")
            ->getJson("/api/v1/exams/{$exam->id}/session-questions");
        $qRes->assertStatus(200)
            ->assertJsonPath('data.total_questions', 1);

        $firstOption = $qRes->json('data.questions.0.options.0');
        $this->assertArrayNotHasKey('is_correct', $firstOption);

        // 4. Autosave jawaban
        $saveRes = $this->withHeader('Authorization', "Bearer {$studentToken}")
            ->postJson("/api/v1/exams/{$exam->id}/save-answer", [
                'question_id' => $question->id,
                'answer' => 'A',
                'is_flagged' => false,
            ]);
        $saveRes->assertStatus(200)
            ->assertJsonPath('data.is_saved', true);

        // 5. Heartbeat browser / anti-cheat
        $hbRes = $this->withHeader('Authorization', "Bearer {$studentToken}")
            ->postJson("/api/v1/exam-sessions/{$sessionId}/heartbeat", [
                'screen_state' => 'fullscreen',
                'active_tab' => true,
                'battery_level' => 90,
                'client_timestamp' => now()->toIso8601String(),
            ]);
        $hbRes->assertStatus(200)
            ->assertJsonPath('data.status', 'mengerjakan');

        // 6. Submit akhir sesi ujian
        $finishRes = $this->withHeader('Authorization', "Bearer {$studentToken}")
            ->postJson("/api/v1/exams/{$exam->id}/finish-session");
        $finishRes->assertStatus(200)
            ->assertJsonPath('data.status', 'selesai');

        // 7. Percobaan autosave setelah submit ditolak (422)
        $postFinishSave = $this->withHeader('Authorization', "Bearer {$studentToken}")
            ->postJson("/api/v1/exams/{$exam->id}/save-answer", [
                'question_id' => $question->id,
                'answer' => 'B',
            ]);
        $postFinishSave->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');
    }

    /**
     * Flow 6: Penolakan akses tidak sah, revokasi token, dan perlindungan privasi data sensitif BK.
     */
    public function test_flow_6_unauthorized_user_and_token_revocation_enforces_strict_rejection(): void
    {
        // 1. Password salah ditolak tanpa kebocoran status eksistensi user (401)
        $badLogin = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'student_ani',
            'password' => 'WrongPassword!',
        ]);
        $badLogin->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED')
            ->assertJsonPath('error.message', 'Kredensial yang diberikan tidak cocok dengan data kami.');

        // 2. Login dan rotasi refresh token
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'student_ani',
            'password' => 'StudentPass123!',
        ]);
        $refreshToken = $loginRes->json('data.tokens.refreshToken');
        $this->assertNotEmpty($refreshToken);

        // Rotasi refresh token pertama
        $refreshRes = $this->postJson('/api/v1/auth/refresh', [
            'refreshToken' => $refreshToken,
        ]);
        $refreshRes->assertStatus(200);
        $newRefreshToken = $refreshRes->json('data.tokens.refreshToken');

        // Percobaan pemakaian ulang refresh token lama (Reuse attack) -> Semua token dicabut!
        $reuseRes = $this->postJson('/api/v1/auth/refresh', [
            'refreshToken' => $refreshToken,
        ]);
        $reuseRes->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        // 3. Akses endpoint sensitif BK tanpa peran konselor ditolak (403)
        $studentToken = $this->student1User->createToken('mobile')->plainTextToken;
        $bkAttempt = $this->withHeader('Authorization', "Bearer {$studentToken}")
            ->getJson('/api/v1/counseling/cases');
        $bkAttempt->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }
}
