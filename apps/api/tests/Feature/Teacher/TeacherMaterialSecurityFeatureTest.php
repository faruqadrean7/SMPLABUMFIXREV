<?php

namespace Tests\Feature\Teacher;

use App\Models\ClassStudent;
use App\Models\File;
use App\Models\LearningMaterial;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Storage\FileStorageService;
use Database\Seeders\TeacherDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeacherMaterialSecurityFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $userMtk;

    protected Teacher $teacherMtk;

    protected User $userWalas;

    protected Teacher $teacherWalas;

    protected SchoolClass $class7A;

    protected SchoolClass $class7B;

    protected Subject $subjectMtk;

    protected User $userStudent7A;

    protected Student $student7A;

    protected User $userStudent7B;

    protected Student $student7B;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        (new TeacherDemoSeeder)->run();

        $this->userMtk = User::where('username', 'guru.mtk.staging')->firstOrFail();
        $this->teacherMtk = Teacher::where('user_id', $this->userMtk->id)->firstOrFail();

        $this->userWalas = User::where('username', 'walas.7a.staging')->firstOrFail();
        $this->teacherWalas = Teacher::where('user_id', $this->userWalas->id)->firstOrFail();

        $this->class7A = SchoolClass::where('name', '7-A')->firstOrFail();
        $this->class7B = SchoolClass::where('name', '7-B')->firstOrFail();

        $this->subjectMtk = Subject::where('code', 'MTK-7')->firstOrFail();

        // Siswa 7-A
        $this->student7A = Student::whereHas('classes', fn ($q) => $q->where('classes.id', $this->class7A->id))->firstOrFail();
        $this->userStudent7A = $this->student7A->user;

        // Buat Siswa 7-B untuk uji isolasi kelas
        $this->userStudent7B = User::firstOrCreate(
            ['username' => 'student.7b.isolation'],
            [
                'school_id' => $this->class7B->school_id,
                'email' => 'student7b.iso@sistlabum.sch.id',
                'password_hash' => bcrypt('password'),
                'status' => 'active',
            ]
        );
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $this->userStudent7B->roles()->syncWithoutDetaching([$roleStudent->id]);

        $this->student7B = Student::firstOrCreate(
            ['user_id' => $this->userStudent7B->id],
            [
                'school_id' => $this->class7B->school_id,
                'nis' => '99002',
                'nisn' => '9900000002',
                'nik_enc' => '3578010101099999',
                'full_name' => 'Siswa Kelas 7-B Isolasi',
                'gender' => 'L',
                'birth_place' => 'Malang',
                'birth_date' => '2012-05-15',
                'religion' => 'Islam',
                'address' => 'Jl. Veteran No. 1 Malang',
                'status' => 'aktif',
                'entry_date' => '2026-07-01',
            ]
        );

        ClassStudent::firstOrCreate([
            'class_id' => $this->class7B->id,
            'student_id' => $this->student7B->id,
        ], ['joined_at' => '2026-07-01']);
    }

    /**
     * 1. Ukuran file melebihi batas (>50MB) ditolak (400)
     */
    public function test_material_upload_rejects_file_exceeding_size_limit(): void
    {
        // 55 MB file (batas adalah 50 MB / 51200 KB)
        $oversizedFile = UploadedFile::fake()->create('large_lecture.pdf', 55 * 1024, 'application/pdf');

        $response = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/materials', [
                'class_id' => $this->class7A->id,
                'subject_id' => $this->subjectMtk->id,
                'title' => 'Materi File Besar',
                'file' => $oversizedFile,
            ]);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['file']);
    }

    /**
     * 2. Tipe file terlarang (.php, .sh, .exe) ditolak (400)
     */
    public function test_material_upload_rejects_forbidden_file_types(): void
    {
        $forbiddenExtensions = [
            ['name' => 'exploit.php', 'mime' => 'application/x-php'],
            ['name' => 'script.sh', 'mime' => 'text/x-shellscript'],
            ['name' => 'virus.exe', 'mime' => 'application/x-msdownload'],
        ];

        foreach ($forbiddenExtensions as $item) {
            $file = UploadedFile::fake()->create($item['name'], 50, $item['mime']);

            $response = $this->actingAs($this->userMtk)
                ->postJson('/api/v1/materials', [
                    'class_id' => $this->class7A->id,
                    'subject_id' => $this->subjectMtk->id,
                    'title' => 'Materi Terlarang',
                    'file' => $file,
                ]);

            $response->assertStatus(400);
            $response->assertJsonValidationErrors(['file']);
        }
    }

    /**
     * 3. Berkas privat via Signed URL:
     * - Valid signed URL -> 200 OK
     * - Expired signed URL -> 403 Forbidden
     * - Tampered signed URL -> 403 Forbidden
     */
    public function test_private_material_file_signed_url_security(): void
    {
        $validPdf = UploadedFile::fake()->create('bab1_aljabar.pdf', 500, 'application/pdf');

        $uploadRes = $this->actingAs($this->userMtk)
            ->post('/api/v1/materials', [
                'class_id' => $this->class7A->id,
                'subject_id' => $this->subjectMtk->id,
                'title' => 'Bab 1: Dasar Aljabar',
                'file' => $validPdf,
            ]);

        $uploadRes->assertStatus(201);
        $fileId = $uploadRes->json('data.file_id');
        $this->assertNotEmpty($fileId);

        $fileRecord = File::findOrFail($fileId);
        $this->assertEquals('privat', $fileRecord->visibility);

        /** @var FileStorageService $storageService */
        $storageService = app(FileStorageService::class);

        // A. Valid Signed URL -> 200 OK
        $urlData = $storageService->getFileUrl($fileId, $this->userMtk, 300);
        $validUrl = $urlData['url'];

        $validRes = $this->get($validUrl);
        $validRes->assertOk();
        $this->assertEquals('application/pdf', $validRes->headers->get('Content-Type'));

        // B. Tampered URL (signature diubah) -> 403 Forbidden
        $tamperedUrl = $validUrl.'&tampered=1';
        $tamperedRes = $this->get($tamperedUrl);
        $tamperedRes->assertStatus(403);

        // C. Expired Signed URL -> 403 Forbidden
        $expiredUrlData = $storageService->getFileUrl($fileId, $this->userMtk, -60);
        $expiredRes = $this->get($expiredUrlData['url']);
        $expiredRes->assertStatus(403);
    }

    /**
     * 4. Siswa kelas lain TIDAK bisa mengakses materi kelas ini (403)
     */
    public function test_other_class_student_cannot_access_material(): void
    {
        $material = LearningMaterial::create([
            'class_id' => $this->class7A->id,
            'subject_id' => $this->subjectMtk->id,
            'teacher_id' => $this->teacherMtk->id,
            'title' => 'Materi Khusus 7-A',
            'description' => 'Khusus siswa kelas 7-A',
        ]);

        // Siswa 7-A bisa mengakses
        $okRes = $this->actingAs($this->userStudent7A)
            ->getJson("/api/v1/materials/{$material->id}");
        $okRes->assertOk();

        // Siswa 7-B ditolak 403
        $forbiddenRes = $this->actingAs($this->userStudent7B)
            ->getJson("/api/v1/materials/{$material->id}");
        $forbiddenRes->assertStatus(403);
    }

    /**
     * 5. Guru lain tidak bisa ubah atau hapus materi guru ini (403)
     */
    public function test_other_teacher_cannot_update_or_delete_material(): void
    {
        $material = LearningMaterial::create([
            'class_id' => $this->class7A->id,
            'subject_id' => $this->subjectMtk->id,
            'teacher_id' => $this->teacherMtk->id,
            'title' => 'Materi Milik Guru MTK',
            'description' => 'Hanya guru MTK yang berhak mengelola',
        ]);

        // Guru lain (Walas 7-A) coba ubah materi -> 403
        $updateRes = $this->actingAs($this->userWalas)
            ->putJson("/api/v1/materials/{$material->id}", [
                'title' => 'Materi Diubah Oleh Guru Lain',
            ]);
        $updateRes->assertStatus(403);

        // Guru lain (Walas 7-A) coba hapus materi -> 403
        $deleteRes = $this->actingAs($this->userWalas)
            ->deleteJson("/api/v1/materials/{$material->id}");
        $deleteRes->assertStatus(403);

        // Guru pemilik asli (Guru MTK) bisa ubah & hapus
        $ownerUpdateRes = $this->actingAs($this->userMtk)
            ->putJson("/api/v1/materials/{$material->id}", [
                'title' => 'Materi Diperbarui Oleh Guru Pemilik',
            ]);
        $ownerUpdateRes->assertOk();

        $ownerDeleteRes = $this->actingAs($this->userMtk)
            ->deleteJson("/api/v1/materials/{$material->id}");
        $ownerDeleteRes->assertOk();

        $this->assertDatabaseMissing('learning_materials', [
            'id' => $material->id,
        ]);
    }
}
