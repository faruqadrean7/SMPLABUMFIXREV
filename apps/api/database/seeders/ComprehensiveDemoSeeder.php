<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentProgramProfile;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * SEEDER LENGKAP KREDENSIAL AKUN DEMO CLIENT SISTLABUM
 *
 * Mencakup:
 * 1. 12 Akun Siswa & 12 Akun Orang Tua (Kelas 7, 8, 9 x 4 Program: Reguler, Tahfidz, ICP, Inklusi).
 * 2. 16 Akun Guru (Seluruh Mata Pelajaran + BK + GPK + Tahfidz + Cambridge).
 * 3. 10 Akun Staf Tenaga Kependidikan (TU, Sarpras, PPDB, Perpustakaan, Keuangan, Kesiswaan, Humas, CMS, IT, Keamanan).
 * 4. 9 Akun Pimpinan, Yayasan, Komite, & Pengawas (Yayasan, Komite, Pengawas, Kepsek, 4 Waka, Super Admin).
 *
 * Password seragam: Password123!
 */
class ComprehensiveDemoSeeder extends Seeder
{
    public const DEFAULT_PASSWORD = 'Password123!';
    public const SCHOOL_ID = '00000000-0000-0000-0000-000000000001';

    public function run(): void
    {
        DB::transaction(function () {
            $schoolId = self::SCHOOL_ID;
            $passwordHash = Hash::make(self::DEFAULT_PASSWORD);

            // 1. Pastikan Data Sekolah & Tahun Ajaran Ada
            School::firstOrCreate(
                ['id' => $schoolId],
                [
                    'name' => 'SMP Laboratorium Universitas Negeri Malang',
                    'npsn' => '20533807',
                    'address' => 'Jl. Simpang Bogor No. T-7, Sumbersari, Kec. Lowokwaru, Kota Malang, Jawa Timur 65145',
                    'phone' => '(0341) 551312',
                    'email' => 'smplabum@gmail.com',
                    'timezone' => 'Asia/Jakarta',
                    'is_active' => true,
                ]
            );

            $academicYear = AcademicYear::where('is_active', true)->first()
                ?? AcademicYear::firstOrCreate(
                    ['name' => '2024/2025'],
                    ['start_date' => '2024-07-15', 'end_date' => '2025-06-20', 'is_active' => true]
                );

            // 2. Registrasi Role Models
            $roleCodes = [
                'super_admin', 'principal', 'vice_principal', 'admin_tu',
                'teacher', 'homeroom', 'counselor', 'facility_staff',
                'ppdb_committee', 'librarian', 'student_affairs', 'public_relations',
                'operator_cms', 'committee', 'supervisor', 'treasurer',
                'student', 'guardian', 'staff', 'guest'
            ];

            $roles = [];
            foreach ($roleCodes as $code) {
                $roles[$code] = Role::firstOrCreate(
                    ['code' => $code],
                    ['name' => ucwords(str_replace('_', ' ', $code)), 'is_system' => true]
                );
            }

            // Helper Upsert User
            $upsertUser = function (string $username, string $email, array $roleList) use ($schoolId, $passwordHash, $roles): User {
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

                $roleIds = [];
                foreach ($roleList as $rCode) {
                    if (isset($roles[$rCode])) {
                        $roleIds[] = $roles[$rCode]->id;
                    }
                }
                $user->roles()->sync($roleIds);

                return $user;
            };

            // 3. Grade Levels (7, 8, 9)
            $grade7 = GradeLevel::firstOrCreate(['name' => 'Kelas VII'], ['school_id' => $schoolId, 'sort_order' => 1]);
            $grade8 = GradeLevel::firstOrCreate(['name' => 'Kelas VIII'], ['school_id' => $schoolId, 'sort_order' => 2]);
            $grade9 = GradeLevel::firstOrCreate(['name' => 'Kelas IX'], ['school_id' => $schoolId, 'sort_order' => 3]);

            $grades = [7 => $grade7, 8 => $grade8, 9 => $grade9];

            // 4. SEED SISWA & ORANG TUA: 3 Tingkat x 4 Program
            $studentProfiles = [
                // TINGKAT 7
                [
                    'grade' => 7,
                    'program' => 'reguler',
                    'username' => 'siswa.7.reguler',
                    'alt_username' => 'siswa.reguler',
                    'name' => 'Bintang Pratama',
                    'nis' => '26701',
                    'nisn' => '0099007101',
                    'class_name' => 'VII-A Reguler',
                    'parent_user' => 'ortu.7.reguler',
                    'alt_parent' => 'ortu.reguler',
                    'parent_name' => 'Bpk. Agus Pratama',
                    'meta' => [
                        'kktp_summary' => ['average_score' => 88.5, 'tuntas_count' => 11, 'remedial_count' => 0],
                        'p5_project' => ['title' => 'Gaya Hidup Berkelanjutan: Bank Sampah Sekolah', 'theme' => 'Lingkungan Hidup'],
                        'ekskul' => [['name' => 'Robotika', 'day' => 'Kamis']],
                    ],
                ],
                [
                    'grade' => 7,
                    'program' => 'tahfidz',
                    'username' => 'siswa.7.tahfidz',
                    'alt_username' => 'siswa.tahfidz',
                    'name' => 'Muhammad Hafizh Al-Fatih',
                    'nis' => '26702',
                    'nisn' => '0099007102',
                    'class_name' => 'VII-B Tahfidz',
                    'parent_user' => 'ortu.7.tahfidz',
                    'alt_parent' => 'ortu.tahfidz',
                    'parent_name' => 'Bpk. Rahmat Al-Fatih',
                    'meta' => [
                        'target_juz' => 5,
                        'current_juz' => 3,
                        'current_surah' => 'Al-Mulk: 1-30',
                        'last_tasmi_grade' => 'Mumtaz',
                        'tasmi_evaluator' => 'Ustadz Abdullah, S.Pd.I',
                    ],
                ],
                [
                    'grade' => 7,
                    'program' => 'icp',
                    'username' => 'siswa.7.icp',
                    'alt_username' => 'siswa.icp',
                    'name' => 'Aisyah Valerie Zahra',
                    'nis' => '26703',
                    'nisn' => '0099007103',
                    'class_name' => 'VII-C ICP',
                    'parent_user' => 'ortu.7.icp',
                    'alt_parent' => 'ortu.icp',
                    'parent_name' => 'Ibu Dian Valerie',
                    'meta' => [
                        'cambridge_candidate_id' => 'ID-MAL-2026-042',
                        'checkpoint_readiness' => [
                            'english' => ['score' => 5.5, 'target' => 6.0, 'status' => 'Ready'],
                            'math' => ['score' => 5.8, 'target' => 6.0, 'status' => 'Exceeding'],
                            'science' => ['score' => 5.2, 'target' => 6.0, 'status' => 'Ready'],
                        ],
                    ],
                ],
                [
                    'grade' => 7,
                    'program' => 'inklusi',
                    'username' => 'siswa.7.inklusi',
                    'alt_username' => 'siswa.inklusi',
                    'name' => 'Rian Ananda Putra',
                    'nis' => '26704',
                    'nisn' => '0099007104',
                    'class_name' => 'VII-D Inklusi',
                    'parent_user' => 'ortu.7.inklusi',
                    'alt_parent' => 'ortu.inklusi',
                    'parent_name' => 'Bpk. Hendro Putra',
                    'meta' => [
                        'gpk' => ['name' => 'Ibu Ratna Dewi, S.Pd (GPK)', 'room' => 'Ruang Sumber Inklusi'],
                        'ppi_milestones' => [
                            ['title' => 'Membaca teks naratif pendek', 'is_completed' => true],
                            ['title' => 'Fokus konsentrasi 20 menit', 'is_completed' => true],
                        ],
                    ],
                ],

                // TINGKAT 8
                [
                    'grade' => 8,
                    'program' => 'reguler',
                    'username' => 'siswa.8.reguler',
                    'name' => 'Dimas Satria Wibowo',
                    'nis' => '25801',
                    'nisn' => '0099008101',
                    'class_name' => 'VIII-A Reguler',
                    'parent_user' => 'ortu.8.reguler',
                    'parent_name' => 'Bpk. Bambang Wibowo',
                    'meta' => [
                        'kktp_summary' => ['average_score' => 90.2, 'tuntas_count' => 11, 'remedial_count' => 0],
                        'p5_project' => ['title' => 'Suara Demokrasi: Simulasi Pemilu OSIS Berintegritas', 'theme' => 'Demokrasi'],
                    ],
                ],
                [
                    'grade' => 8,
                    'program' => 'tahfidz',
                    'username' => 'siswa.8.tahfidz',
                    'name' => 'Fatimah Az-Zahra',
                    'nis' => '25802',
                    'nisn' => '0099008102',
                    'class_name' => 'VIII-B Tahfidz',
                    'parent_user' => 'ortu.8.tahfidz',
                    'parent_name' => 'Bpk. Usman Mansyur',
                    'meta' => [
                        'target_juz' => 8,
                        'current_juz' => 6,
                        'current_surah' => 'Al-Waqi\'ah: 1-96',
                        'last_tasmi_grade' => 'Mumtaz',
                        'tasmi_evaluator' => 'Ustadz Abdullah, S.Pd.I',
                    ],
                ],
                [
                    'grade' => 8,
                    'program' => 'icp',
                    'username' => 'siswa.8.icp',
                    'name' => 'Jonathan Kevin Wijaya',
                    'nis' => '25803',
                    'nisn' => '0099008103',
                    'class_name' => 'VIII-C ICP',
                    'parent_user' => 'ortu.8.icp',
                    'parent_name' => 'Bpk. David Wijaya',
                    'meta' => [
                        'cambridge_candidate_id' => 'ID-MAL-2025-081',
                        'checkpoint_readiness' => [
                            'english' => ['score' => 5.8, 'target' => 6.0, 'status' => 'Exceeding'],
                            'math' => ['score' => 6.0, 'target' => 6.0, 'status' => 'Exceeding'],
                            'science' => ['score' => 5.6, 'target' => 6.0, 'status' => 'Ready'],
                        ],
                    ],
                ],
                [
                    'grade' => 8,
                    'program' => 'inklusi',
                    'username' => 'siswa.8.inklusi',
                    'name' => 'Nabila Putri Kirana',
                    'nis' => '25804',
                    'nisn' => '0099008104',
                    'class_name' => 'VIII-D Inklusi',
                    'parent_user' => 'ortu.8.inklusi',
                    'parent_name' => 'Ibu Sri Rahayu',
                    'meta' => [
                        'gpk' => ['name' => 'Ibu Ratna Dewi, S.Pd (GPK)', 'room' => 'Ruang Sumber Inklusi'],
                        'ppi_milestones' => [
                            ['title' => 'Keterampilan vokasional kriya kain perca', 'is_completed' => true],
                        ],
                    ],
                ],

                // TINGKAT 9
                [
                    'grade' => 9,
                    'program' => 'reguler',
                    'username' => 'siswa.9.reguler',
                    'name' => 'Rizky Arya Ramadhan',
                    'nis' => '24901',
                    'nisn' => '0099009101',
                    'class_name' => 'IX-A Reguler',
                    'parent_user' => 'ortu.9.reguler',
                    'parent_name' => 'Bpk. Joko Ramadhan',
                    'meta' => [
                        'kktp_summary' => ['average_score' => 92.0, 'tuntas_count' => 11, 'remedial_count' => 0],
                        'p5_project' => ['title' => 'Kewirausahaan: Expo Gelar Karya Siswa Terpadu', 'theme' => 'Kewirausahaan'],
                    ],
                ],
                [
                    'grade' => 9,
                    'program' => 'tahfidz',
                    'username' => 'siswa.9.tahfidz',
                    'name' => 'Ahmad Zaki Mubarak',
                    'nis' => '24902',
                    'nisn' => '0099009102',
                    'class_name' => 'IX-B Tahfidz',
                    'parent_user' => 'ortu.9.tahfidz',
                    'parent_name' => 'Bpk. Syarif Mubarak',
                    'meta' => [
                        'target_juz' => 10,
                        'current_juz' => 10,
                        'current_surah' => 'Al-Baqarah: 1-141',
                        'last_tasmi_grade' => 'Mumtaz',
                        'tasmi_evaluator' => 'Ustadz Abdullah, S.Pd.I',
                    ],
                ],
                [
                    'grade' => 9,
                    'program' => 'icp',
                    'username' => 'siswa.9.icp',
                    'name' => 'Clara Stephanie Putri',
                    'nis' => '24903',
                    'nisn' => '0099009103',
                    'class_name' => 'IX-C ICP',
                    'parent_user' => 'ortu.9.icp',
                    'parent_name' => 'Bpk. Robertus Hendra',
                    'meta' => [
                        'cambridge_candidate_id' => 'ID-MAL-2024-019',
                        'checkpoint_readiness' => [
                            'english' => ['score' => 6.0, 'target' => 6.0, 'status' => 'Exceeding'],
                            'math' => ['score' => 6.0, 'target' => 6.0, 'status' => 'Exceeding'],
                            'science' => ['score' => 5.9, 'target' => 6.0, 'status' => 'Exceeding'],
                        ],
                    ],
                ],
                [
                    'grade' => 9,
                    'program' => 'inklusi',
                    'username' => 'siswa.9.inklusi',
                    'name' => 'Fajar Bagus Saputra',
                    'nis' => '24904',
                    'nisn' => '0099009104',
                    'class_name' => 'IX-D Inklusi',
                    'parent_user' => 'ortu.9.inklusi',
                    'parent_name' => 'Ibu Endang Saputra',
                    'meta' => [
                        'gpk' => ['name' => 'Ibu Ratna Dewi, S.Pd (GPK)', 'room' => 'Ruang Sumber Inklusi'],
                        'ppi_milestones' => [
                            ['title' => 'Kemandirian transisi sekolah menengah', 'is_completed' => true],
                        ],
                    ],
                ],
            ];

            foreach ($studentProfiles as $sp) {
                // Buat Akun Siswa
                $uSiswa = $upsertUser($sp['username'], $sp['username'].'@labschool.sch.id', ['student']);
                if (isset($sp['alt_username'])) {
                    $uSiswaAlt = $upsertUser($sp['alt_username'], $sp['alt_username'].'@labschool.sch.id', ['student']);
                }

                $student = Student::updateOrCreate(
                    ['nis' => $sp['nis']],
                    [
                        'user_id' => $uSiswa->id,
                        'school_id' => $schoolId,
                        'nisn' => $sp['nisn'],
                        'nik_enc' => '357301010199'.substr($sp['nisn'], -4),
                        'full_name' => $sp['name'],
                        'gender' => 'L',
                        'birth_place' => 'Malang',
                        'birth_date' => '201'.(12 - ($sp['grade'] - 7)).'-05-10',
                        'status' => 'aktif',
                        'entry_date' => '202'.(4 - ($sp['grade'] - 7)).'-07-15',
                        'program_type' => $sp['program'],
                    ]
                );

                // Setup Kelas Rombel
                $schoolClass = SchoolClass::firstOrCreate(
                    ['name' => $sp['class_name'], 'academic_year_id' => $academicYear->id],
                    [
                        'grade_level_id' => $grades[$sp['grade']]->id,
                        'capacity' => 32,
                        'program_type' => $sp['program'],
                    ]
                );

                ClassStudent::firstOrCreate(
                    ['class_id' => $schoolClass->id, 'student_id' => $student->id],
                    ['joined_at' => '2024-07-15']
                );

                StudentProgramProfile::updateOrCreate(
                    ['student_id' => $student->id],
                    [
                        'program_type' => $sp['program'],
                        'meta_data' => $sp['meta'],
                    ]
                );

                // Buat Akun Orang Tua
                $uOrtu = $upsertUser($sp['parent_user'], $sp['parent_user'].'@labschool.sch.id', ['guardian']);
                if (isset($sp['alt_parent'])) {
                    $uOrtuAlt = $upsertUser($sp['alt_parent'], $sp['alt_parent'].'@labschool.sch.id', ['guardian']);
                }

                $guardian = Guardian::firstOrCreate(
                    ['user_id' => $uOrtu->id],
                    [
                        'school_id' => $schoolId,
                        'full_name' => $sp['parent_name'],
                        'phone' => '081234567'.substr($sp['nisn'], -3),
                        'occupation' => 'Wiraswasta / Profesional',
                        'address' => 'Jl. Veteran No. '.$sp['grade'].', Malang',
                    ]
                );

                if (! $student->guardians()->where('guardian_id', $guardian->id)->exists()) {
                    $student->guardians()->attach($guardian->id, [
                        'relationship' => 'orang_tua',
                        'is_primary' => true,
                    ]);
                }
            }

            // 5. SEED GURU SELURUH MATA PELAJARAN
            $teachersData = [
                ['username' => 'guru.mtk', 'name' => 'Hendra Wijaya, S.Pd.', 'mapel' => 'Matematika', 'code' => 'MTK-DEMO', 'nip' => '198501012010011001', 'roles' => ['teacher', 'homeroom']],
                ['username' => 'guru.ipa', 'name' => 'Sarah Amelia, S.Pd.', 'mapel' => 'Ilmu Pengetahuan Alam (IPA)', 'code' => 'IPA-DEMO', 'nip' => '198702022012012002', 'roles' => ['teacher', 'homeroom']],
                ['username' => 'guru.ips', 'name' => 'Fajar Pratama, S.Kom., M.Pd.', 'mapel' => 'Ilmu Pengetahuan Sosial (IPS)', 'code' => 'IPS-DEMO', 'nip' => '198903032014011003', 'roles' => ['teacher', 'homeroom']],
                ['username' => 'guru.indo', 'name' => 'Rina Marlina, M.Pd.', 'mapel' => 'Bahasa Indonesia', 'code' => 'IND-DEMO', 'nip' => '198404042009012004', 'roles' => ['teacher']],
                ['username' => 'guru.inggris', 'name' => 'Bambang Sutrisno, M.Si.', 'mapel' => 'Bahasa Inggris', 'code' => 'ENG-DEMO', 'nip' => '198205052008011005', 'roles' => ['teacher']],
                ['username' => 'guru.pai', 'name' => 'Tri Wahyuni, S.Pd.I.', 'mapel' => 'Pendidikan Agama Islam', 'code' => 'PAI-DEMO', 'nip' => '199006062015012006', 'roles' => ['teacher']],
                ['username' => 'guru.ppkn', 'name' => 'Dedi Kurniawan, S.Pd.', 'mapel' => 'Pendidikan Pancasila (PPKn)', 'code' => 'PKN-DEMO', 'nip' => '199107072016011007', 'roles' => ['teacher']],
                ['username' => 'guru.informatika', 'name' => 'Ahmad Fauzi, S.Pd.I., M.Kom.', 'mapel' => 'Informatika & Coding', 'code' => 'INF-DEMO', 'nip' => '199208082017011008', 'roles' => ['teacher']],
                ['username' => 'guru.pjok', 'name' => 'Maya Anggraini, S.Pd.', 'mapel' => 'PJOK (Pendidikan Jasmani)', 'code' => 'PJK-DEMO', 'nip' => '199309092018012009', 'roles' => ['teacher']],
                ['username' => 'guru.seni', 'name' => 'Rizki Ramadhan, S.Sn.', 'mapel' => 'Seni Budaya', 'code' => 'SNB-DEMO', 'nip' => '199410102019011010', 'roles' => ['teacher']],
                ['username' => 'guru.prakarya', 'name' => 'Budi Hartono, M.Pd.', 'mapel' => 'Prakarya & Kewirausahaan', 'code' => 'PRK-DEMO', 'nip' => '198611112011011011', 'roles' => ['teacher']],
                ['username' => 'guru.jawa', 'name' => 'Sri Lestari, S.Pd.', 'mapel' => 'Bahasa Daerah (Jawa)', 'code' => 'BJW-DEMO', 'nip' => '198812122013012012', 'roles' => ['teacher']],
                ['username' => 'guru.bk', 'name' => 'Guru BK Konseling, S.Psi.', 'mapel' => 'Bimbingan Konseling (BK)', 'code' => 'BK-DEMO', 'nip' => '199501012020012013', 'roles' => ['counselor']],
                ['username' => 'guru.gpk', 'name' => 'Ibu Ratna Dewi, S.Pd. (GPK)', 'mapel' => 'Guru Pembimbing Khusus Inklusi', 'code' => 'GPK-DEMO', 'nip' => '199202022017012014', 'roles' => ['teacher']],
                ['username' => 'guru.tahfidz', 'name' => 'Ustadz Abdullah, S.Pd.I.', 'mapel' => 'Tahfidz Al-Qur\'an', 'code' => 'TFZ-DEMO', 'nip' => '199003032015011015', 'roles' => ['teacher']],
                ['username' => 'guru.icp', 'name' => 'Mr. Johnathan Smith, B.Ed.', 'mapel' => 'Cambridge Native & Science', 'code' => 'ICP-DEMO', 'nip' => '198504042010011016', 'roles' => ['teacher']],
            ];

            foreach ($teachersData as $t) {
                $uGuru = $upsertUser($t['username'], $t['username'].'@labschool.sch.id', $t['roles']);
                $teacher = Teacher::firstOrNew(['user_id' => $uGuru->id]);
                $teacher->school_id = $schoolId;
                $teacher->nip = $t['nip'];
                $teacher->full_name = $t['name'];
                $teacher->position = 'Guru '.$t['mapel'];
                $teacher->status = 'pns';
                $teacher->save();

                // Pastikan Subject Mapel Ada
                Subject::firstOrCreate(
                    ['code' => $t['code']],
                    [
                        'school_id' => $schoolId,
                        'name' => $t['mapel'],
                        'subject_group' => 'muatan_nasional',
                    ]
                );
            }

            // 6. SEED SELURUH STAF TENAGA KEPENDIDIKAN
            $staffData = [
                ['username' => 'staf.tu', 'alt' => 'admintu', 'name' => 'Staf Tata Usaha Sekolah', 'unit' => 'Tata Usaha', 'position' => 'Kaur Tata Usaha', 'roles' => ['admin_tu']],
                ['username' => 'staf.sarpras', 'alt' => 'stafsarpras', 'name' => 'Staf Sarana & Prasarana', 'unit' => 'Sarpras & Logistik', 'position' => 'Pengelola Aset & Fasilitas', 'roles' => ['facility_staff']],
                ['username' => 'staf.ppdb', 'alt' => 'panitiappdb', 'name' => 'Panitia PPDB Sekolah', 'unit' => 'Panitia PPDB', 'position' => 'Koordinator Pendaftaran PPDB', 'roles' => ['ppdb_committee']],
                ['username' => 'staf.perpustakaan', 'alt' => 'pustakawan', 'name' => 'Pustakawan Sekolah', 'unit' => 'Perpustakaan', 'position' => 'Pengelola Perpustakaan Digital', 'roles' => ['librarian']],
                ['username' => 'staf.keuangan', 'alt' => 'bendahara', 'name' => 'Bendahara Sekolah', 'unit' => 'Keuangan', 'position' => 'Bendahara Operasional Sekolah', 'roles' => ['treasurer']],
                ['username' => 'staf.kesiswaan', 'alt' => 'stafkesiswaan', 'name' => 'Staf Kesiswaan & OSIS', 'unit' => 'Kesiswaan', 'position' => 'Pembina OSIS & Disiplin', 'roles' => ['student_affairs']],
                ['username' => 'staf.humas', 'alt' => 'stafhumas', 'name' => 'Staf Hubungan Masyarakat', 'unit' => 'Humas', 'position' => 'Pranata Humas & Pengaduan', 'roles' => ['public_relations']],
                ['username' => 'staf.cms', 'alt' => 'operatorcms', 'name' => 'Operator Portal CMS & Web', 'unit' => 'TIK & Publikasi', 'position' => 'Webmaster & Editor Berita', 'roles' => ['operator_cms']],
                ['username' => 'staf.it', 'alt' => 'cbt_admin', 'name' => 'Teknisi IT & Server CBT', 'unit' => 'Laboratorium Komputer', 'position' => 'Admin CBT & Jaringan', 'roles' => ['staff', 'admin_tu']],
                ['username' => 'staf.security', 'alt' => 'satpam', 'name' => 'Petugas Keamanan & Presensi', 'unit' => 'Keamanan & Ketertiban', 'position' => 'Koordinator Keamanan Gerbang', 'roles' => ['staff']],
            ];

            foreach ($staffData as $s) {
                $uStaf = $upsertUser($s['username'], $s['username'].'@labschool.sch.id', $s['roles']);
                if (isset($s['alt'])) {
                    $uStafAlt = $upsertUser($s['alt'], $s['alt'].'@labschool.sch.id', $s['roles']);
                }

                $staff = Staff::firstOrNew(['user_id' => $uStaf->id]);
                $staff->school_id = $schoolId;
                $staff->full_name = $s['name'];
                $staff->unit = $s['unit'];
                $staff->position = $s['position'];
                $staff->save();
            }

            // 7. SEED PIMPINAN, YAYASAN, KOMITE, & PENGAWAS
            $execData = [
                ['username' => 'yayasan', 'name' => 'Ketua Pengurus Yayasan BPH UM', 'unit' => 'Yayasan BPH UM', 'position' => 'Ketua Yayasan', 'roles' => ['committee']],
                ['username' => 'komitesekolah', 'name' => 'Ketua Komite Sekolah', 'unit' => 'Komite Sekolah', 'position' => 'Pengurus Komite Sekolah', 'roles' => ['committee']],
                ['username' => 'pengawassekolah', 'name' => 'Pengawas Satuan Pendidikan', 'unit' => 'Dinas Pendidikan', 'position' => 'Pengawas Pembina Sekolah', 'roles' => ['supervisor']],
                ['username' => 'kepsek', 'name' => 'Drs. H. Tamyis Suliantoro, M.Pd.', 'unit' => 'Pimpinan Sekolah', 'position' => 'Kepala Sekolah', 'roles' => ['principal']],
                ['username' => 'wakakurikulum', 'name' => 'Wakil Kepala Kurikulum, S.Pd., M.Pd.', 'unit' => 'Kurikulum', 'position' => 'Waka Kurikulum', 'roles' => ['vice_principal', 'teacher']],
                ['username' => 'wakakesiswaan', 'name' => 'Waka Kesiswaan, S.Pd.', 'unit' => 'Kesiswaan', 'position' => 'Waka Kesiswaan', 'roles' => ['vice_principal', 'teacher']],
                ['username' => 'wakasarpras', 'name' => 'Waka Sarana & Prasarana, S.T.', 'unit' => 'Sarpras', 'position' => 'Waka Sarana & Prasarana', 'roles' => ['vice_principal', 'teacher']],
                ['username' => 'wakahumas', 'name' => 'Waka Humas & Kemitraan, M.Pd.', 'unit' => 'Humas', 'position' => 'Waka Humas', 'roles' => ['vice_principal', 'teacher']],
                ['username' => 'superadmin', 'name' => 'Super Administrator Sistem', 'unit' => 'TIK', 'position' => 'Administrator Utama', 'roles' => ['super_admin']],
                ['username' => 'admin', 'name' => 'Administrator Back-Office', 'unit' => 'TIK', 'position' => 'Administrator Sistem', 'roles' => ['super_admin']],
            ];

            foreach ($execData as $e) {
                $uExec = $upsertUser($e['username'], $e['username'].'@labschool.sch.id', $e['roles']);
                $staff = Staff::firstOrNew(['user_id' => $uExec->id]);
                $staff->school_id = $schoolId;
                $staff->full_name = $e['name'];
                $staff->unit = $e['unit'];
                $staff->position = $e['position'];
                $staff->save();
            }
        });
    }
}
