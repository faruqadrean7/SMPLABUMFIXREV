<?php

namespace Tests\Feature\Bk;

use App\Casts\SafeEncryptedCast;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\ClassStudent;
use App\Models\Complaint;
use App\Models\CounselingCase;
use App\Models\CounselingReferral;
use App\Models\CounselingRequest;
use App\Models\CounselingSession;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class BkAndComplaintPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected User $counselorUser;

    protected User $superAdminUser;

    protected User $adminTuUser;

    protected User $principalUser;

    protected User $homeroomTeacherUser;

    protected User $unrelatedTeacherUser;

    protected User $librarianUser;

    protected User $studentUser;

    protected User $guardianUser;

    protected Student $student;

    protected SchoolClass $classA;

    protected function setUp(): void
    {
        parent::setUp();

        $roleCounselor = Role::firstOrCreate(['code' => 'counselor'], ['name' => 'Guru BK', 'is_system' => true]);
        $roleSuperAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleAdminTu = Role::firstOrCreate(['code' => 'admin_tu'], ['name' => 'Admin TU', 'is_system' => true]);
        $rolePrincipal = Role::firstOrCreate(['code' => 'principal'], ['name' => 'Kepala Sekolah', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleLibrarian = Role::firstOrCreate(['code' => 'librarian'], ['name' => 'Pustakawan', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Wali Murid', 'is_system' => true]);

        $permBk = Permission::firstOrCreate(['code' => 'bk.counseling.manage'], ['description' => 'Kelola BK']);
        $permComplaintRead = Permission::firstOrCreate(['code' => 'complaint.read'], ['description' => 'Baca pengaduan']);
        $permComplaintManage = Permission::firstOrCreate(['code' => 'complaint.manage'], ['description' => 'Kelola pengaduan']);

        $roleCounselor->permissions()->syncWithoutDetaching([$permBk->id, $permComplaintRead->id, $permComplaintManage->id]);
        $rolePrincipal->permissions()->syncWithoutDetaching([$permComplaintRead->id, $permComplaintManage->id]);

        $this->counselorUser = $this->makeUser('p018_bk', $roleCounselor);
        $this->superAdminUser = $this->makeUser('p018_superadmin', $roleSuperAdmin);
        $this->adminTuUser = $this->makeUser('p018_admintu', $roleAdminTu);
        $this->principalUser = $this->makeUser('p018_principal', $rolePrincipal);
        $this->homeroomTeacherUser = $this->makeUser('p018_walas', $roleTeacher);
        $this->unrelatedTeacherUser = $this->makeUser('p018_guru_lain', $roleTeacher);
        $this->librarianUser = $this->makeUser('p018_pustakawan', $roleLibrarian);
        $this->studentUser = $this->makeUser('p018_siswa', $roleStudent);
        $this->guardianUser = $this->makeUser('p018_ortu', $roleGuardian);

        $homeroomTeacher = Teacher::create([
            'user_id' => $this->homeroomTeacherUser->id,
            'nip' => '198801012015011001',
            'full_name' => 'Walas Fiktif',
            'gender' => 'L',
        ]);

        Teacher::create([
            'user_id' => $this->unrelatedTeacherUser->id,
            'nip' => '198902022016012002',
            'full_name' => 'Guru Lain Fiktif',
            'gender' => 'P',
        ]);

        $ay = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $gradeLevel = GradeLevel::create([
            'name' => 'Kelas 8',
            'level' => 8,
        ]);

        $this->classA = SchoolClass::create([
            'academic_year_id' => $ay->id,
            'grade_level_id' => $gradeLevel->id,
            'homeroom_teacher_id' => $homeroomTeacher->id,
            'name' => '8-A',
        ]);

        $this->student = Student::create([
            'user_id' => $this->studentUser->id,
            'full_name' => 'Siswa Uji Privasi',
            'nis' => '20269901',
            'nisn' => '0099887766',
            'nik_enc' => 'enc_dummy_p018',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-03-10',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        ClassStudent::create([
            'class_id' => $this->classA->id,
            'student_id' => $this->student->id,
            'joined_at' => '2026-07-15',
        ]);

        $guardian = Guardian::create([
            'user_id' => $this->guardianUser->id,
            'full_name' => 'Orang Tua Uji',
            'phone' => '081200001111',
        ]);

        $guardian->students()->attach($this->student->id, [
            'relationship' => 'ayah',
            'is_primary' => true,
        ]);
    }

    private function makeUser(string $username, Role $role): User
    {
        $user = User::create([
            'username' => $username,
            'email' => "{$username}@example.test",
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_p0_18_student_counseling_request_blocked_for_non_counselor_roles(): void
    {
        $requestRecord = CounselingRequest::create([
            'student_id' => $this->student->id,
            'requested_by' => $this->studentUser->id,
            'requester_role' => 'student',
            'topic' => 'Curhat pribadi sensitif siswa tentang tekanan teman sebaya',
            'notes' => 'Hanya ingin bicara dengan Guru BK.',
            'status' => 'pending',
        ]);

        // 1. Siswa pemilik dan Guru BK diizinkan membaca detail
        $this->actingAs($this->studentUser, 'sanctum')
            ->getJson("/api/v1/counseling/requests/{$requestRecord->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.topic', 'Curhat pribadi sensitif siswa tentang tekanan teman sebaya');

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->counselorUser, 'sanctum')
            ->getJson("/api/v1/counseling/requests/{$requestRecord->id}")
            ->assertStatus(200);

        // 2. Guru/Wali Kelas, Orang Tua, Admin TU, Kepala Sekolah, dan Super Admin (tanpa peran BK) DITOLAK 403
        $unauthorizedUsers = [
            'homeroom_teacher' => $this->homeroomTeacherUser,
            'unrelated_teacher' => $this->unrelatedTeacherUser,
            'guardian' => $this->guardianUser,
            'admin_tu' => $this->adminTuUser,
            'principal' => $this->principalUser,
            'super_admin' => $this->superAdminUser,
        ];

        foreach ($unauthorizedUsers as $label => $actor) {
            $this->app['auth']->forgetGuards();
            $this->actingAs($actor, 'sanctum')
                ->getJson("/api/v1/counseling/requests/{$requestRecord->id}")
                ->assertStatus(403, "Role {$label} seharusnya ditolak (403) mengakses detail curhat siswa.");

            $this->app['auth']->forgetGuards();
            $this->actingAs($actor, 'sanctum')
                ->patchJson("/api/v1/counseling/requests/{$requestRecord->id}/schedule", [
                    'scheduled_at' => Carbon::now()->addDay()->toDateTimeString(),
                ])
                ->assertStatus(403, "Role {$label} seharusnya ditolak (403) menjadwalkan konseling.");
        }

        // 3. Pada daftar GET /counseling/requests:
        //    - Guardian dan Teacher tidak melihat curhat mandiri siswa (0 hasil)
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->guardianUser, 'sanctum')
            ->getJson('/api/v1/counseling/requests')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 0);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->homeroomTeacherUser, 'sanctum')
            ->getJson('/api/v1/counseling/requests')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 0);

        //    - Admin TU, Principal, dan Super Admin (non-BK) ditolak 403
        foreach ([$this->adminTuUser, $this->principalUser, $this->superAdminUser] as $nonBkAdmin) {
            $this->app['auth']->forgetGuards();
            $this->actingAs($nonBkAdmin, 'sanctum')
                ->getJson('/api/v1/counseling/requests')
                ->assertStatus(403);
        }
    }

    public function test_p0_18_counseling_request_create_enforces_role_and_teacher_class_scope(): void
    {
        // Peran yang tidak berwenang ditolak 403
        foreach ([$this->adminTuUser, $this->principalUser, $this->librarianUser] as $actor) {
            $this->app['auth']->forgetGuards();
            $this->actingAs($actor, 'sanctum')
                ->postJson('/api/v1/counseling/requests', [
                    'student_id' => $this->student->id,
                    'topic' => 'Percobaan pengajuan oleh peran tidak sah',
                ])
                ->assertStatus(403);
        }

        // Guru yang bukan wali kelas dan tidak mengajar siswa ditolak 403
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->unrelatedTeacherUser, 'sanctum')
            ->postJson('/api/v1/counseling/requests', [
                'student_id' => $this->student->id,
                'topic' => 'Pengajuan guru tanpa relasi kelas',
            ])
            ->assertStatus(403);

        // Wali kelas sah diperbolehkan mengajukan dan melihat permintaan yang diajukannya sendiri
        $this->app['auth']->forgetGuards();
        $createResp = $this->actingAs($this->homeroomTeacherUser, 'sanctum')
            ->postJson('/api/v1/counseling/requests', [
                'student_id' => $this->student->id,
                'topic' => 'Permintaan pendampingan belajar dari wali kelas',
            ]);

        $createResp->assertStatus(201)
            ->assertJsonPath('data.requester_role', 'teacher');

        $reqId = $createResp->json('data.id');
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->homeroomTeacherUser, 'sanctum')
            ->getJson("/api/v1/counseling/requests/{$reqId}")
            ->assertStatus(200);
    }

    public function test_p0_18_safe_encrypted_cast_reads_legacy_plaintext_and_backfill_command_works_safely(): void
    {
        $now = Carbon::now();
        $legacyComplaintId = (string) Str::uuid();
        $legacyReqId = (string) Str::uuid();
        $legacyCaseId = (string) Str::uuid();
        $legacySessionId = (string) Str::uuid();

        $legacyComplaintText = 'Laporan legacy plaintext sebelum migrasi enkripsi P0-18.';
        $legacyTopic = 'Topik konseling legacy plaintext';
        $legacyNotes = 'Catatan permintaan legacy plaintext';
        $legacyReason = 'Alasan penolakan legacy plaintext';
        $legacyFollowUp = 'Tindak lanjut sesi legacy plaintext';

        // Sisipkan data legacy plaintext langsung via DB::table (melewati Eloquent cast)
        DB::table('complaints')->insert([
            'id' => $legacyComplaintId,
            'ticket_code' => 'ASP-20260101-LEG1',
            'category' => 'bullying',
            'content' => $legacyComplaintText,
            'is_anonymous' => true,
            'status' => 'baru',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('counseling_requests')->insert([
            'id' => $legacyReqId,
            'student_id' => $this->student->id,
            'requested_by' => $this->studentUser->id,
            'requester_role' => 'student',
            'topic' => $legacyTopic,
            'notes' => $legacyNotes,
            'rejection_reason' => $legacyReason,
            'status' => 'rejected',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $legacyCase = CounselingCase::create([
            'student_id' => $this->student->id,
            'counselor_id' => $this->counselorUser->id,
            'category' => 'Pribadi',
            'status' => 'aktif',
            'summary_enc' => 'Ringkasan kasus uji backfill',
        ]);

        DB::table('counseling_sessions')->insert([
            'id' => $legacySessionId,
            'case_id' => $legacyCase->id,
            'session_date' => '2026-08-01',
            'notes_enc' => Crypt::encryptString('Catatan sesi terenkripsi'),
            'follow_up' => $legacyFollowUp,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 1. SafeEncryptedCast membaca data legacy plaintext secara backward-compatible
        $this->assertSame($legacyComplaintText, Complaint::findOrFail($legacyComplaintId)->content);
        $reqModel = CounselingRequest::findOrFail($legacyReqId);
        $this->assertSame($legacyTopic, $reqModel->topic);
        $this->assertSame($legacyNotes, $reqModel->notes);
        $this->assertSame($legacyReason, $reqModel->rejection_reason);
        $this->assertSame($legacyFollowUp, CounselingSession::findOrFail($legacySessionId)->follow_up);

        // 2. Command backfill default adalah DRY-RUN: tidak mengubah data di DB
        $exitDry = Artisan::call('security:encrypt-legacy-sensitive-data');
        $this->assertSame(0, $exitDry);
        $this->assertSame($legacyComplaintText, DB::table('complaints')->where('id', $legacyComplaintId)->value('content'));
        $this->assertSame($legacyTopic, DB::table('counseling_requests')->where('id', $legacyReqId)->value('topic'));
        $this->assertSame($legacyFollowUp, DB::table('counseling_sessions')->where('id', $legacySessionId)->value('follow_up'));

        // 3. Jalankan backfill nyata (--execute --force)
        $exitExec = Artisan::call('security:encrypt-legacy-sensitive-data', [
            '--execute' => true,
            '--force' => true,
        ]);
        $this->assertSame(0, $exitExec);

        // Raw kolom sekarang terenkripsi dan bukan plaintext
        $rawComplaint = DB::table('complaints')->where('id', $legacyComplaintId)->value('content');
        $rawTopic = DB::table('counseling_requests')->where('id', $legacyReqId)->value('topic');
        $rawFollowUp = DB::table('counseling_sessions')->where('id', $legacySessionId)->value('follow_up');

        $this->assertTrue(SafeEncryptedCast::isEncrypted($rawComplaint));
        $this->assertTrue(SafeEncryptedCast::isEncrypted($rawTopic));
        $this->assertTrue(SafeEncryptedCast::isEncrypted($rawFollowUp));
        $this->assertNotEquals($legacyComplaintText, $rawComplaint);
        $this->assertNotEquals($legacyTopic, $rawTopic);
        $this->assertNotEquals($legacyFollowUp, $rawFollowUp);

        // Eloquent tetap membaca nilai asli secara utuh (zero data loss)
        $this->assertSame($legacyComplaintText, Complaint::findOrFail($legacyComplaintId)->content);
        $this->assertSame($legacyTopic, CounselingRequest::findOrFail($legacyReqId)->topic);
        $this->assertSame($legacyNotes, CounselingRequest::findOrFail($legacyReqId)->notes);
        $this->assertSame($legacyReason, CounselingRequest::findOrFail($legacyReqId)->rejection_reason);
        $this->assertSame($legacyFollowUp, CounselingSession::findOrFail($legacySessionId)->follow_up);

        // 4. Eksekusi kedua idempoten (ciphertext tidak dienkripsi ganda)
        $exitSecond = Artisan::call('security:encrypt-legacy-sensitive-data', [
            '--execute' => true,
            '--force' => true,
        ]);
        $this->assertSame(0, $exitSecond);
        $this->assertSame($rawComplaint, DB::table('complaints')->where('id', $legacyComplaintId)->value('content'));
        $this->assertSame($legacyComplaintText, Complaint::findOrFail($legacyComplaintId)->content);
    }

    public function test_p0_18_public_complaint_tracker_redacts_content_protects_anonymity_and_rate_limits(): void
    {
        $secretBody = 'Laporan anonim rahasia mengenai intimidasi di ruang olahraga barat.';

        $submit = $this->postJson('/api/v1/public/complaints', [
            'category' => 'bullying',
            'content' => $secretBody,
            'is_anonymous' => true,
        ]);

        $submit->assertStatus(201);
        $ticketCode = $submit->json('data.ticket_code');

        // Format kode tiket ber-entropi tinggi (10 hex)
        $this->assertMatchesRegularExpression('/^ASP-\d{8}-[A-F0-9]{10}$/', $ticketCode);

        $complaint = Complaint::where('ticket_code', $ticketCode)->firstOrFail();
        $this->assertNull($complaint->reporter_user_id);

        // Raw DB terenkripsi
        $rawContent = DB::table('complaints')->where('id', $complaint->id)->value('content');
        $this->assertTrue(SafeEncryptedCast::isEncrypted($rawContent));
        $this->assertStringNotContainsString('intimidasi', $rawContent);

        // Audit log anonim tidak menyimpan actor_id, ip, maupun user_agent
        $audit = AuditLog::where('entity', 'Complaint')->where('entity_id', $complaint->id)->firstOrFail();
        $this->assertNull($audit->actor_id);
        $this->assertNull($audit->ip);
        $this->assertNull($audit->user_agent);

        // Endpoint publik pelacakan meredaksi isi mentah pengaduan dan menerapkan rate limit (10/5 menit)
        $clientIp = ['REMOTE_ADDR' => '198.51.100.18'];
        for ($i = 0; $i < 10; $i++) {
            $resp = $this->withServerVariables($clientIp)->getJson("/api/v1/public/complaints/{$ticketCode}");
            $resp->assertStatus(200)
                ->assertJsonPath('data.content', null)
                ->assertJsonPath('data.content_redacted', true)
                ->assertJsonPath('data.is_anonymous', true);
            $this->assertArrayNotHasKey('reporter_user_id', $resp->json('data'));
            $this->assertStringNotContainsString('intimidasi', $resp->getContent());
        }

        // Permintaan ke-11 terkena rate limit 429
        $this->withServerVariables($clientIp)->getJson("/api/v1/public/complaints/{$ticketCode}")
            ->assertStatus(429);
    }

    public function test_p0_18_audit_logs_exclude_sensitive_plaintext_and_principal_sees_masked_followup(): void
    {
        // 1. CounselingRequest::reject tidak menulis rejection_reason ke audit_logs
        $req = CounselingRequest::create([
            'student_id' => $this->student->id,
            'requested_by' => $this->studentUser->id,
            'requester_role' => 'student',
            'topic' => 'Topik sensitif untuk penolakan',
            'status' => 'pending',
        ]);

        $secretRejection = 'RAHASIA-PENOLAKAN-BK-9988';
        $this->actingAs($this->counselorUser, 'sanctum')
            ->patchJson("/api/v1/counseling/requests/{$req->id}/reject", [
                'rejection_reason' => $secretRejection,
            ])
            ->assertStatus(200);

        $rejectAudit = DB::table('audit_logs')
            ->where('action', 'counseling_request.reject')
            ->where('entity_id', $req->id)
            ->first();
        $this->assertNotNull($rejectAudit);
        $this->assertStringNotContainsString($secretRejection, (string) $rejectAudit->new_value);

        // 2. CounselingReferral::updateStatus tidak menulis notes ke audit_logs
        $referral = CounselingReferral::create([
            'student_id' => $this->student->id,
            'referred_by' => $this->homeroomTeacherUser->id,
            'reason' => 'Perlu pendampingan belajar',
            'status' => 'pending',
        ]);

        $secretReferralNote = 'RAHASIA-CATATAN-RUJUKAN-7766';
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->counselorUser, 'sanctum')
            ->patchJson("/api/v1/counseling/referrals/{$referral->id}/status", [
                'status' => 'diterima',
                'notes' => $secretReferralNote,
            ])
            ->assertStatus(200);

        $refAudit = DB::table('audit_logs')
            ->where('action', 'bk_referral.update_status')
            ->where('entity_id', $referral->id)
            ->first();
        $this->assertNotNull($refAudit);
        $this->assertStringNotContainsString($secretReferralNote, (string) $refAudit->new_value);

        // 3. Complaint::updateStatus tidak menulis response_text ke audit_logs
        $complaint = Complaint::create([
            'ticket_code' => 'ASP-20261127-AUD1234567',
            'category' => 'bullying',
            'content' => 'Isi aduan rahasia.',
            'is_anonymous' => true,
            'status' => 'baru',
        ]);

        $secretResponseText = 'RAHASIA-TANGGAPAN-ADUAN-5544';
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->counselorUser, 'sanctum')
            ->patchJson("/api/v1/complaints/{$complaint->id}/status", [
                'status' => 'ditinjau',
                'response_text' => $secretResponseText,
            ])
            ->assertStatus(200);

        $compAudit = DB::table('audit_logs')
            ->where('action', 'complaint.status_updated')
            ->where('entity_id', $complaint->id)
            ->first();
        $this->assertNotNull($compAudit);
        $this->assertStringNotContainsString($secretResponseText, (string) $compAudit->new_value);

        // 4. Principal melihat CounselingSession.follow_up dalam kondisi disamarkan (masked)
        $case = CounselingCase::create([
            'student_id' => $this->student->id,
            'counselor_id' => $this->counselorUser->id,
            'category' => 'Pribadi',
            'status' => 'aktif',
            'summary_enc' => 'Ringkasan kasus privat siswa',
        ]);

        $secretFollowUp = 'RAHASIA-FOLLOWUP-TERAPI-3322';
        $session = CounselingSession::create([
            'case_id' => $case->id,
            'session_date' => '2026-11-27',
            'notes_enc' => 'Catatan sesi privat siswa',
            'follow_up' => $secretFollowUp,
        ]);

        $this->app['auth']->forgetGuards();
        $principalCaseResp = $this->actingAs($this->principalUser, 'sanctum')
            ->getJson("/api/v1/counseling/cases/{$case->id}");
        $principalCaseResp->assertStatus(200);
        $this->assertStringNotContainsString($secretFollowUp, $principalCaseResp->getContent());

        $this->app['auth']->forgetGuards();
        $principalSessionResp = $this->actingAs($this->principalUser, 'sanctum')
            ->getJson("/api/v1/counseling/sessions/{$session->id}");
        $principalSessionResp->assertStatus(200);
        $this->assertStringNotContainsString($secretFollowUp, $principalSessionResp->getContent());
    }
}
