<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Semester;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AcademicYearService
{
    /**
     * BE-MST-01: List academic years with pagination and counts.
     */
    public function listAcademicYears(Request $request): LengthAwarePaginator
    {
        $limit = min($request->integer('limit', 20), 100);
        $q = trim((string) $request->query('q', ''));

        $query = AcademicYear::withCount(['semesters', 'classes'])
            ->orderBy('start_date', 'desc');

        if ($q !== '') {
            $query->where('name', 'like', "%{$q}%");
        }

        return $query->paginate($limit);
    }

    /**
     * BE-MST-01: Get detail of academic year with semesters.
     */
    public function getAcademicYear(string $id): AcademicYear
    {
        return AcademicYear::with('semesters')->findOrFail($id);
    }

    /**
     * BE-MST-01: Create new academic year.
     */
    public function createAcademicYear(array $data, Request $request): AcademicYear
    {
        return DB::transaction(function () use ($data, $request) {
            $isActive = ! empty($data['is_active']);

            if ($isActive) {
                AcademicYear::where('is_active', true)->update(['is_active' => false]);
            }

            $academicYear = AcademicYear::create([
                'school_id' => $data['school_id'] ?? $request->user()?->school_id,
                'name' => $data['name'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_active' => $isActive,
            ]);

            $this->logAudit(
                $request->user()?->id,
                'academic_year.created',
                'AcademicYear',
                $academicYear->id,
                null,
                $academicYear->toArray(),
                $request
            );

            return $academicYear;
        });
    }

    /**
     * BE-MST-01: Update academic year.
     */
    public function updateAcademicYear(string $id, array $data, Request $request): AcademicYear
    {
        $year = AcademicYear::findOrFail($id);
        $oldValue = $year->toArray();

        $year->fill(array_filter([
            'name' => $data['name'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
        ]));
        $year->save();

        $this->logAudit(
            $request->user()?->id,
            'academic_year.updated',
            'AcademicYear',
            $year->id,
            $oldValue,
            $year->toArray(),
            $request
        );

        return $year;
    }

    /**
     * BE-MST-01: Activate single academic year.
     * Enforces single active academic year without deleting previous data.
     */
    public function activateAcademicYear(string $id, Request $request): AcademicYear
    {
        return DB::transaction(function () use ($id, $request) {
            $year = AcademicYear::findOrFail($id);

            // Deactivate all others
            AcademicYear::where('id', '!=', $id)->where('is_active', true)->update(['is_active' => false]);

            $year->is_active = true;
            $year->save();

            $this->logAudit(
                $request->user()?->id,
                'academic_year.activated',
                'AcademicYear',
                $year->id,
                ['is_active' => false],
                ['is_active' => true],
                $request
            );

            return $year;
        });
    }

    /**
     * BE-MST-01: Delete academic year (Active years and years with classes cannot be deleted).
     */
    public function deleteAcademicYear(string $id, Request $request): void
    {
        $year = AcademicYear::findOrFail($id);

        if ($year->is_active) {
            throw new HttpException(400, 'Tahun ajaran aktif tidak dapat dihapus.', null, [], 400);
        }

        if ($year->classes()->exists()) {
            throw new HttpException(
                409,
                'Tahun ajaran tidak dapat dihapus karena sudah memiliki kelas yang terdaftar.',
                null,
                [],
                409
            );
        }

        $oldValue = $year->toArray();
        $year->delete();

        $this->logAudit(
            $request->user()?->id,
            'academic_year.deleted',
            'AcademicYear',
            $year->id,
            $oldValue,
            null,
            $request
        );
    }

    /**
     * BE-MST-01: List semesters with optional filters.
     */
    public function listSemesters(Request $request): Collection
    {
        $query = Semester::with('academicYear')->orderBy('start_date', 'desc');

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->query('academic_year_id'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        return $query->get();
    }

    /**
     * BE-MST-01: Get detail of single semester.
     */
    public function getSemester(string $id): Semester
    {
        return Semester::with('academicYear')->findOrFail($id);
    }

    /**
     * BE-MST-01: Create semester under academic year.
     */
    public function createSemester(array $data, Request $request): Semester
    {
        return DB::transaction(function () use ($data, $request) {
            // Check unique name per academic year
            if (Semester::where('academic_year_id', $data['academic_year_id'])->where('name', $data['name'])->exists()) {
                throw new HttpException(
                    400,
                    "Semester '{$data['name']}' sudah ada pada tahun ajaran ini.",
                    null,
                    [],
                    400
                );
            }

            $isActive = ! empty($data['is_active']);

            if ($isActive) {
                Semester::where('is_active', true)->update(['is_active' => false]);
            }

            $semester = Semester::create([
                'academic_year_id' => $data['academic_year_id'],
                'name' => $data['name'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_active' => $isActive,
            ]);

            $this->logAudit(
                $request->user()?->id,
                'semester.created',
                'Semester',
                $semester->id,
                null,
                $semester->toArray(),
                $request
            );

            return $semester;
        });
    }

    /**
     * BE-MST-01: Update semester.
     */
    public function updateSemester(string $id, array $data, Request $request): Semester
    {
        $semester = Semester::findOrFail($id);
        $oldValue = $semester->toArray();

        if (isset($data['name']) && $data['name'] !== $semester->name) {
            $exists = Semester::where('academic_year_id', $semester->academic_year_id)
                ->where('name', $data['name'])
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                throw new HttpException(
                    400,
                    "Semester '{$data['name']}' sudah ada pada tahun ajaran ini.",
                    null,
                    [],
                    400
                );
            }
        }

        $semester->fill(array_filter([
            'name' => $data['name'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
        ]));
        $semester->save();

        $this->logAudit(
            $request->user()?->id,
            'semester.updated',
            'Semester',
            $semester->id,
            $oldValue,
            $semester->toArray(),
            $request
        );

        return $semester;
    }

    /**
     * BE-MST-01: Activate single semester and its parent academic year.
     */
    public function activateSemester(string $id, Request $request): Semester
    {
        return DB::transaction(function () use ($id, $request) {
            $semester = Semester::with('academicYear')->findOrFail($id);

            // Deactivate all other semesters in system
            Semester::where('id', '!=', $id)->where('is_active', true)->update(['is_active' => false]);

            $semester->is_active = true;
            $semester->save();

            // Ensure parent academic year is also active
            AcademicYear::where('id', '!=', $semester->academic_year_id)->where('is_active', true)->update(['is_active' => false]);
            $semester->academicYear()->update(['is_active' => true]);

            $this->logAudit(
                $request->user()?->id,
                'semester.activated',
                'Semester',
                $semester->id,
                ['is_active' => false],
                ['is_active' => true],
                $request
            );

            return $semester;
        });
    }

    /**
     * BE-MST-01: Delete semester (active semester cannot be deleted).
     */
    public function deleteSemester(string $id, Request $request): void
    {
        $semester = Semester::findOrFail($id);

        if ($semester->is_active) {
            throw new HttpException(400, 'Semester aktif tidak dapat dihapus.', null, [], 400);
        }

        if ($semester->schedules()->exists() || $semester->teachingAssignments()->exists()) {
            throw new HttpException(
                409,
                'Semester tidak dapat dihapus karena sudah memiliki jadwal atau penugasan mengajar terikat.',
                null,
                [],
                409
            );
        }

        $oldValue = $semester->toArray();
        $semester->delete();

        $this->logAudit(
            $request->user()?->id,
            'semester.deleted',
            'Semester',
            $semester->id,
            $oldValue,
            null,
            $request
        );
    }

    /**
     * BE-MST-01: Get active academic year and active semester in single payload.
     */
    public function getActivePeriod(): array
    {
        $activeYear = AcademicYear::where('is_active', true)->first();
        $activeSemester = Semester::where('is_active', true)->first();

        return [
            'academicYear' => $activeYear ? [
                'id' => $activeYear->id,
                'name' => $activeYear->name,
                'startDate' => $activeYear->start_date?->format('Y-m-d'),
                'endDate' => $activeYear->end_date?->format('Y-m-d'),
                'isActive' => true,
            ] : null,
            'semester' => $activeSemester ? [
                'id' => $activeSemester->id,
                'academicYearId' => $activeSemester->academic_year_id,
                'name' => $activeSemester->name,
                'startDate' => $activeSemester->start_date?->format('Y-m-d'),
                'endDate' => $activeSemester->end_date?->format('Y-m-d'),
                'isActive' => true,
            ] : null,
        ];
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
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
