<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PPDBRequirement;
use App\Models\PPDBTrack;
use App\Models\PPDBWave;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PPDBConfigurationService
{
    // =========================================================================
    // WAVES (GELOMBANG PPDB)
    // =========================================================================

    /**
     * BE-PPDB-01: List PPDB waves.
     */
    public function listWaves(Request $request, bool $isPublic = false): LengthAwarePaginator|Collection
    {
        $query = PPDBWave::with(['tracks.requirements']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($schoolId = $request->query('school_id')) {
            $query->where('school_id', $schoolId);
        }

        $query->orderBy('open_at', 'desc');

        if ($request->query('all') === 'true' || $request->query('paginate') === 'false') {
            return $query->get();
        }

        $perPage = min((int) $request->query('per_page', 15), 100);

        return $query->paginate($perPage);
    }

    /**
     * BE-PPDB-01: Get currently active wave.
     */
    public function getActiveWave(): ?PPDBWave
    {
        return PPDBWave::with(['tracks.requirements'])
            ->where('status', 'aktif')
            ->where('open_at', '<=', now())
            ->where('close_at', '>=', now())
            ->orderBy('close_at', 'asc')
            ->first();
    }

    /**
     * BE-PPDB-01: Get single wave.
     */
    public function getWave(string $id): PPDBWave
    {
        return PPDBWave::with(['tracks.requirements'])->findOrFail($id);
    }

    /**
     * BE-PPDB-01: Create wave.
     */
    public function createWave(array $data, Request $request): PPDBWave
    {
        return DB::transaction(function () use ($data, $request) {
            $openAt = Carbon::parse($data['open_at']);
            $closeAt = Carbon::parse($data['close_at']);
            $publishAt = Carbon::parse($data['result_publish_at']);

            if ($closeAt->lte($openAt)) {
                throw new HttpException(400, 'Tanggal tutup pendaftaran harus setelah tanggal buka');
            }

            if ($publishAt->lt($closeAt)) {
                throw new HttpException(400, 'Tanggal pengumuman hasil tidak boleh sebelum tanggal tutup pendaftaran');
            }

            $wave = PPDBWave::create([
                'school_id' => $data['school_id'] ?? $request->user()?->school_id,
                'name' => $data['name'],
                'open_at' => $openAt,
                'close_at' => $closeAt,
                'result_publish_at' => $publishAt,
                'status' => $data['status'],
            ]);

            $this->logAudit(
                $request->user()?->id,
                'ppdb_wave.created',
                'PPDBWave',
                $wave->id,
                null,
                $wave->toArray(),
                $request
            );

            return $wave->load('tracks.requirements');
        });
    }

    /**
     * BE-PPDB-01: Update wave.
     */
    public function updateWave(string $id, array $data, Request $request): PPDBWave
    {
        return DB::transaction(function () use ($id, $data, $request) {
            $wave = PPDBWave::findOrFail($id);
            $oldValue = $wave->toArray();

            $openAt = isset($data['open_at']) ? Carbon::parse($data['open_at']) : $wave->open_at;
            $closeAt = isset($data['close_at']) ? Carbon::parse($data['close_at']) : $wave->close_at;
            $publishAt = isset($data['result_publish_at']) ? Carbon::parse($data['result_publish_at']) : $wave->result_publish_at;

            if ($closeAt->lte($openAt)) {
                throw new HttpException(400, 'Tanggal tutup pendaftaran harus setelah tanggal buka');
            }

            if ($publishAt->lt($closeAt)) {
                throw new HttpException(400, 'Tanggal pengumuman hasil tidak boleh sebelum tanggal tutup pendaftaran');
            }

            if (isset($data['name'])) {
                $wave->name = $data['name'];
            }

            if (isset($data['status'])) {
                $wave->status = $data['status'];
            }

            $wave->open_at = $openAt;
            $wave->close_at = $closeAt;
            $wave->result_publish_at = $publishAt;

            if (isset($data['school_id'])) {
                $wave->school_id = $data['school_id'];
            }

            $wave->save();

            $this->logAudit(
                $request->user()?->id,
                'ppdb_wave.updated',
                'PPDBWave',
                $wave->id,
                $oldValue,
                $wave->fresh()->toArray(),
                $request
            );

            return $wave->fresh(['tracks.requirements']);
        });
    }

    /**
     * BE-PPDB-01: Delete wave.
     */
    public function deleteWave(string $id, Request $request): void
    {
        DB::transaction(function () use ($id, $request) {
            $wave = PPDBWave::findOrFail($id);

            if ($wave->applicants()->exists()) {
                throw new HttpException(409, 'Gelombang tidak dapat dihapus karena sudah memiliki pendaftar.');
            }

            $oldValue = $wave->toArray();
            $wave->delete();

            $this->logAudit(
                $request->user()?->id,
                'ppdb_wave.deleted',
                'PPDBWave',
                $id,
                $oldValue,
                null,
                $request
            );
        });
    }

    // =========================================================================
    // TRACKS (JALUR PENDAFTARAN)
    // =========================================================================

    /**
     * BE-PPDB-01: List tracks.
     */
    public function listTracks(Request $request): Collection
    {
        $query = PPDBTrack::with(['requirements', 'wave']);

        if ($waveId = $request->query('wave_id')) {
            $query->where('wave_id', $waveId);
        }

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->orderBy('name', 'asc')->get();
    }

    /**
     * BE-PPDB-01: Get track detail.
     */
    public function getTrack(string $id): PPDBTrack
    {
        return PPDBTrack::with(['requirements', 'wave'])->findOrFail($id);
    }

    /**
     * BE-PPDB-01: Create track.
     */
    public function createTrack(array $data, Request $request): PPDBTrack
    {
        return DB::transaction(function () use ($data, $request) {
            $track = PPDBTrack::create([
                'wave_id' => $data['wave_id'],
                'code' => $data['code'],
                'name' => $data['name'],
                'quota' => $data['quota'],
                'score_formula' => $data['score_formula'] ?? null,
            ]);

            $this->logAudit(
                $request->user()?->id,
                'ppdb_track.created',
                'PPDBTrack',
                $track->id,
                null,
                $track->toArray(),
                $request
            );

            return $track->load(['requirements', 'wave']);
        });
    }

    /**
     * BE-PPDB-01: Update track.
     */
    public function updateTrack(string $id, array $data, Request $request): PPDBTrack
    {
        return DB::transaction(function () use ($id, $data, $request) {
            $track = PPDBTrack::findOrFail($id);
            $oldValue = $track->toArray();

            if (isset($data['code'])) {
                $track->code = $data['code'];
            }

            if (isset($data['name'])) {
                $track->name = $data['name'];
            }

            if (isset($data['quota'])) {
                $track->quota = $data['quota'];
            }

            if (array_key_exists('score_formula', $data)) {
                $track->score_formula = $data['score_formula'];
            }

            $track->save();

            $this->logAudit(
                $request->user()?->id,
                'ppdb_track.updated',
                'PPDBTrack',
                $track->id,
                $oldValue,
                $track->fresh()->toArray(),
                $request
            );

            return $track->fresh(['requirements', 'wave']);
        });
    }

    /**
     * BE-PPDB-01: Delete track.
     */
    public function deleteTrack(string $id, Request $request): void
    {
        DB::transaction(function () use ($id, $request) {
            $track = PPDBTrack::findOrFail($id);

            if ($track->applicants()->exists()) {
                throw new HttpException(409, 'Jalur pendaftaran tidak dapat dihapus karena sudah memiliki pendaftar.');
            }

            $oldValue = $track->toArray();
            $track->delete();

            $this->logAudit(
                $request->user()?->id,
                'ppdb_track.deleted',
                'PPDBTrack',
                $id,
                $oldValue,
                null,
                $request
            );
        });
    }

    // =========================================================================
    // REQUIREMENTS (SYARAT BERKAS)
    // =========================================================================

    /**
     * BE-PPDB-01: List requirements.
     */
    public function listRequirements(Request $request): Collection
    {
        $query = PPDBRequirement::with('track');

        if ($trackId = $request->query('track_id')) {
            $query->where('track_id', $trackId);
        }

        return $query->orderBy('created_at', 'asc')->get();
    }

    /**
     * BE-PPDB-01: Get single requirement.
     */
    public function getRequirement(string $id): PPDBRequirement
    {
        return PPDBRequirement::with('track')->findOrFail($id);
    }

    /**
     * BE-PPDB-01: Create requirement.
     */
    public function createRequirement(array $data, Request $request): PPDBRequirement
    {
        return DB::transaction(function () use ($data, $request) {
            $requirement = PPDBRequirement::create([
                'track_id' => $data['track_id'],
                'document_type' => $data['document_type'],
                'is_required' => $data['is_required'],
                'description' => $data['description'] ?? null,
            ]);

            $this->logAudit(
                $request->user()?->id,
                'ppdb_requirement.created',
                'PPDBRequirement',
                $requirement->id,
                null,
                $requirement->toArray(),
                $request
            );

            return $requirement->load('track');
        });
    }

    /**
     * BE-PPDB-01: Update requirement.
     */
    public function updateRequirement(string $id, array $data, Request $request): PPDBRequirement
    {
        return DB::transaction(function () use ($id, $data, $request) {
            $requirement = PPDBRequirement::findOrFail($id);
            $oldValue = $requirement->toArray();

            if (isset($data['document_type'])) {
                $requirement->document_type = $data['document_type'];
            }

            if (isset($data['is_required'])) {
                $requirement->is_required = $data['is_required'];
            }

            if (array_key_exists('description', $data)) {
                $requirement->description = $data['description'];
            }

            $requirement->save();

            $this->logAudit(
                $request->user()?->id,
                'ppdb_requirement.updated',
                'PPDBRequirement',
                $requirement->id,
                $oldValue,
                $requirement->fresh()->toArray(),
                $request
            );

            return $requirement->fresh(['track']);
        });
    }

    /**
     * BE-PPDB-01: Delete requirement.
     */
    public function deleteRequirement(string $id, Request $request): void
    {
        DB::transaction(function () use ($id, $request) {
            $requirement = PPDBRequirement::findOrFail($id);
            $oldValue = $requirement->toArray();

            $requirement->delete();

            $this->logAudit(
                $request->user()?->id,
                'ppdb_requirement.deleted',
                'PPDBRequirement',
                $id,
                $oldValue,
                null,
                $request
            );
        });
    }

    /**
     * Log action into audit_logs.
     */
    protected function logAudit(
        ?string $actorId,
        string $action,
        string $entity,
        string $entityId,
        ?array $oldValue,
        ?array $newValue,
        Request $request
    ): void {
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
    }
}
