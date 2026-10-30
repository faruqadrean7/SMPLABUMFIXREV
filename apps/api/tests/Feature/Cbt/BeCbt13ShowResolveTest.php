<?php

namespace Tests\Feature\Cbt;

use App\Models\File;
use App\Models\Permission;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Role;
use App\Models\School;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BeCbt13ShowResolveTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;

    protected User $userNoPerm;

    protected Question $question;

    protected File $mediaFile;

    protected function setUp(): void
    {
        parent::setUp();

        $school = School::firstOrCreate(
            ['id' => '00000000-0000-0000-0000-000000000001'],
            ['name' => 'SMP Laboratorium UM', 'npsn' => '20533807', 'is_active' => true]
        );

        $perm = Permission::firstOrCreate(['code' => 'cbt.bank.write'], ['description' => 'Tulis Bank Soal']);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'school_id' => $school->id]);
        $roleTeacher->permissions()->syncWithoutDetaching([$perm->id]);

        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'school_id' => $school->id]);

        $this->teacher = User::firstOrCreate(
            ['username' => 'guru_show'],
            [
                'school_id' => $school->id,
                'email' => 'guru.show@labschool.sch.id',
                'password_hash' => Hash::make('Password123!'),
                'status' => 'active',
                'must_change_password' => false,
            ]
        );
        $this->teacher->roles()->syncWithoutDetaching([$roleTeacher->id]);

        $this->userNoPerm = User::firstOrCreate(
            ['username' => 'siswa_show'],
            [
                'school_id' => $school->id,
                'email' => 'siswa.show@labschool.sch.id',
                'password_hash' => Hash::make('Password123!'),
                'status' => 'active',
                'must_change_password' => false,
            ]
        );
        $this->userNoPerm->roles()->syncWithoutDetaching([$roleStudent->id]);

        $subject = Subject::firstOrCreate(
            ['code' => 'IPA_SHOW', 'school_id' => $school->id],
            ['name' => 'Ilmu Pengetahuan Alam', 'subject_group' => 'umum']
        );

        $bank = QuestionBank::firstOrCreate(
            ['name' => 'Bank Soal Show'],
            [
                'school_id' => $school->id,
                'subject_id' => $subject->id,
                'owner_id' => $this->teacher->id,
            ]
        );

        $this->mediaFile = File::create([
            'school_id' => $school->id,
            'owner_id' => $bank->id,
            'owner_type' => 'question_bank',
            'bucket' => 'cbt',
            'object_key' => 'cbt/2026/10/sel-preview.png',
            'mime_type' => 'image/png',
            'size_bytes' => 2048,
            'visibility' => 'privat',
        ]);

        $this->question = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'pg',
            'body' => [
                'content' => '<p>Perhatikan gambar sel berikut:</p><img data-file-id="'.$this->mediaFile->id.'" alt="Sel">',
            ],
            'version' => 1,
            'status' => 'aktif',
        ]);

        QuestionOption::create([
            'question_id' => $this->question->id,
            'label' => 'A',
            'body' => [
                'content' => '<p>Organel:</p><img data-file-id="'.$this->mediaFile->id.'" alt="Organel A">',
            ],
            'is_correct' => true,
        ]);
    }

    /**
     * TEST-BE-CBT-13-01: GET /api/v1/questions/{id} menyuntikkan src bertanda-tangan ke tag <img> pada body dan opsi.
     */
    public function test_be_cbt_13_show_injects_signed_src(): void
    {
        Sanctum::actingAs($this->teacher);

        $response = $this->getJson('/api/v1/questions/'.$this->question->id);

        $response->assertStatus(200);

        $bodyContent = $response->json('data.body.content');
        $this->assertStringContainsString('data-file-id="'.$this->mediaFile->id.'"', $bodyContent);
        $this->assertStringContainsString('src=', $bodyContent);
        $this->assertStringContainsString('signature=', $bodyContent);

        $optionContent = $response->json('data.options.0.body.content');
        $this->assertStringContainsString('data-file-id="'.$this->mediaFile->id.'"', $optionContent);
        $this->assertStringContainsString('src=', $optionContent);
        $this->assertStringContainsString('signature=', $optionContent);
    }

    /**
     * TEST-BE-CBT-13-02: GET /api/v1/questions/{id} memerlukan permission cbt.bank.write (403).
     */
    public function test_be_cbt_13_show_requires_cbt_bank_write(): void
    {
        Sanctum::actingAs($this->userNoPerm);

        $response = $this->getJson('/api/v1/questions/'.$this->question->id);

        $response->assertStatus(403);
    }
}
