<?php

namespace Tests\Feature\Teacher;

use App\Models\Assignment;
use App\Models\GradeComponent;
use App\Models\GradePeriod;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSalarySlip;
use App\Models\User;
use Database\Seeders\TeacherDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherReadinessFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $userMtk;

    protected Teacher $teacherMtk;

    protected User $userWalas;

    protected Teacher $teacherWalas;

    protected User $userKurikulum;

    protected Teacher $teacherKurikulum;

    protected SchoolClass $class7A;

    protected SchoolClass $class7B;

    protected Subject $subjectMtk;

    protected Subject $subjectBin;

    protected Subject $subjectIpa;

    protected GradePeriod $openPeriod;

    protected GradePeriod $lockedPeriod;

    protected function setUp(): void
    {
        parent::setUp();

        (new TeacherDemoSeeder)->run();

        $this->userMtk = User::where('username', 'guru.mtk.staging')->firstOrFail();
        $this->teacherMtk = Teacher::where('user_id', $this->userMtk->id)->firstOrFail();

        $this->userWalas = User::where('username', 'walas.7a.staging')->firstOrFail();
        $this->teacherWalas = Teacher::where('user_id', $this->userWalas->id)->firstOrFail();

        $this->userKurikulum = User::where('username', 'kurikulum.staging')->firstOrFail();
        $this->teacherKurikulum = Teacher::where('user_id', $this->userKurikulum->id)->firstOrFail();

        $this->class7A = SchoolClass::where('name', '7-A')->firstOrFail();
        $this->class7B = SchoolClass::where('name', '7-B')->firstOrFail();

        $this->subjectMtk = Subject::where('code', 'MTK-7')->firstOrFail();
        $this->subjectBin = Subject::where('code', 'BIN-7')->firstOrFail();
        $this->subjectIpa = Subject::where('code', 'IPA-7')->firstOrFail();

        $this->openPeriod = GradePeriod::where('status', 'open')->firstOrFail();
        $this->lockedPeriod = GradePeriod::where('status', 'locked')->firstOrFail();
    }

    /**
     * Item 1: Dashboard Guru (GET /api/v1/me/teacher/dashboard)
     * Data dari API nyata, mencocokkan jumlah kelas & jadwal dengan database.
     */
    public function test_01_teacher_dashboard_returns_real_data_matching_database(): void
    {
        $response = $this->actingAs($this->userMtk)
            ->getJson('/api/v1/me/teacher/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.teacherName', $this->teacherMtk->full_name)
            ->assertJsonPath('data.nip', $this->teacherMtk->nip)
            ->assertJsonPath('data.assignedClassesCount', 1);

        $this->assertGreaterThanOrEqual(1, $response->json('data.todaySchedulesCount'));
    }

    /**
     * Item 2: Upload Materi Pembelajaran (GET & POST /api/v1/me/teacher/materials)
     * Guru bisa upload untuk mapel yang diampu, ditolak untuk mapel lain.
     */
    public function test_02_teacher_materials_upload_and_subject_scoping(): void
    {
        // 1. Berhasil unggah materi untuk mata pelajaran Matematika (diampu)
        $uploadRes = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/materials', [
                'title' => 'Slide Bab 2: Pertidaksamaan Linier',
                'subject_id' => $this->subjectMtk->id,
                'description' => 'Materi lanjutan pertemuan ke-3',
                'material_type' => 'modul',
            ]);

        $uploadRes->assertStatus(201)
            ->assertJsonPath('data.title', 'Slide Bab 2: Pertidaksamaan Linier')
            ->assertJsonPath('data.subjectId', $this->subjectMtk->id);

        $materialId = $uploadRes->json('data.id');
        $this->assertDatabaseHas('teaching_materials', [
            'id' => $materialId,
            'title' => 'Slide Bab 2: Pertidaksamaan Linier',
            'uploaded_by' => $this->userMtk->id,
        ]);

        // 2. Daftar materi guru memuat materi yang baru dibuat
        $listRes = $this->actingAs($this->userMtk)
            ->getJson('/api/v1/me/teacher/materials');

        $listRes->assertOk();
        $titles = collect($listRes->json('data'))->pluck('title');
        $this->assertTrue($titles->contains('Slide Bab 2: Pertidaksamaan Linier'));

        // 3. Ditolak jika guru mencoba mengunggah materi untuk mapel yang TIDAK diampu (IPA)
        $unauthorizedRes = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/materials', [
                'title' => 'Materi Fotosintesis Gelap',
                'subject_id' => $this->subjectIpa->id,
            ]);

        $unauthorizedRes->assertStatus(403);
    }

    /**
     * Item 3: Verifikasi modul ajar / RPP (Analisis Kesenjangan PRD vs Implementasi)
     * Backend memiliki repositori kurikulum di /teaching-materials,
     * namun portal guru TIDAK memiliki endpoint penyusunan/pembuatan RPP interaktif (/me/teacher/rpp).
     */
    public function test_03_teaching_materials_curriculum_repository_access_and_rpp_scope(): void
    {
        // 1. Repositori materi kurikulum dapat dibaca oleh pengguna terautentikasi
        $this->actingAs($this->userMtk)
            ->getJson('/api/v1/teaching-materials')
            ->assertOk();

        // 2. Terbukti TIDAK ADA endpoint penyusunan RPP interaktif di portal guru (404)
        $this->actingAs($this->userMtk)
            ->getJson('/api/v1/me/teacher/rpp')
            ->assertStatus(404);

        $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/rpp', [
                'topic' => 'Draf RPP 1 Lembar',
            ])
            ->assertStatus(404);
    }

    /**
     * Item 4: Bank Soal & Ujian CBT Guru (GET /api/v1/me/teacher/exams)
     */
    public function test_04_teacher_can_view_assigned_exams(): void
    {
        $response = $this->actingAs($this->userMtk)
            ->getJson('/api/v1/me/teacher/exams');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    /**
     * Item 5: Tugas KBM, Pengumpulan Siswa, & Penilaian (Speed Grader) dengan Anti-IDOR
     */
    public function test_05_teacher_assignments_submissions_and_grading_with_idor_guard(): void
    {
        // 1. Buat tugas baru untuk kelas 7-A (diampu)
        $createRes = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/assignments', [
                'title' => 'Tugas Mandiri 02: Sistem Persamaan',
                'class_id' => $this->class7A->id,
                'subject_id' => $this->subjectMtk->id,
                'description' => 'Kerjakan 5 soal di buku tugas',
                'due_at' => now()->addDays(3)->toDateString(),
            ]);

        $createRes->assertStatus(201);
        $assignmentId = $createRes->json('data.id');

        // 2. Ditolak jika membuat tugas untuk kelas yang tidak diampu (7-B)
        $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/assignments', [
                'title' => 'Tugas Ilegal',
                'class_id' => $this->class7B->id,
                'subject_id' => $this->subjectMtk->id,
            ])
            ->assertStatus(403);

        // 3. Ambil submission yang sudah ada dari seeder
        $seededAssignment = Assignment::where('teacher_id', $this->teacherMtk->id)->firstOrFail();
        $subRes = $this->actingAs($this->userMtk)
            ->getJson("/api/v1/me/teacher/assignments/{$seededAssignment->id}/submissions");

        $subRes->assertOk();
        $submissionId = $subRes->json('data.1.id'); // submission ke-2 (belum dinilai)
        $this->assertNotNull($submissionId);

        // 4. Guru MTK memberikan nilai dan umpan balik
        $gradeRes = $this->actingAs($this->userMtk)
            ->postJson("/api/v1/me/teacher/submissions/{$submissionId}/grade", [
                'score' => 95,
                'feedback' => 'Luar biasa, cara pengerjaan sangat tepat.',
            ]);

        $gradeRes->assertOk();
        $this->assertDatabaseHas('assignment_submissions', [
            'id' => $submissionId,
            'score' => 95,
        ]);

        // 5. IDOR Guard: Guru lain (Kurikulum/Walas) TIDAK BISA menilai tugas milik Guru MTK
        $idorRes = $this->actingAs($this->userKurikulum)
            ->postJson("/api/v1/me/teacher/submissions/{$submissionId}/grade", [
                'score' => 50,
                'feedback' => 'Percobaan pembobolan nilai oleh guru lain',
            ]);

        $idorRes->assertStatus(403);
    }

    /**
     * Item 6: Presensi di Kelas (POST /api/v1/me/teacher/attendance/sessions)
     */
    public function test_06_classroom_attendance_recording_and_schedule_lock(): void
    {
        $students = Student::whereHas('classes', fn ($q) => $q->where('classes.id', $this->class7A->id))->take(3)->get();
        $this->assertCount(3, $students);

        $attendees = [
            ['student_id' => $students[0]->id, 'status' => 'present', 'notes' => 'Tepat waktu'],
            ['student_id' => $students[1]->id, 'status' => 'sick', 'notes' => 'Surat dokter'],
            ['student_id' => $students[2]->id, 'status' => 'permission', 'notes' => 'Acara keluarga'],
        ];

        $res = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/attendance/sessions', [
                'class_id' => $this->class7A->id,
                'subject_id' => $this->subjectMtk->id,
                'session_date' => now()->toDateString(),
                'session_topic' => 'Kuis Harian Operasi Aljabar',
                'attendees' => $attendees,
            ]);

        $res->assertStatus(201);

        $this->assertDatabaseHas('attendances', [
            'student_id' => $students[0]->id,
            'status' => 'hadir',
        ]);
        $this->assertDatabaseHas('attendances', [
            'student_id' => $students[1]->id,
            'status' => 'sakit',
        ]);
        $this->assertDatabaseHas('attendances', [
            'student_id' => $students[2]->id,
            'status' => 'izin',
        ]);

        // Ditolak jika mencatat presensi untuk kelas yang tidak diampu
        $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/attendance/sessions', [
                'class_id' => $this->class7B->id,
                'subject_id' => $this->subjectMtk->id,
                'attendees' => [],
            ])
            ->assertStatus(403);
    }

    /**
     * Item 7: Jurnal Mengajar (GET & POST /api/v1/me/teacher/journals)
     */
    public function test_07_teaching_journal_creation_and_history(): void
    {
        $res = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/journals', [
                'class_id' => $this->class7A->id,
                'subject_id' => $this->subjectMtk->id,
                'session_date' => now()->toDateString(),
                'topic' => 'Persamaan Linier Dua Variabel',
                'competence_indicator' => 'Mampu menyelesaikan metode eliminasi',
                'learning_obstacles' => 'Sebagian siswa butuh waktu lebih lama pada pecahan',
                'method' => 'Tatap Muka & Diskusi Kelompok',
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.topic', 'Persamaan Linier Dua Variabel');

        // Riwayat jurnal memuat data yang baru disimpan
        $listRes = $this->actingAs($this->userMtk)
            ->getJson('/api/v1/me/teacher/journals');

        $listRes->assertOk();
        $this->assertGreaterThanOrEqual(1, count($listRes->json('data')));
    }

    /**
     * Item 8: Daftar Siswa Per Kelas & Proteksi Privasi NIK serta Data BK
     * GET /api/v1/me/teacher/classes/{classId}/students
     */
    public function test_08_class_students_list_strictly_redacts_nik_and_bk_counseling_data(): void
    {
        $res = $this->actingAs($this->userMtk)
            ->getJson("/api/v1/me/teacher/classes/{$this->class7A->id}/students");

        $res->assertOk()
            ->assertJsonCount(10, 'data');

        $rawResponse = $res->getContent();

        // 1. Data profil dasar tampil
        $firstStudent = $res->json('data.0');
        $this->assertArrayHasKey('studentId', $firstStudent);
        $this->assertArrayHasKey('studentName', $firstStudent);
        $this->assertArrayHasKey('nisn', $firstStudent);
        $this->assertArrayHasKey('nis', $firstStudent);

        // 2. PRIVASI TINGGI: NIK, NIK terenkripsi, data konseling/BK TIDAK BOLEH bocor ke guru mapel
        $this->assertArrayNotHasKey('nik', $firstStudent);
        $this->assertArrayNotHasKey('nik_enc', $firstStudent);
        $this->assertArrayNotHasKey('counseling', $firstStudent);
        $this->assertArrayNotHasKey('cases', $firstStudent);
        $this->assertStringNotContainsString('357801010109', $rawResponse);

        // 3. IDOR Guard: Guru MTK tidak boleh mengakses daftar siswa kelas 7-B (tidak diampu)
        $unauthRes = $this->actingAs($this->userMtk)
            ->getJson("/api/v1/me/teacher/classes/{$this->class7B->id}/students");

        $unauthRes->assertStatus(403);
    }

    /**
     * Item 9: Manajemen Nilai (Rentang Nilai 0-100 & Penolakan Update pada Periode Terkunci)
     */
    public function test_09_grade_management_respects_locked_period_and_valid_range(): void
    {
        $student = Student::whereHas('classes', fn ($q) => $q->where('classes.id', $this->class7A->id))->firstOrFail();
        $component = GradeComponent::where('subject_id', $this->subjectMtk->id)->firstOrFail();

        // 1. Input nilai valid pada periode terbuka berhasil
        $validRes = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/grades/bulk', [
                'class_id' => $this->class7A->id,
                'subject_id' => $this->subjectMtk->id,
                'period_id' => $this->openPeriod->id,
                'grades' => [
                    [
                        'student_id' => $student->id,
                        'scores' => [
                            $component->id => 85.5,
                        ],
                    ],
                ],
            ]);

        $validRes->assertOk();
        $this->assertDatabaseHas('grades', [
            'student_id' => $student->id,
            'component_id' => $component->id,
            'value' => 85.5,
        ]);

        // 2. Input nilai pada periode TERKUNCI (locked) DITOLAK
        $lockedRes = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/grades/bulk', [
                'class_id' => $this->class7A->id,
                'subject_id' => $this->subjectMtk->id,
                'period_id' => $this->lockedPeriod->id,
                'grades' => [
                    [
                        'student_id' => $student->id,
                        'scores' => [
                            $component->id => 90.0,
                        ],
                    ],
                ],
            ]);

        $lockedRes->assertStatus(422);

        // 3. Input nilai di luar rentang valid (misal 150 atau -10) DITOLAK
        $invalidScoreRes = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/grades/bulk', [
                'class_id' => $this->class7A->id,
                'subject_id' => $this->subjectMtk->id,
                'period_id' => $this->openPeriod->id,
                'grades' => [
                    [
                        'student_id' => $student->id,
                        'scores' => [
                            $component->id => 150, // Nilai > 100 tidak sah
                        ],
                    ],
                ],
            ]);

        $invalidScoreRes->assertStatus(422);
    }

    /**
     * Item 10: Fitur Khusus Wali Kelas (GET /api/v1/me/teacher/homeroom)
     * Hanya wali kelas yang boleh mengakses. Guru non-walas ditolak 403.
     */
    public function test_10_homeroom_data_access_restricted_to_homeroom_teacher(): void
    {
        // 1. Walas 7-A berhasil mengakses data rekap kelas binaan
        $walasRes = $this->actingAs($this->userWalas)
            ->getJson('/api/v1/me/teacher/homeroom');

        $walasRes->assertOk()
            ->assertJsonPath('data.className', '7-A')
            ->assertJsonPath('data.totalStudents', 10);

        // 2. Guru MTK (bukan walas) DITOLAK dengan status 403 Forbidden
        $mtkRes = $this->actingAs($this->userMtk)
            ->getJson('/api/v1/me/teacher/homeroom');

        $mtkRes->assertStatus(403);
    }

    /**
     * Item 11: Pengajuan Izin & Cuti Guru (GET & POST /api/v1/me/teacher/leave-requests)
     */
    public function test_11_teacher_leave_requests_lifecycle(): void
    {
        $createRes = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/leave-requests', [
                'leave_type' => 'annual',
                'start_date' => now()->addDays(10)->toDateString(),
                'end_date' => now()->addDays(12)->toDateString(),
                'reason' => 'Keperluan keluarga penting di luar kota',
            ]);

        $createRes->assertStatus(201);

        $listRes = $this->actingAs($this->userMtk)
            ->getJson('/api/v1/me/teacher/leave-requests');

        $listRes->assertOk();
        $this->assertGreaterThanOrEqual(1, count($listRes->json('data')));
    }

    /**
     * Item 12: Slip Gaji Mandiri & Ketat IDOR
     * GET /api/v1/salary-slips dan GET /api/v1/salary-slips/{slipId}/download
     */
    public function test_12_teacher_salary_slips_strict_idor_isolation(): void
    {
        // 1. Guru MTK bisa melihat daftar slip gaji miliknya
        $listRes = $this->actingAs($this->userMtk)
            ->getJson('/api/v1/salary-slips');

        $listRes->assertOk();
        $mtkSlips = $listRes->json('data');
        $this->assertCount(2, $mtkSlips);

        $mtkSlipId = $mtkSlips[0]['id'];

        // 2. Guru MTK bisa mengunduh slip gajinya sendiri
        $downloadRes = $this->actingAs($this->userMtk)
            ->get("/api/v1/salary-slips/{$mtkSlipId}/download");

        $downloadRes->assertOk();
        $this->assertEquals('application/pdf', $downloadRes->headers->get('content-type'));

        // 3. Ambil slip gaji milik Walas 7-A
        $walasSlip = TeacherSalarySlip::where('teacher_id', $this->teacherWalas->id)->firstOrFail();

        // 4. CRITICAL IDOR: Guru MTK mencoba mengunduh slip gaji Walas 7-A -> HARUS DITOLAK 403!
        $idorDownloadRes = $this->actingAs($this->userMtk)
            ->getJson("/api/v1/salary-slips/{$walasSlip->id}/download");

        $idorDownloadRes->assertStatus(403);
    }

    /**
     * Item 13: Presensi Mandiri Guru (GET & POST /api/v1/me/teacher/self-attendance)
     */
    public function test_13_teacher_self_attendance_check_in_out_and_recap(): void
    {
        // 1. Melakukan check-in
        $checkInRes = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/self-attendance', [
                'action' => 'check_in',
            ]);

        $checkInRes->assertOk()
            ->assertJsonPath('data.success', true);

        // 2. Mengambil rekap presensi mandiri
        $statusRes = $this->actingAs($this->userMtk)
            ->getJson('/api/v1/me/teacher/self-attendance');

        $statusRes->assertOk()
            ->assertJsonPath('data.status', 'hadir');
        $this->assertNotNull($statusRes->json('data.checkInTime'));

        // 3. Melakukan check-out
        $checkOutRes = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/self-attendance', [
                'action' => 'check_out',
            ]);

        $checkOutRes->assertOk()
            ->assertJsonPath('data.success', true);
    }
}
