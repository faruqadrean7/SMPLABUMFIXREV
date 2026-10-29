<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RbacService
{
    /**
     * BE-AUTH-03: List all permissions, optionally filtered by module prefix.
     */
    public function listPermissions(?string $module = null): Collection
    {
        $query = Permission::query()->orderBy('code');

        if ($module) {
            $query->where('code', 'like', $module.'.%');
        }

        return $query->get();
    }

    /**
     * BE-AUTH-03: List roles with pagination, search, and counts.
     */
    public function listRoles(Request $request): LengthAwarePaginator
    {
        $limit = min($request->integer('limit', 20), 100);
        $q = trim((string) $request->query('q', ''));

        $query = Role::withCount(['permissions', 'users'])
            ->orderBy('is_system', 'desc')
            ->orderBy('name');

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%");
            });
        }

        return $query->paginate($limit);
    }

    /**
     * BE-AUTH-03: Create a new custom role.
     */
    public function createRole(array $data, Request $request): Role
    {
        $role = Role::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'is_system' => false,
        ]);

        if (! empty($data['permission_ids'])) {
            $role->permissions()->sync($data['permission_ids']);
        }

        $role->load('permissions');

        $this->logAudit(
            $request->user()?->id,
            'role.created',
            'Role',
            $role->id,
            null,
            $role->toArray(),
            $request
        );

        return $role;
    }

    /**
     * BE-AUTH-03: Find role by ID with permissions and user count.
     */
    public function getRole(string $id): Role
    {
        return Role::with('permissions')
            ->withCount('users')
            ->findOrFail($id);
    }

    /**
     * BE-AUTH-03: Update role name and/or permissions.
     */
    public function updateRole(string $id, array $data, Request $request): Role
    {
        $role = Role::findOrFail($id);
        $oldValue = $role->toArray();

        if (isset($data['name'])) {
            $role->name = $data['name'];
            $role->save();
        }

        if (array_key_exists('permission_ids', $data)) {
            $role->permissions()->sync($data['permission_ids'] ?? []);
        }

        $role->load('permissions');

        $this->logAudit(
            $request->user()?->id,
            'role.updated',
            'Role',
            $role->id,
            $oldValue,
            $role->toArray(),
            $request
        );

        return $role;
    }

    /**
     * BE-AUTH-03: Delete a custom role (system roles are protected from deletion).
     */
    public function deleteRole(string $id, Request $request): void
    {
        $role = Role::findOrFail($id);

        if ($role->is_system) {
            throw new HttpException(
                400,
                'Role bawaan sistem tidak dapat dihapus demi menjaga integritas aplikasi.',
                null,
                [],
                400
            );
        }

        if ($role->users()->exists()) {
            throw new HttpException(
                409,
                'Role tidak dapat dihapus karena masih digunakan oleh satu atau lebih pengguna.',
                null,
                [],
                409
            );
        }

        $oldValue = $role->toArray();
        $role->permissions()->detach();
        $role->delete();

        $this->logAudit(
            $request->user()?->id,
            'role.deleted',
            'Role',
            $role->id,
            $oldValue,
            null,
            $request
        );
    }

    /**
     * BE-AUTH-03: Get assigned roles for a user.
     */
    public function getUserRoles(string $userId): array
    {
        $user = User::with(['roles.permissions'])->findOrFail($userId);

        return [
            'userId' => $user->id,
            'username' => $user->username,
            'roles' => $user->roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'code' => $role->code,
                    'name' => $role->name,
                    'isSystem' => $role->is_system,
                    'scopeClassId' => $role->pivot->scope_class_id,
                ];
            }),
        ];
    }

    /**
     * BE-AUTH-03: Assign multiple roles to a user (with optional scope).
     */
    public function assignUserRoles(string $userId, array $rolesData, Request $request): User
    {
        return DB::transaction(function () use ($userId, $rolesData, $request) {
            $user = User::findOrFail($userId);
            $oldRoles = $user->roles()->pluck('code')->all();

            $syncData = [];
            foreach ($rolesData as $item) {
                $syncData[$item['role_id']] = [
                    'scope_class_id' => $item['scope_class_id'] ?? null,
                ];
            }

            $user->roles()->sync($syncData);
            $user->load(['roles.permissions']);

            $newRoles = $user->roles()->pluck('code')->all();

            app(AuditLogService::class)->record(
                action: 'user.roles_assigned',
                entity: 'User',
                entityId: $user->id,
                oldValues: ['roles' => $oldRoles],
                newValues: ['roles' => $newRoles],
                reason: $request->input('reason'),
                request: $request,
                isAuthoritative: true
            );

            return $user;
        });
    }

    /**
     * Helper to write an audit log entry.
     */
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
