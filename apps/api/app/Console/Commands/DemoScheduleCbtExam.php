<?php

namespace App\Console\Commands;

use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\ExamRoom;
use App\Models\Room;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ExamService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Throwable;

/**
 * BE-CBT-IMPORT-MTK-01 (demo): Jadwalkan ujian CBT yang sudah diimpor agar SIAP DICOBA end-to-end.
 *
 * Mengatur jendela waktu aktif + ruang + token + peserta (satu kelas) menggunakan ExamService yang
 * sudah ada, sehingga siswa dapat login, mengerjakan, submit, dan dinilai otomatis. Idempoten.
 * Hanya memakai data master & akun demo yang sudah ada (tidak membuat data pribadi baru).
 */
class DemoScheduleCbtExam extends Command
{
    protected $signature = 'cbt:demo-schedule-exam
        {--exam-title=PAS Matematika Kelas 9 — Genap 2025/2026 : Judul ujian yang sudah diimpor}
        {--class=Kelas 9A : Nama kelas yang didaftarkan sebagai peserta}
        {--room= : Kode ruang (opsional; default ruang pertama sekolah)}
        {--token=MTK9DEMO : Token ruang ujian untuk demo}
        {--status=berlangsung : Status ujian (dijadwalkan|berlangsung)}
        {--window-days=7 : Lama jendela ujian dibuka (hari)}';

    protected $description = 'Jadwalkan ujian CBT hasil impor agar siap dicoba demo (jadwal + ruang + token + peserta).';

    public function handle(ExamService $examService): int
    {
        try {
            $exam = Exam::where('title', $this->option('exam-title'))->first();
            if (! $exam) {
                $this->error('Ujian tidak ditemukan: '.$this->option('exam-title'));

                return self::FAILURE;
            }

            $actor = User::find($exam->created_by);
            if (! $actor) {
                $this->error('Pembuat ujian (created_by) tidak ditemukan.');

                return self::FAILURE;
            }
            $req = $this->requestAs($actor);
            $schoolId = $exam->school_id ?? $actor->school_id;

            $class = SchoolClass::where('school_id', $schoolId)
                ->where('name', $this->option('class'))->first();
            if (! $class) {
                $this->error('Kelas tidak ditemukan: '.$this->option('class'));

                return self::FAILURE;
            }

            $room = $this->option('room')
                ? Room::where('school_id', $schoolId)->where('code', $this->option('room'))->first()
                : Room::where('school_id', $schoolId)->orderBy('code')->first();
            if (! $room) {
                $this->error('Ruang tidak ditemukan.');

                return self::FAILURE;
            }

            $status = $this->option('status');
            $token = strtoupper(trim($this->option('token')));
            $startAt = now()->subMinutes(5);
            $endAt = now()->addDays((int) $this->option('window-days'));

            // 1) Jendela waktu + status aktif
            $examService->updateExam($exam->id, [
                'start_at' => $startAt->toDateTimeString(),
                'end_at' => $endAt->toDateTimeString(),
                'status' => $status,
            ], $req);

            // 2) Ruang + token (idempoten: pakai ruang ujian yang ada bila sudah ada)
            $examRoom = ExamRoom::where('exam_id', $exam->id)->first();
            if (! $examRoom) {
                $assigned = $examService->assignRooms($exam->id, [
                    'rooms' => [[
                        'room_id' => $room->id,
                        'token' => $token,
                        'token_expires_at' => $endAt->toDateTimeString(),
                    ]],
                ], $req);
                $examRoom = $assigned[0];
            } else {
                $examRoom->update(['token' => $token, 'token_expires_at' => $endAt->toDateTimeString()]);
            }

            // 3) Peserta: satu kelas
            $enrolled = $examService->enrollParticipants($exam->id, [
                'exam_room_id' => $examRoom->id,
                'class_id' => $class->id,
            ], $req);

            $participantCount = ExamParticipant::where('exam_id', $exam->id)->count();

            $this->newLine();
            $this->info('Ujian siap dicoba (demo).');
            $this->line('  exam_id      : '.$exam->id);
            $this->line('  judul        : '.$exam->title);
            $this->line('  status       : '.$status);
            $this->line('  jendela       : '.$startAt->toDateTimeString().'  s/d  '.$endAt->toDateTimeString());
            $this->line('  ruang        : '.($examRoom->room?->name ?? $room->name));
            $this->line('  TOKEN        : '.$token);
            $this->line('  kelas peserta: '.$class->name.' ('.count($enrolled).' baru / '.$participantCount.' total)');
            $this->newLine();
            $this->warn('  Catatan: soal bergambar masih placeholder (media_pending) sampai gambar diunggah.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Gagal menjadwalkan demo: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function requestAs(User $user): Request
    {
        $req = Request::create('/cli/cbt-demo-schedule', 'POST');
        $req->setUserResolver(fn () => $user);

        return $req;
    }
}
