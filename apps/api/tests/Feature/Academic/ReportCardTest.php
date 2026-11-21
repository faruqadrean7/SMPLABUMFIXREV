<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\ClassStudent;
use App\Models\Extracurricular;
use App\Models\ExtracurricularMember;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradeLevel;
use App\Models\GradePeriod;
use App\Models\Guardian;
use App\Models\Permission;
use App\Models\ReportCard;
use App\Models\ReportDescriptionTemplate;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportCardTest extends TestCase
{
    use RefreshDatabase;

    protected User $principalUser;

    protected User $adminUser;

    protected User $walasUser;

    protected User $student1User;

    protected User $student2User;

    protected User $guardianUser;

    protected Teacher $walas;

    protected Student $student1;

    protected Student $student2;

    protected SchoolClass $classA;

    protected Semester $semester1;

    protected Subject $subjectMath;

    protected GradeComponent $componentTugas;

    protected GradeComponent $componentSAS;

    protected function setUp(): void
    {
        parent::setUp();

        $rolePrincipal = Role::firstOrCreate(['code' => 'principal'], ['name' => 'Kepala Sekolah', 'is_system' => true]);
        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Wali Murid', 'is_system' => true]);

        $permReportGen = Permission::firstOrCreate(['code' => 'academic.report.generate'], ['description' => 'Mencetak rapor']);
        $permGradeRead = Permission::firstOrCreate(['code' => 'academic.grade.read'], ['description' => 'Melihat nilai']);
        $permGradeWrite = Permission::firstOrCreate(['code' => 'academic.grade.write'], ['description' => 'Menginput nilai']);

        $rolePrincipal->permissions()->syncWithoutDetaching([$permReportGen->id, $permGradeRead->id]);
        $roleAdmin->permissions()->syncWithoutDetaching([$permReportGen->id, $permGradeRead->id, $permGradeWrite->id]);
        $roleTeacher->permissions()->syncWithoutDetaching([$permGradeRead->id, $permGradeWrite->id]);
        $roleStudent->permissions()->syncWithoutDetaching([$permGradeRead->id]);
        $roleGuardian->permissions()->syncWithoutDetaching([$permGradeRead->id]);

        // Users
        $this->principalUser = User::create([
            'username' => 'kepsek_semester',
            'email' => 'kepsek.sem@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->principalUser->roles()->attach($rolePrincipal->id);

        $this->adminUser = User::create([
            'username' => 'admin_kurikulum',
            'email' => 'kurikulum@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->walasUser = User::create([
            'username' => 'walas_sem1',
            'email' => 'walas.sem@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->walasUser->roles()->attach($roleTeacher->id);

        $this->student1User = User::create([
            'username' => 'student_sem1',
            'email' => 'student.sem1@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->student1User->roles()->attach($roleStudent->id);

        $this->student2User = User::create([
            'username' => 'student_sem2',
            'email' => 'student.sem2@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->student2User->roles()->attach($roleStudent->id);

        $this->guardianUser = User::create([
            'username' => 'guardian_sem1',
            'email' => 'guardian.sem@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->guardianUser->roles()->attach($roleGuardian->id);

        // Academic Structure
        $ay = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
        ]);

        $this->semester1 = Semester::create([
            'academic_year_id' => $ay->id,
            'name' => 'Semester Ganjil 2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $periodMonth8 = GradePeriod::create([
            'semester_id' => $this->semester1->id,
            'month' => 8,
            'status' => 'open',
        ]);

        $periodMonth12 = GradePeriod::create([
            'semester_id' => $this->semester1->id,
            'month' => 12,
            'status' => 'open',
        ]);

        $this->walas = Teacher::create([
            'user_id' => $this->walasUser->id,
            'nip' => '198701012010011002',
            'full_name' => 'Dra. Endang Rahayu, M.Pd',
            'status' => 'active',
        ]);

        $gradeLevel = GradeLevel::create([
            'name' => 'Kelas 10',
            'level' => 10,
        ]);

        $this->classA = SchoolClass::create([
            'academic_year_id' => $ay->id,
            'grade_level_id' => $gradeLevel->id,
            'homeroom_teacher_id' => $this->walas->id,
            'name' => 'X-MIPA-1',
            'capacity' => 36,
        ]);

        $this->student1 = Student::create([
            'user_id' => $this->student1User->id,
            'nis' => '202600101',
            'nisn' => '0098765501',
            'nik_enc' => 'enc_3578010101010101',
            'full_name' => 'Muhammad Danang',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-03-03',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $this->student2 = Student::create([
            'user_id' => $this->student2User->id,
            'nis' => '202600102',
            'nisn' => '0098765502',
            'nik_enc' => 'enc_3578010101010102',
            'full_name' => 'Rina Amalia',
            'gender' => 'P',
            'birth_place' => 'Batu',
            'birth_date' => '2010-04-04',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        ClassStudent::create([
            'class_id' => $this->classA->id,
            'student_id' => $this->student1->id,
            'joined_at' => Carbon::now()->subMonths(3),
        ]);

        ClassStudent::create([
            'class_id' => $this->classA->id,
            'student_id' => $this->student2->id,
            'joined_at' => Carbon::now()->subMonths(3),
        ]);

        // Hubungkan Guardian dengan Student1
        $guardian = Guardian::create([
            'user_id' => $this->guardianUser->id,
            'full_name' => 'Bapak Danang Senior',
            'phone' => '081234567899',
        ]);
        $guardian->students()->attach($this->student1->id, ['relationship' => 'father', 'is_primary' => true]);

        // Subject & Components
        $this->subjectMath = Subject::create([
            'code' => 'MAT-10',
            'name' => 'Matematika Peminatan',
            'subject_group' => 'Kelompok A',
        ]);

        $this->componentTugas = GradeComponent::create([
            'subject_id' => $this->subjectMath->id,
            'semester_id' => $this->semester1->id,
            'class_id' => $this->classA->id,
            'code' => 'TGS-SEM',
            'name' => 'Rata-rata Tugas',
            'weight' => 40.00,
        ]);

        $this->componentSAS = GradeComponent::create([
            'subject_id' => $this->subjectMath->id,
            'semester_id' => $this->semester1->id,
            'class_id' => $this->classA->id,
            'code' => 'SAS-1',
            'name' => 'Sumatif Akhir Semester',
            'weight' => 60.00,
        ]);

        // Nilai Student 1: Tugas = 85 (weight 40), SAS = 90 (weight 60) -> Akhir = (3400 + 5400)/100 = 88.0 (Tuntas)
        Grade::create([
            'student_id' => $this->student1->id,
            'component_id' => $this->componentTugas->id,
            'period_id' => $periodMonth8->id,
            'value' => 85.00,
            'source' => 'manual',
            'entered_by' => $this->walasUser->id,
        ]);
        Grade::create([
            'student_id' => $this->student1->id,
            'component_id' => $this->componentSAS->id,
            'period_id' => $periodMonth12->id,
            'value' => 90.00,
            'source' => 'manual',
            'entered_by' => $this->walasUser->id,
        ]);

        // Nilai Student 2: Tugas = 60, SAS = 65 -> Akhir = (2400 + 3900)/100 = 63.0 (Belum Tuntas)
        Grade::create([
            'student_id' => $this->student2->id,
            'component_id' => $this->componentTugas->id,
            'period_id' => $periodMonth8->id,
            'value' => 60.00,
            'source' => 'manual',
            'entered_by' => $this->walasUser->id,
        ]);
        Grade::create([
            'student_id' => $this->student2->id,
            'component_id' => $this->componentSAS->id,
            'period_id' => $periodMonth12->id,
            'value' => 65.00,
            'source' => 'manual',
            'entered_by' => $this->walasUser->id,
        ]);

        // Presensi Semester Student 1
        Attendance::create([
            'student_id' => $this->student1->id,
            'att_date' => '2026-08-10',
            'status' => 'hadir',
            'source' => 'gate',
            'recorded_by' => $this->walasUser->id,
        ]);
        Attendance::create([
            'student_id' => $this->student1->id,
            'att_date' => '2026-11-15',
            'status' => 'sakit',
            'source' => 'gate',
            'recorded_by' => $this->walasUser->id,
        ]);

        // Ekstrakurikuler Student 1
        $ekskul = Extracurricular::create([
            'name' => 'PMR (Palang Merah Remaja)',
            'quota' => 30,
        ]);
        ExtracurricularMember::create([
            'extracurricular_id' => $ekskul->id,
            'student_id' => $this->student1->id,
            'status' => 'aktif',
            'final_grade' => 'A',
        ]);
    }

    /**
     * BE-GRD-04: CRUD template deskripsi capaian kompetensi per mapel.
     */
    public function test_be_grd_04_crud_report_description_templates(): void
    {
        // 1. Simpan template capaian tuntas
        $responseStore = $this->actingAs($this->walasUser, 'sanctum')
            ->postJson('/api/v1/report-description-templates', [
                'subject_id' => $this->subjectMath->id,
                'level' => 'tuntas',
                'template_text' => 'Menunjukkan penguasaan optimal dalam menyelesaikan persamaan dan pertidaksamaan nilai mutlak.',
            ]);

        $responseStore->assertStatus(201)
            ->assertJsonPath('data.subject_id', $this->subjectMath->id)
            ->assertJsonPath('data.level', 'tuntas');

        $templateId = $responseStore->json('data.id');

        // 2. Daftar template
        $responseList = $this->actingAs($this->walasUser, 'sanctum')
            ->getJson('/api/v1/report-description-templates');

        $responseList->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // 3. Hapus template
        $responseDelete = $this->actingAs($this->walasUser, 'sanctum')
            ->deleteJson("/api/v1/report-description-templates/{$templateId}");

        $responseDelete->assertStatus(200);
        $this->assertDatabaseMissing('report_description_templates', ['id' => $templateId]);
    }

    /**
     * BE-GRD-04: Generate E-Rapor semesteran menghitung nilai akhir terbobot dan mengisi deskripsi capaian otomatis.
     */
    public function test_be_grd_04_generate_report_card_computes_final_grades_and_auto_descriptions(): void
    {
        // Siapkan template deskripsi
        ReportDescriptionTemplate::create([
            'subject_id' => $this->subjectMath->id,
            'level' => 'tuntas',
            'template_text' => 'Sangat menguasai materi aljabar dan kalkulus dasar dengan hasil optimal.',
        ]);

        ReportDescriptionTemplate::create([
            'subject_id' => $this->subjectMath->id,
            'level' => 'belum',
            'template_text' => 'Memerlukan pendampingan dalam menyelesaikan kalkulus dasar dan fungsi kuadrat.',
        ]);

        // Generate untuk Student 1 (Nilai 88.0 -> Tuntas)
        $response = $this->actingAs($this->walasUser, 'sanctum')
            ->postJson('/api/v1/report-cards/generate', [
                'semester_id' => $this->semester1->id,
                'student_id' => $this->student1->id,
                'homeroom_note' => 'Pertahankan kedisiplinan dan prestasi akademiknya.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.student_id', $this->student1->id)
            ->assertJsonPath('data.semester_id', $this->semester1->id)
            ->assertJsonPath('data.status', 'draft');

        $report = ReportCard::where('student_id', $this->student1->id)
            ->where('semester_id', $this->semester1->id)
            ->first();

        $this->assertNotNull($report);
        $this->assertNotEmpty($report->verify_token);

        $snapshot = $report->data_snapshot;
        $this->assertEquals('Muhammad Danang', $snapshot['student']['full_name']);
        $this->assertEquals('X-MIPA-1', $snapshot['student']['class_name']);

        // Verifikasi mapel & deskripsi otomatis
        $this->assertCount(1, $snapshot['subjects']);
        $subj = $snapshot['subjects'][0];
        $this->assertEquals('Matematika Peminatan', $subj['subject_name']);
        $this->assertEquals(88.00, $subj['final_score']);
        $this->assertEquals('tuntas', $subj['level']);
        $this->assertEquals('Sangat menguasai materi aljabar dan kalkulus dasar dengan hasil optimal.', $subj['competency_description']);

        // Verifikasi absensi & ekskul
        $this->assertEquals(1, $snapshot['attendance']['hadir']);
        $this->assertEquals(1, $snapshot['attendance']['sakit']);
        $this->assertCount(1, $snapshot['extracurriculars']);
        $this->assertEquals('PMR (Palang Merah Remaja)', $snapshot['extracurriculars'][0]['name']);
        $this->assertEquals('Pertahankan kedisiplinan dan prestasi akademiknya.', $snapshot['homeroom_note']);
    }

    /**
     * BE-GRD-04: Generate massal E-Rapor semester untuk satu rombel kelas.
     */
    public function test_be_grd_04_bulk_generate_report_cards_for_class(): void
    {
        $response = $this->actingAs($this->walasUser, 'sanctum')
            ->postJson('/api/v1/report-cards/generate', [
                'semester_id' => $this->semester1->id,
                'class_id' => $this->classA->id,
                'status' => 'draft',
            ]);

        $response->assertStatus(201)
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('report_cards', [
            'student_id' => $this->student1->id,
            'semester_id' => $this->semester1->id,
        ]);

        $this->assertDatabaseHas('report_cards', [
            'student_id' => $this->student2->id,
            'semester_id' => $this->semester1->id,
        ]);
    }

    /**
     * BE-GRD-04: Penguncian (lock) dan buka kunci (unlock) rapor semester oleh Admin Kurikulum.
     */
    public function test_be_grd_04_admin_can_lock_and_unlock_report_card(): void
    {
        $report = ReportCard::create([
            'student_id' => $this->student1->id,
            'semester_id' => $this->semester1->id,
            'data_snapshot' => ['student' => ['name' => 'Muhammad Danang']],
            'status' => 'draft',
            'verify_token' => 'token-lock-test-1',
        ]);

        // Admin Kurikulum mengunci rapor
        $responseLock = $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/v1/report-cards/{$report->id}/lock", [
                'is_locked' => true,
                'reason' => 'Batas akhir pengisian nilai semester telah ditutup.',
            ]);

        $responseLock->assertStatus(200)
            ->assertJsonPath('data.status', 'terkunci');

        $this->assertNotNull($report->fresh()->locked_at);

        // Percobaan re-generate saat terkunci harus ditolak
        $responseRegenerate = $this->actingAs($this->walasUser, 'sanctum')
            ->postJson('/api/v1/report-cards/generate', [
                'semester_id' => $this->semester1->id,
                'student_id' => $this->student1->id,
            ]);

        $responseRegenerate->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');

        // Admin membuka kunci rapor (unlock)
        $this->app['auth']->forgetGuards();
        $responseUnlock = $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/v1/report-cards/{$report->id}/lock", [
                'is_locked' => false,
                'reason' => 'Perbaikan nilai susulan.',
            ]);

        $responseUnlock->assertStatus(200)
            ->assertJsonPath('data.status', 'draft');

        $this->assertNull($report->fresh()->locked_at);
    }

    /**
     * BE-GRD-04: User non-admin tidak dapat mengunci rapor semester.
     */
    public function test_be_grd_04_non_admin_cannot_lock_report_card(): void
    {
        $report = ReportCard::create([
            'student_id' => $this->student1->id,
            'semester_id' => $this->semester1->id,
            'data_snapshot' => ['student' => ['name' => 'Danang']],
            'status' => 'draft',
            'verify_token' => 'token-lock-unauth-test',
        ]);

        $response = $this->actingAs($this->walasUser, 'sanctum')
            ->patchJson("/api/v1/report-cards/{$report->id}/lock", [
                'is_locked' => true,
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-GRD-04: Lifecycle persetujuan dan pengesahan E-Rapor oleh Kepala Sekolah.
     */
    public function test_be_grd_04_principal_approval_lifecycle_and_publish(): void
    {
        $report = ReportCard::create([
            'student_id' => $this->student1->id,
            'semester_id' => $this->semester1->id,
            'data_snapshot' => ['student' => ['name' => 'Danang']],
            'status' => 'draft',
            'verify_token' => 'token-approval-sem-test',
        ]);

        // ACC Kepala Sekolah
        $responseApprove = $this->actingAs($this->principalUser, 'sanctum')
            ->patchJson("/api/v1/report-cards/{$report->id}/approve", [
                'status' => 'disetujui',
                'note' => 'Disahkan oleh Kepala Sekolah.',
            ]);

        $responseApprove->assertStatus(200)
            ->assertJsonPath('data.status', 'disetujui')
            ->assertJsonPath('data.approved_by', $this->principalUser->id);

        // Terbitkan rapor ke siswa/wali murid
        $this->app['auth']->forgetGuards();
        $responsePublish = $this->actingAs($this->principalUser, 'sanctum')
            ->patchJson("/api/v1/report-cards/{$report->id}/approve", [
                'status' => 'terbit',
            ]);

        $responsePublish->assertStatus(200)
            ->assertJsonPath('data.status', 'terbit');

        // Walas mencoba approve (ditolak)
        $this->app['auth']->forgetGuards();
        $responseWalas = $this->actingAs($this->walasUser, 'sanctum')
            ->patchJson("/api/v1/report-cards/{$report->id}/approve", [
                'status' => 'disetujui',
            ]);

        $responseWalas->assertStatus(403);
    }

    /**
     * BE-GRD-04: Siswa & Wali Murid hanya dapat melihat rapor semester yang berstatus terbit.
     */
    public function test_be_grd_04_student_and_guardian_scoping_cannot_see_draft_or_locked_report(): void
    {
        $reportDraft = ReportCard::create([
            'student_id' => $this->student1->id,
            'semester_id' => $this->semester1->id,
            'data_snapshot' => ['student' => ['name' => 'Danang']],
            'status' => 'draft',
            'verify_token' => 'token-scoping-sem-test',
        ]);

        // Siswa mencoba melihat list
        $responseList = $this->actingAs($this->student1User, 'sanctum')
            ->getJson('/api/v1/report-cards');

        $responseList->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // Siswa mencoba melihat detail
        $responseDetail = $this->actingAs($this->student1User, 'sanctum')
            ->getJson("/api/v1/report-cards/{$reportDraft->id}");

        $responseDetail->assertStatus(403);

        // Setelah berstatus terbit
        $reportDraft->update(['status' => 'terbit', 'approved_by' => $this->principalUser->id]);

        $this->app['auth']->forgetGuards();
        $responseStudentPublished = $this->actingAs($this->student1User, 'sanctum')
            ->getJson("/api/v1/report-cards/{$reportDraft->id}");

        $responseStudentPublished->assertStatus(200)
            ->assertJsonPath('data.status', 'terbit');

        $this->app['auth']->forgetGuards();
        $responseGuardianPublished = $this->actingAs($this->guardianUser, 'sanctum')
            ->getJson("/api/v1/report-cards/{$reportDraft->id}");

        $responseGuardianPublished->assertStatus(200)
            ->assertJsonPath('data.id', $reportDraft->id);
    }

    /**
     * BE-GRD-04: Otorisasi IDOR - Orang tua murid A tidak bisa melihat e-rapor semester murid B.
     */
    public function test_be_grd_04_guardian_cannot_see_other_student_report_card_idor(): void
    {
        $reportStudent2 = ReportCard::create([
            'student_id' => $this->student2->id,
            'semester_id' => $this->semester1->id,
            'data_snapshot' => ['student' => ['name' => 'Rina Amalia']],
            'status' => 'terbit',
            'verify_token' => 'token-sem-stud2-test',
            'approved_by' => $this->principalUser->id,
        ]);

        // Ortu Danang mengakses rapor Rina
        $response = $this->actingAs($this->guardianUser, 'sanctum')
            ->getJson("/api/v1/report-cards/{$reportStudent2->id}");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-GRD-04: Unduh PDF resmi E-Rapor semester %PDF-1.4 bertanda tangan QR.
     */
    public function test_be_grd_04_download_pdf_e_rapor_semester_valid_document(): void
    {
        $report = ReportCard::create([
            'student_id' => $this->student1->id,
            'semester_id' => $this->semester1->id,
            'data_snapshot' => [
                'student' => [
                    'full_name' => 'Muhammad Danang',
                    'nis' => '202600101',
                    'nisn' => '0098765501',
                    'class_name' => 'X-MIPA-1',
                ],
                'semester' => [
                    'name' => 'Semester Ganjil 2026/2027',
                    'academic_year' => '2026/2027',
                ],
                'subjects' => [
                    [
                        'subject_code' => 'MAT-10',
                        'subject_name' => 'Matematika Peminatan',
                        'final_score' => 88.00,
                        'level' => 'tuntas',
                        'competency_description' => 'Sangat menguasai materi kalkulus dasar.',
                    ],
                ],
                'attendance' => [
                    'hadir' => 50,
                    'sakit' => 2,
                    'izin' => 1,
                    'alpa' => 0,
                ],
                'extracurriculars' => [
                    ['name' => 'PMR', 'role' => 'Ketua', 'predicate' => 'Sangat Baik'],
                ],
                'homeroom_note' => 'Pertahankan prestasimu.',
            ],
            'status' => 'terbit',
            'verify_token' => 'sem-pdf-token-valid-776655',
            'approved_by' => $this->principalUser->id,
        ]);

        $response = $this->actingAs($this->student1User, 'sanctum')
            ->get("/api/v1/report-cards/{$report->id}/pdf");

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $content);
        $this->assertStringContainsString('LAPORAN HASIL BELAJAR', $content);
        $this->assertStringContainsString('sem-pdf-token-valid-776655', $content);
    }

    /**
     * BE-GRD-04: Verifikasi dokumen publik via QR token untuk E-Rapor semester.
     */
    public function test_be_grd_04_public_qr_verification_validates_e_rapor_semester(): void
    {
        ReportCard::create([
            'student_id' => $this->student1->id,
            'semester_id' => $this->semester1->id,
            'data_snapshot' => [
                'school_name' => 'Portal Sekolah Satu Pintu SISTLABUM',
                'student' => [
                    'full_name' => 'Muhammad Danang',
                    'nis' => '202600101',
                    'nisn' => '0098765501',
                    'class_name' => 'X-MIPA-1',
                ],
                'semester' => [
                    'name' => 'Semester Ganjil 2026/2027',
                    'academic_year' => '2026/2027',
                ],
            ],
            'status' => 'disetujui',
            'verify_token' => 'qr-semester-token-998811',
            'approved_by' => $this->principalUser->id,
        ]);

        $response = $this->getJson('/api/v1/public/verify/qr-semester-token-998811');

        $response->assertStatus(200)
            ->assertJsonPath('data.is_valid', true)
            ->assertJsonPath('data.document_title', 'Laporan Hasil Belajar (E-Rapor Semester)')
            ->assertJsonPath('data.student.name', 'Muhammad Danang')
            ->assertJsonPath('data.student.nisn', '0098765501')
            ->assertJsonPath('data.approval.approved_by', $this->principalUser->username);
    }

    /**
     * BE-GRD-04: Validasi kesalahan input jika student_id maupun class_id tidak diberikan saat generate.
     */
    public function test_be_grd_04_validation_error_when_neither_student_nor_class_provided(): void
    {
        $response = $this->actingAs($this->walasUser, 'sanctum')
            ->postJson('/api/v1/report-cards/generate', [
                'semester_id' => $this->semester1->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['target']);
    }

    /**
     * BE-GRD-04: Tinjauan status & kesiapan data rapor semester satu kelas (Class Status Matrix).
     */
    public function test_be_grd_04_class_status_matrix_for_homeroom_teacher_and_admin(): void
    {
        // 1. Walas mengakses class-status
        $response = $this->actingAs($this->walasUser, 'sanctum')
            ->getJson("/api/v1/report-cards/class-status?class_id={$this->classA->id}&semester_id={$this->semester1->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.class.id', $this->classA->id)
            ->assertJsonPath('data.semester.id', $this->semester1->id)
            ->assertJsonPath('data.summary.total_students', 2)
            ->assertJsonPath('data.summary.generated_count', 0)
            ->assertJsonPath('data.summary.belum_dibuat_count', 2)
            ->assertJsonPath('data.students.0.data_readiness.has_grades', true);

        // 2. Admin juga dapat mengakses
        $responseAdmin = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/report-cards/class-status?class_id={$this->classA->id}&semester_id={$this->semester1->id}");

        $responseAdmin->assertStatus(200);
    }

    /**
     * BE-GRD-04: Akses class status ditolak untuk guru yang bukan wali kelas rombel tersebut.
     */
    public function test_be_grd_04_class_status_forbidden_for_other_teacher(): void
    {
        $roleTeacher = Role::where('code', 'teacher')->first();
        $otherTeacherUser = User::create([
            'username' => 'guru_lain',
            'email' => 'guru.lain@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $otherTeacherUser->roles()->attach($roleTeacher->id);

        Teacher::create([
            'user_id' => $otherTeacherUser->id,
            'nip' => '199901012020011001',
            'full_name' => 'Guru Lain, S.Pd',
            'status' => 'active',
        ]);

        $response = $this->actingAs($otherTeacherUser, 'sanctum')
            ->getJson("/api/v1/report-cards/class-status?class_id={$this->classA->id}&semester_id={$this->semester1->id}");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-GRD-04: Pratinjau data snapshot semester siswa on-the-fly tanpa menyimpan ke database.
     */
    public function test_be_grd_04_preview_semester_report_without_persisting(): void
    {
        $response = $this->actingAs($this->walasUser, 'sanctum')
            ->getJson("/api/v1/report-cards/preview?student_id={$this->student1->id}&semester_id={$this->semester1->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.student.id', $this->student1->id)
            ->assertJsonPath('data.student.full_name', 'Muhammad Danang')
            ->assertJsonPath('data.subjects.0.subject_code', 'MAT-10')
            ->assertJsonPath('data.subjects.0.final_score', 88)
            ->assertJsonPath('data.subjects.0.level', 'tuntas')
            ->assertJsonPath('data.attendance.hadir', 1)
            ->assertJsonPath('data.attendance.sakit', 1);

        // Pastikan tidak ada data yang tersimpan di tabel report_cards
        $this->assertDatabaseMissing('report_cards', [
            'student_id' => $this->student1->id,
            'semester_id' => $this->semester1->id,
        ]);
    }

    /**
     * BE-GRD-04: Pratinjau rapor semester ditolak jika diakses oleh guru yang bukan wali kelas siswa tersebut.
     */
    public function test_be_grd_04_preview_forbidden_for_unauthorized_teacher(): void
    {
        $roleTeacher = Role::where('code', 'teacher')->first();
        $otherTeacherUser = User::create([
            'username' => 'guru_asing_preview',
            'email' => 'guru.asing@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $otherTeacherUser->roles()->attach($roleTeacher->id);

        Teacher::create([
            'user_id' => $otherTeacherUser->id,
            'nip' => '199901012020011002',
            'full_name' => 'Guru Asing, S.Pd',
            'status' => 'active',
        ]);

        $response = $this->actingAs($otherTeacherUser, 'sanctum')
            ->getJson("/api/v1/report-cards/preview?student_id={$this->student1->id}&semester_id={$this->semester1->id}");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-GRD-04: Penguncian dan pembukaan kunci massal oleh Admin Kurikulum.
     */
    public function test_be_grd_04_bulk_lock_and_unlock_by_admin(): void
    {
        // 1. Generate rapor untuk kelas
        $this->actingAs($this->walasUser, 'sanctum')
            ->postJson('/api/v1/report-cards/generate', [
                'semester_id' => $this->semester1->id,
                'class_id' => $this->classA->id,
            ]);

        // 2. Kunci massal oleh admin
        $responseLock = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/report-cards/bulk-lock', [
                'class_id' => $this->classA->id,
                'semester_id' => $this->semester1->id,
                'is_locked' => true,
                'reason' => 'Batas akhir input nilai semester telah ditutup.',
            ]);

        $responseLock->assertStatus(200)
            ->assertJsonPath('data.count', 2)
            ->assertJsonPath('data.is_locked', true);

        $this->assertDatabaseHas('report_cards', [
            'student_id' => $this->student1->id,
            'status' => 'terkunci',
        ]);

        // 3. Buka kunci massal oleh admin
        $responseUnlock = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/report-cards/bulk-lock', [
                'class_id' => $this->classA->id,
                'semester_id' => $this->semester1->id,
                'is_locked' => false,
                'reason' => 'Perbaikan nilai susulan disetujui.',
            ]);

        $responseUnlock->assertStatus(200)
            ->assertJsonPath('data.count', 2)
            ->assertJsonPath('data.is_locked', false);

        $this->assertDatabaseHas('report_cards', [
            'student_id' => $this->student1->id,
            'status' => 'draft',
        ]);
    }

    /**
     * BE-GRD-04: Pengesahan dan penerbitan massal oleh Kepala Sekolah.
     */
    public function test_be_grd_04_bulk_approve_and_publish_by_principal(): void
    {
        // 1. Generate rapor untuk kelas
        $this->actingAs($this->walasUser, 'sanctum')
            ->postJson('/api/v1/report-cards/generate', [
                'semester_id' => $this->semester1->id,
                'class_id' => $this->classA->id,
            ]);

        // 2. ACC / Pengesahan massal oleh Kepala Sekolah
        $responseApprove = $this->actingAs($this->principalUser, 'sanctum')
            ->postJson('/api/v1/report-cards/bulk-approve', [
                'class_id' => $this->classA->id,
                'semester_id' => $this->semester1->id,
                'status' => 'disetujui',
                'note' => 'Seluruh capaian kompetensi semester telah valid dan terverifikasi.',
            ]);

        $responseApprove->assertStatus(200)
            ->assertJsonPath('data.count', 2)
            ->assertJsonPath('data.status', 'disetujui');

        $this->assertDatabaseHas('report_cards', [
            'student_id' => $this->student1->id,
            'status' => 'disetujui',
            'approved_by' => $this->principalUser->id,
        ]);

        // 3. Penerbitan massal ke siswa & orang tua
        $responsePublish = $this->actingAs($this->principalUser, 'sanctum')
            ->postJson('/api/v1/report-cards/bulk-approve', [
                'class_id' => $this->classA->id,
                'semester_id' => $this->semester1->id,
                'status' => 'terbit',
            ]);

        $responsePublish->assertStatus(200)
            ->assertJsonPath('data.count', 2)
            ->assertJsonPath('data.status', 'terbit');

        $this->assertDatabaseHas('report_cards', [
            'student_id' => $this->student1->id,
            'status' => 'terbit',
        ]);
    }

    /**
     * BE-GRD-04: Guru biasa tidak berwenang melakukan bulk approve.
     */
    public function test_be_grd_04_bulk_approve_forbidden_for_regular_teacher(): void
    {
        $response = $this->actingAs($this->walasUser, 'sanctum')
            ->postJson('/api/v1/report-cards/bulk-approve', [
                'class_id' => $this->classA->id,
                'semester_id' => $this->semester1->id,
                'status' => 'disetujui',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-GRD-04: Guru tidak berwenang men-generate rapor untuk rombel lain (IDOR check).
     */
    public function test_be_grd_04_generate_forbidden_for_non_homeroom_teacher(): void
    {
        $roleTeacher = Role::where('code', 'teacher')->first();
        $otherTeacherUser = User::create([
            'username' => 'guru_non_walas',
            'email' => 'guru.nonwalas@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $otherTeacherUser->roles()->attach($roleTeacher->id);

        Teacher::create([
            'user_id' => $otherTeacherUser->id,
            'nip' => '199901012020011003',
            'full_name' => 'Guru Non Walas, S.Pd',
            'status' => 'active',
        ]);

        $response = $this->actingAs($otherTeacherUser, 'sanctum')
            ->postJson('/api/v1/report-cards/generate', [
                'semester_id' => $this->semester1->id,
                'class_id' => $this->classA->id,
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }
}
