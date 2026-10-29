<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\ClassStudent;
use App\Models\File;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeaveRequestTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $walasUser;

    protected User $otherTeacherUser;

    protected User $guardianUser;

    protected User $otherGuardianUser;

    protected User $student1User;

    protected User $student2User;

    protected Teacher $walas;

    protected Teacher $otherTeacher;

    protected Guardian $guardian;

    protected Guardian $otherGuardian;

    protected Student $student1;

    protected Student $student2;

    protected SchoolClass $classA;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        // Roles & Permissions
        $roleAdmin = Role::firstOrCreate(['code' => 'admin_kurikulum'], ['name' => 'Admin Kurikulum', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Wali Murid', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $permAttendanceRead = Permission::firstOrCreate(['code' => 'academic.attendance.read'], ['description' => 'Melihat presensi']);
        $permAttendanceWrite = Permission::firstOrCreate(['code' => 'academic.attendance.write'], ['description' => 'Mencatat presensi']);

        $roleAdmin->permissions()->syncWithoutDetaching([$permAttendanceRead->id, $permAttendanceWrite->id]);
        $roleTeacher->permissions()->syncWithoutDetaching([$permAttendanceRead->id, $permAttendanceWrite->id]);

        // Users
        $this->adminUser = User::create([
            'username' => 'adminkurikulum',
            'email' => 'kurikulum@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->walasUser = User::create([
            'username' => 'walasbudi',
            'email' => 'walas@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->walasUser->roles()->attach($roleTeacher->id);

        $this->otherTeacherUser = User::create([
            'username' => 'guruasing',
            'email' => 'guruasing@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->otherTeacherUser->roles()->attach($roleTeacher->id);

        $this->guardianUser = User::create([
            'username' => 'walimurid',
            'email' => 'wali@keluarga.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->guardianUser->roles()->attach($roleGuardian->id);

        $this->otherGuardianUser = User::create([
            'username' => 'waliasing',
            'email' => 'waliasing@keluarga.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->otherGuardianUser->roles()->attach($roleGuardian->id);

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

        // Teachers
        $this->walas = Teacher::create([
            'user_id' => $this->walasUser->id,
            'nip' => '198001012005011001',
            'full_name' => 'Budi Walas, M.Pd.',
            'status' => 'tetap',
        ]);

        $this->otherTeacher = Teacher::create([
            'user_id' => $this->otherTeacherUser->id,
            'nip' => '198502022006022002',
            'full_name' => 'Guru Asing, S.Pd.',
            'status' => 'tetap',
        ]);

        // Academic & Class
        $academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-15',
            'end_date' => '2027-06-20',
            'is_active' => true,
        ]);

        $gradeLevel = GradeLevel::create([
            'name' => 'Kelas 10',
            'sort_order' => 10,
        ]);

        $this->classA = SchoolClass::create([
            'academic_year_id' => $academicYear->id,
            'grade_level_id' => $gradeLevel->id,
            'homeroom_teacher_id' => $this->walas->id,
            'name' => 'X-A',
            'capacity' => 36,
        ]);

        // Students
        $this->student1 = Student::create([
            'user_id' => $this->student1User->id,
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

        $this->student2 = Student::create([
            'user_id' => $this->student2User->id,
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

        // Guardians
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

    public function test_be_acd_04_guardian_can_submit_leave_with_attachment_file_upload(): void
    {
        $token = $this->guardianUser->createToken('test')->plainTextToken;
        $fakeFile = UploadedFile::fake()->create('surat_dokter.jpg', 100, 'image/jpeg');

        $startDate = Carbon::now()->addDay()->format('Y-m-d');
        $endDate = Carbon::now()->addDays(2)->format('Y-m-d');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/leave-requests', [
                'student_id' => $this->student1->id,
                'leave_type' => 'sakit',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'reason' => 'Sakit tifus, opname di RS Husada.',
                'attachment_file' => $fakeFile,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.student_id', $this->student1->id)
            ->assertJsonPath('data.status', 'menunggu');

        $leaveId = $response->json('data.id');
        $fileId = $response->json('data.attachment_file_id');

        $this->assertNotNull($fileId);
        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveId,
            'student_id' => $this->student1->id,
            'attachment_file_id' => $fileId,
        ]);

        $file = File::find($fileId);
        $this->assertNotNull($file);
        $this->assertTrue(Storage::disk('local')->exists($file->object_key));
    }

    public function test_be_acd_04_student_can_submit_leave_for_themselves(): void
    {
        $token = $this->student1User->createToken('test')->plainTextToken;

        $startDate = Carbon::now()->format('Y-m-d');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/leave-requests', [
                'student_id' => $this->student1->id,
                'leave_type' => 'dispen',
                'start_date' => $startDate,
                'end_date' => $startDate,
                'reason' => 'Mengikuti lomba OSN Matematika Tingkat Kota.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.student_id', $this->student1->id)
            ->assertJsonPath('data.leave_type', 'dispen');

        $this->assertDatabaseHas('leave_requests', [
            'student_id' => $this->student1->id,
            'requested_by' => $this->student1User->id,
            'leave_type' => 'dispen',
        ]);
    }

    public function test_be_acd_04_guardian_cannot_submit_leave_for_unrelated_student_403(): void
    {
        $token = $this->otherGuardianUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/leave-requests', [
                'student_id' => $this->student1->id,
                'leave_type' => 'izin',
                'start_date' => Carbon::now()->format('Y-m-d'),
                'end_date' => Carbon::now()->format('Y-m-d'),
                'reason' => 'Izin ilegal untuk anak orang lain',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_be_acd_04_student_cannot_submit_leave_for_another_student_403(): void
    {
        // Student 1 attempts to submit leave on behalf of Student 2
        $token = $this->student1User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/leave-requests', [
                'student_id' => $this->student2->id,
                'leave_type' => 'izin',
                'start_date' => Carbon::now()->format('Y-m-d'),
                'end_date' => Carbon::now()->format('Y-m-d'),
                'reason' => 'Izin untuk teman sekelas',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_be_acd_04_homeroom_teacher_can_decide_approval_and_auto_fills_attendances(): void
    {
        $day1 = Carbon::now()->addDay()->format('Y-m-d');
        $day2 = Carbon::now()->addDays(2)->format('Y-m-d');

        $leave = LeaveRequest::create([
            'student_id' => $this->student1->id,
            'requested_by' => $this->guardianUser->id,
            'leave_type' => 'sakit',
            'start_date' => $day1,
            'end_date' => $day2,
            'reason' => 'Demam berdarah',
            'status' => 'menunggu',
        ]);

        $token = $this->walasUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/leave-requests/{$leave->id}/decision", [
                'status' => 'disetujui',
                'decision_notes' => 'Semoga lekas sembuh, jangan lupa beristirahat cukup.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'disetujui')
            ->assertJsonPath('data.decision_notes', 'Semoga lekas sembuh, jangan lupa beristirahat cukup.');

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leave->id,
            'status' => 'disetujui',
            'decided_by' => $this->walasUser->id,
            'decision_notes' => 'Semoga lekas sembuh, jangan lupa beristirahat cukup.',
        ]);

        // Verifies auto-filled attendance for day 1 and day 2
        $this->assertTrue(Attendance::where('student_id', $this->student1->id)
            ->where('status', 'sakit')
            ->whereDate('att_date', $day1)
            ->exists());

        $this->assertTrue(Attendance::where('student_id', $this->student1->id)
            ->where('status', 'sakit')
            ->whereDate('att_date', $day2)
            ->exists());
    }

    public function test_be_acd_04_homeroom_teacher_can_decide_rejection_with_decision_notes(): void
    {
        $today = Carbon::now()->format('Y-m-d');

        $leave = LeaveRequest::create([
            'student_id' => $this->student1->id,
            'requested_by' => $this->guardianUser->id,
            'leave_type' => 'izin',
            'start_date' => $today,
            'end_date' => $today,
            'reason' => 'Nonton konser',
            'status' => 'menunggu',
        ]);

        $token = $this->walasUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/leave-requests/{$leave->id}/decision", [
                'status' => 'ditolak',
                'decision_notes' => 'Alasan izin tidak dapat diterima pada hari efektif belajar.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'ditolak')
            ->assertJsonPath('data.decision_notes', 'Alasan izin tidak dapat diterima pada hari efektif belajar.');

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leave->id,
            'status' => 'ditolak',
            'decision_notes' => 'Alasan izin tidak dapat diterima pada hari efektif belajar.',
        ]);
    }

    public function test_be_acd_04_decision_triggers_instant_notification_to_applicant(): void
    {
        $today = Carbon::now()->format('Y-m-d');

        $leave = LeaveRequest::create([
            'student_id' => $this->student1->id,
            'requested_by' => $this->guardianUser->id,
            'leave_type' => 'sakit',
            'start_date' => $today,
            'end_date' => $today,
            'reason' => 'Flu berat',
            'status' => 'menunggu',
        ]);

        $token = $this->walasUser->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/leave-requests/{$leave->id}/decision", [
                'status' => 'disetujui',
                'decision_notes' => 'Disetujui walas.',
            ]);

        // Verifies notification was created for guardianUser
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->guardianUser->id,
            'notif_type' => 'leave_request.decision',
        ]);
    }

    public function test_be_acd_04_non_homeroom_teacher_cannot_decide_leave_request_403(): void
    {
        $leave = LeaveRequest::create([
            'student_id' => $this->student1->id,
            'requested_by' => $this->guardianUser->id,
            'leave_type' => 'sakit',
            'start_date' => Carbon::now()->format('Y-m-d'),
            'end_date' => Carbon::now()->format('Y-m-d'),
            'reason' => 'Demam',
            'status' => 'menunggu',
        ]);

        // Other teacher is not the walas for Student 1
        $token = $this->otherTeacherUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/leave-requests/{$leave->id}/decision", [
                'status' => 'disetujui',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_be_acd_04_show_leave_request_detail_with_attachment_and_decision(): void
    {
        $leave = LeaveRequest::create([
            'student_id' => $this->student1->id,
            'requested_by' => $this->guardianUser->id,
            'leave_type' => 'sakit',
            'start_date' => Carbon::now()->format('Y-m-d'),
            'end_date' => Carbon::now()->format('Y-m-d'),
            'reason' => 'Batuk pilek',
            'status' => 'disetujui',
            'decided_by' => $this->walasUser->id,
            'decision_notes' => 'Catatan persetujuan walas',
        ]);

        $token = $this->guardianUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/leave-requests/{$leave->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $leave->id)
            ->assertJsonPath('data.student.full_name', 'Aditya Pratama')
            ->assertJsonPath('data.status', 'disetujui')
            ->assertJsonPath('data.decision_notes', 'Catatan persetujuan walas');
    }

    public function test_be_acd_04_unauthorized_user_cannot_access_leave_requests_403_or_401(): void
    {
        // 401 unauthenticated
        $this->getJson('/api/v1/leave-requests')->assertStatus(401);

        $leave = LeaveRequest::create([
            'student_id' => $this->student1->id,
            'requested_by' => $this->guardianUser->id,
            'leave_type' => 'sakit',
            'start_date' => Carbon::now()->format('Y-m-d'),
            'end_date' => Carbon::now()->format('Y-m-d'),
            'reason' => 'Demam',
            'status' => 'menunggu',
        ]);

        // Other guardian cannot view student 1's leave request
        $token = $this->otherGuardianUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/leave-requests/{$leave->id}");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_be_acd_04_cannot_decide_already_decided_leave_request_422(): void
    {
        $leave = LeaveRequest::create([
            'student_id' => $this->student1->id,
            'requested_by' => $this->guardianUser->id,
            'leave_type' => 'sakit',
            'start_date' => Carbon::now()->format('Y-m-d'),
            'end_date' => Carbon::now()->format('Y-m-d'),
            'reason' => 'Demam tinggi',
            'status' => 'disetujui',
            'decided_by' => $this->walasUser->id,
        ]);

        $token = $this->walasUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/leave-requests/{$leave->id}/decision", [
                'status' => 'ditolak',
                'decision_notes' => 'Mencoba membatalkan tanpa izin admin',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');
    }

    public function test_be_acd_04_admin_can_redecide_and_revokes_attendance_on_rejection(): void
    {
        $today = Carbon::now()->format('Y-m-d');

        $leave = LeaveRequest::create([
            'student_id' => $this->student1->id,
            'requested_by' => $this->guardianUser->id,
            'leave_type' => 'sakit',
            'start_date' => $today,
            'end_date' => $today,
            'reason' => 'Flu berat',
            'status' => 'menunggu',
        ]);

        // First approved by walas
        $walasToken = $this->walasUser->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$walasToken}")
            ->patchJson("/api/v1/leave-requests/{$leave->id}/decision", [
                'status' => 'disetujui',
            ])->assertStatus(200);

        $this->assertTrue(Attendance::where('student_id', $this->student1->id)
            ->where('status', 'sakit')
            ->whereDate('att_date', $today)
            ->where('source', 'gerbang')
            ->exists());

        // Reset auth guards to switch user
        app('auth')->forgetGuards();

        // Admin re-decides to 'ditolak'
        $adminToken = $this->adminUser->createToken('test')->plainTextToken;
        $response = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->patchJson("/api/v1/leave-requests/{$leave->id}/decision", [
                'status' => 'ditolak',
                'decision_notes' => 'Dibatalkan oleh kurikulum karena terbukti tidak sakit.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'ditolak');

        // Attendance entry should be cleaned up
        $this->assertFalse(Attendance::where('student_id', $this->student1->id)
            ->where('status', 'sakit')
            ->whereDate('att_date', $today)
            ->where('source', 'gerbang')
            ->exists());
    }

    public function test_be_acd_04_list_leave_requests_with_filters_and_search(): void
    {
        LeaveRequest::create([
            'student_id' => $this->student1->id,
            'requested_by' => $this->guardianUser->id,
            'leave_type' => 'sakit',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-03',
            'reason' => 'Sakit typhus',
            'status' => 'disetujui',
        ]);

        LeaveRequest::create([
            'student_id' => $this->student2->id,
            'requested_by' => $this->student2User->id,
            'leave_type' => 'dispen',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
            'reason' => 'Lomba karya ilmiah',
            'status' => 'menunggu',
        ]);

        $token = $this->adminUser->createToken('test')->plainTextToken;

        // Filter by leave_type
        $res = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/leave-requests?leave_type=dispen');
        $res->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.leave_type', 'dispen');

        // Search by student name 'Bella'
        $resSearch = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/leave-requests?q=Bella');
        $resSearch->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student.full_name', 'Bella Safitri');

        // Filter by class_id
        $resClass = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/leave-requests?class_id={$this->classA->id}");
        $resClass->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_be_acd_04_student_can_only_view_own_leave_requests_idor(): void
    {
        LeaveRequest::create([
            'student_id' => $this->student1->id,
            'requested_by' => $this->student1User->id,
            'leave_type' => 'sakit',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-02',
            'reason' => 'Demam',
            'status' => 'menunggu',
        ]);

        LeaveRequest::create([
            'student_id' => $this->student2->id,
            'requested_by' => $this->student2User->id,
            'leave_type' => 'izin',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-01',
            'reason' => 'Acara keluarga',
            'status' => 'menunggu',
        ]);

        $token = $this->student1User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/leave-requests');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student_id', $this->student1->id);
    }
}
