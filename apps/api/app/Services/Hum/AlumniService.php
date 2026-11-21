<?php

namespace App\Services\Hum;

use App\Models\Alumni;
use App\Models\AuditLog;
use App\Models\Student;
use App\Models\TracerResponse;
use App\Models\TracerSurvey;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AlumniService
{
    // =========================================================================
    // 1. DIREKTORI ALUMNI TERBATAS
    // =========================================================================

    public function listAlumni(array $filters, ?User $currentUser): LengthAwarePaginator
    {
        $query = Alumni::with('student')
            ->orderBy('graduation_year', 'desc');

        if ($currentUser && ! empty($currentUser->school_id)) {
            $query->whereHas('student', function ($sq) use ($currentUser) {
                $sq->where('school_id', $currentUser->school_id)->orWhereNull('school_id');
            });
        }

        if (! empty($filters['graduation_year'])) {
            $query->where('graduation_year', (int) $filters['graduation_year']);
        }

        if (! empty($filters['current_status'])) {
            $query->where('current_status', $filters['current_status']);
        }

        if (! empty($filters['search'])) {
            $kw = '%'.strtolower($filters['search']).'%';
            $query->where(function ($q) use ($kw) {
                $q->whereRaw('LOWER(next_institution) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(study_program) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(occupation) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(company_name) LIKE ?', [$kw])
                    ->orWhereHas('student', function ($sq) use ($kw) {
                        $sq->whereRaw('LOWER(full_name) LIKE ?', [$kw])
                            ->orWhereRaw('LOWER(nis) LIKE ?', [$kw]);
                    });
            });
        }

        $paginator = $query->paginate((int) ($filters['per_page'] ?? 15));

        // Direktori terbatas: bila bukan staf/admin, sensor kontak jika is_public = false
        $isStaff = $this->isStaffOrAdmin($currentUser);
        if (! $isStaff) {
            $paginator->getCollection()->transform(function ($item) use ($currentUser) {
                return $this->maskAlumniDataIfPrivate($item, $currentUser);
            });
        }

        return $paginator;
    }

    public function getAlumni(string $id, ?User $currentUser): Alumni
    {
        $alumni = Alumni::with(['student', 'tracerResponses.survey'])->findOrFail($id);

        if ($currentUser && ! empty($currentUser->school_id) && ! empty($alumni->student?->school_id) && $alumni->student->school_id !== $currentUser->school_id) {
            throw new HttpException(403, 'Akses ditolak: Data alumni berada di luar sekolah penugasan Anda.');
        }

        if (! $this->isStaffOrAdmin($currentUser)) {
            $this->maskAlumniDataIfPrivate($alumni, $currentUser);
        }

        return $alumni;
    }

    public function createAlumni(array $data, Request $request): Alumni
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $alumni = Alumni::create([
            'student_id' => $data['student_id'],
            'graduation_year' => $data['graduation_year'],
            'current_status' => $data['current_status'] ?? 'kuliah',
            'next_institution' => $data['next_institution'] ?? null,
            'study_program' => $data['study_program'] ?? null,
            'occupation' => $data['occupation'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'contact' => $data['contact'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'linkedin_url' => $data['linkedin_url'] ?? null,
            'address' => $data['address'] ?? null,
            'is_public' => $data['is_public'] ?? false,
        ]);

        $this->logAudit($user?->id, 'alumni.create', 'alumni', $alumni->id, null, $alumni->toArray(), $request);

        return $alumni->fresh('student');
    }

    public function updateAlumni(string $id, array $data, Request $request): Alumni
    {
        $user = $request->user();
        $alumni = Alumni::with('student')->findOrFail($id);

        if ($user && ! empty($user->school_id) && ! empty($alumni->student?->school_id) && $alumni->student->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Data alumni berada di luar sekolah penugasan Anda.');
        }

        // Otorisasi: Staff/admin atau alumni pemilik data sendiri
        $isOwner = false;
        if ($user) {
            $student = Student::where('user_id', $user->id)->first();
            if ($student && $student->id === $alumni->student_id) {
                $isOwner = true;
            }
        }

        if (! $isOwner && ! $this->isStaffOrAdmin($user)) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang memperbarui data alumni ini.');
        }

        $oldValues = $alumni->toArray();
        $alumni->update($data);

        $this->logAudit($user?->id, 'alumni.update', 'alumni', $alumni->id, $oldValues, $alumni->fresh()->toArray(), $request);

        return $alumni->fresh('student');
    }

    public function deleteAlumni(string $id, Request $request): bool
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $alumni = Alumni::with('student')->findOrFail($id);

        if ($user && ! empty($user->school_id) && ! empty($alumni->student?->school_id) && $alumni->student->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Data alumni berada di luar sekolah penugasan Anda.');
        }

        $oldValues = $alumni->toArray();

        $alumni->delete();

        $this->logAudit($user?->id, 'alumni.delete', 'alumni', $id, $oldValues, null, $request);

        return true;
    }

    // =========================================================================
    // 2. KUESIONER TRACER STUDY (SURVEYS)
    // =========================================================================

    public function listSurveys(array $filters, ?User $currentUser): LengthAwarePaginator
    {
        $query = TracerSurvey::with('creator')->orderBy('created_at', 'desc');

        if (! empty($filters['target_graduation_year'])) {
            $query->where('target_graduation_year', (int) $filters['target_graduation_year']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        } elseif (! $this->isStaffOrAdmin($currentUser)) {
            // Publik / alumni hanya melihat survei aktif
            $query->where('is_active', true);
        }

        if (! empty($filters['search'])) {
            $kw = '%'.strtolower($filters['search']).'%';
            $query->where(function ($q) use ($kw) {
                $q->whereRaw('LOWER(title) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$kw]);
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function getSurvey(string $id): TracerSurvey
    {
        return TracerSurvey::with(['creator', 'responses.alumni.student'])->findOrFail($id);
    }

    public function createSurvey(array $data, Request $request): TracerSurvey
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $survey = TracerSurvey::create([
            'school_id' => $data['school_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'target_graduation_year' => $data['target_graduation_year'] ?? null,
            'questions' => $data['questions'],
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $user?->id,
        ]);

        $this->logAudit($user?->id, 'tracer_survey.create', 'tracer_surveys', $survey->id, null, $survey->toArray(), $request);

        return $survey->fresh('creator');
    }

    public function updateSurvey(string $id, array $data, Request $request): TracerSurvey
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $survey = TracerSurvey::findOrFail($id);
        $oldValues = $survey->toArray();

        $survey->update($data);

        $this->logAudit($user?->id, 'tracer_survey.update', 'tracer_surveys', $survey->id, $oldValues, $survey->fresh()->toArray(), $request);

        return $survey->fresh('creator');
    }

    public function deleteSurvey(string $id, Request $request): bool
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $survey = TracerSurvey::findOrFail($id);
        $oldValues = $survey->toArray();

        $survey->delete();

        $this->logAudit($user?->id, 'tracer_survey.delete', 'tracer_surveys', $id, $oldValues, null, $request);

        return true;
    }

    // =========================================================================
    // 3. PENGISIAN & STATISTIK TRACER STUDY
    // =========================================================================

    public function submitResponse(string $surveyId, array $data, Request $request): TracerResponse
    {
        $user = $request->user();
        $survey = TracerSurvey::findOrFail($surveyId);

        if (! $survey->is_active) {
            throw new HttpException(422, 'Kuesioner tracer study ini sudah tidak aktif atau ditutup.');
        }

        if ($survey->end_date && Carbon::parse($survey->end_date)->endOfDay()->isPast()) {
            throw new HttpException(422, 'Masa pengisian kuesioner tracer study ini telah berakhir.');
        }

        // Tentukan alumni_id
        $alumniId = $data['alumni_id'] ?? null;
        if (! $alumniId && $user) {
            $student = Student::where('user_id', $user->id)->first();
            $alumni = $student ? Alumni::where('student_id', $student->id)->first() : null;
            $alumniId = $alumni?->id;
        }

        if (! $alumniId) {
            throw new HttpException(422, 'Data alumni tidak ditemukan untuk mengisi survei ini.');
        }

        $alumni = Alumni::findOrFail($alumniId);

        // Validasi target angkatan jika ditentukan
        if ($survey->target_graduation_year !== null && $alumni->graduation_year !== $survey->target_graduation_year) {
            throw new HttpException(422, "Kuesioner ini dikhususkan untuk alumni angkatan {$survey->target_graduation_year}.");
        }

        // Cek duplikasi
        $existing = TracerResponse::where('survey_id', $survey->id)
            ->where('alumni_id', $alumni->id)
            ->first();

        if ($existing) {
            throw new HttpException(422, 'Anda sudah pernah mengisi kuesioner tracer study ini.');
        }

        $response = TracerResponse::create([
            'survey_id' => $survey->id,
            'alumni_id' => $alumni->id,
            'answers' => $data['answers'],
            'submitted_at' => Carbon::now(),
        ]);

        $this->logAudit($user?->id, 'tracer_response.submit', 'tracer_responses', $response->id, null, $response->toArray(), $request);

        return $response->fresh(['survey', 'alumni.student']);
    }

    public function getSurveyStats(string $surveyId, Request $request): array
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $survey = TracerSurvey::with('responses.alumni')->findOrFail($surveyId);
        $totalResponses = $survey->responses->count();

        // Agregasi status alumni responden
        $statusCounts = [];
        foreach ($survey->responses as $resp) {
            $status = $resp->alumni?->current_status ?? 'lainnya';
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        }

        return [
            'survey_id' => $survey->id,
            'title' => $survey->title,
            'total_responses' => $totalResponses,
            'status_breakdown' => $statusCounts,
        ];
    }

    // =========================================================================
    // 4. OTORISASI & AUDIT
    // =========================================================================

    public function isStaffOrAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole('admin') ||
            $user->hasRole('super_admin') ||
            $user->hasRole('admin_tu') ||
            $user->hasRole('principal') ||
            $user->hasRole('humas') ||
            $user->hasRole('public_relations');
    }

    protected function authorizeStaffOrAdmin(?User $user): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        if (! $this->isStaffOrAdmin($user)) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang mengelola data alumni dan kuesioner tracer study.');
        }
    }

    protected function maskAlumniDataIfPrivate(Alumni $alumni, ?User $currentUser): Alumni
    {
        $isOwner = false;
        if ($currentUser) {
            $student = Student::where('user_id', $currentUser->id)->first();
            if ($student && $student->id === $alumni->student_id) {
                $isOwner = true;
            }
        }

        if (! $alumni->is_public && ! $isOwner) {
            $alumni->phone = null;
            $alumni->email = null;
            $alumni->contact = null;
            $alumni->address = null;
        }

        return $alumni;
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
            // Jangan gagalkan transaksi jika audit gagal
        }
    }
}
