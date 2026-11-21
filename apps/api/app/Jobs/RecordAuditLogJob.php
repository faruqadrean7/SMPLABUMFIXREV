<?php

namespace App\Jobs;

use App\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecordAuditLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Jumlah percobaan eksekusi jika terjadi error sementara.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(public array $data) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        AuditLog::create($this->data);
    }
}
