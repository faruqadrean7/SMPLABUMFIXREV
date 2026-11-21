<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\SchoolEvent;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EventService
{
    /**
     * BE-CMS-04: List events with filters for calendar, category, search, and visibility.
     */
    public function listEvents(Request $request, bool $isPublic = false): LengthAwarePaginator|Collection
    {
        $query = SchoolEvent::query();

        if ($isPublic) {
            $query->publicView();
        } elseif ($visibility = $request->query('visibility')) {
            $query->where('visibility', $visibility);
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($search = $request->query('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        if ($schoolId = $request->query('school_id')) {
            $query->where('school_id', $schoolId);
        } elseif ($user = $request->user()) {
            if (! empty($user->school_id)) {
                $query->where(function ($q) use ($user) {
                    $q->where('school_id', $user->school_id)->orWhereNull('school_id');
                });
            }
        }

        // Filter by Month and Year (Calendar View)
        if ($month = $request->query('month')) {
            $year = $request->query('year', (int) date('Y'));
            $startOfMonth = Carbon::createFromDate((int) $year, (int) $month, 1)->startOfMonth();
            $endOfMonth = Carbon::createFromDate((int) $year, (int) $month, 1)->endOfMonth();

            $query->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('start_at', [$startOfMonth, $endOfMonth])
                    ->orWhereBetween('end_at', [$startOfMonth, $endOfMonth])
                    ->orWhere(function ($sub) use ($startOfMonth, $endOfMonth) {
                        $sub->where('start_at', '<=', $startOfMonth)
                            ->where('end_at', '>=', $endOfMonth);
                    });
            });
        } elseif ($startDate = $request->query('start_date')) {
            $endDate = $request->query('end_date');
            if ($endDate) {
                $startCarbon = Carbon::parse($startDate)->startOfDay();
                $endCarbon = Carbon::parse($endDate)->endOfDay();
                $query->where(function ($q) use ($startCarbon, $endCarbon) {
                    $q->whereBetween('start_at', [$startCarbon, $endCarbon])
                        ->orWhereBetween('end_at', [$startCarbon, $endCarbon])
                        ->orWhere(function ($sub) use ($startCarbon, $endCarbon) {
                            $sub->where('start_at', '<=', $startCarbon)
                                ->where('end_at', '>=', $endCarbon);
                        });
                });
            } else {
                $query->where('end_at', '>=', Carbon::parse($startDate)->startOfDay());
            }
        } elseif ($endDate = $request->query('end_date')) {
            $query->where('start_at', '<=', Carbon::parse($endDate)->endOfDay());
        }

        $query->orderBy('start_at', 'asc');

        if ($request->query('all') === 'true' || $request->query('paginate') === 'false') {
            return $query->get();
        }

        $perPage = min((int) $request->query('per_page', 15), 100);

        return $query->paginate($perPage);
    }

    /**
     * BE-CMS-04: Get single event.
     */
    public function getEvent(string $id, bool $isPublic = false, ?Request $request = null): SchoolEvent
    {
        $query = SchoolEvent::query();

        if ($isPublic) {
            $query->publicView();
        }

        $event = $query->findOrFail($id);

        $user = $request?->user();
        if ($user && ! empty($user->school_id) && ! empty($event->school_id) && $event->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Agenda kegiatan berada di luar sekolah penugasan Anda.');
        }

        return $event;
    }

    /**
     * BE-CMS-04: Create event.
     */
    public function createEvent(array $data, Request $request): SchoolEvent
    {
        return DB::transaction(function () use ($data, $request) {
            $startAt = Carbon::parse($data['start_at']);
            $endAt = Carbon::parse($data['end_at']);

            if ($endAt->lt($startAt)) {
                throw new HttpException(422, 'Waktu selesai tidak boleh lebih awal dari waktu mulai');
            }

            $event = SchoolEvent::create([
                'school_id' => $data['school_id'] ?? $request->user()?->school_id,
                'title' => $data['title'],
                'category' => $data['category'],
                'visibility' => $data['visibility'],
                'start_at' => $startAt,
                'end_at' => $endAt,
            ]);

            $this->logAudit(
                $request->user()?->id,
                'event.created',
                'SchoolEvent',
                $event->id,
                null,
                $event->toArray(),
                $request
            );

            return $event;
        });
    }

    /**
     * BE-CMS-04: Update event.
     */
    public function updateEvent(string $id, array $data, Request $request): SchoolEvent
    {
        return DB::transaction(function () use ($id, $data, $request) {
            $event = SchoolEvent::findOrFail($id);
            $user = $request->user();
            if ($user && ! empty($user->school_id) && ! empty($event->school_id) && $event->school_id !== $user->school_id) {
                throw new HttpException(403, 'Akses ditolak: Agenda kegiatan berada di luar sekolah penugasan Anda.');
            }
            $oldValue = $event->toArray();

            $startAt = isset($data['start_at']) ? Carbon::parse($data['start_at']) : $event->start_at;
            $endAt = isset($data['end_at']) ? Carbon::parse($data['end_at']) : $event->end_at;

            if ($endAt->lt($startAt)) {
                throw new HttpException(422, 'Waktu selesai tidak boleh lebih awal dari waktu mulai');
            }

            if (isset($data['title'])) {
                $event->title = $data['title'];
            }

            if (isset($data['category'])) {
                $event->category = $data['category'];
            }

            if (isset($data['visibility'])) {
                $event->visibility = $data['visibility'];
            }

            if (isset($data['start_at'])) {
                $event->start_at = $startAt;
            }

            if (isset($data['end_at'])) {
                $event->end_at = $endAt;
            }

            if (isset($data['school_id'])) {
                $event->school_id = $data['school_id'];
            }

            $event->save();

            $this->logAudit(
                $request->user()?->id,
                'event.updated',
                'SchoolEvent',
                $event->id,
                $oldValue,
                $event->fresh()->toArray(),
                $request
            );

            return $event->fresh();
        });
    }

    /**
     * BE-CMS-04: Delete event.
     */
    public function deleteEvent(string $id, Request $request): void
    {
        DB::transaction(function () use ($id, $request) {
            $event = SchoolEvent::findOrFail($id);
            $oldValue = $event->toArray();

            $event->delete();

            $this->logAudit(
                $request->user()?->id,
                'event.deleted',
                'SchoolEvent',
                $id,
                $oldValue,
                null,
                $request
            );
        });
    }

    /**
     * Log action to audit_logs.
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
