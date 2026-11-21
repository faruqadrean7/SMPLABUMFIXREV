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

/**
 * Mengunci aturan: guru boleh mengelola ujian miliknya sendiri, tetapi
 * TIDAK boleh menyentuh ujian guru lain — meskipun ia memegang izin
 * cbt.exam.manage (izin ini memang diberikan kepada guru agar CBT bisa jalan).
 */
class ExamOwnershipGuardTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $guruA;

    protected User $guruB;

    protected Exam $examGuruA;

    protected Exam $examGuruB;

    protected function setUp(): void
    {
        parent::setUp();

        $permManage = Permission::firstOrCreate(
            ['code' => 'cbt.exam.manage'],
            ['description' => 'Mengelola jadwal dan sesi ujian CBT']
        );

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru Mata Pelajaran', 'is_system' => true]);

        $roleAdmin->permissions()->syncWithoutDetaching([$permManage->id]);
        // Guru memegang cbt.exam.manage — sama seperti DatabaseSeeder setelah perbaikan.
        $roleTeacher->permissions()->syncWithoutDetaching([$permManage->id]);

        $this->admin = $this->makeUser('admin_own', 'admin_own@sekolah.sch.id');
        $this->admin->roles()->attach($roleAdmin->id);

        $this->guruA = $this->makeUser('guru_a', 'guru_a@sekolah.sch.id');
        $this->guruA->roles()->attach($roleTeacher->id);

        $this->guruB = $this->makeUser('guru_b', 'guru_b@sekolah.sch.id');
        $this->guruB->roles()->attach($roleTeacher->id);

        $subject = Subject::create([
            'name' => 'Matematika',
            'code' => 'MTK-OWN',
            'subject_group' => 'muatan_nasional',
        ]);

        $this->examGuruA = $this->makeExam($subject->id, $this->guruA->id, 'Sumatif Matematika — Guru A');
        $this->examGuruB = $this->makeExam($subject->id, $this->guruB->id, 'Sumatif Matematika — Guru B');
    }

    protected function makeUser(string $username, string $email): User
    {
        return User::create([
            'username' => $username,
            'email' => $email,
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
    }

    protected function makeExam(string $subjectId, string $createdBy, string $title): Exam
    {
        return Exam::create([
            'subject_id' => $subjectId,
            'created_by' => $createdBy,
            'title' => $title,
            'exam_type' => 'sas',
            'duration_min' => 90,
            'start_at' => Carbon::now()->addDays(2),
            'end_at' => Carbon::now()->addDays(2)->addHours(3),
            'passing_grade' => 75.0,
            'shuffle_questions' => false,
            'shuffle_options' => false,
            'status' => 'draft',
        ]);
    }

    public function test_guru_boleh_mengubah_ujian_miliknya_sendiri(): void
    {
        $this->actingAs($this->guruA, 'sanctum')
            ->putJson("/api/v1/exams/{$this->examGuruA->id}", ['duration_min' => 60])
            ->assertStatus(200);

        $this->assertSame(60, (int) $this->examGuruA->fresh()->duration_min);
    }

    public function test_guru_tidak_boleh_mengubah_ujian_guru_lain(): void
    {
        $this->actingAs($this->guruA, 'sanctum')
            ->putJson("/api/v1/exams/{$this->examGuruB->id}", ['duration_min' => 30])
            ->assertStatus(403);

        $this->assertSame(90, (int) $this->examGuruB->fresh()->duration_min, 'Ujian guru B tidak boleh berubah.');
    }

    public function test_guru_tidak_boleh_menerbitkan_ujian_guru_lain(): void
    {
        $this->actingAs($this->guruA, 'sanctum')
            ->patchJson("/api/v1/exams/{$this->examGuruB->id}/status", ['status' => 'dijadwalkan'])
            ->assertStatus(403);

        $this->assertSame('draft', $this->examGuruB->fresh()->status);
    }

    public function test_guru_tidak_boleh_menghapus_ujian_guru_lain(): void
    {
        $this->actingAs($this->guruA, 'sanctum')
            ->deleteJson("/api/v1/exams/{$this->examGuruB->id}")
            ->assertStatus(403);

        $this->assertNotNull($this->examGuruB->fresh());
    }

    public function test_guru_boleh_menerbitkan_ujian_miliknya_sendiri(): void
    {
        $this->actingAs($this->guruA, 'sanctum')
            ->patchJson("/api/v1/exams/{$this->examGuruA->id}/status", ['status' => 'dijadwalkan'])
            ->assertStatus(200);

        $this->assertSame('dijadwalkan', $this->examGuruA->fresh()->status);
    }

    public function test_admin_boleh_mengubah_ujian_siapa_pun(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/exams/{$this->examGuruB->id}", ['duration_min' => 45])
            ->assertStatus(200);

        $this->assertSame(45, (int) $this->examGuruB->fresh()->duration_min);
    }
}
