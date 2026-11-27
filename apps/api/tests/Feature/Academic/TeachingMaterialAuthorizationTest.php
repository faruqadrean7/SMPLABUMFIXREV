<?php

namespace Tests\Feature\Academic;

use App\Models\File;
use App\Models\GradeLevel;
use App\Models\Role;
use App\Models\Subject;
use App\Models\TeachingMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeachingMaterialAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $teacher1User;

    protected User $teacher2User;

    protected User $studentUser;

    protected User $guardianUser;

    protected User $guestUser;

    protected User $librarianUser;

    protected GradeLevel $grade7;

    protected Subject $subjectMath;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $roleAdmin = Role::firstOrCreate(['code' => 'admin_kurikulum'], ['name' => 'Admin Kurikulum', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Wali Murid', 'is_system' => true]);
        $roleGuest = Role::firstOrCreate(['code' => 'guest'], ['name' => 'Tamu PPDB', 'is_system' => true]);
        $roleLibrarian = Role::firstOrCreate(['code' => 'librarian'], ['name' => 'Pustakawan', 'is_system' => true]);

        $this->adminUser = $this->makeUser('p009_admin', $roleAdmin);
        $this->teacher1User = $this->makeUser('p009_guru1', $roleTeacher);
        $this->teacher2User = $this->makeUser('p009_guru2', $roleTeacher);
        $this->studentUser = $this->makeUser('p009_siswa', $roleStudent);
        $this->guardianUser = $this->makeUser('p009_ortu', $roleGuardian);
        $this->guestUser = $this->makeUser('p009_guest', $roleGuest);
        $this->librarianUser = $this->makeUser('p009_pustakawan', $roleLibrarian);

        $this->grade7 = GradeLevel::create(['name' => 'Kelas 7', 'sort_order' => 1]);
        $this->subjectMath = Subject::create([
            'code' => 'MAT-P09',
            'name' => 'Matematika',
            'subject_group' => 'umum',
        ]);
    }

    private function makeUser(string $username, Role $role): User
    {
        $user = User::create([
            'username' => $username,
            'email' => "{$username}@example.test",
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_p0_09_non_teacher_roles_cannot_upload_teaching_materials_or_learning_modules(): void
    {
        $unauthorizedActors = [
            'student' => $this->studentUser,
            'guardian' => $this->guardianUser,
            'guest' => $this->guestUser,
            'librarian' => $this->librarianUser,
        ];

        foreach ($unauthorizedActors as $label => $actor) {
            $this->app['auth']->forgetGuards();
            $this->actingAs($actor, 'sanctum')
                ->post('/api/v1/teaching-materials', [
                    'title' => "Modul Ilegal dari {$label}",
                    'material_type' => 'modul',
                    'file' => UploadedFile::fake()->create('ilegal.pdf', 100, 'application/pdf'),
                ], ['Accept' => 'application/json'])
                ->assertStatus(403, "Peran {$label} seharusnya ditolak (403) mengunggah teaching-materials.");

            $this->app['auth']->forgetGuards();
            $this->actingAs($actor, 'sanctum')
                ->post('/api/v1/learning-modules', [
                    'title' => "Modul Alias Ilegal dari {$label}",
                    'material_type' => 'modul',
                    'file' => UploadedFile::fake()->create('ilegal-alias.pdf', 100, 'application/pdf'),
                ], ['Accept' => 'application/json'])
                ->assertStatus(403, "Peran {$label} seharusnya ditolak (403) mengunggah learning-modules.");
        }

        $this->assertDatabaseCount('teaching_materials', 0);
    }

    public function test_p0_09_only_owner_or_admin_can_create_version_revision(): void
    {
        $file1 = File::create([
            'owner_id' => $this->teacher1User->id,
            'owner_type' => 'User',
            'bucket' => 'teaching-materials',
            'object_key' => 'teaching-materials/v1.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 512,
            'visibility' => 'privat',
            'checksum' => 'chk-v1',
        ]);

        $material = TeachingMaterial::create([
            'title' => 'Modul Matematika Asli Guru 1',
            'material_type' => 'modul',
            'grade_level_id' => $this->grade7->id,
            'subject_id' => $this->subjectMath->id,
            'uploaded_by' => $this->teacher1User->id,
            'file_id' => $file1->id,
            'version' => 1,
        ]);

        // Siswa ditolak 403
        $this->actingAs($this->studentUser, 'sanctum')
            ->post("/api/v1/teaching-materials/{$material->id}/versions", [
                'file' => UploadedFile::fake()->create('tamper.pdf', 100, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(403);

        // Guru lain (bukan pemilik) ditolak 403
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->teacher2User, 'sanctum')
            ->post("/api/v1/teaching-materials/{$material->id}/versions", [
                'file' => UploadedFile::fake()->create('tamper-guru2.pdf', 100, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(403);

        // Pemilik sah (Guru 1) diizinkan 201
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->teacher1User, 'sanctum')
            ->post("/api/v1/teaching-materials/{$material->id}/versions", [
                'file' => UploadedFile::fake()->create('revisi-v2.pdf', 100, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(201)
            ->assertJsonPath('data.version', 2);

        // Admin kurikulum diizinkan 201
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->adminUser, 'sanctum')
            ->post("/api/v1/teaching-materials/{$material->id}/versions", [
                'file' => UploadedFile::fake()->create('revisi-v3-admin.pdf', 100, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(201)
            ->assertJsonPath('data.version', 3);
    }

    public function test_p0_09_teacher_cannot_attach_another_users_private_file_id(): void
    {
        $privateFileOfTeacher1 = File::create([
            'owner_id' => $this->teacher1User->id,
            'owner_type' => 'User',
            'bucket' => 'private',
            'object_key' => 'private/slip-gaji.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'visibility' => 'privat',
            'checksum' => 'chk-private',
        ]);

        $this->actingAs($this->teacher2User, 'sanctum')
            ->postJson('/api/v1/teaching-materials', [
                'title' => 'Percobaan Lampirkan File Orang Lain',
                'material_type' => 'modul',
                'file_id' => $privateFileOfTeacher1->id,
            ])
            ->assertStatus(403);
    }

    public function test_p0_09_uploaded_file_uses_consistent_local_disk_and_private_visibility_while_students_can_read(): void
    {
        $createResp = $this->actingAs($this->teacher1User, 'sanctum')
            ->post('/api/v1/teaching-materials', [
                'title' => 'Modul Aljabar Konsisten',
                'material_type' => 'modul',
                'grade_level_id' => $this->grade7->id,
                'subject_id' => $this->subjectMath->id,
                'file' => UploadedFile::fake()->create('aljabar.pdf', 200, 'application/pdf'),
            ], ['Accept' => 'application/json']);

        $createResp->assertStatus(201);
        $materialId = $createResp->json('data.id');
        $fileId = $createResp->json('data.file_id');

        $file = File::findOrFail($fileId);
        $this->assertSame('privat', $file->visibility);
        $this->assertTrue(Storage::disk('local')->exists($file->object_key));

        // Siswa tetap dapat membaca daftar & detail modul ajar di portal siswa
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->studentUser, 'sanctum')
            ->getJson('/api/v1/teaching-materials')
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $materialId);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->studentUser, 'sanctum')
            ->getJson("/api/v1/teaching-materials/{$materialId}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $materialId);
    }
}
