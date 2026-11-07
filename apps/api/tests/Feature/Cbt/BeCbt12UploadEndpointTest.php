<?php

namespace Tests\Feature\Cbt;

use App\Models\Permission;
use App\Models\QuestionBank;
use App\Models\Role;
use App\Models\School;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BeCbt12UploadEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected User $ownerTeacher;

    protected User $otherTeacher;

    protected User $userNoPermission;

    protected QuestionBank $bank;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $school = School::firstOrCreate(
            ['id' => '00000000-0000-0000-0000-000000000001'],
            ['name' => 'SMP Laboratorium UM', 'npsn' => '20533807', 'is_active' => true]
        );

        $perm = Permission::firstOrCreate(['code' => 'cbt.bank.write'], ['description' => 'Tulis Bank Soal']);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'school_id' => $school->id]);
        $roleTeacher->permissions()->syncWithoutDetaching([$perm->id]);

        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'school_id' => $school->id]);

        $this->ownerTeacher = User::firstOrCreate(
            ['username' => 'guru_pemilik'],
            [
                'school_id' => $school->id,
                'email' => 'guru.pemilik@labschool.sch.id',
                'password_hash' => Hash::make('Password123!'),
                'status' => 'active',
                'must_change_password' => false,
            ]
        );
        $this->ownerTeacher->roles()->syncWithoutDetaching([$roleTeacher->id]);

        $this->otherTeacher = User::firstOrCreate(
            ['username' => 'guru_lain'],
            [
                'school_id' => $school->id,
                'email' => 'guru.lain@labschool.sch.id',
                'password_hash' => Hash::make('Password123!'),
                'status' => 'active',
                'must_change_password' => false,
            ]
        );
        $this->otherTeacher->roles()->syncWithoutDetaching([$roleTeacher->id]);

        $this->userNoPermission = User::firstOrCreate(
            ['username' => 'siswa_user'],
            [
                'school_id' => $school->id,
                'email' => 'siswa@labschool.sch.id',
                'password_hash' => Hash::make('Password123!'),
                'status' => 'active',
                'must_change_password' => false,
            ]
        );
        $this->userNoPermission->roles()->syncWithoutDetaching([$roleStudent->id]);

        $subject = Subject::firstOrCreate(
            ['code' => 'IPA_UP', 'school_id' => $school->id],
            ['name' => 'Ilmu Pengetahuan Alam', 'subject_group' => 'umum']
        );

        $this->bank = QuestionBank::firstOrCreate(
            ['name' => 'Bank Soal Biologi Pemilik'],
            [
                'school_id' => $school->id,
                'subject_id' => $subject->id,
                'owner_id' => $this->ownerTeacher->id,
            ]
        );
    }

    /**
     * TEST-BE-CBT-12-07: POST /api/v1/cbt/question-media berhasil untuk guru pemilik bank (201).
     */
    public function test_be_cbt_12_upload_ok_returns_file_id(): void
    {
        Sanctum::actingAs($this->ownerTeacher);

        $file = UploadedFile::fake()->image('gambar_sel.png', 400, 300);

        $response = $this->postJson('/api/v1/cbt/question-media', [
            'bank_id' => $this->bank->id,
            'file' => $file,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'file_id',
                'mime_type',
                'preview_url',
            ],
        ]);
        $this->assertNotEmpty($response->json('data.file_id'));
        $this->assertStringContainsString('signature=', $response->json('data.preview_url'));
    }

    /**
     * TEST-BE-CBT-12-08: POST /api/v1/cbt/question-media menolak (403 IDOR) guru lain yang bukan pemilik bank.
     */
    public function test_be_cbt_12_upload_forbidden_other_bank_idor(): void
    {
        Sanctum::actingAs($this->otherTeacher);

        $file = UploadedFile::fake()->image('sel.png');

        $response = $this->postJson('/api/v1/cbt/question-media', [
            'bank_id' => $this->bank->id,
            'file' => $file,
        ]);

        $response->assertStatus(403);
    }

    /**
     * TEST-BE-CBT-12-09: POST /api/v1/cbt/question-media menolak (403) pemohon tanpa permission cbt.bank.write.
     */
    public function test_be_cbt_12_upload_requires_permission(): void
    {
        Sanctum::actingAs($this->userNoPermission);

        $file = UploadedFile::fake()->image('sel.png');

        $response = $this->postJson('/api/v1/cbt/question-media', [
            'bank_id' => $this->bank->id,
            'file' => $file,
        ]);

        $response->assertStatus(403);
    }

    /**
     * TEST-BE-CBT-12-10: POST /api/v1/cbt/question-media unauthenticated menghasilkan 401.
     */
    public function test_be_cbt_12_upload_401(): void
    {
        $file = UploadedFile::fake()->image('sel.png');

        $response = $this->postJson('/api/v1/cbt/question-media', [
            'bank_id' => $this->bank->id,
            'file' => $file,
        ]);

        $response->assertStatus(401);
    }

    /**
     * TEST-BE-CBT-12-11: POST /api/v1/cbt/question-media menolak berkas tidak valid / bukan gambar (422).
     */
    public function test_be_cbt_12_upload_422_bad_file(): void
    {
        Sanctum::actingAs($this->ownerTeacher);

        $file = UploadedFile::fake()->create('script.sh', 10, 'application/x-sh');

        $response = $this->postJson('/api/v1/cbt/question-media', [
            'bank_id' => $this->bank->id,
            'file' => $file,
        ]);

        $response->assertStatus(422);
    }
}
