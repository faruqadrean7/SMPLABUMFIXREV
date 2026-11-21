<?php

namespace App\Services\Cbt;

use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\ExamRoom;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExamTokenRoomService
{
    /**
     * BE-CBT-03: Regenerasi token ujian untuk ruang & sesi tertentu.
     */
    public function regenerateToken(string $examRoomId, array $data, Request $request): ExamRoom
    {
        $examRoom = ExamRoom::with(['exam', 'room', 'proctor'])->findOrFail($examRoomId);
        $this->authorizeProctorOrAdmin($examRoom, $request->user());

        $validityMinutes = (int) ($data['validity_minutes'] ?? 30);
        $newToken = Str::upper(Str::random(6));
        $newExpiresAt = Carbon::now()->addMinutes($validityMinutes);

        $oldToken = $examRoom->token;
        $examRoom->token = $newToken;
        $examRoom->token_expires_at = $newExpiresAt;
        $examRoom->save();

        $this->logAudit(
            $request->user()?->id,
            'exam_room.regenerate_token',
            'exam_rooms',
            $examRoom->id,
            ['token' => $oldToken],
            ['token' => $newToken, 'expires_at' => $newExpiresAt->toIso8601String()],
            $request
        );

        return $examRoom->fresh(['exam', 'room', 'proctor']);
    }

    /**
     * BE-CBT-03: Cek status token ruang ujian saat ini (aktif vs kedaluwarsa).
     */
    public function getTokenStatus(string $examRoomId, Request $request): array
    {
        $examRoom = ExamRoom::with(['exam', 'room', 'proctor'])->findOrFail($examRoomId);
        $this->authorizeProctorOrAdmin($examRoom, $request->user());

        $now = Carbon::now();
        $isExpired = false;
        $remainingSeconds = 0;

        if (! $examRoom->token) {
            $status = 'no_token';
        } elseif ($examRoom->token_expires_at && $now->greaterThan($examRoom->token_expires_at)) {
            $isExpired = true;
            $status = 'expired';
        } else {
            $status = 'active';
            if ($examRoom->token_expires_at) {
                $remainingSeconds = max(0, $now->diffInSeconds($examRoom->token_expires_at, false));
            }
        }

        return [
            'exam_room_id' => $examRoom->id,
            'exam_id' => $examRoom->exam_id,
            'exam_title' => $examRoom->exam?->title,
            'room_name' => $examRoom->room?->name,
            'session_no' => $examRoom->session_no,
            'token' => $examRoom->token,
            'status' => $status,
            'is_expired' => $isExpired,
            'expires_at' => $examRoom->token_expires_at?->toIso8601String(),
            'remaining_seconds' => $remainingSeconds,
            'proctor_name' => $examRoom->proctor?->teacher?->full_name ?? $examRoom->proctor?->name,
        ];
    }

    /**
     * BE-CBT-03: Auto-distribusi peserta ujian ke dalam ruang-ruang ujian.
     */
    public function distributeParticipants(string $examId, array $data, Request $request): array
    {
        $exam = Exam::with(['rooms.room', 'participants'])->findOrFail($examId);

        $examRoomQuery = ExamRoom::with('room')->where('exam_id', $exam->id);
        if (! empty($data['exam_room_ids'])) {
            $examRoomQuery->whereIn('id', $data['exam_room_ids']);
        }
        $examRooms = $examRoomQuery->orderBy('session_no')->get();

        if ($examRooms->isEmpty()) {
            throw new HttpException(422, 'Tidak ada ruang ujian yang tersedia untuk didistribusikan.');
        }

        $participants = ExamParticipant::where('exam_id', $exam->id)
            ->orderBy('id')
            ->get();

        if ($participants->isEmpty()) {
            throw new HttpException(422, 'Tidak ada peserta ujian yang terdaftar untuk didistribusikan.');
        }

        $mode = $data['mode'] ?? 'balanced';
        $customCapacity = ! empty($data['custom_capacity']) ? (int) $data['custom_capacity'] : null;

        $roomCapacities = [];
        foreach ($examRooms as $er) {
            $roomCapacities[$er->id] = $customCapacity ?? ($er->room?->capacity ?: 30);
        }

        $allocations = [];
        foreach ($examRooms as $er) {
            $allocations[$er->id] = [];
        }

        if ($mode === 'fill_capacity') {
            $currentRoomIndex = 0;
            $currentRoom = $examRooms[$currentRoomIndex];
            $currentCapacity = $roomCapacities[$currentRoom->id];

            foreach ($participants as $participant) {
                while (count($allocations[$currentRoom->id]) >= $currentCapacity && $currentRoomIndex < count($examRooms) - 1) {
                    $currentRoomIndex++;
                    $currentRoom = $examRooms[$currentRoomIndex];
                    $currentCapacity = $roomCapacities[$currentRoom->id];
                }

                $allocations[$currentRoom->id][] = $participant->id;
            }
        } else {
            // balanced (round-robin atau distribusi merata)
            $roomCount = count($examRooms);
            $index = 0;
            foreach ($participants as $participant) {
                $targetRoom = $examRooms[$index % $roomCount];
                $allocations[$targetRoom->id][] = $participant->id;
                $index++;
            }
        }

        DB::transaction(function () use ($allocations) {
            foreach ($allocations as $roomId => $participantIds) {
                if (! empty($participantIds)) {
                    ExamParticipant::whereIn('id', $participantIds)
                        ->update(['exam_room_id' => $roomId]);
                }
            }
        });

        $this->logAudit(
            $request->user()?->id,
            'exam.distribute_participants',
            'exams',
            $exam->id,
            null,
            [
                'total_participants' => $participants->count(),
                'room_count' => count($examRooms),
                'mode' => $mode,
            ],
            $request
        );

        $summary = [];
        foreach ($examRooms as $er) {
            $assignedCount = count($allocations[$er->id]);
            $summary[] = [
                'exam_room_id' => $er->id,
                'room_name' => $er->room?->name,
                'session_no' => $er->session_no,
                'capacity' => $roomCapacities[$er->id],
                'allocated_count' => $assignedCount,
            ];
        }

        return [
            'exam_id' => $exam->id,
            'total_participants' => $participants->count(),
            'distributed_count' => $participants->count(),
            'mode' => $mode,
            'rooms_summary' => $summary,
        ];
    }

    /**
     * BE-CBT-03: Daftar kartu ujian peserta CBT.
     */
    public function listParticipantCards(string $examId, array $filters): LengthAwarePaginator
    {
        $exam = Exam::findOrFail($examId);

        $query = ExamParticipant::with([
            'student.classes',
            'examRoom.room',
            'examRoom.proctor.teacher',
            'exam.subject',
        ])
            ->where('exam_id', $exam->id)
            ->orderBy('id', 'asc');

        if (! empty($filters['exam_room_id'])) {
            $query->where('exam_room_id', $filters['exam_room_id']);
        }

        if (! empty($filters['search'])) {
            $kw = '%'.strtolower($filters['search']).'%';
            $query->whereHas('student', function ($sq) use ($kw) {
                $sq->whereRaw('LOWER(full_name) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(nis) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(nisn) LIKE ?', [$kw]);
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * BE-CBT-03: Unduh PDF kartu ujian peserta.
     */
    public function downloadParticipantCardPdf(string $examId, string $participantId, Request $request): Response
    {
        $participant = ExamParticipant::with([
            'student.classes',
            'examRoom.room',
            'examRoom.proctor.teacher',
            'exam.subject',
        ])
            ->where('exam_id', $examId)
            ->where('id', $participantId)
            ->firstOrFail();

        $user = $request->user();
        $isOwner = $user && $participant->student && $participant->student->user_id === $user->id;
        $isPrivileged = $user && ($user->hasRole(['admin', 'super_admin']) || $user->hasPermission('cbt.exam.manage'));

        if (! $isOwner && ! $isPrivileged) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki hak akses mengunduh kartu ujian peserta ini.');
        }

        $pdfBinary = $this->buildCardPdfContent($participant);

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="kartu_ujian_'.$participant->student?->nis.'.pdf"',
            'Cache-Control' => 'no-cache, private',
        ]);
    }

    /**
     * Otorisasi Pengawas Ruang atau Admin.
     */
    protected function authorizeProctorOrAdmin(ExamRoom $examRoom, ?User $user): void
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

        // Pengawas yang ditugaskan di ruang tersebut
        if ($examRoom->proctor_id && $examRoom->proctor_id === $user->id) {
            return;
        }

        throw new HttpException(403, 'Anda tidak memiliki hak akses untuk mengelola token ruang ujian ini.');
    }

    /**
     * Buat stream file PDF standar %PDF-1.4 untuk Kartu Peserta Ujian.
     */
    protected function buildCardPdfContent(ExamParticipant $participant): string
    {
        $student = $participant->student;
        $exam = $participant->exam;
        $room = $participant->examRoom;
        $qrString = "EXAM-CARD|{$exam->id}|{$student?->id}|{$participant->id}|{$room?->token}";

        $currentClass = $student?->classes?->first();
        $className = $currentClass?->name ?? '-';

        $lines = [
            'PORTAL SEKOLAH SATU PINTU SISTLABUM',
            '========================================================================',
            'KARTU PESERTA UJIAN BERBASIS KOMPUTER (CBT)',
            '========================================================================',
            '',
            'IDENTITAS PESERTA:',
            '  Nama Peserta     : '.strtoupper($student?->full_name ?? '-'),
            '  NIS / NISN       : '.($student?->nis ?? '-').' / '.($student?->nisn ?? '-'),
            '  Kelas            : '.$className,
            '  Nomor Peserta    : '.($participant->id),
            '',
            'INFORMASI UJIAN:',
            '  Judul Ujian      : '.($exam?->title ?? '-'),
            '  Mata Pelajaran   : '.($exam?->subject?->name ?? '-'),
            '  Jenis Ujian      : '.strtoupper($exam?->exam_type ?? '-'),
            '  Ruang Ujian      : '.($room?->room?->name ?? 'Belum ditentukan'),
            '  Sesi Ujian       : Sesi '.($room?->session_no ?? 1),
            '  Kode Paket Soal  : '.($participant->package_code ?? 'DEFAULT'),
            '  Durasi Pengerjaan: '.($exam?->duration_min ?? 90).' Menit',
            '------------------------------------------------------------------------',
            'KODE VERIFIKASI / QR: '.$qrString,
            'TOKEN RUANG UJIAN   : '.($room?->token ?? 'BELUM DI-GENERATE'),
            'STATUS TOKEN        : '.($room?->token_expires_at ? 'Berlaku s/d '.$room->token_expires_at->format('d/m/Y H:i') : '-'),
            '========================================================================',
            'Catatan:',
            '1. Harap membawa kartu ini saat pelaksanaan ujian CBT.',
            '2. Token ruang ujian hanya dapat diaktifkan oleh pengawas ruang resmi.',
            '========================================================================',
        ];

        $textStream = "BT\n/F1 10 Tf\n50 780 Td\n15 TL\n";
        foreach ($lines as $line) {
            $sanitized = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            $textStream .= "({$sanitized}) '\n";
        }
        $textStream .= "ET\n";

        $streamLen = strlen($textStream);

        $objects = [];
        $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";
        $objects[] = "4 0 obj\n<< /Length ".$streamLen." >>\nstream\n".$textStream."endstream\nendobj\n";
        $objects[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>\nendobj\n";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= $obj;
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n".$xrefOffset."\n%%EOF\n";

        return $pdf;
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
