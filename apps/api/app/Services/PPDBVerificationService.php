<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\ApplicantStatusHistory;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PPDBVerificationService
{
    /**
     * BE-PPDB-05: Verify individual document (valid or ditolak).
     */
    public function verifyDocument(string $documentId, array $data, Request $request): ApplicantDocument
    {
        /** @var User $user */
        $user = $request->user();

        $document = ApplicantDocument::with(['applicant.track', 'file'])->findOrFail($documentId);

        if ($document->applicant->status === 'draft') {
            throw new HttpException(400, 'Berkas pendaftaran yang masih berupa draf belum dapat diverifikasi oleh panitia');
        }

        return DB::transaction(function () use ($document, $data, $user, $request) {
            $oldValue = $document->toArray();
            $status = $data['status'];
            $rejectReason = ($status === 'ditolak') ? ($data['reject_reason'] ?? null) : null;

            $document->update([
                'status' => $status,
                'reject_reason' => $rejectReason,
                'verified_by' => $user->id,
            ]);

            $this->logAudit(
                $user->id,
                'ppdb_document.verified',
                'ApplicantDocument',
                $document->id,
                $oldValue,
                $document->fresh()->toArray(),
                $request
            );

            return $document->fresh(['file', 'verifier', 'applicant']);
        });
    }

    /**
     * BE-PPDB-05: Verify overall application (verified or revision).
     */
    public function verifyApplication(string $applicantId, array $data, Request $request): Applicant
    {
        /** @var User $user */
        $user = $request->user();

        $applicant = Applicant::with(['track.requirements', 'documents', 'user'])->findOrFail($applicantId);

        if ($applicant->status === 'draft') {
            throw new HttpException(400, 'Pendaftaran berstatus draf belum dikirimkan oleh calon siswa');
        }

        $targetStatus = $data['status'];

        if ($targetStatus === 'verified') {
            // 1. Check if any document is rejected
            $hasRejected = $applicant->documents->contains('status', 'ditolak');
            if ($hasRejected) {
                throw new HttpException(400, 'Tidak dapat memverifikasi pendaftaran karena masih terdapat berkas yang ditolak');
            }

            // 2. Check if all required track documents are uploaded
            $requiredTypes = $applicant->track->requirements
                ->where('is_required', true)
                ->pluck('document_type')
                ->all();

            $uploadedTypes = $applicant->documents
                ->whereIn('status', ['valid', 'menunggu'])
                ->pluck('document_type')
                ->all();

            $missingRequired = array_diff($requiredTypes, $uploadedTypes);
            if (! empty($missingRequired)) {
                $missingStr = implode(', ', $missingRequired);
                throw new HttpException(400, "Berkas persyaratan wajib belum lengkap: {$missingStr}");
            }
        }

        return DB::transaction(function () use ($applicant, $targetStatus, $data, $user, $request) {
            $oldStatus = $applicant->status;

            // If verified, mark any remaining 'menunggu' documents as 'valid'
            if ($targetStatus === 'verified') {
                foreach ($applicant->documents as $doc) {
                    if ($doc->status === 'menunggu') {
                        $doc->update([
                            'status' => 'valid',
                            'verified_by' => $user->id,
                        ]);
                    }
                }
            }

            $applicant->status = $targetStatus;
            $applicant->save();

            // Record status history
            $defaultNote = ($targetStatus === 'verified')
                ? 'Berkas pendaftaran telah diverifikasi dan dinyatakan valid.'
                : 'Pendaftaran memerlukan perbaikan berkas.';

            $note = ! empty($data['note']) ? $data['note'] : $defaultNote;

            ApplicantStatusHistory::create([
                'applicant_id' => $applicant->id,
                'from_status' => $oldStatus,
                'to_status' => $targetStatus,
                'note' => $note,
                'actor_id' => $user->id,
            ]);

            // Notify applicant
            if ($applicant->user_id) {
                if ($targetStatus === 'verified') {
                    Notification::create([
                        'user_id' => $applicant->user_id,
                        'notif_type' => 'ppdb.application_verified',
                        'title' => 'Pendaftaran PPDB Diverifikasi',
                        'body' => "Selamat, berkas formulir pendaftaran nomor {$applicant->registration_no} telah diverifikasi oleh panitia PPDB.",
                        'data' => [
                            'applicant_id' => $applicant->id,
                            'registration_no' => $applicant->registration_no,
                            'status' => 'verified',
                        ],
                    ]);
                } else {
                    $rejectedDocs = $applicant->documents
                        ->where('status', 'ditolak')
                        ->pluck('reject_reason', 'document_type')
                        ->all();

                    Notification::create([
                        'user_id' => $applicant->user_id,
                        'notif_type' => 'ppdb.revision_required',
                        'title' => 'Revisi Berkas Pendaftaran PPDB',
                        'body' => "Pendaftaran nomor {$applicant->registration_no} memerlukan perbaikan berkas. Catatan panitia: {$note}",
                        'data' => [
                            'applicant_id' => $applicant->id,
                            'registration_no' => $applicant->registration_no,
                            'status' => 'revision',
                            'rejected_documents' => $rejectedDocs,
                        ],
                    ]);
                }
            }

            // Log Audit
            $this->logAudit(
                $user->id,
                'ppdb_application.verified',
                'Applicant',
                $applicant->id,
                ['status' => $oldStatus],
                ['status' => $targetStatus, 'note' => $note],
                $request
            );

            return $applicant->fresh(['track', 'documents.file', 'documents.verifier', 'statusHistory.actor']);
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
