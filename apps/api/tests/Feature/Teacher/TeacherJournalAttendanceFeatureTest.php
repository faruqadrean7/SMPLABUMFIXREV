<?php

namespace Tests\Feature\Teacher;

use App\Models\Journal;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\TeacherDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherJournalAttendanceFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $userTeacherMtk;

    protected User $userOtherTeacher;

    protected SchoolClass $class7A;

    protected Subject $subjectMtk;

    protected Schedule $schedule7A;

    /** @var \Illuminate\Database\Eloquent\Collection<int, Student> */
    protected $students;

    protected function setUp(): void
    {
        parent::setUp();

        (new TeacherDemoSeeder)->run();

        $this->userTeacherMtk = User::where('username', 'guru.mtk.staging')->firstOrFail();

        // Guru lain tanpa jadwal / assignment di kelas 7-A
        $this->userOtherTeacher = User::create([
            'username' => 'guru.lain.journal.test',
            'email' => 'guru.lain.journal@smp-labum.sch.id',
            'password_hash' => bcrypt('Secret123!'),
            'status' => 'active',
        ]);
        $roleTeacher = \App\Models\Role::where('code', 'teacher')->firstOrFail();
        $this->userOtherTeacher->roles()->attach($roleTeacher->id);

        \App\Models\Teacher::create([
            'user_id' => $this->userOtherTeacher->id,
            'nip' => '199606062021011006',
            'nuptk' => '6666777788889999',
            'full_name' => 'Guru Lain Tanpa Jadwal, S.Pd.',
            'status' => 'aktif',
        ]);

        $this->class7A = SchoolClass::where('name', '7-A')->firstOrFail();
        $this->subjectMtk = Subject::where('name', 'Matematika')->firstOrFail();
        $this->schedule7A = Schedule::where('teacher_id', $this->userTeacherMtk->teacher->id)
            ->where('class_id', $this->class7A->id)
            ->firstOrFail();

        $this->students = $this->class7A->students()->take(5)->get();
    }

    /**
     * AC1: Setelah menyimpan jurnal dengan N siswa Hadir, GET /me/teacher/journals
     * mengembalikan attendance_summary.hadir === N dan attendance_rate benar.
     */
    public function test_fix_guru_01_saving_journal_with_attendances_returns_accurate_summary_and_rate(): void
    {
        $sessionDate = now()->toDateString();
        $attendances = [
            ['student_id' => $this->students[0]->id, 'status' => 'hadir', 'note' => null],
            ['student_id' => $this->students[1]->id, 'status' => 'hadir', 'note' => null],
            ['student_id' => $this->students[2]->id, 'status' => 'hadir', 'note' => null],
            ['student_id' => $this->students[3]->id, 'status' => 'sakit', 'note' => 'Demam flu'],
            ['student_id' => $this->students[4]->id, 'status' => 'izin', 'note' => 'Acara keluarga'],
        ];

        $postResponse = $this->actingAs($this->userTeacherMtk, 'sanctum')
            ->postJson('/api/v1/me/teacher/journals', [
                'schedule_id' => $this->schedule7A->id,
                'class_id' => $this->class7A->id,
                'subject_id' => $this->subjectMtk->id,
                'session_date' => $sessionDate,
                'topic' => 'Sistem Persamaan Linier Dua Variabel',
                'method' => 'Problem-Based Learning',
                'notes' => 'Siswa berdiskusi aktif dalam kelompok.',
                'attendances' => $attendances,
            ]);

        $postResponse->assertStatus(201);
        $postData = $postResponse->json('data');

        $this->assertSame(5, $postData['attendance_summary']['total']);
        $this->assertSame(3, $postData['attendance_summary']['hadir']);
        $this->assertSame(1, $postData['attendance_summary']['sakit']);
        $this->assertSame(1, $postData['attendance_summary']['izin']);
        $this->assertSame(0, $postData['attendance_summary']['alpa']);
        $this->assertSame(60, $postData['attendance_summary']['attendance_rate']); // (3/5) * 100 = 60%
        $this->assertCount(2, $postData['studentAbsentees']);

        // GET /me/teacher/journals harus mengembalikan data yang sama secara persist
        $getResponse = $this->actingAs($this->userTeacherMtk, 'sanctum')
            ->getJson('/api/v1/me/teacher/journals');

        $getResponse->assertOk();
        $journals = $getResponse->json('data');
        $this->assertNotEmpty($journals);

        $savedJournal = collect($journals)->firstWhere('topic', 'Sistem Persamaan Linier Dua Variabel');
        $this->assertNotNull($savedJournal);
        $this->assertSame(3, $savedJournal['attendance_summary']['hadir']);
        $this->assertSame(1, $savedJournal['attendance_summary']['sakit']);
        $this->assertSame(1, $savedJournal['attendance_summary']['izin']);
        $this->assertSame(60, $savedJournal['attendance_summary']['attendance_rate']);
    }

    /**
     * AC3: Alur simpan tidak gagal/diam ketika schedule_id tidak dikirim sebagai UUID;
     * data presensi tetap tersimpan dan terbaca (fallback via class_id & subject_id atau active schedule).
     */
    public function test_fix_guru_01_saving_journal_with_non_uuid_schedule_resolves_and_persists(): void
    {
        $sessionDate = now()->addDay()->toDateString();
        $attendances = [
            ['student_id' => $this->students[0]->id, 'status' => 'hadir'],
            ['student_id' => $this->students[1]->id, 'status' => 'hadir'],
        ];

        $postResponse = $this->actingAs($this->userTeacherMtk, 'sanctum')
            ->postJson('/api/v1/me/teacher/journals', [
                'schedule_id' => 'sch-offline-dummy-123', // Bukan format UUID
                'class_id' => $this->class7A->id,
                'subject_id' => $this->subjectMtk->id,
                'session_date' => $sessionDate,
                'topic' => 'Kuis Mandiri Matematika Aljabar',
                'method' => 'Penugasan Mandiri',
                'attendances' => $attendances,
            ]);

        $postResponse->assertStatus(201);
        $postData = $postResponse->json('data');

        $this->assertSame(2, $postData['attendance_summary']['total']);
        $this->assertSame(2, $postData['attendance_summary']['hadir']);
        $this->assertSame(100, $postData['attendance_summary']['attendance_rate']);

        // Pastikan tersimpan di DB
        $journal = Journal::where('topic', 'Kuis Mandiri Matematika Aljabar')->first();
        $this->assertNotNull($journal);
        $this->assertEquals(2, $journal->attendances()->count());
    }

    /**
     * AC4: Batas - Jurnal tanpa sesi presensi mengembalikan summary 0 yang eksplisit (bukan error / bukan 500).
     */
    public function test_fix_guru_01_journal_without_attendances_returns_explicit_zero_summary(): void
    {
        // Buat jurnal manual tanpa attendance
        $journal = Journal::create([
            'teacher_id' => $this->userTeacherMtk->teacher->id,
            'schedule_id' => $this->schedule7A->id,
            'session_date' => now()->subDays(5)->toDateString(),
            'topic' => 'Sesi Pengayaan Khusus Olimpiade',
            'method' => 'Tatap Muka',
            'notes' => 'Tanpa absensi reguler',
        ]);

        $getResponse = $this->actingAs($this->userTeacherMtk, 'sanctum')
            ->getJson('/api/v1/me/teacher/journals');

        $getResponse->assertOk();
        $journals = $getResponse->json('data');

        $found = collect($journals)->firstWhere('id', $journal->id);
        $this->assertNotNull($found);
        $this->assertSame(0, $found['attendance_summary']['total']);
        $this->assertSame(0, $found['attendance_summary']['hadir']);
        $this->assertSame(0, $found['attendance_summary']['sakit']);
        $this->assertSame(0, $found['attendance_summary']['izin']);
        $this->assertSame(0, $found['attendance_summary']['alpa']);
        $this->assertSame(0, $found['attendance_summary']['terlambat']);
        $this->assertSame(0, $found['attendance_summary']['attendance_rate']);
        $this->assertSame([], $found['studentAbsentees']);
    }

    /**
     * AC4: 403 Forbidden - Guru tidak dapat membuat jurnal pada jadwal guru lain (anti-IDOR),
     * dan guru hanya dapat membaca jurnal miliknya sendiri.
     */
    public function test_fix_guru_01_teacher_cannot_store_journal_for_another_teachers_schedule(): void
    {
        // Guru lain mencoba membuat jurnal untuk jadwal milik guru MTK
        $response = $this->actingAs($this->userOtherTeacher, 'sanctum')
            ->postJson('/api/v1/me/teacher/journals', [
                'schedule_id' => $this->schedule7A->id,
                'session_date' => now()->toDateString(),
                'topic' => 'Jurnal Ilegal Mengambil Alih Jadwal',
                'method' => 'Tatap Muka',
            ]);

        $response->assertStatus(403);

        // Guru lain hanya melihat jurnal miliknya sendiri (0 jurnal)
        $getResponse = $this->actingAs($this->userOtherTeacher, 'sanctum')
            ->getJson('/api/v1/me/teacher/journals');

        $getResponse->assertOk();
        $this->assertEmpty($getResponse->json('data'));
    }

    /**
     * AC4: 401 Unauthenticated saat request tanpa autentikasi.
     */
    public function test_fix_guru_01_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/me/teacher/journals')->assertStatus(401);
        $this->postJson('/api/v1/me/teacher/journals', [])->assertStatus(401);
    }
}
