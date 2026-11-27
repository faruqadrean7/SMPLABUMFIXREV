<?php

namespace Tests\Concurrency;

use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\FeeType;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradeHistory;
use App\Models\GradePeriod;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PPDBReRegistration;
use App\Models\PPDBTrack;
use App\Models\PPDBWave;
use App\Models\Role;
use App\Models\School;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\GradeRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * T2B-CONC-01: Suite permanen uji konkurensi PostgreSQL multi-proses nyata.
 *
 * Setiap skenario meluncurkan beberapa child OS process PHP (`proc_open` + barrier timestamp)
 * yang masing-masing membuka sesi PostgreSQL independen (`pg_backend_pid()` berbeda)
 * untuk memverifikasi keamanan race condition pada Finance, PPDB, Role::ensureSystemRole,
 * dan GradeRecorder.
 */
class Tier2PostgreSqlConcurrencyTest extends TestCase
{
    private bool $pgReady = false;

    /** @var array<string, mixed> */
    private array $pgConfig = [];

    private ?School $school = null;

    private ?User $admin = null;

    private ?User $studentUser = null;

    private ?Student $student = null;

    private ?FeeType $feeType = null;

    /** @var list<string> */
    private array $createdUserIds = [];

    /** @var list<string> */
    private array $createdStudentIds = [];

    /** @var list<string> */
    private array $createdInvoiceIds = [];

    /** @var list<string> */
    private array $createdApplicantIds = [];

    /** @var list<string> */
    private array $createdWaveIds = [];

    /** @var list<string> */
    private array $createdRoleCodes = [];

    /** @var list<string> */
    private array $createdComponentIds = [];

    /** @var list<string> */
    private array $createdPeriodIds = [];

    /** @var list<string> */
    private array $createdSemesterIds = [];

    /** @var list<string> */
    private array $createdAcademicYearIds = [];

    /** @var list<string> */
    private array $createdSubjectIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $requirePg = filter_var(env('PG_CONCURRENCY_REQUIRED', false), FILTER_VALIDATE_BOOL);
        $defaultDriver = (string) config('database.default', 'pgsql');

        if ($defaultDriver !== 'pgsql' && ! env('PG_CONCURRENCY_HOST') && ! $requirePg) {
            $this->markTestSkipped('Driver database aktif bukan pgsql dan PG_CONCURRENCY_HOST tidak disetel.');
        }

