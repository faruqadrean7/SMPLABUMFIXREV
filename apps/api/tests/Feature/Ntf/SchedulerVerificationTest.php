<?php

namespace Tests\Feature\Ntf;

use App\Models\Exam;
use App\Models\ReminderDispatch;
use App\Models\Role;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P0 — Scheduler & Timezone Verification Tests (Sprint 39)
 */
class SchedulerVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected Schedule $schedule;

    protected string $schoolId;

    protected User $teacherUser;

    protected Teacher $teacher;

    protected Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schedule = app(Schedule::class);
        $this->schoolId = (string) Str::uuid();

        Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);

        $this->teacherUser = User::create([
            'school_id' => $this->schoolId,
            'username' => 'guru_sched_test',
            'email' => 'guru_sched@sekolah.sch.id',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
        ]);

        $this->teacher = Teacher::create([
            'school_id' => $this->schoolId,
            'user_id' => $this->teacherUser->id,
            'nip' => '198001012005011003',
            'full_name' => 'Guru Scheduler Test',
            'status' => 'aktif',
        ]);

        $this->subject = Subject::create([
            'school_id' => $this->schoolId,
            'code' => 'SCHED-01',
            'name' => 'Scheduler Subject',
            'subject_group' => 'umum',
        ]);
    }

    /**
     * 1. Verifikasi pendaftaran jadwal pada Schedule instance Laravel.
     */
    public function test_reminder_scheduler_events_are_registered_with_correct_intervals_and_timezones(): void
    {
        $events = collect($this->schedule->events());

        // A. Dispatcher Berkala (every 15 minutes, withoutOverlapping)
        $periodicEvent = $events->first(function (Event $e) {
            return str_contains($e->command ?? '', 'reminder:dispatch --type=all');
        });

        $this->assertNotNull($periodicEvent, 'Command reminder:dispatch --type=all must be scheduled');
        $this->assertEquals('*/15 * * * *', $periodicEvent->expression);
        $this->assertTrue($periodicEvent->withoutOverlapping, 'Periodic dispatch must use withoutOverlapping');
        $this->assertEquals(10, $periodicEvent->expiresAt, 'Mutex timeout must be 10 minutes');

        // B. Presensi Harian 10:00 Asia/Jakarta
        $attEvent = $events->first(function (Event $e) {
            return str_contains($e->command ?? '', 'reminder:dispatch --type=att');
        });

        $this->assertNotNull($attEvent, 'Command reminder:dispatch --type=att must be scheduled');
        $this->assertEquals('0 10 * * *', $attEvent->expression);
        $this->assertEquals('Asia/Jakarta', $attEvent->timezone);
        $this->assertTrue($attEvent->withoutOverlapping);
        $this->assertEquals(10, $attEvent->expiresAt);

        // C. Recovery Berkala (Hourly)
        $recoveryEvent = $events->first(function (Event $e) {
            return str_contains($e->command ?? '', 'reminder:dispatch --recover-stuck');
        });

        $this->assertNotNull($recoveryEvent, 'Command reminder:dispatch --recover-stuck must be scheduled');
        $this->assertEquals('0 * * * *', $recoveryEvent->expression);
        $this->assertTrue($recoveryEvent->withoutOverlapping);
        $this->assertEquals(10, $recoveryEvent->expiresAt);
    }

    /**
     * 2. Eksekusi scheduler berulang tidak menduplikasi planning atau notifikasi.
     */
    public function test_repeated_scheduler_executions_are_strictly_idempotent(): void
    {
        $exam = Exam::create([
            'school_id' => $this->schoolId,
            'subject_id' => $this->subject->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Ujian Scheduler Idempotent',
            'exam_type' => 'pts',
            'duration_min' => 60,
            'start_at' => Carbon::now()->addHours(5),
            'end_at' => Carbon::now()->addHours(6),
            'status' => 'scheduled',
        ]);

        // Jalankan perintah scheduler pertama kali
        $exit1 = Artisan::call('reminder:dispatch', [
            '--type' => 'cbt',
            '--school' => $this->schoolId,
        ]);
        $this->assertEquals(0, $exit1);

        $dispatchesCount1 = ReminderDispatch::count();

        // Jalankan perintah scheduler kedua kali dalam jendela yang sama
        $exit2 = Artisan::call('reminder:dispatch', [
            '--type' => 'cbt',
            '--school' => $this->schoolId,
        ]);
        $this->assertEquals(0, $exit2);

        $dispatchesCount2 = ReminderDispatch::count();

        // Jumlah record di tabel reminder_dispatches tidak bertambah
        $this->assertEquals($dispatchesCount1, $dispatchesCount2);
    }

    /**
     * 3. Verifikasi Mutex Lock Recovery.
     */
    public function test_scheduler_mutex_lock_can_be_recovered_safely(): void
    {
        $events = collect($this->schedule->events());
        $periodicEvent = $events->first(function (Event $e) {
            return str_contains($e->command ?? '', 'reminder:dispatch --type=all');
        });

        $mutexName = $periodicEvent->mutexName();
        $this->assertNotEmpty($mutexName);

        // Pasang mutex buatan seolah worker mati saat memegang lock
        $mutex = $periodicEvent->mutex;
        $this->assertTrue($mutex->create($periodicEvent));

        // Verifikasi event terkunci saat ini
        $this->assertTrue($mutex->exists($periodicEvent));

        // Bersihkan lock (simulasi crash recovery atau timeout expiration)
        $mutex->forget($periodicEvent);

        // Verifikasi lock dapat diperoleh kembali oleh scheduler berikutnya
        $this->assertFalse($mutex->exists($periodicEvent));
        $this->assertTrue($mutex->create($periodicEvent));
        $mutex->forget($periodicEvent);
    }
}
