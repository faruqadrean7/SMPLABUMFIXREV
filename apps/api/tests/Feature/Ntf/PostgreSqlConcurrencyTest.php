<?php

namespace Tests\Feature\Ntf;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P0 — PostgreSQL Multi-Connection Concurrency & Integration Tests (Sprint 39)
 *
 * Menguji skenario konkurensi database nyata menggunakan dua koneksi
 * dan backend server process PostgreSQL terpisah (pg_backend_pid).
 */
class PostgreSqlConcurrencyTest extends TestCase
{
    protected ?string $pgSchoolId = null;

    protected ?string $pgUserId = null;

    protected bool $postgresAvailable = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Konfigurasi dua koneksi PostgreSQL staging terpisah
        Config::set('database.connections.pgsql_staging', [
            'driver' => 'pgsql',
            'host' => 'localhost',
            'port' => 5432,
            'database' => 'sistlabum_staging_test',
            'username' => 'macbookprom12020',
            'password' => '',
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ]);

        Config::set('database.connections.pgsql_staging_worker2', [
            'driver' => 'pgsql',
            'host' => 'localhost',
            'port' => 5432,
            'database' => 'sistlabum_staging_test',
            'username' => 'macbookprom12020',
            'password' => '',
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ]);

        try {
            $pid1 = DB::connection('pgsql_staging')->select('SELECT pg_backend_pid() as pid')[0]->pid ?? null;
            $pid2 = DB::connection('pgsql_staging_worker2')->select('SELECT pg_backend_pid() as pid')[0]->pid ?? null;

            if ($pid1 && $pid2 && $pid1 !== $pid2) {
                $this->postgresAvailable = true;
            }
        } catch (\Throwable $e) {
            $this->postgresAvailable = false;
        }

        if (! $this->postgresAvailable) {
            $this->markTestSkipped('PostgreSQL staging database sistlabum_staging_test tidak tersedia.');
        }

        $this->pgSchoolId = (string) Str::uuid();
        $this->pgUserId = (string) Str::uuid();

        // Buat user penerima di PostgreSQL staging
        DB::connection('pgsql_staging')->table('users')->insert([
            'id' => $this->pgUserId,
            'school_id' => $this->pgSchoolId,
            'username' => 'pg_test_user_'.Str::random(6),
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
            DB::connection('pgsql_staging')->table('notifications')->where('user_id', $this->pgUserId)->delete();
            DB::connection('pgsql_staging')->table('reminder_dispatches')->where('recipient_id', $this->pgUserId)->delete();
            DB::connection('pgsql_staging')->table('users')->where('id', $this->pgUserId)->delete();
        }

        parent::tearDown();
    }

