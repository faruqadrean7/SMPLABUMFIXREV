<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\AdminResetPasswordRequest;
use App\Http\Requests\User\ChangeUserStatusRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Services\UserService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class UserController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * ADM-USR-01: List Users (Paginated, Searchable, Filterable)
     * GET /api/v1/users
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $users = $this->userService->listUsers($request);

            return $this->paginatedResponse($users);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar pengguna', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * ADM-USR-01 / ADM-USR-02: Get User Detail
     * GET /api/v1/users/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $user = $this->userService->getUser($id);

            return $this->successResponse($user);
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Pengguna tidak ditemukan');
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil detail pengguna', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * ADM-USR-02: Create User
     * POST /api/v1/users
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        try {
            $result = $this->userService->createUser($request->validated(), $request);

            return $this->successResponse($result, 201);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal membuat akun pengguna baru', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * ADM-USR-02: Update User
     * PUT /api/v1/users/{id}
     */
    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        try {
            $user = $this->userService->updateUser($id, $request->validated(), $request);

            return $this->successResponse($user);
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Pengguna tidak ditemukan');
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui data pengguna', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * ADM-USR-04: Activate or Suspend User Account
     * PATCH /api/v1/users/{id}/status
     */
    public function changeStatus(ChangeUserStatusRequest $request, string $id): JsonResponse
    {
        try {
            $user = $this->userService->changeStatus($id, $request->validated('status'), $request);

            return $this->successResponse($user);
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Pengguna tidak ditemukan');
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengubah status akun pengguna', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * ADM-USR-04: Administrator-Initiated Password Reset
     * POST /api/v1/users/{id}/reset-password
     */
    public function resetPassword(AdminResetPasswordRequest $request, string $id): JsonResponse
    {
        try {
            $result = $this->userService->resetPassword($id, $request->validated(), $request);

            return $this->successResponse($result);
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Pengguna tidak ditemukan');
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal me-reset password akun pengguna', 'INTERNAL_ERROR', 500);
        }
    }
}
