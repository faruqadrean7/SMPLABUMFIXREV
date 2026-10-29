<?php

namespace Tests\Feature\Core;

use App\Models\GradeLevel;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BelongsToSchoolTest extends TestCase
{
    use RefreshDatabase;

    protected School $schoolA;

    protected School $schoolB;

    protected User $userA;

    protected User $userB;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::create([
            'name' => 'SMP Lab UM Kampus A',
            'npsn' => '20533801',
            'is_active' => true,
        ]);

        $this->schoolB = School::create([
            'name' => 'SMP Lab UM Kampus B',
            'npsn' => '20533802',
            'is_active' => true,
        ]);

        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleSuperAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);

        $this->userA = User::create([
            'username' => 'guru_a',
            'email' => 'guru_a@labum.sch.id',
            'password_hash' => Hash::make('password123'),
            'school_id' => $this->schoolA->id,
            'status' => 'active',
        ]);
        $this->userA->roles()->attach($roleTeacher->id);

        $this->userB = User::create([
            'username' => 'guru_b',
            'email' => 'guru_b@labum.sch.id',
            'password_hash' => Hash::make('password123'),
            'school_id' => $this->schoolB->id,
            'status' => 'active',
        ]);
        $this->userB->roles()->attach($roleTeacher->id);

        $this->superAdmin = User::create([
            'username' => 'super_admin',
            'email' => 'super@labum.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->superAdmin->roles()->attach($roleSuperAdmin->id);
    }

    public function test_be_mst_01_creating_model_auto_populates_school_id_from_auth_user(): void
    {
        $this->actingAs($this->userA);

        $grade = GradeLevel::create([
            'name' => 'Kelas VII Kampus A',
            'sort_order' => 7,
        ]);

        $this->assertSame($this->schoolA->id, $grade->school_id);
        $this->assertSame('SMP Lab UM Kampus A', $grade->school->name);
    }

    public function test_be_mst_01_global_scope_isolates_records_by_tenant_school_id(): void
    {
        // Buat data untuk masing-masing sekolah
        GradeLevel::create([
            'school_id' => $this->schoolA->id,
            'name' => 'Kelas VII Kampus A',
            'sort_order' => 7,
        ]);

        GradeLevel::create([
            'school_id' => $this->schoolB->id,
            'name' => 'Kelas VII Kampus B',
            'sort_order' => 7,
        ]);

        // User A hanya melihat data Sekolah A
        $this->actingAs($this->userA);
        $recordsA = GradeLevel::all();
        $this->assertCount(1, $recordsA);
        $this->assertSame('Kelas VII Kampus A', $recordsA->first()->name);

        // User B hanya melihat data Sekolah B
        $this->actingAs($this->userB);
        $recordsB = GradeLevel::all();
        $this->assertCount(1, $recordsB);
        $this->assertSame('Kelas VII Kampus B', $recordsB->first()->name);
    }

    public function test_be_mst_01_super_admin_has_cross_tenant_visibility(): void
    {
        GradeLevel::create([
            'school_id' => $this->schoolA->id,
            'name' => 'Kelas VII Kampus A',
            'sort_order' => 7,
        ]);

        GradeLevel::create([
            'school_id' => $this->schoolB->id,
            'name' => 'Kelas VII Kampus B',
            'sort_order' => 7,
        ]);

        // Super admin melihat semua data (lintas sekolah)
        $this->actingAs($this->superAdmin);
        $this->assertCount(2, GradeLevel::all());
    }

    public function test_be_mst_01_without_school_scope_bypasses_tenant_isolation(): void
    {
        GradeLevel::create([
            'school_id' => $this->schoolA->id,
            'name' => 'Kelas VII Kampus A',
            'sort_order' => 7,
        ]);

        GradeLevel::create([
            'school_id' => $this->schoolB->id,
            'name' => 'Kelas VII Kampus B',
            'sort_order' => 7,
        ]);

        $this->actingAs($this->userA);
        $all = GradeLevel::withoutSchoolScope()->get();
        $this->assertCount(2, $all);
    }
}
