<?php

namespace Tests\Feature\Teacher;

use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradePeriod;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\TeacherDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherReportCardLifecycleFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $userWalas;

    protected Teacher $teacherWalas;

    protected User $userMtk;

    protected Teacher $teacherMtk;

    protected SchoolClass $class7A;

    protected Student $student7A;

    protected Semester $semesterGanjil;

    protected GradePeriod $period;

    protected GradeComponent $component;

    protected function setUp(): void
    {
        parent::setUp();

        (new TeacherDemoSeeder)->run();

        $this->userWalas = User::where('username', 'walas.7a.staging')->firstOrFail();
        $this->teacherWalas = Teacher::where('user_id', $this->userWalas->id)->firstOrFail();

        $this->userMtk = User::where('username', 'guru.mtk.staging')->firstOrFail();
        $this->teacherMtk = Teacher::where('user_id', $this->userMtk->id)->firstOrFail();

        $this->class7A = SchoolClass::where('name', '7-A')->firstOrFail();
        $this->student7A = Student::whereHas('classes', fn ($q) => $q->where('classes.id', $this->class7A->id))->firstOrFail();

        $this->semesterGanjil = Semester::where('name', 'Ganjil')->firstOrFail();

        // Pastikan ada komponen nilai dan nilai untuk siswa ini
        $this->component = GradeComponent::firstOrCreate(
            [
                'subject_id' => $this->teacherMtk->teachingAssignments()->first()->subject_id,
                'semester_id' => $this->semesterGanjil->id,
                'code' => 'MTK-UAT-01',
            ],
            [
                'name' => 'Formatif MTK UAT',
                'weight' => 30,
            ]
        );

        // Ambil open period dari seeder
        $this->period = GradePeriod::where('semester_id', $this->semesterGanjil->id)
            ->where('status', 'open')
            ->firstOrFail();

        Grade::updateOrCreate(
            [
                'student_id' => $this->student7A->id,
                'component_id' => $this->component->id,
                'period_id' => $this->period->id,
            ],
            [
                'value' => 85.0,
                'source' => 'manual',
                'entered_by' => $this->userMtk->id,
            ]
        );
    }

    /**
     * C. Rapor (wali kelas) - Full Lifecycle:
     * Draf -> Pratinjau -> Terbitkan ditolak bila periode open ->
     * Terbitkan sukses bila periode locked -> Tidak bisa terbitkan dua kali ->
     * Snapshot JSONB beku (ubah nilai master, isi rapor tidak berubah) ->
     * QR /public/verify/{token} valid -> PDF terunduh.
     */
    public function test_homeroom_teacher_report_card_lifecycle(): void
    {
        // 1. Walas buat draf rapor siswa binaannya
        $draftRes = $this->actingAs($this->userWalas)
            ->postJson('/api/v1/report-cards/generate', [
                'semester_id' => $this->semesterGanjil->id,
                'student_id' => $this->student7A->id,
                'status' => 'draft',
                'homeroom_note' => 'Pertahankan prestasi belajar dan keaktifan di kelas.',
            ]);

        $draftRes->assertStatus(201);
        $reportId = $draftRes->json('data.id');
        $this->assertNotEmpty($reportId);
        $this->assertEquals('draft', $draftRes->json('data.status'));

        // 2. Walas pratinjau rapor semester
        $previewRes = $this->actingAs($this->userWalas)
            ->getJson("/api/v1/report-cards/preview?student_id={$this->student7A->id}&semester_id={$this->semesterGanjil->id}");

        $previewRes->assertOk();
        $this->assertEquals($this->student7A->id, $previewRes->json('data.student.id'));

        // 3. Walas coba terbitkan saat periode nilai MASIH TERBUKA (open) -> ditolak 422
        $publishWhenOpenRes = $this->actingAs($this->userWalas)
            ->patchJson("/api/v1/report-cards/{$reportId}/approve", [
                'status' => 'terbit',
                'note' => 'Penerbitan rapor semester',
            ]);
        $publishWhenOpenRes->assertStatus(422);

        // 4. Kunci seluruh periode penilaian semester ini
        GradePeriod::where('semester_id', $this->semesterGanjil->id)->update(['status' => 'locked']);

        // 5. Walas terbitkan rapor saat periode nilai TERKUNCI (locked) -> sukses 200
        $publishRes = $this->actingAs($this->userWalas)
            ->patchJson("/api/v1/report-cards/{$reportId}/approve", [
                'status' => 'terbit',
                'note' => 'Penerbitan resmi rapor semester ganjil',
            ]);
        $publishRes->assertOk();
        $this->assertEquals('terbit', $publishRes->json('data.status'));
        $verifyToken = $publishRes->json('data.verify_token');
        $this->assertNotEmpty($verifyToken);

        $initialSnapshot = $publishRes->json('data.data_snapshot');
        $initialMtkScore = null;
        foreach ($initialSnapshot['subjects'] ?? [] as $item) {
            if (($item['code'] ?? '') === 'MTK-7' || str_contains($item['subject_name'] ?? '', 'Matematika')) {
                $initialMtkScore = $item['final_score'];
                break;
            }
        }
        $this->assertNotNull($initialMtkScore);

        // 6. Rapor tidak dapat diterbitkan dua kali -> ditolak 422
        $publishTwiceRes = $this->actingAs($this->userWalas)
            ->patchJson("/api/v1/report-cards/{$reportId}/approve", [
                'status' => 'terbit',
                'note' => 'Coba terbitkan ulang',
            ]);
        $publishTwiceRes->assertStatus(422);

        // 7. Bukti Snapshot JSONB Beku: ubah nilai master di tabel grades
        Grade::where('student_id', $this->student7A->id)
            ->where('component_id', $this->component->id)
            ->update(['value' => 99.0]);

        $detailRes = $this->actingAs($this->userWalas)
            ->getJson("/api/v1/report-cards/{$reportId}");
        $detailRes->assertOk();

        // Nilai pada snapshot beku tetap sama dengan initialMtkScore, BUKAN 99
        $snapshot = $detailRes->json('data.data_snapshot');
        $this->assertNotNull($snapshot);
        $subjectItems = $snapshot['subjects'] ?? [];
        $foundMtk = false;
        foreach ($subjectItems as $item) {
            if (($item['code'] ?? '') === 'MTK-7' || str_contains($item['subject_name'] ?? '', 'Matematika')) {
                $foundMtk = true;
                $this->assertEquals($initialMtkScore, $item['final_score'], 'Snapshot nilai beku tidak boleh berubah');
                $this->assertNotEquals(99.0, $item['final_score']);
            }
        }
        $this->assertTrue($foundMtk, 'Mata pelajaran matematika harus ditemukan pada snapshot rapor');

        // 8. Verifikasi Dokumen Publik QR via /public/verify/{token} (tanpa auth)
        $verifyRes = $this->getJson("/api/v1/public/verify/{$verifyToken}");
        $verifyRes->assertOk();
        $this->assertTrue($verifyRes->json('data.is_valid'));
        $this->assertEquals('terbit', $verifyRes->json('data.status'));
        $this->assertEquals($verifyToken, $verifyRes->json('data.verification_token'));

        // 9. Unduh PDF E-Rapor bertanda tangan QR
        $pdfRes = $this->actingAs($this->userWalas)
            ->get("/api/v1/report-cards/{$reportId}/pdf");
        $pdfRes->assertOk();
        $this->assertStringStartsWith('%PDF', $pdfRes->getContent());
        $this->assertEquals('application/pdf', $pdfRes->headers->get('Content-Type'));
    }

    /**
     * Guru biasa (non-walas) harus mendapat 403 Forbidden di setiap langkah siklus hidup rapor.
     */
    public function test_regular_teacher_forbidden_at_all_report_card_steps(): void
    {
        // Setup satu rapor draft milik siswa kelas 7-A
        $report = ReportCard::create([
            'student_id' => $this->student7A->id,
            'semester_id' => $this->semesterGanjil->id,
            'status' => 'draft',
            'verify_token' => 'token-test-security-idor-403',
            'data_snapshot' => ['student' => ['id' => $this->student7A->id]],
        ]);

        // 1. Guru biasa pratinjau rapor siswa bukan binaannya -> 403
        $previewRes = $this->actingAs($this->userMtk)
            ->getJson("/api/v1/report-cards/preview?student_id={$this->student7A->id}&semester_id={$this->semesterGanjil->id}");
        $previewRes->assertStatus(403);

        // 2. Guru biasa generate / simpan draf rapor siswa bukan binaannya -> 403
        $generateRes = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/report-cards/generate', [
                'semester_id' => $this->semesterGanjil->id,
                'student_id' => $this->student7A->id,
                'status' => 'draft',
            ]);
        $generateRes->assertStatus(403);

        // 3. Guru biasa mengesahkan / menerbitkan rapor siswa bukan binaannya -> 403
        $approveRes = $this->actingAs($this->userMtk)
            ->patchJson("/api/v1/report-cards/{$report->id}/approve", [
                'status' => 'terbit',
            ]);
        $approveRes->assertStatus(403);

        // 4. Guru biasa mengunduh PDF rapor siswa bukan binaannya -> 403
        $pdfRes = $this->actingAs($this->userMtk)
            ->get("/api/v1/report-cards/{$report->id}/pdf");
        $pdfRes->assertStatus(403);
    }
}
