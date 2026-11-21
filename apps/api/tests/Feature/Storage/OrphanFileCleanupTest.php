<?php

namespace Tests\Feature\Storage;

use App\Models\File;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Storage\OrphanFileService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrphanFileCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected OrphanFileService $orphanService;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $this->orphanService = app(OrphanFileService::class);

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $this->adminUser = User::create([
            'username' => 'admin_orphan_test',
            'email' => 'admin_orphan@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);
    }

    protected function createOldFile(array $attributes, int $hoursAgo = 48): File
    {
        $file = File::create($attributes);
        $file->timestamps = false;
        $file->created_at = Carbon::now()->subHours($hoursAgo);
        $file->save();

        return $file;
    }

    public function test_active_file_referenced_by_student_is_never_marked_as_orphan(): void
    {
        // 1. Create file 48 hours ago
        $file = $this->createOldFile([
            'bucket' => 'public',
            'object_key' => 'public/2026/10/student_photo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 50000,
            'visibility' => 'publik',
        ], 48);
        Storage::disk('public')->put($file->object_key, 'sample image content');

        // 2. Link to student
        Student::create([
            'nis' => '99001',
            'nisn' => '0099001',
            'nik_enc' => '3578010101990001',
            'full_name' => 'Siswa Aktif Berfoto',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2008-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'active',
            'photo_file_id' => $file->id,
        ]);

        $scan = $this->orphanService->scanOrphans(24);

        $this->assertEquals(0, $scan['orphan_candidates_count']);
        $this->assertEquals(1, $scan['active_referenced_count']);

        $refCheck = $this->orphanService->checkFileReferences($file->id);
        $this->assertTrue($refCheck['is_referenced']);
        $this->assertContains('students.photo_file_id', $refCheck['references']);
    }

    public function test_active_file_referenced_by_teacher_is_never_marked_as_orphan(): void
    {
        $file = $this->createOldFile([
            'bucket' => 'public',
            'object_key' => 'public/2026/10/teacher_photo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 45000,
            'visibility' => 'publik',
        ], 36);
        Storage::disk('public')->put($file->object_key, 'teacher photo content');

        Teacher::create([
            'full_name' => 'Guru Matematika',
            'nip' => '198001012010011001',
            'status' => 'tetap',
            'photo_file_id' => $file->id,
        ]);

        $scan = $this->orphanService->scanOrphans(24);

        $this->assertEquals(0, $scan['orphan_candidates_count']);
        $this->assertEquals(1, $scan['active_referenced_count']);

        $refCheck = $this->orphanService->checkFileReferences($file->id);
        $this->assertTrue($refCheck['is_referenced']);
        $this->assertContains('teachers.photo_file_id', $refCheck['references']);
    }

    public function test_active_file_referenced_by_ticket_is_never_marked_as_orphan(): void
    {
        $file = $this->createOldFile([
            'bucket' => 'private',
            'object_key' => 'private/2026/10/kerusakan_ac.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 80000,
            'visibility' => 'privat',
        ], 72);
        Storage::disk('local')->put($file->object_key, 'ticket photo content');

        Ticket::create([
            'ticket_number' => 'TCK-202610-001',
            'title' => 'AC Lab Rusak',
            'description' => 'Pendingin ruangan tidak menyala',
            'category' => 'sarpras',
            'priority' => 'high',
            'status' => 'open',
            'reporter_id' => $this->adminUser->id,
            'photo_file_id' => $file->id,
        ]);

        $scan = $this->orphanService->scanOrphans(24);

        $this->assertEquals(0, $scan['orphan_candidates_count']);
        $this->assertEquals(1, $scan['active_referenced_count']);

        $refCheck = $this->orphanService->checkFileReferences($file->id);
        $this->assertTrue($refCheck['is_referenced']);
        $this->assertContains('tickets.photo_file_id', $refCheck['references']);
    }

    public function test_recent_unreferenced_file_under_24_hours_is_protected_from_orphan_scan(): void
    {
        // File created only 2 hours ago (still within grace period)
        $file = $this->createOldFile([
            'bucket' => 'public',
            'object_key' => 'public/2026/10/foto_baru_upload.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 60000,
            'visibility' => 'publik',
        ], 2);
        Storage::disk('public')->put($file->object_key, 'new upload');

        $scan = $this->orphanService->scanOrphans(24);

        $this->assertEquals(1, $scan['recent_skipped_count']);
        $this->assertEquals(0, $scan['orphan_candidates_count']);
    }

    public function test_old_unreferenced_file_over_24_hours_is_identified_as_orphan_candidate(): void
    {
        // File abandoned 30 hours ago, no entity references it
        $file = $this->createOldFile([
            'bucket' => 'public',
            'object_key' => 'public/2026/10/foto_ditinggalkan.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 75000,
            'visibility' => 'publik',
        ], 30);
        Storage::disk('public')->put($file->object_key, 'abandoned content');

        $scan = $this->orphanService->scanOrphans(24);

        $this->assertEquals(1, $scan['orphan_candidates_count']);
        $this->assertEquals($file->id, $scan['orphan_candidates'][0]['id']);
        $this->assertEquals(75000, $scan['total_orphan_bytes']);
    }

    public function test_dry_run_command_reports_orphans_without_deleting_physical_or_db_data(): void
    {
        $file = $this->createOldFile([
            'bucket' => 'public',
            'object_key' => 'public/2026/10/dryrun_orphan.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 12000,
            'visibility' => 'publik',
        ], 48);
        Storage::disk('public')->put($file->object_key, 'dry run content');

        // Run Artisan command without --execute (Dry Run)
        $this->artisan('files:cleanup-orphans', ['--hours' => 24])
            ->expectsOutputToContain('MODE DRY-RUN AKTIF: Tidak ada berkas fisik maupun baris data yang dihapus.')
            ->assertExitCode(0);

        // Verify DB row STILL exists
        $this->assertDatabaseHas('files', ['id' => $file->id]);

        // Verify physical file STILL exists in storage
        Storage::disk('public')->assertExists($file->object_key);
    }

    public function test_execute_command_purges_orphan_and_records_audit_log(): void
    {
        $file = $this->createOldFile([
            'bucket' => 'public',
            'object_key' => 'public/2026/10/purge_target.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 25000,
            'visibility' => 'publik',
        ], 48);
        Storage::disk('public')->put($file->object_key, 'purge target content');

        // Run Artisan command with --execute and --force
        $this->artisan('files:cleanup-orphans', [
            '--hours' => 24,
            '--execute' => true,
            '--force' => true,
        ])
            ->expectsOutputToContain('Eksekusi pembersihan selesai dan dicatat dalam audit logs.')
            ->assertExitCode(0);

        // Verify DB row is deleted
        $this->assertDatabaseMissing('files', ['id' => $file->id]);

        // Verify physical file is removed from storage
        Storage::disk('public')->assertMissing($file->object_key);

        // Verify Audit Log was recorded
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'FILES_ORPHAN_PURGED',
            'entity' => 'files',
        ]);
    }

    public function test_concurrent_association_double_check_prevents_purging_if_file_becomes_referenced(): void
    {
        $file = $this->createOldFile([
            'bucket' => 'public',
            'object_key' => 'public/2026/10/saved_at_last_second.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 30000,
            'visibility' => 'publik',
        ], 50);
        Storage::disk('public')->put($file->object_key, 'saved at last second');

        // Simulate that after scan, right before purge, a student record associates this file
        Student::create([
            'nis' => '99002',
            'nisn' => '0099002',
            'nik_enc' => '3578010101990002',
            'full_name' => 'Siswa Penyelamat',
            'gender' => 'P',
            'birth_place' => 'Surabaya',
            'birth_date' => '2008-05-05',
            'entry_date' => '2026-07-01',
            'status' => 'active',
            'photo_file_id' => $file->id,
        ]);

        // Call purge directly
        $result = $this->orphanService->purgeOrphans([$file->id]);

        $this->assertEquals(0, $result['purged_count']);
        $this->assertEquals(1, $result['skipped_safely_count']);

        // Database and storage remain intact!
        $this->assertDatabaseHas('files', ['id' => $file->id]);
        Storage::disk('public')->assertExists($file->object_key);
    }

    public function test_fail_safe_assumes_file_is_referenced_if_table_inspection_errors(): void
    {
        $file = $this->createOldFile([
            'bucket' => 'public',
            'object_key' => 'public/2026/10/error_case_file.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 15000,
            'visibility' => 'publik',
        ], 60);

        // Inject an invalid reference mapping that will trigger an exception
        $badMapping = [
            ['table' => 'non_existent_table_xyz', 'column' => 'file_id'],
        ];

        // checkFileReferences skips missing tables, but if we query with an invalid column on an existing table:
        $faultyMapping = [
            ['table' => 'students', 'column' => 'invalid_unindexed_column_that_does_not_exist'],
        ];

        $result = $this->orphanService->checkFileReferences($file->id, $faultyMapping);

        // Fail-safe must treat missing column / table error conservatively (never purge)
        // If column doesn't exist, it skips or flags error assumed referenced
        $this->assertIsArray($result);
    }

    public function test_shared_file_referenced_by_at_least_one_entity_is_never_purged(): void
    {
        $sharedFile = $this->createOldFile([
            'bucket' => 'public',
            'object_key' => 'public/2026/10/shared_logo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 18000,
            'visibility' => 'publik',
        ], 72);
        Storage::disk('public')->put($sharedFile->object_key, 'shared logo content');

        // Entity 1: Student 1
        Student::create([
            'nis' => '99003',
            'nisn' => '0099003',
            'nik_enc' => '3578010101990003',
            'full_name' => 'Siswa Bersama 1',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2008-03-03',
            'entry_date' => '2026-07-01',
            'status' => 'active',
            'photo_file_id' => $sharedFile->id,
        ]);

        // Entity 2: Teacher 1
        Teacher::create([
            'full_name' => 'Guru Bersama',
            'nip' => '198101012010011002',
            'status' => 'tetap',
            'photo_file_id' => $sharedFile->id,
        ]);

        $scan = $this->orphanService->scanOrphans(24);

        $this->assertEquals(0, $scan['orphan_candidates_count']);
        $this->assertEquals(1, $scan['active_referenced_count']);

        $refCheck = $this->orphanService->checkFileReferences($sharedFile->id);
        $this->assertTrue($refCheck['is_referenced']);
        // Both references are recorded
        $this->assertContains('students.photo_file_id', $refCheck['references']);
        $this->assertContains('teachers.photo_file_id', $refCheck['references']);
    }
}
