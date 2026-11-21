<?php

namespace Tests\Feature\Sar;

use App\Models\Booking;
use App\Models\Facility;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class FacilityAndBookingTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $sarprasUser;

    protected User $teacherUser;

    protected User $otherTeacherUser;

    protected Facility $hallFacility;

    protected Facility $projectorFacility;

    protected Room $roomAula;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleSarpras = Role::firstOrCreate(['code' => 'facility_staff'], ['name' => 'Staf Sarana & Prasarana', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);

        $permBooking = Permission::firstOrCreate(['code' => 'facility.booking.manage'], ['description' => 'Menyetujui/menolak booking fasilitas']);
        $roleSarpras->permissions()->syncWithoutDetaching([$permBooking->id]);
        $roleAdmin->permissions()->syncWithoutDetaching([$permBooking->id]);

        $this->adminUser = User::create([
            'username' => 'admin_sarpras',
            'email' => 'admin_sarpras@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->sarprasUser = User::create([
            'username' => 'staf_sarpras',
            'email' => 'sarpras@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->sarprasUser->roles()->attach($roleSarpras->id);

        $this->teacherUser = User::create([
            'username' => 'guru_budi',
            'email' => 'budi@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher->id);

        $this->otherTeacherUser = User::create([
            'username' => 'guru_ani',
            'email' => 'ani@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->otherTeacherUser->roles()->attach($roleTeacher->id);

        $this->roomAula = Room::create([
            'name' => 'Aula Utama',
            'code' => 'AULA-01',
            'room_type' => 'aula',
            'capacity' => 200,
            'is_active' => true,
        ]);

        $this->hallFacility = Facility::create([
            'name' => 'Aula Serbaguna Graha UM',
            'facility_type' => 'ruang',
            'room_id' => $this->roomAula->id,
            'capacity' => 250,
            'requires_approval' => true,
        ]);

        $this->projectorFacility = Facility::create([
            'name' => 'Proyektor Epson 4K Portable',
            'facility_type' => 'alat',
            'capacity' => null,
            'requires_approval' => false,
        ]);
    }

    /**
     * BE-SAR-01: Admin / Sarpras dapat melakukan CRUD fasilitas.
     */
    public function test_be_sar_01_manage_facility_crud(): void
    {
        $this->app['auth']->forgetGuards();

        // 1. Create Facility
        $responseCreate = $this->actingAs($this->sarprasUser, 'sanctum')
            ->postJson('/api/v1/facilities', [
                'name' => 'Laboratorium Komputer 3',
                'facility_type' => 'ruang',
                'capacity' => 40,
                'requires_approval' => true,
            ]);

        $responseCreate->assertStatus(201)
            ->assertJsonPath('data.name', 'Laboratorium Komputer 3');

        $facilityId = $responseCreate->json('data.id');

        // 2. Read Detail Facility
        $responseShow = $this->actingAs($this->sarprasUser, 'sanctum')
            ->getJson("/api/v1/facilities/{$facilityId}");

        $responseShow->assertStatus(200)
            ->assertJsonPath('data.id', $facilityId);

        // 3. Update Facility
        $responseUpdate = $this->actingAs($this->sarprasUser, 'sanctum')
            ->putJson("/api/v1/facilities/{$facilityId}", [
                'name' => 'Laboratorium Komputer 3 (Multimedia)',
                'capacity' => 45,
            ]);

        $responseUpdate->assertStatus(200)
            ->assertJsonPath('data.name', 'Laboratorium Komputer 3 (Multimedia)')
            ->assertJsonPath('data.capacity', 45);

        // 4. Delete Facility
        $responseDelete = $this->actingAs($this->sarprasUser, 'sanctum')
            ->deleteJson("/api/v1/facilities/{$facilityId}");

        $responseDelete->assertStatus(200);
        $this->assertDatabaseMissing('facilities', ['id' => $facilityId]);
    }

    /**
     * BE-SAR-01: Pengguna biasa dilarang membuat atau menghapus fasilitas (403 Forbidden).
     */
    public function test_be_sar_01_unauthorized_user_cannot_create_or_delete_facility(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/facilities', [
                'name' => 'Ruang Musik Ilegal',
                'facility_type' => 'ruang',
            ]);

        $response->assertStatus(403);
    }

    /**
     * BE-SAR-01: Cek ketersediaan fasilitas secara real-time.
     */
    public function test_be_sar_01_check_availability_real_time(): void
    {
        $start1 = Carbon::now()->addDays(2)->setTime(8, 0, 0)->toDateTimeString();
        $end1 = Carbon::now()->addDays(2)->setTime(12, 0, 0)->toDateTimeString();

        // Buat booking yang telah disetujui pada jam 08:00 - 12:00
        Booking::create([
            'facility_id' => $this->hallFacility->id,
            'requested_by' => $this->teacherUser->id,
            'start_at' => $start1,
            'end_at' => $end1,
            'purpose' => 'Seminar Pendidikan Nasional',
            'status' => 'disetujui',
        ]);

        $this->app['auth']->forgetGuards();

        // 1. Cek slot yang bertabrakan (09:00 - 11:00) -> is_available = false
        $overlapStart = Carbon::now()->addDays(2)->setTime(9, 0, 0)->toDateTimeString();
        $overlapEnd = Carbon::now()->addDays(2)->setTime(11, 0, 0)->toDateTimeString();

        $responseConflict = $this->actingAs($this->teacherUser, 'sanctum')
            ->getJson("/api/v1/bookings/availability?facility_id={$this->hallFacility->id}&start_at={$overlapStart}&end_at={$overlapEnd}");

        $responseConflict->assertStatus(200)
            ->assertJsonPath('data.is_available', false)
            ->assertJsonCount(1, 'data.conflicts');

        // 2. Cek slot yang kosong di hari yang sama (13:00 - 16:00) -> is_available = true
        $freeStart = Carbon::now()->addDays(2)->setTime(13, 0, 0)->toDateTimeString();
        $freeEnd = Carbon::now()->addDays(2)->setTime(16, 0, 0)->toDateTimeString();

        $responseFree = $this->actingAs($this->teacherUser, 'sanctum')
            ->getJson("/api/v1/bookings/availability?facility_id={$this->hallFacility->id}&start_at={$freeStart}&end_at={$freeEnd}");

        $responseFree->assertStatus(200)
            ->assertJsonPath('data.is_available', true)
            ->assertJsonCount(0, 'data.conflicts');
    }

    /**
     * BE-SAR-01: Pengajuan peminjaman sukses disertai peralatan pendukung (booking_items).
     */
    public function test_be_sar_01_create_booking_success_with_items(): void
    {
        $start = Carbon::now()->addDays(3)->setTime(9, 0, 0)->toDateTimeString();
        $end = Carbon::now()->addDays(3)->setTime(11, 30, 0)->toDateTimeString();

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/bookings', [
                'facility_id' => $this->hallFacility->id,
                'start_at' => $start,
                'end_at' => $end,
                'purpose' => 'Rapat Pleno Komite Sekolah',
                'items' => [
                    [
                        'facility_id' => $this->projectorFacility->id,
                        'quantity' => 2,
                        'notes' => 'Termasuk kabel HDMI panjang',
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.facility_id', $this->hallFacility->id)
            ->assertJsonPath('data.status', 'diajukan')
            ->assertJsonPath('data.requested_by', $this->teacherUser->id);

        $bookingId = $response->json('data.id');

        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'facility_id' => $this->hallFacility->id,
            'requested_by' => $this->teacherUser->id,
            'status' => 'diajukan',
        ]);

        $this->assertDatabaseHas('booking_items', [
            'booking_id' => $bookingId,
            'facility_id' => $this->projectorFacility->id,
            'quantity' => 2,
            'notes' => 'Termasuk kabel HDMI panjang',
        ]);
    }

    /**
     * BE-SAR-01: Pengajuan peminjaman gagal jika terjadi bentrok jadwal dengan booking yang disetujui.
     */
    public function test_be_sar_01_create_booking_conflict_rejected(): void
    {
        $start1 = Carbon::now()->addDays(4)->setTime(8, 0, 0)->toDateTimeString();
        $end1 = Carbon::now()->addDays(4)->setTime(12, 0, 0)->toDateTimeString();

        // Booking A telah disetujui
        Booking::create([
            'facility_id' => $this->hallFacility->id,
            'requested_by' => $this->otherTeacherUser->id,
            'start_at' => $start1,
            'end_at' => $end1,
            'purpose' => 'Lomba Debat Bahasa Inggris Antar Kelas',
            'status' => 'disetujui',
        ]);

        $this->app['auth']->forgetGuards();

        // Booking B mencoba mengajukan di waktu yang bertabrakan (10:00 - 14:00)
        $startConflict = Carbon::now()->addDays(4)->setTime(10, 0, 0)->toDateTimeString();
        $endConflict = Carbon::now()->addDays(4)->setTime(14, 0, 0)->toDateTimeString();

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/bookings', [
                'facility_id' => $this->hallFacility->id,
                'start_at' => $startConflict,
                'end_at' => $endConflict,
                'purpose' => 'Workshop Robotika',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.message', 'Fasilitas tidak tersedia pada jadwal yang dipilih karena telah disetujui peminjaman lain (bentrok jadwal).');
    }

    /**
     * BE-SAR-01: Staf Sarpras / Admin dapat menyetujui peminjaman dan mencatat audit log.
     */
    public function test_be_sar_01_sarpras_staff_can_approve_booking(): void
    {
        $start = Carbon::now()->addDays(5)->setTime(13, 0, 0)->toDateTimeString();
        $end = Carbon::now()->addDays(5)->setTime(15, 0, 0)->toDateTimeString();

        $booking = Booking::create([
            'facility_id' => $this->hallFacility->id,
            'requested_by' => $this->teacherUser->id,
            'start_at' => $start,
            'end_at' => $end,
            'purpose' => 'Gladi Resik Pentas Seni',
            'status' => 'diajukan',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->sarprasUser, 'sanctum')
            ->patchJson("/api/v1/bookings/{$booking->id}/decision", [
                'status' => 'disetujui',
                'decision_notes' => 'Disetujui. Kunci aula dan remote proyektor dapat diambil di ruang Sarpras H-1.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'disetujui')
            ->assertJsonPath('data.decided_by', $this->sarprasUser->id)
            ->assertJsonPath('data.decision_notes', 'Disetujui. Kunci aula dan remote proyektor dapat diambil di ruang Sarpras H-1.');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'disetujui',
            'decided_by' => $this->sarprasUser->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->sarprasUser->id,
            'action' => 'booking.decide',
            'entity' => 'bookings',
            'entity_id' => $booking->id,
        ]);
    }

    /**
     * BE-SAR-01: Staf Sarpras / Admin dapat menolak peminjaman disertai catatan alasan penolakan.
     */
    public function test_be_sar_01_sarpras_staff_can_reject_booking(): void
    {
        $booking = Booking::create([
            'facility_id' => $this->hallFacility->id,
            'requested_by' => $this->teacherUser->id,
            'start_at' => Carbon::now()->addDays(6)->setTime(10, 0, 0)->toDateTimeString(),
            'end_at' => Carbon::now()->addDays(6)->setTime(12, 0, 0)->toDateTimeString(),
            'purpose' => 'Acara Non-Kedinasan',
            'status' => 'diajukan',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->sarprasUser, 'sanctum')
            ->patchJson("/api/v1/bookings/{$booking->id}/decision", [
                'status' => 'ditolak',
                'decision_notes' => 'Aula sedang dalam jadwal pemeliharaan AC dan kelistrikan.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'ditolak')
            ->assertJsonPath('data.decision_notes', 'Aula sedang dalam jadwal pemeliharaan AC dan kelistrikan.');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'ditolak',
            'decided_by' => $this->sarprasUser->id,
        ]);
    }

    /**
     * BE-SAR-01: Pengguna tanpa hak akses dilarang membuat keputusan persetujuan booking.
     */
    public function test_be_sar_01_unauthorized_user_cannot_decide_booking(): void
    {
        $booking = Booking::create([
            'facility_id' => $this->hallFacility->id,
            'requested_by' => $this->otherTeacherUser->id,
            'start_at' => Carbon::now()->addDays(7)->setTime(8, 0, 0)->toDateTimeString(),
            'end_at' => Carbon::now()->addDays(7)->setTime(10, 0, 0)->toDateTimeString(),
            'purpose' => 'Uji Otorisasi',
            'status' => 'diajukan',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->patchJson("/api/v1/bookings/{$booking->id}/decision", [
                'status' => 'disetujui',
            ]);

        $response->assertStatus(403);
    }

    /**
     * BE-SAR-01: Pemohon dapat membatalkan peminjamannya sendiri.
     */
    public function test_be_sar_01_requester_can_cancel_booking(): void
    {
        $booking = Booking::create([
            'facility_id' => $this->hallFacility->id,
            'requested_by' => $this->teacherUser->id,
            'start_at' => Carbon::now()->addDays(8)->setTime(8, 0, 0)->toDateTimeString(),
            'end_at' => Carbon::now()->addDays(8)->setTime(10, 0, 0)->toDateTimeString(),
            'purpose' => 'Rapat yang dibatalkan pemohon',
            'status' => 'diajukan',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->deleteJson("/api/v1/bookings/{$booking->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'dibatalkan');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'dibatalkan',
        ]);
    }

    /**
     * BE-SAR-01: Scoping daftar peminjaman:
     * Pengguna biasa hanya melihat booking miliknya, staf sarpras melihat seluruh booking.
     */
    public function test_be_sar_01_list_bookings_scoping(): void
    {
        $bookingBudi = Booking::create([
            'facility_id' => $this->hallFacility->id,
            'requested_by' => $this->teacherUser->id,
            'start_at' => Carbon::now()->addDays(9)->setTime(8, 0, 0)->toDateTimeString(),
            'end_at' => Carbon::now()->addDays(9)->setTime(10, 0, 0)->toDateTimeString(),
            'purpose' => 'Booking Pak Budi',
            'status' => 'diajukan',
        ]);

        $bookingAni = Booking::create([
            'facility_id' => $this->hallFacility->id,
            'requested_by' => $this->otherTeacherUser->id,
            'start_at' => Carbon::now()->addDays(10)->setTime(8, 0, 0)->toDateTimeString(),
            'end_at' => Carbon::now()->addDays(10)->setTime(10, 0, 0)->toDateTimeString(),
            'purpose' => 'Booking Bu Ani',
            'status' => 'diajukan',
        ]);

        $this->app['auth']->forgetGuards();

        // 1. Pak Budi hanya melihat booking miliknya
        $respBudi = $this->actingAs($this->teacherUser, 'sanctum')
            ->getJson('/api/v1/bookings');

        $respBudi->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $bookingBudi->id);

        $this->app['auth']->forgetGuards();

        // 2. Staf Sarpras dapat melihat seluruh booking
        $respSarpras = $this->actingAs($this->sarprasUser, 'sanctum')
            ->getJson('/api/v1/bookings');

        $respSarpras->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    /**
     * BE-SAR-01: Validasi kelayakan: Fasilitas nonaktif atau dalam masa pemeliharaan dilarang dipesan (422).
     */
    public function test_be_sar_01_booking_ineligible_facility_is_rejected(): void
    {
        $this->app['auth']->forgetGuards();

        $inactiveFacility = Facility::create([
            'name' => 'Lab Fisika (Perbaikan Lantai)',
            'facility_type' => 'ruang',
            'is_active' => false,
            'status' => 'nonaktif',
        ]);

        $maintenanceFacility = Facility::create([
            'name' => 'Lab Biologi (Pemeliharaan Alat)',
            'facility_type' => 'ruang',
            'is_active' => true,
            'status' => 'pemeliharaan',
        ]);

        $start = Carbon::now()->addDays(5)->setTime(8, 0, 0)->toDateTimeString();
        $end = Carbon::now()->addDays(5)->setTime(10, 0, 0)->toDateTimeString();

        // 1. Coba pesan fasilitas nonaktif
        $resInactive = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/bookings', [
                'facility_id' => $inactiveFacility->id,
                'start_at' => $start,
                'end_at' => $end,
                'purpose' => 'Praktikum Fisika Dasar',
            ]);

        $resInactive->assertStatus(422)
            ->assertJsonPath('error.message', 'Fasilitas sedang nonaktif atau dalam masa pemeliharaan sehingga tidak dapat dipesan.');

        // 2. Coba pesan fasilitas dalam status pemeliharaan
        $resMaint = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/bookings', [
                'facility_id' => $maintenanceFacility->id,
                'start_at' => $start,
                'end_at' => $end,
                'purpose' => 'Praktikum Biologi Sel',
            ]);

        $resMaint->assertStatus(422)
            ->assertJsonPath('error.message', 'Fasilitas sedang nonaktif atau dalam masa pemeliharaan sehingga tidak dapat dipesan.');
    }

    /**
     * BE-SAR-01: Multi-tenant & Anti-IDOR: Pengguna dari sekolah berbeda dilarang mengakses fasilitas/booking sekolah lain (403).
     */
    public function test_be_sar_01_multi_tenant_cross_school_isolation(): void
    {
        $this->app['auth']->forgetGuards();

        $schoolA = (string) Str::uuid();
        $schoolB = (string) Str::uuid();

        $this->sarprasUser->school_id = $schoolA;
        $this->sarprasUser->save();

        $this->teacherUser->school_id = $schoolA;
        $this->teacherUser->save();

        $facilitySchoolB = Facility::create([
            'school_id' => $schoolB,
            'name' => 'Aula Serbaguna Kampus B',
            'facility_type' => 'ruang',
            'is_active' => true,
            'status' => 'tersedia',
        ]);

        // 1. Guru Sekolah A dilarang memesan fasilitas Sekolah B
        $start = Carbon::now()->addDays(6)->setTime(9, 0, 0)->toDateTimeString();
        $end = Carbon::now()->addDays(6)->setTime(11, 0, 0)->toDateTimeString();

        $resCrossBook = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/bookings', [
                'facility_id' => $facilitySchoolB->id,
                'start_at' => $start,
                'end_at' => $end,
                'purpose' => 'Peminjaman Lintas Kampus Ilegal',
            ]);

        $resCrossBook->assertStatus(403)
            ->assertJsonPath('error.message', 'Akses ditolak: Fasilitas berada di luar sekolah penugasan Anda.');

        // 2. Guru Sekolah A dilarang melihat detail fasilitas Sekolah B
        $resShowCross = $this->actingAs($this->teacherUser, 'sanctum')
            ->getJson("/api/v1/facilities/{$facilitySchoolB->id}");

        $resShowCross->assertStatus(403);
    }

    /**
     * BE-SAR-01: Validasi status transisi & proteksi status terminal (selesai, dibatalkan).
     */
    public function test_be_sar_01_terminal_status_transitions(): void
    {
        $this->app['auth']->forgetGuards();

        $booking = Booking::create([
            'facility_id' => $this->hallFacility->id,
            'requested_by' => $this->teacherUser->id,
            'start_at' => Carbon::now()->addDays(7)->setTime(8, 0, 0)->toDateTimeString(),
            'end_at' => Carbon::now()->addDays(7)->setTime(10, 0, 0)->toDateTimeString(),
            'purpose' => 'Rapat Evaluasi Triwulan',
            'status' => 'disetujui',
        ]);

        // 1. Tandai booking selesai oleh staf sarpras
        $resSelesai = $this->actingAs($this->sarprasUser, 'sanctum')
            ->patchJson("/api/v1/bookings/{$booking->id}/decision", [
                'status' => 'selesai',
                'decision_notes' => 'Acara telah selesai, kunci dan peralatan telah dikembalikan lengkap.',
            ]);

        $resSelesai->assertStatus(200)
            ->assertJsonPath('data.status', 'selesai');

        // 2. Booking berstatus selesai tidak dapat diubah statusnya lagi (terminal)
        $resReapprove = $this->actingAs($this->sarprasUser, 'sanctum')
            ->patchJson("/api/v1/bookings/{$booking->id}/decision", [
                'status' => 'disetujui',
            ]);

        $resReapprove->assertStatus(422)
            ->assertJsonPath('error.message', 'Peminjaman dengan status selesai tidak dapat diproses lagi.');

        // 3. Booking berstatus selesai dilarang dibatalkan
        $resCancel = $this->actingAs($this->teacherUser, 'sanctum')
            ->deleteJson("/api/v1/bookings/{$booking->id}");

        $resCancel->assertStatus(422)
            ->assertJsonPath('error.message', 'Peminjaman dengan status selesai tidak dapat dibatalkan.');
    }

    /**
     * BE-SAR-01: Endpoint ringkasan metrik peminjaman fasilitas (/bookings/summary).
     */
    public function test_be_sar_01_summary_metrics_endpoint(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->sarprasUser, 'sanctum')
            ->getJson('/api/v1/bookings/summary');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_facilities',
                    'active_facilities',
                    'total_bookings',
                    'pending_bookings',
                    'approved_bookings',
                    'rejected_bookings',
                    'completed_bookings',
                    'cancelled_bookings',
                    'today_bookings',
                ],
            ]);
    }

    /**
     * BE-SAR-01: Privacy masking pada kalender peminjaman bagi pengguna umum.
     */
    public function test_be_sar_01_calendar_view_privacy_masking(): void
    {
        $this->app['auth']->forgetGuards();

        $bookingBudiPrivate = Booking::create([
            'facility_id' => $this->hallFacility->id,
            'requested_by' => $this->teacherUser->id,
            'start_at' => Carbon::now()->addDays(8)->setTime(13, 0, 0)->toDateTimeString(),
            'end_at' => Carbon::now()->addDays(8)->setTime(15, 0, 0)->toDateTimeString(),
            'purpose' => 'Rapat Rahasia Komite Disiplin Guru',
            'status' => 'disetujui',
            'decision_notes' => 'Catatan sensitif internal',
        ]);

        // Bu Ani (pengguna umum/guru lain) melihat kalender peminjaman
        $response = $this->actingAs($this->otherTeacherUser, 'sanctum')
            ->getJson('/api/v1/bookings?view=calendar');

        $response->assertStatus(200);

        $items = $response->json('data');
        $found = collect($items)->firstWhere('id', $bookingBudiPrivate->id);

        $this->assertNotNull($found);
        // Detail sensitif harus disamarkan untuk pengguna non-manager yang bukan pemohon
        $this->assertEquals('[Kegiatan Terjadwal]', $found['purpose']);
        $this->assertNull($found['decision_notes']);
    }
}
