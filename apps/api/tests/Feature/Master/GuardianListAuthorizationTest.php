<?php

namespace Tests\Feature\Master;

use App\Models\Guardian;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuardianListAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $studentUser;

    protected User $librarianUser;

    protected User $guardianUser1;

    protected User $guardianUser2;

    protected Guardian $guardian1;

    protected Guardian $guardian2;

    protected function setUp(): void
    {
        parent::setUp();

        $permRead = Permission::firstOrCreate(['code' => 'master.student.read'], ['description' => 'Read student']);
        $roleAdmin = Role::firstOrCreate(['code' => 'admin_tu'], ['name' => 'Admin TU', 'is_system' => true]);
        $roleAdmin->permissions()->syncWithoutDetaching([$permRead->id]);

        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $roleLibrarian = Role::firstOrCreate(['code' => 'librarian'], ['name' => 'Pustakawan', 'is_system' => true]);
        $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Wali Murid', 'is_system' => true]);

        $this->adminUser = $this->createUserWithRole('admin_grd_auth', $roleAdmin);
        $this->studentUser = $this->createUserWithRole('siswa_grd_auth', $roleStudent);
        $this->librarianUser = $this->createUserWithRole('pustaka_grd_auth', $roleLibrarian);
        $this->guardianUser1 = $this->createUserWithRole('wali1_grd_auth', $roleGuardian);
        $this->guardianUser2 = $this->createUserWithRole('wali2_grd_auth', $roleGuardian);

        $student = Student::create([
            'user_id' => $this->studentUser->id,
            'nis' => '20268811',
            'nisn' => '0088112233',
            'nik_enc' => '3578010101018811',
            'full_name' => 'Siswa Uji Wali',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $this->guardian1 = Guardian::create([
            'user_id' => $this->guardianUser1->id,
            'full_name' => 'Wali Pertama',
            'phone' => '081234568801',
        ]);
        $this->guardian1->students()->attach($student->id, ['relationship' => 'ayah', 'is_primary' => true]);

        $this->guardian2 = Guardian::create([
            'user_id' => $this->guardianUser2->id,
            'full_name' => 'Wali Kedua',
            'phone' => '081234568802',
        ]);
    }

    private function createUserWithRole(string $username, Role $role): User
    {
        $user = User::create([
            'username' => $username,
            'email' => "{$username}@sekolah.sch.id",
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_p0_06_student_and_unauthorized_staff_are_rejected_403_on_list_guardians(): void
    {
        Sanctum::actingAs($this->studentUser);
        $this->getJson('/api/v1/guardians')->assertStatus(403);

        Sanctum::actingAs($this->librarianUser);
        $this->getJson('/api/v1/guardians')->assertStatus(403);
    }

    public function test_p0_06_guardian_only_sees_own_record_and_admin_with_permission_sees_all(): void
    {
        // Guardian 1 hanya melihat dirinya sendiri
        Sanctum::actingAs($this->guardianUser1);
        $resGuardian = $this->getJson('/api/v1/guardians');
        $resGuardian->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->guardian1->id);

        // Admin TU dengan master.student.read melihat seluruh wali
        Sanctum::actingAs($this->adminUser);
        $resAdmin = $this->getJson('/api/v1/guardians');
        $resAdmin->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }
}
