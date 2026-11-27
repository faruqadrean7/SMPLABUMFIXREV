<?php

declare(strict_types=1);
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\FinanceService;
use App\Services\GradeRecorder;
use App\Services\PPDBEnrollmentService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * T2B-CONC-01: Real OS child-process worker for permanent PostgreSQL concurrency tests.
 *
 * Boots Laravel using active PostgreSQL environment/config (zero hardcoded credentials),
 * opens an independent PostgreSQL backend session (pg_backend_pid), waits on a shared
 * timestamp barrier for real parallel contention, and executes production Service/Model code.
 */
$apiRoot = dirname(__DIR__, 2);
require $apiRoot.'/vendor/autoload.php';

$app = require $apiRoot.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$base = config('database.connections.pgsql', []);

$host = getenv('PG_CONCURRENCY_HOST');
$port = getenv('PG_CONCURRENCY_PORT');
$database = getenv('PG_CONCURRENCY_DATABASE');
$username = getenv('PG_CONCURRENCY_USERNAME');
$password = getenv('PG_CONCURRENCY_PASSWORD');
$sslmode = getenv('PG_CONCURRENCY_SSLMODE');

config([
    'database.default' => 'pgsql',
    'database.connections.pgsql' => array_merge($base, [
        'driver' => 'pgsql',
        'host' => ($host !== false && $host !== '') ? $host : ($base['host'] ?? '127.0.0.1'),
        'port' => (int) (($port !== false && $port !== '') ? $port : ($base['port'] ?? 5432)),
        'database' => ($database !== false && $database !== '') ? $database : ($base['database'] ?? 'sistlabum_testing'),
        'username' => ($username !== false && $username !== '') ? $username : ($base['username'] ?? 'postgres'),
        'password' => $password !== false ? (string) $password : (string) ($base['password'] ?? ''),
        'sslmode' => ($sslmode !== false && $sslmode !== '') ? $sslmode : ($base['sslmode'] ?? 'prefer'),
    ]),
]);
DB::purge('pgsql');

