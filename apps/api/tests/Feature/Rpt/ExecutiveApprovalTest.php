<?php

namespace Tests\Feature\Rpt;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\ReportCard;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExecutiveApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected User $principalUser;

    protected User $teacherUser;

    protected User $studentUser;

    protected function setUp(): void
    {
        parent::setUp();

        $rolePrincipal = Role::firstOrCreate(['code' => 'principal'], ['name' => 'Kepala Sekolah', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $this->principalUser = User::create([
            'username' => 'kepsek_test',
            'email' => 'kepsek@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->principalUser->roles()->attach($rolePrincipal->id);

        $this->teacherUser = User::create([
            'username' => 'guru_test',
            'email' => 'guru@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher->id);

        $this->studentUser = User::create([
            'username' => 'siswa_test',
            'email' => 'siswa@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($roleStudent->id);
    }

    /**
     * ADM-DASH-02: Unauthenticated user cannot view approvals.
     */
    public function test_adm_dash_02_unauthenticated_cannot_access_approvals(): void
    {
        $response = $this->getJson('/api/v1/approvals/pending');
        $response->assertStatus(401);
    }

    /**
     * ADM-DASH-02: Non-executive role (e.g. student) cannot view approvals.
     */
    public function test_adm_dash_02_unauthorized_role_cannot_view_approvals(): void
    {
        Sanctum::actingAs($this->studentUser);

        $response = $this->getJson('/api/v1/approvals/pending');
        $response->assertStatus(403);
    }

    /**
     * ADM-DASH-02: Principal can view pending approvals across leave, bookings, and report cards.
     */
    public function test_adm_dash_02_principal_can_view_pending_approvals(): void
    {
        Sanctum::actingAs($this->principalUser);

        // 1. Create a pending leave request
        $leave = LeaveRequest::create([
            'requested_by' => $this->teacherUser->id,
            'leave_type' => 'sakit',
            'start_date' => Carbon::today(),
            'end_date' => Carbon::tomorrow(),
            'reason' => 'Sakit demam butuh istirahat',
            'status' => 'diajukan',
        ]);

        // 2. Create a pending facility booking
        $facility = Facility::create([
            'name' => 'Lab Komputer 1',
            'code' => 'LAB-KOMP-1',
            'category' => 'laboratorium',
            'capacity' => 40,
            'requires_approval' => true,
        ]);
        $booking = Booking::create([
            'facility_id' => $facility->id,
            'requested_by' => $this->teacherUser->id,
            'start_at' => Carbon::now()->addDays(2),
            'end_at' => Carbon::now()->addDays(2)->addHours(2),
            'purpose' => 'Ujian simulasi praktikum',
            'status' => 'diajukan',
        ]);

        $response = $this->getJson('/api/v1/approvals/pending');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'category',
                        'title',
                        'requesterName',
                        'requesterRole',
                        'details',
                        'submittedAt',
                        'status',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $categories = array_column($data, 'category');
        $this->assertContains('leave_request', $categories);
        $this->assertContains('facility_purchase', $categories);
    }

    /**
     * ADM-DASH-02: Principal can approve a leave request.
     */
    public function test_adm_dash_02_principal_can_approve_leave_request(): void
    {
        Sanctum::actingAs($this->principalUser);

        $leave = LeaveRequest::create([
            'requested_by' => $this->teacherUser->id,
            'leave_type' => 'sakit',
            'start_date' => Carbon::today(),
            'end_date' => Carbon::tomorrow(),
            'reason' => 'Sakit demam',
            'status' => 'diajukan',
        ]);

        $response = $this->postJson("/api/v1/approvals/{$leave->id}/review", [
            'action' => 'approve',
            'notes' => 'Disetujui. Cepat sembuh.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'reviewedAt']);

        $leave->refresh();
        $this->assertEquals('disetujui', $leave->status);
        $this->assertEquals($this->principalUser->id, $leave->decided_by);
        $this->assertEquals('Disetujui. Cepat sembuh.', $leave->decision_notes);

        // Verify AuditLog was recorded
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'executive.approval.decision',
            'entity' => 'leave_requests',
            'entity_id' => $leave->id,
        ]);
    }

    /**
     * ADM-DASH-02: Principal can reject a leave request with required notes.
     */
    public function test_adm_dash_02_principal_can_reject_leave_request_with_reason(): void
    {
        Sanctum::actingAs($this->principalUser);

        $leave = LeaveRequest::create([
            'requested_by' => $this->teacherUser->id,
            'leave_type' => 'pribadi',
            'start_date' => Carbon::today(),
            'end_date' => Carbon::tomorrow(),
            'reason' => 'Keperluan pribadi mendadak',
            'status' => 'diajukan',
        ]);

        $response = $this->postJson("/api/v1/approvals/{$leave->id}/review", [
            'action' => 'reject',
            'notes' => 'Jadwal mengajar padat, mohon koordinasikan guru pengganti terlebih dahulu.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $leave->refresh();
        $this->assertEquals('ditolak', $leave->status);
        $this->assertEquals($this->principalUser->id, $leave->decided_by);
        $this->assertStringContainsString('Jadwal mengajar padat', $leave->decision_notes);
    }

    /**
     * ADM-DASH-02: Rejection without notes must fail validation.
     */
    public function test_adm_dash_02_reject_without_notes_fails_validation(): void
    {
        Sanctum::actingAs($this->principalUser);

        $leave = LeaveRequest::create([
            'requested_by' => $this->teacherUser->id,
            'leave_type' => 'pribadi',
            'start_date' => Carbon::today(),
            'end_date' => Carbon::tomorrow(),
            'reason' => 'Keperluan pribadi',
            'status' => 'diajukan',
        ]);

        $response = $this->postJson("/api/v1/approvals/{$leave->id}/review", [
            'action' => 'reject',
            'notes' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['notes']);
    }

    /**
     * ADM-DASH-02: Reviewing non-existent approval returns 404.
     */
    public function test_adm_dash_02_review_non_existent_approval_returns_404(): void
    {
        Sanctum::actingAs($this->principalUser);

        $response = $this->postJson('/api/v1/approvals/00000000-0000-0000-0000-000000000000/review', [
            'action' => 'approve',
            'notes' => 'Disetujui',
        ]);

        $response->assertStatus(404);
    }

    /**
     * ADM-DASH-02: Teacher without executive approval permission cannot execute decision.
     */
    public function test_adm_dash_02_teacher_cannot_execute_approval_decision(): void
    {
        Sanctum::actingAs($this->teacherUser);

        $leave = LeaveRequest::create([
            'requested_by' => $this->teacherUser->id,
            'leave_type' => 'sakit',
            'start_date' => Carbon::today(),
            'end_date' => Carbon::tomorrow(),
            'reason' => 'Sakit demam',
            'status' => 'diajukan',
        ]);

        $response = $this->postJson("/api/v1/approvals/{$leave->id}/review", [
            'action' => 'approve',
            'notes' => 'Disetujui',
        ]);

        $response->assertStatus(403);
    }

    /**
     * ADM-DASH-02: Principal can approve facility booking.
     */
    public function test_adm_dash_02_principal_can_approve_booking(): void
    {
        Sanctum::actingAs($this->principalUser);

        $facility = Facility::create([
            'name' => 'Aula Graha Cakrawala Mini',
            'code' => 'AULA-GCM',
            'category' => 'aula',
            'capacity' => 200,
            'requires_approval' => true,
        ]);
        $booking = Booking::create([
            'facility_id' => $facility->id,
            'requested_by' => $this->teacherUser->id,
            'start_at' => Carbon::now()->addDays(3),
            'end_at' => Carbon::now()->addDays(3)->addHours(4),
            'purpose' => 'Seminar Literasi Digital',
            'status' => 'diajukan',
        ]);

        $response = $this->postJson("/api/v1/approvals/{$booking->id}/review", [
            'action' => 'approve',
            'notes' => 'Disetujui, fasilitas siap digunakan.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $booking->refresh();
        $this->assertEquals('disetujui', $booking->status);
        $this->assertEquals($this->principalUser->id, $booking->decided_by);
    }

    /**
     * ADM-DASH-02: Principal can approve report card.
     */
    public function test_adm_dash_02_principal_can_approve_report_card(): void
    {
        Sanctum::actingAs($this->principalUser);

        $student = Student::create([
            'nis' => '99001',
            'nisn' => '0099887766',
            'nik_enc' => '3578010101019901',
            'full_name' => 'Siswa Teladan Rapor',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2009-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'active',
        ]);

        $academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $semester = Semester::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'Ganjil',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $reportCard = ReportCard::create([
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'status' => 'dikunci',
            'verify_token' => Str::random(32),
            'locked_at' => Carbon::now(),
            'data_snapshot' => ['student_name' => 'Siswa Teladan Rapor', 'average_score' => 88.5],
        ]);

        $response = $this->postJson("/api/v1/approvals/{$reportCard->id}/review", [
            'action' => 'approve',
            'notes' => 'Disahkan secara resmi.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $reportCard->refresh();
        $this->assertEquals('disetujui', $reportCard->status);
        $this->assertEquals($this->principalUser->id, $reportCard->approved_by);
    }

    /**
     * SEC-APPR-01: Cannot review already decided/finalized leave request.
     */
    public function test_sec_appr_01_cannot_review_already_decided_leave_request(): void
    {
        Sanctum::actingAs($this->principalUser);

        $leaveApproved = LeaveRequest::create([
            'requested_by' => $this->teacherUser->id,
            'leave_type' => 'sakit',
            'start_date' => Carbon::today(),
            'end_date' => Carbon::tomorrow(),
            'reason' => 'Sakit demam',
            'status' => 'disetujui',
            'decided_by' => $this->principalUser->id,
        ]);

        $response = $this->postJson("/api/v1/approvals/{$leaveApproved->id}/review", [
            'action' => 'reject',
            'notes' => 'Mencoba membatalkan status yang sudah disetujui',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.message', 'Permohonan izin ini telah diputuskan sebelumnya dan tidak dapat diubah kembali.');

        $leaveRejected = LeaveRequest::create([
            'requested_by' => $this->teacherUser->id,
            'leave_type' => 'pribadi',
            'start_date' => Carbon::today(),
            'end_date' => Carbon::tomorrow(),
            'reason' => 'Keperluan keluarga',
            'status' => 'ditolak',
            'decided_by' => $this->principalUser->id,
        ]);

        $response2 = $this->postJson("/api/v1/approvals/{$leaveRejected->id}/review", [
            'action' => 'approve',
            'notes' => 'Mencoba menyetujui yang sudah ditolak',
        ]);

        $response2->assertStatus(422)
            ->assertJsonPath('error.message', 'Permohonan izin ini telah diputuskan sebelumnya dan tidak dapat diubah kembali.');
    }

    /**
     * SEC-APPR-01: Cannot review finalized (selesai/dibatalkan) or already approved booking.
     */
    public function test_sec_appr_01_cannot_review_finalized_or_cancelled_booking(): void
    {
        Sanctum::actingAs($this->principalUser);

        $facility = Facility::create([
            'name' => 'Ruang Musik',
            'code' => 'R-MUSIK',
            'category' => 'khusus',
            'capacity' => 30,
            'requires_approval' => true,
        ]);

        $bookingDone = Booking::create([
            'facility_id' => $facility->id,
            'requested_by' => $this->teacherUser->id,
            'start_at' => Carbon::now()->subDays(2),
            'end_at' => Carbon::now()->subDays(2)->addHours(2),
            'purpose' => 'Latihan paduan suara',
            'status' => 'selesai',
        ]);

        $response1 = $this->postJson("/api/v1/approvals/{$bookingDone->id}/review", [
            'action' => 'reject',
            'notes' => 'Mencoba menolak booking selesai',
        ]);
        $response1->assertStatus(422);

        $bookingApproved = Booking::create([
            'facility_id' => $facility->id,
            'requested_by' => $this->teacherUser->id,
            'start_at' => Carbon::now()->addDays(5),
            'end_at' => Carbon::now()->addDays(5)->addHours(2),
            'purpose' => 'Latihan ansambel',
            'status' => 'disetujui',
        ]);

        $response2 = $this->postJson("/api/v1/approvals/{$bookingApproved->id}/review", [
            'action' => 'approve',
            'notes' => 'Mencoba menyetujui ulang',
        ]);
        $response2->assertStatus(422)
            ->assertJsonPath('error.message', 'Peminjaman ini sudah disetujui sebelumnya.');
    }

    /**
     * SEC-APPR-01: Cannot approve draft or already approved report card.
     */
    public function test_sec_appr_01_cannot_approve_draft_or_published_report_card(): void
    {
        Sanctum::actingAs($this->principalUser);

        $student = Student::create([
            'nis' => '99002',
            'nisn' => '0099887767',
            'nik_enc' => '3578010101019902',
            'full_name' => 'Siswa Draf Rapor',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2009-02-02',
            'entry_date' => '2026-07-01',
            'status' => 'active',
        ]);

        $academicYear = AcademicYear::create([
            'name' => '2026/2027 Genap',
            'start_date' => '2027-01-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $semester = Semester::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'Genap',
            'start_date' => '2027-01-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $draftReport = ReportCard::create([
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'status' => 'draft',
            'verify_token' => Str::random(32),
            'data_snapshot' => ['student_name' => 'Siswa Draf Rapor'],
        ]);

        $response = $this->postJson("/api/v1/approvals/{$draftReport->id}/review", [
            'action' => 'approve',
            'notes' => 'Mencoba mengesahkan rapor draf',
        ]);
        $response->assertStatus(422)
            ->assertJsonPath('error.message', 'E-Rapor masih berstatus draf dan belum dikunci oleh kurikulum untuk pengesahan.');

        $student2 = Student::create([
            'nis' => '99012',
            'nisn' => '0099887777',
            'nik_enc' => '3578010101019912',
            'full_name' => 'Siswa Approved Rapor',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2009-02-03',
            'entry_date' => '2026-07-01',
            'status' => 'active',
        ]);

        $approvedReport = ReportCard::create([
            'student_id' => $student2->id,
            'semester_id' => $semester->id,
            'status' => 'disetujui',
            'approved_by' => $this->principalUser->id,
            'verify_token' => Str::random(32),
            'data_snapshot' => ['student_name' => 'Siswa Approved Rapor'],
        ]);

        $response2 = $this->postJson("/api/v1/approvals/{$approvedReport->id}/review", [
            'action' => 'approve',
            'notes' => 'Mencoba mengesahkan ulang',
        ]);
        $response2->assertStatus(422)
            ->assertJsonPath('error.message', 'E-Rapor ini telah disahkan atau diterbitkan sebelumnya.');
    }

    /**
     * SEC-APPR-02: Facility double-booking prevention check.
     */
    public function test_sec_appr_02_cannot_approve_conflicting_facility_booking(): void
    {
        Sanctum::actingAs($this->principalUser);

        $facility = Facility::create([
            'name' => 'Laboratorium IPA',
            'code' => 'LAB-IPA',
            'category' => 'laboratorium',
            'capacity' => 36,
            'requires_approval' => true,
        ]);

        // Booking 1: Approved from 08:00 to 10:00 tomorrow
        $bookingApproved = Booking::create([
            'facility_id' => $facility->id,
            'requested_by' => $this->teacherUser->id,
            'start_at' => Carbon::tomorrow()->setHour(8)->setMinute(0),
            'end_at' => Carbon::tomorrow()->setHour(10)->setMinute(0),
            'purpose' => 'Praktikum Kimia Kelas 8A',
            'status' => 'disetujui',
        ]);

        // Booking 2: Overlapping from 09:00 to 11:00 tomorrow, status diajukan
        $bookingOverlapping = Booking::create([
            'facility_id' => $facility->id,
            'requested_by' => $this->teacherUser->id,
            'start_at' => Carbon::tomorrow()->setHour(9)->setMinute(0),
            'end_at' => Carbon::tomorrow()->setHour(11)->setMinute(0),
            'purpose' => 'Praktikum Biologi Kelas 8B',
            'status' => 'diajukan',
        ]);

        $responseConflict = $this->postJson("/api/v1/approvals/{$bookingOverlapping->id}/review", [
            'action' => 'approve',
            'notes' => 'Setujui jadwal bertabrakan',
        ]);

        $responseConflict->assertStatus(422)
            ->assertJsonPath('error.message', 'Tidak dapat menyetujui peminjaman karena fasilitas telah dibooking jadwal lain pada jam tersebut.');

        // Booking 3: Non-overlapping from 10:00 to 12:00 tomorrow, status diajukan
        $bookingNonOverlapping = Booking::create([
            'facility_id' => $facility->id,
            'requested_by' => $this->teacherUser->id,
            'start_at' => Carbon::tomorrow()->setHour(10)->setMinute(0),
            'end_at' => Carbon::tomorrow()->setHour(12)->setMinute(0),
            'purpose' => 'Praktikum Fisika Kelas 8C',
            'status' => 'diajukan',
        ]);

        $responseSuccess = $this->postJson("/api/v1/approvals/{$bookingNonOverlapping->id}/review", [
            'action' => 'approve',
            'notes' => 'Disetujui, jadwal aman tanpa konflik.',
        ]);

        $responseSuccess->assertStatus(200)
            ->assertJsonPath('success', true);

        $bookingNonOverlapping->refresh();
        $this->assertEquals('disetujui', $bookingNonOverlapping->status);
    }

    /**
     * SEC-APPR-03: Real canonical 'menunggu' leave request appears in pending queue.
     */
    public function test_sec_appr_03_canonical_menunggu_status_appears_in_pending_queue(): void
    {
        Sanctum::actingAs($this->principalUser);

        $leave = LeaveRequest::create([
            'requested_by' => $this->teacherUser->id,
            'leave_type' => 'sakit',
            'start_date' => Carbon::today(),
            'end_date' => Carbon::tomorrow(),
            'reason' => 'Sakit typhus surat dokter terlampir',
            'status' => 'menunggu',
        ]);

        $response = $this->getJson('/api/v1/approvals/pending');

        $response->assertStatus(200);
        $ids = array_column($response->json('data'), 'id');
        $this->assertContains((string) $leave->id, $ids);
    }

    /**
     * SEC-APPR-04: Report card queue includes locked cards and excludes draft/approved cards.
     */
    public function test_sec_appr_04_report_card_queue_filters_locked_cards(): void
    {
        Sanctum::actingAs($this->principalUser);

        $academicYear = AcademicYear::create([
            'name' => '2026/2027 Ganjil Filter',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $semester = Semester::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'Ganjil',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $student1 = Student::create([
            'nis' => '99003',
            'nisn' => '0099887768',
            'nik_enc' => '3578010101019903',
            'full_name' => 'Siswa Rapor Terkunci',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2009-03-03',
            'entry_date' => '2026-07-01',
            'status' => 'active',
        ]);

        $student2 = Student::create([
            'nis' => '99004',
            'nisn' => '0099887769',
            'nik_enc' => '3578010101019904',
            'full_name' => 'Siswa Rapor Draft',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2009-03-04',
            'entry_date' => '2026-07-01',
            'status' => 'active',
        ]);

        $student3 = Student::create([
            'nis' => '99005',
            'nisn' => '0099887770',
            'nik_enc' => '3578010101019905',
            'full_name' => 'Siswa Rapor Disetujui',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2009-03-05',
            'entry_date' => '2026-07-01',
            'status' => 'active',
        ]);

        // 1. Terkunci (canonical locked by kurikulum) - should appear
        $reportTerkunci = ReportCard::create([
            'student_id' => $student1->id,
            'semester_id' => $semester->id,
            'status' => 'terkunci',
            'locked_at' => Carbon::now(),
            'verify_token' => Str::random(32),
            'data_snapshot' => ['student_name' => 'Siswa Rapor Terkunci'],
        ]);

        // 2. Draft - should NOT appear
        $reportDraft = ReportCard::create([
            'student_id' => $student2->id,
            'semester_id' => $semester->id,
            'status' => 'draft',
            'verify_token' => Str::random(32),
            'data_snapshot' => ['student_name' => 'Siswa Rapor Draft'],
        ]);

        // 3. Approved - should NOT appear
        $reportApproved = ReportCard::create([
            'student_id' => $student3->id,
            'semester_id' => $semester->id,
            'status' => 'terkunci',
            'approved_by' => $this->principalUser->id,
            'verify_token' => Str::random(32),
            'data_snapshot' => ['student_name' => 'Siswa Rapor Disetujui'],
        ]);

        $response = $this->getJson('/api/v1/approvals/pending');
        $response->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains((string) $reportTerkunci->id, $ids);
        $this->assertNotContains((string) $reportDraft->id, $ids);
        $this->assertNotContains((string) $reportApproved->id, $ids);
    }

    /**
     * SEC-APPR-05: Approving student leave creates attendances and delivers in-app notification.
     */
    public function test_sec_appr_05_student_leave_approval_syncs_attendance_and_sends_notification(): void
    {
        Sanctum::actingAs($this->principalUser);

        $student = Student::create([
            'nis' => '99006',
            'nisn' => '0099887771',
            'nik_enc' => '3578010101019906',
            'full_name' => 'Siswa Sakit Rawat',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2009-04-04',
            'entry_date' => '2026-07-01',
            'status' => 'active',
        ]);

        $startDate = Carbon::today()->addDays(1);
        $endDate = Carbon::today()->addDays(2);

        $leave = LeaveRequest::create([
            'student_id' => $student->id,
            'requested_by' => $this->teacherUser->id,
            'leave_type' => 'sakit',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => 'Demam berdarah opname',
            'status' => 'menunggu',
        ]);

        $response = $this->postJson("/api/v1/approvals/{$leave->id}/review", [
            'action' => 'approve',
            'notes' => 'Disetujui. Semoga lekas sembuh.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Verify Attendance created for both dates
        $attendances = Attendance::where('student_id', $student->id)->orderBy('att_date')->get();
        $this->assertCount(2, $attendances);

        $this->assertEquals($startDate->format('Y-m-d'), $attendances[0]->att_date->format('Y-m-d'));
        $this->assertEquals('sakit', $attendances[0]->status);
        $this->assertEquals('gerbang', $attendances[0]->source);
        $this->assertEquals($this->principalUser->id, $attendances[0]->recorded_by);

        $this->assertEquals($endDate->format('Y-m-d'), $attendances[1]->att_date->format('Y-m-d'));
        $this->assertEquals('sakit', $attendances[1]->status);
        $this->assertEquals('gerbang', $attendances[1]->source);
        $this->assertEquals($this->principalUser->id, $attendances[1]->recorded_by);

        // Verify Notification sent to requester
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->teacherUser->id,
            'notif_type' => 'leave_request.decision',
        ]);
    }

    /**
     * SEC-APPR-07: Cross-school approval is blocked with 403 Forbidden.
     */
    public function test_sec_appr_07_cross_school_approval_is_forbidden(): void
    {
        $schoolA = (string) Str::uuid();
        $schoolB = (string) Str::uuid();

        // Assign principal to School A
        $this->principalUser->update(['school_id' => $schoolA]);
        Sanctum::actingAs($this->principalUser);

        // 1. Leave Request belonging to School B
        $studentSchoolB = Student::create([
            'school_id' => $schoolB,
            'nis' => '99007',
            'nisn' => '0099887772',
            'nik_enc' => '3578010101019907',
            'full_name' => 'Siswa Sekolah B',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2009-05-05',
            'entry_date' => '2026-07-01',
            'status' => 'active',
        ]);

        $leaveOtherSchool = LeaveRequest::create([
            'student_id' => $studentSchoolB->id,
            'leave_type' => 'sakit',
            'start_date' => Carbon::today(),
            'end_date' => Carbon::tomorrow(),
            'reason' => 'Sakit di Sekolah B',
            'status' => 'menunggu',
        ]);

        $responseLeave = $this->postJson("/api/v1/approvals/{$leaveOtherSchool->id}/review", [
            'action' => 'approve',
            'notes' => 'Coba menyetujui izin sekolah lain',
        ]);
        $responseLeave->assertStatus(403)
            ->assertJsonPath('error.message', 'Akses ditolak: Permohonan izin berada di luar sekolah penugasan Anda.');

        // 2. Booking belonging to School B
        $facilitySchoolB = Facility::create([
            'school_id' => $schoolB,
            'name' => 'Aula Sekolah B',
            'code' => 'AULA-SCH-B',
            'category' => 'aula',
            'capacity' => 100,
            'requires_approval' => true,
        ]);

        $bookingOtherSchool = Booking::create([
            'school_id' => $schoolB,
            'facility_id' => $facilitySchoolB->id,
            'requested_by' => $this->teacherUser->id,
            'start_at' => Carbon::now()->addDays(4),
            'end_at' => Carbon::now()->addDays(4)->addHours(2),
            'purpose' => 'Acara di Sekolah B',
            'status' => 'diajukan',
        ]);

        $responseBooking = $this->postJson("/api/v1/approvals/{$bookingOtherSchool->id}/review", [
            'action' => 'approve',
            'notes' => 'Coba menyetujui booking sekolah lain',
        ]);
        $responseBooking->assertStatus(403)
            ->assertJsonPath('error.message', 'Akses ditolak: Peminjaman berada di luar sekolah penugasan Anda.');
    }

    /**
     * SEC-APPR-08: AuditLog records client IP address correctly.
     */
    public function test_sec_appr_08_audit_log_records_client_ip_and_request_id(): void
    {
        Sanctum::actingAs($this->principalUser);

        $leave = LeaveRequest::create([
            'requested_by' => $this->teacherUser->id,
            'leave_type' => 'sakit',
            'start_date' => Carbon::today(),
            'end_date' => Carbon::tomorrow(),
            'reason' => 'Sakit audit check',
            'status' => 'menunggu',
        ]);

        $response = $this->postJson(
            "/api/v1/approvals/{$leave->id}/review",
            [
                'action' => 'approve',
                'notes' => 'Persetujuan audit IP',
            ],
            [
                'X-Request-Id' => 'test-req-id-12345',
            ]
        );

        $response->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'entity' => 'leave_requests',
            'entity_id' => (string) $leave->id,
            'action' => 'executive.approval.decision',
            'request_id' => 'test-req-id-12345',
            'ip' => '127.0.0.1',
        ]);
    }
}
