<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\ClassStudent;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentProgramProfile;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ComprehensiveAccountsAndQuestionBanksSeeder extends Seeder
{
    public const DEFAULT_SCHOOL_ID = '00000000-0000-0000-0000-000000000001';

    public function run(): void
    {
        DB::transaction(function () {
            $school = School::first() ?? School::firstOrCreate(
                ['id' => self::DEFAULT_SCHOOL_ID],
                [
                    'name' => 'SMP Laboratorium Universitas Negeri Malang',
                    'npsn' => '20533807',
                    'timezone' => 'Asia/Jakarta',
                    'is_active' => true,
                ]
            );
            $schoolId = $school->id;

            $academicYear = AcademicYear::where('is_active', true)->first()
                ?? AcademicYear::firstOrCreate(
                    ['name' => '2024/2025'],
                    ['start_date' => '2024-07-15', 'end_date' => '2025-06-20', 'is_active' => true]
                );

            $semester = Semester::where('is_active', true)->first()
                ?? Semester::firstOrCreate(
                    ['academic_year_id' => $academicYear->id, 'semester_type' => 'gasal'],
                    ['start_date' => '2024-07-15', 'end_date' => '2024-12-20', 'is_active' => true]
                );

            $grade7 = GradeLevel::where('school_id', $schoolId)
                ->where(fn ($q) => $q->where('sort_order', 7)->orWhere('name', 'LIKE', '%7%')->orWhere('name', 'LIKE', '%VII%'))
                ->first()
                ?? GradeLevel::firstOrCreate(['school_id' => $schoolId, 'sort_order' => 7], ['name' => 'Kelas 7']);

            $studentRole = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
            $guardianRole = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Orang Tua / Wali Murid', 'is_system' => true]);

            // Dapatkan Guru Pembina / Pengampu
            $guruTahfidz = Teacher::whereHas('user', fn ($u) => $u->where('username', 'guru.tahfidz'))->first();
            $guruIcp = Teacher::whereHas('user', fn ($u) => $u->where('username', 'guru.icp'))->first();
            $guruGpk = Teacher::whereHas('user', fn ($u) => $u->where('username', 'guru.gpk'))->first();
            $guruMtk = Teacher::whereHas('user', fn ($u) => $u->where('username', 'guru.mtk'))->first();
            $guruIpa = Teacher::whereHas('user', fn ($u) => $u->where('username', 'guru.ipa'))->first();
            $guruIndo = Teacher::whereHas('user', fn ($u) => $u->where('username', 'guru.indo'))->first();
            $guruInggris = Teacher::whereHas('user', fn ($u) => $u->where('username', 'guru.inggris'))->first();
            $guruPai = Teacher::whereHas('user', fn ($u) => $u->where('username', 'guru.pai'))->first();
            $guruInf = Teacher::whereHas('user', fn ($u) => $u->where('username', 'guru.informatika'))->first();

            // =========================================================================
            // 1. KELAS PROGRAM KHUSUS & REGULER
            // =========================================================================
            $classReguler = SchoolClass::updateOrCreate(
                ['name' => 'VII-A Reguler'],
                [
                    'academic_year_id' => $academicYear->id,
                    'grade_level_id' => $grade7->id,
                    'capacity' => 32,
                    'program_type' => 'reguler',
                    'homeroom_teacher_id' => $guruMtk?->id,
                ]
            );

            $classTahfidz = SchoolClass::updateOrCreate(
                ['name' => 'VII-B Tahfidz'],
                [
                    'academic_year_id' => $academicYear->id,
                    'grade_level_id' => $grade7->id,
                    'capacity' => 30,
                    'program_type' => 'tahfidz',
                    'homeroom_teacher_id' => $guruTahfidz?->id,
                ]
            );

            $classIcp = SchoolClass::updateOrCreate(
                ['name' => 'VII-C ICP'],
                [
                    'academic_year_id' => $academicYear->id,
                    'grade_level_id' => $grade7->id,
                    'capacity' => 28,
                    'program_type' => 'icp',
                    'homeroom_teacher_id' => $guruIcp?->id,
                ]
            );

            $classInklusi = SchoolClass::updateOrCreate(
                ['name' => 'VII-D Inklusi'],
                [
                    'academic_year_id' => $academicYear->id,
                    'grade_level_id' => $grade7->id,
                    'capacity' => 20,
                    'program_type' => 'inklusi',
                    'homeroom_teacher_id' => $guruGpk?->id,
                ]
            );

            // =========================================================================
            // 2. DATA 5 SISWA PER PROGRAM (TOTAL 20 SISWA) + 20 ORTU WALI MURID
            // =========================================================================
            $studentsData = [
                // ------------------ TAHFIDZ (5 Siswa) ------------------
                [
                    'username' => 'siswa.tahfidz.1',
                    'email' => 'siswa.tahfidz.1@labschool.sch.id',
                    'name' => 'Muhammad Hafizh Al-Fatih',
                    'nis' => '26102',
                    'nisn' => '0099002102',
                    'gender' => 'L',
                    'program_type' => 'tahfidz',
                    'class' => $classTahfidz,
                    'parent_name' => 'Ahmad Al-Fatih, S.Ag.',
                    'parent_user' => 'ortu.tahfidz.1',
                    'parent_phone' => '081234567101',
                    'meta_data' => [
                        'target_juz' => 3,
                        'current_juz' => 2,
                        'current_surah' => 'Al-Mulk: 1-30',
                        'last_tasmi_date' => '2026-10-28',
                        'last_tasmi_grade' => 'Mumtaz',
                        'mutabaah_today' => ['sholat_fardhu' => 5, 'tilawah_pages' => 4, 'dhuha' => true, 'tahajud' => false],
                    ],
                ],
                [
                    'username' => 'siswa.tahfidz.2',
                    'email' => 'siswa.tahfidz.2@labschool.sch.id',
                    'name' => 'Ibrahim Hanif Al-Qurthubi',
                    'nis' => '26105',
                    'nisn' => '0099002105',
                    'gender' => 'L',
                    'program_type' => 'tahfidz',
                    'class' => $classTahfidz,
                    'parent_name' => 'Bambang Hanif, M.E.I.',
                    'parent_user' => 'ortu.tahfidz.2',
                    'parent_phone' => '081234567102',
                    'meta_data' => [
                        'target_juz' => 3,
                        'current_juz' => 1,
                        'current_surah' => 'An-Naba: 1-40',
                        'last_tasmi_date' => '2026-11-02',
                        'last_tasmi_grade' => 'Jayyid Jiddan',
                        'mutabaah_today' => ['sholat_fardhu' => 5, 'tilawah_pages' => 3, 'dhuha' => true, 'tahajud' => true],
                    ],
                ],
                [
                    'username' => 'siswa.tahfidz.3',
                    'email' => 'siswa.tahfidz.3@labschool.sch.id',
                    'name' => 'Maryam Khansa Nabilah',
                    'nis' => '26106',
                    'nisn' => '0099002106',
                    'gender' => 'P',
                    'program_type' => 'tahfidz',
                    'class' => $classTahfidz,
                    'parent_name' => 'Farhan Nabil, S.T.',
                    'parent_user' => 'ortu.tahfidz.3',
                    'parent_phone' => '081234567103',
                    'meta_data' => [
                        'target_juz' => 4,
                        'current_juz' => 3,
                        'current_surah' => 'Yasin: 1-83',
                        'last_tasmi_date' => '2026-11-05',
                        'last_tasmi_grade' => 'Mumtaz',
                        'mutabaah_today' => ['sholat_fardhu' => 5, 'tilawah_pages' => 5, 'dhuha' => true, 'tahajud' => true],
                    ],
                ],
                [
                    'username' => 'siswa.tahfidz.4',
                    'email' => 'siswa.tahfidz.4@labschool.sch.id',
                    'name' => 'Zaidan Ali Ash-Shiddiq',
                    'nis' => '26107',
                    'nisn' => '0099002107',
                    'gender' => 'L',
                    'program_type' => 'tahfidz',
                    'class' => $classTahfidz,
                    'parent_name' => 'Ali Ash-Shiddiq, M.Pd.',
                    'parent_user' => 'ortu.tahfidz.4',
                    'parent_phone' => '081234567104',
                    'meta_data' => [
                        'target_juz' => 2,
                        'current_juz' => 2,
                        'current_surah' => 'Al-Insan: 1-31',
                        'last_tasmi_date' => '2026-10-30',
                        'last_tasmi_grade' => 'Mumtaz',
                        'mutabaah_today' => ['sholat_fardhu' => 5, 'tilawah_pages' => 4, 'dhuha' => true, 'tahajud' => false],
                    ],
                ],
                [
                    'username' => 'siswa.tahfidz.5',
                    'email' => 'siswa.tahfidz.5@labschool.sch.id',
                    'name' => 'Fathimah Azzahra Humaira',
                    'nis' => '26108',
                    'nisn' => '0099002108',
                    'gender' => 'P',
                    'program_type' => 'tahfidz',
                    'class' => $classTahfidz,
                    'parent_name' => 'Dr. Lukman Hakim, M.Ag.',
                    'parent_user' => 'ortu.tahfidz.5',
                    'parent_phone' => '081234567105',
                    'meta_data' => [
                        'target_juz' => 5,
                        'current_juz' => 4,
                        'current_surah' => 'Ar-Rahman: 1-78',
                        'last_tasmi_date' => '2026-11-08',
                        'last_tasmi_grade' => 'Mumtaz',
                        'mutabaah_today' => ['sholat_fardhu' => 5, 'tilawah_pages' => 6, 'dhuha' => true, 'tahajud' => true],
                    ],
                ],

                // ------------------ ICP (CAMBRIDGE) (5 Siswa) ------------------
                [
                    'username' => 'siswa.icp.1',
                    'email' => 'siswa.icp.1@labschool.sch.id',
                    'name' => 'Aisyah Valerie Zahra',
                    'nis' => '26103',
                    'nisn' => '0099002103',
                    'gender' => 'P',
                    'program_type' => 'icp',
                    'class' => $classIcp,
                    'parent_name' => 'Ir. Hendra Zahra, M.Sc.',
                    'parent_user' => 'ortu.icp.1',
                    'parent_phone' => '081234567201',
                    'meta_data' => [
                        'cambridge_candidate_id' => 'ID-MAL-2026-042',
                        'checkpoint_readiness' => [
                            'english' => ['score' => 4.8, 'target' => 6.0, 'status' => 'Ready'],
                            'math' => ['score' => 5.2, 'target' => 6.0, 'status' => 'Exceeding'],
                            'science' => ['score' => 4.5, 'target' => 6.0, 'status' => 'Ready'],
                        ],
                    ],
                ],
                [
                    'username' => 'siswa.icp.2',
                    'email' => 'siswa.icp.2@labschool.sch.id',
                    'name' => 'Nathan Alexander Smith',
                    'nis' => '26109',
                    'nisn' => '0099002109',
                    'gender' => 'L',
                    'program_type' => 'icp',
                    'class' => $classIcp,
                    'parent_name' => 'Robert Alexander, Ph.D.',
                    'parent_user' => 'ortu.icp.2',
                    'parent_phone' => '081234567202',
                    'meta_data' => [
                        'cambridge_candidate_id' => 'ID-MAL-2026-043',
                        'checkpoint_readiness' => [
                            'english' => ['score' => 5.5, 'target' => 6.0, 'status' => 'Exceeding'],
                            'math' => ['score' => 4.9, 'target' => 6.0, 'status' => 'Ready'],
                            'science' => ['score' => 5.0, 'target' => 6.0, 'status' => 'Exceeding'],
                        ],
                    ],
                ],
                [
                    'username' => 'siswa.icp.3',
                    'email' => 'siswa.icp.3@labschool.sch.id',
                    'name' => 'Audrey Clarissa Quinn',
                    'nis' => '26110',
                    'nisn' => '0099002110',
                    'gender' => 'P',
                    'program_type' => 'icp',
                    'class' => $classIcp,
                    'parent_name' => 'Dr. Michael Quinn, Sp.A.',
                    'parent_user' => 'ortu.icp.3',
                    'parent_phone' => '081234567203',
                    'meta_data' => [
                        'cambridge_candidate_id' => 'ID-MAL-2026-044',
                        'checkpoint_readiness' => [
                            'english' => ['score' => 5.1, 'target' => 6.0, 'status' => 'Exceeding'],
                            'math' => ['score' => 4.6, 'target' => 6.0, 'status' => 'Ready'],
                            'science' => ['score' => 4.8, 'target' => 6.0, 'status' => 'Ready'],
                        ],
                    ],
                ],
                [
                    'username' => 'siswa.icp.4',
                    'email' => 'siswa.icp.4@labschool.sch.id',
                    'name' => 'Kenzie Ethan Leonardo',
                    'nis' => '26111',
                    'nisn' => '0099002111',
                    'gender' => 'L',
                    'program_type' => 'icp',
                    'class' => $classIcp,
                    'parent_name' => 'David Leonardo, S.E., MBA',
                    'parent_user' => 'ortu.icp.4',
                    'parent_phone' => '081234567204',
                    'meta_data' => [
                        'cambridge_candidate_id' => 'ID-MAL-2026-045',
                        'checkpoint_readiness' => [
                            'english' => ['score' => 4.7, 'target' => 6.0, 'status' => 'Ready'],
                            'math' => ['score' => 5.3, 'target' => 6.0, 'status' => 'Exceeding'],
                            'science' => ['score' => 5.1, 'target' => 6.0, 'status' => 'Exceeding'],
                        ],
                    ],
                ],
                [
                    'username' => 'siswa.icp.5',
                    'email' => 'siswa.icp.5@labschool.sch.id',
                    'name' => 'Callista Olivia Wijaya',
                    'nis' => '26112',
                    'nisn' => '0099002112',
                    'gender' => 'P',
                    'program_type' => 'icp',
                    'class' => $classIcp,
                    'parent_name' => 'Stefan Wijaya, B.Eng.',
                    'parent_user' => 'ortu.icp.5',
                    'parent_phone' => '081234567205',
                    'meta_data' => [
                        'cambridge_candidate_id' => 'ID-MAL-2026-046',
                        'checkpoint_readiness' => [
                            'english' => ['score' => 5.4, 'target' => 6.0, 'status' => 'Exceeding'],
                            'math' => ['score' => 5.0, 'target' => 6.0, 'status' => 'Ready'],
                            'science' => ['score' => 4.9, 'target' => 6.0, 'status' => 'Ready'],
                        ],
                    ],
                ],

                // ------------------ INKLUSI (PPI) (5 Siswa) ------------------
                [
                    'username' => 'siswa.inklusi.1',
                    'email' => 'siswa.inklusi.1@labschool.sch.id',
                    'name' => 'Rian Ananda Putra',
                    'nis' => '26104',
                    'nisn' => '0099002104',
                    'gender' => 'L',
                    'program_type' => 'inklusi',
                    'class' => $classInklusi,
                    'parent_name' => 'Budi Ananda, S.Kom.',
                    'parent_user' => 'ortu.inklusi.1',
                    'parent_phone' => '081234567301',
                    'meta_data' => [
                        'gpk' => ['name' => 'Ibu Ratna Dewi, S.Pd (GPK)', 'room' => 'Ruang Sumber Inklusi'],
                        'ppi_milestones' => [
                            ['id' => 'm1', 'title' => 'Menyelesaikan modul membaca teks deskriptif', 'is_completed' => true],
                            ['id' => 'm2', 'title' => 'Latihan fokus & motorik halus terarah (15 menit)', 'is_completed' => false],
                        ],
                    ],
                ],
                [
                    'username' => 'siswa.inklusi.2',
                    'email' => 'siswa.inklusi.2@labschool.sch.id',
                    'name' => 'Daffa Surya Ramadhan',
                    'nis' => '26113',
                    'nisn' => '0099002113',
                    'gender' => 'L',
                    'program_type' => 'inklusi',
                    'class' => $classInklusi,
                    'parent_name' => 'Surya Pratama, S.T.',
                    'parent_user' => 'ortu.inklusi.2',
                    'parent_phone' => '081234567302',
                    'meta_data' => [
                        'gpk' => ['name' => 'Ibu Ratna Dewi, S.Pd (GPK)', 'room' => 'Ruang Sumber Inklusi'],
                        'ppi_milestones' => [
                            ['id' => 'm1', 'title' => 'Mengidentifikasi warna & bentuk geometri dasar', 'is_completed' => true],
                            ['id' => 'm2', 'title' => 'Menulis kalimat sederhana dengan panduan bergaris', 'is_completed' => true],
                        ],
                    ],
                ],
                [
                    'username' => 'siswa.inklusi.3',
                    'email' => 'siswa.inklusi.3@labschool.sch.id',
                    'name' => 'Kirana Ayu Larasati',
                    'nis' => '26114',
                    'nisn' => '0099002114',
                    'gender' => 'P',
                    'program_type' => 'inklusi',
                    'class' => $classInklusi,
                    'parent_name' => 'Agus Larasati, M.Si.',
                    'parent_user' => 'ortu.inklusi.3',
                    'parent_phone' => '081234567303',
                    'meta_data' => [
                        'gpk' => ['name' => 'Ibu Ratna Dewi, S.Pd (GPK)', 'room' => 'Ruang Sumber Inklusi'],
                        'ppi_milestones' => [
                            ['id' => 'm1', 'title' => 'Mengekspresikan kebutuhan melalui komunikasi visual (PECS)', 'is_completed' => true],
                            ['id' => 'm2', 'title' => 'Mendengarkan instruksi 2 tahap secara mandiri', 'is_completed' => true],
                        ],
                    ],
                ],
                [
                    'username' => 'siswa.inklusi.4',
                    'email' => 'siswa.inklusi.4@labschool.sch.id',
                    'name' => 'Bima Arya Wicaksono',
                    'nis' => '26115',
                    'nisn' => '0099002115',
                    'gender' => 'L',
                    'program_type' => 'inklusi',
                    'class' => $classInklusi,
                    'parent_name' => 'Hadi Wicaksono, S.Pd.',
                    'parent_user' => 'ortu.inklusi.4',
                    'parent_phone' => '081234567304',
                    'meta_data' => [
                        'gpk' => ['name' => 'Ibu Ratna Dewi, S.Pd (GPK)', 'room' => 'Ruang Sumber Inklusi'],
                        'ppi_milestones' => [
                            ['id' => 'm1', 'title' => 'Penyusunan puzzle angka 1-20 dan operasional dasar', 'is_completed' => true],
                            ['id' => 'm2', 'title' => 'Mengikuti senam ritmik dan koordinasi gerak tubuh', 'is_completed' => false],
                        ],
                    ],
                ],
                [
                    'username' => 'siswa.inklusi.5',
                    'email' => 'siswa.inklusi.5@labschool.sch.id',
                    'name' => 'Nayla Salsabila Putri',
                    'nis' => '26116',
                    'nisn' => '0099002116',
                    'gender' => 'P',
                    'program_type' => 'inklusi',
                    'class' => $classInklusi,
                    'parent_name' => 'Dedi Salsabila, S.H.',
                    'parent_user' => 'ortu.inklusi.5',
                    'parent_phone' => '081234567305',
                    'meta_data' => [
                        'gpk' => ['name' => 'Ibu Ratna Dewi, S.Pd (GPK)', 'room' => 'Ruang Sumber Inklusi'],
                        'ppi_milestones' => [
                            ['id' => 'm1', 'title' => 'Membaca kosakata ramah fonik bahasa Indonesia', 'is_completed' => true],
                            ['id' => 'm2', 'title' => 'Sosialisasi ramah sebaya pada jam istirahat terpandu', 'is_completed' => true],
                        ],
                    ],
                ],

                // ------------------ REGULER (5 Siswa) ------------------
                [
                    'username' => 'siswa.reguler.1',
                    'email' => 'siswa.reguler.1@labschool.sch.id',
                    'name' => 'Bintang Pratama',
                    'nis' => '26101',
                    'nisn' => '0099002101',
                    'gender' => 'L',
                    'program_type' => 'reguler',
                    'class' => $classReguler,
                    'parent_name' => 'Pratama Kusuma, S.E.',
                    'parent_user' => 'ortu.reguler.1',
                    'parent_phone' => '081234567401',
                    'meta_data' => [
                        'kktp_summary' => ['average_score' => 86.4, 'tuntas_count' => 10],
                        'p5_project' => ['title' => 'Pengolahan Sampah Organik Sekolah'],
                    ],
                ],
                [
                    'username' => 'siswa.reguler.2',
                    'email' => 'siswa.reguler.2@labschool.sch.id',
                    'name' => 'Aditya Bagus Saputra',
                    'nis' => '26117',
                    'nisn' => '0099002117',
                    'gender' => 'L',
                    'program_type' => 'reguler',
                    'class' => $classReguler,
                    'parent_name' => 'Bagus Saputra, S.T.',
                    'parent_user' => 'ortu.reguler.2',
                    'parent_phone' => '081234567402',
                    'meta_data' => [
                        'kktp_summary' => ['average_score' => 88.0, 'tuntas_count' => 11],
                        'p5_project' => ['title' => 'Pengolahan Sampah Organik Sekolah'],
                    ],
                ],
                [
                    'username' => 'siswa.reguler.3',
                    'email' => 'siswa.reguler.3@labschool.sch.id',
                    'name' => 'Citra Dewi Lestari',
                    'nis' => '26118',
                    'nisn' => '0099002118',
                    'gender' => 'P',
                    'program_type' => 'reguler',
                    'class' => $classReguler,
                    'parent_name' => 'Joko Lestari, M.Pd.',
                    'parent_user' => 'ortu.reguler.3',
                    'parent_phone' => '081234567403',
                    'meta_data' => [
                        'kktp_summary' => ['average_score' => 91.2, 'tuntas_count' => 11],
                        'p5_project' => ['title' => 'Kearifan Lokal Batik Topeng Malangan'],
                    ],
                ],
                [
                    'username' => 'siswa.reguler.4',
                    'email' => 'siswa.reguler.4@labschool.sch.id',
                    'name' => 'Fajar Maulana Malik',
                    'nis' => '26119',
                    'nisn' => '0099002119',
                    'gender' => 'L',
                    'program_type' => 'reguler',
                    'class' => $classReguler,
                    'parent_name' => 'Maulana Malik, S.H.',
                    'parent_user' => 'ortu.reguler.4',
                    'parent_phone' => '081234567404',
                    'meta_data' => [
                        'kktp_summary' => ['average_score' => 84.5, 'tuntas_count' => 9],
                        'p5_project' => ['title' => 'Kearifan Lokal Batik Topeng Malangan'],
                    ],
                ],
                [
                    'username' => 'siswa.reguler.5',
                    'email' => 'siswa.reguler.5@labschool.sch.id',
                    'name' => 'Gita Permata Sari',
                    'nis' => '26120',
                    'nisn' => '0099002120',
                    'gender' => 'P',
                    'program_type' => 'reguler',
                    'class' => $classReguler,
                    'parent_name' => 'Wahyu Permata, S.E.',
                    'parent_user' => 'ortu.reguler.5',
                    'parent_phone' => '081234567405',
                    'meta_data' => [
                        'kktp_summary' => ['average_score' => 89.5, 'tuntas_count' => 11],
                        'p5_project' => ['title' => 'Pengolahan Sampah Organik Sekolah'],
                    ],
                ],
            ];

            foreach ($studentsData as $item) {
                // 1. Akun User Siswa
                $userStudent = User::updateOrCreate(
                    ['username' => $item['username']],
                    [
                        'school_id' => $schoolId,
                        'email' => $item['email'],
                        'password_hash' => Hash::make('Password123!'),
                        'status' => 'active',
                        'must_change_password' => false,
                    ]
                );
                if (! $userStudent->roles()->where('role_id', $studentRole->id)->exists()) {
                    $userStudent->roles()->attach($studentRole->id);
                }

                // 2. Profil Student
                $student = Student::updateOrCreate(
                    ['nis' => $item['nis']],
                    [
                        'school_id' => $schoolId,
                        'user_id' => $userStudent->id,
                        'nisn' => $item['nisn'],
                        'nik_enc' => '3573010101' . $item['nis'],
                        'full_name' => $item['name'],
                        'gender' => $item['gender'],
                        'birth_place' => 'Malang',
                        'birth_date' => '2012-05-15',
                        'status' => 'aktif',
                        'entry_date' => '2024-07-15',
                        'program_type' => $item['program_type'],
                    ]
                );

                // 3. Hubungkan Siswa ke Rombel Kelas
                ClassStudent::updateOrCreate(
                    ['class_id' => $item['class']->id, 'student_id' => $student->id],
                    ['joined_at' => '2024-07-15']
                );

                // 4. Profil Khusus Siswa
                StudentProgramProfile::updateOrCreate(
                    ['student_id' => $student->id],
                    [
                        'program_type' => $item['program_type'],
                        'meta_data' => $item['meta_data'],
                    ]
                );

                // 5. Akun User Orang Tua (Wali Murid)
                $userParent = User::updateOrCreate(
                    ['username' => $item['parent_user']],
                    [
                        'school_id' => $schoolId,
                        'email' => $item['parent_user'] . '@labschool.sch.id',
                        'password_hash' => Hash::make('Password123!'),
                        'status' => 'active',
                        'must_change_password' => false,
                    ]
                );
                if (! $userParent->roles()->where('role_id', $guardianRole->id)->exists()) {
                    $userParent->roles()->attach($guardianRole->id);
                }

                // 6. Profil Guardian
                $guardian = Guardian::updateOrCreate(
                    ['user_id' => $userParent->id],
                    [
                        'school_id' => $schoolId,
                        'full_name' => $item['parent_name'],
                        'phone' => $item['parent_phone'],
                        'occupation' => 'Wiraswasta / Profesional',
                        'address' => 'Jl. Bendungan Sigura-gura No. ' . substr($item['nis'], 2) . ', Kota Malang',
                    ]
                );

                // 7. Sambungkan Ortu dengan Siswa di Pivot
                $guardian->students()->syncWithoutDetaching([
                    $student->id => ['relationship' => $item['gender'] === 'L' ? 'ayah' : 'ibu', 'is_primary' => true],
                ]);
            }

            // Juga pastikan alias username tanpa angka (siswa.tahfidz, siswa.icp, dll) tetap mengarah ke profil siswa pertama
            $aliases = [
                'siswa.tahfidz' => 'siswa.tahfidz.1',
                'siswa.icp' => 'siswa.icp.1',
                'siswa.inklusi' => 'siswa.inklusi.1',
                'siswa.reguler' => 'siswa.reguler.1',
            ];
            foreach ($aliases as $alias => $target) {
                $targetUser = User::where('username', $target)->first();
                if ($targetUser) {
                    User::updateOrCreate(
                        ['username' => $alias],
                        [
                            'school_id' => $schoolId,
                            'email' => $alias . '@labschool.sch.id',
                            'password_hash' => Hash::make('Password123!'),
                            'status' => 'active',
                            'must_change_password' => false,
                        ]
                    );
                }
            }

            // =========================================================================
            // 3. PENUGASAN MENGAJAR GURU (TeachingAssignment) UNTUK SEMUA KELAS
            // =========================================================================
            $subjectMtk = Subject::where('code', 'MTK-7')->orWhere('code', 'MTK-DEMO')->first();
            $subjectIpa = Subject::where('code', 'IPA-7')->orWhere('code', 'IPA-DEMO')->first();
            $subjectIndo = Subject::where('code', 'BIN-7')->orWhere('code', 'IND-DEMO')->first();
            $subjectEng = Subject::where('code', 'ENG-7')->orWhere('code', 'ENG-DEMO')->first();
            $subjectPai = Subject::where('code', 'PAI-DEMO')->orWhere('code', 'PAI')->first();
            $subjectInf = Subject::where('code', 'INF-7')->orWhere('code', 'INF-DEMO')->first();
            $subjectTfz = Subject::where('code', 'TFZ-DEMO')->first();
            $subjectIcp = Subject::where('code', 'ICP-DEMO')->first();
            $subjectGpk = Subject::where('code', 'GPK-DEMO')->first();

            $teachingMatrix = [
                ['class' => $classReguler, 'teacher' => $guruMtk, 'subject' => $subjectMtk],
                ['class' => $classReguler, 'teacher' => $guruIpa, 'subject' => $subjectIpa],
                ['class' => $classReguler, 'teacher' => $guruIndo, 'subject' => $subjectIndo],
                ['class' => $classReguler, 'teacher' => $guruInggris, 'subject' => $subjectEng],
                ['class' => $classReguler, 'teacher' => $guruInf, 'subject' => $subjectInf],

                ['class' => $classTahfidz, 'teacher' => $guruTahfidz, 'subject' => $subjectTfz ?? $subjectPai],
                ['class' => $classTahfidz, 'teacher' => $guruPai, 'subject' => $subjectPai],
                ['class' => $classTahfidz, 'teacher' => $guruMtk, 'subject' => $subjectMtk],
                ['class' => $classTahfidz, 'teacher' => $guruIndo, 'subject' => $subjectIndo],
                ['class' => $classTahfidz, 'teacher' => $guruIpa, 'subject' => $subjectIpa],

                ['class' => $classIcp, 'teacher' => $guruIcp, 'subject' => $subjectIcp ?? $subjectEng],
                ['class' => $classIcp, 'teacher' => $guruMtk, 'subject' => $subjectMtk],
                ['class' => $classIcp, 'teacher' => $guruIpa, 'subject' => $subjectIpa],
                ['class' => $classIcp, 'teacher' => $guruInggris, 'subject' => $subjectEng],
                ['class' => $classIcp, 'teacher' => $guruInf, 'subject' => $subjectInf],

                ['class' => $classInklusi, 'teacher' => $guruGpk, 'subject' => $subjectGpk ?? $subjectIndo],
                ['class' => $classInklusi, 'teacher' => $guruIndo, 'subject' => $subjectIndo],
                ['class' => $classInklusi, 'teacher' => $guruMtk, 'subject' => $subjectMtk],
                ['class' => $classInklusi, 'teacher' => $guruIpa, 'subject' => $subjectIpa],
                ['class' => $classInklusi, 'teacher' => $guruInggris, 'subject' => $subjectEng],
            ];

            foreach ($teachingMatrix as $tm) {
                if ($tm['teacher'] && $tm['subject'] && $tm['class']) {
                    TeachingAssignment::updateOrCreate(
                        [
                            'semester_id' => $semester->id,
                            'class_id' => $tm['class']->id,
                            'subject_id' => $tm['subject']->id,
                            'teacher_id' => $tm['teacher']->id,
                        ],
                        ['hours_per_week' => 4]
                    );
                }
            }

            // =========================================================================
            // 4. LIMA (5) TUGAS NYATA TIAP KELAS (TOTAL 20 TUGAS LMS)
            // =========================================================================
            $tasksByClass = [
                // VII-A Reguler
                [
                    'class' => $classReguler,
                    'teacher' => $guruMtk,
                    'subject' => $subjectMtk,
                    'title' => 'Tugas Matematika: Pemecahan Masalah Aljabar Sederhana dalam Kehidupan Nyata',
                    'description' => "Instruksi Pengerjaan:\n1. Buka buku paket Matematika Kelas VII Bab 3: Bentuk Aljabar.\n2. Kerjakan soal cerita nomor 1-5 halaman 88 tentang permodelan belanja kebutuhan sehari-hari ke dalam variabel aljabar x dan y.\n3. Tulis langkah penyelesaian secara terstruktur di buku tugas dan unggah hasil pengerjaan berupa dokumen PDF yang jelas dan terbaca.",
                    'days' => 5,
                ],
                [
                    'class' => $classReguler,
                    'teacher' => $guruIpa,
                    'subject' => $subjectIpa,
                    'title' => 'Tugas IPA: Laporan Pengamatan Sel Tumbuhan & Sel Hewan dengan Mikroskop',
                    'description' => "Instruksi Praktikum:\n1. Susun laporan praktikum mikroskopis berdasarkan pengamatan sel epidermis bawang merah dan sel epitel pipi.\n2. Cantumkan sketsa diagram sel lengkap dengan bagian dinding sel, membran sel, sitoplasma, dan inti sel.\n3. Berikan analisis perbedaan mendasar antara kedua jenis sel tersebut.",
                    'days' => 6,
                ],
                [
                    'class' => $classReguler,
                    'teacher' => $guruIndo,
                    'subject' => $subjectIndo,
                    'title' => 'Tugas Bahasa Indonesia: Menulis Teks Deskripsi Objek Wisata Bersejarah Kota Malang',
                    'description' => "Instruksi Penulisan:\n1. Pilihlah salah satu objek wisata cagar budaya atau ruang publik di Kota Malang (misal: Candi Singosari, Alun-Alun Tugu, Kampung Warna-Warni Jodipan).\n2. Susun teks deskripsi sepanjang 3-4 paragraf yang memuat struktur identifikasi, deskripsi bagian, dan penutup.\n3. Perhatikan penggunaan huruf kapital, tanda baca, serta kosakata baku sesuai PUEBI.",
                    'days' => 7,
                ],
                [
                    'class' => $classReguler,
                    'teacher' => $guruInggris,
                    'subject' => $subjectEng,
                    'title' => 'Tugas Bahasa Inggris: Descriptive Paragraph of Favorite Role Model',
                    'description' => "Writing Prompt:\n1. Write a descriptive text about your favorite role model or family member (120-150 words).\n2. Include identification (name, who they are) and description (physical appearance, personality traits, and why you admire them).\n3. Use Simple Present Tense and appropriate adjectives.",
                    'days' => 8,
                ],
                [
                    'class' => $classReguler,
                    'teacher' => $guruInf,
                    'subject' => $subjectInf,
                    'title' => 'Tugas Informatika: Pembuatan Infografis Etika Berinternet dan Keamanan Jejak Digital',
                    'description' => "Tugas Proyek Digital:\n1. Rancang sebuah infografis edukatif ukuran A4 bertema 'Keluarga Digital Cerdas: Menjaga Privasi & Keamanan Akun Media Sosial'.\n2. Gunakan aplikasi desain pilihan Anda (Canva/Inkscape/Google Slides).\n3. Muat tips membuat kata sandi kuat, bahaya phising, dan etika berkomentar sopan di ruang publik siber.",
                    'days' => 9,
                ],

                // VII-B Tahfidz
                [
                    'class' => $classTahfidz,
                    'teacher' => $guruTahfidz,
                    'subject' => $subjectTfz ?? $subjectPai,
                    'title' => 'Tugas Tahfidz: Setoran Mandiri Audio Hafalan Surah Al-Mulk Ayat 1-15',
                    'description' => "Instruksi Setoran:\n1. Rekam lantunan hafalan Surah Al-Mulk ayat 1 sampai 15 dengan bacaan tartil.\n2. Perhatikan penerapan hukum tajwid: Ghunnah, Mad Thobi'i, Idgham Bighunnah, dan Qalqalah Kubra/Sughra.\n3. Unggah berkas rekaman suara (format MP3 atau M4A) berdurasi maksimal 4 menit.",
                    'days' => 4,
                ],
                [
                    'class' => $classTahfidz,
                    'teacher' => $guruPai,
                    'subject' => $subjectPai,
                    'title' => 'Tugas PAI: Resume Makna Hukum Bacaan Nun Sukun dan Tanwin dalam Surah An-Naba',
                    'description' => "Instruksi Analisis Tajwid:\n1. Telusuri ayat 1-25 dalam Surah An-Naba.\n2. Temukan masing-masing minimal 2 contoh lafadz yang mengandung hukum Izhar Halqi, Idgham Bighunnah, Idgham Bilaghunnah, Iqlab, dan Ikhfa Haqiqi.\n3. Buat tabel rangkuman lafadz, bunyi hukum, dan alasan kaidah tajwidnya.",
                    'days' => 5,
                ],
                [
                    'class' => $classTahfidz,
                    'teacher' => $guruMtk,
                    'subject' => $subjectMtk,
                    'title' => 'Tugas Matematika Qurani: Perhitungan Proporsi Pembagian Zakat & Waris Sederhana',
                    'description' => "Instruksi Soal Terapan:\n1. Selesaikan 4 studi kasus perhitungan zakat mal perdagangan dan hasil pertanian menggunakan konsep rasio serta pecahan desimal.\n2. Tunjukkan cara kerja matematika langkah demi langkah beserta dasar nishab yang digunakan.",
                    'days' => 6,
                ],
                [
                    'class' => $classTahfidz,
                    'teacher' => $guruTahfidz,
                    'subject' => $subjectTfz ?? $subjectPai,
                    'title' => 'Tugas Mutabaah: Lembar Jurnal Refleksi Adab Penghafal Al-Qur\'an',
                    'description' => "Instruksi Refleksi Diri:\n1. Tuliskan catatan refleksi mingguan mengenai konsistensi waktu murojaah harian Anda (pagi setelah Subuh dan malam sebelum tidur).\n2. Cantumkan kendala yang dihadapi dalam menjaga hafalan serta komitmen perbaikan untuk pekan depan.",
                    'days' => 7,
                ],
                [
                    'class' => $classTahfidz,
                    'teacher' => $guruIpa,
                    'subject' => $subjectIpa,
                    'title' => 'Tugas IPA: Kajian Konsep Thaharah Air Bersih Berdasarkan Parameter Fisik & Kimia',
                    'description' => "Instruksi Studi Literatur:\n1. Analisis kriteria air mutlak (suci dan menyucikan) ditinjau dari parameter kualitas air bersih (kekeruhan, bau, rasa, dan pH).\n2. Buat ringkasan infografis sederhana cara filtrasi air sederhana untuk sarana tempat wudhu darurat.",
                    'days' => 8,
                ],

                // VII-C ICP
                [
                    'class' => $classIcp,
                    'teacher' => $guruIcp,
                    'subject' => $subjectIcp ?? $subjectEng,
                    'title' => 'Tugas Cambridge Science: Practical Lab Report on Photosynthesis & Starch Iodine Test',
                    'description' => "Lab Assignment Instructions:\n1. Read the experimental guide for testing variegated leaves for starch using iodine solution.\n2. Complete the laboratory worksheet: State the hypothesis, identify control variables, describe safety procedures with boiling ethanol, and record expected color observations.\n3. Submit your completed report in English (PDF format).",
                    'days' => 5,
                ],
                [
                    'class' => $classIcp,
                    'teacher' => $guruIcp,
                    'subject' => $subjectIcp ?? $subjectMtk,
                    'title' => 'Tugas Cambridge Math: Algebraic Modeling & Geometric Tessellation Challenge',
                    'description' => "Project Brief:\n1. Design a geometric tessellation pattern using transformations (translations, reflections, and rotations).\n2. Formulate 3 algebraic equations that represent perimeter and area scaling for your repeating units.\n3. Include a short 200-word mathematical reflection explaining your creative construction.",
                    'days' => 6,
                ],
                [
                    'class' => $classIcp,
                    'teacher' => $guruIcp,
                    'subject' => $subjectIcp ?? $subjectEng,
                    'title' => 'Tugas English Checkpoint: Analytical Essay on Marine Ecosystem Conservation',
                    'description' => "Writing Assessment:\n1. Write an argumentative essay (250-300 words) addressing the impact of single-use plastic waste on coastal marine life.\n2. Use persuasive devices: rhetorical questions, statistics from credible sources, counterarguments, and modal verbs.\n3. Strictly adhere to Cambridge Lower Secondary English grading criteria.",
                    'days' => 7,
                ],
                [
                    'class' => $classIcp,
                    'teacher' => $guruInggris,
                    'subject' => $subjectEng,
                    'title' => 'Tugas Global Perspectives: Research Summary on Clean Water Access Initiatives',
                    'description' => "Research Task:\n1. Research one international initiative (e.g. UNICEF WASH program or Water.org) providing clean water solutions in developing nations.\n2. Summarize the global vs local perspectives on community participation and sustainable maintenance.\n3. Provide reference citations in APA style.",
                    'days' => 8,
                ],
                [
                    'class' => $classIcp,
                    'teacher' => $guruInf,
                    'subject' => $subjectInf,
                    'title' => 'Tugas Informatika ICP: Python Control Structures & Algorithm Flowchart Design',
                    'description' => "Programming Exercise:\n1. Design an algorithm flowchart for calculating student grading boundaries (A, B, C, D) based on Cambridge percentage scores.\n2. Translate the flowchart into a clean Python script using if-elif-else statements.\n3. Upload your .py script and screenshot of 3 different test run executions.",
                    'days' => 9,
                ],

                // VII-D Inklusi
                [
                    'class' => $classInklusi,
                    'teacher' => $guruGpk,
                    'subject' => $subjectGpk ?? $subjectIndo,
                    'title' => 'Tugas PPI Adaptif: Portofolio Mengenal Emosi Diri & Regulasi Sensorik Harian',
                    'description' => "Panduan Siswa & Orang Tua:\n1. Lengkapi bagan roda emosi bergambar (senang, tenang, sedih, lelah) selama 5 hari belajar di sekolah.\n2. Pilih 1 teknik relaksasi favorit yang paling membantu saat merasa overstimulasi (misal: mendengarkan musik lembut, menarik napas 4-4-4, atau meremas sensory ball).\n3. Tuliskan atau ceritakan pengalaman Anda dibantu orang tua di rumah.",
                    'days' => 5,
                ],
                [
                    'class' => $classInklusi,
                    'teacher' => $guruIndo,
                    'subject' => $subjectIndo,
                    'title' => 'Tugas Bahasa Indonesia Ramah Sensori: Menceritakan Benda Kesayangan dengan Kartu Kata',
                    'description' => "Instruksi Ramah Pembelajar:\n1. Pilihlah 1 benda kesayangan yang ada di rumah (boneka, buku cerita, tanaman, atau mainan balok).\n2. Susunlah 3 kalimat sederhana menggunakan panduan kartu kata: nama benda, ciri fisik/warnanya, dan alasan mengapa Anda menyukainya.\n3. Rekam audio singkat (1 menit) atau foto tulisan indah Anda.",
                    'days' => 6,
                ],
                [
                    'class' => $classInklusi,
                    'teacher' => $guruMtk,
                    'subject' => $subjectMtk,
                    'title' => 'Tugas Matematika Konkret: Simulasi Belanja dengan Koin Pecahan & Benda Nyata',
                    'description' => "Aktivitas Matematika Fungsional:\n1. Siapkan 5 macam alat tulis dengan label harga sederhana (Rp1.000, Rp2.000, Rp5.000).\n2. Lakukan simulasi transaksi jual beli bersama orang tua atau pendamping.\n3. Hitung total belanjaan dan nominal uang kembalian secara tepat.",
                    'days' => 7,
                ],
                [
                    'class' => $classInklusi,
                    'teacher' => $guruIpa,
                    'subject' => $subjectIpa,
                    'title' => 'Tugas IPA Eksplorasi Lingkungan: Kolase Daun dan Pengamatan Warna Alami',
                    'description' => "Instruksi Belajar Berbasis Sensori:\n1. Kumpulkan 4 jenis daun berbeda bentuk yang ada di halaman rumah atau taman sekolah.\n2. Tempelkan daun-daun tersebut pada lembar kerja dan amati tekstur permukaannya (halus, kasar, berbulu).\n3. Berikan cap stiker atau centang pada kartu sensori yang disediakan guru pendamping.",
                    'days' => 8,
                ],
                [
                    'class' => $classInklusi,
                    'teacher' => $guruGpk,
                    'subject' => $subjectGpk ?? $subjectIndo,
                    'title' => 'Tugas Keterampilan Hidup: Kemandirian Merapikan Meja Belajar & Jadwal Pelajaran',
                    'description' => "Aktivitas Kecakapan Hidup Mandiri:\n1. Siapkan tas sekolah Anda pada sore hari dengan mencocokkan buku sesuai jadwal pelajaran esok hari.\n2. Bersihkan dan susun alat tulis di atas meja belajar agar rapi.\n3. Lampirkan foto meja belajar rapi dan checklist mandiri yang telah ditandatangani orang tua.",
                    'days' => 9,
                ],
            ];

            foreach ($tasksByClass as $t) {
                if ($t['class'] && $t['teacher'] && $t['subject']) {
                    Assignment::updateOrCreate(
                        [
                            'class_id' => $t['class']->id,
                            'title' => $t['title'],
                        ],
                        [
                            'subject_id' => $t['subject']->id,
                            'teacher_id' => $t['teacher']->id,
                            'description' => $t['description'],
                            'due_at' => Carbon::now()->addDays($t['days']),
                            'max_score' => '100.00',
                        ]
                    );
                }
            }

            // =========================================================================
            // 5. LIMA (5) BANK SOAL BEDA MAPEL
            // BANK 1 (IPA): MEMUAT SELURUH 6 MODEL SOAL + ILUSTRASI SVG SETIAP SOAL!
            // =========================================================================

            // --- ILUSTRASI SVG (Data URI) UNTUK TIAP MODEL SOAL IPA ---
            // 1. Diagram Sel Tumbuhan (SVG)
            $svgCell = 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 300" width="100%" height="100%"><rect width="600" height="300" fill="#f0fdf4" rx="16"/><rect x="40" y="30" width="520" height="240" rx="30" fill="#dcfce7" stroke="#16a34a" stroke-width="8"/><rect x="60" y="50" width="480" height="200" rx="20" fill="#bbf7d0" stroke="#22c55e" stroke-width="4"/><ellipse cx="320" cy="150" rx="140" ry="70" fill="#bae6fd" stroke="#0284c7" stroke-width="3"/><circle cx="160" cy="140" r="45" fill="#fde047" stroke="#ca8a04" stroke-width="3"/><circle cx="160" cy="140" r="20" fill="#a16207"/><ellipse cx="440" cy="90" rx="30" ry="18" fill="#4ade80" stroke="#15803d" stroke-width="2"/><ellipse cx="440" cy="210" rx="30" ry="18" fill="#4ade80" stroke="#15803d" stroke-width="2"/><text x="110" y="270" font-family="Arial, sans-serif" font-size="14" font-weight="bold" fill="#166534">Dinding Sel (Cell Wall)</text><text x="300" y="155" font-family="Arial, sans-serif" font-size="14" font-weight="bold" fill="#0369a1">Vakuola Besar</text><text x="135" y="145" font-family="Arial, sans-serif" font-size="13" font-weight="bold" fill="#713f12">Nukleus</text><text x="405" y="95" font-family="Arial, sans-serif" font-size="11" font-weight="bold" fill="#14532d">Kloroplas</text></svg>');

            // 2. Diagram Sistem Pencernaan Manusia (SVG)
            $svgDigestion = 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 320" width="100%" height="100%"><rect width="600" height="320" fill="#fff7ed" rx="16"/><path d="M300,30 Q300,100 290,120" stroke="#ea580c" stroke-width="14" fill="none"/><ellipse cx="290" cy="35" rx="35" ry="18" fill="#fdba74" stroke="#c2410c" stroke-width="3"/><path d="M280,120 Q360,110 350,170 Q330,210 270,180 Q240,150 280,120 Z" fill="#fca5a5" stroke="#dc2626" stroke-width="4"/><path d="M220,125 Q280,115 285,150 L220,165 Z" fill="#bbf7d0" stroke="#16a34a" stroke-width="3"/><rect x="250" y="190" width="100" height="60" rx="15" fill="#fde047" stroke="#ca8a04" stroke-width="4"/><path d="M230,250 C230,290 370,290 370,250 C370,210 230,210 230,270" stroke="#9333ea" stroke-width="12" fill="none"/><text x="350" y="40" font-family="Arial, sans-serif" font-size="13" font-weight="bold" fill="#9a3412">1. Rongga Mulut (Amilase)</text><text x="375" y="150" font-family="Arial, sans-serif" font-size="13" font-weight="bold" fill="#b91c1c">2. Lambung (HCl &amp; Pepsin)</text><text x="140" y="145" font-family="Arial, sans-serif" font-size="13" font-weight="bold" fill="#15803d">Hati (Empedu)</text><text x="365" y="210" font-family="Arial, sans-serif" font-size="13" font-weight="bold" fill="#854d0e">3. Pankreas (Tripsin, Lipase)</text><text x="385" y="270" font-family="Arial, sans-serif" font-size="13" font-weight="bold" fill="#6b21a8">4. Usus Halus</text></svg>');

            // 3. Diagram Hukum Pemantulan Cahaya Cermin Datar (SVG)
            $svgReflection = 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 300" width="100%" height="100%"><rect width="600" height="300" fill="#f8fafc" rx="16"/><line x1="60" y1="230" x2="540" y2="230" stroke="#334155" stroke-width="8"/><line x1="300" y1="50" x2="300" y2="230" stroke="#64748b" stroke-width="3" stroke-dasharray="8,6"/><line x1="120" y1="70" x2="300" y2="230" stroke="#2563eb" stroke-width="5"/><polygon points="215,145 200,155 205,135" fill="#2563eb"/><line x1="300" y1="230" x2="480" y2="70" stroke="#dc2626" stroke-width="5"/><polygon points="390,140 405,150 400,130" fill="#dc2626"/><path d="M260,230 A50,50 0 0,1 285,185" fill="none" stroke="#2563eb" stroke-width="3"/><path d="M315,185 A50,50 0 0,1 340,230" fill="none" stroke="#dc2626" stroke-width="3"/><text x="130" y="60" font-family="Arial, sans-serif" font-size="14" font-weight="bold" fill="#1d4ed8">Sinar Datang (i)</text><text x="440" y="60" font-family="Arial, sans-serif" font-size="14" font-weight="bold" fill="#b91c1c">Sinar Pantul (r)</text><text x="310" y="80" font-family="Arial, sans-serif" font-size="13" font-weight="bold" fill="#475569">Garis Normal (N)</text><text x="235" y="210" font-family="Arial, sans-serif" font-size="14" font-weight="bold" fill="#1d4ed8">&#952;i</text><text x="345" y="210" font-family="Arial, sans-serif" font-size="14" font-weight="bold" fill="#b91c1c">&#952;r</text><text x="210" y="265" font-family="Arial, sans-serif" font-size="14" font-weight="bold" fill="#0f172a">Permukaan Cermin Datar (&#952;i = &#952;r)</text></svg>');

            // 4. Diagram Alat Ukur Besaran Fisika (SVG)
            $svgInstruments = 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 300" width="100%" height="100%"><rect width="600" height="300" fill="#f1f5f9" rx="16"/><rect x="40" y="30" width="240" height="110" rx="12" fill="#e2e8f0" stroke="#475569" stroke-width="3"/><text x="55" y="60" font-family="Arial, sans-serif" font-size="13" font-weight="bold" fill="#1e293b">[A] Jangka Sorong (0.01 cm)</text><rect x="65" y="80" width="190" height="20" fill="#94a3b8"/><line x1="80" y1="80" x2="80" y2="95" stroke="#0f172a" stroke-width="2"/><line x1="120" y1="80" x2="120" y2="95" stroke="#0f172a" stroke-width="2"/><line x1="160" y1="80" x2="160" y2="95" stroke="#0f172a" stroke-width="2"/><line x1="200" y1="80" x2="200" y2="95" stroke="#0f172a" stroke-width="2"/><rect x="320" y="30" width="240" height="110" rx="12" fill="#e2e8f0" stroke="#475569" stroke-width="3"/><text x="335" y="60" font-family="Arial, sans-serif" font-size="13" font-weight="bold" fill="#1e293b">[B] Neraca Ohaus 3 Lengan</text><rect x="340" y="80" width="200" height="14" fill="#64748b"/><circle cx="370" cy="115" r="16" fill="#cbd5e1" stroke="#334155" stroke-width="2"/><rect x="40" y="160" width="240" height="110" rx="12" fill="#e2e8f0" stroke="#475569" stroke-width="3"/><text x="55" y="190" font-family="Arial, sans-serif" font-size="13" font-weight="bold" fill="#1e293b">[C] Stopwatch Analog (0.1 s)</text><circle cx="160" cy="225" r="30" fill="#ffffff" stroke="#0f172a" stroke-width="3"/><line x1="160" y1="225" x2="160" y2="205" stroke="#dc2626" stroke-width="3"/><rect x="320" y="160" width="240" height="110" rx="12" fill="#e2e8f0" stroke="#475569" stroke-width="3"/><text x="335" y="190" font-family="Arial, sans-serif" font-size="13" font-weight="bold" fill="#1e293b">[D] Termometer Alkohol/Raksa</text><rect x="420" y="200" width="16" height="60" rx="8" fill="#ffffff" stroke="#0f172a" stroke-width="2"/><rect x="424" y="225" width="8" height="35" rx="4" fill="#dc2626"/></svg>');

            // 5. Diagram Rangkaian Listrik Hukum Ohm (SVG)
            $svgCircuit = 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 280" width="100%" height="100%"><rect width="600" height="280" fill="#f8fafc" rx="16"/><rect x="80" y="40" width="440" height="200" fill="none" stroke="#1e293b" stroke-width="4"/><line x1="280" y1="30" x2="280" y2="50" stroke="#dc2626" stroke-width="5"/><line x1="295" y1="20" x2="295" y2="60" stroke="#16a34a" stroke-width="5"/><line x1="310" y1="30" x2="310" y2="50" stroke="#dc2626" stroke-width="5"/><line x1="325" y1="20" x2="325" y2="60" stroke="#16a34a" stroke-width="5"/><text x="270" y="80" font-family="Arial, sans-serif" font-size="14" font-weight="bold" fill="#1e293b">Sumber V = 12 Volt</text><rect x="250" y="220" width="100" height="40" fill="#fef08a" stroke="#ca8a04" stroke-width="3"/><text x="275" y="245" font-family="Arial, sans-serif" font-size="14" font-weight="bold" fill="#854d0e">R = 4 &#937;</text><circle cx="80" cy="140" r="22" fill="#e0f2fe" stroke="#0284c7" stroke-width="3"/><text x="73" y="146" font-family="Arial, sans-serif" font-size="15" font-weight="bold" fill="#0369a1">A</text><text x="115" y="145" font-family="Arial, sans-serif" font-size="13" font-weight="bold" fill="#0284c7">Kuat Arus (I = ?)</text></svg>');

            // 6. Diagram Daur Air / Siklus Hidrologi (SVG)
            $svgWaterCycle = 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 320" width="100%" height="100%"><rect width="600" height="320" fill="#f0f9ff" rx="16"/><circle cx="80" cy="60" r="35" fill="#facc15" stroke="#ca8a04" stroke-width="3"/><path d="M260,70 Q280,45 320,50 Q360,40 380,65 Q410,75 390,95 Q350,110 300,105 Q240,105 260,70 Z" fill="#94a3b8" stroke="#475569" stroke-width="3"/><polygon points="400,150 480,260 580,260 500,150" fill="#86efac" stroke="#15803d" stroke-width="2"/><rect x="0" y="240" width="600" height="80" fill="#38bdf8"/><path d="M120,230 Q140,160 160,110" stroke="#0284c7" stroke-width="4" stroke-dasharray="6,4" fill="none"/><polygon points="160,105 150,118 168,118" fill="#0284c7"/><text x="100" y="180" font-family="Arial, sans-serif" font-size="13" font-weight="bold" fill="#0369a1">1. Evaporasi</text><line x1="330" y1="120" x2="310" y2="190" stroke="#0284c7" stroke-width="4" stroke-dasharray="6,4"/><polygon points="308,198 305,185 318,187" fill="#0284c7"/><text x="330" y="160" font-family="Arial, sans-serif" font-size="13" font-weight="bold" fill="#0369a1">2. Presipitasi (Hujan)</text><text x="350" y="50" font-family="Arial, sans-serif" font-size="13" font-weight="bold" fill="#334155">Kondensasi Awan</text><text x="220" y="290" font-family="Arial, sans-serif" font-size="15" font-weight="bold" fill="#ffffff">Badan Air Permukaan &amp; Lautan</text></svg>');

            // =========================================================================
            // BANK 1: ILMU PENGETAHUAN ALAM (IPA) - LENGKAP 6 MODEL SOAL + ILUSTRASI
            // =========================================================================
            $bankIpa = QuestionBank::updateOrCreate(
                [
                    'school_id' => $schoolId,
                    'subject_id' => $subjectIpa->id,
                    'name' => 'Asesmen Komprehensif IPA Terpadu Kelas VII (Seluruh Model Soal & Ilustrasi Visual)',
                ],
                [
                    'grade_level_id' => $grade7->id,
                    'owner_id' => $guruIpa->user_id ?? User::first()->id,
                ]
            );

            // 1. Soal PG (Tunggal) - Biologi Sel
            $q1 = Question::updateOrCreate(
                ['bank_id' => $bankIpa->id, 'cp_code' => 'IPA-MOD-PG'],
                [
                    'question_type' => 'pg',
                    'body' => [
                        'text' => "Perhatikan ilustrasi diagram mikroskopis struktur sel di atas.\nBagian sel bernomor dan bertuliskan warna hijau terluar yang berfungsi memberikan bentuk kaku, kokoh, serta perlindungan mekanik pada sel tumbuhan adalah ...",
                        'content' => "Perhatikan ilustrasi diagram mikroskopis struktur sel di atas.\nBagian sel bernomor dan bertuliskan warna hijau terluar yang berfungsi memberikan bentuk kaku, kokoh, serta perlindungan mekanik pada sel tumbuhan adalah ...",
                        'media_url' => $svgCell,
                    ],
                    'cognitive_level' => 'LOTS',
                    'difficulty' => 1,
                    'weight' => '1.00',
                    'explanation' => 'Dinding sel tumbuhan tersusun atas selulosa pektin yang kuat dan kaku, membedakannya dari sel hewan yang hanya memiliki membran plasma elastis.',
                    'version' => 1,
                    'status' => 'aktif',
                ]
            );
            $q1Options = [
                ['label' => 'A', 'text' => 'Membran Plasma Fleksibel', 'is_correct' => false],
                ['label' => 'B', 'text' => 'Dinding Sel (Cell Wall)', 'is_correct' => true],
                ['label' => 'C', 'text' => 'Vakuola Penyimpan Cadangan Air', 'is_correct' => false],
                ['label' => 'D', 'text' => 'Nukleus Pengatur Keturunan', 'is_correct' => false],
            ];
            foreach ($q1Options as $opt) {
                QuestionOption::updateOrCreate(
                    ['question_id' => $q1->id, 'label' => $opt['label']],
                    ['body' => ['text' => $opt['text']], 'is_correct' => $opt['is_correct']]
                );
            }

            // 2. Soal PG Kompleks (Multi Select) - Sistem Pencernaan
            $q2 = Question::updateOrCreate(
                ['bank_id' => $bankIpa->id, 'cp_code' => 'IPA-MOD-PGK'],
                [
                    'question_type' => 'pg_kompleks',
                    'body' => [
                        'text' => "Perhatikan skema organ sistem pencernaan manusia pada ilustrasi di atas.\nPilihlah seluruh pernyataan berikut yang BENAR mengenai fungsi organ dan sekresi enzim pencernaan kimiawi (Pilih lebih dari satu):",
                        'content' => "Perhatikan skema organ sistem pencernaan manusia pada ilustrasi di atas.\nPilihlah seluruh pernyataan berikut yang BENAR mengenai fungsi organ dan sekresi enzim pencernaan kimiawi (Pilih lebih dari satu):",
                        'media_url' => $svgDigestion,
                    ],
                    'cognitive_level' => 'HOTS',
                    'difficulty' => 3,
                    'weight' => '2.00',
                    'explanation' => 'Kelenjar saliva menghasilkan enzim ptialin (amilase mulut). Lambung menghasilkan HCl dan enzim pepsin. Pankreas menghasilkan getah pankreas kaya amilase, tripsin, dan lipase.',
                    'version' => 1,
                    'status' => 'aktif',
                ]
            );
            $q2Options = [
                ['label' => 'A', 'text' => 'Kelenjar ludah di rongga mulut mensekresikan enzim ptialin (amilase) untuk memecah amilum menjadi maltosa', 'is_correct' => true],
                ['label' => 'B', 'text' => 'Lambung mensekresikan asam klorida (HCl) untuk membunuh bakteri patogen dan mengaktifkan pepsinogen', 'is_correct' => true],
                ['label' => 'C', 'text' => 'Hati memproduksi getah empedu yang disimpan di kantung empedu untuk mengemulsikan lemak', 'is_correct' => true],
                ['label' => 'D', 'text' => 'Usus besar (kolon) adalah tempat utama terjadinya pencernaan protein dan penyerapan glukosa', 'is_correct' => false],
            ];
            foreach ($q2Options as $opt) {
                QuestionOption::updateOrCreate(
                    ['question_id' => $q2->id, 'label' => $opt['label']],
                    ['body' => ['text' => $opt['text']], 'is_correct' => $opt['is_correct']]
                );
            }

            // 3. Soal Benar / Salah (BS) - Hukum Pemantulan Cahaya
            $q3 = Question::updateOrCreate(
                ['bank_id' => $bankIpa->id, 'cp_code' => 'IPA-MOD-BS'],
                [
                    'question_type' => 'bs',
                    'body' => [
                        'text' => "Perhatikan diagram jalannya berkas sinar pemantulan cahaya pada cermin datar di atas.\n'Menurut Hukum Snellius tentang Pemantulan Cahaya, besar sudut sinar datang (θi) yang diukur terhadap garis normal selalu sama persis dengan besar sudut sinar pantul (θr) terhadap garis normal.'",
                        'content' => "Perhatikan diagram jalannya berkas sinar pemantulan cahaya pada cermin datar di atas.\n'Menurut Hukum Snellius tentang Pemantulan Cahaya, besar sudut sinar datang (θi) yang diukur terhadap garis normal selalu sama persis dengan besar sudut sinar pantul (θr) terhadap garis normal.'",
                        'media_url' => $svgReflection,
                    ],
                    'cognitive_level' => 'LOTS',
                    'difficulty' => 1,
                    'weight' => '1.00',
                    'explanation' => 'Hukum Pemantulan Cahaya menyatakan bahwa sinar datang, sinar pantul, dan garis normal berada pada satu bidang datar, serta sudut datang sama dengan sudut pantul (θi = θr).',
                    'version' => 1,
                    'status' => 'aktif',
                ]
            );
            $q3Options = [
                ['label' => 'Benar', 'text' => 'Benar', 'is_correct' => true],
                ['label' => 'Salah', 'text' => 'Salah', 'is_correct' => false],
            ];
            foreach ($q3Options as $opt) {
                QuestionOption::updateOrCreate(
                    ['question_id' => $q3->id, 'label' => $opt['label']],
                    ['body' => ['text' => $opt['text']], 'is_correct' => $opt['is_correct']]
                );
            }

            // 4. Soal Menjodohkan (Matching) - Alat Ukur Besaran Fisika
            $q4 = Question::updateOrCreate(
                ['bank_id' => $bankIpa->id, 'cp_code' => 'IPA-MOD-MTC'],
                [
                    'question_type' => 'menjodohkan',
                    'body' => [
                        'text' => "Perhatikan keempat gambar instrumen alat ukur laboratorium sains pada ilustrasi di atas.\nPasangkan masing-masing instrumen ukur di kolom kiri dengan besaran fisika serta satuan internasional (SI) yang tepat di kolom kanan:",
                        'content' => "Perhatikan keempat gambar instrumen alat ukur laboratorium sains pada ilustrasi di atas.\nPasangkan masing-masing instrumen ukur di kolom kiri dengan besaran fisika serta satuan internasional (SI) yang tepat di kolom kanan:",
                        'media_url' => $svgInstruments,
                    ],
                    'cognitive_level' => 'MOTS',
                    'difficulty' => 2,
                    'weight' => '2.00',
                    'explanation' => 'Jangka sorong mengukur panjang/ketebalan (meter), Neraca Ohaus mengukur massa benda (kilogram), Stopwatch mengukur selang waktu (sekon), dan Termometer mengukur suhu/temperatur (Kelvin/Celsius).',
                    'version' => 1,
                    'status' => 'aktif',
                ]
            );
            $q4Options = [
                ['label' => '1', 'text' => '[A] Jangka Sorong Ketelitian 0,01 cm', 'match_key' => 'Mengukur Panjang & Diameter Benda (Meter)'],
                ['label' => '2', 'text' => '[B] Neraca Ohaus 3 Lengan Geser', 'match_key' => 'Mengukur Massa Benda Logam (Kilogram)'],
                ['label' => '3', 'text' => '[C] Stopwatch Analog Presisi 0,1 s', 'match_key' => 'Mengukur Selang Waktu Periode Getaran (Sekon)'],
                ['label' => '4', 'text' => '[D] Termometer Batang Skala Suhu', 'match_key' => 'Mengukur Derajat Panas Suhu Benda (Kelvin)'],
            ];
            foreach ($q4Options as $opt) {
                QuestionOption::updateOrCreate(
                    ['question_id' => $q4->id, 'label' => $opt['label']],
                    [
                        'body' => ['text' => $opt['text']],
                        'is_correct' => true,
                        'match_key' => $opt['match_key'],
                    ]
                );
            }

            // 5. Soal Isian Singkat - Hukum Ohm
            $q5 = Question::updateOrCreate(
                ['bank_id' => $bankIpa->id, 'cp_code' => 'IPA-MOD-ISN'],
                [
                    'question_type' => 'isian',
                    'body' => [
                        'text' => "Perhatikan skema rangkaian listrik arus searah (DC) pada ilustrasi diagram di atas.\nSebuah sumber tegangan baterai V = 12 Volt dihubungkan dengan sebuah resistor hambatan R = 4 Ohm.\nBerdasarkan Hukum Ohm (V = I x R), berapakah besar kuat arus listrik (I) dalam satuan Ampere yang terukur oleh Amperemeter? (Tuliskan hanya angka jawabannya):",
                        'content' => "Perhatikan skema rangkaian listrik arus searah (DC) pada ilustrasi diagram di atas.\nSebuah sumber tegangan baterai V = 12 Volt dihubungkan dengan sebuah resistor hambatan R = 4 Ohm.\nBerdasarkan Hukum Ohm (V = I x R), berapakah besar kuat arus listrik (I) dalam satuan Ampere yang terukur oleh Amperemeter? (Tuliskan hanya angka jawabannya):",
                        'media_url' => $svgCircuit,
                    ],
                    'cognitive_level' => 'MOTS',
                    'difficulty' => 2,
                    'weight' => '1.00',
                    'explanation' => 'I = V / R = 12 Volt / 4 Ohm = 3 Ampere.',
                    'version' => 1,
                    'status' => 'aktif',
                ]
            );
            QuestionOption::updateOrCreate(
                ['question_id' => $q5->id, 'label' => 'Kunci'],
                ['body' => ['text' => '3'], 'is_correct' => true]
            );

            // 6. Soal Uraian / Esai - Siklus Air & Konservasi
            $q6 = Question::updateOrCreate(
                ['bank_id' => $bankIpa->id, 'cp_code' => 'IPA-MOD-URN'],
                [
                    'question_type' => 'uraian',
                    'body' => [
                        'text' => "Perhatikan ilustrasi tahapan daur air (siklus hidrologi) pada gambar di atas.\n1. Jelaskan secara berurutan proses terjadinya evaporasi, kondensasi awan, dan presipitasi (hujan)!\n2. Uraikan dampak deforestasi (penebangan hutan gundul di area pegunungan) terhadap penurunan laju infiltrasi air tanah dan risiko banjir bandang!",
                        'content' => "Perhatikan ilustrasi tahapan daur air (siklus hidrologi) pada gambar di atas.\n1. Jelaskan secara berurutan proses terjadinya evaporasi, kondensasi awan, dan presipitasi (hujan)!\n2. Uraikan dampak deforestasi (penebangan hutan gundul di area pegunungan) terhadap penurunan laju infiltrasi air tanah dan risiko banjir bandang!",
                        'media_url' => $svgWaterCycle,
                    ],
                    'cognitive_level' => 'HOTS',
                    'difficulty' => 3,
                    'weight' => '3.00',
                    'explanation' => 'Kriteria Penilaian Esai: (1) Penjelasan tepat evaporasi (penguapan air laut oleh panas matahari), kondensasi (uap air menjadi butiran awan saat mendingin), dan presipitasi (jatuhnya air hujan ke bumi). (2) Analisis tepat bahwa tanpa vegetasi akar pohon, air hujan tidak terserap ke dalam tanah (infiltrasi menurun drastis) melainkan menjadi limpasan permukaan (surface runoff) yang memicu erosi, longsor, dan banjir bandang di hilir.',
                    'version' => 1,
                    'status' => 'aktif',
                ]
            );
            QuestionOption::updateOrCreate(
                ['question_id' => $q6->id, 'label' => 'Rubrik'],
                ['body' => ['text' => 'Rubrik penilaian otomatis/manual: memuat konsep evaporasi, kondensasi, presipitasi, infiltrasi, dan surface runoff.'], 'is_correct' => true]
            );

            // =========================================================================
            // BANK 2: MATEMATIKA (MTK)
            // =========================================================================
            $bankMtk = QuestionBank::updateOrCreate(
                [
                    'school_id' => $schoolId,
                    'subject_id' => $subjectMtk->id,
                    'name' => 'Bank Soal Matematika: Aljabar, Persamaan Linear & Aritmatika Sosial',
                ],
                [
                    'grade_level_id' => $grade7->id,
                    'owner_id' => $guruMtk->user_id ?? User::first()->id,
                ]
            );
            $qMtk = Question::updateOrCreate(
                ['bank_id' => $bankMtk->id, 'cp_code' => 'MTK-7-01'],
                [
                    'question_type' => 'pg',
                    'body' => [
                        'text' => "Bentuk paling sederhana dari aljabar: 4(2x - 3y) - 2(3x - 5y) adalah ...",
                        'content' => "Bentuk paling sederhana dari aljabar: 4(2x - 3y) - 2(3x - 5y) adalah ...",
                    ],
                    'cognitive_level' => 'MOTS',
                    'difficulty' => 2,
                    'weight' => '1.00',
                    'explanation' => '4(2x - 3y) - 2(3x - 5y) = 8x - 12y - 6x + 10y = 2x - 2y.',
                    'version' => 1,
                    'status' => 'aktif',
                ]
            );
            $qMtkOpts = [
                ['label' => 'A', 'text' => '2x - 2y', 'is_correct' => true],
                ['label' => 'B', 'text' => '2x + 2y', 'is_correct' => false],
                ['label' => 'C', 'text' => '14x - 22y', 'is_correct' => false],
                ['label' => 'D', 'text' => '2x - 22y', 'is_correct' => false],
            ];
            foreach ($qMtkOpts as $opt) {
                QuestionOption::updateOrCreate(
                    ['question_id' => $qMtk->id, 'label' => $opt['label']],
                    ['body' => ['text' => $opt['text']], 'is_correct' => $opt['is_correct']]
                );
            }

            // =========================================================================
            // BANK 3: BAHASA INGGRIS (BIG)
            // =========================================================================
            $bankEng = QuestionBank::updateOrCreate(
                [
                    'school_id' => $schoolId,
                    'subject_id' => $subjectEng->id,
                    'name' => 'Bank Soal Bahasa Inggris: Reading Comprehension & Functional Text Checkpoint',
                ],
                [
                    'grade_level_id' => $grade7->id,
                    'owner_id' => $guruInggris->user_id ?? User::first()->id,
                ]
            );
            $qEng = Question::updateOrCreate(
                ['bank_id' => $bankEng->id, 'cp_code' => 'ENG-7-01'],
                [
                    'question_type' => 'pg',
                    'body' => [
                        'text' => "Read the sentence below:\n'Neither Sarah nor her classmates ... present at the English debate club yesterday because of the heavy rain.'\nThe most appropriate verb phrase to complete the sentence is ...",
                        'content' => "Read the sentence below:\n'Neither Sarah nor her classmates ... present at the English debate club yesterday because of the heavy rain.'\nThe most appropriate verb phrase to complete the sentence is ...",
                    ],
                    'cognitive_level' => 'MOTS',
                    'difficulty' => 2,
                    'weight' => '1.00',
                    'explanation' => 'With "neither... nor...", the verb agrees with the subject closest to it ("her classmates" = plural) in the past tense ("yesterday" -> "were").',
                    'version' => 1,
                    'status' => 'aktif',
                ]
            );
            $qEngOpts = [
                ['label' => 'A', 'text' => 'was', 'is_correct' => false],
                ['label' => 'B', 'text' => 'were', 'is_correct' => true],
                ['label' => 'C', 'text' => 'is', 'is_correct' => false],
                ['label' => 'D', 'text' => 'are', 'is_correct' => false],
            ];
            foreach ($qEngOpts as $opt) {
                QuestionOption::updateOrCreate(
                    ['question_id' => $qEng->id, 'label' => $opt['label']],
                    ['body' => ['text' => $opt['text']], 'is_correct' => $opt['is_correct']]
                );
            }

            // =========================================================================
            // BANK 4: PAI & BUDI PEKERTI (PAI)
            // =========================================================================
            $bankPai = QuestionBank::updateOrCreate(
                [
                    'school_id' => $schoolId,
                    'subject_id' => $subjectPai->id,
                    'name' => 'Bank Soal PAI & Budi Pekerti: Ulumul Quran, Fiqih Thaharah & Akhlak',
                ],
                [
                    'grade_level_id' => $grade7->id,
                    'owner_id' => $guruPai->user_id ?? User::first()->id,
                ]
            );
            $qPai = Question::updateOrCreate(
                ['bank_id' => $bankPai->id, 'cp_code' => 'PAI-7-01'],
                [
                    'question_type' => 'pg',
                    'body' => [
                        'text' => "Apabila terdapat huruf Nun Sukun (نْ) atau Tanwin bertemu dengan salah satu huruf Halqiyah yaitu: Hamzah (ء), Ha (هـ), 'Ain (ع), Ha (ح), Ghain (غ), Kha (خ), maka hukum tajwid yang berlaku adalah ...",
                        'content' => "Apabila terdapat huruf Nun Sukun (نْ) atau Tanwin bertemu dengan salah satu huruf Halqiyah yaitu: Hamzah (ء), Ha (هـ), 'Ain (ع), Ha (ح), Ghain (غ), Kha (خ), maka hukum tajwid yang berlaku adalah ...",
                    ],
                    'cognitive_level' => 'LOTS',
                    'difficulty' => 1,
                    'weight' => '1.00',
                    'explanation' => 'Izhar Halqi dibaca jelas tanpa ghunnah/dengung saat nun mati atau tanwin bertemu dengan keenam huruf tenggorokan (halq).',
                    'version' => 1,
                    'status' => 'aktif',
                ]
            );
            $qPaiOpts = [
                ['label' => 'A', 'text' => 'Idgham Bighunnah', 'is_correct' => false],
                ['label' => 'B', 'text' => 'Ikhfa Haqiqi', 'is_correct' => false],
                ['label' => 'C', 'text' => 'Izhar Halqi', 'is_correct' => true],
                ['label' => 'D', 'text' => 'Iqlab', 'is_correct' => false],
            ];
            foreach ($qPaiOpts as $opt) {
                QuestionOption::updateOrCreate(
                    ['question_id' => $qPai->id, 'label' => $opt['label']],
                    ['body' => ['text' => $opt['text']], 'is_correct' => $opt['is_correct']]
                );
            }

            // =========================================================================
            // BANK 5: INFORMATIKA & CODING (INF)
            // =========================================================================
            $bankInf = QuestionBank::updateOrCreate(
                [
                    'school_id' => $schoolId,
                    'subject_id' => $subjectInf->id,
                    'name' => 'Bank Soal Informatika: Computational Thinking & Algoritma Pemrograman',
                ],
                [
                    'grade_level_id' => $grade7->id,
                    'owner_id' => $guruInf->user_id ?? User::first()->id,
                ]
            );
            $qInf = Question::updateOrCreate(
                ['bank_id' => $bankInf->id, 'cp_code' => 'INF-7-01'],
                [
                    'question_type' => 'pg',
                    'body' => [
                        'text' => "Dalam pilar Berpikir Komputasional (Computational Thinking), teknik memecah masalah yang rumit atau kompleks menjadi bagian-bagian yang lebih kecil dan mudah dikelola disebut ...",
                        'content' => "Dalam pilar Berpikir Komputasional (Computational Thinking), teknik memecah masalah yang rumit atau kompleks menjadi bagian-bagian yang lebih kecil dan mudah dikelola disebut ...",
                    ],
                    'cognitive_level' => 'LOTS',
                    'difficulty' => 1,
                    'weight' => '1.00',
                    'explanation' => 'Dekomposisi (Decomposition) adalah teknik memecah masalah besar menjadi sub-masalah yang lebih kecil agar dapat dianalisis dan diselesaikan secara efisien.',
                    'version' => 1,
                    'status' => 'aktif',
                ]
            );
            $qInfOpts = [
                ['label' => 'A', 'text' => 'Abstraksi (Abstraction)', 'is_correct' => false],
                ['label' => 'B', 'text' => 'Dekomposisi (Decomposition)', 'is_correct' => true],
                ['label' => 'C', 'text' => 'Pengenalan Pola (Pattern Recognition)', 'is_correct' => false],
                ['label' => 'D', 'text' => 'Perancangan Algoritma (Algorithm Design)', 'is_correct' => false],
            ];
            foreach ($qInfOpts as $opt) {
                QuestionOption::updateOrCreate(
                    ['question_id' => $qInf->id, 'label' => $opt['label']],
                    ['body' => ['text' => $opt['text']], 'is_correct' => $opt['is_correct']]
                );
            }
        });
    }
}
