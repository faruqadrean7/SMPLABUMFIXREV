<?php

namespace App\Services\Cbt;

use App\Models\AuditLog;
use App\Models\CbtSyncLog;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamParticipant;
use App\Models\ExamSession;
use App\Models\ExamViolation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExamSyncService
{
    /**
     * BE-CBT-11: Ekspor Paket Soal & Peserta untuk Server Lokal Sekolah.
     * POST /api/v1/cbt/sync/pull
     */
    public function pullPackage(array $data, Request $request): array
    {
        $examId = $data['exam_id'];
        $nodeId = $data['node_id'];
        $syncToken = $data['sync_token'] ?? null;
        $roomId = $data['room_id'] ?? null;

        $exam = Exam::with(['subject', 'questions.question.options', 'rooms.room'])->findOrFail($examId);
        $user = $request->user('sanctum') ?? $request->user();
        $this->authorizeSyncNodeOrAdmin($exam, $syncToken, $user);

        $participantsQuery = ExamParticipant::with(['student'])
            ->where('exam_id', $examId);

        if ($roomId) {
            $participantsQuery->where('exam_room_id', $roomId);
        }

        $participants = $participantsQuery->get();

        $questionsPayload = $exam->questions->map(function ($eq) {
            $q = $eq->question;
            if (! $q) {
                return null;
            }

            return [
                'id' => $q->id,
                'sort_order' => $eq->sort_order,
                'weight' => (float) ($eq->weight_override ?? $q->weight ?? 1.0),
                'package_code' => $eq->package_code,
                'question_type' => $q->question_type,
                'body' => $q->body,
                'options' => $q->options->map(function ($opt) {
                    return [
                        'id' => $opt->id,
                        'label' => $opt->label,
                        'body' => $opt->body,
                        'match_key' => $opt->match_key,
                        'is_correct_hash' => hash('sha256', ($opt->is_correct ? 'CORRECT_' : 'INCORRECT_').$opt->id),
                    ];
                })->all(),
            ];
        })->filter()->values()->all();

        $participantsPayload = $participants->map(function ($p) {
            return [
                'participant_id' => $p->id,
                'student_id' => $p->student_id,
                'student_name' => $p->student?->full_name,
                'student_nis' => $p->student?->nis,
                'exam_room_id' => $p->exam_room_id,
                'package_code' => $p->package_code,
                'seed' => Str::random(12),
            ];
        })->all();

        $serializedData = json_encode([
            'exam_id' => $exam->id,
            'questions' => $questionsPayload,
            'participants_count' => count($participantsPayload),
        ]);
        $packageHash = hash('sha256', $serializedData);

        // Catat ke cbt_sync_logs
        CbtSyncLog::create([
            'school_id' => $exam->school_id,
            'exam_id' => $exam->id,
            'node_id' => $nodeId,
            'sync_type' => 'pull',
            'package_hash' => $packageHash,
            'records_count' => count($participantsPayload),
            'status' => 'success',
            'meta' => [
                'room_id' => $roomId,
                'ip' => $request->ip(),
            ],
        ]);

        $this->logAudit(
            $request->user()?->id,
            'cbt.sync_pull',
            'exams',
            $exam->id,
            null,
            ['node_id' => $nodeId, 'package_hash' => $packageHash, 'participants' => count($participantsPayload)],
            $request
        );

        return [
            'status' => 'success',
            'node_id' => $nodeId,
            'package_hash' => $packageHash,
            'exported_at' => Carbon::now()->toIso8601String(),
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'subject_name' => $exam->subject?->name,
                'exam_type' => $exam->exam_type,
                'duration_min' => $exam->duration_min,
                'passing_grade' => (float) $exam->passing_grade,
                'start_at' => $exam->start_at?->toIso8601String(),
                'end_at' => $exam->end_at?->toIso8601String(),
                'shuffle_questions' => $exam->shuffle_questions,
                'shuffle_options' => $exam->shuffle_options,
            ],
            'questions' => $questionsPayload,
            'participants' => $participantsPayload,
        ];
    }

    /**
     * BE-CBT-11: Sinkronisasi Hasil Ujian Offline dari Server Lokal ke Server Pusat.
     * POST /api/v1/cbt/sync/push
     */
    public function pushResults(array $data, Request $request): array
    {
        $examId = $data['exam_id'];
        $nodeId = $data['node_id'];
        $syncToken = $data['sync_token'] ?? null;
        $sessionsData = $data['sessions'] ?? [];

        $exam = Exam::findOrFail($examId);
        $user = $request->user('sanctum') ?? $request->user();
        $this->authorizeSyncNodeOrAdmin($exam, $syncToken, $user);

        $processedSessions = 0;
        $processedAnswers = 0;
        $conflictCount = 0;

        DB::transaction(function () use ($exam, $sessionsData, &$processedSessions, &$processedAnswers, &$conflictCount) {
            foreach ($sessionsData as $item) {
                $studentId = $item['student_id'];
                $session = ExamSession::where('exam_id', $exam->id)
                    ->where('student_id', $studentId)
                    ->first();

                $startedAt = ! empty($item['started_at']) ? Carbon::parse($item['started_at']) : Carbon::now()->subMinutes($exam->duration_min);
                $submittedAt = ! empty($item['submitted_at']) ? Carbon::parse($item['submitted_at']) : Carbon::now();

                if (! $session) {
                    $session = ExamSession::create([
                        'exam_id' => $exam->id,
                        'student_id' => $studentId,
                        'status' => 'selesai',
                        'started_at' => $startedAt,
                        'deadline_at' => $startedAt->copy()->addMinutes($exam->duration_min),
                        'submitted_at' => $submittedAt,
                        'seed' => $item['seed'] ?? Str::random(10),
                    ]);
                } else {
                    // Idempotency: Jika sesi sudah berstatus selesai di server pusat, hindari penimpaan tidak perlu
                    if ($session->status === 'selesai' && $session->score !== null) {
                        $conflictCount++;
                    } else {
                        $session->status = 'selesai';
                        $session->submitted_at = $submittedAt;
                        $session->save();
                    }
                }

                $processedSessions++;

                // Sinkronkan lembar jawaban
                if (! empty($item['answers']) && is_array($item['answers'])) {
                    foreach ($item['answers'] as $ans) {
                        $questionId = $ans['question_id'];
                        $answerVal = $ans['answer'] ?? null;

                        ExamAnswer::updateOrCreate(
                            [
                                'session_id' => $session->id,
                                'question_id' => $questionId,
                            ],
                            [
                                'answer' => $answerVal,
                                'is_flagged' => (bool) ($ans['is_flagged'] ?? false),
                                'answered_at' => ! empty($ans['answered_at']) ? Carbon::parse($ans['answered_at']) : Carbon::now(),
                            ]
                        );

                        $processedAnswers++;
                    }
                }

                // Sinkronkan rekaman pelanggaran jika ada
                if (! empty($item['violations']) && is_array($item['violations'])) {
                    foreach ($item['violations'] as $viol) {
                        ExamViolation::create([
                            'session_id' => $session->id,
                            'violation_type' => $viol['violation_type'] ?? 'offline_event',
                            'meta' => $viol['meta'] ?? null,
                            'occurred_at' => ! empty($viol['occurred_at']) ? Carbon::parse($viol['occurred_at']) : Carbon::now(),
                        ]);
                    }
                }
            }
        });

        $packageHash = hash('sha256', $nodeId.$examId.count($sessionsData).Carbon::now()->timestamp);

        // Catat ke cbt_sync_logs
        CbtSyncLog::create([
            'school_id' => $exam->school_id,
            'exam_id' => $exam->id,
            'node_id' => $nodeId,
            'sync_type' => 'push',
            'package_hash' => $packageHash,
            'records_count' => $processedSessions,
            'status' => 'success',
            'meta' => [
                'processed_answers' => $processedAnswers,
                'conflicts' => $conflictCount,
                'ip' => $request->ip(),
            ],
        ]);

        $this->logAudit(
            $request->user()?->id,
            'cbt.sync_push',
            'exams',
            $exam->id,
            null,
            ['node_id' => $nodeId, 'sessions_count' => $processedSessions, 'answers_count' => $processedAnswers],
            $request
        );

        return [
            'status' => 'success',
            'exam_id' => $exam->id,
            'node_id' => $nodeId,
            'processed_sessions' => $processedSessions,
            'processed_answers' => $processedAnswers,
            'conflicts' => $conflictCount,
            'synced_at' => Carbon::now()->toIso8601String(),
        ];
    }

    protected function authorizeSyncNodeOrAdmin(Exam $exam, ?string $syncToken, ?User $user): void
    {
        // 1. Otorisasi via Node Token
        if (! empty($syncToken)) {
            if ($exam->sync_node_token && hash_equals($exam->sync_node_token, $syncToken)) {
                return;
            }
            if (config('services.cbt.master_sync_token') && hash_equals(config('services.cbt.master_sync_token'), $syncToken)) {
                return;
            }
        }

        // 2. Otorisasi via User Role
        if ($user) {
            if ($user->hasRole(['admin', 'super_admin']) || $user->hasPermission('cbt.exam.manage')) {
                return;
            }
            if ($exam->created_by === $user->id) {
                return;
            }
        }

        throw new HttpException(403, 'Akses ditolak: Token sinkronisasi tidak valid atau tidak memiliki hak akses server lokal.');
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
