<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\ApplicantStatusHistory;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\PPDBTrack;
use App\Models\PPDBWave;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PPDBSelectionService
{
    /**
     * BE-PPDB-06: Automatically rank applicants and allocate quota per track.
     * Quota: rank 1..quota = diterima (selected), rank quota+1..quota+waitlist = cadangan (waitlist), else ditolak (rejected).
     */
    public function rankWaveApplicants(string $waveId, array $data, Request $request): array
    {
        /** @var User $user */
        $user = $request->user();

        $wave = PPDBWave::with('tracks')->findOrFail($waveId);

        return DB::transaction(function () use ($wave, $data, $user, $request) {
            // 1. Update individual scores if provided in payload (with support for Decision #1: 60% Rapor + 40% Tes)
            if (! empty($data['scores']) && is_array($data['scores'])) {
                foreach ($data['scores'] as $scoreItem) {
                    $applicantId = $scoreItem['applicant_id'];
                    $computedScore = null;

                    if (isset($scoreItem['score'])) {
                        $computedScore = (float) $scoreItem['score'];
                    } elseif (isset($scoreItem['rapor_score']) || isset($scoreItem['test_score'])) {
                        $rapor = (float) ($scoreItem['rapor_score'] ?? 0);
                        $test = (float) ($scoreItem['test_score'] ?? 0);

                        // Decision #1: Rapor 60% + Tes 40% (atau bobot formula dinamis per jalur)
                        $targetTrack = isset($scoreItem['track_id']) ? PPDBTrack::find($scoreItem['track_id']) : null;
                        $formula = $targetTrack?->score_formula ?? ['rapor_weight' => 0.60, 'test_weight' => 0.40];
                        $raporWeight = (float) ($formula['rapor_weight'] ?? 0.60);
                        $testWeight = (float) ($formula['test_weight'] ?? 0.40);

                        $computedScore = round(($rapor * $raporWeight) + ($test * $testWeight), 2);
                    }

                    if ($computedScore !== null) {
                        Applicant::where('id', $applicantId)
                            ->where('wave_id', $wave->id)
                            ->update(['score' => $computedScore]);
                    }
                }
            }

            // 2. Resolve tracks to process
            $tracksQuery = $wave->tracks();
            if (! empty($data['track_id'])) {
                $tracksQuery->where('id', $data['track_id']);
            }
            $tracks = $tracksQuery->get();

            $results = [];

            foreach ($tracks as $track) {
                // Get eligible verified applicants (or re-ranking existing decisions)
                $applicants = Applicant::where('track_id', $track->id)
                    ->where('wave_id', $wave->id)
                    ->whereIn('status', ['verified', 'selected', 'waitlist', 'rejected'])
                    ->orderByRaw('CASE WHEN score IS NULL THEN 1 ELSE 0 END ASC, score DESC')
                    ->orderBy('birth_date', 'asc') // Tie-breaker: older birth_date first
                    ->orderBy('created_at', 'asc')  // Tie-breaker 2: earlier submission
                    ->get();

                $quota = (int) $track->quota;
                $waitlistQuota = isset($data['waitlist_quota'])
                    ? (int) $data['waitlist_quota']
                    : (int) ceil($quota * 0.2); // Default 20% waitlist quota

                $stats = [
                    'track_id' => $track->id,
                    'track_name' => $track->name,
                    'quota' => $quota,
                    'waitlist_quota' => $waitlistQuota,
                    'total_applicants' => $applicants->count(),
                    'selected' => 0,
                    'waitlist' => 0,
                    'rejected' => 0,
                ];

                foreach ($applicants as $index => $applicant) {
                    $rank = $index + 1;
                    $oldStatus = $applicant->status;
                    $oldDecision = $applicant->decision;

                    if ($rank <= $quota) {
                        $decision = 'diterima';
                        $status = 'selected';
                        $stats['selected']++;
                    } elseif ($rank <= ($quota + $waitlistQuota)) {
                        $decision = 'cadangan';
                        $status = 'waitlist';
                        $stats['waitlist']++;
                    } else {
                        $decision = 'ditolak';
                        $status = 'rejected';
                        $stats['rejected']++;
                    }

                    $applicant->update([
                        'rank' => $rank,
                        'decision' => $decision,
                        'status' => $status,
                    ]);

                    // Record status history if status transitioned
                    if ($oldStatus !== $status) {
                        ApplicantStatusHistory::create([
                            'applicant_id' => $applicant->id,
                            'from_status' => $oldStatus,
                            'to_status' => $status,
                            'note' => "Penetapan seleksi ranking ke-{$rank}: {$decision}.",
                            'actor_id' => $user->id,
                        ]);
                    }
                }

                $results[] = $stats;
            }

            // 3. Log Audit
            $this->logAudit(
                $user->id,
                'ppdb_selection.ranked',
                'PPDBWave',
                $wave->id,
                null,
                ['results' => $results],
                $request
            );

            return [
                'wave_id' => $wave->id,
                'wave_name' => $wave->name,
                'tracks' => $results,
                'message' => 'Proses seleksi dan perankingan gelombang PPDB berhasil diselesaikan.',
            ];
        });
    }

    /**
     * BE-PPDB-06: Get ranked applicants for a wave.
     */
    public function getRankings(string $waveId, Request $request): array
    {
        $wave = PPDBWave::with(['tracks'])->findOrFail($waveId);

        $query = Applicant::with(['track', 'user'])
            ->where('wave_id', $wave->id)
            ->whereNotNull('rank');

        if ($request->filled('track_id')) {
            $query->where('track_id', $request->query('track_id'));
        }

        if ($request->filled('decision')) {
            $query->where('decision', $request->query('decision'));
        }

        $rankedApplicants = $query->orderBy('rank', 'asc')->get();

        return [
            'wave_id' => $wave->id,
            'wave_name' => $wave->name,
            'result_publish_at' => $wave->result_publish_at?->toIso8601String(),
            'total' => $rankedApplicants->count(),
            'applicants' => $rankedApplicants->map(function (Applicant $applicant) {
                return [
                    'id' => $applicant->id,
                    'registration_no' => $applicant->registration_no,
                    'full_name' => $applicant->full_name,
                    'birth_date' => $applicant->birth_date?->format('Y-m-d'),
                    'track_code' => $applicant->track?->code,
                    'track_name' => $applicant->track?->name,
                    'score' => $applicant->score,
                    'rank' => $applicant->rank,
                    'decision' => $applicant->decision,
                    'status' => $applicant->status,
                ];
            })->all(),
        ];
    }

    /**
     * BE-PPDB-06: Override or cancel decision with mandatory logged reason.
     */
    public function overrideDecision(string $applicantId, array $data, Request $request): Applicant
    {
        /** @var User $user */
        $user = $request->user();

        $applicant = Applicant::with(['wave', 'track', 'statusHistory'])->findOrFail($applicantId);

        $decision = $data['decision'];
        $reason = trim($data['reason']);

        $statusMap = [
            'diterima' => 'selected',
            'cadangan' => 'waitlist',
            'ditolak' => 'rejected',
        ];

        $targetStatus = $statusMap[$decision];

        return DB::transaction(function () use ($applicant, $decision, $targetStatus, $reason, $user, $request) {
            $oldDecision = $applicant->decision;
            $oldStatus = $applicant->status;

            $applicant->update([
                'decision' => $decision,
                'status' => $targetStatus,
            ]);

            // Record history with mandatory reason
            ApplicantStatusHistory::create([
                'applicant_id' => $applicant->id,
                'from_status' => $oldStatus,
                'to_status' => $targetStatus,
                'note' => "Penetapan keputusan manual menjadi {$decision}. Alasan: {$reason}",
                'actor_id' => $user->id,
            ]);

            // Log Audit
            $this->logAudit(
                $user->id,
                'ppdb_selection.decision_override',
                'Applicant',
                $applicant->id,
                [
                    'decision' => $oldDecision,
                    'status' => $oldStatus,
                ],
                [
                    'decision' => $decision,
                    'status' => $targetStatus,
                    'reason' => $reason,
                ],
                $request
            );

            return $applicant->fresh(['wave', 'track', 'statusHistory.actor']);
        });
    }

    /**
     * BE-PPDB-06: Publish selection results for wave and notify applicants.
     */
    public function publishResults(string $waveId, Request $request): array
    {
        /** @var User $user */
        $user = $request->user();

        $wave = PPDBWave::findOrFail($waveId);

        return DB::transaction(function () use ($wave, $user, $request) {
            // Lock and update publish timestamp
            $wave->result_publish_at = Carbon::now();
            $wave->save();

            // Fetch all applicants with decisions
            $applicants = Applicant::where('wave_id', $wave->id)
                ->whereNotNull('decision')
                ->get();

            $notifiedCount = 0;

            foreach ($applicants as $applicant) {
                if ($applicant->user_id) {
                    $decisionText = match ($applicant->decision) {
                        'diterima' => 'DITERIMA',
                        'cadangan' => 'CADANGAN (WAITLIST)',
                        'ditolak' => 'TIDAK DITERIMA',
                        default => strtoupper($applicant->decision),
                    };

                    Notification::create([
                        'user_id' => $applicant->user_id,
                        'notif_type' => 'ppdb.result_published',
                        'title' => 'Pengumuman Hasil Seleksi PPDB',
                        'body' => "Hasil seleksi pendaftaran nomor {$applicant->registration_no} telah diterbitkan: {$decisionText}.",
                        'data' => [
                            'applicant_id' => $applicant->id,
                            'registration_no' => $applicant->registration_no,
                            'decision' => $applicant->decision,
                            'status' => $applicant->status,
                            'rank' => $applicant->rank,
                        ],
                    ]);

                    $notifiedCount++;
                }
            }

            // Log Audit
            $this->logAudit(
                $user->id,
                'ppdb_selection.published',
                'PPDBWave',
                $wave->id,
                null,
                [
                    'published_at' => $wave->result_publish_at->toIso8601String(),
                    'notified_count' => $notifiedCount,
                ],
                $request
            );

            return [
                'wave_id' => $wave->id,
                'wave_name' => $wave->name,
                'result_publish_at' => $wave->result_publish_at->toIso8601String(),
                'notified_applicants' => $notifiedCount,
                'message' => 'Hasil seleksi PPDB berhasil dipublikasikan dan notifikasi telah dikirimkan kepada calon siswa.',
            ];
        });
    }

    /**
     * BE-PPDB-06 / Decision #3: Promote waitlist candidates after deadline expiration with committee approval.
     */
    public function escalateWaitlist(string $waveId, array $data, Request $request): array
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        $wave = PPDBWave::with('tracks')->findOrFail($waveId);
        $confirmed = (bool) ($data['confirmed'] ?? false);
        $force = (bool) ($data['force'] ?? false);
        $publishAt = $wave->result_publish_at ?? $wave->close_at;
        $deadline = $publishAt ? $publishAt->copy()->addWeekdays(3)->endOfDay() : Carbon::now()->subDay();

        // Guard: tidak boleh mengeksekusi eskalasi cadangan sebelum batas waktu daftar ulang 3 hari kerja berakhir
        if (! $force && Carbon::now()->lte($deadline)) {
            $formattedDeadline = $deadline->translatedFormat('d F Y H:i');
            throw new HttpException(
                422,
                "Batas waktu konfirmasi daftar ulang belum berakhir (tenggat: {$formattedDeadline} WIB). Eskalasi cadangan hanya dapat dijalankan setelah batas waktu berakhir."
            );
        }

        return DB::transaction(function () use ($wave, $confirmed, $deadline, $currentUser, $request) {
            $tracks = $wave->tracks;
            $results = [];

            foreach ($tracks as $track) {
                // Find candidates who were selected, but haven't confirmed/verified re-registration past deadline
                $expiredApplicants = Applicant::where('track_id', $track->id)
                    ->where('wave_id', $wave->id)
                    ->where('status', 'selected')
                    ->whereDoesntHave('reRegistration', function ($q) {
                        $q->whereIn('status', ['dikonfirmasi', 'diverifikasi']);
                    })
                    ->get();

                $vacantSlots = $expiredApplicants->count();

                // Find top waitlist candidates to promote
                $waitlistCandidates = Applicant::where('track_id', $track->id)
                    ->where('wave_id', $wave->id)
                    ->where('status', 'waitlist')
                    ->orderBy('rank', 'asc')
                    ->limit($vacantSlots)
                    ->get();

                if ($confirmed && $vacantSlots > 0) {
                    // Mark expired candidates as rejected/cancelled due to deadline pass
                    foreach ($expiredApplicants as $expired) {
                        $expired->update([
                            'status' => 'rejected',
                            'decision' => 'ditolak',
                        ]);

                        ApplicantStatusHistory::create([
                            'applicant_id' => $expired->id,
                            'from_status' => 'selected',
                            'to_status' => 'rejected',
                            'note' => 'Gugur otomatis karena melewati batas waktu konfirmasi daftar ulang 3 hari kerja.',
                            'actor_id' => $currentUser->id,
                        ]);
                    }

                    // Promote waitlist candidates to selected
                    foreach ($waitlistCandidates as $promoted) {
                        $promoted->update([
                            'status' => 'selected',
                            'decision' => 'diterima',
                        ]);

                        ApplicantStatusHistory::create([
                            'applicant_id' => $promoted->id,
                            'from_status' => 'waitlist',
                            'to_status' => 'selected',
                            'note' => 'Dipromosikan dari cadangan ke diterima menggantikan calon siswa yang gugur (disetujui panitia).',
                            'actor_id' => $currentUser->id,
                        ]);
                    }

                    $this->logAudit(
                        $currentUser->id,
                        'ppdb_waitlist.escalated',
                        'PPDBWave',
                        $wave->id,
                        null,
                        [
                            'track_id' => $track->id,
                            'vacant_slots' => $vacantSlots,
                            'promoted_count' => $waitlistCandidates->count(),
                        ],
                        $request
                    );
                }

                $results[] = [
                    'track_id' => $track->id,
                    'track_name' => $track->name,
                    'vacant_slots' => $vacantSlots,
                    'expired_applicants' => $expiredApplicants->map(fn ($a) => ['id' => $a->id, 'registration_no' => $a->registration_no, 'full_name' => $a->full_name]),
                    'candidates_to_promote' => $waitlistCandidates->map(fn ($a) => ['id' => $a->id, 'registration_no' => $a->registration_no, 'full_name' => $a->full_name, 'rank' => $a->rank]),
                ];
            }

            return [
                'wave_id' => $wave->id,
                'confirmed' => $confirmed,
                'deadline' => $deadline->toIso8601String(),
                'tracks' => $results,
                'message' => $confirmed
                    ? 'Promosi cadangan berhasil disetujui dan diterapkan.'
                    : 'Pratinjau promosi cadangan (butuh persetujuan panitia dengan confirmed: true).',
            ];
        });
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
        Request $request
    ): void {
        try {
            AuditLog::create([
                'actor_id' => $actorId,
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
