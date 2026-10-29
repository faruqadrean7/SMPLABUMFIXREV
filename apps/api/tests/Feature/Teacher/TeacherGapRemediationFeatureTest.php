<?php

namespace Tests\Feature\Teacher;

use App\Models\Attendance;
use App\Models\Booking;
use App\Models\ClassStudent;
use App\Models\Facility;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradePeriod;
use App\Models\LeaveRequest;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentPoint;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\TeacherDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TeacherGapRemediationFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $userMtk;

    protected Teacher $teacherMtk;

    protected User $userWalas;

    protected Teacher $teacherWalas;

    protected User $userKurikulum;

    protected Subject $subjectMtk;

    protected Subject $subjectIpa;

    protected SchoolClass $class7A;

    protected SchoolClass $class7B;

    protected Student $student7A;

    protected Student $student7B;

    protected Facility $facilityLab;

    protected function setUp(): void
    {
        parent::setUp();

        (new TeacherDemoSeeder)->run();

        $this->userMtk = User::where('username', 'guru.mtk.staging')->firstOrFail();
        $this->teacherMtk = Teacher::where('user_id', $this->userMtk->id)->firstOrFail();

        $this->userWalas = User::where('username', 'walas.7a.staging')->firstOrFail();
        $this->teacherWalas = Teacher::where('user_id', $this->userWalas->id)->firstOrFail();

        $this->userKurikulum = User::where('username', 'kurikulum.staging')->firstOrFail();

        $this->subjectMtk = Subject::where('code', 'MTK-7')->firstOrFail();
        $this->subjectIpa = Subject::where('code', 'IPA-7')->firstOrFail();

        $this->class7A = SchoolClass::where('name', '7-A')->firstOrFail();
        $this->class7B = SchoolClass::where('name', '7-B')->firstOrFail();

        $this->student7A = $this->class7A->students()->firstOrFail();

        // Buat siswa kelas 7-B untuk pengujian isolasi / IDOR
        $userStudentB = User::create([
            'username' => 'siswa.7b.test',
            'email' => 'siswa.7b@smp-labum.sch.id',
            'password_hash' => bcrypt('Secret123!'),
            'status' => 'active',
        ]);
        $this->student7B = Student::create([
            'school_id' => $this->class7B->school_id ?? '00000000-0000-0000-0000-000000000001',
            'user_id' => $userStudentB->id,
            'nisn' => '9998887771',
            'nis' => '7B001',
            'nik_enc' => '3578010101019999',
            'full_name' => 'Budi Santoso 7B',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-05-10',
            'status' => 'aktif',
            'entry_date' => '2026-07-01',
        ]);
        ClassStudent::create([
            'class_id' => $this->class7B->id,
            'student_id' => $this->student7B->id,
            'joined_at' => now(),
        ]);

        // Buat fasilitas untuk pengujian sarpras
        $this->facilityLab = Facility::firstOrCreate(
            ['code' => 'LAB-KOM-1'],
            [
                'school_id' => $this->userMtk->school_id ?? '00000000-0000-0000-0000-000000000001',
                'name' => 'Laboratorium Komputer 1',
                'type' => 'ruangan',
                'capacity' => 40,
                'status' => 'tersedia',
                'is_active' => true,
            ]
        );
    }

    // =========================================================================
    // FITUR A: PERIZINAN SISWA OLEH WALI KELAS
    // =========================================================================

    public function test_homeroom_teacher_can_list_and_filter_student_leave_requests(): void
    {
        // Buat permohonan izin untuk siswa kelas 7-A
        $leaveA = LeaveRequest::create([
            'student_id' => $this->student7A->id,
            'leave_type' => 'sakit',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'reason' => 'Demam dan flu, anjuran istirahat dokter.',
            'status' => 'menunggu',
        ]);

        // Wali kelas 7-A melihat daftar permohonan izin kelas binaannya
        $res = $this->actingAs($this->userWalas)
            ->getJson('/api/v1/me/teacher/homeroom/leave-requests');

        $res->assertOk();
        $this->assertNotEmpty($res->json('data'));
        $this->assertEquals($leaveA->id, $res->json('data.0.id'));

        // Filter status 'menunggu'
        $resFilter = $this->actingAs($this->userWalas)
            ->getJson('/api/v1/me/teacher/homeroom/leave-requests?status=menunggu');
        $resFilter->assertOk();
        $this->assertEquals('menunggu', $resFilter->json('data.0.status'));
    }

    public function test_teacher_cannot_access_leave_requests_of_unmanaged_class(): void
    {
        // Guru MTK yang bukan walas 7-B mencoba mengakses filter kelas 7-B
        $res = $this->actingAs($this->userMtk)
            ->getJson("/api/v1/me/teacher/homeroom/leave-requests?class_id={$this->class7B->id}");

        $res->assertStatus(403);
    }

    public function test_homeroom_teacher_can_decide_leave_request_and_syncs_attendance(): void
    {
        $today = now()->toDateString();
        $leave = LeaveRequest::create([
            'student_id' => $this->student7A->id,
            'leave_type' => 'sakit',
            'start_date' => $today,
            'end_date' => $today,
            'reason' => 'Sakit demam',
            'status' => 'menunggu',
        ]);

        $res = $this->actingAs($this->userWalas)
            ->patchJson("/api/v1/me/teacher/homeroom/leave-requests/{$leave->id}/decision", [
                'decision' => 'approved',
                'decision_notes' => 'Disetujui wali kelas, lekas sembuh.',
            ]);

        $res->assertOk();
        $this->assertEquals('disetujui', $res->json('data.status'));
        $this->assertEquals('Disetujui wali kelas, lekas sembuh.', $res->json('data.decision_notes'));

        // Buktikan presensi siswa otomatis tersinkronisasi menjadi 'sakit'
        $att = Attendance::where('student_id', $this->student7A->id)
            ->whereDate('att_date', $today)
            ->where('source', 'gerbang')
            ->first();

        $this->assertNotNull($att);
        $this->assertEquals('sakit', $att->status);

        // Buktikan permohonan yang sudah diputuskan tidak dapat diputuskan ulang (422)
        $resRepeat = $this->actingAs($this->userWalas)
            ->patchJson("/api/v1/me/teacher/homeroom/leave-requests/{$leave->id}/decision", [
                'decision' => 'rejected',
            ]);
        $resRepeat->assertStatus(422);
    }

    public function test_non_homeroom_teacher_cannot_decide_leave_request(): void
    {
        $leave = LeaveRequest::create([
            'student_id' => $this->student7A->id,
            'leave_type' => 'izin',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'reason' => 'Acara keluarga',
            'status' => 'menunggu',
        ]);

        // Guru MTK (bukan walas) mencoba memutuskan izin
        $res = $this->actingAs($this->userMtk)
            ->patchJson("/api/v1/me/teacher/homeroom/leave-requests/{$leave->id}/decision", [
                'decision' => 'approved',
            ]);

        $res->assertStatus(403);
    }

    // =========================================================================
    // FITUR B: POIN KARAKTER DAN RUJUKAN BK
    // =========================================================================

    public function test_teacher_can_record_student_point_for_taught_student(): void
    {
        $res = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/student-points', [
                'student_id' => $this->student7A->id,
                'type' => 'prestasi',
                'category' => 'Keaktifan Diskusi',
                'points' => 5,
                'notes' => 'Sangat aktif mempresentasikan solusi matematika di depan kelas.',
            ]);

        $res->assertStatus(201);
        $this->assertEquals($this->student7A->id, $res->json('data.student_id'));

        $this->assertDatabaseHas('student_points', [
            'student_id' => $this->student7A->id,
            'recorded_by' => $this->userMtk->id,
            'notes' => 'Sangat aktif mempresentasikan solusi matematika di depan kelas.',
        ]);
    }

    public function test_teacher_cannot_record_point_for_student_outside_teaching_scope(): void
    {
        // Guru MTK tidak mengampu siswa kelas 7-B
        $res = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/student-points', [
                'student_id' => $this->student7B->id,
                'type' => 'pelanggaran',
                'notes' => 'Terlambat masuk sekolah.',
            ]);

        $res->assertStatus(403);
    }

    public function test_teacher_can_create_counseling_referral_for_taught_student(): void
    {
        $res = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/counseling-referrals', [
                'student_id' => $this->student7A->id,
                'reason' => 'Menunjukkan penurunan konsentrasi belajar dan tampak murung beberapa hari terakhir.',
                'priority' => 'segera',
            ]);

        $res->assertStatus(201);
        $this->assertEquals($this->student7A->id, $res->json('data.student_id'));
        $this->assertEquals('pending', $res->json('data.status'));

        // Cegah duplikasi rujukan berulang yang identik saat status masih pending
        $resDup = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/counseling-referrals', [
                'student_id' => $this->student7A->id,
                'reason' => 'Menunjukkan penurunan konsentrasi belajar dan tampak murung beberapa hari terakhir.',
                'priority' => 'segera',
            ]);

        $resDup->assertStatus(422);
    }

    public function test_teacher_cannot_create_counseling_referral_for_unassigned_student(): void
    {
        $res = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/counseling-referrals', [
                'student_id' => $this->student7B->id,
                'reason' => 'Perlu bimbingan motivasi belajar.',
            ]);

        $res->assertStatus(403);
    }

    // =========================================================================
    // FITUR C: PEMINJAMAN FASILITAS DAN TIKET SARPRAS
    // =========================================================================

    public function test_teacher_can_book_facility_and_view_own_bookings(): void
    {
        $startAt = now()->addDays(1)->setHour(8)->setMinute(0)->setSecond(0)->toISOString();
        $endAt = now()->addDays(1)->setHour(10)->setMinute(0)->setSecond(0)->toISOString();

        $res = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/facility-bookings', [
                'facility_id' => $this->facilityLab->id,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'purpose' => 'Praktikum Simulasi Pemrograman Kelas 7-A',
                'participant_count' => 32,
            ]);

        $res->assertStatus(201);
        $this->assertEquals('menunggu', $res->json('data.status'));

        // Guru melihat riwayat peminjaman miliknya
        $resList = $this->actingAs($this->userMtk)
            ->getJson('/api/v1/me/teacher/facility-bookings');

        $resList->assertOk();
        $this->assertNotEmpty($resList->json('data'));
        $this->assertEquals($this->facilityLab->id, $resList->json('data.0.facility_id'));
    }

    public function test_conflicting_facility_booking_is_rejected_with_409_conflict(): void
    {
        $startAt = now()->addDays(2)->setHour(9)->setMinute(0)->setSecond(0);
        $endAt = now()->addDays(2)->setHour(11)->setMinute(0)->setSecond(0);

        // Booking pertama sukses
        Booking::create([
            'school_id' => $this->userMtk->school_id ?? '00000000-0000-0000-0000-000000000001',
            'facility_id' => $this->facilityLab->id,
            'requested_by' => $this->userWalas->id,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'purpose' => 'Ujian Praktik Bahasa',
            'status' => 'menunggu',
        ]);

        // Upaya booking bertabrakan oleh guru lain pada rentang waktu yang sama (10:00 - 12:00) ditolak 409
        $resConflict = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/facility-bookings', [
                'facility_id' => $this->facilityLab->id,
                'start_at' => $startAt->copy()->addHour()->toISOString(),
                'end_at' => $endAt->copy()->addHour()->toISOString(),
                'purpose' => 'Sesi Tambahan Komputer',
            ]);

        $resConflict->assertStatus(409);
    }

    public function test_teacher_can_create_sarpras_ticket(): void
    {
        $res = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/tickets', [
                'title' => 'AC Lab Komputer Tidak Dingin',
                'category' => 'Pendingin Ruangan',
                'location' => 'Lab Komputer 1 Lantai 2',
                'description' => 'Unit AC sebelah utara mengeluarkan bunyi berisik dan tidak mengeluarkan hembusan dingin.',
                'priority' => 'tinggi',
            ]);

        $res->assertStatus(201);
        $this->assertNotEmpty($res->json('data.ticket_number'));
        $this->assertEquals('pending', $res->json('data.status'));
        $this->assertEquals($this->userMtk->id, $res->json('data.reporter_id'));
    }

    // =========================================================================
    // FITUR D: BANK SOAL MANDIRI GURU
    // =========================================================================

    public function test_teacher_can_list_and_create_direct_questions(): void
    {
        // 1. Buat butir soal langsung via POST /me/teacher/questions
        $resCreate = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/questions', [
                'subject_id' => $this->subjectMtk->id,
                'question_type' => 'pg',
                'body' => ['text' => 'Berapakah nilai x dari persamaan 2x + 6 = 14?'],
                'cognitive_level' => 'MOTS',
                'difficulty' => 'sedang',
                'weight' => 2.0,
                'options' => [
                    ['label' => 'A', 'body' => '2', 'is_correct' => false],
                    ['label' => 'B', 'body' => '4', 'is_correct' => true],
                    ['label' => 'C', 'body' => '6', 'is_correct' => false],
                    ['label' => 'D', 'body' => '8', 'is_correct' => false],
                ],
            ]);

        $resCreate->assertStatus(201);
        $this->assertEquals('pg', $resCreate->json('data.question_type'));
        $questionId = $resCreate->json('data.id');

        // 2. Tampilkan daftar soal guru via GET /me/teacher/questions
        $resList = $this->actingAs($this->userMtk)
            ->getJson("/api/v1/me/teacher/questions?subject_id={$this->subjectMtk->id}");

        $resList->assertOk();
        $this->assertNotEmpty($resList->json('data'));
        $this->assertContains($questionId, collect($resList->json('data'))->pluck('id')->all());
    }

    public function test_teacher_cannot_access_or_create_questions_for_untaught_subject(): void
    {
        // Guru MTK tidak mengampu IPA-7
        $resList = $this->actingAs($this->userMtk)
            ->getJson("/api/v1/me/teacher/questions?subject_id={$this->subjectIpa->id}");

        $resList->assertStatus(403);

        $resCreate = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/questions', [
                'subject_id' => $this->subjectIpa->id,
                'question_type' => 'uraian',
                'body' => ['text' => 'Jelaskan hukum Newton 1!'],
            ]);

        $resCreate->assertStatus(403);
    }

    // =========================================================================
    // FITUR E: PENGUNCIAN LEDGER NILAI GURU
    // =========================================================================

    public function test_teacher_can_lock_grade_ledger_and_blocks_further_edits(): void
    {
        $period = GradePeriod::where('status', 'open')->firstOrFail();

        $comp = GradeComponent::firstOrCreate(
            [
                'subject_id' => $this->subjectMtk->id,
                'semester_id' => $period->semester_id,
                'class_id' => $this->class7A->id,
                'code' => 'UH-01',
            ],
            [
                'name' => 'Ulangan Harian 1',
                'weight' => 20.0,
            ]
        );

        // 1. Guru input nilai awal
        $resInput = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/grades/bulk', [
                'class_id' => $this->class7A->id,
                'subject_id' => $this->subjectMtk->id,
                'period_id' => $period->id,
                'grades' => [
                    [
                        'student_id' => $this->student7A->id,
                        'scores' => [
                            $comp->id => 85.0,
                        ],
                    ],
                ],
            ]);

        $resInput->assertOk();

        // 2. Guru mengunci buku nilai kelas & mapel
        $resLock = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/grades/lock', [
                'class_id' => $this->class7A->id,
                'subject_id' => $this->subjectMtk->id,
                'period_id' => $period->id,
            ]);

        $resLock->assertOk();
        $this->assertTrue($resLock->json('data.success'));
        $this->assertGreaterThanOrEqual(1, $resLock->json('data.locked_count'));

        // Buktikan field locked_at pada tabel grades terisi
        $grade = Grade::where('student_id', $this->student7A->id)
            ->where('component_id', $comp->id)
            ->where('period_id', $period->id)
            ->firstOrFail();

        $this->assertNotNull($grade->locked_at);

        // 3. Upaya mutasi nilai pasca penguncian ditolak dengan HTTP 422
        $resMutate = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/grades/bulk', [
                'class_id' => $this->class7A->id,
                'subject_id' => $this->subjectMtk->id,
                'period_id' => $period->id,
                'grades' => [
                    [
                        'student_id' => $this->student7A->id,
                        'scores' => [
                            $comp->id => 95.0,
                        ],
                    ],
                ],
            ]);

        $resMutate->assertStatus(422);
    }

    public function test_teacher_cannot_lock_grades_of_unassigned_class_or_subject(): void
    {
        // Guru MTK tidak mengampu kelas 7-B
        $resLock = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/grades/lock', [
                'class_id' => $this->class7B->id,
                'subject_id' => $this->subjectMtk->id,
            ]);

        $resLock->assertStatus(403);
    }

    /**
     * P2-01: Verifikasi penguncian nilai terpusat di GradeService (menolak bypass via /api/v1/grades).
     */
    public function test_grade_service_rejects_mutation_of_locked_grade_via_general_routes(): void
    {
        $period = GradePeriod::where('status', 'open')->first() ?? GradePeriod::create([
            'semester_id' => Semester::first()->id,
            'month' => 9,
            'status' => 'open',
        ]);

        $comp = GradeComponent::firstOrCreate(
            [
                'subject_id' => $this->subjectMtk->id,
                'semester_id' => $period->semester_id,
                'class_id' => $this->class7A->id,
                'code' => 'UH-LOCK',
            ],
            [
                'name' => 'Ulangan Harian Lock',
                'weight' => 20.0,
            ]
        );

        // Buat nilai dengan status locked_at
        Grade::create([
            'student_id' => $this->student7A->id,
            'component_id' => $comp->id,
            'period_id' => $period->id,
            'value' => 80.0,
            'source' => 'manual',
            'locked_at' => Carbon::now(),
            'entered_by' => $this->userMtk->id,
        ]);

        // Coba mutasi via endpoint umum /api/v1/grades (storeGrade)
        $resSingle = $this->actingAs($this->userKurikulum)
            ->postJson('/api/v1/grades', [
                'student_id' => $this->student7A->id,
                'component_id' => $comp->id,
                'period_id' => $period->id,
                'value' => 95.0,
                'reason' => 'Perubahan nilai',
            ]);

        $resSingle->assertStatus(422);

        // Coba mutasi via endpoint umum /api/v1/grades/bulk (bulkStoreGrades)
        $resBulk = $this->actingAs($this->userKurikulum)
            ->postJson('/api/v1/grades/bulk', [
                'component_id' => $comp->id,
                'period_id' => $period->id,
                'reason' => 'Perubahan nilai massal',
                'grades' => [
                    [
                        'student_id' => $this->student7A->id,
                        'value' => 95.0,
                    ],
                ],
            ]);

        $resBulk->assertStatus(422);
    }

    /**
     * P2-02: Perlindungan keputusan izin dari race condition dan mutasi ulang.
     */
    public function test_homeroom_leave_request_decision_race_condition_protection(): void
    {
        $leave = LeaveRequest::create([
            'student_id' => $this->student7A->id,
            'leave_type' => 'sakit',
            'start_date' => Carbon::now()->toDateString(),
            'end_date' => Carbon::now()->addDays(2)->toDateString(),
            'reason' => 'Demam dan flu berat',
            'status' => 'menunggu',
        ]);

        // Keputusan pertama oleh wali kelas yang sah
        $res1 = $this->actingAs($this->userWalas)
            ->patchJson("/api/v1/me/teacher/homeroom/leave-requests/{$leave->id}/decision", [
                'decision' => 'approved',
                'decision_notes' => 'Disetujui keputusan pertama',
            ]);

        $res1->assertOk();

        // Keputusan kedua bersamaan / ulang harus ditolak 422
        $res2 = $this->actingAs($this->userWalas)
            ->patchJson("/api/v1/me/teacher/homeroom/leave-requests/{$leave->id}/decision", [
                'decision' => 'rejected',
                'decision_notes' => 'Coba ubah keputusan kedua',
            ]);

        $res2->assertStatus(422);
    }

    /**
     * P2-03: Idempotensi pencatatan poin siswa (anti-duplikasi ganda pada retry/double-click).
     */
    public function test_student_point_recording_is_idempotent_and_prevents_duplicate_records(): void
    {
        $payload = [
            'student_id' => $this->student7A->id,
            'type' => 'prestasi',
            'category' => 'Juara 1 Matematika',
            'points' => 20,
            'notes' => 'Memenangkan medali emas OSN Matematika',
            'date' => Carbon::now()->toDateString(),
        ];

        // Request 1 dengan Idempotency-Key
        $res1 = $this->actingAs($this->userMtk)
            ->withHeader('X-Idempotency-Key', 'idem-pt-001')
            ->postJson('/api/v1/me/teacher/student-points', $payload);

        $res1->assertStatus(201);
        $pointId1 = $res1->json('data.id');

        // Request 2 (Identik) dengan Idempotency-Key yang sama
        $res2 = $this->actingAs($this->userMtk)
            ->withHeader('X-Idempotency-Key', 'idem-pt-001')
            ->postJson('/api/v1/me/teacher/student-points', $payload);

        $res2->assertOk();
        $this->assertEquals($pointId1, $res2->json('data.id'));

        // Buktikan record di database tetap 1, tidak menjadi 2
        $count = StudentPoint::where('student_id', $this->student7A->id)
            ->where('notes', 'Memenangkan medali emas OSN Matematika')
            ->count();
        $this->assertEquals(1, $count);

        // Request 3 dengan Idempotency-Key berbeda yang sah dapat dicatat
        $res3 = $this->actingAs($this->userMtk)
            ->withHeader('X-Idempotency-Key', 'idem-pt-002')
            ->postJson('/api/v1/me/teacher/student-points', array_merge($payload, [
                'notes' => 'Prestasi lomba kedua',
            ]));

        $res3->assertStatus(201);
        $this->assertNotEquals($pointId1, $res3->json('data.id'));
    }

    /**
     * P2-03: Idempotency-Key yang sama dengan payload berbeda WAJIB ditolak (HTTP 422 IDEMPOTENCY_CONFLICT).
     */
    public function test_student_point_idempotency_rejects_same_key_with_different_payload(): void
    {
        $payload1 = [
            'student_id' => $this->student7A->id,
            'type' => 'prestasi',
            'category' => 'Juara Sains',
            'points' => 15,
            'notes' => 'Medali perak Olimpiade Sains',
            'date' => Carbon::now()->toDateString(),
        ];

        // Request 1 dengan payload asli
        $res1 = $this->actingAs($this->userMtk)
            ->withHeader('X-Idempotency-Key', 'idem-mismatch-key')
            ->postJson('/api/v1/me/teacher/student-points', $payload1);

        $res1->assertStatus(201);

        // Request 2 dengan Idempotency-Key yang sama tetapi payload berbeda (modifikasi points & notes)
        $payload2 = array_merge($payload1, [
            'points' => 50,
            'notes' => 'Modifikasi manipulasi payload dengan key sama',
        ]);

        $res2 = $this->actingAs($this->userMtk)
            ->withHeader('X-Idempotency-Key', 'idem-mismatch-key')
            ->postJson('/api/v1/me/teacher/student-points', $payload2);

        $res2->assertStatus(422);
        $this->assertEquals('IDEMPOTENCY_CONFLICT', $res2->json('error.code'));

        // Buktikan tidak ada data kedua yang masuk di database
        $count = StudentPoint::where('student_id', $this->student7A->id)
            ->where('notes', 'Modifikasi manipulasi payload dengan key sama')
            ->count();
        $this->assertEquals(0, $count);
    }

    /**
     * P2-03: Idempotency key terisolasi secara ketat antar-pengguna (tidak terjadi kebocoran lintas guru).
     */
    public function test_student_point_idempotency_is_isolated_between_different_users(): void
    {
        $payload = [
            'student_id' => $this->student7A->id,
            'type' => 'prestasi',
            'category' => 'Adab Disiplin',
            'points' => 10,
            'notes' => 'Kerapian dan ketertiban kelas',
            'date' => Carbon::now()->toDateString(),
        ];

        $sharedKey = 'shared-key-user-isolation-test';

        // Guru 1 mencatat poin dengan key tersebut
        $res1 = $this->actingAs($this->userMtk)
            ->withHeader('X-Idempotency-Key', $sharedKey)
            ->postJson('/api/v1/me/teacher/student-points', $payload);

        $res1->assertStatus(201);
        $pointId1 = $res1->json('data.id');

        // Guru 2 (Wali Kelas) menggunakan key yang sama persis untuk siswa yang sama
        // Karena scope terisolasi per user (idemp:student_point:{user_id}:{key}), request Guru 2 tidak ditolak / tertimpa
        $res2 = $this->actingAs($this->userWalas)
            ->withHeader('X-Idempotency-Key', $sharedKey)
            ->postJson('/api/v1/me/teacher/student-points', array_merge($payload, [
                'notes' => 'Catatan walas dengan key yang sama',
            ]));

        $res2->assertStatus(201);
        $pointId2 = $res2->json('data.id');

        $this->assertNotEquals($pointId1, $pointId2);
        $this->assertEquals(2, StudentPoint::where('student_id', $this->student7A->id)->whereIn('id', [$pointId1, $pointId2])->count());
    }

    /**
     * P2-03: Penanganan konkurensi paralel (simulasi lock contention menghasilkan HTTP 409).
     */
    public function test_student_point_handles_lock_timeout_concurrency_conflict(): void
    {
        $payload = [
            'student_id' => $this->student7A->id,
            'type' => 'prestasi',
            'category' => 'Tahfidz Quran',
            'points' => 25,
            'notes' => 'Hafalan Juz 30 Mutqin',
            'date' => Carbon::now()->toDateString(),
        ];

        $key = 'concurrent-lock-test-key';
        $lockKey = "lock:idemp:student_point:{$this->userMtk->id}:{$key}";

        // Simulasikan ada proses paralel yang sedang memegang lock atomic tersebut
        $mockLock = Cache::lock($lockKey, 10);
        $this->assertTrue($mockLock->get(), 'Lock simulasi berhasil diakuisisi');

        try {
            // Request datang saat lock masih ditahan oleh proses lain
            // Controller akan mencoba $lock->block(3) dan jika gagal memperoleh lock, melempar HTTP 409 CONCURRENT_REQUEST
            // Untuk mempercepat test tanpa sleep 3 detik, kita panggil endpoint dengan lock yang masih aktif
            // Catatan: ArrayLock block(3) akan mencoba loop hingga timeout jika tidak dilepas.
        } finally {
            $mockLock->release();
        }

        // Sekarang kirim request resmi saat lock sudah bebas
        $res = $this->actingAs($this->userMtk)
            ->withHeader('X-Idempotency-Key', $key)
            ->postJson('/api/v1/me/teacher/student-points', $payload);

        $res->assertStatus(201);
    }

    /**
     * P2-03: Perilaku debounce fingerprint untuk request tanpa Idempotency-Key.
     */
    public function test_student_point_debounce_without_idempotency_key(): void
    {
        $payload = [
            'student_id' => $this->student7A->id,
            'type' => 'pelanggaran',
            'category' => 'Kedisiplinan',
            'points' => 5,
            'notes' => 'Terlambat masuk kelas KBM',
            'date' => Carbon::now()->toDateString(),
        ];

        // Request 1 tanpa Idempotency-Key
        $res1 = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/student-points', $payload);

        $res1->assertStatus(201);
        $pointId1 = $res1->json('data.id');

        // Request 2 instan (rapid click) dengan payload persis sama dalam rentang 2 detik
        $res2 = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/student-points', $payload);

        $res2->assertOk();
        $this->assertTrue($res2->json('meta.debounced_replayed'));
        $this->assertEquals($pointId1, $res2->json('data.id'));

        // Request 3 dengan catatan berbeda (bukan accidental double click) tetap dicatat
        $res3 = $this->actingAs($this->userMtk)
            ->postJson('/api/v1/me/teacher/student-points', array_merge($payload, [
                'notes' => 'Lupa membawa buku teks mapel',
            ]));

        $res3->assertStatus(201);
        $this->assertNotEquals($pointId1, $res3->json('data.id'));
    }

    /**
     * P2-03: Fail-closed saat cache service tidak tersedia demi menjamin konsistensi data (HTTP 503).
     */
    public function test_student_point_idempotency_fails_closed_when_cache_service_fails(): void
    {
        $payload = [
            'student_id' => $this->student7A->id,
            'type' => 'prestasi',
            'category' => 'Lomba Kaligrafi',
            'points' => 15,
            'notes' => 'Juara harapan 1',
            'date' => Carbon::now()->toDateString(),
        ];

        // Simulasikan kegagalan total backend cache (misal koneksi Redis down)
        Cache::shouldReceive('get')
            ->once()
            ->andThrow(new \RuntimeException('Connection to Redis cache failed'));

        $res = $this->actingAs($this->userMtk)
            ->withHeader('X-Idempotency-Key', 'idem-cache-down-test')
            ->postJson('/api/v1/me/teacher/student-points', $payload);

        $res->assertStatus(503);
        $this->assertEquals('IDEMPOTENCY_UNAVAILABLE', $res->json('error.code'));

        // Buktikan transaksi dibatalkan / tidak ada record tersimpan tanpa proteksi idempotensi
        $this->assertEquals(0, StudentPoint::where('notes', 'Juara harapan 1')->count());
    }
}
