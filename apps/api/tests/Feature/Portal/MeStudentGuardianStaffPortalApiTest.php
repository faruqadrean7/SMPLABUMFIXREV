<?php

namespace Tests\Feature\Portal;

use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\ClassStudent;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\ExamRoom;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradeLevel;
use App\Models\GradePeriod;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MeStudentGuardianStaffPortalApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $guardianUser1;

    protected User $guardianUser2;

    protected Guardian $guardian1;

    protected Guardian $guardian2;

    protected Student $student1;

    protected Student $student2;

    protected SchoolClass $classA;

    protected User $studentUser1;

    protected User $teacherUser1;

    protected Teacher $teacher1;

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
            'name' => 'Semester Ganjil',
            'semester_type' => 'odd',
            'start_date' => '2024-07-15',
            'end_date' => '2024-12-20',
            'is_active' => true,
        ]);

        $this->timeSlot = TimeSlot::create([
            'name' => 'Jam Ke-1',
            'start_time' => '07:00:00',
            'end_time' => '08:20:00',
            'order' => 1,
            'is_break' => false,
        ]);

        // Teacher & Subject
        $this->teacherUser1 = User::create([
            'username' => 'guru.budi',
            'email' => 'budi@sekolah.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->teacherUser1->roles()->attach($roleTeacher->id);

        $this->teacher1 = Teacher::create([
            'user_id' => $this->teacherUser1->id,
            'nip' => '198501012010011001',
            'full_name' => 'Budi Santoso, S.Pd',
            'gender' => 'male',
            'status' => 'aktif',
        ]);

        $this->subjectMath = Subject::create([
            'code' => 'MAT-7',
            'name' => 'Matematika VII',
            'subject_group' => 'wajib',
            'passing_grade' => 75.0,
        ]);

        $gradeLevel = GradeLevel::firstOrCreate(
            ['name' => 'Kelas 7'],
            ['sort_order' => 7]
        );

        // Class
        $this->classA = SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $gradeLevel->id,
            'name' => 'VII-A',
            'homeroom_teacher_id' => $this->teacher1->id,
            'capacity' => 32,
            'status' => 'active',
        ]);

        // Students
        $this->studentUser1 = User::create([
            'username' => 'siswa.anita',
            'email' => 'anita@sekolah.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->studentUser1->roles()->attach($roleStudent->id);

        $this->student1 = Student::create([
            'user_id' => $this->studentUser1->id,
            'nis' => '24001',
            'nisn' => '0098765432',
            'nik_enc' => '3578010101010001',
            'full_name' => 'Anita Rahmawati',
            'gender' => 'P',
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
            'username' => 'siswa.bambang',
            'email' => 'bambang@sekolah.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $studentUser2->roles()->attach($roleStudent->id);

        $this->student2 = Student::create([
            'user_id' => $studentUser2->id,
            'nis' => '24002',
            'nisn' => '0098765433',
            'nik_enc' => '3578010101010002',
            'full_name' => 'Bambang Pamungkas',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-06-12',
            'religion' => 'Islam',
            'address' => 'Jl. Bendungan Sigura-gura No. 2, Malang',
            'entry_date' => '2024-07-15',
            'status' => 'aktif',
        ]);

        // Guardians
        $this->guardianUser1 = User::create([
            'username' => 'ortu.anita',
            'email' => 'ortu.anita@gmail.com',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->guardianUser1->roles()->attach($roleGuardian->id);

        $this->guardian1 = Guardian::create([
            'user_id' => $this->guardianUser1->id,
            'full_name' => 'Bpk. Rahmadi (Wali Anita)',
            'phone' => '081234567890',
        ]);
        $this->guardian1->students()->attach($this->student1->id, ['relationship' => 'father']);

        $this->guardianUser2 = User::create([
            'username' => 'ortu.bambang',
            'email' => 'ortu.bambang@gmail.com',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->guardianUser2->roles()->attach($roleGuardian->id);

        $this->guardian2 = Guardian::create([
            'user_id' => $this->guardianUser2->id,
            'full_name' => 'Ibu Siti (Wali Bambang)',
            'phone' => '081234567891',
        ]);
        $this->guardian2->students()->attach($this->student2->id, ['relationship' => 'mother']);

        // Staff
        $this->staffUser1 = User::create([
            'username' => 'staf.eko',
            'email' => 'eko@sekolah.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->staffUser1->roles()->attach($roleStaff->id);

        $this->staff1 = Staff::create([
            'user_id' => $this->staffUser1->id,
            'nip' => '199002022015021002',
            'full_name' => 'Eko Prasetyo, S.Kom',
            'gender' => 'male',
            'status' => 'aktif',
            'unit' => 'Tata Usaha & Sarpras',
        ]);
    }

    /**
     * BE-FE-PORTAL-STUDENT-GUARDIAN-STAFF-AUDIT:
     * Siswa mengakses dashboard, jadwal, nilai, dan ujian CBT.
     */
    public function test_be_fe_portal_student_dashboard_schedule_grades_exams(): void
    {
        // 1. Dashboard
        $response = $this->actingAs($this->studentUser1, 'sanctum')
            ->getJson('/api/v1/me/student-dashboard');

        $response->assertOk()
            ->assertJsonPath('data.name', 'Anita Rahmawati')
            ->assertJsonPath('data.nis', '24001')
            ->assertJsonPath('data.className', 'VII-A');

        // 2. Schedule
        $schedule = Schedule::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'time_slot_id' => $this->timeSlot->id,
            'day_of_week' => 1,
        ]);

        $resSched = $this->actingAs($this->studentUser1, 'sanctum')
            ->getJson('/api/v1/me/schedule');

        $resSched->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subjectName', 'Matematika VII');

        // 3. Grades
        $period = GradePeriod::create([
            'semester_id' => $this->semester->id,
            'month' => 10,
            'status' => 'open',
        ]);

        $component = GradeComponent::create([
            'subject_id' => $this->subjectMath->id,
            'semester_id' => $this->semester->id,
            'class_id' => $this->classA->id,
            'code' => 'TUGAS-1',
            'name' => 'Tugas Aljabar',
            'weight' => 20,
        ]);

        Grade::create([
            'student_id' => $this->student1->id,
            'component_id' => $component->id,
            'period_id' => $period->id,
            'value' => 88.5,
        ]);

        $resGrades = $this->actingAs($this->studentUser1, 'sanctum')
            ->getJson('/api/v1/me/grades');

        $resGrades->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subjectName', 'Matematika VII')
            ->assertJsonPath('data.0.finalScore', 88.5);

        // 4. Exams
        $exam = Exam::create([
            'title' => 'UTS Matematika VII',
            'subject_id' => $this->subjectMath->id,
            'exam_type' => 'harian',
            'duration_min' => 90,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(2),
            'status' => 'published',
            'created_by' => $this->teacherUser1->id,
        ]);

        $room = Room::create([
            'name' => 'Lab Komputer 1',
            'code' => 'LAB-1',
            'room_type' => 'laboratorium',
            'capacity' => 30,
        ]);

        $examRoom = ExamRoom::create([
            'exam_id' => $exam->id,
            'room_id' => $room->id,
            'proctor_id' => $this->teacherUser1->id,
            'session_no' => 1,
            'token' => 'ABCDEF',
        ]);

        ExamParticipant::create([
            'exam_id' => $exam->id,
            'student_id' => $this->student1->id,
            'exam_room_id' => $examRoom->id,
            'status' => 'registered',
        ]);

        $resExams = $this->actingAs($this->studentUser1, 'sanctum')
            ->getJson('/api/v1/me/exams');

        $resExams->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'UTS Matematika VII');
    }

    /**
     * BE-FE-PORTAL-STUDENT-GUARDIAN-STAFF-AUDIT:
     * Siswa melihat tugas KBM dan mengumpulkan tugas via tautan dokumen/drive & catatan.
     */
    public function test_be_fe_portal_student_tasks_list_and_submission(): void
    {
        $assignment = Assignment::create([
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'title' => 'Latihan Himpunan Mandiri',
            'description' => 'Kerjakan soal 1-10 di buku catatan atau Google Doc.',
            'due_at' => now()->addDays(2),
            'max_score' => 100,
        ]);

        // List tasks before submission
        $resList = $this->actingAs($this->studentUser1, 'sanctum')
            ->getJson('/api/v1/me/tasks');

        $resList->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'aktif')
            ->assertJsonPath('data.0.title', 'Latihan Himpunan Mandiri')
            ->assertJsonPath('data.0.teacherName', 'Budi Santoso, S.Pd');

        // Submit task with driveLink & notes (camelCase and snake_case supported)
        $resSubmit = $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson("/api/v1/me/tasks/{$assignment->id}/submit", [
                'driveLink' => 'https://drive.google.com/file/d/12345/view',
                'fileName' => 'Tugas_Anita_Himpunan.pdf',
                'notes' => 'Berikut hasil pengerjaan latihan himpunan saya.',
            ]);

        $resSubmit->assertCreated()
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.message', 'Tugas berhasil dikumpulkan.');

        // List tasks after submission -> status must be 'dikumpulkan'
        $resListAfter = $this->actingAs($this->studentUser1, 'sanctum')
            ->getJson('/api/v1/me/tasks');

        $resListAfter->assertOk()
            ->assertJsonPath('data.0.status', 'dikumpulkan');

        $this->assertNotNull($resListAfter->json('data.0.submission'));

        $this->assertDatabaseHas('assignment_submissions', [
            'assignment_id' => $assignment->id,
            'student_id' => $this->student1->id,
        ]);
    }

    /**
     * BE-FE-PORTAL-STUDENT-GUARDIAN-STAFF-AUDIT:
     * Siswa mengajukan permohonan konseling dan melihat riwayat konselingnya.
     */
    public function test_be_fe_portal_student_counseling_request_flow(): void
    {
        // Siswa membuat janji temu konseling mandiri
        $resCreate = $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson('/api/v1/me/counseling-requests', [
                'topic' => 'Konsultasi Penjurusan IPA/IPS',
                'category' => 'Akademik',
                'preferredDate' => now()->addDays(3)->toDateString(),
                'preferredTime' => 'Sepulang Sekolah (13:30 WIB)',
                'reason' => 'Saya ingin berdiskusi mengenai prospek pilihan minat studi lanjut.',
            ]);

        $resCreate->assertCreated()
            ->assertJsonPath('data.topic', 'Konsultasi Penjurusan IPA/IPS')
            ->assertJsonPath('data.status', 'pending');

        // Siswa melihat daftar permohonan konseling
        $resList = $this->actingAs($this->studentUser1, 'sanctum')
            ->getJson('/api/v1/me/counseling-requests');

        $resList->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.topic', 'Konsultasi Penjurusan IPA/IPS')
            ->assertJsonPath('data.0.status', 'menunggu');

        $this->assertDatabaseHas('counseling_requests', [
            'student_id' => $this->student1->id,
            'requested_by' => $this->studentUser1->id,
            'requester_role' => 'student',
            'topic' => 'Konsultasi Penjurusan IPA/IPS',
            'status' => 'pending',
        ]);
    }

    /**
     * BE-FE-PORTAL-STUDENT-GUARDIAN-STAFF-AUDIT:
     * Wali murid memantau data anak dan sistem menolak akses IDOR anak lain (403 Forbidden).
     */
    public function test_be_fe_portal_guardian_monitoring_and_idor_protection(): void
    {
        // 1. Wali melihat daftar anak sah
        $resChildren = $this->actingAs($this->guardianUser1, 'sanctum')
            ->getJson('/api/v1/me/children');

        $resChildren->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->student1->id)
            ->assertJsonPath('data.0.name', 'Anita Rahmawati');

        // 2. Presensi anak
        Attendance::create([
            'student_id' => $this->student1->id,
            'att_date' => now()->toDateString(),
            'status' => 'hadir',
            'note' => 'Hadir tepat waktu',
        ]);

        $resAtt = $this->actingAs($this->guardianUser1, 'sanctum')
            ->getJson("/api/v1/me/children/{$this->student1->id}/attendance");

        $resAtt->assertOk()
            ->assertJsonPath('data.summary.presentDays', 1);

        // 3. IDOR Protection: Wali 1 mengakses data anak 2 (Bambang)
        $resForbidden = $this->actingAs($this->guardianUser1, 'sanctum')
            ->getJson("/api/v1/me/children/{$this->student2->id}/attendance");

        $resForbidden->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-FE-PORTAL-STUDENT-GUARDIAN-STAFF-AUDIT:
     * Wali murid mengajukan surat izin anak dengan camelCase & normalisasi jenis izin.
     */
    public function test_be_fe_portal_guardian_submits_leave_request_for_child(): void
    {
        $res = $this->actingAs($this->guardianUser1, 'sanctum')
            ->postJson('/api/v1/leave-requests', [
                'studentId' => $this->student1->id,
                'leaveType' => 'sick',
                'startDate' => now()->toDateString(),
                'endDate' => now()->addDay()->toDateString(),
                'reason' => 'Siswa demam tinggi dan disarankan dokter beristirahat.',
            ]);

        $res->assertCreated()
            ->assertJsonPath('data.leave_type', 'sakit')
            ->assertJsonPath('data.status', 'menunggu');

        $this->assertDatabaseHas('leave_requests', [
            'student_id' => $this->student1->id,
            'leave_type' => 'sakit',
            'requested_by' => $this->guardianUser1->id,
            'status' => 'menunggu',
        ]);
    }

    /**
     * BE-FE-PORTAL-STUDENT-GUARDIAN-STAFF-AUDIT:
     * Staf Tata Usaha: Dashboard, Presensi Mandiri (Check-In & Check-Out), Izin Kerja, dan Tiket Sarpras.
     */
    public function test_be_fe_portal_staff_full_operational_flow(): void
    {
        // 1. Dashboard Staf
        $resDash = $this->actingAs($this->staffUser1, 'sanctum')
            ->getJson('/api/v1/me/staff/dashboard');

        $resDash->assertOk()
            ->assertJsonPath('data.staffName', 'Eko Prasetyo, S.Kom')
            ->assertJsonPath('data.department', 'Tata Usaha & Sarpras');

        // 2. Presensi Check-in
        $resCheckIn = $this->actingAs($this->staffUser1, 'sanctum')
            ->postJson('/api/v1/me/staff/self-attendance', [
                'action' => 'check_in',
            ]);

        $resCheckIn->assertOk()
            ->assertJsonPath('data.success', true);

        // 3. Presensi Check-out
        $resCheckOut = $this->actingAs($this->staffUser1, 'sanctum')
            ->postJson('/api/v1/me/staff/self-attendance', [
                'action' => 'check_out',
            ]);

        $resCheckOut->assertOk()
            ->assertJsonPath('data.success', true);

        $this->assertDatabaseHas('staff_attendances', [
            'user_id' => $this->staffUser1->id,
            'status' => 'hadir',
        ]);

        // 4. Pengajuan Izin/Cuti Kerja Staf dengan camelCase & tipe 'sick' -> 'sakit'
        $resLeave = $this->actingAs($this->staffUser1, 'sanctum')
            ->postJson('/api/v1/me/staff/leave-requests', [
                'leaveType' => 'sick',
                'startDate' => now()->toDateString(),
                'endDate' => now()->toDateString(),
                'reason' => 'Periksa kesehatan rutin di klinik.',
            ]);

        $resLeave->assertCreated()
            ->assertJsonPath('data.leave_type', 'sakit')
            ->assertJsonPath('data.status', 'menunggu');

        $this->assertDatabaseHas('leave_requests', [
            'requested_by' => $this->staffUser1->id,
            'leave_type' => 'sakit',
            'status' => 'menunggu',
        ]);

        // 5. Pembuatan Tiket Aduan Sarpras oleh Staf
        $resTicket = $this->actingAs($this->staffUser1, 'sanctum')
            ->postJson('/api/v1/tickets', [
                'title' => 'Pendingin Ruangan TU Rusak',
                'category' => 'Laboratorium & Elektronik',
                'location' => 'Kantor Tata Usaha',
                'description' => 'AC unit 2 tidak menyala dan mengeluarkan dengung.',
                'priority' => 'tinggi',
            ]);

        $resTicket->assertCreated();

        $this->assertDatabaseHas('tickets', [
            'reporter_id' => $this->staffUser1->id,
            'title' => 'Pendingin Ruangan TU Rusak',
            'priority' => 'tinggi',
            'status' => 'pending',
        ]);

        // 6. Daftar Tiket Staf
        $resStaffTickets = $this->actingAs($this->staffUser1, 'sanctum')
            ->getJson('/api/v1/me/staff/tickets');

        $resStaffTickets->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Pendingin Ruangan TU Rusak');
    }

    /**
     * SPRINT 39 REGRESSION: Pencabutan relasi perwalian seketika memblokir seluruh endpoint anak (Anti-IDOR / 403).
     */
    public function test_be_sec_sprint39_guardian_child_revocation_blocks_access_403(): void
    {
        // 1. Verifikasi awalnya wali sah dapat mengakses profil anak
        $resBefore = $this->actingAs($this->guardianUser1, 'sanctum')
            ->getJson("/api/v1/me/children/{$this->student1->id}");
        $resBefore->assertOk()
            ->assertJsonPath('data.id', $this->student1->id);

        // 2. Cabut relasi perwalian di database (misal dicabut oleh pengadilan / verifikasi sekolah)
        $this->guardian1->students()->detach($this->student1->id);

        // 3. Verifikasi seketika seluruh endpoint anak terblokir dengan 403 Forbidden
        $endpoints = [
            "/api/v1/me/children/{$this->student1->id}",
            "/api/v1/me/children/{$this->student1->id}/attendance",
            "/api/v1/me/children/{$this->student1->id}/grades",
            "/api/v1/me/children/{$this->student1->id}/report-cards",
            "/api/v1/me/children/{$this->student1->id}/character-notes",
            "/api/v1/me/children/{$this->student1->id}/progress-reports",
        ];

        foreach ($endpoints as $url) {
            $res = $this->actingAs($this->guardianUser1, 'sanctum')->getJson($url);
            $res->assertStatus(403)
                ->assertJsonPath('error.code', 'FORBIDDEN');
        }
    }

    /**
     * SPRINT 39 REGRESSION: Siswa yang dipindahkan atau tidak terdaftar di kelas diblokir dari mengumpulkan tugas (Anti-IDOR / 403).
     */
    public function test_be_sec_sprint39_student_unenrollment_blocks_assignment_submission_403(): void
    {
        // 1. Buat tugas di kelas A
        $assignment = Assignment::create([
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'title' => 'Tugas Aljabar Lanjutan',
            'description' => 'Kerjakan soal 1-10 di buku latihan.',
            'due_at' => Carbon::now()->addDays(3),
            'max_score' => 100.0,
        ]);

        // 2. Siswa terdaftar awalnya dapat melihat tugas
        $resList = $this->actingAs($this->studentUser1, 'sanctum')
            ->getJson('/api/v1/me/tasks');
        $resList->assertOk();

        // 3. Siswa dipindahkan / dicabut dari kelas A
        ClassStudent::where('student_id', $this->student1->id)
            ->where('class_id', $this->classA->id)
            ->delete();

        // 4. Akses detail tugas kelas A ditolak (403)
        $resDetail = $this->actingAs($this->studentUser1, 'sanctum')
            ->getJson("/api/v1/assignments/{$assignment->id}");
        $resDetail->assertStatus(403);

        // 5. Upaya pengumpulan tugas kelas A ditolak (403)
        $resSubmit = $this->actingAs($this->studentUser1, 'sanctum')
            ->postJson("/api/v1/me/tasks/{$assignment->id}/submit", [
                'notes' => 'Mencoba submit tanpa terdaftar di kelas.',
            ]);
        $resSubmit->assertStatus(403);
    }

    /**
     * SPRINT 39 REGRESSION: Tiket sarpras hanya dapat diakses pelapor, teknisi/manajer yang berwenang (Anti-IDOR / 403).
     */
    public function test_be_sec_sprint39_ticket_cross_user_isolation_blocks_unauthorized_access_403(): void
    {
        // 1. Buat tiket oleh Staf 1
        $resTicket = $this->actingAs($this->staffUser1, 'sanctum')
            ->postJson('/api/v1/tickets', [
                'title' => 'Kerusakan Proyektor TU',
                'description' => 'Lampu proyektor berkedip merah terus.',
                'priority' => 'sedang',
            ]);
        $resTicket->assertCreated();
        $ticketId = $resTicket->json('data.id');

        // 2. Siswa atau user lain yang bukan pelapor dan bukan teknisi/manajer mencoba melihat tiket tersebut
        $resForeign = $this->actingAs($this->studentUser1, 'sanctum')
            ->getJson("/api/v1/tickets/{$ticketId}");
        $resForeign->assertStatus(403);
    }

    /**
     * SPRINT 39 REGRESSION: Pencabutan SK mengajar (Teaching Assignment) seketika memblokir input jurnal guru (Anti-IDOR / 403).
     */
    public function test_be_sec_sprint39_teacher_assignment_revocation_blocks_journal_403(): void
    {
        // 1. Buat teaching assignment dan jadwal untuk guru
        $assignment = TeachingAssignment::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'hours_per_week' => 4,
        ]);

        $schedule = Schedule::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'time_slot_id' => $this->timeSlot->id,
            'day_of_week' => 1,
        ]);

        // 2. Cabut penugasan dan jadwal mengajar guru untuk kelas dan mata pelajaran tersebut
        $assignment->delete();
        $schedule->delete();

        // 3. Guru mencoba menginput jurnal KBM dengan subjectId/classId yang sudah dicabut
        $resDenied = $this->actingAs($this->teacherUser1, 'sanctum')
            ->postJson('/api/v1/me/teacher/journals', [
                'classId' => $this->classA->id,
                'subjectId' => $this->subjectMath->id,
                'scheduleId' => $schedule->id,
                'date' => Carbon::now()->toDateString(),
                'topic' => 'Sistem Persamaan Linier',
                'teachingMethod' => 'Diskusi Interaktif',
                'studentAttendanceCount' => 30,
            ]);

        // Harus 403 Forbidden karena guru sudah tidak memiliki teaching assignment yang sah
        $resDenied->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }
}
