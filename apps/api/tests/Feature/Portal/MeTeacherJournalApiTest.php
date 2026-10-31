<?php

namespace Tests\Feature\Portal;

use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\ClassStudent;
use App\Models\GradeComponent;
use App\Models\GradeLevel;
use App\Models\GradePeriod;
use App\Models\Journal;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MeTeacherJournalApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacherUser;

    protected Teacher $teacher;

    protected User $studentUser;

    protected Student $student;

    protected SchoolClass $class;

    protected Subject $subject;

    protected Schedule $schedule;

    protected AcademicYear $academicYear;

    protected Semester $semester;

    protected GradePeriod $period;

    protected GradeComponent $component;

    protected function setUp(): void
    {
        parent::setUp();

        $teacherRole = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $studentRole = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $this->academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $this->semester = Semester::create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'Ganjil',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $gradeLevel = GradeLevel::create([
            'name' => 'Kelas 7',
            'sort_order' => 7,
        ]);

        $this->class = SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $gradeLevel->id,
            'name' => '7-A',
            'capacity' => 36,
        ]);

        $this->subject = Subject::create([
            'code' => 'MTK-7',
            'name' => 'Matematika',
            'subject_group' => 'wajib',
        ]);

        $this->teacherUser = User::create([
            'username' => 'guru.hendra',
            'email' => 'guru.hendra@labum.sch.id',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($teacherRole->id);

        $this->teacher = Teacher::create([
            'user_id' => $this->teacherUser->id,
            'nip' => '198501012010011001',
            'full_name' => 'Hendra Setiawan, S.Pd.',
            'status' => 'aktif',
        ]);

        TeachingAssignment::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'hours_per_week' => 4,
        ]);

        $timeSlot = TimeSlot::create([
            'name' => 'Jam ke-1',
            'start_time' => '07:00',
            'end_time' => '08:20',
            'day_type' => 'reguler',
        ]);

        $this->schedule = Schedule::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'time_slot_id' => $timeSlot->id,
            'day_of_week' => 1,
        ]);

        $this->studentUser = User::create([
            'username' => 'siswa.ahmad',
            'email' => 'siswa.ahmad@labum.sch.id',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($studentRole->id);

        $this->student = Student::create([
            'user_id' => $this->studentUser->id,
            'nis' => '26001',
            'nisn' => '0099001001',
            'nik_enc' => '3578010101090001',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-05-15',
            'religion' => 'Islam',
            'address' => 'Jl. Simpang Bogor No. 1, Malang',
            'entry_date' => '2026-07-01',
            'full_name' => 'Ahmad Fauzi',
            'status' => 'aktif',
        ]);

        ClassStudent::create([
            'class_id' => $this->class->id,
            'student_id' => $this->student->id,
            'joined_at' => '2026-07-01',
        ]);

        $this->period = GradePeriod::create([
            'semester_id' => $this->semester->id,
            'month' => 10,
            'status' => 'open',
        ]);

        $this->component = GradeComponent::create([
            'semester_id' => $this->semester->id,
            'subject_id' => $this->subject->id,
            'name' => 'Ulangan Harian 1',
            'code' => 'UH-1',
            'weight' => 30,
        ]);
    }

    /**
     * BE-ACD-02: Guru dapat membuat jurnal dengan format snake_case.
     */
    public function test_teacher_can_create_journal_with_snake_case_fields(): void
    {
        $response = $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/me/teacher/journals', [
                'schedule_id' => $this->schedule->id,
                'session_date' => '2026-10-16',
                'lesson_topic' => 'Aljabar Linier Dasar',
                'competence_indicator' => 'Siswa dapat menyelesaikan SPLDV',
                'method' => 'Tatap Muka',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('journals', [
            'teacher_id' => $this->teacher->id,
            'schedule_id' => $this->schedule->id,
            'topic' => 'Aljabar Linier Dasar',
        ]);
    }

    /**
     * BE-ACD-02: Guru dapat membuat jurnal dengan payload camelCase dari antarmuka Web.
     */
    public function test_teacher_can_create_journal_with_camel_case_fields(): void
    {
        $response = $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/me/teacher/journals', [
                'classId' => $this->class->id,
                'className' => '7-A',
                'subjectId' => $this->subject->id,
                'date' => '2026-10-16',
                'timeSlot' => '08:00 - 09:20',
                'lessonTopic' => 'Persamaan Kuadrat dan Faktorisasi',
                'competenceIndicator' => 'Mampu memfaktorkan persamaan kuadrat.',
                'learningObstacles' => 'Beberapa siswa kesulitan dengan tanda negatif.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.lessonTopic', 'Persamaan Kuadrat dan Faktorisasi')
            ->assertJsonPath('data.className', '7-A');

        $this->assertDatabaseHas('journals', [
            'teacher_id' => $this->teacher->id,
            'topic' => 'Persamaan Kuadrat dan Faktorisasi',
            'method' => 'Tatap Muka',
        ]);
    }

    /**
     * BE-ACD-02: Guru dapat mengambil daftar riwayat jurnal mengajar.
     */
    public function test_teacher_can_get_journals_list(): void
    {
        Journal::create([
            'teacher_id' => $this->teacher->id,
            'schedule_id' => $this->schedule->id,
            'session_date' => '2026-10-15',
            'topic' => 'Teorema Pythagoras',
            'method' => 'Tatap Muka',
            'notes' => 'Segitiga siku-siku',
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->getJson('/api/v1/me/teacher/journals');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.lessonTopic', 'Teorema Pythagoras');
    }

    /**
     * BE-ACD-02: Guru mencatat sesi presensi KBM dan otomatis membuat jurnal dan presensi siswa.
     */
    public function test_teacher_can_record_attendance_session_and_creates_journal_and_attendances(): void
    {
        $response = $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/me/teacher/attendance/sessions', [
                'classId' => $this->class->id,
                'subjectId' => $this->subject->id,
                'sessionDate' => '2026-10-16',
                'sessionTopic' => 'Pembahasan Soal Eksponen',
                'attendees' => [
                    [
                        'studentId' => $this->student->id,
                        'status' => 'present',
                        'notes' => 'Aktif di kelas',
                    ],
                ],
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('journals', [
            'teacher_id' => $this->teacher->id,
            'topic' => 'Pembahasan Soal Eksponen',
        ]);

        $this->assertDatabaseHas('attendances', [
            'student_id' => $this->student->id,
            'status' => 'hadir',
        ]);
        $attendance = Attendance::where('student_id', $this->student->id)->first();
        $this->assertNotNull($attendance);
        $this->assertEquals('2026-10-16', $attendance->att_date->format('Y-m-d'));
    }

    /**
     * BE-LMS-01: Guru dapat membuat tugas KBM dengan field camelCase dari Web.
     */
    public function test_teacher_can_create_assignment_with_camel_case_fields(): void
    {
        $response = $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/me/teacher/assignments', [
                'title' => 'Latihan Soal SPLDV 1',
                'classId' => $this->class->id,
                'subjectId' => $this->subject->id,
                'dueDate' => '2026-10-20',
                'description' => 'Kerjakan halaman 45 no 1-10.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Latihan Soal SPLDV 1');

        $this->assertDatabaseHas('assignments', [
            'teacher_id' => $this->teacher->id,
            'class_id' => $this->class->id,
            'title' => 'Latihan Soal SPLDV 1',
        ]);
    }

    /**
     * BE-LMS-03: Tugas portal menyimpan max_score & tautan komponen nilai.
     */
    public function test_portal_create_assignment_persists_max_score_and_component(): void
    {
        $response = $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/me/teacher/assignments', [
                'title' => 'Proyek Statistika',
                'classId' => $this->class->id,
                'subjectId' => $this->subject->id,
                'dueDate' => '2026-10-20',
                'max_score' => 80,
                'grade_component_id' => $this->component->id,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('assignments', [
            'title' => 'Proyek Statistika',
            'max_score' => 80,
            'grade_component_id' => $this->component->id,
        ]);
    }

    /**
     * BE-LMS-03: Komponen nilai dari mapel lain ditolak (cegah tautan silang mapel).
     */
    public function test_portal_create_assignment_rejects_component_of_other_subject(): void
    {
        $otherSubject = Subject::create(['code' => 'IPA-7', 'name' => 'IPA', 'subject_group' => 'wajib']);
        $otherComponent = GradeComponent::create([
            'semester_id' => $this->semester->id,
            'subject_id' => $otherSubject->id,
            'name' => 'UH IPA',
            'code' => 'UH-IPA',
            'weight' => 20,
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/me/teacher/assignments', [
                'title' => 'Tugas Salah Komponen',
                'classId' => $this->class->id,
                'subjectId' => $this->subject->id,
                'dueDate' => '2026-10-20',
                'grade_component_id' => $otherComponent->id,
            ]);

        $response->assertStatus(422);
    }

    /**
     * BE-LMS-03: Menilai tugas lewat portal guru ikut menulis ke buku nilai (grades).
     */
    public function test_portal_grade_submission_syncs_to_gradebook(): void
    {
        $assignment = Assignment::create([
            'teacher_id' => $this->teacher->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'title' => 'Tugas Tersinkron',
            'due_at' => '2026-10-20 23:59:00',
            'max_score' => 100,
            'grade_component_id' => $this->component->id,
        ]);

        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->student->id,
            'notes' => 'Terlampir',
            'submitted_at' => '2026-10-19 10:00:00',
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/me/teacher/submissions/'.$submission->id.'/grade', [
                'score' => 88,
                'feedback' => 'Kerja bagus.',
            ]);

        $response->assertOk();

        // Skor tersimpan di submission...
        $this->assertDatabaseHas('assignment_submissions', [
            'id' => $submission->id,
            'score' => 88,
        ]);

        // ...dan ikut masuk buku nilai pada komponen & periode yang sesuai.
        $this->assertDatabaseHas('grades', [
            'student_id' => $this->student->id,
            'component_id' => $this->component->id,
            'period_id' => $this->period->id,
            'value' => 88,
        ]);
    }

    /**
     * BE-LMS-03: Daftar tugas guru melaporkan jumlah yang sudah dinilai (bukan 0 statis).
     */
    public function test_portal_assignment_list_reports_graded_count(): void
    {
        $assignment = Assignment::create([
            'teacher_id' => $this->teacher->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'title' => 'Tugas Hitung Dinilai',
            'due_at' => '2026-10-20 23:59:00',
            'max_score' => 100,
        ]);

        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->student->id,
            'notes' => 'A',
            'submitted_at' => '2026-10-19 10:00:00',
            'score' => 90,
        ]);

        $response = $this->actingAs($this->teacherUser)
            ->getJson('/api/v1/me/teacher/assignments');

        $response->assertOk();
        $item = collect($response->json('data'))->firstWhere('id', $assignment->id);
        $this->assertNotNull($item);
        $this->assertSame(1, $item['gradedCount']);
        $this->assertSame(1, $item['submittedCount']);
    }

    /**
     * BE-LMS-02: Guru dapat memperbarui buku nilai secara massal dan tersimpan ke basis data.
     */
    public function test_teacher_can_update_bulk_grades_and_persists_to_database(): void
    {
        $response = $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/me/teacher/grades/bulk', [
                'classId' => $this->class->id,
                'subjectId' => $this->subject->id,
                'grades' => [
                    [
                        'studentId' => $this->student->id,
                        'scores' => [
                            $this->component->id => 92.5,
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('grades', [
            'student_id' => $this->student->id,
            'component_id' => $this->component->id,
            'value' => 92.5,
        ]);
    }

    /**
     * Keamanan: Permintaan tanpa token otentikasi ditolak 401.
     */
    public function test_unauthenticated_user_cannot_create_journal(): void
    {
        $this->postJson('/api/v1/me/teacher/journals', [
            'lesson_topic' => 'Tes Topic',
        ])->assertStatus(401);
    }

    /**
     * Keamanan: Pengguna bukan guru ditolak 403.
     */
    public function test_non_teacher_user_cannot_create_journal(): void
    {
        $this->actingAs($this->studentUser)
            ->postJson('/api/v1/me/teacher/journals', [
                'lesson_topic' => 'Tes Topic',
            ])->assertStatus(403);
    }

    /**
     * BE-SEC-01: ID yang hilang atau malformed ditolak 422 tanpa fallback diam-diam.
     */
    public function test_be_sec_01_missing_or_malformed_ids_rejected_with_422(): void
    {
        // 1. Jurnal dengan non-UUID dummy classId/subjectId
        $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/me/teacher/journals', [
                'classId' => 'cls-dummy-id',
                'subjectId' => 'sub-mat',
                'lesson_topic' => 'Topik Aljabar',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        // 2. Presensi sesi tanpa ID kelas dan mapel
        $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/me/teacher/attendance/sessions', [
                'sessionDate' => '2026-10-19',
                'sessionTopic' => 'Topik Presensi',
            ])
            ->assertStatus(422);

        // 3. Materi tanpa ID mata pelajaran
        $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/me/teacher/materials', [
                'title' => 'Modul Geometri',
            ])
            ->assertStatus(422);

        // 4. Tugas KBM dengan format UUID tidak valid
        $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/me/teacher/assignments', [
                'title' => 'Tugas Matrik',
                'classId' => 'cls-7a',
                'subjectId' => 'sub-mat',
            ])
            ->assertStatus(422);

        // 5. Buku nilai tanpa classId dan subjectId
        $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/me/teacher/grades/bulk', [
                'grades' => [],
            ])
            ->assertStatus(422);
    }

    /**
     * BE-SEC-01: ID jadwal malformed atau tidak konsisten ditolak 422.
     */
    public function test_be_sec_01_malformed_or_inconsistent_schedule_id_rejected(): void
    {
        // Malformed schedule_id
        $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/me/teacher/journals', [
                'schedule_id' => 'not-a-valid-uuid',
                'lesson_topic' => 'Topik Aljabar',
            ])
            ->assertStatus(422);

        // Schedule id valid tapi classId yang dikirim tidak konsisten
        $otherClassUuid = (string) Str::uuid();
        $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/me/teacher/journals', [
                'schedule_id' => $this->schedule->id,
                'class_id' => $otherClassUuid,
                'lesson_topic' => 'Topik Aljabar',
            ])
            ->assertStatus(422);
    }

    /**
     * BE-SEC-01: Guru tidak dapat memakai jadwal milik guru lain (Anti-IDOR).
     */
    public function test_be_sec_01_teacher_cannot_use_schedule_belonging_to_another_teacher(): void
    {
        // Buat guru kedua (Budi) dengan jadwalnya sendiri
        $teacherRole = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $userBudi = User::create([
            'username' => 'guru.budi',
            'email' => 'guru.budi@labum.sch.id',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $userBudi->roles()->attach($teacherRole->id);

        $teacherBudi = Teacher::create([
            'user_id' => $userBudi->id,
            'nip' => '198702022011011002',
            'full_name' => 'Budi Prasetyo, S.Pd.',
            'status' => 'aktif',
        ]);

        $subjectBudi = Subject::create([
            'code' => 'BIG-7',
            'name' => 'Bahasa Inggris',
            'subject_group' => 'wajib',
        ]);

        $scheduleBudi = Schedule::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->class->id,
            'subject_id' => $subjectBudi->id,
            'teacher_id' => $teacherBudi->id,
            'time_slot_id' => $this->schedule->time_slot_id,
            'day_of_week' => 2,
        ]);

        // Guru Hendra mencoba membuat jurnal memakai scheduleBudi->id
        $response = $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/me/teacher/journals', [
                'schedule_id' => $scheduleBudi->id,
                'session_date' => '2026-10-19',
                'lesson_topic' => 'Attempted IDOR Topic',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('journals', [
            'teacher_id' => $this->teacher->id,
            'schedule_id' => $scheduleBudi->id,
        ]);

        // Guru Hendra mencoba mencatat presensi sesi memakai scheduleBudi->id
        $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/me/teacher/attendance/sessions', [
                'schedule_id' => $scheduleBudi->id,
                'sessionDate' => '2026-10-19',
            ])
            ->assertStatus(403);
    }

    /**
     * BE-SEC-01: Guru tidak dapat mengakses mapel atau kelas yang tidak diampu (Otorisasi eksplisit).
     */
    public function test_be_sec_01_teacher_cannot_use_unassigned_subject_or_class(): void
    {
        $unassignedSubject = Subject::create([
            'code' => 'BIO-7',
            'name' => 'Biologi Lingkungan',
            'subject_group' => 'peminatan',
        ]);

        $unassignedClass = SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->class->grade_level_id,
            'name' => '7-Z',
            'capacity' => 30,
        ]);

        // 1. Jurnal dengan mapel yang tidak diampu
        $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/me/teacher/journals', [
                'classId' => $this->class->id,
                'subjectId' => $unassignedSubject->id,
                'lessonTopic' => 'Fotosintesis Tanaman',
            ])
            ->assertStatus(403);

        // 2. Tugas KBM dengan kelas yang tidak diampu
        $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/me/teacher/assignments', [
                'title' => 'Laporan Ekosistem',
                'classId' => $unassignedClass->id,
                'subjectId' => $this->subject->id,
            ])
            ->assertStatus(403);

        // 3. Materi dengan mapel yang tidak diampu
        $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/me/teacher/materials', [
                'title' => 'Modul Sel Hewan',
                'subjectId' => $unassignedSubject->id,
            ])
            ->assertStatus(403);

        // 4. Presensi KBM dengan mapel yang tidak diampu
        $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/me/teacher/attendance/sessions', [
                'classId' => $this->class->id,
                'subjectId' => $unassignedSubject->id,
            ])
            ->assertStatus(403);

        // 5. Update buku nilai dengan kelas yang tidak diampu
        $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/me/teacher/grades/bulk', [
                'classId' => $unassignedClass->id,
                'subjectId' => $this->subject->id,
                'grades' => [],
            ])
            ->assertStatus(403);
    }

    /**
     * BE-SEC-01: Manipulasi payload teacherId tidak mengubah kepemilikan data.
     */
    public function test_be_sec_01_payload_manipulation_of_teacher_id_is_ignored(): void
    {
        $fakeTeacherUuid = (string) Str::uuid();

        $response = $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/me/teacher/assignments', [
                'title' => 'Tugas Matematika Diskrit',
                'classId' => $this->class->id,
                'subjectId' => $this->subject->id,
                'teacherId' => $fakeTeacherUuid,
                'teacher_id' => $fakeTeacherUuid,
                'dueDate' => '2026-10-25',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('assignments', [
            'title' => 'Tugas Matematika Diskrit',
            'teacher_id' => $this->teacher->id,
        ]);
        $this->assertDatabaseMissing('assignments', [
            'teacher_id' => $fakeTeacherUuid,
        ]);
    }

    /**
     * BE-SEC-01: Guru dengan beberapa penugasan dapat menggunakan semua penugasan yang sah.
     */
    public function test_be_sec_01_teacher_with_multiple_assignments_can_use_any_valid_assignment(): void
    {
        $classB = SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->class->grade_level_id,
            'name' => '7-B',
            'capacity' => 36,
        ]);

        $subjectFisika = Subject::create([
            'code' => 'FIS-7',
            'name' => 'Fisika Dasar',
            'subject_group' => 'wajib',
        ]);

        TeachingAssignment::create([
            'semester_id' => $this->semester->id,
            'class_id' => $classB->id,
            'subject_id' => $subjectFisika->id,
            'teacher_id' => $this->teacher->id,
            'hours_per_week' => 4,
        ]);

        // Berhasil membuat jurnal untuk penugasan kedua
        $resJournal = $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/me/teacher/journals', [
                'classId' => $classB->id,
                'subjectId' => $subjectFisika->id,
                'lessonTopic' => 'Hukum Newton I, II, III',
                'competenceIndicator' => 'Mampu menganalisis gaya gerak',
            ]);
        $resJournal->assertStatus(201);

        // Berhasil membuat tugas KBM untuk penugasan kedua
        $resAssignment = $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/me/teacher/assignments', [
                'title' => 'Eksperimen Gaya Gesek',
                'classId' => $classB->id,
                'subjectId' => $subjectFisika->id,
                'dueDate' => '2026-10-28',
            ]);
        $resAssignment->assertStatus(201);

        // Berhasil mengunggah materi untuk mata pelajaran kedua
        $resMaterial = $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/me/teacher/materials', [
                'title' => 'Slide Dinamika Partikel',
                'subjectId' => $subjectFisika->id,
            ]);
        $resMaterial->assertStatus(201);
    }

    /**
     * BE-PORTAL-GURU: Roster kelas menampilkan program belajar siswa (prioritas program siswa).
     */
    public function test_class_students_roster_exposes_student_program(): void
    {
        $this->student->update(['program_type' => 'tahfidz']);

        $response = $this->actingAs($this->teacherUser)
            ->getJson('/api/v1/me/teacher/classes/'.$this->class->id.'/students');

        $response->assertOk()
            ->assertJsonPath('data.0.programType', 'tahfidz')
            ->assertJsonPath('data.0.programLabel', 'Tahfidz');
    }

    /**
     * BE-PORTAL-GURU: Roster memakai program rombel sebagai fallback via helper (prioritas program siswa).
     */
    public function test_class_students_roster_reflects_icp_program(): void
    {
        $this->student->update(['program_type' => 'icp']);

        $response = $this->actingAs($this->teacherUser)
            ->getJson('/api/v1/me/teacher/classes/'.$this->class->id.'/students');

        $response->assertOk()
            ->assertJsonPath('data.0.programType', 'icp')
            ->assertJsonPath('data.0.programLabel', 'ICP');
    }

    /**
     * BE-PORTAL-GURU: Daftar perwalian (homeroom) menampilkan program tiap siswa.
     */
    public function test_homeroom_roster_exposes_student_program(): void
    {
        $this->class->update(['homeroom_teacher_id' => $this->teacher->id]);
        $this->student->update(['program_type' => 'inklusi']);

        $response = $this->actingAs($this->teacherUser)
            ->getJson('/api/v1/me/teacher/homeroom');

        $response->assertOk()
            ->assertJsonPath('data.students.0.programType', 'inklusi')
            ->assertJsonPath('data.students.0.programLabel', 'Inklusi');
    }
}
