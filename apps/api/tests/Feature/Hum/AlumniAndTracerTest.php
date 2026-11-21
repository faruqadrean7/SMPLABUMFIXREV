<?php

namespace Tests\Feature\Hum;

use App\Models\Alumni;
use App\Models\Role;
use App\Models\Student;
use App\Models\TracerResponse;
use App\Models\TracerSurvey;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AlumniAndTracerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $humasUser;

    protected User $alumniUser1;

    protected User $alumniUser2;

    protected User $regularStudentUser;

    protected Student $student1;

    protected Student $student2;

    protected Student $regularStudent;

    protected Alumni $alumni1;

    protected Alumni $alumni2;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleHumas = Role::firstOrCreate(['code' => 'humas'], ['name' => 'Staf Humas & Kemitraan', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa / Alumni', 'is_system' => true]);

        // Users
        $this->adminUser = User::create([
            'username' => 'admin_alumni',
            'email' => 'admin_alumni@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->humasUser = User::create([
            'username' => 'staff_humas_alumni',
            'email' => 'humas_alumni@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->humasUser->roles()->attach($roleHumas->id);

        $this->alumniUser1 = User::create([
            'username' => 'alumni_2024_01',
            'email' => 'fajar_alumni@alumni.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->alumniUser1->roles()->attach($roleStudent->id);

        $this->alumniUser2 = User::create([
            'username' => 'alumni_2024_02',
            'email' => 'nadia_alumni@alumni.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->alumniUser2->roles()->attach($roleStudent->id);

        $this->regularStudentUser = User::create([
            'username' => 'siswa_aktif_10',
            'email' => 'siswa10@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->regularStudentUser->roles()->attach($roleStudent->id);

        // Students
        $this->student1 = Student::create([
            'user_id' => $this->alumniUser1->id,
            'nis' => '20211001',
            'nisn' => '0077889901',
            'nik_enc' => '3201010101010101',
            'full_name' => 'Fajar Nugraha',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2006-03-12',
            'entry_date' => '2021-07-15',
            'status' => 'graduated',
        ]);

        $this->student2 = Student::create([
            'user_id' => $this->alumniUser2->id,
            'nis' => '20211002',
            'nisn' => '0077889902',
            'nik_enc' => '3201010101010102',
            'full_name' => 'Nadia Safitri',
            'gender' => 'P',
            'birth_place' => 'Surabaya',
            'birth_date' => '2006-09-18',
            'entry_date' => '2021-07-15',
            'status' => 'graduated',
        ]);

        $this->regularStudent = Student::create([
            'user_id' => $this->regularStudentUser->id,
            'nis' => '20261099',
            'nisn' => '0099887799',
            'nik_enc' => '3201010101010199',
            'full_name' => 'Aditya Putra',
            'gender' => 'L',
            'birth_place' => 'Kediri',
            'birth_date' => '2009-11-05',
            'entry_date' => '2026-07-15',
            'status' => 'active',
        ]);

        // Alumni records: 1 private, 1 public
        $this->alumni1 = Alumni::create([
            'student_id' => $this->student1->id,
            'graduation_year' => 2024,
            'current_status' => 'kuliah',
            'next_institution' => 'Institut Teknologi Bandung',
            'study_program' => 'Teknik Informatika',
            'phone' => '081234567801',
            'email' => 'fajar.nugraha@itb.ac.id',
            'contact' => '081234567801',
            'linkedin_url' => 'https://linkedin.com/in/fajarnugraha',
            'address' => 'Jl. Cisitu Lama No. 5 Bandung',
            'is_public' => false, // Private contact
        ]);

        $this->alumni2 = Alumni::create([
            'student_id' => $this->student2->id,
            'graduation_year' => 2024,
            'current_status' => 'bekerja',
            'next_institution' => null,
            'occupation' => 'Junior Software Engineer',
            'company_name' => 'PT Tokopedia',
            'phone' => '081234567802',
            'email' => 'nadia.safitri@tokopedia.com',
            'contact' => '081234567802',
            'linkedin_url' => 'https://linkedin.com/in/nadiasafitri',
            'address' => 'Jakarta Selatan',
            'is_public' => true, // Public contact
        ]);
    }

    public function test_be_hum_02_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/alumni')->assertStatus(401);
        $this->postJson('/api/v1/alumni', [])->assertStatus(401);
        $this->getJson('/api/v1/tracer-surveys')->assertStatus(401);
        $this->postJson('/api/v1/tracer-surveys', [])->assertStatus(401);
    }

    public function test_be_hum_02_staff_can_register_and_update_alumni(): void
    {
        $newStudent = Student::create([
            'nis' => '20211003',
            'nisn' => '0077889903',
            'nik_enc' => '3201010101010103',
            'full_name' => 'Dwi Cahyono',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2006-05-10',
            'entry_date' => '2021-07-15',
            'status' => 'graduated',
        ]);

        $payload = [
            'student_id' => $newStudent->id,
            'graduation_year' => 2024,
            'current_status' => 'wirausaha',
            'company_name' => 'Kopi Senja Mandiri',
            'occupation' => 'Founder & Barista',
            'phone' => '081234567803',
            'email' => 'dwi@kopisenja.com',
            'is_public' => true,
        ];

        $response = $this->actingAs($this->humasUser)
            ->postJson('/api/v1/alumni', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.graduation_year', 2024)
            ->assertJsonPath('data.current_status', 'wirausaha')
            ->assertJsonPath('data.company_name', 'Kopi Senja Mandiri');

        $alumniId = $response->json('data.id');

        // Update alumni
        $updatePayload = [
            'current_status' => 'bekerja',
            'company_name' => 'PT Sumber Alfaria Trijaya',
            'occupation' => 'Store Operations Manager',
        ];

        $updateResponse = $this->actingAs($this->humasUser)
            ->putJson("/api/v1/alumni/{$alumniId}", $updatePayload);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.current_status', 'bekerja')
            ->assertJsonPath('data.company_name', 'PT Sumber Alfaria Trijaya');
    }

    public function test_be_hum_02_alumni_can_update_their_own_profile(): void
    {
        // Alumni 1 logs in and updates their own alumni data
        $payload = [
            'occupation' => 'AI Researcher Intern',
            'company_name' => 'BRIN Riset Sains',
            'is_public' => true,
        ];

        $response = $this->actingAs($this->alumniUser1)
            ->putJson("/api/v1/alumni/{$this->alumni1->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.occupation', 'AI Researcher Intern')
            ->assertJsonPath('data.company_name', 'BRIN Riset Sains')
            ->assertJsonPath('data.is_public', true);

        // Regular student tries to update Alumni 1 profile -> 403 Forbidden
        $unauthorizedResponse = $this->actingAs($this->regularStudentUser)
            ->putJson("/api/v1/alumni/{$this->alumni1->id}", [
                'occupation' => 'Hacker',
            ]);

        $unauthorizedResponse->assertStatus(403);
    }

    public function test_be_hum_02_limited_alumni_directory_masks_private_contact_for_non_staff(): void
    {
        // Regular student views alumni list
        $studentResponse = $this->actingAs($this->regularStudentUser)
            ->getJson('/api/v1/alumni');

        $studentResponse->assertStatus(200);

        $data = $studentResponse->json('data');
        $alumni1Data = collect($data)->firstWhere('id', $this->alumni1->id);
        $alumni2Data = collect($data)->firstWhere('id', $this->alumni2->id);

        // Alumni 1 has is_public = false -> sensitive contact fields masked to null
        $this->assertNull($alumni1Data['phone']);
        $this->assertNull($alumni1Data['email']);
        $this->assertNull($alumni1Data['contact']);
        $this->assertNull($alumni1Data['address']);
        // But university/study program is visible for alumni directory
        $this->assertEquals('Institut Teknologi Bandung', $alumni1Data['next_institution']);

        // Alumni 2 has is_public = true -> contact fields are visible
        $this->assertEquals('081234567802', $alumni2Data['phone']);
        $this->assertEquals('nadia.safitri@tokopedia.com', $alumni2Data['email']);

        // Staff views alumni list -> all contact details visible regardless of is_public
        $staffResponse = $this->actingAs($this->humasUser)
            ->getJson('/api/v1/alumni');

        $staffData = $staffResponse->json('data');
        $staffAlumni1 = collect($staffData)->firstWhere('id', $this->alumni1->id);
        $this->assertEquals('081234567801', $staffAlumni1['phone']);
        $this->assertEquals('fajar.nugraha@itb.ac.id', $staffAlumni1['email']);
    }

    public function test_be_hum_02_filter_and_search_alumni_directory(): void
    {
        // Filter by current_status 'bekerja'
        $bekerjaResponse = $this->actingAs($this->humasUser)
            ->getJson('/api/v1/alumni?current_status=bekerja');

        $bekerjaResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.occupation', 'Junior Software Engineer');

        // Search by next_institution keyword 'Bandung'
        $searchResponse = $this->actingAs($this->regularStudentUser)
            ->getJson('/api/v1/alumni?search=Bandung');

        $searchResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.next_institution', 'Institut Teknologi Bandung');
    }

    public function test_be_hum_02_cannot_register_same_student_as_alumni_twice(): void
    {
        $payload = [
            'student_id' => $this->student1->id,
            'graduation_year' => 2024,
        ];

        $response = $this->actingAs($this->humasUser)
            ->postJson('/api/v1/alumni', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['student_id']);
    }

    public function test_be_hum_02_staff_can_create_update_and_delete_tracer_survey(): void
    {
        $payload = [
            'title' => 'Tracer Study Lulusan Tahun 2024',
            'description' => 'Survei penelusuran lulusan untuk evaluasi relevansi kurikulum dan transisi dunia kerja.',
            'target_graduation_year' => 2024,
            'questions' => [
                ['id' => 'q1', 'question' => 'Apa status kesibukan utama Anda saat ini?', 'type' => 'choice', 'options' => ['Kuliah', 'Bekerja', 'Wirausaha', 'Mencari Kerja']],
                ['id' => 'q2', 'question' => 'Berapa lama waktu tunggu hingga Anda mendapatkan pekerjaan/kuliah pertama?', 'type' => 'choice', 'options' => ['< 1 bulan', '1 - 3 bulan', '3 - 6 bulan', '> 6 bulan']],
                ['id' => 'q3', 'question' => 'Seberapa relevan materi pembelajaran sekolah dengan bidang Anda sekarang?', 'type' => 'rating', 'scale' => 5],
                ['id' => 'q4', 'question' => 'Saran dan masukan untuk pengembangan kurikulum sekolah:', 'type' => 'text'],
            ],
            'start_date' => Carbon::now()->subDays(5)->format('Y-m-d'),
            'end_date' => Carbon::now()->addMonths(2)->format('Y-m-d'),
            'is_active' => true,
        ];

        $response = $this->actingAs($this->humasUser)
            ->postJson('/api/v1/tracer-surveys', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Tracer Study Lulusan Tahun 2024')
            ->assertJsonPath('data.target_graduation_year', 2024)
            ->assertJsonCount(4, 'data.questions');

        $surveyId = $response->json('data.id');

        // Update survey
        $updateResponse = $this->actingAs($this->humasUser)
            ->putJson("/api/v1/tracer-surveys/{$surveyId}", [
                'title' => 'Tracer Study Lulusan 2024 (Edisi Diperpanjang)',
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.title', 'Tracer Study Lulusan 2024 (Edisi Diperpanjang)');

        // Delete survey
        $deleteResponse = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/tracer-surveys/{$surveyId}");

        $deleteResponse->assertStatus(200);
        $this->assertDatabaseMissing('tracer_surveys', ['id' => $surveyId]);
    }

    public function test_be_hum_02_alumni_can_submit_tracer_survey_response(): void
    {
        $survey = TracerSurvey::create([
            'title' => 'Tracer Study Angkatan 2024',
            'target_graduation_year' => 2024,
            'questions' => [
                ['id' => 'q1', 'question' => 'Status Anda saat ini?'],
                ['id' => 'q2', 'question' => 'Tingkat kepuasan kurikulum?'],
            ],
            'is_active' => true,
        ]);

        $payload = [
            'answers' => [
                'q1' => 'Kuliah di ITB',
                'q2' => 'Sangat Puas (skor 5)',
            ],
        ];

        $response = $this->actingAs($this->alumniUser1)
            ->postJson("/api/v1/tracer-surveys/{$survey->id}/responses", $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.survey_id', $survey->id)
            ->assertJsonPath('data.alumni_id', $this->alumni1->id)
            ->assertJsonPath('data.answers.q1', 'Kuliah di ITB');

        $this->assertDatabaseHas('tracer_responses', [
            'survey_id' => $survey->id,
            'alumni_id' => $this->alumni1->id,
        ]);
    }

    public function test_be_hum_02_cannot_submit_tracer_response_twice(): void
    {
        $survey = TracerSurvey::create([
            'title' => 'Tracer Study Singkat',
            'target_graduation_year' => 2024,
            'questions' => [
                ['id' => 'q1', 'question' => 'Pertanyaan singkat'],
            ],
            'is_active' => true,
        ]);

        TracerResponse::create([
            'survey_id' => $survey->id,
            'alumni_id' => $this->alumni1->id,
            'answers' => ['q1' => 'Jawaban pertama'],
            'submitted_at' => Carbon::now(),
        ]);

        // Attempt second submission
        $response = $this->actingAs($this->alumniUser1)
            ->postJson("/api/v1/tracer-surveys/{$survey->id}/responses", [
                'answers' => ['q1' => 'Jawaban kedua'],
            ]);

        $response->assertStatus(422);
    }

    public function test_be_hum_02_cannot_submit_response_to_inactive_or_expired_survey(): void
    {
        // 1. Inactive survey
        $inactiveSurvey = TracerSurvey::create([
            'title' => 'Survei Nonaktif',
            'questions' => [['id' => 'q1', 'question' => 'Pertanyaan']],
            'is_active' => false,
        ]);

        $res1 = $this->actingAs($this->alumniUser1)
            ->postJson("/api/v1/tracer-surveys/{$inactiveSurvey->id}/responses", [
                'answers' => ['q1' => 'Test'],
            ]);

        $res1->assertStatus(422);

        // 2. Expired survey
        $expiredSurvey = TracerSurvey::create([
            'title' => 'Survei Masa Lalu',
            'questions' => [['id' => 'q1', 'question' => 'Pertanyaan']],
            'start_date' => Carbon::now()->subMonths(3),
            'end_date' => Carbon::now()->subDays(2),
            'is_active' => true,
        ]);

        $res2 = $this->actingAs($this->alumniUser1)
            ->postJson("/api/v1/tracer-surveys/{$expiredSurvey->id}/responses", [
                'answers' => ['q1' => 'Test'],
            ]);

        $res2->assertStatus(422);
    }

    public function test_be_hum_02_cannot_submit_response_if_target_graduation_year_mismatched(): void
    {
        // Survey targeted to graduation year 2022
        $survey2022 = TracerSurvey::create([
            'title' => 'Survei Khusus Lulusan 2022',
            'target_graduation_year' => 2022,
            'questions' => [['id' => 'q1', 'question' => 'Pertanyaan']],
            'is_active' => true,
        ]);

        // Alumni 1 is from 2024
        $response = $this->actingAs($this->alumniUser1)
            ->postJson("/api/v1/tracer-surveys/{$survey2022->id}/responses", [
                'answers' => ['q1' => 'Test'],
            ]);

        $response->assertStatus(422);
    }

    public function test_be_hum_02_staff_can_view_tracer_survey_stats(): void
    {
        $survey = TracerSurvey::create([
            'title' => 'Survei Agregasi Tracer',
            'questions' => [['id' => 'q1', 'question' => 'Pertanyaan']],
            'is_active' => true,
        ]);

        // Response 1 from alumni 1 (status: kuliah)
        TracerResponse::create([
            'survey_id' => $survey->id,
            'alumni_id' => $this->alumni1->id,
            'answers' => ['q1' => 'Jawaban 1'],
            'submitted_at' => Carbon::now(),
        ]);

        // Response 2 from alumni 2 (status: bekerja)
        TracerResponse::create([
            'survey_id' => $survey->id,
            'alumni_id' => $this->alumni2->id,
            'answers' => ['q1' => 'Jawaban 2'],
            'submitted_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->humasUser)
            ->getJson("/api/v1/tracer-surveys/{$survey->id}/stats");

        $response->assertStatus(200)
            ->assertJsonPath('data.survey_id', $survey->id)
            ->assertJsonPath('data.total_responses', 2)
            ->assertJsonPath('data.status_breakdown.kuliah', 1)
            ->assertJsonPath('data.status_breakdown.bekerja', 1);
    }
}
