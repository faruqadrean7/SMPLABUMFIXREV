<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\ExamQuestion;
use App\Models\ExamRoom;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Room;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TeacherExamController extends Controller
{
    use ApiResponse;
    use TeacherPortalContextTrait;

    /**
     * GET /api/v1/me/teacher/exams
     */
    public function exams(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $exams = Exam::with(['subject', 'rooms'])
            ->where('created_by', $request->user()->id)
            ->latest()
            ->get();

        $data = $exams->map(fn (Exam $e) => [
            'id' => $e->id,
            'title' => $e->title,
            'subjectName' => $e->subject?->name ?? 'Mata Pelajaran',
            'classNames' => [],
            'durationMinutes' => $e->duration_min ?? 60,
            'totalQuestions' => 30,
            'startTime' => $e->start_at?->toISOString() ?? now()->toISOString(),
            'endTime' => $e->end_at?->toISOString() ?? now()->addHours(2)->toISOString(),
            'isProctor' => false,
            'status' => $e->status ?? 'scheduled',
        ]);

        return $this->successResponse($data);
    }

    /**
     * GET /api/v1/me/teacher/question-banks
     */
    public function questionBanks(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $taughtSubjectIds = TeachingAssignment::where('teacher_id', $teacher->id)->pluck('subject_id');

        $banks = QuestionBank::with(['subject', 'gradeLevel'])
            ->withCount('questions')
            ->where(function ($q) use ($request, $taughtSubjectIds) {
                $q->where('owner_id', $request->user()->id)
                    ->orWhereIn('subject_id', $taughtSubjectIds);
            })
            ->latest()
            ->get();

        return $this->successResponse($banks);
    }

    /**
     * POST /api/v1/me/teacher/question-banks
     */
    public function storeQuestionBank(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject_id' => 'required|uuid',
            'grade_level_id' => 'nullable|uuid',
        ]);

        if (! $this->verifySubjectAccess($teacher, $validated['subject_id'])) {
            return $this->errorResponse('Akses ditolak: Anda tidak ditugaskan mengampu mata pelajaran ini.', 'FORBIDDEN', 403);
        }

        $bank = QuestionBank::create([
            'school_id' => $request->user()->school_id ?? '00000000-0000-0000-0000-000000000001',
            'subject_id' => $validated['subject_id'],
            'grade_level_id' => $validated['grade_level_id'] ?? null,
            'owner_id' => $request->user()->id,
            'name' => $validated['name'],
        ]);

        $bank->load(['subject', 'gradeLevel']);

        return $this->successResponse($bank, 201);
    }

    /**
     * GET /api/v1/me/teacher/question-banks/{id}
     */
    public function showQuestionBank(Request $request, string $id): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $bank = QuestionBank::with(['subject', 'gradeLevel', 'questions.options'])->find($id);
        if (! $bank) {
            return $this->errorResponse('Bank soal tidak ditemukan.', 'NOT_FOUND', 404);
        }

        $taughtSubjectIds = TeachingAssignment::where('teacher_id', $teacher->id)->pluck('subject_id');
        if ($bank->owner_id !== $request->user()->id && ! $taughtSubjectIds->contains($bank->subject_id)) {
            return $this->errorResponse('Akses ditolak: Anda tidak memiliki wewenang pada bank soal ini.', 'FORBIDDEN', 403);
        }

        return $this->successResponse($bank);
    }

    /**
     * PUT /api/v1/me/teacher/question-banks/{id}
     */
    public function updateQuestionBank(Request $request, string $id): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $bank = QuestionBank::find($id);
        if (! $bank) {
            return $this->errorResponse('Bank soal tidak ditemukan.', 'NOT_FOUND', 404);
        }

        if ($bank->owner_id !== $request->user()->id) {
            return $this->errorResponse('Akses ditolak: Anda bukan pemilik bank soal ini.', 'FORBIDDEN', 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'grade_level_id' => 'nullable|uuid',
        ]);

        $bank->update($validated);

        return $this->successResponse($bank);
    }

    /**
     * DELETE /api/v1/me/teacher/question-banks/{id}
     */
    public function destroyQuestionBank(Request $request, string $id): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $bank = QuestionBank::find($id);
        if (! $bank) {
            return $this->errorResponse('Bank soal tidak ditemukan.', 'NOT_FOUND', 404);
        }

        if ($bank->owner_id !== $request->user()->id) {
            return $this->errorResponse('Akses ditolak: Anda bukan pemilik bank soal ini.', 'FORBIDDEN', 403);
        }

        $questionIds = $bank->questions()->pluck('id');
        $isUsedInActiveExam = ExamQuestion::whereIn('question_id', $questionIds)
            ->whereHas('exam', function ($q) {
                $q->whereIn('status', [Exam::STATUS_SCHEDULED, Exam::STATUS_IN_PROGRESS, Exam::STATUS_FINISHED, 'dijadwalkan', 'berlangsung', 'selesai']);
            })
            ->exists();

        if ($isUsedInActiveExam) {
            return $this->errorResponse('Bank soal tidak dapat dihapus karena butir soal telah digunakan dalam ujian.', 'BUSINESS_RULE_VIOLATION', 422);
        }

        DB::transaction(function () use ($bank, $questionIds) {
            QuestionOption::whereIn('question_id', $questionIds)->delete();
            $bank->questions()->delete();
            $bank->delete();
        });

        return $this->successResponse(['message' => 'Bank soal berhasil dihapus.']);
    }

    /**
     * POST /api/v1/me/teacher/question-banks/{id}/questions
     */
    public function storeQuestion(Request $request, string $id): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $bank = QuestionBank::find($id);
        if (! $bank) {
            return $this->errorResponse('Bank soal tidak ditemukan.', 'NOT_FOUND', 404);
        }

        $taughtSubjectIds = TeachingAssignment::where('teacher_id', $teacher->id)->pluck('subject_id');
        if ($bank->owner_id !== $request->user()->id && ! $taughtSubjectIds->contains($bank->subject_id)) {
            return $this->errorResponse('Akses ditolak: Anda tidak memiliki wewenang pada bank soal ini.', 'FORBIDDEN', 403);
        }

        $validated = $request->validate([
            'question_type' => 'required|in:pg,pg_kompleks,bs,menjodohkan,isian,uraian',
            'body' => 'required|array',
            'cognitive_level' => 'nullable|string|max:10',
            'difficulty' => 'nullable|integer|min:1|max:5',
            'weight' => 'nullable|numeric|min:0',
            'explanation' => 'nullable|string',
            'options' => 'nullable|array',
        ]);

        $question = DB::transaction(function () use ($bank, $validated) {
            $q = Question::create([
                'bank_id' => $bank->id,
                'question_type' => $validated['question_type'],
                'body' => $validated['body'],
                'cognitive_level' => $validated['cognitive_level'] ?? 'C2',
                'difficulty' => $validated['difficulty'] ?? 2,
                'weight' => $validated['weight'] ?? 1.0,
                'explanation' => $validated['explanation'] ?? null,
                'version' => 1,
                'status' => 'draft',
            ]);

            if (! empty($validated['options']) && is_array($validated['options'])) {
                foreach ($validated['options'] as $opt) {
                    QuestionOption::create([
                        'question_id' => $q->id,
                        'label' => $opt['label'] ?? null,
                        'body' => $opt['body'] ?? null,
                        'is_correct' => $opt['is_correct'] ?? false,
                        'match_key' => $opt['match_key'] ?? null,
                    ]);
                }
            }

            return $q->load('options');
        });

        return $this->successResponse($question, 201);
    }

    /**
     * GET /api/v1/me/teacher/questions/{id}
     */
    public function showQuestion(Request $request, string $id): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $question = Question::with(['bank', 'options'])->find($id);
        if (! $question || ! $question->bank) {
            return $this->errorResponse('Soal tidak ditemukan.', 'NOT_FOUND', 404);
        }

        $taughtSubjectIds = TeachingAssignment::where('teacher_id', $teacher->id)->pluck('subject_id');
        if ($question->bank->owner_id !== $request->user()->id && ! $taughtSubjectIds->contains($question->bank->subject_id)) {
            return $this->errorResponse('Akses ditolak: Anda tidak memiliki wewenang untuk melihat soal ini.', 'FORBIDDEN', 403);
        }

        return $this->successResponse($question);
    }

    /**
     * PUT /api/v1/me/teacher/questions/{id}
     */
    public function updateQuestion(Request $request, string $id): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $question = Question::with('bank')->find($id);
        if (! $question || ! $question->bank) {
            return $this->errorResponse('Soal tidak ditemukan.', 'NOT_FOUND', 404);
        }

        if ($question->bank->owner_id !== $request->user()->id) {
            return $this->errorResponse('Akses ditolak: Anda bukan pemilik bank soal ini.', 'FORBIDDEN', 403);
        }

        $isUsedInActiveExam = ExamQuestion::where('question_id', $question->id)
            ->whereHas('exam', function ($q) {
                $q->whereIn('status', [Exam::STATUS_SCHEDULED, Exam::STATUS_IN_PROGRESS, Exam::STATUS_FINISHED, 'dijadwalkan', 'berlangsung', 'selesai']);
            })
            ->exists();

        if ($isUsedInActiveExam) {
            return $this->errorResponse('Soal tidak dapat diubah karena sedang atau telah digunakan dalam sesi ujian.', 'BUSINESS_RULE_VIOLATION', 422);
        }

        $validated = $request->validate([
            'body' => 'nullable|array',
            'cognitive_level' => 'nullable|string|max:10',
            'difficulty' => 'nullable|integer|min:1|max:5',
            'weight' => 'nullable|numeric|min:0',
            'explanation' => 'nullable|string',
            'options' => 'nullable|array',
        ]);

        $question = DB::transaction(function () use ($question, $validated) {
            $question->update(array_filter([
                'body' => $validated['body'] ?? null,
                'cognitive_level' => $validated['cognitive_level'] ?? null,
                'difficulty' => $validated['difficulty'] ?? null,
                'weight' => $validated['weight'] ?? null,
                'explanation' => $validated['explanation'] ?? null,
            ]));

            if (isset($validated['options']) && is_array($validated['options'])) {
                $question->options()->delete();
                foreach ($validated['options'] as $opt) {
                    QuestionOption::create([
                        'question_id' => $question->id,
                        'label' => $opt['label'] ?? null,
                        'body' => $opt['body'] ?? null,
                        'is_correct' => $opt['is_correct'] ?? false,
                        'match_key' => $opt['match_key'] ?? null,
                    ]);
                }
            }

            return $question->load('options');
        });

        return $this->successResponse($question);
    }

    /**
     * DELETE /api/v1/me/teacher/questions/{id}
     */
    public function destroyQuestion(Request $request, string $id): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $question = Question::with('bank')->find($id);
        if (! $question || ! $question->bank) {
            return $this->errorResponse('Soal tidak ditemukan.', 'NOT_FOUND', 404);
        }

        if ($question->bank->owner_id !== $request->user()->id) {
            return $this->errorResponse('Akses ditolak: Anda bukan pemilik bank soal ini.', 'FORBIDDEN', 403);
        }

        $isUsedInActiveExam = ExamQuestion::where('question_id', $question->id)
            ->whereHas('exam', function ($q) {
                $q->whereIn('status', [Exam::STATUS_SCHEDULED, Exam::STATUS_IN_PROGRESS, Exam::STATUS_FINISHED, 'dijadwalkan', 'berlangsung', 'selesai']);
            })
            ->exists();

        if ($isUsedInActiveExam) {
            return $this->errorResponse('Soal tidak dapat diubah karena sedang atau telah digunakan dalam sesi ujian.', 'BUSINESS_RULE_VIOLATION', 422);
        }

        DB::transaction(function () use ($question) {
            $question->options()->delete();
            $question->delete();
        });

        return $this->successResponse(['message' => 'Soal berhasil dihapus.']);
    }

    /**
     * POST /api/v1/me/teacher/exams
     */
    public function storeExam(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subject_id' => 'required|uuid',
            'class_ids' => 'required|array|min:1',
            'class_ids.*' => 'required|uuid',
            'bank_id' => 'required|uuid',
            'duration_min' => 'required|integer|min:5|max:300',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'passing_grade' => 'nullable|numeric|min:0|max:100',
            'exam_type' => 'nullable|string|in:formatif,sumatif,sts,sas,kuis',
        ]);

        if (! $this->verifySubjectAccess($teacher, $validated['subject_id'])) {
            return $this->errorResponse('Akses ditolak: Anda tidak ditugaskan mengampu mata pelajaran ini.', 'FORBIDDEN', 403);
        }

        foreach ($validated['class_ids'] as $classId) {
            if (! $this->verifyClassAccess($teacher, $classId)) {
                return $this->errorResponse("Akses ditolak: Anda tidak ditugaskan di kelas {$classId}.", 'FORBIDDEN', 403);
            }
        }

        $bank = QuestionBank::find($validated['bank_id']);
        if (! $bank) {
            return $this->errorResponse('Bank soal tidak ditemukan.', 'NOT_FOUND', 404);
        }

        $exam = DB::transaction(function () use ($validated, $bank, $request) {
            $exam = Exam::create([
                'school_id' => $request->user()->school_id ?? '00000000-0000-0000-0000-000000000001',
                'subject_id' => $validated['subject_id'],
                'created_by' => $request->user()->id,
                'title' => $validated['title'],
                'exam_type' => $validated['exam_type'] ?? 'formatif',
                'duration_min' => $validated['duration_min'],
                'start_at' => $validated['start_at'],
                'end_at' => $validated['end_at'],
                'passing_grade' => $validated['passing_grade'] ?? 75.0,
                'status' => 'dijadwalkan',
                'shuffle_questions' => true,
                'shuffle_options' => true,
            ]);

            // Assign questions from bank
            $questions = $bank->questions()->get();
            foreach ($questions as $idx => $q) {
                ExamQuestion::create([
                    'exam_id' => $exam->id,
                    'question_id' => $q->id,
                    'sort_order' => $idx + 1,
                    'weight_override' => $q->weight ?? 1.0,
                ]);
            }

            // Create initial exam room
            $room = Room::first();
            $examRoom = ExamRoom::create([
                'exam_id' => $exam->id,
                'room_id' => $room?->id,
                'proctor_id' => $request->user()->id,
                'session_no' => 1,
                'token' => strtoupper(Str::random(6)),
                'token_expires_at' => $exam->end_at,
            ]);

            // Assign participants from the selected classes
            $students = Student::whereHas('classes', function ($q) use ($validated) {
                $q->whereIn('classes.id', $validated['class_ids']);
            })->get();

            foreach ($students as $stu) {
                ExamParticipant::firstOrCreate([
                    'exam_id' => $exam->id,
                    'student_id' => $stu->id,
                    'exam_room_id' => $examRoom->id,
                ]);
            }

            return $exam;
        });

        $exam->load(['subject', 'questions.question']);

        return $this->successResponse($exam, 201);
    }

    /**
     * GET /api/v1/me/teacher/questions
     */
    public function questions(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $taughtSubjectIds = TeachingAssignment::where('teacher_id', $teacher->id)->pluck('subject_id');

        $query = Question::with(['bank.subject', 'options'])
            ->whereHas('bank', function ($b) use ($request, $taughtSubjectIds) {
                $b->where('owner_id', $request->user()->id)
                    ->orWhereIn('subject_id', $taughtSubjectIds);
            });

        if ($request->filled('subject_id')) {
            $subjectId = $request->query('subject_id');
            if (! Str::isUuid($subjectId)) {
                return $this->errorResponse('Format ID mata pelajaran tidak valid.', 'VALIDATION_ERROR', 422);
            }

            // Verify teacher has access to this subject
            $hasAccess = $taughtSubjectIds->contains($subjectId) ||
                QuestionBank::where('subject_id', $subjectId)->where('owner_id', $request->user()->id)->exists();

            if (! $hasAccess) {
                return $this->errorResponse('Akses ditolak: Anda tidak memiliki wewenang untuk mata pelajaran ini.', 'FORBIDDEN', 403);
            }

            $query->whereHas('bank', fn ($b) => $b->where('subject_id', $subjectId));
        }

        if ($request->filled('cognitive_level')) {
            $query->where('cognitive_level', $request->query('cognitive_level'));
        }

        if ($request->filled('type') || $request->filled('question_type')) {
            $rawType = $request->query('type') ?? $request->query('question_type');
            $type = match ($rawType) {
                'benar_salah' => 'bs',
                default => $rawType,
            };
            $query->where('question_type', $type);
        }

        if ($request->filled('difficulty')) {
            $diff = $request->query('difficulty');
            $diffVal = match ($diff) {
                'mudah' => 1,
                'sedang' => 2,
                'sulit' => 3,
                default => is_numeric($diff) ? (int) $diff : null,
            };
            if ($diffVal !== null) {
                $query->where('difficulty', $diffVal);
            }
        }

        if ($request->filled('q') || $request->filled('search')) {
            $search = trim($request->query('q') ?? $request->query('search'));
            $query->where('body', 'like', "%{$search}%");
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $paginator = $query->latest()->paginate($perPage);

        return $this->paginatedResponse($paginator);
    }

    /**
     * POST /api/v1/me/teacher/questions
     */
    public function storeDirectQuestion(Request $request): JsonResponse
    {
        $teacher = $this->getTeacher($request);
        if (! $teacher) {
            return $this->errorResponse('Akses ditolak: Profil guru tidak ditemukan.', 'FORBIDDEN', 403);
        }

        $validated = $request->validate([
            'subject_id' => 'nullable|uuid|exists:subjects,id',
            'bank_id' => 'nullable|uuid|exists:question_banks,id',
            'question_type' => 'required|in:pg,pg_kompleks,bs,benar_salah,menjodohkan,isian,uraian',
            'body' => 'nullable',
            'question_text' => 'nullable|string',
            'cognitive_level' => 'nullable|string|max:10',
            'difficulty' => 'nullable',
            'weight' => 'nullable|numeric|min:0',
            'points' => 'nullable|numeric|min:0',
            'explanation' => 'nullable|string',
            'options' => 'nullable|array',
        ]);

        $bank = null;
        if (! empty($validated['bank_id'])) {
            $bank = QuestionBank::find($validated['bank_id']);
            if (! $bank) {
                return $this->errorResponse('Bank soal tidak ditemukan.', 'NOT_FOUND', 404);
            }

            $taughtSubjectIds = TeachingAssignment::where('teacher_id', $teacher->id)->pluck('subject_id');
            if ($bank->owner_id !== $request->user()->id && ! $taughtSubjectIds->contains($bank->subject_id)) {
                return $this->errorResponse('Akses ditolak: Anda tidak memiliki wewenang pada bank soal ini.', 'FORBIDDEN', 403);
            }
        } else {
            $subjectId = $validated['subject_id'] ?? null;
            if (! $subjectId) {
                return $this->errorResponse('Wajib menyertakan bank_id atau subject_id.', 'VALIDATION_ERROR', 422);
            }

            if (! $this->verifySubjectAccess($teacher, $subjectId)) {
                return $this->errorResponse('Akses ditolak: Anda tidak ditugaskan mengampu mata pelajaran ini.', 'FORBIDDEN', 403);
            }

            $bank = QuestionBank::firstOrCreate(
                [
                    'owner_id' => $request->user()->id,
                    'subject_id' => $subjectId,
                ],
                [
                    'name' => 'Bank Soal Mandiri',
                    'school_id' => $request->user()->school_id ?? '00000000-0000-0000-0000-000000000001',
                ]
            );
        }

        // Normalize body
        $body = $validated['body'] ?? null;
        if (is_string($body)) {
            $body = ['text' => $body];
        } elseif (! is_array($body) && ! empty($validated['question_text'])) {
            $body = ['text' => $validated['question_text']];
        } elseif (! is_array($body)) {
            $body = ['text' => 'Soal'];
        }

        $rawType = $validated['question_type'];
        $questionType = match ($rawType) {
            'benar_salah' => 'bs',
            default => $rawType,
        };

        $rawDiff = $validated['difficulty'] ?? 2;
        $difficulty = match ($rawDiff) {
            'mudah' => 1,
            'sedang' => 2,
            'sulit' => 3,
            default => is_numeric($rawDiff) ? (int) $rawDiff : 2,
        };

        $weight = $validated['weight'] ?? ($validated['points'] ?? 1.0);

        $question = DB::transaction(function () use ($bank, $questionType, $body, $validated, $difficulty, $weight) {
            $q = Question::create([
                'bank_id' => $bank->id,
                'question_type' => $questionType,
                'body' => $body,
                'cognitive_level' => $validated['cognitive_level'] ?? 'MOTS',
                'difficulty' => $difficulty,
                'weight' => $weight,
                'explanation' => $validated['explanation'] ?? null,
                'version' => 1,
                'status' => 'draft',
            ]);

            if (! empty($validated['options']) && is_array($validated['options'])) {
                foreach ($validated['options'] as $opt) {
                    $optBody = $opt['body'] ?? ($opt['text'] ?? null);
                    if (is_string($optBody)) {
                        $optBody = ['text' => $optBody];
                    }
                    QuestionOption::create([
                        'question_id' => $q->id,
                        'label' => $opt['label'] ?? null,
                        'body' => $optBody,
                        'is_correct' => $opt['is_correct'] ?? false,
                        'match_key' => $opt['match_key'] ?? null,
                    ]);
                }
            }

            return $q->load('options');
        });

        return $this->successResponse($question, 201);
    }
}
