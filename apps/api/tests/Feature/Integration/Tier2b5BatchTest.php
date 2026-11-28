<?php

namespace Tests\Feature\Integration;

use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\ApplicantParent;
use App\Models\Booking;
use App\Models\ClassStudent;
use App\Models\Extracurricular;
use App\Models\ExtracurricularMember;
use App\Models\Facility;
use App\Models\FeeType;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradeLevel;
use App\Models\GradePeriod;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\PPDBTrack;
use App\Models\PPDBWave;
use App\Models\ReportCard;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\SiteSetting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Finance\Channels\ManualGiroPaymentChannel;
use App\Services\Finance\FinanceService;
use App\Services\PPDBEnrollmentService;
use App\Services\PPDBReceiptAndStatsService;
use App\Services\PPDBSelectionService;
use App\Services\ReportCardService;
use App\Services\Rpt\DashboardService;
use App\Services\Rpt\ReportService;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Tier2b5BatchTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->school = School::create([
            'name' => 'SMP Lab Tier2B5',
            'npsn' => '20534300',
            'address' => 'Jl. Fiktif T2B5 No. 5',
            'phone' => '0341000055',
            'email' => 't2b5@test.local',
        ]);

        $adminRole = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        foreach ([
            'academic.report.generate',
            'academic.grade.read',
            'ppdb.applicant.verify',
            'ppdb.selection.run',
            'ppdb.applicant.read',
            'finance.payment.verify',
            'finance.invoice.manage',
            'reports.view',
        ] as $permCode) {
            $perm = Permission::firstOrCreate(['code' => $permCode], ['description' => $permCode]);
            $adminRole->permissions()->syncWithoutDetaching([$perm->id]);
        }

        $this->adminUser = User::create([
            'school_id' => $this->school->id,
            'username' => 'admin_t2b5_'.uniqid(),
            'email' => 'admin_t2b5_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($adminRole->id);
    }

    public function test_p1_16_lock_and_bulk_lock_protect_published_reports_and_filter_active_extracurriculars(): void
    {
        $studentRole = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $gradeReadPerm = Permission::firstOrCreate(['code' => 'academic.grade.read'], ['description' => 'grade.read']);
        $studentRole->permissions()->syncWithoutDetaching([$gradeReadPerm->id]);

        $studentUser1 = User::create([
            'school_id' => $this->school->id,
            'username' => 's1_p116_'.uniqid(),
            'email' => 's1_p116_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $studentUser1->roles()->attach($studentRole->id);

        $studentUser2 = User::create([
            'school_id' => $this->school->id,
            'username' => 's2_p116_'.uniqid(),
            'email' => 's2_p116_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $studentUser2->roles()->attach($studentRole->id);

        $ay = AcademicYear::create([
            'school_id' => $this->school->id,
            'name' => '2026/2027 P116',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
        ]);
        $semester = Semester::create([
            'academic_year_id' => $ay->id,
            'name' => 'Ganjil P116',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-20',
            'is_active' => true,
        ]);
        $gl = GradeLevel::create(['name' => 'Kelas 8 P116', 'sort_order' => 8]);
        $class = SchoolClass::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $ay->id,
            'grade_level_id' => $gl->id,
            'name' => 'VIII-P116',
            'capacity' => 30,
        ]);

        $student1 = Student::create([
            'school_id' => $this->school->id,
            'user_id' => $studentUser1->id,
            'nis' => 'P116-01',
            'nisn' => '00116001',
            'nik_enc' => 'enc_p116_1',
            'full_name' => 'Siswa Terbit P116',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2011-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);
        $student2 = Student::create([
            'school_id' => $this->school->id,
            'user_id' => $studentUser2->id,
            'nis' => 'P116-02',
            'nisn' => '00116002',
            'nik_enc' => 'enc_p116_2',
            'full_name' => 'Siswa Draf P116',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2011-02-02',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        ClassStudent::create(['class_id' => $class->id, 'student_id' => $student1->id, 'joined_at' => '2026-07-01']);
        ClassStudent::create(['class_id' => $class->id, 'student_id' => $student2->id, 'joined_at' => '2026-07-01']);

        // Ekskul: 1 aktif dalam semester, 1 nonaktif, 1 masuk setelah semester berakhir
        $exAktif = Extracurricular::create(['name' => 'Pramuka Aktif', 'category' => 'wajib', 'status' => 'aktif']);
        $exNonaktif = Extracurricular::create(['name' => 'Karate Lama', 'category' => 'pilihan', 'status' => 'aktif']);
        $exFuture = Extracurricular::create(['name' => 'Robotika Masa Depan', 'category' => 'pilihan', 'status' => 'aktif']);

        ExtracurricularMember::create([
            'extracurricular_id' => $exAktif->id,
            'student_id' => $student1->id,
            'status' => 'aktif',
            'final_grade' => 'A',
            'joined_at' => '2026-08-01',
        ]);
        ExtracurricularMember::create([
            'extracurricular_id' => $exNonaktif->id,
            'student_id' => $student1->id,
            'status' => 'nonaktif',
            'final_grade' => 'B',
            'joined_at' => '2026-08-01',
        ]);
        ExtracurricularMember::create([
            'extracurricular_id' => $exFuture->id,
            'student_id' => $student1->id,
            'status' => 'aktif',
            'final_grade' => 'A',
            'joined_at' => '2027-02-01',
        ]);

        // Snapshot hanya memuat ekskul aktif dalam rentang semester
        $snapshot = app(ReportCardService::class)->compileSemesterSnapshot($student1, $semester);
        $this->assertCount(1, $snapshot['extracurriculars']);
        $this->assertEquals('Pramuka Aktif', $snapshot['extracurriculars'][0]['name']);

        $publishedReport = ReportCard::create([
            'student_id' => $student1->id,
            'semester_id' => $semester->id,
            'data_snapshot' => $snapshot,
            'status' => 'terbit',
            'approved_by' => $this->adminUser->id,
            'verify_token' => 'p116-terbit-token',
        ]);

        $draftReport = ReportCard::create([
            'student_id' => $student2->id,
            'semester_id' => $semester->id,
            'data_snapshot' => ['student' => ['full_name' => 'Siswa Draf P116']],
            'status' => 'draft',
            'verify_token' => 'p116-draft-token',
        ]);

        // lockReportCard menolak rapor status 'terbit' dengan 422
        $singleLockRes = $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/v1/report-cards/{$publishedReport->id}/lock", [
                'is_locked' => true,
            ]);
        $singleLockRes->assertStatus(422);
        $this->assertEquals('terbit', $publishedReport->fresh()->status);

        // bulkLock hanya mengunci 'draft' & melompati 'terbit'
        $bulkRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/report-cards/bulk-lock', [
                'class_id' => $class->id,
                'semester_id' => $semester->id,
                'is_locked' => true,
                'reason' => 'Tutup semester',
            ]);
        $bulkRes->assertStatus(200)
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.skipped_published_count', 1);

        $this->assertEquals('terbit', $publishedReport->fresh()->status);
        $this->assertEquals('terkunci', $draftReport->fresh()->status);

        // Siswa tetap 200 saat melihat rapor miliknya yang berstatus 'terbit'
        $this->app['auth']->forgetGuards();
        $studentView = $this->actingAs($studentUser1, 'sanctum')
            ->getJson("/api/v1/report-cards/{$publishedReport->id}");
        $studentView->assertStatus(200)->assertJsonPath('data.status', 'terbit');
    }

    public function test_p1_25_ppdb_post_enrollment_isolation_waitlist_activation_and_qr_verification(): void
    {
        $guestRole = Role::firstOrCreate(['code' => 'guest'], ['name' => 'Tamu', 'is_system' => true]);

        $wave = PPDBWave::create([
            'school_id' => $this->school->id,
            'name' => 'Gelombang P125',
            'academic_year' => '2026/2027',
            'open_at' => Carbon::now()->subDays(20),
            'close_at' => Carbon::now()->subDays(5),
            'result_publish_at' => Carbon::now()->subDays(4),
            'status' => 'aktif',
        ]);

        $trackA = PPDBTrack::create([
            'wave_id' => $wave->id,
            'code' => 'ZON-P125',
            'name' => 'Zonasi P125',
            'quota' => 5,
            'min_score' => 50,
            'sort_order' => 1,
        ]);
        $trackB = PPDBTrack::create([
            'wave_id' => $wave->id,
            'code' => 'PRES-P125',
            'name' => 'Prestasi P125',
            'quota' => 5,
            'min_score' => 50,
            'sort_order' => 2,
        ]);

        $u1 = User::create([
            'school_id' => $this->school->id,
            'username' => 'app_p125_1_'.uniqid(),
            'email' => 'app_p125_1_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $u1->roles()->attach($guestRole->id);

        $u2 = User::create([
            'school_id' => $this->school->id,
            'username' => 'app_p125_2_'.uniqid(),
            'email' => 'app_p125_2_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $u2->roles()->attach($guestRole->id);

        $sharedPhone = '081299998888';

        $app1 = Applicant::create([
            'user_id' => $u1->id,
            'wave_id' => $wave->id,
            'track_id' => $trackA->id,
            'registration_no' => 'PPDB-P125-001',
            'full_name' => 'Keluarga A Siswa',
            'nik_enc' => 'enc_p125_a',
            'birth_date' => '2012-01-10',
            'status' => 'selected',
            'decision' => 'diterima',
            'step_data' => ['personal' => ['gender' => 'L']],
        ]);
        ApplicantParent::create([
            'applicant_id' => $app1->id,
            'parent_type' => 'ayah',
            'name' => 'Ayah Keluarga A',
            'phone' => $sharedPhone,
        ]);

        $app2 = Applicant::create([
            'user_id' => $u2->id,
            'wave_id' => $wave->id,
            'track_id' => $trackA->id,
            'registration_no' => 'PPDB-P125-002',
            'full_name' => 'Keluarga B Siswa',
            'nik_enc' => 'enc_p125_b',
            'birth_date' => '2012-02-10',
            'status' => 'selected',
            'decision' => 'diterima',
            'step_data' => ['personal' => ['gender' => 'P']],
        ]);
        ApplicantParent::create([
            'applicant_id' => $app2->id,
            'parent_type' => 'ayah',
            'name' => 'Ayah Keluarga B',
            'phone' => $sharedPhone,
        ]);

        // 1. Enroll dua keluarga dengan nomor HP identik -> harus menghasilkan 2 Guardian terisolasi
        $enrollReq = Request::create('/api/v1/ppdb/enroll', 'POST');
        $enrollReq->setUserResolver(fn () => $this->adminUser);

        $res1 = app(PPDBEnrollmentService::class)->enrollApplicant($app1->id, ['force_enroll' => true], $enrollReq);
        $res2 = app(PPDBEnrollmentService::class)->enrollApplicant($app2->id, ['force_enroll' => true], $enrollReq);

        $guardian1Id = $res1['activation_links'][0]['guardian_id'];
        $guardian2Id = $res2['activation_links'][0]['guardian_id'];
        $this->assertNotEquals($guardian1Id, $guardian2Id);
        $this->assertEquals(1, Guardian::findOrFail($guardian1Id)->students()->count());
        $this->assertEquals(1, Guardian::findOrFail($guardian2Id)->students()->count());

        // 2. Aktivasi akun wali via endpoint GET & POST /auth/activate-guardian (sekali pakai)
        $token = $res1['activation_links'][0]['activation_token'];
        $username = User::findOrFail($res1['activation_links'][0]['user_id'])->username;

        $inspectRes = $this->getJson('/api/v1/auth/activate-guardian?token='.urlencode($token));
        $inspectRes->assertStatus(200)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.username', $username);

        $activateRes = $this->postJson('/api/v1/auth/activate-guardian', [
            'token' => $token,
            'password' => 'SandiBaruP125!',
            'password_confirmation' => 'SandiBaruP125!',
        ]);
        $activateRes->assertStatus(200)->assertJsonPath('data.activated', true);

        // Replay token sama harus gagal 404
        $replayRes = $this->postJson('/api/v1/auth/activate-guardian', [
            'token' => $token,
            'password' => 'SandiLain99!',
            'password_confirmation' => 'SandiLain99!',
        ]);
        $replayRes->assertStatus(404);

        // Akun dapat login dengan kata sandi baru
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'identifier' => $username,
            'password' => 'SandiBaruP125!',
        ]);
        $loginRes->assertStatus(200);

        // 3. Verifikasi QR bukti PPDB (tanpa kebocoran PII)
        $receiptData = app(PPDBReceiptAndStatsService::class)->getReceiptData($app1->id, $enrollReq);
        $urlPath = parse_url($receiptData['verification_url'], PHP_URL_PATH);
        $shortToken = basename((string) $urlPath);

        $verifyRes = $this->getJson("/api/v1/public/verify/{$shortToken}");
        $verifyRes->assertStatus(200)
            ->assertJsonPath('data.document_type', 'ppdb_receipt')
            ->assertJsonPath('data.is_valid', true)
            ->assertJsonPath('data.registration_no', 'PPDB-P125-001')
            ->assertJsonMissingPath('data.applicant.nik');

        $invalidVerify = $this->getJson('/api/v1/public/verify/invalid_token_xyz_1234567890');
        $invalidVerify->assertStatus(404);

        // 4. escalateWaitlist menghormati track_id & re_registration_deadline_at > now()
        $promotedActive = Applicant::create([
            'wave_id' => $wave->id,
            'track_id' => $trackA->id,
            'registration_no' => 'PPDB-P125-ACT',
            'full_name' => 'Cadangan Aktif Track A',
            'nik_enc' => 'enc_p125_act',
            'birth_date' => '2012-03-10',
            'status' => 'selected',
            'decision' => 'diterima',
            'rank' => 2,
            're_registration_deadline_at' => Carbon::now()->addDays(2),
        ]);
        $otherTrackCandidate = Applicant::create([
            'wave_id' => $wave->id,
            'track_id' => $trackB->id,
            'registration_no' => 'PPDB-P125-TRKB',
            'full_name' => 'Kandidat Track B',
            'nik_enc' => 'enc_p125_b2',
            'birth_date' => '2012-04-10',
            'status' => 'selected',
            'decision' => 'diterima',
            'rank' => 1,
        ]);

        $selReq = Request::create('/api/v1/ppdb/escalate', 'POST');
        $selReq->setUserResolver(fn () => $this->adminUser);
        app(PPDBSelectionService::class)->escalateWaitlist($wave->id, [
            'confirmed' => true,
            'force' => true,
            'track_id' => $trackA->id,
        ], $selReq);

        $this->assertEquals('diterima', $promotedActive->fresh()->decision);
        $this->assertEquals('diterima', $otherTrackCandidate->fresh()->decision);
    }

    public function test_p1_26_finance_schedule_giro_sitesetting_and_void_notification(): void
    {
        // 1. Cek jadwal cron finance:send-due-reminders & finance:reconcile terdaftar + onOneServer
        $events = collect(app(Schedule::class)->events());
        $dueReminder = $events->first(fn ($e) => str_contains((string) $e->command, 'finance:send-due-reminders'));
        $reconcile = $events->first(fn ($e) => str_contains((string) $e->command, 'finance:reconcile'));

        $this->assertNotNull($dueReminder);
        $this->assertNotNull($reconcile);
        $this->assertTrue((bool) $dueReminder->onOneServer);
        $this->assertTrue((bool) $reconcile->onOneServer);

        // 2. ManualGiroPaymentChannel membaca SiteSetting
        SiteSetting::create([
            'school_id' => $this->school->id,
            'setting_key' => ManualGiroPaymentChannel::SETTING_KEY,
            'setting_value' => [
                'bank_name' => 'Bank Mandiri Syariah Lab',
                'account_name' => 'Bendahara SMP Lab T2B5',
                'account_number' => '777-888-2026',
                'notes' => 'Cantumkan nomor {invoice_number} di catatan.',
            ],
        ]);

        $studentUser = User::create([
            'school_id' => $this->school->id,
            'username' => 'siswa_fin_p126_'.uniqid(),
            'email' => 'siswa_fin_p126_'.uniqid().'@test.local',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $student = Student::create([
            'school_id' => $this->school->id,
            'user_id' => $studentUser->id,
            'nis' => 'P126-01',
            'nisn' => '00126001',
            'nik_enc' => 'enc_p126_1',
            'full_name' => 'Siswa Keuangan P126',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2011-06-06',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $feeType = FeeType::create([
            'school_id' => $this->school->id,
            'code' => 'SPP-P126',
            'name' => 'SPP P126',
            'category' => 'spp',
            'default_amount' => 400000,
            'is_recurring' => true,
            'is_active' => true,
        ]);

        $invoice = Invoice::create([
            'school_id' => $this->school->id,
            'invoice_number' => 'INV/2026/11/9901',
            'student_id' => $student->id,
            'period' => '2026-11',
            'due_date' => '2026-11-30',
            'total_amount' => 400000,
            'paid_amount' => 400000,
            'status' => 'lunas',
        ]);

        $instructions = app(ManualGiroPaymentChannel::class)->getPaymentInstructions($invoice);
        $this->assertEquals('Bank Mandiri Syariah Lab', $instructions['bank_name']);
        $this->assertEquals('777-888-2026', $instructions['account_number']);
        $this->assertStringContainsString('INV/2026/11/9901', $instructions['notes']);

        // 3. voidPayment membuat Notification resmi ke pembayar & siswa
        $payment = Payment::create([
            'school_id' => $this->school->id,
            'invoice_id' => $invoice->id,
            'amount' => 400000,
            'payment_date' => now(),
            'channel' => 'manual_transfer',
            'external_reference' => 'REF-P126-9901',
            'status' => 'terverifikasi',
            'submitted_by' => $studentUser->id,
            'verified_by' => $this->adminUser->id,
            'verified_at' => now(),
            'receipt_number' => 'KWT/2026/9901',
        ]);

        $voided = app(FinanceService::class)->voidPayment($payment, 'Dana transfer diretur bank', $this->adminUser);
        $this->assertEquals('void', $voided->status);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $studentUser->id,
            'notif_type' => 'finance_payment',
        ]);
        $notif = Notification::where('user_id', $studentUser->id)->firstOrFail();
        $this->assertStringContainsString('Void', $notif->title);
    }

    public function test_p1_32_dashboard_kpis_accurate_and_report_csv_blocks_formula_injection(): void
    {
        $ay = AcademicYear::create([
            'school_id' => $this->school->id,
            'name' => '2026/2027 P132',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
        ]);
        $semester = Semester::create([
            'academic_year_id' => $ay->id,
            'name' => 'Ganjil P132',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);
        $period = GradePeriod::create(['semester_id' => $semester->id, 'month' => 11, 'status' => 'open']);

        $gl7 = GradeLevel::create(['name' => 'Kelas 7 P132', 'sort_order' => 7]);
        $gl8 = GradeLevel::create(['name' => 'Kelas 8 P132', 'sort_order' => 8]);
        $class7 = SchoolClass::create(['school_id' => $this->school->id, 'academic_year_id' => $ay->id, 'grade_level_id' => $gl7->id, 'name' => 'VII-P132', 'capacity' => 30]);
        $class8 = SchoolClass::create(['school_id' => $this->school->id, 'academic_year_id' => $ay->id, 'grade_level_id' => $gl8->id, 'name' => 'VIII-P132', 'capacity' => 30]);

        $activeStudent = Student::create([
            'school_id' => $this->school->id,
            'nis' => 'P132-01',
            'nisn' => '00132001',
            'nik_enc' => 'enc_p132_1',
            'full_name' => '=CMD|"/C calc"!A0',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2011-03-03',
            'entry_date' => '2025-07-01',
            'status' => 'aktif',
        ]);
        // Siswa non-aktif tidak boleh terhitung di total_students
        Student::create([
            'school_id' => $this->school->id,
            'nis' => 'P132-02',
            'nisn' => '00132002',
            'nik_enc' => 'enc_p132_2',
            'full_name' => 'Alumni Lulus',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2010-03-03',
            'entry_date' => '2023-07-01',
            'status' => 'lulus',
        ]);

        // Siswa naik kelas: kelas 7 sudah left_at, kelas 8 aktif
        ClassStudent::create(['class_id' => $class7->id, 'student_id' => $activeStudent->id, 'joined_at' => '2025-07-01', 'left_at' => '2026-06-30']);
        ClassStudent::create(['class_id' => $class8->id, 'student_id' => $activeStudent->id, 'joined_at' => '2026-07-01']);

        $subject = Subject::create(['code' => 'SUB-P132', 'name' => 'IPA P132', 'subject_group' => 'wajib']);
        $comp = GradeComponent::create(['subject_id' => $subject->id, 'semester_id' => $semester->id, 'class_id' => $class8->id, 'code' => 'UH1', 'name' => '+SUM(A1)', 'weight' => 100.0]);
        Grade::create(['student_id' => $activeStudent->id, 'component_id' => $comp->id, 'period_id' => $period->id, 'value' => 88.0, 'source' => 'manual', 'entered_by' => $this->adminUser->id]);

        // Booking status 'diajukan' terhitung; tiket darurat status 'selesai' tidak terhitung
        $facility = Facility::create(['school_id' => $this->school->id, 'code' => 'LAB-P132', 'name' => 'Lab P132', 'category' => 'laboratorium', 'capacity' => 30]);
        Booking::create(['facility_id' => $facility->id, 'requested_by' => $this->adminUser->id, 'start_at' => Carbon::now()->addDay(), 'end_at' => Carbon::now()->addDay()->addHour(), 'purpose' => 'Uji booking', 'status' => 'diajukan']);
        Ticket::create(['school_id' => $this->school->id, 'reporter_id' => $this->adminUser->id, 'description' => '@IMPORT("evil")', 'priority' => 'darurat', 'status' => 'selesai']);
        Ticket::create(['school_id' => $this->school->id, 'reporter_id' => $this->adminUser->id, 'description' => 'Darurat Aktif', 'priority' => 'darurat', 'status' => 'pending']);

        $dashReq = Request::create('/api/v1/dashboard/executive', 'GET');
        $dashReq->setUserResolver(fn () => $this->adminUser);
        $dashData = app(DashboardService::class)->getExecutiveDashboard($dashReq);

        $this->assertEquals(1, $dashData['master_summary']['total_students']);
        $this->assertEquals(1, $dashData['master_summary']['pending_verifications']['facility_bookings']);
        $this->assertEquals(1, $dashData['infrastructure_status']['critical_tickets_count']);
        $this->assertCount(1, $dashData['grades_by_grade_level']);
        $this->assertEquals($gl8->id, $dashData['grades_by_grade_level'][0]['grade_level_id']);

        // Export CSV mensanitasi formula injection & UTF-8 BOM
        $repReq = Request::create('/api/v1/reports/grades/export', 'GET');
        $repReq->setUserResolver(fn () => $this->adminUser);
        $csvExport = app(ReportService::class)->exportReport('grades', [], $repReq);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csvExport['content']);
        $this->assertStringContainsString("'=CMD|", $csvExport['content']);
        $this->assertStringContainsString("'+SUM(A1)", $csvExport['content']);
        $this->assertStringNotContainsString("\n=CMD|", $csvExport['content']);
    }
}
