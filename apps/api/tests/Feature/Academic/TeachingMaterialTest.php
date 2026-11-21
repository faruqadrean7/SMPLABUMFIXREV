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

class TeachingMaterialTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $teacher1User;

    protected User $teacher2User;

    protected GradeLevel $grade7;

    protected GradeLevel $grade8;

    protected Subject $subjectMath;

    protected Subject $subjectScience;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $roleAdmin = Role::firstOrCreate(['code' => 'admin_kurikulum'], ['name' => 'Admin Kurikulum', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);

        $this->adminUser = User::create([
            'username' => 'adminkurikulum',
            'email' => 'kurikulum@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->teacher1User = User::create([
            'username' => 'gurubudi',
            'email' => 'budi@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacher1User->roles()->attach($roleTeacher->id);

        $this->teacher2User = User::create([
            'username' => 'gurusiti',
            'email' => 'siti@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacher2User->roles()->attach($roleTeacher->id);

        $this->grade7 = GradeLevel::create(['name' => 'Kelas 7', 'sort_order' => 1]);
        $this->grade8 = GradeLevel::create(['name' => 'Kelas 8', 'sort_order' => 2]);

        $this->subjectMath = Subject::create([
            'code' => 'MAT-7',
            'name' => 'Matematika',
            'subject_group' => 'umum',
        ]);

        $this->subjectScience = Subject::create([
            'code' => 'IPA-8',
            'name' => 'Ilmu Pengetahuan Alam',
            'subject_group' => 'umum',
        ]);
    }

    public function test_be_acd_06_teacher_can_upload_new_teaching_material(): void
    {
        $token = $this->teacher1User->createToken('test')->plainTextToken;
        $fakeFile = UploadedFile::fake()->create('modul-aljabar.pdf', 2048, 'application/pdf');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/teaching-materials', [
                'title' => 'Modul Ajar Bab 1 Aljabar Dasar',
                'description' => 'Perangkat ajar kurikulum merdeka aljabar fase D',
                'material_type' => 'modul',
                'grade_level_id' => $this->grade7->id,
                'subject_id' => $this->subjectMath->id,
                'file' => $fakeFile,
            ], ['Accept' => 'application/json']);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Modul Ajar Bab 1 Aljabar Dasar')
            ->assertJsonPath('data.material_type', 'modul')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.uploaded_by', $this->teacher1User->id);

        $data = $response->json('data');

        $this->assertDatabaseHas('teaching_materials', [
            'id' => $data['id'],
            'title' => 'Modul Ajar Bab 1 Aljabar Dasar',
            'version' => 1,
        ]);

        $this->assertDatabaseHas('files', [
            'id' => $data['file_id'],
            'bucket' => 'teaching-materials',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->teacher1User->id,
            'action' => 'teaching_material.created',
        ]);
    }

    public function test_be_acd_06_upload_fails_with_invalid_file_format_or_missing_file_422(): void
    {
        $token = $this->teacher1User->createToken('test')->plainTextToken;

        // Tanpa berkas
        $resNoFile = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/teaching-materials', [
                'title' => 'Modul Tanpa Berkas',
                'material_type' => 'modul',
            ]);

        $resNoFile->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // Berkas dengan ekstensi tidak diizinkan (.exe)
        $invalidFile = UploadedFile::fake()->create('virus.exe', 1024, 'application/octet-stream');

        $resInvalid = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/teaching-materials', [
                'title' => 'Modul Berkas Jahat',
                'material_type' => 'modul',
                'file' => $invalidFile,
            ], ['Accept' => 'application/json']);

        $resInvalid->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_be_acd_06_teacher_can_upload_new_version_revision(): void
    {
        // 1. Buat versi 1 awal
        $fakeFile1 = File::create([
            'owner_id' => $this->teacher1User->id,
            'owner_type' => 'User',
            'bucket' => 'teaching-materials',
            'object_key' => 'teaching-materials/v1.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'visibility' => 'publik',
            'checksum' => 'checksum-v1',
        ]);

        $material = TeachingMaterial::create([
            'title' => 'ATP Matematika Fase D',
            'description' => 'Versi awal draft',
            'material_type' => 'ATP',
            'grade_level_id' => $this->grade7->id,
            'subject_id' => $this->subjectMath->id,
            'uploaded_by' => $this->teacher1User->id,
            'file_id' => $fakeFile1->id,
            'version' => 1,
        ]);

        // 2. Unggah revisi versi 2
        $token = $this->teacher1User->createToken('test')->plainTextToken;
        $fakeFile2 = UploadedFile::fake()->create('atp-matematika-revisi.pdf', 3000, 'application/pdf');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post("/api/v1/teaching-materials/{$material->id}/versions", [
                'title' => 'ATP Matematika Fase D (Revisi Tim Kurikulum)',
                'description' => 'Penambahan materi peluang dan statistika',
                'file' => $fakeFile2,
            ], ['Accept' => 'application/json']);

        $response->assertStatus(201)
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.parent_id', $material->id);

        $newVersionId = $response->json('data.id');

        $this->assertDatabaseHas('teaching_materials', [
            'id' => $newVersionId,
            'parent_id' => $material->id,
            'version' => 2,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->teacher1User->id,
            'action' => 'teaching_material.version_created',
        ]);
    }

    public function test_be_acd_06_get_teaching_material_detail_includes_all_versions(): void
    {
        $file1 = File::create([
            'owner_id' => $this->teacher1User->id,
            'owner_type' => 'User',
            'bucket' => 'teaching-materials',
            'object_key' => 'teaching-materials/v1.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'visibility' => 'publik',
            'checksum' => 'checksum-v1',
        ]);

        $file2 = File::create([
            'owner_id' => $this->teacher1User->id,
            'owner_type' => 'User',
            'bucket' => 'teaching-materials',
            'object_key' => 'teaching-materials/v2.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 2048,
            'visibility' => 'publik',
            'checksum' => 'checksum-v2',
        ]);

        $parent = TeachingMaterial::create([
            'title' => 'Modul P5: Kearifan Lokal',
            'description' => 'Projek penguatan profil pelajar pancasila',
            'material_type' => 'P5',
            'grade_level_id' => $this->grade7->id,
            'uploaded_by' => $this->teacher1User->id,
            'file_id' => $file1->id,
            'version' => 1,
        ]);

        TeachingMaterial::create([
            'title' => 'Modul P5: Kearifan Lokal Revisi Final',
            'material_type' => 'P5',
            'grade_level_id' => $this->grade7->id,
            'uploaded_by' => $this->teacher1User->id,
            'file_id' => $file2->id,
            'version' => 2,
            'parent_id' => $parent->id,
        ]);

        $token = $this->teacher1User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/teaching-materials/{$parent->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $parent->id)
            ->assertJsonPath('data.title', 'Modul P5: Kearifan Lokal');

        $this->assertCount(1, $response->json('data.versions'));
        $this->assertEquals(2, $response->json('data.versions.0.version'));
    }

    public function test_be_acd_06_list_teaching_materials_filters_by_type_grade_and_subject(): void
    {
        $dummyFile = File::create([
            'owner_id' => $this->teacher1User->id,
            'owner_type' => 'User',
            'bucket' => 'teaching-materials',
            'object_key' => 'teaching-materials/dummy.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'visibility' => 'publik',
            'checksum' => 'checksum-dummy',
        ]);

        // Perangkat 1: Modul Matematika Kelas 7
        TeachingMaterial::create([
            'title' => 'Modul Ajar Geometri',
            'material_type' => 'modul',
            'grade_level_id' => $this->grade7->id,
            'subject_id' => $this->subjectMath->id,
            'uploaded_by' => $this->teacher1User->id,
            'file_id' => $dummyFile->id,
            'version' => 1,
        ]);

        // Perangkat 2: Silabus IPA Kelas 8
        TeachingMaterial::create([
            'title' => 'Silabus Pembelajaran IPA Terpadu',
            'material_type' => 'silabus',
            'grade_level_id' => $this->grade8->id,
            'subject_id' => $this->subjectScience->id,
            'uploaded_by' => $this->teacher2User->id,
            'file_id' => $dummyFile->id,
            'version' => 1,
        ]);

        // Perangkat 3: Modul P5 Kelas 7
        TeachingMaterial::create([
            'title' => 'Modul P5 Suara Demokrasi',
            'material_type' => 'P5',
            'grade_level_id' => $this->grade7->id,
            'uploaded_by' => $this->teacher1User->id,
            'file_id' => $dummyFile->id,
            'version' => 1,
        ]);

        $token = $this->teacher1User->createToken('test')->plainTextToken;

        // Filter 1: Hanya tipe modul
        $resModul = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/teaching-materials?material_type=modul');
        $resModul->assertStatus(200);
        $this->assertCount(1, $resModul->json('data'));
        $this->assertEquals('Modul Ajar Geometri', $resModul->json('data.0.title'));

        // Filter 2: Berdasarkan mata pelajaran IPA
        $resSubject = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/teaching-materials?subject_id={$this->subjectScience->id}");
        $resSubject->assertStatus(200);
        $this->assertCount(1, $resSubject->json('data'));
        $this->assertEquals('Silabus Pembelajaran IPA Terpadu', $resSubject->json('data.0.title'));

        // Filter 3: Pencarian kata kunci 'Demokrasi'
        $resSearch = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/teaching-materials?search=Demokrasi');
        $resSearch->assertStatus(200);
        $this->assertCount(1, $resSearch->json('data'));
        $this->assertEquals('Modul P5 Suara Demokrasi', $resSearch->json('data.0.title'));
    }

    public function test_be_acd_06_uploader_or_admin_can_delete_teaching_material(): void
    {
        $dummyFile = File::create([
            'owner_id' => $this->teacher1User->id,
            'owner_type' => 'User',
            'bucket' => 'teaching-materials',
            'object_key' => 'teaching-materials/delete.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'visibility' => 'publik',
            'checksum' => 'checksum-delete',
        ]);

        $material = TeachingMaterial::create([
            'title' => 'Modul Yang Akan Dihapus',
            'material_type' => 'modul',
            'uploaded_by' => $this->teacher1User->id,
            'file_id' => $dummyFile->id,
            'version' => 1,
        ]);

        $token = $this->teacher1User->createToken('test')->plainTextToken;

        // Pemilik menghapus perangkat miliknya sendiri
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/teaching-materials/{$material->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'Perangkat ajar berhasil dihapus');

        $this->assertDatabaseMissing('teaching_materials', [
            'id' => $material->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->teacher1User->id,
            'action' => 'teaching_material.deleted',
        ]);
    }

    public function test_be_acd_06_other_teacher_cannot_delete_teaching_material_403(): void
    {
        $dummyFile = File::create([
            'owner_id' => $this->teacher1User->id,
            'owner_type' => 'User',
            'bucket' => 'teaching-materials',
            'object_key' => 'teaching-materials/protected.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'visibility' => 'publik',
            'checksum' => 'checksum-protected',
        ]);

        $material = TeachingMaterial::create([
            'title' => 'Modul Milik Guru 1',
            'material_type' => 'modul',
            'uploaded_by' => $this->teacher1User->id,
            'file_id' => $dummyFile->id,
            'version' => 1,
        ]);

        // Guru 2 mencoba menghapus modul milik Guru 1
        $tokenTeacher2 = $this->teacher2User->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$tokenTeacher2}")
            ->deleteJson("/api/v1/teaching-materials/{$material->id}");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->assertDatabaseHas('teaching_materials', [
            'id' => $material->id,
        ]);
    }

    public function test_be_acd_06_admin_can_delete_any_teaching_material(): void
    {
        $dummyFile = File::create([
            'owner_id' => $this->teacher1User->id,
            'owner_type' => 'User',
            'bucket' => 'teaching-materials',
            'object_key' => 'teaching-materials/admin-delete.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'visibility' => 'publik',
            'checksum' => 'checksum-admin-del',
        ]);

        $material = TeachingMaterial::create([
            'title' => 'Modul Dihapus Admin Kurikulum',
            'material_type' => 'modul',
            'uploaded_by' => $this->teacher1User->id,
            'file_id' => $dummyFile->id,
            'version' => 1,
        ]);

        $tokenAdmin = $this->adminUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$tokenAdmin}")
            ->deleteJson("/api/v1/teaching-materials/{$material->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('teaching_materials', [
            'id' => $material->id,
        ]);
    }

    public function test_be_acd_06_can_access_via_learning_modules_alias(): void
    {
        $dummyFile = File::create([
            'owner_id' => $this->teacher1User->id,
            'owner_type' => 'User',
            'bucket' => 'teaching-materials',
            'object_key' => 'teaching-materials/alias-test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 2048,
            'visibility' => 'publik',
            'checksum' => 'checksum-alias',
        ]);

        $material = TeachingMaterial::create([
            'title' => 'Modul Via Alias Learning Modules',
            'material_type' => 'ATP',
            'uploaded_by' => $this->teacher1User->id,
            'file_id' => $dummyFile->id,
            'version' => 1,
        ]);

        $tokenTeacher = $this->teacher1User->createToken('test')->plainTextToken;

        // Test GET /learning-modules
        $resIndex = $this->withHeader('Authorization', "Bearer {$tokenTeacher}")
            ->getJson('/api/v1/learning-modules');
        $resIndex->assertStatus(200)
            ->assertJsonPath('data.0.title', 'Modul Via Alias Learning Modules');

        // Test GET /learning-modules/{id}
        $resShow = $this->withHeader('Authorization', "Bearer {$tokenTeacher}")
            ->getJson("/api/v1/learning-modules/{$material->id}");
        $resShow->assertStatus(200)
            ->assertJsonPath('data.id', $material->id);
    }
}
