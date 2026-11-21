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

            // Assign roles if provided
            if (! empty($data['roles'])) {
                $uuidRoles = array_values(array_filter($data['roles'], fn ($r) => is_string($r) && Str::isUuid($r)));
                $codeRoles = array_values(array_filter($data['roles'], fn ($r) => is_string($r) && ! Str::isUuid($r)));

                $roleIds = Role::query()
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

                if (! empty($roleIds)) {
                    $user->roles()->sync($roleIds);
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
            $oldValues = [
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'roles' => $user->roles->pluck('code')->all(),
            ];

            // Protect against self-deactivation/suspension
            if (isset($data['status']) && $data['status'] !== $user->status) {
                if ($user->id === $request->user()?->id && in_array($data['status'], ['inactive', 'suspended'], true)) {
                    throw new HttpException(400, 'Tidak dapat menonaktifkan atau menangguhkan akun Anda sendiri demi menjaga akses administratif.');
                }
                $user->status = $data['status'];

                // Revoke active sessions if deactivated or suspended
                if (in_array($data['status'], ['inactive', 'suspended'], true)) {
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

            // Sync roles if provided
            if (isset($data['roles'])) {
                $uuidRoles = array_values(array_filter($data['roles'], fn ($r) => is_string($r) && Str::isUuid($r)));
                $codeRoles = array_values(array_filter($data['roles'], fn ($r) => is_string($r) && ! Str::isUuid($r)));

                $roleIds = Role::query()
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
                $user->roles()->sync($roleIds);
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

        if ($user->id === $request->user()?->id && in_array($status, ['inactive', 'suspended'], true)) {
            throw new HttpException(400, 'Tidak dapat menonaktifkan atau menangguhkan akun Anda sendiri demi menjaga akses administratif.');
        }

        $oldStatus = $user->status;
        $user->status = $status;
        $user->save();

        if (in_array($status, ['inactive', 'suspended'], true)) {
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
