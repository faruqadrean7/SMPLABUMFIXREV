<?php

namespace Tests\Feature\Ntf;

use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Booking;
use App\Models\ClassStudent;
use App\Models\Complaint;
use App\Models\CounselingRequest;
use App\Models\Facility;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Tier2b7CommNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected School $schoolA;

    protected School $schoolB;

    protected AcademicYear $ay;

    protected GradeLevel $grade9;

    protected SchoolClass $classA;

    protected SchoolClass $classOther;

    protected Subject $subject;

    protected User $adminUser;

    protected User $principalUser;

    protected User $principalOtherSchool;

    protected User $counselorUser;

    protected User $teacherUser;

    protected User $teacherOtherUser;

    protected User $sarprasUser;

    protected User $studentUser;

    protected User $leftStudentUser;

    protected User $guardianUser;

    protected User $unrelatedGuardianUser;

    protected Teacher $teacher;

    protected Student $student;

    protected Student $leftStudent;

    protected Guardian $guardian;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::create(['name' => 'SMP Lab A', 'npsn' => '20500001']);
        $this->schoolB = School::create(['name' => 'SMP Lab B', 'npsn' => '20500002']);

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $rolePrincipal = Role::firstOrCreate(['code' => 'principal'], ['name' => 'Kepala Sekolah', 'is_system' => true]);
        $roleCounselor = Role::firstOrCreate(['code' => 'counselor'], ['name' => 'Guru BK', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleSarpras = Role::firstOrCreate(['code' => 'facility_staff'], ['name' => 'Staf Sarpras', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Wali Murid', 'is_system' => true]);

        $permBroadcast = Permission::firstOrCreate(['code' => 'notification.broadcast'], ['description' => 'Broadcast notif']);
        $permApprove = Permission::firstOrCreate(['code' => 'executive.approval.manage'], ['description' => 'Kelola persetujuan']);
        $permSarpras = Permission::firstOrCreate(['code' => 'sarpras.manage'], ['description' => 'Kelola sarpras']);
        $permBk = Permission::firstOrCreate(['code' => 'bk.counseling.manage'], ['description' => 'Kelola BK']);
        $permTeacher = Permission::firstOrCreate(['code' => 'master.teacher.manage'], ['description' => 'Kelola guru']);

        $roleAdmin->permissions()->syncWithoutDetaching([$permBroadcast->id, $permApprove->id, $permSarpras->id, $permBk->id, $permTeacher->id]);
        $rolePrincipal->permissions()->syncWithoutDetaching([$permApprove->id]);
        $roleCounselor->permissions()->syncWithoutDetaching([$permBk->id]);
        $roleSarpras->permissions()->syncWithoutDetaching([$permSarpras->id]);

        $this->adminUser = $this->makeUser('t2b7_admin', $roleAdmin, $this->schoolA->id);
        $this->principalUser = $this->makeUser('t2b7_principal', $rolePrincipal, $this->schoolA->id);
        $this->principalOtherSchool = $this->makeUser('t2b7_principal_b', $rolePrincipal, $this->schoolB->id);
        $this->counselorUser = $this->makeUser('t2b7_bk', $roleCounselor, $this->schoolA->id);
        $this->teacherUser = $this->makeUser('t2b7_guru', $roleTeacher, $this->schoolA->id);
        $this->teacherOtherUser = $this->makeUser('t2b7_guru_lain', $roleTeacher, $this->schoolA->id);
        $this->sarprasUser = $this->makeUser('t2b7_sarpras', $roleSarpras, $this->schoolA->id);
        $this->studentUser = $this->makeUser('t2b7_siswa', $roleStudent, $this->schoolA->id);
        $this->leftStudentUser = $this->makeUser('t2b7_siswa_keluar', $roleStudent, $this->schoolA->id);
        $this->guardianUser = $this->makeUser('t2b7_ortu', $roleGuardian, $this->schoolA->id);
        $this->unrelatedGuardianUser = $this->makeUser('t2b7_ortu_lain', $roleGuardian, $this->schoolA->id);

        $this->teacher = Teacher::create([
            'school_id' => $this->schoolA->id,
            'user_id' => $this->teacherUser->id,
            'nip' => '198601012015011001',
            'full_name' => 'Guru Uji T2B7',
            'gender' => 'L',
        ]);

        $this->ay = AcademicYear::create([
            'name' => '2026/2027 T2B7',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $this->grade9 = GradeLevel::create([
            'name' => 'Kelas 9 T2B7',
            'level' => 9,
        ]);

        $this->classA = SchoolClass::create([
            'school_id' => $this->schoolA->id,
            'academic_year_id' => $this->ay->id,
            'grade_level_id' => $this->grade9->id,
            'homeroom_teacher_id' => $this->teacher->id,
            'name' => 'IX-T2B7-A',
            'capacity' => 30,
        ]);

        $this->classOther = SchoolClass::create([
            'school_id' => $this->schoolA->id,
            'academic_year_id' => $this->ay->id,
            'grade_level_id' => $this->grade9->id,
            'name' => 'IX-T2B7-B',
            'capacity' => 30,
        ]);

        $this->subject = Subject::create([
            'code' => 'MAT-T2B7',
            'name' => 'Matematika',
            'subject_group' => 'wajib',
        ]);

        $this->student = Student::create([
            'school_id' => $this->schoolA->id,
            'user_id' => $this->studentUser->id,
            'nis' => '20267001',
            'nisn' => '00977001',
            'nik_enc' => '3578010101017001',
            'full_name' => 'Siswa Fiktif T2B7',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2011-02-02',
            'entry_date' => '2026-07-01',
            'status' => 'active',
        ]);

        $this->leftStudent = Student::create([
            'school_id' => $this->schoolA->id,
            'user_id' => $this->leftStudentUser->id,
            'nis' => '20267002',
            'nisn' => '00977002',
            'nik_enc' => '3578010101017002',
            'full_name' => 'Siswa Pindah T2B7',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2011-03-03',
            'entry_date' => '2026-07-01',
            'status' => 'active',
        ]);

        ClassStudent::create([
            'class_id' => $this->classA->id,
            'student_id' => $this->student->id,
            'joined_at' => '2026-07-01',
        ]);

        ClassStudent::create([
            'class_id' => $this->classA->id,
            'student_id' => $this->leftStudent->id,
            'joined_at' => '2026-07-01',
            'left_at' => '2026-09-01',
        ]);

        $this->guardian = Guardian::create([
            'school_id' => $this->schoolA->id,
            'user_id' => $this->guardianUser->id,
            'full_name' => 'Wali Fiktif T2B7',
            'phone' => '081200001111',
        ]);
        $this->guardian->students()->attach($this->student->id, ['relationship' => 'Ayah', 'is_primary' => true]);

        Guardian::create([
            'school_id' => $this->schoolA->id,
            'user_id' => $this->unrelatedGuardianUser->id,
            'full_name' => 'Wali Lain Fiktif',
            'phone' => '081200002222',
        ]);
    }

    protected function makeUser(string $username, Role $role, ?string $schoolId = null): User
    {
        $user = User::create([
            'school_id' => $schoolId,
            'username' => $username,
            'email' => "{$username}@example.test",
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_p1_24_long_webpush_device_register_and_delete_by_body_and_throttle(): void
    {
        $webPushToken = json_encode([
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.str_repeat('AbCdEfGh123456/', 45),
            'keys' => ['p256dh' => str_repeat('k', 80), 'auth' => str_repeat('a', 32)],
        ]);
        $this->assertGreaterThan(650, strlen($webPushToken));

        Sanctum::actingAs($this->studentUser);

        $registerRes = $this->postJson('/api/v1/devices', [
            'platform' => 'web',
            'token' => $webPushToken,
        ]);
        $registerRes->assertStatus(201);
        $this->assertDatabaseHas('devices', [
            'user_id' => $this->studentUser->id,
            'fcm_token' => $webPushToken,
        ]);

        // Unregister via DELETE /devices (JSON body containing '/' URL chars)
        $delBodyRes = $this->deleteJson('/api/v1/devices', [
            'fcm_token' => $webPushToken,
        ]);
        $delBodyRes->assertStatus(200);
        $this->assertDatabaseMissing('devices', ['fcm_token' => $webPushToken]);

        // Verify throttle middleware attached on broadcast & devices routes
        $routes = collect(Route::getRoutes()->getRoutes());
        $broadcastRoute = $routes->first(fn ($r) => $r->uri() === 'api/v1/notifications/broadcast');
        $this->assertNotNull($broadcastRoute);
        $this->assertTrue(
            collect($broadcastRoute->gatherMiddleware())->contains(fn ($m) => str_starts_with($m, 'throttle:'))
        );
    }

    public function test_p1_18_all_school_staff_roles_can_save_canonical_notification_preferences(): void
    {
        foreach ([$this->principalUser, $this->counselorUser, $this->sarprasUser] as $staff) {
            Sanctum::actingAs($staff);
            $getRes = $this->getJson('/api/v1/me/notification-preferences');
            $getRes->assertStatus(200);
            $this->assertNotEmpty($getRes->json('data'));

            $putRes = $this->putJson('/api/v1/me/notification-preferences', [
                'preferences' => [
                    ['notif_type' => 'academic_announcement', 'channel_push' => true, 'is_enabled' => true],
                    ['notif_type' => 'system_alert', 'channel_push' => false, 'is_enabled' => false],
                ],
            ]);
            $putRes->assertStatus(200);
            $this->assertDatabaseHas('notification_preferences', [
                'user_id' => $staff->id,
                'notif_type' => 'system_alert',
                'is_enabled' => false,
            ]);
        }
    }

    public function test_p1_20_commands_emit_canonical_target_url_and_complaint_escalate_isolates_school(): void
    {
        Artisan::call('academic:progress-report-reminder');
        $progressNotif = Notification::where('user_id', $this->teacherUser->id)
            ->where('notif_type', 'academic_reminder')
            ->first();
        $this->assertNotNull($progressNotif);
        $this->assertSame('/portal/guru/nilai', $progressNotif->data['target_url'] ?? null);

        $complaint = Complaint::create([
            'school_id' => $this->schoolA->id,
            'ticket_code' => 'ASP-T2B7-001',
            'category' => 'bullying',
            'content' => 'Laporan uji SLA lintas sekolah',
            'is_anonymous' => true,
            'status' => 'baru',
            'severity' => 'tinggi',
            'due_at' => Carbon::now()->subHours(2),
        ]);

        Artisan::call('complaints:escalate-overdue');

        $schoolANotif = Notification::where('user_id', $this->principalUser->id)
            ->where('notif_type', 'complaint.sla_breach')
            ->first();
        $this->assertNotNull($schoolANotif);
        $this->assertSame('/admin/public-relations?tab=complaints', $schoolANotif->data['target_url'] ?? null);
        $this->assertSame('/admin/public-relations?tab=complaints', $schoolANotif->data['action_url'] ?? null);

        // Principal of School B MUST NOT receive escalation notification for School A
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->principalOtherSchool->id,
            'notif_type' => 'complaint.sla_breach',
        ]);
    }

    public function test_p1_21_guardian_sees_child_class_announcements_and_teacher_is_scoped(): void
    {
        $classAnnouncement = Announcement::create([
            'school_id' => $this->schoolA->id,
            'author_id' => $this->adminUser->id,
            'title' => 'Info Khusus Kelas IX-T2B7-A',
            'body' => 'Jadwal foto kelas besok pagi.',
            'target_type' => 'kelas',
            'target_ids' => [$this->classA->id],
            'publish_at' => Carbon::now()->subMinute(),
        ]);

        Announcement::create([
            'school_id' => $this->schoolA->id,
            'author_id' => $this->adminUser->id,
            'title' => 'Info Kelas Lain IX-T2B7-B',
            'body' => 'Tidak untuk wali murid kelas A.',
            'target_type' => 'kelas',
            'target_ids' => [$this->classOther->id],
            'publish_at' => Carbon::now()->subMinute(),
        ]);

        // Guardian of active student in class A sees class A announcement, NOT classOther
        Sanctum::actingAs($this->guardianUser);
        $resList = $this->getJson('/api/v1/announcements');
        $resList->assertStatus(200);
        $titles = collect($resList->json('data'))->pluck('title')->all();
        $this->assertContains('Info Khusus Kelas IX-T2B7-A', $titles);
        $this->assertNotContains('Info Kelas Lain IX-T2B7-B', $titles);

        // Unrelated guardian gets 403 on classA detail
        Sanctum::actingAs($this->unrelatedGuardianUser);
        $this->getJson("/api/v1/announcements/{$classAnnouncement->id}")->assertStatus(403);

        // Pure teacher CANNOT post 'semua' or another class's announcement (403)
        Sanctum::actingAs($this->teacherUser);
        $this->postJson('/api/v1/announcements', [
            'title' => 'Broadcast Liar Guru',
            'body' => 'Tidak boleh',
            'target_type' => 'semua',
        ])->assertStatus(403);

        $this->postJson('/api/v1/announcements', [
            'title' => 'Pengumuman Kelas Orang Lain',
            'body' => 'Tidak boleh',
            'target_type' => 'kelas',
            'target_ids' => [$this->classOther->id],
        ])->assertStatus(403);

        // Teacher CAN post to their own homeroom/teaching class
        $this->postJson('/api/v1/announcements', [
            'title' => 'Info Wali Kelas IX-T2B7-A',
            'body' => 'Harap bawa buku paket besok.',
            'target_type' => 'kelas',
            'target_ids' => [$this->classA->id],
        ])->assertStatus(201);

        // Class broadcast excludes left students and includes active guardian
        Sanctum::actingAs($this->adminUser);
        $bcRes = $this->postJson('/api/v1/notifications/broadcast', [
            'target_type' => 'class',
            'target_class_id' => $this->classA->id,
            'title' => 'Broadcast Kelas IX-T2B7-A',
            'body' => 'Info agenda wali murid & siswa aktif.',
        ]);
        $bcRes->assertStatus(201);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->studentUser->id,
            'title' => 'Broadcast Kelas IX-T2B7-A',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->guardianUser->id,
            'title' => 'Broadcast Kelas IX-T2B7-A',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->leftStudentUser->id,
            'title' => 'Broadcast Kelas IX-T2B7-A',
        ]);
    }

    public function test_p1_22_operational_events_emit_sterile_notifications_and_ticket_sla_deduplicates(): void
    {
        // 1. LeaveRequest -> notifies homeroom teacher
        Sanctum::actingAs($this->guardianUser);
        $this->postJson('/api/v1/leave-requests', [
            'student_id' => $this->student->id,
            'leave_type' => 'sakit',
            'start_date' => Carbon::now()->next(Carbon::MONDAY)->toDateString(),
            'end_date' => Carbon::now()->next(Carbon::MONDAY)->toDateString(),
            'reason' => 'Demam tinggi perlu istirahat.',
        ])->assertStatus(201);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->teacherUser->id,
            'notif_type' => 'leave_request.created',
        ]);

        // 2. Booking Approval -> notifies requester
        $facility = Facility::create([
            'school_id' => $this->schoolA->id,
            'code' => 'LAB-T2B7',
            'name' => 'Lab Sains T2B7',
            'facility_type' => 'laboratorium',
            'capacity' => 30,
            'requires_approval' => true,
            'is_active' => true,
        ]);
        $booking = Booking::create([
            'school_id' => $this->schoolA->id,
            'facility_id' => $facility->id,
            'requested_by' => $this->teacherUser->id,
            'start_at' => Carbon::now()->addDay(),
            'end_at' => Carbon::now()->addDay()->addHours(2),
            'purpose' => 'Praktikum',
            'attendees_count' => 25,
            'status' => 'diajukan',
        ]);
        Sanctum::actingAs($this->principalUser);
        $this->postJson("/api/v1/approvals/{$booking->id}/review", [
            'action' => 'approve',
            'notes' => 'Silakan gunakan lab.',
        ])->assertStatus(200);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->teacherUser->id,
            'notif_type' => 'booking.decision',
        ]);

        // 3. CounselingRequest sterile notifications
        Sanctum::actingAs($this->guardianUser);
        $secretTopic = 'RAHASIA_KONSELING_PRIVAT_T2B7';
        $crRes = $this->postJson('/api/v1/counseling/requests', [
            'student_id' => $this->student->id,
            'topic' => $secretTopic,
            'notes' => 'Konsultasi tertutup keluarga.',
        ])->assertStatus(201);
        $crId = $crRes->json('data.id');

        $bkNotif = Notification::where('user_id', $this->counselorUser->id)
            ->where('notif_type', 'counseling_update')
            ->first();
        $this->assertNotNull($bkNotif);
        $this->assertStringNotContainsString($secretTopic, $bkNotif->title.' '.$bkNotif->body);

        Sanctum::actingAs($this->counselorUser);
        $this->patchJson("/api/v1/counseling/requests/{$crId}/schedule", [
            'scheduled_at' => Carbon::now()->addDays(2)->toIso8601String(),
            'notes' => 'Jadwal tatap muka rahasia',
        ])->assertStatus(200);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->guardianUser->id,
            'notif_type' => 'counseling_update',
        ]);

        // 4. Salary slip notification contains zero monetary numbers
        Sanctum::actingAs($this->adminUser);
        $this->postJson("/api/v1/teachers/{$this->teacher->id}/salary-slips", [
            'period_month' => 11,
            'period_year' => 2026,
            'basic_salary' => 5500000,
            'allowances' => 500000,
            'deductions' => 100000,
            'status' => 'published',
        ])->assertStatus(201);
        $slipNotif = Notification::where('user_id', $this->teacherUser->id)
            ->where('notif_type', 'salary_slip.published')
            ->first();
        $this->assertNotNull($slipNotif);
        $this->assertStringNotContainsString('5500000', $slipNotif->title.' '.$slipNotif->body);
        $this->assertStringNotContainsString('5900000', json_encode($slipNotif->data));

        // 5. Ticket SLA 'diproses' picked up & deduplicated on 2x runs
        $ticket = Ticket::create([
            'school_id' => $this->schoolA->id,
            'ticket_number' => 'TKT-T2B7-01',
            'title' => 'AC Lab Bocor',
            'reporter_id' => $this->teacherUser->id,
            'description' => 'Menetes sejak pagi.',
            'priority' => 'darurat',
            'status' => 'diproses',
        ]);
        DB::table('tickets')->where('id', $ticket->id)->update([
            'created_at' => Carbon::now()->subHours(10),
        ]);

        Artisan::call('sarpras:ticket-sla-reminder');
        Artisan::call('sarpras:ticket-sla-reminder');

        $slaNotifCount = Notification::where('user_id', $this->sarprasUser->id)
            ->where('notif_type', 'ticket_sla_breach')
            ->count();
        $this->assertSame(1, $slaNotifCount);
    }

    public function test_p1_19_messaging_service_dispatches_real_mail_and_http_whatsapp_adapter(): void
    {
        Mail::fake();
        Http::fake([
            'https://wa-gateway.test/*' => Http::response(['id' => 'wa_provider_ok_99'], 200),
        ]);
        config(['services.whatsapp.endpoint' => 'https://wa-gateway.test/send']);
        config(['services.whatsapp.token' => 'secret-wa-token']);

        $waTpl = NotificationTemplate::create([
            'school_id' => $this->schoolA->id,
            'code' => 'WA_T2B7',
            'name' => 'Info T2B7',
            'channel' => 'whatsapp',
            'template_text' => 'Halo {name}',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->adminUser);
        $res = $this->postJson('/api/v1/notification-templates/send', [
            'template_id' => $waTpl->id,
            'recipient' => '081234567890',
            'params' => ['name' => 'Wali Murid'],
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.provider_ref', 'wa_provider_ok_99');

        Http::assertSent(fn ($request) => $request->url() === 'https://wa-gateway.test/send'
            && $request['target'] === '6281234567890'
        );
    }
}
