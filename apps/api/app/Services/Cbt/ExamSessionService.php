<?php

namespace App\Services\Cbt;

use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamParticipant;
use App\Models\ExamProctorAction;
use App\Models\ExamQuestion;
use App\Models\ExamRoom;
use App\Models\ExamSession;
use App\Models\ExamViolation;
use App\Models\Question;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExamSessionService
{
    /**
     * BE-CBT-04: Mulai atau lanjutkan sesi ujian siswa dengan verifikasi token ruang ujian.
     */
    public function startOrResumeSession(string $examId, array $data, Request $request): array
    {
        $user = $request->user();
        $student = $this->resolveStudentForUser($user);

        $exam = Exam::with('rooms')->findOrFail($examId);

        if (in_array($exam->status, ['arsip', 'selesai'])) {
            throw new HttpException(422, 'Ujian ini sudah ditutup atau diarsipkan.');
        }

        // Cari data pendaftaran peserta ujian
        $participant = ExamParticipant::where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->first();

        if (! $participant) {
            throw new HttpException(403, 'Anda tidak terdaftar sebagai peserta pada ujian ini.');
        }

        // Verifikasi token ruang ujian
        $tokenInput = trim(strtoupper($data['token'] ?? ''));
        $examRoom = null;

        if ($participant->exam_room_id) {
            $examRoom = ExamRoom::where('id', $participant->exam_room_id)->first();
        }

        if (! $examRoom) {
            $examRoom = ExamRoom::where('exam_id', $exam->id)
                ->where('token', $tokenInput)
                ->first();
        }

        if (! $examRoom || strtoupper($examRoom->token) !== $tokenInput) {
            throw new HttpException(422, 'Token ruang ujian tidak valid atau tidak cocok.');
        }

        if ($examRoom->token_expires_at && Carbon::now()->greaterThan($examRoom->token_expires_at)) {
            throw new HttpException(422, 'Token ruang ujian telah kedaluwarsa. Silakan minta pengawas untuk me-regenerate token.');
        }

        // Pastikan ruang peserta terhubung ke exam_room_id ini
        if ($participant->exam_room_id !== $examRoom->id) {
            $participant->update(['exam_room_id' => $examRoom->id]);
        }

        $now = Carbon::now();
        $durationMinutes = (int) ($exam->duration_min ?? 90);
        $calculatedEndsAt = $now->copy()->addMinutes($durationMinutes);
        if ($exam->end_at) {
            $examEndAt = Carbon::parse($exam->end_at);
            if ($calculatedEndsAt->greaterThan($examEndAt)) {
                $calculatedEndsAt = $examEndAt;
            }
        }

        // Cari atau buat sesi ujian siswa secara aman terhadap konkurensi
        try {
            $session = ExamSession::firstOrCreate(
                [
                    'exam_id' => $exam->id,
                    'student_id' => $student->id,
                ],
                [
                    'status' => 'mengerjakan',
                    'started_at' => $now,
                    'ends_at' => $calculatedEndsAt,
                    'deadline_at' => $calculatedEndsAt,
                    'seed' => Str::random(16),
                    'extra_minutes' => 0,
                    'violation_count' => 0,
                ]
            );
        } catch (UniqueConstraintViolationException) {
            $session = ExamSession::where('exam_id', $exam->id)
                ->where('student_id', $student->id)
                ->firstOrFail();
        }

        // Jika sesi sudah pernah dibuat tapi statusnya 'belum'
        if ($session->status === 'belum') {
            $session->status = 'mengerjakan';
            $session->started_at = $now;
            $endsAtWithExtra = $calculatedEndsAt->copy()->addMinutes($session->extra_minutes);
            $session->ends_at = $endsAtWithExtra;
            $session->deadline_at = $endsAtWithExtra;
            $session->save();
        } elseif ($session->status === 'selesai') {
            throw new HttpException(422, 'Ujian telah Anda selesaikan sebelumnya dan tidak dapat diakses kembali.');
        } elseif ($session->status === 'terkunci') {
            throw new HttpException(403, 'Sesi ujian Anda sedang terkunci: '.($session->locked_reason ?? 'Pelanggaran anti-cheat atau aturan ujian').'. Hubungi pengawas ruang.');
        }

        if (! $session->ends_at && $session->deadline_at) {
            $session->ends_at = $session->deadline_at;
            $session->save();
        }

        $effectiveEndsAt = $session->ends_at ?? $session->deadline_at;
        $graceSeconds = (int) config('cbt.grace_period_seconds', 5);

        // Auto-lock jika waktu server telah melewati deadline
        if ($effectiveEndsAt && $now->greaterThan($effectiveEndsAt->copy()->addSeconds($graceSeconds))) {
            $session->status = 'selesai';
            $session->submitted_at = $effectiveEndsAt;
            $session->save();
            throw new HttpException(422, 'Waktu ujian telah habis. Sesi ujian otomatis diselesaikan.', null, [
                'X-Error-Code' => 'EXAM_TIME_OVER',
            ]);
        }

        $remainingSeconds = max(0, $now->diffInSeconds($effectiveEndsAt, false));

        // Ambil jawaban yang sudah tersimpan untuk memulihkan state klien pada resume
        $savedAnswers = ExamAnswer::where('session_id', $session->id)
            ->get()
            ->keyBy('question_id')
            ->map(function ($ans) {
                return [
                    'question_id' => $ans->question_id,
                    'answer' => $ans->answer,
                    'is_flagged' => (bool) $ans->is_flagged,
                    'answered_at' => $ans->answered_at?->toIso8601String(),
                ];
            });

        $this->logAudit(
            $user->id,
            'exam.start_session',
            'exam_sessions',
            $session->id,
            null,
            ['status' => $session->status, 'started_at' => $session->started_at?->toIso8601String()],
            $request
        );

        return [
            'session_id' => $session->id,
            'exam_id' => $exam->id,
            'exam_title' => $exam->title,
            'student_id' => $student->id,
            'student_name' => $student->full_name,
            'status' => $session->status,
            'started_at' => $session->started_at?->toIso8601String(),
            'deadline_at' => $effectiveEndsAt?->toIso8601String(),
            'ends_at' => $effectiveEndsAt?->toIso8601String(),
            'extra_minutes' => $session->extra_minutes,
            'remaining_seconds' => $remainingSeconds,
            'violation_count' => $session->violation_count ?? 0,
            'server_time' => $now->toIso8601String(),
            'answers' => $savedAnswers,
            'room_name' => $examRoom->room?->name,
            'session_no' => $examRoom->session_no,
        ];
    }

    /**
     * BE-CBT-04: Ambil daftar butir soal untuk sesi ujian siswa.
     * ATURAN KEAMANAN: Kunci jawaban dan bobot nilai sensitif DIBERSIHKAN!
     */
    public function getSessionQuestions(string $examId, Request $request): array
    {
        $user = $request->user();
        $student = $this->resolveStudentForUser($user);

        $session = ExamSession::where('exam_id', $examId)
            ->where('student_id', $student->id)
            ->firstOrFail();

        $this->ensureSessionActive($session);

        $exam = Exam::findOrFail($examId);
        $participant = ExamParticipant::where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->first();

        $packageCode = $participant?->package_code;

        // Ambil butir soal ujian
        $examQuestionsQuery = ExamQuestion::with(['question.options'])
            ->where('exam_id', $exam->id);

        if ($packageCode) {
            $examQuestionsQuery->where(function ($q) use ($packageCode) {
                $q->where('package_code', $packageCode)
                    ->orWhereNull('package_code');
            });
        }

        $examQuestions = $examQuestionsQuery->orderBy('sort_order', 'asc')->get();

        // Ambil jawaban yang telah tersimpan untuk sesi ini
        $savedAnswers = ExamAnswer::where('session_id', $session->id)
            ->get()
            ->keyBy('question_id');

        // Acak urutan butir soal berdasarkan seed sesi jika exam->shuffle_questions true
        $questionItems = $examQuestions->map(function ($eq) {
            return $eq->question;
        })->filter()->values();

        if ($exam->shuffle_questions) {
            $questionItems = $this->shuffleWithSeed($questionItems, $session->seed.'_q');
        }

        $now = Carbon::now();
        $effectiveEndsAt = $session->ends_at ?? $session->deadline_at;
        $remainingSeconds = max(0, $now->diffInSeconds($effectiveEndsAt, false));

        // Format butir soal yang aman (STRIP jawaban yang benar)
        $formattedQuestions = [];
        $index = 1;

        foreach ($questionItems as $q) {
            /** @var Question $q */
            $options = $q->options;

            if ($exam->shuffle_options && in_array($q->question_type, ['pg', 'pg_kompleks', 'bs'])) {
                $options = $this->shuffleWithSeed($options, $session->seed.'_opt_'.$q->id);
            }

            // Sanitasi opsi secara ketat (WHITELIST): Hanya field id, label, text, dan body ter-whitelist
            $sanitizedOptions = [];
            if (! in_array($q->question_type, ['isian', 'uraian'])) {
                $sanitizedOptions = $options->map(function ($opt) {
                    $optText = is_array($opt->body)
                        ? ($opt->body['text'] ?? $opt->body['content'] ?? '')
                        : (string) $opt->body;

                    $optBody = [];
                    if (is_array($opt->body)) {
                        if (isset($opt->body['text'])) {
                            $optBody['text'] = (string) $opt->body['text'];
                        }
                        if (isset($opt->body['content'])) {
                            $optBody['content'] = (string) $opt->body['content'];
                        }
                        if (isset($opt->body['media_url'])) {
                            $optBody['media_url'] = (string) $opt->body['media_url'];
                        }
                        if (isset($opt->body['image_url'])) {
                            $optBody['image_url'] = (string) $opt->body['image_url'];
                        }
                    } else {
                        $optBody = ['text' => (string) $opt->body];
                    }

                    return [
                        'id' => (string) $opt->id,
                        'label' => (string) $opt->label,
                        'text' => (string) $optText,
                        'body' => $optBody,
                    ];
                })->values()->all();
            }

            // Sanitasi body secara ketat (WHITELIST): Hanya field yang eksplisit dibutuhkan siswa
            $sanitizedBody = [];
            if (is_array($q->body)) {
                if (isset($q->body['content'])) {
                    $sanitizedBody['content'] = (string) $q->body['content'];
                }
                if (isset($q->body['text'])) {
                    $sanitizedBody['text'] = (string) $q->body['text'];
                }
                if (isset($q->body['formula'])) {
                    $sanitizedBody['formula'] = $q->body['formula'];
                }
                if (isset($q->body['media_url'])) {
                    $sanitizedBody['media_url'] = (string) $q->body['media_url'];
                }
                if (isset($q->body['media'])) {
                    $sanitizedBody['media'] = $q->body['media'];
                }
                if (isset($q->body['image_url'])) {
                    $sanitizedBody['image_url'] = (string) $q->body['image_url'];
                }
                if (isset($q->body['audio_url'])) {
                    $sanitizedBody['audio_url'] = (string) $q->body['audio_url'];
                }
            } else {
                $sanitizedBody = [
                    'content' => (string) $q->body,
                    'text' => (string) $q->body,
                ];
            }

            // Untuk soal menjodohkan: sediakan target pilihan unik teracak untuk kolom pasangan (tanpa mengaitkan kunci ke premis)
            if ($q->question_type === 'menjodohkan') {
                $matchTargets = $options->pluck('match_key')->filter()->unique()->values();
                if ($matchTargets->isNotEmpty()) {
                    $shuffledTargets = $this->shuffleWithSeed($matchTargets, $session->seed.'_targets_'.$q->id)->all();
                    $sanitizedBody['targets'] = $shuffledTargets;
                } elseif (isset($q->body['targets']) && is_array($q->body['targets'])) {
                    $sanitizedBody['targets'] = array_values($q->body['targets']);
                }
            }

            $existingAnswer = $savedAnswers->get($q->id);
            $eq = $examQuestions->firstWhere('question_id', $q->id);
            $weight = (float) ($eq?->weight_override ?? $q->weight ?? 1.0);

            $formattedQuestions[] = [
                'number' => $index++,
                'id' => $q->id,
                'question_id' => $q->id,
                'question_type' => $q->question_type,
                'weight' => $weight,
                'body' => $sanitizedBody,
                'stem' => $sanitizedBody['content'] ?? $sanitizedBody['text'] ?? '',
                'options' => $sanitizedOptions,
                'user_answer' => $existingAnswer?->answer,
                'current_answer' => $existingAnswer?->answer,
                'is_flagged' => (bool) ($existingAnswer?->is_flagged ?? false),
                'answered_at' => $existingAnswer?->answered_at?->toIso8601String(),
            ];
        }

        return [
            'session_id' => $session->id,
            'exam_id' => $exam->id,
            'title' => $exam->title,
            'status' => $session->status,
            'remaining_seconds' => $remainingSeconds,
            'deadline_at' => $effectiveEndsAt?->toIso8601String(),
            'ends_at' => $effectiveEndsAt?->toIso8601String(),
            'violation_count' => $session->violation_count ?? 0,
            'server_time' => $now->toIso8601String(),
            'total_questions' => count($formattedQuestions),
            'questions' => $formattedQuestions,
        ];
    }

    /**
     * BE-CBT-04: Autosave jawaban siswa per butir soal.
     */
    public function saveAnswer(string $examId, array $data, Request $request): array
    {
        $user = $request->user();
        $student = $this->resolveStudentForUser($user);

        $session = ExamSession::where('exam_id', $examId)
            ->where('student_id', $student->id)
            ->firstOrFail();

        $this->ensureSessionActive($session);

        $questionId = $data['question_id'];

        // SEC-DATA-01: Validasi kepemilikan butir soal terhadap ujian
        $isQuestionInExam = ExamQuestion::where('exam_id', $examId)
            ->where('question_id', $questionId)
            ->exists();

        if (! $isQuestionInExam) {
            throw new HttpException(422, 'Butir soal tidak terdaftar pada paket ujian ini.');
        }

        $answerData = $data['answer'] ?? null;
        $isFlagged = (bool) ($data['is_flagged'] ?? false);

        $now = Carbon::now();
        $incomingAnsweredAt = ! empty($data['answered_at'])
            ? Carbon::parse($data['answered_at'])
            : $now;

        // Batasi answered_at agar tidak melebihi jam server
        if ($incomingAnsweredAt->greaterThan($now)) {
            $incomingAnsweredAt = $now;
        }

        // Batasi answered_at agar tidak mendahului waktu mulai sesi ujian
        if ($session->started_at && $incomingAnsweredAt->lessThan($session->started_at)) {
            $incomingAnsweredAt = $session->started_at->copy();
        }

        // Cek apakah jawaban sudah tersimpan sebelumnya
        $existing = ExamAnswer::where('session_id', $session->id)
            ->where('question_id', $questionId)
            ->first();

        if ($existing) {
            // Rule 1: Idempotent match (nilai jawaban dan flag ragu-ragu persis sama)
            $existingAnswer = is_string($existing->answer) ? json_decode($existing->answer, true) : $existing->answer;
            if ($existingAnswer == $answerData && (bool) $existing->is_flagged === $isFlagged) {
                return [
                    'question_id' => $questionId,
                    'is_saved' => true,
                    'is_flagged' => (bool) $existing->is_flagged,
                    'is_stale' => false,
                    'answered_at' => $existing->answered_at?->toIso8601String() ?? $now->toIso8601String(),
                    'server_time' => $now->toIso8601String(),
                ];
            }

            // Rule 2: Out-of-order retry protection (timestamp incoming lebih lama dari yang telah tersimpan)
            if ($existing->answered_at && $incomingAnsweredAt->lessThan($existing->answered_at)) {
                return [
                    'question_id' => $questionId,
                    'is_saved' => true,
                    'is_stale' => true,
                    'answered_at' => $existing->answered_at->toIso8601String(),
                    'server_time' => $now->toIso8601String(),
                ];
            }
        }

        // SEC-CBT-CONC: Atomic UPSERT berbasis DB (ON CONFLICT DO UPDATE)
        // Mencegah abort transaksi PostgreSQL dan race condition pada autosave konkuren
        ExamAnswer::upsert(
            [
                [
                    'id' => $existing ? $existing->id : (string) Str::uuid(),
                    'session_id' => $session->id,
                    'question_id' => $questionId,
                    'answer' => $answerData !== null ? json_encode($answerData) : null,
                    'is_flagged' => $isFlagged,
                    'answered_at' => $incomingAnsweredAt,
                    'created_at' => $existing ? $existing->created_at : $now,
                    'updated_at' => $now,
                ],
            ],
            ['session_id', 'question_id'],
            ['answer', 'is_flagged', 'answered_at', 'updated_at']
        );

        return [
            'question_id' => $questionId,
            'is_saved' => true,
            'is_flagged' => $isFlagged,
            'answered_at' => $incomingAnsweredAt->toIso8601String(),
            'server_time' => $now->toIso8601String(),
        ];
    }

    /**
     * BE-CBT-04: Catat kejadian pelanggaran anti-cheat (blur, tab switch, minimize).
     */
    public function logViolation(string $examId, array $data, Request $request): array
    {
        $user = $request->user();
        $student = $this->resolveStudentForUser($user);

        return DB::transaction(function () use ($examId, $student, $data) {
            $session = ExamSession::where('exam_id', $examId)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($session->status === 'selesai') {
                return [
                    'status' => 'already_finished',
                    'session_status' => 'selesai',
                    'violation_count' => $session->violation_count ?? 0,
                    'total_violations' => $session->violation_count ?? 0,
                    'is_locked' => false,
                    'consequence' => 'none',
                    'server_time' => Carbon::now()->toIso8601String(),
                ];
            }

            $now = Carbon::now();
            $session->increment('violation_count');
            $session->refresh();

            $violation = ExamViolation::create([
                'session_id' => $session->id,
                'violation_type' => $data['violation_type'],
                'meta' => $data['meta'] ?? ($data['details'] ?? []),
                'occurred_at' => $now,
            ]);

            $totalViolations = $session->violation_count;
            $isLocked = false;
            $consequence = 'warning';

            $lockThreshold = (int) config('cbt.lock_threshold', 3);
            $disqualifyThreshold = (int) config('cbt.disqualify_threshold', 5);

            if ($totalViolations >= $disqualifyThreshold && in_array($session->status, ['mengerjakan', 'terkunci'])) {
                $session->status = 'selesai';
                $session->submitted_at = $now;
                $session->locked_reason = "Diskualifikasi otomatis: Pelanggaran integritas ujian berulang ({$totalViolations} kali)";
                $session->save();
                $consequence = 'disqualified';
            } elseif ($totalViolations >= $lockThreshold && $session->status === 'mengerjakan') {
                $session->status = 'terkunci';
                $session->locked_reason = "Terdeteksi perpindahan layar/aplikasi melebihi toleransi ({$totalViolations} kali)";
                $session->save();
                $isLocked = true;
                $consequence = 'locked';
            }

            return [
                'violation_id' => $violation->id,
                'total_violations' => $totalViolations,
                'violation_count' => $totalViolations,
                'is_locked' => $isLocked,
                'consequence' => $consequence,
                'session_status' => $session->status,
                'locked_reason' => $session->locked_reason,
                'server_time' => $now->toIso8601String(),
            ];
        });
    }

    /**
     * BE-CBT-04: Submit akhir / selesaikan ujian oleh siswa.
     */
    public function finishSession(string $examId, Request $request): array
    {
        $user = $request->user();
        $student = $this->resolveStudentForUser($user);

        return DB::transaction(function () use ($examId, $student, $user, $request) {
            $session = ExamSession::where('exam_id', $examId)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($session->status === 'selesai') {
                return [
                    'session_id' => $session->id,
                    'status' => 'selesai',
                    'message' => 'Ujian sudah diselesaikan sebelumnya.',
                    'submitted_at' => $session->submitted_at?->toIso8601String(),
                ];
            }

            if ($session->status === 'terkunci') {
                throw new HttpException(403, 'Sesi sedang terkunci: '.$session->locked_reason.'. Hubungi pengawas untuk membuka sesi.');
            }

            $now = Carbon::now();
            $session->status = 'selesai';
            $session->submitted_at = $now;
            $session->save();

            try {
                app(ExamGradingService::class)->gradeSessionObjectiveAnswers($session);
            } catch (\Throwable $gradeEx) {
                // Non-blocking
            }

            $this->logAudit(
                $user->id,
                'exam.finish_session',
                'exam_sessions',
                $session->id,
                ['status' => 'mengerjakan'],
                ['status' => 'selesai', 'submitted_at' => $now->toIso8601String()],
                $request
            );

            $totalAnswers = ExamAnswer::where('session_id', $session->id)
                ->whereNotNull('answer')
                ->count();

            return [
                'session_id' => $session->id,
                'status' => 'selesai',
                'submitted_at' => $now->toIso8601String(),
                'answered_count' => $totalAnswers,
                'message' => 'Ujian berhasil diselesaikan.',
            ];
        });
    }

    /**
     * BE-CBT-04: Pengawas atau admin membuka kunci sesi dan/atau menambah waktu ujian.
     */
    public function unlockOrManageSession(string $sessionId, array $data, Request $request): array
    {
        $session = ExamSession::with(['exam', 'student'])->findOrFail($sessionId);
        $currentUser = $request->user();

        $this->authorizeProctorOrAdminForExam($session->exam_id, $currentUser);

        // SEC-AUTHZ-01: Isolasi ruang untuk aksi proctor
        $isGlobalAdmin = $currentUser && ($currentUser->hasRole(['admin', 'super_admin']) || $currentUser->hasPermission('cbt.exam.manage'));
        if (! $isGlobalAdmin) {
            $assignedRoomIds = ExamRoom::where('exam_id', $session->exam_id)
                ->where('proctor_id', $currentUser->id)
                ->pluck('id')
                ->all();

            $isParticipantInRoom = ExamParticipant::where('exam_id', $session->exam_id)
                ->where('student_id', $session->student_id)
                ->whereIn('exam_room_id', $assignedRoomIds)
                ->exists();

            if (! $isParticipantInRoom) {
                throw new HttpException(403, 'Anda tidak berwenang mengelola peserta di luar ruangan penugasan Anda.');
            }
        }

        $action = $data['action'];
        $reason = $data['reason'] ?? 'Tindakan pengawas ruang';
        $extraMinutes = (int) ($data['extra_minutes'] ?? 0);

        if ($session->exam?->status === 'arsip') {
            throw new HttpException(422, 'Ujian telah diarsipkan, data sesi tidak dapat diubah.');
        }

        if ($action === 'extra_time') {
            if ($session->status === 'selesai') {
                throw new HttpException(422, 'Tidak dapat menambahkan waktu pada sesi ujian yang telah selesai. Buka kembali sesi terlebih dahulu bila diperlukan.');
            }
            if ($extraMinutes <= 0) {
                throw new HttpException(422, 'Jumlah tambahan waktu harus lebih dari 0 menit.');
            }
        }

        $oldStatus = $session->status;
        $oldDeadline = $session->deadline_at?->toIso8601String();

        DB::transaction(function () use ($session, $action, $reason, $extraMinutes, $currentUser, $request, $oldStatus, $oldDeadline) {
            if ($action === 'unlock') {
                $session->status = 'mengerjakan';
                $session->locked_reason = null;

                // Jika deadline telah lewat, tambah waktu otomatis minimal sisa atau extra_minutes
                if ($extraMinutes > 0) {
                    $session->extra_minutes += $extraMinutes;
                    $session->deadline_at = ($session->deadline_at && $session->deadline_at->isFuture()
                        ? $session->deadline_at
                        : Carbon::now())->addMinutes($extraMinutes);
                } elseif ($session->deadline_at && $session->deadline_at->isPast()) {
                    // Beri waktu darurat 10 menit jika deadline sudah lewat saat di-unlock
                    $session->extra_minutes += 10;
                    $session->deadline_at = Carbon::now()->addMinutes(10);
                }
                $session->ends_at = $session->deadline_at;
                $session->save();
            } elseif ($action === 'extra_time') {
                $session->extra_minutes += $extraMinutes;
                $session->deadline_at = ($session->deadline_at && $session->deadline_at->isFuture()
                    ? $session->deadline_at
                    : Carbon::now())->addMinutes($extraMinutes);
                $session->ends_at = $session->deadline_at;
                $session->save();
            } elseif ($action === 'reset') {
                $session->status = 'mengerjakan';
                $session->started_at = Carbon::now();
                $session->deadline_at = Carbon::now()->addMinutes((int) ($session->exam?->duration_min ?? 90));
                $session->ends_at = $session->deadline_at;
                $session->submitted_at = null;
                $session->locked_reason = null;
                $session->violation_count = 0;
                $session->save();
            } elseif ($action === 'force_submit') {
                if ($session->status !== 'selesai') {
                    $session->status = 'selesai';
                    $session->submitted_at = Carbon::now();
                    $session->save();
                }
            }

            ExamProctorAction::create([
                'session_id' => $session->id,
                'actor_id' => $currentUser->id,
                'action' => $action,
                'reason' => $reason,
            ]);

            $this->logAudit(
                $currentUser->id,
                'exam.proctor_action.'.$action,
                'exam_sessions',
                $session->id,
                ['status' => $oldStatus, 'deadline_at' => $oldDeadline],
                [
                    'status' => $session->status,
                    'action' => $action,
                    'reason' => $reason,
                    'extra_minutes' => $extraMinutes,
                    'deadline_at' => $session->deadline_at?->toIso8601String(),
                ],
                $request
            );
        });

        $session->refresh();

        return [
            'session_id' => $session->id,
            'student_name' => $session->student?->full_name,
            'status' => $session->status,
            'action_performed' => $action,
            'extra_minutes' => $session->extra_minutes,
            'deadline_at' => $session->deadline_at?->toIso8601String(),
            'ends_at' => $session->ends_at?->toIso8601String(),
        ];
    }

    /**
     * Cari profil siswa berdasarkan user yang login.
     */
    protected function resolveStudentForUser(?User $user): Student
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        $student = Student::where('user_id', $user->id)->first();

        // Fallback jika login via NIS siswa tanpa user_id relasi langsung, HANYA jika akun memiliki role student
        if (! $student && $user->hasRole('student')) {
            $student = Student::where('nis', $user->username)->first();
        }

        if (! $student) {
            throw new HttpException(403, 'Akun pengguna ini tidak terdaftar sebagai profil siswa.');
        }

        return $student;
    }

    /**
     * Pastikan sesi ujian masih aktif (belum selesai, belum terkunci, dan belum timeout).
     */
    protected function ensureSessionActive(ExamSession $session): void
    {
        if ($session->status === 'selesai') {
            throw new HttpException(422, 'Sesi ujian telah selesai dan tidak dapat diubah.');
        }

        if ($session->status === 'terkunci') {
            throw new HttpException(403, 'Sesi ujian Anda terkunci: '.($session->locked_reason ?? 'Pelanggaran anti-cheat').'. Hubungi pengawas.');
        }

        $now = Carbon::now();
        $effectiveEndsAt = $session->ends_at ?? $session->deadline_at;
        $graceSeconds = (int) config('cbt.grace_period_seconds', 5);

        if ($effectiveEndsAt && $now->greaterThan($effectiveEndsAt->copy()->addSeconds($graceSeconds))) {
            $session->status = 'selesai';
            $session->submitted_at = $effectiveEndsAt;
            $session->save();

            try {
                app(ExamGradingService::class)->gradeSessionObjectiveAnswers($session);
            } catch (\Throwable $gradeEx) {
                // Non-blocking
            }

            $errorCode = $session->ends_at ? 'EXAM_TIME_OVER' : 'BUSINESS_RULE_VIOLATION';

            throw new HttpException(422, 'Waktu pengerjaan ujian telah berakhir (timeout lock).', null, [
                'X-Error-Code' => $errorCode,
            ]);
        }
    }

    /**
     * Otorisasi Pengawas Ruang pada Ujian atau Admin.
     */
    protected function authorizeProctorOrAdminForExam(string $examId, ?User $user): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
            return;
        }

        if ($user->hasPermission('cbt.exam.manage')) {
            return;
        }

        $isProctor = ExamRoom::where('exam_id', $examId)
            ->where('proctor_id', $user->id)
            ->exists();

        if ($isProctor) {
            return;
        }

        throw new HttpException(403, 'Anda tidak berwenang mengelola sesi ujian ini.');
    }

    /**
     * Fisher-Yates shuffle dengan seed deterministik string.
     */
    protected function shuffleWithSeed($collection, string $seedString)
    {
        $items = $collection->values()->all();
        $count = count($items);
        if ($count <= 1) {
            return collect($items);
        }

        // Hasilkan deret angka integer dari seed hash
        $hash = md5($seedString);
        $seedInt = hexdec(substr($hash, 0, 8));

        // Deterministik pseudorandom generator sederhana
        $rng = function () use (&$seedInt) {
            $seedInt = ($seedInt * 1103515245 + 12345) & 0x7FFFFFFF;

            return $seedInt;
        };

        for ($i = $count - 1; $i > 0; $i--) {
            $j = $rng() % ($i + 1);
            $temp = $items[$i];
            $items[$i] = $items[$j];
            $items[$j] = $temp;
        }

        return collect($items);
    }

    /**
     * BE-CBT-06: Monitoring Live Sesi Ujian Pengawas / Admin.
     * GET /api/v1/exams/{id}/monitor
     */
    public function getLiveMonitoring(string $examId, array $filters, Request $request): array
    {
        $user = $request->user();
        $this->authorizeProctorOrAdminForExam($examId, $user);

        $exam = Exam::withCount('questions')->findOrFail($examId);
        $totalQuestions = $exam->questions_count;

        $participantsQuery = ExamParticipant::with(['student.user', 'examRoom.room', 'examRoom.proctor'])
            ->where('exam_id', $examId);

        // SEC-AUTHZ-01: Isolasi ruangan untuk pengawas non-admin
        $isGlobalAdmin = $user && ($user->hasRole(['admin', 'super_admin']) || $user->hasPermission('cbt.exam.manage'));
        if (! $isGlobalAdmin) {
            $assignedRoomIds = ExamRoom::where('exam_id', $examId)
                ->where('proctor_id', $user->id)
                ->pluck('id')
                ->all();

            if (! empty($filters['room_id'])) {
                if (! in_array($filters['room_id'], $assignedRoomIds)) {
                    throw new HttpException(403, 'Anda tidak berwenang memantau ruangan ujian ini.');
                }
                $participantsQuery->where('exam_room_id', $filters['room_id']);
            } else {
                $participantsQuery->whereIn('exam_room_id', $assignedRoomIds);
            }
        } elseif (! empty($filters['room_id'])) {
            $participantsQuery->where('exam_room_id', $filters['room_id']);
        }

        $allParticipants = $participantsQuery->get();
        $participantStudentIds = $allParticipants->pluck('student_id')->all();

        $sessions = ExamSession::where('exam_id', $examId)
            ->whereIn('student_id', $participantStudentIds)
            ->withCount(['answers' => function ($q) {
                $q->whereNotNull('answer');
            }])
            ->withCount('violations')
            ->get()
            ->keyBy('student_id');

        $notStarted = 0;
        $inProgress = 0;
        $finished = 0;
        $locked = 0;
        $totalViolations = 0;

        $items = [];
        $now = Carbon::now();

        foreach ($allParticipants as $participant) {
            $session = $sessions->get($participant->student_id);
            $sessionStatus = $session ? $session->status : 'belum';

            if (! $session || $sessionStatus === 'belum') {
                $notStarted++;
            } elseif ($sessionStatus === 'mengerjakan') {
                $inProgress++;
            } elseif ($sessionStatus === 'selesai') {
                $finished++;
            } elseif ($sessionStatus === 'terkunci') {
                $locked++;
            }

            $violationCount = $session ? $session->violations_count : 0;
            $totalViolations += $violationCount;

            $remainingSeconds = 0;
            if ($session && $sessionStatus === 'mengerjakan' && $session->deadline_at) {
                $remainingSeconds = max(0, $now->diffInSeconds($session->deadline_at, false));
            }

            $answeredCount = $session ? $session->answers_count : 0;
            $progressPct = $totalQuestions > 0 ? round(($answeredCount / $totalQuestions) * 100, 1) : 0;

            if (! empty($filters['status']) && $sessionStatus !== $filters['status']) {
                continue;
            }

            if (! empty($filters['search'])) {
                $search = strtolower($filters['search']);
                $name = strtolower($participant->student?->full_name ?? '');
                $nis = strtolower($participant->student?->nis ?? '');
                if (! str_contains($name, $search) && ! str_contains($nis, $search)) {
                    continue;
                }
            }

            $items[] = [
                'participant_id' => $participant->id,
                'student_id' => $participant->student_id,
                'student_name' => $participant->student?->full_name,
                'student_nis' => $participant->student?->nis,
                'package_code' => $participant->package_code,
                'room_id' => $participant->exam_room_id,
                'room_name' => $participant->examRoom?->room?->name,
                'proctor_name' => $participant->examRoom?->proctor?->name,
                'session_id' => $session?->id,
                'status' => $sessionStatus,
                'started_at' => $session?->started_at?->toIso8601String(),
                'deadline_at' => $session?->deadline_at?->toIso8601String(),
                'submitted_at' => $session?->submitted_at?->toIso8601String(),
                'extra_minutes' => $session?->extra_minutes ?? 0,
                'remaining_seconds' => $remainingSeconds,
                'answered_questions' => $answeredCount,
                'total_questions' => $totalQuestions,
                'progress_percentage' => $progressPct,
                'violation_count' => $violationCount,
                'last_heartbeat_at' => $session?->last_heartbeat_at?->toIso8601String(),
                'ip_address' => $session?->ip_address,
                'blur_count' => $session?->blur_count ?? 0,
            ];
        }

        return [
            'exam_id' => $exam->id,
            'exam_title' => $exam->title,
            'duration_min' => $exam->duration_min,
            'server_time' => $now->toIso8601String(),
            'summary' => [
                'total_participants' => $allParticipants->count(),
                'not_started_count' => $notStarted,
                'in_progress_count' => $inProgress,
                'finished_count' => $finished,
                'locked_count' => $locked,
                'total_violations' => $totalViolations,
            ],
            'participants' => $items,
        ];
    }

    /**
     * BE-CBT-12: Heartbeat Peserta & Deteksi Anomali Perangkat/IP.
     * POST /api/v1/exam-sessions/{sessionId}/heartbeat
     */
    public function recordHeartbeat(string $sessionId, array $data, Request $request): array
    {
        $session = ExamSession::with(['exam', 'student.user'])->findOrFail($sessionId);

        $user = $request->user();
        if ($user && ! $user->hasRole(['admin', 'super_admin'])) {
            if ($session->student?->user_id !== $user->id) {
                throw new HttpException(403, 'Anda tidak berwenang mengirim heartbeat untuk sesi ini.');
            }
        }

        if ($session->status !== 'mengerjakan') {
            throw new HttpException(422, 'Sesi ujian sedang tidak dalam status aktif pengerjaan.');
        }

        $now = Carbon::now();
        $incomingIp = $request->ip();
        $incomingUa = (string) $request->userAgent();
        $deviceFingerprint = $data['device_fingerprint'] ?? null;
        $screenState = $data['screen_state'] ?? 'fullscreen';
        $activeTab = $data['active_tab'] ?? true;

        $anomaliesDetected = [];

        // 1. Deteksi pergantian alamat IP
        if (! empty($session->ip_address) && $session->ip_address !== $incomingIp) {
            ExamViolation::create([
                'session_id' => $session->id,
                'violation_type' => 'anomaly_ip_change',
                'meta' => [
                    'previous_ip' => $session->ip_address,
                    'new_ip' => $incomingIp,
                    'description' => 'Pergantian alamat IP terdeteksi selama sesi aktif berlangsung',
                ],
                'occurred_at' => $now,
            ]);
            $anomaliesDetected[] = 'anomaly_ip_change';
        }

        // 2. Deteksi pergantian browser / User-Agent
        if (! empty($session->user_agent) && $session->user_agent !== $incomingUa) {
            ExamViolation::create([
                'session_id' => $session->id,
                'violation_type' => 'anomaly_device_change',
                'meta' => [
                    'previous_ua' => $session->user_agent,
                    'new_ua' => $incomingUa,
                    'description' => 'Perubahan User-Agent / peramban terdeteksi',
                ],
                'occurred_at' => $now,
            ]);
            $anomaliesDetected[] = 'anomaly_device_change';
        }

        // 3. Deteksi jeda waktu mencurigakan (gap > 180 detik)
        if ($session->last_heartbeat_at) {
            $gapSeconds = abs((int) $now->diffInSeconds($session->last_heartbeat_at));
            if ($gapSeconds > 180) {
                ExamViolation::create([
                    'session_id' => $session->id,
                    'violation_type' => 'anomaly_suspicious_gap',
                    'meta' => [
                        'gap_seconds' => $gapSeconds,
                        'description' => 'Jeda waktu heartbeat terlalu lama tanpa koneksi aktif',
                    ],
                    'occurred_at' => $now,
                ]);
                $anomaliesDetected[] = 'anomaly_suspicious_gap';
            }
        }

        // 4. Deteksi blur atau perpindahan tab
        if ($screenState === 'blur' || $activeTab === false) {
            $session->blur_count = ($session->blur_count ?? 0) + 1;
            ExamViolation::create([
                'session_id' => $session->id,
                'violation_type' => 'blur',
                'meta' => [
                    'screen_state' => $screenState,
                    'active_tab' => $activeTab,
                    'blur_count' => $session->blur_count,
                ],
                'occurred_at' => $now,
            ]);
            $anomaliesDetected[] = 'blur';
        }

        // Update session tracking
        $session->last_heartbeat_at = $now;
        $session->ip_address = $incomingIp;
        $session->user_agent = substr($incomingUa, 0, 500);
        if ($deviceFingerprint) {
            $session->device_fingerprint = $deviceFingerprint;
        }
        $session->save();

        $remainingSeconds = max(0, $now->diffInSeconds($session->deadline_at, false));

        return [
            'session_id' => $session->id,
            'status' => $session->status,
            'server_time' => $now->toIso8601String(),
            'remaining_seconds' => $remainingSeconds,
            'anomalies_detected' => $anomaliesDetected,
            'blur_count' => $session->blur_count,
        ];
    }

    /**
     * BE-CBT-12: Ambil Daftar Anomali Ujian untuk Ditinjau Pengawas.
     * GET /api/v1/exams/{id}/anomalies
     */
    public function getExamAnomalies(string $examId, array $filters, Request $request): array
    {
        $user = $request->user();
        $this->authorizeProctorOrAdminForExam($examId, $user);

        $exam = Exam::findOrFail($examId);

        $query = ExamViolation::whereHas('session', function ($q) use ($examId) {
            $q->where('exam_id', $examId);
        })->with(['session.student']);

        // SEC-AUTHZ-01: Isolasi anomali hanya untuk siswa di ruangan yang ditugaskan
        $isGlobalAdmin = $user && ($user->hasRole(['admin', 'super_admin']) || $user->hasPermission('cbt.exam.manage'));
        if (! $isGlobalAdmin) {
            $assignedRoomIds = ExamRoom::where('exam_id', $examId)
                ->where('proctor_id', $user->id)
                ->pluck('id')
                ->all();

            $assignedStudentIds = ExamParticipant::where('exam_id', $examId)
                ->whereIn('exam_room_id', $assignedRoomIds)
                ->pluck('student_id')
                ->all();

            $query->whereHas('session', function ($q) use ($examId, $assignedStudentIds) {
                $q->where('exam_id', $examId)
                    ->whereIn('student_id', $assignedStudentIds);
            });
        }

        if (! empty($filters['type'])) {
            $query->where('violation_type', $filters['type']);
        }

        $allViolations = $query->orderBy('occurred_at', 'desc')->get();

        $ipChangeCount = $allViolations->where('violation_type', 'anomaly_ip_change')->count();
        $deviceChangeCount = $allViolations->where('violation_type', 'anomaly_device_change')->count();
        $gapCount = $allViolations->where('violation_type', 'anomaly_suspicious_gap')->count();
        $blurCount = $allViolations->where('violation_type', 'blur')->count();

        $list = $allViolations->map(function ($v) {
            return [
                'id' => $v->id,
                'session_id' => $v->session_id,
                'student_id' => $v->session?->student_id,
                'student_name' => $v->session?->student?->full_name,
                'student_nis' => $v->session?->student?->nis,
                'violation_type' => $v->violation_type,
                'meta' => $v->meta,
                'occurred_at' => $v->occurred_at?->toIso8601String() ?? $v->created_at?->toIso8601String(),
            ];
        });

        return [
            'exam_id' => $exam->id,
            'exam_title' => $exam->title,
            'summary' => [
                'total_anomalies' => $allViolations->count(),
                'ip_changes' => $ipChangeCount,
                'device_changes' => $deviceChangeCount,
                'suspicious_gaps' => $gapCount,
                'blur_events' => $blurCount,
            ],
            'anomalies' => $list,
        ];
    }

    /**
     * BE-CBT-04 / GAP-B: Siswa melihat hasil dan pembahasan mandiri ujian.
     */
    public function getExamReview(string $examId, Request $request): array
    {
        $user = $request->user();
        $student = $this->resolveStudentForUser($user);

        $exam = Exam::with(['subject'])->findOrFail($examId);

        if (in_array($exam->status, ['draft', 'dibatalkan'])) {
            throw new HttpException(403, 'Ujian ini tidak dalam status yang dapat direview.');
        }

        $participant = ExamParticipant::where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->first();

        if (! $participant) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak terdaftar sebagai peserta ujian ini.');
        }

        $session = ExamSession::where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $session) {
            throw new HttpException(404, 'Sesi ujian Anda tidak ditemukan.');
        }

        if ($session->status !== 'selesai') {
            throw new HttpException(403, 'Hasil dan pembahasan hanya dapat diakses setelah sesi ujian selesai dikumpulkan.');
        }

        $mode = $exam->show_result_mode ?? 'never';

        if ($mode === 'never') {
            throw new HttpException(403, 'Hasil dan pembahasan mandiri dinonaktifkan untuk ujian ini.');
        }

        if ($mode === 'after_release') {
            if (! in_array($exam->status, ['selesai', 'arsip'])) {
                throw new HttpException(403, 'Hasil dan pembahasan ujian belum dirilis oleh pihak sekolah.');
            }
        }

        $packageCode = $participant->package_code;
        $examQuestionsQuery = ExamQuestion::with(['question.options'])
            ->where('exam_id', $exam->id);

        if ($packageCode) {
            $examQuestionsQuery->where(function ($q) use ($packageCode) {
                $q->where('package_code', $packageCode)
                    ->orWhereNull('package_code');
            });
        }

        $examQuestions = $examQuestionsQuery->orderBy('sort_order', 'asc')->get();

        $savedAnswers = ExamAnswer::where('session_id', $session->id)
            ->get()
            ->keyBy('question_id');

        $questionItems = $examQuestions->map(fn ($eq) => $eq->question)->filter()->values();
        if ($exam->shuffle_questions) {
            $questionItems = $this->shuffleWithSeed($questionItems, $session->seed.'_q');
        }

        $examQuestionsByKey = $examQuestions->keyBy('question_id');

        $reviewedQuestions = [];
        $totalAnswered = 0;
        $totalCorrect = 0;
        $hasEssay = false;

        foreach ($questionItems as $index => $q) {
            $answer = $savedAnswers->get($q->id);
            $eq = $examQuestionsByKey->get($q->id);
            $maxWeight = (float) ($eq?->weight_override ?? $q->weight ?? 1.0);
            $userAnswer = $answer?->answer;

            if ($userAnswer !== null) {
                $totalAnswered++;
            }

            $isEssay = ($q->question_type === 'uraian');
            if ($isEssay) {
                $hasEssay = true;
            }

            $options = $q->options;
            if ($exam->shuffle_options && in_array($q->question_type, ['pg', 'pg_kompleks', 'bs'])) {
                $options = $this->shuffleWithSeed($options, $session->seed.'_opt_'.$q->id);
            }

            $optionsData = $options->map(function ($opt) {
                return [
                    'id' => $opt->id,
                    'label' => $opt->label,
                    'body' => $opt->body,
                    'is_correct' => (bool) $opt->is_correct,
                    'match_key' => $opt->match_key,
                ];
            })->values()->all();

            $score = $answer?->score !== null ? (float) $answer->score : null;
            $isCorrect = null;

            if (! $isEssay) {
                if ($score !== null) {
                    $isCorrect = ($score >= $maxWeight);
                }
                if ($isCorrect) {
                    $totalCorrect++;
                }
            }

            $reviewedQuestions[] = [
                'number' => $index + 1,
                'question_id' => $q->id,
                'question_type' => $q->question_type,
                'body' => $q->body,
                'options' => $optionsData,
                'max_score' => $maxWeight,
                'student_answer' => $userAnswer,
                'score' => $score,
                'is_correct' => $isCorrect,
                'is_graded' => $isEssay ? ($score !== null) : true,
                'explanation' => $q->explanation,
            ];
        }

        $totalScore = $session->score !== null ? (float) $session->score : null;
        $passingGrade = $exam->passing_grade !== null ? (float) $exam->passing_grade : 75.0;
        $isPassed = $totalScore !== null ? ($totalScore >= $passingGrade) : null;

        $remedialExam = null;
        if ($isPassed === false) {
            $remedial = Exam::where('parent_exam_id', $exam->id)
                ->whereIn('status', ['dijadwalkan', 'berlangsung'])
                ->first();
            if ($remedial) {
                $remedialExam = [
                    'remedial_exam_id' => $remedial->id,
                    'title' => $remedial->title,
                    'start_at' => $remedial->start_at?->toIso8601String(),
                    'end_at' => $remedial->end_at?->toIso8601String(),
                ];
            }
        }

        return [
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'exam_type' => $exam->exam_type,
                'subject_name' => $exam->subject?->name,
                'passing_grade' => $passingGrade,
                'show_result_mode' => $mode,
            ],
            'attempt' => [
                'session_id' => $session->id,
                'student_id' => $student->id,
                'student_name' => $student->full_name,
                'status' => $session->status,
                'started_at' => $session->started_at?->toIso8601String(),
                'submitted_at' => $session->submitted_at?->toIso8601String(),
                'total_score' => $totalScore,
                'is_passed' => $isPassed,
                'total_questions' => count($questionItems),
                'total_answered' => $totalAnswered,
                'has_ungraded_essay' => $hasEssay && ($totalScore === null),
            ],
            'remedial' => $remedialExam,
            'questions' => $reviewedQuestions,
        ];
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
