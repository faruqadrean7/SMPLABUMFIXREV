<?php

namespace Tests\Feature\Console;

use App\Models\Attendance;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Partnership;
use App\Models\PPDBWave;
use App\Models\Role;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScheduledJobsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $this->adminUser = User::create([
            'username' => 'admin_sched',
            'email' => 'admin_sched@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);
    }

    public function test_be_sched_01_all_seven_jobs_are_registered_in_scheduler(): void
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $commands = $events->map(fn ($event) => $event->command)->filter()->implode(' ');

        $this->assertStringContainsString('ppdb:close-wave', $commands);
        $this->assertStringContainsString('exam:auto-submit', $commands);
        $this->assertStringContainsString('attendance:daily-summary', $commands);
        $this->assertStringContainsString('academic:progress-report-reminder', $commands);
        $this->assertStringContainsString('partnership:mou-expiry-reminder', $commands);
        $this->assertStringContainsString('sarpras:ticket-sla-reminder', $commands);
        $this->assertStringContainsString('system:backup-verify', $commands);
    }

    public function test_be_sched_01_ppdb_close_wave_closes_expired_waves(): void
    {
        // 1. Gelombang aktif tapi close_at sudah lewat
        $expiredWave = PPDBWave::create([
            'name' => 'Gelombang 1 Reguler',
            'open_at' => Carbon::now()->subDays(10),
            'close_at' => Carbon::now()->subMinute(),
            'result_publish_at' => Carbon::now()->addDays(5),
            'status' => 'aktif',
        ]);

        // 2. Gelombang aktif masih berlaku
        $activeWave = PPDBWave::create([
            'name' => 'Gelombang 2 Prestasi',
            'open_at' => Carbon::now()->subDay(),
            'close_at' => Carbon::now()->addDays(5),
            'result_publish_at' => Carbon::now()->addDays(10),
            'status' => 'aktif',
        ]);

        $this->artisan('ppdb:close-wave')
            ->expectsOutputToContain('Berhasil menutup 1 gelombang PPDB')
            ->assertExitCode(0);

        $this->assertEquals('ditutup', $expiredWave->fresh()->status);
        $this->assertEquals('aktif', $activeWave->fresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'PPDB_WAVE_AUTO_CLOSED',
            'entity' => 'ppdb_waves',
            'entity_id' => $expiredWave->id,
        ]);
    }

    public function test_be_sched_01_exam_auto_submit_submits_expired_cbt_sessions(): void
    {
        $subject = Subject::create([
            'name' => 'Fisika',
            'code' => 'FIS',
            'subject_group' => 'peminatan',
        ]);

        $exam = Exam::create([
            'subject_id' => $subject->id,
            'created_by' => $this->adminUser->id,
            'title' => 'PAS Fisika',
            'exam_type' => 'semester',
            'duration_min' => 60,
            'start_at' => Carbon::now()->subHours(2),
            'end_at' => Carbon::now()->addHour(),
            'status' => 'berlangsung',
        ]);

        $studentUser = User::create([
            'username' => 'siswa_cbt_sched',
            'email' => 'siswa_cbt_sched@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'nis' => '77001',
            'nisn' => '0077001',
            'nik_enc' => '3578010101017701',
            'full_name' => 'Budi CBT',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2008-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'active',
        ]);

        // Sesi yang melewati batas deadline
        $expiredSession = ExamSession::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'seed' => 12345,
            'status' => 'in_progress',
            'started_at' => Carbon::now()->subMinutes(70),
            'deadline_at' => Carbon::now()->subMinutes(10),
        ]);

        $this->artisan('exam:auto-submit')
            ->expectsOutputToContain('Berhasil mengumpulkan otomatis 1 sesi ujian CBT')
            ->assertExitCode(0);

        $freshSession = $expiredSession->fresh();
        $this->assertEquals('selesai', $freshSession->status);
        $this->assertNotNull($freshSession->submitted_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'EXAM_SESSION_AUTO_SUBMITTED',
            'entity' => 'exam_sessions',
            'entity_id' => $expiredSession->id,
        ]);
    }

    public function test_be_sched_01_daily_attendance_summary_and_early_warning(): void
    {
        $studentUser = User::create([
            'username' => 'siswa_att_sched',
            'email' => 'siswa_att_sched@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'nis' => '77002',
            'nisn' => '0077002',
            'nik_enc' => '3578010101017702',
            'full_name' => 'Citra Absen',
            'gender' => 'P',
            'birth_place' => 'Surabaya',
            'birth_date' => '2008-02-02',
            'entry_date' => '2026-07-01',
            'status' => 'active',
        ]);

        // 3 alpa dalam 30 hari terakhir
        Attendance::create(['student_id' => $student->id, 'att_date' => Carbon::today()->toDateString(), 'status' => 'alpa']);
        Attendance::create(['student_id' => $student->id, 'att_date' => Carbon::today()->subDays(2)->toDateString(), 'status' => 'alpa']);
        Attendance::create(['student_id' => $student->id, 'att_date' => Carbon::today()->subDays(5)->toDateString(), 'status' => 'alpa']);

        $this->artisan('attendance:daily-summary')
            ->expectsOutputToContain('Rekap Presensi Tanggal')
            ->assertExitCode(0);

        $this->assertDatabaseHas('early_warnings', [
            'student_id' => $student->id,
            'rule_code' => 'ATT_LOW',
            'status' => 'aktif',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'DAILY_ATTENDANCE_SUMMARY_PROCESSED',
            'entity' => 'attendances',
        ]);
    }

    public function test_be_sched_01_progress_report_reminder_sends_notifications(): void
    {
        $teacherUser = User::create([
            'username' => 'guru_sched',
            'email' => 'guru_sched@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $teacher = Teacher::create([
            'user_id' => $teacherUser->id,
            'full_name' => 'Ibu Sri Guru',
            'nip' => '198501012010012001',
            'status' => 'tetap',
        ]);

        $this->artisan('academic:progress-report-reminder')
            ->expectsOutputToContain('Berhasil mengirim pengingat pengisian nilai dan karakter')
            ->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $teacherUser->id,
            'notif_type' => 'academic_reminder',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'PROGRESS_REPORT_REMINDER_SENT',
            'entity' => 'notifications',
        ]);
    }

    public function test_be_sched_01_mou_expiry_reminder_updates_and_alerts(): void
    {
        // 1. MoU sudah kedaluwarsa
        $expiredMou = Partnership::create([
            'partner_name' => 'PT Industri Lama',
            'mou_number' => 'MOU/2025/001',
            'partner_type' => 'dudi',
            'scope' => 'Kerjasama DUDI & Magang',
            'valid_from' => '2025-01-01',
            'valid_until' => Carbon::today()->subDay()->toDateString(),
            'status' => 'active',
        ]);

        // 2. MoU yang akan habis dalam 10 hari
        $expiringMou = Partnership::create([
            'partner_name' => 'Universitas Mitra',
            'mou_number' => 'MOU/2026/002',
            'partner_type' => 'ptn',
            'scope' => 'Program Beasiswa Kampus Mitra',
            'valid_from' => '2025-10-01',
            'valid_until' => Carbon::today()->addDays(10)->toDateString(),
            'status' => 'active',
        ]);

        $this->artisan('partnership:mou-expiry-reminder')
            ->expectsOutputToContain('Pengecekan MoU selesai')
            ->assertExitCode(0);

        $this->assertEquals('expired', $expiredMou->fresh()->status);
        $this->assertEquals('active', $expiringMou->fresh()->status);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->adminUser->id,
            'notif_type' => 'partnership_reminder',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'MOU_EXPIRY_CHECK_PROCESSED',
            'entity' => 'partnerships',
        ]);
    }

    public function test_be_sched_01_ticket_sla_reminder_detects_breaches(): void
    {
        $roleFacility = Role::firstOrCreate(['code' => 'facility_staff'], ['name' => 'Staf Sarpras', 'is_system' => true]);
        $facilityUser = User::create([
            'username' => 'staf_sarpras_sched',
            'email' => 'sarpras_sched@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $facilityUser->roles()->attach($roleFacility->id);

        // Tiket darurat (SLA 4 jam) dibuat 6 jam lalu
        $ticket = Ticket::create([
            'ticket_number' => 'TCK-SLA-001',
            'title' => 'Pipa Utama Pecah Banjir',
            'reporter_id' => $this->adminUser->id,
            'assignee_id' => $facilityUser->id,
            'description' => 'Air meluap ke ruang kelas',
            'priority' => 'darurat',
            'status' => 'pending',
        ]);
        // Set created_at to 6 hours ago
        $ticket->created_at = Carbon::now()->subHours(6);
        $ticket->save();

        $this->artisan('sarpras:ticket-sla-reminder')
            ->expectsOutputToContain('Pemeriksaan SLA tiket selesai: 1 tiket melewati SLA')
            ->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $facilityUser->id,
            'notif_type' => 'ticket_sla_breach',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'TICKET_SLA_CHECK_PROCESSED',
            'entity' => 'tickets',
        ]);
    }

    public function test_be_sched_01_backup_verify_executes_successfully(): void
    {
        $this->artisan('system:backup-verify')
            ->expectsOutputToContain('Verifikasi backup mingguan berhasil diselesaikan')
            ->assertExitCode(0);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'BACKUP_VERIFY_RUN',
            'entity' => 'system',
        ]);
    }
}
