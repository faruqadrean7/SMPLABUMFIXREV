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
use App\Models\ProgressReport;
use App\Models\ReportCard;
use App\Models\ReportDescriptionTemplate;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AcademicIntegrationAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $principalUser;

    protected User $adminUser;

    protected User $walasUser;

    protected User $otherTeacherUser;

    protected User $student1User;

    protected User $student2User;

    protected User $guardian1User;

    protected User $guardian2User;

    protected Teacher $walas;

    protected Teacher $otherTeacher;

    protected Student $student1;

    protected Student $student2;

    protected SchoolClass $classA;

    protected SchoolClass $classB;

    protected AcademicYear $academicYear;

    protected Semester $semester1;

    protected GradePeriod $periodMonth8;

    protected Subject $subjectMath;

    protected Subject $subjectEnglish;

    protected GradeComponent $compMathFormatif;

    protected GradeComponent $compMathSumatif;

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
        $permAttendanceRead = Permission::firstOrCreate(['code' => 'academic.attendance.read'], ['description' => 'Melihat presensi']);

        $rolePrincipal->permissions()->syncWithoutDetaching([$permReportGen->id, $permGradeRead->id, $permAttendanceRead->id]);
        $roleAdmin->permissions()->syncWithoutDetaching([$permReportGen->id, $permGradeRead->id, $permGradeWrite->id, $permAttendanceRead->id]);
        $roleTeacher->permissions()->syncWithoutDetaching([$permGradeRead->id, $permGradeWrite->id, $permAttendanceRead->id]);
        $roleStudent->permissions()->syncWithoutDetaching([$permGradeRead->id, $permAttendanceRead->id]);
        $roleGuardian->permissions()->syncWithoutDetaching([$permGradeRead->id, $permAttendanceRead->id]);

        $this->principalUser = User::create([
            'username' => 'kepsek_audit',
            'email' => 'kepsek.audit@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->principalUser->roles()->attach($rolePrincipal->id);

        $this->adminUser = User::create([
            'username' => 'admin_audit',
            'email' => 'admin.audit@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->walasUser = User::create([
            'username' => 'walas_audit',
            'email' => 'walas.audit@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->walasUser->roles()->attach($roleTeacher->id);

        $this->otherTeacherUser = User::create([
            'username' => 'other_guru_audit',
            'email' => 'guru.audit@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->otherTeacherUser->roles()->attach($roleTeacher->id);

        $this->student1User = User::create([
            'username' => 'student1_audit',
            'email' => 'danang.audit@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->student1User->roles()->attach($roleStudent->id);

        $this->student2User = User::create([
            'username' => 'student2_audit',
            'email' => 'rina.audit@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->student2User->roles()->attach($roleStudent->id);

        $this->guardian1User = User::create([
            'username' => 'guardian1_audit',
            'email' => 'ortu.danang@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->guardian1User->roles()->attach($roleGuardian->id);

        $this->guardian2User = User::create([
            'username' => 'guardian2_audit',
            'email' => 'ortu.rina@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->guardian2User->roles()->attach($roleGuardian->id);

        // Academic Structure
        $this->academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
        ]);

        $this->semester1 = Semester::create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'Semester Ganjil 2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $this->periodMonth8 = GradePeriod::create([
            'semester_id' => $this->semester1->id,
            'month' => 8,
            'status' => 'open',
        ]);

        $this->walas = Teacher::create([
            'user_id' => $this->walasUser->id,
            'nip' => '198801012011011001',
            'full_name' => 'Budi Santoso, M.Pd',
            'status' => 'active',
        ]);

        $this->otherTeacher = Teacher::create([
            'user_id' => $this->otherTeacherUser->id,
            'nip' => '199001012015012002',
            'full_name' => 'Siti Nurhaliza, S.Pd',
            'status' => 'active',
        ]);

        $gradeLevel = GradeLevel::create([
            'name' => 'Kelas 10',
            'level' => 10,
        ]);

        $this->classA = SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $gradeLevel->id,
            'homeroom_teacher_id' => $this->walas->id,
            'name' => 'X-MIPA-1',
            'capacity' => 36,
        ]);

        $this->classB = SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $gradeLevel->id,
            'homeroom_teacher_id' => $this->otherTeacher->id,
            'name' => 'X-MIPA-2',
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

        // Link Guardians
        $guard1 = Guardian::create([
            'user_id' => $this->guardian1User->id,
            'full_name' => 'Bapak Danang',
            'phone' => '081234567891',
        ]);
        $guard1->students()->attach($this->student1->id, ['relationship' => 'father', 'is_primary' => true]);

        $guard2 = Guardian::create([
            'user_id' => $this->guardian2User->id,
            'full_name' => 'Ibu Rina',
            'phone' => '081234567892',
        ]);
        $guard2->students()->attach($this->student2->id, ['relationship' => 'mother', 'is_primary' => true]);

        // Subjects & Teaching Assignments
        $this->subjectMath = Subject::create([
            'code' => 'MAT-10',
            'name' => 'Matematika Peminatan',
            'subject_group' => 'Kelompok A',
        ]);

        $this->subjectEnglish = Subject::create([
            'code' => 'BIG-10',
            'name' => 'Bahasa Inggris',
            'subject_group' => 'Kelompok A',
        ]);

        TeachingAssignment::create([
            'teacher_id' => $this->walas->id,
            'subject_id' => $this->subjectMath->id,
            'class_id' => $this->classA->id,
            'academic_year_id' => $this->academicYear->id,
            'semester_id' => $this->semester1->id,
        ]);

        TeachingAssignment::create([
            'teacher_id' => $this->walas->id,
            'subject_id' => $this->subjectEnglish->id,
            'class_id' => $this->classA->id,
            'academic_year_id' => $this->academicYear->id,
            'semester_id' => $this->semester1->id,
        ]);

        // Components: Formatif (40%), Sumatif (60%)
        $this->compMathFormatif = GradeComponent::create([
            'subject_id' => $this->subjectMath->id,
            'semester_id' => $this->semester1->id,
            'class_id' => $this->classA->id,
            'period_id' => $this->periodMonth8->id,
            'code' => 'FMT-M8',
            'name' => 'Formatif Bulan 8',
            'weight' => 40.00,
        ]);

        $this->compMathSumatif = GradeComponent::create([
            'subject_id' => $this->subjectMath->id,
            'semester_id' => $this->semester1->id,
            'class_id' => $this->classA->id,
            'period_id' => $this->periodMonth8->id,
            'code' => 'SMT-M8',
            'name' => 'Sumatif Bulan 8',
            'weight' => 60.00,
        ]);

        // Description templates
        ReportDescriptionTemplate::create([
            'subject_id' => $this->subjectMath->id,
            'grade_level_id' => $gradeLevel->id,
            'semester_id' => $this->semester1->id,
            'level' => 'tuntas',
            'template_text' => 'Menunjukkan pemahaman sangat baik terhadap konsep aljabar dan kalkulus dasar.',
        ]);

        ReportDescriptionTemplate::create([
            'subject_id' => $this->subjectMath->id,
            'grade_level_id' => $gradeLevel->id,
            'semester_id' => $this->semester1->id,
            'level' => 'belum',
            'template_text' => 'Perlu bimbingan dan latihan lebih intensif dalam materi pecahan dan aljabar dasar.',
        ]);
    }

    /**
     * AUDIT END-TO-END: Alur lengkap data dari Buku Nilai -> Karakter -> E-Progress Report -> E-Rapor Semester.
     */
    public function test_be_grd_audit_complete_end_to_end_academic_pipeline(): void
    {
        // 1. INPUT NILAI (ADM-GRD-01)
        // Student 1: Formatif = 80 (weight 40), Sumatif = 90 (weight 60) -> Final = (3200 + 5400) / 100 = 86.00 (Tuntas >= 75.0)
        Grade::create([
            'component_id' => $this->compMathFormatif->id,
            'period_id' => $this->periodMonth8->id,
            'student_id' => $this->student1->id,
            'value' => 80.00,
            'source' => 'manual',
            'entered_by' => $this->walasUser->id,
        ]);
        Grade::create([
            'component_id' => $this->compMathSumatif->id,
            'period_id' => $this->periodMonth8->id,
            'student_id' => $this->student1->id,
            'value' => 90.00,
            'source' => 'manual',
            'entered_by' => $this->walasUser->id,
        ]);

        // Student 2: Formatif = 0.0 (explicit 0), Sumatif = 50.0 (weight 60) -> Final = (0 + 3000) / 100 = 30.00 (Belum tuntas < 75.0)
        Grade::create([
            'component_id' => $this->compMathFormatif->id,
            'period_id' => $this->periodMonth8->id,
            'student_id' => $this->student2->id,
            'value' => 0.00,
            'source' => 'manual',
            'entered_by' => $this->walasUser->id,
        ]);
        Grade::create([
            'component_id' => $this->compMathSumatif->id,
            'period_id' => $this->periodMonth8->id,
            'student_id' => $this->student2->id,
            'value' => 50.00,
            'source' => 'manual',
            'entered_by' => $this->walasUser->id,
        ]);

        // Verifikasi perhitungan matrix nilai
        $resMatrix = $this->actingAs($this->walasUser, 'sanctum')
            ->getJson("/api/v1/grades/matrix?class_id={$this->classA->id}&subject_id={$this->subjectMath->id}&period_id={$this->periodMonth8->id}");

        $resMatrix->assertStatus(200)
            ->assertJsonPath('data.summary.total_students', 2)
            ->assertJsonPath('data.summary.complete_count', 2)
            ->assertJsonPath('data.students.0.final_score', 86)
            ->assertJsonPath('data.students.1.final_score', 30);

        // 2. INPUT CATATAN KARAKTER 8 DIMENSI LENGKAP (ADM-GRD-02)
        $eightDimensions = [
            'beriman_bertakwa' => 'sangat_baik',
            'berkebinekaan_global' => 'baik',
            'gotong_royong' => 'baik',
            'mandiri' => 'sangat_baik',
            'bernalar_kritis' => 'sangat_baik',
            'kreatif' => 'baik',
            'kedisiplinan' => 'sangat_baik',
            'kejujuran' => 'sangat_baik',
        ];

        $resChar1 = $this->actingAs($this->walasUser, 'sanctum')
            ->postJson('/api/v1/character-notes', [
                'student_id' => $this->student1->id,
                'period_id' => $this->periodMonth8->id,
                'scale_data' => $eightDimensions,
                'homeroom_note' => 'Menunjukkan sikap terpuji dan konsistensi tinggi dalam pembelajaran.',
            ]);
        $resChar1->assertStatus(201);

        // Student 2: Belum lengkap (hanya 2 dimensi tanpa catatan walas)
        $resChar2 = $this->actingAs($this->walasUser, 'sanctum')
            ->postJson('/api/v1/character-notes', [
                'student_id' => $this->student2->id,
                'period_id' => $this->periodMonth8->id,
                'scale_data' => ['beriman_bertakwa' => 'baik', 'mandiri' => 'cukup'],
                'homeroom_note' => null,
            ]);
        $resChar2->assertStatus(201);

        // Periksa matrix karakter: Student 1 lengkap (8 dimensi), Student 2 belum_lengkap
        $resCharMatrix = $this->actingAs($this->walasUser, 'sanctum')
            ->getJson("/api/v1/character-notes/matrix?class_id={$this->classA->id}&period_id={$this->periodMonth8->id}");

        $resCharMatrix->assertStatus(200)
            ->assertJsonPath('data.students.0.completeness_status', 'lengkap')
            ->assertJsonPath('data.students.0.all_dimensions_complete', true)
            ->assertJsonPath('data.students.0.dimensions_count', 8)
            ->assertJsonPath('data.students.1.completeness_status', 'belum_lengkap')
            ->assertJsonPath('data.students.1.all_dimensions_complete', false)
            ->assertJsonPath('data.students.1.dimensions_count', 2);

        // 3. PRESENSI KBM (ADM-ACD-03)
        Attendance::create([
            'student_id' => $this->student1->id,
            'att_date' => '2026-08-03',
            'status' => 'hadir',
            'source' => 'manual',
            'recorded_by' => $this->walasUser->id,
        ]);
        Attendance::create([
            'student_id' => $this->student1->id,
            'att_date' => '2026-08-04',
            'status' => 'sakit',
            'source' => 'manual',
            'recorded_by' => $this->walasUser->id,
        ]);

        // 4. GENERATE E-PROGRESS REPORT BULANAN (ADM-GRD-03)
        $resGenMonthly = $this->actingAs($this->walasUser, 'sanctum')
            ->postJson('/api/v1/progress-reports/generate', [
                'period_id' => $this->periodMonth8->id,
                'student_id' => $this->student1->id,
                'status' => 'menunggu_acc',
            ]);

        $resGenMonthly->assertStatus(201)
            ->assertJsonPath('data.status', 'menunggu_acc')
            ->assertJsonPath('data.data_snapshot.grades.0.final_score', 86)
            ->assertJsonPath('data.data_snapshot.character.homeroom_note', 'Menunjukkan sikap terpuji dan konsistensi tinggi dalam pembelajaran.');

        $monthlyReportId = $resGenMonthly->json('data.id');

        // Walas / Non-Kepsek tidak boleh menyetujui rapor
        $resWalasApprove = $this->actingAs($this->walasUser, 'sanctum')
            ->patchJson("/api/v1/progress-reports/{$monthlyReportId}/approve", [
                'status' => 'disetujui',
            ]);
        $resWalasApprove->assertStatus(403);

        // Kepsek menyetujui rapor bulanan
        $resKepsekApprove = $this->actingAs($this->principalUser, 'sanctum')
            ->patchJson("/api/v1/progress-reports/{$monthlyReportId}/approve", [
                'status' => 'disetujui',
                'note' => 'Disetujui untuk diterbitkan kepada wali murid.',
            ]);
        $resKepsekApprove->assertStatus(200)
            ->assertJsonPath('data.status', 'disetujui');

        // Terbitkan rapor bulanan
        $resPublishMonthly = $this->actingAs($this->principalUser, 'sanctum')
            ->patchJson("/api/v1/progress-reports/{$monthlyReportId}/approve", [
                'status' => 'terbit',
            ]);
        $resPublishMonthly->assertStatus(200)
            ->assertJsonPath('data.status', 'terbit');

        $verifyTokenMonthly = $resPublishMonthly->json('data.verify_token');

        // Verifikasi publik via QR token (tidak membocorkan data rahasia)
        $resVerifyMonthly = $this->getJson("/api/v1/public/verify/{$verifyTokenMonthly}");
        $resVerifyMonthly->assertStatus(200)
            ->assertJsonPath('data.is_valid', true)
            ->assertJsonPath('data.student.name', 'Muhammad Danang')
            ->assertJsonMissingPath('data.grades'); // Non-sensitive

        // Download PDF Bulanan (valid PDF 1.4)
        $resPdfMonthly = $this->actingAs($this->student1User, 'sanctum')
            ->get("/api/v1/progress-reports/{$monthlyReportId}/pdf");

        $resPdfMonthly->assertStatus(200);
        $this->assertEquals('application/pdf', $resPdfMonthly->headers->get('Content-Type'));
        $pdfContentMonthly = $resPdfMonthly->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $pdfContentMonthly);
        $this->assertStringContainsString('E-PROGRESS REPORT BULANAN', $pdfContentMonthly);
        $this->assertStringContainsString($verifyTokenMonthly, $pdfContentMonthly);

        // 5. GENERATE E-RAPOR SEMESTER (ADM-GRD-04)
        // Ekstrakurikuler
        $ekskul = Extracurricular::create(['name' => 'Robotika', 'category' => 'Sains & Teknologi']);
        ExtracurricularMember::create([
            'extracurricular_id' => $ekskul->id,
            'student_id' => $this->student1->id,
            'academic_year_id' => $this->academicYear->id,
            'semester_id' => $this->semester1->id,
            'role' => 'Ketua Tim',
            'predicate' => 'A',
            'status' => 'aktif',
        ]);

        // Generate Rapor Semester untuk Student 1 (Tuntas)
        $resGenSem1 = $this->actingAs($this->walasUser, 'sanctum')
            ->postJson('/api/v1/report-cards/generate', [
                'semester_id' => $this->semester1->id,
                'student_id' => $this->student1->id,
                'homeroom_note' => 'Pertahankan prestasi akademik dan karakter unggul di jenjang berikutnya.',
            ]);

        $resGenSem1->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.data_snapshot.subjects.0.final_score', 86)
            ->assertJsonPath('data.data_snapshot.subjects.0.level', 'tuntas')
            ->assertJsonPath('data.data_snapshot.extracurriculars.0.name', 'Robotika');

        $sem1ReportId = $resGenSem1->json('data.id');

        // Generate Rapor Semester untuk Student 2 (Perlu Bimbingan / Belum Tuntas < 75.0)
        $resGenSem2 = $this->actingAs($this->walasUser, 'sanctum')
            ->postJson('/api/v1/report-cards/generate', [
                'semester_id' => $this->semester1->id,
                'student_id' => $this->student2->id,
                'homeroom_note' => 'Tingkatkan kedisiplinan belajar dan ikuti program perbaikan nilai.',
            ]);

        $resGenSem2->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.data_snapshot.subjects.0.final_score', 30)
            ->assertJsonPath('data.data_snapshot.subjects.0.level', 'belum');

        // Admin mengunci rapor semester
        $resLock = $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/v1/report-cards/{$sem1ReportId}/lock", [
                'is_locked' => true,
            ]);
        $resLock->assertStatus(200)
            ->assertJsonPath('data.status', 'terkunci');

        // Kepsek mengesahkan & menerbitkan rapor semester
        $resApproveSem = $this->actingAs($this->principalUser, 'sanctum')
            ->patchJson("/api/v1/report-cards/{$sem1ReportId}/approve", [
                'status' => 'disetujui',
            ]);
        $resApproveSem->assertStatus(200)
            ->assertJsonPath('data.status', 'disetujui');

        $resPublishSem = $this->actingAs($this->principalUser, 'sanctum')
            ->patchJson("/api/v1/report-cards/{$sem1ReportId}/approve", [
                'status' => 'terbit',
            ]);
        $resPublishSem->assertStatus(200)
            ->assertJsonPath('data.status', 'terbit');

        $verifyTokenSem = $resPublishSem->json('data.verify_token');

        // Verifikasi publik E-Rapor via QR token
        $resVerifySem = $this->getJson("/api/v1/public/verify/{$verifyTokenSem}");
        $resVerifySem->assertStatus(200)
            ->assertJsonPath('data.is_valid', true)
            ->assertJsonPath('data.student.name', 'Muhammad Danang')
            ->assertJsonMissingPath('data.subjects');

        // Download PDF E-Rapor Semester (multi-page PDF 1.4 dengan deskripsi utuh tanpa truncation)
        $resPdfSem = $this->actingAs($this->student1User, 'sanctum')
            ->get("/api/v1/report-cards/{$sem1ReportId}/pdf");

        $resPdfSem->assertStatus(200);
        $this->assertEquals('application/pdf', $resPdfSem->headers->get('Content-Type'));
        $pdfContentSem = $resPdfSem->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $pdfContentSem);
        $this->assertStringContainsString('LAPORAN HASIL BELAJAR', $pdfContentSem);
        $this->assertStringContainsString('Menunjukkan pemahaman sangat baik', $pdfContentSem);
        $this->assertStringContainsString($verifyTokenSem, $pdfContentSem);

        // 6. UJI INTEGRITAS DATA & IMMUTABILITAS SNAPSHOT
        // Bila nilai siswa atau data master di database diubah SETELAH rapor diterbitkan,
        // data rapor historis TIDAK BOLEH berubah!
        Grade::where('student_id', $this->student1->id)->update(['value' => 10.00]);
        $this->student1->update(['full_name' => 'Nama Yang Telah Dirubah']);

        $refreshedMonthly = ProgressReport::find($monthlyReportId);
        $this->assertEquals(86, $refreshedMonthly->data_snapshot['grades'][0]['final_score']);
        $this->assertEquals('Muhammad Danang', $refreshedMonthly->data_snapshot['student']['full_name']);

        $refreshedSemester = ReportCard::find($sem1ReportId);
        $this->assertEquals(86, $refreshedSemester->data_snapshot['subjects'][0]['final_score']);
        $this->assertEquals('Muhammad Danang', $refreshedSemester->data_snapshot['student']['full_name']);

        // Mencoba generate ulang rapor yang sudah disetujui/diterbitkan harus ditolak (422)
        $resRegenBlocked = $this->actingAs($this->walasUser, 'sanctum')
            ->postJson('/api/v1/report-cards/generate', [
                'semester_id' => $this->semester1->id,
                'student_id' => $this->student1->id,
            ]);
        $resRegenBlocked->assertStatus(422);
    }

    /**
     * AUDIT FORMULA: Perbedaan nilai 0.0 (angka sah) vs unentered (null), serta pembobotan & pembulatan 2 desimal.
     */
    public function test_be_grd_audit_grade_zero_vs_unentered_distinction_and_rounding(): void
    {
        // Komponen Bobot: 30% dan 70%
        $comp1 = GradeComponent::create([
            'subject_id' => $this->subjectEnglish->id,
            'semester_id' => $this->semester1->id,
            'class_id' => $this->classA->id,
            'period_id' => $this->periodMonth8->id,
            'code' => 'ING-1',
            'name' => 'Tugas 1',
            'weight' => 30.00,
        ]);
        $comp2 = GradeComponent::create([
            'subject_id' => $this->subjectEnglish->id,
            'semester_id' => $this->semester1->id,
            'class_id' => $this->classA->id,
            'period_id' => $this->periodMonth8->id,
            'code' => 'ING-2',
            'name' => 'Tugas 2',
            'weight' => 70.00,
        ]);

        // Student 1: 0.0 pada comp1, 0.0 pada comp2 -> skor akhir harus 0.0, bukan null
        Grade::create([
            'component_id' => $comp1->id,
            'period_id' => $this->periodMonth8->id,
            'student_id' => $this->student1->id,
            'value' => 0.00,
            'source' => 'manual',
            'entered_by' => $this->walasUser->id,
        ]);
        Grade::create([
            'component_id' => $comp2->id,
            'period_id' => $this->periodMonth8->id,
            'student_id' => $this->student1->id,
            'value' => 0.00,
            'source' => 'manual',
            'entered_by' => $this->walasUser->id,
        ]);

        // Student 2: belum diisi sama sekali (tidak ada row grade) -> final_score harus null
        // (tidak ada row grade untuk student 2)

        $resMatrix = $this->actingAs($this->walasUser, 'sanctum')
            ->getJson("/api/v1/grades/matrix?class_id={$this->classA->id}&subject_id={$this->subjectEnglish->id}&period_id={$this->periodMonth8->id}");

        $resMatrix->assertStatus(200)
            ->assertJsonPath('data.students.0.final_score', 0)
            ->assertJsonPath('data.students.0.is_complete', true)
            ->assertJsonPath('data.students.1.final_score', null)
            ->assertJsonPath('data.students.1.is_complete', false);

        // Uji rounding pembagian desimal berulang:
        // Bobot 30 dan 70: skor 85 pada comp1 (30), skor 87 pada comp2 (70) -> (2550 + 6090)/100 = 86.4
        // Mari uji 85.3333... : Comp1 (weight 30) = 85.00, Comp2 (weight 70) = 85.50 -> (2550 + 5985)/100 = 85.35
        Grade::where('student_id', $this->student1->id)->where('component_id', $comp1->id)->update(['value' => 85.00]);
        Grade::where('student_id', $this->student1->id)->where('component_id', $comp2->id)->update(['value' => 85.50]);

        $resMatrix2 = $this->actingAs($this->walasUser, 'sanctum')
            ->getJson("/api/v1/grades/matrix?class_id={$this->classA->id}&subject_id={$this->subjectEnglish->id}&period_id={$this->periodMonth8->id}");

        $resMatrix2->assertStatus(200)
            ->assertJsonPath('data.students.0.final_score', 85.35);
    }

    /**
     * AUDIT PERIODE LOCK: Periode nilai berstatus 'locked' mutlak menolak penambahan/pengubahan data (422).
     */
    public function test_be_grd_audit_locked_period_prevents_grade_and_character_mutation(): void
    {
        // Kunci periode bulan 8
        $this->periodMonth8->update(['status' => 'locked']);

        // 1. Coba input nilai pada periode terkunci
        $resStoreGrade = $this->actingAs($this->walasUser, 'sanctum')
            ->postJson('/api/v1/grades', [
                'component_id' => $this->compMathFormatif->id,
                'period_id' => $this->periodMonth8->id,
                'student_id' => $this->student1->id,
                'value' => 95.00,
            ]);

        $resStoreGrade->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');

        // 2. Coba input catatan karakter pada periode terkunci
        $resStoreChar = $this->actingAs($this->walasUser, 'sanctum')
            ->postJson('/api/v1/character-notes', [
                'student_id' => $this->student1->id,
                'period_id' => $this->periodMonth8->id,
                'scale_data' => ['beriman_bertakwa' => 'baik'],
            ]);

        $resStoreChar->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');
    }

    /**
     * AUDIT KEAMANAN RBAC & IDOR: Pembatasan ketat hak akses multi-peran dan multi-cakupan.
     */
    public function test_be_grd_audit_security_rbac_and_negative_idor_checks(): void
    {
        // 1. Guru non-walas tidak berhak mengakses matriks catatan karakter kelas A (403)
        $resNonWalasChar = $this->actingAs($this->otherTeacherUser, 'sanctum')
            ->getJson("/api/v1/character-notes/matrix?class_id={$this->classA->id}&period_id={$this->periodMonth8->id}");

        $resNonWalasChar->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // 2. Buat draft e-rapor semester untuk Student 1
        $reportDraft1 = ReportCard::create([
            'student_id' => $this->student1->id,
            'semester_id' => $this->semester1->id,
            'data_snapshot' => ['student' => ['full_name' => 'Danang']],
            'status' => 'draft',
            'verify_token' => 'tok-draft-danang-audit',
        ]);

        // Student 1 tidak boleh melihat rapornya sendiri jika masih draft (403)
        $resStudentDraft = $this->actingAs($this->student1User, 'sanctum')
            ->getJson("/api/v1/report-cards/{$reportDraft1->id}");

        $resStudentDraft->assertStatus(403);

        // Ortu Danang tidak boleh melihat rapor Danang jika masih draft (403)
        $resParentDraft = $this->actingAs($this->guardian1User, 'sanctum')
            ->getJson("/api/v1/report-cards/{$reportDraft1->id}");

        $resParentDraft->assertStatus(403);

        // Setelah rapor diterbitkan
        $reportDraft1->update(['status' => 'terbit', 'approved_by' => $this->principalUser->id]);

        // Student 1 dan Ortu Danang sekarang berhak melihat rapor Danang
        $resStudentOk = $this->actingAs($this->student1User, 'sanctum')
            ->getJson("/api/v1/report-cards/{$reportDraft1->id}");
        $resStudentOk->assertStatus(200);

        $resParentOk = $this->actingAs($this->guardian1User, 'sanctum')
            ->getJson("/api/v1/report-cards/{$reportDraft1->id}");
        $resParentOk->assertStatus(200);

        // 3. IDOR NEGATIVE: Ortu Rina (guardian2) mencoba melihat rapor Danang (403 FORBIDDEN)
        $resIdorParent = $this->actingAs($this->guardian2User, 'sanctum')
            ->getJson("/api/v1/report-cards/{$reportDraft1->id}");

        $resIdorParent->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // 4. IDOR NEGATIVE: Student 2 (Rina) mencoba melihat rapor Danang (403 FORBIDDEN)
        $resIdorStudent = $this->actingAs($this->student2User, 'sanctum')
            ->getJson("/api/v1/report-cards/{$reportDraft1->id}");

        $resIdorStudent->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // 5. Guru/Walas tidak berhak menyetujui atau menerbitkan e-rapor semester (403)
        $resWalasApprove = $this->actingAs($this->walasUser, 'sanctum')
            ->patchJson("/api/v1/report-cards/{$reportDraft1->id}/approve", [
                'status' => 'terbit',
            ]);

        $resWalasApprove->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * AUDIT PDF MULTI-PAGE: Dokumen dengan banyak mata pelajaran / catatan panjang menghasilkan PDF 1.4 multi-halaman tanpa pemotongan teks.
     */
    public function test_be_grd_audit_pdf_multi_page_support_and_no_truncation(): void
    {
        $manySubjects = [];
        for ($i = 1; $i <= 14; $i++) {
            $manySubjects[] = [
                'subject_code' => "MPL-{$i}",
                'subject_name' => "Mata Pelajaran Uji {$i}",
                'final_score' => 80 + ($i % 15),
                'level' => 'tuntas',
                'competency_description' => "Menunjukkan kompetensi yang sangat memuaskan dalam pemahaman konsep teori dan praktik modul ke-{$i} secara mandiri dan kolaboratif.",
            ];
        }

        $longHomeroomNote = 'Siswa menunjukkan dedikasi yang luar biasa sepanjang semester ganjil ini. Pertahankan semangat belajar, kejujuran, kepedulian sosial, serta kepemimpinan positif dalam setiap kegiatan akademik maupun non-akademik di sekolah.';

        $reportMultiPage = ReportCard::create([
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
                'subjects' => $manySubjects,
                'attendance' => ['hadir' => 90, 'sakit' => 2, 'izin' => 1, 'alpa' => 0],
                'extracurriculars' => [
                    ['name' => 'Pramuka', 'predicate' => 'A'],
                    ['name' => 'Robotika', 'predicate' => 'A'],
                ],
                'homeroom_note' => $longHomeroomNote,
            ],
            'status' => 'terbit',
            'verify_token' => 'token-multi-page-sem-test-999',
            'approved_by' => $this->principalUser->id,
        ]);

        $response = $this->actingAs($this->student1User, 'sanctum')
            ->get("/api/v1/report-cards/{$reportMultiPage->id}/pdf");

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $content = $response->getContent();

        // Verifikasi PDF 1.4
        $this->assertStringStartsWith('%PDF-1.4', $content);
        $this->assertStringContainsString('%%EOF', $content);

        // Verifikasi multi-halaman (/Count 2 atau lebih)
        $this->assertMatchesRegularExpression('/\/Type \/Pages \/Kids \[.*\] \/Count [2-9]/', $content);

        // Verifikasi semua mata pelajaran 1 s.d. 14 tercantum
        for ($i = 1; $i <= 14; $i++) {
            $this->assertStringContainsString("MPL-{$i}", $content);
        }

        // Verifikasi catatan walas lengkap tanpa pemotongan
        $this->assertStringContainsString('Siswa menunjukkan dedikasi yang luar biasa', $content);
        $this->assertStringContainsString('kegiatan akademik maupun', $content);
        $this->assertStringContainsString('non-akademik di sekolah.', $content);
    }
}
