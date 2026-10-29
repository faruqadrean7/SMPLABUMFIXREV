<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            static::recordAuditLog($model, 'created', null, static::auditSafe($model, $model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $old = array_intersect_key($model->getOriginal(), $model->getDirty());
            $new = $model->getDirty();
            static::recordAuditLog($model, 'updated', static::auditSafe($model, $old), static::auditSafe($model, $new));
        });

        static::deleted(function (Model $model) {
            static::recordAuditLog($model, 'deleted', static::auditSafe($model, $model->getAttributes()), null);
        });
    }

    /**
     * Buang atribut tersembunyi (mis. password_hash, token) dan nilai terenkripsi
     * dari payload audit agar rahasia tidak pernah tercatat di audit_logs.
     */
    protected static function auditSafe(Model $model, array $values): array
    {
        $hidden = array_flip($model->getHidden());
        $redactKeys = ['password_hash', 'password', 'remember_token', 'token_hash', 'nik_enc', 'nik_hash', 'fcm_token'];

        foreach (array_keys($values) as $key) {
            if (isset($hidden[$key]) || in_array($key, $redactKeys, true)) {
                $values[$key] = '[REDACTED]';
            }
        }

        return $values;
    }

    protected static function recordAuditLog(Model $model, string $action, ?array $oldValue, ?array $newValue): void
    {
        if ($model instanceof AuditLog) {
            return;
        }

        try {
            AuditLog::create([
                'school_id' => $model->school_id ?? null,
                'actor_id' => Auth::id(),
                'action' => $action,
                'entity' => class_basename($model),
                'entity_id' => $model->getKey(),
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'ip' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Jangan menghentikan transaksi bisnis jika audit log gagal sementara.
            report($e);
        }
    }
}
