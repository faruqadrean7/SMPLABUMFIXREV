<?php

namespace Tests\Feature\Master;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Relasi admin ↔ murid ↔ guru: admin harus bisa membuatkan akun login portal
 * untuk siswa dan guru. Sebelumnya hanya orang tua yang punya endpoint ini,
 * sehingga siswa/guru hasil input admin tidak bisa masuk portal.
 */
class AccountProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $guruBiasa;

    protected Student $student;

    protected Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $permStudent = Permission::firstOrCreate(['code' => 'master.student.write'], ['description' => 'Mengubah data siswa']);
        $permTeacher = Permission::firstOrCreate(['code' => 'master.teacher.manage'], ['description' => 'Mengelola data guru']);

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Administrator', 'is_system' => true]);
        $roleAdmin->permissions()->syncWithoutDetaching([$permStudent->id, $permTeacher->id]);

        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru Mata Pelajaran', 'is_system' => true]);

        $this->admin = User::create([
            'username' => 'admin_akun',
            'email' => 'admin_akun@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->admin->roles()->attach($roleAdmin->id);

        $this->guruBiasa = User::create([
            'username' => 'guru_biasa',
            'email' => 'guru_biasa@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->guruBiasa->roles()->attach($roleTeacher->id);

        $this->student = Student::create([
            'full_name' => 'Siswa Tanpa Akun',
            'nis' => '20269901',
            'nisn' => '0099887766',
            'nik_enc' => 'enc_3578010101990001',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2011-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $this->teacher = Teacher::create([
            'nip' => '199001012020011001',
            'full_name' => 'Guru Tanpa Akun',
            'gender' => 'P',
            'status' => 'aktif',
        ]);
    }

    public function test_admin_membuatkan_akun_login_untuk_siswa(): void
    {
        $this->assertNull($this->student->user_id, 'Awalnya siswa belum punya akun.');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/students/{$this->student->id}/account", [
                'username' => 'siswa.uji',
                'password' => 'Rahasia123',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.user.username', 'siswa.uji');

        $student = $this->student->fresh();
        $this->assertNotNull($student->user_id, 'Siswa harus tertaut ke akun login.');
        $this->assertSame('siswa.uji', $student->user->username);
        $this->assertTrue($student->user->hasRole('student'), 'Akun harus berperan siswa.');
    }

    public function test_admin_membuatkan_akun_login_untuk_guru(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/teachers/{$this->teacher->id}/account", [
                'username' => 'guru.uji',
                'password' => 'Rahasia123',
            ])
            ->assertStatus(200);

        $teacher = $this->teacher->fresh();
        $this->assertNotNull($teacher->user_id);
        $this->assertTrue($teacher->user->hasRole('teacher'));
    }

    public function test_provision_ulang_tidak_menggandakan_akun_dan_mereset_password(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/students/{$this->student->id}/account", ['username' => 'siswa.uji', 'password' => 'Rahasia123'])
            ->assertStatus(200);

        $userId = $this->student->fresh()->user_id;

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/students/{$this->student->id}/account", ['password' => 'PasswordBaru9'])
            ->assertStatus(200);

        $student = $this->student->fresh();
        $this->assertSame($userId, $student->user_id, 'Akun lama harus dipakai ulang, bukan dibuat baru.');
        $this->assertSame(1, User::where('username', 'siswa.uji')->count());
    }

    public function test_guru_tanpa_izin_tidak_boleh_membuatkan_akun(): void
    {
        $this->actingAs($this->guruBiasa, 'sanctum')
            ->postJson("/api/v1/students/{$this->student->id}/account", ['username' => 'ilegal'])
            ->assertStatus(403);

        $this->assertNull($this->student->fresh()->user_id);
    }
}
