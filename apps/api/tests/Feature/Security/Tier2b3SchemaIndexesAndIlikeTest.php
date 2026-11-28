<?php

namespace Tests\Feature\Security;

use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\Device;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\ExamRoom;
use App\Models\ExamSession;
use App\Models\Facility;
use App\Models\File;
use App\Models\PPDBTrack;
use App\Models\PPDBWave;
use App\Models\ProgramMaterial;
use App\Models\Role;
use App\Models\Room;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Batch 2B-3: Verifikasi skema PostgreSQL non-destruktif (P1-41, P0-30, P1-42, P1-43, P1-44)
 * dan pre-flight audit CLI db:preflight-tier2b3.
 */
class Tier2b3SchemaIndexesAndIlikeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(
            ['code' => 'super_admin'],
            ['name' => 'Super Administrator', 'is_system' => true]
        );

        $this->admin = User::create([
            'username' => 'admin_t2b3',
            'email' => 'admin_t2b3@sekolah.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->admin->roles()->attach($role->id);
    }

    public function test_p1_41_nik_enc_and_fcm_token_support_long_text_values(): void
    {
        $longCiphertext = str_repeat('X', 360);
        $longToken = str_repeat('T', 750);

        $student = Student::create([
            'nis' => '991001',
            'nisn' => '00991001',
            'nik_enc' => $longCiphertext,
            'full_name' => 'Siswa Uji Panjang Ciphertext',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2011-01-10',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $wave = PPDBWave::create([
            'name' => 'Gelombang Uji P1-41',
            'open_at' => '2026-01-01 00:00:00',
            'close_at' => '2026-03-31 23:59:59',
            'result_publish_at' => '2026-04-05 08:00:00',
            'status' => 'draft',
        ]);
        $track = PPDBTrack::create([
            'wave_id' => $wave->id,
            'code' => 'REG-P141',
            'name' => 'Reguler',
            'quota' => 50,
        ]);

        $applicant = Applicant::create([
            'wave_id' => $wave->id,
            'track_id' => $track->id,
            'registration_no' => 'REG-P141-001',
            'nik_enc' => $longCiphertext,
            'full_name' => 'Calon Uji Panjang Ciphertext',
            'birth_date' => '2011-02-10',
            'status' => 'draft',
        ]);

        $device = Device::create([
            'user_id' => $this->admin->id,
            'platform' => 'web',
            'fcm_token' => $longToken,
        ]);

        $this->assertSame(360, strlen($student->fresh()->nik_enc));
        $this->assertSame(360, strlen($applicant->fresh()->nik_enc));
        $this->assertSame(750, strlen($device->fresh()->fcm_token));
    }

    public function test_p0_30_null_school_partial_uniques_and_composite_indexes_are_enforced(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Partial unique & pg_indexes check requires PostgreSQL.');
        }

        AcademicYear::create([
            'school_id' => null,
            'name' => '2026/2027-Uji-A',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);
        AcademicYear::create([
            'school_id' => null,
            'name' => '2026/2027-Uji-B',
            'start_date' => '2027-07-01',
            'end_date' => '2028-06-30',
            'is_active' => true,
        ]);
    }

    public function test_p0_30_ppdb_wave_null_school_partial_unique_and_composite_indexes_exist(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Partial unique & pg_indexes check requires PostgreSQL.');
        }

        $indexes = collect(DB::select("SELECT indexname FROM pg_indexes WHERE schemaname = 'public'"))
            ->pluck('indexname')
            ->all();

        foreach ([
            'uq_single_active_academic_year_null_school',
            'uq_single_active_ppdb_wave_null_school',
            'idx_grades_period_component',
            'idx_grades_student_created_at',
            'idx_grades_exam_id',
            'idx_exam_answers_question_score',
            'idx_exam_sessions_status_ends_at',
            'idx_exam_sessions_status_deadline_at',
            'idx_exam_sessions_student_status',
            'idx_audit_logs_created_at_desc',
            'idx_audit_logs_action_created_at',
            'idx_attendances_att_date_status',
            'idx_attendances_student_date_status',
            'idx_invoices_status_due_date',
            'idx_payments_status_payment_date',
            'idx_applicants_wave_track_status',
            'idx_applicants_status_rereg_deadline',
        ] as $expectedIndex) {
            $this->assertContains($expectedIndex, $indexes);
        }

        PPDBWave::create([
            'school_id' => null,
            'name' => 'Gelombang Aktif 1',
            'open_at' => '2026-01-01 00:00:00',
            'close_at' => '2026-03-31 23:59:59',
            'result_publish_at' => '2026-04-05 08:00:00',
            'status' => 'aktif',
        ]);

        $this->expectException(QueryException::class);
        PPDBWave::create([
            'school_id' => null,
            'name' => 'Gelombang Aktif 2',
            'open_at' => '2026-04-01 00:00:00',
            'close_at' => '2026-06-30 23:59:59',
            'result_publish_at' => '2026-07-05 08:00:00',
            'status' => 'aktif',
        ]);
    }

    public function test_p1_42_soft_deleted_records_can_be_recreated_while_active_duplicates_fail(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'nis' => '99202601',
            'nisn' => '0099202601',
            'nik' => '3579010101010099',
            'full_name' => 'Siswa Soft Delete Uji',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2012-05-05',
            'entry_date' => '2026-07-01',
        ];

        $create1 = $this->postJson('/api/v1/students', $payload);
        $create1->assertStatus(201);
        $firstStudentId = $create1->json('data.id');

        // Duplikat aktif wajib ditolak validasi (400 VALIDATION_FAILED)
        $dupActive = $this->postJson('/api/v1/students', $payload);
        $dupActive->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // Soft-delete lalu buat ulang dengan NIS/NISN sama wajib sukses
        $this->deleteJson("/api/v1/students/{$firstStudentId}")
            ->assertStatus(200);

        $recreate = $this->postJson('/api/v1/students', $payload);
        $recreate->assertStatus(201);
        $this->assertNotSame($firstStudentId, $recreate->json('data.id'));

        if (DB::getDriverName() === 'pgsql') {
            $this->expectException(QueryException::class);
            Student::create([
                'nis' => '99202601',
                'nisn' => '0099202699',
                'nik_enc' => 'enc-dup',
                'full_name' => 'Siswa Duplikat DB',
                'gender' => 'L',
                'birth_place' => 'Malang',
                'birth_date' => '2012-05-05',
                'entry_date' => '2026-07-01',
                'status' => 'aktif',
            ]);
        }
    }

    public function test_p1_43_where_ilike_and_trigram_indexes_work_case_insensitively(): void
    {
        Sanctum::actingAs($this->admin);

        Student::create([
            'nis' => '993001',
            'nisn' => '00993001',
            'nik_enc' => 'enc-3001',
            'full_name' => 'BUDI SANTOSO',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2011-04-04',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        Facility::create([
            'name' => 'Laboratorium Komputer Utama',
            'code' => null,
            'location' => 'Gedung B Lantai 2',
            'facility_type' => 'lab',
            'capacity' => 36,
            'is_active' => true,
        ]);

        $matchedStudents = Student::query()->whereIlike('full_name', '%budi santoso%')->count();
        $this->assertSame(1, $matchedStudents);

        // Memverifikasi perbaikan FacilityService (COALESCE(code, "")) bekerja bersih di PostgreSQL
        $this->getJson('/api/v1/facilities?search=komputer')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        if (DB::getDriverName() === 'pgsql') {
            $indexes = collect(DB::select("SELECT indexname FROM pg_indexes WHERE schemaname = 'public'"))
                ->pluck('indexname')
                ->all();

            foreach ([
                'idx_trgm_students_nis',
                'idx_trgm_applicants_name',
                'idx_trgm_exams_title',
                'idx_trgm_tickets_title',
                'idx_trgm_assets_name',
                'idx_trgm_announcements_title',
            ] as $trgmIndex) {
                $this->assertContains($trgmIndex, $indexes);
            }

            $planRows = DB::transaction(function () {
                DB::statement('SET LOCAL enable_seqscan = off');

                return DB::select("EXPLAIN (FORMAT JSON) SELECT id FROM students WHERE full_name ILIKE '%santoso%'");
            });

            $planJson = json_encode($planRows);
            $this->assertStringContainsString('idx_trgm_students_name', $planJson ?: '');
        }
    }

    public function test_p1_44_exam_sync_sets_ends_at_fks_null_on_delete_and_preflight_cli(): void
    {
        $this->artisan('db:preflight-tier2b3', ['--json' => true])
            ->assertExitCode(0);

        $subject = Subject::create([
            'code' => 'INF-T2B3',
            'name' => 'Informatika',
            'subject_group' => 'wajib',
        ]);

        $exam = Exam::create([
            'subject_id' => $subject->id,
            'created_by' => $this->admin->id,
            'title' => 'Ujian Sync P1-44',
            'exam_type' => 'uts',
            'duration_min' => 60,
            'start_at' => now()->subHours(2),
            'end_at' => now()->addHours(2),
            'status' => 'berlangsung',
            'sync_node_token' => 'sync-token-p144',
        ]);

        $studentNew = Student::create([
            'nis' => '994001',
            'nisn' => '00994001',
            'nik_enc' => 'enc-4001',
            'full_name' => 'Peserta Sync Baru',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2011-05-05',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);
        $studentExisting = Student::create([
            'nis' => '994002',
            'nisn' => '00994002',
            'nik_enc' => 'enc-4002',
            'full_name' => 'Peserta Sync Lama',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2011-06-06',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $room = Room::create([
            'code' => 'R-T2B3-01',
            'name' => 'Ruang CBT T2B3',
            'room_type' => 'kelas',
            'capacity' => 36,
        ]);
        $examRoom = ExamRoom::create([
            'exam_id' => $exam->id,
            'room_id' => $room->id,
            'proctor_id' => $this->admin->id,
            'session_no' => 1,
            'token' => 'T2B3OK',
        ]);

        ExamParticipant::create(['exam_id' => $exam->id, 'student_id' => $studentNew->id, 'exam_room_id' => $examRoom->id]);
        ExamParticipant::create(['exam_id' => $exam->id, 'student_id' => $studentExisting->id, 'exam_room_id' => $examRoom->id]);

        $existingSession = ExamSession::create([
            'exam_id' => $exam->id,
            'student_id' => $studentExisting->id,
            'status' => 'mengerjakan',
            'started_at' => now()->subMinutes(40),
            'deadline_at' => null,
            'ends_at' => null,
            'seed' => 'seed-lama',
        ]);

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/cbt/sync/push', [
            'exam_id' => $exam->id,
            'node_id' => 'node-lab-01',
            'sync_token' => 'sync-token-p144',
            'sessions' => [
                [
                    'student_id' => $studentNew->id,
                    'started_at' => now()->subMinutes(50)->toIso8601String(),
                    'submitted_at' => now()->subMinutes(5)->toIso8601String(),
                ],
                [
                    'student_id' => $studentExisting->id,
                    'started_at' => now()->subMinutes(40)->toIso8601String(),
                    'submitted_at' => now()->subMinutes(2)->toIso8601String(),
                ],
            ],
        ])
            ->assertStatus(200);

        $newSession = ExamSession::where('exam_id', $exam->id)->where('student_id', $studentNew->id)->firstOrFail();
        $existingSession->refresh();

        $this->assertNotNull($newSession->deadline_at);
        $this->assertSame($newSession->deadline_at->toIso8601String(), $newSession->ends_at?->toIso8601String());
        $this->assertNotNull($existingSession->deadline_at);
        $this->assertSame($existingSession->deadline_at->toIso8601String(), $existingSession->ends_at?->toIso8601String());

        // Verifikasi FK ON DELETE SET NULL pada program_materials.file_id & schools.logo_file_id
        $file = File::create([
            'owner_type' => 'material',
            'owner_id' => (string) Str::uuid(),
            'bucket' => 'materials',
            'object_key' => 'materials/p144-test.pdf',
            'original_name' => 'p144-test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'visibility' => 'privat',
            'uploaded_by' => $this->admin->id,
        ]);

        $material = ProgramMaterial::create([
            'program_type' => 'tahfidz',
            'title' => 'Materi Tahfidz Uji FK',
            'material_type' => 'panduan',
            'file_id' => $file->id,
            'created_by' => $this->admin->id,
            'target_scope' => 'all',
        ]);

        $school = School::create([
            'name' => 'SMP Lab Uji FK',
            'npsn' => '20500999',
            'logo_file_id' => $file->id,
            'is_active' => true,
        ]);

        $file->delete();

        $this->assertNull($material->fresh()->file_id);
        $this->assertNull($school->fresh()->logo_file_id);
    }
}
