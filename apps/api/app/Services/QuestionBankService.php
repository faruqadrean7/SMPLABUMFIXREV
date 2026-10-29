<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ExamQuestion;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class QuestionBankService
{
    /**
     * BE-CBT-01: Daftar paket bank soal.
     */
    public function listQuestionBanks(array $filters, Request $request): LengthAwarePaginator
    {
        $query = QuestionBank::with(['subject', 'gradeLevel', 'owner'])
            ->withCount('questions')
            ->orderBy('created_at', 'desc');

        if (! empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (! empty($filters['grade_level_id'])) {
            $query->where('grade_level_id', $filters['grade_level_id']);
        }

        if (! empty($filters['search'])) {
            $keyword = '%'.strtolower($filters['search']).'%';
            $query->whereRaw('LOWER(name) LIKE ?', [$keyword]);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * BE-CBT-01: Simpan paket bank soal baru.
     */
    public function createQuestionBank(array $data, Request $request): QuestionBank
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        $bank = QuestionBank::create([
            'name' => $data['name'],
            'subject_id' => $data['subject_id'],
            'grade_level_id' => $data['grade_level_id'] ?? null,
            'owner_id' => $currentUser->id,
            'school_id' => $currentUser->school_id,
        ]);

        $this->logAudit(
            $currentUser->id,
            'question_bank.create',
            'question_banks',
            $bank->id,
            null,
            $data,
            $request
        );

        return $bank->load(['subject', 'gradeLevel', 'owner']);
    }

    /**
     * BE-CBT-01: Detail paket bank soal.
     */
    public function getQuestionBank(string $id): QuestionBank
    {
        return QuestionBank::with(['subject', 'gradeLevel', 'owner'])
            ->withCount('questions')
            ->findOrFail($id);
    }

    /**
     * BE-CBT-01: Perbarui paket bank soal.
     */
    public function updateQuestionBank(string $id, array $data, Request $request): QuestionBank
    {
        $bank = QuestionBank::findOrFail($id);
        $oldData = $bank->only(['name', 'subject_id', 'grade_level_id']);

        $bank->update([
            'name' => $data['name'] ?? $bank->name,
            'subject_id' => $data['subject_id'] ?? $bank->subject_id,
            'grade_level_id' => array_key_exists('grade_level_id', $data) ? $data['grade_level_id'] : $bank->grade_level_id,
        ]);

        $this->logAudit(
            $request->user()?->id,
            'question_bank.update',
            'question_banks',
            $bank->id,
            $oldData,
            $data,
            $request
        );

        return $bank->fresh(['subject', 'gradeLevel', 'owner']);
    }

    /**
     * BE-CBT-01: Hapus paket bank soal.
     */
    public function deleteQuestionBank(string $id, Request $request): void
    {
        $bank = QuestionBank::findOrFail($id);

        $hasUsedInExam = ExamQuestion::whereHas('question', fn ($q) => $q->where('bank_id', $id))->exists();
        if ($hasUsedInExam) {
            throw new HttpException(422, 'Bank soal tidak dapat dihapus karena memuat butir soal yang sudah digunakan dalam pelaksanaan ujian.');
        }

        $bank->delete();

        $this->logAudit(
            $request->user()?->id,
            'question_bank.delete',
            'question_banks',
            $id,
            ['name' => $bank->name],
            null,
            $request
        );
    }

    /**
     * BE-CBT-01: Daftar butir soal dengan filter komprehensif.
     */
    public function listQuestions(array $filters): LengthAwarePaginator
    {
        $query = Question::with(['bank.subject', 'options'])
            ->orderBy('created_at', 'desc');

        if (! empty($filters['bank_id'])) {
            $query->where('bank_id', $filters['bank_id']);
        }

        if (! empty($filters['subject_id'])) {
            $query->whereHas('bank', fn ($b) => $b->where('subject_id', $filters['subject_id']));
        }

        if (! empty($filters['grade_level_id'])) {
            $query->whereHas('bank', fn ($b) => $b->where('grade_level_id', $filters['grade_level_id']));
        }

        if (! empty($filters['question_type'])) {
            $query->where('question_type', $filters['question_type']);
        }

        if (! empty($filters['cognitive_level'])) {
            $query->where('cognitive_level', $filters['cognitive_level']);
        }

        if (! empty($filters['difficulty'])) {
            $query->where('difficulty', $filters['difficulty']);
        }

        if (! empty($filters['search'])) {
            $keyword = '%'.strtolower($filters['search']).'%';
            $isPgsql = DB::getDriverName() === 'pgsql';
            $jsonContentExpr = $isPgsql ? "LOWER(body->>'content')" : "LOWER(json_extract(body, '$.content'))";

            $query->where(function ($q) use ($keyword, $jsonContentExpr) {
                $q->whereRaw('LOWER(cp_code) LIKE ?', [$keyword])
                    ->orWhereRaw("{$jsonContentExpr} LIKE ?", [$keyword]);
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * BE-CBT-01: Simpan butir soal baru beserta pilihan jawaban.
     */
    public function createQuestion(array $data, Request $request): Question
    {
        return DB::transaction(function () use ($data, $request) {
            $question = Question::create([
                'bank_id' => $data['bank_id'],
                'question_type' => $data['question_type'],
                'body' => $data['body'],
                'cp_code' => $data['cp_code'] ?? null,
                'cognitive_level' => $data['cognitive_level'] ?? 'MOTS',
                'difficulty' => $data['difficulty'] ?? 3,
                'weight' => $data['weight'] ?? 1.0,
                'explanation' => $data['explanation'] ?? null,
                'version' => 1,
                'status' => $data['status'] ?? 'draft',
            ]);

            if (! empty($data['options']) && is_array($data['options'])) {
                foreach ($data['options'] as $opt) {
                    QuestionOption::create([
                        'question_id' => $question->id,
                        'label' => $opt['label'],
                        'body' => $opt['body'],
                        'is_correct' => (bool) ($opt['is_correct'] ?? false),
                        'match_key' => $opt['match_key'] ?? null,
                    ]);
                }
            }

            $this->logAudit(
                $request->user()?->id,
                'question.create',
                'questions',
                $question->id,
                null,
                ['bank_id' => $question->bank_id, 'type' => $question->question_type],
                $request
            );

            return $question->load(['bank.subject', 'options']);
        });
    }

    /**
     * BE-CBT-01: Ambil detail butir soal beserta opsi jawaban.
     */
    public function getQuestion(string $id): Question
    {
        return Question::with(['bank.subject', 'bank.gradeLevel', 'options'])
            ->findOrFail($id);
    }

    /**
     * BE-CBT-01: Perbarui butir soal.
     * Aturan PRD: Soal yang sudah dipakai ujian terkunci edit -> otomatis buat versi baru.
     */
    public function updateQuestion(string $id, array $data, Request $request): Question
    {
        $existing = Question::with('options')->findOrFail($id);
        $isUsedInExam = ExamQuestion::where('question_id', $id)->exists();

        return DB::transaction(function () use ($existing, $isUsedInExam, $data, $request) {
            if ($isUsedInExam) {
                // Buat versi baru untuk menjaga integritas data ujian yang sedang berjalan/arsip
                $newQuestion = Question::create([
                    'bank_id' => $data['bank_id'] ?? $existing->bank_id,
                    'question_type' => $data['question_type'] ?? $existing->question_type,
                    'body' => $data['body'] ?? $existing->body,
                    'cp_code' => array_key_exists('cp_code', $data) ? $data['cp_code'] : $existing->cp_code,
                    'cognitive_level' => $data['cognitive_level'] ?? $existing->cognitive_level,
                    'difficulty' => $data['difficulty'] ?? $existing->difficulty,
                    'weight' => $data['weight'] ?? $existing->weight,
                    'explanation' => array_key_exists('explanation', $data) ? $data['explanation'] : $existing->explanation,
                    'version' => $existing->version + 1,
                    'status' => $data['status'] ?? $existing->status,
                ]);

                $optionsToSave = $data['options'] ?? $existing->options->toArray();
                foreach ($optionsToSave as $opt) {
                    QuestionOption::create([
                        'question_id' => $newQuestion->id,
                        'label' => $opt['label'],
                        'body' => $opt['body'],
                        'is_correct' => (bool) ($opt['is_correct'] ?? false),
                        'match_key' => $opt['match_key'] ?? null,
                    ]);
                }

                $this->logAudit(
                    $request->user()?->id,
                    'question.version_increment',
                    'questions',
                    $newQuestion->id,
                    ['parent_id' => $existing->id, 'old_version' => $existing->version],
                    ['new_version' => $newQuestion->version],
                    $request
                );

                return $newQuestion->load(['bank.subject', 'options']);
            }

            // Jika belum pernah dipakai ujian, lakukan update langsung
            $existing->update([
                'bank_id' => $data['bank_id'] ?? $existing->bank_id,
                'question_type' => $data['question_type'] ?? $existing->question_type,
                'body' => $data['body'] ?? $existing->body,
                'cp_code' => array_key_exists('cp_code', $data) ? $data['cp_code'] : $existing->cp_code,
                'cognitive_level' => $data['cognitive_level'] ?? $existing->cognitive_level,
                'difficulty' => $data['difficulty'] ?? $existing->difficulty,
                'weight' => $data['weight'] ?? $existing->weight,
                'explanation' => array_key_exists('explanation', $data) ? $data['explanation'] : $existing->explanation,
                'status' => $data['status'] ?? $existing->status,
            ]);

            if (isset($data['options']) && is_array($data['options'])) {
                $existing->options()->delete();
                foreach ($data['options'] as $opt) {
                    QuestionOption::create([
                        'question_id' => $existing->id,
                        'label' => $opt['label'],
                        'body' => $opt['body'],
                        'is_correct' => (bool) ($opt['is_correct'] ?? false),
                        'match_key' => $opt['match_key'] ?? null,
                    ]);
                }
            }

            $this->logAudit(
                $request->user()?->id,
                'question.update',
                'questions',
                $existing->id,
                null,
                $data,
                $request
            );

            return $existing->fresh(['bank.subject', 'options']);
        });
    }

    /**
     * BE-CBT-01: Hapus butir soal.
     */
    public function deleteQuestion(string $id, Request $request): void
    {
        $question = Question::findOrFail($id);

        if (ExamQuestion::where('question_id', $id)->exists()) {
            throw new HttpException(422, 'Butir soal tidak dapat dihapus karena sudah digunakan dalam ujian. Silakan nonaktifkan atau arsipkan soal.');
        }

        $question->delete();

        $this->logAudit(
            $request->user()?->id,
            'question.delete',
            'questions',
            $id,
            ['bank_id' => $question->bank_id, 'type' => $question->question_type],
            null,
            $request
        );
    }

    /**
     * BE-CBT-01: Import massal butir soal ke dalam bank soal.
     */
    public function importQuestions(array $data, Request $request): array
    {
        $bank = QuestionBank::findOrFail($data['bank_id']);
        $createdList = [];

        DB::transaction(function () use ($data, $bank, &$createdList, $request) {
            foreach ($data['questions'] as $qData) {
                $question = Question::create([
                    'bank_id' => $bank->id,
                    'question_type' => $qData['question_type'],
                    'body' => $qData['body'],
                    'cp_code' => $qData['cp_code'] ?? null,
                    'cognitive_level' => $qData['cognitive_level'] ?? 'MOTS',
                    'difficulty' => $qData['difficulty'] ?? 3,
                    'weight' => $qData['weight'] ?? 1.0,
                    'explanation' => $qData['explanation'] ?? null,
                    'version' => 1,
                    'status' => 'aktif',
                ]);

                if (! empty($qData['options']) && is_array($qData['options'])) {
                    foreach ($qData['options'] as $opt) {
                        QuestionOption::create([
                            'question_id' => $question->id,
                            'label' => $opt['label'],
                            'body' => $opt['body'],
                            'is_correct' => (bool) ($opt['is_correct'] ?? false),
                            'match_key' => $opt['match_key'] ?? null,
                        ]);
                    }
                }

                $createdList[] = $question->load('options');
            }

            $this->logAudit(
                $request->user()?->id,
                'question.import',
                'questions',
                $bank->id,
                null,
                ['count' => count($createdList), 'bank_id' => $bank->id],
                $request
            );
        });

        return [
            'bank_id' => $bank->id,
            'imported_count' => count($createdList),
            'questions' => $createdList,
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
