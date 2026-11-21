<?php

namespace Tests\Feature\Std;

use App\Models\Achievement;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\File;
use App\Models\Guardian;
use App\Models\PointType;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentPoint;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompetitionAndAchievementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $kesiswaanUser;

    protected User $teacherUser;

    protected User $studentUser;

    protected Teacher $teacher;

    protected Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleKesiswaan = Role::firstOrCreate(['code' => 'kesiswaan'], ['name' => 'Staf Kesiswaan', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $this->adminUser = User::create([
            'username' => 'admin_lomba',
            'email' => 'admin_lomba@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->kesiswaanUser = User::create([
            'username' => 'staff_kesiswaan_3',
            'email' => 'kesiswaan3@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->kesiswaanUser->roles()->attach($roleKesiswaan->id);

        $this->teacherUser = User::create([
            'username' => 'guru_pembimbing',
            'email' => 'pembimbing@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher->id);

        $this->studentUser = User::create([
            'username' => '20261020',
            'email' => 'bayu_siswa@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($roleStudent->id);

        $this->teacher = Teacher::create([
            'user_id' => $this->teacherUser->id,
            'nip' => '198808082012011008',
            'full_name' => 'Dr. Suryo Prasetyo, M.Si',
            'gender' => 'L',
        ]);

        $this->student = Student::create([
            'user_id' => $this->studentUser->id,
            'nis' => '20261020',
            'nisn' => '0098765488',
            'nik_enc' => '3201010101010088',
            'full_name' => 'Bayu Pratama',
            'gender' => 'L',
            'birth_place' => 'Semarang',
            'birth_date' => '2009-04-10',
            'entry_date' => '2026-07-15',
            'status' => 'active',
        ]);
    }

    public function test_be_std_03_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/competitions')->assertStatus(401);
        $this->getJson('/api/v1/achievements')->assertStatus(401);
    }

    public function test_be_std_03_teacher_can_create_and_update_competition(): void
    {
        $payload = [
            'name' => 'Olimpiade Sains Nasional (OSN) Matematika',
            'organizer' => 'Puspresnas Kemdikbudristek',
            'level' => 'Nasional',
            'category' => 'akademik',
            'held_at' => Carbon::now()->addMonths(1)->toDateString(),
            'description' => 'Ajang talenta sains nasional bidang matematika jenjang SMA/MA',
        ];

        $res = $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/competitions', $payload);

        $res->assertStatus(201)
            ->assertJsonPath('data.name', 'Olimpiade Sains Nasional (OSN) Matematika')
            ->assertJsonPath('data.level', 'Nasional');

        $compId = $res->json('data.id');

        // Update
        $updateRes = $this->actingAs($this->teacherUser)
            ->putJson("/api/v1/competitions/{$compId}", [
                'organizer' => 'BPTI Kemdikbudristek',
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.organizer', 'BPTI Kemdikbudristek');
    }

    public function test_be_std_03_regular_student_cannot_create_competition(): void
    {
        $this->actingAs($this->studentUser)
            ->postJson('/api/v1/competitions', [
                'name' => 'Turnamen E-Sport Ilegal',
                'level' => 'Kota',
                'held_at' => Carbon::now()->toDateString(),
            ])
            ->assertStatus(403);
    }

    public function test_be_std_03_teacher_can_register_student_delegation_entry(): void
    {
        $comp = Competition::create([
            'name' => 'Lomba Debat Bahasa Inggris (NEDS)',
            'level' => 'Provinsi',
            'held_at' => Carbon::now()->addWeeks(2)->toDateString(),
        ]);

        $res = $this->actingAs($this->teacherUser)
            ->postJson("/api/v1/competitions/{$comp->id}/entries", [
                'student_id' => $this->student->id,
                'coach_teacher_id' => $this->teacher->id,
                'notes' => 'Delegasi utama mewakili sekolah',
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.competition_id', $comp->id)
            ->assertJsonPath('data.student_id', $this->student->id);

        $this->assertDatabaseHas('competition_entries', [
            'competition_id' => $comp->id,
            'student_id' => $this->student->id,
        ]);
    }

    public function test_be_std_03_cannot_register_same_student_twice_in_competition(): void
    {
        $comp = Competition::create([
            'name' => 'Lomba Catur Cepat Pelajar',
            'level' => 'Kota',
            'held_at' => Carbon::now()->addDays(5)->toDateString(),
        ]);

        CompetitionEntry::create([
            'competition_id' => $comp->id,
            'student_id' => $this->student->id,
        ]);

        // Coba daftarkan kembali siswa yang sama
        $res = $this->actingAs($this->teacherUser)
            ->postJson("/api/v1/competitions/{$comp->id}/entries", [
                'student_id' => $this->student->id,
            ]);

        $res->assertStatus(422)
            ->assertJsonPath('error.code', 'HTTP_ERROR');
    }

    public function test_be_std_03_teacher_can_update_entry_result(): void
    {
        $comp = Competition::create([
            'name' => 'Lomba Desain Poster Lingkungan',
            'level' => 'Kota',
            'held_at' => Carbon::now()->subDays(2)->toDateString(),
        ]);

        $entry = CompetitionEntry::create([
            'competition_id' => $comp->id,
            'student_id' => $this->student->id,
        ]);

        $res = $this->actingAs($this->teacherUser)
            ->putJson("/api/v1/competition-entries/{$entry->id}", [
                'result' => 'Juara 1',
                'notes' => 'Memperoleh piala dan uang pembinaan',
            ]);

        $res->assertStatus(200)
            ->assertJsonPath('data.result', 'Juara 1');

        $this->assertDatabaseHas('competition_entries', [
            'id' => $entry->id,
            'result' => 'Juara 1',
        ]);
    }

    public function test_be_std_03_recording_achievement_automatically_records_student_point(): void
    {
        $comp = Competition::create([
            'name' => 'Olimpiade Biologi Nasional',
            'level' => 'Nasional',
            'held_at' => Carbon::now()->subDays(3)->toDateString(),
        ]);

        // Daftarkan delegasi terlebih dahulu
        $entry = CompetitionEntry::create([
            'competition_id' => $comp->id,
            'student_id' => $this->student->id,
        ]);

        $certFile = File::create([
            'bucket' => 'local',
            'object_key' => 'certs/osn_bio_gold.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'visibility' => 'private',
        ]);

        $trophyFile = File::create([
            'bucket' => 'local',
            'object_key' => 'trophies/piala_emas_osn.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 2048,
            'visibility' => 'public',
        ]);

        $payload = [
            'student_id' => $this->student->id,
            'competition_id' => $comp->id,
            'title' => 'Medali Emas Olimpiade Biologi Nasional 2026',
            'rank_level' => 'Juara 1',
            'achieved_at' => Carbon::now()->toDateString(),
            'certificate_file_id' => $certFile->id,
            'photo_file_id' => $trophyFile->id,
            'points_awarded' => 50,
            'notes' => 'Peringkat 1 dari 150 finalis nasional',
        ];

        $res = $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/achievements', $payload);

        $res->assertStatus(201)
            ->assertJsonPath('data.title', 'Medali Emas Olimpiade Biologi Nasional 2026')
            ->assertJsonPath('data.points_awarded', 50);

        $achievementId = $res->json('data.id');

        // Pastikan entry kompetisi ter-update otomatis dengan achievement_id
        $this->assertEquals($achievementId, $entry->fresh()->achievement_id);
        $this->assertEquals('Juara 1', $entry->fresh()->result);

        // Pastikan poin prestasi otomatis tercatat di student_points
        $this->assertDatabaseHas('student_points', [
            'student_id' => $this->student->id,
            'recorded_by' => $this->teacherUser->id,
            'evidence_file_id' => $certFile->id,
        ]);

        // Pastikan poin prestasi di student_points terhubung ke jenis poin ber-kind 'prestasi'
        $recordedPoint = StudentPoint::where('student_id', $this->student->id)->latest()->first();
        $this->assertNotNull($recordedPoint);
        $this->assertEquals('prestasi', $recordedPoint->pointType->kind);
    }

    public function test_be_std_03_anyone_authenticated_can_view_trophy_gallery_and_achievements(): void
    {
        $trophy = File::create([
            'bucket' => 'local',
            'object_key' => 'trophies/piala_futsal.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'visibility' => 'public',
        ]);

        Achievement::create([
            'student_id' => $this->student->id,
            'title' => 'Juara 1 Lomba Band Pelajar',
            'rank_level' => 'Juara 1',
            'achieved_at' => Carbon::now()->toDateString(),
            'photo_file_id' => $trophy->id,
            'points_awarded' => 30,
        ]);

        // Siswa dapat melihat daftar prestasi dan galeri piala
        $res = $this->actingAs($this->studentUser)
            ->getJson('/api/v1/achievements');

        $res->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Juara 1 Lomba Band Pelajar')
            ->assertJsonPath('data.0.photo_file_id', $trophy->id);
    }

    public function test_be_std_03_student_can_submit_own_achievement_with_pending_status(): void
    {
        $res = $this->actingAs($this->studentUser)
            ->postJson('/api/v1/achievements', [
                'student_id' => $this->student->id,
                'title' => 'Juara 1 Lomba Cipta Puisi Nasional 2026',
                'rank_level' => 'Juara 1',
                'achieved_at' => Carbon::now()->toDateString(),
                'notes' => 'Diselenggarakan oleh Kemdikbudristek',
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.verification_status', 'pending')
            ->assertJsonPath('data.student_point_id', null);

        $this->assertDatabaseHas('achievements', [
            'student_id' => $this->student->id,
            'title' => 'Juara 1 Lomba Cipta Puisi Nasional 2026',
            'verification_status' => 'pending',
            'student_point_id' => null,
        ]);
    }

    public function test_be_std_03_student_cannot_submit_achievement_for_another_student(): void
    {
        $otherStudent = Student::create([
            'nis' => '20261021',
            'nisn' => '0098765489',
            'nik_enc' => '3201010101010089',
            'full_name' => 'Dimas Anggara',
            'gender' => 'L',
            'birth_place' => 'Surabaya',
            'birth_date' => '2009-05-12',
            'entry_date' => '2026-07-15',
            'status' => 'active',
        ]);

        $this->actingAs($this->studentUser)
            ->postJson('/api/v1/achievements', [
                'student_id' => $otherStudent->id,
                'title' => 'Klaim Juara Teman',
                'rank_level' => 'Juara 1',
                'achieved_at' => Carbon::now()->toDateString(),
            ])
            ->assertStatus(403);
    }

    public function test_be_std_03_student_cannot_self_verify_or_award_points(): void
    {
        $res = $this->actingAs($this->studentUser)
            ->postJson('/api/v1/achievements', [
                'student_id' => $this->student->id,
                'title' => 'Klaim Langsung Verified',
                'rank_level' => 'Juara 1',
                'achieved_at' => Carbon::now()->toDateString(),
                'verification_status' => 'verified',
                'points_awarded' => 100,
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.verification_status', 'pending')
            ->assertJsonPath('data.student_point_id', null);

        $this->assertDatabaseHas('achievements', [
            'student_id' => $this->student->id,
            'title' => 'Klaim Langsung Verified',
            'verification_status' => 'pending',
            'student_point_id' => null,
        ]);
    }

    public function test_be_std_03_guardian_can_submit_achievement_for_own_child(): void
    {
        $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Wali Murid', 'is_system' => true]);
        $guardianUser = User::create([
            'username' => 'wali_bayu',
            'email' => 'wali_bayu@gmail.com',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $guardianUser->roles()->attach($roleGuardian->id);

        $guardian = Guardian::create([
            'user_id' => $guardianUser->id,
            'full_name' => 'Bambang Pratama',
            'phone' => '081234567890',
        ]);

        $guardian->students()->attach($this->student->id, [
            'relationship' => 'ayah',
            'is_primary' => true,
        ]);

        $res = $this->actingAs($guardianUser)
            ->postJson('/api/v1/achievements', [
                'student_id' => $this->student->id,
                'title' => 'Juara 2 Robotika Pelajar se-Jawa Timur',
                'rank_level' => 'Juara 2',
                'achieved_at' => Carbon::now()->toDateString(),
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.verification_status', 'pending')
            ->assertJsonPath('data.student_point_id', null);

        $this->assertDatabaseHas('achievements', [
            'student_id' => $this->student->id,
            'title' => 'Juara 2 Robotika Pelajar se-Jawa Timur',
            'verification_status' => 'pending',
        ]);
    }

    public function test_be_std_03_guardian_cannot_submit_achievement_for_unauthorized_student(): void
    {
        $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Wali Murid', 'is_system' => true]);
        $guardianUser = User::create([
            'username' => 'wali_orang_lain',
            'email' => 'wali_orang_lain@gmail.com',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $guardianUser->roles()->attach($roleGuardian->id);

        Guardian::create([
            'user_id' => $guardianUser->id,
            'full_name' => 'Pak Joko',
            'phone' => '081234567899',
        ]);

        $this->actingAs($guardianUser)
            ->postJson('/api/v1/achievements', [
                'student_id' => $this->student->id,
                'title' => 'Klaim Ilegal Anak Orang Lain',
                'rank_level' => 'Juara 3',
                'achieved_at' => Carbon::now()->toDateString(),
            ])
            ->assertStatus(403);
    }

    public function test_be_std_03_student_can_update_and_delete_own_pending_achievement(): void
    {
        $ach = Achievement::create([
            'student_id' => $this->student->id,
            'title' => 'Draf Pengajuan Prestasi',
            'rank_level' => 'Harapan 1',
            'achieved_at' => Carbon::now()->toDateString(),
            'verification_status' => 'pending',
        ]);

        // Student updates pending achievement
        $this->actingAs($this->studentUser)
            ->putJson("/api/v1/achievements/{$ach->id}", [
                'title' => 'Revisi Pengajuan Prestasi',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.title', 'Revisi Pengajuan Prestasi');

        // Student deletes own pending achievement
        $this->actingAs($this->studentUser)
            ->deleteJson("/api/v1/achievements/{$ach->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('achievements', ['id' => $ach->id]);
    }

    public function test_be_std_03_student_cannot_update_or_delete_verified_achievement(): void
    {
        $ach = Achievement::create([
            'student_id' => $this->student->id,
            'title' => 'Prestasi Resmi Terverifikasi',
            'rank_level' => 'Juara 1',
            'achieved_at' => Carbon::now()->toDateString(),
            'verification_status' => 'verified',
        ]);

        // Student attempts to update verified achievement -> 403
        $this->actingAs($this->studentUser)
            ->putJson("/api/v1/achievements/{$ach->id}", [
                'title' => 'Mencoba Mengubah Prestasi Terverifikasi',
            ])
            ->assertStatus(403);

        // Student attempts to delete verified achievement -> 403
        $this->actingAs($this->studentUser)
            ->deleteJson("/api/v1/achievements/{$ach->id}")
            ->assertStatus(403);
    }

    public function test_be_std_03_student_and_guardian_cannot_verify_achievement(): void
    {
        $ach = Achievement::create([
            'student_id' => $this->student->id,
            'title' => 'Pengajuan Lomba Menunggu Persetujuan',
            'rank_level' => 'Juara 2',
            'achieved_at' => Carbon::now()->toDateString(),
            'verification_status' => 'pending',
        ]);

        $this->actingAs($this->studentUser)
            ->patchJson("/api/v1/achievements/{$ach->id}/verify", [
                'status' => 'verified',
            ])
            ->assertStatus(403);
    }

    public function test_be_std_03_admin_verification_is_idempotent_and_does_not_duplicate_points(): void
    {
        $ach = Achievement::create([
            'student_id' => $this->student->id,
            'title' => 'Olimpiade Kimia Nasional 2026',
            'rank_level' => 'Juara 1',
            'achieved_at' => Carbon::now()->toDateString(),
            'verification_status' => 'pending',
            'points_awarded' => 30,
        ]);

        // First verification
        $res1 = $this->actingAs($this->kesiswaanUser)
            ->patchJson("/api/v1/achievements/{$ach->id}/verify", [
                'status' => 'verified',
            ]);

        $res1->assertStatus(200)
            ->assertJsonPath('data.verification_status', 'verified');

        $pointId1 = $res1->json('data.student_point_id');
        $this->assertNotNull($pointId1);

        $initialPointsCount = StudentPoint::where('student_id', $this->student->id)->count();
        $this->assertEquals(1, $initialPointsCount);

        // Second duplicate verification (idempotent test)
        $res2 = $this->actingAs($this->kesiswaanUser)
            ->patchJson("/api/v1/achievements/{$ach->id}/verify", [
                'status' => 'verified',
            ]);

        $res2->assertStatus(200)
            ->assertJsonPath('data.verification_status', 'verified')
            ->assertJsonPath('data.student_point_id', $pointId1);

        // Points must not be duplicated
        $finalPointsCount = StudentPoint::where('student_id', $this->student->id)->count();
        $this->assertEquals(1, $finalPointsCount);
    }

    public function test_be_std_03_admin_rejection_stores_reason_and_revokes_point_safely(): void
    {
        $pointType = PointType::firstOrCreate(
            ['kind' => 'prestasi', 'name' => 'Kedisiplinan'],
            ['points' => 10, 'severity' => 'ringan']
        );

        // Create an unrelated student point that MUST NOT be touched
        $unrelatedPoint = StudentPoint::create([
            'student_id' => $this->student->id,
            'point_type_id' => $pointType->id,
            'recorded_by' => $this->kesiswaanUser->id,
            'point_date' => Carbon::now()->toDateString(),
            'notes' => 'Poin apresiasi kedisiplinan upacara bendera',
        ]);

        $ach = Achievement::create([
            'student_id' => $this->student->id,
            'title' => 'Lomba Fiktif Tanpa Bukti',
            'rank_level' => 'Juara 1',
            'achieved_at' => Carbon::now()->toDateString(),
            'verification_status' => 'pending',
            'points_awarded' => 25,
        ]);

        // First verify to award points
        $resVerify = $this->actingAs($this->kesiswaanUser)
            ->patchJson("/api/v1/achievements/{$ach->id}/verify", [
                'status' => 'verified',
            ]);
        $achPointId = $resVerify->json('data.student_point_id');
        $this->assertNotNull($achPointId);

        // Now reject
        $resReject = $this->actingAs($this->kesiswaanUser)
            ->patchJson("/api/v1/achievements/{$ach->id}/verify", [
                'status' => 'rejected',
                'rejection_reason' => 'Sertifikat tidak sah dan tidak ada cap stempel panitia.',
            ]);

        $resReject->assertStatus(200)
            ->assertJsonPath('data.verification_status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Sertifikat tidak sah dan tidak ada cap stempel panitia.')
            ->assertJsonPath('data.student_point_id', null);

        // Associated point deleted
        $this->assertDatabaseMissing('student_points', ['id' => $achPointId]);

        // Unrelated point is preserved intact!
        $this->assertDatabaseHas('student_points', ['id' => $unrelatedPoint->id]);
    }

    public function test_be_std_03_staff_can_delete_achievement(): void
    {
        $ach = Achievement::create([
            'student_id' => $this->student->id,
            'title' => 'Juara Kaligrafi',
            'rank_level' => 'Harapan 2',
            'achieved_at' => Carbon::now()->toDateString(),
        ]);

        $this->actingAs($this->kesiswaanUser)
            ->deleteJson("/api/v1/achievements/{$ach->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('achievements', ['id' => $ach->id]);
    }

    public function test_be_std_03_cannot_register_inactive_student_to_competition(): void
    {
        $inactiveStudent = Student::create([
            'nis' => '20269999',
            'nisn' => '0098765999',
            'nik_enc' => '3201010101019999',
            'full_name' => 'Siswa Tidak Aktif',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2009-01-01',
            'entry_date' => '2026-07-15',
            'status' => 'graduated',
        ]);

        $comp = Competition::create([
            'name' => 'Lomba Catur Cepat',
            'level' => 'Kota',
            'held_at' => Carbon::now()->addDays(5)->toDateString(),
        ]);

        $res = $this->actingAs($this->teacherUser)
            ->postJson("/api/v1/competitions/{$comp->id}/entries", [
                'student_id' => $inactiveStudent->id,
            ]);

        $res->assertStatus(422)
            ->assertJsonPath('error.message', 'Hanya siswa berstatus aktif yang dapat didaftarkan sebagai delegasi lomba.');
    }

    public function test_be_std_03_cannot_register_student_across_different_schools(): void
    {
        $schoolA = (string) Str::uuid();
        $schoolB = (string) Str::uuid();

        $this->teacherUser->update(['school_id' => $schoolA]);
        $this->student->update(['school_id' => $schoolB]);

        $comp = Competition::create([
            'school_id' => $schoolA,
            'name' => 'Olimpiade Kimia Provinsi',
            'level' => 'Provinsi',
            'held_at' => Carbon::now()->addDays(10)->toDateString(),
        ]);

        $res = $this->actingAs($this->teacherUser)
            ->postJson("/api/v1/competitions/{$comp->id}/entries", [
                'student_id' => $this->student->id,
            ]);

        $res->assertStatus(403);
    }

    public function test_be_std_03_registration_deadline_cannot_be_after_held_at(): void
    {
        $res = $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/competitions', [
                'name' => 'Kompetisi Debat Bahasa Inggris',
                'level' => 'Nasional',
                'held_at' => '2026-11-10',
                'registration_deadline' => '2026-11-15', // Ilegal: setelah tanggal lomba
            ]);

        $res->assertStatus(422);
    }

    public function test_be_std_03_duplicate_achievement_is_prevented_with_422(): void
    {
        $payload = [
            'student_id' => $this->student->id,
            'title' => 'Juara 1 Lomba Pidato Bahasa Jawa',
            'rank_level' => 'Juara 1',
            'achieved_at' => '2026-10-10',
        ];

        // Pertama: berhasil
        $res1 = $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/achievements', $payload);
        $res1->assertStatus(201);

        // Kedua dengan data yang sama persis: gagal 422
        $res2 = $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/achievements', $payload);
        $res2->assertStatus(422)
            ->assertJsonPath('error.message', 'Prestasi serupa untuk siswa ini pada tanggal dan tingkatan yang sama sudah tercatat sebelumnya.');
    }

    public function test_be_std_03_verify_achievement_workflow_awards_point_on_verify_and_revokes_on_reject(): void
    {
        // Buat prestasi dengan status pending
        $payload = [
            'student_id' => $this->student->id,
            'title' => 'Finalis Lomba Robotika Nasional',
            'rank_level' => 'Finalis',
            'achieved_at' => '2026-10-12',
            'verification_status' => 'pending',
        ];

        $createRes = $this->actingAs($this->teacherUser)
            ->postJson('/api/v1/achievements', $payload);

        $createRes->assertStatus(201)
            ->assertJsonPath('data.verification_status', 'pending');

        $achId = $createRes->json('data.id');

        // Pastikan belum ada poin tercatat karena status pending
        $this->assertDatabaseMissing('student_points', [
            'student_id' => $this->student->id,
            'point_date' => '2026-10-12',
        ]);

        // Verifikasi oleh kesiswaan -> Poin otomatis diberikan
        $verifyRes = $this->actingAs($this->kesiswaanUser)
            ->patchJson("/api/v1/achievements/{$achId}/verify", [
                'status' => 'verified',
            ]);

        $verifyRes->assertStatus(200)
            ->assertJsonPath('data.verification_status', 'verified');
        $this->assertEquals($this->kesiswaanUser->id, $verifyRes->json('data.verified_by.id') ?? $verifyRes->json('data.verified_by'));

        $this->assertDatabaseHas('student_points', [
            'student_id' => $this->student->id,
        ]);
        $point = StudentPoint::where('student_id', $this->student->id)->latest()->first();
        $this->assertNotNull($point);
        $this->assertEquals('2026-10-12', Carbon::parse($point->point_date)->toDateString());

        // Jika kemudian ditolak karena sertifikat palsu -> Poin otomatis dicabut
        $rejectRes = $this->actingAs($this->kesiswaanUser)
            ->patchJson("/api/v1/achievements/{$achId}/verify", [
                'status' => 'rejected',
                'rejection_reason' => 'Sertifikat tidak terdaftar pada laman puspresnas.',
            ]);

        $rejectRes->assertStatus(200)
            ->assertJsonPath('data.verification_status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Sertifikat tidak terdaftar pada laman puspresnas.');

        $this->assertDatabaseMissing('student_points', [
            'student_id' => $this->student->id,
        ]);
    }

    public function test_be_std_03_competitions_summary_returns_aggregated_metrics(): void
    {
        $comp = Competition::create([
            'name' => 'Lomba Renang Antar Pelajar',
            'level' => 'Kota',
            'held_at' => Carbon::now()->toDateString(),
            'status' => 'pendaftaran',
        ]);

        Achievement::create([
            'student_id' => $this->student->id,
            'competition_id' => $comp->id,
            'title' => 'Medali Emas 100m Gaya Bebas',
            'rank_level' => 'Medali Emas',
            'achieved_at' => Carbon::now()->toDateString(),
            'verification_status' => 'verified',
        ]);

        $res = $this->actingAs($this->teacherUser)
            ->getJson('/api/v1/competitions/summary');

        $res->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_competitions',
                    'active_competitions',
                    'total_entries',
                    'total_achievements',
                    'verified_achievements',
                    'pending_verification',
                    'medals' => [
                        'emas',
                        'perak',
                        'perunggu',
                        'finalis',
                    ],
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, $res->json('data.total_competitions'));
        $this->assertGreaterThanOrEqual(1, $res->json('data.medals.emas'));
    }

    public function test_be_std_03_guardian_cannot_list_unauthorized_child_achievements(): void
    {
        $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Wali Murid', 'is_system' => true]);
        $guardianUser = User::create([
            'username' => 'wali_terbatas',
            'email' => 'wali_terbatas@gmail.com',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $guardianUser->roles()->attach($roleGuardian->id);

        $guardian = Guardian::create([
            'user_id' => $guardianUser->id,
            'full_name' => 'Wali Terbatas',
            'phone' => '081234567888',
        ]);

        $unauthorizedStudent = Student::create([
            'nis' => '20268888',
            'nisn' => '0098765888',
            'nik_enc' => '3201010101018888',
            'full_name' => 'Siswa Bukan Anak',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2009-06-15',
            'entry_date' => '2026-07-15',
            'status' => 'active',
        ]);

        // Attempt to query achievements of non-custody student
        $this->actingAs($guardianUser)
            ->getJson("/api/v1/achievements?student_id={$unauthorizedStudent->id}")
            ->assertStatus(403);
    }

    public function test_be_std_03_student_cannot_view_other_student_pending_achievement(): void
    {
        $otherStudent = Student::create([
            'nis' => '20267777',
            'nisn' => '0098765777',
            'nik_enc' => '3201010101017777',
            'full_name' => 'Siswa Lain Rahasia',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2009-07-20',
            'entry_date' => '2026-07-15',
            'status' => 'active',
        ]);

        $pendingAch = Achievement::create([
            'student_id' => $otherStudent->id,
            'title' => 'Pengajuan Rahasia Siswa Lain',
            'rank_level' => 'Juara 1',
            'achieved_at' => Carbon::now()->toDateString(),
            'verification_status' => 'pending',
        ]);

        // Student tries to view detail of another student's pending achievement -> 403
        $this->actingAs($this->studentUser)
            ->getJson("/api/v1/achievements/{$pendingAch->id}")
            ->assertStatus(403);

        // Student lists achievements -> must not contain other student's pending achievement
        $listRes = $this->actingAs($this->studentUser)
            ->getJson('/api/v1/achievements');

        $listRes->assertStatus(200);
        $ids = collect($listRes->json('data'))->pluck('id')->all();
        $this->assertNotContains($pendingAch->id, $ids);
    }

    public function test_be_std_03_cross_school_user_cannot_access_or_submit_achievement(): void
    {
        $schoolAId = (string) Str::uuid();
        $schoolBId = (string) Str::uuid();

        $this->student->update(['school_id' => $schoolAId]);

        $crossSchoolUser = User::create([
            'username' => 'guru_sekolah_b',
            'email' => 'guru_b@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'school_id' => $schoolBId,
            'status' => 'active',
        ]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $crossSchoolUser->roles()->attach($roleTeacher->id);

        // Cannot create achievement for student of School A
        $this->actingAs($crossSchoolUser)
            ->postJson('/api/v1/achievements', [
                'student_id' => $this->student->id,
                'title' => 'Prestasi Lintas Sekolah',
                'rank_level' => 'Juara 1',
                'achieved_at' => Carbon::now()->toDateString(),
            ])
            ->assertStatus(403);
    }
}
