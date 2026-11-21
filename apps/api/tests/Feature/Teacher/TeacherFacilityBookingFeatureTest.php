<?php

namespace Tests\Feature\Teacher;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\TeacherDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherFacilityBookingFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $userMtk;

    protected Teacher $teacherMtk;

    protected User $userWalas;

    protected Facility $facilityLab;

    protected function setUp(): void
    {
        parent::setUp();

        (new TeacherDemoSeeder)->run();

        $this->userMtk = User::where('username', 'guru.mtk.staging')->firstOrFail();
        $this->teacherMtk = Teacher::where('user_id', $this->userMtk->id)->firstOrFail();

        $this->userWalas = User::where('username', 'walas.7a.staging')->firstOrFail();

        $this->facilityLab = Facility::firstOrCreate(
            ['code' => 'LAB-KOM-1'],
            [
                'school_id' => $this->userMtk->school_id ?? '00000000-0000-0000-0000-000000000001',
                'name' => 'Laboratorium Komputer 1',
                'facility_type' => 'ruangan',
                'capacity' => 40,
                'status' => 'tersedia',
                'is_active' => true,
            ]
        );
    }

    /**
     * FIX-GURU-11: Teacher can fetch real facilities list for dropdown.
     */
    public function test_fix_guru_11_teacher_can_fetch_facilities_list(): void
    {
        $response = $this->actingAs($this->userMtk)
            ->getJson('/api/v1/me/teacher/facilities');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'type', 'capacity', 'status', 'is_active'],
                ],
            ]);

        $this->assertNotEmpty($response->json('data'));
        $facilityIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($this->facilityLab->id, $facilityIds);
    }

    /**
     * FIX-GURU-11 AC1: Teacher can book facility successfully with 201 Created.
     */
    public function test_fix_guru_11_teacher_can_book_facility_successfully(): void
    {
        $startAt = now()->addDays(2)->setHour(8)->setMinute(0)->setSecond(0)->toISOString();
        $endAt = now()->addDays(2)->setHour(10)->setMinute(0)->setSecond(0)->toISOString();

        $response = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/facility-bookings', [
                'facility_id' => $this->facilityLab->id,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'purpose' => 'Praktikum Simulasi Pemrograman Kelas 7-A',
                'attendees_count' => 32,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.facility_id', $this->facilityLab->id)
            ->assertJsonPath('data.status', 'menunggu')
            ->assertJsonPath('data.purpose', 'Praktikum Simulasi Pemrograman Kelas 7-A');

        $bookingId = $response->json('data.id');
        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'facility_id' => $this->facilityLab->id,
            'requested_by' => $this->userMtk->id,
            'status' => 'menunggu',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->userMtk->id,
            'action' => 'teacher.booking.created',
            'entity' => 'Booking',
            'entity_id' => $bookingId,
        ]);
    }

    /**
     * FIX-GURU-11 AC2: Non-existent facility UUID returns 422 Unprocessable Entity.
     */
    public function test_fix_guru_11_booking_fails_with_422_when_facility_id_is_non_existent_uuid(): void
    {
        $nonExistentUuid = '99999999-9999-9999-9999-999999999999';
        $startAt = now()->addDays(2)->setHour(8)->setMinute(0)->setSecond(0)->toISOString();
        $endAt = now()->addDays(2)->setHour(10)->setMinute(0)->setSecond(0)->toISOString();

        $response = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/facility-bookings', [
                'facility_id' => $nonExistentUuid,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'purpose' => 'Kegiatan tanpa fasilitas valid',
                'attendees_count' => 20,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['facility_id']);
    }

    /**
     * FIX-GURU-11 AC3: Schedule conflict returns 409 Conflict.
     */
    public function test_fix_guru_11_conflicting_facility_booking_returns_409_conflict(): void
    {
        $startAt = now()->addDays(3)->setHour(9)->setMinute(0)->setSecond(0);
        $endAt = now()->addDays(3)->setHour(11)->setMinute(0)->setSecond(0);

        Booking::create([
            'school_id' => $this->userMtk->school_id ?? '00000000-0000-0000-0000-000000000001',
            'facility_id' => $this->facilityLab->id,
            'requested_by' => $this->userWalas->id,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'purpose' => 'Ujian Praktik Bahasa Komputer',
            'status' => 'menunggu',
        ]);

        $responseConflict = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/facility-bookings', [
                'facility_id' => $this->facilityLab->id,
                'start_at' => $startAt->copy()->addHour()->toISOString(),
                'end_at' => $endAt->copy()->addHour()->toISOString(),
                'purpose' => 'Sesi Tambahan Komputer',
            ]);

        $responseConflict->assertStatus(409)
            ->assertJsonPath('error.code', 'CONFLICT');
    }

    /**
     * FIX-GURU-11 AC4: Unauthenticated booking request returns 401.
     */
    public function test_fix_guru_11_booking_requires_authentication_401(): void
    {
        $response = $this->postJson('/api/v1/me/teacher/facility-bookings', [
            'facility_id' => $this->facilityLab->id,
            'start_at' => now()->addDays(1)->toISOString(),
            'end_at' => now()->addDays(1)->addHours(2)->toISOString(),
            'purpose' => 'Tanpa autentikasi',
        ]);

        $response->assertStatus(401);
    }

    /**
     * FIX-GURU-11: Teacher can view own facility bookings list.
     */
    public function test_fix_guru_11_teacher_can_view_own_facility_bookings(): void
    {
        $startAt = now()->addDays(4)->setHour(8)->setMinute(0)->setSecond(0);
        $endAt = now()->addDays(4)->setHour(10)->setMinute(0)->setSecond(0);

        Booking::create([
            'school_id' => $this->userMtk->school_id ?? '00000000-0000-0000-0000-000000000001',
            'facility_id' => $this->facilityLab->id,
            'requested_by' => $this->userMtk->id,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'purpose' => 'Praktikum Desain Grafis',
            'attendees_count' => 30,
            'status' => 'menunggu',
        ]);

        $response = $this->actingAs($this->userMtk)
            ->getJson('/api/v1/me/teacher/facility-bookings');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'facility_id',
                        'facility_name',
                        'start_at',
                        'end_at',
                        'purpose',
                        'status',
                    ],
                ],
            ]);

        $this->assertNotEmpty($response->json('data'));
        $this->assertEquals($this->facilityLab->id, $response->json('data.0.facility_id'));
    }
}
