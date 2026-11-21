<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Journal;
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

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected AcademicYear $academicYear;

    protected Semester $semester;

    protected GradeLevel $gradeLevel;

    protected SchoolClass $classA;

    protected SchoolClass $classB;

    protected Teacher $teacher1;

    protected Teacher $teacher2;

    protected Subject $subjectMath;

    protected Subject $subjectEnglish;

    protected Room $room101;

    protected Room $room102;

    protected TimeSlot $slot1;

    protected TimeSlot $slot2;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Roles & Permissions
        $roleAdmin = Role::firstOrCreate(['code' => 'admin_kurikulum'], ['name' => 'Admin Kurikulum', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);

        $permManage = Permission::firstOrCreate(['code' => 'academic.schedule.manage'], ['description' => 'Mengelola jadwal pelajaran']);
        $permRead = Permission::firstOrCreate(['code' => 'academic.schedule.read'], ['description' => 'Melihat jadwal pelajaran']);

        $roleAdmin->permissions()->syncWithoutDetaching([$permManage->id, $permRead->id]);
        $roleTeacher->permissions()->syncWithoutDetaching([$permRead->id]);

        $this->adminUser = User::create([
            'username' => 'adminkurikulum',
            'email' => 'kurikulum@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->regularUser = User::create([
            'username' => 'gurubiasa',
            'email' => 'guru@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->regularUser->roles()->attach($roleTeacher->id);

        // 2. Master Academic Data
        $this->academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-15',
            'end_date' => '2027-06-20',
            'is_active' => true,
        ]);

        $this->semester = Semester::create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'Ganjil',
            'semester_type' => 'ganjil',
            'start_date' => '2026-07-15',
            'end_date' => '2026-12-20',
            'is_active' => true,
        ]);

        $this->gradeLevel = GradeLevel::create([
            'name' => 'Kelas 10',
            'sort_order' => 10,
        ]);

        $this->classA = SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->gradeLevel->id,
            'name' => 'X-A',
            'capacity' => 36,
        ]);

        $this->classB = SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->gradeLevel->id,
            'name' => 'X-B',
            'capacity' => 36,
        ]);

        $this->teacher1 = Teacher::create([
            'nip' => '198001012005011001',
            'full_name' => 'Budi Santoso, M.Pd.',
            'status' => 'tetap',
        ]);

        $this->teacher2 = Teacher::create([
            'nip' => '198502022006022002',
            'full_name' => 'Siti Aminah, S.Pd.',
            'status' => 'tetap',
        ]);

        $this->subjectMath = Subject::create([
            'code' => 'MAT-10',
            'name' => 'Matematika Wajib',
            'subject_group' => 'umum',
        ]);

        $this->subjectEnglish = Subject::create([
            'code' => 'ING-10',
            'name' => 'Bahasa Inggris',
            'subject_group' => 'umum',
        ]);

        $this->room101 = Room::create([
            'code' => 'R101',
            'name' => 'Ruang 101',
            'room_type' => 'kelas',
            'capacity' => 36,
        ]);

        $this->room102 = Room::create([
            'code' => 'R102',
            'name' => 'Ruang 102',
            'room_type' => 'kelas',
            'capacity' => 36,
        ]);

        $this->slot1 = TimeSlot::create([
            'name' => 'Jam ke-1',
            'start_time' => '07:00',
            'end_time' => '07:45',
            'day_type' => 'reguler',
        ]);

        $this->slot2 = TimeSlot::create([
            'name' => 'Jam ke-2',
            'start_time' => '07:45',
            'end_time' => '08:30',
            'day_type' => 'reguler',
        ]);
    }

    public function test_be_acd_01_can_crud_time_slots(): void
    {
        $token = $this->adminUser->createToken('test')->plainTextToken;

        // 1. Create time slot
        $createRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/time-slots', [
                'name' => 'Jam ke-3',
                'start_time' => '08:30',
                'end_time' => '09:15',
                'day_type' => 'reguler',
            ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('data.name', 'Jam ke-3')
            ->assertJsonPath('data.start_time', '08:30');

        $slotId = $createRes->json('data.id');

        // 2. Read time slot
        $getRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/time-slots/{$slotId}");
        $getRes->assertStatus(200)
            ->assertJsonPath('data.name', 'Jam ke-3');

        // 3. Update time slot
        $updateRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/time-slots/{$slotId}", [
                'name' => 'Jam ke-3 (Revisi)',
                'end_time' => '09:20',
            ]);
        $updateRes->assertStatus(200)
            ->assertJsonPath('data.name', 'Jam ke-3 (Revisi)')
            ->assertJsonPath('data.end_time', '09:20');

        // 4. Delete time slot
        $deleteRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/time-slots/{$slotId}");
        $deleteRes->assertStatus(200);

        $this->assertDatabaseMissing('time_slots', ['id' => $slotId]);
    }

    public function test_be_acd_01_cannot_delete_time_slot_in_use(): void
    {
        // Attach schedule to slot1
        Schedule::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'room_id' => $this->room101->id,
            'time_slot_id' => $this->slot1->id,
            'day_of_week' => 1,
        ]);

        $token = $this->adminUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/time-slots/{$this->slot1->id}");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');

        $this->assertDatabaseHas('time_slots', ['id' => $this->slot1->id]);
    }

    public function test_be_acd_01_check_conflicts_endpoint_detects_class_conflict(): void
    {
        // Existing schedule for Class A on Monday slot 1
        Schedule::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'room_id' => $this->room101->id,
            'time_slot_id' => $this->slot1->id,
            'day_of_week' => 1,
        ]);

        $token = $this->adminUser->createToken('test')->plainTextToken;

        // Checking with same Class A, different teacher and subject
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/schedules/conflicts/check', [
                'semester_id' => $this->semester->id,
                'class_id' => $this->classA->id,
                'subject_id' => $this->subjectEnglish->id,
                'teacher_id' => $this->teacher2->id,
                'room_id' => $this->room102->id,
                'time_slot_id' => $this->slot1->id,
                'day_of_week' => 1,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.has_conflict', true)
            ->assertJsonPath('data.conflicts.0.type', 'CLASS_CONFLICT');
    }

    public function test_be_acd_01_check_conflicts_endpoint_detects_teacher_conflict(): void
    {
        // Teacher 1 teaches Class A on Monday slot 1
        Schedule::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'room_id' => $this->room101->id,
            'time_slot_id' => $this->slot1->id,
            'day_of_week' => 1,
        ]);

        $token = $this->adminUser->createToken('test')->plainTextToken;

        // Checking if Teacher 1 can teach Class B on same day and slot
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/schedules/conflicts/check', [
                'semester_id' => $this->semester->id,
                'class_id' => $this->classB->id,
                'subject_id' => $this->subjectMath->id,
                'teacher_id' => $this->teacher1->id,
                'room_id' => $this->room102->id,
                'time_slot_id' => $this->slot1->id,
                'day_of_week' => 1,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.has_conflict', true)
            ->assertJsonPath('data.conflicts.0.type', 'TEACHER_CONFLICT');
    }

    public function test_be_acd_01_check_conflicts_endpoint_detects_room_conflict(): void
    {
        // Room 101 used by Class A on Monday slot 1
        Schedule::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'room_id' => $this->room101->id,
            'time_slot_id' => $this->slot1->id,
            'day_of_week' => 1,
        ]);

        $token = $this->adminUser->createToken('test')->plainTextToken;

        // Checking if Class B with Teacher 2 also tries to use Room 101 on same day and slot
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/schedules/conflicts/check', [
                'semester_id' => $this->semester->id,
                'class_id' => $this->classB->id,
                'subject_id' => $this->subjectEnglish->id,
                'teacher_id' => $this->teacher2->id,
                'room_id' => $this->room101->id,
                'time_slot_id' => $this->slot1->id,
                'day_of_week' => 1,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.has_conflict', true)
            ->assertJsonPath('data.conflicts.0.type', 'ROOM_CONFLICT');
    }

    public function test_be_acd_01_check_conflicts_endpoint_returns_no_conflict_when_available(): void
    {
        $token = $this->adminUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/schedules/conflicts/check', [
                'semester_id' => $this->semester->id,
                'class_id' => $this->classA->id,
                'subject_id' => $this->subjectMath->id,
                'teacher_id' => $this->teacher1->id,
                'room_id' => $this->room101->id,
                'time_slot_id' => $this->slot1->id,
                'day_of_week' => 1,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.has_conflict', false)
            ->assertJsonPath('data.conflicts', []);
    }

    public function test_be_acd_01_store_schedule_succeeds_without_conflict(): void
    {
        $token = $this->adminUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/schedules', [
                'semester_id' => $this->semester->id,
                'class_id' => $this->classA->id,
                'subject_id' => $this->subjectMath->id,
                'teacher_id' => $this->teacher1->id,
                'room_id' => $this->room101->id,
                'time_slot_id' => $this->slot1->id,
                'day_of_week' => 1,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.class_id', $this->classA->id)
            ->assertJsonPath('data.teacher_id', $this->teacher1->id)
            ->assertJsonPath('data.time_slot_id', $this->slot1->id);

        $this->assertDatabaseHas('schedules', [
            'class_id' => $this->classA->id,
            'time_slot_id' => $this->slot1->id,
            'day_of_week' => 1,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'schedule.created',
            'entity' => 'Schedule',
        ]);
    }

    public function test_be_acd_01_store_schedule_fails_with_409_on_class_conflict(): void
    {
        // Existing schedule
        Schedule::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'room_id' => $this->room101->id,
            'time_slot_id' => $this->slot1->id,
            'day_of_week' => 1,
        ]);

        $token = $this->adminUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/schedules', [
                'semester_id' => $this->semester->id,
                'class_id' => $this->classA->id,
                'subject_id' => $this->subjectEnglish->id,
                'teacher_id' => $this->teacher2->id,
                'room_id' => $this->room102->id,
                'time_slot_id' => $this->slot1->id,
                'day_of_week' => 1,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'SCHEDULE_CONFLICT');
    }

    public function test_be_acd_01_store_schedule_fails_with_409_on_teacher_conflict(): void
    {
        // Existing schedule for Teacher 1
        Schedule::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'room_id' => $this->room101->id,
            'time_slot_id' => $this->slot1->id,
            'day_of_week' => 1,
        ]);

        $token = $this->adminUser->createToken('test')->plainTextToken;

        // Try assigning Teacher 1 to Class B in same slot
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/schedules', [
                'semester_id' => $this->semester->id,
                'class_id' => $this->classB->id,
                'subject_id' => $this->subjectMath->id,
                'teacher_id' => $this->teacher1->id,
                'room_id' => $this->room102->id,
                'time_slot_id' => $this->slot1->id,
                'day_of_week' => 1,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'SCHEDULE_CONFLICT');
    }

    public function test_be_acd_01_store_schedule_fails_with_409_on_room_conflict(): void
    {
        // Existing schedule in Room 101
        Schedule::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'room_id' => $this->room101->id,
            'time_slot_id' => $this->slot1->id,
            'day_of_week' => 1,
        ]);

        $token = $this->adminUser->createToken('test')->plainTextToken;

        // Try using Room 101 with another class and teacher
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/schedules', [
                'semester_id' => $this->semester->id,
                'class_id' => $this->classB->id,
                'subject_id' => $this->subjectEnglish->id,
                'teacher_id' => $this->teacher2->id,
                'room_id' => $this->room101->id,
                'time_slot_id' => $this->slot1->id,
                'day_of_week' => 1,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'SCHEDULE_CONFLICT');
    }

    public function test_be_acd_01_update_schedule_allows_retaining_same_slot_without_self_conflict(): void
    {
        $schedule = Schedule::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'room_id' => $this->room101->id,
            'time_slot_id' => $this->slot1->id,
            'day_of_week' => 1,
        ]);

        $token = $this->adminUser->createToken('test')->plainTextToken;

        // Updating subject or room without changing slot/teacher should succeed
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/schedules/{$schedule->id}", [
                'room_id' => $this->room102->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.room_id', $this->room102->id);

        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'room_id' => $this->room102->id,
        ]);
    }

    public function test_be_acd_01_update_schedule_fails_with_409_on_conflict_with_another_schedule(): void
    {
        // Schedule 1 on slot 1
        $schedule1 = Schedule::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'room_id' => $this->room101->id,
            'time_slot_id' => $this->slot1->id,
            'day_of_week' => 1,
        ]);

        // Schedule 2 on slot 2
        $schedule2 = Schedule::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectEnglish->id,
            'teacher_id' => $this->teacher2->id,
            'room_id' => $this->room102->id,
            'time_slot_id' => $this->slot2->id,
            'day_of_week' => 1,
        ]);

        $token = $this->adminUser->createToken('test')->plainTextToken;

        // Trying to move schedule2 to slot 1 causes class conflict with schedule1
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/schedules/{$schedule2->id}", [
                'time_slot_id' => $this->slot1->id,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'SCHEDULE_CONFLICT');
    }

    public function test_be_acd_01_filter_schedules_by_class_teacher_and_day(): void
    {
        Schedule::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'room_id' => $this->room101->id,
            'time_slot_id' => $this->slot1->id,
            'day_of_week' => 1,
        ]);

        Schedule::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->classB->id,
            'subject_id' => $this->subjectEnglish->id,
            'teacher_id' => $this->teacher2->id,
            'room_id' => $this->room102->id,
            'time_slot_id' => $this->slot2->id,
            'day_of_week' => 2,
        ]);

        $token = $this->adminUser->createToken('test')->plainTextToken;

        // Filter by class A
        $resClass = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/schedules?class_id={$this->classA->id}");

        $resClass->assertStatus(200);
        $this->assertCount(1, $resClass->json('data'));

        // Filter by day 2 (Tuesday)
        $resDay = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/schedules?day_of_week=2');

        $resDay->assertStatus(200);
        $this->assertCount(1, $resDay->json('data'));
        $this->assertEquals($this->classB->id, $resDay->json('data.0.class_id'));
    }

    public function test_be_acd_01_cannot_delete_schedule_with_journal_and_can_delete_without_journal(): void
    {
        $schedule = Schedule::create([
            'semester_id' => $this->semester->id,
            'class_id' => $this->classA->id,
            'subject_id' => $this->subjectMath->id,
            'teacher_id' => $this->teacher1->id,
            'room_id' => $this->room101->id,
            'time_slot_id' => $this->slot1->id,
            'day_of_week' => 1,
        ]);

        // Attach journal
        $journal = Journal::create([
            'schedule_id' => $schedule->id,
            'teacher_id' => $this->teacher1->id,
            'session_date' => '2026-08-01',
            'topic' => 'Aljabar Linier',
            'method' => 'Diskusi',
        ]);

        $token = $this->adminUser->createToken('test')->plainTextToken;

        // Deletion fails
        $resFail = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/schedules/{$schedule->id}");

        $resFail->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');

        // Delete journal first, then delete schedule succeeds
        $journal->delete();

        $resSuccess = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/schedules/{$schedule->id}");

        $resSuccess->assertStatus(200);
        $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
    }

    public function test_be_acd_01_unauthorized_user_cannot_manage_schedules_403(): void
    {
        $token = $this->regularUser->createToken('test')->plainTextToken;

        // Regular teacher without academic.schedule.manage cannot create schedule
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/schedules', [
                'semester_id' => $this->semester->id,
                'class_id' => $this->classA->id,
                'subject_id' => $this->subjectMath->id,
                'teacher_id' => $this->teacher1->id,
                'room_id' => $this->room101->id,
                'time_slot_id' => $this->slot1->id,
                'day_of_week' => 1,
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_be_acd_01_unauthenticated_request_cannot_access_schedules_401(): void
    {
        $response = $this->getJson('/api/v1/schedules');

        $response->assertStatus(401);
    }
}
