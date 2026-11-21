<?php

namespace Tests\Feature\Teacher;

use App\Models\AuditLog;
use App\Models\ClassStudent;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\TeacherDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherCounselingReferralFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $userMtk;

    protected Teacher $teacherMtk;

    protected SchoolClass $class7A;

    protected SchoolClass $class7B;

    protected Student $student7A;

    protected Student $student7B;

    protected function setUp(): void
    {
        parent::setUp();

        (new TeacherDemoSeeder)->run();

        $this->userMtk = User::where('username', 'guru.mtk.staging')->firstOrFail();
        $this->teacherMtk = Teacher::where('user_id', $this->userMtk->id)->firstOrFail();

        $this->class7A = SchoolClass::where('name', '7-A')->firstOrFail();
        $this->class7B = SchoolClass::where('name', '7-B')->firstOrFail();

        $this->student7A = $this->class7A->students()->firstOrFail();

        $userStudentB = User::create([
            'username' => 'siswa.7b.bk.test',
            'email' => 'siswa.7b.bk@smp-labum.sch.id',
            'password_hash' => bcrypt('Secret123!'),
            'status' => 'active',
        ]);
        $this->student7B = Student::create([
            'school_id' => $this->class7B->school_id ?? '00000000-0000-0000-0000-000000000001',
            'user_id' => $userStudentB->id,
            'nisn' => '9998887772',
            'nis' => '7B002',
            'nik_enc' => '3578010101019998',
            'full_name' => 'Fajar Pratama 7B',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-08-15',
            'status' => 'aktif',
            'entry_date' => '2026-07-01',
        ]);
        ClassStudent::create([
            'class_id' => $this->class7B->id,
            'student_id' => $this->student7B->id,
            'joined_at' => now(),
        ]);
    }

    /**
     * AC1: Guru dapat membuat rujukan BK untuk siswa yang diajarnya (happy path).
     */
    public function test_FIX_GURU_10_teacher_can_create_counseling_referral_happy_path(): void
    {
        $response = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/counseling-referrals', [
                'student_id' => $this->student7A->id,
                'reason' => 'Siswa sering terlihat murung dan menarik diri dari pergaulan kelas.',
                'priority' => 'segera',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.student_id', $this->student7A->id)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('counseling_referrals', [
            'student_id' => $this->student7A->id,
            'referred_by' => $this->userMtk->id,
            'status' => 'pending',
        ]);
    }

    /**
     * AC1: Rujukan BK yang telah dibuat dapat dibaca kembali via GET /me/teacher/counseling-referrals (persist).
     */
    public function test_FIX_GURU_10_teacher_can_list_counseling_referrals_persisted(): void
    {
        $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/counseling-referrals', [
                'student_id' => $this->student7A->id,
                'reason' => 'Perlu bimbingan pemilihan ekstrakurikuler dan penyesuaian diri.',
                'priority' => 'biasa',
            ])
            ->assertStatus(201);

        $listResponse = $this->actingAs($this->userMtk)
            ->getJson('/api/v1/me/teacher/counseling-referrals?class_id=' . $this->class7A->id);

        $listResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'student_id',
                        'reason',
                        'status',
                        'student' => ['id', 'full_name'],
                    ],
                ],
            ]);

        $this->assertNotEmpty($listResponse->json('data'));
        $this->assertEquals($this->student7A->id, $listResponse->json('data.0.student_id'));
    }

    /**
     * AC2: Validasi gagal jika alasan kurang dari 5 karakter.
     */
    public function test_FIX_GURU_10_validation_failure_for_short_reason(): void
    {
        $response = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/counseling-referrals', [
                'student_id' => $this->student7A->id,
                'reason' => 'abc',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    /**
     * AC2: Mencegah duplikasi rujukan pending yang identik untuk siswa yang sama.
     */
    public function test_FIX_GURU_10_duplicate_pending_referral_rejected(): void
    {
        $payload = [
            'student_id' => $this->student7A->id,
            'reason' => 'Terlambat masuk sekolah lebih dari 3 kali berturut-turut.',
            'priority' => 'biasa',
        ];

        // Pertama sukses
        $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/counseling-referrals', $payload)
            ->assertStatus(201);

        // Kedua ditolak
        $response = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/counseling-referrals', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');
    }

    /**
     * AC2: IDOR dicegah - guru tidak dapat membuat rujukan untuk siswa di luar wewenangnya.
     */
    public function test_FIX_GURU_10_idor_teacher_cannot_create_referral_for_foreign_student(): void
    {
        $response = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/counseling-referrals', [
                'student_id' => $this->student7B->id,
                'reason' => 'Mencoba merujuk siswa di luar kelas ajarnya.',
            ]);

        $response->assertStatus(403);
    }

    /**
     * AC3 (Privasi AGENTS.md §6.5): Alasan rujukan BK tidak ditulis mentah ke audit log.
     */
    public function test_FIX_GURU_10_privacy_audit_log_redacts_confidential_bk_reason(): void
    {
        $secretReason = 'Catatan pribadi konseling keluarga yang sangat rahasia.';

        $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/counseling-referrals', [
                'student_id' => $this->student7A->id,
                'reason' => $secretReason,
                'priority' => 'segera',
            ])
            ->assertStatus(201);

        $auditLog = AuditLog::where('action', 'teacher.counseling_referral.created')
            ->latest()
            ->firstOrFail();

        // Pastikan teks rahasia tidak pernah masuk ke audit_log
        $this->assertStringNotContainsString($secretReason, json_encode($auditLog->new_values));
        $this->assertEquals('[REDACTED_CONFIDENTIAL_BK]', $auditLog->new_values['reason']);
    }

    /**
     * AC4: Permintaan tanpa autentikasi menghasilkan 401.
     */
    public function test_FIX_GURU_10_unauthenticated_request_returns_401(): void
    {
        $this->postJson('/api/v1/me/teacher/counseling-referrals', [])
            ->assertStatus(401);

        $this->getJson('/api/v1/me/teacher/counseling-referrals')
            ->assertStatus(401);
    }
}
