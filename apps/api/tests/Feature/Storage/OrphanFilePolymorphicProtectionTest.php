<?php

namespace Tests\Feature\Storage;

use App\Models\Complaint;
use App\Models\File;
use App\Models\ProgramMaterial;
use App\Models\ProgramTask;
use App\Models\ProgramTaskSubmission;
use App\Models\School;
use App\Models\SiteSetting;
use App\Models\Student;
use App\Services\Storage\OrphanFileService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrphanFilePolymorphicProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected OrphanFileService $orphanService;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->orphanService = app(OrphanFileService::class);
    }

    protected function createOldFile(array $attributes, int $hoursAgo = 48): File
    {
        $file = File::create($attributes);
        $file->timestamps = false;
        $file->created_at = Carbon::now()->subHours($hoursAgo);
        $file->save();

        return $file;
    }

    public function test_p0_02_complaint_polymorphic_attachment_is_never_marked_orphan_or_purged(): void
    {
        $complaint = Complaint::create([
            'ticket_code' => 'ASP-20261127-TEST',
            'category' => 'bullying',
            'content' => 'Laporan dugaan perundungan di kelas.',
            'is_anonymous' => true,
            'status' => 'baru',
        ]);

        $attachment = $this->createOldFile([
            'owner_type' => 'complaint',
            'owner_id' => $complaint->id,
            'bucket' => 'private',
            'object_key' => 'private/complaints/2026/11/bukti-bullying.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 12345,
            'visibility' => 'privat',
        ], 72);
        Storage::disk('local')->put($attachment->object_key, 'bukti foto rahasia');

        // Scan dry-run tidak boleh memasukkan lampiran pengaduan sebagai orphan
        $scan = $this->orphanService->scanOrphans(24);
        $this->assertEquals(0, $scan['orphan_candidates_count']);
        $this->assertEquals(1, $scan['active_referenced_count']);

        // Double check satuan juga harus melaporkan referensi polimorfik aktif
        $check = $this->orphanService->checkFileReferences($attachment->id);
        $this->assertTrue($check['is_referenced']);
        $this->assertContains('complaints.polymorphic(complaint)', $check['references']);

        // Sekalipun ID dipaksa masuk purgeOrphans, double-check wajib membatalkan penghapusan
        $purge = $this->orphanService->purgeOrphans([$attachment->id]);
        $this->assertEquals(0, $purge['purged_count']);
        $this->assertEquals(1, $purge['skipped_safely_count']);
        $this->assertDatabaseHas('files', ['id' => $attachment->id]);
        Storage::disk('local')->assertExists($attachment->object_key);
    }

    public function test_p0_02_non_fk_program_khusus_school_logo_and_site_settings_files_are_protected(): void
    {
        // 1. School logo_file_id (tanpa FK di migrasi)
        $logoFile = $this->createOldFile([
            'bucket' => 'public',
            'object_key' => 'public/2026/11/logo-sekolah.png',
            'mime_type' => 'image/png',
            'size_bytes' => 5000,
            'visibility' => 'publik',
        ], 48);
        School::create([
            'name' => 'SMP Lab UM',
            'npsn' => '20533700',
            'logo_file_id' => $logoFile->id,
            'is_active' => true,
        ]);

        // 2. ProgramMaterial, ProgramTask, ProgramTaskSubmission (tanpa FK di migrasi)
        $progMatFile = $this->createOldFile([
            'bucket' => 'private',
            'object_key' => 'private/2026/11/tahfidz-modul.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 15000,
            'visibility' => 'privat',
        ], 48);
        ProgramMaterial::create([
            'program_type' => 'tahfidz',
            'title' => 'Modul Juz 30',
            'file_id' => $progMatFile->id,
            'target_scope' => 'program',
            'is_published' => true,
        ]);

        $progTaskFile = $this->createOldFile([
            'bucket' => 'private',
            'object_key' => 'private/2026/11/icp-task.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 12000,
            'visibility' => 'privat',
        ], 48);
        $task = ProgramTask::create([
            'program_type' => 'icp',
            'title' => 'Cambridge Worksheet 1',
            'file_id' => $progTaskFile->id,
            'target_scope' => 'program',
            'is_published' => true,
        ]);

        $student = Student::create([
            'nis' => '20269901',
            'nisn' => '0099887701',
            'nik_enc' => '3578010101019901',
            'full_name' => 'Siswa Program Uji',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $progSubFile = $this->createOldFile([
            'bucket' => 'private',
            'object_key' => 'private/2026/11/icp-submission.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 11000,
            'visibility' => 'privat',
        ], 48);
        ProgramTaskSubmission::create([
            'task_id' => $task->id,
            'student_id' => $student->id,
            'file_id' => $progSubFile->id,
            'status' => 'submitted',
            'submitted_at' => now()->subDays(2),
        ]);

        // 3. File URL tersimpan di JSON site_settings
        $siteAssetFile = $this->createOldFile([
            'bucket' => 'public',
            'object_key' => 'public/2026/11/kepsek-banner-resmi.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 8000,
            'visibility' => 'publik',
        ], 48);
        SiteSetting::create([
            'setting_key' => 'school_profile',
            'setting_value' => [
                'principal_photo_url' => '/storage/public/2026/11/kepsek-banner-resmi.jpg',
            ],
        ]);

        // 4. Satu berkas yatim sejati (tidak direferensikan di mana pun)
        $realOrphan = $this->createOldFile([
            'owner_type' => 'User',
            'owner_id' => Str::uuid()->toString(),
            'bucket' => 'public',
            'object_key' => 'public/2026/11/abandoned-orphan.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 4096,
            'visibility' => 'publik',
        ], 48);

        $scan = $this->orphanService->scanOrphans(24);

        $this->assertEquals(5, $scan['active_referenced_count']);
        $this->assertEquals(1, $scan['orphan_candidates_count']);
        $this->assertEquals($realOrphan->id, $scan['orphan_candidates'][0]['id']);
    }

    public function test_p0_02_abandoned_complaint_pending_without_complaint_is_detected_in_dry_run_only(): void
    {
        $abandonedPending = $this->createOldFile([
            'owner_type' => 'complaint_pending',
            'owner_id' => null,
            'bucket' => 'private',
            'object_key' => 'private/complaints/2026/11/abandoned-pending.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 3000,
            'visibility' => 'privat',
        ], 48);
        Storage::disk('local')->put($abandonedPending->object_key, 'abandoned');

        $this->artisan('files:cleanup-orphans', ['--hours' => 24])
            ->expectsOutputToContain('MODE DRY-RUN AKTIF')
            ->assertExitCode(0);

        // Mode default dry-run tidak boleh menghapus file
        $this->assertDatabaseHas('files', ['id' => $abandonedPending->id]);
        Storage::disk('local')->assertExists($abandonedPending->object_key);
    }
}
