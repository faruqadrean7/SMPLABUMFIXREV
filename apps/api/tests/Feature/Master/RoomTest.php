<?php

namespace Tests\Feature\Master;

use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamRoom;
use App\Models\GradeLevel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoomTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected Role $adminRole;

    protected Role $guestRole;

    protected Permission $permManageRoom;

    protected function setUp(): void
    {
        parent::setUp();

        // Permissions
        $this->permManageRoom = Permission::create([
            'code' => 'master.room.manage',
            'description' => 'Mengelola ruangan dan fasilitas dasar',
        ]);

        // Roles
        $this->adminRole = Role::create([
            'code' => 'super_admin',
            'name' => 'Super Administrator',
            'is_system' => true,
        ]);
        $this->adminRole->permissions()->attach([$this->permManageRoom->id]);

        $this->guestRole = Role::create([
            'code' => 'guest',
            'name' => 'Tamu',
            'is_system' => false,
        ]);

        // Users
        $this->adminUser = User::create([
            'username' => 'admin_sarpras',
            'email' => 'sarpras@labschool.sch.id',
            'password_hash' => Hash::make('AdminSecret123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($this->adminRole->id);

        $this->regularUser = User::create([
            'username' => 'guest_user',
            'email' => 'guest@labschool.sch.id',
            'password_hash' => Hash::make('GuestSecret123!'),
            'status' => 'active',
        ]);
        $this->regularUser->roles()->attach($this->guestRole->id);
    }

    /**
     * BE-MST-07: Unauthenticated requests are rejected with 401
     */
    public function test_be_mst_07_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/rooms/types')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->getJson('/api/v1/rooms')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->postJson('/api/v1/rooms', [])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->getJson('/api/v1/rooms/00000000-0000-0000-0000-000000000000')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->putJson('/api/v1/rooms/00000000-0000-0000-0000-000000000000', [])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->deleteJson('/api/v1/rooms/00000000-0000-0000-0000-000000000000')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    /**
     * BE-MST-07: User without permission is forbidden for room CRUD (403)
     */
    public function test_be_mst_07_user_without_permission_is_forbidden(): void
    {
        $this->actingAs($this->regularUser)
            ->getJson('/api/v1/rooms')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($this->regularUser)
            ->postJson('/api/v1/rooms', [
                'code' => 'R-101',
                'name' => 'Ruang 101',
                'room_type' => 'kelas',
                'capacity' => 36,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($this->regularUser)
            ->putJson('/api/v1/rooms/00000000-0000-0000-0000-000000000000', [])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($this->regularUser)
            ->deleteJson('/api/v1/rooms/00000000-0000-0000-0000-000000000000')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-MST-07: Any authenticated user can fetch room types reference
     */
    public function test_be_mst_07_can_fetch_room_types(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/v1/rooms/types');

        $response->assertStatus(200)
            ->assertJsonPath('data', [
                'kelas',
                'laboratorium',
                'aula',
                'perpustakaan',
                'kantor',
                'lapangan',
                'lainnya',
            ]);
    }

    /**
     * BE-MST-07: Room validation fails on missing fields or duplicate code
     */
    public function test_be_mst_07_room_validation_fails_on_missing_fields_or_duplicate_code(): void
    {
        // 1. Missing required fields
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/rooms', [])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // 2. Invalid room_type & negative capacity
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/rooms', [
                'code' => 'R-INVALID',
                'name' => 'Ruang Invalid',
                'room_type' => 'spaceship',
                'capacity' => 0,
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // 3. Create initial room
        Room::create([
            'code' => 'LAB-KOM-1',
            'name' => 'Laboratorium Komputer 1',
            'room_type' => 'laboratorium',
            'capacity' => 40,
        ]);

        // 4. Duplicate code on create
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/rooms', [
                'code' => 'LAB-KOM-1',
                'name' => 'Lab Komputer Baru',
                'room_type' => 'laboratorium',
                'capacity' => 35,
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // 5. Duplicate code on update
        $room2 = Room::create([
            'code' => 'LAB-IPA',
            'name' => 'Laboratorium IPA',
            'room_type' => 'laboratorium',
            'capacity' => 36,
        ]);

        $this->actingAs($this->adminUser)
            ->putJson("/api/v1/rooms/{$room2->id}", [
                'code' => 'LAB-KOM-1',
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /**
     * BE-MST-07: Admin can create, update, and get room detail with audit logs
     */
    public function test_be_mst_07_admin_can_create_update_and_get_room_with_audit_logs(): void
    {
        // 1. Create Room
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/rooms', [
                'code' => 'R-AULA-UTAMA',
                'name' => 'Aula Utama Graha Cendekia',
                'room_type' => 'aula',
                'capacity' => 300,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'R-AULA-UTAMA')
            ->assertJsonPath('data.name', 'Aula Utama Graha Cendekia')
            ->assertJsonPath('data.room_type', 'aula')
            ->assertJsonPath('data.capacity', 300);

        $roomId = $response->json('data.id');

        $this->assertDatabaseHas('rooms', [
            'id' => $roomId,
            'code' => 'R-AULA-UTAMA',
            'name' => 'Aula Utama Graha Cendekia',
            'room_type' => 'aula',
            'capacity' => 300,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'room.created',
            'entity' => 'Room',
            'entity_id' => $roomId,
        ]);

        // 2. Get Room Detail
        $detailResp = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/rooms/{$roomId}");

        $detailResp->assertStatus(200)
            ->assertJsonPath('data.id', $roomId)
            ->assertJsonPath('data.code', 'R-AULA-UTAMA');

        // 3. Update Room
        $updateResp = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/rooms/{$roomId}", [
                'name' => 'Aula Serbaguna Graha Cendekia',
                'capacity' => 350,
            ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('data.name', 'Aula Serbaguna Graha Cendekia')
            ->assertJsonPath('data.capacity', 350);

        $this->assertDatabaseHas('rooms', [
            'id' => $roomId,
            'name' => 'Aula Serbaguna Graha Cendekia',
            'capacity' => 350,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'room.updated',
            'entity' => 'Room',
            'entity_id' => $roomId,
        ]);
    }

    /**
     * BE-MST-07: Admin can list rooms with pagination, search, and filters
     */
    public function test_be_mst_07_admin_can_list_rooms_with_pagination_search_and_filters(): void
    {
        Room::create(['code' => 'R-101', 'name' => 'Kelas X MIPA 1', 'room_type' => 'kelas', 'capacity' => 36]);
        Room::create(['code' => 'R-102', 'name' => 'Kelas X MIPA 2', 'room_type' => 'kelas', 'capacity' => 36]);
        Room::create(['code' => 'LAB-BAHASA', 'name' => 'Laboratorium Multimedia Bahasa', 'room_type' => 'laboratorium', 'capacity' => 45]);
        Room::create(['code' => 'AULA', 'name' => 'Aula Serbaguna', 'room_type' => 'aula', 'capacity' => 200]);

        // 1. Search keyword
        $searchResp = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/rooms?search=Bahasa');

        $searchResp->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'LAB-BAHASA');

        // 2. Filter by room_type
        $typeResp = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/rooms?room_type=kelas');

        $typeResp->assertStatus(200)
            ->assertJsonPath('meta.total', 2);

        // 3. Filter by min_capacity
        $capacityResp = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/rooms?min_capacity=100');

        $capacityResp->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'AULA');
    }

    /**
     * BE-MST-07: Cannot delete room with existing schedules or exam rooms (409 CONFLICT)
     */
    public function test_be_mst_07_cannot_delete_room_with_existing_schedules_or_exam_rooms(): void
    {
        $room1 = Room::create([
            'code' => 'R-201',
            'name' => 'Kelas XI MIPA 1',
            'room_type' => 'kelas',
            'capacity' => 36,
        ]);

        $academicYear = AcademicYear::create([
            'name' => '2024/2025',
            'start_date' => '2024-07-15',
            'end_date' => '2025-06-20',
            'is_active' => true,
        ]);

        $semester = Semester::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'Ganjil',
            'semester_type' => 'ganjil',
            'start_date' => '2024-07-15',
            'end_date' => '2024-12-20',
            'is_active' => true,
        ]);

        $gradeLevel = GradeLevel::create(['name' => 'Kelas 11', 'sort_order' => 11]);
        $class = SchoolClass::create([
            'academic_year_id' => $academicYear->id,
            'grade_level_id' => $gradeLevel->id,
            'name' => 'XI-MIPA-1',
            'capacity' => 36,
        ]);

        $subject = Subject::create(['code' => 'MAT-11', 'name' => 'Matematika XI', 'subject_group' => 'Kelompok A']);
        $teacher = Teacher::create(['nip' => '198001012005011001', 'full_name' => 'Guru Pengampu', 'status' => 'tetap']);
        $timeSlot = TimeSlot::create(['name' => 'Jam 1', 'start_time' => '07:00', 'end_time' => '07:45']);

        // Schedule links to room1
        Schedule::create([
            'semester_id' => $semester->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'room_id' => $room1->id,
            'time_slot_id' => $timeSlot->id,
            'day_of_week' => 1,
        ]);

        // Attempt deleting room1 with schedule
        $resp1 = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/rooms/{$room1->id}");

        $resp1->assertStatus(409)
            ->assertJsonPath('error.code', 'CONFLICT')
            ->assertJsonPath('error.message', 'Ruangan tidak dapat dihapus karena masih digunakan pada jadwal pelajaran terikat.');

        $this->assertDatabaseHas('rooms', ['id' => $room1->id, 'deleted_at' => null]);

        // Room 2 with ExamRoom
        $room2 = Room::create([
            'code' => 'R-EXAM',
            'name' => 'Ruang Ujian Utama',
            'room_type' => 'kelas',
            'capacity' => 40,
        ]);

        $exam = Exam::create([
            'subject_id' => $subject->id,
            'created_by' => $this->adminUser->id,
            'title' => 'STS Ganjil Matematika',
            'exam_type' => 'sts',
            'duration_min' => 90,
            'start_at' => '2024-09-20 08:00:00',
            'end_at' => '2024-09-20 10:00:00',
            'passing_grade' => 75.00,
            'status' => 'draft',
        ]);

        ExamRoom::create([
            'exam_id' => $exam->id,
            'room_id' => $room2->id,
            'session_no' => 1,
            'token' => 'TOKEN123',
        ]);

        $resp2 = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/rooms/{$room2->id}");

        $resp2->assertStatus(409)
            ->assertJsonPath('error.code', 'CONFLICT')
            ->assertJsonPath('error.message', 'Ruangan tidak dapat dihapus karena masih terikat pada ruangan ujian CBT.');

        $this->assertDatabaseHas('rooms', ['id' => $room2->id, 'deleted_at' => null]);
    }

    /**
     * BE-MST-07: Admin can delete room without dependencies (Soft delete)
     */
    public function test_be_mst_07_admin_can_delete_room_without_dependencies(): void
    {
        $room = Room::create([
            'code' => 'R-GUDANG',
            'name' => 'Gudang Sarpras Belakang',
            'room_type' => 'lainnya',
            'capacity' => 10,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/rooms/{$room->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'Ruangan berhasil dihapus');

        $this->assertSoftDeleted('rooms', [
            'id' => $room->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'room.deleted',
            'entity' => 'Room',
            'entity_id' => $room->id,
        ]);
    }
}
