<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cbt\AssignExamQuestionsRequest;
use App\Http\Requests\Cbt\AssignExamRoomsRequest;
use App\Http\Requests\Cbt\EnrollExamParticipantsRequest;
use App\Http\Requests\Cbt\ListExamRequest;
use App\Http\Requests\Cbt\StoreExamRequest;
use App\Http\Requests\Cbt\UpdateExamRequest;
use App\Models\Exam;
use App\Services\Concerns\GuardsExamOwnership;
use App\Services\ExamService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ExamController extends Controller
{
    use ApiResponse;
    use GuardsExamOwnership;

    public function __construct(
        protected ExamService $service
    ) {}

    /**
     * BE-CBT-02: Daftar ujian CBT.
     * GET /api/v1/exams
     */
    public function index(ListExamRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();
            $user = $request->user();
            $isPrivileged = $user && ($this->isCbtAdmin($user) || $user->hasRole('teacher') || $user->hasPermission('cbt.exam.manage'));
            if (! $isPrivileged) {
                $filters['exclude_draft_archived'] = true;
            }

            $paginator = $this->service->listExams($filters, $user);

            return $this->paginatedResponse($paginator);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat daftar ujian.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-02: Buat ujian CBT baru.
     * POST /api/v1/exams
     */
    public function store(StoreExamRequest $request): JsonResponse
    {
        try {
            $exam = $this->service->createExam($request->validated(), $request);

            return $this->successResponse($exam, 201);
        } catch (Throwable $e) {
            report($e);

            $msg = config('app.debug')
                ? 'Terjadi kesalahan saat membuat ujian: '.$e->getMessage()
                : 'Terjadi kesalahan saat membuat ujian.';

            return $this->errorResponse($msg, 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-02: Detail ujian CBT.
     * GET /api/v1/exams/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $exam = $this->service->getExam($id);

            $user = $request->user();
            $this->assertExamSchoolAccess($exam, $user);

            // P1-11: Hanya admin CBT atau pembuat ujian yang boleh melihat soal/kunci/token/peserta lengkap
            $isPrivileged = $this->canManageExam($exam, $user);

            if (! $isPrivileged) {
                if (in_array($exam->status, ['draft', 'dibatalkan'], true)) {
                    return $this->errorResponse('Ujian CBT belum tersedia.', 'FORBIDDEN', 403);
                }

                // SEC-CBT-03: Cegah kebocoran soal, token ruang, dan PII daftar peserta kelas
                $exam->unsetRelation('questions');
                $exam->unsetRelation('participants');
                if ($exam->relationLoaded('rooms')) {
                    $exam->rooms->each(function ($room) {
                        $room->makeHidden(['token', 'proctor_id']);
                        $room->unsetRelation('proctor');
                    });
                }
            }

            return $this->successResponse($exam);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Ujian CBT tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memuat detail ujian.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-02: Perbarui pengaturan ujian CBT.
     * PUT /api/v1/exams/{id}
     */
    public function update(string $id, UpdateExamRequest $request): JsonResponse
    {
        try {
            $exam = $this->service->updateExam($id, $request->validated(), $request);

            return $this->successResponse($exam);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Ujian CBT tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'BUSINESS_RULE_VIOLATION', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memperbarui ujian.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-02: Hapus ujian CBT (hanya draft).
     * DELETE /api/v1/exams/{id}
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        try {
            $this->service->deleteExam($id, $request);

            return $this->successResponse(['message' => 'Ujian berhasil dihapus.']);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Ujian CBT tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'BUSINESS_RULE_VIOLATION', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat menghapus ujian.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-02: Plotting butir soal ke ujian CBT.
     * POST /api/v1/exams/{id}/questions
     */
    public function assignQuestions(string $id, AssignExamQuestionsRequest $request): JsonResponse
    {
        try {
            $questions = $this->service->assignQuestions($id, $request->validated(), $request);

            return $this->successResponse($questions, 201);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Ujian CBT tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'BUSINESS_RULE_VIOLATION', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat memasukkan butir soal ke ujian.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-02: Lihat daftar butir soal ujian.
     * GET /api/v1/exams/{id}/questions
     */
    public function getQuestions(string $id, Request $request): JsonResponse
    {
        try {
            $exam = Exam::with(['questions.question.options'])->findOrFail($id);
            $this->assertCanManage($exam, $request->user());

            return $this->successResponse($exam->questions);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Ujian CBT tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        }
    }

    /**
     * BE-CBT-02: Plotting ruang ujian dan pengawas.
     * POST /api/v1/exams/{id}/rooms
     */
    public function assignRooms(string $id, AssignExamRoomsRequest $request): JsonResponse
    {
        try {
            $rooms = $this->service->assignRooms($id, $request->validated(), $request);

            return $this->successResponse($rooms, 201);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Ujian CBT tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengalokasikan ruang ujian.', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-02: Lihat daftar ruang ujian.
     * GET /api/v1/exams/{id}/rooms
     */
    public function getRooms(string $id, Request $request): JsonResponse
    {
        try {
            $exam = Exam::with(['rooms.room', 'rooms.proctor'])->findOrFail($id);
            $this->assertCanManage($exam, $request->user());

            return $this->successResponse($exam->rooms);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Ujian CBT tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        }
    }

    /**
     * BE-CBT-02: Plotting peserta ujian (siswa/rombel).
     * POST /api/v1/exams/{id}/participants
     */
    public function enrollParticipants(string $id, EnrollExamParticipantsRequest $request): JsonResponse
    {
        try {
            $participants = $this->service->enrollParticipants($id, $request->validated(), $request);

            return $this->successResponse($participants, 201);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Data ujian atau ruang ujian tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'BUSINESS_RULE_VIOLATION', $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            $msg = config('app.debug')
                ? 'Terjadi kesalahan saat mendaftarkan peserta ujian: '.$e->getMessage()
                : 'Terjadi kesalahan saat mendaftarkan peserta ujian.';

            return $this->errorResponse($msg, 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-CBT-02: Lihat daftar peserta ujian.
     * GET /api/v1/exams/{id}/participants
     */
    public function getParticipants(string $id, Request $request): JsonResponse
    {
        try {
            $exam = Exam::with(['participants.student', 'participants.examRoom.room'])->findOrFail($id);
            $this->assertCanManage($exam, $request->user());

            return $this->successResponse($exam->participants);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Ujian CBT tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->errorResponse($e->getMessage(), 'FORBIDDEN', $e->getStatusCode());
        }
    }

    /**
     * BE-CBT-02: Ubah status lifecycle ujian.
     * PATCH /api/v1/exams/{id}/status
     */
    public function updateStatus(string $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:draft,dijadwalkan,berlangsung,selesai,arsip'],
        ]);

        try {
            $exam = $this->service->updateStatus($id, $validated['status'], $request);

            return $this->successResponse($exam);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Ujian CBT tidak ditemukan.', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            $code = match ($e->getStatusCode()) {
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN',
                422 => 'BUSINESS_RULE_VIOLATION',
                default => 'BAD_REQUEST',
            };

            return $this->errorResponse($e->getMessage(), $code, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Terjadi kesalahan saat mengubah status ujian.', 'INTERNAL_ERROR', 500);
        }
    }
}
