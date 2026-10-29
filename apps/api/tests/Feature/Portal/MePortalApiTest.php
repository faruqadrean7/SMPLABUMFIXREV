<?php

namespace Tests\Feature\Portal;

use App\Models\AcademicYear;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\ClassStudent;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Ticket;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MePortalApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $guardianUser1;

    protected User $guardianUser2;

    protected Guardian $guardian1;

    protected Guardian $guardian2;

    protected Student $student1;

    protected Student $student2;

    protected SchoolClass $classA;

    protected SchoolClass $classB;

    protected User $studentUser1;

    protected User $teacherUser1;

    protected User $teacherUser2;

    protected Teacher $teacher1;

    protected Teacher $teacher2;

    protected Subject $subjectMath;

    protected User $staffUser1;

    protected Staff $staff1;

    protected AcademicYear $academicYear;

    protected Semester $semester;

    protected TimeSlot $timeSlot;

    protected function setUp(): void
    {
        parent::setUp();

        // Roles
        $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Wali Murid', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleStaff = Role::firstOrCreate(['code' => 'admin_tu'], ['name' => 'Staf Tata Usaha', 'is_system' => true]);

        // Academic Year & Semester
        $this->academicYear = AcademicYear::create([
            'name' => '2024/2025',
            'start_date' => '2024-07-15',
            'end_date' => '2025-06-20',
            'is_active' => true,
        ]);

        $this->semester = Semester::create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'Ganjil',
            'start_date' => '2024-07-15',
            'end_date' => '2024-12-20',
            'is_active' => true,
        ]);

        // Time slot & Subject
        $this->timeSlot = TimeSlot::create([
            'name' => 'Jam 1-2',
            'start_time' => '07:00:00',
            'end_time' => '08:20:00',
            'day_type' => 'regular',
        ]);

        $this->subjectMath = Subject::create([
            'code' => 'MTK',
            'name' => 'Matematika',
            'subject_group' => 'wajib',
        ]);

        // Teachers
        $this->teacherUser1 = User::create([
            'username' => 'teacher_one',
            'email' => 'teacher1@labschool.sch.id',
            'password_hash' => Hash::make('password'),
            'status' => 'active',
        ]);
        $this->teacherUser1->roles()->attach($roleTeacher->id);
        $this->teacher1 = Teacher::create([
            'user_id' => $this->teacherUser1->id,
            'nip' => '198001012005011001',
            'full_name' => 'Guru Satu, S.Pd.',
            'position' => 'Guru Matematika',
            'status' => 'active',
        ]);

        $this->teacherUser2 = User::create([
            'username' => 'teacher_two',
            'email' => 'teacher2@labschool.sch.id',
            'password_hash' => Hash::make('password'),
            'status' => 'active',
        ]);
        $this->teacherUser2->roles()->attach($roleTeacher->id);
        $this->teacher2 = Teacher::create([
            'user_id' => $this->teacherUser2->id,
            'nip' => '198202022006022002',
            'full_name' => 'Guru Dua (Wali Kelas), M.Pd.',
            'position' => 'Wali Kelas 7-B',
            'status' => 'active',
        ]);

        $gradeLevel7 = GradeLevel::create([
            'name' => 'Kelas 7',
            'sort_order' => 7,
        ]);

        // Classes
        $this->classA = SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $gradeLevel7->id,
            'name' => '7-A',
            'homeroom_teacher_id' => $this->teacher1->id,
            'status' => 'active',
        ]);

        $this->classB = SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $gradeLevel7->id,
            'name' => '7-B',
            'homeroom_teacher_id' => $this->teacher2->id,
            'status' => 'active',
        ]);

        // Teaching Assignment: Teacher 1 teaches Class A. Teacher 2 teaches Class B.
        TeachingAssignment::create([
            'teacher_id' => $this->teacher1->id,
            'subject_id' => $this->subjectMath->id,
            'class_id' => $this->classA->id,
            'semester_id' => $this->semester->id,
            'hours_per_week' => 4,
        ]);

        TeachingAssignment::create([
            'teacher_id' => $this->teacher2->id,
            'subject_id' => $this->subjectMath->id,
            'class_id' => $this->classB->id,
            'semester_id' => $this->semester->id,
            'hours_per_week' => 4,
        ]);

        // Schedule for Class A & B
        Schedule::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'time_slot_id' => $this->timeSlot->id,
            'day_of_week' => 1,
        ]);

        Schedule::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->classB->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher2->id,
            'time_slot_id' => $this->timeSlot->id,
            'day_of_week' => 2,
        ]);

        // Students & Users
        $this->studentUser1 = User::create([
            'username' => 'student_one',
            'email' => 'student1@labschool.sch.id',
            'password_hash' => Hash::make('password'),
            'status' => 'active',
        ]);
        $this->studentUser1->roles()->attach($roleStudent->id);
        $this->student1 = Student::create([
            'user_id' => $this->studentUser1->id,
            'nis' => '24001',
            'nisn' => '0081234561',
            'nik_enc' => '3578010101010001',
            'full_name' => 'Anak Kandung Satu',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-05-10',
            'religion' => 'Islam',
            'address' => 'Jl. Veteran No. 1, Malang',
            'entry_date' => '2024-07-15',
            'status' => 'aktif',
        ]);
        ClassStudent::create([
            'class_id' => $this->classA->id,
            'student_id' => $this->student1->id,
            'joined_at' => '2024-07-15',
        ]);

        $studentUser2 = User::create([
            'username' => 'student_two',
            'email' => 'student2@labschool.sch.id',
            'password_hash' => Hash::make('password'),
            'status' => 'active',
        ]);
        $studentUser2->roles()->attach($roleStudent->id);
        $this->student2 = Student::create([
            'user_id' => $studentUser2->id,
            'nis' => '24002',
            'nisn' => '0081234562',
            'nik_enc' => '3578010101010002',
            'full_name' => 'Anak Orang Lain',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2012-08-20',
            'religion' => 'Islam',
            'address' => 'Jl. Bendungan Sigura-gura No. 2, Malang',
            'entry_date' => '2024-07-15',
            'status' => 'aktif',
        ]);
        ClassStudent::create([
            'class_id' => $this->classB->id,
            'student_id' => $this->student2->id,
            'joined_at' => '2024-07-15',
        ]);

        // Guardians
        $this->guardianUser1 = User::create([
            'username' => 'guardian_one',
            'email' => 'guardian1@example.com',
            'password_hash' => Hash::make('password'),
            'status' => 'active',
        ]);
        $this->guardianUser1->roles()->attach($roleGuardian->id);
        $this->guardian1 = Guardian::create([
            'user_id' => $this->guardianUser1->id,
            'full_name' => 'Wali Satu (Orang Tua Student 1)',
            'phone' => '081234567891',
        ]);
        $this->guardian1->students()->attach($this->student1->id, ['relationship' => 'ayah', 'is_primary' => true]);

        $this->guardianUser2 = User::create([
            'username' => 'guardian_two',
            'email' => 'guardian2@example.com',
            'password_hash' => Hash::make('password'),
            'status' => 'active',
        ]);
        $this->guardianUser2->roles()->attach($roleGuardian->id);
        $this->guardian2 = Guardian::create([
            'user_id' => $this->guardianUser2->id,
            'full_name' => 'Wali Dua (Orang Tua Student 2)',
            'phone' => '081234567892',
        ]);
        $this->guardian2->students()->attach($this->student2->id, ['relationship' => 'ibu', 'is_primary' => true]);

        // Staff
        $this->staffUser1 = User::create([
            'username' => 'staff_tu',
            'email' => 'staff@labschool.sch.id',
            'password_hash' => Hash::make('password'),
            'status' => 'active',
        ]);
        $this->staffUser1->roles()->attach($roleStaff->id);
        $this->staff1 = Staff::create([
            'user_id' => $this->staffUser1->id,
            'full_name' => 'Budi Staf TU',
            'position' => 'Administrasi Kepegawaian',
            'unit' => 'Tata Usaha',
        ]);
    }

    /**
     * GAP-08: Tanpa token auth, seluruh endpoint /me/* dan /salary-slips wajib mengembalikan 401 Unauthorized.
     */
    public function test_gap_08_unauthenticated_requests_to_me_endpoints_return_401(): void
    {
        $endpoints = [
            '/api/v1/me/children',
            '/api/v1/me/children/some-id',
            '/api/v1/me/student-dashboard',
            '/api/v1/me/schedule',
            '/api/v1/me/teacher/dashboard',
            '/api/v1/me/teacher/classes',
            '/api/v1/me/staff/dashboard',
            '/api/v1/salary-slips',
        ];

        foreach ($endpoints as $url) {
            $response = $this->getJson($url);
            $response->assertStatus(401);
        }
    }

    /**
     * GAP-08: Ortu mengakses anak orang lain -> 403 Forbidden (Anti-IDOR).
     */
    public function test_gap_08_guardian_cannot_access_child_of_another_guardian_idor_403(): void
    {
        $token = $this->guardianUser1->createToken('token')->plainTextToken;
        $foreignChildId = $this->student2->id;

        $routesToTest = [
            "/api/v1/me/children/{$foreignChildId}",
            "/api/v1/me/children/{$foreignChildId}/attendance",
            "/api/v1/me/children/{$foreignChildId}/grades",
            "/api/v1/me/children/{$foreignChildId}/report-cards",
            "/api/v1/me/children/{$foreignChildId}/character-notes",
            "/api/v1/me/children/{$foreignChildId}/progress-reports",
        ];

        foreach ($routesToTest as $url) {
            $res = $this->withHeader('Authorization', 'Bearer '.$token)->getJson($url);
            $res->assertStatus(403);
        }
    }

    /**
     * GAP-08: Ortu mengakses data anaknya sendiri -> 200 OK dengan format sesuai types.
     */
    public function test_gap_08_guardian_can_access_own_child_data_200(): void
    {
        $token = $this->guardianUser1->createToken('token')->plainTextToken;
        $ownChildId = $this->student1->id;

        // 1. List children
        $resList = $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/me/children');
        $resList->assertStatus(200)
            ->assertJsonPath('data.0.id', $ownChildId)
            ->assertJsonPath('data.0.name', 'Anak Kandung Satu');

        // 2. Child detail
        $resDetail = $this->withHeader('Authorization', 'Bearer '.$token)->getJson("/api/v1/me/children/{$ownChildId}");
        $resDetail->assertStatus(200)
            ->assertJsonPath('data.id', $ownChildId);

        // 3. Child attendance
        $resAtt = $this->withHeader('Authorization', 'Bearer '.$token)->getJson("/api/v1/me/children/{$ownChildId}/attendance");
        $resAtt->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'summary' => ['present_days', 'sick_days', 'leave_days', 'unexcused_days', 'total_effective_days', 'attendance_percentage'],
                    'history',
                ],
            ]);

        // 4. Child grades
        $resGrades = $this->withHeader('Authorization', 'Bearer '.$token)->getJson("/api/v1/me/children/{$ownChildId}/grades");
        $resGrades->assertStatus(200);

        // 5. Child report cards
        $resReport = $this->withHeader('Authorization', 'Bearer '.$token)->getJson("/api/v1/me/children/{$ownChildId}/report-cards");
        $resReport->assertStatus(200);

        // 6. Child character notes
        $resChar = $this->withHeader('Authorization', 'Bearer '.$token)->getJson("/api/v1/me/children/{$ownChildId}/character-notes");
        $resChar->assertStatus(200);

        // 7. Child progress reports
        $resProg = $this->withHeader('Authorization', 'Bearer '.$token)->getJson("/api/v1/me/children/{$ownChildId}/progress-reports");
        $resProg->assertStatus(200);
    }

    /**
     * GAP-08: Portal Siswa mandiri hanya mengambil data siswa terautentikasi -> 200 OK.
     */
    public function test_gap_08_student_portal_endpoints_scoped_to_logged_in_student_200(): void
    {
        $token = $this->studentUser1->createToken('token')->plainTextToken;

        // 1. Dashboard
        $resDash = $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/me/student-dashboard');
        $resDash->assertStatus(200)
            ->assertJsonPath('data.name', 'Anak Kandung Satu')
            ->assertJsonPath('data.nis', '24001');

        // 2. Schedule
        $resSched = $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/me/schedule');
        $resSched->assertStatus(200);

        // 3. Grades
        $resGrades = $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/me/grades');
        $resGrades->assertStatus(200);

        // 4. Exams
        $resExams = $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/me/exams');
        $resExams->assertStatus(200);
    }

    /**
     * GAP-08: Guru tidak boleh mengakses data kelas yang tidak diampu -> 403 Forbidden.
     */
    public function test_gap_08_teacher_cannot_access_class_they_do_not_teach_idor_403(): void
    {
        $token = $this->teacherUser1->createToken('token')->plainTextToken;
        $foreignClassId = $this->classB->id;

        // Cek siswa di kelas lain
        $res = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/me/teacher/classes/{$foreignClassId}/students");
        $res->assertStatus(403);

        // Input presensi di kelas lain
        $resAtt = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/me/teacher/attendance/sessions', [
                'class_id' => $foreignClassId,
                'subject_id' => $this->subjectMath->id,
                'session_date' => '2026-10-14',
                'attendees' => [],
            ]);
        $resAtt->assertStatus(403);

        // Akses buku nilai di kelas lain
        $resGrades = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/me/teacher/grades?class_id={$foreignClassId}&subject_id={$this->subjectMath->id}");
        $resGrades->assertStatus(403);
    }

    /**
     * GAP-08: Guru yang bukan wali kelas dilarang mengakses /me/teacher/homeroom -> 403 Forbidden.
     */
    public function test_gap_08_teacher_non_homeroom_cannot_access_homeroom_data_403(): void
    {
        // Teacher 1 homeroom is Class A, but let's test a teacher without homeroom
        $plainTeacherUser = User::create([
            'username' => 'plain_teacher',
            'email' => 'plain_teacher@labschool.sch.id',
            'password_hash' => Hash::make('password'),
            'status' => 'active',
        ]);
        $roleTeacher = Role::where('code', 'teacher')->first();
        $plainTeacherUser->roles()->attach($roleTeacher->id);
        Teacher::create([
            'user_id' => $plainTeacherUser->id,
            'nip' => '199001012015011003',
            'full_name' => 'Guru Biasa Tanpa Kelas Binaan, S.Pd.',
            'position' => 'Guru',
            'status' => 'active',
        ]);

        $token = $plainTeacherUser->createToken('token')->plainTextToken;
        $res = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me/teacher/homeroom');

        $res->assertStatus(403);
    }

    /**
     * GAP-08: Guru yang sah dapat mengakses kelas ampuannya dan homeroom -> 200 OK.
     */
    public function test_gap_08_teacher_can_access_own_teaching_data_and_homeroom_200(): void
    {
        $token = $this->teacherUser2->createToken('token')->plainTextToken;

        // Dashboard
        $resDash = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me/teacher/dashboard');
        $resDash->assertStatus(200);

        // Classes
        $resClasses = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me/teacher/classes');
        $resClasses->assertStatus(200)
            ->assertJsonPath('data.0.id', $this->classB->id);

        // Schedules
        $resSched = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me/teacher/schedules');
        $resSched->assertStatus(200);

        // Class Students
        $resStudents = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/me/teacher/classes/{$this->classB->id}/students");
        $resStudents->assertStatus(200);

        // Homeroom
        $resHome = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me/teacher/homeroom');
        $resHome->assertStatus(200)
            ->assertJsonPath('data.class_id', $this->classB->id);
    }

    /**
     * GAP-08: Staf login dapat mengakses dashboard, absensi mandiri, dan tiketnya -> 200 OK.
     */
    public function test_gap_08_staff_portal_endpoints_scoped_to_logged_in_staff_200(): void
    {
        $token = $this->staffUser1->createToken('token')->plainTextToken;

        // Buat 1 tiket milik staffUser1 dan 1 tiket milik orang lain
        Ticket::create([
            'school_id' => Str::uuid()->toString(),
            'ticket_number' => 'TCK-001',
            'title' => 'AC Rusak Lab Komputer',
            'reporter_id' => $this->staffUser1->id,
            'description' => 'AC tidak dingin',
            'priority' => 'sedang',
            'status' => 'pending',
        ]);

        Ticket::create([
            'school_id' => Str::uuid()->toString(),
            'ticket_number' => 'TCK-002',
            'title' => 'Proyektor Ruang Guru Rusak',
            'reporter_id' => $this->guardianUser1->id,
            'description' => 'Lampu mati',
            'priority' => 'tinggi',
            'status' => 'pending',
        ]);

        // Dashboard
        $resDash = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me/staff/dashboard');
        $resDash->assertStatus(200);

        // Self attendance
        $resAtt = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me/staff/self-attendance');
        $resAtt->assertStatus(200);

        // Record check in
        $resCheckIn = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/me/staff/self-attendance', ['action' => 'check_in']);
        $resCheckIn->assertStatus(200);

        // My tickets (hanya tiket milik staffUser1)
        $resTickets = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me/staff/tickets');
        $resTickets->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.ticket_number', 'TCK-001');
    }

    /**
     * GAP-08: Pengajuan konseling mandiri oleh siswa -> 201 Created dengan student_id terkunci ke siswa aktif.
     */
    public function test_gap_08_counseling_request_submission_by_student_201(): void
    {
        $token = $this->studentUser1->createToken('token')->plainTextToken;

        $res = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/me/counseling-requests', [
                'reason' => 'Butuh konsultasi penjurusan dan minat bakat.',
                'is_urgent' => true,
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.student_id', $this->student1->id)
            ->assertJsonPath('data.topic', 'Butuh konsultasi penjurusan dan minat bakat.');
    }

    /**
     * COND-03: Permohonan konseling dengan topik eksplisit valid (<= 200 karakter).
     */
    public function test_cond_03_counseling_request_with_valid_explicit_topic(): void
    {
        $token = $this->studentUser1->createToken('token')->plainTextToken;

        $res = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/me/counseling-requests', [
                'topic' => 'Konsultasi Manajemen Waktu Belajar',
                'reason' => 'Sering bentrok antara jadwal les privat dan ekstrakurikuler.',
                'preferred_date' => now()->addDays(2)->toDateString(),
                'preferred_time' => '14:00 WIB',
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.student_id', $this->student1->id)
            ->assertJsonPath('data.topic', 'Konsultasi Manajemen Waktu Belajar')
            ->assertJsonPath('data.notes', 'Sering bentrok antara jadwal les privat dan ekstrakurikuler.');

        $this->assertDatabaseHas('counseling_requests', [
            'student_id' => $this->student1->id,
            'topic' => 'Konsultasi Manajemen Waktu Belajar',
        ]);
    }

    /**
     * COND-03: Permohonan konseling tanpa topik (null) fallback ke reason.
     */
    public function test_cond_03_counseling_request_with_null_topic_falls_back_to_reason(): void
    {
        $token = $this->studentUser1->createToken('token')->plainTextToken;

        $res = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/me/counseling-requests', [
                'topic' => null,
                'reason' => 'Alasan permohonan konseling tanpa topik khusus.',
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.student_id', $this->student1->id)
            ->assertJsonPath('data.topic', 'Alasan permohonan konseling tanpa topik khusus.');
    }

    /**
     * COND-03: Menolak topik yang melebihi batas 200 karakter dengan 422 Unprocessable Entity.
     */
    public function test_cond_03_counseling_request_rejects_topic_exceeding_200_chars(): void
    {
        $token = $this->studentUser1->createToken('token')->plainTextToken;
        $longTopic = str_repeat('A', 201);

        $res = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/me/counseling-requests', [
                'topic' => $longTopic,
                'reason' => 'Alasan valid untuk konseling.',
            ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['topic']);
    }

    /**
     * COND-03: Menolak topik bertipe data non-string (array).
     */
    public function test_cond_03_counseling_request_rejects_non_string_topic(): void
    {
        $token = $this->studentUser1->createToken('token')->plainTextToken;

        $res = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/me/counseling-requests', [
                'topic' => ['invalid' => 'format'],
                'reason' => 'Alasan valid untuk konseling.',
            ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['topic']);
    }

    /**
     * COND-03: Request tanpa autentikasi ditolak 401 Unauthorized.
     */
    public function test_cond_03_counseling_request_unauthenticated_returns_401(): void
    {
        $res = $this->postJson('/api/v1/me/counseling-requests', [
            'topic' => 'Topik Konseling',
            'reason' => 'Alasan konseling.',
        ]);

        $res->assertStatus(401);
    }

    /**
     * COND-03: User tanpa profil siswa (misal guru) ditolak 403 Forbidden.
     */
    public function test_cond_03_counseling_request_non_student_returns_403(): void
    {
        $teacherToken = $this->teacherUser1->createToken('token')->plainTextToken;

        $res = $this->withHeader('Authorization', 'Bearer '.$teacherToken)
            ->postJson('/api/v1/me/counseling-requests', [
                'topic' => 'Topik Konseling',
                'reason' => 'Alasan konseling.',
            ]);

        $res->assertStatus(403);
    }

    /**
     * GAP-08: Guru membuat tugas KBM dan menilai submission siswa -> 201 & 200.
     */
    public function test_gap_08_teacher_assignment_creation_and_grading_flow(): void
    {
        $teacherToken = $this->teacherUser1->createToken('token')->plainTextToken;

        // 1. Guru buat tugas di Class A
        $resAsg = $this->withHeader('Authorization', 'Bearer '.$teacherToken)
            ->postJson('/api/v1/me/teacher/assignments', [
                'title' => 'Tugas 1: Aljabar Linier',
                'class_id' => $this->classA->id,
                'subject_id' => $this->subjectMath->id,
                'description' => 'Kerjakan halaman 10-15',
            ]);
        $resAsg->assertStatus(201);
        $asgId = $resAsg->json('data.id');

        // 2. Buat dummy submission dari student 1
        $submission = AssignmentSubmission::create([
            'assignment_id' => $asgId,
            'student_id' => $this->student1->id,
            'notes' => 'Jawaban terlampir pak',
            'submitted_at' => now(),
        ]);

        // 3. Guru lihat submissions
        $resSubs = $this->withHeader('Authorization', 'Bearer '.$teacherToken)
            ->getJson("/api/v1/me/teacher/assignments/{$asgId}/submissions");
        $resSubs->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $submission->id);

        // 4. Guru beri nilai
        $resGrade = $this->withHeader('Authorization', 'Bearer '.$teacherToken)
            ->postJson("/api/v1/me/teacher/submissions/{$submission->id}/grade", [
                'score' => 95,
                'feedback' => 'Sangat rapi dan tepat.',
            ]);
        $resGrade->assertStatus(200);

        // 5. Guru lain coba beri nilai di tugas ini -> 403 Forbidden!
        $this->app['auth']->forgetGuards();
        $otherTeacherToken = $this->teacherUser2->createToken('token')->plainTextToken;
        $resForbidden = $this->withHeader('Authorization', 'Bearer '.$otherTeacherToken)
            ->postJson("/api/v1/me/teacher/submissions/{$submission->id}/grade", [
                'score' => 50,
            ]);
        $resForbidden->assertStatus(403);
    }

    /**
     * GAP-08: Pengajuan izin/cuti mandiri oleh guru dan staf -> 201 Created & scoped ke user.
     */
    public function test_gap_08_teacher_and_staff_leave_requests_flow(): void
    {
        $teacherToken = $this->teacherUser1->createToken('token')->plainTextToken;

        $resTeacherLeave = $this->withHeader('Authorization', 'Bearer '.$teacherToken)
            ->postJson('/api/v1/me/teacher/leave-requests', [
                'leave_type' => 'sakit',
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDays(2)->toDateString(),
                'reason' => 'Demam berdarah perlu istirahat dokter.',
            ]);
        $resTeacherLeave->assertStatus(201)
            ->assertJsonPath('data.requested_by', $this->teacherUser1->id);

        $resListTeacher = $this->withHeader('Authorization', 'Bearer '.$teacherToken)
            ->getJson('/api/v1/me/teacher/leave-requests');
        $resListTeacher->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // Staff leave
        $this->app['auth']->forgetGuards();
        $staffToken = $this->staffUser1->createToken('token')->plainTextToken;
        $resStaffLeave = $this->withHeader('Authorization', 'Bearer '.$staffToken)
            ->postJson('/api/v1/me/staff/leave-requests', [
                'leave_type' => 'annual',
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDays(1)->toDateString(),
                'reason' => 'Keperluan keluarga mendesak.',
            ]);
        $resStaffLeave->assertStatus(201)
            ->assertJsonPath('data.requested_by', $this->staffUser1->id);

        $resListStaff = $this->withHeader('Authorization', 'Bearer '.$staffToken)
            ->getJson('/api/v1/me/staff/leave-requests');
        $resListStaff->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    /**
     * GAP-08: Siswa mencoba mengakses endpoint guru/staf -> 403 Forbidden.
     */
    public function test_gap_08_wrong_role_access_returns_403(): void
    {
        $studentToken = $this->studentUser1->createToken('token')->plainTextToken;

        // Siswa coba panggil teacher classes
        $res1 = $this->withHeader('Authorization', 'Bearer '.$studentToken)
            ->getJson('/api/v1/me/teacher/classes');
        $res1->assertStatus(403);

        // Siswa coba panggil teacher homeroom
        $res2 = $this->withHeader('Authorization', 'Bearer '.$studentToken)
            ->getJson('/api/v1/me/teacher/homeroom');
        $res2->assertStatus(403);

        // Siswa coba panggil teacher dashboard
        $res3 = $this->withHeader('Authorization', 'Bearer '.$studentToken)
            ->getJson('/api/v1/me/teacher/dashboard');
        $res3->assertStatus(403);
    }

    /**
     * GAP-08: Notifikasi personal alias /me/notifications
     */
    public function test_gap_08_personal_notifications_alias_routes(): void
    {
        $token = $this->teacherUser1->createToken('token')->plainTextToken;

        // List notifications
        $res = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me/notifications');
        $res->assertStatus(200);

        // Mark all read
        $resAll = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/me/notifications/read-all');
        $resAll->assertStatus(200);
    }
}
