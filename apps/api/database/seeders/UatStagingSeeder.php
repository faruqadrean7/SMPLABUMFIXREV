<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\Booking;
use App\Models\ClassStudent;
use App\Models\Facility;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\LeaveRequest;
use App\Models\Permission;
use App\Models\PPDBTrack;
use App\Models\PPDBWave;
use App\Models\ReportCard;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\SiteSetting;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UatStagingSeeder extends Seeder
{
    /**
     * Default canonical school ID for SISTLABUM automated testing only.
     */
    public const DEFAULT_SCHOOL_ID = '00000000-0000-0000-0000-000000000001';

    /**
     * Safely check whether the current environment allows running the UAT Staging Seeder.
     *
     * @throws \RuntimeException
     */
    public function checkEnvironmentSafety(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('UatStagingSeeder dilarang keras dijalankan pada lingkungan production!');
        }

        if (! app()->environment('testing')) {
            $optIn = filter_var(env('UAT_SEED_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
            if (! in_array(app()->environment(), ['staging', 'local'], true) || ! $optIn) {
                throw new \RuntimeException('UatStagingSeeder hanya boleh dijalankan di lingkungan staging/local dengan opt-in UAT_SEED_ENABLED=true atau lingkungan testing.');
            }
        }
    }

    /**
     * Resolve and validate tenant school_id before any database write.
     *
     * @throws \RuntimeException
     */
    public function resolveTenantSchoolId(): string
    {
        // 1. Cek konfigurasi env eksplisit
        $configuredSchoolId = env('UAT_SCHOOL_ID') ?: env('DEFAULT_SCHOOL_ID');

        // 2. Cek apakah ada SiteSetting existing di database
        $existingSiteSettingSchoolId = SiteSetting::where('setting_key', 'general_info')
            ->whereNotNull('school_id')
            ->value('school_id');

        if ($configuredSchoolId && $existingSiteSettingSchoolId && $configuredSchoolId !== $existingSiteSettingSchoolId) {
            throw new \RuntimeException(
                "Konflik tenant: UAT_SCHOOL_ID ({$configuredSchoolId}) tidak cocok dengan school_id yang tercatat di SiteSetting ({$existingSiteSettingSchoolId})."
            );
        }

        $schoolId = $configuredSchoolId ?: $existingSiteSettingSchoolId;

        if (empty($schoolId)) {
            if (app()->environment(['testing', 'local'])) {
                $schoolId = self::DEFAULT_SCHOOL_ID;
            } else {
                throw new \RuntimeException(
                    'Identitas tenant/school_id staging belum terdefinisi. '.
                    'Harap sediakan UAT_SCHOOL_ID yang valid via environment atau pastikan SiteSetting general_info telah diinisialisasi.'
                );
            }
        }

        // Validasi format UUID
        if (! Str::isUuid($schoolId)) {
            throw new \RuntimeException("Identitas tenant/school_id '{$schoolId}' bukan format UUID yang valid.");
        }

        return $schoolId;
    }

    /**
     * Resolve UAT password safely.
     *
     * In staging:
     * - A dedicated secret MUST be provided via UAT_STAGING_PASSWORD or UAT_DEFAULT_PASSWORD.
     * - Falling back to a hardcoded default is strictly forbidden.
     *
     * In testing:
     * - Allows a safe testing fallback so automated tests run without requiring manual secret configuration.
     *
     * @throws \RuntimeException
     */
    public function resolveUatPassword(): string
    {
        $password = env('UAT_STAGING_PASSWORD') ?: env('UAT_DEFAULT_PASSWORD');

        if (app()->environment('staging')) {
            if (empty($password)) {
                throw new \RuntimeException(
                    'Di lingkungan staging, kata sandi akun UAT wajib disediakan secara eksplisit '.
                    'melalui variabel lingkungan UAT_STAGING_PASSWORD atau UAT_DEFAULT_PASSWORD.'
                );
            }
            if ($password === 'SistLabUM2026!UAT' && ! filter_var(env('ALLOW_PREDICTABLE_UAT_PASSWORD', false), FILTER_VALIDATE_BOOLEAN)) {
                throw new \RuntimeException(
                    'Di lingkungan staging, penggunaan kata sandi default publik tidak diizinkan. '.
                    'Silakan sediakan rahasia unik yang aman via UAT_STAGING_PASSWORD.'
                );
            }
        }

        if (empty($password)) {
            if (app()->environment(['testing', 'local'])) {
                $password = 'SistLabUM2026!UAT';
            } else {
                throw new \RuntimeException('Kata sandi UAT belum dikonfigurasikan.');
            }
        }

        return $password;
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Preflight checks executed before any database operations
        $this->checkEnvironmentSafety();
        $schoolId = $this->resolveTenantSchoolId();
        $password = $this->resolveUatPassword();

        DB::transaction(function () use ($schoolId, $password) {
            // 1. Ensure core roles & permissions are populated
            (new DatabaseSeeder)->run();

            // Ensure site setting school identification
            SiteSetting::firstOrCreate(
                ['setting_key' => 'general_info'],
                [
                    'school_id' => $schoolId,
                    'setting_value' => [
                        'school_name' => 'SMP Laboratorium Universitas Negeri Malang',
                        'npsn' => '20533807',
                        'accreditation' => 'A',
                        'address' => 'Jl. Simpang Bogor No. 1, Lowokwaru, Malang',
                    ],
                ]
            );

            // 2. Ensure curriculum roles and permissions exist for Kurikulum personas
            $kurikulumPerms = Permission::whereIn('code', [
                'master.academic_year.manage',
                'master.student.read',
                'master.teacher.manage',
                'master.class.manage',
                'master.subject.manage',
                'master.room.manage',
                'master.promotion.manage',
                'academic.schedule.read',
                'academic.schedule.manage',
                'academic.attendance.read',
                'academic.grade.read',
                'academic.grade.write',
                'cbt.bank.write',
                'cbt.exam.manage',
                'executive.approval.manage',
                'dashboard.executive',
            ])->pluck('id')->toArray();

            foreach (['admin_kurikulum', 'kurikulum'] as $kRoleCode) {
                $kRole = Role::firstOrCreate(
                    ['code' => $kRoleCode],
                    ['name' => 'Kurikulum', 'is_system' => true]
                );
                if (! empty($kurikulumPerms)) {
                    $kRole->permissions()->syncWithoutDetaching($kurikulumPerms);
                }
            }

            // 3. Master Academic Setup (Tahun Ajaran, Semester, Jenjang, Rombel, Mapel)
            $academicYear = AcademicYear::firstOrCreate(
                ['name' => '2026/2027'],
                [
                    'school_id' => $schoolId,
                    'start_date' => '2026-07-01',
                    'end_date' => '2027-06-30',
                    'is_active' => true,
                ]
            );

            $semester = Semester::firstOrCreate(
                [
                    'academic_year_id' => $academicYear->id,
                    'name' => 'Ganjil',
                ],
                [
                    'start_date' => '2026-07-01',
                    'end_date' => '2026-12-31',
                    'is_active' => true,
                ]
            );

            $gradeLevel7 = GradeLevel::firstOrCreate(
                ['name' => 'Kelas 7'],
                [
                    'school_id' => $schoolId,
                    'sort_order' => 7,
                ]
            );

            $subjectMtk = Subject::firstOrCreate(
                ['code' => 'MTK-7'],
                [
                    'school_id' => $schoolId,
                    'name' => 'Matematika',
                    'subject_group' => 'wajib',
                ]
            );

            // 4. PPDB Setup (Gelombang 1 & 4 Jalur Pendaftaran Faktual)
            $wave1 = PPDBWave::firstOrCreate(
                ['name' => 'Gelombang 1'],
                [
                    'school_id' => $schoolId,
                    'open_at' => '2026-01-01 00:00:00',
                    'close_at' => '2027-06-30 23:59:59',
                    'result_publish_at' => '2027-07-05 10:00:00',
                    'status' => 'aktif',
                ]
            );

            PPDBTrack::firstOrCreate(
                ['wave_id' => $wave1->id, 'code' => 'reguler'],
                [
                    'name' => 'Jalur Reguler',
                    'quota' => 120,
                    'score_formula' => ['rapor' => 0.7, 'tes' => 0.3],
                ]
            );

            $trackPrestasi = PPDBTrack::firstOrCreate(
                ['wave_id' => $wave1->id, 'code' => 'prestasi'],
                [
                    'name' => 'Jalur Prestasi',
                    'quota' => 50,
                    'score_formula' => ['rapor' => 0.6, 'prestasi' => 0.4],
                ]
            );

            PPDBTrack::firstOrCreate(
                ['wave_id' => $wave1->id, 'code' => 'afirmasi'],
                [
                    'name' => 'Jalur Afirmasi',
                    'quota' => 40,
                    'score_formula' => ['rapor' => 1.0],
                ]
            );

            PPDBTrack::firstOrCreate(
                ['wave_id' => $wave1->id, 'code' => 'tahfidz'],
                [
                    'name' => 'Jalur Tahfidz Qur\'an',
                    'quota' => 15,
                    'score_formula' => ['tahfidz' => 0.6, 'rapor' => 0.4],
                ]
            );

            $forceResetPasswords = filter_var(env('UAT_FORCE_RESET_PASSWORDS', false), FILTER_VALIDATE_BOOLEAN);

            // Helper to get or create UAT user safely, idempotently, and preventing accidental password/identity overwrite
            $seedUser = function (string $username, string $email, array $roleCodes) use ($schoolId, $password, $forceResetPasswords): User {
                $userByUsername = User::where('username', $username)->first();
                $userByEmail = User::where('email', $email)->first();

                // Explicit account identifier conflict detection
                if ($userByUsername && $userByEmail && $userByUsername->id !== $userByEmail->id) {
                    throw new \RuntimeException(
                        "Konflik identifier akun UAT: username '{$username}' dan email '{$email}' terikat pada dua akun berbeda."
                    );
                }

                if (! $userByUsername && $userByEmail) {
                    throw new \RuntimeException(
                        "Konflik identifier akun UAT: email '{$email}' telah digunakan oleh akun lain (username: '{$userByEmail->username}')."
                    );
                }

                $user = $userByUsername ?? new User(['username' => $username]);
                $user->email = $email;
                $user->school_id = $schoolId;
                $user->status = 'active';
                $user->must_change_password = false;

                // Perilaku aman untuk password:
                // 1. Akun baru (! $user->exists): set password awal dari secret yang valid
                // 2. Akun sudah ada ($user->exists): JANGAN ubah password secara diam-diam kecuali $forceResetPasswords = true
                if (! $user->exists || $forceResetPasswords) {
                    $user->password_hash = Hash::make($password);
                }

                $user->save();

                $roleIds = Role::whereIn('code', $roleCodes)->pluck('id')->toArray();
                $user->roles()->sync($roleIds);

                return $user;
            };

            // 5. Seed 12 UAT Personas
            // 1) Super Admin
            $userAdmin = $seedUser('admin.staging', 'admin.staging@smp-labum.sch.id', ['super_admin']);

            // 2) Kepala Sekolah
            $userKepsek = $seedUser('kepsek.staging', 'kepsek.staging@smp-labum.sch.id', ['principal']);
            $teacherKepsek = Teacher::firstOrNew(['user_id' => $userKepsek->id]);
            $teacherKepsek->school_id = $schoolId;
            $teacherKepsek->nip = '197501012000031001';
            $teacherKepsek->nuptk = '1234567890123456';
            $teacherKepsek->full_name = 'Bapak Tamyis Suliantoro, M.Pd.';
            $teacherKepsek->position = 'Kepala Sekolah';
            $teacherKepsek->status = 'aktif';
            $teacherKepsek->save();

            // 3) Kurikulum
            $userKurikulum = $seedUser('kurikulum.staging', 'kurikulum.staging@smp-labum.sch.id', ['admin_tu', 'teacher', 'admin_kurikulum', 'kurikulum']);
            $teacherKurikulum = Teacher::firstOrNew(['user_id' => $userKurikulum->id]);
            $teacherKurikulum->school_id = $schoolId;
            $teacherKurikulum->nip = '198002022005011002';
            $teacherKurikulum->nuptk = '2345678901234567';
            $teacherKurikulum->full_name = 'Wakil Kepala Kurikulum UAT, S.Pd., M.Pd.';
            $teacherKurikulum->position = 'Wakil Kepala Bidang Kurikulum';
            $teacherKurikulum->status = 'aktif';
            $teacherKurikulum->save();

            // 4) Wali Kelas 7-A
            $userWalas = $seedUser('walas.7a.staging', 'walas.7a.staging@smp-labum.sch.id', ['homeroom', 'teacher']);
            $teacherWalas = Teacher::firstOrNew(['user_id' => $userWalas->id]);
            $teacherWalas->school_id = $schoolId;
            $teacherWalas->nip = '198503032010012003';
            $teacherWalas->nuptk = '3456789012345678';
            $teacherWalas->full_name = 'Wali Kelas 7-A UAT, S.Pd.';
            $teacherWalas->position = 'Guru / Wali Kelas 7-A';
            $teacherWalas->status = 'aktif';
            $teacherWalas->save();

            // 5) Guru Mapel Matematika
            $userGuruMtk = $seedUser('guru.mtk.staging', 'guru.mtk.staging@smp-labum.sch.id', ['teacher']);
            $teacherMtk = Teacher::firstOrNew(['user_id' => $userGuruMtk->id]);
            $teacherMtk->school_id = $schoolId;
            $teacherMtk->nip = '199004042015021004';
            $teacherMtk->nuptk = '4567890123456789';
            $teacherMtk->full_name = 'Guru Matematika UAT, S.Pd.';
            $teacherMtk->position = 'Guru Mata Pelajaran Matematika';
            $teacherMtk->status = 'aktif';
            $teacherMtk->save();

            // 6) Guru BK
            $userBk = $seedUser('bk.staging', 'bk.staging@smp-labum.sch.id', ['counselor']);
            $teacherBk = Teacher::firstOrNew(['user_id' => $userBk->id]);
            $teacherBk->school_id = $schoolId;
            $teacherBk->nip = '199205052018032005';
            $teacherBk->nuptk = '5678901234567890';
            $teacherBk->full_name = 'Guru BK Konseling UAT, S.Psi.';
            $teacherBk->position = 'Guru Bimbingan Konseling';
            $teacherBk->status = 'aktif';
            $teacherBk->save();

            // 7) Staf Tata Usaha
            $userTu = $seedUser('tu.staging', 'tu.staging@smp-labum.sch.id', ['admin_tu']);
            $staffTu = Staff::firstOrNew(['user_id' => $userTu->id]);
            $staffTu->school_id = $schoolId;
            $staffTu->full_name = 'Staf Tata Usaha UAT';
            $staffTu->position = 'Administrasi Tata Usaha';
            $staffTu->unit = 'Tata Usaha';
            $staffTu->save();

            // 8) Staf Sarpras
            $userSarpras = $seedUser('sarpras.staging', 'sarpras.staging@smp-labum.sch.id', ['facility_staff']);
            $staffSarpras = Staff::firstOrNew(['user_id' => $userSarpras->id]);
            $staffSarpras->school_id = $schoolId;
            $staffSarpras->full_name = 'Staf Sarana & Prasarana UAT';
            $staffSarpras->position = 'Pengelola Sarpras';
            $staffSarpras->unit = 'Sarana & Prasarana';
            $staffSarpras->save();

            // 9) Panitia PPDB
            $userPpdb = $seedUser('ppdb.panitia', 'ppdb.panitia@smp-labum.sch.id', ['ppdb_committee']);
            $staffPpdb = Staff::firstOrNew(['user_id' => $userPpdb->id]);
            $staffPpdb->school_id = $schoolId;
            $staffPpdb->full_name = 'Panitia PPDB UAT';
            $staffPpdb->position = 'Koordinator Verifikasi PPDB';
            $staffPpdb->unit = 'Panitia PPDB';
            $staffPpdb->save();

            // 10) Siswa Aktif
            $userSiswa = $seedUser('siswa.lab.001', 'siswa.lab.001@smp-labum.sch.id', ['student']);
            $student = Student::firstOrNew(['user_id' => $userSiswa->id]);
            $student->school_id = $schoolId;
            $student->nis = '26001';
            $student->nisn = '0099001001';
            $student->nik_enc = '3578010101090001';
            $student->full_name = 'Siswa UAT Lab 001';
            $student->gender = 'L';
            $student->birth_place = 'Malang';
            $student->birth_date = '2012-05-15';
            $student->religion = 'Islam';
            $student->address = 'Jl. Simpang Bogor No. 1, Malang';
            $student->status = 'aktif';
            $student->entry_date = '2026-07-01';
            $student->save();

            // 11) Orang Tua / Wali
            $userOrtu = $seedUser('ortu.siswa.001', 'ortu.siswa.001@smp-labum.sch.id', ['guardian']);
            $guardian = Guardian::firstOrNew(['user_id' => $userOrtu->id]);
            $guardian->school_id = $schoolId;
            $guardian->full_name = 'Orang Tua Siswa UAT 001';
            $guardian->phone = '081234567890';
            $guardian->occupation = 'Karyawan Swasta';
            $guardian->address = 'Jl. Simpang Bogor No. 1, Malang';
            $guardian->save();

            if (! $student->guardians()->where('guardian_id', $guardian->id)->exists()) {
                $student->guardians()->attach($guardian->id, [
                    'relationship' => 'ayah',
                    'is_primary' => true,
                ]);
            }

            // 12) Calon Siswa PPDB
            $userCalon = $seedUser('calon.ppdb.01', 'calon.ppdb.01@gmail.com', ['guest']);
            $applicant = Applicant::firstOrNew(['user_id' => $userCalon->id]);
            $applicant->wave_id = $wave1->id;
            $applicant->track_id = $trackPrestasi->id;
            $applicant->registration_no = 'PPDB-2026-0001';
            $applicant->nik_enc = '3578010202100002';
            $applicant->full_name = 'Calon Siswa PPDB UAT 01';
            $applicant->birth_date = '2013-01-20';
            $applicant->status = 'submitted';
            $applicant->score = 87.50;
            $applicant->rank = 1;
            $applicant->step_data = [
                'step' => 5,
                'completed' => true,
                'pilihan' => 'prestasi',
            ];
            $applicant->save();

            // 13) Staf Kesiswaan
            $userKesiswaan = $seedUser('kesiswaan.staging', 'kesiswaan.staging@smp-labum.sch.id', ['student_affairs']);
            $staffKesiswaan = Staff::firstOrNew(['user_id' => $userKesiswaan->id]);
            $staffKesiswaan->school_id = $schoolId;
            $staffKesiswaan->full_name = 'Staf Kesiswaan UAT';
            $staffKesiswaan->position = 'Staf Pembina Kesiswaan';
            $staffKesiswaan->unit = 'Kesiswaan';
            $staffKesiswaan->save();

            // 14) Staf Humas
            $userHumas = $seedUser('humas.staging', 'humas.staging@smp-labum.sch.id', ['public_relations']);
            $staffHumas = Staff::firstOrNew(['user_id' => $userHumas->id]);
            $staffHumas->school_id = $schoolId;
            $staffHumas->full_name = 'Staf Humas UAT';
            $staffHumas->position = 'Staf Hubungan Masyarakat';
            $staffHumas->unit = 'Humas';
            $staffHumas->save();

            // 6. Connect Rombel 7-A, Homeroom, Student, and Teaching Assignment
            $class7A = SchoolClass::firstOrNew([
                'academic_year_id' => $academicYear->id,
                'name' => '7-A',
            ]);
            $class7A->school_id = $schoolId;
            $class7A->grade_level_id = $gradeLevel7->id;
            $class7A->homeroom_teacher_id = $teacherWalas->id;
            $class7A->capacity = 36;
            $class7A->save();

            ClassStudent::firstOrCreate(
                [
                    'class_id' => $class7A->id,
                    'student_id' => $student->id,
                ],
                [
                    'joined_at' => '2026-07-01',
                ]
            );

            TeachingAssignment::firstOrCreate(
                [
                    'semester_id' => $semester->id,
                    'class_id' => $class7A->id,
                    'subject_id' => $subjectMtk->id,
                ],
                [
                    'teacher_id' => $teacherMtk->id,
                    'hours_per_week' => 4,
                ]
            );

            // 7. Initial Pending Records for Executive Approval Queue
            // a. Leave Request (menunggu)
            LeaveRequest::firstOrCreate(
                [
                    'student_id' => $student->id,
                    'start_date' => '2026-10-20',
                    'end_date' => '2026-10-21',
                ],
                [
                    'requested_by' => $userOrtu->id,
                    'leave_type' => 'izin',
                    'reason' => 'Keperluan keluarga di luar kota',
                    'status' => 'menunggu',
                    'decided_by' => null,
                ]
            );

            // b. Facility & Booking (diajukan)
            $facility = Facility::firstOrNew(['name' => 'Aula Graha Cakrawala Mini']);
            $facility->school_id = $schoolId;
            $facility->code = 'AULA-01';
            $facility->location = 'Gedung Utama Lantai 2';
            $facility->description = 'Aula serbaguna kapasitas 150 orang untuk rapat dan simulasi';
            $facility->facility_type = 'ruang';
            $facility->capacity = 150;
            $facility->requires_approval = true;
            $facility->is_active = true;
            $facility->status = 'tersedia';
            $facility->save();

            Booking::firstOrCreate(
                [
                    'facility_id' => $facility->id,
                    'start_at' => '2026-10-25 08:00:00',
                    'end_at' => '2026-10-25 12:00:00',
                ],
                [
                    'school_id' => $schoolId,
                    'requested_by' => $userSarpras->id,
                    'purpose' => 'Simulasi dan Briefing Penguji UAT SISTLABUM',
                    'attendees_count' => 50,
                    'status' => 'diajukan',
                    'decided_by' => null,
                ]
            );

            // c. Report Card (terkunci)
            ReportCard::firstOrCreate(
                [
                    'verify_token' => 'UAT-RAPOR-7A-001',
                ],
                [
                    'student_id' => $student->id,
                    'semester_id' => $semester->id,
                    'status' => 'terkunci',
                    'locked_at' => '2026-10-15 10:00:00',
                    'approved_by' => null,
                    'data_snapshot' => [
                        'student' => [
                            'nis' => $student->nis,
                            'nisn' => $student->nisn,
                            'name' => $student->full_name,
                        ],
                        'class' => '7-A',
                        'semester' => 'Ganjil',
                        'academic_year' => '2026/2027',
                        'grades' => [
                            [
                                'subject' => 'Matematika',
                                'score' => 88,
                                'predicate' => 'A',
                                'description' => 'Sangat menguasai konsep aljabar dan bilangan bulat.',
                            ],
                        ],
                    ],
                ]
            );
        });
    }
}
