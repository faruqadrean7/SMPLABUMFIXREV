<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ClassStudent;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\ExamQuestion;
use App\Models\ExamRoom;
use App\Models\Room;
use App\Models\User;
use App\Services\Concerns\GuardsExamOwnership;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExamService
{
    use GuardsExamOwnership;

    /**
     * BE-CBT-02: Daftar ujian CBT dengan filter.
     */
    public function listExams(array $filters, ?User $user = null): LengthAwarePaginator
    {
        $query = Exam::with(['subject', 'creator', 'parentExam'])
            ->withCount(['questions', 'rooms', 'participants'])
            ->orderBy('created_at', 'desc');

        if ($user && ! $user->hasRole('super_admin') && ! empty($user->school_id)) {
            $query->where(function ($q) use ($user) {
                $q->where('school_id', $user->school_id)->orWhereNull('school_id');
            });
        }

        if (! empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (! empty($filters['exam_type'])) {
            $query->where('exam_type', $filters['exam_type']);
        }

        // P1-11: Terapkan filter status dan exclude_draft_archived secara independen (AND) agar ?status=draft tidak membypass guard
        if (! empty($filters['exclude_draft_archived'])) {
            $query->whereNotIn('status', ['draft', 'arsip', 'dibatalkan']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $keyword = '%'.strtolower($filters['search']).'%';
            $query->whereRaw('LOWER(title) LIKE ?', [$keyword]);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * BE-CBT-02: Buat ujian baru (termasuk remedial).
     */
    public function createExam(array $data, Request $request): Exam
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        $exam = Exam::create([
            'school_id' => $currentUser->school_id,
            'subject_id' => $data['subject_id'],
            'created_by' => $currentUser->id,
            'parent_exam_id' => $data['parent_exam_id'] ?? null,
            'title' => $data['title'],
            'exam_type' => $data['exam_type'],
            'duration_min' => $data['duration_min'],
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'],
            'passing_grade' => $data['passing_grade'] ?? 75.0,
            'shuffle_questions' => $data['shuffle_questions'] ?? true,
            'shuffle_options' => $data['shuffle_options'] ?? true,
            'package_mode' => $data['package_mode'] ?? 'single',
            'show_result_mode' => $data['show_result_mode'] ?? 'after_release',
            'status' => $data['status'] ?? 'draft',
        ]);

        $this->logAudit(
            $currentUser->id,
            'exam.create',
            'exams',
            $exam->id,
            null,
            $data,
            $request
        );

        return $exam->load(['subject', 'creator', 'parentExam']);
    }

    /**
     * BE-CBT-02: Detail ujian lengkap beserta plotting soal, ruang, dan peserta.
     */
    public function getExam(string $id): Exam
    {
        return Exam::with([
            'subject',
            'creator',
            'parentExam',
            'questions.question.options',
            'rooms.room',
            'rooms.proctor',
            'participants.student',
        ])
            ->withCount(['questions', 'rooms', 'participants'])
            ->findOrFail($id);
    }

    public function updateExam(string $id, array $data, Request $request): Exam
    {
        $exam = Exam::findOrFail($id);
        $this->assertCanManage($exam, $request->user());
        $oldData = $exam->toArray();

        if (in_array($exam->status, ['berlangsung', 'selesai']) && isset($data['duration_min']) && $data['duration_min'] !== $exam->duration_min) {
            throw new HttpException(422, 'Tidak dapat mengubah durasi pada ujian yang sedang berlangsung atau sudah selesai.');
        }

        $exam->update($data);

        $this->logAudit(
            $request->user()?->id,
            'exam.update',
            'exams',
            $exam->id,
            $oldData,
            $data,
            $request
        );

        return $exam->fresh(['subject', 'creator', 'parentExam']);
    }

    /**
     * BE-CBT-02: Hapus ujian (hanya jika berstatus draft).
     */
    public function deleteExam(string $id, Request $request): void
    {
        $exam = Exam::findOrFail($id);
        $this->assertCanManage($exam, $request->user());

        if ($exam->status !== 'draft') {
            throw new HttpException(422, "Ujian tidak dapat dihapus karena sudah berstatus {$exam->status}. Silakan arsipkan ujian jika tidak lagi digunakan.");
        }

        $exam->delete();

        $this->logAudit(
            $request->user()?->id,
            'exam.delete',
            'exams',
            $id,
            ['title' => $exam->title, 'status' => $exam->status],
            null,
            $request
        );
    }

    /**
     * BE-CBT-02: Asosiasikan butir soal ke ujian (bobot & paket A/B).
     */
    public function assignQuestions(string $id, array $data, Request $request): array
    {
        $exam = Exam::findOrFail($id);
        $this->assertCanManage($exam, $request->user());

        if (in_array($exam->status, ['berlangsung', 'selesai'])) {
            throw new HttpException(422, 'Tidak dapat mengubah komposisi butir soal pada ujian yang sedang berlangsung atau selesai.');
        }

        $createdQuestions = [];

        DB::transaction(function () use ($exam, $data, &$createdQuestions, $request) {
            foreach ($data['questions'] as $idx => $qItem) {
                $sortOrder = $qItem['sort_order'] ?? ($idx + 1);
                $packageCode = $qItem['package_code'] ?? null;

                $examQuestion = ExamQuestion::updateOrCreate(
                    [
                        'exam_id' => $exam->id,
                        'question_id' => $qItem['question_id'],
                        'package_code' => $packageCode,
                    ],
                    [
                        'sort_order' => $sortOrder,
                        'weight_override' => $qItem['weight_override'] ?? null,
                    ]
                );

                $createdQuestions[] = $examQuestion->load('question');
            }

            $this->logAudit(
                $request->user()?->id,
                'exam.assign_questions',
                'exams',
                $exam->id,
                null,
                ['count' => count($createdQuestions)],
                $request
            );
        });

        return $createdQuestions;
    }

    /**
     * BE-CBT-02: Plotting ruang ujian, sesi, dan pengawas.
     */
    public function assignRooms(string $id, array $data, Request $request): array
    {
        $exam = Exam::findOrFail($id);
        $this->assertCanManage($exam, $request->user());
        $assignedRooms = [];

        DB::transaction(function () use ($exam, $data, &$assignedRooms, $request) {
            foreach ($data['rooms'] as $roomItem) {
                $sessionNo = $roomItem['session_no'] ?? 1;

                // Idempoten: satu (ujian, ruang, sesi) hanya boleh punya SATU baris.
                // Dulu setiap panggilan membuat baris baru, sehingga peserta bisa
                // terdaftar di ruang lama sementara guru mengumumkan token baru —
                // siswa tidak akan pernah bisa memulai ujian.
                $examRoom = ExamRoom::firstOrNew([
                    'exam_id' => $exam->id,
                    'room_id' => $roomItem['room_id'],
                    'session_no' => $sessionNo,
                ]);

                $examRoom->token = $roomItem['token'] ?? ($examRoom->token ?: strtoupper(Str::random(6)));
                $examRoom->proctor_id = $roomItem['proctor_id'] ?? $examRoom->proctor_id;
                $examRoom->token_expires_at = $roomItem['token_expires_at'] ?? ($examRoom->token_expires_at ?: $exam->end_at);
                $examRoom->save();

                $assignedRooms[] = $examRoom->fresh()->load(['room', 'proctor']);
            }

            $this->logAudit(
                $request->user()?->id,
                'exam.assign_rooms',
                'exams',
                $exam->id,
                null,
                ['count' => count($assignedRooms)],
                $request
            );
        });

        return $assignedRooms;
    }

    /**
     * BE-CBT-02: Pendaftaran peserta siswa ke ruang ujian CBT.
     */
    public function enrollParticipants(string $id, array $data, Request $request): array
    {
        $exam = Exam::findOrFail($id);
        $this->assertCanManage($exam, $request->user());

        if (! empty($data['exam_room_id'])) {
            $examRoom = ExamRoom::where('exam_id', $exam->id)->findOrFail($data['exam_room_id']);
        } else {
            $examRoom = ExamRoom::where('exam_id', $exam->id)->orderBy('session_no')->first();
            if (! $examRoom) {
                $defaultRoom = Room::first();
                if (! $defaultRoom) {
                    $defaultRoom = Room::create([
                        'school_id' => $exam->school_id ?? $request->user()?->school_id,
                        'code' => 'LAB-CBT-01',
                        'name' => 'Lab Komputer 1',
                        'room_type' => 'laboratorium',
                        'capacity' => 40,
                    ]);
                }
                $examRoom = ExamRoom::create([
                    'exam_id' => $exam->id,
                    'room_id' => $defaultRoom->id,
                    'proctor_id' => $request->user()?->id,
                    'session_no' => 1,
                    'token' => strtoupper(Str::random(6)),
                    'token_expires_at' => $exam->end_at,
                ]);
            }
        }

        $targetClassIds = [];
        if (! empty($data['class_id'])) {
            $targetClassIds[] = $data['class_id'];
        }
        if (! empty($data['class_ids']) && is_array($data['class_ids'])) {
            $targetClassIds = array_values(array_unique(array_merge($targetClassIds, $data['class_ids'])));
        }

        $studentIds = collect();
        if (! empty($targetClassIds)) {
            $classStudentIds = ClassStudent::whereIn('class_id', $targetClassIds)
                ->whereNull('left_at')
                ->pluck('student_id');
            $studentIds = $studentIds->merge($classStudentIds);
        }

        if (! empty($data['student_ids']) && is_array($data['student_ids'])) {
            $studentIds = $studentIds->merge($data['student_ids']);
        }

        $studentIds = $studentIds->unique()->values();

        if ($studentIds->isEmpty()) {
            throw new HttpException(422, 'Tidak ada siswa yang ditemukan untuk didaftarkan ke ujian.');
        }

        $enrolledList = [];

        DB::transaction(function () use ($exam, $examRoom, $studentIds, $data, &$enrolledList, $request) {
            foreach ($studentIds as $sId) {
                $participant = ExamParticipant::updateOrCreate(
                    [
                        'exam_id' => $exam->id,
                        'student_id' => $sId,
                    ],
                    [
                        'exam_room_id' => $examRoom->id,
                        'package_code' => $data['package_code'] ?? null,
                    ]
                );

                $enrolledList[] = $participant->load('student');
            }

            $this->logAudit(
                $request->user()?->id,
                'exam.enroll_participants',
                'exams',
                $exam->id,
                null,
                ['count' => count($enrolledList), 'exam_room_id' => $examRoom->id],
                $request
            );
        });

        return $enrolledList;
    }

    /**
     * BE-CBT-02: Perbarui status lifecycle ujian CBT.
     */
    public function updateStatus(string $id, string $status, Request $request): Exam
    {
        $exam = Exam::findOrFail($id);
        $this->assertCanManage($exam, $request->user());
        $oldStatus = $exam->status;

        $exam->status = $status;
        $exam->save();

        $this->logAudit(
            $request->user()?->id,
            'exam.status_change',
            'exams',
            $exam->id,
            ['status' => $oldStatus],
            ['status' => $status],
            $request
        );

        return $exam->fresh(['subject', 'creator']);
    }

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
