<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\AuditLog;
use App\Models\File;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PPDBDocumentService
{
    /**
     * BE-PPDB-04: List documents for applicant.
     */
    public function listDocuments(string $applicantId, Request $request): Collection
    {
        /** @var User $user */
        $user = $request->user();
        $applicant = Applicant::findOrFail($applicantId);

        // IDOR check: must be owner or have ppdb.applicant.read
        if ($applicant->user_id !== $user->id && ! $user->hasPermission('ppdb.applicant.read')) {
            throw new HttpException(403, 'Anda tidak memiliki hak akses untuk melihat berkas pendaftaran ini');
        }

        return $applicant->documents()->with(['file', 'verifier'])->get();
    }

    /**
     * BE-PPDB-04: Upload document for applicant.
     * File: PDF/JPG/PNG max 2MB; visibility: privat; status: menunggu.
     * Locked if not draft or revision.
     */
    public function uploadDocument(string $applicantId, array $data, Request $request): ApplicantDocument
    {
        /** @var User $user */
        $user = $request->user();
        $applicant = Applicant::findOrFail($applicantId);

        // IDOR check
        if ($applicant->user_id !== $user->id && ! $user->hasPermission('ppdb.applicant.verify')) {
            throw new HttpException(403, 'Anda tidak memiliki hak akses untuk mengunggah berkas pada pendaftaran ini');
        }

        // Lock check: only draft or revision allowed
        if ($applicant->status !== 'draft' && $applicant->status !== 'revision') {
            throw new HttpException(400, 'Data berkas pendaftaran telah dikunci dan tidak dapat diubah kecuali dalam status revisi');
        }

        $uploadedFile = $request->file('file');
        if (! $uploadedFile) {
            throw new HttpException(400, 'Berkas dokumen wajib diunggah');
        }

        $checksum = hash_file('sha256', $uploadedFile->getRealPath());
        $documentType = trim($data['document_type']);

        return DB::transaction(function () use ($applicant, $uploadedFile, $documentType, $checksum, $user, $request) {
            // Save file to private local storage
            $storagePath = $uploadedFile->store("ppdb/documents/{$applicant->id}", 'local');

            // 1. Create File record (privat visibility)
            $fileRecord = File::create([
                'school_id' => $applicant->wave?->school_id,
                'owner_id' => $applicant->id,
                'owner_type' => 'Applicant',
                'bucket' => 'ppdb-documents',
                'object_key' => $storagePath,
                'mime_type' => $uploadedFile->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $uploadedFile->getSize(),
                'visibility' => 'privat',
                'checksum' => $checksum,
            ]);

            // 2. Check existing document with same document_type (re-upload on revision)
            $existingDoc = ApplicantDocument::where('applicant_id', $applicant->id)
                ->where('document_type', $documentType)
                ->first();

            if ($existingDoc) {
                $oldFile = $existingDoc->file;

                // Update document to new file_id first to release FK constraint
                $existingDoc->update([
                    'file_id' => $fileRecord->id,
                    'status' => 'menunggu',
                    'reject_reason' => null,
                    'verified_by' => null,
                ]);

                // Then safely delete old physical file and file record
                if ($oldFile) {
                    if (Storage::disk('local')->exists($oldFile->object_key)) {
                        Storage::disk('local')->delete($oldFile->object_key);
                    }
                    $oldFile->delete();
                }

                $document = $existingDoc;
            } else {
                $document = ApplicantDocument::create([
                    'applicant_id' => $applicant->id,
                    'file_id' => $fileRecord->id,
                    'document_type' => $documentType,
                    'status' => 'menunggu',
                    'reject_reason' => null,
                    'verified_by' => null,
                ]);
            }

            // 3. Log Audit
            $this->logAudit(
                $user->id,
                'ppdb_document.uploaded',
                'ApplicantDocument',
                $document->id,
                null,
                [
                    'document_type' => $documentType,
                    'file_id' => $fileRecord->id,
                    'size_bytes' => $fileRecord->size_bytes,
                ],
                $request
            );

            return $document->fresh(['file', 'verifier']);
        });
    }

    /**
     * BE-PPDB-04: Delete document for applicant.
     * Allowed only if status is draft or revision.
     */
    public function deleteDocument(string $applicantId, string $documentId, Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $applicant = Applicant::findOrFail($applicantId);

        // IDOR check
        if ($applicant->user_id !== $user->id && ! $user->hasPermission('ppdb.applicant.verify')) {
            throw new HttpException(403, 'Anda tidak memiliki hak akses untuk menghapus berkas pada pendaftaran ini');
        }

        // Lock check
        if ($applicant->status !== 'draft' && $applicant->status !== 'revision') {
            throw new HttpException(400, 'Data berkas pendaftaran telah dikunci dan tidak dapat diubah kecuali dalam status revisi');
        }

        $document = ApplicantDocument::where('applicant_id', $applicant->id)->findOrFail($documentId);

        DB::transaction(function () use ($document, $user, $request) {
            $file = $document->file;
            $oldValue = $document->toArray();

            // Delete document record first to satisfy foreign key constraint on files
            $document->delete();

            if ($file) {
                if (Storage::disk('local')->exists($file->object_key)) {
                    Storage::disk('local')->delete($file->object_key);
                }
                $file->delete();
            }

            $this->logAudit(
                $user->id,
                'ppdb_document.deleted',
                'ApplicantDocument',
                $document->id,
                $oldValue,
                null,
                $request
            );
        });
    }

    /**
     * BE-PPDB-04: Download / view private document file.
     */
    public function downloadDocument(string $applicantId, string $documentId, Request $request): BinaryFileResponse
    {
        /** @var User $user */
        $user = $request->user();
        $applicant = Applicant::findOrFail($applicantId);

        // IDOR check: must be owner or have ppdb.applicant.read
        if ($applicant->user_id !== $user->id && ! $user->hasPermission('ppdb.applicant.read')) {
            throw new HttpException(403, 'Anda tidak memiliki hak akses untuk mengunduh berkas ini');
        }

        $document = ApplicantDocument::where('applicant_id', $applicant->id)
            ->with('file')
            ->findOrFail($documentId);

        $file = $document->file;
        if (! $file || ! Storage::disk('local')->exists($file->object_key)) {
            throw new HttpException(404, 'Berkas fisik tidak ditemukan di server penyimpanan');
        }

        $filePath = Storage::disk('local')->path($file->object_key);

        return response()->file($filePath, [
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => 'inline; filename="'.basename($file->object_key).'"',
        ]);
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
