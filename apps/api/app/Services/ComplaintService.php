<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintEvent;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ComplaintService
{
    /**
     * BE-GUEST-02: Public complaint / whistleblowing submission.
     * Menerima aduan publik dengan opsi anonim (whistleblower protection).
     */
    public function submitComplaint(array $data, Request $request): array
    {
        return DB::transaction(function () use ($data, $request) {
            $isAnonymous = (bool) ($data['is_anonymous'] ?? false);
            $currentUser = $request->user();

            // AC2: Jika anonim, reporter_user_id wajib NULL
            $reporterUserId = $isAnonymous ? null : ($currentUser?->id ?? null);

            // AC3: Format kode tiket pelacak unik acak: ASP-YYYYMMDD-XXXX
            $datePrefix = Carbon::now()->format('Ymd');
            $ticketCode = 'ASP-'.$datePrefix.'-'.strtoupper(Str::random(4));
            while (Complaint::where('ticket_code', $ticketCode)->exists()) {
                $ticketCode = 'ASP-'.$datePrefix.'-'.strtoupper(Str::random(4));
            }

            $complaint = Complaint::create([
                'school_id' => $data['school_id'] ?? null,
                'reporter_user_id' => $reporterUserId,
                'ticket_code' => $ticketCode,
                'category' => $data['category'],
                'content' => trim($data['content']),
                'is_anonymous' => $isAnonymous,
                'status' => 'baru',
                'assigned_to' => null,
            ]);

            // Catat event awal pada timeline
            ComplaintEvent::create([
                'complaint_id' => $complaint->id,
                'actor_id' => null,
                'event_type' => 'created',
                'response_text' => 'Aspirasi / pengaduan berhasil didaftarkan dan menunggu peninjauan tim sekolah.',
                'created_at' => Carbon::now(),
            ]);

            // AC2: Whistleblower protection - jika anonim, IP dan user agent DILARANG disimpan
            $auditActorId = $isAnonymous ? null : ($currentUser?->id ?? null);
            $auditIp = $isAnonymous ? null : $request->ip();
            $auditUserAgent = $isAnonymous ? null : $request->userAgent();

            $this->logAudit(
                $auditActorId,
                'complaint.submitted',
                'Complaint',
                $complaint->id,
                null,
                [
                    'ticket_code' => $complaint->ticket_code,
                    'category' => $complaint->category,
                    'is_anonymous' => $isAnonymous,
                ],
                $auditIp,
                $auditUserAgent
            );

            // BE-REPORT-02: Notifikasi ringan ke BK & Kepsek untuk aduan bullying (doc 09 §6.7)
            if ($complaint->category === 'bullying') {
                $this->notifyBullyingReport($complaint);
            }

            return [
                'ticket_code' => $complaint->ticket_code,
                'category' => $complaint->category,
                'is_anonymous' => $complaint->is_anonymous,
                'status' => $complaint->status,
                'created_at' => $complaint->created_at->toIso8601String(),
                'message' => 'Pengaduan berhasil dikirim. Simpan kode tiket ini untuk memeriksa perkembangan penanganan secara berkala.',
            ];
        });
    }

    /**
     * BE-GUEST-02: Public track complaint status by ticket code.
     * Pelapor memeriksa perkembangan aduan tanpa login.
     */
    public function trackComplaint(string $ticketCode): array
    {
        $complaint = Complaint::with(['events'])
            ->where('ticket_code', trim($ticketCode))
            ->first();

        if (! $complaint) {
            throw new HttpException(404, 'Data pengaduan dengan kode tiket tersebut tidak ditemukan');
        }

        // Jangan pernah membocorkan identitas pelapor ke endpoint publik
        return [
            'ticket_code' => $complaint->ticket_code,
            'category' => $complaint->category,
            'content' => $complaint->content,
            'status' => $complaint->status,
            'created_at' => $complaint->created_at->toIso8601String(),
            'updated_at' => $complaint->updated_at->toIso8601String(),
            'timeline' => $complaint->events->map(function (ComplaintEvent $event) {
                return [
                    'event_type' => $event->event_type,
                    'response_text' => $event->response_text,
                    'created_at' => $event->created_at->toIso8601String(),
                ];
            })->values()->all(),
        ];
    }

    /**
     * BE-GUEST-02: List complaints for authorized staff (Kepala Sekolah & Guru BK).
     */
    public function listComplaints(array $filters, Request $request): LengthAwarePaginator
    {
        $this->verifyComplaintAccess($request->user());

        /** @var User $currentUser */
        $currentUser = $request->user();

        $query = Complaint::with(['reporter', 'assignedToUser', 'events']);

        // Multi-tenant school isolation
        if (! empty($currentUser->school_id)) {
            $query->where(function ($q) use ($currentUser) {
                $q->where('school_id', $currentUser->school_id)->orWhereNull('school_id');
            });
        }

        if (! empty($filters['q'])) {
            $q = trim($filters['q']);
            $query->where(function ($sub) use ($q) {
                $sub->where('ticket_code', 'like', "%{$q}%")
                    ->orWhere('category', 'like', "%{$q}%")
                    ->orWhere('content', 'like', "%{$q}%");
            });
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        if (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Hak akses melihat identitas pelapor non-anonim
        $canViewReporter = $this->canViewReporterIdentity($currentUser);

        if (! $canViewReporter) {
            $paginator->getCollection()->transform(function (Complaint $complaint) {
                if (! $complaint->is_anonymous) {
                    $complaint->setRelation('reporter', null);
                    $complaint->reporter_user_id = null;
                }

                return $complaint;
            });
        }

        return $paginator;
    }

    /**
     * BE-GUEST-02: Get complaint detail for authorized staff.
     */
    public function getComplaintDetail(string $id, Request $request): Complaint
    {
        $this->verifyComplaintAccess($request->user());

        /** @var User $currentUser */
        $currentUser = $request->user();

        $complaint = Complaint::with(['reporter', 'assignedToUser', 'events.actor'])->findOrFail($id);

        if (! empty($currentUser->school_id) && ! empty($complaint->school_id) && $complaint->school_id !== $currentUser->school_id) {
            throw new HttpException(403, 'Akses ditolak: Pengaduan berada di luar sekolah penugasan Anda.');
        }

        // BE-REPORT-02 (AC1): Audit-baca saat detail aduan diakses oleh staf berwenang
        $this->logAudit(
            $currentUser->id,
            'complaint.read_detail',
            'Complaint',
            $complaint->id,
            null,
            [
                'ticket_code' => $complaint->ticket_code,
                'category' => $complaint->category,
                'is_anonymous' => (bool) $complaint->is_anonymous,
            ],
            $request->ip(),
            $request->userAgent()
        );

        if (! $this->canViewReporterIdentity($currentUser) && ! $complaint->is_anonymous) {
            $complaint->setRelation('reporter', null);
            $complaint->reporter_user_id = null;
        }

        return $complaint;
    }

    /**
     * BE-GUEST-02: Update complaint status and record response event.
     */
    public function updateStatus(string $id, array $data, Request $request): Complaint
    {
        $this->verifyComplaintAccess($request->user(), requireManage: true);

        /** @var User $currentUser */
        $currentUser = $request->user();

        $complaint = Complaint::with('events')->findOrFail($id);

        if (! empty($currentUser->school_id) && ! empty($complaint->school_id) && $complaint->school_id !== $currentUser->school_id) {
            throw new HttpException(403, 'Akses ditolak: Pengaduan berada di luar sekolah penugasan Anda.');
        }

        $newStatus = $data['status'];
        $currentStatus = $complaint->status;

        // Validasi alur status (tidak boleh kembali ke status 'baru' setelah selesai/ditolak)
        if (in_array($currentStatus, ['selesai', 'ditolak'], true) && in_array($newStatus, ['baru', 'ditinjau'], true)) {
            throw new HttpException(422, "Pengaduan yang sudah berstatus '{$currentStatus}' tidak dapat dikembalikan ke status '{$newStatus}'.");
        }

        return DB::transaction(function () use ($complaint, $newStatus, $data, $currentUser, $request) {
            $oldStatus = $complaint->status;
            $oldAssignedTo = $complaint->assigned_to;

            $complaint->status = $newStatus;
            if (array_key_exists('assigned_to', $data)) {
                $complaint->assigned_to = $data['assigned_to'];
            }
            $complaint->save();

            // Buat event perkembangan pada timeline
            ComplaintEvent::create([
                'complaint_id' => $complaint->id,
                'actor_id' => $currentUser->id,
                'event_type' => 'status_update',
                'response_text' => trim($data['response_text']),
                'created_at' => Carbon::now(),
            ]);

            // Audit Log
            $this->logAudit(
                $currentUser->id,
                'complaint.status_updated',
                'Complaint',
                $complaint->id,
                [
                    'status' => $oldStatus,
                    'assigned_to' => $oldAssignedTo,
                ],
                [
                    'status' => $complaint->status,
                    'assigned_to' => $complaint->assigned_to,
                    'notes' => trim($data['response_text']),
                ],
                $request->ip(),
                $request->userAgent()
            );

            $refreshed = $complaint->fresh(['reporter', 'assignedToUser', 'events.actor']);

            if (! $this->canViewReporterIdentity($currentUser) && ! $refreshed->is_anonymous) {
                $refreshed->setRelation('reporter', null);
                $refreshed->reporter_user_id = null;
            }

            return $refreshed;
        });
    }

    /**
     * Memeriksa apakah pengguna memiliki wewenang melihat identitas pelapor non-anonim.
     */
    protected function canViewReporterIdentity(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('super_admin') || $user->hasRole('principal')) {
            return true;
        }

        return $user->hasPermission('complaint.view_reporter');
    }

    /**
     * Decision #9: Verifikasi hak akses khusus Kepala Sekolah dan Guru BK.
     */
    protected function verifyComplaintAccess(?User $user, bool $requireManage = false): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        // Super Admin selalu memiliki akses penuh
        if ($user->hasRole('super_admin')) {
            return;
        }

        // Decision #9: Kepala Sekolah & Guru BK
        if ($user->hasRole('principal') || $user->hasRole('counselor')) {
            return;
        }

        // Role/User dengan permission eksplisit
        $perm = $requireManage ? 'complaint.manage' : 'complaint.read';
        if ($user->hasPermission($perm)) {
            return;
        }

        throw new HttpException(403, 'Akses ditolak: Hanya Kepala Sekolah, Guru BK, dan pejabat berwenang yang dapat mengakses layanan aspirasi dan pengaduan.');
    }

    /**
     * Helper to write an audit log entry.
     */
    protected function logAudit(
        ?string $actorId,
        string $action,
        string $entity,
        ?string $entityId,
        ?array $oldValue,
        ?array $newValue,
        ?string $ip,
        ?string $userAgent
    ): void {
        try {
            AuditLog::create([
                'actor_id' => $actorId,
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * BE-REPORT-02 (AC2): Notifikasi ringan ke Guru BK dan Kepala Sekolah saat ada laporan bullying.
     * Aturan ketat 09_SECURITY_PRIVACY §6.7: DILARANG menyertakan isi aduan atau data identitas pelapor.
     */
    protected function notifyBullyingReport(Complaint $complaint): void
    {
        try {
            $query = User::where('status', 'active');

            if (! empty($complaint->school_id)) {
                $query->where(function ($q) use ($complaint) {
                    $q->where('school_id', $complaint->school_id)->orWhereNull('school_id');
                });
            }

            $staffUsers = $query->whereHas('roles', function ($q) {
                $q->whereIn('code', [
                    'counselor',
                    'guru_bk',
                    'principal',
                    'kepala_sekolah',
                    'super_admin',
                ]);
            })->get();

            foreach ($staffUsers as $staff) {
                Notification::create([
                    'user_id' => $staff->id,
                    'notif_type' => 'complaint.bullying_alert',
                    'title' => 'Laporan Baru: Perundungan (Bullying)',
                    'body' => "Terdapat pengaduan baru kategori Perundungan dengan kode tiket {$complaint->ticket_code}. Harap segera ditinjau.",
                    'data' => [
                        'ticket_code' => $complaint->ticket_code,
                        'category' => $complaint->category,
                        'action_url' => '/admin/public-relations?tab=complaints&category=bullying',
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
