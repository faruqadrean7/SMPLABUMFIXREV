<?php

namespace App\Services\Cbt;

use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamItemAnalysis;
use App\Models\ExamParticipant;
use App\Models\ExamQuestion;
use App\Models\ExamRoom;
use App\Models\ExamSession;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradePeriod;
use App\Models\Question;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExamGradingService
{
    /**
     * BE-CBT-05: Jalankan koreksi otomatis untuk soal-soal objektif (PG, PG Kompleks, BS, Menjodohkan, Isian).
     */
    public function autoGradeExam(string $examId, array $data, Request $request): array
    {
        $exam = Exam::with(['questions.question.options'])->findOrFail($examId);
        $this->authorizeTeacherOrAdmin($exam, $request->user());

        $sessionQuery = ExamSession::with(['answers.question.options', 'student'])
            ->where('exam_id', $exam->id);

        if (! empty($data['session_id'])) {
            $sessionQuery->where('id', $data['session_id']);
        }

        $sessions = $sessionQuery->get();

        if ($sessions->isEmpty()) {
            throw new HttpException(422, 'Tidak ada sesi ujian yang ditemukan untuk dinilai.');
        }

        // Peta bobot soal ujian (mengutamakan weight_override jika ada)
        $examQuestions = ExamQuestion::where('exam_id', $exam->id)->get()->keyBy('question_id');

        $gradedSessionsCount = 0;
        $gradedAnswersCount = 0;

        DB::transaction(function () use ($sessions, $examQuestions, $request, &$gradedSessionsCount, &$gradedAnswersCount) {
            foreach ($sessions as $session) {
                foreach ($session->answers as $answer) {
                    $question = $answer->question;
                    if (! $question) {
                        continue;
                    }

                    // Tipe objektif yang dikoreksi otomatis
                    if (in_array($question->question_type, ['pg', 'pg_kompleks', 'bs', 'menjodohkan', 'isian'])) {
                        $eq = $examQuestions->get($question->id);
                        $maxWeight = (float) ($eq?->weight_override ?? $question->weight ?? 1.0);

                        $score = $this->evaluateObjectiveAnswer($question, $answer->answer, $maxWeight);

                        $answer->score = $score;
                        $answer->graded_by = $request->user()?->id;
                        $answer->save();

                        $gradedAnswersCount++;
                    }
                }

                // Kalkulasi ulang total score sesi
                $this->refreshSessionTotalScore($session, $examQuestions);
                $gradedSessionsCount++;
            }
        });

        // Update analisis butir soal secara otomatis
        $this->recalculateItemAnalysis($exam->id);

        $this->logAudit(
            $request->user()?->id,
            'exam.auto_grade',
            'exams',
            $exam->id,
            null,
            ['sessions_graded' => $gradedSessionsCount, 'answers_evaluated' => $gradedAnswersCount],
            $request
        );

        return [
            'exam_id' => $exam->id,
            'sessions_graded' => $gradedSessionsCount,
            'answers_evaluated' => $gradedAnswersCount,
            'message' => "Koreksi otomatis berhasil dijalankan untuk {$gradedSessionsCount} sesi peserta.",
        ];
    }

    /**
     * BE-CBT-05: Ambil daftar lembar jawaban esai/uraian yang perlu dinilai secara manual oleh guru.
     */
    public function getEssaySubmissions(string $examId, array $filters, Request $request): array
    {
        $exam = Exam::findOrFail($examId);
        $this->authorizeTeacherOrAdmin($exam, $request->user());

        $query = ExamAnswer::with([
            'session.student',
            'question',
        ])
            ->whereHas('session', function ($sq) use ($exam) {
                $sq->where('exam_id', $exam->id);
            })
            ->whereHas('question', function ($qq) {
                $qq->where('question_type', 'uraian');
            });

        if (! empty($filters['question_id'])) {
            $query->where('question_id', $filters['question_id']);
        }

        if (! empty($filters['status']) && $filters['status'] === 'graded') {
            $query->whereNotNull('score');
        } elseif (! empty($filters['status']) && $filters['status'] === 'ungraded') {
            $query->whereNull('score');
        }

        $answers = $query->orderBy('session_id')->get();
        $examQuestions = ExamQuestion::where('exam_id', $exam->id)->get()->keyBy('question_id');

        $items = $answers->map(function ($ans) use ($examQuestions) {
            $eq = $examQuestions->get($ans->question_id);
            $maxScore = (float) ($eq?->weight_override ?? $ans->question?->weight ?? 1.0);

            return [
                'answer_id' => $ans->id,
                'session_id' => $ans->session_id,
                'student_id' => $ans->session?->student_id,
                'student_name' => $ans->session?->student?->full_name,
                'nis' => $ans->session?->student?->nis,
                'question_id' => $ans->question_id,
                'question_text' => $ans->question?->body['content'] ?? ($ans->question?->body['text'] ?? ''),
                'student_answer' => $ans->answer,
                'current_score' => $ans->score !== null ? (float) $ans->score : null,
                'max_score' => $maxScore,
                'is_graded' => $ans->score !== null,
                'graded_by' => $ans->grader?->name,
                'answered_at' => $ans->answered_at?->toIso8601String(),
            ];
        });

        return [
            'exam_id' => $exam->id,
            'total_essays' => $items->count(),
            'ungraded_count' => $items->where('is_graded', false)->count(),
            'submissions' => $items->values()->all(),
        ];
    }

    /**
     * BE-CBT-05: Input skor manual untuk soal uraian/esai oleh guru.
     */
    public function gradeEssayAnswer(string $examId, array $data, Request $request): array
    {
        $exam = Exam::findOrFail($examId);
        $this->authorizeTeacherOrAdmin($exam, $request->user());

        $answer = ExamAnswer::with(['session', 'question'])->findOrFail($data['answer_id']);

        if ($answer->session?->exam_id !== $exam->id) {
            throw new HttpException(422, 'Jawaban ini bukan bagian dari ujian yang dipilih.');
        }

        $eq = ExamQuestion::where('exam_id', $exam->id)
            ->where('question_id', $answer->question_id)
            ->first();

        $maxScore = (float) ($eq?->weight_override ?? $answer->question?->weight ?? 10.0);
        $givenScore = (float) $data['score'];

        if ($givenScore < 0 || $givenScore > $maxScore) {
            throw new HttpException(422, "Skor nilai harus berada dalam rentang 0 sampai {$maxScore}.");
        }

        $answer->score = $givenScore;
        $answer->graded_by = $request->user()?->id;
        $answer->save();

        // Refresh total score sesi siswa
        $examQuestions = ExamQuestion::where('exam_id', $exam->id)->get()->keyBy('question_id');
        $this->refreshSessionTotalScore($answer->session, $examQuestions);

        $this->logAudit(
            $request->user()?->id,
            'exam.grade_essay',
            'exam_answers',
            $answer->id,
            null,
            ['score' => $givenScore, 'graded_by' => $request->user()?->id],
            $request
        );

        return [
            'answer_id' => $answer->id,
            'session_id' => $answer->session_id,
            'question_id' => $answer->question_id,
            'score' => (float) $answer->score,
            'max_score' => $maxScore,
            'session_total_score' => (float) $answer->session->fresh()->score,
        ];
    }

    /**
     * BE-CBT-05: Rekap hasil nilai akhir ujian peserta CBT.
     */
    public function getExamResults(string $examId, array $filters, Request $request): array
    {
        $exam = Exam::with(['subject'])->findOrFail($examId);
        $this->authorizeTeacherOrAdmin($exam, $request->user());

        $query = ExamSession::with(['student.classes', 'answers'])
            ->where('exam_id', $exam->id)
            ->orderBy('created_at', 'asc');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $sessions = $query->get();
        $passingGrade = (float) ($exam->passing_grade ?? 75.0);

        $results = $sessions->map(function ($s) use ($passingGrade) {
            $score = $s->score !== null ? (float) $s->score : null;
            $isPassed = $score !== null && $score >= $passingGrade;

            return [
                'session_id' => $s->id,
                'student_id' => $s->student_id,
                'student_name' => $s->student?->full_name,
                'nis' => $s->student?->nis,
                'class_name' => $s->student?->classes?->first()?->name ?? '-',
                'status' => $s->status,
                'started_at' => $s->started_at?->toIso8601String(),
                'submitted_at' => $s->submitted_at?->toIso8601String(),
                'total_score' => $score,
                'passing_grade' => $passingGrade,
                'is_passed' => $isPassed,
                'answers_count' => $s->answers->count(),
            ];
        });

        $completedScores = $results->pluck('total_score')->filter(fn ($v) => $v !== null)->values();
        $averageScore = $completedScores->count() > 0 ? round($completedScores->avg(), 2) : 0;
        $highestScore = $completedScores->count() > 0 ? (float) $completedScores->max() : 0;
        $lowestScore = $completedScores->count() > 0 ? (float) $completedScores->min() : 0;
        $passedCount = $results->where('is_passed', true)->count();

        return [
            'exam_id' => $exam->id,
            'exam_title' => $exam->title,
            'subject_name' => $exam->subject?->name,
            'passing_grade' => $passingGrade,
            'summary' => [
                'total_participants' => $results->count(),
                'completed_participants' => $completedScores->count(),
                'passed_participants' => $passedCount,
                'failed_participants' => $completedScores->count() - $passedCount,
                'average_score' => $averageScore,
                'highest_score' => $highestScore,
                'lowest_score' => $lowestScore,
            ],
            'results' => $results->values()->all(),
        ];
    }

    /**
     * BE-CBT-05: Rekapitulasi Analisis Butir Soal (Item Analysis).
     * Menghitung tingkat kesukaran ($p$) dan daya pembeda ($D$) butir soal.
     */
    public function getItemAnalysis(string $examId, Request $request): array
    {
        $exam = Exam::with('questions.question.options')->findOrFail($examId);
        $this->authorizeTeacherOrAdmin($exam, $request->user());

        $analyses = ExamItemAnalysis::with('question.options')
            ->where('exam_id', $exam->id)
            ->get();

        if ($analyses->isEmpty()) {
            $this->recalculateItemAnalysis($exam->id);
            $analyses = ExamItemAnalysis::with('question.options')
                ->where('exam_id', $exam->id)
                ->get();
        }

        $items = $analyses->map(function ($an) {
            $diffIndex = (float) $an->difficulty_index;
            $discIndex = (float) $an->discrimination_index;

            // Kategori tingkat kesukaran: > 0.7 Mudah, 0.3 - 0.7 Sedang, < 0.3 Sukar
            $difficultyCategory = match (true) {
                $diffIndex > 0.70 => 'Mudah',
                $diffIndex >= 0.30 => 'Sedang',
                default => 'Sukar',
            };

            // Kategori daya pembeda: >= 0.40 Sangat Baik, 0.30-0.39 Baik, 0.20-0.29 Cukup, < 0.20 Buruk/Perlu Revisi
            $discriminationCategory = match (true) {
                $discIndex >= 0.40 => 'Sangat Baik',
                $discIndex >= 0.30 => 'Baik',
                $discIndex >= 0.20 => 'Cukup',
                default => 'Perlu Revisi',
            };

            return [
                'question_id' => $an->question_id,
                'question_type' => $an->question?->question_type,
                'body' => $an->question?->body,
                'difficulty_index' => $diffIndex,
                'difficulty_category' => $difficultyCategory,
                'discrimination_index' => $discIndex,
                'discrimination_category' => $discriminationCategory,
                'distractor_stats' => $an->distractor_stats,
                'calculated_at' => $an->calculated_at?->toIso8601String(),
            ];
        });

        return [
            'exam_id' => $exam->id,
            'exam_title' => $exam->title,
            'total_analyzed_questions' => $items->count(),
            'analysis' => $items->values()->all(),
        ];
    }

    /**
     * Hitung ulang analisis butir soal untuk seluruh soal ujian.
     */
    public function recalculateItemAnalysis(string $examId): void
    {
        $exam = Exam::with(['questions.question.options', 'sessions.answers'])->findOrFail($examId);

        // Ambil semua sesi yang memiliki skor
        $sessions = $exam->sessions->filter(fn ($s) => $s->score !== null)->sortByDesc('score')->values();
        $totalSessions = $sessions->count();

        if ($totalSessions === 0) {
            return;
        }

        // Tentukan kelompok atas dan bawah (masing-masing 27% dari total peserta, minimal 1)
        $groupSize = max(1, (int) round($totalSessions * 0.27));
        if ($totalSessions == 2) {
            $groupSize = 1;
        }

        $upperGroup = $sessions->slice(0, $groupSize);
        $lowerGroup = $sessions->slice(-$groupSize);

        $examQuestions = $exam->questions;

        foreach ($examQuestions as $eq) {
            $q = $eq->question;
            if (! $q) {
                continue;
            }

            $maxWeight = (float) ($eq->weight_override ?? $q->weight ?? 1.0);

            // Kumpulkan semua jawaban untuk soal ini
            $allAnswers = ExamAnswer::whereHas('session', fn ($sq) => $sq->where('exam_id', $exam->id))
                ->where('question_id', $q->id)
                ->get();

            $totalParticipants = $allAnswers->count();
            if ($totalParticipants === 0) {
                continue;
            }

            // Jumlah proporsi kebenaran (skor perolehan / maxWeight)
            $totalScoreSum = $allAnswers->sum(fn ($a) => (float) ($a->score ?? 0));
            $difficultyIndex = round($totalScoreSum / ($totalParticipants * $maxWeight), 3);

            // Hitung daya pembeda: p_upper - p_lower
            $upperAnswers = $allAnswers->whereIn('session_id', $upperGroup->pluck('id'));
            $lowerAnswers = $allAnswers->whereIn('session_id', $lowerGroup->pluck('id'));

            $upperScoreAvg = $upperAnswers->count() > 0 ? ($upperAnswers->sum('score') / ($upperAnswers->count() * $maxWeight)) : 0;
            $lowerScoreAvg = $lowerAnswers->count() > 0 ? ($lowerAnswers->sum('score') / ($lowerAnswers->count() * $maxWeight)) : 0;

            $discriminationIndex = round($upperScoreAvg - $lowerScoreAvg, 3);

            // Statistik distraktor untuk soal PG
            $distractorStats = [];
            if ($q->question_type === 'pg' && $q->options->isNotEmpty()) {
                foreach ($q->options as $opt) {
                    $chosenCount = $allAnswers->filter(function ($a) use ($opt) {
                        return is_array($a->answer)
                            && (
                                ($a->answer['selected_option_id'] ?? null) === $opt->id
                                || ($a->answer['option_id'] ?? null) === $opt->id
                                || ($a->answer['value'] ?? null) === $opt->label
                            );
                    })->count();

                    $distractorStats[] = [
                        'option_id' => $opt->id,
                        'label' => $opt->label,
                        'is_correct' => (bool) $opt->is_correct,
                        'chosen_count' => $chosenCount,
                        'percentage' => round(($chosenCount / $totalParticipants) * 100, 1),
                    ];
                }
            }

            ExamItemAnalysis::updateOrCreate(
                [
                    'exam_id' => $exam->id,
                    'question_id' => $q->id,
                ],
                [
                    'difficulty_index' => $difficultyIndex,
                    'discrimination_index' => $discriminationIndex,
                    'distractor_stats' => $distractorStats,
                    'calculated_at' => Carbon::now(),
                ]
            );
        }
    }

    /**
     * Hitung skor jawaban butir objektif.
     */
    protected function evaluateObjectiveAnswer(Question $question, mixed $userAnswer, float $maxWeight): float
    {
        if ($userAnswer === null) {
            return 0.0;
        }

        $type = $question->question_type;

        switch ($type) {
            case 'pg':
            case 'bs':
                // Cari opsi yang benar
                $correctOption = $question->options->firstWhere('is_correct', true);
                if (! $correctOption) {
                    return 0.0;
                }

                $chosenId = is_array($userAnswer)
                    ? ($userAnswer['selected_option_id'] ?? $userAnswer['option_id'] ?? $userAnswer['opt'] ?? $userAnswer['value'] ?? null)
                    : $userAnswer;

                $correctText = is_array($correctOption->body) ? ($correctOption->body['text'] ?? $correctOption->body['content'] ?? '') : (string) $correctOption->body;
                if (
                    $chosenId === $correctOption->id ||
                    $chosenId === $correctOption->label ||
                    (is_string($chosenId) && strtolower(trim($chosenId)) === strtolower(trim($correctText))) ||
                    (isset($userAnswer['value']) && strtolower(trim((string) $userAnswer['value'])) === strtolower(trim((string) $correctOption->label))) ||
                    (isset($userAnswer['value']) && strtolower(trim((string) $userAnswer['value'])) === strtolower(trim($correctText)))
                ) {
                    return $maxWeight;
                }

                return 0.0;

            case 'pg_kompleks':
                // Multiple select: hitung proporsi kebenaran
                $correctOptionIds = $question->options->where('is_correct', true)->pluck('id')->all();
                $chosenIds = [];
                if (is_array($userAnswer)) {
                    if (isset($userAnswer['selected_option_ids']) && is_array($userAnswer['selected_option_ids'])) {
                        $chosenIds = $userAnswer['selected_option_ids'];
                    } elseif (isset($userAnswer['option_ids']) && is_array($userAnswer['option_ids'])) {
                        $chosenIds = $userAnswer['option_ids'];
                    } elseif (array_is_list($userAnswer)) {
                        $chosenIds = $userAnswer;
                    }
                }

                if (empty($correctOptionIds) || empty($chosenIds)) {
                    return 0.0;
                }

                $correctCount = count(array_intersect($chosenIds, $correctOptionIds));
                $wrongCount = count(array_diff($chosenIds, $correctOptionIds));

                // Skor parsial proporsional dikurangi kesalahan
                $ratio = max(0, ($correctCount - $wrongCount) / count($correctOptionIds));

                return round($ratio * $maxWeight, 2);

            case 'menjodohkan':
                // Format matching: key-value pairs
                $options = $question->options;
                if ($options->isEmpty() || ! is_array($userAnswer)) {
                    return 0.0;
                }

                $pairs = $userAnswer['pairs'] ?? $userAnswer;
                if (! is_array($pairs)) {
                    return 0.0;
                }

                $matchedCount = 0;
                $totalTarget = 0;

                foreach ($options as $opt) {
                    if ($opt->match_key !== null) {
                        $totalTarget++;
                        if (isset($pairs[$opt->id]) && (string) $pairs[$opt->id] === (string) $opt->match_key) {
                            $matchedCount++;
                        } elseif (isset($pairs[$opt->label]) && (string) $pairs[$opt->label] === (string) $opt->match_key) {
                            $matchedCount++;
                        }
                    }
                }

                if ($totalTarget === 0) {
                    return 0.0;
                }

                return round(($matchedCount / $totalTarget) * $maxWeight, 2);

            case 'isian':
                // Isian singkat: bandingkan dengan label atau body teks opsi yang benar (case-insensitive & trimmed)
                $correctText = null;
                $correctOption = $question->options->firstWhere('is_correct', true);

                if ($correctOption) {
                    $correctText = $correctOption->body['text'] ?? $correctOption->label;
                } elseif (isset($question->body['correct_answer'])) {
                    $correctText = $question->body['correct_answer'];
                }

                if ($correctText === null) {
                    return 0.0;
                }

                $studentText = is_array($userAnswer) ? ($userAnswer['text'] ?? $userAnswer['value'] ?? '') : (string) $userAnswer;

                $normCorrect = strtolower(trim(preg_replace('/\s+/', ' ', (string) $correctText)));
                $normStudent = strtolower(trim(preg_replace('/\s+/', ' ', (string) $studentText)));

                if ($normCorrect === $normStudent) {
                    return $maxWeight;
                }

                return 0.0;

            default:
                return 0.0;
        }
    }

    /**
     * Hitung nilai butir soal objektif (PG, dsb) dan total skor untuk satu sesi ujian.
     */
    public function gradeSessionObjectiveAnswers(ExamSession $session): void
    {
        $session->loadMissing(['answers.question.options', 'exam']);
        $examQuestions = ExamQuestion::where('exam_id', $session->exam_id)->get()->keyBy('question_id');

        foreach ($session->answers as $answer) {
            $question = $answer->question;
            if (! $question) {
                continue;
            }

            if (in_array($question->question_type, ['pg', 'pg_kompleks', 'bs', 'menjodohkan', 'isian'])) {
                $eq = $examQuestions->get($question->id);
                $maxWeight = (float) ($eq?->weight_override ?? $question->weight ?? 1.0);
                $score = $this->evaluateObjectiveAnswer($question, $answer->answer, $maxWeight);
                $answer->score = $score;
                $answer->save();
            }
        }

        $this->refreshSessionTotalScore($session, $examQuestions);
    }

    /**
     * Hitung ulang total score sesi dari seluruh butir jawaban dan normalisasi ke skala 0–100.
     */
    public function refreshSessionTotalScore(ExamSession $session, $examQuestions = null): void
    {
        if ($examQuestions === null) {
            $examQuestions = ExamQuestion::where('exam_id', $session->exam_id)->get()->keyBy('question_id');
        }

        $answers = ExamAnswer::where('session_id', $session->id)->get();

        $totalEarned = 0.0;
        $totalMax = 0.0;

        foreach ($examQuestions as $eq) {
            $maxWeight = (float) ($eq->weight_override ?? $eq->question?->weight ?? 1.0);
            $totalMax += $maxWeight;

            $ans = $answers->firstWhere('question_id', $eq->question_id);
            if ($ans && $ans->score !== null) {
                $totalEarned += (float) $ans->score;
            }
        }

        if ($totalMax > 0) {
            $normalizedScore = round(($totalEarned / $totalMax) * 100, 2);
        } else {
            $normalizedScore = 0.0;
        }

        // Jika ujian bertipe remedial dan max_remedial_score ditentukan, batasi nilai maksimum
        $exam = $session->exam;
        if ($exam && $exam->exam_type === 'remedial' && $exam->max_remedial_score !== null) {
            $maxScore = (float) $exam->max_remedial_score;
            if ($normalizedScore > $maxScore) {
                $normalizedScore = $maxScore;
            }
        }

        $session->score = $normalizedScore;
        $session->save();
    }

    /**
     * BE-CBT-09: Buat Ujian Remedial Otomatis untuk Siswa di Bawah KKTP.
     * POST /api/v1/exams/{id}/remedial
     */
    public function createRemedialExam(string $parentExamId, array $data, Request $request): Exam
    {
        $parentExam = Exam::with(['questions.question', 'rooms.room', 'participants'])->findOrFail($parentExamId);
        $this->authorizeTeacherOrAdmin($parentExam, $request->user());

        $passingGrade = (float) ($data['passing_grade'] ?? $parentExam->passing_grade);
        $maxRemedialScore = (float) ($data['max_remedial_score'] ?? $passingGrade);

        $eligibleStudentIds = [];

        if (! empty($data['target_student_ids'])) {
            $parentParticipantStudentIds = $parentExam->participants->pluck('student_id')->all();
            foreach ($data['target_student_ids'] as $sid) {
                if (! in_array($sid, $parentParticipantStudentIds)) {
                    throw new HttpException(422, "Siswa dengan ID {$sid} bukan peserta ujian induk.");
                }
                $eligibleStudentIds[] = $sid;
            }
        } else {
            $sessions = ExamSession::where('exam_id', $parentExam->id)
                ->where(function ($q) use ($passingGrade) {
                    $q->where('score', '<', $passingGrade)
                        ->orWhereNull('score');
                })
                ->get();

            $eligibleStudentIds = $sessions->pluck('student_id')->unique()->values()->all();

            $existingSessionStudentIds = ExamSession::where('exam_id', $parentExam->id)->pluck('student_id')->all();
            $unstartedStudentIds = $parentExam->participants
                ->pluck('student_id')
                ->diff($existingSessionStudentIds)
                ->all();

            $eligibleStudentIds = array_values(array_unique(array_merge($eligibleStudentIds, $unstartedStudentIds)));
        }

        if (empty($eligibleStudentIds)) {
            throw new HttpException(422, 'Tidak ada peserta yang memenuhi syarat remedial (seluruh siswa telah lulus KKTP atau belum ada peserta).');
        }

        $remedialTitle = $data['title'] ?? ('Remedial - '.$parentExam->title);

        // SEC-CBT-REMEDIAL: Idempotensi pembuatan ujian remedial
        $existingRemedial = Exam::where('parent_exam_id', $parentExam->id)
            ->where('title', $remedialTitle)
            ->where('exam_type', 'remedial')
            ->first();

        if ($existingRemedial) {
            return $existingRemedial->load(['subject', 'questions.question', 'rooms', 'participants.student']);
        }

        return DB::transaction(function () use ($parentExam, $data, $eligibleStudentIds, $passingGrade, $maxRemedialScore, $request, $remedialTitle) {
            $remedialExam = Exam::create([
                'school_id' => $parentExam->school_id,
                'subject_id' => $parentExam->subject_id,
                'created_by' => $request->user()->id,
                'parent_exam_id' => $parentExam->id,
                'title' => $remedialTitle,
                'exam_type' => 'remedial',
                'duration_min' => $data['duration_min'] ?? $parentExam->duration_min,
                'start_at' => $data['start_at'],
                'end_at' => $data['end_at'],
                'passing_grade' => $passingGrade,
                'max_remedial_score' => $maxRemedialScore,
                'shuffle_questions' => $parentExam->shuffle_questions,
                'shuffle_options' => $parentExam->shuffle_options,
                'package_mode' => $parentExam->package_mode,
                'status' => 'dijadwalkan',
            ]);

            if (! empty($data['question_ids'])) {
                foreach ($data['question_ids'] as $idx => $qid) {
                    ExamQuestion::create([
                        'exam_id' => $remedialExam->id,
                        'question_id' => $qid,
                        'sort_order' => $idx + 1,
                    ]);
                }
            } elseif (! empty($data['question_bank_id'])) {
                $bankQuestions = Question::where('bank_id', $data['question_bank_id'])
                    ->where('status', 'aktif')
                    ->get();
                foreach ($bankQuestions as $idx => $q) {
                    ExamQuestion::create([
                        'exam_id' => $remedialExam->id,
                        'question_id' => $q->id,
                        'sort_order' => $idx + 1,
                    ]);
                }
            } else {
                foreach ($parentExam->questions as $eq) {
                    ExamQuestion::create([
                        'exam_id' => $remedialExam->id,
                        'question_id' => $eq->question_id,
                        'sort_order' => $eq->sort_order,
                        'weight_override' => $eq->weight_override,
                        'package_code' => $eq->package_code,
                    ]);
                }
            }

            $firstRoom = $parentExam->rooms->first();
            $remedialRoom = ExamRoom::create([
                'exam_id' => $remedialExam->id,
                'room_id' => $firstRoom?->room_id ?? $parentExam->subject_id,
                'proctor_id' => $firstRoom?->proctor_id ?? $request->user()->id,
                'session_no' => 1,
                'token' => strtoupper(Str::random(6)),
                'token_expires_at' => Carbon::parse($data['end_at']),
            ]);

            foreach ($eligibleStudentIds as $sid) {
                ExamParticipant::create([
                    'exam_id' => $remedialExam->id,
                    'student_id' => $sid,
                    'exam_room_id' => $remedialRoom->id,
                ]);
            }

            $this->logAudit(
                $request->user()->id,
                'cbt_exam.create_remedial',
                'exams',
                $remedialExam->id,
                null,
                [
                    'parent_exam_id' => $parentExam->id,
                    'remedial_exam_id' => $remedialExam->id,
                    'enrolled_students_count' => count($eligibleStudentIds),
                    'max_remedial_score' => $maxRemedialScore,
                ],
                $request
            );

            return $remedialExam->load(['subject', 'questions.question', 'rooms', 'participants.student']);
        });
    }

    /**
     * BE-CBT-05 & ADM-GRD-01: Publikasikan hasil nilai ujian CBT ke Buku Nilai (Gradebook).
     * POST /api/v1/exams/{id}/publish-gradebook
     */
    public function publishToGradebook(string $examId, array $data, Request $request): array
    {
        $exam = Exam::with(['subject'])->findOrFail($examId);
        $this->authorizeTeacherOrAdmin($exam, $request->user());

        $component = GradeComponent::with(['subject', 'semester'])->findOrFail($data['component_id']);
        if ($component->subject_id !== $exam->subject_id) {
            throw new HttpException(422, 'Mata pelajaran komponen nilai tidak sesuai dengan mata pelajaran ujian CBT.');
        }

        $period = GradePeriod::findOrFail($data['period_id']);
        if ($period->status === 'locked') {
            throw new HttpException(422, 'Periode penilaian telah dikunci. Pengiriman nilai CBT tidak diizinkan tanpa membuka kunci periode terlebih dahulu.');
        }

        $sessions = ExamSession::where('exam_id', $exam->id)
            ->whereNotNull('score')
            ->get();

        if ($sessions->isEmpty()) {
            throw new HttpException(422, 'Belum ada sesi ujian siswa dengan nilai yang dapat dipublikasikan ke buku nilai.');
        }

        $overwriteLocked = (bool) ($data['overwrite_locked'] ?? false);
        $user = $request->user();
        if ($overwriteLocked) {
            $canOverrideLocked = $user && ($user->hasRole(['admin', 'super_admin']) || $user->hasPermission('grade.unlock'));
            if (! $canOverrideLocked) {
                throw new HttpException(403, 'Anda tidak memiliki hak akses untuk menimpa nilai yang telah dikunci.');
            }
        }

        $syncedCount = 0;
        $skippedLockedCount = 0;

        DB::transaction(function () use ($sessions, $component, $period, $exam, $request, $overwriteLocked, &$syncedCount, &$skippedLockedCount) {
            foreach ($sessions as $session) {
                $existingGrade = Grade::where('student_id', $session->student_id)
                    ->where('component_id', $component->id)
                    ->where('period_id', $period->id)
                    ->first();

                if ($existingGrade && $existingGrade->locked_at !== null && ! $overwriteLocked) {
                    $skippedLockedCount++;

                    continue;
                }

                Grade::updateOrCreate(
                    [
                        'student_id' => $session->student_id,
                        'component_id' => $component->id,
                        'period_id' => $period->id,
                    ],
                    [
                        'exam_id' => $exam->id,
                        'value' => $session->score,
                        'source' => 'cbt',
                        'entered_by' => $request->user()?->id,
                    ]
                );

                $syncedCount++;
            }
        });

        $this->logAudit(
            $request->user()?->id,
            'exam.publish_gradebook',
            'exams',
            $exam->id,
            null,
            [
                'exam_id' => $exam->id,
                'component_id' => $component->id,
                'period_id' => $period->id,
                'grades_synced' => $syncedCount,
                'skipped_locked' => $skippedLockedCount,
            ],
            $request
        );

        return [
            'exam_id' => $exam->id,
            'component_id' => $component->id,
            'period_id' => $period->id,
            'grades_synced' => $syncedCount,
            'skipped_locked' => $skippedLockedCount,
            'message' => "Nilai ujian CBT berhasil dipublikasikan ke Buku Nilai ({$syncedCount} tersimpan".($skippedLockedCount > 0 ? ", {$skippedLockedCount} dilewati karena terkunci" : '').').',
        ];
    }

    /**
     * Otorisasi pengajar / admin yang berwenang.
     */
    protected function authorizeTeacherOrAdmin(Exam $exam, ?User $user): void
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

        if ($exam->created_by === $user->id) {
            return;
        }

        throw new HttpException(403, 'Anda tidak memiliki hak akses penilaian untuk ujian ini.');
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
