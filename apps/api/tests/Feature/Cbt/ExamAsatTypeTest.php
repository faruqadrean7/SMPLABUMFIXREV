<?php

namespace Tests\Feature\Cbt;

use App\Models\Exam;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExamAsatTypeTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $unauthorizedUser;

    protected Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'admin'], ['name' => 'Administrator', 'is_system' => true]);
        $permManage = Permission::firstOrCreate(['code' => 'cbt.exam.manage'], ['description' => 'Kelola Ujian CBT']);
        $roleAdmin->permissions()->syncWithoutDetaching([$permManage->id]);

        $this->adminUser = User::create([
            'username' => 'cbt_admin',
            'email' => 'cbt_admin@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->unauthorizedUser = User::create([
            'username' => 'plain_user',
            'email' => 'plain_user@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $this->subject = Subject::create([
            'code' => 'BIO-10',
            'name' => 'Biologi',
            'subject_group' => 'umum',
        ]);
    }

    public function test_authorized_user_can_create_exam_with_asat_type(): void
    {
        $payload = [
            'subject_id' => $this->subject->id,
            'title' => 'Asesmen Sumatif Akhir Tahun Biologi Kelas 10',
            'exam_type' => 'ASAT',
            'duration_min' => 90,
            'start_at' => Carbon::now()->addDays(2)->toIso8601String(),
            'end_at' => Carbon::now()->addDays(2)->addHours(2)->toIso8601String(),
            'passing_grade' => 75.0,
            'shuffle_questions' => true,
            'shuffle_options' => true,
            'package_mode' => 'single',
            'show_result_mode' => 'after_release',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/exams', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.exam_type', 'ASAT')
            ->assertJsonPath('data.title', 'Asesmen Sumatif Akhir Tahun Biologi Kelas 10');

        $this->assertDatabaseHas('exams', [
            'id' => $response->json('data.id'),
            'exam_type' => 'ASAT',
        ]);
    }

    public function test_authorized_user_can_update_exam_to_asat_type(): void
    {
        $exam = Exam::create([
            'subject_id' => $this->subject->id,
            'created_by' => $this->adminUser->id,
            'title' => 'Ujian Akhir Semester Biologi',
            'exam_type' => 'SAS',
            'duration_min' => 90,
            'start_at' => Carbon::now()->addDays(1),
            'end_at' => Carbon::now()->addDays(1)->addHours(2),
            'passing_grade' => 70,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/exams/{$exam->id}", [
                'exam_type' => 'ASAT',
                'title' => 'Asesmen Sumatif Akhir Tahun Biologi',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.exam_type', 'ASAT');

        $this->assertDatabaseHas('exams', [
            'id' => $exam->id,
            'exam_type' => 'ASAT',
        ]);
    }

    public function test_filter_exams_by_asat_type_succeeds(): void
    {
        Exam::create([
            'subject_id' => $this->subject->id,
            'created_by' => $this->adminUser->id,
            'title' => 'ASAT Biologi',
            'exam_type' => 'ASAT',
            'duration_min' => 90,
            'start_at' => Carbon::now()->addDays(1),
            'end_at' => Carbon::now()->addDays(1)->addHours(2),
            'status' => 'draft',
        ]);

        Exam::create([
            'subject_id' => $this->subject->id,
            'created_by' => $this->adminUser->id,
            'title' => 'Kuis Biologi',
            'exam_type' => 'kuis',
            'duration_min' => 30,
            'start_at' => Carbon::now()->addDays(1),
            'end_at' => Carbon::now()->addDays(1)->addHours(1),
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/exams?exam_type=ASAT');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.exam_type', 'ASAT');
    }

    public function test_invalid_exam_type_is_rejected(): void
    {
        $payload = [
            'subject_id' => $this->subject->id,
            'title' => 'Ujian Jenis Invalid',
            'exam_type' => 'ujian_nasional_palsu',
            'duration_min' => 60,
            'start_at' => Carbon::now()->addDays(2)->toIso8601String(),
            'end_at' => Carbon::now()->addDays(2)->addHours(2)->toIso8601String(),
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/exams', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['exam_type']);
    }

    public function test_all_existing_exam_types_remain_valid(): void
    {
        $validTypes = ['kuis', 'harian', 'STS', 'SAS', 'remedial', 'pengayaan'];

        foreach ($validTypes as $type) {
            $response = $this->actingAs($this->adminUser)
                ->postJson('/api/v1/exams', [
                    'subject_id' => $this->subject->id,
                    'title' => "Ujian Tipe {$type}",
                    'exam_type' => $type,
                    'duration_min' => 60,
                    'start_at' => Carbon::now()->addDays(3)->toIso8601String(),
                    'end_at' => Carbon::now()->addDays(3)->addHours(2)->toIso8601String(),
                ]);

            $response->assertStatus(201)
                ->assertJsonPath('data.exam_type', $type);
        }
    }

    public function test_unauthorized_user_cannot_create_asat_exam(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->postJson('/api/v1/exams', [
                'subject_id' => $this->subject->id,
                'title' => 'ASAT Tanpa Hak Akses',
                'exam_type' => 'ASAT',
                'duration_min' => 60,
                'start_at' => Carbon::now()->addDays(2)->toIso8601String(),
                'end_at' => Carbon::now()->addDays(2)->addHours(2)->toIso8601String(),
            ]);

        $response->assertStatus(403);
    }
}
