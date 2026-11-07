<?php

namespace App\Jobs;

use App\Models\QuestionImportJob;
use App\Services\Cbt\DocxQuestionParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ParseQuestionImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $jobId) {}

    public function handle(DocxQuestionParser $parser): void
    {
        /** @var QuestionImportJob|null $job */
        $job = QuestionImportJob::with('sourceFile')->find($this->jobId);
        if (! $job) {
            return;
        }

        $job->update(['status' => 'parsing']);

        try {
            $filePath = null;
            if ($job->sourceFile && $job->sourceFile->object_key) {
                $filePath = Storage::disk(config('filesystems.default', 'local'))->path($job->sourceFile->object_key);
                if (! file_exists($filePath)) {
                    // Coba disk private atau local jika path absolut berbeda
                    $filePath = storage_path('app/'.$job->sourceFile->object_key);
                }
            }

            if (! $filePath || ! file_exists($filePath)) {
                throw new \RuntimeException('Berkas sumber DOCX tidak ditemukan pada storage.');
            }

            $parsedResult = $parser->parse($filePath);

            $status = 'parsed';
            if (empty($parsedResult['questions']) && ! empty($parsedResult['errors'])) {
                $status = 'failed';
            }

            $job->update([
                'status' => $status,
                'parsed_payload' => [
                    'bank_id' => $job->bank_id,
                    'questions' => $parsedResult['questions'],
                ],
                'parse_errors' => $parsedResult['errors'],
            ]);
        } catch (Throwable $e) {
            Log::error("Gagal melakukan parsing DOCX impor soal (Job ID: {$this->jobId}): ".$e->getMessage());

            $job->update([
                'status' => 'failed',
                'parse_errors' => [
                    ['item_number' => 0, 'error' => 'Gagal memproses berkas DOCX: '.$e->getMessage()],
                ],
            ]);
        }
    }
}
