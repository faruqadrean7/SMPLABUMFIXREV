<?php

namespace Tests\Feature\Seeder;

use App\Models\Applicant;
use App\Models\Asset;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\ClassStudent;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Facility;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\LeaveRequest;
use App\Models\LibraryBook;
use App\Models\Loan;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\QuestionBank;
use App\Models\ReportCard;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherSalarySlip;
use App\Models\TeachingMaterial;
use App\Models\Ticket;
use Database\Seeders\MonthlySimulationUatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MonthlySimulationSeederFeatureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * BATCH 4B - B.1 & B.2: Verifikasi kelengkapan entitas civitas simulasi 1 bulan kalender relatif.
     */
    public function test_batch4b_monthly_simulation_seeder_completeness(): void
    {
        $this->seed(MonthlySimulationUatSeeder::class);

        // 1. 50 Siswa & Distribusi Rombel (25 di 7-A, 25 di 7-B)
        $this->assertEquals(50, Student::count());

        $class7A = SchoolClass::where('name', 'Kelas 7-A')->firstOrFail();
        $class7B = SchoolClass::where('name', 'Kelas 7-B')->firstOrFail();

        $count7A = ClassStudent::where('class_id', $class7A->id)->count();
        $count7B = ClassStudent::where('class_id', $class7B->id)->count();

        $this->assertEquals(25, $count7A);
        $this->assertEquals(25, $count7B);

        // 2. 50 Wali Murid dengan relasi 1:1
        $guardianRelationsCount = DB::table('student_guardians')->count();
        $this->assertEquals(50, $guardianRelationsCount);

        // 3. 10 Guru dan Wali Kelas Terdefinisi
        $this->assertEquals(10, Teacher::count());
        $this->assertNotNull($class7A->homeroom_teacher_id);
        $this->assertNotNull($class7B->homeroom_teacher_id);

        // 4. KBM 20 Hari Kerja: Jurnal & Presensi Siswa
        $journalsCount = Journal::count();
        $this->assertGreaterThanOrEqual(20, $journalsCount);

        $attendanceCount = Attendance::count();
        $this->assertGreaterThanOrEqual(1000, $attendanceCount);

        // Presensi Guru (10 guru x 20 hari = 200 data)
        $staffAttCount = StaffAttendance::count();
        $this->assertEquals(200, $staffAttCount);

        // 5. Rapor Semester (ReportCard) untuk 50 siswa
        $reportCardCount = ReportCard::count();
        $this->assertEquals(50, $reportCardCount);
        $this->assertEquals(50, ReportCard::where('status', 'published')->count());

        // 6. Modul Ajar (TeachingMaterial / RPP) per guru
        $teachingMaterialsCount = TeachingMaterial::count();
        $this->assertGreaterThanOrEqual(8, $teachingMaterialsCount);

        // 7. Sarana Prasarana (Fasilitas, Booking, Aset, Tiket Kerusakan)
        $this->assertGreaterThanOrEqual(1, Facility::count());
        $this->assertGreaterThanOrEqual(1, Booking::count());
        $this->assertGreaterThanOrEqual(1, Asset::count());
        $this->assertGreaterThanOrEqual(1, Ticket::count());

        // 8. CBT (Bank Soal, Soal, Ujian, Sesi Siswa)
        $this->assertGreaterThanOrEqual(1, QuestionBank::count());
        $this->assertGreaterThanOrEqual(1, Exam::count());
        $this->assertGreaterThanOrEqual(1, ExamSession::count());

        // 9. Izin/Cuti Guru
        $this->assertGreaterThanOrEqual(1, LeaveRequest::count());

        // 10. Slip Gaji 10 Guru
        $salarySlipsCount = TeacherSalarySlip::count();
        $this->assertEquals(10, $salarySlipsCount);

        // 11. Perpustakaan (Buku & Peminjaman)
        $this->assertGreaterThanOrEqual(1, LibraryBook::count());
        $this->assertGreaterThanOrEqual(1, Loan::count());

        // 12. Notifikasi & Audit Log
        $this->assertGreaterThanOrEqual(1, Notification::count());
        $this->assertGreaterThanOrEqual(1, AuditLog::count());

        // 13. Data PPDB (Pendaftar berbagai status: draft, submitted, verified, selected, waitlist, rejected)
        $applicantsCount = Applicant::count();
        $this->assertGreaterThanOrEqual(6, $applicantsCount);
        $this->assertTrue(Applicant::where('status', 'draft')->exists());
        $this->assertTrue(Applicant::where('status', 'submitted')->exists());
        $this->assertTrue(Applicant::where('status', 'verified')->exists());
        $this->assertTrue(Applicant::where('decision', 'diterima')->exists());
        $this->assertTrue(Applicant::where('decision', 'cadangan')->exists());
        $this->assertTrue(Applicant::where('decision', 'ditolak')->exists());

        // 14. Modul Keuangan: 50 Tagihan ber-VA dan Slip Pembayaran Bervariasi
        $this->assertEquals(50, Invoice::count());
        $this->assertEquals(50, Invoice::whereNotNull('va_number')->count());

        $this->assertEquals(42, Payment::count());
        $this->assertEquals(30, Payment::where('status', 'terverifikasi')->count());
        $this->assertEquals(10, Payment::where('status', 'menunggu_verifikasi')->count());
        $this->assertEquals(2, Payment::where('status', 'ditolak')->count());
    }

    /**
     * BATCH 4B - B.3: Guard menolak berjalan pada lingkungan production.
     */
    public function test_batch4b_seeder_rejects_execution_in_production(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('MonthlySimulationUatSeeder DILARANG dijalankan di lingkungan production');

        // Simulasikan environment production
        $this->app['env'] = 'production';
        config(['app.env' => 'production']);

        $seeder = new MonthlySimulationUatSeeder;
        $seeder->run();
    }

    /**
     * BATCH 4B - B.4: File kredensial akun uji terbuat dan tidak kosong.
     */
    public function test_batch4b_seeder_generates_credentials_file(): void
    {
        $this->seed(MonthlySimulationUatSeeder::class);

        $credsPath = storage_path('app/seed-credentials.txt');
        $this->assertFileExists($credsPath);

        $content = file_get_contents($credsPath);
        $this->assertStringContainsString('admin_demo', $content);
        $this->assertStringContainsString('bendahara_demo', $content);
        $this->assertStringContainsString('guru.mtk', $content);
        $this->assertStringContainsString('siswa_001', $content);
        $this->assertStringContainsString('ortu_001', $content);
    }

    /**
     * BATCH 5 - A.4: Idempotensi Lintas Hari dengan Carbon::setTestNow().
     * Menjalankan seeder pada hari D (mis. 2026-10-26) lalu pada hari D+1 (2026-10-27).
     * Memastikan data KBM lama di luar sliding window 20 hari dibersihkan dan data tidak menumpuk ganda.
     */
    public function test_batch5_cross_day_idempotency_with_sliding_window(): void
    {
        // 1. Eksekusi seeder pada Hari D (Senin, 26 Oktober 2026)
        Carbon::setTestNow(Carbon::parse('2026-10-26 09:00:00'));
        $this->seed(MonthlySimulationUatSeeder::class);

        // 2. Eksekusi seeder pada Hari D+1 (Selasa, 27 Oktober 2026)
        Carbon::setTestNow(Carbon::parse('2026-10-27 09:00:00'));
        $this->seed(MonthlySimulationUatSeeder::class);

        // Jumlah hari KBM harus tetap tepat 20 hari kerja (sliding window), tidak menumpuk menjadi 21 hari
        $distinctJournalDates = Journal::distinct('session_date')->count('session_date');
        $this->assertEquals(20, $distinctJournalDates);

        $distinctStaffAttDates = StaffAttendance::distinct('att_date')->count('att_date');
        $this->assertEquals(20, $distinctStaffAttDates);

        // Staff attendance harus tetap 200 (10 guru x 20 hari)
        $this->assertEquals(200, StaffAttendance::count());

        // Siswa tetap 50, tagihan tetap 50
        $this->assertEquals(50, Student::count());
        $this->assertEquals(50, Invoice::count());

        // Kembalikan Carbon test now
        Carbon::setTestNow();
    }

    /**
     * BATCH 5B - A.3 (a): Seeder gagal dengan pesan jelas jika SEEDER_PASSWORD tidak diset.
     */
    public function test_batch5b_seeder_fails_when_seeder_password_not_defined(): void
    {
        $originalPass = env('SEEDER_PASSWORD');
        putenv('SEEDER_PASSWORD=');
        unset($_ENV['SEEDER_PASSWORD'], $_SERVER['SEEDER_PASSWORD']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SEEDER_PASSWORD');

        try {
            (new MonthlySimulationUatSeeder)->run();
        } finally {
            if ($originalPass !== null) {
                putenv("SEEDER_PASSWORD={$originalPass}");
                $_ENV['SEEDER_PASSWORD'] = $originalPass;
                $_SERVER['SEEDER_PASSWORD'] = $originalPass;
            }
        }
    }

    /**
     * BATCH 5B - A.3 (b): Sliding-window hanya menghapus data ber-marker seeder dan menjaga data riil/non-seeder.
     */
    public function test_batch5b_sliding_window_preserves_non_seeder_records(): void
    {
        // 1. Jalankan seeder di hari D
        Carbon::setTestNow(Carbon::parse('2026-10-26 09:00:00'));
        $this->seed(MonthlySimulationUatSeeder::class);

        // 2. Buat data manual non-seeder pada tanggal di luar jendela (mis. 2026-09-01)
        $schedule = Schedule::firstOrFail();
        $teacher = Teacher::firstOrFail();

        $manualJournal = Journal::create([
            'schedule_id' => $schedule->id,
            'session_date' => '2026-09-01',
            'teacher_id' => $teacher->id,
            'topic' => 'Topik Manual Guru Asli',
            'method' => 'Ceramah Interaktif',
            'notes' => 'Catatan manual guru tanpa marker seeder',
        ]);

        $manualAttendance = StaffAttendance::create([
            'user_id' => $teacher->user_id,
            'att_date' => '2026-09-01',
            'check_in' => '2026-09-01 07:00:00',
            'check_out' => '2026-09-01 16:00:00',
            'status' => 'hadir',
            'notes' => 'Presensi manual riil petugas TU',
        ]);

        // 3. Jalankan seeder di hari D+1
        Carbon::setTestNow(Carbon::parse('2026-10-27 09:00:00'));
        $this->seed(MonthlySimulationUatSeeder::class);

        // 4. Pastikan data manual non-seeder TIDAK terhapus
        $this->assertDatabaseHas('journals', ['id' => $manualJournal->id]);
        $this->assertDatabaseHas('staff_attendances', ['id' => $manualAttendance->id]);

        Carbon::setTestNow();
    }

    /**
     * BATCH 5B - A.3 (c): Skala konsisten menskalakan guru, siswa, dan ortu secara proporsional.
     */
    public function test_batch5b_scale_consistently_scales_teachers_students_and_guardians(): void
    {
        (new MonthlySimulationUatSeeder)->run(scaleOverride: 2);

        // Scale 2: 100 Siswa, 100 Ortu, 20 Guru
        $this->assertEquals(100, Student::count());
        $this->assertEquals(20, Teacher::count());
        $this->assertEquals(100, DB::table('student_guardians')->count());

        // Attendances presisi 100 siswa x 20 hari KBM = 2.000 record
        $this->assertEquals(2000, Attendance::count());

        // Invoices presisi 100 siswa = 100 record
        $this->assertEquals(100, Invoice::count());
    }
}
