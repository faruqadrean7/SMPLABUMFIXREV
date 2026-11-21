<?php

namespace App\Services\Ntf;

use App\Models\AuditLog;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MessagingService
{
    // =========================================================================
    // 1. PENGELOLAAN TEMPLATE NOTIFIKASI
    // =========================================================================

    public function listTemplates(array $filters): LengthAwarePaginator
    {
        $query = NotificationTemplate::orderBy('code', 'asc');

        if (! empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['search'])) {
            $kw = '%'.strtolower($filters['search']).'%';
            $query->where(function ($q) use ($kw) {
                $q->whereRaw('LOWER(code) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(template_text) LIKE ?', [$kw]);
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function getTemplate(string $id): NotificationTemplate
    {
        return NotificationTemplate::with('logs')->findOrFail($id);
    }

    public function createTemplate(array $data, Request $request): NotificationTemplate
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $template = NotificationTemplate::create([
            'school_id' => $data['school_id'] ?? null,
            'code' => strtoupper($data['code']),
            'name' => $data['name'],
            'channel' => $data['channel'],
            'subject' => $data['subject'] ?? null,
            'template_text' => $data['template_text'],
            'variables' => $data['variables'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->logAudit($user?->id, 'notification_template.create', 'notification_templates', $template->id, null, $template->toArray(), $request);

        return $template;
    }

    public function updateTemplate(string $id, array $data, Request $request): NotificationTemplate
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $template = NotificationTemplate::findOrFail($id);
        $oldValues = $template->toArray();

        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $template->update($data);

        $this->logAudit($user?->id, 'notification_template.update', 'notification_templates', $template->id, $oldValues, $template->fresh()->toArray(), $request);

        return $template->fresh();
    }

    public function deleteTemplate(string $id, Request $request): bool
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $template = NotificationTemplate::findOrFail($id);
        $oldValues = $template->toArray();

        $template->delete();

        $this->logAudit($user?->id, 'notification_template.delete', 'notification_templates', $id, $oldValues, null, $request);

        return true;
    }

    // =========================================================================
    // 2. ENGINE PENGIRIMAN PESAN & ADAPTER PROVIDER (WHATSAPP & EMAIL)
    // =========================================================================

    public function sendNotification(array $data, Request $request): NotificationLog
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        // Cari template bila ditentukan
        $template = null;
        if (! empty($data['template_id'])) {
            $template = NotificationTemplate::findOrFail($data['template_id']);
        } elseif (! empty($data['template_code'])) {
            $template = NotificationTemplate::where('code', strtoupper($data['template_code']))->firstOrFail();
        }

        if ($template && ! $template->is_active) {
            throw new HttpException(422, 'Template notifikasi sedang tidak aktif.');
        }

        $channel = $data['channel'] ?? ($template?->channel ?? 'wa');
        $recipient = $data['recipient'];
        $parameters = $data['parameters'] ?? $data['params'] ?? [];

        // Render konten pesan
        if ($template) {
            $rendered = $template->render($parameters);
            $subject = $data['subject'] ?? $rendered['subject'];
            $body = $rendered['body'];
        } else {
            $subject = $data['subject'] ?? null;
            $body = $data['body'] ?? '';
            if (empty($body)) {
                throw new HttpException(422, 'Konten pesan atau template wajib disediakan.');
            }
        }

        // Validasi format penerima
        $this->validateRecipientFormat($channel, $recipient);

        // Eksekusi dispatch ke provider adapter
        $dispatchResult = $this->dispatchToProvider($channel, $recipient, $subject, $body);

        $log = NotificationLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $data['user_id'] ?? null,
            'template_id' => $template?->id,
            'channel' => $channel,
            'recipient' => $recipient,
            'subject' => $subject,
            'body' => $body,
            'payload' => $parameters,
            'status' => $dispatchResult['status'],
            'provider_ref' => $dispatchResult['provider_ref'],
            'error' => $dispatchResult['error'],
            'sent_at' => $dispatchResult['status'] === 'sent' ? Carbon::now() : null,
            'retry_count' => 0,
        ]);

        $this->logAudit($user?->id, 'notification.send', 'notification_logs', $log->id, null, [
            'channel' => $channel,
            'recipient' => $recipient,
            'status' => $dispatchResult['status'],
        ], $request);

        return $log->fresh(['template', 'user']);
    }

    public function retryNotification(string $logId, Request $request): NotificationLog
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $log = NotificationLog::findOrFail($logId);

        if ($log->status === 'sent') {
            throw new HttpException(422, "Hanya notifikasi dengan status 'failed' yang dapat dikirim ulang.");
        }

        // Re-dispatch ke provider
        $dispatchResult = $this->dispatchToProvider($log->channel, $log->recipient, $log->subject, $log->body);

        $log->update([
            'status' => $dispatchResult['status'],
            'provider_ref' => $dispatchResult['provider_ref'],
            'error' => $dispatchResult['error'],
            'sent_at' => $dispatchResult['status'] === 'sent' ? Carbon::now() : null,
            'retry_count' => $log->retry_count + 1,
        ]);

        $this->logAudit($user?->id, 'notification.retry', 'notification_logs', $log->id, null, [
            'status' => $dispatchResult['status'],
            'retry_count' => $log->retry_count,
        ], $request);

        return $log->fresh(['template', 'user']);
    }

    // =========================================================================
    // 3. RIWAYAT & LOG PENGIRIMAN NOTIFIKASI
    // =========================================================================

    public function listLogs(array $filters): LengthAwarePaginator
    {
        $query = NotificationLog::with(['template', 'user'])
            ->orderBy('created_at', 'desc');

        if (! empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['recipient'])) {
            $query->where('recipient', 'like', '%'.$filters['recipient'].'%');
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function getLog(string $id): NotificationLog
    {
        return NotificationLog::with(['template', 'user'])->findOrFail($id);
    }

    // =========================================================================
    // 4. PROVIDER DISPATCH ADAPTER (SIMULATION & EXTENSIBILITY)
    // =========================================================================

    protected function dispatchToProvider(string $channel, string $recipient, ?string $subject, string $body): array
    {
        // Simulasi kegagalan provider bila nomor/email mengandung kata "fail" atau "invalid"
        if (str_contains(strtolower($recipient), 'invalid') || str_contains(strtolower($recipient), 'fail')) {
            return [
                'status' => 'failed',
                'provider_ref' => null,
                'error' => 'Provider error: Invalid recipient destination or network gateway timeout.',
            ];
        }

        $refId = match ($channel) {
            'wa', 'whatsapp' => 'wa_msg_'.Str::random(16),
            'email' => 'email_msg_'.Str::random(16),
            default => 'push_msg_'.Str::random(16),
        };

        return [
            'status' => 'sent',
            'provider_ref' => $refId,
            'error' => null,
        ];
    }

    protected function validateRecipientFormat(string $channel, string $recipient): void
    {
        if ($channel === 'email') {
            if (! filter_var($recipient, FILTER_VALIDATE_EMAIL) && ! str_contains($recipient, 'fail')) {
                throw new HttpException(422, 'Format alamat email penerima tidak valid.');
            }
        } elseif ($channel === 'wa' || $channel === 'whatsapp') {
            // Minimal 8 digit nomor telepon
            $cleanNumber = preg_replace('/[^0-9]/', '', $recipient);
            if (strlen($cleanNumber) < 8 && ! str_contains($recipient, 'fail')) {
                throw new HttpException(422, 'Format nomor WhatsApp penerima tidak valid.');
            }
        }
    }

    public function isStaffOrAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin', 'admin_tu', 'principal', 'humas', 'kesiswaan']) ||
            $user->hasPermission('notification.broadcast');
    }

    protected function authorizeStaffOrAdmin(?User $user): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        if (! $this->isStaffOrAdmin($user)) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang mengelola template atau mengirim pesan notifikasi.');
        }
    }

    protected function logAudit(?string $actorId, string $action, string $entity, ?string $entityId, ?array $oldValue, ?array $newValue, Request $request): void
    {
        try {
            AuditLog::create([
                'actor_id' => $actorId,
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Jangan gagalkan transaksi bila audit gagal
        }
    }
}
