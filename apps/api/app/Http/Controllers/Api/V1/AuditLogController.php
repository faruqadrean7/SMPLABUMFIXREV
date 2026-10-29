<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Audit\StoreAuditTelemetryRequest;
use App\Services\AuditLogService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AuditLogController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * BE-AUTH-05: List Audit Logs (Paginated & Filterable)
     * GET /api/v1/audit-logs
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $logs = $this->auditLogService->list($request);

            return $this->paginatedResponse($logs);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil data log audit', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-AUTH-05: Get Audit Log Detail
     * GET /api/v1/audit-logs/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $log = $this->auditLogService->find($id);

            return $this->successResponse($log);
        } catch (Throwable $e) {
            return $this->errorResponse('Data log audit tidak ditemukan', 'NOT_FOUND', 404);
        }
    }

    /**
     * GAP-06 & SEC-02: Store client UI telemetry with strict validation & throttling.
     * Rejects sensitive actions directly submitted from the client, tags source=client.
     * POST /api/v1/audit-logs
     */
    public function storeTelemetry(StoreAuditTelemetryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $action = strtolower(trim((string) ($validated['action'] ?? $request->input('action'))));
        $entity = strtolower(trim((string) ($validated['entity'] ?? $request->input('entity'))));

        // Aksi bisnis sensitif yang wajib dilakukan lewat endpoint bisnis server:
        // 1. Ubah nilai
        // 2. Kunci / buka rapor
        // 3. Persetujuan eksekutif
        // 4. Penetapan role
        // 5. Ekspor data massal
        $sensitiveActionPatterns = [
            '/^(academic\.)?grade\.(update|entered|bulk_entered|store|create|delete)/',
            '/^(academic\.)?report_card\.(lock|unlock|approve|bulk_lock|bulk_approve)/',
            '/^(progress_report)\.(lock|unlock|approve|bulk_approve)/',
            '/^(auth\.)?role\.(assign|attach|sync)/',
            '/^user\.roles_assigned/',
            '/^(report|data)\.export/',
            '/\b(approve|reject|unlock)\b/',
        ];

        $isSensitiveAction = false;
        foreach ($sensitiveActionPatterns as $pattern) {
            if (preg_match($pattern, $action)) {
                $isSensitiveAction = true;
                break;
            }
        }

        if (! str_starts_with($action, 'ui.') && ! str_starts_with($action, 'telemetry.')) {
            $businessEntities = ['grade', 'reportcard', 'report_card', 'progressreport', 'userrole', 'role'];
            if (in_array(str_replace(['_', '-'], '', $entity), $businessEntities, true)) {
                $isSensitiveAction = true;
            }
        }

        if ($isSensitiveAction) {
            return $this->errorResponse(
                'Aksi bisnis sensitif tidak diizinkan dikirim langsung ke audit log dari klien. Audit ini dicatat secara otomatis dan otoritatif oleh server pada endpoint terkait.',
                'UNPROCESSABLE_ENTITY',
                422
            );
        }

        try {
            $newValues = array_merge(
                (array) ($request->input('new_values') ?? []),
                ['source' => 'client']
            );

            $audit = $this->auditLogService->record(
                action: (string) $request->input('action', 'ui.telemetry'),
                entity: (string) ($request->input('entity') ?? 'ClientTelemetry'),
                entityId: $request->input('entity_id'),
                oldValues: $request->input('old_values'),
                newValues: $newValues,
                reason: $request->input('reason'),
                request: $request,
                isAuthoritative: false
            );

            return $this->successResponse([
                'id' => $audit->id,
                'source' => 'client',
                'is_authoritative' => false,
                'message' => 'Telemetri berhasil dicatat',
            ], 200);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mencatat telemetri UI', 'INTERNAL_ERROR', 500);
        }
    }
}
