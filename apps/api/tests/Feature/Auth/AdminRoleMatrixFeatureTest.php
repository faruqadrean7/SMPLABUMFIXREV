<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Api\V1\AdminAuthController;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminRoleMatrixFeatureTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * BATCH 5 - A.2: Matriks Peran x Jalur Login.
     * Pastikan setiap peran administratif diterima di /auth/admin/login dan DITOLAK di /auth/login.
     * Pastikan setiap peran civitas diterima di /auth/login dan DITOLAK di /auth/admin/login.
     */
    public function test_batch5_role_matrix_cross_verification_all_canonical_roles(): void
    {
        $adminRoles = AdminAuthController::ADMIN_ROLES;
        $civitasRoles = [
            'teacher',
            'homeroom',
            'student',
            'guardian',
            'committee',
            'supervisor',
            'guest',
        ];

        // 1. Uji setiap Admin Role
        foreach ($adminRoles as $roleCode) {
            $role = Role::firstOrCreate(['code' => $roleCode], ['name' => ucfirst($roleCode)]);
            $username = 'matrix_adm_'.$roleCode;
            $user = User::create([
                'username' => $username,
                'email' => $username.'@smp-labum.sch.id',
                'password_hash' => Hash::make('MatrixPass123!'),
                'status' => 'active',
                'two_factor_confirmed_at' => now(), // Assume 2FA confirmed for non-enrollment testing
            ]);
            $user->roles()->attach($role->id);

            // A. Harus DITOLAK di jalur civitas: POST /api/v1/auth/login
            $resCivitas = $this->postJson('/api/v1/auth/login', [
                'identifier' => $username,
                'password' => 'MatrixPass123!',
            ]);
            $this->assertEquals(
                401,
                $resCivitas->status(),
                "Peran admin '{$roleCode}' seharusnya ditolak di jalur login civitas."
            );

            // B. Harus DITERIMA di jalur admin: POST /api/v1/auth/admin/login
            $resAdmin = $this->postJson('/api/v1/auth/admin/login', [
                'identifier' => $username,
                'password' => 'MatrixPass123!',
            ]);
            $this->assertEquals(
                200,
                $resAdmin->status(),
                "Peran admin '{$roleCode}' seharusnya diterima di jalur login admin."
            );
        }

        // 2. Uji setiap Civitas / Non-Admin Role
        foreach ($civitasRoles as $roleCode) {
            $role = Role::firstOrCreate(['code' => $roleCode], ['name' => ucfirst($roleCode)]);
            $username = 'matrix_civ_'.$roleCode;
            $user = User::create([
                'username' => $username,
                'email' => $username.'@smp-labum.sch.id',
                'password_hash' => Hash::make('MatrixPass123!'),
                'status' => 'active',
            ]);
            $user->roles()->attach($role->id);

            // A. Harus DITOLAK di jalur admin: POST /api/v1/auth/admin/login
            $resAdmin = $this->postJson('/api/v1/auth/admin/login', [
                'identifier' => $username,
                'password' => 'MatrixPass123!',
            ]);
            $this->assertEquals(
                401,
                $resAdmin->status(),
                "Peran civitas '{$roleCode}' seharusnya ditolak di jalur login admin."
            );

            // B. Harus DITERIMA di jalur civitas: POST /api/v1/auth/login
            $resCivitas = $this->postJson('/api/v1/auth/login', [
                'identifier' => $username,
                'password' => 'MatrixPass123!',
            ]);
            $this->assertEquals(
                200,
                $resCivitas->status(),
                "Peran civitas '{$roleCode}' seharusnya diterima di jalur login civitas."
            );
        }
    }

    /**
     * BATCH 5C - B: Iterasi atas SEMUA peran di tabel roles.
     * Tidak ada peran yang bisa masuk shell admin tanpa 2FA (wajib challenge/enrollment),
     * dan peran tidak dikenal ditolak keras (deny by default).
     */
    public function test_batch5c_all_roles_in_database_and_unknown_roles_adhere_to_strict_security(): void
    {
        if (Role::count() === 0) {
            (new \Database\Seeders\DatabaseSeeder())->run();
        }

        $allRoles = Role::all();
        $this->assertNotEmpty($allRoles, 'Tabel roles tidak boleh kosong.');

        foreach ($allRoles as $role) {
            $username = 'audit_role_'.$role->code;
            $user = User::create([
                'username' => $username,
                'email' => $username.'@smp-labum.sch.id',
                'password_hash' => Hash::make('AuditRolePass123!'),
                'status' => 'active',
                'two_factor_confirmed_at' => null, // Belum setup 2FA
            ]);
            $user->roles()->attach($role->id);

            $isAdminRole = in_array($role->code, User::ADMIN_SHELL_ROLES, true) ||
                           isset(User::ROLE_ALIASES[$role->code]);

            $resAdmin = $this->postJson('/api/v1/auth/admin/login', [
                'identifier' => $username,
                'password' => 'AuditRolePass123!',
            ]);

            if ($isAdminRole) {
                // Admin role TANPA 2FA aktif WAJIB memicu mfa_enrollment_required, BUKAN token shell langsung
                $this->assertEquals(200, $resAdmin->status());
                $this->assertTrue(
                    (bool) $resAdmin->json('data.mfa_enrollment_required'),
                    "Peran admin '{$role->code}' tanpa 2FA wajib memicu mfa_enrollment_required."
                );
                $this->assertNull(
                    $resAdmin->json('data.token'),
                    "Peran admin '{$role->code}' tidak boleh mendapatkan token shell tanpa 2FA."
                );
            } else {
                // Non-admin role DITOLAK (401) di jalur admin
                $this->assertEquals(
                    401,
                    $resAdmin->status(),
                    "Peran non-admin '{$role->code}' harus ditolak di gerbang admin."
                );
            }
        }

        // Uji Deny-by-Default untuk Peran Tak Dikenal (Unknown Role)
        $unknownRole = Role::firstOrCreate(['code' => 'random_unknown_intruder'], ['name' => 'Unknown Intruder']);
        $intruderUser = User::create([
            'username' => 'unknown_intruder',
            'email' => 'intruder@unknown.com',
            'password_hash' => Hash::make('IntruderPass123!'),
            'status' => 'active',
            'two_factor_confirmed_at' => now(),
        ]);
        $intruderUser->roles()->attach($unknownRole->id);

        $resIntruder = $this->postJson('/api/v1/auth/admin/login', [
            'identifier' => 'unknown_intruder',
            'password' => 'IntruderPass123!',
        ]);
        $this->assertEquals(401, $resIntruder->status(), 'Peran tak dikenal harus ditolak deny-by-default.');
    }

    /**
     * BATCH 5C - B: Verifikasi konsistensi alias peran lama/kanonis.
     */
    public function test_batch5c_role_aliases_normalize_transparently(): void
    {
        $aliases = User::ROLE_ALIASES;
        foreach ($aliases as $alias => $canonical) {
            $role = Role::firstOrCreate(['code' => $alias], ['name' => ucfirst($alias)]);
            $user = User::create([
                'username' => 'alias_user_'.$alias,
                'email' => 'alias_'.$alias.'@smp-labum.sch.id',
                'password_hash' => Hash::make('AliasPass123!'),
                'status' => 'active',
                'two_factor_confirmed_at' => now(),
            ]);
            $user->roles()->attach($role->id);

            // User dengan role alias otomatis dikenali memiliki canonical role
            $this->assertTrue(
                $user->hasRole($canonical),
                "User dengan alias '{$alias}' harus otomatis dikenali sebagai '{$canonical}'."
            );
        }
    }
}
