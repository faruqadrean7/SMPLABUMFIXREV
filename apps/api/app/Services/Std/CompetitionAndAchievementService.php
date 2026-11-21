<?php

namespace App\Services\Std;

use App\Models\Achievement;
use App\Models\AuditLog;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\PointType;
use App\Models\Student;
use App\Models\StudentPoint;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CompetitionAndAchievementService
{
    // =========================================================================
    // 1. MASTER KOMPETISI / LOMBA (BE-STD-03)
    // =========================================================================

    public function listCompetitions(array $filters, ?User $user = null): LengthAwarePaginator
    {
        $query = Competition::with(['entries.student', 'achievements'])
            ->orderBy('held_at', 'desc');

        // Multi-tenant school isolation
        if ($user && ! empty($user->school_id)) {
            $query->where(function ($q) use ($user) {
                $q->where('school_id', $user->school_id)
                    ->orWhereNull('school_id');
            });
        }

        if (! empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('held_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('held_at', '<=', $filters['to_date']);
        }

        if (! empty($filters['search'])) {
            $kw = '%'.strtolower($filters['search']).'%';
            $query->where(function ($q) use ($kw) {
                $q->whereRaw('LOWER(name) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(organizer) LIKE ?', [$kw]);
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function getCompetition(string $id, ?User $user = null): Competition
    {
        $competition = Competition::with([
            'entries.student.classes',
            'entries.coach',
            'entries.achievement',
            'achievements.student.classes',
            'achievements.photoFile',
            'achievements.certificateFile',
            'achievements.verifiedBy',
        ])->findOrFail($id);

        if ($user && ! empty($user->school_id) && ! empty($competition->school_id) && $competition->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Kompetisi berada di luar sekolah penugasan Anda.');
        }

        return $competition;
    }

    public function createCompetition(array $data, Request $request): Competition
    {
        $user = $request->user();
        $this->authorizeTeacherOrStaff($user);

        // Validasi tenggat pendaftaran sebelum/pada tanggal lomba
        if (! empty($data['registration_deadline']) && ! empty($data['held_at'])) {
            if (Carbon::parse($data['registration_deadline'])->isAfter(Carbon::parse($data['held_at']))) {
                throw new HttpException(422, 'Batas pendaftaran tidak boleh melebihi tanggal pelaksanaan lomba.');
            }
        }

        $competition = Competition::create([
            'school_id' => $data['school_id'] ?? $user?->school_id ?? null,
            'name' => $data['name'],
            'organizer' => $data['organizer'] ?? null,
            'level' => $data['level'],
            'category' => $data['category'] ?? null,
            'held_at' => $data['held_at'],
            'registration_deadline' => $data['registration_deadline'] ?? null,
            'status' => $data['status'] ?? 'direncanakan',
            'description' => $data['description'] ?? null,
        ]);

        $this->logAudit($user?->id, 'competition.create', 'competitions', $competition->id, null, $competition->toArray(), $request);

        return $competition;
    }

    public function updateCompetition(string $id, array $data, Request $request): Competition
    {
        $user = $request->user();
        $this->authorizeTeacherOrStaff($user);

        $competition = Competition::findOrFail($id);

        if ($user && ! empty($user->school_id) && ! empty($competition->school_id) && $competition->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Kompetisi berada di luar sekolah penugasan Anda.');
        }

        if (! empty($data['registration_deadline']) && ! empty($data['held_at'])) {
            if (Carbon::parse($data['registration_deadline'])->isAfter(Carbon::parse($data['held_at']))) {
                throw new HttpException(422, 'Batas pendaftaran tidak boleh melebihi tanggal pelaksanaan lomba.');
            }
        }

        $oldValues = $competition->toArray();
        $competition->update($data);

        $this->logAudit($user?->id, 'competition.update', 'competitions', $competition->id, $oldValues, $competition->fresh()->toArray(), $request);

        return $competition->fresh();
    }

    public function deleteCompetition(string $id, Request $request): bool
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $competition = Competition::findOrFail($id);

        if ($user && ! empty($user->school_id) && ! empty($competition->school_id) && $competition->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Kompetisi berada di luar sekolah penugasan Anda.');
        }

        $oldValues = $competition->toArray();
        $competition->delete();

        $this->logAudit($user?->id, 'competition.delete', 'competitions', $id, $oldValues, null, $request);

        return true;
    }

    // =========================================================================
    // 2. DELEGASI PESERTA KOMPETISI (ENTRIES)
    // =========================================================================

    public function listEntries(string $competitionId, ?User $user = null): LengthAwarePaginator
    {
        $competition = Competition::findOrFail($competitionId);

        if ($user && ! empty($user->school_id) && ! empty($competition->school_id) && $competition->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Kompetisi berada di luar sekolah penugasan Anda.');
        }

        return CompetitionEntry::with(['student.classes', 'coach', 'achievement'])
            ->where('competition_id', $competitionId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
    }

    public function registerEntry(array $data, Request $request): CompetitionEntry
    {
        $user = $request->user();
        $this->authorizeTeacherOrStaff($user);

        $competition = Competition::findOrFail($data['competition_id']);
        $student = Student::findOrFail($data['student_id']);

        // Multi-tenant school isolation anti-IDOR
        if ($user && ! empty($user->school_id)) {
            if (! empty($student->school_id) && $student->school_id !== $user->school_id) {
                throw new HttpException(403, 'Akses ditolak: Siswa berada di luar sekolah penugasan Anda.');
            }
            if (! empty($competition->school_id) && $competition->school_id !== $user->school_id) {
                throw new HttpException(403, 'Akses ditolak: Kompetisi berada di luar sekolah penugasan Anda.');
            }
        }

        // Validasi siswa aktif
        if ($student->status !== 'active') {
            throw new HttpException(422, 'Hanya siswa berstatus aktif yang dapat didaftarkan sebagai delegasi lomba.');
        }

        // Validasi status lomba
        if (in_array($competition->status, ['selesai', 'dibatalkan'])) {
            throw new HttpException(422, 'Pendaftaran ditutup: Kompetisi telah selesai atau dibatalkan.');
        }

        // Cegah pendaftaran ganda siswa yang sama pada satu kompetisi
        $existing = CompetitionEntry::where('competition_id', $competition->id)
            ->where('student_id', $student->id)
            ->first();

        if ($existing) {
            throw new HttpException(422, 'Siswa ini sudah terdaftar sebagai delegasi pada kompetisi terkait.');
        }

        // Validasi guru pembimbing jika disertakan
        if (! empty($data['coach_teacher_id'])) {
            $coach = Teacher::findOrFail($data['coach_teacher_id']);
            if ($user && ! empty($user->school_id) && ! empty($coach->school_id) && $coach->school_id !== $user->school_id) {
                throw new HttpException(403, 'Akses ditolak: Guru pembimbing berada di luar sekolah penugasan Anda.');
            }
        }

        $entry = CompetitionEntry::create([
            'competition_id' => $competition->id,
            'student_id' => $student->id,
            'coach_teacher_id' => $data['coach_teacher_id'] ?? null,
            'status' => $data['status'] ?? 'terdaftar',
            'result' => $data['result'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->logAudit($user?->id, 'competition_entry.register', 'competition_entries', $entry->id, null, $entry->toArray(), $request);

        return $entry->fresh(['competition', 'student', 'coach']);
    }

    public function updateEntry(string $id, array $data, Request $request): CompetitionEntry
    {
        $user = $request->user();
        $this->authorizeTeacherOrStaff($user);

        $entry = CompetitionEntry::with(['competition', 'student'])->findOrFail($id);

        if ($user && ! empty($user->school_id)) {
            if (! empty($entry->student?->school_id) && $entry->student->school_id !== $user->school_id) {
                throw new HttpException(403, 'Akses ditolak: Siswa berada di luar sekolah penugasan Anda.');
            }
        }

        $oldValues = $entry->toArray();
        $entry->update($data);

        $this->logAudit($user?->id, 'competition_entry.update', 'competition_entries', $entry->id, $oldValues, $entry->fresh()->toArray(), $request);

        return $entry->fresh(['competition', 'student', 'coach', 'achievement']);
    }

    public function deleteEntry(string $id, Request $request): bool
    {
        $user = $request->user();
        $this->authorizeTeacherOrStaff($user);

        $entry = CompetitionEntry::with(['student'])->findOrFail($id);

        if ($user && ! empty($user->school_id)) {
            if (! empty($entry->student?->school_id) && $entry->student->school_id !== $user->school_id) {
                throw new HttpException(403, 'Akses ditolak: Siswa berada di luar sekolah penugasan Anda.');
            }
        }

        $oldValues = $entry->toArray();
        $entry->delete();

        $this->logAudit($user?->id, 'competition_entry.delete', 'competition_entries', $id, $oldValues, null, $request);

        return true;
    }

    // =========================================================================
    // 3. PRESTASI & GALERI PIALA (ACHIEVEMENTS)
    // =========================================================================

    public function listAchievements(array $filters, ?User $user = null): LengthAwarePaginator
    {
        $query = Achievement::with(['student.classes', 'competition', 'certificateFile', 'photoFile', 'verifiedBy'])
            ->orderBy('achieved_at', 'desc');

        // Multi-tenant school isolation
        if ($user && ! empty($user->school_id)) {
            $query->where(function ($q) use ($user) {
                $q->where('school_id', $user->school_id)
                    ->orWhereHas('student', fn ($sq) => $sq->where('school_id', $user->school_id));
            });
        }

        // Siswa scoping: dapat melihat galeri piala sekolah terverifikasi ATAU pengajuan milik sendiri
        if ($user && $this->isStudentUser($user)) {
            $studentId = $user->student?->id;
            if (! empty($filters['only_my']) || (! empty($filters['student_id']) && $filters['student_id'] === $studentId)) {
                $query->where('student_id', $studentId);
            } else {
                $query->where(function ($q) use ($studentId) {
                    $q->where('verification_status', 'verified');
                    if ($studentId) {
                        $q->orWhere('student_id', $studentId);
                    }
                });
            }
        } elseif ($user && $this->isGuardianUser($user)) {
            // Wali murid scoping: verifikasi relasi anak sah
            $guardianStudentIds = $user->guardian?->students()->pluck('students.id')->toArray() ?? [];
            if (! empty($filters['student_id'])) {
                if (! in_array($filters['student_id'], $guardianStudentIds, true)) {
                    throw new HttpException(403, 'Akses ditolak: Siswa ini bukan anak di bawah perwalian Anda.');
                }
                $query->where('student_id', $filters['student_id']);
            } elseif (! empty($filters['only_my'])) {
                $query->whereIn('student_id', $guardianStudentIds);
            } else {
                $query->where(function ($q) use ($guardianStudentIds) {
                    $q->where('verification_status', 'verified');
                    if (! empty($guardianStudentIds)) {
                        $q->orWhereIn('student_id', $guardianStudentIds);
                    }
                });
            }
        } elseif (! empty($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (! empty($filters['competition_id'])) {
            $query->where('competition_id', $filters['competition_id']);
        }

        if (! empty($filters['rank_level'])) {
            $query->where('rank_level', $filters['rank_level']);
        }

        if (! empty($filters['verification_status'])) {
            $query->where('verification_status', $filters['verification_status']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('achieved_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('achieved_at', '<=', $filters['to_date']);
        }

        if (! empty($filters['search'])) {
            $kw = '%'.strtolower($filters['search']).'%';
            $query->where(function ($q) use ($kw) {
                $q->whereRaw('LOWER(title) LIKE ?', [$kw])
                    ->orWhereRaw('LOWER(rank_level) LIKE ?', [$kw]);
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function getAchievement(string $id, ?User $user = null): Achievement
    {
        $achievement = Achievement::with(['student.classes', 'competition', 'certificateFile', 'photoFile', 'verifiedBy', 'studentPoint'])
            ->findOrFail($id);

        if ($user && ! empty($user->school_id)) {
            $schoolMatches = ($achievement->school_id === $user->school_id) || ($achievement->student?->school_id === $user->school_id);
            if (! $schoolMatches && ! empty($achievement->school_id)) {
                throw new HttpException(403, 'Akses ditolak: Prestasi berada di luar sekolah penugasan Anda.');
            }
        }

        // Anti-IDOR for student & guardian viewing non-verified achievements
        if ($achievement->verification_status !== 'verified') {
            if ($user && $this->isStudentUser($user)) {
                if ($achievement->student_id !== $user->student?->id) {
                    throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang melihat detail pengajuan prestasi siswa lain.');
                }
            } elseif ($user && $this->isGuardianUser($user)) {
                $isOwnChild = $user->guardian?->students()->where('students.id', $achievement->student_id)->exists();
                if (! $isOwnChild) {
                    throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang melihat pengajuan prestasi siswa di luar perwalian Anda.');
                }
            }
        }

        return $achievement;
    }

    public function createAchievement(array $data, Request $request): Achievement
    {
        $user = $request->user();
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        $student = Student::findOrFail($data['student_id']);

        // Multi-tenant school isolation anti-IDOR
        if (! empty($user->school_id) && ! empty($student->school_id) && $student->school_id !== $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Siswa berada di luar sekolah penugasan Anda.');
        }

        if (! empty($data['competition_id'])) {
            $competition = Competition::findOrFail($data['competition_id']);
            if (! empty($user->school_id) && ! empty($competition->school_id) && $competition->school_id !== $user->school_id) {
                throw new HttpException(403, 'Akses ditolak: Kompetisi berada di luar sekolah penugasan Anda.');
            }
        }

        // Scoping & Anti-IDOR:
        $isStudent = $this->isStudentUser($user);
        $isGuardian = $this->isGuardianUser($user);
        $isStaffOrAdmin = $this->isStaffOrAdmin($user);
        $isTeacher = $this->isTeacherOrStaff($user);

        if ($isStudent) {
            $ownStudentId = $user->student?->id;
            if (! $ownStudentId || $ownStudentId !== $student->id) {
                throw new HttpException(403, 'Akses ditolak: Anda hanya dapat mengajukan prestasi untuk diri Anda sendiri.');
            }
        } elseif ($isGuardian) {
            $isOwnChild = $user->guardian?->students()->where('students.id', $student->id)->exists();
            if (! $isOwnChild) {
                throw new HttpException(403, 'Akses ditolak: Anda hanya dapat mengajukan prestasi untuk anak yang terdaftar di bawah perwalian Anda.');
            }
        } elseif (! $isTeacher) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang mencatat data prestasi.');
        }

        // Cegah duplikasi pencatatan prestasi
        $duplicateQuery = Achievement::where('student_id', $student->id)
            ->where('title', $data['title'])
            ->where('rank_level', $data['rank_level'])
            ->whereDate('achieved_at', $data['achieved_at']);

        if (! empty($data['competition_id'])) {
            $duplicateQuery->where('competition_id', $data['competition_id']);
        }

        if ($duplicateQuery->exists()) {
            throw new HttpException(422, 'Prestasi serupa untuk siswa ini pada tanggal dan tingkatan yang sama sudah tercatat sebelumnya.');
        }

        // Tentukan status verifikasi dan hak pemberian poin
        // Siswa dan wali murid TIDAK BOLEH memverifikasi diri sendiri atau menentukan status verified
        if ($isStudent || $isGuardian) {
            $verificationStatus = 'pending';
            $verifiedBy = null;
            $verifiedAt = null;
            $pointsAwarded = (int) ($data['points_awarded'] ?? 0);
        } else {
            // Dicatat oleh guru / staf / admin
            $verificationStatus = $data['verification_status'] ?? 'verified';
            $verifiedBy = $verificationStatus === 'verified' ? $user->id : null;
            $verifiedAt = $verificationStatus === 'verified' ? Carbon::now() : null;
            $pointsAwarded = (int) ($data['points_awarded'] ?? 0);
        }

        $pointType = null;
        if ($verificationStatus === 'verified') {
            if (! empty($data['point_type_id'])) {
                $pointType = PointType::find($data['point_type_id']);
                if ($pointType) {
                    $pointsAwarded = $pointType->points;
                }
            }

            if (! $pointType) {
                $pointsAwarded = $pointsAwarded > 0 ? $pointsAwarded : 25;
                $pointType = PointType::firstOrCreate(
                    ['kind' => 'prestasi', 'name' => 'Prestasi: '.$data['rank_level']],
                    ['points' => $pointsAwarded, 'severity' => 'sedang', 'school_id' => $user?->school_id ?? $student->school_id]
                );
            }
        }

        return DB::transaction(function () use ($data, $user, $student, $pointType, $pointsAwarded, $verificationStatus, $verifiedBy, $verifiedAt, $request) {
            $studentPointId = null;

            // Jika status terverifikasi, catat poin ke student_points
            if ($verificationStatus === 'verified' && $pointType) {
                $studentPoint = StudentPoint::create([
                    'school_id' => $user?->school_id ?? $student->school_id,
                    'student_id' => $student->id,
                    'point_type_id' => $pointType->id,
                    'recorded_by' => $user->id,
                    'point_date' => $data['achieved_at'],
                    'evidence_file_id' => $data['certificate_file_id'] ?? $data['photo_file_id'] ?? null,
                    'notes' => "Pencatatan otomatis prestasi: {$data['title']} ({$data['rank_level']})",
                ]);
                $studentPointId = $studentPoint->id;
            }

            $achievement = Achievement::create([
                'school_id' => $data['school_id'] ?? $user?->school_id ?? $student->school_id,
                'student_id' => $student->id,
                'competition_id' => $data['competition_id'] ?? null,
                'title' => $data['title'],
                'rank_level' => $data['rank_level'],
                'achieved_at' => $data['achieved_at'],
                'certificate_file_id' => $data['certificate_file_id'] ?? null,
                'photo_file_id' => $data['photo_file_id'] ?? null,
                'points_awarded' => $pointsAwarded,
                'verification_status' => $verificationStatus,
                'verified_by' => $verifiedBy,
                'verified_at' => $verifiedAt,
                'student_point_id' => $studentPointId,
                'notes' => $data['notes'] ?? null,
            ]);

            // Jika terhubung ke kompetisi, perbarui status hasil delegasi terkait
            if (! empty($data['competition_id'])) {
                $entry = CompetitionEntry::where('competition_id', $data['competition_id'])
                    ->where('student_id', $student->id)
                    ->first();

                if ($entry) {
                    $entry->update([
                        'achievement_id' => $achievement->id,
                        'result' => $data['rank_level'],
                        'status' => 'selesai',
                    ]);
                }
            }

            $this->logAudit($user?->id, 'achievement.create', 'achievements', $achievement->id, null, $achievement->toArray(), $request);

            return $achievement->fresh(['student.classes', 'competition', 'certificateFile', 'photoFile', 'verifiedBy', 'studentPoint']);
        });
    }

    public function updateAchievement(string $id, array $data, Request $request): Achievement
    {
        $user = $request->user();
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        $achievement = Achievement::with(['student'])->findOrFail($id);

        if (! empty($user->school_id)) {
            $schoolMatches = ($achievement->school_id === $user->school_id) || ($achievement->student?->school_id === $user->school_id);
            if (! $schoolMatches && ! empty($achievement->school_id)) {
                throw new HttpException(403, 'Akses ditolak: Prestasi berada di luar sekolah penugasan Anda.');
            }
        }

        $isStudent = $this->isStudentUser($user);
        $isGuardian = $this->isGuardianUser($user);
        $isStaffOrAdmin = $this->isStaffOrAdmin($user);
        $isTeacher = $this->isTeacherOrStaff($user);

        if ($isStudent) {
            $ownStudentId = $user->student?->id;
            if (! $ownStudentId || $ownStudentId !== $achievement->student_id) {
                throw new HttpException(403, 'Akses ditolak: Anda hanya dapat memperbarui pengajuan prestasi milik Anda sendiri.');
            }
            if ($achievement->verification_status === 'verified') {
                throw new HttpException(403, 'Prestasi yang sudah diverifikasi tidak dapat diubah oleh siswa.');
            }
            // Strip privileged fields
            unset($data['verification_status'], $data['verified_by'], $data['verified_at'], $data['student_point_id'], $data['points_awarded'], $data['student_id']);
            // Jika sebelumnya ditolak dan diajukan ulang oleh siswa
            if ($achievement->verification_status === 'rejected') {
                $data['verification_status'] = 'pending';
                $data['rejection_reason'] = null;
            }
        } elseif ($isGuardian) {
            $isOwnChild = $user->guardian?->students()->where('students.id', $achievement->student_id)->exists();
            if (! $isOwnChild) {
                throw new HttpException(403, 'Akses ditolak: Anda hanya dapat memperbarui prestasi anak yang terdaftar di bawah perwalian Anda.');
            }
            if ($achievement->verification_status === 'verified') {
                throw new HttpException(403, 'Prestasi yang sudah diverifikasi tidak dapat diubah oleh wali murid.');
            }
            // Strip privileged fields
            unset($data['verification_status'], $data['verified_by'], $data['verified_at'], $data['student_point_id'], $data['points_awarded'], $data['student_id']);
            // Jika sebelumnya ditolak dan diajukan ulang oleh wali
            if ($achievement->verification_status === 'rejected') {
                $data['verification_status'] = 'pending';
                $data['rejection_reason'] = null;
            }
        } elseif (! $isTeacher) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang memperbarui data prestasi.');
        }

        // Cegah perubahan prestasi yang sudah diverifikasi oleh pengguna tanpa wewenang staf/admin
        if ($achievement->verification_status === 'verified' && ! $isStaffOrAdmin) {
            throw new HttpException(403, 'Prestasi yang sudah diverifikasi tidak dapat diubah tanpa kewenangan staf kesiswaan atau admin.');
        }

        return DB::transaction(function () use ($achievement, $data, $user, $request) {
            $oldValues = $achievement->toArray();
            $achievement->update($data);

            // Selaraskan ke student_points bila terhubung
            if ($achievement->student_point_id && $achievement->studentPoint) {
                $achievement->studentPoint->update([
                    'point_date' => $achievement->achieved_at,
                    'notes' => "Pencatatan otomatis prestasi: {$achievement->title} ({$achievement->rank_level})",
                ]);
            }

            $this->logAudit($user?->id, 'achievement.update', 'achievements', $achievement->id, $oldValues, $achievement->fresh()->toArray(), $request);

            return $achievement->fresh(['student.classes', 'competition', 'certificateFile', 'photoFile', 'verifiedBy', 'studentPoint']);
        });
    }

    public function deleteAchievement(string $id, Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        $achievement = Achievement::with(['student'])->findOrFail($id);

        if (! empty($user->school_id)) {
            $schoolMatches = ($achievement->school_id === $user->school_id) || ($achievement->student?->school_id === $user->school_id);
            if (! $schoolMatches && ! empty($achievement->school_id)) {
                throw new HttpException(403, 'Akses ditolak: Prestasi berada di luar sekolah penugasan Anda.');
            }
        }

        $isStudent = $this->isStudentUser($user);
        $isGuardian = $this->isGuardianUser($user);
        $isStaffOrAdmin = $this->isStaffOrAdmin($user);

        if ($isStudent) {
            $ownStudentId = $user->student?->id;
            if (! $ownStudentId || $ownStudentId !== $achievement->student_id) {
                throw new HttpException(403, 'Akses ditolak: Anda hanya dapat membatalkan pengajuan prestasi milik Anda sendiri.');
            }
            if ($achievement->verification_status === 'verified') {
                throw new HttpException(403, 'Prestasi yang sudah diverifikasi tidak dapat dihapus oleh siswa.');
            }
        } elseif ($isGuardian) {
            $isOwnChild = $user->guardian?->students()->where('students.id', $achievement->student_id)->exists();
            if (! $isOwnChild) {
                throw new HttpException(403, 'Akses ditolak: Anda hanya dapat membatalkan pengajuan prestasi anak perwalian Anda.');
            }
            if ($achievement->verification_status === 'verified') {
                throw new HttpException(403, 'Prestasi yang sudah diverifikasi tidak dapat dihapus oleh wali murid.');
            }
        } elseif (! $isStaffOrAdmin) {
            throw new HttpException(403, 'Akses ditolak: Hanya staf kesiswaan atau admin yang berwenang menghapus data prestasi.');
        }

        return DB::transaction(function () use ($achievement, $user, $id, $request) {
            $oldValues = $achievement->toArray();

            // Lepas relasi pada competition_entries
            CompetitionEntry::where('achievement_id', $achievement->id)
                ->update(['achievement_id' => null]);

            // Hapus poin terkait di student_points
            if ($achievement->student_point_id) {
                StudentPoint::where('id', $achievement->student_point_id)
                    ->where('student_id', $achievement->student_id)
                    ->delete();
            }

            $achievement->delete();

            $this->logAudit($user?->id, 'achievement.delete', 'achievements', $id, $oldValues, null, $request);

            return true;
        });
    }

    public function verifyAchievement(string $id, array $data, Request $request): Achievement
    {
        $user = $request->user();
        $this->authorizeStaffOrAdmin($user);

        $newStatus = $data['status']; // 'verified' atau 'rejected'

        return DB::transaction(function () use ($id, $newStatus, $data, $user, $request) {
            $achievement = Achievement::where('id', $id)
                ->lockForUpdate()
                ->with(['student'])
                ->firstOrFail();

            if ($user && ! empty($user->school_id)) {
                $schoolMatches = ($achievement->school_id === $user->school_id) || ($achievement->student?->school_id === $user->school_id);
                if (! $schoolMatches && ! empty($achievement->school_id)) {
                    throw new HttpException(403, 'Akses ditolak: Prestasi berada di luar sekolah penugasan Anda.');
                }
            }

            $oldValues = $achievement->toArray();

            if ($newStatus === 'verified') {
                $achievement->verification_status = 'verified';
                $achievement->verified_by = $user->id;
                $achievement->verified_at = Carbon::now();
                $achievement->rejection_reason = null;

                // Bila belum ada poin, berikan poin prestasi (idempoten)
                if (! $achievement->student_point_id) {
                    $pointType = PointType::firstOrCreate(
                        ['kind' => 'prestasi', 'name' => 'Prestasi: '.$achievement->rank_level],
                        ['points' => $achievement->points_awarded > 0 ? $achievement->points_awarded : 25, 'severity' => 'sedang', 'school_id' => $user?->school_id ?? $achievement->student?->school_id]
                    );

                    $studentPoint = StudentPoint::create([
                        'school_id' => $user?->school_id ?? $achievement->student?->school_id,
                        'student_id' => $achievement->student_id,
                        'point_type_id' => $pointType->id,
                        'recorded_by' => $user->id,
                        'point_date' => $achievement->achieved_at,
                        'evidence_file_id' => $achievement->certificate_file_id ?? $achievement->photo_file_id ?? null,
                        'notes' => "Pencatatan otomatis prestasi terverifikasi: {$achievement->title} ({$achievement->rank_level})",
                    ]);

                    $achievement->student_point_id = $studentPoint->id;
                }
            } else {
                $achievement->verification_status = 'rejected';
                $achievement->verified_by = $user->id;
                $achievement->verified_at = Carbon::now();
                $achievement->rejection_reason = $data['rejection_reason'] ?? 'Dokumen bukti tidak memenuhi kriteria verifikasi.';

                // Bila sebelumnya ada poin tercatat, cabut HANYA poin yang terkait dengan prestasi ini
                if ($achievement->student_point_id) {
                    StudentPoint::where('id', $achievement->student_point_id)
                        ->where('student_id', $achievement->student_id)
                        ->delete();
                    $achievement->student_point_id = null;
                }
            }

            $achievement->save();

            $this->logAudit($user?->id, 'achievement.verify', 'achievements', $achievement->id, $oldValues, $achievement->fresh()->toArray(), $request);

            return $achievement->fresh(['student.classes', 'competition', 'certificateFile', 'photoFile', 'verifiedBy', 'studentPoint']);
        });
    }

    // =========================================================================
    // 4. STATISTIK & RINGKASAN PRESTASI (BE-STD-03 SUMMARY)
    // =========================================================================

    public function getSummary(?User $user = null): array
    {
        $compQuery = Competition::query();
        $achQuery = Achievement::query();
        $entryQuery = CompetitionEntry::query();

        if ($user && ! empty($user->school_id)) {
            $compQuery->where(function ($q) use ($user) {
                $q->where('school_id', $user->school_id)->orWhereNull('school_id');
            });
            $achQuery->where(function ($q) use ($user) {
                $q->where('school_id', $user->school_id)
                    ->orWhereHas('student', fn ($sq) => $sq->where('school_id', $user->school_id));
            });
            $entryQuery->whereHas('student', fn ($sq) => $sq->where('school_id', $user->school_id));
        }

        $totalCompetitions = (clone $compQuery)->count();
        $activeCompetitions = (clone $compQuery)->whereIn('status', ['pendaftaran', 'berlangsung'])->count();
        $totalEntries = (clone $entryQuery)->count();
        $totalAchievements = (clone $achQuery)->count();
        $verifiedAchievements = (clone $achQuery)->where('verification_status', 'verified')->count();
        $pendingVerification = (clone $achQuery)->where('verification_status', 'pending')->count();

        // Rekap Medali & Trofi
        $medals = [
            'emas' => (clone $achQuery)->where(function ($q) {
                $q->whereRaw('LOWER(rank_level) LIKE ?', ['%emas%'])
                    ->orWhereRaw('LOWER(rank_level) LIKE ?', ['%juara 1%'])
                    ->orWhereRaw('LOWER(title) LIKE ?', ['%juara 1%']);
            })->count(),
            'perak' => (clone $achQuery)->where(function ($q) {
                $q->whereRaw('LOWER(rank_level) LIKE ?', ['%perak%'])
                    ->orWhereRaw('LOWER(rank_level) LIKE ?', ['%juara 2%'])
                    ->orWhereRaw('LOWER(title) LIKE ?', ['%juara 2%']);
            })->count(),
            'perunggu' => (clone $achQuery)->where(function ($q) {
                $q->whereRaw('LOWER(rank_level) LIKE ?', ['%perunggu%'])
                    ->orWhereRaw('LOWER(rank_level) LIKE ?', ['%juara 3%'])
                    ->orWhereRaw('LOWER(title) LIKE ?', ['%juara 3%']);
            })->count(),
            'finalis' => (clone $achQuery)->where(function ($q) {
                $q->whereRaw('LOWER(rank_level) LIKE ?', ['%finalis%'])
                    ->orWhereRaw('LOWER(rank_level) LIKE ?', ['%harapan%']);
            })->count(),
        ];

        return [
            'total_competitions' => $totalCompetitions,
            'active_competitions' => $activeCompetitions,
            'total_entries' => $totalEntries,
            'total_achievements' => $totalAchievements,
            'verified_achievements' => $verifiedAchievements,
            'pending_verification' => $pendingVerification,
            'medals' => $medals,
        ];
    }

    // =========================================================================
    // OTORISASI & AUDIT
    // =========================================================================

    public function isStaffOrAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole('admin') ||
            $user->hasRole('super_admin') ||
            $user->hasRole('kesiswaan') ||
            $user->hasRole('student_affairs');
    }

    public function isStudentUser(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->student()->exists() || $user->hasRole('student') || $user->hasRole('siswa');
    }

    public function isGuardianUser(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->guardian()->exists() || $user->hasRole('guardian') || $user->hasRole('wali') || $user->hasRole('orang_tua');
    }

    public function isTeacherOrStaff(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->isStaffOrAdmin($user) ||
            $user->hasRole('teacher') ||
            $user->hasRole('homeroom_teacher') ||
            $user->hasRole('counselor') ||
            $user->hasPermission('student.point.write');
    }

    protected function authorizeTeacherOrStaff(?User $user): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        if (! $this->isTeacherOrStaff($user)) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang mengelola data lomba & prestasi.');
        }
    }

    protected function authorizeStaffOrAdmin(?User $user): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        if (! $this->isStaffOrAdmin($user)) {
            throw new HttpException(403, 'Akses ditolak: Hanya staf kesiswaan atau admin yang berwenang.');
        }
    }

    protected function logAudit(?string $actorId, string $action, string $entity, ?string $entityId, ?array $oldValue, ?array $newValue, Request $request): void
    {
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
            // Jangan gagalkan transaksi bila audit gagal
        }
    }
}
