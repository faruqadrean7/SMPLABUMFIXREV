<?php

namespace Tests\Feature\Security;

use App\Models\Announcement;
use App\Models\CounselingCase;
use App\Models\CounselingReferral;
use App\Models\CounselingSession;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\NotificationTemplate;
use App\Models\Permission;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MultiTenantAndCbtSyncSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function createSchool(string $name, string $npsn): School
    {
        return School::create([
            'name' => $name,
            'npsn' => $npsn,
            'address' => 'Jl. Pendidikan No. 1',
            'phone' => '0341000001',
            'email' => strtolower(str_replace(' ', '_', $name)).'@test.local',
        ]);
    }

    private function createUserWithRoleAndPerms(School $school, string $roleCode, array $permCodes): User
    {
        $role = Role::firstOrCreate(['code' => $roleCode], ['name' => ucfirst($roleCode), 'is_system' => true]);
        foreach ($permCodes as $code) {
            $perm = Permission::firstOrCreate(['code' => $code], ['description' => $code]);
            $role->permissions()->syncWithoutDetaching([$perm->id]);
        }

        $user = User::create([
            'school_id' => $school->id,
            'username' => $roleCode.'_'.uniqid(),
            'email' => $roleCode.'_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_p1_08_cbt_sync_pull_never_exposes_is_correct_hash_or_match_key(): void
    {
        $school = $this->createSchool('SMP Lab CBT', '20533001');
        $admin = $this->createUserWithRoleAndPerms($school, 'super_admin', ['cbt.manage']);
        $subject = Subject::create([
            'school_id' => $school->id,
            'code' => 'IPA-SYNC',
            'name' => 'IPA Terpadu',
            'subject_group' => 'wajib',
        ]);
        $bank = QuestionBank::create([
            'subject_id' => $subject->id,
            'owner_id' => $admin->id,
            'name' => 'Bank Sync Test',
        ]);

        $qPg = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'pg',
            'body' => ['content' => 'Ibukota Indonesia?'],
            'weight' => 10,
            'status' => 'aktif',
        ]);
        QuestionOption::create([
            'question_id' => $qPg->id,
            'label' => 'A',
            'body' => ['text' => 'Jakarta'],
            'is_correct' => true,
        ]);

        $qMatch = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'menjodohkan',
            'body' => ['content' => 'Pasangkan unsur berikut'],
            'weight' => 10,
            'status' => 'aktif',
        ]);
        QuestionOption::create([
            'question_id' => $qMatch->id,
            'label' => 'H',
            'body' => ['text' => 'Simbol H'],
            'match_key' => 'Hidrogen_RAHASIA',
            'is_correct' => true,
        ]);

        $exam = Exam::create([
            'school_id' => $school->id,
            'subject_id' => $subject->id,
            'created_by' => $admin->id,
            'title' => 'Ujian Sync Tanpa Kunci',
            'exam_type' => 'sas',
            'duration_min' => 60,
            'start_at' => Carbon::now()->subHour(),
            'end_at' => Carbon::now()->addHour(),
            'status' => 'berlangsung',
            'sync_node_token' => 'node-secret-token-p108',
        ]);
        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $qPg->id, 'sort_order' => 1]);
        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $qMatch->id, 'sort_order' => 2]);

        $res = $this->postJson('/api/v1/cbt/sync/pull', [
            'exam_id' => $exam->id,
            'node_id' => 'node-lab-p108',
            'sync_token' => 'node-secret-token-p108',
        ]);

        $res->assertStatus(200);
        $rawBody = $res->getContent();
        $this->assertStringNotContainsString('is_correct', $rawBody);
        $this->assertStringNotContainsString('match_key', $rawBody);
        $this->assertStringNotContainsString('Hidrogen_RAHASIA', $rawBody);

        foreach ($res->json('data.questions') as $q) {
            foreach ($q['options'] as $opt) {
                $this->assertArrayNotHasKey('is_correct', $opt);
                $this->assertArrayNotHasKey('is_correct_hash', $opt);
                $this->assertArrayNotHasKey('match_key', $opt);
            }
        }
    }

    public function test_p0_20_counseling_cross_tenant_isolation_blocks_other_school_updates_and_sessions(): void
    {
        $schoolA = $this->createSchool('SMP Tenant A', '20533011');
        $schoolB = $this->createSchool('SMP Tenant B', '20533012');

        $bkA = $this->createUserWithRoleAndPerms($schoolA, 'counselor', ['bk.counseling.manage', 'counseling.case.read', 'counseling.case.write']);
        $bkB = $this->createUserWithRoleAndPerms($schoolB, 'counselor', ['bk.counseling.manage', 'counseling.case.read', 'counseling.case.write']);

        $studentB = Student::create([
            'school_id' => $schoolB->id,
            'nis' => 'NIS-B-001',
            'nisn' => '0099887766',
            'nik_enc' => '3573010101010001',
            'full_name' => 'Siswa Fiktif Tenant B',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-05-10',
            'religion' => 'Islam',
            'address' => 'Jl. Fiktif No. 2',
            'entry_date' => '2025-07-14',
            'status' => 'aktif',
        ]);

        $caseB = CounselingCase::create([
            'student_id' => $studentB->id,
            'counselor_id' => $bkB->id,
            'category' => 'pribadi',
            'status' => 'aktif',
            'summary_enc' => 'Catatan privat tenant B',
        ]);

        $sessionB = CounselingSession::create([
            'case_id' => $caseB->id,
            'session_date' => now()->toDateString(),
            'notes_enc' => 'Catatan sesi tenant B',
        ]);

        $referralB = CounselingReferral::create([
            'student_id' => $studentB->id,
            'referred_by' => $bkB->id,
            'reason' => 'Rujukan tenant B',
            'status' => 'pending',
        ]);

        // Konselor Sekolah A ditolak 403 saat mengubah kasus, membuat sesi, membaca sesi, atau ubah rujukan Sekolah B
        $this->actingAs($bkA, 'sanctum')
            ->putJson("/api/v1/counseling/cases/{$caseB->id}", ['status' => 'selesai'])
            ->assertStatus(403);

        $this->actingAs($bkA, 'sanctum')
            ->postJson("/api/v1/counseling/cases/{$caseB->id}/sessions", [
                'session_date' => now()->toDateString(),
                'notes' => 'Sesi ilegal lintas tenant',
            ])
            ->assertStatus(403);

        $this->actingAs($bkA, 'sanctum')
            ->getJson("/api/v1/counseling/sessions/{$sessionB->id}")
            ->assertStatus(403);

        $this->actingAs($bkA, 'sanctum')
            ->patchJson("/api/v1/counseling/referrals/{$referralB->id}/status", ['status' => 'diterima'])
            ->assertStatus(403);
    }

    public function test_p0_20_announcements_and_templates_enforce_school_id_and_block_tenant_spoofing(): void
    {
        $schoolA = $this->createSchool('SMP NTF A', '20533021');
        $schoolB = $this->createSchool('SMP NTF B', '20533022');

        $adminA = $this->createUserWithRoleAndPerms($schoolA, 'admin_tu', ['notification.broadcast']);
        $adminB = $this->createUserWithRoleAndPerms($schoolB, 'admin_tu', ['notification.broadcast']);

        // 1. Admin A mencoba memalsukan school_id Sekolah B saat membuat pengumuman -> harus dipaksa ke schoolA
        $createAnn = $this->actingAs($adminA, 'sanctum')
            ->postJson('/api/v1/announcements', [
                'school_id' => $schoolB->id,
                'title' => 'Pengumuman Sekolah A',
                'body' => 'Isi pengumuman resmi',
                'target_type' => 'semua',
            ])
            ->assertStatus(201);

        $annId = $createAnn->json('data.id');
        $this->assertSame($schoolA->id, Announcement::findOrFail($annId)->school_id);

        // Admin B ditolak 403 saat melihat / mengubah / menghapus pengumuman milik Sekolah A
        $this->actingAs($adminB, 'sanctum')->getJson("/api/v1/announcements/{$annId}")->assertStatus(403);
        $this->actingAs($adminB, 'sanctum')->putJson("/api/v1/announcements/{$annId}", ['title' => 'Dibajak'])->assertStatus(403);
        $this->actingAs($adminB, 'sanctum')->deleteJson("/api/v1/announcements/{$annId}")->assertStatus(403);

        // 2. Admin A mencoba memalsukan school_id Sekolah B saat membuat template notifikasi -> harus dipaksa ke schoolA
        $createTpl = $this->actingAs($adminA, 'sanctum')
            ->postJson('/api/v1/notification-templates', [
                'school_id' => $schoolB->id,
                'code' => 'TPL_TENANT_A',
                'name' => 'Template Sekolah A',
                'channel' => 'wa',
                'template_text' => 'Halo {{nama}}',
            ])
            ->assertStatus(201);

        $tplId = $createTpl->json('data.id');
        $this->assertSame($schoolA->id, NotificationTemplate::findOrFail($tplId)->school_id);

        // Admin B ditolak 403 saat mengakses / mengubah / menghapus template Sekolah A
        $this->actingAs($adminB, 'sanctum')->getJson("/api/v1/notification-templates/{$tplId}")->assertStatus(403);
        $this->actingAs($adminB, 'sanctum')->putJson("/api/v1/notification-templates/{$tplId}", ['name' => 'Dibajak'])->assertStatus(403);
        $this->actingAs($adminB, 'sanctum')->deleteJson("/api/v1/notification-templates/{$tplId}")->assertStatus(403);

        // 3. Pengguna terautentikasi di Sekolah A mengirim pengaduan dengan school_id Sekolah B -> tetap tercatat di Sekolah A
        $complaintRes = $this->actingAs($adminA, 'sanctum')
            ->postJson('/api/v1/public/complaints', [
                'school_id' => $schoolB->id,
                'category' => 'fasilitas',
                'content' => 'Laporan fasilitas ruang kelas',
                'is_anonymous' => false,
            ])
            ->assertStatus(201);

        $ticketCode = $complaintRes->json('data.ticket_code');
        $this->assertDatabaseHas('complaints', [
            'ticket_code' => $ticketCode,
            'school_id' => $schoolA->id,
        ]);
    }
}
