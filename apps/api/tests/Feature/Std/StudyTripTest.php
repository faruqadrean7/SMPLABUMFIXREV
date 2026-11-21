<?php

namespace Tests\Feature\Std;

use App\Models\File;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\Student;
use App\Models\Trip;
use App\Models\TripConsent;
use App\Models\TripParticipant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudyTripTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $kesiswaanUser;

    protected User $teacherUser;

    protected User $studentUser1;

    protected User $studentUser2;

    protected User $parentUser1;

    protected User $parentUser2;

    protected Student $student1;

    protected Student $student2;

    protected Guardian $guardian1;

    protected Guardian $guardian2;

    protected File $guideFile;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleKesiswaan = Role::firstOrCreate(['code' => 'kesiswaan'], ['name' => 'Staf Kesiswaan', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $roleParent = Role::firstOrCreate(['code' => 'parent'], ['name' => 'Orang Tua / Wali', 'is_system' => true]);

        // Users
        $this->adminUser = User::create([
            'username' => 'admin_trip',
            'email' => 'admin_trip@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->kesiswaanUser = User::create([
            'username' => 'staff_kesiswaan_trip',
            'email' => 'kesiswaan_trip@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->kesiswaanUser->roles()->attach($roleKesiswaan->id);

        $this->teacherUser = User::create([
            'username' => 'guru_pendamping',
            'email' => 'pendamping@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher->id);

        $this->studentUser1 = User::create([
            'username' => 'siswa_trip_1',
            'email' => 'siswa1@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser1->roles()->attach($roleStudent->id);

        $this->studentUser2 = User::create([
            'username' => 'siswa_trip_2',
            'email' => 'siswa2@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser2->roles()->attach($roleStudent->id);

        $this->parentUser1 = User::create([
            'username' => 'ortu_siswa_1',
            'email' => 'ortu1@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->parentUser1->roles()->attach($roleParent->id);

        $this->parentUser2 = User::create([
            'username' => 'ortu_siswa_2',
            'email' => 'ortu2@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->parentUser2->roles()->attach($roleParent->id);

        // Students
        $this->student1 = Student::create([
            'user_id' => $this->studentUser1->id,
            'nis' => '20261050',
            'nisn' => '0098765450',
            'nik_enc' => '3201010101010050',
            'full_name' => 'Dimas Anggara',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2009-02-14',
            'entry_date' => '2026-07-15',
            'status' => 'active',
        ]);

        $this->student2 = Student::create([
            'user_id' => $this->studentUser2->id,
            'nis' => '20261051',
            'nisn' => '0098765451',
            'nik_enc' => '3201010101010051',
            'full_name' => 'Annisa Rahma',
            'gender' => 'P',
            'birth_place' => 'Surabaya',
            'birth_date' => '2009-08-20',
            'entry_date' => '2026-07-15',
            'status' => 'active',
        ]);

        // Guardians
        $this->guardian1 = Guardian::create([
            'user_id' => $this->parentUser1->id,
            'full_name' => 'Hendra Anggara (Ayah Dimas)',
            'phone' => '081234567891',
        ]);
        $this->guardian1->students()->attach($this->student1->id, [
            'relationship' => 'ayah',
            'is_primary' => true,
        ]);

        $this->guardian2 = Guardian::create([
            'user_id' => $this->parentUser2->id,
            'full_name' => 'Ratna Rahma (Ibu Annisa)',
            'phone' => '081234567892',
        ]);
        $this->guardian2->students()->attach($this->student2->id, [
            'relationship' => 'ibu',
            'is_primary' => true,
        ]);

        // Guide File
        $this->guideFile = File::create([
            'bucket' => 'local',
            'object_key' => 'trips/buku_panduan_jogja_2026.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024 * 500,
            'visibility' => 'public',
        ]);
    }

    public function test_be_std_04_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/trips')->assertStatus(401);
        $this->postJson('/api/v1/trips', [])->assertStatus(401);
        $this->getJson('/api/v1/trips/00000000-0000-0000-0000-000000000000')->assertStatus(401);
        $this->getJson('/api/v1/trips/00000000-0000-0000-0000-000000000000/participants')->assertStatus(401);
        $this->getJson('/api/v1/trips/00000000-0000-0000-0000-000000000000/consents')->assertStatus(401);
    }

    public function test_be_std_04_teacher_can_create_and_update_trip(): void
    {
        $payload = [
            'title' => 'Outing Class Edukasi Geologi Karangsambung 2026',
            'destination' => 'Karangsambung, Kebumen',
            'depart_date' => Carbon::now()->addDays(14)->format('Y-m-d H:i:s'),
            'return_date' => Carbon::now()->addDays(16)->format('Y-m-d H:i:s'),
            'route' => [
                ['day' => 1, 'time' => '06:00', 'activity' => 'Kumpul di Lapangan Sekolah & Pengarahan', 'location' => 'SISTLABUM'],
                ['day' => 1, 'time' => '13:00', 'activity' => 'Tiba di Balai Informasi Geodiversitas & Observasi', 'location' => 'Karangsambung'],
                ['day' => 2, 'time' => '08:00', 'activity' => 'Kunjungan Singkapan Batuan Melange & Sungai Luk Ulo', 'location' => 'Kebumen'],
            ],
            'guide_file_id' => $this->guideFile->id,
            'description' => 'Pembelajaran langsung struktur geologi tertua di Jawa untuk kelas X.',
            'cost_notes' => 'Biaya mandiri Rp 350.000 meliputi bus pariwisata, penginapan wisma LIPI, dan 5x makan.',
            'status' => 'direncanakan',
        ];

        $response = $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/trips', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Outing Class Edukasi Geologi Karangsambung 2026')
            ->assertJsonPath('data.destination', 'Karangsambung, Kebumen')
            ->assertJsonPath('data.guide_file_id', $this->guideFile->id);

        $tripId = $response->json('data.id');

        // Update trip
        $updatePayload = [
            'title' => 'Outing Class Edukasi Geologi Karangsambung 2026 (Revisi Jadwal)',
            'status' => 'dibuka',
            'cost_notes' => 'Biaya per siswa Rp 375.000 (tambahan snack & asuransi perjalanan).',
        ];

        $updateResponse = $this->actingAs($this->teacherUser)
            ->putJson("/api/v1/trips/{$tripId}", $updatePayload);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.title', 'Outing Class Edukasi Geologi Karangsambung 2026 (Revisi Jadwal)')
            ->assertJsonPath('data.status', 'dibuka');

        $this->assertDatabaseHas('trips', [
            'id' => $tripId,
            'title' => 'Outing Class Edukasi Geologi Karangsambung 2026 (Revisi Jadwal)',
            'status' => 'dibuka',
        ]);
    }

    public function test_be_std_04_regular_student_cannot_create_or_update_trip(): void
    {
        $payload = [
            'title' => 'Trip Liar Siswa',
            'destination' => 'Pantai Parangtritis',
            'depart_date' => Carbon::now()->addDays(5)->format('Y-m-d H:i:s'),
        ];

        $response = $this->actingAs($this->studentUser1)
            ->postJson('/api/v1/trips', $payload);

        $response->assertStatus(403);
    }

    public function test_be_std_04_can_list_and_filter_trips_by_status_and_search(): void
    {
        Trip::create([
            'title' => 'Study Tour Sejarah Candi Borobudur',
            'destination' => 'Magelang, Jawa Tengah',
            'depart_date' => Carbon::now()->addDays(10)->format('Y-m-d H:i:s'),
            'status' => 'direncanakan',
        ]);

        Trip::create([
            'title' => 'Kunjungan Industri Batan Yogyakarta',
            'destination' => 'Yogyakarta',
            'depart_date' => Carbon::now()->addDays(20)->format('Y-m-d H:i:s'),
            'status' => 'dibuka',
        ]);

        Trip::create([
            'title' => 'Study Lapangan Ekosistem Mangrove',
            'destination' => 'Kulon Progo',
            'depart_date' => Carbon::now()->subDays(10)->format('Y-m-d H:i:s'),
            'status' => 'selesai',
        ]);

        // Filter status 'dibuka'
        $response = $this->actingAs($this->studentUser1)
            ->getJson('/api/v1/trips?status=dibuka');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Kunjungan Industri Batan Yogyakarta');

        // Search keyword 'Borobudur'
        $searchResponse = $this->actingAs($this->parentUser1)
            ->getJson('/api/v1/trips?search=Borobudur');

        $searchResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Study Tour Sejarah Candi Borobudur');
    }

    public function test_be_std_04_cannot_delete_trip_that_is_ongoing_or_completed(): void
    {
        $tripOngoing = Trip::create([
            'title' => 'Kunjungan Museum Nasional',
            'destination' => 'Jakarta Pusat',
            'depart_date' => Carbon::now()->subHours(2)->format('Y-m-d H:i:s'),
            'status' => 'berlangsung',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/trips/{$tripOngoing->id}");

        $response->assertStatus(422);

        $tripCompleted = Trip::create([
            'title' => 'Outing Class Pesisir',
            'destination' => 'Gunungkidul',
            'depart_date' => Carbon::now()->subDays(3)->format('Y-m-d H:i:s'),
            'status' => 'selesai',
        ]);

        $response2 = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/trips/{$tripCompleted->id}");

        $response2->assertStatus(422);

        // Trip with status 'direncanakan' can be deleted
        $tripDraft = Trip::create([
            'title' => 'Draf Rencana Trip Bandung',
            'destination' => 'Bandung',
            'depart_date' => Carbon::now()->addDays(30)->format('Y-m-d H:i:s'),
            'status' => 'direncanakan',
        ]);

        $response3 = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/trips/{$tripDraft->id}");

        $response3->assertStatus(200);
        $this->assertDatabaseMissing('trips', ['id' => $tripDraft->id]);
    }

    public function test_be_std_04_teacher_can_register_and_remove_participant(): void
    {
        $trip = Trip::create([
            'title' => 'Kunjungan Planetarium & Observatorium Bosscha',
            'destination' => 'Lembang, Bandung Barat',
            'depart_date' => Carbon::now()->addDays(7)->format('Y-m-d H:i:s'),
            'status' => 'dibuka',
        ]);

        $payload = [
            'student_id' => $this->student1->id,
            'notes' => 'Perwakilan kelas X-MIPA-1',
        ];

        $response = $this->actingAs($this->teacherUser)
            ->postJson("/api/v1/trips/{$trip->id}/participants", $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.trip_id', $trip->id)
            ->assertJsonPath('data.student_id', $this->student1->id)
            ->assertJsonPath('data.status', 'terdaftar');

        $participantId = $response->json('data.id');

        // Check participant list
        $listResponse = $this->actingAs($this->teacherUser)
            ->getJson("/api/v1/trips/{$trip->id}/participants");

        $listResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student.full_name', 'Dimas Anggara');

        // Remove participant
        $deleteResponse = $this->actingAs($this->teacherUser)
            ->deleteJson("/api/v1/trip-participants/{$participantId}");

        $deleteResponse->assertStatus(200);
        $this->assertDatabaseMissing('trip_participants', ['id' => $participantId]);
    }

    public function test_be_std_04_cannot_register_same_student_twice_to_same_trip(): void
    {
        $trip = Trip::create([
            'title' => 'Studi Banding Budaya Keraton Yogyakarta',
            'destination' => 'Yogyakarta',
            'depart_date' => Carbon::now()->addDays(12)->format('Y-m-d H:i:s'),
            'status' => 'dibuka',
        ]);

        TripParticipant::create([
            'trip_id' => $trip->id,
            'student_id' => $this->student1->id,
            'status' => 'terdaftar',
        ]);

        $payload = [
            'student_id' => $this->student1->id,
            'notes' => 'Coba daftarkan kembali',
        ];

        $response = $this->actingAs($this->teacherUser)
            ->postJson("/api/v1/trips/{$trip->id}/participants", $payload);

        $response->assertStatus(422);
    }

    public function test_be_std_04_parent_can_submit_digital_consent_for_their_child(): void
    {
        $trip = Trip::create([
            'title' => 'Outing Class Konservasi Terumbu Karang',
            'destination' => 'Kepulauan Karimunjawa',
            'depart_date' => Carbon::now()->addDays(20)->format('Y-m-d H:i:s'),
            'status' => 'dibuka',
        ]);

        $participant = TripParticipant::create([
            'trip_id' => $trip->id,
            'student_id' => $this->student1->id,
            'status' => 'terdaftar',
        ]);

        $payload = [
            'student_id' => $this->student1->id,
            'decision' => 'setuju',
            'notes' => 'Diizinkan ikut. Catatan kesehatan: alergi makanan laut udang/kepiting, membawa inhaler asma.',
        ];

        $response = $this->actingAs($this->parentUser1)
            ->postJson("/api/v1/trips/{$trip->id}/consents", $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.decision', 'setuju')
            ->assertJsonPath('data.trip_participant_id', $participant->id)
            ->assertJsonPath('data.guardian_id', $this->guardian1->id);

        $this->assertDatabaseHas('trip_consents', [
            'trip_participant_id' => $participant->id,
            'guardian_id' => $this->guardian1->id,
            'decision' => 'setuju',
        ]);
    }

    public function test_be_std_04_parent_cannot_submit_consent_for_other_student_anti_idor(): void
    {
        $trip = Trip::create([
            'title' => 'Kunjungan Laboratorium Biofarmaka',
            'destination' => 'Bogor',
            'depart_date' => Carbon::now()->addDays(15)->format('Y-m-d H:i:s'),
            'status' => 'dibuka',
        ]);

        TripParticipant::create([
            'trip_id' => $trip->id,
            'student_id' => $this->student2->id,
            'status' => 'terdaftar',
        ]);

        // Parent 1 (guardian of student 1) tries to submit consent for student 2
        $payload = [
            'student_id' => $this->student2->id,
            'decision' => 'tolak',
            'notes' => 'Percobaan memalsukan persetujuan siswa lain',
        ];

        $response = $this->actingAs($this->parentUser1)
            ->postJson("/api/v1/trips/{$trip->id}/consents", $payload);

        $response->assertStatus(403);
    }

    public function test_be_std_04_parent_only_sees_consents_for_their_own_child_anti_idor(): void
    {
        $trip = Trip::create([
            'title' => 'Studi Eksplorasi Ekologi Mangrove Kulonprogo',
            'destination' => 'Kulon Progo',
            'depart_date' => Carbon::now()->addDays(10)->format('Y-m-d H:i:s'),
            'status' => 'dibuka',
        ]);

        $part1 = TripParticipant::create([
            'trip_id' => $trip->id,
            'student_id' => $this->student1->id,
            'status' => 'terdaftar',
        ]);

        $part2 = TripParticipant::create([
            'trip_id' => $trip->id,
            'student_id' => $this->student2->id,
            'status' => 'terdaftar',
        ]);

        TripConsent::create([
            'trip_participant_id' => $part1->id,
            'guardian_id' => $this->guardian1->id,
            'decision' => 'setuju',
            'decided_at' => Carbon::now(),
            'notes' => 'Catatan persetujuan anak 1',
        ]);

        TripConsent::create([
            'trip_participant_id' => $part2->id,
            'guardian_id' => $this->guardian2->id,
            'decision' => 'tolak',
            'decided_at' => Carbon::now(),
            'notes' => 'Catatan penolakan anak 2',
        ]);

        // Parent 1 accesses /trips/{id}/consents -> only sees consent for child 1
        $parent1Response = $this->actingAs($this->parentUser1)
            ->getJson("/api/v1/trips/{$trip->id}/consents");

        $parent1Response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.guardian_id', $this->guardian1->id);

        // Teacher accesses /trips/{id}/consents -> sees all consents
        $teacherResponse = $this->actingAs($this->teacherUser)
            ->getJson("/api/v1/trips/{$trip->id}/consents");

        $teacherResponse->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_be_std_04_cannot_submit_consent_if_student_not_registered_in_trip(): void
    {
        $trip = Trip::create([
            'title' => 'Study Tour Museum Kereta Ambarawa',
            'destination' => 'Ambarawa, Semarang',
            'depart_date' => Carbon::now()->addDays(8)->format('Y-m-d H:i:s'),
            'status' => 'dibuka',
        ]);

        // Student 1 is not registered in this trip
        $payload = [
            'student_id' => $this->student1->id,
            'decision' => 'setuju',
            'notes' => 'Anak saya belum didaftarkan tapi saya ingin menyetujui',
        ];

        $response = $this->actingAs($this->parentUser1)
            ->postJson("/api/v1/trips/{$trip->id}/consents", $payload);

        $response->assertStatus(422);
    }

    public function test_be_std_04_validation_errors_on_invalid_data(): void
    {
        // 1. Missing required title and depart_date
        $invalidTripPayload = [
            'destination' => 'Kota Bandung',
        ];

        $res1 = $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/trips', $invalidTripPayload);

        $res1->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'depart_date']);

        // 2. Invalid consent decision (must be 'setuju' or 'tolak')
        $trip = Trip::create([
            'title' => 'Kunjungan Taman Pintar',
            'destination' => 'Yogyakarta',
            'depart_date' => Carbon::now()->addDays(5)->format('Y-m-d H:i:s'),
            'status' => 'dibuka',
        ]);

        $invalidConsentPayload = [
            'student_id' => $this->student1->id,
            'decision' => 'mungkin_ikut',
        ];

        $res2 = $this->actingAs($this->parentUser1)
            ->postJson("/api/v1/trips/{$trip->id}/consents", $invalidConsentPayload);

        $res2->assertStatus(422)
            ->assertJsonValidationErrors(['decision']);
    }
}
