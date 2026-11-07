<?php

namespace Tests\Feature\Cbt;

use App\Models\Permission;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Role;
use App\Models\School;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BeCbt11StoreQuestionTest extends TestCase
{
    use RefreshDatabase;

    protected User $userWithPermission;

    protected User $userWithoutPermission;

    protected QuestionBank $bank;

    protected function setUp(): void
    {
        parent::setUp();

        $school = School::firstOrCreate(
            ['id' => '00000000-0000-0000-0000-000000000001'],
            ['name' => 'SMP Laboratorium UM', 'npsn' => '20533807', 'is_active' => true]
        );

        $perm = Permission::firstOrCreate(['code' => 'cbt.bank.write'], ['description' => 'Tulis Bank Soal']);
        $role = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'school_id' => $school->id]);
        $role->permissions()->syncWithoutDetaching([$perm->id]);

        $this->userWithPermission = User::firstOrCreate(
            ['username' => 'guru_cbt'],
            [
                'school_id' => $school->id,
                'email' => 'guru.cbt@labschool.sch.id',
                'password_hash' => Hash::make('Password123!'),
                'status' => 'active',
                'must_change_password' => false,
            ]
        );
        $this->userWithPermission->roles()->syncWithoutDetaching([$role->id]);

        $this->userWithoutPermission = User::firstOrCreate(
            ['username' => 'user_polos'],
            [
                'school_id' => $school->id,
                'email' => 'polos@labschool.sch.id',
                'password_hash' => Hash::make('Password123!'),
                'status' => 'active',
                'must_change_password' => false,
            ]
        );

        $subject = Subject::firstOrCreate(
            ['code' => 'IPA', 'school_id' => $school->id],
            ['name' => 'Ilmu Pengetahuan Alam', 'subject_group' => 'umum']
        );

        $this->bank = QuestionBank::firstOrCreate(
            ['name' => 'Bank Soal Uji CBT'],
            [
                'school_id' => $school->id,
                'subject_id' => $subject->id,
                'owner_id' => $this->userWithPermission->id,
            ]
        );
    }

    /**
     * TEST-BE-CBT-11-06: POST /api/v1/questions menyanitasi HTML body.content dari XSS & script.
     */
    public function test_be_cbt_11_store_sanitizes_body_html(): void
    {
        Sanctum::actingAs($this->userWithPermission);

        $payload = [
            'bank_id' => $this->bank->id,
            'question_type' => 'pg',
            'body' => [
                'content' => '<p>Manakah organel berikut?</p><script>alert("XSS")</script>',
            ],
            'options' => [
                [
                    'label' => 'A',
                    'body' => ['content' => 'Mitokondria'],
                    'is_correct' => true,
                ],
                [
                    'label' => 'B',
                    'body' => ['content' => 'Ribosom'],
                    'is_correct' => false,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/questions', $payload);

        $response->assertStatus(201);
        $questionId = $response->json('data.id');

        $question = Question::findOrFail($questionId);
        $this->assertStringNotContainsString('<script>', $question->body['content']);
        $this->assertStringNotContainsString('alert', $question->body['content']);
        $this->assertStringContainsString('Manakah organel berikut?', $question->body['content']);
    }

    /**
     * TEST-BE-CBT-11-07: POST /api/v1/questions menyanitasi HTML pada opsi jawaban dan membuang src namun mempertahankan data-file-id.
     */
    public function test_be_cbt_11_store_sanitizes_option_html(): void
    {
        Sanctum::actingAs($this->userWithPermission);

        $payload = [
            'bank_id' => $this->bank->id,
            'question_type' => 'pg',
            'body' => [
                'content' => '<p>Perhatikan gambar sel berikut:</p>',
            ],
            'options' => [
                [
                    'label' => 'A',
                    'body' => [
                        'content' => '<p>Pilihan A</p><img src="https://evil.com/leak.jpg" onerror="alert(1)" data-file-id="00000000-0000-0000-0000-000000000099" alt="Sel A">',
                    ],
                    'is_correct' => true,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/questions', $payload);

        $response->assertStatus(201);
        $questionId = $response->json('data.id');

        $question = Question::with('options')->findOrFail($questionId);
        $optionA = $question->options->firstWhere('label', 'A');

        $this->assertNotNull($optionA);
        $optContent = $optionA->body['content'];
        $this->assertStringNotContainsString('onerror', $optContent);
        $this->assertStringNotContainsString('src=', $optContent);
        $this->assertStringContainsString('data-file-id="00000000-0000-0000-0000-000000000099"', $optContent);
    }

    /**
     * TEST-BE-CBT-11-08: POST /api/v1/questions menolak konten lebih dari 100.000 karakter (422).
     */
    public function test_be_cbt_11_rejects_content_over_100k(): void
    {
        Sanctum::actingAs($this->userWithPermission);

        $hugeContent = str_repeat('A', 100001);

        $payload = [
            'bank_id' => $this->bank->id,
            'question_type' => 'uraian',
            'body' => [
                'content' => $hugeContent,
            ],
        ];

        $response = $this->postJson('/api/v1/questions', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['body.content']);
    }

    /**
     * TEST-BE-CBT-11-09: Akses ditolak (403) jika tidak memiliki permission cbt.bank.write.
     */
    public function test_be_cbt_11_requires_cbt_bank_write(): void
    {
        Sanctum::actingAs($this->userWithoutPermission);

        $payload = [
            'bank_id' => $this->bank->id,
            'question_type' => 'uraian',
            'body' => ['content' => 'Soal tes'],
        ];

        $response = $this->postJson('/api/v1/questions', $payload);

        $response->assertStatus(403);
    }

    /**
     * TEST-BE-CBT-11-10: Akses unauthenticated menghasilkan 401.
     */
    public function test_be_cbt_11_unauthenticated_401(): void
    {
        $payload = [
            'bank_id' => $this->bank->id,
            'question_type' => 'uraian',
            'body' => ['content' => 'Soal tes'],
        ];

        $response = $this->postJson('/api/v1/questions', $payload);

        $response->assertStatus(401);
    }
}
