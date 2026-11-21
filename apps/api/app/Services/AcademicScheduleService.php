<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Schedule;
use App\Models\TimeSlot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AcademicScheduleService
{
    // =========================================================================
    // TIME SLOTS
    // =========================================================================

    /**
     * List all time slots ordered by start_time.
     */
    public function listTimeSlots(?string $dayType = null): Collection
    {
        $query = TimeSlot::query()->orderBy('start_time', 'asc');

        if (! empty($dayType)) {
            $query->where('day_type', $dayType);
        }

        return $query->get();
    }

    /**
     * Get detail of a specific time slot.
     */
    public function getTimeSlot(string $id): TimeSlot
    {
        return TimeSlot::findOrFail($id);
    }

    /**
     * Create a new time slot.
     */
    public function createTimeSlot(array $data, Request $request): TimeSlot
    {
        return DB::transaction(function () use ($data, $request) {
            $timeSlot = TimeSlot::create([
                'school_id' => $data['school_id'] ?? null,
                'name' => trim($data['name']),
                'start_time' => trim($data['start_time']),
                'end_time' => trim($data['end_time']),
                'day_type' => $data['day_type'] ?? 'reguler',
            ]);

            $this->logAudit(
                $request->user()?->id,
                'time_slot.created',
                'TimeSlot',
                $timeSlot->id,
                null,
                $timeSlot->toArray(),
                $request
            );

            return $timeSlot;
        });
    }

    /**
     * Update an existing time slot.
     */
    public function updateTimeSlot(string $id, array $data, Request $request): TimeSlot
    {
        $timeSlot = TimeSlot::findOrFail($id);

        return DB::transaction(function () use ($timeSlot, $data, $request) {
            $oldData = $timeSlot->toArray();

            $timeSlot->update($data);

            $this->logAudit(
                $request->user()?->id,
                'time_slot.updated',
                'TimeSlot',
                $timeSlot->id,
                $oldData,
                $timeSlot->toArray(),
                $request
            );

            return $timeSlot;
        });
    }

    /**
     * Delete a time slot if not in use.
     */
    public function deleteTimeSlot(string $id, Request $request): void
    {
        $timeSlot = TimeSlot::findOrFail($id);

        if (Schedule::where('time_slot_id', $id)->exists()) {
            throw new HttpException(422, 'Slot jam pelajaran tidak dapat dihapus karena masih digunakan pada jadwal pelajaran.');
        }

        DB::transaction(function () use ($timeSlot, $request) {
            $oldData = $timeSlot->toArray();
            $timeSlot->delete();

            $this->logAudit(
                $request->user()?->id,
                'time_slot.deleted',
                'TimeSlot',
                $timeSlot->id,
                $oldData,
                null,
                $request
            );
        });
    }

    // =========================================================================
    // SCHEDULE CONFLICT CHECKING
    // =========================================================================

    /**
     * Check if a schedule configuration causes conflicts (class, teacher, or room).
     */
    public function checkConflicts(array $params): array
    {
        $semesterId = $params['semester_id'];
        $classId = $params['class_id'];
        $teacherId = $params['teacher_id'];
        $roomId = $params['room_id'] ?? null;
        $timeSlotId = $params['time_slot_id'];
        $dayOfWeek = (int) $params['day_of_week'];
        $excludeScheduleId = $params['exclude_schedule_id'] ?? null;

        $conflicts = [];

        // 1. Bentrok Kelas (Rombel yang sama tidak boleh memiliki 2 jadwal di jam yang sama)
        $classConflictQuery = Schedule::with(['subject', 'teacher'])
            ->where('semester_id', $semesterId)
            ->where('class_id', $classId)
            ->where('day_of_week', $dayOfWeek)
            ->where('time_slot_id', $timeSlotId);

        if ($excludeScheduleId) {
            $classConflictQuery->where('id', '!=', $excludeScheduleId);
        }

        $classConflict = $classConflictQuery->first();
        if ($classConflict) {
            $subjectName = $classConflict->subject?->name ?? 'Mata pelajaran lain';
            $teacherName = $classConflict->teacher?->full_name ?? 'guru lain';
            $conflicts[] = [
                'type' => 'CLASS_CONFLICT',
                'message' => "Kelas sudah memiliki jadwal pelajaran pada hari dan jam tersebut ({$subjectName} oleh {$teacherName}).",
                'schedule_id' => $classConflict->id,
            ];
        }

        // 2. Bentrok Guru (Guru yang sama tidak boleh mengajar di 2 kelas berbeda di jam yang sama)
        $teacherConflictQuery = Schedule::with(['class', 'subject'])
            ->where('semester_id', $semesterId)
            ->where('teacher_id', $teacherId)
            ->where('day_of_week', $dayOfWeek)
            ->where('time_slot_id', $timeSlotId);

        if ($excludeScheduleId) {
            $teacherConflictQuery->where('id', '!=', $excludeScheduleId);
        }

        $teacherConflict = $teacherConflictQuery->first();
        if ($teacherConflict) {
            $className = $teacherConflict->class?->name ?? 'kelas lain';
            $conflicts[] = [
                'type' => 'TEACHER_CONFLICT',
                'message' => "Guru yang dipilih sudah terjadwal mengajar di kelas {$className} pada hari dan jam tersebut.",
                'schedule_id' => $teacherConflict->id,
            ];
        }

        // 3. Bentrok Ruangan (Jika ruangan dipilih, tidak boleh dipakai kelas lain di jam yang sama)
        if (! empty($roomId)) {
            $roomConflictQuery = Schedule::with(['class', 'subject', 'room'])
                ->where('semester_id', $semesterId)
                ->where('room_id', $roomId)
                ->where('day_of_week', $dayOfWeek)
                ->where('time_slot_id', $timeSlotId);

            if ($excludeScheduleId) {
                $roomConflictQuery->where('id', '!=', $excludeScheduleId);
            }

            $roomConflict = $roomConflictQuery->first();
            if ($roomConflict) {
                $className = $roomConflict->class?->name ?? 'kelas lain';
                $roomName = $roomConflict->room?->name ?? 'Ruangan';
                $conflicts[] = [
                    'type' => 'ROOM_CONFLICT',
                    'message' => "{$roomName} sudah digunakan oleh kelas {$className} pada hari dan jam tersebut.",
                    'schedule_id' => $roomConflict->id,
                ];
            }
        }

        return [
            'has_conflict' => count($conflicts) > 0,
            'conflicts' => $conflicts,
        ];
    }

    // =========================================================================
    // SCHEDULES CRUD
    // =========================================================================

    /**
     * List schedules with filters and relationships.
     */
    public function listSchedules(array $filters, Request $request): LengthAwarePaginator|Collection
    {
        $query = Schedule::with(['semester', 'class', 'subject', 'teacher.user', 'room', 'timeSlot'])
            ->join('time_slots', 'schedules.time_slot_id', '=', 'time_slots.id')
            ->select('schedules.*')
            ->orderBy('schedules.day_of_week', 'asc')
            ->orderBy('time_slots.start_time', 'asc');

        if (! empty($filters['semester_id'])) {
            $query->where('schedules.semester_id', $filters['semester_id']);
        }
        if (! empty($filters['class_id'])) {
            $query->where('schedules.class_id', $filters['class_id']);
        }
        if (! empty($filters['teacher_id'])) {
            $query->where('schedules.teacher_id', $filters['teacher_id']);
        }
        if (! empty($filters['room_id'])) {
            $query->where('schedules.room_id', $filters['room_id']);
        }
        if (! empty($filters['subject_id'])) {
            $query->where('schedules.subject_id', $filters['subject_id']);
        }
        if (! empty($filters['day_of_week'])) {
            $query->where('schedules.day_of_week', (int) $filters['day_of_week']);
        }

        if (! empty($filters['all'])) {
            return $query->get();
        }

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);

        return $query->paginate($perPage);
    }

    /**
     * Get detail of a specific schedule.
     */
    public function getSchedule(string $id): Schedule
    {
        return Schedule::with(['semester', 'class', 'subject', 'teacher.user', 'room', 'timeSlot'])
            ->findOrFail($id);
    }

    /**
     * Create a new schedule with conflict prevention (HTTP 409).
     */
    public function createSchedule(array $data, Request $request): Schedule
    {
        // Pengecekan bentrok jadwal
        $conflictResult = $this->checkConflicts($data);
        if ($conflictResult['has_conflict']) {
            $firstConflict = $conflictResult['conflicts'][0];
            throw new HttpException(409, $firstConflict['message']);
        }

        return DB::transaction(function () use ($data, $request) {
            $schedule = Schedule::create([
                'semester_id' => $data['semester_id'],
                'class_id' => $data['class_id'],
                'subject_id' => $data['subject_id'],
                'teacher_id' => $data['teacher_id'],
                'room_id' => $data['room_id'] ?? null,
                'time_slot_id' => $data['time_slot_id'],
                'day_of_week' => (int) $data['day_of_week'],
            ]);

            $this->logAudit(
                $request->user()?->id,
                'schedule.created',
                'Schedule',
                $schedule->id,
                null,
                $schedule->toArray(),
                $request
            );

            return $schedule->load(['semester', 'class', 'subject', 'teacher.user', 'room', 'timeSlot']);
        });
    }

    /**
     * Update an existing schedule with conflict prevention (HTTP 409).
     */
    public function updateSchedule(string $id, array $data, Request $request): Schedule
    {
        $schedule = Schedule::findOrFail($id);

        $mergedData = [
            'semester_id' => $data['semester_id'] ?? $schedule->semester_id,
            'class_id' => $data['class_id'] ?? $schedule->class_id,
            'subject_id' => $data['subject_id'] ?? $schedule->subject_id,
            'teacher_id' => $data['teacher_id'] ?? $schedule->teacher_id,
            'room_id' => array_key_exists('room_id', $data) ? $data['room_id'] : $schedule->room_id,
            'time_slot_id' => $data['time_slot_id'] ?? $schedule->time_slot_id,
            'day_of_week' => array_key_exists('day_of_week', $data) ? (int) $data['day_of_week'] : $schedule->day_of_week,
            'exclude_schedule_id' => $schedule->id,
        ];

        $conflictResult = $this->checkConflicts($mergedData);
        if ($conflictResult['has_conflict']) {
            $firstConflict = $conflictResult['conflicts'][0];
            throw new HttpException(409, $firstConflict['message']);
        }

        return DB::transaction(function () use ($schedule, $data, $request) {
            $oldData = $schedule->toArray();

            $schedule->update($data);

            $this->logAudit(
                $request->user()?->id,
                'schedule.updated',
                'Schedule',
                $schedule->id,
                $oldData,
                $schedule->toArray(),
                $request
            );

            return $schedule->fresh(['semester', 'class', 'subject', 'teacher.user', 'room', 'timeSlot']);
        });
    }

    /**
     * Delete a schedule if it does not have journal entries.
     */
    public function deleteSchedule(string $id, Request $request): void
    {
        $schedule = Schedule::findOrFail($id);

        if ($schedule->journals()->exists()) {
            throw new HttpException(422, 'Jadwal pelajaran tidak dapat dihapus karena sudah memiliki jurnal mengajar.');
        }

        DB::transaction(function () use ($schedule, $request) {
            $oldData = $schedule->toArray();
            $schedule->delete();

            $this->logAudit(
                $request->user()?->id,
                'schedule.deleted',
                'Schedule',
                $schedule->id,
                $oldData,
                null,
                $request
            );
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
