<?php

namespace Tests\Feature\Ppdb;

use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\ApplicantParent;
use App\Models\ClassStudent;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\Permission;
use App\Models\PPDBReRegistration;
use App\Models\PPDBTrack;
use App\Models\PPDBWave;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PPDBEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $candidateUser;

    protected User $otherCandidateUser;

    protected Role $adminRole;

    protected Role $guestRole;

    protected Role $studentRole;

    protected Role $guardianRole;

    protected Permission $permVerify;

    protected PPDBWave $wave;

    protected PPDBTrack $track;

    protected Applicant $acceptedApplicant;

    protected SchoolClass $schoolClass;

    protected AcademicYear $academicYear;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Permissions & Roles
        $this->permVerify = Permission::firstOrCreate(
            ['code' => 'ppdb.applicant.verify'],
            ['description' => 'Memverifikasi berkas dan konversi pendaftar PPDB']
        );

        $this->adminRole = Role::firstOrCreate(
            ['code' => 'ppdb_admin'],
            ['name' => 'Panitia PPDB', 'is_system' => true]
        );
        $this->adminRole->permissions()->syncWithoutDetaching([$this->permVerify->id]);

        $this->guestRole = Role::firstOrCreate(
            ['code' => 'guest'],
            ['name' => 'Tamu / Calon Siswa', 'is_system' => true]
        );

        $this->studentRole = Role::firstOrCreate(
            ['code' => 'student'],
            ['name' => 'Siswa', 'is_system' => true]
        );

        $this->guardianRole = Role::firstOrCreate(
            ['code' => 'guardian'],
            ['name' => 'Orang Tua / Wali Murid', 'is_system' => true]
        );

        // 2. Users
        $this->adminUser = User::create([
            'username' => 'panitia_ppdb_08',
            'email' => 'panitia08@labschool.sch.id',
            'phone' => '081299990001',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($this->adminRole->id);

        $this->candidateUser = User::create([
            'username' => 'calon_siswa_08',
            'email' => 'calon08@example.com',
            'phone' => '081299990002',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $this->candidateUser->roles()->attach($this->guestRole->id);

        $this->otherCandidateUser = User::create([
            'username' => 'calon_lain_08',
            'email' => 'calon_lain08@example.com',
            'phone' => '081299990003',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $this->otherCandidateUser->roles()->attach($this->guestRole->id);

        // 3. Wave & Track
        $this->wave = PPDBWave::create([
            'name' => 'PPDB Gelombang 1 2026/2027',
            'open_at' => Carbon::now()->subDays(10),
            'close_at' => Carbon::now()->subDays(2),
            'result_publish_at' => Carbon::now()->subDay(),
            'status' => 'aktif',
        ]);

        $this->track = PPDBTrack::create([
            'wave_id' => $this->wave->id,
            'code' => 'REGULER',
            'name' => 'Jalur Reguler',
            'quota' => 30,
        ]);

        // 4. Accepted Applicant
        $this->acceptedApplicant = Applicant::create([
            'wave_id' => $this->wave->id,
            'track_id' => $this->track->id,
            'user_id' => $this->candidateUser->id,
            'registration_no' => 'REG-2026-08001',
            'nik_enc' => '3201012345670001',
            'full_name' => 'Ahmad Fauzi Rahman',
            'birth_date' => '2010-05-14',
            'status' => 'selected',
            'decision' => 'diterima',
            'rank' => 1,
            'score' => 88.50,
            'step_data' => [
                'personal' => [
                    'nik' => '3201012345670001',
                    'full_name' => 'Ahmad Fauzi Rahman',
                    'birth_date' => '2010-05-14',
                    'gender' => 'L',
                    'birth_place' => 'Malang',
                    'religion' => 'Islam',
                    'address' => 'Jl. Ijen No. 10 Kota Malang',
                    'phone' => '081299990002',
                    'nisn' => '0054321987',
                ],
            ],
        ]);

        // 5. Parents for Applicant
        ApplicantParent::create([
            'applicant_id' => $this->acceptedApplicant->id,
            'parent_type' => 'ayah',
            'name' => 'Bambang Rahman',
            'phone' => '081122334455',
            'occupation' => 'PNS',
            'income_range' => '5.000.000 - 10.000.000',
        ]);

        ApplicantParent::create([
            'applicant_id' => $this->acceptedApplicant->id,
            'parent_type' => 'ibu',
            'name' => 'Siti Aminah',
            'phone' => '081122334456',
            'occupation' => 'Wiraswasta',
            'income_range' => '3.000.000 - 5.000.000',
        ]);

        // 6. Master Data for Class Placement
        $this->academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-15',
            'end_date' => '2027-06-20',
            'is_active' => true,
        ]);

        $gradeLevel = GradeLevel::create([
            'name' => 'Kelas 10',
            'sort_order' => 10,
        ]);

        $this->schoolClass = SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $gradeLevel->id,
            'name' => 'X-MIPA-1',
            'capacity' => 36,
        ]);
    }

    /**
     * BE-PPDB-08: Candidate can confirm re-registration.
     */
    public function test_be_ppdb_08_candidate_can_confirm_re_registration(): void
    {
        $response = $this->actingAs($this->candidateUser)
            ->postJson("/api/v1/ppdb/applications/{$this->acceptedApplicant->id}/re-register", [
                'notes' => 'Konfirmasi daftar ulang dan pemesanan seragam ukuran L.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.re_registration.status', 'dikonfirmasi')
            ->assertJsonPath('data.re_registration.applicant_id', $this->acceptedApplicant->id);

        $this->assertDatabaseHas('ppdb_re_registrations', [
            'applicant_id' => $this->acceptedApplicant->id,
            'status' => 'dikonfirmasi',
            'notes' => 'Konfirmasi daftar ulang dan pemesanan seragam ukuran L.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->candidateUser->id,
            'action' => 'ppdb_re_registration.confirmed',
        ]);
    }

    /**
     * BE-PPDB-08: Candidate cannot re-register if not accepted.
     */
    public function test_be_ppdb_08_cannot_re_register_if_not_accepted(): void
    {
        $unacceptedApplicant = Applicant::create([
            'wave_id' => $this->wave->id,
            'track_id' => $this->track->id,
            'user_id' => $this->candidateUser->id,
            'registration_no' => 'REG-2026-UNACC',
            'nik_enc' => '3201012345670099',
            'full_name' => 'Calon Belum Lolos',
            'birth_date' => '2010-06-20',
            'status' => 'submitted',
            'decision' => null,
        ]);

        $response = $this->actingAs($this->candidateUser)
            ->postJson("/api/v1/ppdb/applications/{$unacceptedApplicant->id}/re-register", [
                'notes' => 'Mencoba daftar ulang',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'UNPROCESSABLE_ENTITY');
    }

    /**
     * BE-PPDB-08: Cannot re-register another applicant's application without permission (IDOR protection).
     */
    public function test_be_ppdb_08_cannot_re_register_other_applicant_without_permission_idor(): void
    {
        $response = $this->actingAs($this->otherCandidateUser)
            ->postJson("/api/v1/ppdb/applications/{$this->acceptedApplicant->id}/re-register", [
                'notes' => 'Mencoba membajak konfirmasi',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-PPDB-08: Admin can enroll accepted applicant and convert into student.
     */
    public function test_be_ppdb_08_admin_can_enroll_accepted_applicant_and_create_student(): void
    {
        // Re-registration confirmed
        PPDBReRegistration::create([
            'applicant_id' => $this->acceptedApplicant->id,
            'status' => 'dikonfirmasi',
            'confirmed_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/ppdb/applications/{$this->acceptedApplicant->id}/enroll", [
                'nis' => '20260001',
                'entry_date' => '2026-07-15',
                'notes' => 'Konversi pendaftar ke siswa baru resmi',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.is_idempotent', false)
            ->assertJsonPath('data.student.nis', '20260001')
            ->assertJsonPath('data.student.full_name', 'Ahmad Fauzi Rahman')
            ->assertJsonPath('data.student.status', 'aktif')
            ->assertJsonPath('data.applicant.status', 'enrolled');

        // Check students table
        $student = Student::where('nis', '20260001')->first();
        $this->assertNotNull($student);
        $this->assertEquals('Ahmad Fauzi Rahman', $student->full_name);
        $this->assertEquals('0054321987', $student->nisn);
        $this->assertEquals('3201012345670001', $student->nik_enc);

        // Check applicant updated
        $this->acceptedApplicant->refresh();
        $this->assertEquals('enrolled', $this->acceptedApplicant->status);
        $this->assertEquals($student->id, $this->acceptedApplicant->student_id);

        // Check candidate user upgraded with student role
        $this->assertTrue($this->candidateUser->fresh()->hasRole('student'));

        // Check applicant status history & audit log
        $this->assertDatabaseHas('applicant_status_history', [
            'applicant_id' => $this->acceptedApplicant->id,
            'from_status' => 'selected',
            'to_status' => 'enrolled',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'ppdb_applicant.enrolled',
        ]);
    }

    /**
     * BE-PPDB-08: Enrollment creates guardian profiles and user accounts.
     */
    public function test_be_ppdb_08_enrollment_creates_guardian_and_user_account(): void
    {
        PPDBReRegistration::create([
            'applicant_id' => $this->acceptedApplicant->id,
            'status' => 'dikonfirmasi',
            'confirmed_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/ppdb/applications/{$this->acceptedApplicant->id}/enroll");

        $response->assertStatus(200);

        // Guardians should be created for Bambang and Siti
        $guardianBambang = Guardian::where('full_name', 'Bambang Rahman')->first();
        $this->assertNotNull($guardianBambang);
        $this->assertEquals('081122334455', $guardianBambang->phone);
        $this->assertNotNull($guardianBambang->user);
        $this->assertTrue($guardianBambang->user->hasRole('guardian'));

        $guardianSiti = Guardian::where('full_name', 'Siti Aminah')->first();
        $this->assertNotNull($guardianSiti);
        $this->assertEquals('081122334456', $guardianSiti->phone);

        // Linked via student_guardians pivot
        $student = Student::where('full_name', 'Ahmad Fauzi Rahman')->first();
        $this->assertTrue($student->guardians->contains($guardianBambang->id));
        $this->assertTrue($student->guardians->contains($guardianSiti->id));
    }

    /**
     * BE-PPDB-08: Enrollment places student in class if class_id is provided.
     */
    public function test_be_ppdb_08_enrollment_places_student_in_class_if_class_id_provided(): void
    {
        PPDBReRegistration::create([
            'applicant_id' => $this->acceptedApplicant->id,
            'status' => 'dikonfirmasi',
            'confirmed_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/ppdb/applications/{$this->acceptedApplicant->id}/enroll", [
                'class_id' => $this->schoolClass->id,
            ]);

        $response->assertStatus(200);

        $student = Student::where('full_name', 'Ahmad Fauzi Rahman')->first();
        $this->assertNotNull($student);

        $this->assertDatabaseHas('class_students', [
            'class_id' => $this->schoolClass->id,
            'student_id' => $student->id,
        ]);
    }

    /**
     * BE-PPDB-08: Enrollment is strictly idempotent; no duplicate records created on repeated execution.
     */
    public function test_be_ppdb_08_enrollment_is_idempotent_no_duplicates_created(): void
    {
        PPDBReRegistration::create([
            'applicant_id' => $this->acceptedApplicant->id,
            'status' => 'dikonfirmasi',
            'confirmed_at' => Carbon::now(),
        ]);

        // 1st Execution
        $firstResponse = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/ppdb/applications/{$this->acceptedApplicant->id}/enroll", [
                'class_id' => $this->schoolClass->id,
            ]);

        $firstResponse->assertStatus(200)
            ->assertJsonPath('data.is_idempotent', false);

        $studentCountBefore = Student::count();
        $userCountBefore = User::count();
        $guardianCountBefore = Guardian::count();
        $classStudentCountBefore = ClassStudent::count();

        // 2nd Execution (re-run)
        $secondResponse = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/ppdb/applications/{$this->acceptedApplicant->id}/enroll", [
                'class_id' => $this->schoolClass->id,
            ]);

        $secondResponse->assertStatus(200)
            ->assertJsonPath('data.is_idempotent', true);

        // Verify counts remain identical
        $this->assertEquals($studentCountBefore, Student::count(), 'Student count should not increase on repeated enrollment');
        $this->assertEquals($userCountBefore, User::count(), 'User count should not increase on repeated enrollment');
        $this->assertEquals($guardianCountBefore, Guardian::count(), 'Guardian count should not increase on repeated enrollment');
        $this->assertEquals($classStudentCountBefore, ClassStudent::count(), 'Class assignment should not duplicate');
    }

    /**
     * BE-PPDB-08: Cannot enroll unaccepted applicant.
     */
    public function test_be_ppdb_08_cannot_enroll_unaccepted_applicant(): void
    {
        $waitlistApplicant = Applicant::create([
            'wave_id' => $this->wave->id,
            'track_id' => $this->track->id,
            'user_id' => $this->otherCandidateUser->id,
            'registration_no' => 'REG-WAITLIST-01',
            'nik_enc' => '3201012345679999',
            'full_name' => 'Siswa Cadangan',
            'birth_date' => '2010-07-12',
            'status' => 'waitlist',
            'decision' => 'cadangan',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/ppdb/applications/{$waitlistApplicant->id}/enroll");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'UNPROCESSABLE_ENTITY');
    }

    /**
     * BE-PPDB-08: Unauthorized user cannot enroll applicant.
     */
    public function test_be_ppdb_08_unauthorized_user_cannot_enroll_applicant(): void
    {
        PPDBReRegistration::create([
            'applicant_id' => $this->acceptedApplicant->id,
            'status' => 'dikonfirmasi',
            'confirmed_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->candidateUser)
            ->postJson("/api/v1/ppdb/applications/{$this->acceptedApplicant->id}/enroll");

        $response->assertStatus(403);
    }

    /**
     * BE-PPDB-08: Cannot enroll if re-registration is not confirmed unless force_enroll is true.
     */
    public function test_be_ppdb_08_cannot_enroll_if_re_registration_not_confirmed_without_force(): void
    {
        // Re-registration not confirmed yet
        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/ppdb/applications/{$this->acceptedApplicant->id}/enroll");

        $response->assertStatus(422)
            ->assertJsonPath('error.message', 'Calon siswa belum melakukan konfirmasi daftar ulang');

        // Can bypass with force_enroll: true
        $forceResponse = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/ppdb/applications/{$this->acceptedApplicant->id}/enroll", [
                'force_enroll' => true,
            ]);

        $forceResponse->assertStatus(200)
            ->assertJsonPath('data.student.full_name', 'Ahmad Fauzi Rahman');
    }

    /**
     * BE-PPDB-08 / Decision #4: Candidate cannot re-register after 3 business days deadline.
     */
    public function test_be_ppdb_08_cannot_re_register_after_3_business_days_deadline(): void
    {
        // Set wave result_publish_at to 10 days ago (past 3 business days deadline)
        $this->wave->update([
            'result_publish_at' => Carbon::now()->subWeekdays(5),
        ]);

        $response = $this->actingAs($this->candidateUser)
            ->postJson("/api/v1/ppdb/applications/{$this->acceptedApplicant->id}/re-register", [
                'notes' => 'Konfirmasi terlambat',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'UNPROCESSABLE_ENTITY');
        $this->assertStringContainsString('Batas waktu konfirmasi daftar ulang telah berakhir', $response->json('error.message'));

        // Admin with permission can still process re-registration override
        $adminOverrideResponse = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/ppdb/applications/{$this->acceptedApplicant->id}/re-register", [
                'notes' => 'Disetujui panitia lewat tenggat',
            ]);

        $adminOverrideResponse->assertStatus(200);
    }

    /**
     * BE-PPDB-08 / Decision #6: Guardian account generated with activation token link and not plain password.
     */
    public function test_be_ppdb_08_guardian_account_generated_with_activation_token(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/ppdb/applications/{$this->acceptedApplicant->id}/enroll", [
                'force_enroll' => true,
            ]);

        $response->assertStatus(200);
        $activationLinks = $response->json('data.activation_links');
        $this->assertNotEmpty($activationLinks);
        $this->assertNotNull($activationLinks[0]['activation_token']);
        $this->assertStringContainsString('/auth/activate-guardian?token=', $activationLinks[0]['activation_url']);
    }
}
