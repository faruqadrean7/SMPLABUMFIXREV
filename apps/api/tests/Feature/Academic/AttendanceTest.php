<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\ClassStudent;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\Journal;
use App\Models\LeaveRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $teacher1User;

    protected User $teacher2User;

    protected User $guardianUser;

    protected User $otherGuardianUser;

    protected Teacher $teacher1;

    protected Teacher $teacher2;

    protected Guardian $guardian;

    protected Guardian $otherGuardian;

    protected Student $student1;

    protected Student $student2;

    protected SchoolClass $classA;

    protected Journal $journal;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Roles & Permissions
        $roleAdmin = Role::firstOrCreate(['code' => 'admin_kurikulum'], ['name' => 'Admin Kurikulum', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Wali Murid', 'is_system' => true]);

        $permScheduleManage = Permission::firstOrCreate(['code' => 'academic.schedule.manage'], ['description' => 'Mengelola jadwal pelajaran']);
        $permAttendanceRead = Permission::firstOrCreate(['code' => 'academic.attendance.read'], ['description' => 'Melihat presensi siswa']);
        $permAttendanceWrite = Permission::firstOrCreate(['code' => 'academic.attendance.write'], ['description' => 'Mencatat presensi siswa']);
        $permJournalWrite = Permission::firstOrCreate(['code' => 'academic.journal.write'], ['description' => 'Mengisi jurnal mengajar']);

        $roleAdmin->permissions()->syncWithoutDetaching([$permScheduleManage->id, $permAttendanceRead->id, $permAttendanceWrite->id, $permJournalWrite->id]);
        $roleTeacher->permissions()->syncWithoutDetaching([$permAttendanceRead->id, $permAttendanceWrite->id, $permJournalWrite->id]);
        $roleGuardian->permissions()->syncWithoutDetaching([$permAttendanceRead->id]);

        // 2. Users
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

        $this->guardianUser = User::create([
            'username' => 'walibudi',
            'email' => 'wali.budi@keluarga.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->guardianUser->roles()->attach($roleGuardian->id);

        $this->otherGuardianUser = User::create([
            'username' => 'waliasing',
            'email' => 'wali.asing@keluarga.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->otherGuardianUser->roles()->attach($roleGuardian->id);

        // 3. Teachers (Teacher 1 is also homeroom teacher for Class A)
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

        // 4. Academic Setup
        $academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-15',
            'end_date' => '2027-06-20',
            'is_active' => true,
        ]);

        $semester = Semester::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'Ganjil',
            'semester_type' => 'ganjil',
            'start_date' => '2026-07-15',
            'end_date' => '2026-12-20',
            'is_active' => true,
        ]);

        $gradeLevel = GradeLevel::create([
            'name' => 'Kelas 10',
            'sort_order' => 10,
        ]);

        $this->classA = SchoolClass::create([
            'academic_year_id' => $academicYear->id,
            'grade_level_id' => $gradeLevel->id,
            'homeroom_teacher_id' => $this->teacher1->id,
            'name' => 'X-A',
            'capacity' => 36,
        ]);

        $subjectMath = Subject::create([
            'code' => 'MAT-10',
            'name' => 'Matematika Wajib',
            'subject_group' => 'umum',
        ]);

        $room = Room::create([
            'code' => 'R101',
            'name' => 'Ruang 101',
            'room_type' => 'kelas',
            'capacity' => 36,
        ]);

        $slot1 = TimeSlot::create([
            'name' => 'Jam ke-1',
            'start_time' => '07:00',
            'end_time' => '07:45',
            'day_type' => 'reguler',
        ]);

        $schedule = Schedule::create([
            'semester_id' => $semester->id,
            'class_id' => $this->classA->id,
            'subject_id' => $subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'room_id' => $room->id,
            'time_slot_id' => $slot1->id,
            'day_of_week' => 1,
        ]);

        $this->journal = Journal::create([
            'schedule_id' => $schedule->id,
            'teacher_id' => $this->teacher1->id,
            'session_date' => Carbon::now()->format('Y-m-d'),
            'topic' => 'Logaritma Dasar',
            'method' => 'Diskusi',
        ]);

        // 5. Students
        $studentUser1 = User::create([
            'username' => 'siswa1',
            'email' => 'siswa1@sekolah.sch.id',
            'password_hash' => Hash::make('secret'),
            'status' => 'active',
        ]);
        $this->student1 = Student::create([
            'user_id' => $studentUser1->id,
            'nis' => '202600001',
            'nisn' => '0012345678',
            'nik_enc' => 'enc_3578010101010001',
            'full_name' => 'Aditya Pratama',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2008-05-15',
            'status' => 'aktif',
            'entry_date' => '2026-07-15',
        ]);
        ClassStudent::create([
            'class_id' => $this->classA->id,
            'student_id' => $this->student1->id,
            'joined_at' => Carbon::now(),
        ]);

        $studentUser2 = User::create([
            'username' => 'siswa2',
            'email' => 'siswa2@sekolah.sch.id',
            'password_hash' => Hash::make('secret'),
            'status' => 'active',
        ]);
        $this->student2 = Student::create([
            'user_id' => $studentUser2->id,
            'nis' => '202600002',
            'nisn' => '0012345679',
            'nik_enc' => 'enc_3578010101010002',
            'full_name' => 'Bella Safitri',
            'gender' => 'P',
            'birth_place' => 'Surabaya',
            'birth_date' => '2008-08-20',
            'status' => 'aktif',
            'entry_date' => '2026-07-15',
        ]);
        ClassStudent::create([
            'class_id' => $this->classA->id,
            'student_id' => $this->student2->id,
            'joined_at' => Carbon::now(),
        ]);

        // 6. Guardians
        $this->guardian = Guardian::create([
            'user_id' => $this->guardianUser->id,
            'full_name' => 'Bambang Pratama',
            'phone' => '08123456789',
        ]);
        $this->guardian->students()->attach($this->student1->id, ['relationship' => 'ayah', 'is_primary' => true]);

        $this->otherGuardian = Guardian::create([
            'user_id' => $this->otherGuardianUser->id,
            'full_name' => 'Wali Asing',
            'phone' => '08198765432',
        ]);
    }

    public function test_be_acd_03_teacher_can_batch_submit_session_attendance(): void
    {
        $token = $this->teacher1User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendances', [
                'journal_id' => $this->journal->id,
                'attendances' => [
                    [
                        'student_id' => $this->student1->id,
                        'status' => 'hadir',
                    ],
                    [
                        'student_id' => $this->student2->id,
                        'status' => 'terlambat',
                        'note' => 'Hadir terlambat 10 menit karena ban bocor',
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.total_recorded', 2);

        $this->assertDatabaseHas('attendances', [
            'journal_id' => $this->journal->id,
            'student_id' => $this->student1->id,
            'status' => 'hadir',
        ]);

        $this->assertDatabaseHas('attendances', [
            'journal_id' => $this->journal->id,
            'student_id' => $this->student2->id,
            'status' => 'terlambat',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'attendance.session_recorded',
            'entity' => 'Journal',
        ]);
    }

    public function test_be_acd_03_other_teacher_cannot_submit_attendance_for_unassigned_journal_403(): void
    {
        $token = $this->teacher2User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendances', [
                'journal_id' => $this->journal->id,
                'attendances' => [
                    [
                        'student_id' => $this->student1->id,
                        'status' => 'hadir',
                    ],
                ],
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_be_acd_03_staff_can_record_daily_gate_attendance(): void
    {
        $token = $this->adminUser->createToken('test')->plainTextToken;

        $today = Carbon::now()->format('Y-m-d');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendances/daily', [
                'student_id' => $this->student1->id,
                'att_date' => $today,
                'status' => 'hadir',
                'note' => 'Tap RFID gerbang masuk pukul 06:45 WIB',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.student_id', $this->student1->id)
            ->assertJsonPath('data.source', 'gerbang')
            ->assertJsonPath('data.status', 'hadir');

        $this->assertDatabaseHas('attendances', [
            'student_id' => $this->student1->id,
            'source' => 'gerbang',
            'status' => 'hadir',
        ]);
    }

    public function test_be_acd_03_guardian_can_submit_leave_request_for_own_child(): void
    {
        $token = $this->guardianUser->createToken('test')->plainTextToken;

        $startDate = Carbon::now()->addDay()->format('Y-m-d');
        $endDate = Carbon::now()->addDays(2)->format('Y-m-d');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/leave-requests', [
                'student_id' => $this->student1->id,
                'leave_type' => 'sakit',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'reason' => 'Demam dan flu, istirahat atas petunjuk dokter.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.student_id', $this->student1->id)
            ->assertJsonPath('data.status', 'menunggu');

        $this->assertDatabaseHas('leave_requests', [
            'student_id' => $this->student1->id,
            'leave_type' => 'sakit',
            'status' => 'menunggu',
        ]);
    }

    public function test_be_acd_03_guardian_cannot_submit_leave_request_for_other_child_idor_403(): void
    {
        // Other guardian attempts to submit leave request for Student 1
        $token = $this->otherGuardianUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/leave-requests', [
                'student_id' => $this->student1->id,
                'leave_type' => 'izin',
                'start_date' => Carbon::now()->format('Y-m-d'),
                'end_date' => Carbon::now()->format('Y-m-d'),
                'reason' => 'Acara keluarga',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_be_acd_03_homeroom_teacher_can_approve_leave_request_and_syncs_attendance(): void
    {
        $today = Carbon::now()->format('Y-m-d');

        $leave = LeaveRequest::create([
            'student_id' => $this->student1->id,
            'requested_by' => $this->guardianUser->id,
            'leave_type' => 'sakit',
            'start_date' => $today,
            'end_date' => $today,
            'reason' => 'Sakit demam',
            'status' => 'menunggu',
        ]);

        // Teacher 1 is homeroom teacher (walas) for Class A where Student 1 belongs
        $token = $this->teacher1User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/leave-requests/{$leave->id}/status", [
                'status' => 'disetujui',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'disetujui');

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leave->id,
            'status' => 'disetujui',
        ]);

        // Verifies automatic attendance synchronization
        $this->assertTrue(Attendance::where('student_id', $this->student1->id)
            ->where('status', 'sakit')
            ->where('source', 'gerbang')
            ->whereDate('att_date', $today)
            ->exists());
    }

    public function test_be_acd_03_session_attendance_automatically_respects_approved_leave_status(): void
    {
        $sessionDate = Carbon::parse($this->journal->session_date)->format('Y-m-d');

        // Student 1 has an approved leave request on the session date
        LeaveRequest::create([
            'student_id' => $this->student1->id,
            'requested_by' => $this->guardianUser->id,
            'leave_type' => 'sakit',
            'start_date' => $sessionDate,
            'end_date' => $sessionDate,
            'reason' => 'Rawat inap',
            'status' => 'disetujui',
        ]);

        $token = $this->teacher1User->createToken('test')->plainTextToken;

        // Teacher marks student1 as 'hadir' in batch form, system overrides/sets to approved 'sakit'
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendances', [
                'journal_id' => $this->journal->id,
                'attendances' => [
                    [
                        'student_id' => $this->student1->id,
                        'status' => 'hadir',
                    ],
                ],
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('attendances', [
            'journal_id' => $this->journal->id,
            'student_id' => $this->student1->id,
            'status' => 'sakit',
        ]);
    }

    public function test_be_acd_03_homeroom_teacher_can_reject_leave_request(): void
    {
        $leave = LeaveRequest::create([
            'student_id' => $this->student1->id,
            'requested_by' => $this->guardianUser->id,
            'leave_type' => 'izin',
            'start_date' => Carbon::now()->format('Y-m-d'),
            'end_date' => Carbon::now()->format('Y-m-d'),
            'reason' => 'Liburan pribadi',
            'status' => 'menunggu',
        ]);

        $token = $this->teacher1User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/leave-requests/{$leave->id}/status", [
                'status' => 'ditolak',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'ditolak');

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leave->id,
            'status' => 'ditolak',
        ]);
    }

    public function test_be_acd_03_calculate_attendance_recap_for_class_or_student(): void
    {
        // Setup attendance records
        Attendance::create([
            'student_id' => $this->student1->id,
            'att_date' => '2026-08-01',
            'status' => 'hadir',
            'source' => 'gerbang',
        ]);
        Attendance::create([
            'student_id' => $this->student1->id,
            'att_date' => '2026-08-02',
            'status' => 'izin',
            'source' => 'gerbang',
        ]);
        Attendance::create([
            'student_id' => $this->student1->id,
            'att_date' => '2026-08-03',
            'status' => 'alpa',
            'source' => 'gerbang',
        ]);
        Attendance::create([
            'student_id' => $this->student1->id,
            'att_date' => '2026-08-04',
            'status' => 'terlambat',
            'source' => 'gerbang',
        ]);

        $token = $this->adminUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/attendances/recap?student_id={$this->student1->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.total_sessions', 4)
            ->assertJsonPath('data.summary.hadir', 1)
            ->assertJsonPath('data.summary.izin', 1)
            ->assertJsonPath('data.summary.alpa', 1)
            ->assertJsonPath('data.summary.terlambat', 1)
            ->assertJsonPath('data.attendance_rate', 50); // (1 hadir + 1 terlambat) / 4 * 100 = 50%
    }

    public function test_be_acd_03_guardian_scoped_to_own_child_attendance(): void
    {
        // Record attendance for student 1 and student 2
        Attendance::create([
            'student_id' => $this->student1->id,
            'att_date' => '2026-08-01',
            'status' => 'hadir',
            'source' => 'gerbang',
        ]);
        Attendance::create([
            'student_id' => $this->student2->id,
            'att_date' => '2026-08-01',
            'status' => 'hadir',
            'source' => 'gerbang',
        ]);

        $token = $this->guardianUser->createToken('test')->plainTextToken;

        // Guardian of Student 1 queries attendance list
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/attendances');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($this->student1->id, $response->json('data.0.student_id'));
    }

    public function test_be_acd_03_unauthorized_user_cannot_access_attendances_403_or_401(): void
    {
        // 401 unauthenticated
        $this->getJson('/api/v1/attendances')->assertStatus(401);

        // Regular user without permission cannot record daily attendance
        $guestRole = Role::firstOrCreate(['code' => 'guest'], ['name' => 'Guest']);
        $guestUser = User::create([
            'username' => 'tamu',
            'email' => 'tamu@sekolah.sch.id',
            'password_hash' => Hash::make('secret'),
            'status' => 'active',
        ]);
        $guestUser->roles()->attach($guestRole->id);

        $token = $guestUser->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendances/daily', [
                'student_id' => $this->student1->id,
                'att_date' => '2026-08-01',
                'status' => 'hadir',
            ])->assertStatus(403);
    }

    public function test_be_acd_03_student_not_enrolled_in_class_is_rejected_422(): void
    {
        // Create student 3 who is NOT in class A
        $studentUser3 = User::create([
            'username' => 'siswa3',
            'email' => 'siswa3@sekolah.sch.id',
            'password_hash' => Hash::make('secret'),
            'status' => 'active',
        ]);
        $student3 = Student::create([
            'user_id' => $studentUser3->id,
            'nis' => '202600003',
            'nisn' => '0012345680',
            'nik_enc' => 'enc_3578010101010003',
            'full_name' => 'Citra Lestari',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2008-09-10',
            'status' => 'aktif',
            'entry_date' => '2026-07-15',
        ]);

        $token = $this->teacher1User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendances', [
                'journal_id' => $this->journal->id,
                'attendances' => [
                    [
                        'student_id' => $student3->id,
                        'status' => 'hadir',
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'UNPROCESSABLE_ENTITY');
    }

    public function test_be_acd_03_invalid_attendance_status_is_rejected_422(): void
    {
        $token = $this->teacher1User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendances', [
                'journal_id' => $this->journal->id,
                'attendances' => [
                    [
                        'student_id' => $this->student1->id,
                        'status' => 'bolos', // invalid status
                    ],
                ],
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_be_acd_03_duplicate_student_in_payload_is_rejected_422(): void
    {
        $token = $this->teacher1User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendances', [
                'journal_id' => $this->journal->id,
                'attendances' => [
                    [
                        'student_id' => $this->student1->id,
                        'status' => 'hadir',
                    ],
                    [
                        'student_id' => $this->student1->id,
                        'status' => 'izin',
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'UNPROCESSABLE_ENTITY');
    }

    public function test_be_acd_03_teacher_cannot_submit_attendance_past_edit_window_422(): void
    {
        // Journal created 5 days ago
        $pastJournal = Journal::create([
            'schedule_id' => $this->journal->schedule_id,
            'teacher_id' => $this->teacher1->id,
            'session_date' => Carbon::now()->subDays(5)->format('Y-m-d'),
            'topic' => 'Sesi 5 Hari Lalu',
            'method' => 'Ceramah',
        ]);

        $token = $this->teacher1User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendances', [
                'journal_id' => $pastJournal->id,
                'attendances' => [
                    [
                        'student_id' => $this->student1->id,
                        'status' => 'hadir',
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'UNPROCESSABLE_ENTITY');
    }

    public function test_be_acd_03_admin_can_override_edit_window_attendance(): void
    {
        // Journal created 10 days ago
        $pastJournal = Journal::create([
            'schedule_id' => $this->journal->schedule_id,
            'teacher_id' => $this->teacher1->id,
            'session_date' => Carbon::now()->subDays(10)->format('Y-m-d'),
            'topic' => 'Sesi 10 Hari Lalu',
            'method' => 'Diskusi',
        ]);

        $token = $this->adminUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendances', [
                'journal_id' => $pastJournal->id,
                'attendances' => [
                    [
                        'student_id' => $this->student1->id,
                        'status' => 'hadir',
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.total_recorded', 1);
    }

    public function test_be_acd_03_non_existent_journal_id_is_rejected_422(): void
    {
        $token = $this->teacher1User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendances', [
                'journal_id' => '00000000-0000-0000-0000-000000000000',
                'attendances' => [
                    [
                        'student_id' => $this->student1->id,
                        'status' => 'hadir',
                    ],
                ],
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_be_acd_03_list_attendances_supports_search_keyword_q(): void
    {
        Attendance::create([
            'student_id' => $this->student1->id,
            'att_date' => Carbon::now()->format('Y-m-d'),
            'status' => 'hadir',
            'source' => 'gerbang',
        ]);
        Attendance::create([
            'student_id' => $this->student2->id,
            'att_date' => Carbon::now()->format('Y-m-d'),
            'status' => 'hadir',
            'source' => 'gerbang',
        ]);

        $token = $this->adminUser->createToken('test')->plainTextToken;

        // Search for student 1: 'Aditya'
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/attendances?q=Aditya');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($this->student1->id, $response->json('data.0.student_id'));
    }
}
