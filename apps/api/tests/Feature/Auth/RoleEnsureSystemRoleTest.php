<?php

namespace Tests\Feature\Auth;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoleEnsureSystemRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_t2b_conc_02_creates_system_role_when_missing(): void
    {
        Role::where('code', 'guardian')->delete();
        $this->assertDatabaseMissing('roles', ['code' => 'guardian']);

        $role = Role::ensureSystemRole('guardian', 'Orang Tua / Wali Murid');

        $this->assertSame('guardian', $role->code);
        $this->assertSame('Orang Tua / Wali Murid', $role->name);
        $this->assertTrue($role->is_system);
        $this->assertSame(1, Role::where('code', 'guardian')->count());
    }

    public function test_t2b_conc_02_returns_existing_role_without_modifying_name_or_permissions(): void
    {
        $existing = Role::create([
            'code' => 'student',
            'name' => 'Siswa Existing Custom',
            'is_system' => true,
        ]);

        $permission = Permission::create([
            'code' => 'lms.material.view',
            'description' => 'Lihat materi pembelajaran',
        ]);
        $existing->permissions()->attach($permission->id);

        $resolved = Role::ensureSystemRole('student', 'Siswa');

        $this->assertSame($existing->id, $resolved->id);
        $this->assertSame('Siswa Existing Custom', $resolved->name);
        $this->assertTrue($resolved->is_system);
        $this->assertSame(1, Role::where('code', 'student')->count());
        $this->assertTrue($resolved->permissions()->where('permissions.id', $permission->id)->exists());
    }

    public function test_t2b_conc_02_insert_or_ignore_is_safe_inside_active_transaction(): void
    {
        Role::where('code', 'guest')->delete();

        $roles = DB::transaction(function () {
            $first = Role::ensureSystemRole('guest', 'Tamu / Calon Siswa');
            $second = Role::ensureSystemRole('guest', 'Tamu / Calon Siswa');

            // Simulasikan duplikat langsung via insertOrIgnore di dalam transaksi yang sama:
            // transaksi PostgreSQL tidak boleh abort (SQLSTATE[25P02]).
            DB::table('roles')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'school_id' => null,
                'code' => 'guest',
                'name' => 'Nama Konflik Tidak Boleh Menimpa',
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $third = Role::ensureSystemRole('guest', 'Tamu / Calon Siswa');

            return [$first, $second, $third];
        });

        $this->assertSame($roles[0]->id, $roles[1]->id);
        $this->assertSame($roles[0]->id, $roles[2]->id);
        $this->assertSame('Tamu / Calon Siswa', $roles[2]->name);
        $this->assertSame(1, Role::where('code', 'guest')->count());
    }
}
