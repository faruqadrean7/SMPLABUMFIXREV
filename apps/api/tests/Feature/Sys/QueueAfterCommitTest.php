<?php

namespace Tests\Feature\Sys;

use App\Jobs\RecordAuditLogJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P1-39: Verifikasi konfigurasi queue after_commit pada transaksi PostgreSQL nyata
 * (tanpa RefreshDatabase agar outer transaction level = 0 sehingga callback commit benar-benar dieksekusi).
 */
class QueueAfterCommitTest extends TestCase
{
    private string $testQueue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testQueue = 'p1_39_'.Str::uuid();
    }

    protected function tearDown(): void
    {
        DB::table('queue_jobs')->where('queue', $this->testQueue)->delete();

        parent::tearDown();
    }

    public function test_p1_39_async_queue_drivers_enable_after_commit_by_default(): void
    {
        $this->assertTrue((bool) config('queue.connections.database.after_commit'));
        $this->assertTrue((bool) config('queue.connections.redis.after_commit'));
        $this->assertTrue((bool) config('queue.connections.sqs.after_commit'));
        $this->assertTrue((bool) config('queue.connections.beanstalkd.after_commit'));
    }

    public function test_p1_39_job_is_persisted_only_after_transaction_commits(): void
    {
        $this->assertSame(0, DB::transactionLevel());

        $countDuringTransaction = null;

        DB::transaction(function () use (&$countDuringTransaction) {
            RecordAuditLogJob::dispatch([
                'action' => 'p1_39.commit_check',
                'entity' => 'QueueProbe',
            ])->onConnection('database')->onQueue($this->testQueue);

            // Selama transaksi belum commit, baris belum boleh masuk ke queue_jobs
            $countDuringTransaction = DB::table('queue_jobs')
                ->where('queue', $this->testQueue)
                ->count();
        });

        $this->assertSame(0, $countDuringTransaction);
        $this->assertSame(1, DB::table('queue_jobs')->where('queue', $this->testQueue)->count());
    }

    public function test_p1_39_job_is_discarded_when_transaction_rolls_back(): void
    {
        $thrown = false;

        try {
            DB::transaction(function () {
                RecordAuditLogJob::dispatch([
                    'action' => 'p1_39.rollback_check',
                    'entity' => 'QueueProbe',
                ])->onConnection('database')->onQueue($this->testQueue);

                throw new \RuntimeException('Simulasi kegagalan transaksi');
            });
        } catch (\RuntimeException $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
        $this->assertSame(0, DB::table('queue_jobs')->where('queue', $this->testQueue)->count());
    }

    public function test_p1_39_nested_transaction_savepoint_rollback_only_dispatches_committed_branch(): void
    {
        DB::transaction(function () {
            RecordAuditLogJob::dispatch([
                'action' => 'p1_39.outer_ok',
                'entity' => 'QueueProbe',
            ])->onConnection('database')->onQueue($this->testQueue);

            try {
                DB::transaction(function () {
                    RecordAuditLogJob::dispatch([
                        'action' => 'p1_39.inner_rolled_back',
                        'entity' => 'QueueProbe',
                    ])->onConnection('database')->onQueue($this->testQueue);

                    throw new \RuntimeException('Rollback savepoint dalam');
                });
            } catch (\RuntimeException $e) {
                // Savepoint dalam gagal; transaksi luar tetap commit.
            }

            // Belum ada job di queue_jobs sebelum transaksi paling luar commit
            $this->assertSame(0, DB::table('queue_jobs')->where('queue', $this->testQueue)->count());
        });

        $jobs = DB::table('queue_jobs')->where('queue', $this->testQueue)->get();

        $this->assertCount(1, $jobs);
        $this->assertStringContainsString('p1_39.outer_ok', (string) $jobs[0]->payload);
        $this->assertStringNotContainsString('p1_39.inner_rolled_back', (string) $jobs[0]->payload);
    }

    public function test_p1_39_outer_rollback_discards_nested_committed_savepoint_jobs(): void
    {
        $thrown = false;

        try {
            DB::transaction(function () {
                DB::transaction(function () {
                    RecordAuditLogJob::dispatch([
                        'action' => 'p1_39.inner_ok_outer_fail',
                        'entity' => 'QueueProbe',
                    ])->onConnection('database')->onQueue($this->testQueue);
                });

                throw new \RuntimeException('Transaksi paling luar gagal');
            });
        } catch (\RuntimeException $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
        $this->assertSame(0, DB::table('queue_jobs')->where('queue', $this->testQueue)->count());
    }
}
