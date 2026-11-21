<?php

namespace Tests\Feature\Ntf;

use App\Jobs\ProcessReminderDispatchJob;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\ExamRoom;
use App\Models\Notification;
use App\Models\ReminderDispatch;
use App\Models\Role;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Ntf\ReminderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P0 — Queue Worker Integration Tests (Sprint 39)
 *
 * Menguji perilaku queue worker database secara nyata:
 * - Successful job execution via queue:work --once
 * - Transient failure and retry
 * - Worker crash recovery and lease expiration
 * - Anti-duplication idempotency under multiple worker executions
 */
class QueueWorkerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected ReminderService $reminderService;

    protected User $studentUser;

    protected Student $student;

    protected User $teacherUser;

    protected Teacher $teacher;

    protected Subject $subject;

    protected string $schoolId;

    protected function setUp(): void
    {
        parent::setUp();

        // Gunakan queue database connection untuk pengujian worker integrasi
        Config::set('queue.default', 'database');
        Config::set('queue.connections.database.table', 'queue_jobs');

        $this->reminderService = app(ReminderService::class);
        $this->schoolId = (string) Str::uuid();

        Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);

        $this->studentUser = User::create([
            'school_id' => $this->schoolId,
            'username' => 'siswa_queue_test',
            'email' => 'siswa_queue@sekolah.sch.id',
            'password_hash' => Hash::make('password'),
            'status' => 'active',
        ]);

        $this->student = Student::create([
            'school_id' => $this->schoolId,
            'user_id' => $this->studentUser->id,
            'nis' => '99001',
            'nisn' => '0099000001',
            'nik_enc' => '3171010101990001',
            'full_name' => 'Siswa Queue Test',
            'gender' => 'L',
            'birth_place' => 'Jakarta',
            'birth_date' => '2008-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $this->teacherUser = User::create([
            'school_id' => $this->schoolId,
            'username' => 'guru_queue_test',
            'email' => 'guru_queue@sekolah.sch.id',
            'password_hash' => Hash::make('password'),
            'status' => 'active',
        ]);

        $this->teacher = Teacher::create([
            'school_id' => $this->schoolId,
            'user_id' => $this->teacherUser->id,
            'nip' => '198501012010011005',
            'full_name' => 'Guru Queue Test',
            'status' => 'aktif',
        ]);

        $this->subject = Subject::create([
            'school_id' => $this->schoolId,
            'code' => 'Q-TEST',
            'name' => 'Queue Testing Subject',
            'subject_group' => 'umum',
        ]);
    }

    protected function tearDown(): void
    {
        Config::set('queue.default', 'sync');
        parent::tearDown();
    }

    /**
     * 1. Job Berhasil: Dispatch dikirim ke queue_jobs dan diproses oleh worker.
     */
    public function test_queue_worker_successfully_processes_reminder_job(): void
    {
        $exam = Exam::create([
            'school_id' => $this->schoolId,
            'subject_id' => $this->subject->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Ujian Queue Worker Sukses',
            'exam_type' => 'pts',
            'duration_min' => 60,
            'start_at' => Carbon::now()->addHours(5),
            'end_at' => Carbon::now()->addHours(6),
            'status' => 'scheduled',
        ]);

        $room = Room::create([
            'school_id' => $this->schoolId,
            'code' => 'R-Q1',
            'name' => 'Ruang Queue 1',
            'room_type' => 'kelas',
            'capacity' => 30,
        ]);

        $examRoom = ExamRoom::create([
            'exam_id' => $exam->id,
            'room_id' => $room->id,
            'session_no' => 1,
            'token' => 'QWERTY',
        ]);

        ExamParticipant::create([
            'exam_id' => $exam->id,
            'student_id' => $this->student->id,
            'exam_room_id' => $examRoom->id,
        ]);

        $dispatch = ReminderDispatch::create([
            'school_id' => $this->schoolId,
            'event_type' => 'cbt_exam',
            'event_id' => $exam->id,
            'recipient_id' => $this->studentUser->id,
            'milestone' => '24h',
            'channel' => 'in_app',
            'status' => 'pending',
            'metadata' => [
                'title' => 'Ujian Segera Mulai',
                'body' => 'Persiapkan diri Anda',
                'notif_type' => 'academic_announcement',
            ],
        ]);

        // Push job ke antrean database
        ProcessReminderDispatchJob::dispatch($dispatch->id);

        // Verifikasi job tercatat di tabel queue_jobs
        $this->assertEquals(1, DB::table('queue_jobs')->count());
        $this->assertEquals('pending', $dispatch->fresh()->status);

        // Jalankan worker satu siklus (queue:work --once)
        Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);

        // Verifikasi job selesai dan terhapus dari queue_jobs
        $this->assertEquals(0, DB::table('queue_jobs')->count());

        // Verifikasi dispatch berubah menjadi 'dispatched'
        $dispatch->refresh();
        $this->assertEquals('dispatched', $dispatch->status);
        $this->assertNull($dispatch->claim_token);

        // Verifikasi notifikasi terkirim tepat 1
        $this->assertEquals(1, Notification::where('user_id', $this->studentUser->id)->count());
    }

    /**
     * 2. Job Gagal karena Transient Error dan Dicoba Ulang.
     */
    public function test_queue_worker_handles_transient_failure_and_retries_successfully(): void
    {
        $exam = Exam::create([
            'school_id' => $this->schoolId,
            'subject_id' => $this->subject->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Ujian Transient Error',
            'exam_type' => 'pts',
            'duration_min' => 60,
            'start_at' => Carbon::now()->addHours(5),
            'end_at' => Carbon::now()->addHours(6),
            'status' => 'scheduled',
        ]);

        $dispatch = ReminderDispatch::create([
            'school_id' => $this->schoolId,
            'event_type' => 'cbt_exam',
            'event_id' => $exam->id,
            'recipient_id' => $this->studentUser->id,
            'milestone' => '24h',
            'channel' => 'in_app',
            'status' => 'failed',
            'error_message' => 'Simulated temporary redis connection timeout',
            'metadata' => [
                'title' => 'Pengingat Transient',
                'body' => 'Gagal sementara',
                'notif_type' => 'academic_announcement',
            ],
        ]);

        // Retry via ReminderService
        $retried = $this->reminderService->retryFailedDispatches($this->schoolId, 50, false);
        $this->assertEquals(1, $retried);

        $dispatch->refresh();
        $this->assertEquals('dispatched', $dispatch->status);
        $this->assertNull($dispatch->error_message);
        $this->assertEquals(1, Notification::where('user_id', $this->studentUser->id)->count());
    }

    /**
     * 3. Worker Crash & Pemulihan Stuck Processing via Scheduler / Command.
     */
    public function test_queue_worker_crash_recovery_resumes_cleanly(): void
    {
        $exam = Exam::create([
            'school_id' => $this->schoolId,
            'subject_id' => $this->subject->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Ujian Crash Recovery',
            'exam_type' => 'pts',
            'duration_min' => 60,
            'start_at' => Carbon::now()->addHours(5),
            'end_at' => Carbon::now()->addHours(6),
            'status' => 'scheduled',
        ]);

        $dispatch = ReminderDispatch::create([
            'school_id' => $this->schoolId,
            'event_type' => 'cbt_exam',
            'event_id' => $exam->id,
            'recipient_id' => $this->studentUser->id,
            'milestone' => '24h',
            'channel' => 'in_app',
            'status' => 'processing',
            'claim_token' => 'crashed-worker-token-xyz',
            'claim_expires_at' => Carbon::now()->subMinutes(10), // Lease expired
            'metadata' => [
                'title' => 'Ujian Worker Crash',
                'body' => 'Simulasi worker OOM/SIGKILL',
                'notif_type' => 'academic_announcement',
            ],
        ]);

        $dispatch->updated_at = Carbon::now()->subMinutes(30);
        $dispatch->save();

        // Eksekusi pemulihan stuck dispatches
        $recovered = $this->reminderService->recoverStuckProcessing(15, false);
        $this->assertEquals(1, $recovered);

        $dispatch->refresh();
        $this->assertEquals('dispatched', $dispatch->status);
        $this->assertNull($dispatch->claim_token);
        $this->assertEquals(1, Notification::where('user_id', $this->studentUser->id)->count());
    }

    /**
     * 4. Idempotency: Eksekusi berulang job yang sama tidak memicu duplikasi notifikasi.
     */
    public function test_repeated_worker_executions_strictly_produce_single_notification(): void
    {
        $exam = Exam::create([
            'school_id' => $this->schoolId,
            'subject_id' => $this->subject->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Ujian Anti Duplicate',
            'exam_type' => 'pts',
            'duration_min' => 60,
            'start_at' => Carbon::now()->addHours(5),
            'end_at' => Carbon::now()->addHours(6),
            'status' => 'scheduled',
        ]);

        $dispatch = ReminderDispatch::create([
            'school_id' => $this->schoolId,
            'event_type' => 'cbt_exam',
            'event_id' => $exam->id,
            'recipient_id' => $this->studentUser->id,
            'milestone' => '24h',
            'channel' => 'in_app',
            'status' => 'pending',
            'metadata' => [
                'title' => 'Ujian Anti Duplicate',
                'body' => 'Badan notifikasi',
                'notif_type' => 'academic_announcement',
            ],
        ]);

        // Push 3 job duplikat untuk dispatch yang sama
        ProcessReminderDispatchJob::dispatch($dispatch->id);
        ProcessReminderDispatchJob::dispatch($dispatch->id);
        ProcessReminderDispatchJob::dispatch($dispatch->id);

        $this->assertEquals(3, DB::table('queue_jobs')->count());

        // Jalankan worker untuk memproses semua job di antrean secara tuntas
        while (DB::table('queue_jobs')->count() > 0) {
            Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);
        }

        // Seluruh job terproses dan terhapus
        $this->assertEquals(0, DB::table('queue_jobs')->count());

        // Verifikasi hasil: TEPAT 1 notifikasi di inbox penerima
        $this->assertEquals(1, Notification::where('user_id', $this->studentUser->id)->count());

        $dispatch->refresh();
        $this->assertEquals('dispatched', $dispatch->status);
    }
}
