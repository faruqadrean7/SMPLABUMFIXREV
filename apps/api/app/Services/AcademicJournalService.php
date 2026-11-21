<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Journal;
use App\Models\Schedule;
use App\Models\SiteSetting;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AcademicJournalService
{
    /**
     * BE-ACD-02: List journals with filtering and scoping.
     */
    public function listJournals(array $filters, Request $request): LengthAwarePaginator
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $teacher = Teacher::where('user_id', $currentUser->id)->first();
        $isAdmin = $currentUser->hasRole('super_admin')
            || $currentUser->hasPermission('academic.schedule.manage')
            || $currentUser->hasPermission('academic.journal.read');

        $query = Journal::with([
            'schedule.class',
            'schedule.subject',
            'schedule.timeSlot',
            'schedule.room',
            'teacher.user',
        ])->withCount('attendances');

        // Scoping: Jika guru biasa tanpa permission global, batasi hanya jurnal miliknya
        if (! $isAdmin && $teacher) {
            $query->where('teacher_id', $teacher->id);
        }

        if (! empty($filters['schedule_id'])) {
            $query->where('schedule_id', $filters['schedule_id']);
        }

        if (! empty($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }

        if (! empty($filters['class_id'])) {
            $classId = $filters['class_id'];
            $query->whereHas('schedule', fn ($q) => $q->where('class_id', $classId));
        }

        if (! empty($filters['subject_id'])) {
            $subjectId = $filters['subject_id'];
            $query->whereHas('schedule', fn ($q) => $q->where('subject_id', $subjectId));
        }

        if (! empty($filters['start_date'])) {
            $query->whereDate('session_date', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('session_date', '<=', $filters['end_date']);
        }

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);

        return $query->orderBy('session_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * BE-ACD-02: Get journal detail with schedule and attendance records.
     */
    public function getJournal(string $id, Request $request): Journal
    {
        return Journal::with([
            'schedule.class',
            'schedule.subject',
            'schedule.timeSlot',
            'schedule.room',
            'teacher.user',
            'attendances.student.user',
        ])->findOrFail($id);
    }

    /**
     * BE-ACD-02: Create journal for a schedule (only assigned teacher or admin).
     */
    public function createJournal(array $data, Request $request): Journal
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $teacher = Teacher::where('user_id', $currentUser->id)->first();
        $isAdmin = $currentUser->hasRole('super_admin') || $currentUser->hasPermission('academic.schedule.manage');

        $schedule = Schedule::with(['class'])->findOrFail($data['schedule_id']);

        // 1. Otorisasi: Hanya guru penugas atau admin
        if (! $isAdmin && (! $teacher || $teacher->id !== $schedule->teacher_id)) {
            throw new HttpException(403, 'Hanya guru penugas jadwal ini yang berhak mengisi jurnal mengajar.');
        }

        // 2. Cegah duplikasi sesi untuk jadwal dan tanggal yang sama
        $duplicateExists = Journal::where('schedule_id', $schedule->id)
            ->whereDate('session_date', $data['session_date'])
            ->exists();

        if ($duplicateExists) {
            throw new HttpException(422, 'Jurnal mengajar untuk jadwal ini pada tanggal tersebut sudah dibuat.');
        }

        $teacherId = $teacher ? $teacher->id : $schedule->teacher_id;

        return DB::transaction(function () use ($data, $schedule, $teacherId, $request) {
            $journal = Journal::create([
                'schedule_id' => $schedule->id,
                'teacher_id' => $teacherId,
                'session_date' => $data['session_date'],
                'topic' => trim($data['topic']),
                'method' => trim($data['method']),
                'notes' => isset($data['notes']) ? trim($data['notes']) : null,
            ]);

            $this->logAudit(
                $request->user()?->id,
                'journal.created',
                'Journal',
                $journal->id,
                null,
                $journal->toArray(),
                $request
            );

            return $journal->load([
                'schedule.class',
                'schedule.subject',
                'schedule.timeSlot',
                'schedule.room',
                'teacher.user',
            ]);
        });
    }

    /**
     * BE-ACD-02: Update journal (only within N days, unless admin override).
     */
    public function updateJournal(string $id, array $data, Request $request): Journal
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $teacher = Teacher::where('user_id', $currentUser->id)->first();
        $isAdmin = $currentUser->hasRole('super_admin') || $currentUser->hasPermission('academic.schedule.manage');

        $journal = Journal::with(['schedule.class'])->findOrFail($id);

        // 1. Otorisasi kepemilikan
        if (! $isAdmin && (! $teacher || $teacher->id !== $journal->teacher_id)) {
            throw new HttpException(403, 'Hanya guru pembuat/penugas jurnal ini yang dapat memperbarui jurnal.');
        }

        // 2. Batas waktu edit N hari (konfigurasi)
        if (! $isAdmin) {
            $maxDays = $this->getMaxEditDays($journal->schedule?->class?->school_id);
            $sessionDate = Carbon::parse($journal->session_date)->startOfDay();
            $today = Carbon::now()->startOfDay();
            $daysDiff = $sessionDate->diffInDays($today, false);

            if ($daysDiff > $maxDays) {
                throw new HttpException(422, "Batas waktu pengubahan jurnal mengajar telah berakhir (maksimal {$maxDays} hari setelah sesi).");
            }
        }

        // 3. Cek duplikasi jika tanggal sesi diubah
        if (! empty($data['session_date']) && $data['session_date'] !== $journal->session_date->format('Y-m-d')) {
            $duplicate = Journal::where('schedule_id', $journal->schedule_id)
                ->whereDate('session_date', $data['session_date'])
                ->where('id', '!=', $journal->id)
                ->exists();

            if ($duplicate) {
                throw new HttpException(422, 'Jurnal mengajar untuk jadwal ini pada tanggal tersebut sudah ada.');
            }
        }

        return DB::transaction(function () use ($journal, $data, $request) {
            $oldData = $journal->toArray();

            $journal->update($data);

            $this->logAudit(
                $request->user()?->id,
                'journal.updated',
                'Journal',
                $journal->id,
                $oldData,
                $journal->toArray(),
                $request
            );

            return $journal->fresh([
                'schedule.class',
                'schedule.subject',
                'schedule.timeSlot',
                'schedule.room',
                'teacher.user',
            ]);
        });
    }

    /**
     * BE-ACD-02: Delete journal (only within N days, unless admin override).
     */
    public function deleteJournal(string $id, Request $request): void
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $teacher = Teacher::where('user_id', $currentUser->id)->first();
        $isAdmin = $currentUser->hasRole('super_admin') || $currentUser->hasPermission('academic.schedule.manage');

        $journal = Journal::with(['schedule.class'])->findOrFail($id);

        if (! $isAdmin && (! $teacher || $teacher->id !== $journal->teacher_id)) {
            throw new HttpException(403, 'Hanya guru pembuat/penugas jurnal ini yang dapat menghapus jurnal.');
        }

        if (! $isAdmin) {
            $maxDays = $this->getMaxEditDays($journal->schedule?->class?->school_id);
            $sessionDate = Carbon::parse($journal->session_date)->startOfDay();
            $today = Carbon::now()->startOfDay();
            $daysDiff = $sessionDate->diffInDays($today, false);

            if ($daysDiff > $maxDays) {
                throw new HttpException(422, "Batas waktu penghapusan jurnal mengajar telah berakhir (maksimal {$maxDays} hari setelah sesi).");
            }
        }

        DB::transaction(function () use ($journal, $request) {
            $oldData = $journal->toArray();
            $journal->delete();

            $this->logAudit(
                $request->user()?->id,
                'journal.deleted',
                'Journal',
                $journal->id,
                $oldData,
                null,
                $request
            );
        });
    }

    /**
     * Mendapatkan batas hari pengeditan jurnal dari SiteSetting (default 3 hari).
     */
    public function getMaxEditDays(?string $schoolId = null): int
    {
        $settingQuery = SiteSetting::where('setting_key', 'journal_edit_max_days');

        if ($schoolId) {
            $settingQuery->where('school_id', $schoolId);
        }

        $setting = $settingQuery->first();

        if ($setting && is_array($setting->setting_value) && isset($setting->setting_value['days'])) {
            return max(1, (int) $setting->setting_value['days']);
        }

        return 3; // Default standar sekolah: 3 hari kalender
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
