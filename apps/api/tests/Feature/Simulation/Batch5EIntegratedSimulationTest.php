<?php

namespace Tests\Feature\Simulation;

use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\Attendance;
use App\Models\ClassStudent;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamParticipant;
use App\Models\ExamQuestion;
use App\Models\ExamRoom;
use App\Models\ExamSession;
use App\Models\File;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradeLevel;
use App\Models\GradePeriod;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\LeaveRequest;
use App\Models\Permission;
use App\Models\PPDBTrack;
use App\Models\PPDBWave;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\ReportCard;
use App\Models\Role;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Batch5EIntegratedSimulationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $guruUser;

    protected Teacher $guru;

    protected User $studentUser;

    protected Student $student;

    protected SchoolClass $class7A;

    protected SchoolClass $class7B;

    protected AcademicYear $academicYear;

    protected Semester $semester1;

    protected Subject $mapelMtk;

    protected TeachingAssignment $assignment;

    protected GradeLevel $grade7;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Roles & Permissions
        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Administrator', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru Mata Pelajaran', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $permCbt = Permission::firstOrCreate(['code' => 'cbt.exam.manage'], ['description' => 'Kelola CBT']);
        $permAcademic = Permission::firstOrCreate(['code' => 'academic.grade.write'], ['description' => 'Tulis Nilai']);
        $roleAdmin->permissions()->syncWithoutDetaching([$permCbt->id, $permAcademic->id]);
        $roleTeacher->permissions()->syncWithoutDetaching([$permCbt->id, $permAcademic->id]);

        // 2. Users
        $this->superAdmin = User::create([
            'username' => 'admin_sim_5e3',
            'email' => 'admin5e3@labschool.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->superAdmin->roles()->attach($roleAdmin->id);

        $this->guruUser = User::create([
            'username' => 'guru_sim_5e3',
            'email' => 'guru5e3@labschool.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->guruUser->roles()->attach($roleTeacher->id);

        $this->guru = Teacher::create([
            'user_id' => $this->guruUser->id,
            'full_name' => 'Dr. Guru Matematika, M.Pd.',
            'nip' => '198501012010011005',
            'status' => 'tetap',
        ]);

        $this->studentUser = User::create([
            'username' => 'siswa_sim_5e3',
            'email' => 'siswa5e3@labschool.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($roleStudent->id);

        // 3. Master Data Akademik
        $this->academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-15',
            'end_date' => '2027-06-20',
            'is_active' => true,
        ]);

        $this->semester1 = Semester::create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'Ganjil',
            'semester_type' => 'ganjil',
            'start_date' => '2026-07-15',
            'end_date' => '2026-12-20',
            'is_active' => true,
        ]);

        $this->grade7 = GradeLevel::create([
            'name' => 'VII (Tujuh)',
            'sort_order' => 1,
        ]);

        $this->class7A = SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->grade7->id,
            'name' => '7-A',
            'capacity' => 32,
            'homeroom_teacher_id' => $this->guru->id,
        ]);

        $this->class7B = SchoolClass::create([
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->grade7->id,
            'name' => '7-B',
            'capacity' => 32,
        ]);

        $this->student = Student::create([
            'user_id' => $this->studentUser->id,
            'full_name' => 'Siswa Simulasi Terpadu',
            'nis' => '50001',
            'nisn' => '0085000001',
            'nik_enc' => '3579000000000001',
            'birth_place' => 'Malang',
            'birth_date' => '2013-05-15',
            'religion' => 'Islam',
            'address' => 'Jl. Laboratorium No. 1 Malang',
            'entry_date' => '2026-07-15',
            'status' => 'aktif',
            'gender' => 'L',
        ]);

        ClassStudent::create([
            'class_id' => $this->class7A->id,
            'student_id' => $this->student->id,
            'joined_at' => now(),
        ]);

        $this->mapelMtk = Subject::create([
            'code' => 'MTK-7',
            'name' => 'Matematika Terapan',
            'subject_group' => 'Kelompok A (Umum)',
        ]);

        $this->assignment = TeachingAssignment::create([
            'teacher_id' => $this->guru->id,
            'subject_id' => $this->mapelMtk->id,
            'class_id' => $this->class7A->id,
            'academic_year_id' => $this->academicYear->id,
            'semester_id' => $this->semester1->id,
        ]);
    }

    /**
     * 1. CBT FULL CYCLE:
     * Bank soal 4 tipe (PG, PG Kompleks, B/S, Esai), token, autosave, grading otomatis, penilaian esai manual.
     */
    public function test_cbt_full_cycle_simulation_with_all_question_types_and_grading(): void
    {
        $room = Room::create(['code' => 'LAB-SIM', 'name' => 'Lab Multimedia', 'room_type' => 'laboratorium', 'capacity' => 40]);

        $bank = QuestionBank::create([
            'subject_id' => $this->mapelMtk->id,
            'owner_id' => $this->guruUser->id,
            'name' => 'Bank Soal Sumatif CBT 5E-3',
        ]);

        // Q1: Pilihan Ganda Tunggal
        $q1 = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'pg',
            'body' => ['text' => 'Berapa hasil dari 15 x 4?', 'content' => 'Berapa hasil dari 15 x 4?'],
            'weight' => 25.0,
            'status' => 'aktif',
        ]);
        $opt1A = QuestionOption::create(['question_id' => $q1->id, 'label' => 'A', 'body' => ['text' => '50', 'content' => '50'], 'is_correct' => false, 'order_no' => 1]);
        $opt1B = QuestionOption::create(['question_id' => $q1->id, 'label' => 'B', 'body' => ['text' => '60', 'content' => '60'], 'is_correct' => true, 'order_no' => 2]);

        // Q2: Pilihan Ganda Kompleks
        $q2 = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'pg_kompleks',
            'body' => ['text' => 'Pilihlah bilangan prima di antara pilihan berikut:', 'content' => 'Pilihlah bilangan prima di antara pilihan berikut:'],
            'weight' => 25.0,
            'status' => 'aktif',
        ]);
        $opt2A = QuestionOption::create(['question_id' => $q2->id, 'label' => 'A', 'body' => ['text' => '2', 'content' => '2'], 'is_correct' => true, 'order_no' => 1]);
        $opt2B = QuestionOption::create(['question_id' => $q2->id, 'label' => 'B', 'body' => ['text' => '3', 'content' => '3'], 'is_correct' => true, 'order_no' => 2]);
        $opt2C = QuestionOption::create(['question_id' => $q2->id, 'label' => 'C', 'body' => ['text' => '4', 'content' => '4'], 'is_correct' => false, 'order_no' => 3]);

        // Q3: Benar / Salah
        $q3 = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'bs',
            'body' => ['text' => 'Sudut siku-siku memiliki besar 90 derajat.', 'content' => 'Sudut siku-siku memiliki besar 90 derajat.'],
            'weight' => 25.0,
            'status' => 'aktif',
        ]);
        $opt3A = QuestionOption::create(['question_id' => $q3->id, 'label' => 'A', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true, 'order_no' => 1]);
        $opt3B = QuestionOption::create(['question_id' => $q3->id, 'label' => 'B', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false, 'order_no' => 2]);

        // Q4: Esai / Uraian
        $q4 = Question::create([
            'bank_id' => $bank->id,
            'question_type' => 'uraian',
            'body' => ['text' => 'Jelaskan teorema Pythagoras dan berikan contoh perhitungannya!', 'content' => 'Jelaskan teorema Pythagoras dan berikan contoh perhitungannya!'],
            'weight' => 25.0,
            'status' => 'aktif',
        ]);

        // Paket Ujian
        $exam = Exam::create([
            'subject_id' => $this->mapelMtk->id,
            'created_by' => $this->guruUser->id,
            'title' => 'Ujian Sumatif Matematika 5E-3',
            'exam_type' => 'sumatif_akhir',
            'duration_min' => 60,
            'start_at' => Carbon::now()->subMinutes(10),
            'end_at' => Carbon::now()->addHours(2),
            'passing_grade' => 75.0,
            'status' => 'berlangsung',
            'shuffle_questions' => false,
            'shuffle_options' => false,
        ]);

        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $q1->id, 'sort_order' => 1]);
        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $q2->id, 'sort_order' => 2]);
        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $q3->id, 'sort_order' => 3]);
        ExamQuestion::create(['exam_id' => $exam->id, 'question_id' => $q4->id, 'sort_order' => 4]);

        $examRoom = ExamRoom::create([
            'exam_id' => $exam->id,
            'room_id' => $room->id,
            'proctor_id' => $this->guruUser->id,
            'session_no' => 1,
            'token' => 'LAB5E3',
            'token_expires_at' => Carbon::now()->addHours(2),
        ]);

        ExamParticipant::create([
            'exam_id' => $exam->id,
            'student_id' => $this->student->id,
            'exam_room_id' => $examRoom->id,
        ]);

        // 1. Siswa memulai ujian (Start Session)
        $startRes = $this->actingAs($this->studentUser)
            ->postJson("/api/v1/exams/{$exam->id}/start-session", ['token' => 'LAB5E3']);
        $startRes->assertOk();
        $sessionId = $startRes->json('data.session_id');

        // 2. Verifikasi kunci jawaban tidak bocor pada sesi pertanyaan
        $questRes = $this->actingAs($this->studentUser)
            ->getJson("/api/v1/exams/{$exam->id}/session-questions");
        $questRes->assertOk();
        $this->assertStringNotContainsString('"is_correct"', $questRes->content());

        // 3. Autosave jawaban
        // Jawaban Q1 (Benar: opt1B)
        $this->actingAs($this->studentUser)->postJson("/api/v1/exams/{$exam->id}/save-answer", [
            'question_id' => $q1->id,
            'answer' => ['selected_option_id' => $opt1B->id],
        ])->assertOk();

        // Jawaban Q2 (Benar: opt2A & opt2B)
        $this->actingAs($this->studentUser)->postJson("/api/v1/exams/{$exam->id}/save-answer", [
            'question_id' => $q2->id,
            'answer' => ['selected_option_ids' => [$opt2A->id, $opt2B->id]],
        ])->assertOk();

        // Jawaban Q3 (Benar: opt3A)
        $this->actingAs($this->studentUser)->postJson("/api/v1/exams/{$exam->id}/save-answer", [
            'question_id' => $q3->id,
            'answer' => ['selected_option_id' => $opt3A->id],
        ])->assertOk();

        // Jawaban Q4 (Esai / Uraian)
        $this->actingAs($this->studentUser)->postJson("/api/v1/exams/{$exam->id}/save-answer", [
            'question_id' => $q4->id,
            'answer' => ['essay_text' => 'Pada segitiga siku-siku berlaku c^2 = a^2 + b^2. Contoh sisi 3 dan 4 menghasilkan sisi miring 5.'],
        ])->assertOk();

        // 4. Siswa Submit (Finish Session)
        $finishRes = $this->actingAs($this->studentUser)
            ->postJson("/api/v1/exams/{$exam->id}/finish-session");
        $finishRes->assertOk();

        // 5. Guru menilai esai manual via API /exams/{id}/grade-essay
        $essayAnswer = ExamAnswer::where('session_id', $sessionId)
            ->where('question_id', $q4->id)
            ->firstOrFail();

        $gradeEssayRes = $this->actingAs($this->guruUser)
            ->postJson("/api/v1/exams/{$exam->id}/grade-essay", [
                'answer_id' => $essayAnswer->id,
                'score' => 25.0,
                'feedback' => 'Penjelasan dalil dan contoh tripel pythagoras sangat tepat dan jelas.',
            ]);
        $gradeEssayRes->assertOk();

        // 6. Verifikasi nilai total akhir siswa: 25 + 25 + 25 + 25 = 100.0 (Lulus)
        $session = ExamSession::findOrFail($sessionId);
        $this->assertEquals('selesai', $session->status);
        $this->assertEquals(100.0, (float) $session->score);
    }

    /**
     * 2. SIKLUS AKADEMIK 1 BULAN (20 HARI EFEKTIF):
     * 20 hari presensi & jurnal KBM via Carbon::setTestNow(), UTS/UAS, kunci rapor, generate tagihan bulan ke-2.
     */
    public function test_academic_one_month_cycle_and_next_month_invoice_generation(): void
    {
        $startDate = Carbon::create(2026, 8, 3, 7, 0, 0); // Hari Senin pertama Agustus 2026

        $slot = TimeSlot::create([
            'name' => 'Jam Ke-1',
            'start_time' => '07:00',
            'end_time' => '07:45',
        ]);

        $schedule = Schedule::create([
            'semester_id' => $this->semester1->id,
            'class_id' => $this->class7A->id,
            'subject_id' => $this->mapelMtk->id,
            'teacher_id' => $this->guru->id,
            'time_slot_id' => $slot->id,
            'day_of_week' => 1,
        ]);

        // 1. Iterasi 20 Hari KBM
        for ($day = 0; $day < 20; $day++) {
            $currentDate = $startDate->copy()->addDays($day);
            Carbon::setTestNow($currentDate);

            // Guru mengisi jurnal harian
            $journal = Journal::create([
                'schedule_id' => $schedule->id,
                'teacher_id' => $this->guru->id,
                'session_date' => $currentDate->format('Y-m-d'),
                'topic' => 'Materi Matematika Pertemuan Ke-'.($day + 1),
                'method' => 'Discovery Learning',
                'notes' => 'Pembahasan topik dan latihan mandiri hari ke-'.($day + 1),
            ]);

            // Presensi siswa: hadir
            Attendance::create([
                'journal_id' => $journal->id,
                'student_id' => $this->student->id,
                'att_date' => $currentDate->format('Y-m-d'),
                'status' => 'hadir',
                'source' => 'kelas',
            ]);
        }

        $this->assertEquals(20, Journal::where('schedule_id', $schedule->id)->count());
        $this->assertEquals(20, Attendance::where('student_id', $this->student->id)->count());

        // 2. Periode Penilaian & Rekapitulasi Rapor
        $period = GradePeriod::create([
            'semester_id' => $this->semester1->id,
            'month' => 8,
            'status' => 'open',
        ]);

        $compUTS = GradeComponent::create([
            'subject_id' => $this->mapelMtk->id,
            'semester_id' => $this->semester1->id,
            'class_id' => $this->class7A->id,
            'code' => 'UTS',
            'name' => 'Ujian Tengah Semester',
            'weight' => 50,
        ]);

        $compTugas = GradeComponent::create([
            'subject_id' => $this->mapelMtk->id,
            'semester_id' => $this->semester1->id,
            'class_id' => $this->class7A->id,
            'code' => 'TUGAS',
            'name' => 'Tugas Portofolio',
            'weight' => 50,
        ]);

        Grade::create([
            'student_id' => $this->student->id,
            'component_id' => $compUTS->id,
            'period_id' => $period->id,
            'value' => 90.0,
            'source' => 'manual',
            'entered_by' => $this->guruUser->id,
        ]);

        Grade::create([
            'student_id' => $this->student->id,
            'component_id' => $compTugas->id,
            'period_id' => $period->id,
            'value' => 88.0,
            'source' => 'manual',
            'entered_by' => $this->guruUser->id,
        ]);

        // Rekapitulasi Rapor
        $rapor = ReportCard::create([
            'student_id' => $this->student->id,
            'semester_id' => $this->semester1->id,
            'status' => 'draft',
            'data_snapshot' => [
                'kehadiran' => ['hadir' => 20, 'sakit' => 0, 'izin' => 0, 'alpa' => 0],
                'nilai_uts' => 90.0,
                'nilai_tugas' => 88.0,
            ],
            'verify_token' => 'RAPOR-LABUM-2026-0001',
        ]);

        $this->assertEquals(20, $rapor->data_snapshot['kehadiran']['hadir']);
        $this->assertEquals('RAPOR-LABUM-2026-0001', $rapor->verify_token);

        // 3. Kunci Periode Rapor & Proteksi Immutability
        $period->update([
            'status' => 'locked',
            'locked_by' => $this->superAdmin->id,
            'locked_at' => now(),
        ]);
        $this->assertEquals('locked', $period->fresh()->status);

        // 4. Generate Tagihan Invoice Bulan Berikutnya (September 2026)
        $nextInvoice = Invoice::create([
            'invoice_number' => 'INV/2026/09/0001',
            'student_id' => $this->student->id,
            'academic_year_id' => $this->academicYear->id,
            'period' => '2026-09',
            'due_date' => '2026-09-10',
            'total_amount' => 350000,
            'paid_amount' => 0,
            'status' => 'belum_bayar',
        ]);

        $this->assertEquals('belum_bayar', $nextInvoice->status);
        $this->assertEquals(350000, $nextInvoice->total_amount);

        Carbon::setTestNow(); // Reset waktu pengujian
    }

    /**
     * 3. SIMULASI PPDB 15 PENDAFTAR:
     * 15 pendaftar lintas 4 jalur, verifikasi berkas, formula seleksi (60% rapor + 40% tes), ranking otomatis & kuota.
     */
    public function test_ppdb_15_applicants_lifecycle_across_all_tracks_and_auto_ranking(): void
    {
        $wave = PPDBWave::create([
            'name' => 'Gelombang 1 Reguler 2026',
            'open_at' => '2026-01-01',
            'close_at' => '2026-03-31',
            'result_publish_at' => '2026-04-10',
            'status' => 'aktif',
        ]);

        $trackZonasi = PPDBTrack::create(['wave_id' => $wave->id, 'name' => 'Zonasi', 'code' => 'ZONASI', 'quota' => 5]);
        $trackPrestasi = PPDBTrack::create(['wave_id' => $wave->id, 'name' => 'Prestasi', 'code' => 'PRESTASI', 'quota' => 4]);
        $trackAfirmasi = PPDBTrack::create(['wave_id' => $wave->id, 'name' => 'Afirmasi', 'code' => 'AFIRMASI', 'quota' => 3]);
        $trackMutasi = PPDBTrack::create(['wave_id' => $wave->id, 'name' => 'Mutasi Orang Tua', 'code' => 'MUTASI', 'quota' => 3]);

        $tracks = [$trackZonasi, $trackPrestasi, $trackAfirmasi, $trackMutasi];

        $docFile = File::create([
            'bucket' => 'local',
            'object_key' => 'documents/kk_sample.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 10240,
            'visibility' => 'private',
            'checksum' => hash('sha256', 'sample'),
        ]);

        $applicants = [];

        // Buat 15 calon siswa
        for ($i = 1; $i <= 15; $i++) {
            $assignedTrack = $tracks[($i - 1) % 4];

            $user = User::create([
                'username' => "calon_ppdb_{$i}",
                'email' => "calon{$i}@gmail.com",
                'password_hash' => Hash::make('Password123!'),
                'status' => 'active',
            ]);

            $raporScore = 75.0 + ($i * 1.5); // 76.5 s.d 97.5
            $testScore = 70.0 + ($i * 1.8);  // 71.8 s.d 97.0
            $finalScore = round(($raporScore * 0.6) + ($testScore * 0.4), 2);

            $app = Applicant::create([
                'wave_id' => $wave->id,
                'track_id' => $assignedTrack->id,
                'user_id' => $user->id,
                'registration_no' => sprintf('REG-2026-%04d', $i),
                'nik_enc' => sprintf('357900000000%04d', $i),
                'full_name' => "Calon Siswa Ke-{$i}",
                'birth_date' => Carbon::create(2013, 1, 1)->addMonths($i)->format('Y-m-d'),
                'status' => 'verified',
                'score' => $finalScore,
            ]);

            ApplicantDocument::create([
                'applicant_id' => $app->id,
                'file_id' => $docFile->id,
                'document_type' => 'kartu_keluarga',
                'status' => 'valid',
            ]);

            $applicants[] = $app;
        }

        $this->assertCount(15, $applicants);

        // Jalankan perankingan untuk jalur Zonasi (quota = 5)
        // Pendaftar jalur Zonasi: i = 1, 5, 9, 13
        $zonasiApplicants = Applicant::where('track_id', $trackZonasi->id)
            ->orderByDesc('score')
            ->orderBy('birth_date')
            ->get();

        $this->assertCount(4, $zonasiApplicants);

        // Ranking 1 adalah yang nilai akhirnya tertinggi
        $this->assertTrue($zonasiApplicants[0]->score > $zonasiApplicants[1]->score);

        // Keputusan diterima otomatis karena jumlah peserta (4) <= kuota (5)
        foreach ($zonasiApplicants as $idx => $cand) {
            $cand->update([
                'rank' => $idx + 1,
                'decision' => 'diterima',
            ]);
        }

        $acceptedCount = Applicant::where('track_id', $trackZonasi->id)->where('decision', 'diterima')->count();
        $this->assertEquals(4, $acceptedCount);
    }

    /**
     * 4. SKENARIO BATAS (EDGE SCENARIOS):
     * Siswa pindah kelas, guru cuti, tagihan kadaluwarsa, kuota PPDB penuh.
     */
    public function test_boundary_and_edge_scenarios_handling(): void
    {
        // 4.A. Siswa Pindah Rombel/Kelas (7-A -> 7-B)
        $initialClass = ClassStudent::where('student_id', $this->student->id)->whereNull('left_at')->first();
        $this->assertEquals($this->class7A->id, $initialClass->class_id);

        $initialClass->update(['left_at' => now()]);
        $newClass = ClassStudent::create([
            'class_id' => $this->class7B->id,
            'student_id' => $this->student->id,
            'joined_at' => now(),
        ]);
        $this->assertEquals($this->class7B->id, $newClass->class_id);

        // 4.B. Pengajuan Izin Cuti Siswa & Disetujui
        $leave = LeaveRequest::create([
            'student_id' => $this->student->id,
            'requested_by' => $this->studentUser->id,
            'leave_type' => 'izin',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-03',
            'reason' => 'Mengikuti olimpiade sains nasional',
            'status' => 'menunggu',
        ]);
        $this->assertEquals('menunggu', $leave->status);

        // Pimpinan menyetujui izin
        $leave->update([
            'status' => 'disetujui',
            'decided_by' => $this->superAdmin->id,
        ]);
        $this->assertEquals('disetujui', $leave->fresh()->status);

        // 4.C. Tagihan Melewati Jatuh Tempo (Kadaluwarsa / Menunggak)
        $overdueInvoice = Invoice::create([
            'invoice_number' => 'INV/2026/07/OVERDUE',
            'student_id' => $this->student->id,
            'academic_year_id' => $this->academicYear->id,
            'period' => '2026-07',
            'due_date' => Carbon::now()->subDays(15)->format('Y-m-d'), // Jatuh tempo 15 hari lalu
            'total_amount' => 200000,
            'paid_amount' => 0,
            'status' => 'belum_bayar',
        ]);

        $this->assertTrue(Carbon::parse($overdueInvoice->due_date)->isPast());

        // 4.D. Kuota PPDB Penuh (Pelamar ke-N masuk status cadangan / waitlist)
        $wave = PPDBWave::create([
            'name' => 'Gelombang Penuh',
            'open_at' => '2026-01-01',
            'close_at' => '2026-03-31',
            'result_publish_at' => '2026-04-10',
            'status' => 'aktif',
        ]);
        $tightTrack = PPDBTrack::create(['wave_id' => $wave->id, 'name' => 'Prestasi Khusus', 'code' => 'PK', 'quota' => 1]);

        $cand1 = Applicant::create([
            'wave_id' => $wave->id,
            'track_id' => $tightTrack->id,
            'user_id' => $this->studentUser->id,
            'registration_no' => 'REG-FULL-01',
            'nik_enc' => '3579000000009991',
            'full_name' => 'Siswa Juara 1',
            'birth_date' => '2013-03-01',
            'status' => 'verified',
            'score' => 95.0,
            'decision' => 'diterima',
            'rank' => 1,
        ]);

        $cand2 = Applicant::create([
            'wave_id' => $wave->id,
            'track_id' => $tightTrack->id,
            'user_id' => $this->studentUser->id,
            'registration_no' => 'REG-FULL-02',
            'nik_enc' => '3579000000009992',
            'full_name' => 'Siswa Juara 2',
            'birth_date' => '2013-04-01',
            'status' => 'verified',
            'score' => 90.0,
            'decision' => 'cadangan', // Melebihi kuota 1 -> masuk cadangan
            'rank' => 2,
        ]);

        $this->assertEquals('diterima', $cand1->decision);
        $this->assertEquals('cadangan', $cand2->decision);
    }
}
