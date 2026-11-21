<?php

namespace Tests\Feature\Rpt;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\CharacterNote;
use App\Models\ClassStudent;
use App\Models\CounselingCase;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamParticipant;
use App\Models\ExamQuestion;
use App\Models\ExamRoom;
use App\Models\ExamSession;
use App\Models\GradeLevel;
use App\Models\GradePeriod;
use App\Models\Guardian;
use App\Models\Question;
use App\Models\QuestionBank;
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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $teacherUser;

    protected Teacher $teacher;

    protected User $studentUser1;

    protected Student $student1;

    protected User $studentUser2;

    protected Student $student2;

    protected User $guardianUser;

    protected Guardian $guardian;

    protected SchoolClass $class1;

    protected SchoolClass $class2;

    protected AcademicYear $academicYear;

    protected GradeLevel $gradeLevel;

    protected Subject $subject1;

    protected Subject $subject2;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'admin'], ['name' => 'Administrator', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Wali Murid', 'is_system' => true]);
        $roleCounselor = Role::firstOrCreate(['code' => 'counselor'], ['name' => 'Guru BK', 'is_system' => true]);

        $this->adminUser = User::create([
            'username' => 'admin_enh',
            'email' => 'admin_enh@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->teacherUser = User::create([
            'username' => 'guru_enh',
            'email' => 'guru_enh@sekolah.sch.id',
            'phone' => '081234567890',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher->id);

        $this->teacher = Teacher::create([
            'user_id' => $this->teacherUser->id,
            'full_name' => 'Bambang Guru, M.Pd',
            'nip' => '198001012005011001',
            'phone' => '081234567890',
            'status' => 'tetap',
        ]);

        $this->academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $this->gradeLevel = GradeLevel::create([
            'name' => 'Kelas 10',
            'level' => 10,
        ]);

        $this->class1 = SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->gradeLevel->id,
            'homeroom_teacher_id' => $this->teacher->id,
            'name' => '10-IPA-1',
            'capacity' => 36,
        ]);

        $this->class2 = SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->gradeLevel->id,
            'name' => '10-IPA-2',
            'capacity' => 36,
        ]);

        $this->studentUser1 = User::create([
            'username' => 'siswa_enh_1',
            'email' => 'siswa_enh1@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser1->roles()->attach($roleStudent->id);

        $this->student1 = Student::create([
            'user_id' => $this->studentUser1->id,
            'nis' => '10001',
            'nisn' => '0010001',
            'nik_enc' => 'enc_3578010101010001',
            'full_name' => 'Ananda Siswa 1',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        ClassStudent::create([
            'class_id' => $this->class1->id,
            'student_id' => $this->student1->id,
            'joined_at' => '2026-07-15',
        ]);

        $this->studentUser2 = User::create([
            'username' => 'siswa_enh_2',
            'email' => 'siswa_enh2@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser2->roles()->attach($roleStudent->id);

        $this->student2 = Student::create([
            'user_id' => $this->studentUser2->id,
            'nis' => '10002',
            'nisn' => '0010002',
            'nik_enc' => 'enc_3578010101010002',
            'full_name' => 'Bintang Siswa 2',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2010-02-02',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        ClassStudent::create([
            'class_id' => $this->class2->id,
            'student_id' => $this->student2->id,
            'joined_at' => '2026-07-15',
        ]);

        $this->guardianUser = User::create([
            'username' => 'wali_enh_1',
            'email' => 'wali_enh1@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->guardianUser->roles()->attach($roleGuardian->id);

        $this->guardian = Guardian::create([
            'user_id' => $this->guardianUser->id,
            'full_name' => 'H. Suryadi',
            'phone' => '081399887766',
        ]);

        $this->guardian->students()->attach($this->student1->id, [
            'relationship' => 'ayah',
            'is_primary' => true,
        ]);

        $this->subject1 = Subject::create([
            'code' => 'BIO-10',
            'name' => 'Biologi',
            'subject_group' => 'umum',
        ]);

        $this->subject2 = Subject::create([
            'code' => 'FIS-10',
            'name' => 'Fisika',
            'subject_group' => 'umum',
        ]);
    }

    /**
     * GAP C: Student Dashboard memiliki tomorrow_schedules dengan isolasi kelas.
     */
    public function test_gap_c_student_dashboard_tomorrow_schedules_isolation(): void
    {
        Sanctum::actingAs($this->studentUser1);

        $dayNames = ['minggu', 'senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu'];
        $tomorrowDay = $dayNames[Carbon::tomorrow()->dayOfWeek];

        $semester = Semester::create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'Semester Ganjil',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $assignment1 = TeachingAssignment::create([
            'academic_year_id' => $this->academicYear->id,
            'semester_id' => $semester->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject1->id,
            'class_id' => $this->class1->id,
        ]);

        $assignment2 = TeachingAssignment::create([
            'academic_year_id' => $this->academicYear->id,
            'semester_id' => $semester->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject2->id,
            'class_id' => $this->class2->id,
        ]);

        $room = Room::create([
            'code' => 'R-101',
            'name' => 'Ruang 101',
            'room_type' => 'kelas',
            'capacity' => 36,
        ]);

        $slot1 = TimeSlot::create([
            'name' => 'Jam 1-2',
            'start_time' => '07:30',
            'end_time' => '09:00',
        ]);

        $slot2 = TimeSlot::create([
            'name' => 'Jam 3-4',
            'start_time' => '09:15',
            'end_time' => '10:45',
        ]);

        // Schedule kelas 1 untuk besok
        Schedule::create([
            'semester_id' => $semester->id,
            'class_id' => $this->class1->id,
            'subject_id' => $this->subject1->id,
            'teacher_id' => $this->teacher->id,
            'room_id' => $room->id,
            'time_slot_id' => $slot1->id,
            'day_of_week' => Carbon::now()->addDay()->dayOfWeekIso,
        ]);

        // Schedule kelas 2 untuk besok (tidak boleh muncul untuk siswa 1)
        Schedule::create([
            'semester_id' => $semester->id,
            'class_id' => $this->class2->id,
            'subject_id' => $this->subject2->id,
            'teacher_id' => $this->teacher->id,
            'room_id' => $room->id,
            'time_slot_id' => $slot2->id,
            'day_of_week' => Carbon::now()->addDay()->dayOfWeekIso,
        ]);

        $res = $this->getJson('/api/v1/dashboard/student');
        $res->assertStatus(200);

        $tomorrowSchedules = $res->json('data.tomorrow_schedules');
        $this->assertIsArray($tomorrowSchedules);
        $this->assertCount(1, $tomorrowSchedules);
        $this->assertEquals('Biologi', $tomorrowSchedules[0]['subject']['name']);
        $this->assertEquals('07:30', $tomorrowSchedules[0]['time_slot']['start_time']);
        $this->assertEquals('09:00', $tomorrowSchedules[0]['time_slot']['end_time']);
        $this->assertNotNull($res->json('data.tomorrow_day'));
    }

    /**
     * GAP C: Student Dashboard menghitung rekap kehadiran bulan berjalan dengan benar.
     */
    public function test_gap_c_student_dashboard_current_month_attendance_recap(): void
    {
        Sanctum::actingAs($this->studentUser1);

        $now = Carbon::now();
        $thisMonthDate1 = $now->copy()->startOfMonth()->toDateString();
        $thisMonthDate2 = $now->copy()->startOfMonth()->addDays(2)->toDateString();
        $lastMonthDate = $now->copy()->subMonth()->toDateString();

        // Kehadiran bulan ini
        Attendance::create([
            'student_id' => $this->student1->id,
            'att_date' => $thisMonthDate1,
            'status' => 'hadir',
        ]);
        Attendance::create([
            'student_id' => $this->student1->id,
            'att_date' => $thisMonthDate2,
            'status' => 'sakit',
        ]);

        // Kehadiran bulan lalu (tidak boleh masuk hitungan bulan ini)
        Attendance::create([
            'student_id' => $this->student1->id,
            'att_date' => $lastMonthDate,
            'status' => 'hadir',
        ]);

        $res = $this->getJson('/api/v1/dashboard/student');
        $res->assertStatus(200);

        $attendanceRecap = $res->json('data.current_month_attendance');
        $this->assertNotNull($attendanceRecap);
        $this->assertEquals(1, $attendanceRecap['hadir']);
        $this->assertEquals(1, $attendanceRecap['sakit']);
        $this->assertEquals(0, $attendanceRecap['izin']);
        $this->assertEquals(0, $attendanceRecap['alpa']);
        $this->assertEquals(2, $attendanceRecap['total_recorded']);
    }

    /**
     * GAP C: Student Dashboard menampilkan ujian hari ini dengan requires_token tanpa membocorkan token.
     */
    public function test_gap_c_student_dashboard_today_exams_token_security(): void
    {
        Sanctum::actingAs($this->studentUser1);

        $exam = Exam::create([
            'subject_id' => $this->subject1->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Asesmen Harian Biologi Hari Ini',
            'exam_type' => 'harian',
            'duration_min' => 60,
            'start_at' => Carbon::now()->subMinutes(10),
            'end_at' => Carbon::now()->addHour(),
            'status' => 'berlangsung',
        ]);

        $room = Room::create([
            'code' => 'LAB-BIO',
            'name' => 'Lab Biologi',
            'room_type' => 'laboratorium',
            'capacity' => 30,
        ]);

        $examRoom = ExamRoom::create([
            'exam_id' => $exam->id,
            'room_id' => $room->id,
            'token' => 'SUPER_SECRET_TOKEN',
            'token_expires_at' => Carbon::now()->addHour(),
        ]);

        ExamParticipant::create([
            'exam_id' => $exam->id,
            'student_id' => $this->student1->id,
            'exam_room_id' => $examRoom->id,
        ]);

        $res = $this->getJson('/api/v1/dashboard/student');
        $res->assertStatus(200);

        $todayExams = $res->json('data.today_exams');
        $this->assertNotEmpty($todayExams);

        $examData = collect($todayExams)->firstWhere('id', $exam->id);
        $this->assertNotNull($examData);
        $this->assertTrue($examData['requires_token']);
        $this->assertEquals('belum_mulai', $examData['session_status']);

        // Pastikan tidak ada token rahasia yang bocor
        $responseContent = $res->getContent();
        $this->assertStringNotContainsString('SUPER_SECRET_TOKEN', $responseContent);

        // Periksa menu cepat
        $quickMenus = $res->json('data.quick_menus');
        $this->assertIsArray($quickMenus);
        $this->assertCount(3, $quickMenus);
    }

    /**
     * GAP D: Guardian Dashboard menampilkan kontak guru/wali kelas, konselor BK, catatan karakter, dan blokir IDOR.
     */
    public function test_gap_d_guardian_dashboard_contacts_character_note_and_security(): void
    {
        Sanctum::actingAs($this->guardianUser);

        $semester = Semester::create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'Semester 1',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $period = GradePeriod::create([
            'semester_id' => $semester->id,
            'month' => (int) Carbon::now()->month,
            'status' => 'aktif',
        ]);

        // Catatan karakter untuk anak 1
        CharacterNote::create([
            'student_id' => $this->student1->id,
            'period_id' => $period->id,
            'author_id' => $this->teacherUser->id,
            'homeroom_note' => 'Ananda sangat aktif dan membantu teman dalam kerja kelompok sains.',
            'scale_data' => ['integritas' => 4, 'gotong_royong' => 5],
        ]);

        // Catatan karakter untuk anak 2 (tidak boleh bocor ke wali anak 1)
        CharacterNote::create([
            'student_id' => $this->student2->id,
            'period_id' => $period->id,
            'author_id' => $this->teacherUser->id,
            'homeroom_note' => 'Catatan privat anak 2',
            'scale_data' => ['integritas' => 3],
        ]);

        // Buat akun konselor BK
        $counselorUser = User::create([
            'username' => 'guru_bk_enh',
            'email' => 'bk_enh@sekolah.sch.id',
            'phone' => '081298765432',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $roleCounselor = Role::where('code', 'counselor')->first();
        $counselorUser->roles()->attach($roleCounselor->id);

        Teacher::create([
            'user_id' => $counselorUser->id,
            'full_name' => 'Siti Rahmawati, S.Psi',
            'nip' => '198805052012012002',
            'phone' => '081298765432',
            'status' => 'tetap',
        ]);

        CounselingCase::create([
            'student_id' => $this->student1->id,
            'counselor_id' => $counselorUser->id,
            'title' => 'Konsultasi Minat Bakat',
            'summary_enc' => 'enc_ringkasan_kasus_karir',
            'category' => 'karir',
            'priority' => 'rendah',
            'status' => 'terbuka',
        ]);

        $res = $this->getJson('/api/v1/dashboard/guardian/'.$this->student1->id);
        $res->assertStatus(200);

        // Verifikasi catatan karakter anak 1
        $charNote = $res->json('data.latest_character_note');
        $this->assertNotNull($charNote);
        $this->assertEquals('Ananda sangat aktif dan membantu teman dalam kerja kelompok sains.', $charNote['homeroom_note']);
        $this->assertStringNotContainsString('Catatan privat anak 2', $res->getContent());

        // Verifikasi kontak wali kelas
        $homeroomContact = $res->json('data.homeroom_teacher_contact');
        $this->assertNotNull($homeroomContact);
        $this->assertEquals('Bambang Guru, M.Pd', $homeroomContact['name']);
        $this->assertEquals('081234567890', $homeroomContact['phone']);

        // Verifikasi kontak guru BK
        $counselorContact = $res->json('data.counselor_contact');
        $this->assertNotNull($counselorContact);
        $this->assertEquals('Siti Rahmawati, S.Psi', $counselorContact['name']);
        $this->assertEquals('081298765432', $counselorContact['phone']);

        // Verifikasi catatan keuangan out-of-scope
        $payment = $res->json('data.payment_administration');
        $this->assertNotNull($payment);
        $this->assertEquals('not_available', $payment['status']);

        // Verifikasi quick actions
        $this->assertIsArray($res->json('data.quick_actions'));
    }

    /**
     * GAP E: Teacher Dashboard menampilkan pending_essay_grading_count dan grading_progress.
     */
    public function test_gap_e_teacher_dashboard_pending_essay_grading_and_progress(): void
    {
        Sanctum::actingAs($this->teacherUser);

        $bank = QuestionBank::create([
            'name' => 'Bank Soal Kimia Guru',
            'subject_id' => $this->subject1->id,
            'grade_level_id' => $this->gradeLevel->id,
            'owner_id' => $this->teacherUser->id,
        ]);

        $qEssay1 = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'uraian',
            'body' => ['content' => 'Jelaskan hukum kekekalan massa!'],
            'weight' => 50.0,
            'status' => 'aktif',
        ]);

        $qEssay2 = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'uraian',
            'body' => ['content' => 'Jelaskan perbedaan endoterm dan eksoterm!'],
            'weight' => 50.0,
            'status' => 'aktif',
        ]);

        $exam = Exam::create([
            'subject_id' => $this->subject1->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Ujian Kimia Esai Guru',
            'exam_type' => 'harian',
            'duration_min' => 60,
            'start_at' => Carbon::now()->subHours(2),
            'end_at' => Carbon::now()->subHour(),
            'status' => 'selesai',
        ]);

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'question_id' => $qEssay1->id,
            'sort_order' => 1,
            'weight_override' => 50.0,
        ]);

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'question_id' => $qEssay2->id,
            'sort_order' => 2,
            'weight_override' => 50.0,
        ]);

        $session = ExamSession::create([
            'exam_id' => $exam->id,
            'student_id' => $this->student1->id,
            'status' => 'selesai',
            'started_at' => Carbon::now()->subHours(2),
            'submitted_at' => Carbon::now()->subHour(),
            'seed' => Str::random(16),
        ]);

        // Jawaban 1 sudah dinilai (score 45.0)
        ExamAnswer::create([
            'session_id' => $session->id,
            'question_id' => $qEssay1->id,
            'answer' => 'Massa sebelum reaksi sama dengan massa sesudah reaksi.',
            'score' => 45.0,
        ]);

        // Jawaban 2 belum dinilai (score null) -> harus dihitung dalam pending_essay_grading_count
        ExamAnswer::create([
            'session_id' => $session->id,
            'question_id' => $qEssay2->id,
            'answer' => 'Endoterm menyerap kalor, eksoterm melepas kalor.',
            'score' => null,
        ]);

        $res = $this->getJson('/api/v1/dashboard/teacher');
        $res->assertStatus(200);

        // Pending koreksi esai harus bernilai 1
        $this->assertEquals(1, $res->json('data.pending_essay_grading_count'));

        // Grading progress harus ada dan berupa array
        $gradingProgress = $res->json('data.grading_progress');
        $this->assertIsArray($gradingProgress);

        // Quick actions harus ada
        $this->assertIsArray($res->json('data.quick_actions'));
    }

    /**
     * GAP C: Student Dashboard melakukan deduplikasi presensi harian jika terdapat multiple scan pada tanggal yang sama.
     */
    public function test_student_dashboard_attendance_deduplication_on_same_date(): void
    {
        Sanctum::actingAs($this->studentUser1);

        $now = Carbon::now();
        $todayDate = $now->toDateString();

        // Presensi scan masuk pagi
        Attendance::create([
            'student_id' => $this->student1->id,
            'att_date' => $todayDate,
            'status' => 'hadir',
            'created_at' => $now->copy()->setTime(7, 0, 0),
        ]);

        // Presensi scan sore / duplikasi scan
        Attendance::create([
            'student_id' => $this->student1->id,
            'att_date' => $todayDate,
            'status' => 'hadir',
            'created_at' => $now->copy()->setTime(15, 0, 0),
        ]);

        $res = $this->getJson('/api/v1/dashboard/student');
        $res->assertStatus(200);

        // Hanya boleh dihitung 1 kali untuk tanggal tersebut dalam total_recorded bulan berjalan
        $recap = $res->json('data.current_month_attendance');
        $this->assertEquals(1, $recap['hadir']);
        $this->assertEquals(1, $recap['total_recorded']);
        $this->assertEquals(100.0, $recap['rate']);
    }

    /**
     * GAP D: Guardian Dashboard menormalkan tautan WhatsApp ke format internasional (kode 62).
     */
    public function test_guardian_dashboard_whatsapp_url_international_prefix_normalization(): void
    {
        Sanctum::actingAs($this->guardianUser);

        // Buat akun konselor BK
        $counselorUser = User::create([
            'username' => 'guru_bk_wa',
            'email' => 'bk_wa@sekolah.sch.id',
            'phone' => '085678901234',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $roleCounselor = Role::where('code', 'counselor')->first();
        $counselorUser->roles()->attach($roleCounselor->id);

        Teacher::create([
            'user_id' => $counselorUser->id,
            'full_name' => 'Konselor BK, S.Pd',
            'nip' => '199001012015012001',
            'phone' => '085678901234',
            'status' => 'tetap',
        ]);

        $res = $this->getJson('/api/v1/dashboard/guardian/'.$this->student1->id);
        $res->assertStatus(200);

        $quickActions = $res->json('data.quick_actions');
        $this->assertNotNull($quickActions['contact_homeroom_url']);
        // 081234567890 harus dinormalisasi menjadi 6281234567890
        $this->assertStringStartsWith('https://wa.me/6281234567890', $quickActions['contact_homeroom_url']);
        $this->assertStringStartsWith('https://wa.me/6285678901234', $quickActions['contact_counselor_url']);
    }

    /**
     * GAP D: Guardian Dashboard menerapkan tie-breaker deterministik (id desc) pada catatan karakter jika timestamp identik.
     */
    public function test_guardian_dashboard_character_note_deterministic_tie_breaker(): void
    {
        Sanctum::actingAs($this->guardianUser);

        $semester = Semester::create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'Semester 1',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $period1 = GradePeriod::create([
            'semester_id' => $semester->id,
            'month' => 1,
            'status' => 'aktif',
        ]);

        $period2 = GradePeriod::create([
            'semester_id' => $semester->id,
            'month' => 2,
            'status' => 'aktif',
        ]);

        $sameTimestamp = Carbon::now();

        $note1 = CharacterNote::create([
            'student_id' => $this->student1->id,
            'period_id' => $period1->id,
            'author_id' => $this->teacherUser->id,
            'homeroom_note' => 'Catatan Karakter Bulan 1',
            'scale_data' => ['integritas' => 4],
            'created_at' => $sameTimestamp,
        ]);

        $note2 = CharacterNote::create([
            'student_id' => $this->student1->id,
            'period_id' => $period2->id,
            'author_id' => $this->teacherUser->id,
            'homeroom_note' => 'Catatan Karakter Bulan 2 (Tie Breaker ID)',
            'scale_data' => ['integritas' => 5],
            'created_at' => $sameTimestamp,
        ]);

        $res = $this->getJson('/api/v1/dashboard/guardian/'.$this->student1->id);
        $res->assertStatus(200);

        $charNote = $res->json('data.latest_character_note');
        $this->assertNotNull($charNote);
        $this->assertEquals($note2->id, $charNote['id']);
        $this->assertEquals('Catatan Karakter Bulan 2 (Tie Breaker ID)', $charNote['homeroom_note']);
    }

    /**
     * GAP C: Student Dashboard mengabaikan kelas historis (left_at terisi) dan memilih kelas aktif.
     */
    public function test_student_dashboard_active_class_filters_out_historical_classes(): void
    {
        Sanctum::actingAs($this->studentUser1);

        // Kelas historis di mana siswa sudah keluar (left_at terisi)
        $oldClass = SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->gradeLevel->id,
            'name' => '10-OLD-ALUMNI',
            'capacity' => 30,
        ]);

        ClassStudent::create([
            'class_id' => $oldClass->id,
            'student_id' => $this->student1->id,
            'joined_at' => '2025-07-01',
            'left_at' => '2026-06-30', // Sudah keluar
        ]);

        $res = $this->getJson('/api/v1/dashboard/student');
        $res->assertStatus(200);

        // Kelas aktif harus tetap 10-IPA-1, bukan kelas lama
        $this->assertEquals('10-IPA-1', $res->json('data.student.class'));
    }

    /**
     * GAP D: Guardian Dashboard tanpa parameter URL otomatis me-resolve anak asuh pertama/utama.
     */
    public function test_guardian_dashboard_without_student_id_resolves_primary_child(): void
    {
        Sanctum::actingAs($this->guardianUser);

        $res = $this->getJson('/api/v1/dashboard/guardian');
        $res->assertStatus(200);

        $this->assertEquals($this->student1->id, $res->json('data.student.id'));
        $this->assertEquals('Ananda Siswa 1', $res->json('data.student.name'));
    }

    /**
     * GAP D: Guardian Dashboard mendukung resolusi melalui query parameter ?student_id={id}.
     */
    public function test_guardian_dashboard_with_query_param_student_id_succeeds(): void
    {
        Sanctum::actingAs($this->guardianUser);

        $res = $this->getJson("/api/v1/dashboard/guardian?student_id={$this->student1->id}");
        $res->assertStatus(200);

        $this->assertEquals($this->student1->id, $res->json('data.student.id'));
    }
}
