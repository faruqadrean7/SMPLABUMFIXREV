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
use App\Models\PointType;
use App\Models\PPDBTrack;
use App\Models\PPDBWave;
use App\Models\Role;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\SiteSetting;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentPoint;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Ticket;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LocalRbacTestSeeder extends Seeder
{
    /**
     * Kata sandi seragam untuk seluruh akun pengujian lokal.
     */
    public const DEFAULT_PASSWORD = 'Password123!';

    /**
     * ID Sekolah Kanonis untuk SISTLABUM pengujian lokal.
     */
    public const DEFAULT_SCHOOL_ID = '00000000-0000-0000-0000-000000000001';

    public function run(): void
    {
        DB::transaction(function () {
            // 1. Jalankan DatabaseSeeder inti terlebih dahulu untuk memastikan permission dan role dasar terisi
            (new DatabaseSeeder)->run();

            $schoolId = self::DEFAULT_SCHOOL_ID;

            // Pastikan identitas sekolah tersimpan di SiteSetting
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

            // 2. Registrasi seluruh 19 Role resmi SISTLABUM
            $allRoles = [
                ['code' => 'super_admin', 'name' => 'Super Administrator'],
                ['code' => 'principal', 'name' => 'Kepala Sekolah'],
                ['code' => 'vice_principal', 'name' => 'Wakil Kepala Sekolah (Kurikulum)'],
                ['code' => 'admin_tu', 'name' => 'Admin Tata Usaha'],
                ['code' => 'teacher', 'name' => 'Guru Mata Pelajaran'],
                ['code' => 'homeroom', 'name' => 'Wali Kelas'],
                ['code' => 'counselor', 'name' => 'Guru BK (Bimbingan Konseling)'],
                ['code' => 'facility_staff', 'name' => 'Staf Sarana & Prasarana'],
                ['code' => 'ppdb_committee', 'name' => 'Panitia PPDB'],
                ['code' => 'librarian', 'name' => 'Pustakawan'],
                ['code' => 'student_affairs', 'name' => 'Staf Kesiswaan'],
                ['code' => 'public_relations', 'name' => 'Staf Humas'],
                ['code' => 'operator_cms', 'name' => 'Operator CMS / Website'],
                ['code' => 'committee', 'name' => 'Komite Sekolah (Read Only)'],
                ['code' => 'supervisor', 'name' => 'Pengawas Sekolah (Read Only)'],
                ['code' => 'treasurer', 'name' => 'Bendahara Sekolah'],
                ['code' => 'student', 'name' => 'Siswa'],
                ['code' => 'guardian', 'name' => 'Orang Tua / Wali Murid'],
                ['code' => 'guest', 'name' => 'Tamu / Calon Siswa'],
            ];

            $roleModels = [];
            foreach ($allRoles as $r) {
                $roleModels[$r['code']] = Role::firstOrCreate(
                    ['code' => $r['code']],
                    ['name' => $r['name'], 'is_system' => true]
                );
            }

            // 3. Tambahkan pemetaan izin untuk role tambahan
            $assignPerms = function (string $roleCode, array $permCodes) use ($roleModels) {
                if (! isset($roleModels[$roleCode])) {
                    return;
                }
                $permIds = Permission::whereIn('code', $permCodes)->pluck('id')->toArray();
                if (! empty($permIds)) {
                    $roleModels[$roleCode]->permissions()->syncWithoutDetaching($permIds);
                }
            };

            $assignPerms('vice_principal', [
                'dashboard.executive',
                'executive.dashboard.view',
                'reports.view',
                'academic.schedule.manage',
                'academic.schedule.read',
                'academic.journal.read',
                'academic.attendance.read',
                'academic.grade.read',
                'academic.grade.write',
                'cbt.exam.manage',
                'cbt.bank.write',
                'master.academic_year.manage',
                'master.student.read',
                'master.teacher.manage',
                'master.class.manage',
                'master.subject.manage',
                'master.promotion.manage',
            ]);

            $assignPerms('operator_cms', [
                'cms.site.manage',
                'cms.post.manage',
                'cms.gallery.manage',
                'cms.event.manage',
                'cms.download.manage',
                'cms.page.manage',
            ]);

            $assignPerms('committee', [
                'reports.view',
                'dashboard.executive',
                'executive.dashboard.view',
            ]);

            $assignPerms('supervisor', [
                'reports.view',
                'dashboard.executive',
                'executive.dashboard.view',
            ]);

            $assignPerms('treasurer', [
                'reports.view',
            ]);

            $assignPerms('student_affairs', [
                'student.point.read',
                'student.point.write',
                'master.student.read',
                'reports.view',
            ]);

            $assignPerms('public_relations', [
                'guest.book.read',
                'guest.book.manage',
                'complaint.read',
                'complaint.manage',
                'cms.site.manage',
                'cms.post.manage',
                'cms.gallery.manage',
                'cms.event.manage',
                'cms.download.manage',
                'cms.page.manage',
            ]);

            // 4. Data Master Akademik Pendukung
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
                    'name' => 'Semester Ganjil',
                ],
                [
                    'start_date' => '2026-07-01',
                    'end_date' => '2026-12-31',
                    'is_active' => true,
                ]
            );

            $gradeLevel7 = GradeLevel::firstOrCreate(
                ['name' => 'Kelas 7'],
                ['school_id' => $schoolId, 'sort_order' => 7]
            );

            $subjectMtk = Subject::firstOrCreate(
                ['code' => 'MTK-7'],
                [
                    'school_id' => $schoolId,
                    'name' => 'Matematika',
                    'subject_group' => 'muatan_nasional',
                ]
            );

            $subjectIpa = Subject::firstOrCreate(
                ['code' => 'IPA-7'],
                [
                    'school_id' => $schoolId,
                    'name' => 'Ilmu Pengetahuan Alam',
                    'subject_group' => 'muatan_nasional',
                ]
            );

            // Fasilitas & Ruangan
            $facility = Facility::firstOrCreate(
                ['code' => 'AULA-01'],
                [
                    'school_id' => $schoolId,
                    'name' => 'Aula Graha Cakrawala Mini',
                    'location' => 'Gedung Utama Lantai 2',
                    'description' => 'Aula serbaguna kapasitas 150 orang',
                    'facility_type' => 'ruang',
                    'capacity' => 150,
                    'requires_approval' => true,
                    'is_active' => true,
                    'status' => 'tersedia',
                ]
            );

            $room = Room::firstOrCreate(
                ['code' => 'R-7A'],
                [
                    'school_id' => $schoolId,
                    'name' => 'Ruang Kelas 7-A',
                    'room_type' => 'kelas',
                    'capacity' => 36,
                ]
            );

            $timeSlot1 = TimeSlot::first() ?? TimeSlot::firstOrCreate(
                ['name' => 'Jam ke-1'],
                [
                    'school_id' => $schoolId,
                    'start_time' => '07:00',
                    'end_time' => '07:45',
                    'day_type' => 'reguler',
                ]
            );

            // PPDB Master
            $wave = PPDBWave::firstOrCreate(
                ['name' => 'Gelombang 1 2026'],
                [
                    'school_id' => $schoolId,
                    'open_at' => '2026-01-01 00:00:00',
                    'close_at' => '2027-06-30 23:59:59',
                    'result_publish_at' => '2027-07-05 10:00:00',
                    'status' => 'aktif',
                ]
            );

            PPDBTrack::firstOrCreate(
                ['wave_id' => $wave->id, 'code' => 'reguler'],
                [
                    'name' => 'Jalur Reguler',
                    'quota' => 120,
                    'score_formula' => ['rapor' => 0.7, 'tes' => 0.3],
                ]
            );

            PPDBTrack::firstOrCreate(
                ['wave_id' => $wave->id, 'code' => 'prestasi'],
                [
                    'name' => 'Jalur Prestasi',
                    'quota' => 50,
                    'score_formula' => ['rapor' => 0.6, 'prestasi' => 0.4],
                ]
            );

            PPDBTrack::firstOrCreate(
                ['wave_id' => $wave->id, 'code' => 'afirmasi'],
                [
                    'name' => 'Jalur Afirmasi',
                    'quota' => 40,
                    'score_formula' => ['rapor' => 1.0],
                ]
            );

            PPDBTrack::firstOrCreate(
                ['wave_id' => $wave->id, 'code' => 'tahfidz'],
                [
                    'name' => 'Jalur Tahfidz Qur\'an',
                    'quota' => 15,
                    'score_formula' => ['tahfidz' => 0.6, 'rapor' => 0.4],
                ]
            );

            // 5. Helper Pembuat / Pengupdate User
            $passwordHash = Hash::make(self::DEFAULT_PASSWORD);

            $upsertUser = function (string $username, string $email, array $roleCodes) use ($schoolId, $passwordHash): User {
                $user = User::where('username', $username)->first()
                    ?? User::where('email', $email)->first()
                    ?? new User;
                $user->username = $username;
                $user->email = $email;
                $user->school_id = $schoolId;
                $user->password_hash = $passwordHash;
                $user->status = 'active';
                $user->must_change_password = false;
                $user->save();

                $roleIds = Role::whereIn('code', $roleCodes)->pluck('id')->toArray();
                $user->roles()->sync($roleIds);

                return $user;
            };

            // Update user superadmin yang sudah ada
            $userSuperAdmin = User::where('username', 'superadmin')->first();
            if ($userSuperAdmin) {
                $userSuperAdmin->password_hash = $passwordHash;
                $userSuperAdmin->must_change_password = false;
                $userSuperAdmin->status = 'active';
                $userSuperAdmin->save();
            }

            // Buat alias 'admin' untuk super_admin
            $userAdmin = $upsertUser('admin', 'admin@smp-labum.sch.id', ['super_admin']);

            // 6. Buat Akun & Profil untuk Seluruh 19 RBAC Roles

            // (1) Kepala Sekolah
            $userKepsek = $upsertUser('kepsek', 'kepsek@labschool.sch.id', ['principal']);
            $teacherKepsek = Teacher::firstOrNew(['user_id' => $userKepsek->id]);
            $teacherKepsek->school_id = $schoolId;
            $teacherKepsek->nip = '197501012000031001';
            $teacherKepsek->nuptk = '1234567890123456';
            $teacherKepsek->full_name = 'Drs. H. Tamyis Suliantoro, M.Pd.';
            $teacherKepsek->position = 'Kepala Sekolah';
            $teacherKepsek->status = 'pns';
            $teacherKepsek->save();

            // (2) Wakil Kepala Sekolah Kurikulum
            $userWaka = $upsertUser('wakakurikulum', 'kurikulum@labschool.sch.id', ['vice_principal', 'teacher']);
            $teacherWaka = Teacher::firstOrNew(['user_id' => $userWaka->id]);
            $teacherWaka->school_id = $schoolId;
            $teacherWaka->nip = '198002022005011002';
            $teacherWaka->nuptk = '2345678901234567';
            $teacherWaka->full_name = 'Wakil Kepala Kurikulum, S.Pd., M.Pd.';
            $teacherWaka->position = 'Waka Kurikulum';
            $teacherWaka->status = 'pns';
            $teacherWaka->save();

            // (3) Admin Tata Usaha (TU)
            $userTu = $upsertUser('admintu', 'tu@labschool.sch.id', ['admin_tu']);
            $staffTu = Staff::firstOrNew(['user_id' => $userTu->id]);
            $staffTu->school_id = $schoolId;
            $staffTu->full_name = 'Staf Tata Usaha Sekolah';
            $staffTu->position = 'Kepala Urusan Tata Usaha';
            $staffTu->unit = 'Tata Usaha';
            $staffTu->save();

            // (4) Wali Kelas VII A
            $userWalas = $upsertUser('walikelas', 'walikelas.7a@labschool.sch.id', ['homeroom', 'teacher']);
            $teacherWalas = Teacher::firstOrNew(['user_id' => $userWalas->id]);
            $teacherWalas->school_id = $schoolId;
            $teacherWalas->nip = '198503032010012003';
            $teacherWalas->nuptk = '3456789012345678';
            $teacherWalas->full_name = 'Wali Kelas VII A, S.Pd.';
            $teacherWalas->position = 'Wali Kelas VII A / Guru IPA';
            $teacherWalas->status = 'pns';
            $teacherWalas->save();

            // Setup Rombel VII A dan tautkan Wali Kelas
            $class7A = SchoolClass::firstOrNew(['name' => 'VII A']);
            $class7A->academic_year_id = $academicYear->id;
            $class7A->grade_level_id = $gradeLevel7->id;
            $class7A->homeroom_teacher_id = $teacherWalas->id;
            $class7A->capacity = 36;
            $class7A->save();

            // (5) Guru Mapel Matematika
            $userGuruMtk = $upsertUser('gurumtk', 'guru.mtk@labschool.sch.id', ['teacher']);
            $teacherMtk = Teacher::firstOrNew(['user_id' => $userGuruMtk->id]);
            $teacherMtk->school_id = $schoolId;
            $teacherMtk->nip = '199004042015021004';
            $teacherMtk->nuptk = '4567890123456789';
            $teacherMtk->full_name = 'Guru Matematika, S.Pd.';
            $teacherMtk->position = 'Guru Matematika';
            $teacherMtk->status = 'honorer';
            $teacherMtk->save();

            // Penugasan Mengajar Matematika di VII A
            $teachingMtk = TeachingAssignment::firstOrCreate(
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

            // Jadwal Pelajaran Matematika
            Schedule::firstOrCreate(
                [
                    'semester_id' => $semester->id,
                    'class_id' => $class7A->id,
                    'subject_id' => $subjectMtk->id,
                    'day_of_week' => 1,
                    'time_slot_id' => $timeSlot1->id,
                ],
                [
                    'teacher_id' => $teacherMtk->id,
                    'room_id' => $room->id,
                ]
            );

            // (6) Guru BK (Bimbingan Konseling)
            $userBk = $upsertUser('gurubk', 'bk@labschool.sch.id', ['counselor']);
            $teacherBk = Teacher::firstOrNew(['user_id' => $userBk->id]);
            $teacherBk->school_id = $schoolId;
            $teacherBk->nip = '199205052018032005';
            $teacherBk->nuptk = '5678901234567890';
            $teacherBk->full_name = 'Guru BK Konseling, S.Psi.';
            $teacherBk->position = 'Guru Bimbingan & Konseling';
            $teacherBk->status = 'pns';
            $teacherBk->save();

            // (7) Staf Sarana & Prasarana
            $userSarpras = $upsertUser('stafsarpras', 'sarpras@labschool.sch.id', ['facility_staff']);
            $staffSarpras = Staff::firstOrNew(['user_id' => $userSarpras->id]);
            $staffSarpras->school_id = $schoolId;
            $staffSarpras->full_name = 'Staf Sarana & Prasarana';
            $staffSarpras->position = 'Pengelola Sarpras & Aset';
            $staffSarpras->unit = 'Sarana & Prasarana';
            $staffSarpras->save();

            // (8) Panitia PPDB
            $userPpdb = $upsertUser('panitiappdb', 'ppdb@labschool.sch.id', ['ppdb_committee']);
            $staffPpdb = Staff::firstOrNew(['user_id' => $userPpdb->id]);
            $staffPpdb->school_id = $schoolId;
            $staffPpdb->full_name = 'Panitia PPDB Sekolah';
            $staffPpdb->position = 'Koordinator Verifikasi PPDB';
            $staffPpdb->unit = 'Panitia PPDB';
            $staffPpdb->save();

            // (9) Pustakawan
            $userPustaka = $upsertUser('pustakawan', 'perpustakaan@labschool.sch.id', ['librarian']);
            $staffPustaka = Staff::firstOrNew(['user_id' => $userPustaka->id]);
            $staffPustaka->school_id = $schoolId;
            $staffPustaka->full_name = 'Pustakawan Sekolah';
            $staffPustaka->position = 'Pengelola Perpustakaan';
            $staffPustaka->unit = 'Perpustakaan';
            $staffPustaka->save();

            // (10) Staf Kesiswaan
            $userKesiswaan = $upsertUser('stafkesiswaan', 'kesiswaan@labschool.sch.id', ['student_affairs']);
            $staffKesiswaan = Staff::firstOrNew(['user_id' => $userKesiswaan->id]);
            $staffKesiswaan->school_id = $schoolId;
            $staffKesiswaan->full_name = 'Staf Pembina Kesiswaan';
            $staffKesiswaan->position = 'Pembina OSIS & Kesiswaan';
            $staffKesiswaan->unit = 'Kesiswaan';
            $staffKesiswaan->save();

            // (11) Staf Humas
            $userHumas = $upsertUser('stafhumas', 'humas@labschool.sch.id', ['public_relations']);
            $staffHumas = Staff::firstOrNew(['user_id' => $userHumas->id]);
            $staffHumas->school_id = $schoolId;
            $staffHumas->full_name = 'Staf Hubungan Masyarakat';
            $staffHumas->position = 'Pranata Humas & Pengaduan';
            $staffHumas->unit = 'Humas';
            $staffHumas->save();

            // (12) Operator CMS
            $userCms = $upsertUser('operatorcms', 'cms@labschool.sch.id', ['operator_cms']);
            $staffCms = Staff::firstOrNew(['user_id' => $userCms->id]);
            $staffCms->school_id = $schoolId;
            $staffCms->full_name = 'Operator Portal CMS';
            $staffCms->position = 'Webmaster / CMS Operator';
            $staffCms->unit = 'TIK & Publikasi';
            $staffCms->save();

            // (13) Komite Sekolah (Read Only)
            $userKomite = $upsertUser('komitesekolah', 'komite@labschool.sch.id', ['committee']);
            $staffKomite = Staff::firstOrNew(['user_id' => $userKomite->id]);
            $staffKomite->school_id = $schoolId;
            $staffKomite->full_name = 'Ketua Komite Sekolah';
            $staffKomite->position = 'Pengurus Komite Sekolah';
            $staffKomite->unit = 'Komite Sekolah';
            $staffKomite->save();

            // (14) Pengawas Sekolah (Read Only)
            $userPengawas = $upsertUser('pengawassekolah', 'pengawas@labschool.sch.id', ['supervisor']);
            $staffPengawas = Staff::firstOrNew(['user_id' => $userPengawas->id]);
            $staffPengawas->school_id = $schoolId;
            $staffPengawas->full_name = 'Pengawas Satuan Pendidikan';
            $staffPengawas->position = 'Pengawas Sekolah Pembina';
            $staffPengawas->unit = 'Dinas Pendidikan';
            $staffPengawas->save();

            // (15) Bendahara Sekolah
            $userBendahara = $upsertUser('bendahara', 'bendahara@labschool.sch.id', ['treasurer']);
            $staffBendahara = Staff::firstOrNew(['user_id' => $userBendahara->id]);
            $staffBendahara->school_id = $schoolId;
            $staffBendahara->full_name = 'Bendahara Sekolah';
            $staffBendahara->position = 'Bendahara Operasional';
            $staffBendahara->unit = 'Keuangan';
            $staffBendahara->save();

            // (16) Siswa Aktif
            $userSiswa = $upsertUser('siswa', 'siswa.ahmad@labschool.sch.id', ['student']);
            $student = Student::firstOrNew(['user_id' => $userSiswa->id]);
            $student->school_id = $schoolId;
            $student->nis = '26001';
            $student->nisn = '0099001001';
            $student->nik_enc = '3578010101090001';
            $student->full_name = 'Ahmad Siswa Pratama';
            $student->gender = 'L';
            $student->birth_place = 'Malang';
            $student->birth_date = '2012-05-15';
            $student->religion = 'Islam';
            $student->address = 'Jl. Simpang Bogor No. 1, Malang';
            $student->status = 'aktif';
            $student->entry_date = '2026-07-01';
            $student->save();

            // Masukkan Siswa ke Rombel VII A
            ClassStudent::firstOrCreate(
                [
                    'class_id' => $class7A->id,
                    'student_id' => $student->id,
                ],
                [
                    'joined_at' => '2026-07-01',
                ]
            );

            // (17) Orang Tua / Wali Murid
            $userOrtu = $upsertUser('ortu', 'ortu.ahmad@labschool.sch.id', ['guardian']);
            $guardian = Guardian::firstOrNew(['user_id' => $userOrtu->id]);
            $guardian->school_id = $schoolId;
            $guardian->full_name = 'Bapak Budi Santoso (Orang Tua Ahmad)';
            $guardian->phone = '081234567890';
            $guardian->occupation = 'Karyawan Swasta';
            $guardian->address = 'Jl. Simpang Bogor No. 1, Malang';
            $guardian->save();

            // Tautkan Orang Tua ke Siswa Ahmad
            if (! $student->guardians()->where('guardian_id', $guardian->id)->exists()) {
                $student->guardians()->attach($guardian->id, [
                    'relationship' => 'ayah',
                    'is_primary' => true,
                ]);
            }

            // (18) Calon Siswa / Tamu PPDB
            $userCalon = $upsertUser('calonsiswa', 'calonsiswa.rizky@gmail.com', ['guest']);
            $applicant = Applicant::firstOrNew(['user_id' => $userCalon->id]);
            $applicant->wave_id = $wave->id;
            $applicant->track_id = $track->id;
            $applicant->registration_no = 'PPDB-2026-0001';
            $applicant->nik_enc = '3578010202100002';
            $applicant->full_name = 'Rizky Calon Siswa';
            $applicant->birth_date = '2013-01-20';
            $applicant->status = 'submitted';
            $applicant->score = 88.50;
            $applicant->rank = 1;
            $applicant->step_data = [
                'step' => 5,
                'completed' => true,
                'pilihan' => 'prestasi',
            ];
            $applicant->save();

            // 7. Data Simulasi Alur Kerja Terpadu (Untuk Pengujian Fitur Nyata)
            // Permohonan izin siswa dari orang tua
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

            // Permohonan sewa aula dari staf sarpras
            Booking::firstOrCreate(
                [
                    'facility_id' => $facility->id,
                    'start_at' => '2026-10-25 08:00:00',
                    'end_at' => '2026-10-25 12:00:00',
                ],
                [
                    'school_id' => $schoolId,
                    'requested_by' => $userSarpras->id,
                    'purpose' => 'Rapat Koordinasi dan Simulasi Pengujian Lokal SISTLABUM',
                    'attendees_count' => 40,
                    'status' => 'diajukan',
                    'decided_by' => null,
                ]
            );

            // Poin prestasi siswa
            $pointType = PointType::firstOrCreate(
                ['name' => 'Juara 1 Lomba Sains'],
                [
                    'school_id' => $schoolId,
                    'kind' => 'penghargaan',
                    'points' => 20,
                    'severity' => 'ringan',
                ]
            );

            StudentPoint::firstOrCreate(
                [
                    'student_id' => $student->id,
                    'point_type_id' => $pointType->id,
                ],
                [
                    'recorded_by' => $userKesiswaan->id,
                    'point_date' => '2026-10-10',
                    'notes' => 'Juara 1 Lomba Sains Tingkat Kota Malang (Piagam & Poin Prestasi)',
                ]
            );

            // Tiket fasilitas helpdesk
            Ticket::firstOrCreate(
                [
                    'ticket_number' => 'TCK-2026-0001',
                ],
                [
                    'school_id' => $schoolId,
                    'reporter_id' => $userWalas->id,
                    'title' => 'Perbaikan LCD Proyektor Ruang VII A',
                    'priority' => 'tinggi',
                    'description' => 'Proyektor di ruang kelas VII A sering berkedip saat KBM berlangsung.',
                    'status' => 'terbuka',
                ]
            );
        });
    }
}