$payload = json_decode($argv[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
$action = (string) ($payload['action'] ?? '');
$barrierAt = (float) ($payload['barrier_at'] ?? 0.0);

// Buka koneksi PostgreSQL sebelum barrier agar latensi TCP tidak mengurangi kontensi paralel
$pid = (int) DB::selectOne('SELECT pg_backend_pid() AS pid')->pid;

while (microtime(true) < $barrierAt) {
    usleep(500);
}

$startedAt = microtime(true);

try {
    $result = null;

    if ($action === 'verify_payment') {
        /** @var FinanceService $service */
        $service = $app->make(FinanceService::class);
        $payment = Payment::findOrFail($payload['payment_id']);
        $verifier = User::findOrFail($payload['actor_id']);
        $verified = $service->verifyPayment($payment, 'Concurrent verify test', $verifier);
        $result = [
            'payment_id' => $verified->id,
            'status' => $verified->status,
            'receipt_number' => $verified->receipt_number,
        ];
    } elseif ($action === 'submit_payment') {
        /** @var FinanceService $service */
        $service = $app->make(FinanceService::class);
        $invoice = Invoice::findOrFail($payload['invoice_id']);
        $actor = User::findOrFail($payload['actor_id']);

        $tmpPng = tempnam(sys_get_temp_dir(), 'conc_proof_');
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        file_put_contents($tmpPng, $pngBytes);
        $uploadedFile = new UploadedFile($tmpPng, 'bukti.png', 'image/png', null, true);

        try {
            $payment = $service->submitPayment($invoice, [
                'amount' => $payload['amount'],
                'channel' => 'Transfer Bank',
                'channel_type' => 'manual_transfer',
                'external_reference' => $payload['external_reference'],
                'payment_date' => date('Y-m-d'),
            ], $uploadedFile, $actor);

            $result = [
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'amount' => (int) $payment->amount,
            ];
        } finally {
            @unlink($tmpPng);
        }
    } elseif ($action === 'cancel_invoice') {
        /** @var FinanceService $service */
        $service = $app->make(FinanceService::class);
        $invoice = Invoice::findOrFail($payload['invoice_id']);
        $actor = User::findOrFail($payload['actor_id']);
        $cancelled = $service->cancelInvoice($invoice, 'Concurrent cancel test', $actor);
        $result = [
            'invoice_id' => $cancelled->id,
            'status' => $cancelled->status,
        ];
    } elseif ($action === 'create_invoice') {
        /** @var FinanceService $service */
        $service = $app->make(FinanceService::class);
        $actor = User::findOrFail($payload['actor_id']);
        $invoice = $service->createInvoice([
            'student_id' => $payload['student_id'],
            'period' => date('Y-m'),
            'due_date' => date('Y-m-d', strtotime('+14 days')),
            'items' => [
                [
                    'fee_type_id' => $payload['fee_type_id'],
                    'amount' => 500000,
                    'discount' => 0,
                ],
            ],
        ], $actor);
        $result = [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
        ];
    } elseif ($action === 'enroll_applicant') {
        /** @var PPDBEnrollmentService $service */
        $service = $app->make(PPDBEnrollmentService::class);
        $actor = User::findOrFail($payload['actor_id']);
        $request = Request::create('/api/v1/ppdb/applicants/'.$payload['applicant_id'].'/enroll', 'POST');
        $request->setUserResolver(fn () => $actor);
        $enrolled = $service->enrollApplicant($payload['applicant_id'], ['force_enroll' => false], $request);
        $result = [
            'is_idempotent' => $enrolled['is_idempotent'],
            'student_id' => $enrolled['student']->id,
            'nis' => $enrolled['student']->nis,
        ];
    } elseif ($action === 'ensure_system_role') {
        $role = DB::transaction(function () use ($payload) {
            $ensured = Role::ensureSystemRole(
                (string) $payload['role_code'],
                (string) $payload['role_name']
            );
            // Jalankan query tambahan setelah ensureSystemRole untuk membuktikan transaksi tidak aborted (25P02)
            $check = DB::selectOne('SELECT 1 AS alive');
            if ((int) ($check->alive ?? 0) !== 1) {
                throw new RuntimeException('Transaction health check failed');
            }

            return $ensured;
        });

        $result = [
            'role_id' => $role->id,
            'code' => $role->code,
            'name' => $role->name,
        ];
    } elseif ($action === 'grade_recorder_manual') {
        /** @var GradeRecorder $recorder */
        $recorder = $app->make(GradeRecorder::class);
        $grade = $recorder->recordManual(
            studentId: (string) $payload['student_id'],
            componentId: (string) $payload['component_id'],
            periodId: (string) $payload['period_id'],
            value: (float) $payload['value'],
            changedBy: (string) $payload['actor_id'],
            note: (string) ($payload['note'] ?? 'Concurrent manual write'),
        );
        $result = [
            'grade_id' => $grade->id,
            'value' => (float) $grade->value,
            'manual_value' => $grade->manual_value !== null ? (float) $grade->manual_value : null,
            'auto_value' => $grade->auto_value !== null ? (float) $grade->auto_value : null,
            'source' => $grade->source,
        ];
    } elseif ($action === 'grade_recorder_auto') {
        /** @var GradeRecorder $recorder */
        $recorder = $app->make(GradeRecorder::class);
        $grade = $recorder->recordAuto(
            studentId: (string) $payload['student_id'],
            componentId: (string) $payload['component_id'],
            periodId: (string) $payload['period_id'],
            value: (float) $payload['value'],
            changeSource: (string) ($payload['change_source'] ?? GradeRecorder::SOURCE_CBT),
            changedBy: (string) $payload['actor_id'],
            examId: $payload['exam_id'] ?? null,
            note: (string) ($payload['note'] ?? 'Concurrent auto write'),
        );
        $result = [
            'grade_id' => $grade->id,
            'value' => (float) $grade->value,
            'manual_value' => $grade->manual_value !== null ? (float) $grade->manual_value : null,
            'auto_value' => $grade->auto_value !== null ? (float) $grade->auto_value : null,
            'source' => $grade->source,
        ];
    } else {
        throw new RuntimeException("Unknown concurrency worker action: {$action}");
    }

    echo json_encode([
        'ok' => true,
        'pg_pid' => $pid,
        'os_pid' => getmypid(),
        'elapsed_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        'result' => $result,
    ], JSON_THROW_ON_ERROR);
} catch (ValidationException $e) {
    echo json_encode([
        'ok' => false,
        'type' => 'validation',
        'pg_pid' => $pid,
        'os_pid' => getmypid(),
        'elapsed_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        'errors' => $e->errors(),
        'message' => $e->getMessage(),
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'type' => get_class($e),
        'pg_pid' => $pid,
        'os_pid' => getmypid(),
        'elapsed_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        'message' => $e->getMessage(),
    ], JSON_THROW_ON_ERROR);
}
