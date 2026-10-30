<?php

namespace Tests\Feature\Seeder;

use App\Models\Exam;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\QuestionBank;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Database\Seeders\TestingFullMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestingFullMasterSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TEST-SEED-01: Verifikasi eksekusi dan idempotensi TestingFullMasterSeeder.
     */
    public function test_seed_01_testing_full_master_seeder_executes_and_is_idempotent(): void
    {
        // Jalankan seeder pertama kali
        $this->seed(TestingFullMasterSeeder::class);

        // Verifikasi entitas utama terbuat (20 guru mapel + 1 konselor BK = 21 guru)
        $this->assertGreaterThanOrEqual(5, User::whereIn('username', ['superadmin', 'kepsek', 'bendahara', 'tatausaha', 'bk'])->count());
        $this->assertEquals(21, Teacher::count());
        $this->assertEquals(21, SchoolClass::count());
        $this->assertEquals(420, Student::count());
        $this->assertEquals(420, Guardian::count());
        $this->assertEquals(10, Subject::count());
        $this->assertEquals(3, GradeLevel::count());
        $this->assertGreaterThanOrEqual(1, QuestionBank::count());
        $this->assertGreaterThanOrEqual(6, Exam::count());

        // Verifikasi tidak ada bentrok pada jadwal yang dibuat
        $schedules = Schedule::all();
        $this->assertNotEmpty($schedules);

        $teacherSlotPairs = [];
        $roomSlotPairs = [];
        $classSlotPairs = [];

        foreach ($schedules as $s) {
            $tKey = "{$s->semester_id}_{$s->teacher_id}_{$s->day_of_week}_{$s->time_slot_id}";
            $this->assertArrayNotHasKey($tKey, $teacherSlotPairs, "Bentrok guru terdeteksi pada slot: {$tKey}");
            $teacherSlotPairs[$tKey] = true;

            $rKey = "{$s->semester_id}_{$s->room_id}_{$s->day_of_week}_{$s->time_slot_id}";
            $this->assertArrayNotHasKey($rKey, $roomSlotPairs, "Bentrok ruangan terdeteksi pada slot: {$rKey}");
            $roomSlotPairs[$rKey] = true;

            $cKey = "{$s->semester_id}_{$s->class_id}_{$s->day_of_week}_{$s->time_slot_id}";
            $this->assertArrayNotHasKey($cKey, $classSlotPairs, "Bentrok kelas terdeteksi pada slot: {$cKey}");
            $classSlotPairs[$cKey] = true;
        }

        // Verifikasi variasi nama unik (tidak ada duplikat nama antar siswa dan antar ortu)
        $this->assertEquals(420, Student::distinct('full_name')->count('full_name'), 'Semua siswa harus memiliki nama unik bervariasi.');
        $this->assertEquals(420, Guardian::distinct('full_name')->count('full_name'), 'Semua wali murid harus memiliki nama unik bervariasi.');
        $this->assertGreaterThanOrEqual(15, Guardian::distinct('occupation')->count('occupation'), 'Profesi orang tua harus bervariasi.');

        // Verifikasi nama orang tua tidak sama dengan nama anak
        $studentGuardians = DB::table('student_guardians')
            ->join('students', 'student_guardians.student_id', '=', 'students.id')
            ->join('guardians', 'student_guardians.guardian_id', '=', 'guardians.id')
            ->select('students.full_name as student_name', 'guardians.full_name as guardian_name')
            ->get();

        foreach ($studentGuardians as $pair) {
            $this->assertNotEquals(
                $pair->student_name,
                $pair->guardian_name,
                "Nama wali tidak boleh sama persis dengan nama anak: {$pair->student_name}"
            );
            $cleanGuardian = str_replace(['Bapak ', 'Ibu '], '', $pair->guardian_name);
            $this->assertNotEquals(
                $pair->student_name,
                $cleanGuardian,
                "Nama wali tidak boleh hanya copy nama anak: {$pair->student_name}"
            );
        }

        // Verifikasi penugasan mengajar unik (1 guru per mapel per kelas)
        $assignments = TeachingAssignment::all();
        $classSubjectPairs = [];
        foreach ($assignments as $ta) {
            $csKey = "{$ta->semester_id}_{$ta->class_id}_{$ta->subject_id}";
            $this->assertArrayNotHasKey($csKey, $classSubjectPairs, "Multiple guru per mapel terdeteksi: {$csKey}");
            $classSubjectPairs[$csKey] = $ta->teacher_id;
        }

        // Verifikasi idempotensi: jalankan kedua kali tanpa error unique constraint
        $this->seed(TestingFullMasterSeeder::class);

        // Jumlah tidak bertambah ganda
        $this->assertEquals(21, Teacher::count());
        $this->assertEquals(21, SchoolClass::count());
        $this->assertEquals(420, Student::count());
        $this->assertEquals(420, Guardian::count());
    }

    /**
     * TEST-SEED-02: Verifikasi file kredensial markdown terbuat.
     */
    public function test_seed_02_credentials_markdown_file_is_generated(): void
    {
        $this->seed(TestingFullMasterSeeder::class);

        $credFile = file_exists(base_path('../../00_README.md'))
            ? realpath(base_path('../..')).'/19_KREDENSIAL_AKUN_TESTING.md'
            : base_path('19_KREDENSIAL_AKUN_TESTING.md');
        $this->assertFileExists($credFile);

        $content = file_get_contents($credFile);
        $this->assertStringContainsString('Kredensial Akun Testing', $content);
        $this->assertStringContainsString('tatausaha', $content);
        $this->assertStringContainsString('guru01', $content);
        $this->assertStringContainsString('siswa001', $content);
        $this->assertStringContainsString('ortu001', $content);
    }
}