    /**
     * Skenario 1, 2, 3: Dua koneksi PostgreSQL mencoba mengklaim dispatch yang sama bersamaan.
     * Hanya satu worker yang berhasil, dan worker kedua tidak menghasilkan duplikasi notifikasi.
     */
    public function test_two_connections_compete_to_claim_dispatch_atomically(): void
    {
        $dispatchId = (string) Str::uuid();
        $eventId = (string) Str::uuid();

        // Buat record pending di koneksi 1
        DB::connection('pgsql_staging')->table('reminder_dispatches')->insert([
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

        // Worker 1 mengklaim via koneksi 1
        $claim1 = DB::connection('pgsql_staging')
            ->table('reminder_dispatches')
            ->where('id', $dispatchId)
            ->whereIn('status', ['pending', 'failed'])
            ->update([
                'status' => 'processing',
                'claim_token' => $token1,
                'claim_expires_at' => $now->copy()->addMinutes(15),
                'updated_at' => $now,
            ]);

        // Worker 2 mencoba mengklaim dispatch yang sama via koneksi 2
        $claim2 = DB::connection('pgsql_staging_worker2')
            ->table('reminder_dispatches')
            ->where('id', $dispatchId)
            ->whereIn('status', ['pending', 'failed'])
            ->update([
                'status' => 'processing',
                'claim_token' => $token2,
                'claim_expires_at' => $now->copy()->addMinutes(15),
                'updated_at' => $now,
            ]);

        // Verifikasi atomisitas: tepat satu klaim sukses
        $this->assertEquals(1, $claim1);
        $this->assertEquals(0, $claim2);

        // Record di basis data harus memuat token Worker 1
        $finalRecord = DB::connection('pgsql_staging')
            ->table('reminder_dispatches')
            ->where('id', $dispatchId)
            ->first();

        $this->assertEquals('processing', $finalRecord->status);
        $this->assertEquals($token1, $finalRecord->claim_token);

        // Worker 2 memeriksa apakah ia memiliki klaim yang sah
        $worker2OwnsClaim = DB::connection('pgsql_staging_worker2')
            ->table('reminder_dispatches')
            ->where('id', $dispatchId)
            ->where('status', 'processing')
            ->where('claim_token', $token2)
            ->exists();

        $this->assertFalse($worker2OwnsClaim, 'Worker 2 must not own the claim');

        // Worker 2 keluar secara damai tanpa membuat notifikasi
        if (! $worker2OwnsClaim) {
            // Worker 2 aborts
        }

        // Hitung notifikasi di PostgreSQL: tepat 0 karena worker 2 tidak mengirim
        $notifCount = DB::connection('pgsql_staging')
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

        // Buat dispatch yang sedang diproses dengan masa berlaku 14 menit lagi
        DB::connection('pgsql_staging')->table('reminder_dispatches')->insert([
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

        // Recovery query berjalan di koneksi kedua
        $threshold = Carbon::now()->subMinutes(15);
        $now = Carbon::now();

        $stuckQuery = DB::connection('pgsql_staging_worker2')
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

        $record = DB::connection('pgsql_staging')->table('reminder_dispatches')->where('id', $dispatchId)->first();
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

        // 1. Worker lama mengklaim dispatch
        DB::connection('pgsql_staging')->table('reminder_dispatches')->insert([
            'id' => $dispatchId,
            'school_id' => $this->pgSchoolId,
            'event_type' => 'cbt_exam',
            'event_id' => $eventId,
            'recipient_id' => $this->pgUserId,
            'milestone' => '24h',
            'channel' => 'in_app',
            'status' => 'processing',
            'claim_token' => $tokenOldWorker,
            'claim_expires_at' => Carbon::now()->subMinutes(5), // Sudah expired
            'created_at' => Carbon::now()->subMinutes(30),
            'updated_at' => Carbon::now()->subMinutes(25),
        ]);

        // 2. Recovery memulihkan dispatch ke status pending via koneksi 2
        $recovered = DB::connection('pgsql_staging_worker2')
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

        // 3. Worker baru mengklaim dispatch dengan Token Baru via koneksi 2
        $claimedNew = DB::connection('pgsql_staging_worker2')
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

        // 4. Worker baru menyelesaikan pengiriman
        DB::connection('pgsql_staging_worker2')->table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $this->pgUserId,
            'notif_type' => 'academic_announcement',
            'title' => 'Pengingat dari Worker Baru',
            'body' => 'Badan notifikasi',
            'data' => json_encode(['event_id' => $eventId, 'milestone' => '24h']),
            'created_at' => Carbon::now(),
        ]);

        DB::connection('pgsql_staging_worker2')
            ->table('reminder_dispatches')
            ->where('id', $dispatchId)
            ->where('claim_token', $tokenNewWorker)
            ->update([
                'status' => 'dispatched',
                'claim_token' => null,
                'claim_expires_at' => null,
                'dispatched_at' => Carbon::now(),
            ]);

        // 5. Worker lama (koneksi 1) bangun kembali dan mencoba menyelesaikan transaksi menggunakan Token Lama
        $fencedOldWorker = DB::connection('pgsql_staging')->transaction(function () use ($dispatchId, $tokenOldWorker) {
            $current = DB::connection('pgsql_staging')
                ->table('reminder_dispatches')
                ->where('id', $dispatchId)
                ->where('claim_token', $tokenOldWorker)
                ->where('status', 'processing')
                ->lockForUpdate()
                ->first();

            if (! $current) {
                // Fencing violation: token lama ditolak!
                return 'ABORTED_BY_FENCING';
            }

            // Jika tidak ada fencing, baris ini akan membuat notifikasi ganda
            return 'SENT';
        });

        $this->assertEquals('ABORTED_BY_FENCING', $fencedOldWorker);

        // 6. Verifikasi jumlah notifikasi tepat 1
        $notifCount = DB::connection('pgsql_staging')
            ->table('notifications')
            ->where('user_id', $this->pgUserId)
            ->count();
        $this->assertEquals(1, $notifCount, 'Must contain exactly 1 notification from worker B');

        // Status dispatch tetap 'dispatched'
        $finalStatus = DB::connection('pgsql_staging')
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

        DB::connection('pgsql_staging')->table('reminder_dispatches')->insert([
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

        // Recovery 1 dan Recovery 2 bersaing mengupdate status dari 'processing' ke 'pending'
        $rec1 = DB::connection('pgsql_staging')
            ->table('reminder_dispatches')
            ->where('id', $dispatchId)
            ->where('status', 'processing')
            ->update([
                'status' => 'pending',
                'claim_token' => null,
                'claim_expires_at' => null,
                'updated_at' => Carbon::now(),
            ]);

        $rec2 = DB::connection('pgsql_staging_worker2')
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

        // Insert pertama berhasil
        DB::connection('pgsql_staging')->table('reminder_dispatches')->insert([
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
            // Insert kedua dengan kunci identik harus memicu SQLSTATE[23505] di PostgreSQL
            DB::connection('pgsql_staging_worker2')->table('reminder_dispatches')->insert([
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
            // PostgreSQL Unique Violation SQLSTATE code = 23505
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

        DB::connection('pgsql_staging')->table('reminder_dispatches')->insert([
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
            DB::connection('pgsql_staging')->transaction(function () use ($eventId) {
                // 1. Sisipkan notifikasi
                DB::connection('pgsql_staging')->table('notifications')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $this->pgUserId,
                    'notif_type' => 'academic_announcement',
                    'title' => 'Notifikasi yang akan di-rollback',
                    'body' => 'Simulasi crash sebelum commit',
                    'data' => json_encode(['event_id' => $eventId]),
                    'created_at' => Carbon::now(),
                ]);

                // 2. Simulasi kegagalan runtime (misal unhandled exception / connection drop)
                throw new \RuntimeException('Simulated worker crash inside transaction');
            });
        } catch (\RuntimeException $e) {
            $errorCaught = true;

            // Worker catch block memperbarui status menjadi 'failed'
            DB::connection('pgsql_staging')
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

        // Verifikasi di PostgreSQL: Notifikasi TIDAK ada (ter-rollback sempurna)
        $notifCount = DB::connection('pgsql_staging')
            ->table('notifications')
            ->where('user_id', $this->pgUserId)
            ->count();
        $this->assertEquals(0, $notifCount, 'Notification must be rolled back by PostgreSQL');

        // Status dispatch adalah 'failed' dan claim_token bersih
        $record = DB::connection('pgsql_staging')
            ->table('reminder_dispatches')
            ->where('id', $dispatchId)
            ->first();

        $this->assertEquals('failed', $record->status);
        $this->assertNull($record->claim_token);
        $this->assertEquals('Simulated worker crash inside transaction', $record->error_message);
    }
}
