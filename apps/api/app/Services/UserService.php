<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class UserService
{
    /**
     * ADM-USR-01: List users with search, role filter, status filter, and pagination.
     */
    public function listUsers(Request $request): LengthAwarePaginator
    {
        $limit = min($request->integer('limit', 20), 100);
        $status = $request->query('status');
        $role = $request->query('role');
        $q = trim((string) $request->query('q', ''));

        $query = User::with(['roles:id,code,name,is_system'])
            ->select([
                'id',
                'school_id',
                'username',
                'email',
                'phone',
                'status',
                'must_change_password',
                'last_login_at',
                'created_at',
            ]);

        if ($status) {
            $query->where('status', $status);
        }

        if ($role) {
            $query->whereHas('roles', function ($rq) use ($role) {
                $rq->where('code', $role);
            });
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('username', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($limit);
    }

    /**
     * ADM-USR-01 / ADM-USR-02: Get user detail with roles and profile summary.
     */
    public function getUser(string $id): User
    {
        $user = User::with([
            'roles:id,code,name,is_system',
            'student.classes',
            'teacher',
            'guardian.students',
            'staff',
            'applicant',
        ])->findOrFail($id);

        $user->append('profile_summary');

        return $user;
    }

    /**
     * ADM-USR-02: Create new user account with transaction and audit logging.
     */
    public function createUser(array $data, Request $request): array
    {
        return DB::transaction(function () use ($data, $request) {
            $generatedPassword = null;
            if (empty($data['password'])) {
                $generatedPassword = Str::password(12, true, true, true, false);
                $password = $generatedPassword;
            } else {
                $password = $data['password'];
            }

            $user = User::create([
                'username' => strtolower(trim($data['username'])),
                'email' => ! empty($data['email']) ? strtolower(trim($data['email'])) : null,
                'phone' => $data['phone'] ?? null,
                'password_hash' => Hash::make($password),
                'status' => $data['status'] ?? 'active',
                'must_change_password' => $data['must_change_password'] ?? true,
            ]);

            // Assign roles if provided (P0-07: cegah privilege escalation tanpa auth.role.manage / super_admin)
            if (! empty($data['roles'])) {
                $roleIds = $this->resolveAuthorizedRoleIds($data['roles'], $request->user());
                if (! empty($roleIds)) {
                    $user->roles()->sync($roleIds);
                    $user->flushPermissionCache();
                }
            }

            $user->load('roles:id,code,name,is_system');

            $this->logAudit(
                $request->user()?->id,
                'user.created',
                'User',
                $user->id,
                null,
                [
                    'username' => $user->username,
                    'email' => $user->email,
                    'status' => $user->status,
                    'roles' => $user->roles->pluck('code')->all(),
                ],
                $request
            );

            return [
                'user' => $user,
                'temporary_password' => $generatedPassword,
            ];
        });
    }

    /**
     * ADM-USR-02: Update existing user account with mass-assignment protection.
     */
    public function updateUser(string $id, array $data, Request $request): User
    {
        return DB::transaction(function () use ($id, $data, $request) {
            $user = User::with('roles')->findOrFail($id);
            $actor = $request->user();

            // P0-07: Non-super_admin tidak boleh memodifikasi akun super_admin
            if ($user->hasRole('super_admin') && ! ($actor?->hasRole('super_admin') ?? false)) {
                throw new HttpException(403, 'Akses ditolak: Hanya Super Admin yang dapat mengubah akun Super Admin.');
            }

            $oldValues = [
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'roles' => $user->roles->pluck('code')->all(),
            ];

            // Protect against self-deactivation/suspension/locking (P1-03)
            if (isset($data['status']) && $data['status'] !== $user->status) {
                if ($user->id === $request->user()?->id && in_array($data['status'], ['inactive', 'suspended', 'locked'], true)) {
                    throw new HttpException(400, 'Tidak dapat menonaktifkan, menangguhkan, atau mengunci akun Anda sendiri demi menjaga akses administratif.');
                }
                $user->status = $data['status'];

                // Revoke active sessions if deactivated, suspended, or locked
                if (in_array($data['status'], ['inactive', 'suspended', 'locked'], true)) {
                    $this->revokeUserSessions($user->id);
                }
            }

            if (isset($data['username'])) {
                $user->username = strtolower(trim($data['username']));
            }
            if (array_key_exists('email', $data)) {
                $user->email = ! empty($data['email']) ? strtolower(trim($data['email'])) : null;
            }
            if (array_key_exists('phone', $data)) {
                $user->phone = $data['phone'];
            }
            if (isset($data['must_change_password'])) {
                $user->must_change_password = (bool) $data['must_change_password'];
            }

            $user->save();

            // Sync roles if provided (P0-07: wajib auth.role.manage & larang eskalasi super_admin)
            if (isset($data['roles'])) {
                $requestedRoleIds = $this->findRoleIds($data['roles']);
                $currentRoleIds = $user->roles->pluck('id')->all();
                sort($requestedRoleIds);
                sort($currentRoleIds);

                if ($requestedRoleIds !== $currentRoleIds) {
                    $roleIds = $this->resolveAuthorizedRoleIds($data['roles'], $actor, $user);
                    $user->roles()->sync($roleIds);
                    $user->flushPermissionCache();
                }
            }

            $user->load('roles:id,code,name,is_system');

            $newValues = [
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'roles' => $user->roles->pluck('code')->all(),
            ];

            $this->logAudit(
                $request->user()?->id,
                'user.updated',
                'User',
                $user->id,
                $oldValues,
                $newValues,
                $request
            );

            return $user;
        });
    }

    /**
     * ADM-USR-04: Activate or suspend user account with token revocation.
     */
    public function changeStatus(string $id, string $status, Request $request): User
    {
        $user = User::with('roles:id,code,name')->findOrFail($id);

        if ($user->id === $request->user()?->id && in_array($status, ['inactive', 'suspended', 'locked'], true)) {
            throw new HttpException(400, 'Tidak dapat menonaktifkan, menangguhkan, atau mengunci akun Anda sendiri demi menjaga akses administratif.');
        }

        $oldStatus = $user->status;
        $user->status = $status;
        $user->save();

        if (in_array($status, ['inactive', 'suspended', 'locked'], true)) {
            $this->revokeUserSessions($user->id);
        }

        $this->logAudit(
            $request->user()?->id,
            'user.status_changed',
            'User',
            $user->id,
            ['status' => $oldStatus],
            ['status' => $status],
            $request
        );

        return $user;
    }

    /**
     * ADM-USR-04: Administrator-initiated password reset with session revocation.
     */
    public function resetPassword(string $id, array $data, Request $request): array
    {
        $user = User::findOrFail($id);

        $generatedPassword = null;
        if (! empty($data['new_password'])) {
            $password = $data['new_password'];
        } else {
            $generatedPassword = Str::password(12, true, true, true, false);
            $password = $generatedPassword;
        }

        $user->password_hash = Hash::make($password);
        $user->must_change_password = $data['must_change_password'] ?? true;
        $user->save();

        // Revoke all existing sessions and refresh tokens immediately
        $this->revokeUserSessions($user->id);

        // Security: Never log the password plaintext or hash in audit trails
        $this->logAudit(
            $request->user()?->id,
            'user.password_reset',
            'User',
            $user->id,
            null,
            ['must_change_password' => $user->must_change_password],
            $request
        );

        return [
            'message' => 'Password akun berhasil di-reset. Seluruh sesi aktif telah dicabut.',
            'temporary_password' => $generatedPassword,
            'must_change_password' => $user->must_change_password,
        ];
    }

    /**
     * Resolusi daftar ID role dari input kode role atau UUID.
     *
     * @param  array<int, mixed>  $rolesInput
     * @return string[]
     */
    protected function findRoleIds(array $rolesInput): array
    {
        $uuidRoles = array_values(array_filter($rolesInput, fn ($r) => is_string($r) && Str::isUuid($r)));
        $codeRoles = array_values(array_filter($rolesInput, fn ($r) => is_string($r) && ! Str::isUuid($r)));

        if (empty($uuidRoles) && empty($codeRoles)) {
            return [];
        }

        return Role::query()
            ->where(function ($q) use ($codeRoles, $uuidRoles) {
                if (! empty($codeRoles)) {
                    $q->whereIn('code', $codeRoles);
                }
                if (! empty($uuidRoles)) {
                    if (! empty($codeRoles)) {
                        $q->orWhereIn('id', $uuidRoles);
                    } else {
                        $q->whereIn('id', $uuidRoles);
                    }
                }
            })
            ->pluck('id')
            ->all();
    }

    /**
     * P0-07: Validasi otorisasi penetapan role dan cegah eskalasi privilege ke super_admin.
     *
     * @param  array<int, mixed>  $rolesInput
     * @return string[]
     */
    protected function resolveAuthorizedRoleIds(array $rolesInput, ?User $actor, ?User $targetUser = null): array
    {
        $actorIsSuperAdmin = $actor?->hasRole('super_admin') ?? false;
        $actorCanManageRoles = $actorIsSuperAdmin || ($actor?->hasPermission('auth.role.manage') ?? false);

        if (! $actorCanManageRoles) {
            throw new HttpException(403, 'Akses ditolak: Anda memerlukan izin auth.role.manage untuk menetapkan atau mengubah role pengguna.');
        }

        $roleIds = $this->findRoleIds($rolesInput);
        if (empty($roleIds)) {
            return [];
        }

        if (! $actorIsSuperAdmin) {
            $assigningSuperAdmin = Role::whereIn('id', $roleIds)
                ->where('code', 'super_admin')
                ->exists();

            if ($assigningSuperAdmin) {
                throw new HttpException(403, 'Akses ditolak: Hanya Super Admin yang dapat menetapkan role super_admin.');
            }

            if ($targetUser && $targetUser->hasRole('super_admin')) {
                throw new HttpException(403, 'Akses ditolak: Hanya Super Admin yang dapat mengubah role akun Super Admin.');
            }
        }

        return $roleIds;
    }

    /**
     * Revoke all access tokens, refresh tokens, and active sessions for a user.
     */
    protected function revokeUserSessions(string $userId): void
    {
        try {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', $userId)
                ->delete();

            DB::table('refresh_tokens')
                ->where('user_id', $userId)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            cache()->forget("user_perm_cache:{$userId}");
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Write an immutable audit log entry.
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
        } catch (Throwable $e) {
            report($e);
        }
    }
}
