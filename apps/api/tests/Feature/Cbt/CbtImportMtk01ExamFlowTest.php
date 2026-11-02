<?php

namespace Tests\Feature\Cbt;

use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\ExamQuestion;
use App\Models\ExamRoom;
use App\Models\ExamSession;
use App\Models\GradeLevel;
use App\Models\Question;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\Cbt\ExamSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * BE-CBT-IMPORT-MTK-01 (demo-ready): bukti ujian hasil impor bisa dikerjakan end-to-end dan dinilai
 * otomatis, dengan kunci jawaban TIDAK bocor ke siswa. Menyiapkan jadwal + ruang + peserta langsung
 * (command cbt:demo-schedule-exam melakukan hal yang sama lewat kelas/akun demo nyata).
 */
class CbtImportMtk01ExamFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $schoolId = '00000000-0000-0000-0000-000000000001';

    private string $token = 'DEMO01';

    protected function setUp(): void
    {
        parent::setUp();

        Subject::firstOrCreate(
            ['code' => 'MTK', 'school_id' => $this->schoolId],
            ['name' => 'Matematika', 'subject_group' => 'wajib']
        );
        GradeLevel::firstOrCreate(
            ['name' => 'Kelas IX', 'school_id' => $this->schoolId],
            ['sort_order' => 9]
        );
        foreach (['guru.mtk@labschool.sch.id', 'wakakurikulum@labschool.sch.id'] as $email) {
            User::firstOrCreate(['email' => $email], [
                'username' => explode('@', $email)[0],
                'password_hash' => Hash::make('Password123!'),
                'status' => 'active',
                'must_change_password' => false,
                'school_id' => $this->schoolId,
            ]);
        }

        Artisan::call('cbt:import-package', ['manifest' => 'import/mtk-pas-9-genap-2526.json', '--fresh' => true]);
    }

    public function test_imported_exam_can_be_taken_end_to_end_and_autograded_without_key_leak(): void
    {
        $exam = Exam::where('title', 'PAS Matematika Kelas 9 — Genap 2025/2026')->firstOrFail();

        // --- Jadwalkan agar takeable sekarang ---
        $exam->update([
            'start_at' => now()->subMinutes(5),
            'end_at' => now()->addDays(7),
            'status' => 'berlangsung',
        ]);

        $room = Room::firstOrCreate(
            ['code' => 'R-DEMO-9A', 'school_id' => $this->schoolId],
            ['name' => 'Ruang Demo 9A', 'room_type' => 'kelas', 'capacity' => 30]
        );
        $examRoom = ExamRoom::create([
            'exam_id' => $exam->id,
            'room_id' => $room->id,
            'session_no' => 1,
            'token' => $this->token,
            'token_expires_at' => now()->addDays(7),
        ]);

        $student = $this->makeStudent();
        ExamParticipant::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'exam_room_id' => $examRoom->id,
        ]);

        $svc = app(ExamSessionService::class);
        $req = $this->requestAs($student->user_id);

        // 1) Mulai sesi dengan token
        $start = $svc->startOrResumeSession($exam->id, ['token' => $this->token], $req);
        $this->assertSame('mengerjakan', $start['status']);

        // 2) Soal yang diterima siswa: 26 butir, TANPA kunci
        $payload = $svc->getSessionQuestions($exam->id, $req);
        $this->assertCount(26, $payload['questions']);
        $json = json_encode($payload);
        $this->assertStringNotContainsString('is_correct', $json, 'Kunci is_correct bocor ke siswa!');
        $this->assertStringNotContainsString('match_key', $json, 'match_key bocor ke siswa!');

        // 3) Jawab semua benar (kunci diambil server-side, bukan dari payload siswa)
        $questions = Question::whereIn('id', ExamQuestion::where('exam_id', $exam->id)->pluck('question_id'))
            ->with('options')->get();
        foreach ($questions as $q) {
            $svc->saveAnswer($exam->id, ['question_id' => $q->id, 'answer' => $this->correctAnswerFor($q)], $req);
        }

        // 4) Submit -> penilaian otomatis
        $finish = $svc->finishSession($exam->id, $req);
        $this->assertSame('selesai', $finish['status']);

        $session = ExamSession::where('exam_id', $exam->id)->where('student_id', $student->id)->firstOrFail();
        $this->assertEqualsWithDelta(100.0, (float) $session->score, 0.01, 'Semua benar harus 100.');
        $this->assertGreaterThanOrEqual((float) $exam->passing_grade, (float) $session->score, 'Harus lulus KKM.');
    }

    private function correctAnswerFor(Question $q): mixed
    {
        if ($q->question_type === 'menjodohkan') {
            $pairs = [];
            foreach ($q->options as $opt) {
                $pairs[$opt->label] = $opt->match_key;
            }

            return ['pairs' => $pairs];
        }

        return $q->options->firstWhere('is_correct', true)?->id;
    }

    private function makeStudent(): Student
    {
        $user = User::create([
            'email' => 'siswa.flow.mtk@labschool.sch.id',
            'username' => 'siswa.flow.mtk',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
            'must_change_password' => false,
            'school_id' => $this->schoolId,
        ]);

        return Student::create([
            'user_id' => $user->id,
            'school_id' => $this->schoolId,
            'nis' => '99003',
            'nisn' => '0099009903',
            'nik_enc' => '3578010101990003',
            'full_name' => 'Siswa Uji Flow MTK',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-01-01',
            'religion' => 'Islam',
            'address' => 'Jl. Uji Flow No. 1',
            'status' => 'aktif',
            'entry_date' => '2026-07-01',
        ]);
    }

    private function requestAs(string $userId): Request
    {
        $user = User::findOrFail($userId);
        $req = Request::create('/cli/take', 'POST');
        $req->setUserResolver(fn () => $user);

        return $req;
    }
}
