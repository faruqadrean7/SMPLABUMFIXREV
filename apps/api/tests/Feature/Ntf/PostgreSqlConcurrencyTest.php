<?php

namespace Tests\Feature\Ntf;

use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * T2B-CONC-01: PostgreSQL Multi-Connection Concurrency & Integration Tests
 *
 * Menguji skenario konkurensi database nyata menggunakan dua koneksi
 * dan backend server process PostgreSQL terpisah (pg_backend_pid) berbasis
 * konfigurasi/environment aktif tanpa kredensial hardcoded.
 */
class PostgreSqlConcurrencyTest extends TestCase
{
    protected const CONN_W1 = 'pgsql_concurrency_w1';

    protected const CONN_W2 = 'pgsql_concurrency_w2';

    protected ?string $pgSchoolId = null;

    protected ?string $pgUserId = null;

    protected bool $postgresAvailable = false;

    protected ?int $worker1Pid = null;

    protected ?int $worker2Pid = null;

    protected function setUp(): void
    {
        parent::setUp();

        $requirePg = filter_var(env('PG_CONCURRENCY_REQUIRED', false), FILTER_VALIDATE_BOOL);
        $defaultDriver = (string) config('database.default', 'pgsql');

        if ($defaultDriver !== 'pgsql' && ! env('PG_CONCURRENCY_HOST') && ! $requirePg) {
            $this->markTestSkipped('Driver database aktif bukan pgsql dan PG_CONCURRENCY_HOST tidak disetel.');
        }

        $pgConfig = $this->resolvePgConnectionConfig();

        Config::set('database.connections.'.self::CONN_W1, $pgConfig);
        Config::set('database.connections.'.self::CONN_W2, $pgConfig);
        DB::purge(self::CONN_W1);
        DB::purge(self::CONN_W2);

        $connectionError = null;
        try {
            $pid1 = (int) (DB::connection(self::CONN_W1)->selectOne('SELECT pg_backend_pid() AS pid')->pid ?? 0);
            $pid2 = (int) (DB::connection(self::CONN_W2)->selectOne('SELECT pg_backend_pid() AS pid')->pid ?? 0);

            if ($pid1 > 0 && $pid2 > 0 && $pid1 !== $pid2) {
                $this->worker1Pid = $pid1;
                $this->worker2Pid = $pid2;
                $this->postgresAvailable = true;
            }
        } catch (\Throwable $e) {
            $connectionError = $e->getMessage();
            $this->postgresAvailable = false;
        }

        if (! $this->postgresAvailable) {
            $msg = sprintf(
                'PostgreSQL concurrency database (%s:%s/%s) tidak tersedia%s.',
                $pgConfig['host'],
                $pgConfig['port'],
                $pgConfig['database'],
                $connectionError ? ': '.$connectionError : ''
            );

            if ($requirePg) {
                $this->fail($msg);
            }

            $this->markTestSkipped($msg);
        }

        $this->pgSchoolId = (string) Str::uuid();
        $this->pgUserId = (string) Str::uuid();

        DB::connection(self::CONN_W1)->table('users')->insert([
            'id' => $this->pgUserId,
            'school_id' => $this->pgSchoolId,
            'username' => 'pg_test_user_'.Str::random(8),
            'email' => 'pg_'.Str::random(8).'@sekolah.sch.id',
            'password_hash' => Hash::make('password'),
            'status' => 'active',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->postgresAvailable && $this->pgUserId) {
            DB::connection(self::CONN_W1)->table('notifications')->where('user_id', $this->pgUserId)->delete();
            DB::connection(self::CONN_W1)->table('reminder_dispatches')->where('recipient_id', $this->pgUserId)->delete();
            DB::connection(self::CONN_W1)->table('users')->where('id', $this->pgUserId)->delete();
        }

        DB::disconnect(self::CONN_W1);
        DB::disconnect(self::CONN_W2);

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
     * Skenario 1, 2, 3: Dua koneksi PostgreSQL mencoba mengklaim dispatch yang sama bersamaan.
     * Hanya satu worker yang berhasil, dan worker kedua tidak menghasilkan duplikasi notifikasi.
     */
    public function test_two_connections_compete_to_claim_dispatch_atomically(): void
    {
        $this->assertNotSame($this->worker1Pid, $this->worker2Pid);

        $dispatchId = (string) Str::uuid();
        $eventId = (string) Str::uuid();

        DB::connection(self::CONN_W1)->table('reminder_dispatches')->insert([
            'id' => $dispatchId,
            'school_id' => $this->pgSchoolId,
            'event_type' => 'cbt_exam',
            'event_id' => $eventId,
            'recipient_id' => $this->pgUserId,
            'milestone' => '24h',
            'channel' => 'in_app',
            'status' => 'pending',
            'claim_token' => null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $token1 = 'worker-1-'.Str::uuid();
        $token2 = 'worker-2-'.Str::uuid();
        $now = Carbon::now();

        $claim1 = DB::connection(self::CONN_W1)
            ->table('reminder_dispatches')
            ->where('id', $dispatchId)
            ->whereIn('status', ['pending', 'failed'])
            ->update([
                'status' => 'processing',
                'claim_token' => $token1,
                'claim_expires_at' => $now->copy()->addMinutes(15),
                'updated_at' => $now,
            ]);

        $claim2 = DB::connection(self::CONN_W2)
            ->table('reminder_dispatches')
            ->where('id', $dispatchId)
            ->whereIn('status', ['pending', 'failed'])
            ->update([
                'status' => 'processing',
                'claim_token' => $token2,
                'claim_expires_at' => $now->copy()->addMinutes(15),
                'updated_at' => $now,
            ]);

        $this->assertEquals(1, $claim1);
        $this->assertEquals(0, $claim2);

        $finalRecord = DB::connection(self::CONN_W1)
            ->table('reminder_dispatches')
            ->where('id', $dispatchId)
            ->first();

        $this->assertEquals('processing', $finalRecord->status);
        $this->assertEquals($token1, $finalRecord->claim_token);

        $worker2OwnsClaim = DB::connection(self::CONN_W2)
            ->table('reminder_dispatches')
            ->where('id', $dispatchId)
            ->where('status', 'processing')
            ->where('claim_token', $token2)
            ->exists();

        $this->assertFalse($worker2OwnsClaim, 'Worker 2 must not own the claim');

        $notifCount = DB::connection(self::CONN_W1)
            ->table('notifications')
            ->where('user_id', $this->pgUserId)
            ->count();
        $this->assertEquals(0, $notifCount);
    }

    /**
     * Skenario 4: Recovery tidak mengambil alih job yang masih memiliki masa sewa sah.
     */
    public function test_recovery_does_not_steal_in_flight_valid_lease(): void
    {
        $dispatchId = (string) Str::uuid();
        $eventId = (string) Str::uuid();
        $activeToken = 'active-worker-'.Str::uuid();

        DB::connection(self::CONN_W1)->table('reminder_dispatches')->insert([
            'id' => $dispatchId,
            'school_id' => $this->pgSchoolId,
            'event_type' => 'cbt_exam',
            'event_id' => $eventId,
            'recipient_id' => $this->pgUserId,
            'milestone' => '24h',
            'channel' => 'in_app',
            'status' => 'processing',
            'claim_token' => $activeToken,
            'claim_expires_at' => Carbon::now()->addMinutes(14),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()->subMinute(),
        ]);

        $threshold = Carbon::now()->subMinutes(15);
        $now = Carbon::now();

        $stuckQuery = DB::connection(self::CONN_W2)
            ->table('reminder_dispatches')
            ->where('id', $dispatchId)
            ->where('status', 'processing')
            ->where(function ($q) use ($threshold, $now) {
                $q->where('updated_at', '<', $threshold)
                    ->orWhere(function ($q2) use ($now) {
                        $q2->whereNotNull('claim_expires_at')
                            ->where('claim_expires_at', '<', $now);
                    });
            });

        $stuckIds = $stuckQuery->pluck('id')->all();
        $this->assertCount(0, $stuckIds, 'Recovery query must NOT pick active in-flight worker');

        $record = DB::connection(self::CONN_W1)->table('reminder_dispatches')->where('id', $dispatchId)->first();
        $this->assertEquals('processing', $record->status);
        $this->assertEquals($activeToken, $record->claim_token);
    }

    /**
     * Skenario 5: Worker lama bangun setelah lease kedaluwarsa dan tidak dapat menyelesaikan dispatch milik worker baru (Fencing).
     */
    public function test_fencing_prevents_stale_worker_from_overwriting_new_worker_in_postgresql(): void
    {
        $dispatchId = (string) Str::uuid();
        $eventId = (string) Str::uuid();
        $tokenOldWorker = 'old-worker-'.Str::uuid();
        $tokenNewWorker = 'new-worker-'.Str::uuid();

        DB::connection(self::CONN_W1)->table('reminder_dispatches')->insert([
            'id' => $dispatchId,
            'school_id' => $this->pgSchoolId,
            'event_type' => 'cbt_exam',
            'event_id' => $eventId,
            'recipient_id' => $this->pgUserId,
            'milestone' => '24h',
            'channel' => 'in_app',
            'status' => 'processing',
            'claim_token' => $tokenOldWorker,
            'claim_expires_at' => Carbon::now()->subMinutes(5),
            'created_at' => Carbon::now()->subMinutes(30),
            'updated_at' => Carbon::now()->subMinutes(25),
        ]);

        $recovered = DB::connection(self::CONN_W2)
            ->table('reminder_dispatches')
            ->where('id', $dispatchId)
            ->where('status', 'processing')
            ->update([
                'status' => 'pending',
                'claim_token' => null,
                'claim_expires_at' => null,
                'updated_at' => Carbon::now(),
            ]);
        $this->assertEquals(1, $recovered);

        $claimedNew = DB::connection(self::CONN_W2)
            ->table('reminder_dispatches')
            ->where('id', $dispatchId)
            ->whereIn('status', ['pending', 'failed'])
            ->update([
                'status' => 'processing',
                'claim_token' => $tokenNewWorker,
                'claim_expires_at' => Carbon::now()->addMinutes(15),
                'updated_at' => Carbon::now(),
            ]);
        $this->assertEquals(1, $claimedNew);

        DB::connection(self::CONN_W2)->table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $this->pgUserId,
            'notif_type' => 'academic_announcement',
            'title' => 'Pengingat dari Worker Baru',
            'body' => 'Badan notifikasi',
            'data' => json_encode(['event_id' => $eventId, 'milestone' => '24h']),
            'created_at' => Carbon::now(),
        ]);

        DB::connection(self::CONN_W2)
            ->table('reminder_dispatches')
            ->where('id', $dispatchId)
            ->where('claim_token', $tokenNewWorker)
            ->update([
                'status' => 'dispatched',
                'claim_token' => null,
                'claim_expires_at' => null,
                'dispatched_at' => Carbon::now(),
            ]);

        $fencedOldWorker = DB::connection(self::CONN_W1)->transaction(function () use ($dispatchId, $tokenOldWorker) {
            $current = DB::connection(self::CONN_W1)
                ->table('reminder_dispatches')
                ->where('id', $dispatchId)
                ->where('claim_token', $tokenOldWorker)
                ->where('status', 'processing')
                ->lockForUpdate()
                ->first();

            if (! $current) {
                return 'ABORTED_BY_FENCING';
            }

            return 'SENT';
        });

        $this->assertEquals('ABORTED_BY_FENCING', $fencedOldWorker);

        $notifCount = DB::connection(self::CONN_W1)
            ->table('notifications')
            ->where('user_id', $this->pgUserId)
            ->count();
        $this->assertEquals(1, $notifCount, 'Must contain exactly 1 notification from worker B');

        $finalStatus = DB::connection(self::CONN_W1)
            ->table('reminder_dispatches')
            ->where('id', $dispatchId)
            ->value('status');
        $this->assertEquals('dispatched', $finalStatus);
    }

    /**
     * Skenario 6: Dua proses recovery berjalan bersamaan tidak memulihkan record secara ganda.
     */
    public function test_concurrent_recovery_processes_are_atomic(): void
    {
        $dispatchId = (string) Str::uuid();

        DB::connection(self::CONN_W1)->table('reminder_dispatches')->insert([
            'id' => $dispatchId,
            'school_id' => $this->pgSchoolId,
            'event_type' => 'cbt_exam',
            'event_id' => (string) Str::uuid(),
            'recipient_id' => $this->pgUserId,
            'milestone' => '24h',
            'channel' => 'in_app',
            'status' => 'processing',
            'claim_token' => 'stuck-token',
            'claim_expires_at' => Carbon::now()->subMinutes(10),
            'created_at' => Carbon::now()->subMinutes(40),
            'updated_at' => Carbon::now()->subMinutes(25),
        ]);

        $rec1 = DB::connection(self::CONN_W1)
            ->table('reminder_dispatches')
            ->where('id', $dispatchId)
            ->where('status', 'processing')
            ->update([
                'status' => 'pending',
                'claim_token' => null,
                'claim_expires_at' => null,
                'updated_at' => Carbon::now(),
            ]);

        $rec2 = DB::connection(self::CONN_W2)
            ->table('reminder_dispatches')
            ->where('id', $dispatchId)
            ->where('status', 'processing')
            ->update([
                'status' => 'pending',
                'claim_token' => null,
                'claim_expires_at' => null,
                'updated_at' => Carbon::now(),
            ]);

        $this->assertEquals(1, $rec1);
        $this->assertEquals(0, $rec2);
    }

    /**
     * Skenario 7: Unique constraint PostgreSQL mencegah dispatch planning duplikat.
     */
    public function test_postgresql_unique_constraint_rejects_duplicate_planning(): void
    {
        $eventId = (string) Str::uuid();
        $milestone = '24h';

        DB::connection(self::CONN_W1)->table('reminder_dispatches')->insert([
            'id' => (string) Str::uuid(),
            'school_id' => $this->pgSchoolId,
            'event_type' => 'cbt_exam',
            'event_id' => $eventId,
            'recipient_id' => $this->pgUserId,
            'milestone' => $milestone,
            'channel' => 'in_app',
            'status' => 'pending',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $duplicateRejected = false;
        try {
            DB::connection(self::CONN_W2)->table('reminder_dispatches')->insert([
                'id' => (string) Str::uuid(),
                'school_id' => $this->pgSchoolId,
                'event_type' => 'cbt_exam',
                'event_id' => $eventId,
                'recipient_id' => $this->pgUserId,
                'milestone' => $milestone,
                'channel' => 'in_app',
                'status' => 'pending',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), '23505') || str_contains($e->getMessage(), 'unique') || str_contains($e->getMessage(), 'reminder_dispatches_event_recip_ms_unique')) {
                $duplicateRejected = true;
            }
        }

        $this->assertTrue($duplicateRejected, 'PostgreSQL unique constraint must throw unique violation on duplicate planning');
    }

    /**
     * Skenario 8: Transaksi yang gagal di PostgreSQL me-rollback notifikasi tanpa meninggalkan state inkonsisten.
     */
    public function test_transaction_rollback_in_postgresql_leaves_consistent_state(): void
    {
        $dispatchId = (string) Str::uuid();
        $eventId = (string) Str::uuid();
        $token = 'worker-rollback-'.Str::uuid();

        DB::connection(self::CONN_W1)->table('reminder_dispatches')->insert([
            'id' => $dispatchId,
            'school_id' => $this->pgSchoolId,
            'event_type' => 'cbt_exam',
            'event_id' => $eventId,
            'recipient_id' => $this->pgUserId,
            'milestone' => '24h',
            'channel' => 'in_app',
            'status' => 'processing',
            'claim_token' => $token,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $errorCaught = false;

        try {
            DB::connection(self::CONN_W1)->transaction(function () use ($eventId) {
                DB::connection(self::CONN_W1)->table('notifications')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $this->pgUserId,
                    'notif_type' => 'academic_announcement',
                    'title' => 'Notifikasi yang akan di-rollback',
                    'body' => 'Simulasi crash sebelum commit',
                    'data' => json_encode(['event_id' => $eventId]),
                    'created_at' => Carbon::now(),
                ]);

                throw new \RuntimeException('Simulated worker crash inside transaction');
            });
        } catch (\RuntimeException $e) {
            $errorCaught = true;

            DB::connection(self::CONN_W1)
                ->table('reminder_dispatches')
                ->where('id', $dispatchId)
                ->where('claim_token', $token)
                ->update([
                    'status' => 'failed',
                    'claim_token' => null,
                    'error_message' => $e->getMessage(),
                    'updated_at' => Carbon::now(),
                ]);
        }

        $this->assertTrue($errorCaught);

        $notifCount = DB::connection(self::CONN_W1)
            ->table('notifications')
            ->where('user_id', $this->pgUserId)
            ->count();
        $this->assertEquals(0, $notifCount, 'Notification must be rolled back by PostgreSQL');

        $record = DB::connection(self::CONN_W1)
            ->table('reminder_dispatches')
            ->where('id', $dispatchId)
            ->first();

        $this->assertEquals('failed', $record->status);
        $this->assertNull($record->claim_token);
        $this->assertEquals('Simulated worker crash inside transaction', $record->error_message);
    }
}
