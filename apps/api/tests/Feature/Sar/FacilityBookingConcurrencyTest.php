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
use Tests\TestCase;

class FacilityBookingConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $user1;

    protected User $user2;

    protected Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'admin'], ['name' => 'Admin', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);

        $permManage = Permission::firstOrCreate(['code' => 'sar.booking.manage'], ['description' => 'Kelola Booking Sarpras']);
        $roleAdmin->permissions()->syncWithoutDetaching([$permManage->id]);

        $this->adminUser = User::create([
            'username' => 'admin_sar_conc',
            'email' => 'admin_sar_conc@test.sch.id',
            'password_hash' => Hash::make('secret123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->user1 = User::create([
            'username' => 'user1_sar_conc',
            'email' => 'user1_conc@test.sch.id',
            'password_hash' => Hash::make('secret123'),
            'status' => 'active',
        ]);
        $this->user1->roles()->attach($roleTeacher->id);

        $this->user2 = User::create([
            'username' => 'user2_sar_conc',
            'email' => 'user2_conc@test.sch.id',
            'password_hash' => Hash::make('secret123'),
            'status' => 'active',
        ]);
        $this->user2->roles()->attach($roleTeacher->id);

        $room = Room::create([
            'code' => 'AULA-UTAMA',
            'name' => 'Aula Utama',
            'room_type' => 'aula',
            'capacity' => 200,
        ]);

        $this->facility = Facility::create([
            'room_id' => $room->id,
            'code' => 'FAC-AULA-01',
            'name' => 'Aula Utama & Soundsystem',
            'category' => 'ruangan',
            'requires_approval' => true,
            'status' => 'tersedia',
        ]);
    }

    public function test_p2_sec_conc_01_first_approval_succeeds_and_conflicting_approval_is_rejected(): void
    {
        $startTime = Carbon::now()->addDays(2)->setHour(9)->setMinute(0);
        $endTime = Carbon::now()->addDays(2)->setHour(12)->setMinute(0);

        // Booking 1 submitted by User 1
        $booking1 = Booking::create([
            'facility_id' => $this->facility->id,
            'requested_by' => $this->user1->id,
            'start_at' => $startTime,
            'end_at' => $endTime,
            'purpose' => 'Seminar Pendidikan',
            'status' => 'diajukan',
        ]);

        // Booking 2 submitted by User 2 (overlapping time slot 10:00 - 13:00)
        $booking2 = Booking::create([
            'facility_id' => $this->facility->id,
            'requested_by' => $this->user2->id,
            'start_at' => $startTime->copy()->addHour(), // 10:00
            'end_at' => $endTime->copy()->addHour(),   // 13:00
            'purpose' => 'Latihan Paduan Suara',
            'status' => 'diajukan',
        ]);

        // Admin approves Booking 1
        $res1 = $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/v1/bookings/{$booking1->id}/decision", [
                'status' => 'disetujui',
                'decision_notes' => 'Disetujui untuk seminar',
            ]);

        $res1->assertOk()
            ->assertJsonPath('data.status', 'disetujui');

        $this->assertEquals('disetujui', $booking1->fresh()->status);

        // Admin attempts to approve Booking 2 (must be rejected due to collision lock)
        $res2 = $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/v1/bookings/{$booking2->id}/decision", [
                'status' => 'disetujui',
                'decision_notes' => 'Disetujui juga',
            ]);

        $res2->assertStatus(422)
            ->assertJsonPath('error.message', 'Tidak dapat menyetujui peminjaman karena fasilitas telah dibooking jadwal lain pada jam tersebut.');

        $this->assertEquals('diajukan', $booking2->fresh()->status);
    }

    public function test_p2_sec_conc_01_non_overlapping_bookings_can_both_be_approved(): void
    {
        $day = Carbon::now()->addDays(3);

        // Booking A: 08:00 - 10:00
        $bookingA = Booking::create([
            'facility_id' => $this->facility->id,
            'requested_by' => $this->user1->id,
            'start_at' => $day->copy()->setHour(8)->setMinute(0),
            'end_at' => $day->copy()->setHour(10)->setMinute(0),
            'purpose' => 'Rapat Pagi',
            'status' => 'diajukan',
        ]);

        // Booking B: 10:00 - 12:00 (exact boundary touch, no overlap)
        $bookingB = Booking::create([
            'facility_id' => $this->facility->id,
            'requested_by' => $this->user2->id,
            'start_at' => $day->copy()->setHour(10)->setMinute(0),
            'end_at' => $day->copy()->setHour(12)->setMinute(0),
            'purpose' => 'Rapat Siang',
            'status' => 'diajukan',
        ]);

        $resA = $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/v1/bookings/{$bookingA->id}/decision", [
                'status' => 'disetujui',
            ]);
        $resA->assertOk();

        $resB = $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/v1/bookings/{$bookingB->id}/decision", [
                'status' => 'disetujui',
            ]);
        $resB->assertOk();

        $this->assertEquals('disetujui', $bookingA->fresh()->status);
        $this->assertEquals('disetujui', $bookingB->fresh()->status);
    }
}