        $this->pgConfig = $this->resolvePgConnectionConfig();
        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql' => $this->pgConfig,
        ]);
        DB::purge('pgsql');

        $error = null;
        try {
            $pid = (int) (DB::selectOne('SELECT pg_backend_pid() AS pid')->pid ?? 0);
            $this->pgReady = $pid > 0;
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            $this->pgReady = false;
        }

        if (! $this->pgReady) {
            $msg = sprintf(
                'PostgreSQL concurrency database (%s:%s/%s) tidak tersedia%s.',
                $this->pgConfig['host'],
                $this->pgConfig['port'],
                $this->pgConfig['database'],
                $error ? ': '.$error : ''
            );

            if ($requirePg) {
                $this->fail($msg);
            }

            $this->markTestSkipped($msg);
        }

        $suffix = Str::lower(Str::random(8));

        $this->school = School::create([
            'name' => 'SMP Lab Concurrency '.$suffix,
            'npsn' => (string) random_int(10000000, 99999999),
            'level' => 'smp',
            'status' => 'active',
        ]);

        $this->admin = User::create([
            'school_id' => $this->school->id,
            'username' => 'conc_admin_'.$suffix,
            'email' => 'conc_admin_'.$suffix.'@sekolah.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->createdUserIds[] = $this->admin->id;

        $this->studentUser = User::create([
            'school_id' => $this->school->id,
            'username' => 'conc_stu_'.$suffix,
            'email' => 'conc_stu_'.$suffix.'@siswa.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->createdUserIds[] = $this->studentUser->id;

        $this->student = Student::create([
            'school_id' => $this->school->id,
            'user_id' => $this->studentUser->id,
            'nis' => '99'.random_int(1000000, 9999999),
            'nisn' => '00'.random_int(10000000, 99999999),
            'nik_enc' => '35730101'.random_int(10000000, 99999999),
            'full_name' => 'Siswa Uji Konkurensi '.$suffix,
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-05-10',
            'status' => 'aktif',
            'entry_date' => '2025-07-01',
        ]);
        $this->createdStudentIds[] = $this->student->id;

        $this->feeType = FeeType::create([
            'school_id' => $this->school->id,
            'code' => 'SPP-'.strtoupper($suffix),
            'name' => 'SPP Uji Konkurensi '.$suffix,
            'category' => 'spp',
            'default_amount' => 500000,
            'is_recurring' => true,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->pgReady) {
            try {
                if (! empty($this->createdComponentIds)) {
                    DB::table('grade_histories')->whereIn('component_id', $this->createdComponentIds)->delete();
                    DB::table('grades')->whereIn('component_id', $this->createdComponentIds)->delete();
                    DB::table('grade_components')->whereIn('id', $this->createdComponentIds)->delete();
                }

                if (! empty($this->createdPeriodIds)) {
                    DB::table('grade_periods')->whereIn('id', $this->createdPeriodIds)->delete();
                }

                if (! empty($this->createdSemesterIds)) {
                    DB::table('semesters')->whereIn('id', $this->createdSemesterIds)->delete();
                }

                if (! empty($this->createdAcademicYearIds)) {
                    DB::table('academic_years')->whereIn('id', $this->createdAcademicYearIds)->delete();
                }

                if (! empty($this->createdSubjectIds)) {
                    DB::table('subjects')->whereIn('id', $this->createdSubjectIds)->delete();
                }

                if (! empty($this->createdApplicantIds)) {
                    $enrolledStudentIds = DB::table('applicants')
                        ->whereIn('id', $this->createdApplicantIds)
                        ->whereNotNull('student_id')
                        ->pluck('student_id')
                        ->all();

                    foreach ($enrolledStudentIds as $sid) {
                        $this->createdStudentIds[] = (string) $sid;
                    }

                    DB::table('applicant_status_history')->whereIn('applicant_id', $this->createdApplicantIds)->delete();
                    DB::table('ppdb_re_registrations')->whereIn('applicant_id', $this->createdApplicantIds)->delete();
                    DB::table('applicants')->whereIn('id', $this->createdApplicantIds)->delete();
                }

                if (! empty($this->createdWaveIds)) {
                    DB::table('ppdb_tracks')->whereIn('wave_id', $this->createdWaveIds)->delete();
                    DB::table('ppdb_waves')->whereIn('id', $this->createdWaveIds)->delete();
                }

                if ($this->school) {
                    $schoolInvoiceIds = DB::table('invoices')->where('school_id', $this->school->id)->pluck('id')->all();
                    $allInvoiceIds = array_values(array_unique(array_merge($this->createdInvoiceIds, $schoolInvoiceIds)));

                    if (! empty($allInvoiceIds)) {
                        DB::table('payments')->whereIn('invoice_id', $allInvoiceIds)->delete();
                        DB::table('invoice_items')->whereIn('invoice_id', $allInvoiceIds)->delete();
                        DB::table('invoices')->whereIn('id', $allInvoiceIds)->delete();
                    }

                    DB::table('fee_types')->where('school_id', $this->school->id)->delete();
                }

                $uniqueStudentIds = array_values(array_unique($this->createdStudentIds));
                if (! empty($uniqueStudentIds)) {
                    $studentUserIds = DB::table('students')
                        ->whereIn('id', $uniqueStudentIds)
                        ->whereNotNull('user_id')
                        ->pluck('user_id')
                        ->all();
                    foreach ($studentUserIds as $uid) {
                        $this->createdUserIds[] = (string) $uid;
                    }

                    DB::table('student_status_history')->whereIn('student_id', $uniqueStudentIds)->delete();
                    DB::table('student_guardians')->whereIn('student_id', $uniqueStudentIds)->delete();
                    DB::table('students')->whereIn('id', $uniqueStudentIds)->delete();
                }

                $uniqueUserIds = array_values(array_unique($this->createdUserIds));
                if (! empty($uniqueUserIds)) {
                    DB::table('audit_logs')->whereIn('actor_id', $uniqueUserIds)->delete();
                    DB::table('notifications')->whereIn('user_id', $uniqueUserIds)->delete();
                    DB::table('user_roles')->whereIn('user_id', $uniqueUserIds)->delete();
                    DB::table('users')->whereIn('id', $uniqueUserIds)->delete();
                }

                if (! empty($this->createdRoleCodes)) {
                    DB::table('roles')->whereIn('code', $this->createdRoleCodes)->delete();
                }

                if ($this->school) {
                    DB::table('schools')->where('id', $this->school->id)->delete();
                }
            } finally {
                DB::disconnect('pgsql');
            }
        }

        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePgConnectionConfig(): array
    {
        $base = config('database.connections.pgsql', []);

        $host = env('PG_CONCURRENCY_HOST');
        $port = env('PG_CONCURRENCY_PORT');
        $database = env('PG_CONCURRENCY_DATABASE');
        $username = env('PG_CONCURRENCY_USERNAME');
        $password = env('PG_CONCURRENCY_PASSWORD');
        $sslmode = env('PG_CONCURRENCY_SSLMODE');

        return array_merge($base, [
            'driver' => 'pgsql',
            'host' => ($host !== null && $host !== '') ? $host : ($base['host'] ?? '127.0.0.1'),
            'port' => (int) (($port !== null && $port !== '') ? $port : ($base['port'] ?? 5432)),
            'database' => ($database !== null && $database !== '') ? $database : ($base['database'] ?? 'sistlabum_testing'),
            'username' => ($username !== null && $username !== '') ? $username : ($base['username'] ?? 'postgres'),
            'password' => $password !== null ? (string) $password : (string) ($base['password'] ?? ''),
            'charset' => $base['charset'] ?? 'utf8',
            'prefix' => '',
            'search_path' => $base['search_path'] ?? $base['schema'] ?? 'public',
            'sslmode' => ($sslmode !== null && $sslmode !== '') ? $sslmode : ($base['sslmode'] ?? 'prefer'),
        ]);
    }

    /**
     * Luncurkan N child process PHP paralel yang disinkronkan pada barrier timestamp.
     *
     * @param  list<array<string, mixed>>  $jobs
     * @return list<array<string, mixed>>
     */
    private function runParallelWorkers(array $jobs, float $leadSeconds = 0.5): array
    {
        $workerScript = __DIR__.'/worker.php';
        $barrierAt = microtime(true) + $leadSeconds;

        $workerEnv = array_merge(getenv(), [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql',
            'PG_CONCURRENCY_HOST' => (string) $this->pgConfig['host'],
            'PG_CONCURRENCY_PORT' => (string) $this->pgConfig['port'],
            'PG_CONCURRENCY_DATABASE' => (string) $this->pgConfig['database'],
            'PG_CONCURRENCY_USERNAME' => (string) $this->pgConfig['username'],
            'PG_CONCURRENCY_PASSWORD' => (string) $this->pgConfig['password'],
            'PG_CONCURRENCY_SSLMODE' => (string) ($this->pgConfig['sslmode'] ?? 'prefer'),
        ]);

        $procs = [];
        $pipesList = [];

        foreach ($jobs as $idx => $job) {
            $job['barrier_at'] = $barrierAt;
            $cmd = [PHP_BINARY, $workerScript, json_encode($job, JSON_THROW_ON_ERROR)];
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $proc = proc_open($cmd, $descriptors, $pipes, base_path(), $workerEnv);
            $this->assertTrue(is_resource($proc), "Gagal meluncurkan worker proses #{$idx}");

            fclose($pipes[0]);
            $procs[$idx] = $proc;
            $pipesList[$idx] = $pipes;
        }

        $results = [];
        foreach ($procs as $idx => $proc) {
            $stdout = stream_get_contents($pipesList[$idx][1]);
            $stderr = stream_get_contents($pipesList[$idx][2]);
            fclose($pipesList[$idx][1]);
            fclose($pipesList[$idx][2]);
            $exitCode = proc_close($proc);

            $decoded = json_decode((string) $stdout, true);
            if (! is_array($decoded)) {
                $results[$idx] = [
                    'ok' => false,
                    'type' => 'process_error',
                    'exit_code' => $exitCode,
                    'stdout' => $stdout,
                    'stderr' => $stderr,
                ];
            } else {
                $results[$idx] = $decoded;
            }
        }

        return $results;
    }

    /**
     * @param  list<array<string, mixed>>  $results
     */
    private function assertDistinctPgPids(array $results, int $expectedCount): void
    {
        $pids = array_values(array_unique(array_filter(array_column($results, 'pg_pid'))));
        $this->assertCount(
            $expectedCount,
            $pids,
            'Setiap worker wajib menggunakan pg_backend_pid PostgreSQL berbeda. Hasil: '.json_encode($results)
        );
    }

    private function createInvoiceRecord(string $codeSuffix, int $totalAmount = 1000000): Invoice
    {
        $invoice = Invoice::create([
            'school_id' => $this->school->id,
            'invoice_number' => 'INV/T2B/'.strtoupper($codeSuffix).'/'.Str::upper(Str::random(6)),
            'student_id' => $this->student->id,
            'period' => date('Y-m'),
            'due_date' => now()->addDays(10),
            'total_amount' => $totalAmount,
            'paid_amount' => 0,
            'status' => 'belum_bayar',
        ]);
        $this->createdInvoiceIds[] = $invoice->id;

        return $invoice;
    }

    private function createPendingPayment(Invoice $invoice, int $amount): Payment
    {
        return Payment::create([
            'school_id' => $this->school->id,
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'channel' => 'Transfer Bank',
            'external_reference' => 'EXT-'.Str::upper(Str::random(10)),
            'payment_date' => now()->toDateString(),
            'status' => 'menunggu_verifikasi',
            'submitted_by' => $this->studentUser->id,
        ]);
    }

    private function createAcceptedApplicant(PPDBWave $wave, PPDBTrack $track, int $idx): Applicant
    {
        $suffix = Str::lower(Str::random(6));
        $user = User::create([
            'school_id' => $this->school->id,
            'username' => "ppdb_conc_{$idx}_{$suffix}",
            'email' => "ppdb_conc_{$idx}_{$suffix}@calon.sch.id",
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->createdUserIds[] = $user->id;

        $applicant = Applicant::create([
            'wave_id' => $wave->id,
            'track_id' => $track->id,
            'user_id' => $user->id,
            'registration_no' => 'PPDB-T2B-'.str_pad((string) $idx, 4, '0', STR_PAD_LEFT).'-'.Str::upper($suffix),
            'full_name' => "Calon Siswa Konkurensi {$idx} {$suffix}",
            'nik_enc' => '35730102'.random_int(10000000, 99999999),
            'birth_date' => '2013-03-15',
            'status' => 'selected',
            'decision' => 'diterima',
            'step_data' => ['personal' => ['gender' => 'L', 'birth_place' => 'Malang']],
        ]);
        $this->createdApplicantIds[] = $applicant->id;

        PPDBReRegistration::create([
            'applicant_id' => $applicant->id,
            'confirmed_at' => now(),
            'status' => 'dikonfirmasi',
        ]);

        return $applicant;
    }

    /**
     * @return array{component: GradeComponent, period: GradePeriod}
     */
    private function createGradeFixtures(): array
    {
        $suffix = Str::upper(Str::random(6));

        $year = AcademicYear::create([
            'school_id' => $this->school->id,
            'name' => 'TA-CONC-'.$suffix,
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => false,
        ]);
        $this->createdAcademicYearIds[] = $year->id;

        $semester = Semester::create([
            'academic_year_id' => $year->id,
            'name' => 'Ganjil',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => false,
        ]);
        $this->createdSemesterIds[] = $semester->id;

        $period = GradePeriod::create([
            'semester_id' => $semester->id,
            'month' => 9,
            'status' => 'open',
        ]);
        $this->createdPeriodIds[] = $period->id;

        $subject = Subject::create([
            'school_id' => $this->school->id,
            'code' => 'CONC-'.$suffix,
            'name' => 'Matematika Uji Konkurensi '.$suffix,
            'subject_group' => 'wajib',
        ]);
        $this->createdSubjectIds[] = $subject->id;

        $component = GradeComponent::create([
            'subject_id' => $subject->id,
            'semester_id' => $semester->id,
            'class_id' => null,
            'code' => 'UH-'.$suffix,
            'name' => 'Ulangan Harian Konkurensi',
            'weight' => 30.0,
        ]);
        $this->createdComponentIds[] = $component->id;

        return ['component' => $component, 'period' => $period];
    }

    public function test_t2b_conc_01_concurrent_verify_payment_same_payment_prevents_double_credit(): void
    {
        $invoice = $this->createInvoiceRecord('1A', 1000000);
        $payment = $this->createPendingPayment($invoice, 1000000);

        $jobs = array_fill(0, 4, [
            'action' => 'verify_payment',
            'payment_id' => $payment->id,
            'actor_id' => $this->admin->id,
        ]);

        $res = $this->runParallelWorkers($jobs);
        $this->assertDistinctPgPids($res, 4);

        $ok = array_values(array_filter($res, fn ($r) => ($r['ok'] ?? false) === true));
        $valFail = array_values(array_filter($res, fn ($r) => ($r['type'] ?? '') === 'validation'));

        $invoice->refresh();
        $payment->refresh();

        $this->assertCount(1, $ok, 'Tepat 1 verifyPayment harus berhasil: '.json_encode($res));
        $this->assertCount(3, $valFail, '3 worker lain harus ditolak dengan ValidationException');
        $this->assertSame(1000000, (int) $invoice->paid_amount);
        $this->assertSame('lunas', $invoice->status);
        $this->assertSame('terverifikasi', $payment->status);
        $this->assertNotEmpty($payment->receipt_number);
    }

    public function test_t2b_conc_01_concurrent_verify_payment_blocks_overpayment(): void
    {
        $invoice = $this->createInvoiceRecord('1B', 1000000);
        $payA = $this->createPendingPayment($invoice, 600000);
        $payB = $this->createPendingPayment($invoice, 600000);

        $res = $this->runParallelWorkers([
            ['action' => 'verify_payment', 'payment_id' => $payA->id, 'actor_id' => $this->admin->id],
            ['action' => 'verify_payment', 'payment_id' => $payB->id, 'actor_id' => $this->admin->id],
        ]);
        $this->assertDistinctPgPids($res, 2);

        $ok = array_values(array_filter($res, fn ($r) => ($r['ok'] ?? false) === true));
        $valFail = array_values(array_filter($res, fn ($r) => ($r['type'] ?? '') === 'validation'));

        $invoice->refresh();

        $this->assertCount(1, $ok, 'Overpayment pada verifyPayment wajib diblokir: '.json_encode($res));
        $this->assertCount(1, $valFail);
        $this->assertSame(600000, (int) $invoice->paid_amount);
        $this->assertSame('sebagian', $invoice->status);
    }

    public function test_t2b_conc_01_concurrent_verify_payment_accurate_sum_zero_lost_update(): void
    {
        $invoice = $this->createInvoiceRecord('1C', 1000000);
        $payA = $this->createPendingPayment($invoice, 500000);
        $payB = $this->createPendingPayment($invoice, 500000);

        $res = $this->runParallelWorkers([
            ['action' => 'verify_payment', 'payment_id' => $payA->id, 'actor_id' => $this->admin->id],
            ['action' => 'verify_payment', 'payment_id' => $payB->id, 'actor_id' => $this->admin->id],
        ]);
        $this->assertDistinctPgPids($res, 2);

        $ok = array_values(array_filter($res, fn ($r) => ($r['ok'] ?? false) === true));
        $receipts = array_unique(array_map(fn ($r) => $r['result']['receipt_number'] ?? '', $ok));

        $invoice->refresh();

        $this->assertCount(2, $ok, 'Kedua verifikasi 500rb harus berhasil: '.json_encode($res));
        $this->assertCount(2, $receipts);
        $this->assertSame(1000000, (int) $invoice->paid_amount);
        $this->assertSame('lunas', $invoice->status);
    }

    public function test_t2b_conc_01_concurrent_submit_payment_blocks_overpayment(): void
    {
        $invoice = $this->createInvoiceRecord('2', 1000000);

        $jobs = [];
        for ($i = 0; $i < 4; $i++) {
            $jobs[] = [
                'action' => 'submit_payment',
                'invoice_id' => $invoice->id,
                'actor_id' => $this->studentUser->id,
                'amount' => 600000,
                'external_reference' => 'EXT-SUB-'.Str::upper(Str::random(8))."-{$i}",
            ];
        }

        $res = $this->runParallelWorkers($jobs);
        $this->assertDistinctPgPids($res, 4);

        $ok = array_values(array_filter($res, fn ($r) => ($r['ok'] ?? false) === true));
        $valFail = array_values(array_filter($res, fn ($r) => ($r['type'] ?? '') === 'validation'));
        $pendingSum = (int) Payment::where('invoice_id', $invoice->id)
            ->where('status', 'menunggu_verifikasi')
            ->sum('amount');

        $this->assertCount(1, $ok, 'Tepat 1 submitPayment 600rb boleh lolos: '.json_encode($res));
        $this->assertCount(3, $valFail);
        $this->assertSame(600000, $pendingSum);
    }

    public function test_t2b_conc_01_concurrent_cancel_invoice_vs_verify_payment_is_mutually_exclusive(): void
    {
        for ($round = 1; $round <= 4; $round++) {
            $invoice = $this->createInvoiceRecord("3R{$round}", 500000);
            $payment = $this->createPendingPayment($invoice, 500000);

            $jobs = ($round % 2 === 1)
                ? [
                    ['action' => 'cancel_invoice', 'invoice_id' => $invoice->id, 'actor_id' => $this->admin->id],
                    ['action' => 'verify_payment', 'payment_id' => $payment->id, 'actor_id' => $this->admin->id],
                ]
                : [
                    ['action' => 'verify_payment', 'payment_id' => $payment->id, 'actor_id' => $this->admin->id],
                    ['action' => 'cancel_invoice', 'invoice_id' => $invoice->id, 'actor_id' => $this->admin->id],
                ];

            $res = $this->runParallelWorkers($jobs, 0.35);
            $this->assertDistinctPgPids($res, 2);

            $ok = array_values(array_filter($res, fn ($r) => ($r['ok'] ?? false) === true));
            $valFail = array_values(array_filter($res, fn ($r) => ($r['type'] ?? '') === 'validation'));

            $invoice->refresh();
            $payment->refresh();

            $this->assertCount(1, $ok, "Round {$round} harus punya tepat 1 pemenang: ".json_encode($res));
            $this->assertCount(1, $valFail, "Round {$round} harus menolak operasi kalah");
            $this->assertFalse(
                $invoice->status === 'dibatalkan' && $payment->status === 'terverifikasi',
                "Round {$round}: state inkonsisten (dibatalkan + terverifikasi)"
            );
        }
    }

    public function test_t2b_conc_01_concurrent_generate_invoice_number_is_unique_and_sequential(): void
    {
        $ymPrefix = 'INV/'.date('Y').'/'.date('m').'/';
        $baselineRow = DB::selectOne(
            "SELECT MAX(CAST(SPLIT_PART(invoice_number, '/', 4) AS INTEGER)) AS max_seq
             FROM invoices
             WHERE invoice_number LIKE ? AND SPLIT_PART(invoice_number, '/', 4) ~ '^[0-9]+$'",
            [$ymPrefix.'%']
        );
        $baseline = (int) ($baselineRow->max_seq ?? 0);

        $workerCount = 6;
        $jobs = array_fill(0, $workerCount, [
            'action' => 'create_invoice',
            'student_id' => $this->student->id,
            'fee_type_id' => $this->feeType->id,
            'actor_id' => $this->admin->id,
        ]);

        $res = $this->runParallelWorkers($jobs);
        $this->assertDistinctPgPids($res, $workerCount);

        $ok = array_values(array_filter($res, fn ($r) => ($r['ok'] ?? false) === true));
        $this->assertCount($workerCount, $ok, 'Semua createInvoice harus berhasil: '.json_encode($res));

        $numbers = array_map(fn ($r) => $r['result']['invoice_number'], $ok);
        sort($numbers);

        $expected = array_map(
            fn ($n) => $ymPrefix.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            range($baseline + 1, $baseline + $workerCount)
        );

        $this->assertSame($expected, $numbers);
    }

    public function test_t2b_conc_01_concurrent_generate_receipt_number_is_unique_and_sequential(): void
    {
        $kwtPrefix = 'KWT/'.date('Y').'/'.date('m').'/';
        $baselineRow = DB::selectOne(
            "SELECT MAX(CAST(SPLIT_PART(receipt_number, '/', 4) AS INTEGER)) AS max_seq
             FROM payments
             WHERE receipt_number LIKE ? AND SPLIT_PART(receipt_number, '/', 4) ~ '^[0-9]+$'",
            [$kwtPrefix.'%']
        );
        $baseline = (int) ($baselineRow->max_seq ?? 0);

        $workerCount = 6;
        $jobs = [];
        for ($i = 1; $i <= $workerCount; $i++) {
            $invoice = $this->createInvoiceRecord("5R{$i}", 300000);
            $payment = $this->createPendingPayment($invoice, 300000);
            $jobs[] = [
                'action' => 'verify_payment',
                'payment_id' => $payment->id,
                'actor_id' => $this->admin->id,
            ];
        }

        $res = $this->runParallelWorkers($jobs);
        $this->assertDistinctPgPids($res, $workerCount);

        $ok = array_values(array_filter($res, fn ($r) => ($r['ok'] ?? false) === true));
        $this->assertCount($workerCount, $ok, 'Semua verifyPayment harus berhasil: '.json_encode($res));

        $receipts = array_map(fn ($r) => $r['result']['receipt_number'], $ok);
        sort($receipts);

        $expected = array_map(
            fn ($n) => $kwtPrefix.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            range($baseline + 1, $baseline + $workerCount)
        );

        $this->assertSame($expected, $receipts);
    }

    public function test_t2b_conc_01_concurrent_ppdb_enrollment_idempotency_and_sequential_nis(): void
    {
        $wave = PPDBWave::create([
            'school_id' => $this->school->id,
            'name' => 'Gelombang Konkurensi '.Str::random(6),
            'open_at' => now()->subDays(10),
            'close_at' => now()->addDays(10),
            'result_publish_at' => now()->subDay(),
            'status' => 'aktif',
        ]);
        $this->createdWaveIds[] = $wave->id;

        $track = PPDBTrack::create([
            'wave_id' => $wave->id,
            'name' => 'Zonasi Konkurensi',
            'code' => 'ZON-'.Str::upper(Str::random(6)),
            'quota' => 50,
        ]);

        // Bagian A: 4 worker mendaftarkan pendaftar yang SAMA -> 1 created + 3 idempotent, 1 student_id
        $sameApplicant = $this->createAcceptedApplicant($wave, $track, 1);
        $resA = $this->runParallelWorkers(array_fill(0, 4, [
            'action' => 'enroll_applicant',
            'applicant_id' => $sameApplicant->id,
            'actor_id' => $this->admin->id,
        ]));
        $this->assertDistinctPgPids($resA, 4);

        $okA = array_values(array_filter($resA, fn ($r) => ($r['ok'] ?? false) === true));
        $this->assertCount(4, $okA, 'Semua worker enroll idempoten harus sukses: '.json_encode($resA));

        $createdCount = count(array_filter($okA, fn ($r) => ($r['result']['is_idempotent'] ?? true) === false));
        $idempotentCount = count(array_filter($okA, fn ($r) => ($r['result']['is_idempotent'] ?? false) === true));
        $studentIdsA = array_unique(array_map(fn ($r) => $r['result']['student_id'], $okA));

        $this->assertSame(1, $createdCount);
        $this->assertSame(3, $idempotentCount);
        $this->assertCount(1, $studentIdsA);

        // Bagian B: 6 worker mendaftarkan 6 pendaftar BERBEDA -> 6 NIS unik & berurutan dari baseline
        $year = date('Y');
        $baselineRow = DB::selectOne(
            "SELECT MAX(CAST(SUBSTRING(nis FROM 5) AS INTEGER)) AS max_seq
             FROM students
             WHERE nis LIKE ? AND LENGTH(nis) >= 5 AND SUBSTRING(nis FROM 5) ~ '^[0-9]+$'",
            [$year.'%']
        );
        $baseline = (int) ($baselineRow->max_seq ?? 0);

        $workerCount = 6;
        $jobsB = [];
        for ($i = 2; $i <= $workerCount + 1; $i++) {
            $ap = $this->createAcceptedApplicant($wave, $track, $i);
            $jobsB[] = [
                'action' => 'enroll_applicant',
                'applicant_id' => $ap->id,
                'actor_id' => $this->admin->id,
            ];
        }

        $resB = $this->runParallelWorkers($jobsB);
        $this->assertDistinctPgPids($resB, $workerCount);

        $okB = array_values(array_filter($resB, fn ($r) => ($r['ok'] ?? false) === true));
        $this->assertCount($workerCount, $okB, 'Semua 6 enrollment paralel harus berhasil: '.json_encode($resB));

        $nisList = array_map(fn ($r) => $r['result']['nis'], $okB);
        sort($nisList);

        $expectedNis = array_map(
            fn ($n) => $year.str_pad((string) $n, 5, '0', STR_PAD_LEFT),
            range($baseline + 1, $baseline + $workerCount)
        );

        $this->assertSame($expectedNis, $nisList);
    }

    public function test_t2b_conc_02_concurrent_role_ensure_system_role_safe_inside_transactions(): void
    {
        $roleCode = 't2b_conc_role_'.Str::lower(Str::random(10));
        $this->createdRoleCodes[] = $roleCode;

        $this->assertSame(0, Role::where('code', $roleCode)->count());

        $jobs = array_fill(0, 4, [
            'action' => 'ensure_system_role',
            'role_code' => $roleCode,
            'role_name' => 'Peran Sistem Uji Konkurensi',
        ]);

        $res = $this->runParallelWorkers($jobs);
        $this->assertDistinctPgPids($res, 4);

        $ok = array_values(array_filter($res, fn ($r) => ($r['ok'] ?? false) === true));
        $this->assertCount(
            4,
            $ok,
            'Seluruh 4 transaksi paralel Role::ensureSystemRole() harus sukses tanpa 23505/25P02: '.json_encode($res)
        );

        $roleIds = array_unique(array_map(fn ($r) => $r['result']['role_id'], $ok));
        $this->assertCount(1, $roleIds, 'Seluruh worker wajib menerima role ID yang sama');
        $this->assertSame(1, Role::where('code', $roleCode)->count());
    }

    public function test_t2b_conc_03_concurrent_grade_recorder_prevents_duplicate_and_lost_update(): void
    {
        // Fase A: Race pada sel nilai BARU (manual 85 vs CBT 80 vs assignment 78 vs manual 85)
        $fixturesA = $this->createGradeFixtures();
        $resA = $this->runParallelWorkers([
            [
                'action' => 'grade_recorder_manual',
                'student_id' => $this->student->id,
                'component_id' => $fixturesA['component']->id,
                'period_id' => $fixturesA['period']->id,
                'value' => 85.0,
                'actor_id' => $this->admin->id,
                'note' => 'Manual paralel 1',
            ],
            [
                'action' => 'grade_recorder_auto',
                'student_id' => $this->student->id,
                'component_id' => $fixturesA['component']->id,
                'period_id' => $fixturesA['period']->id,
                'value' => 80.0,
                'change_source' => GradeRecorder::SOURCE_CBT,
                'actor_id' => $this->admin->id,
                'note' => 'CBT paralel 80',
            ],
            [
                'action' => 'grade_recorder_auto',
                'student_id' => $this->student->id,
                'component_id' => $fixturesA['component']->id,
                'period_id' => $fixturesA['period']->id,
                'value' => 78.0,
                'change_source' => GradeRecorder::SOURCE_ASSIGNMENT,
                'actor_id' => $this->admin->id,
                'note' => 'Tugas paralel 78',
            ],
            [
                'action' => 'grade_recorder_manual',
                'student_id' => $this->student->id,
                'component_id' => $fixturesA['component']->id,
                'period_id' => $fixturesA['period']->id,
                'value' => 85.0,
                'actor_id' => $this->admin->id,
                'note' => 'Manual paralel 2',
            ],
        ]);
        $this->assertDistinctPgPids($resA, 4);

        $okA = array_values(array_filter($resA, fn ($r) => ($r['ok'] ?? false) === true));
        $this->assertCount(4, $okA, 'Semua 4 penulisan GradeRecorder pada sel baru wajib sukses: '.json_encode($resA));

        $gradesA = Grade::where('student_id', $this->student->id)
            ->where('component_id', $fixturesA['component']->id)
            ->where('period_id', $fixturesA['period']->id)
            ->get();

        $this->assertCount(1, $gradesA);
        $this->assertEquals(85.0, (float) $gradesA[0]->value);
        $this->assertEquals(85.0, (float) $gradesA[0]->manual_value);
        $this->assertEquals(80.0, (float) $gradesA[0]->auto_value);
        $this->assertSame(GradeRecorder::SOURCE_MANUAL, $gradesA[0]->source);
        $this->assertSame(4, GradeHistory::where('grade_id', $gradesA[0]->id)->count());

        // Fase B: Race pada sel nilai EKSISTING (manual 70 vs CBT 92 -> efektif wajib 92 cbt, manual_value=70 & auto_value=92 utuh)
        $fixturesB = $this->createGradeFixtures();
        app(GradeRecorder::class)->recordManual(
            $this->student->id,
            $fixturesB['component']->id,
            $fixturesB['period']->id,
            60.0,
            $this->admin->id,
            'Seed awal 60'
        );

        $resB = $this->runParallelWorkers([
            [
                'action' => 'grade_recorder_manual',
                'student_id' => $this->student->id,
                'component_id' => $fixturesB['component']->id,
                'period_id' => $fixturesB['period']->id,
                'value' => 70.0,
                'actor_id' => $this->admin->id,
                'note' => 'Manual paralel 70',
            ],
            [
                'action' => 'grade_recorder_auto',
                'student_id' => $this->student->id,
                'component_id' => $fixturesB['component']->id,
                'period_id' => $fixturesB['period']->id,
                'value' => 92.0,
                'change_source' => GradeRecorder::SOURCE_CBT,
                'actor_id' => $this->admin->id,
                'note' => 'CBT paralel 92',
            ],
        ]);
        $this->assertDistinctPgPids($resB, 2);

        $okB = array_values(array_filter($resB, fn ($r) => ($r['ok'] ?? false) === true));
        $this->assertCount(2, $okB, 'Semua penulisan paralel pada sel eksisting wajib sukses: '.json_encode($resB));

        $gradeB = Grade::where('student_id', $this->student->id)
            ->where('component_id', $fixturesB['component']->id)
            ->where('period_id', $fixturesB['period']->id)
            ->firstOrFail();

        $this->assertEquals(92.0, (float) $gradeB->value);
        $this->assertEquals(70.0, (float) $gradeB->manual_value);
        $this->assertEquals(92.0, (float) $gradeB->auto_value);
        $this->assertSame(GradeRecorder::SOURCE_CBT, $gradeB->source);
        $this->assertSame(3, GradeHistory::where('grade_id', $gradeB->id)->count());
    }
}
