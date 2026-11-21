<?php

namespace Tests\Feature\Master;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\GradeLevel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected Role $adminRole;

    protected Role $guestRole;

    protected Permission $permManagePromotion;

    protected AcademicYear $yearOld;

    protected AcademicYear $yearNew;

    protected GradeLevel $grade10;

    protected GradeLevel $grade11;

    protected GradeLevel $grade12;

    protected SchoolClass $class10Old;

    protected SchoolClass $class12Old;

    protected SchoolClass $class11New;

    protected Student $student1;

    protected Student $student2;

    protected Student $student3;

    protected function setUp(): void
    {
        parent::setUp();

        // Permissions
        $this->permManagePromotion = Permission::create([
            'code' => 'master.promotion.manage',
            'description' => 'Mengelola kenaikan kelas dan kelulusan siswa',
        ]);

        // Roles
        $this->adminRole = Role::create([
            'code' => 'super_admin',
            'name' => 'Super Administrator',
            'is_system' => true,
        ]);
        $this->adminRole->permissions()->attach([$this->permManagePromotion->id]);

        $this->guestRole = Role::create([
            'code' => 'guest',
            'name' => 'Tamu',
            'is_system' => false,
        ]);

        // Users
        $this->adminUser = User::create([
            'username' => 'admin_kurikulum',
            'email' => 'kurikulum@labschool.sch.id',
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

        // Academic Years
        $this->yearOld = AcademicYear::create([
            'name' => '2023/2024',
            'start_date' => '2023-07-15',
            'end_date' => '2024-06-20',
            'is_active' => false,
        ]);

        $this->yearNew = AcademicYear::create([
            'name' => '2024/2025',
            'start_date' => '2024-07-15',
            'end_date' => '2025-06-20',
            'is_active' => true,
        ]);

        // Grade Levels
        $this->grade10 = GradeLevel::create(['name' => 'Kelas 10', 'sort_order' => 10]);
        $this->grade11 = GradeLevel::create(['name' => 'Kelas 11', 'sort_order' => 11]);
        $this->grade12 = GradeLevel::create(['name' => 'Kelas 12', 'sort_order' => 12]);

        // Classes
        $this->class10Old = SchoolClass::create([
            'academic_year_id' => $this->yearOld->id,
            'grade_level_id' => $this->grade10->id,
            'name' => 'X-MIPA-1',
            'capacity' => 36,
        ]);

        $this->class12Old = SchoolClass::create([
            'academic_year_id' => $this->yearOld->id,
            'grade_level_id' => $this->grade12->id,
            'name' => 'XII-MIPA-1',
            'capacity' => 36,
        ]);

        $this->class11New = SchoolClass::create([
            'academic_year_id' => $this->yearNew->id,
            'grade_level_id' => $this->grade11->id,
            'name' => 'XI-MIPA-1',
            'capacity' => 36,
        ]);

        // Students
        $this->student1 = Student::create([
            'nis' => '10001',
            'nisn' => '0012345671',
            'nik_enc' => '3578010101080001',
            'full_name' => 'Ahmad Dahlan',
            'gender' => 'L',
            'birth_place' => 'Surabaya',
            'birth_date' => '2008-01-01',
            'entry_date' => '2023-07-15',
            'status' => 'aktif',
        ]);

        $this->student2 = Student::create([
            'nis' => '10002',
            'nisn' => '0012345672',
            'nik_enc' => '3578010101080002',
            'full_name' => 'Bambang Sudirman',
            'gender' => 'L',
            'birth_place' => 'Surabaya',
            'birth_date' => '2008-02-02',
            'entry_date' => '2023-07-15',
            'status' => 'aktif',
        ]);

        $this->student3 = Student::create([
            'nis' => '12001',
            'nisn' => '0012345673',
            'nik_enc' => '3578010101080003',
            'full_name' => 'Citra Lestari',
            'gender' => 'P',
            'birth_place' => 'Surabaya',
            'birth_date' => '2006-03-03',
            'entry_date' => '2021-07-15',
            'status' => 'aktif',
        ]);

        // Enrolled in Old Year Classes
        ClassStudent::create([
            'class_id' => $this->class10Old->id,
            'student_id' => $this->student1->id,
            'joined_at' => '2023-07-15',
        ]);

        ClassStudent::create([
            'class_id' => $this->class10Old->id,
            'student_id' => $this->student2->id,
            'joined_at' => '2023-07-15',
        ]);

        ClassStudent::create([
            'class_id' => $this->class12Old->id,
            'student_id' => $this->student3->id,
            'joined_at' => '2023-07-15',
        ]);
    }

    /**
     * BE-MST-08: Unauthenticated requests are rejected with 401
     */
    public function test_be_mst_08_unauthenticated_requests_are_rejected(): void
    {
        $this->postJson('/api/v1/promotions/preview', [])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->postJson('/api/v1/promotions/execute', [])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->postJson('/api/v1/promotions/rollback', [])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    /**
     * BE-MST-08: User without permission is forbidden (403)
     */
    public function test_be_mst_08_user_without_permission_is_forbidden(): void
    {
        $this->actingAs($this->regularUser)
            ->postJson('/api/v1/promotions/preview', [
                'source_academic_year_id' => $this->yearOld->id,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($this->regularUser)
            ->postJson('/api/v1/promotions/execute', [
                'source_academic_year_id' => $this->yearOld->id,
                'effective_date' => '2024-06-21',
                'promotions' => [],
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($this->regularUser)
            ->postJson('/api/v1/promotions/rollback', [
                'student_ids' => [$this->student1->id],
                'reason' => 'Tes rollback',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-MST-08: Validation fails on invalid or missing fields (400 VALIDATION_FAILED)
     */
    public function test_be_mst_08_validation_fails_on_invalid_or_missing_fields(): void
    {
        // 1. Preview missing source_academic_year_id
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/promotions/preview', [])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // 2. Execute missing required fields
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/promotions/execute', [])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // 3. Rollback missing reason
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/promotions/rollback', [
                'student_ids' => [$this->student1->id],
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /**
     * BE-MST-08: Preview student promotions and graduation with auto-detection and overrides
     */
    public function test_be_mst_08_preview_promotions_and_graduation_with_auto_detection(): void
    {
        $payload = [
            'source_academic_year_id' => $this->yearOld->id,
            'default_target_class_id' => $this->class11New->id,
            'overrides' => [
                [
                    'student_id' => $this->student2->id,
                    'action' => 'retain', // Student 2 tinggal kelas
                    'target_class_id' => $this->class10Old->id,
                    'notes' => 'Tinggal kelas karena belum tuntas nilai',
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/promotions/preview', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total_students', 3)
            ->assertJsonPath('data.summary.total_promote', 1)
            ->assertJsonPath('data.summary.total_retain', 1)
            ->assertJsonPath('data.summary.total_graduate', 1);

        $students = collect($response->json('data.students'));

        // Student 1: Grade 10 -> Promote to Grade 11 (auto)
        $s1 = $students->firstWhere('student_id', $this->student1->id);
        $this->assertNotNull($s1);
        $this->assertEquals('promote', $s1['action']);
        $this->assertEquals($this->class11New->id, $s1['target_class_id']);

        // Student 2: Grade 10 -> Retain (override)
        $s2 = $students->firstWhere('student_id', $this->student2->id);
        $this->assertNotNull($s2);
        $this->assertEquals('retain', $s2['action']);
        $this->assertEquals($this->class10Old->id, $s2['target_class_id']);

        // Student 3: Grade 12 -> Graduate (auto-detect highest grade level)
        $s3 = $students->firstWhere('student_id', $this->student3->id);
        $this->assertNotNull($s3);
        $this->assertEquals('graduate', $s3['action']);
    }

    /**
     * BE-MST-08: Preview detects capacity overflow warning
     */
    public function test_be_mst_08_preview_detects_capacity_overflow_warning(): void
    {
        // Small capacity class (1 seat only)
        $smallClass = SchoolClass::create([
            'academic_year_id' => $this->yearNew->id,
            'grade_level_id' => $this->grade11->id,
            'name' => 'XI-KECIL',
            'capacity' => 1,
        ]);

        $payload = [
            'source_academic_year_id' => $this->yearOld->id,
            'source_class_id' => $this->class10Old->id,
            'default_target_class_id' => $smallClass->id,
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/promotions/preview', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.has_warnings', true);

        $warnings = $response->json('data.summary.warnings');
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('Kapasitas kelas tujuan', $warnings[0]);
    }

    /**
     * BE-MST-08: Execute promotions and graduation successfully with audit logs
     */
    public function test_be_mst_08_execute_promotions_and_graduation_successfully(): void
    {
        $student4 = Student::create([
            'nis' => '10004',
            'nisn' => '0012345674',
            'nik_enc' => '3578010101080004',
            'full_name' => 'Doni Mutasi',
            'gender' => 'L',
            'birth_place' => 'Surabaya',
            'birth_date' => '2008-04-04',
            'entry_date' => '2023-07-15',
            'status' => 'aktif',
        ]);

        ClassStudent::create([
            'class_id' => $this->class10Old->id,
            'student_id' => $student4->id,
            'joined_at' => '2023-07-15',
        ]);

        $class10New = SchoolClass::create([
            'academic_year_id' => $this->yearNew->id,
            'grade_level_id' => $this->grade10->id,
            'name' => 'X-MIPA-1 (24/25)',
            'capacity' => 36,
        ]);

        $payload = [
            'source_academic_year_id' => $this->yearOld->id,
            'target_academic_year_id' => $this->yearNew->id,
            'effective_date' => '2024-06-21',
            'promotions' => [
                [
                    'student_id' => $this->student1->id,
                    'current_class_id' => $this->class10Old->id,
                    'action' => 'promote',
                    'target_class_id' => $this->class11New->id,
                ],
                [
                    'student_id' => $this->student2->id,
                    'current_class_id' => $this->class10Old->id,
                    'action' => 'retain',
                    'target_class_id' => $class10New->id,
                ],
                [
                    'student_id' => $this->student3->id,
                    'current_class_id' => $this->class12Old->id,
                    'action' => 'graduate',
                    'notes' => 'Lulus dengan predikat sangat memuaskan',
                ],
                [
                    'student_id' => $student4->id,
                    'current_class_id' => $this->class10Old->id,
                    'action' => 'drop_out',
                    'notes' => 'Pindah ke luar kota',
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/promotions/execute', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.summary.total_processed', 4)
            ->assertJsonPath('data.summary.total_promoted', 1)
            ->assertJsonPath('data.summary.total_retained', 1)
            ->assertJsonPath('data.summary.total_graduated', 1)
            ->assertJsonPath('data.summary.total_dropped_out', 1);

        // Verify Student 1 (Promoted)
        $this->assertDatabaseHas('class_students', [
            'class_id' => $this->class10Old->id,
            'student_id' => $this->student1->id,
            'left_at' => '2024-06-21',
        ]);
        $enrollment1 = ClassStudent::where('class_id', $this->class11New->id)
            ->where('student_id', $this->student1->id)
            ->first();
        $this->assertNotNull($enrollment1);
        $this->assertEquals('2024-06-21', $enrollment1->joined_at->format('Y-m-d'));
        $this->assertNull($enrollment1->left_at);

        // Verify Student 2 (Retained)
        $enrollment2 = ClassStudent::where('class_id', $class10New->id)
            ->where('student_id', $this->student2->id)
            ->first();
        $this->assertNotNull($enrollment2);
        $this->assertEquals('2024-06-21', $enrollment2->joined_at->format('Y-m-d'));
        $this->assertNull($enrollment2->left_at);

        // Verify Student 3 (Graduated)
        $this->assertDatabaseHas('students', [
            'id' => $this->student3->id,
            'status' => 'lulus',
        ]);
        $this->assertDatabaseHas('student_status_history', [
            'student_id' => $this->student3->id,
            'status' => 'lulus',
            'reason' => 'Lulus dengan predikat sangat memuaskan',
        ]);

        // Verify Student 4 (Dropped Out)
        $this->assertDatabaseHas('students', [
            'id' => $student4->id,
            'status' => 'keluar',
        ]);
        $this->assertDatabaseHas('student_status_history', [
            'student_id' => $student4->id,
            'status' => 'keluar',
            'reason' => 'Pindah ke luar kota',
        ]);

        // Audit Log
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'promotion.executed',
            'entity' => 'Promotion',
        ]);
    }

    /**
     * BE-MST-08: Execute fails if target class capacity exceeded (400 BUSINESS_RULE_VIOLATION)
     */
    public function test_be_mst_08_execute_fails_if_target_class_capacity_exceeded(): void
    {
        $smallClass = SchoolClass::create([
            'academic_year_id' => $this->yearNew->id,
            'grade_level_id' => $this->grade11->id,
            'name' => 'XI-KAPASITAS-1',
            'capacity' => 1,
        ]);

        $payload = [
            'source_academic_year_id' => $this->yearOld->id,
            'effective_date' => '2024-06-21',
            'promotions' => [
                [
                    'student_id' => $this->student1->id,
                    'current_class_id' => $this->class10Old->id,
                    'action' => 'promote',
                    'target_class_id' => $smallClass->id,
                ],
                [
                    'student_id' => $this->student2->id,
                    'current_class_id' => $this->class10Old->id,
                    'action' => 'promote',
                    'target_class_id' => $smallClass->id,
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/promotions/execute', $payload);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');

        // Verify no changes occurred (Transaction rollback)
        $this->assertDatabaseMissing('class_students', [
            'class_id' => $smallClass->id,
            'student_id' => $this->student1->id,
        ]);
    }

    /**
     * BE-MST-08: Admin can rollback executed promotions before locked
     */
    public function test_be_mst_08_can_rollback_executed_promotions(): void
    {
        // 1. Execute promotion & graduation first
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/promotions/execute', [
                'source_academic_year_id' => $this->yearOld->id,
                'effective_date' => '2024-06-21',
                'promotions' => [
                    [
                        'student_id' => $this->student1->id,
                        'current_class_id' => $this->class10Old->id,
                        'action' => 'promote',
                        'target_class_id' => $this->class11New->id,
                    ],
                    [
                        'student_id' => $this->student3->id,
                        'current_class_id' => $this->class12Old->id,
                        'action' => 'graduate',
                    ],
                ],
            ])
            ->assertStatus(201);

        // 2. Rollback for both student 1 and student 3
        $rollbackResp = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/promotions/rollback', [
                'student_ids' => [$this->student1->id, $this->student3->id],
                'reason' => 'Koreksi administratif sebelum penguncian rapor akhir',
            ]);

        $rollbackResp->assertStatus(200)
            ->assertJsonPath('data.summary.total_rolled_back', 2);

        // Verify Student 1: new class enrollment removed, old reopened
        $this->assertDatabaseMissing('class_students', [
            'class_id' => $this->class11New->id,
            'student_id' => $this->student1->id,
        ]);
        $this->assertDatabaseHas('class_students', [
            'class_id' => $this->class10Old->id,
            'student_id' => $this->student1->id,
            'left_at' => null,
        ]);

        // Verify Student 3: status restored to 'aktif', old enrollment reopened
        $this->assertDatabaseHas('students', [
            'id' => $this->student3->id,
            'status' => 'aktif',
        ]);
        $this->assertDatabaseHas('class_students', [
            'class_id' => $this->class12Old->id,
            'student_id' => $this->student3->id,
            'left_at' => null,
        ]);

        // Audit Log
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'promotion.rolled_back',
            'entity' => 'Promotion',
        ]);
    }
}
