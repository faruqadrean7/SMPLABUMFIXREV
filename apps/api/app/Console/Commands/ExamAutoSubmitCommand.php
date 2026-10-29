<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ExamSession;
use App\Services\Cbt\ExamGradingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExamAutoSubmitCommand extends Command
{
    protected $signature = 'exam:auto-submit {--grace-seconds= : Toleransi waktu keterlambatan dalam detik}';

    protected $aliases = ['cbt:close-expired-sessions'];

    protected $description = 'Submit otomatis sesi ujian CBT yang melewati batas waktu deadline pengerjaan';

    public function handle(ExamGradingService $gradingService): int
    {
        $now = Carbon::now();
        $graceSeconds = (int) ($this->option('grace-seconds') ?? config('cbt.grace_period_seconds', 5));
        $cutoff = $now->copy()->subSeconds($graceSeconds);

        // Ambil ID sesi kandidat yang kedaluwarsa untuk diproses satu demi satu dengan transaksi & lock
        $expiredSessionIds = ExamSession::whereIn('status', ['mengerjakan', 'in_progress'])
            ->where(function ($query) use ($cutoff) {
                $query->where(function ($q) use ($cutoff) {
                    $q->whereNotNull('ends_at')
                        ->where('ends_at', '<=', $cutoff);
                })->orWhere(function ($q) use ($cutoff) {
                    $q->whereNull('ends_at')
                        ->whereNotNull('deadline_at')
                        ->where('deadline_at', '<=', $cutoff);
                });
            })
            ->pluck('id');

        $count = 0;
        foreach ($expiredSessionIds as $sessionId) {
            try {
                DB::transaction(function () use ($sessionId, $cutoff, $gradingService, &$count) {
                    // Lock sesi untuk mencegah race condition antar-worker yang berjalan bersamaan
                    $session = ExamSession::where('id', $sessionId)
                        ->whereIn('status', ['mengerjakan', 'in_progress'])
                        ->lockForUpdate()
                        ->first();

                    if (! $session) {
                        return; // Sesi telah diproses oleh worker lain atau sudah ditutup
                    }

                    // Verifikasi ulang deadline
                    $effectiveDeadline = $session->ends_at ?? $session->deadline_at;
                    if (! $effectiveDeadline || $effectiveDeadline->greaterThan($cutoff)) {
                        return; // Belum melewati batas waktu cutoff
                    }

                    $session->status = 'selesai';
                    $session->submitted_at = $effectiveDeadline;
                    $session->save();

                    // Evaluasi otomatis butir soal objektif & kalkulasi total skor sesi
                    try {
                        $gradingService->gradeSessionObjectiveAnswers($session);
                    } catch (\Throwable $gradeEx) {
                        Log::warning("Auto-grade failed for auto-submitted session {$session->id}: {$gradeEx->getMessage()}");
                    }

                    AuditLog::create([
                        'action' => 'EXAM_SESSION_AUTO_SUBMITTED',
                        'entity' => 'exam_sessions',
                        'entity_id' => $session->id,
                        'new_value' => [
                            'status' => 'selesai',
                            'submitted_at' => $effectiveDeadline->toIso8601String(),
                        ],
                    ]);

                    $count++;
                });
            } catch (\Throwable $e) {
                Log::error("Failed to auto-submit CBT session {$sessionId}: {$e->getMessage()}", [
                    'session_id' => $sessionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Berhasil mengumpulkan otomatis {$count} sesi ujian CBT yang melewati batas waktu.");

        return Command::SUCCESS;
    }
}
