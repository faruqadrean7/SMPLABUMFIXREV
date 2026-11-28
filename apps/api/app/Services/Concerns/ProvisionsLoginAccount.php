<?php

namespace App\Services\Concerns;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Membuat / menyetel ulang akun login portal untuk siswa, guru, dan orang tua.
 *
 * Ketiganya punya kolom `user_id` (nullable) dan peran masing-masing, sehingga
 * tanpa endpoint ini admin hanya bisa membuat data induk tanpa akun login —
 * siswa/guru tidak bisa masuk ke portalnya.
 */
trait ProvisionsLoginAccount
{
    protected function upsertLoginAccount(
        Model $person,
        string $roleCode,
        string $roleName,
        array $data,
        ?string $schoolId,
        ?string $phone,
        string $usernameFallback,
    ): User {
        $password = ! empty($data['password']) ? $data['password'] : 'Password123!';
        $role = Role::ensureSystemRole($roleCode, $roleName);

        if ($person->user) {
            $user = $person->user;
            $user->password_hash = Hash::make($password);

            if (! empty($data['username'])) {
                $user->username = strtolower(trim($data['username']));
            }
            if (array_key_exists('email', $data)) {
                $user->email = ! empty($data['email']) ? strtolower(trim($data['email'])) : null;
            }

            $user->save();
            $user->roles()->syncWithoutDetaching([$role->id]);
            $user->flushPermissionCache();

            return $user;
        }

        $raw = ! empty($data['username']) ? $data['username'] : $usernameFallback;
        $username = strtolower(trim((string) $raw));

        if ($username === '') {
            $digits = preg_replace('/\D/', '', (string) $phone);
            $username = $digits !== '' ? 'user_'.$digits : 'user_'.Str::lower(Str::random(6));
        }

        $base = $username;
        $counter = 1;
        while (User::where('username', $username)->exists()) {
            $username = $base.$counter;
            $counter++;
        }

        $user = User::create([
            'school_id' => $schoolId,
            'username' => $username,
            'email' => ! empty($data['email']) ? strtolower(trim($data['email'])) : null,
            'phone' => $phone,
            'password_hash' => Hash::make($password),
            'status' => 'active',
            'must_change_password' => true,
        ]);

        $user->roles()->syncWithoutDetaching([$role->id]);

        $person->user_id = $user->id;
        $person->save();

        return $user;
    }
}
