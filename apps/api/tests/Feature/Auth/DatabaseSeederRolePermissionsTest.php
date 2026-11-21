<?php

namespace Tests\Feature\Auth;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseSeederRolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * GAP F: DatabaseSeeder memberikan permission default sesuai peran dan prinsip least privilege.
     */
    public function test_database_seeder_assigns_default_permissions_per_role(): void
    {
        $this->seed(DatabaseSeeder::class);

        // 1. Role Teacher
        $teacherRole = Role::where('code', 'teacher')->firstOrFail();
        $this->assertTrue($teacherRole->permissions()->where('code', 'academic.attendance.write')->exists());
        $this->assertTrue($teacherRole->permissions()->where('code', 'academic.grade.write')->exists());
        $this->assertTrue($teacherRole->permissions()->where('code', 'cbt.bank.write')->exists());
        // Guru tidak boleh memiliki hak admin sistem
        $this->assertFalse($teacherRole->permissions()->where('code', 'admin.config.edit')->exists());

        // 2. Role Counselor / Guru BK
        $counselorRole = Role::where('code', 'counselor')->firstOrFail();
        $this->assertTrue($counselorRole->permissions()->where('code', 'counseling.case.write')->exists());
        $this->assertTrue($counselorRole->permissions()->where('code', 'bk.counseling.manage')->exists());
        $this->assertTrue($counselorRole->permissions()->where('code', 'complaint.manage')->exists());
        // BK tidak boleh mengedit master fasilitas
        $this->assertFalse($counselorRole->permissions()->where('code', 'facility.asset.manage')->exists());

        // 3. Role Student
        $studentRole = Role::where('code', 'student')->firstOrFail();
        $this->assertTrue($studentRole->permissions()->where('code', 'cbt.exam.take')->exists());
        $this->assertTrue($studentRole->permissions()->where('code', 'cbt.exam.review')->exists());
        $this->assertTrue($studentRole->permissions()->where('code', 'academic.schedule.read')->exists());
        // Siswa tidak boleh membuat bank soal CBT atau mengedit nilai
        $this->assertFalse($studentRole->permissions()->where('code', 'cbt.bank.write')->exists());
        $this->assertFalse($studentRole->permissions()->where('code', 'academic.grade.write')->exists());

        // 4. Role Guardian
        $guardianRole = Role::where('code', 'guardian')->firstOrFail();
        $this->assertTrue($guardianRole->permissions()->where('code', 'academic.attendance.read')->exists());
        $this->assertTrue($guardianRole->permissions()->where('code', 'academic.grade.read')->exists());
        // Wali tidak boleh ikut ujian CBT atau mengubah data
        $this->assertFalse($guardianRole->permissions()->where('code', 'cbt.exam.take')->exists());

        // 5. Role Principal
        $principalRole = Role::where('code', 'principal')->firstOrFail();
        $this->assertTrue($principalRole->permissions()->where('code', 'cbt.exam.manage')->exists());
        $this->assertTrue($principalRole->permissions()->where('code', 'academic.report.generate')->exists());
        $this->assertTrue($principalRole->permissions()->where('code', 'executive.dashboard.view')->exists());
        $this->assertTrue($principalRole->permissions()->where('code', 'reports.view')->exists());

        // 6. Role Facility Staff
        $facilityRole = Role::where('code', 'facility_staff')->firstOrFail();
        $this->assertTrue($facilityRole->permissions()->where('code', 'facility.booking.manage')->exists());
        $this->assertTrue($facilityRole->permissions()->where('code', 'facility.asset.manage')->exists());

        // 7. Role Librarian
        $librarianRole = Role::where('code', 'librarian')->firstOrFail();
        $this->assertTrue($librarianRole->permissions()->where('code', 'library.book.manage')->exists());

        // 8. Role PPDB Committee
        $ppdbRole = Role::where('code', 'ppdb_committee')->firstOrFail();
        $this->assertTrue($ppdbRole->permissions()->where('code', 'ppdb.applicant.verify')->exists());

        // 9. Role Student Affairs / Kesiswaan (SEC-RBAC-02)
        $studentAffairsRole = Role::where('code', 'student_affairs')->firstOrFail();
        $this->assertTrue($studentAffairsRole->permissions()->where('code', 'student.point.read')->exists());
        $this->assertTrue($studentAffairsRole->permissions()->where('code', 'student.point.write')->exists());
        $this->assertTrue($studentAffairsRole->permissions()->where('code', 'master.student.read')->exists());
        $this->assertTrue($studentAffairsRole->permissions()->where('code', 'reports.view')->exists());
        $this->assertFalse($studentAffairsRole->permissions()->where('code', 'facility.asset.manage')->exists());
        $this->assertFalse($studentAffairsRole->permissions()->where('code', 'cbt.bank.write')->exists());

        // 10. Role Public Relations / Humas (SEC-RBAC-02)
        $prRole = Role::where('code', 'public_relations')->firstOrFail();
        $this->assertTrue($prRole->permissions()->where('code', 'guest.book.read')->exists());
        $this->assertTrue($prRole->permissions()->where('code', 'guest.book.manage')->exists());
        $this->assertTrue($prRole->permissions()->where('code', 'complaint.read')->exists());
        $this->assertTrue($prRole->permissions()->where('code', 'complaint.manage')->exists());
        $this->assertTrue($prRole->permissions()->where('code', 'cms.post.manage')->exists());
        $this->assertTrue($prRole->permissions()->where('code', 'cms.site.manage')->exists());
        $this->assertFalse($prRole->permissions()->where('code', 'academic.grade.write')->exists());
        $this->assertFalse($prRole->permissions()->where('code', 'student.point.write')->exists());
    }

    /**
     * GAP F: DatabaseSeeder bersifat idempoten dan tidak menduplikasi pivot role_permissions.
     */
    public function test_database_seeder_is_idempotent(): void
    {
        // Jalankan seeder pertama kali
        $this->seed(DatabaseSeeder::class);
        $countFirstRun = DB::table('role_permissions')->count();

        // Jalankan seeder kedua kali
        $this->seed(DatabaseSeeder::class);
        $countSecondRun = DB::table('role_permissions')->count();

        // Jumlah entri pivot tidak boleh bertambah (idempoten)
        $this->assertEquals($countFirstRun, $countSecondRun);
    }

    /**
     * GAP F: syncWithoutDetaching mempertahankan custom permission yang ditambahkan admin secara manual.
     */
    public function test_seeder_preserves_manually_assigned_custom_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $teacherRole = Role::where('code', 'teacher')->firstOrFail();

        // Admin manual memberikan permission tambahan khusus kepada role guru
        $customPerm = Permission::firstOrCreate(
            ['code' => 'custom.special.access'],
            ['name' => 'Akses Khusus Guru', 'module' => 'custom', 'description' => 'Custom grant']
        );
        $teacherRole->permissions()->syncWithoutDetaching([$customPerm->id]);

        $this->assertTrue($teacherRole->permissions()->where('code', 'custom.special.access')->exists());

        // Jalankan ulang seeder DatabaseSeeder
        $this->seed(DatabaseSeeder::class);

        // Custom permission tidak boleh hilang (tidak terhapus karena syncWithoutDetaching)
        $teacherRole->refresh();
        $this->assertTrue($teacherRole->permissions()->where('code', 'custom.special.access')->exists());
    }

    /**
     * GAP F: DatabaseSeeder membersihkan izin administratif berbahaya jika sebelumnya terpasang pada student atau guardian.
     */
    public function test_database_seeder_detaches_prohibited_permissions_from_student_and_guardian(): void
    {
        $this->seed(DatabaseSeeder::class);

        $studentRole = Role::where('code', 'student')->firstOrFail();
        $guardianRole = Role::where('code', 'guardian')->firstOrFail();

        $dangerousPerm = Permission::where('code', 'academic.grade.write')->firstOrFail();
        $cbtBankPerm = Permission::where('code', 'cbt.bank.write')->firstOrFail();

        // Sengaja kontaminasi role student & guardian dengan permission administratif
        $studentRole->permissions()->attach([$dangerousPerm->id, $cbtBankPerm->id]);
        $guardianRole->permissions()->attach([$dangerousPerm->id, $cbtBankPerm->id]);

        $this->assertTrue($studentRole->permissions()->where('code', 'academic.grade.write')->exists());
        $this->assertTrue($guardianRole->permissions()->where('code', 'academic.grade.write')->exists());

        // Jalankan kembali seeder
        $this->seed(DatabaseSeeder::class);

        // Verifikasi izin terlarang telah dicabut kembali
        $studentRole->refresh();
        $guardianRole->refresh();

        $this->assertFalse($studentRole->permissions()->where('code', 'academic.grade.write')->exists());
        $this->assertFalse($studentRole->permissions()->where('code', 'cbt.bank.write')->exists());
        $this->assertFalse($guardianRole->permissions()->where('code', 'academic.grade.write')->exists());
        $this->assertFalse($guardianRole->permissions()->where('code', 'cbt.bank.write')->exists());
    }
}
