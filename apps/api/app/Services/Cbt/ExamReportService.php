<?php

namespace App\Services\Cbt;

use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\ExamProctorAction;
use App\Models\ExamRoom;
use App\Models\ExamSession;
use App\Models\ExamViolation;
use App\Models\SiteSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExamReportService
{
    /**
     * BE-CBT-10: Ambil Rekapitulasi Lengkap Pelaksanaan & Hasil Ujian CBT.
     * GET /api/v1/exams/{id}/report
     */
    public function getExamReport(string $examId, array $filters, Request $request): array
    {
        $exam = Exam::with(['subject', 'rooms.room', 'rooms.proctor.teacher', 'participants.student'])->findOrFail($examId);
        $this->authorizeProctorOrAdmin($exam, $request->user());

        $roomFilter = $filters['room_id'] ?? null;

        // Peserta terdaftar
        $participantsQuery = ExamParticipant::with(['student', 'examRoom.room', 'examRoom.proctor.teacher'])
            ->where('exam_id', $examId);

        if ($roomFilter) {
            $participantsQuery->where('exam_room_id', $roomFilter);
        }

        $participants = $participantsQuery->get();
        $studentIds = $participants->pluck('student_id')->all();

        // Sesi ujian
        $sessions = ExamSession::with(['answers', 'violations'])
            ->where('exam_id', $examId)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        $presentCount = 0;
        $absentCount = 0;
        $scores = [];
        $passCount = 0;
        $failCount = 0;
        $passingGrade = (float) $exam->passing_grade;

        $participantReports = [];
        foreach ($participants as $p) {
            $session = $sessions->get($p->student_id);
            $isPresent = $session && in_array($session->status, ['mengerjakan', 'selesai', 'terkunci']);

            if ($isPresent) {
                $presentCount++;
                if ($session->score !== null) {
                    $scoreVal = (float) $session->score;
                    $scores[] = $scoreVal;
                    if ($scoreVal >= $passingGrade) {
                        $passCount++;
                    } else {
                        $failCount++;
                    }
                }
            } else {
                $absentCount++;
            }

            $participantReports[] = [
                'student_id' => $p->student_id,
                'student_name' => $p->student?->full_name,
                'student_nis' => $p->student?->nis,
                'room_id' => $p->exam_room_id,
                'room_name' => $p->examRoom?->room?->name,
                'attendance_status' => $isPresent ? 'hadir' : 'tidak_hadir',
                'session_status' => $session?->status ?? 'belum',
                'score' => $session?->score,
                'passed' => $session && $session->score !== null ? ((float) $session->score >= $passingGrade) : null,
                'violation_count' => $session ? $session->violations->count() : 0,
            ];
        }

        // Statistik nilai
        $highestScore = ! empty($scores) ? max($scores) : 0.0;
        $lowestScore = ! empty($scores) ? min($scores) : 0.0;
        $averageScore = ! empty($scores) ? round(array_sum($scores) / count($scores), 2) : 0.0;
        $passRate = ($presentCount > 0 && ! empty($scores)) ? round(($passCount / count($scores)) * 100, 1) : 0.0;

        // Rekap per ruang
        $roomsRecap = [];
        $roomsQuery = $exam->rooms;
        if ($roomFilter) {
            $roomsQuery = $roomsQuery->where('id', $roomFilter);
        }

        foreach ($roomsQuery as $room) {
            $roomParticipants = $participants->where('exam_room_id', $room->id);
            $roomStudentIds = $roomParticipants->pluck('student_id')->all();
            $roomPresent = $sessions->whereIn('student_id', $roomStudentIds)->whereIn('status', ['mengerjakan', 'selesai', 'terkunci'])->count();
            $roomAbsent = $roomParticipants->count() - $roomPresent;

            $proctorName = $room->proctor?->teacher?->full_name ?? $room->proctor?->name ?? $room->proctor?->username ?? 'Pengawas Ruang';

            $roomsRecap[] = [
                'exam_room_id' => $room->id,
                'room_name' => $room->room?->name,
                'session_no' => $room->session_no,
                'proctor_name' => $proctorName,
                'total_allocated' => $roomParticipants->count(),
                'present_count' => $roomPresent,
                'absent_count' => $roomAbsent,
            ];
        }

        // Rekap pelanggaran
        $violationsQuery = ExamViolation::whereHas('session', function ($q) use ($examId, $studentIds) {
            $q->where('exam_id', $examId)->whereIn('student_id', $studentIds);
        });
        $violationsGrouped = $violationsQuery->selectRaw('violation_type, count(*) as count')
            ->groupBy('violation_type')
            ->pluck('count', 'violation_type')
            ->all();

        // Aksi pengawas
        $proctorActions = ExamProctorAction::with(['actor'])
            ->whereHas('session', function ($q) use ($examId, $studentIds) {
                $q->where('exam_id', $examId)->whereIn('student_id', $studentIds);
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($act) {
                return [
                    'id' => $act->id,
                    'session_id' => $act->session_id,
                    'actor_name' => $act->actor?->name,
                    'action' => $act->action,
                    'reason' => $act->reason,
                    'created_at' => $act->created_at?->toIso8601String(),
                ];
            });

        return [
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'subject_name' => $exam->subject?->name,
                'exam_type' => $exam->exam_type,
                'start_at' => $exam->start_at?->toIso8601String(),
                'end_at' => $exam->end_at?->toIso8601String(),
                'duration_min' => $exam->duration_min,
                'passing_grade' => $passingGrade,
                'status' => $exam->status,
            ],
            'attendance_summary' => [
                'total_allocated' => $participants->count(),
                'present_count' => $presentCount,
                'absent_count' => $absentCount,
                'attendance_percentage' => $participants->count() > 0 ? round(($presentCount / $participants->count()) * 100, 1) : 0,
            ],
            'score_statistics' => [
                'highest_score' => $highestScore,
                'lowest_score' => $lowestScore,
                'average_score' => $averageScore,
                'pass_count' => $passCount,
                'fail_count' => $failCount,
                'pass_rate_percentage' => $passRate,
            ],
            'rooms_recap' => $roomsRecap,
            'violations_summary' => $violationsGrouped,
            'proctor_actions' => $proctorActions,
            'participants' => $participantReports,
        ];
    }

    /**
     * BE-CBT-10: Cetak Dokumen Berita Acara Pelaksanaan Ujian (PDF/HTML Stream).
     * GET /api/v1/exams/{id}/minutes/pdf
     */
    public function generateMinutesDocument(string $examId, array $filters, Request $request): Response|array
    {
        $report = $this->getExamReport($examId, $filters, $request);

        if (($filters['format'] ?? null) === 'json') {
            return $report;
        }

        $exam = $report['exam'];
        $attendance = $report['attendance_summary'];
        $stats = $report['score_statistics'];
        $rooms = $report['rooms_recap'];
        $participants = $report['participants'];
        $violations = $report['violations_summary'];

        $absentStudents = array_filter($participants, fn ($p) => $p['attendance_status'] === 'tidak_hadir');

        $schoolNameSetting = SiteSetting::where('setting_key', 'school_name')->value('setting_value');
        $schoolName = is_array($schoolNameSetting) ? ($schoolNameSetting['value'] ?? 'SMA LAB UM MALANG') : ($schoolNameSetting ?? 'SMA LABORATORIUM UNIVERSITAS NEGERI MALANG');

        $schoolAddressSetting = SiteSetting::where('setting_key', 'school_address')->value('setting_value');
        $schoolAddress = is_array($schoolAddressSetting) ? ($schoolAddressSetting['value'] ?? 'Jl. Bromo No. 16') : ($schoolAddressSetting ?? 'Jl. Bromo No. 16, Kota Malang, Jawa Timur');

        $npsnSetting = SiteSetting::where('setting_key', 'school_npsn')->value('setting_value');
        $npsn = is_array($npsnSetting) ? ($npsnSetting['value'] ?? '20533814') : ($npsnSetting ?? '20533814');

        $examDate = Carbon::parse($exam['start_at'])->translatedFormat('l, d F Y');
        $printedDate = Carbon::now()->translatedFormat('d F Y');
        $baNumber = 'BA-CBT/'.Carbon::now()->format('Y/m').'/'.substr($exam['id'], 0, 8);

        $absentRowsHtml = '';
        if (! empty($absentStudents)) {
            $no = 1;
            foreach ($absentStudents as $abs) {
                $absentRowsHtml .= "<tr><td>{$no}</td><td>{$abs['student_nis']}</td><td>{$abs['student_name']}</td><td>{$abs['room_name']}</td><td>Tanpa Keterangan</td></tr>";
                $no++;
            }
        } else {
            $absentRowsHtml = '<tr><td colspan="5" style="text-align:center;">Seluruh peserta hadir lengkap (Nihil)</td></tr>';
        }

        $violationRowsHtml = '';
        if (! empty($violations)) {
            foreach ($violations as $type => $count) {
                $violationRowsHtml .= "<li>Jenis Kejadian: <strong>{$type}</strong> — Frekuensi: {$count} kali</li>";
            }
        } else {
            $violationRowsHtml = '<li>Tidak ada catatan pelanggaran khusus selama sesi ujian.</li>';
        }

        $proctorName = $rooms[0]['proctor_name'] ?? 'Pengawas Ruang Terdaftar';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Berita Acara Pelaksanaan Ujian - {$exam['title']}</title>
    <style>
        body { font-family: 'Times New Roman', serif; margin: 30px; font-size: 14px; color: #111; line-height: 1.5; }
        .header { text-align: center; border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 20px; }
        .header h2 { margin: 0; font-size: 18px; text-transform: uppercase; }
        .header p { margin: 2px 0; font-size: 13px; }
        .doc-title { text-align: center; margin: 20px 0; }
        .doc-title h3 { margin: 0; text-decoration: underline; text-transform: uppercase; font-size: 16px; }
        .doc-title p { margin: 4px 0; font-style: italic; }
        table.meta-table { width: 100%; margin-bottom: 15px; }
        table.meta-table td { padding: 3px 5px; }
        table.data-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        table.data-table th, table.data-table td { border: 1px solid #333; padding: 6px 8px; font-size: 13px; }
        table.data-table th { background: #f2f2f2; text-align: center; }
        .signatures { margin-top: 40px; width: 100%; }
        .signatures td { width: 50%; text-align: center; vertical-align: top; padding-top: 10px; }
        .sig-space { height: 70px; }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>{$schoolName}</h2>
        <p>NPSN: {$npsn} | Alamat: {$schoolAddress}</p>
        <p>PORTAL SISTEM INFORMASI AKADEMIK & CBT DIGITAL (SISTLABUM)</p>
    </div>

    <div class="doc-title">
        <h3>BERITA ACARA PELAKSANAAN UJIAN BERBASIS KOMPUTER</h3>
        <p>Nomor: {$baNumber}</p>
    </div>

    <p>Pada hari ini <strong>{$examDate}</strong>, telah diselenggarakan Ujian Berbasis Komputer (CBT) dengan rincian sebagai berikut:</p>

    <table class="meta-table">
        <tr><td style="width: 25%;"><strong>Mata Pelajaran</strong></td><td>: {$exam['subject_name']}</td></tr>
        <tr><td><strong>Judul Ujian</strong></td><td>: {$exam['title']} ({$exam['exam_type']})</td></tr>
        <tr><td><strong>Waktu Pelaksanaan</strong></td><td>: {$exam['duration_min']} Menit</td></tr>
        <tr><td><strong>Standar Kelulusan (KKTP)</strong></td><td>: {$exam['passing_grade']}</td></tr>
    </table>

    <h4>I. REKAPITULASI KEHADIRAN PESERTA</h4>
    <table class="meta-table">
        <tr><td style="width: 35%;">Jumlah Peserta Terdaftar</td><td>: <strong>{$attendance['total_allocated']}</strong> siswa</td></tr>
        <tr><td>Jumlah Peserta Hadir</td><td>: <strong>{$attendance['present_count']}</strong> siswa ({$attendance['attendance_percentage']}%)</td></tr>
        <tr><td>Jumlah Peserta Tidak Hadir</td><td>: <strong>{$attendance['absent_count']}</strong> siswa</td></tr>
    </table>

    <p><strong>Daftar Peserta Tidak Hadir:</strong></p>
    <table class="data-table">
        <thead>
            <tr><th style="width:5%;">No</th><th style="width:20%;">NIS</th><th>Nama Peserta</th><th style="width:25%;">Ruang</th><th>Keterangan</th></tr>
        </thead>
        <tbody>
            {$absentRowsHtml}
        </tbody>
    </table>

    <h4>II. CATATAN KHUSUS & REKAMAN PELANGGARAN</h4>
    <ul>
        {$violationRowsHtml}
    </ul>

    <p style="margin-top: 25px;">Demikian Berita Acara ini dibuat dengan sebenarnya sesuai dengan keadaan pelaksanaan ujian yang sesungguhnya untuk dapat dipergunakan sebagaimana mestinya.</p>

    <table class="signatures">
        <tr>
            <td>
                Mengetahui,<br>
                Kepala Sekolah
                <div class="sig-space"></div>
                <strong>( ________________________ )</strong><br>
                NIP. ........................................
            </td>
            <td>
                Malang, {$printedDate}<br>
                Pengawas / Proktor Ruang Ujian
                <div class="sig-space"></div>
                <strong>( {$proctorName} )</strong><br>
                Tanda Tangan & Nama Terang
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

        // Log audit cetak berita acara
        $this->logAudit(
            $request->user()?->id,
            'cbt.print_minutes',
            'exams',
            $examId,
            null,
            ['ba_number' => $baNumber, 'attendance_count' => $attendance['present_count']],
            $request
        );

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="berita-acara-cbt-'.$examId.'.html"',
            'X-Document-Type' => 'cbt-minutes-pdf',
        ]);
    }

    protected function authorizeProctorOrAdmin(Exam $exam, ?User $user): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
            return;
        }

        if ($user->hasPermission('cbt.exam.manage')) {
            return;
        }

        if ($exam->created_by === $user->id) {
            return;
        }

        $isProctor = ExamRoom::where('exam_id', $exam->id)
            ->where('proctor_id', $user->id)
            ->exists();

        if ($isProctor) {
            return;
        }

        throw new HttpException(403, 'Anda tidak memiliki hak akses untuk berita acara ujian ini.');
    }

    protected function logAudit(
        ?string $actorId,
        string $action,
        string $entity,
        ?string $entityId,
        ?array $oldValue,
        ?array $newValue,
        Request $request
    ): void {
        try {
            AuditLog::create([
                'actor_id' => $actorId,
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
