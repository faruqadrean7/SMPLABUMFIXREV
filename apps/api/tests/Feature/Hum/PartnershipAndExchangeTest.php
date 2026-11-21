<?php

namespace Tests\Feature\Hum;

use App\Models\ExchangeParticipant;
use App\Models\ExchangeProgram;
use App\Models\File;
use App\Models\Partnership;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PartnershipAndExchangeTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $humasUser;

    protected User $teacherUser;

    protected User $studentUser1;

    protected User $studentUser2;

    protected Teacher $teacher;

    protected Student $student1;

    protected Student $student2;

    protected File $mouFile;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleHumas = Role::firstOrCreate(['code' => 'humas'], ['name' => 'Staf Humas & Kemitraan', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        // Users
        $this->adminUser = User::create([
            'username' => 'admin_humas',
            'email' => 'admin_humas@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->humasUser = User::create([
            'username' => 'staff_humas_1',
            'email' => 'humas1@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->humasUser->roles()->attach($roleHumas->id);

        $this->teacherUser = User::create([
            'username' => 'guru_pic_exchange',
            'email' => 'pic_exchange@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher->id);

        $this->studentUser1 = User::create([
            'username' => 'siswa_exchange_1',
            'email' => 'siswa_ex1@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser1->roles()->attach($roleStudent->id);

        $this->studentUser2 = User::create([
            'username' => 'siswa_exchange_2',
            'email' => 'siswa_ex2@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser2->roles()->attach($roleStudent->id);

        // Teacher
        $this->teacher = Teacher::create([
            'user_id' => $this->teacherUser->id,
            'nip' => '198705052011011005',
            'full_name' => 'Bambang Irawan, M.Pd.',
            'gender' => 'L',
        ]);

        // Students
        $this->student1 = Student::create([
            'user_id' => $this->studentUser1->id,
            'nis' => '20262001',
            'nisn' => '0098877661',
            'nik_enc' => '3201010101010091',
            'full_name' => 'Rangga Wijaya',
            'gender' => 'L',
            'birth_place' => 'Surabaya',
            'birth_date' => '2009-01-15',
            'entry_date' => '2026-07-15',
            'status' => 'active',
        ]);

        $this->student2 = Student::create([
            'user_id' => $this->studentUser2->id,
            'nis' => '20262002',
            'nisn' => '0098877662',
            'nik_enc' => '3201010101010092',
            'full_name' => 'Dewi Lestari',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2009-07-22',
            'entry_date' => '2026-07-15',
            'status' => 'active',
        ]);

        // MoU File
        $this->mouFile = File::create([
            'bucket' => 'local',
            'object_key' => 'partnerships/mou_itb_2026.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024 * 750,
            'visibility' => 'private',
        ]);
    }

    public function test_be_hum_01_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/partnerships')->assertStatus(401);
        $this->postJson('/api/v1/partnerships', [])->assertStatus(401);
        $this->getJson('/api/v1/partnerships/expiring')->assertStatus(401);
        $this->getJson('/api/v1/exchange-programs')->assertStatus(401);
        $this->postJson('/api/v1/exchange-programs', [])->assertStatus(401);
    }

    public function test_be_hum_01_staff_can_create_and_update_partnership_mou(): void
    {
        $payload = [
            'partner_name' => 'Institut Teknologi Bandung (ITB)',
            'mou_number' => 'MOU/ITB-SIST/2026/04',
            'partner_type' => 'universitas',
            'contact_person' => 'Prof. Dr. Ir. Ahmad Sudrajat',
            'phone' => '0222500935',
            'email' => 'kerjasama@itb.ac.id',
            'address' => 'Jl. Ganesha No. 10, Bandung',
            'scope' => 'Kerja sama riset sains terapan, pembinaan olimpiade, dan program magang laboratorium siswa.',
            'valid_from' => Carbon::now()->subMonths(1)->format('Y-m-d'),
            'valid_until' => Carbon::now()->addYears(3)->format('Y-m-d'),
            'status' => 'aktif',
            'mou_file_id' => $this->mouFile->id,
            'notes' => 'Diperpanjang secara berkala setiap 3 tahun.',
        ];

        $response = $this->actingAs($this->humasUser)
            ->postJson('/api/v1/partnerships', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.partner_name', 'Institut Teknologi Bandung (ITB)')
            ->assertJsonPath('data.mou_number', 'MOU/ITB-SIST/2026/04')
            ->assertJsonPath('data.partner_type', 'universitas')
            ->assertJsonPath('data.status', 'aktif')
            ->assertJsonPath('data.mou_file_id', $this->mouFile->id);

        $partnershipId = $response->json('data.id');

        // Update partnership
        $updatePayload = [
            'contact_person' => 'Dr. Eng. Wawan Hermawan',
            'phone' => '0222500999',
            'notes' => 'Pembaruan kontak penanggung jawab kemitraan ITB.',
        ];

        $updateResponse = $this->actingAs($this->humasUser)
            ->putJson("/api/v1/partnerships/{$partnershipId}", $updatePayload);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.contact_person', 'Dr. Eng. Wawan Hermawan')
            ->assertJsonPath('data.phone', '0222500999');

        $this->assertDatabaseHas('partnerships', [
            'id' => $partnershipId,
            'contact_person' => 'Dr. Eng. Wawan Hermawan',
        ]);
    }

    public function test_be_hum_01_student_cannot_create_or_update_partnership(): void
    {
        $payload = [
            'partner_name' => 'Mitra Gelap',
            'scope' => 'Kerja sama tidak berwenang',
            'valid_from' => Carbon::now()->format('Y-m-d'),
            'valid_until' => Carbon::now()->addYear()->format('Y-m-d'),
        ];

        $response = $this->actingAs($this->studentUser1)
            ->postJson('/api/v1/partnerships', $payload);

        $response->assertStatus(403);
    }

    public function test_be_hum_01_can_filter_partnerships_by_status_type_and_search(): void
    {
        Partnership::create([
            'partner_name' => 'Universitas Gadjah Mada',
            'mou_number' => 'MOU/UGM/2026/01',
            'partner_type' => 'universitas',
            'scope' => 'Pertukaran siswa dan kuliah umum dosen tamu',
            'valid_from' => Carbon::now()->subMonths(6),
            'valid_until' => Carbon::now()->addMonths(6),
            'status' => 'aktif',
        ]);

        Partnership::create([
            'partner_name' => 'PT Astra Honda Motor',
            'mou_number' => 'MOU/AHM/2025/11',
            'partner_type' => 'industri',
            'scope' => 'Kunjungan industri dan beasiswa teknologi',
            'valid_from' => Carbon::now()->subYears(2),
            'valid_until' => Carbon::now()->subDays(10),
            'status' => 'kedaluwarsa',
        ]);

        Partnership::create([
            'partner_name' => 'Dinas Lingkungan Hidup Kota Malang',
            'mou_number' => 'MOU/DLH/2026/03',
            'partner_type' => 'instansi_pemerintah',
            'scope' => 'Program Adiwiyata dan bank sampah sekolah',
            'valid_from' => Carbon::now()->subMonths(2),
            'valid_until' => Carbon::now()->addYear(),
            'status' => 'aktif',
        ]);

        // Filter status 'aktif' & partner_type 'universitas'
        $response = $this->actingAs($this->humasUser)
            ->getJson('/api/v1/partnerships?status=aktif&partner_type=universitas');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.partner_name', 'Universitas Gadjah Mada');

        // Search query 'Honda'
        $searchResponse = $this->actingAs($this->humasUser)
            ->getJson('/api/v1/partnerships?search=Honda');

        $searchResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.partner_name', 'PT Astra Honda Motor');
    }

    public function test_be_hum_01_can_retrieve_expiring_mou_list(): void
    {
        // 1. Expiring in 15 days (should be caught by 30 days filter)
        Partnership::create([
            'partner_name' => 'Kyoto University Japan',
            'mou_number' => 'MOU/KU/2023/001',
            'partner_type' => 'universitas',
            'scope' => 'Pertukaran pelajar internasional musim gugur',
            'valid_from' => Carbon::now()->subYears(3),
            'valid_until' => Carbon::now()->addDays(15),
            'status' => 'aktif',
        ]);

        // 2. Expiring in 90 days (should NOT be caught by 30 days filter)
        Partnership::create([
            'partner_name' => 'National University of Singapore',
            'mou_number' => 'MOU/NUS/2024/002',
            'partner_type' => 'universitas',
            'scope' => 'Olimpiade sains regional',
            'valid_from' => Carbon::now()->subYear(),
            'valid_until' => Carbon::now()->addDays(90),
            'status' => 'aktif',
        ]);

        $response = $this->actingAs($this->humasUser)
            ->getJson('/api/v1/partnerships/expiring?days=30');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.partner_name', 'Kyoto University Japan');
    }

    public function test_be_hum_01_cannot_delete_partnership_with_active_exchange_programs(): void
    {
        $partnership = Partnership::create([
            'partner_name' => 'Tokyo Metropolitan High School',
            'scope' => 'Sister school & student exchange',
            'valid_from' => Carbon::now()->subMonths(3),
            'valid_until' => Carbon::now()->addYears(2),
            'status' => 'aktif',
        ]);

        ExchangeProgram::create([
            'partnership_id' => $partnership->id,
            'name' => 'Summer Youth Exchange Tokyo 2026',
            'start_date' => Carbon::now()->addMonths(1),
            'end_date' => Carbon::now()->addMonths(2),
            'status' => 'dibuka',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/partnerships/{$partnership->id}");

        $response->assertStatus(422);

        // Deleting partnership without programs succeeds
        $emptyPartnership = Partnership::create([
            'partner_name' => 'Mitra Tanpa Program',
            'scope' => 'Konsultasi kurikulum',
            'valid_from' => Carbon::now()->subMonths(1),
            'valid_until' => Carbon::now()->addYear(),
            'status' => 'aktif',
        ]);

        $deleteResponse = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/partnerships/{$emptyPartnership->id}");

        $deleteResponse->assertStatus(200);
        $this->assertDatabaseMissing('partnerships', ['id' => $emptyPartnership->id]);
    }

    public function test_be_hum_01_staff_can_create_and_update_exchange_program(): void
    {
        $partnership = Partnership::create([
            'partner_name' => 'Nanyang Technological University',
            'scope' => 'Program pertukaran sains pelajar',
            'valid_from' => Carbon::now()->subMonths(2),
            'valid_until' => Carbon::now()->addYears(2),
            'status' => 'aktif',
        ]);

        $payload = [
            'partnership_id' => $partnership->id,
            'name' => 'ASEAN Youth Science Immersion 2026',
            'type' => 'pelajar',
            'destination_country' => 'Singapura',
            'destination_institution' => 'NTU School of Physical and Mathematical Sciences',
            'quota' => 5,
            'description' => 'Program riset intensif sains selama 2 pekan di laboratorium NTU Singapura.',
            'start_date' => Carbon::now()->addMonths(2)->format('Y-m-d'),
            'end_date' => Carbon::now()->addMonths(2)->addDays(14)->format('Y-m-d'),
            'pic_teacher_id' => $this->teacher->id,
            'status' => 'dibuka',
        ];

        $response = $this->actingAs($this->humasUser)
            ->postJson('/api/v1/exchange-programs', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'ASEAN Youth Science Immersion 2026')
            ->assertJsonPath('data.destination_country', 'Singapura')
            ->assertJsonPath('data.quota', 5)
            ->assertJsonPath('data.pic_teacher_id', $this->teacher->id);

        $programId = $response->json('data.id');

        // Update exchange program
        $updatePayload = [
            'quota' => 8,
            'status' => 'berjalan',
        ];

        $updateResponse = $this->actingAs($this->humasUser)
            ->putJson("/api/v1/exchange-programs/{$programId}", $updatePayload);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.quota', 8)
            ->assertJsonPath('data.status', 'berjalan');
    }

    public function test_be_hum_01_cannot_create_exchange_program_under_expired_partnership(): void
    {
        $expiredPartnership = Partnership::create([
            'partner_name' => 'Mitra Kedaluwarsa',
            'scope' => 'Pertukaran lama',
            'valid_from' => Carbon::now()->subYears(3),
            'valid_until' => Carbon::now()->subDays(5),
            'status' => 'kedaluwarsa',
        ]);

        $payload = [
            'partnership_id' => $expiredPartnership->id,
            'name' => 'Program pada Mitra Kedaluwarsa',
            'start_date' => Carbon::now()->addMonth()->format('Y-m-d'),
            'end_date' => Carbon::now()->addMonths(2)->format('Y-m-d'),
        ];

        $response = $this->actingAs($this->humasUser)
            ->postJson('/api/v1/exchange-programs', $payload);

        $response->assertStatus(422);
    }

    public function test_be_hum_01_staff_can_register_and_update_exchange_participant(): void
    {
        $partnership = Partnership::create([
            'partner_name' => 'Melbourne High School Australia',
            'scope' => 'Cross-culture student immersion',
            'valid_from' => Carbon::now()->subMonths(1),
            'valid_until' => Carbon::now()->addYears(2),
            'status' => 'aktif',
        ]);

        $program = ExchangeProgram::create([
            'partnership_id' => $partnership->id,
            'name' => 'Melbourne Immersion 2026',
            'quota' => 3,
            'start_date' => Carbon::now()->addMonths(3),
            'end_date' => Carbon::now()->addMonths(3)->addDays(20),
            'status' => 'dibuka',
        ]);

        // Register student 1
        $regPayload = [
            'student_id' => $this->student1->id,
            'status' => 'terdaftar',
            'passport_number' => 'X1234567',
            'notes' => 'Nilai TOEFL Jr 850, rekomendasi guru bahasa Inggris.',
        ];

        $regResponse = $this->actingAs($this->humasUser)
            ->postJson("/api/v1/exchange-programs/{$program->id}/participants", $regPayload);

        $regResponse->assertStatus(201)
            ->assertJsonPath('data.student_id', $this->student1->id)
            ->assertJsonPath('data.passport_number', 'X1234567')
            ->assertJsonPath('data.status', 'terdaftar');

        $participantId = $regResponse->json('data.id');

        // Check list participants
        $listResponse = $this->actingAs($this->humasUser)
            ->getJson("/api/v1/exchange-programs/{$program->id}/participants");

        $listResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student.full_name', 'Rangga Wijaya');

        // Update status participant
        $updatePartPayload = [
            'status' => 'lolos_seleksi',
            'evaluation_notes' => 'Lolos wawancara dan tes kesehatan tahap 1.',
        ];

        $updatePartResponse = $this->actingAs($this->humasUser)
            ->putJson("/api/v1/exchange-participants/{$participantId}", $updatePartPayload);

        $updatePartResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'lolos_seleksi')
            ->assertJsonPath('data.evaluation_notes', 'Lolos wawancara dan tes kesehatan tahap 1.');

        // Remove participant
        $delResponse = $this->actingAs($this->humasUser)
            ->deleteJson("/api/v1/exchange-participants/{$participantId}");

        $delResponse->assertStatus(200);
        $this->assertDatabaseMissing('exchange_participants', ['id' => $participantId]);
    }

    public function test_be_hum_01_cannot_register_same_student_twice_or_exceed_quota(): void
    {
        $partnership = Partnership::create([
            'partner_name' => 'Seoul Global High School',
            'scope' => 'Korea-Indonesia Student Camp',
            'valid_from' => Carbon::now()->subMonths(1),
            'valid_until' => Carbon::now()->addYear(),
            'status' => 'aktif',
        ]);

        // Program with quota 1
        $program = ExchangeProgram::create([
            'partnership_id' => $partnership->id,
            'name' => 'Winter Camp Seoul',
            'quota' => 1,
            'start_date' => Carbon::now()->addMonths(4),
            'end_date' => Carbon::now()->addMonths(4)->addDays(10),
            'status' => 'dibuka',
        ]);

        ExchangeParticipant::create([
            'program_id' => $program->id,
            'student_id' => $this->student1->id,
            'status' => 'terdaftar',
        ]);

        // 1. Try to register same student 1 again -> 422
        $resDuplicate = $this->actingAs($this->humasUser)
            ->postJson("/api/v1/exchange-programs/{$program->id}/participants", [
                'student_id' => $this->student1->id,
            ]);

        $resDuplicate->assertStatus(422);

        // 2. Try to register student 2 when quota (1) is already full -> 422
        $resQuotaFull = $this->actingAs($this->humasUser)
            ->postJson("/api/v1/exchange-programs/{$program->id}/participants", [
                'student_id' => $this->student2->id,
            ]);

        $resQuotaFull->assertStatus(422);
    }

    public function test_be_hum_01_cannot_delete_exchange_program_that_is_ongoing(): void
    {
        $partnership = Partnership::create([
            'partner_name' => 'Waseda University',
            'scope' => 'Pre-college workshop',
            'valid_from' => Carbon::now()->subMonths(1),
            'valid_until' => Carbon::now()->addYears(2),
            'status' => 'aktif',
        ]);

        $programOngoing = ExchangeProgram::create([
            'partnership_id' => $partnership->id,
            'name' => 'Pre-College Science Workshop Tokyo',
            'start_date' => Carbon::now()->subDays(5),
            'end_date' => Carbon::now()->addDays(10),
            'status' => 'berjalan',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/exchange-programs/{$programOngoing->id}");

        $response->assertStatus(422);
    }

    public function test_be_hum_01_validation_errors_on_invalid_dates_and_required_fields(): void
    {
        // 1. valid_until before valid_from
        $invalidPayload = [
            'partner_name' => 'Mitra Tanggal Terbalik',
            'scope' => 'Testing validation',
            'valid_from' => Carbon::now()->format('Y-m-d'),
            'valid_until' => Carbon::now()->subDays(10)->format('Y-m-d'),
        ];

        $res1 = $this->actingAs($this->humasUser)
            ->postJson('/api/v1/partnerships', $invalidPayload);

        $res1->assertStatus(422)
            ->assertJsonValidationErrors(['valid_until']);

        // 2. Missing required fields on exchange program
        $res2 = $this->actingAs($this->humasUser)
            ->postJson('/api/v1/exchange-programs', [
                'destination_country' => 'Jepang',
            ]);

        $res2->assertStatus(422)
            ->assertJsonValidationErrors(['partnership_id', 'name', 'start_date', 'end_date']);
    }
}
