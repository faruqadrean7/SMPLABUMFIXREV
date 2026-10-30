<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\ApplicantStatusHistory;
use App\Models\AuditLog;
use App\Models\PPDBTrack;
use App\Models\PPDBWave;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PPDBApplicationService
{
    /**
     * BE-PPDB-03: List applications.
     * Admin sees all with filters; applicant sees their own.
     */
    public function listApplications(Request $request): LengthAwarePaginator|Collection
    {
        /** @var User $user */
        $user = $request->user();
        $isAdmin = $user->hasPermission('ppdb.applicant.read');

        $query = Applicant::with(['wave', 'track', 'parents']);

        if (! $isAdmin) {
            // Applicant only sees their own applications
            return $query->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        // Admin filters
        if ($request->filled('wave_id')) {
            $query->where('wave_id', $request->query('wave_id'));
        }

        if ($request->filled('track_id')) {
            $query->where('track_id', $request->query('track_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'ilike', "%{$search}%")
                    ->orWhere('registration_no', 'ilike', "%{$search}%");
            });
        }

        $perPage = max(1, min(100, (int) $request->query('limit', 15)));

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * BE-PPDB-03: Get application detail by ID (with IDOR protection).
     */
    public function getApplication(string $id, Request $request): Applicant
    {
        /** @var User $user */
        $user = $request->user();

        $applicant = Applicant::with([
            'wave',
            'track.requirements',
            'parents',
            'documents.file',
            'documents.verifier',
            'statusHistory.actor',
            'user',
        ])->findOrFail($id);

        // IDOR check: must be owner or have ppdb.applicant.read permission
        if ($applicant->user_id !== $user->id && ! $user->hasPermission('ppdb.applicant.read')) {
            throw new HttpException(403, 'Anda tidak memiliki hak akses untuk melihat data pendaftaran ini');
        }

        return $applicant;
    }

    /**
     * Petakan model Applicant ke kontrak JSON yang diharapkan portal admin.
     * Field biodata (nisn/nik/tempat lahir/gender/alamat) tersimpan di step_data,
     * nama ortu di kolom `name`/`parent_type`, dan dokumen butuh relasi file.
     */
    public function presentDetail(Applicant $a): array
    {
        $step = $a->step_data ?? [];
        $personal = is_array($step['personal'] ?? null) ? $step['personal'] : [];
        $school = is_array($step['school'] ?? null) ? $step['school'] : [];

        return [
            'id' => $a->id,
            'user_id' => $a->user_id,
            'wave_id' => $a->wave_id,
            'wave' => $a->wave,
            'track_id' => $a->track_id,
            'track' => $a->track,
            'registration_no' => $a->registration_no,
            'full_name' => $a->full_name,
            'gender' => $personal['gender'] ?? 'L',
            'birth_place' => $personal['birth_place'] ?? null,
            'birth_date' => $a->birth_date,
            'nisn' => $personal['nisn'] ?? null,
            'nik' => $personal['nik'] ?? null,
            'school_origin' => $school['origin_school'] ?? ($personal['school_origin'] ?? null),
            'phone' => $personal['phone'] ?? ($a->user?->phone ?? ($school['phone'] ?? null)),
            'address' => $personal['address'] ?? null,
            'religion' => $personal['religion'] ?? null,
            'status' => $a->status,
            'score' => $a->score,
            'rank' => $a->rank,
            'decision' => $a->decision,
            'created_at' => $a->created_at,
            'updated_at' => $a->updated_at,
            'step_data' => $a->step_data,
            'parents' => $a->parents->map(fn ($p) => [
                'id' => $p->id,
                'applicant_id' => $p->applicant_id,
                'relationship' => $p->parent_type,
                'full_name' => $p->name,
                'phone' => $p->phone,
                'occupation' => $p->occupation,
                'income' => $p->income_range,
            ])->values(),
            'documents' => $a->documents->map(fn ($d) => [
                'id' => $d->id,
                'applicant_id' => $d->applicant_id,
                'document_type' => $d->document_type,
                'file_id' => $d->file_id,
                'status' => $d->status,
                'reject_reason' => $d->reject_reason,
                'verified_by' => $d->verified_by,
                'file' => $d->file ? [
                    'id' => $d->file->id,
                    'filename' => basename((string) $d->file->object_key),
                    'original_name' => basename((string) $d->file->object_key),
                    'mime_type' => $d->file->mime_type,
                    'size' => $d->file->size_bytes,
                    'path' => $d->file->object_key,
                ] : null,
                'verifier' => $d->verifier ? [
                    'id' => $d->verifier->id,
                    'name' => $d->verifier->username,
                    'email' => $d->verifier->email,
                ] : null,
                'created_at' => $d->created_at,
                'updated_at' => $d->updated_at,
            ])->values(),
            'statusHistory' => $a->statusHistory->map(fn ($h) => [
                'id' => $h->id,
                'applicant_id' => $h->applicant_id,
                'from_status' => $h->from_status,
                'to_status' => $h->to_status,
                'note' => $h->note,
                'actor_id' => $h->actor_id,
                'actor' => $h->actor ? [
                    'id' => $h->actor->id,
                    'name' => $h->actor->username,
                    'email' => $h->actor->email,
                ] : null,
                'created_at' => $h->created_at,
            ])->values(),
        ];
    }

    /**
     * BE-PPDB-03: Create draft application.
     */
    public function createApplication(array $data, Request $request): Applicant
    {
        /** @var User $user */
        $user = $request->user();

        return DB::transaction(function () use ($data, $user, $request) {
            // 1. Resolve Wave
            if (! empty($data['wave_id'])) {
                $wave = PPDBWave::findOrFail($data['wave_id']);
                if ($wave->status !== 'aktif' || Carbon::now()->lt($wave->open_at) || Carbon::now()->gt($wave->close_at)) {
                    throw new HttpException(400, 'Gelombang pendaftaran yang dipilih sedang tidak aktif');
                }
            } else {
                $wave = PPDBWave::where('status', 'aktif')
                    ->where('open_at', '<=', Carbon::now())
                    ->where('close_at', '>=', Carbon::now())
                    ->first();

                if (! $wave) {
                    throw new HttpException(400, 'Tidak ada gelombang PPDB yang aktif saat ini');
                }
            }

            // 2. Validate Track belongs to Wave
            $track = PPDBTrack::where('id', $data['track_id'])
                ->where('wave_id', $wave->id)
                ->first();

            if (! $track) {
                throw new HttpException(400, 'Jalur pendaftaran yang dipilih tidak terdaftar pada gelombang ini');
            }

            // 3. Check 1 NIK = 1 active registration in wave
            $activeApplicants = Applicant::where('wave_id', $wave->id)
                ->whereNotIn('status', ['rejected', 'cancelled'])
                ->get();

            foreach ($activeApplicants as $existing) {
                if ($existing->nik_enc === $data['nik']) {
                    throw new HttpException(400, 'NIK ini sudah terdaftar dalam pendaftaran PPDB yang aktif');
                }
            }

            // Check if user already has active registration in this wave
            $existingUserApp = Applicant::where('wave_id', $wave->id)
                ->where('user_id', $user->id)
                ->whereNotIn('status', ['rejected', 'cancelled'])
                ->first();

            if ($existingUserApp) {
                throw new HttpException(400, 'Anda sudah memiliki formulir pendaftaran aktif pada gelombang ini');
            }

            // 4. Draft Registration Number
            $draftRegNo = 'DFT-'.Carbon::now()->format('Ymd').'-'.strtoupper(Str::random(5));

            // 5. Create Applicant
            $applicant = Applicant::create([
                'wave_id' => $wave->id,
                'track_id' => $track->id,
                'user_id' => $user->id,
                'registration_no' => $draftRegNo,
                'nik_enc' => $data['nik'],
                'full_name' => $data['full_name'],
                'birth_date' => $data['birth_date'],
                'status' => 'draft',
                'step_data' => [
                    'current_step' => 1,
                    'personal' => [
                        'nik' => $data['nik'],
                        'full_name' => $data['full_name'],
                        'birth_date' => $data['birth_date'],
                    ],
                ],
            ]);

            // 6. Record Status History
            ApplicantStatusHistory::create([
                'applicant_id' => $applicant->id,
                'from_status' => 'new',
                'to_status' => 'draft',
                'note' => 'Inisialisasi draf formulir pendaftaran',
                'actor_id' => $user->id,
            ]);

            // 7. Audit Log
            $this->logAudit(
                $user->id,
                'ppdb_application.created',
                'Applicant',
                $applicant->id,
                null,
                [
                    'registration_no' => $draftRegNo,
                    'wave_id' => $wave->id,
                    'track_id' => $track->id,
                ],
                $request
            );

            return $applicant->fresh(['wave', 'track']);
        });
    }

    /**
     * BE-PPDB-03: Update specific form step (Autosave).
     * Rule: After submit, data is locked and only editable if status is 'revision'.
     */
    public function updateStep(string $id, string $step, array $data, Request $request): Applicant
    {
        /** @var User $user */
        $user = $request->user();

        return DB::transaction(function () use ($id, $step, $data, $user, $request) {
            $applicant = Applicant::findOrFail($id);

            // IDOR Protection
            if ($applicant->user_id !== $user->id && ! $user->hasPermission('ppdb.applicant.verify')) {
                throw new HttpException(403, 'Anda tidak berhak mengubah data pendaftaran ini');
            }

            // Lock Rule: only editable if draft or revision
            if ($applicant->status !== 'draft' && $applicant->status !== 'revision') {
                throw new HttpException(400, 'Data pendaftaran telah dikunci dan tidak dapat diubah kecuali dalam status revisi');
            }

            $oldValue = $applicant->toArray();
            $stepData = $applicant->step_data ?? [];

            // Step dispatching
            switch ($step) {
                case '1':
                case 'personal':
                    if (isset($data['full_name'])) {
                        $applicant->full_name = $data['full_name'];
                    }
                    if (isset($data['birth_date'])) {
                        $applicant->birth_date = $data['birth_date'];
                    }
                    $stepData['personal'] = array_merge($stepData['personal'] ?? [], $data);
                    break;

                case '2':
                case 'parent':
                    $stepData['parent'] = $data;
                    if (isset($data['parents']) && is_array($data['parents'])) {
                        $applicant->parents()->delete();
                        foreach ($data['parents'] as $parentInfo) {
                            $applicant->parents()->create([
                                'parent_type' => $parentInfo['parent_type'],
                                'name' => $parentInfo['name'],
                                'occupation' => $parentInfo['occupation'] ?? null,
                                'income_range' => $parentInfo['income_range'] ?? null,
                                'phone' => $parentInfo['phone'] ?? null,
                            ]);
                        }
                    }
                    break;

                case '3':
                case 'school':
                    $stepData['school'] = $data;
                    break;

                case '4':
                case 'track':
                    $stepData['track'] = $data;
                    if (isset($data['track_id']) && $data['track_id'] !== $applicant->track_id) {
                        $track = PPDBTrack::where('id', $data['track_id'])
                            ->where('wave_id', $applicant->wave_id)
                            ->firstOrFail();
                        $applicant->track_id = $track->id;
                    }
                    break;

                default:
                    $stepData[$step] = $data;
                    break;
            }

            $stepData['last_updated_step'] = $step;
            $applicant->step_data = $stepData;
            $applicant->save();

            $this->logAudit(
                $user->id,
                'ppdb_application.step_updated',
                'Applicant',
                $applicant->id,
                $oldValue,
                $applicant->fresh()->toArray(),
                $request
            );

            return $applicant->fresh(['wave', 'track', 'parents']);
        });
    }

    /**
     * BE-PPDB-03: Submit application.
     * Generates official registration number, locks data, updates status to 'submitted'.
     */
    public function submitApplication(string $id, Request $request): Applicant
    {
        /** @var User $user */
        $user = $request->user();

        return DB::transaction(function () use ($id, $user, $request) {
            $applicant = Applicant::lockForUpdate()->findOrFail($id);

            // IDOR Protection
            if ($applicant->user_id !== $user->id && ! $user->hasPermission('ppdb.applicant.verify')) {
                throw new HttpException(403, 'Anda tidak berhak mengirimkan pendaftaran ini');
            }

            // Only draft or revision can be submitted
            if ($applicant->status !== 'draft' && $applicant->status !== 'revision') {
                throw new HttpException(400, 'Pendaftaran sudah dikirimkan atau sedang diproses');
            }

            // P0: Validasi status gelombang aktif dan rentang jadwal pendaftaran (open_at s.d. close_at)
            $wave = $applicant->wave;
            if (! $wave) {
                throw new HttpException(400, 'Gelombang pendaftaran tidak valid atau tidak ditemukan');
            }

            $now = Carbon::now();
            if ($wave->status !== 'aktif' || $now->lt($wave->open_at) || $now->gt($wave->close_at)) {
                throw new HttpException(400, 'Gelombang pendaftaran telah ditutup atau sedang tidak aktif');
            }

            $stepData = $applicant->step_data ?? [];

            // Validation: require personal data and parent data
            if (empty($applicant->full_name) || empty($applicant->birth_date)) {
                throw new HttpException(400, 'Data diri calon siswa belum lengkap');
            }

            if (! $applicant->parents()->exists() && empty($stepData['parent'])) {
                throw new HttpException(400, 'Data orang tua / wali wajib diisi sebelum mengirimkan pendaftaran');
            }

            $oldStatus = $applicant->status;

            // Generate unique official registration number if currently draft
            if (str_starts_with($applicant->registration_no, 'DFT-')) {
                $year = Carbon::now()->format('Y');
                $prefix = "PPDB-{$year}-";

                $existingNumbers = Applicant::where('registration_no', 'like', "{$prefix}%")
                    ->pluck('registration_no');

                $maxSeq = 0;
                foreach ($existingNumbers as $regNum) {
                    $numStr = substr($regNum, strlen($prefix));
                    if (is_numeric($numStr)) {
                        $num = (int) $numStr;
                        if ($num > $maxSeq) {
                            $maxSeq = $num;
                        }
                    }
                }

                $seq = $maxSeq + 1;
                do {
                    $candidate = sprintf('%s%05d', $prefix, $seq);
                    $exists = Applicant::where('registration_no', $candidate)->exists();
                    if (! $exists) {
                        $applicant->registration_no = $candidate;
                        break;
                    }
                    $seq++;
                } while ($exists);
            }

            $applicant->status = 'submitted';
            $applicant->save();

            // Record status history
            ApplicantStatusHistory::create([
                'applicant_id' => $applicant->id,
                'from_status' => $oldStatus,
                'to_status' => 'submitted',
                'note' => 'Formulir pendaftaran berhasil disubmit secara resmi',
                'actor_id' => $user->id,
            ]);

            // Audit Log
            $this->logAudit(
                $user->id,
                'ppdb_application.submitted',
                'Applicant',
                $applicant->id,
                ['status' => $oldStatus],
                [
                    'status' => 'submitted',
                    'registration_no' => $applicant->registration_no,
                ],
                $request
            );

            return $applicant->fresh(['wave', 'track', 'parents', 'statusHistory']);
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
