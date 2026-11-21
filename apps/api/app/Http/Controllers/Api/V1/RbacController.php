<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rbac\AssignUserRolesRequest;
use App\Http\Requests\Rbac\StoreRoleRequest;
use App\Http\Requests\Rbac\UpdateRoleRequest;
use App\Services\RbacService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class RbacController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected RbacService $rbacService
    ) {}

    /**
     * BE-AUTH-03: List Master Permissions
     * GET /api/v1/permissions
     */
    public function permissions(Request $request): JsonResponse
    {
        try {
            $module = $request->query('module');
            $permissions = $this->rbacService->listPermissions($module);

            return $this->successResponse($permissions);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar izin', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-AUTH-03: List Roles (Paginated + Search)
     * GET /api/v1/roles
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $roles = $this->rbacService->listRoles($request);

            return $this->paginatedResponse($roles);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal mengambil daftar role', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-AUTH-03: Create Custom Role
     * POST /api/v1/roles
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        try {
            $role = $this->rbacService->createRole($request->validated(), $request);

            return $this->successResponse($role, 201);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal membuat role baru', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-AUTH-03: Show Role Detail
     * GET /api/v1/roles/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $role = $this->rbacService->getRole($id);

            return $this->successResponse($role);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Role tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            return $this->errorResponse('Role tidak ditemukan', 'NOT_FOUND', 404);
        }
    }

    /**
     * BE-AUTH-03: Update Role & Permissions
     * PUT /api/v1/roles/{id}
     */
    public function update(UpdateRoleRequest $request, string $id): JsonResponse
    {
        try {
            $role = $this->rbacService->updateRole($id, $request->validated(), $request);

            return $this->successResponse($role);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Role tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui role', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-AUTH-03: Delete Custom Role (Reject System Role)
     * DELETE /api/v1/roles/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $this->rbacService->deleteRole($id, $request);

            return $this->successResponse(['message' => 'Role berhasil dihapus']);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Role tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal menghapus role', 'INTERNAL_ERROR', 500);
        }
    }

    /**
     * BE-AUTH-03: Get User Roles
     * GET /api/v1/users/{id}/roles
     */
    public function getUserRoles(string $id): JsonResponse
    {
        try {
            $data = $this->rbacService->getUserRoles($id);

            return $this->successResponse($data);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Pengguna tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            return $this->errorResponse('Pengguna tidak ditemukan', 'NOT_FOUND', 404);
        }
    }

    /**
     * BE-AUTH-03: Assign Multiple Roles to User
     * PUT /api/v1/users/{id}/roles
     */
    public function assignUserRoles(AssignUserRolesRequest $request, string $id): JsonResponse
    {
        try {
            $user = $this->rbacService->assignUserRoles(
                $id,
                $request->validated('roles'),
                $request
            );

            return $this->successResponse([
                'message' => 'Peran pengguna berhasil diperbarui',
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'roles' => $user->roles->pluck('code')->all(),
                ],
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Pengguna tidak ditemukan', 'NOT_FOUND', 404);
        } catch (HttpException $e) {
            return $this->handleHttpException($e);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Gagal memperbarui peran pengguna', 'INTERNAL_ERROR', 500);
        }
    }
}
