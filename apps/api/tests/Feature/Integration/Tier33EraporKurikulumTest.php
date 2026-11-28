<?php

namespace Tests\Feature\Integration;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradeLevel;
use App\Models\GradePeriod;
use App\Models\Permission;
use App\Models\ProgressReport;
use App\Models\ReportCard;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\SiteSetting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Tier33EraporKurikulumTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $principalUser;

    private User $studentUser;

    private Student $student;

    private SchoolClass $classA;

    private Semester $semester;

    private GradePeriod $periodMonth;

    private Subject $longSubject;

    private GradeComponent $compHighest;

    private GradeComponent $compLowest;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $rolePrincipal = Role::firstOrCreate(['code' => 'principal'], ['name' => 'Kepala Sekolah', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $permGen = Permission::firstOrCreate(['code' => 'academic.report.generate'], ['description' => 'Generate rapor']);
        $permRead = Permission::firstOrCreate(['code' => 'academic.grade.read'], ['description' => 'Read nilai']);
        $permWrite = Permission::firstOrCreate(['code' => 'academic.grade.write'], ['description' => 'Write nilai']);

        $roleAdmin->permissions()->syncWithoutDetaching([$permGen->id, $permRead->id, $permWrite->id]);
        $rolePrincipal->permissions()->syncWithoutDetaching([$permGen->id, $permRead->id]);
        $roleStudent->permissions()->syncWithoutDetaching([$permRead->id]);

        $this->adminUser = User::create([
            'username' => 'admin_t33_'.uniqid(),
            'email' => 'admin_t33_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->principalUser = User::create([
            'username' => 'kepsek_t33_'.uniqid(),
            'email' => 'kepsek_t33_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->principalUser->roles()->attach($rolePrincipal->id);

        $walasUser = User::create([
            'username' => 'walas_t33_'.uniqid(),
            'email' => 'walas_t33_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $walas = Teacher::create([
            'user_id' => $walasUser->id,
            'nip' => '198801012015011009',
            'full_name' => 'Ibu Ratna Dewi, M.Pd',
            'status' => 'active',
        ]);

        $this->studentUser = User::create([
            'username' => 'siswa_t33_'.uniqid(),
            'email' => 'siswa_t33_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($roleStudent->id);

        SiteSetting::updateOrCreate(
            ['setting_key' => 'identity'],
            ['setting_value' => ['name' => 'SMP NEGERI LAB NUSANTARA 99', 'tagline' => 'Sekolah Penggerak Merdeka']]
        );
        SiteSetting::updateOrCreate(
            ['setting_key' => 'address'],
            ['setting_value' => ['street' => 'Jl. Pendidikan Fiktif No. 99, Kota Malang']]
        );
        SiteSetting::updateOrCreate(
            ['setting_key' => 'contact'],
            ['setting_value' => ['phone' => '(0341) 999888', 'email' => 'info@lab99.test.local']]
        );

        $ay = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
        ]);

        $this->semester = Semester::create([
            'academic_year_id' => $ay->id,
            'name' => 'Semester Ganjil 2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $this->periodMonth = GradePeriod::create([
            'semester_id' => $this->semester->id,
            'month' => 10,
            'status' => 'open',
        ]);

        $gradeLevel = GradeLevel::create([
            'name' => 'Kelas 8',
            'level' => 8,
        ]);

        $this->classA = SchoolClass::create([
            'academic_year_id' => $ay->id,
            'grade_level_id' => $gradeLevel->id,
            'homeroom_teacher_id' => $walas->id,
            'name' => 'VIII-Unggulan',
            'capacity' => 32,
        ]);

        $this->student = Student::create([
            'user_id' => $this->studentUser->id,
            'nis' => '202609901',
            'nisn' => '0098769901',
            'nik_enc' => 'enc_3578010101019901',
            'full_name' => 'Nadia Kusuma Wardhani',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2012-05-14',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        ClassStudent::create([
            'class_id' => $this->classA->id,
            'student_id' => $this->student->id,
            'joined_at' => Carbon::now()->subMonths(2),
        ]);

        // Nama mapel panjang > 25 karakter untuk menguji tidak ada pemotongan substr(0, 25)
        $this->longSubject = Subject::create([
            'code' => 'IPAS-08',
            'name' => 'Ilmu Pengetahuan Alam dan Sosial Terpadu',
            'subject_group' => 'Kelompok A',
            'kktp' => 80.0,
        ]);

        $this->compHighest = GradeComponent::create([
            'subject_id' => $this->longSubject->id,
            'semester_id' => $this->semester->id,
            'class_id' => $this->classA->id,
            'code' => 'TP-EKO',
            'name' => 'TP Ekosistem & Energi Terbarukan',
            'weight' => 50.00,
        ]);

        $this->compLowest = GradeComponent::create([
            'subject_id' => $this->longSubject->id,
            'semester_id' => $this->semester->id,
            'class_id' => $this->classA->id,
            'code' => 'TP-ZAT',
            'name' => 'TP Reaksi Kimia & Perubahan Zat',
            'weight' => 50.00,
        ]);
    }

    public function test_p1_15_kurikulum_merdeka_4_level_predicate_kktp_and_dynamic_placeholders(): void
    {
        // Simpan template 4-level Kurikulum Merdeka beserta placeholder dinamis & KKTP 80
        $tplRes = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/report-description-templates', [
                'subject_id' => $this->longSubject->id,
                'level' => 'cukup',
                'kktp_threshold' => 80,
                'template_text' => 'Ananda {nama_siswa} mencapai {predikat} (nilai {nilai_akhir}, KKTP {kktp}) pada {mata_pelajaran}. Menguasai {tp_tertinggi}, namun perlu penguatan {tp_terendah}.',
            ]);
        $tplRes->assertStatus(201);

        $tplBelow = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/report-description-templates', [
                'subject_id' => $this->longSubject->id,
                'level' => 'perlu_bimbingan',
                'kktp_threshold' => 80,
                'template_text' => 'Ananda {nama_siswa} berstatus {predikat} ({nilai_akhir} < KKTP {kktp}). Perlu bimbingan pada {tp_terendah}.',
            ]);
        $tplBelow->assertStatus(201);

        // Skenario 1: Nilai rata-rata = 78.0 -> Di bawah KKTP 80 ('perlu_bimbingan', level kompatibel 'belum')
        Grade::create([
            'student_id' => $this->student->id,
            'component_id' => $this->compHighest->id,
            'period_id' => $this->periodMonth->id,
            'value' => 84.00,
        ]);
        Grade::create([
            'student_id' => $this->student->id,
            'component_id' => $this->compLowest->id,
            'period_id' => $this->periodMonth->id,
            'value' => 72.00,
        ]);

        $previewBelow = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/report-cards/preview?student_id='.$this->student->id.'&semester_id='.$this->semester->id);

        $previewBelow->assertStatus(200);
        $subjectSnapBelow = $previewBelow->json('data.subjects.0');
        $this->assertEquals(78.0, (float) $subjectSnapBelow['final_score']);
        $this->assertEquals(80.0, (float) $subjectSnapBelow['kktp']);
        $this->assertSame('belum', $subjectSnapBelow['level']);
        $this->assertSame('perlu_bimbingan', $subjectSnapBelow['predicate_level']);
        $this->assertSame('Perlu Bimbingan', $subjectSnapBelow['predicate_label']);
        $this->assertStringContainsString('Nadia Kusuma Wardhani', $subjectSnapBelow['competency_description']);
        $this->assertStringContainsString('Perlu Bimbingan', $subjectSnapBelow['competency_description']);
        $this->assertStringContainsString('TP Reaksi Kimia & Perubahan Zat', $subjectSnapBelow['competency_description']);

        // Skenario 2: Perbarui nilai menjadi 86.0 dan 76.0 -> rata-rata = 81.0 (>= KKTP 80, rentang 80-89 => 'baik', level kompatibel 'tuntas')
        Grade::where('student_id', $this->student->id)
            ->where('component_id', $this->compHighest->id)
            ->update(['value' => 86.00]);
        Grade::where('student_id', $this->student->id)
            ->where('component_id', $this->compLowest->id)
            ->update(['value' => 76.00]);

        // Simpan juga template level 'baik'
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/report-description-templates', [
                'subject_id' => $this->longSubject->id,
                'level' => 'baik',
                'kktp_threshold' => 80,
                'template_text' => '{nama_siswa} berpredikat {predikat} ({nilai_akhir}/KKTP {kktp}) di {mata_pelajaran}. Unggul di {tp_tertinggi}, terus tingkatkan {tp_terendah}.',
            ])->assertStatus(201);

        $genRes = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/report-cards/generate', [
                'class_id' => $this->classA->id,
                'semester_id' => $this->semester->id,
                'kktp' => 80,
            ]);

        $genRes->assertStatus(201);
        $reportId = $genRes->json('data.id') ?? $genRes->json('data.0.id');
        $report = ReportCard::findOrFail($reportId);
        $subj = $report->data_snapshot['subjects'][0];

        $this->assertEquals(81.0, (float) $subj['final_score']);
        $this->assertSame('tuntas', $subj['level']);
        $this->assertSame('baik', $subj['predicate_level']);
        $this->assertSame('Baik', $subj['predicate_label']);
        $this->assertSame('TP Ekosistem & Energi Terbarukan', $subj['highest_topic']);
        $this->assertSame('TP Reaksi Kimia & Perubahan Zat', $subj['lowest_topic']);
        $this->assertStringContainsString('Nadia Kusuma Wardhani', $subj['competency_description']);
        $this->assertStringContainsString('Ilmu Pengetahuan Alam dan Sosial Terpadu', $subj['competency_description']);
        $this->assertStringContainsString('TP Ekosistem & Energi Terbarukan', $subj['competency_description']);
        $this->assertStringContainsString('TP Reaksi Kimia & Perubahan Zat', $subj['competency_description']);
    }

    public function test_p0_17_official_dompdf_qr_renders_full_utf8_site_setting_and_caches_immutable_file_id(): void
    {
        Storage::fake('local');

        Grade::create([
            'student_id' => $this->student->id,
            'component_id' => $this->compHighest->id,
            'period_id' => $this->periodMonth->id,
            'value' => 92.00,
        ]);
        Grade::create([
            'student_id' => $this->student->id,
            'component_id' => $this->compLowest->id,
            'period_id' => $this->periodMonth->id,
            'value' => 88.00,
        ]);

        // 1. Generate & sahkan E-Rapor Semester
        $genCard = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/report-cards/generate', [
                'class_id' => $this->classA->id,
                'semester_id' => $this->semester->id,
            ])->assertStatus(201);
        $reportCardId = $genCard->json('data.id') ?? $genCard->json('data.0.id');

        $this->actingAs($this->principalUser)
            ->patchJson("/api/v1/report-cards/{$reportCardId}/approve", ['status' => 'terbit'])
            ->assertStatus(200);

        $reportCard = ReportCard::findOrFail($reportCardId);
        $this->assertNull($reportCard->file_id);

        // Unduh pertama kali -> render DomPDF + QR & cache ke file_id
        $pdfRes1 = $this->actingAs($this->studentUser)
            ->get("/api/v1/report-cards/{$reportCardId}/pdf");

        $pdfRes1->assertStatus(200);
        $pdfRes1->assertHeader('content-type', 'application/pdf');
        $rawPdf1 = $pdfRes1->getContent();
        $this->assertStringStartsWith('%PDF-', $rawPdf1);
        $this->assertStringContainsString('SMP NEGERI LAB NUSANTARA 99', $rawPdf1);
        $this->assertStringContainsString('Ilmu Pengetahuan Alam', $rawPdf1);
        $this->assertStringContainsString('Sosial Terpadu', $rawPdf1);
        $this->assertStringContainsString($reportCard->verify_token, $rawPdf1);

        $reportCard->refresh();
        $this->assertNotNull($reportCard->file_id);
        $cachedFileId = $reportCard->file_id;
        Storage::disk('local')->assertExists($reportCard->file->object_key);

        // Unduh kedua kali -> harus memakai file_id tersimpan yang sama (immutable cache)
        $pdfRes2 = $this->actingAs($this->studentUser)
            ->get("/api/v1/report-cards/{$reportCardId}/pdf");
        $pdfRes2->assertStatus(200);
        $this->assertSame($rawPdf1, $pdfRes2->getContent());
        $reportCard->refresh();
        $this->assertSame($cachedFileId, $reportCard->file_id);

        // 2. Generate, sahkan, dan unduh E-Progress Report Bulanan
        $genProgress = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/progress-reports/generate', [
                'class_id' => $this->classA->id,
                'period_id' => $this->periodMonth->id,
            ])->assertStatus(201);
        $progressId = $genProgress->json('data.id') ?? $genProgress->json('data.0.id');

        $this->actingAs($this->principalUser)
            ->patchJson("/api/v1/progress-reports/{$progressId}/approve", ['status' => 'terbit'])
            ->assertStatus(200);

        $progress = ProgressReport::findOrFail($progressId);
        $progressPdf = $this->actingAs($this->studentUser)
            ->get("/api/v1/progress-reports/{$progressId}/pdf");

        $progressPdf->assertStatus(200);
        $progressPdf->assertHeader('content-type', 'application/pdf');
        $rawProgressPdf = $progressPdf->getContent();
        $this->assertStringStartsWith('%PDF-', $rawProgressPdf);
        $this->assertStringContainsString('SMP NEGERI LAB NUSANTARA 99', $rawProgressPdf);
        $this->assertStringContainsString('Ilmu Pengetahuan Alam', $rawProgressPdf);
        $this->assertStringContainsString('Sosial Terpadu', $rawProgressPdf);
        $this->assertStringContainsString($progress->verify_token, $rawProgressPdf);

        $progress->refresh();
        $this->assertNotNull($progress->file_id);
    }
}
