<?php

namespace Tests\Feature\Integration;

use App\Models\AcademicYear;
use App\Models\Alumni;
use App\Models\ClassStudent;
use App\Models\ExchangeProgram;
use App\Models\File;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\Partnership;
use App\Models\PasswordReset;
use App\Models\Permission;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\RefreshToken;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TracerSurvey;
use App\Models\Trip;
use App\Models\TripParticipant;
use App\Models\User;
use App\Services\Rpt\DashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class Tier31SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $adminUser;

    private User $teacherUser;

    private Teacher $teacher;

    private User $studentUser1;

    private Student $student1;

    private User $studentUser2;

    private Student $student2;

    private User $guardianUser1;

    private Guardian $guardian1;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake('local');

        $this->school = School::create([
            'name' => 'SMP Lab Tier 3-1',
            'npsn' => '20534310',
            'address' => 'Jl. Fiktif T31 No. 1',
            'phone' => '0341000031',
            'email' => 't31@test.local',
        ]);

        $adminRole = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        foreach (['master.promotion.manage', 'cms.post.manage', 'cms.site.manage'] as $permCode) {
            $perm = Permission::firstOrCreate(['code' => $permCode], ['description' => $permCode]);
            $adminRole->permissions()->syncWithoutDetaching([$perm->id]);
        }

        $teacherRole = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $studentRole = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $parentRole = Role::firstOrCreate(['code' => 'parent'], ['name' => 'Orang Tua', 'is_system' => true]);

        $this->adminUser = User::create([
            'school_id' => $this->school->id,
            'username' => 'admin_t31_'.uniqid(),
            'email' => 'admin_t31_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($adminRole->id);

        $this->teacherUser = User::create([
            'school_id' => $this->school->id,
            'username' => 'guru_t31_'.uniqid(),
            'email' => 'guru_t31_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($teacherRole->id);

        $this->teacher = Teacher::create([
            'school_id' => $this->school->id,
            'user_id' => $this->teacherUser->id,
            'nip' => '198801012015011031',
            'full_name' => 'Guru Fiktif T31',
        ]);

        $this->studentUser1 = User::create([
            'school_id' => $this->school->id,
            'username' => 'siswa1_t31_'.uniqid(),
            'email' => 'siswa1_t31_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->studentUser1->roles()->attach($studentRole->id);

        $this->student1 = Student::create([
            'school_id' => $this->school->id,
            'user_id' => $this->studentUser1->id,
            'nis' => '31001',
            'nisn' => '0031000001',
            'nik_enc' => encrypt('3573010000000031'),
            'full_name' => 'Siswa Fiktif Satu',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2010-05-10',
            'entry_date' => '2025-07-15',
            'status' => 'aktif',
        ]);

        $this->studentUser2 = User::create([
            'school_id' => $this->school->id,
            'username' => 'siswa2_t31_'.uniqid(),
            'email' => 'siswa2_t31_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->studentUser2->roles()->attach($studentRole->id);

        $this->student2 = Student::create([
            'school_id' => $this->school->id,
            'user_id' => $this->studentUser2->id,
            'nis' => '31002',
            'nisn' => '0031000002',
            'nik_enc' => encrypt('3573010000000032'),
            'full_name' => 'Siswa Fiktif Dua',
            'gender' => 'P',
            'birth_place' => 'Surabaya',
            'birth_date' => '2010-08-20',
            'entry_date' => '2025-07-15',
            'status' => 'aktif',
        ]);

        $this->guardianUser1 = User::create([
            'school_id' => $this->school->id,
            'username' => 'ortu1_t31_'.uniqid(),
            'email' => 'ortu1_t31_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->guardianUser1->roles()->attach($parentRole->id);

        $this->guardian1 = Guardian::create([
            'user_id' => $this->guardianUser1->id,
            'full_name' => 'Wali Fiktif Satu',
            'phone' => '081200003101',
        ]);
        $this->guardian1->students()->attach($this->student1->id, ['relationship' => 'ayah', 'is_primary' => true]);
    }

    public function test_p1_29_trip_participants_isolated_by_student_and_guardian_and_no_synthetic_guardian(): void
    {
        $trip = Trip::create([
            'school_id' => $this->school->id,
            'title' => 'Study Tour Museum Fiktif',
            'destination' => 'Malang',
            'depart_date' => '2026-12-01',
            'return_date' => '2026-12-02',
            'status' => 'terjadwal',
        ]);

        $p1 = TripParticipant::create([
            'trip_id' => $trip->id,
            'student_id' => $this->student1->id,
            'status' => 'terdaftar',
            'notes' => 'Alergi kacang siswa satu',
        ]);
        TripParticipant::create([
            'trip_id' => $trip->id,
            'student_id' => $this->student2->id,
            'status' => 'terdaftar',
            'notes' => 'Catatan medis rahasia siswa dua',
        ]);

        // Student 1 sees only self on show & participants
        $showRes = $this->actingAs($this->studentUser1)->getJson("/api/v1/trips/{$trip->id}");
        $showRes->assertStatus(200);
        $this->assertCount(1, $showRes->json('data.participants'));
        $this->assertEquals($this->student1->id, $showRes->json('data.participants.0.student_id'));

        $listRes = $this->actingAs($this->studentUser1)->getJson("/api/v1/trips/{$trip->id}/participants");
        $listRes->assertStatus(200);
        $this->assertCount(1, $listRes->json('data'));
        $this->assertEquals($this->student1->id, $listRes->json('data.0.student_id'));

        // Guardian 1 sees only child (student 1)
        $guardianList = $this->actingAs($this->guardianUser1)->getJson("/api/v1/trips/{$trip->id}/participants");
        $guardianList->assertStatus(200);
        $this->assertCount(1, $guardianList->json('data'));
        $this->assertEquals($this->student1->id, $guardianList->json('data.0.student_id'));

        // Admin submitting consent for student2 (who has NO registered guardian) must fail 422 and NOT create synthetic Guardian
        $guardianCountBefore = Guardian::count();
        $this->actingAs($this->adminUser)
            ->postJson("/api/v1/trips/{$trip->id}/consents", [
                'student_id' => $this->student2->id,
                'decision' => 'setuju',
            ])
            ->assertStatus(422);
        $this->assertEquals($guardianCountBefore, Guardian::count());

        // Admin submitting consent for student1 uses guardian1 without creating new Guardian rows
        $this->actingAs($this->adminUser)
            ->postJson("/api/v1/trips/{$trip->id}/consents", [
                'student_id' => $this->student1->id,
                'decision' => 'setuju',
                'notes' => 'Disetujui via wali',
            ])
            ->assertStatus(201);
        $this->assertEquals($guardianCountBefore, Guardian::count());
        $this->assertDatabaseHas('trip_consents', [
            'trip_participant_id' => $p1->id,
            'guardian_id' => $this->guardian1->id,
            'decision' => 'setuju',
        ]);
    }

    public function test_p1_30_relations_route_rbac_and_graduation_auto_creates_alumni(): void
    {
        $partnership = Partnership::create([
            'school_id' => $this->school->id,
            'partner_name' => 'Universitas Fiktif',
            'mou_number' => 'MOU-T31-001',
            'partner_type' => 'kampus',
            'contact_person' => 'Humas Mitra',
            'scope' => 'Pertukaran pelajar & riset',
            'valid_from' => '2026-01-01',
            'valid_until' => '2027-12-31',
            'status' => 'aktif',
        ]);

        $exchange = ExchangeProgram::create([
            'partnership_id' => $partnership->id,
            'name' => 'Student Exchange Fiktif',
            'type' => 'outbound',
            'destination_country' => 'Jepang',
            'destination_institution' => 'Partner School',
            'quota' => 5,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-10',
            'status' => 'pendaftaran',
        ]);

        $survey = TracerSurvey::create([
            'school_id' => $this->school->id,
            'title' => 'Tracer Study 2026',
            'questions' => [['key' => 'status', 'label' => 'Status saat ini']],
            'target_graduation_year' => 2026,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
            'created_by' => $this->adminUser->id,
        ]);

        // Student is blocked from admin/humas routes at middleware level
        $this->actingAs($this->studentUser1)->getJson('/api/v1/partnerships')->assertStatus(403);
        $this->actingAs($this->studentUser1)->getJson("/api/v1/exchange-programs/{$exchange->id}/participants")->assertStatus(403);
        $this->actingAs($this->studentUser1)->getJson("/api/v1/tracer-surveys/{$survey->id}/stats")->assertStatus(403);

        // Student retains legitimate access to masked alumni directory and active tracer surveys
        Alumni::create([
            'student_id' => $this->student2->id,
            'graduation_year' => 2026,
            'current_status' => 'kuliah',
            'phone' => '081234567890',
            'email' => 'alumni2@test.local',
            'is_public' => true,
        ]);
        $this->actingAs($this->studentUser1)->getJson('/api/v1/alumni')->assertStatus(200);
        $this->actingAs($this->studentUser1)->getJson('/api/v1/tracer-surveys')->assertStatus(200);

        // Graduation automatically creates Alumni record for student1
        $year = AcademicYear::create([
            'school_id' => $this->school->id,
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);
        $grade9 = GradeLevel::create([
            'school_id' => $this->school->id,
            'name' => 'Kelas 9',
            'sort_order' => 9,
        ]);
        $class9A = SchoolClass::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $year->id,
            'grade_level_id' => $grade9->id,
            'name' => '9A',
            'capacity' => 32,
        ]);
        ClassStudent::create([
            'class_id' => $class9A->id,
            'student_id' => $this->student1->id,
            'joined_at' => '2026-07-01',
        ]);

        $promoRes = $this->actingAs($this->adminUser)->postJson('/api/v1/promotions/execute', [
            'source_academic_year_id' => $year->id,
            'effective_date' => '2026-11-28',
            'promotions' => [
                [
                    'student_id' => $this->student1->id,
                    'current_class_id' => $class9A->id,
                    'action' => 'graduate',
                    'notes' => 'Lulus Ujian Akhir',
                ],
            ],
        ]);
        $promoRes->assertStatus(201);

        $this->assertDatabaseHas('students', [
            'id' => $this->student1->id,
            'status' => 'lulus',
        ]);
        $this->assertDatabaseHas('alumni', [
            'student_id' => $this->student1->id,
            'graduation_year' => 2026,
            'is_public' => false,
        ]);
    }

    public function test_p1_31_guest_signature_token_mime_sniffing_private_photo_and_post_xss_and_nullable_clear(): void
    {
        // Private photo_file_id must be rejected on public guest registration
        $privateFile = File::create([
            'school_id' => $this->school->id,
            'bucket' => 'private-docs',
            'object_key' => 'docs/secret.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 128,
            'visibility' => 'internal',
        ]);

        $this->postJson('/api/v1/public/guests', [
            'full_name' => 'Tamu Uji Privasi',
            'phone' => '081234567899',
            'purpose' => 'Bertemu Kepala Sekolah',
            'photo_file_id' => $privateFile->id,
        ])->assertStatus(403);

        // Legitimate registration issues a one-time signature_token
        $regRes = $this->postJson('/api/v1/public/guests', [
            'full_name' => 'Tamu Uji Resmi',
            'phone' => '081234567898',
            'purpose' => 'Kunjungan Akademik',
            'target_user_id' => $this->teacherUser->id,
        ]);
        $regRes->assertStatus(201);
        $guestId = $regRes->json('data.id');
        $signatureToken = $regRes->json('data.signature_token');
        $this->assertNotEmpty($signatureToken);

        $validPngData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

        // Missing or wrong signature_token -> 403
        $this->postJson("/api/v1/public/guests/{$guestId}/signature", [
            'signature_data' => $validPngData,
        ])->assertStatus(403);

        $this->postJson("/api/v1/public/guests/{$guestId}/signature", [
            'signature_token' => 'wrong-token-value',
            'signature_data' => $validPngData,
        ])->assertStatus(403);

        // SVG disguised as base64 image -> 422 (finfo MIME check rejects non-raster image)
        $svgBase64 = 'data:image/png;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->postJson("/api/v1/public/guests/{$guestId}/signature", [
            'signature_token' => $signatureToken,
            'signature_data' => $svgBase64,
        ])->assertStatus(422);

        // Valid signature + valid token -> 201, and replay with same token -> 403
        $this->postJson("/api/v1/public/guests/{$guestId}/signature", [
            'signature_token' => $signatureToken,
            'signature_data' => $validPngData,
        ])->assertStatus(201);

        $this->postJson("/api/v1/public/guests/{$guestId}/signature", [
            'signature_token' => $signatureToken,
            'signature_data' => $validPngData,
        ])->assertStatus(403);

        // Post XSS sanitization + clearing nullable category_id / cover_file_id on PUT
        $category = PostCategory::create([
            'school_id' => $this->school->id,
            'name' => 'Berita',
            'slug' => 'berita-t31',
        ]);
        $cover = File::create([
            'school_id' => $this->school->id,
            'bucket' => 'cms',
            'object_key' => 'covers/t31.png',
            'mime_type' => 'image/png',
            'size_bytes' => 100,
            'visibility' => 'publik',
        ]);

        $createPostRes = $this->actingAs($this->adminUser)->postJson('/api/v1/posts', [
            'title' => 'Berita Keamanan XSS',
            'content' => '<p>Aman</p><script>alert("xss")</script><img src="x" onerror="alert(2)"><a href="javascript:alert(3)">Klik</a>',
            'category_id' => $category->id,
            'cover_file_id' => $cover->id,
            'status' => Post::STATUS_DRAFT,
            'visibility' => Post::VISIBILITY_PUBLIC,
        ]);
        $createPostRes->assertStatus(201);
        $postId = $createPostRes->json('data.id');
        $savedContent = $createPostRes->json('data.content');
        $this->assertStringNotContainsString('<script', $savedContent);
        $this->assertStringNotContainsString('onerror=', $savedContent);
        $this->assertStringNotContainsString('javascript:', $savedContent);
        $this->assertStringContainsString('<p>Aman</p>', $savedContent);

        $updatePostRes = $this->actingAs($this->adminUser)->putJson("/api/v1/posts/{$postId}", [
            'category_id' => null,
            'cover_file_id' => null,
            'content' => '<p>Diperbarui</p><iframe src="https://evil.test"></iframe>',
        ]);
        $updatePostRes->assertStatus(200);
        $this->assertNull($updatePostRes->json('data.category_id'));
        $this->assertNull($updatePostRes->json('data.cover_file_id'));
        $this->assertStringNotContainsString('<iframe', $updatePostRes->json('data.content'));
    }

    public function test_p1_45_security_headers_no_api_user_route_prune_tokens_and_dashboard_cache(): void
    {
        // Boilerplate /api/user removed & security headers attached on API responses
        $res = $this->actingAs($this->adminUser)->getJson('/api/user');
        $res->assertStatus(404);
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $res->assertHeader('X-Frame-Options', 'DENY');
        $res->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $res->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Expired refresh_tokens & password_resets pruned while active tokens remain
        $expiredRefresh = RefreshToken::create([
            'user_id' => $this->studentUser1->id,
            'token_hash' => Str::random(64),
            'expires_at' => Carbon::now()->subDay(),
        ]);
        $activeRefresh = RefreshToken::create([
            'user_id' => $this->studentUser1->id,
            'token_hash' => Str::random(64),
            'expires_at' => Carbon::now()->addDay(),
        ]);
        $expiredReset = PasswordReset::create([
            'user_id' => $this->studentUser1->id,
            'token_hash' => Str::random(64),
            'expires_at' => Carbon::now()->subHour(),
        ]);
        $activeReset = PasswordReset::create([
            'user_id' => $this->studentUser1->id,
            'token_hash' => Str::random(64),
            'expires_at' => Carbon::now()->addHour(),
        ]);

        $this->artisan('auth:prune-tokens')->assertExitCode(0);

        $this->assertDatabaseMissing('refresh_tokens', ['id' => $expiredRefresh->id]);
        $this->assertDatabaseHas('refresh_tokens', ['id' => $activeRefresh->id]);
        $this->assertDatabaseMissing('password_resets', ['id' => $expiredReset->id]);
        $this->assertDatabaseHas('password_resets', ['id' => $activeReset->id]);

        // Short-TTL dashboard caching for teacher, student, guardian
        $dashboardService = app(DashboardService::class);
        $todayStr = Carbon::today()->toDateString();

        $teacherReq = Request::create('/api/v1/dashboards/teacher', 'GET');
        $teacherReq->setUserResolver(fn () => $this->teacherUser);
        $dashboardService->getTeacherDashboard($teacherReq);
        $this->assertTrue(Cache::has("rpt:teacher-dashboard:{$this->teacherUser->id}:{$this->teacher->id}:{$todayStr}"));

        $studentReq = Request::create('/api/v1/dashboards/student', 'GET');
        $studentReq->setUserResolver(fn () => $this->studentUser1);
        $dashboardService->getStudentDashboard($studentReq);
        $this->assertTrue(Cache::has("rpt:student-dashboard:{$this->studentUser1->id}:{$this->student1->id}:{$todayStr}"));

        $guardianReq = Request::create('/api/v1/dashboards/guardian', 'GET');
        $guardianReq->setUserResolver(fn () => $this->guardianUser1);
        $dashboardService->getGuardianDashboard($this->student1->id, $guardianReq);
        $this->assertTrue(Cache::has("rpt:guardian-dashboard:{$this->guardianUser1->id}:{$this->student1->id}:{$todayStr}"));
    }
}
