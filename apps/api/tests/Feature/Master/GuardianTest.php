<?php

namespace Tests\Feature\Master;

use App\Models\Guardian;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GuardianTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected User $guardianUser1;

    protected User $guardianUser2;

    protected Guardian $guardian1;

    protected Guardian $guardian2;

    protected Student $student1;

    protected Student $student2;

    protected Role $adminRole;

    protected Role $guardianRole;

    protected Role $guestRole;

    protected Permission $permReadStudent;

    protected Permission $permWriteStudent;

    protected function setUp(): void
    {
        parent::setUp();

        // Permissions
        $this->permReadStudent = Permission::create([
            'code' => 'master.student.read',
            'description' => 'Melihat data siswa dan wali',
        ]);
        $this->permWriteStudent = Permission::create([
            'code' => 'master.student.write',
            'description' => 'Mengubah data siswa dan wali',
        ]);

        // Roles
        $this->adminRole = Role::create([
            'code' => 'super_admin',
            'name' => 'Super Administrator',
            'is_system' => true,
        ]);
        $this->adminRole->permissions()->attach([
            $this->permReadStudent->id,
            $this->permWriteStudent->id,
        ]);

        $this->guardianRole = Role::create([
            'code' => 'guardian',
            'name' => 'Orang Tua / Wali',
            'is_system' => true,
        ]);

        $this->guestRole = Role::create([
            'code' => 'guest',
            'name' => 'Tamu',
            'is_system' => false,
        ]);

        // Users
        $this->adminUser = User::create([
            'username' => 'admin_wali',
            'email' => 'admin.wali@labschool.sch.id',
            'password_hash' => Hash::make('AdminSecret123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($this->adminRole->id);

        $this->regularUser = User::create([
            'username' => 'guest_wali',
            'email' => 'guest.wali@labschool.sch.id',
            'password_hash' => Hash::make('GuestSecret123!'),
            'status' => 'active',
        ]);
        $this->regularUser->roles()->attach($this->guestRole->id);

        // Guardian 1 (Ayah)
        $this->guardianUser1 = User::create([
            'username' => 'wali1',
            'email' => 'wali1@gmail.com',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $this->guardianUser1->roles()->attach($this->guardianRole->id);

        $this->guardian1 = Guardian::create([
            'user_id' => $this->guardianUser1->id,
            'full_name' => 'Dr. Bambang Kusumo, Sp.A.',
            'phone' => '081234567890',
            'occupation' => 'Dokter Spesialis Anak',
            'address' => 'Jl. Dharmahusada No. 12, Surabaya',
        ]);

        // Guardian 2 (Ibu)
        $this->guardianUser2 = User::create([
            'username' => 'wali2',
            'email' => 'wali2@gmail.com',
            'password_hash' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $this->guardianUser2->roles()->attach($this->guardianRole->id);

        $this->guardian2 = Guardian::create([
            'user_id' => $this->guardianUser2->id,
            'full_name' => 'Dra. Endang Rahayu, M.M.',
            'phone' => '081234567891',
            'occupation' => 'Dosen',
            'address' => 'Jl. Dharmahusada No. 12, Surabaya',
        ]);

        // Students
        $this->student1 = Student::create([
            'nis' => '20001',
            'nisn' => '0022334411',
            'nik_enc' => '3578010101080001',
            'full_name' => 'Ananda Kusumo',
            'gender' => 'L',
            'birth_place' => 'Surabaya',
            'birth_date' => '2008-01-15',
            'status' => 'aktif',
            'entry_date' => '2024-07-15',
        ]);

        $this->student2 = Student::create([
            'nis' => '20002',
            'nisn' => '0022334422',
            'nik_enc' => '3578010101080002',
            'full_name' => 'Adinda Kusumo',
            'gender' => 'P',
            'birth_place' => 'Surabaya',
            'birth_date' => '2010-06-20',
            'status' => 'aktif',
            'entry_date' => '2024-07-15',
        ]);
    }

    /**
     * BE-MST-04: Unauthenticated requests are rejected with 401
     */
    public function test_be_mst_04_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/guardians')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->postJson('/api/v1/guardians', [])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->getJson("/api/v1/students/{$this->student1->id}/guardians")
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->postJson("/api/v1/students/{$this->student1->id}/guardians", [])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    /**
     * BE-MST-04: User without permission cannot mutate guardians (403)
     */
    public function test_be_mst_04_user_without_permission_cannot_mutate_guardians(): void
    {
        $this->actingAs($this->regularUser)
            ->postJson('/api/v1/guardians', [
                'full_name' => 'Wali Tanpa Izin',
                'phone' => '081234567800',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($this->regularUser)
            ->postJson("/api/v1/students/{$this->student1->id}/guardians", [
                'guardian_id' => $this->guardian1->id,
                'relationship' => 'ayah',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-MST-04: Admin can create, update, and delete guardian with audit logging
     */
    public function test_be_mst_04_admin_can_create_update_delete_guardian_and_logs_audit(): void
    {
        // 1. Create Guardian
        $createResp = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/guardians', [
                'full_name' => 'Ir. Gunawan Wibisono',
                'phone' => '081299887766',
                'occupation' => 'Wiraswasta',
                'address' => 'Jl. Kutai No. 4, Surabaya',
            ]);

        $createResp->assertStatus(201)
            ->assertJsonPath('data.full_name', 'Ir. Gunawan Wibisono')
            ->assertJsonPath('data.phone', '081299887766');

        $guardianId = $createResp->json('data.id');

        $this->assertDatabaseHas('guardians', [
            'id' => $guardianId,
            'full_name' => 'Ir. Gunawan Wibisono',
            'phone' => '081299887766',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'guardian.created',
            'entity' => 'Guardian',
            'entity_id' => $guardianId,
        ]);

        // 2. Update Guardian
        $updateResp = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/guardians/{$guardianId}", [
                'occupation' => 'Direktur Utama',
                'phone' => '081299887799',
            ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('data.occupation', 'Direktur Utama')
            ->assertJsonPath('data.phone', '081299887799');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'guardian.updated',
            'entity' => 'Guardian',
            'entity_id' => $guardianId,
        ]);

        // 3. Delete Guardian (Soft delete)
        $deleteResp = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/guardians/{$guardianId}");

        $deleteResp->assertStatus(200);

        $this->assertSoftDeleted('guardians', [
            'id' => $guardianId,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'guardian.deleted',
            'entity' => 'Guardian',
            'entity_id' => $guardianId,
        ]);
    }

    /**
     * BE-MST-04: Validation errors on creating guardian
     */
    public function test_be_mst_04_guardian_validation_errors(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/guardians', [])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details']]);
    }

    /**
     * BE-MST-04: Link multiple guardians to one student (ayah and ibu)
     */
    public function test_be_mst_04_link_multiple_guardians_to_one_student(): void
    {
        // 1. Link Ayah as primary
        $resp1 = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/students/{$this->student1->id}/guardians", [
                'guardian_id' => $this->guardian1->id,
                'relationship' => 'ayah',
                'is_primary' => true,
            ]);

        $resp1->assertStatus(201);

        // 2. Link Ibu as secondary
        $resp2 = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/students/{$this->student1->id}/guardians", [
                'guardian_id' => $this->guardian2->id,
                'relationship' => 'ibu',
                'is_primary' => false,
            ]);

        $resp2->assertStatus(201);

        // Verify both links in DB
        $this->assertDatabaseHas('student_guardians', [
            'student_id' => $this->student1->id,
            'guardian_id' => $this->guardian1->id,
            'relationship' => 'ayah',
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('student_guardians', [
            'student_id' => $this->student1->id,
            'guardian_id' => $this->guardian2->id,
            'relationship' => 'ibu',
            'is_primary' => false,
        ]);

        // Verify GET /students/{id}/guardians returns both
        $listResp = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/students/{$this->student1->id}/guardians");

        $listResp->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /**
     * BE-MST-04: Link one guardian to multiple students (parent with 2 children)
     */
    public function test_be_mst_04_link_one_guardian_to_multiple_students(): void
    {
        // Link Guardian 1 to Student 1
        $this->actingAs($this->adminUser)
            ->postJson("/api/v1/students/{$this->student1->id}/guardians", [
                'guardian_id' => $this->guardian1->id,
                'relationship' => 'ayah',
                'is_primary' => true,
            ])
            ->assertStatus(201);

        // Link Guardian 1 to Student 2
        $this->actingAs($this->adminUser)
            ->postJson("/api/v1/students/{$this->student2->id}/guardians", [
                'guardian_id' => $this->guardian1->id,
                'relationship' => 'ayah',
                'is_primary' => true,
            ])
            ->assertStatus(201);

        // Verify GET /guardians/{id} shows both students
        $resp = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/guardians/{$this->guardian1->id}");

        $resp->assertStatus(200)
            ->assertJsonCount(2, 'data.students');
    }

    /**
     * BE-MST-04: Cannot link the same guardian to the same student twice (409 CONFLICT)
     */
    public function test_be_mst_04_cannot_duplicate_link_same_guardian(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson("/api/v1/students/{$this->student1->id}/guardians", [
                'guardian_id' => $this->guardian1->id,
                'relationship' => 'ayah',
                'is_primary' => true,
            ])
            ->assertStatus(201);

        // Duplicate link attempt
        $respDup = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/students/{$this->student1->id}/guardians", [
                'guardian_id' => $this->guardian1->id,
                'relationship' => 'ayah',
            ]);

        $respDup->assertStatus(409)
            ->assertJsonPath('error.code', 'CONFLICT');
    }

    /**
     * BE-MST-04: Enforces single primary guardian per student
     */
    public function test_be_mst_04_enforces_single_primary_guardian_per_student(): void
    {
        // 1. Ayah as primary
        $this->actingAs($this->adminUser)
            ->postJson("/api/v1/students/{$this->student1->id}/guardians", [
                'guardian_id' => $this->guardian1->id,
                'relationship' => 'ayah',
                'is_primary' => true,
            ]);

        // 2. Ibu linked as primary -> Ayah becomes non-primary
        $this->actingAs($this->adminUser)
            ->postJson("/api/v1/students/{$this->student1->id}/guardians", [
                'guardian_id' => $this->guardian2->id,
                'relationship' => 'ibu',
                'is_primary' => true,
            ]);

        $this->assertDatabaseHas('student_guardians', [
            'student_id' => $this->student1->id,
            'guardian_id' => $this->guardian1->id,
            'is_primary' => false,
        ]);

        $this->assertDatabaseHas('student_guardians', [
            'student_id' => $this->student1->id,
            'guardian_id' => $this->guardian2->id,
            'is_primary' => true,
        ]);

        // 3. Update Ayah back to primary via PUT
        $updateResp = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/students/{$this->student1->id}/guardians/{$this->guardian1->id}", [
                'is_primary' => true,
            ]);

        $updateResp->assertStatus(200);

        $this->assertDatabaseHas('student_guardians', [
            'student_id' => $this->student1->id,
            'guardian_id' => $this->guardian1->id,
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('student_guardians', [
            'student_id' => $this->student1->id,
            'guardian_id' => $this->guardian2->id,
            'is_primary' => false,
        ]);
    }

    /**
     * BE-MST-04: Detach guardian from student and logs audit
     */
    public function test_be_mst_04_can_detach_guardian_from_student(): void
    {
        $this->student1->guardians()->attach($this->guardian1->id, [
            'relationship' => 'ayah',
            'is_primary' => true,
        ]);

        $resp = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/students/{$this->student1->id}/guardians/{$this->guardian1->id}");

        $resp->assertStatus(200);

        $this->assertDatabaseMissing('student_guardians', [
            'student_id' => $this->student1->id,
            'guardian_id' => $this->guardian1->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'student_guardian.unlinked',
            'entity' => 'StudentGuardian',
        ]);
    }

    /**
     * BE-MST-04: IDOR Protection: Guardian can only view self and linked children
     */
    public function test_be_mst_04_idor_protection_for_guardian(): void
    {
        // Link Guardian 1 to Student 1 only
        $this->student1->guardians()->attach($this->guardian1->id, [
            'relationship' => 'ayah',
            'is_primary' => true,
        ]);

        // Guardian 1 views own profile -> 200 OK
        $this->actingAs($this->guardianUser1)
            ->getJson("/api/v1/guardians/{$this->guardian1->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $this->guardian1->id);

        // Guardian 1 attempts viewing Guardian 2 profile -> 403 FORBIDDEN
        $this->actingAs($this->guardianUser1)
            ->getJson("/api/v1/guardians/{$this->guardian2->id}")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // Guardian 1 views own child's guardians -> 200 OK
        $this->actingAs($this->guardianUser1)
            ->getJson("/api/v1/students/{$this->student1->id}/guardians")
            ->assertStatus(200);

        // Guardian 1 attempts viewing unrelated Student 2's guardians -> 403 FORBIDDEN
        $this->actingAs($this->guardianUser1)
            ->getJson("/api/v1/students/{$this->student2->id}/guardians")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-MST-04: List guardians with search and scoping
     */
    public function test_be_mst_04_list_guardians_with_search_and_scoping(): void
    {
        // Admin sees all 2 guardians
        $respAdmin = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/guardians');

        $respAdmin->assertStatus(200)
            ->assertJsonCount(2, 'data');

        // Search by name
        $respSearch = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/guardians?q=Bambang');

        $respSearch->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Dr. Bambang Kusumo, Sp.A.');

        // Guardian user only sees self in list
        $respGuardian = $this->actingAs($this->guardianUser1)
            ->getJson('/api/v1/guardians');

        $respGuardian->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->guardian1->id);
    }
}
