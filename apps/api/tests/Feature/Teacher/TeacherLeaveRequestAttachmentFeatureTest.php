<?php

namespace Tests\Feature\Teacher;

use App\Models\ClassStudent;
use App\Models\File;
use App\Models\LeaveRequest;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\TeacherDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeacherLeaveRequestAttachmentFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $userWalas7A;

    protected User $userTeacherMtk;

    protected User $userOtherTeacher;

    protected SchoolClass $class7A;

    protected SchoolClass $class7B;

    protected Student $student7A;

    protected Student $student7B;

    protected File $fileAttachment7A;

    protected File $fileAttachment7B;

    protected LeaveRequest $leaveRequest7A;

    protected LeaveRequest $leaveRequest7B;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        (new TeacherDemoSeeder)->run();

        $this->userWalas7A = User::where('username', 'walas.7a.staging')->firstOrFail();
        $this->userTeacherMtk = User::where('username', 'guru.mtk.staging')->firstOrFail();

        // Guru lain yang tidak mengajar 7-A (misal kurikulum tanpa assignment 7-A)
        $this->userOtherTeacher = User::create([
            'username' => 'guru.lain.staging',
            'email' => 'guru.lain@smp-labum.sch.id',
            'password_hash' => bcrypt('Secret123!'),
            'status' => 'active',
        ]);
        $roleTeacher = \App\Models\Role::where('code', 'teacher')->firstOrFail();
        $this->userOtherTeacher->roles()->attach($roleTeacher->id);

        $teacherOther = \App\Models\Teacher::create([
            'user_id' => $this->userOtherTeacher->id,
            'nip' => '199505052020011005',
            'nuptk' => '5555666677778888',
            'full_name' => 'Guru Lain Luar Kelas, S.Pd.',
            'status' => 'aktif',
        ]);

        $this->class7A = SchoolClass::where('name', '7-A')->firstOrFail();
        $this->class7B = SchoolClass::where('name', '7-B')->firstOrFail();

        $this->student7A = $this->class7A->students()->firstOrFail();

        // Buat student 7-B
        $userStudentB = User::create([
            'username' => 'siswa.7b.attachment.test',
            'email' => 'siswa.7b.attachment@smp-labum.sch.id',
            'password_hash' => bcrypt('Secret123!'),
            'status' => 'active',
        ]);
        $this->student7B = Student::create([
            'school_id' => $this->class7B->school_id ?? '00000000-0000-0000-0000-000000000001',
            'user_id' => $userStudentB->id,
            'nisn' => '9991112223',
            'nis' => '7B099',
            'nik_enc' => '3578010101018888',
            'full_name' => 'Bintang Pratama 7B',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-09-20',
            'status' => 'aktif',
            'entry_date' => '2026-07-01',
        ]);
        ClassStudent::create([
            'class_id' => $this->class7B->id,
            'student_id' => $this->student7B->id,
            'joined_at' => now(),
        ]);

        // Simpan file fisik di fake storage
        $objectKey7A = 'leave-attachments/2026/11/surat_dokter_7a.pdf';
        Storage::disk('local')->put($objectKey7A, '%PDF-1.4 Mock Surat Keterangan Dokter 7-A');

        $this->fileAttachment7A = File::create([
            'school_id' => $this->class7A->school_id ?? '00000000-0000-0000-0000-000000000001',
            'owner_id' => $this->student7A->id,
            'owner_type' => 'Student',
            'bucket' => 'leave-attachments',
            'object_key' => $objectKey7A,
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'visibility' => 'privat',
            'checksum' => hash('sha256', '%PDF-1.4 Mock Surat Keterangan Dokter 7-A'),
        ]);

        $this->leaveRequest7A = LeaveRequest::create([
            'student_id' => $this->student7A->id,
            'requested_by' => $this->student7A->user_id,
            'leave_type' => 'sakit',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'reason' => 'Sakit demam berdarah dan rawat jalan.',
            'attachment_file_id' => $this->fileAttachment7A->id,
            'status' => 'menunggu',
        ]);

        $objectKey7B = 'leave-attachments/2026/11/surat_dokter_7b.pdf';
        Storage::disk('local')->put($objectKey7B, '%PDF-1.4 Mock Surat Keterangan Dokter 7-B');

        $this->fileAttachment7B = File::create([
            'school_id' => $this->class7B->school_id ?? '00000000-0000-0000-0000-000000000001',
            'owner_id' => $this->student7B->id,
            'owner_type' => 'Student',
            'bucket' => 'leave-attachments',
            'object_key' => $objectKey7B,
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'visibility' => 'privat',
            'checksum' => hash('sha256', '%PDF-1.4 Mock Surat Keterangan Dokter 7-B'),
        ]);

        $this->leaveRequest7B = LeaveRequest::create([
            'student_id' => $this->student7B->id,
            'requested_by' => $this->student7B->user_id,
            'leave_type' => 'sakit',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'reason' => 'Sakit flu dan batuk.',
            'attachment_file_id' => $this->fileAttachment7B->id,
            'status' => 'menunggu',
        ]);
    }

    /**
     * FIX-GURU-07 AC1: Homeroom teacher can resolve and download leave attachment of managed student.
     */
    public function test_fix_guru_07_homeroom_teacher_can_get_signed_url_for_managed_student_leave_attachment(): void
    {
        $response = $this->actingAs($this->userWalas7A)
            ->getJson("/api/v1/files/{$this->fileAttachment7A->id}/url");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'url',
                    'visibility',
                    'mime_type',
                    'size_bytes',
                    'expires_at',
                ],
            ])
            ->assertJsonPath('data.visibility', 'privat')
            ->assertJsonPath('data.id', $this->fileAttachment7A->id);

        $signedUrl = $response->json('data.url');
        $this->assertNotEmpty($signedUrl);

        // Unduh berkas fisik via Signed URL
        $downloadResponse = $this->get($signedUrl);
        $downloadResponse->assertOk();
    }

    /**
     * FIX-GURU-07 AC1: Subject teacher who teaches the student can also resolve leave attachment.
     */
    public function test_fix_guru_07_subject_teacher_who_teaches_student_can_get_signed_url(): void
    {
        $response = $this->actingAs($this->userTeacherMtk)
            ->getJson("/api/v1/files/{$this->fileAttachment7A->id}/url");

        $response->assertOk()
            ->assertJsonPath('data.id', $this->fileAttachment7A->id);
    }

    /**
     * FIX-GURU-07 AC2: Teacher cannot access leave attachment of student outside their teaching/homeroom scope (Anti-IDOR).
     */
    public function test_fix_guru_07_unassigned_teacher_cannot_access_leave_attachment_of_another_class_idor_403(): void
    {
        // Wali Kelas 7-A mencoba mengakses lampiran perizinan siswa 7-B (tidak mengajar & bukan walas 7-B)
        $response = $this->actingAs($this->userWalas7A)
            ->getJson("/api/v1/files/{$this->fileAttachment7B->id}/url");

        $response->assertStatus(403);

        // Guru lain yang tidak ada teaching assignment di 7-A mencoba akses file 7-A
        $responseOther = $this->actingAs($this->userOtherTeacher)
            ->getJson("/api/v1/files/{$this->fileAttachment7A->id}/url");

        $responseOther->assertStatus(403);
    }

    /**
     * FIX-GURU-07 AC4: Accessing non-existent file returns 404 Not Found.
     */
    public function test_fix_guru_07_accessing_non_existent_file_returns_404(): void
    {
        $nonExistentUuid = '99999999-9999-9999-9999-999999999999';

        $response = $this->actingAs($this->userWalas7A)
            ->getJson("/api/v1/files/{$nonExistentUuid}/url");

        $response->assertStatus(404);
    }

    /**
     * FIX-GURU-07 AC4: Unauthenticated file access returns 401 Unauthenticated.
     */
    public function test_fix_guru_07_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson("/api/v1/files/{$this->fileAttachment7A->id}/url");

        $response->assertStatus(401);
    }
}
