<?php

namespace Tests\Feature\Ntf;

use App\Jobs\ProcessReminderDispatchJob;
use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\ClassStudent;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\ExamRoom;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\ReminderDispatch;
use App\Models\Role;
use App\Models\Room;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Ntf\ReminderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReminderEngineTest extends TestCase
{
    use RefreshDatabase;

    protected ReminderService $reminderService;

    protected User $studentUser1;

    protected User $studentUser2;

    protected Student $student1;

    protected Student $student2;

    protected User $teacherUser;

    protected Teacher $teacher;

    protected User $guardianUser;

    protected Guardian $guardian;

    protected SchoolClass $class;

    protected Subject $subject;

    protected string $schoolId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reminderService = app(ReminderService::class);
        $this->schoolId = (string) Str::uuid();

        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleGuardian = Role::firstOrCreate(['code' => 'parent'], ['name' => 'Wali', 'is_system' => true]);

        // 1. Users
        $this->studentUser1 = User::create([
            'school_id' => $this->schoolId,
            'username' => 'siswa_cbt_1',
            'email' => 'siswa1@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser1->roles()->attach($roleStudent->id);

        $this->studentUser2 = User::create([
            'school_id' => $this->schoolId,
            'username' => 'siswa_cbt_2',
            'email' => 'siswa2@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser2->roles()->attach($roleStudent->id);

        $this->teacherUser = User::create([
            'school_id' => $this->schoolId,
            'username' => 'walas_mipa',
            'email' => 'walas@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher->id);

        $this->guardianUser = User::create([
            'school_id' => $this->schoolId,
            'username' => 'wali_siswa_1',
            'email' => 'wali1@keluarga.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->guardianUser->roles()->attach($roleGuardian->id);

        // 2. Domain models
        $this->teacher = Teacher::create([
            'school_id' => $this->schoolId,
            'user_id' => $this->teacherUser->id,
            'nip' => '198501012010011001',
            'full_name' => 'Budi Santoso, S.Pd.',
            'status' => 'aktif',
        ]);

        $this->student1 = Student::create([
            'school_id' => $this->schoolId,
            'user_id' => $this->studentUser1->id,
            'nis' => '10001',
            'nisn' => '0010000001',
            'nik_enc' => '3171010101010001',
            'full_name' => 'Ahmad Siswa',
            'gender' => 'L',
            'birth_place' => 'Jakarta',
            'birth_date' => '2008-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $this->student2 = Student::create([
            'school_id' => $this->schoolId,
            'user_id' => $this->studentUser2->id,
            'nis' => '10002',
            'nisn' => '0010000002',
            'nik_enc' => '3171010101010002',
            'full_name' => 'Bambang Siswa',
            'gender' => 'L',
            'birth_place' => 'Jakarta',
            'birth_date' => '2008-02-02',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $this->guardian = Guardian::create([
            'school_id' => $this->schoolId,
            'user_id' => $this->guardianUser->id,
            'full_name' => 'Hendra Walimurid',
            'phone' => '081234567890',
        ]);

        $this->student1->guardians()->attach($this->guardian->id, [
            'relationship' => 'ayah',
            'is_primary' => true,
        ]);

        $ay = AcademicYear::create([
            'school_id' => $this->schoolId,
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $gl = GradeLevel::create([
            'school_id' => $this->schoolId,
            'name' => '10',
            'sort_order' => 1,
        ]);

        $this->class = SchoolClass::create([
            'school_id' => $this->schoolId,
            'academic_year_id' => $ay->id,
            'grade_level_id' => $gl->id,
            'homeroom_teacher_id' => $this->teacher->id,
            'name' => 'X-MIPA-1',
            'capacity' => 36,
        ]);

        ClassStudent::create([
            'class_id' => $this->class->id,
            'student_id' => $this->student1->id,
            'joined_at' => '2026-07-15',
        ]);

        ClassStudent::create([
            'class_id' => $this->class->id,
            'student_id' => $this->student2->id,
            'joined_at' => '2026-07-15',
        ]);

        $this->subject = Subject::create([
            'school_id' => $this->schoolId,
            'code' => 'MAT-10',
            'name' => 'Matematika Wajib',
            'subject_group' => 'umum',
        ]);

        $this->room = Room::create([
            'school_id' => $this->schoolId,
            'code' => 'LAB-KOMP-1',
            'name' => 'Lab Komputer 1',
            'room_type' => 'laboratorium',
            'capacity' => 40,
        ]);
    }

    protected function createExamRoom(Exam $exam): ExamRoom
    {
        return ExamRoom::create([
            'exam_id' => $exam->id,
            'room_id' => $this->room->id,
            'session_no' => 1,
            'token' => 'ABCDEF',
        ]);
    }

    public function test_be_ntf_01_cbt_reminder_dispatches_only_to_registered_participants(): void
    {
        // Ujian 5 jam lagi (masuk ke milestone 24h)
        $exam = Exam::create([
            'school_id' => $this->schoolId,
            'subject_id' => $this->subject->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Penilaian Tengah Semester Matematika',
            'exam_type' => 'pts',
            'duration_min' => 90,
            'start_at' => Carbon::now()->addHours(5),
            'end_at' => Carbon::now()->addHours(7),
            'status' => 'scheduled',
            'sync_node_token' => 'SECRET_TOKEN_DO_NOT_LEAK',
        ]);

        $examRoom = $this->createExamRoom($exam);

        // Daftarkan HANYA student1
        ExamParticipant::create([
            'exam_id' => $exam->id,
            'exam_room_id' => $examRoom->id,
            'student_id' => $this->student1->id,
        ]);

        $result = $this->reminderService->dispatchCbtExamReminders($this->schoolId, false);

        $this->assertEquals(1, $result['dispatched']);
        $this->assertEquals(0, $result['skipped']);

        // Student 1 menerima notifikasi
        $notif1 = Notification::where('user_id', $this->studentUser1->id)->first();
        $this->assertNotNull($notif1);
        $this->assertStringContainsString('Pengingat Ujian CBT', $notif1->title);
        $this->assertEquals('/portal/siswa/cbt', $notif1->data['deep_link']);

        // Keamanan: sync_node_token tidak boleh bocor dalam notifikasi
        $this->assertStringNotContainsString('SECRET_TOKEN_DO_NOT_LEAK', json_encode($notif1->data));
        $this->assertStringNotContainsString('SECRET_TOKEN_DO_NOT_LEAK', $notif1->body);

        // Student 2 (bukan peserta) TIDAK menerima notifikasi
        $notif2 = Notification::where('user_id', $this->studentUser2->id)->first();
        $this->assertNull($notif2);

        // Record reminder_dispatches tercatat
        $this->assertDatabaseHas('reminder_dispatches', [
            'event_type' => 'cbt_exam',
            'event_id' => $exam->id,
            'recipient_id' => $this->studentUser1->id,
            'milestone' => '24h',
            'status' => 'dispatched',
        ]);
    }

    public function test_be_ntf_01_cbt_reminder_is_strictly_idempotent_on_repeated_runs(): void
    {
        $exam = Exam::create([
            'school_id' => $this->schoolId,
            'subject_id' => $this->subject->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Kuis Aljabar Linear',
            'exam_type' => 'kuis',
            'duration_min' => 45,
            'start_at' => Carbon::now()->addHours(2),
            'end_at' => Carbon::now()->addHours(3),
            'status' => 'scheduled',
        ]);

        $examRoom = $this->createExamRoom($exam);

        ExamParticipant::create([
            'exam_id' => $exam->id,
            'exam_room_id' => $examRoom->id,
            'student_id' => $this->student1->id,
        ]);

        // Run pertama: dispatch berhasil
        $run1 = $this->reminderService->dispatchCbtExamReminders($this->schoolId, false);
        $this->assertEquals(1, $run1['dispatched']);
        $this->assertEquals(0, $run1['skipped']);

        // Run kedua: di-skip karena unique key database
        $run2 = $this->reminderService->dispatchCbtExamReminders($this->schoolId, false);
        $this->assertEquals(0, $run2['dispatched']);
        $this->assertEquals(1, $run2['skipped']);

        // Notifikasi hanya ada 1 (tidak duplikat)
        $this->assertEquals(1, Notification::where('user_id', $this->studentUser1->id)->count());
        $this->assertEquals(1, ReminderDispatch::where('event_id', $exam->id)->count());
    }

    public function test_be_ntf_01_lms_assignment_due_reminder_only_notifies_unsubmitted_students(): void
    {
        $assignment = Assignment::create([
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Latihan Soal Trigonometri Bab 3',
            'due_at' => Carbon::now()->addHours(12),
            'max_score' => 100,
        ]);

        // Student 1 SUDAH mengumpulkan tugas
        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->student1->id,
            'submitted_at' => Carbon::now()->subHour(),
        ]);

        // Student 2 BELUM mengumpulkan tugas

        $result = $this->reminderService->dispatchLmsAssignmentReminders($this->schoolId, false);

        $this->assertEquals(1, $result['dispatched']);
        $this->assertEquals(0, $result['skipped']);

        // Student 1 (sudah mengumpulkan) TIDAK menerima notifikasi
        $notif1 = Notification::where('user_id', $this->studentUser1->id)->first();
        $this->assertNull($notif1);

        // Student 2 (belum mengumpulkan) Menerima notifikasi
        $notif2 = Notification::where('user_id', $this->studentUser2->id)->first();
        $this->assertNotNull($notif2);
        $this->assertStringContainsString('Pengingat Tenggat Tugas', $notif2->title);
        $this->assertEquals('/portal/siswa/tugas', $notif2->data['deep_link']);

        // Pastikan idempotency
        $run2 = $this->reminderService->dispatchLmsAssignmentReminders($this->schoolId, false);
        $this->assertEquals(0, $run2['dispatched']);
        $this->assertEquals(1, $run2['skipped']);
    }

    public function test_be_ntf_01_attendance_cutoff_reminds_homeroom_teacher(): void
    {
        // Belum ada data presensi untuk kelas X-MIPA-1 hari ini
        $result = $this->reminderService->dispatchAttendanceReminders($this->schoolId, false);

        $this->assertGreaterThanOrEqual(1, $result['dispatched']);

        $notif = Notification::where('user_id', $this->teacherUser->id)
            ->where('notif_type', 'attendance_alert')
            ->first();

        $this->assertNotNull($notif);
        $this->assertStringContainsString('Pengingat Presensi: Kelas X-MIPA-1', $notif->title);
        $this->assertEquals('/portal/guru/presensi', $notif->data['deep_link']);
    }

    public function test_be_ntf_01_attendance_alpha_alerts_parent_guardian(): void
    {
        // Buat data presensi alpha untuk student 1 hari ini
        $today = Carbon::now(config('app.school_timezone', 'Asia/Jakarta'))->toDateString();
        $attendance = Attendance::create([
            'student_id' => $this->student1->id,
            'att_date' => $today,
            'status' => 'alpha',
            'recorded_by' => $this->teacherUser->id,
        ]);

        $result = $this->reminderService->dispatchAttendanceReminders($this->schoolId, false);

        // Wali murid menerima notifikasi ketidakhadiran
        $notif = Notification::where('user_id', $this->guardianUser->id)
            ->where('notif_type', 'attendance_alert')
            ->first();

        $this->assertNotNull($notif);
        $this->assertStringContainsString('Pemberitahuan Presensi', $notif->title);
        $this->assertStringContainsString('Ahmad Siswa', $notif->body);
        $this->assertEquals('/portal/wali/presensi', $notif->data['deep_link']);
    }

    public function test_be_ntf_01_respects_user_notification_preference(): void
    {
        // Nonaktifkan notifikasi akademik untuk student 1
        NotificationPreference::create([
            'user_id' => $this->studentUser1->id,
            'notif_type' => 'academic_announcement',
            'channel_push' => false,
            'channel_wa' => false,
            'channel_email' => false,
            'is_enabled' => false,
        ]);

        $exam = Exam::create([
            'school_id' => $this->schoolId,
            'subject_id' => $this->subject->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Ujian Akhir Semester',
            'exam_type' => 'pas',
            'duration_min' => 120,
            'start_at' => Carbon::now()->addHours(4),
            'end_at' => Carbon::now()->addHours(6),
            'status' => 'scheduled',
        ]);

        $examRoom = $this->createExamRoom($exam);

        ExamParticipant::create([
            'exam_id' => $exam->id,
            'exam_room_id' => $examRoom->id,
            'student_id' => $this->student1->id,
        ]);

        $this->reminderService->dispatchCbtExamReminders($this->schoolId, false);

        // In-app notification TIDAK dibuat karena preference is_enabled = false
        $this->assertEquals(0, Notification::where('user_id', $this->studentUser1->id)->count());

        // Status di reminder_dispatches tercatat sebagai skipped_by_preference
        $this->assertDatabaseHas('reminder_dispatches', [
            'event_type' => 'cbt_exam',
            'event_id' => $exam->id,
            'recipient_id' => $this->studentUser1->id,
            'status' => 'skipped_by_preference',
        ]);
    }

    public function test_be_ntf_01_artisan_reminder_dispatch_command_runs_successfully(): void
    {
        $this->artisan('reminder:dispatch', [
            '--type' => 'all',
            '--school' => $this->schoolId,
        ])
            ->assertExitCode(0);
    }

    public function test_be_ntf_01_tenant_isolation_prevents_cross_school_leak(): void
    {
        $otherSchoolId = (string) Str::uuid();

        // Ujian di sekolah lain
        $otherExam = Exam::create([
            'school_id' => $otherSchoolId,
            'subject_id' => $this->subject->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Ujian Sekolah Lain',
            'exam_type' => 'pts',
            'duration_min' => 60,
            'start_at' => Carbon::now()->addHours(3),
            'end_at' => Carbon::now()->addHours(4),
            'status' => 'scheduled',
        ]);

        $examRoom = ExamRoom::create([
            'exam_id' => $otherExam->id,
            'room_id' => $this->room->id,
            'session_no' => 1,
            'token' => 'OTHTOK',
        ]);

        ExamParticipant::create([
            'exam_id' => $otherExam->id,
            'exam_room_id' => $examRoom->id,
            'student_id' => $this->student1->id,
        ]);

        // Jalankan scheduler HANYA untuk this->schoolId
        $result = $this->reminderService->dispatchCbtExamReminders($this->schoolId, false);

        // Tidak ada yang di-dispatch karena event berasal dari sekolah lain
        $this->assertEquals(0, $result['dispatched']);
        $this->assertEquals(0, Notification::where('user_id', $this->studentUser1->id)->count());
    }

    public function test_be_ntf_01_concurrent_worker_claim_is_strictly_atomic(): void
    {
        $exam = Exam::create([
            'school_id' => $this->schoolId,
            'subject_id' => $this->subject->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Ujian Concurrency',
            'exam_type' => 'pts',
            'duration_min' => 60,
            'start_at' => Carbon::now()->addHours(5),
            'end_at' => Carbon::now()->addHours(6),
            'status' => 'scheduled',
        ]);

        // Simulasi dispatch dibuat
        $dispatch = ReminderDispatch::create([
            'school_id' => $this->schoolId,
            'event_type' => 'cbt_exam',
            'event_id' => $exam->id,
            'recipient_id' => $this->studentUser1->id,
            'milestone' => '24h',
            'channel' => 'in_app',
            'status' => 'pending',
            'metadata' => [
                'title' => 'Ujian Concurrency',
                'body' => 'Ujian concurrency test',
                'notif_type' => 'academic_announcement',
            ],
        ]);

        // Worker 1 dan Worker 2 berjalan bersamaan untuk dispatch ID yang sama
        $job1 = new ProcessReminderDispatchJob($dispatch->id);
        $job2 = new ProcessReminderDispatchJob($dispatch->id);

        $job1->handle();
        $job2->handle();

        // Notifikasi hanya terkirim tepat satu kali
        $this->assertEquals(1, Notification::where('user_id', $this->studentUser1->id)->count());

        $dispatch->refresh();
        $this->assertEquals('dispatched', $dispatch->status);
    }

    public function test_be_ntf_01_stale_lms_assignment_is_safely_skipped_if_submitted_after_planning(): void
    {
        $assignment = Assignment::create([
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Tugas Stale Test',
            'due_at' => Carbon::now()->addHours(6),
            'max_score' => 100,
        ]);

        // Dispatch terencana saat student belum submit
        $dispatch = ReminderDispatch::create([
            'school_id' => $this->schoolId,
            'event_type' => 'lms_assignment',
            'event_id' => $assignment->id,
            'recipient_id' => $this->studentUser1->id,
            'milestone' => '24h',
            'channel' => 'in_app',
            'status' => 'pending',
            'metadata' => [
                'title' => 'Tenggat Tugas',
                'body' => 'Segera kumpulkan tugas',
                'notif_type' => 'academic_announcement',
            ],
        ]);

        // Siswa mengumpulkan tugas SEBELUM worker mengeksekusi job
        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->student1->id,
            'submitted_at' => Carbon::now(),
        ]);

        // Worker mengeksekusi job
        $job = new ProcessReminderDispatchJob($dispatch->id);
        $job->handle();

        $dispatch->refresh();
        $this->assertEquals('skipped_stale_submitted', $dispatch->status);

        // Tidak ada notifikasi yang terkirim ke inbox siswa
        $this->assertEquals(0, Notification::where('user_id', $this->studentUser1->id)->count());
    }

    public function test_be_ntf_01_stale_cbt_exam_is_safely_skipped_if_cancelled(): void
    {
        $exam = Exam::create([
            'school_id' => $this->schoolId,
            'subject_id' => $this->subject->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Ujian Batal',
            'exam_type' => 'kuis',
            'duration_min' => 30,
            'start_at' => Carbon::now()->addHours(2),
            'end_at' => Carbon::now()->addHours(3),
            'status' => 'cancelled', // Ujian dibatalkan
        ]);

        $dispatch = ReminderDispatch::create([
            'school_id' => $this->schoolId,
            'event_type' => 'cbt_exam',
            'event_id' => $exam->id,
            'recipient_id' => $this->studentUser1->id,
            'milestone' => '1h',
            'channel' => 'in_app',
            'status' => 'pending',
            'metadata' => [
                'title' => 'Ujian Segera Mulai',
                'body' => 'Bersiap ujian',
                'notif_type' => 'academic_announcement',
            ],
        ]);

        $job = new ProcessReminderDispatchJob($dispatch->id);
        $job->handle();

        $dispatch->refresh();
        $this->assertEquals('skipped_stale_cancelled', $dispatch->status);
        $this->assertEquals(0, Notification::where('user_id', $this->studentUser1->id)->count());
    }

    public function test_be_ntf_01_stale_attendance_is_safely_skipped_if_recorded_or_status_changed(): void
    {
        $today = Carbon::now(config('app.school_timezone', 'Asia/Jakarta'))->toDateString();

        // 1. Daily cutoff: presensi sudah diisi sebelum worker berjalan
        $dispatchRecord = ReminderDispatch::create([
            'school_id' => $this->schoolId,
            'event_type' => 'att_recording',
            'event_id' => $this->class->id,
            'recipient_id' => $this->teacherUser->id,
            'milestone' => "daily_cutoff_{$today}",
            'channel' => 'in_app',
            'status' => 'pending',
            'metadata' => [
                'title' => 'Presensi Belum Direkap',
                'body' => 'Segera isi presensi',
                'notif_type' => 'attendance_alert',
            ],
        ]);

        // Guru mengisi presensi sebelum worker sempat jalan
        Attendance::create([
            'student_id' => $this->student1->id,
            'att_date' => $today,
            'status' => 'hadir',
            'recorded_by' => $this->teacherUser->id,
        ]);

        $job = new ProcessReminderDispatchJob($dispatchRecord->id);
        $job->handle();

        $dispatchRecord->refresh();
        $this->assertEquals('skipped_stale_recorded', $dispatchRecord->status);
        $this->assertEquals(0, Notification::where('user_id', $this->teacherUser->id)->count());

        // 2. Alpha status: data absensi diubah menjadi 'izin' sebelum worker jalan
        $attendanceAlpha = Attendance::create([
            'student_id' => $this->student2->id,
            'att_date' => $today,
            'status' => 'izin', // Dikoreksi menjadi izin
            'recorded_by' => $this->teacherUser->id,
        ]);

        $dispatchAlpha = ReminderDispatch::create([
            'school_id' => $this->schoolId,
            'event_type' => 'att_alpha',
            'event_id' => $attendanceAlpha->id,
            'recipient_id' => $this->guardianUser->id,
            'milestone' => "alpha_{$today}",
            'channel' => 'in_app',
            'status' => 'pending',
            'metadata' => [
                'title' => 'Siswa Alpha',
                'body' => 'Siswa tidak hadir tanpa keterangan',
                'notif_type' => 'attendance_alert',
            ],
        ]);

        $jobAlpha = new ProcessReminderDispatchJob($dispatchAlpha->id);
        $jobAlpha->handle();

        $dispatchAlpha->refresh();
        $this->assertEquals('skipped_stale_status_changed', $dispatchAlpha->status);
        $this->assertEquals(0, Notification::where('user_id', $this->guardianUser->id)->count());
    }

    public function test_be_ntf_01_tenant_mismatch_in_job_is_safely_rejected(): void
    {
        $otherSchoolId = (string) Str::uuid();

        // Dispatch mencantumkan school_id milik sekolah lain tetapi recipient adalah siswa sekolah A
        $dispatch = ReminderDispatch::create([
            'school_id' => $otherSchoolId,
            'event_type' => 'cbt_exam',
            'event_id' => (string) Str::uuid(),
            'recipient_id' => $this->studentUser1->id, // User belongs to $this->schoolId
            'milestone' => '24h',
            'channel' => 'in_app',
            'status' => 'pending',
            'metadata' => [
                'title' => 'Ujian Cross School',
                'body' => 'Bocoran lintas tenant',
                'notif_type' => 'academic_announcement',
            ],
        ]);

        $job = new ProcessReminderDispatchJob($dispatch->id);
        $job->handle();

        $dispatch->refresh();
        $this->assertEquals('skipped_tenant_mismatch', $dispatch->status);
        $this->assertEquals(0, Notification::where('user_id', $this->studentUser1->id)->count());
    }

    public function test_be_ntf_01_recovery_of_stuck_processing_dispatches(): void
    {
        $exam = Exam::create([
            'school_id' => $this->schoolId,
            'subject_id' => $this->subject->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Ujian Stuck Test',
            'exam_type' => 'pts',
            'duration_min' => 60,
            'start_at' => Carbon::now()->addHours(5),
            'end_at' => Carbon::now()->addHours(6),
            'status' => 'scheduled',
        ]);

        // Buat dispatch yang macet di status 'processing' lebih dari 45 menit yang lalu
        $dispatchStuck = ReminderDispatch::create([
            'school_id' => $this->schoolId,
            'event_type' => 'cbt_exam',
            'event_id' => $exam->id,
            'recipient_id' => $this->studentUser1->id,
            'milestone' => '24h',
            'channel' => 'in_app',
            'status' => 'processing',
            'metadata' => [
                'title' => 'Ujian Macet',
                'body' => 'Ujian setelah worker crash',
                'notif_type' => 'academic_announcement',
            ],
        ]);

        // Simulasikan updated_at sudah 40 menit yang lalu
        $dispatchStuck->updated_at = Carbon::now()->subMinutes(40);
        $dispatchStuck->save();

        $recoveredCount = $this->reminderService->recoverStuckProcessing(30, false);
        $this->assertEquals(1, $recoveredCount);

        $dispatchStuck->refresh();
        $this->assertEquals('dispatched', $dispatchStuck->status);
        $this->assertEquals(1, Notification::where('user_id', $this->studentUser1->id)->count());
    }

    public function test_be_ntf_01_retry_failed_dispatches(): void
    {
        $exam = Exam::create([
            'school_id' => $this->schoolId,
            'subject_id' => $this->subject->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Ujian Retry Test',
            'exam_type' => 'pts',
            'duration_min' => 60,
            'start_at' => Carbon::now()->addHours(5),
            'end_at' => Carbon::now()->addHours(6),
            'status' => 'scheduled',
        ]);

        // Buat dispatch yang berstatus failed
        $dispatchFailed = ReminderDispatch::create([
            'school_id' => $this->schoolId,
            'event_type' => 'cbt_exam',
            'event_id' => $exam->id,
            'recipient_id' => $this->studentUser1->id,
            'milestone' => '24h',
            'channel' => 'in_app',
            'status' => 'failed',
            'error_message' => 'Simulated temporary database lock',
            'metadata' => [
                'title' => 'Ujian Retry',
                'body' => 'Ujian yang gagal dicoba ulang',
                'notif_type' => 'academic_announcement',
            ],
        ]);

        $retried = $this->reminderService->retryFailedDispatches($this->schoolId, 50, false);
        $this->assertEquals(1, $retried);

        $dispatchFailed->refresh();
        $this->assertEquals('dispatched', $dispatchFailed->status);
        $this->assertNull($dispatchFailed->error_message);
        $this->assertEquals(1, Notification::where('user_id', $this->studentUser1->id)->count());
    }

    public function test_be_ntf_01_observability_stats_and_artisan_flags(): void
    {
        ReminderDispatch::create([
            'school_id' => $this->schoolId,
            'event_type' => 'cbt_exam',
            'event_id' => (string) Str::uuid(),
            'recipient_id' => $this->studentUser1->id,
            'milestone' => '24h',
            'channel' => 'in_app',
            'status' => 'dispatched',
        ]);

        ReminderDispatch::create([
            'school_id' => $this->schoolId,
            'event_type' => 'lms_assignment',
            'event_id' => (string) Str::uuid(),
            'recipient_id' => $this->studentUser2->id,
            'milestone' => '3h',
            'channel' => 'in_app',
            'status' => 'failed',
            'error_message' => 'Network error',
        ]);

        $stats = $this->reminderService->getStats($this->schoolId);
        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(1, $stats['by_status']['dispatched']);
        $this->assertEquals(1, $stats['by_status']['failed']);
        $this->assertEquals(1, $stats['failed']);

        // Uji command dengan flag --stats, --retry-failed, dan --recover-stuck
        $this->artisan('reminder:dispatch', ['--stats' => true, '--school' => $this->schoolId])
            ->assertExitCode(0);

        $this->artisan('reminder:dispatch', ['--retry-failed' => true, '--school' => $this->schoolId])
            ->assertExitCode(0);

        $this->artisan('reminder:dispatch', ['--recover-stuck' => true, '--school' => $this->schoolId])
            ->assertExitCode(0);
    }

    public function test_be_ntf_01_artisan_command_aliases_and_options_validation(): void
    {
        // 1. Alias reminders:dispatch is registered and runs successfully
        $this->artisan('reminders:dispatch', ['--type' => 'all', '--school' => $this->schoolId])
            ->assertExitCode(0);

        // 2. Valid --type options
        $validTypes = ['all', 'cbt', 'cbt_exam', 'lms', 'lms_assignment', 'att', 'attendance', 'attendance_daily'];
        foreach ($validTypes as $type) {
            $this->artisan('reminder:dispatch', ['--type' => $type, '--school' => $this->schoolId])
                ->assertExitCode(0);
        }

        // 3. Invalid --type option is rejected with code 1 and descriptive error message
        $this->artisan('reminder:dispatch', ['--type' => 'invalid_type_foo', '--school' => $this->schoolId])
            ->expectsOutput("Tipe pengingat 'invalid_type_foo' tidak valid. Pilihan valid: all, cbt, lms, att (atau attendance_daily).")
            ->assertExitCode(1);
    }

    public function test_be_ntf_01_stats_command_does_not_mutate_data(): void
    {
        $exam = Exam::create([
            'school_id' => $this->schoolId,
            'subject_id' => $this->subject->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Ujian Stats Mutate Guard',
            'exam_type' => 'pts',
            'duration_min' => 60,
            'start_at' => Carbon::now()->addHours(5),
            'end_at' => Carbon::now()->addHours(6),
            'status' => 'scheduled',
        ]);

        $dispatch = ReminderDispatch::create([
            'school_id' => $this->schoolId,
            'event_type' => 'cbt_exam',
            'event_id' => $exam->id,
            'recipient_id' => $this->studentUser1->id,
            'milestone' => '24h',
            'channel' => 'in_app',
            'status' => 'pending',
            'metadata' => ['title' => 'Ujian Stats Test'],
        ]);

        $initialState = ReminderDispatch::all()->toArray();
        $this->assertCount(1, $initialState);

        // Jalankan artisan --stats
        $this->artisan('reminders:dispatch', ['--stats' => true, '--school' => $this->schoolId])
            ->assertExitCode(0);

        // Pastikan tidak ada data yang berubah (status tetap pending, count tetap 1)
        $afterState = ReminderDispatch::all()->toArray();
        $this->assertEquals($initialState, $afterState);
    }

    public function test_be_ntf_01_retry_only_takes_retry_eligible_failures(): void
    {
        $exam = Exam::create([
            'school_id' => $this->schoolId,
            'subject_id' => $this->subject->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Ujian Retry Eligibility Test',
            'exam_type' => 'pts',
            'duration_min' => 60,
            'start_at' => Carbon::now()->addHours(5),
            'end_at' => Carbon::now()->addHours(6),
            'status' => 'scheduled',
        ]);

        // 1. Transient failure (eligible for retry)
        $transient = ReminderDispatch::create([
            'school_id' => $this->schoolId,
            'event_type' => 'cbt_exam',
            'event_id' => $exam->id,
            'recipient_id' => $this->studentUser1->id,
            'milestone' => '24h',
            'channel' => 'in_app',
            'status' => 'failed',
            'error_message' => 'Connection timeout to redis queue',
            'metadata' => ['title' => 'Ujian Transient'],
        ]);

        // 2. Permanent failure (user missing / permanent error, not eligible for retry)
        $permanent = ReminderDispatch::create([
            'school_id' => $this->schoolId,
            'event_type' => 'cbt_exam',
            'event_id' => $exam->id,
            'recipient_id' => $this->studentUser2->id,
            'milestone' => '1h',
            'channel' => 'in_app',
            'status' => 'failed',
            'error_message' => 'Recipient user record does not exist',
            'metadata' => ['title' => 'Ujian Permanent Missing User'],
        ]);

        // 3. Terminal skip states
        $skipped = ReminderDispatch::create([
            'school_id' => $this->schoolId,
            'event_type' => 'cbt_exam',
            'event_id' => $exam->id,
            'recipient_id' => $this->studentUser2->id,
            'milestone' => '24h',
            'channel' => 'in_app',
            'status' => 'skipped_by_preference',
            'metadata' => ['title' => 'Ujian Skipped'],
        ]);

        $retried = $this->reminderService->retryFailedDispatches($this->schoolId, 50, false);
        $this->assertEquals(1, $retried);

        $transient->refresh();
        $this->assertEquals('dispatched', $transient->status);

        $permanent->refresh();
        $this->assertEquals('failed', $permanent->status);
        $this->assertEquals('Recipient user record does not exist', $permanent->error_message);

        $skipped->refresh();
        $this->assertEquals('skipped_by_preference', $skipped->status);
    }

    public function test_be_ntf_01_fencing_token_prevents_zombie_worker_duplicate_send_and_overwrite(): void
    {
        // Scenario A-B-C-D-E:
        // A. Worker A berhasil mengklaim dispatch.
        // B. Worker A berjalan lebih lama dari batas recovery (simulasi timeout/crash).
        // C. Recovery mengembalikan dispatch ke antrean 'pending' dan membersihkan token lama.
        // D. Worker B mengklaim dispatch yang sama dan menyelesaikannya menjadi 'dispatched'.
        // E. Worker A bangun kembali dan mencoba mengirim atau mengubah status.
        // FENCING GUARANTEE: Worker A ditolak (claim token mismatch), tidak menduplikasi notifikasi, dan tidak menimpa status.

        $exam = Exam::create([
            'school_id' => $this->schoolId,
            'subject_id' => $this->subject->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Ujian Fencing Scenario ABCDE',
            'exam_type' => 'pts',
            'duration_min' => 60,
            'start_at' => Carbon::now()->addHours(5),
            'end_at' => Carbon::now()->addHours(6),
            'status' => 'scheduled',
        ]);

        $dispatch = ReminderDispatch::create([
            'school_id' => $this->schoolId,
            'event_type' => 'cbt_exam',
            'event_id' => $exam->id,
            'recipient_id' => $this->studentUser1->id,
            'milestone' => '24h',
            'channel' => 'in_app',
            'status' => 'pending',
            'metadata' => [
                'title' => 'Pengingat Ujian Fencing',
                'body' => 'Ujian simulasi fencing',
                'notif_type' => 'academic_announcement',
            ],
        ]);

        // A. Worker A mengklaim dispatch dengan Token A
        $tokenA = 'worker-a-token-'.Str::random(10);
        $jobA = new ProcessReminderDispatchJob($dispatch->id, $tokenA);

        // Simulasi Worker A baru selesai melakukan atomic claim
        $claimedA = ReminderDispatch::where('id', $dispatch->id)
            ->whereIn('status', ['pending', 'failed'])
            ->update([
                'status' => 'processing',
                'claim_token' => $tokenA,
                'claim_expires_at' => Carbon::now()->addMinutes(15),
                'updated_at' => Carbon::now(),
            ]);
        $this->assertEquals(1, $claimedA);

        // B. Worker A mengalami pause/freeze lebih lama dari ambang recovery (40 menit)
        $dispatch->updated_at = Carbon::now()->subMinutes(40);
        $dispatch->claim_expires_at = Carbon::now()->subMinutes(25);
        $dispatch->save();

        // C. Recovery berjalan dan mengembalikan dispatch ke status 'pending' serta mengosongkan claim_token
        $recovered = $this->reminderService->recoverStuckProcessing(15, false);
        $this->assertEquals(1, $recovered);

        $dispatch->refresh();
        $this->assertEquals('dispatched', $dispatch->status); // Recovery langsung memproses job baru via worker B
        $this->assertEquals(1, Notification::where('user_id', $this->studentUser1->id)->count());

        // D & E. Sekarang Worker A yang tadinya tertidur tiba-tiba bangun dan mengeksekusi handle()-nya
        $jobA->handle();

        // FENCING VERIFICATION:
        // 1. Notifikasi tetap tepat 1 (Worker A tidak mengirim notifikasi kedua)
        $this->assertEquals(1, Notification::where('user_id', $this->studentUser1->id)->count());

        // 2. Status dispatch tetap 'dispatched' milik Worker B dan claim_token tetap aman
        $dispatch->refresh();
        $this->assertEquals('dispatched', $dispatch->status);
    }

    public function test_be_ntf_01_recovery_does_not_take_over_legitimate_running_worker(): void
    {
        $exam = Exam::create([
            'school_id' => $this->schoolId,
            'subject_id' => $this->subject->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Ujian Legitimate Running Worker',
            'exam_type' => 'pts',
            'duration_min' => 60,
            'start_at' => Carbon::now()->addHours(5),
            'end_at' => Carbon::now()->addHours(6),
            'status' => 'scheduled',
        ]);

        $dispatch = ReminderDispatch::create([
            'school_id' => $this->schoolId,
            'event_type' => 'cbt_exam',
            'event_id' => $exam->id,
            'recipient_id' => $this->studentUser1->id,
            'milestone' => '24h',
            'channel' => 'in_app',
            'status' => 'processing',
            'claim_token' => (string) Str::uuid(),
            'claim_expires_at' => Carbon::now()->addMinutes(14), // Masih sah dalam masa sewa
            'metadata' => ['title' => 'Ujian Legitimate In-Flight'],
        ]);

        // Simulasikan baru berjalan 1 menit yang lalu
        $dispatch->updated_at = Carbon::now()->subMinute();
        $dispatch->save();

        // Jalankan recovery dengan threshold 15 menit
        $recovered = $this->reminderService->recoverStuckProcessing(15, false);

        // Job sah TIDAK boleh diambil alih oleh recovery
        $this->assertEquals(0, $recovered);

        $dispatch->refresh();
        $this->assertEquals('processing', $dispatch->status);
        $this->assertNotNull($dispatch->claim_token);
    }

    public function test_be_ntf_01_stale_check_skips_when_guardian_unlinked_from_student(): void
    {
        $today = Carbon::now(config('app.school_timezone', 'Asia/Jakarta'))->toDateString();

        // 1. Wali sudah di-link di setUp(). Buat absensi alpha dan dispatch
        $attendanceAlpha = Attendance::create([
            'student_id' => $this->student1->id,
            'att_date' => $today,
            'status' => 'alpha',
            'recorded_by' => $this->teacherUser->id,
        ]);

        $dispatchAlpha = ReminderDispatch::create([
            'school_id' => $this->schoolId,
            'event_type' => 'att_alpha',
            'event_id' => $attendanceAlpha->id,
            'recipient_id' => $this->guardianUser->id,
            'milestone' => "alpha_rel_test_{$today}",
            'channel' => 'in_app',
            'status' => 'pending',
            'metadata' => [
                'title' => 'Siswa Alpha Guardian Check',
                'body' => 'Pengingat untuk wali murid',
                'notif_type' => 'attendance_alert',
            ],
        ]);

        // 2. Hubungan wali dilepas (unlinked/revoked) SEBELUM worker berjalan
        $this->student1->guardians()->detach($this->guardian->id);

        $jobAlpha = new ProcessReminderDispatchJob($dispatchAlpha->id);
        $jobAlpha->handle();

        $dispatchAlpha->refresh();
        $this->assertEquals('skipped_stale_relationship_changed', $dispatchAlpha->status);
        $this->assertEquals(0, Notification::where('user_id', $this->guardianUser->id)->count());
    }

    public function test_be_ntf_01_stale_check_skips_when_student_unenrolled_from_exam(): void
    {
        $exam = Exam::create([
            'school_id' => $this->schoolId,
            'subject_id' => $this->subject->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Ujian Unenrolled Test',
            'exam_type' => 'pts',
            'duration_min' => 60,
            'start_at' => Carbon::now()->addHours(5),
            'end_at' => Carbon::now()->addHours(6),
            'status' => 'scheduled',
        ]);

        $examRoom = $this->createExamRoom($exam);

        // 1. Siswa 1 dan Siswa 2 didaftarkan sebagai peserta ujian
        $participant1 = ExamParticipant::create([
            'exam_id' => $exam->id,
            'student_id' => $this->student1->id,
            'exam_room_id' => $examRoom->id,
        ]);
        ExamParticipant::create([
            'exam_id' => $exam->id,
            'student_id' => $this->student2->id,
            'exam_room_id' => $examRoom->id,
        ]);

        $dispatch = ReminderDispatch::create([
            'school_id' => $this->schoolId,
            'event_type' => 'cbt_exam',
            'event_id' => $exam->id,
            'recipient_id' => $this->studentUser1->id,
            'milestone' => '24h',
            'channel' => 'in_app',
            'status' => 'pending',
            'metadata' => [
                'title' => 'Ujian CBT Peserta',
                'body' => 'Pengingat peserta ujian',
                'notif_type' => 'academic_announcement',
            ],
        ]);

        // 2. Siswa 1 dikeluarkan dari kepesertaan ujian sebelum worker jalan
        $participant1->delete();

        $job = new ProcessReminderDispatchJob($dispatch->id);
        $job->handle();

        $dispatch->refresh();
        $this->assertEquals('skipped_stale_unenrolled', $dispatch->status);
        $this->assertEquals(0, Notification::where('user_id', $this->studentUser1->id)->count());
    }

    public function test_be_ntf_01_stale_check_skips_when_homeroom_teacher_reassigned(): void
    {
        $class = SchoolClass::create([
            'school_id' => $this->schoolId,
            'academic_year_id' => $this->class->academic_year_id,
            'grade_level_id' => $this->class->grade_level_id,
            'name' => 'Kelas Reassigned Test',
            'homeroom_teacher_id' => $this->teacher->id,
        ]);

        $today = Carbon::now(config('app.school_timezone', 'Asia/Jakarta'))->toDateString();

        $dispatch = ReminderDispatch::create([
            'school_id' => $this->schoolId,
            'event_type' => 'att_recording',
            'event_id' => $class->id,
            'recipient_id' => $this->teacherUser->id,
            'milestone' => "daily_cutoff_reassign_{$today}",
            'channel' => 'in_app',
            'status' => 'pending',
            'metadata' => [
                'title' => 'Presensi Homeroom Reassign',
                'body' => 'Isi presensi walas',
                'notif_type' => 'attendance_alert',
            ],
        ]);

        // Buat guru pengganti
        $newTeacherUser = User::create([
            'school_id' => $this->schoolId,
            'username' => 'guru_pengganti',
            'name' => 'Guru Pengganti',
            'email' => 'pengganti@sekolah.sch.id',
            'password_hash' => Hash::make('password'),
        ]);
        $newTeacher = Teacher::create([
            'user_id' => $newTeacherUser->id,
            'school_id' => $this->schoolId,
            'nip' => '888888888888',
            'full_name' => 'Guru Pengganti',
        ]);

        // Wali kelas dipindahkan ke guru baru sebelum worker jalan
        $class->homeroom_teacher_id = $newTeacher->id;
        $class->save();

        $job = new ProcessReminderDispatchJob($dispatch->id);
        $job->handle();

        $dispatch->refresh();
        $this->assertEquals('skipped_stale_reassigned', $dispatch->status);
        $this->assertEquals(0, Notification::where('user_id', $this->teacherUser->id)->count());
    }
}
