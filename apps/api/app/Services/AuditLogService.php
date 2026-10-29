<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogService
{
    /**
     * GAP-06: Record authoritative or telemetry audit log entry.
     */
    public function record(
        string $action,
        string $entity,
        ?string $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?Request $request = null,
        bool $isAuthoritative = true,
        ?User $actor = null,
        ?string $schoolId = null
    ): AuditLog {
        $request = $request ?? request();
        $actor = $actor ?? $request?->user();

        $actorId = $actor?->id;
        $actorRole = null;
        if ($actor) {
            $actor->loadMissing('roles');
            $actorRole = $actor->roles->pluck('code')->first();
        }

        $ip = $request?->ip();
        $userAgent = $request?->userAgent();
        $requestId = $request?->header('X-Request-Id') ?? (string) Str::uuid();
        $schoolId = $schoolId ?? $actor?->school_id;

        $redactedOld = $oldValues !== null ? $this->redactSensitive($oldValues) : null;
        $redactedNew = $newValues !== null ? $this->redactSensitive($newValues) : null;

        return AuditLog::create([
            'school_id' => $schoolId,
            'actor_id' => $actorId,
            'actor_role' => $actorRole,
            'action' => $action,
            'entity' => $entity,
            'entity_type' => $entity,
            'entity_id' => $entityId,
            'old_value' => $redactedOld,
            'new_value' => $redactedNew,
            'reason' => $reason,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'request_id' => $requestId,
            'is_authoritative' => $isAuthoritative,
            'created_at' => now(),
        ]);
    }

    /**
     * Recursively mask sensitive fields (passwords, tokens, keys).
     */
    public function redactSensitive(array $data): array
    {
        $sensitivePattern = '/(password|password_hash|token|access_token|refresh_token|fcm_token|nik|secret|api_key)/i';

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->redactSensitive($value);
            } elseif (is_string($key) && preg_match($sensitivePattern, $key)) {
                $data[$key] = '[REDACTED]';
            }
        }

        return $data;
    }

    /**
     * BE-AUTH-05: List audit logs with comprehensive filtering and pagination.
     */
    public function list(Request $request): LengthAwarePaginator
    {
        $limit = min($request->integer('limit', 20), 100);

        $query = AuditLog::with(['actor:id,username,email'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('actor_id')) {
            $query->where('actor_id', $request->query('actor_id'));
        }

        if ($request->filled('action')) {
            $action = trim((string) $request->query('action'));
            if (str_contains($action, '*')) {
                $prefix = rtrim($action, '*');
                $query->where('action', 'like', "{$prefix}%");
            } else {
                $query->where('action', $action);
            }
        }

        if ($request->filled('entity')) {
            $query->where('entity', trim((string) $request->query('entity')));
        }

        if ($request->filled('entity_id')) {
            $query->where('entity_id', trim((string) $request->query('entity_id')));
        }

        if ($request->filled('start_date')) {
            $startDate = (string) $request->query('start_date');
            $startDate = preg_replace('/\s(\d{2}:\d{2})$/', '+$1', $startDate);
            try {
                $query->where('created_at', '>=', Carbon::parse($startDate));
            } catch (\Throwable) {
                $query->where('created_at', '>=', $startDate);
            }
        }

        if ($request->filled('end_date')) {
            $endDate = (string) $request->query('end_date');
            $endDate = preg_replace('/\s(\d{2}:\d{2})$/', '+$1', $endDate);
            try {
                $query->where('created_at', '<=', Carbon::parse($endDate));
            } catch (\Throwable) {
                $query->where('created_at', '<=', $endDate);
            }
        }

        if ($request->filled('q')) {
            $q = trim((string) $request->query('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('action', 'like', "%{$q}%")
                    ->orWhere('entity', 'like', "%{$q}%")
                    ->orWhere('ip', 'like', "%{$q}%")
                    ->orWhereHas('actor', function ($actorQuery) use ($q) {
                        $actorQuery->where('username', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%");
                    });
            });
        }

        return $query->paginate($limit);
    }

    /**
     * BE-AUTH-05: Get single audit log detail by ID.
     */
    public function find(string $id): AuditLog
    {
        return AuditLog::with(['actor:id,username,email'])
            ->findOrFail($id);
    }
}
