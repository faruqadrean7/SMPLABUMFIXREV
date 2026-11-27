<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\ApplicantStatusHistory;
use App\Models\AuditLog;
use App\Models\ClassStudent;
use App\Models\Guardian;
use App\Models\PPDBReRegistration;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentStatusHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PPDBEnrollmentService
{
    /**
     * BE-PPDB-08: Confirm candidate re-registration.
     * Calon siswa diterima mengonfirmasi daftar ulang.
     */
    public function confirmReRegistration(string $applicantId, array $data, Request $request): array
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        $applicant = Applicant::with(['reRegistration', 'wave', 'track'])->findOrFail($applicantId);

        // IDOR Protection: only owner or staff with ppdb.applicant.verify permission
        if ($applicant->user_id !== $currentUser->id && ! $currentUser->hasPermission('ppdb.applicant.verify')) {
            throw new HttpException(403, 'Anda tidak memiliki hak akses untuk mengonfirmasi daftar ulang pendaftaran ini');
        }

        // Acceptance validation
        $isAccepted = $applicant->decision === 'diterima' || in_array($applicant->status, ['selected', 'enrolled'], true);
        if (! $isAccepted) {
            throw new HttpException(422, 'Hanya calon siswa yang dinyatakan diterima yang dapat melakukan konfirmasi daftar ulang');
        }

        // Idempotent return if already enrolled/verified
        if ($applicant->status === 'enrolled' || $applicant->reRegistration?->status === 'diverifikasi') {
            return [
                're_registration' => $applicant->reRegistration,
                'applicant' => $applicant,
                'message' => 'Calon siswa sudah terdaftar dan diverifikasi sebagai siswa.',
            ];
        }

        // Decision #4: Batas waktu daftar ulang 3 hari kerja setelah pengumuman hasil seleksi
        if (! $currentUser->hasPermission('ppdb.applicant.verify')) {
            $wave = $applicant->wave;
            $publishAt = $wave?->result_publish_at ?? $wave?->close_at;
            if ($publishAt) {
                $deadline = $publishAt->copy()->addWeekdays(3)->endOfDay();
                if (Carbon::now()->gt($deadline)) {
                    $formattedDeadline = $deadline->translatedFormat('d F Y H:i');
                    throw new HttpException(
                        422,
                        "Batas waktu konfirmasi daftar ulang telah berakhir pada tanggal {$formattedDeadline} WIB (3 hari kerja setelah pengumuman hasil seleksi). Silakan hubungi panitia PPDB."
                    );
                }
            }
        }

        return DB::transaction(function () use ($applicant, $data, $currentUser, $request) {
            $notes = $data['notes'] ?? null;

            $reReg = PPDBReRegistration::updateOrCreate(
                ['applicant_id' => $applicant->id],
                [
                    'confirmed_at' => Carbon::now(),
                    'status' => 'dikonfirmasi',
                    'notes' => $notes,
                ]
            );

            ApplicantStatusHistory::create([
                'applicant_id' => $applicant->id,
                'from_status' => $applicant->status,
                'to_status' => $applicant->status,
                'note' => 'Konfirmasi daftar ulang dilakukan oleh calon siswa',
                'actor_id' => $currentUser->id,
            ]);

            $this->logAudit(
                $currentUser->id,
                'ppdb_re_registration.confirmed',
                'PPDBReRegistration',
                $reReg->id,
                null,
                $reReg->toArray(),
                $request
            );

            return [
                're_registration' => $reReg->fresh(),
                'applicant' => $applicant->fresh(['reRegistration', 'wave', 'track']),
            ];
        });
    }

    /**
     * BE-PPDB-08: Validate and enroll applicant into student.
     * Admin/Panitia memvalidasi dan mengonversi pendaftar ke siswa.
     * Operasi bersifat idempoten: jika di-run berulang kali, tidak membuat duplikat siswa/user.
     */
    public function enrollApplicant(string $applicantId, array $data, Request $request): array
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        $applicant = Applicant::with(['wave', 'track', 'parents', 'reRegistration', 'user', 'documents'])->findOrFail($applicantId);

        // 1. Idempotency Check: if already enrolled or student_id exists
        if ($applicant->student_id !== null || $applicant->status === 'enrolled') {
            $existingStudent = Student::with(['user.roles', 'guardians.user', 'classes'])->find($applicant->student_id);
            if ($existingStudent) {
                return [
                    'is_idempotent' => true,
                    'message' => 'Pendaftar telah dikonversi menjadi siswa sebelumnya (idempoten)',
                    'student' => $existingStudent,
                    'applicant' => $applicant->fresh(['statusHistory', 'reRegistration']),
                ];
            }
        }

        // 2. Acceptance Verification
        $isAccepted = $applicant->decision === 'diterima' || $applicant->status === 'selected';
        if (! $isAccepted) {
            throw new HttpException(422, 'Calon siswa belum dinyatakan diterima untuk gelombang seleksi ini');
        }

        // 3. Re-registration Check
        $forceEnroll = (bool) ($data['force_enroll'] ?? false);
        $reReg = $applicant->reRegistration;
        if (! $forceEnroll && (! $reReg || $reReg->status !== 'dikonfirmasi')) {
            throw new HttpException(422, 'Calon siswa belum melakukan konfirmasi daftar ulang');
        }

        return DB::transaction(function () use ($applicant, $data, $currentUser, $request) {
            // A. Roles Setup
            $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
            $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Orang Tua / Wali Murid', 'is_system' => true]);

            // B. Resolve or Create Student User Account
            $studentUserId = null;
            if ($applicant->user_id) {
                $alreadyUsed = Student::where('user_id', $applicant->user_id)->exists();
                if (! $alreadyUsed) {
                    $studentUserId = $applicant->user_id;
                    $applicant->user?->roles()->syncWithoutDetaching([$roleStudent->id]);
                }
            }

            if (! $studentUserId) {
                $studentUsername = 'sis_'.Str::slug($applicant->full_name, '_').'_'.random_int(1000, 9999);
                while (User::where('username', $studentUsername)->exists()) {
                    $studentUsername = 'sis_'.Str::slug($applicant->full_name, '_').'_'.random_int(1000, 9999);
                }

                $studentActivationToken = Str::random(48);
                $randomSecret = Str::random(32);

                $studentUser = User::create([
                    'school_id' => $applicant->wave?->school_id,
                    'username' => $studentUsername,
                    'email' => $studentUsername.'@siswa.sistlabum.sch.id',
                    'phone' => $applicant->step_data['personal']['phone'] ?? null,
                    'password_hash' => Hash::make($randomSecret),
                    'status' => 'active',
                    'must_change_password' => true,
                ]);

                $studentUser->roles()->attach($roleStudent->id);
                $studentUserId = $studentUser->id;

                Cache::put("student_activation:{$studentUser->id}", $studentActivationToken, 86400 * 7);
                Cache::put("student_activation_token:{$studentActivationToken}", $studentUser->id, 86400 * 7);
            }

            // C. Resolve NIS & NISN
            $nis = $data['nis'] ?? null;
            if (! $nis) {
                $year = Carbon::now()->format('Y');
                $seq = Student::where('nis', 'like', $year.'%')->count() + 1;
                $nis = $year.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
                while (Student::where('nis', $nis)->exists()) {
                    $seq++;
                    $nis = $year.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
                }
            }

            $nisn = $data['nisn'] ?? ($applicant->step_data['personal']['nisn'] ?? ($applicant->step_data['nisn'] ?? null));
            if (! $nisn || Student::where('nisn', $nisn)->exists()) {
                $seqNisn = random_int(10000000, 99999999);
                $nisn = '00'.$seqNisn;
                while (Student::where('nisn', $nisn)->exists()) {
                    $seqNisn = random_int(10000000, 99999999);
                    $nisn = '00'.$seqNisn;
                }
            }

            // D. Extract Personal Info
            $personal = $applicant->step_data['personal'] ?? [];
            $gender = $personal['gender'] ?? ($personal['jenis_kelamin'] ?? 'L');
            $birthPlace = $personal['birth_place'] ?? ($personal['tempat_lahir'] ?? 'Kota Asal');
            $religion = $personal['religion'] ?? ($personal['agama'] ?? null);
            $address = $personal['address'] ?? ($personal['alamat'] ?? null);
            $entryDate = $data['entry_date'] ?? Carbon::now()->toDateString();

            // Link photo if uploaded
            $photoDoc = $applicant->documents()
                ->where(function ($q) {
                    $q->whereIn('document_type', ['foto', 'pas_foto', 'photo', 'foto_siswa', 'FOTO', 'PAS_FOTO', 'PHOTO', 'FOTO_SISWA'])
                        ->orWhereRaw("LOWER(document_type) IN ('foto', 'pas_foto', 'photo', 'foto_siswa', 'pasfoto')");
                })
                ->first();
            $photoFileId = $photoDoc?->file_id;

            // E. Create Student
            $student = Student::create([
                'school_id' => $applicant->wave?->school_id,
                'user_id' => $studentUserId,
                'nis' => $nis,
                'nisn' => $nisn,
                'nik_enc' => $applicant->nik_enc,
                'full_name' => $applicant->full_name,
                'gender' => in_array($gender, ['L', 'P'], true) ? $gender : 'L',
                'birth_place' => $birthPlace,
                'birth_date' => $applicant->birth_date,
                'religion' => $religion,
                'address' => $address,
                'photo_file_id' => $photoFileId,
                'status' => 'aktif',
                'entry_date' => $entryDate,
            ]);

            // F. Initial Student Status History
            StudentStatusHistory::create([
                'student_id' => $student->id,
                'status' => 'aktif',
                'reason' => 'Konversi dari pendaftaran PPDB No: '.$applicant->registration_no,
                'effective_date' => $entryDate,
            ]);

            // G. Process Guardians from Applicant Parents
            $activationLinks = [];
            if ($applicant->parents->isNotEmpty()) {
                $isFirst = true;
                foreach ($applicant->parents as $parent) {
                    $guardian = null;
                    if (! empty($parent->phone)) {
                        $guardian = Guardian::where('phone', $parent->phone)->first();
                    }

                    if (! $guardian) {
                        $parentBase = Str::slug($parent->name, '_') ?: 'ortu';
                        $guardianUsername = 'ortu_'.$parentBase.'_'.random_int(1000, 9999);
                        while (User::where('username', $guardianUsername)->exists()) {
                            $guardianUsername = 'ortu_'.$parentBase.'_'.random_int(1000, 9999);
                        }

                        // Decision #6: Aman, tidak menggunakan default password terbuka.
                        // Menggunakan unguessable secret hash + tautan aktivasi mandiri portal.
                        $activationToken = Str::random(48);
                        $randomSecret = Str::random(32);

                        $guardianUser = User::create([
                            'school_id' => $applicant->wave?->school_id,
                            'username' => $guardianUsername,
                            'email' => $guardianUsername.'@wali.sistlabum.sch.id',
                            'phone' => $parent->phone ?? ('08'.random_int(10000000, 99999999)),
                            'password_hash' => Hash::make($randomSecret),
                            'status' => 'active',
                            'must_change_password' => true,
                        ]);

                        $guardianUser->roles()->attach($roleGuardian->id);

                        // Simpan token aktivasi di cache selama 7 hari
                        Cache::put("guardian_activation:{$guardianUser->id}", $activationToken, 86400 * 7);
                        Cache::put("guardian_activation_token:{$activationToken}", $guardianUser->id, 86400 * 7);

                        $guardian = Guardian::create([
                            'school_id' => $applicant->wave?->school_id,
                            'user_id' => $guardianUser->id,
                            'full_name' => $parent->name,
                            'phone' => $parent->phone ?? $guardianUser->phone,
                            'occupation' => $parent->occupation,
                            'address' => $address,
                        ]);

                        $activationLinks[] = [
                            'guardian_id' => $guardian->id,
                            'user_id' => $guardianUser->id,
                            'full_name' => $parent->name,
                            'phone' => $parent->phone,
                            'activation_token' => $activationToken,
                            'activation_url' => url('/auth/activate-guardian?token='.$activationToken),
                        ];
                    }

                    $student->guardians()->syncWithoutDetaching([
                        $guardian->id => [
                            'relationship' => in_array($parent->parent_type, ['ayah', 'ibu', 'wali'], true) ? $parent->parent_type : 'wali',
                            'is_primary' => $isFirst,
                        ],
                    ]);
                    $isFirst = false;
                }
            }

            // H. Class Placement if requested
            if (! empty($data['class_id'])) {
                $class = SchoolClass::findOrFail($data['class_id']);
                ClassStudent::firstOrCreate(
                    [
                        'class_id' => $class->id,
                        'student_id' => $student->id,
                    ],
                    [
                        'joined_at' => $entryDate,
                    ]
                );
            }

            // I. Update Applicant & Re-registration
            $oldStatus = $applicant->status;
            $applicant->student_id = $student->id;
            $applicant->status = 'enrolled';
            $applicant->save();

            if ($applicant->reRegistration) {
                $applicant->reRegistration->status = 'diverifikasi';
                $applicant->reRegistration->save();
            }

            ApplicantStatusHistory::create([
                'applicant_id' => $applicant->id,
                'from_status' => $oldStatus,
                'to_status' => 'enrolled',
                'note' => 'Pendaftar berhasil divalidasi dan dikonversi menjadi siswa dengan NIS '.$student->nis,
                'actor_id' => $currentUser->id,
            ]);

            // J. Audit Log
            $this->logAudit(
                $currentUser->id,
                'ppdb_applicant.enrolled',
                'Applicant',
                $applicant->id,
                ['status' => $oldStatus],
                [
                    'status' => 'enrolled',
                    'student_id' => $student->id,
                    'nis' => $student->nis,
                    'class_id' => $data['class_id'] ?? null,
                ],
                $request
            );

            return [
                'is_idempotent' => false,
                'message' => 'Pendaftar berhasil dikonversi menjadi siswa baru',
                'student' => $student->fresh(['user.roles', 'guardians.user', 'classes']),
                'applicant' => $applicant->fresh(['statusHistory', 'reRegistration']),
                'activation_links' => $activationLinks,
            ];
        });
    }

    /**
     * Helper to write an audit log entry.
     */
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
