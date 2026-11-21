<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\ExamQuestion;
use App\Models\ExamRoom;
use App\Models\GradeLevel;
use App\Models\Permission;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Role;
use App\Models\Room;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\SiteSetting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CbtMultiLevelSeeder extends Seeder
{
    public const DEFAULT_SCHOOL_ID = '00000000-0000-0000-0000-000000000001';

    public function run(): void
    {
        $schoolId = SiteSetting::first()?->school_id ?? self::DEFAULT_SCHOOL_ID;
        $defaultPassword = Hash::make('SistLabUM2026!UAT');

        DB::beginTransaction();

        try {
            // 1. Roles & Permissions Hardening for Admin & Teachers
            $roleSuperAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Administrator', 'is_system' => true]);
            $roleAdminTu = Role::firstOrCreate(['code' => 'admin_tu'], ['name' => 'Admin Tata Usaha', 'is_system' => true]);
            $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
            $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

            $cbtBankWrite = Permission::firstOrCreate(['code' => 'cbt.bank.write'], ['description' => 'Membuat, mengubah, dan mengarsipkan bank soal']);
            $cbtExamManage = Permission::firstOrCreate(['code' => 'cbt.exam.manage'], ['description' => 'Membuat dan mengonfigurasi jadwal ujian CBT']);
            $cbtProctor = Permission::firstOrCreate(['code' => 'cbt.proctor.action'], ['description' => 'Aksi pengawas CBT dan manajemen token']);

            // Pastikan Admin TU dan Super Admin memiliki izin kelola & edit soal
            $roleSuperAdmin->permissions()->syncWithoutDetaching([$cbtBankWrite->id, $cbtExamManage->id, $cbtProctor->id]);
            $roleAdminTu->permissions()->syncWithoutDetaching([$cbtBankWrite->id, $cbtExamManage->id, $cbtProctor->id]);
            $roleTeacher->permissions()->syncWithoutDetaching([$cbtBankWrite->id, $cbtProctor->id]);

            // Ensure Admin User for Testing Edit
            $adminUser = User::where('username', 'superadmin')->first();
            if (! $adminUser) {
                $adminUser = User::firstOrCreate(
                    ['username' => 'admin.staging'],
                    [
                        'school_id' => $schoolId,
                        'email' => 'admin.staging@smp-labum.sch.id',
                        'password_hash' => $defaultPassword,
                        'status' => 'active',
                    ]
                );
            }
            $adminUser->roles()->syncWithoutDetaching([$roleSuperAdmin->id]);

            // 2. Academic Year & Semester
            $academicYear = AcademicYear::firstOrCreate(
                ['school_id' => $schoolId, 'name' => '2026/2027'],
                ['start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]
            );

            $semester = Semester::firstOrCreate(
                ['academic_year_id' => $academicYear->id, 'name' => 'Ganjil'],
                ['start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => true]
            );

            // 3. Setup 3 Grade Levels (Tingkat 7, 8, dan 9)
            $grade7 = GradeLevel::firstOrCreate(['school_id' => $schoolId, 'name' => 'Kelas 7'], ['sort_order' => 7]);
            $grade8 = GradeLevel::firstOrCreate(['school_id' => $schoolId, 'name' => 'Kelas 8'], ['sort_order' => 8]);
            $grade9 = GradeLevel::firstOrCreate(['school_id' => $schoolId, 'name' => 'Kelas 9'], ['sort_order' => 9]);

            // 4. Setup Rooms for 3 Levels
            $room7A = Room::firstOrCreate(['school_id' => $schoolId, 'code' => 'R-7A'], ['name' => 'Ruang Teori 7-A', 'room_type' => 'kelas', 'capacity' => 36]);
            $room8A = Room::firstOrCreate(['school_id' => $schoolId, 'code' => 'R-8A'], ['name' => 'Ruang Teori 8-A', 'room_type' => 'kelas', 'capacity' => 36]);
            $room9A = Room::firstOrCreate(['school_id' => $schoolId, 'code' => 'R-9A'], ['name' => 'Ruang Teori 9-A', 'room_type' => 'kelas', 'capacity' => 36]);

            // 5. Setup School Classes
            $class7A = SchoolClass::firstOrCreate(
                ['school_id' => $schoolId, 'academic_year_id' => $academicYear->id, 'name' => '7-A'],
                ['grade_level_id' => $grade7->id, 'capacity' => 36]
            );
            $class8A = SchoolClass::firstOrCreate(
                ['school_id' => $schoolId, 'academic_year_id' => $academicYear->id, 'name' => '8-A'],
                ['grade_level_id' => $grade8->id, 'capacity' => 36]
            );
            $class9A = SchoolClass::firstOrCreate(
                ['school_id' => $schoolId, 'academic_year_id' => $academicYear->id, 'name' => '9-A'],
                ['grade_level_id' => $grade9->id, 'capacity' => 36]
            );

            // 6. Setup Subjects
            $subjectsData = [
                'MTK-7' => ['name' => 'Matematika Kelas 7', 'group' => 'wajib'],
                'INF-7' => ['name' => 'Informatika Kelas 7', 'group' => 'wajib'],
                'IPA-7' => ['name' => 'IPA Kelas 7', 'group' => 'wajib'],
                'MTK-8' => ['name' => 'Matematika Kelas 8', 'group' => 'wajib'],
                'INF-8' => ['name' => 'Informatika Kelas 8', 'group' => 'wajib'],
                'IPA-8' => ['name' => 'IPA Kelas 8', 'group' => 'wajib'],
                'MTK-9' => ['name' => 'Matematika Kelas 9', 'group' => 'wajib'],
                'INF-9' => ['name' => 'Informatika Kelas 9', 'group' => 'wajib'],
                'IPA-9' => ['name' => 'IPA Kelas 9', 'group' => 'wajib'],
            ];

            $subjects = [];
            foreach ($subjectsData as $code => $info) {
                $subjects[$code] = Subject::firstOrCreate(
                    ['school_id' => $schoolId, 'code' => $code],
                    ['name' => $info['name'], 'subject_group' => $info['group']]
                );
            }

            // 7. Enrolling Students for 3 Levels
            // Level 7: siswa.lab.001 - siswa.lab.005
            $students7 = [];
            for ($i = 1; $i <= 5; $i++) {
                $uname = 'siswa.lab.'.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
                $u = User::firstOrCreate(['username' => $uname], [
                    'school_id' => $schoolId,
                    'email' => "{$uname}@smp-labum.sch.id",
                    'password_hash' => $defaultPassword,
                    'status' => 'active',
                ]);
                $u->roles()->syncWithoutDetaching([$roleStudent->id]);
                $std = Student::firstOrCreate(['user_id' => $u->id], [
                    'school_id' => $schoolId,
                    'nis' => (string) (26000 + $i),
                    'nisn' => '0099'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                    'nik_enc' => '357801'.str_pad((string) $i, 10, '0', STR_PAD_LEFT),
                    'full_name' => $i === 1 ? 'Ahmad Fauzi' : "Siswa Tingkat 7 #{$i}",
                    'gender' => $i % 2 === 1 ? 'L' : 'P',
                    'birth_place' => 'Malang',
                    'birth_date' => '2012-05-15',
                    'religion' => 'Islam',
                    'status' => 'aktif',
                    'entry_date' => '2026-07-01',
                ]);
                ClassStudent::firstOrCreate(['class_id' => $class7A->id, 'student_id' => $std->id], ['joined_at' => '2026-07-01']);
                $students7[] = $std;
            }

            // Level 8: siswa.lab.801 - siswa.lab.805
            $students8 = [];
            for ($i = 1; $i <= 5; $i++) {
                $uname = "siswa.lab.80{$i}";
                $u = User::firstOrCreate(['username' => $uname], [
                    'school_id' => $schoolId,
                    'email' => "{$uname}@smp-labum.sch.id",
                    'password_hash' => $defaultPassword,
                    'status' => 'active',
                ]);
                $u->roles()->syncWithoutDetaching([$roleStudent->id]);
                $std = Student::firstOrCreate(['user_id' => $u->id], [
                    'school_id' => $schoolId,
                    'nis' => (string) (25000 + $i),
                    'nisn' => '0088'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                    'nik_enc' => '357802'.str_pad((string) $i, 10, '0', STR_PAD_LEFT),
                    'full_name' => "Siswa Tingkat 8 #{$i}",
                    'gender' => $i % 2 === 1 ? 'L' : 'P',
                    'birth_place' => 'Malang',
                    'birth_date' => '2011-04-12',
                    'religion' => 'Islam',
                    'status' => 'aktif',
                    'entry_date' => '2025-07-01',
                ]);
                ClassStudent::firstOrCreate(['class_id' => $class8A->id, 'student_id' => $std->id], ['joined_at' => '2025-07-01']);
                $students8[] = $std;
            }

            // Level 9: siswa.lab.901 - siswa.lab.905
            $students9 = [];
            for ($i = 1; $i <= 5; $i++) {
                $uname = "siswa.lab.90{$i}";
                $u = User::firstOrCreate(['username' => $uname], [
                    'school_id' => $schoolId,
                    'email' => "{$uname}@smp-labum.sch.id",
                    'password_hash' => $defaultPassword,
                    'status' => 'active',
                ]);
                $u->roles()->syncWithoutDetaching([$roleStudent->id]);
                $std = Student::firstOrCreate(['user_id' => $u->id], [
                    'school_id' => $schoolId,
                    'nis' => (string) (24000 + $i),
                    'nisn' => '0077'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                    'nik_enc' => '357803'.str_pad((string) $i, 10, '0', STR_PAD_LEFT),
                    'full_name' => "Siswa Tingkat 9 #{$i}",
                    'gender' => $i % 2 === 1 ? 'L' : 'P',
                    'birth_place' => 'Malang',
                    'birth_date' => '2010-02-18',
                    'religion' => 'Islam',
                    'status' => 'aktif',
                    'entry_date' => '2024-07-01',
                ]);
                ClassStudent::firstOrCreate(['class_id' => $class9A->id, 'student_id' => $std->id], ['joined_at' => '2024-07-01']);
                $students9[] = $std;
            }

            // 8. Helper function to seed questions covering all types
            $seedQuestions = function (QuestionBank $bank, array $items) {
                $saved = [];
                foreach ($items as $data) {
                    $question = Question::updateOrCreate(
                        [
                            'bank_id' => $bank->id,
                            'cp_code' => $data['cp_code'],
                        ],
                        [
                            'question_type' => $data['type'],
                            'body' => $data['body'],
                            'cognitive_level' => $data['cognitive_level'] ?? 'MOTS',
                            'difficulty' => $data['difficulty'] ?? 3,
                            'weight' => $data['weight'] ?? 10.0,
                            'explanation' => $data['explanation'] ?? null,
                            'version' => 1,
                            'status' => 'aktif',
                        ]
                    );

                    $question->options()->delete();
                    foreach ($data['options'] as $opt) {
                        QuestionOption::create([
                            'question_id' => $question->id,
                            'label' => $opt['label'],
                            'body' => $opt['body'],
                            'is_correct' => $opt['is_correct'] ?? false,
                            'match_key' => $opt['match_key'] ?? null,
                        ]);
                    }
                    $saved[] = $question;
                }

                return $saved;
            };

            // =========================================================================
            // 9. CONFIGURING 9 QUESTION BANKS (3 LEVELS x 3 BANKS)
            // =========================================================================
            $banksData = [
                // ─── TINGKAT 1 (KELAS 7) ───
                'bank_7_mtk' => [
                    'name' => 'Bank Soal Matematika Kelas 7 - Aljabar & Bilangan',
                    'subject' => $subjects['MTK-7'],
                    'grade' => $grade7,
                    'questions' => [
                        [
                            'cp_code' => 'MTK7-CP01-PG',
                            'type' => 'pg',
                            'cognitive_level' => 'LOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Bentuk paling sederhana dari 3(2x - 5y) - 2(x - 4y) adalah ...', 'text' => 'Bentuk paling sederhana dari 3(2x - 5y) - 2(x - 4y) adalah ...'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => '4x - 7y', 'content' => '4x - 7y'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => '4x - 23y', 'content' => '4x - 23y'], 'is_correct' => false],
                                ['label' => 'C', 'body' => ['text' => '5x - 7y', 'content' => '5x - 7y'], 'is_correct' => false],
                                ['label' => 'D', 'body' => ['text' => '4x + 7y', 'content' => '4x + 7y'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'MTK7-CP02-PGK',
                            'type' => 'pg_kompleks',
                            'cognitive_level' => 'MOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Diberikan himpunan S = {bilangan bulat dari 1 sampai 20}. Manakah pernyataan yang benar? (Pilih semua yang benar)', 'text' => 'Diberikan himpunan S = {bilangan bulat dari 1 sampai 20}. Manakah pernyataan yang benar? (Pilih semua yang benar)'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => 'Bilangan 2 adalah satu-satunya bilangan genap yang prima di S', 'content' => 'Bilangan 2 adalah satu-satunya bilangan genap yang prima di S'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => 'Bilangan prima antara 10 dan 20 ada sebanyak 4 buah', 'content' => 'Bilangan prima antara 10 dan 20 ada sebanyak 4 buah'], 'is_correct' => true],
                                ['label' => 'C', 'body' => ['text' => 'Semua bilangan ganjil di S adalah bilangan prima', 'content' => 'Semua bilangan ganjil di S adalah bilangan prima'], 'is_correct' => false],
                                ['label' => 'D', 'body' => ['text' => 'Angka 9 dan 15 bukan merupakan bilangan prima', 'content' => 'Angka 9 dan 15 bukan merupakan bilangan prima'], 'is_correct' => true],
                            ],
                        ],
                        [
                            'cp_code' => 'MTK7-CP03-BS',
                            'type' => 'bs',
                            'cognitive_level' => 'MOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Pernyataan Aritmatika: Sebuah buku seharga Rp50.000 didiskon 20%, maka harga bayar menjadi Rp40.000.', 'text' => 'Pernyataan Aritmatika: Sebuah buku seharga Rp50.000 didiskon 20%, maka harga bayar menjadi Rp40.000.'],
                            'options' => [
                                ['label' => 'BENAR', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                                ['label' => 'SALAH', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'MTK7-CP04-MATCH',
                            'type' => 'menjodohkan',
                            'cognitive_level' => 'MOTS',
                            'weight' => 20.0,
                            'body' => ['content' => 'Pasangkan bangun datar di Kolom Kiri dengan rumus luasnya di Kolom Kanan!', 'text' => 'Pasangkan bangun datar di Kolom Kiri dengan rumus luasnya di Kolom Kanan!'],
                            'options' => [
                                ['label' => 'P1', 'body' => ['text' => '1/2 x a x t', 'content' => '1/2 x a x t'], 'is_correct' => false, 'match_key' => 'Segitiga'],
                                ['label' => 'P2', 'body' => ['text' => 'a x t', 'content' => 'a x t'], 'is_correct' => false, 'match_key' => 'Jajargenjang'],
                                ['label' => 'P3', 'body' => ['text' => '1/2 x (a + b) x t', 'content' => '1/2 x (a + b) x t'], 'is_correct' => false, 'match_key' => 'Trapesium'],
                            ],
                        ],
                        [
                            'cp_code' => 'MTK7-CP05-ISIAN',
                            'type' => 'isian',
                            'cognitive_level' => 'LOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Tentukan penyelesaian nilai x dari persamaan: 5x - 14 = 2x + 16', 'text' => 'Tentukan penyelesaian nilai x dari persamaan: 5x - 14 = 2x + 16', 'correct_answer' => '10'],
                            'options' => [
                                ['label' => 'JAWABAN', 'body' => ['text' => '10', 'content' => '10'], 'is_correct' => true],
                            ],
                        ],
                        [
                            'cp_code' => 'MTK7-CP06-URAIAN',
                            'type' => 'uraian',
                            'cognitive_level' => 'HOTS',
                            'weight' => 20.0,
                            'body' => ['content' => 'Kebun hidroponik berbentuk persegi panjang berukuran panjang (3x + 4) meter dan lebar (2x - 1) meter. Jika kelilingnya tidak boleh lebih dari 46 meter, tentukan nilai maksimum x dan hitunglah luas maksimumnya!', 'text' => 'Kebun hidroponik berbentuk persegi panjang berukuran panjang (3x + 4) meter dan lebar (2x - 1) meter. Jika kelilingnya tidak boleh lebih dari 46 meter, tentukan nilai maksimum x dan hitunglah luas maksimumnya!'],
                            'explanation' => 'K = 2(5x + 3) <= 46 => 10x <= 40 => x <= 4. Untuk x=4, p=16, l=7, maka Luas = 112 m2.',
                            'options' => [],
                        ],
                        [
                            'cp_code' => 'MTK7-CP07-PG',
                            'type' => 'pg',
                            'cognitive_level' => 'LOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Hasil dari (-15) + (-8) - (-12) adalah ...', 'text' => 'Hasil dari (-15) + (-8) - (-12) adalah ...'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => '-11', 'content' => '-11'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => '-35', 'content' => '-35'], 'is_correct' => false],
                                ['label' => 'C', 'body' => ['text' => '5', 'content' => '5'], 'is_correct' => false],
                                ['label' => 'D', 'body' => ['text' => '-19', 'content' => '-19'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'MTK7-CP08-PGK',
                            'type' => 'pg_kompleks',
                            'cognitive_level' => 'MOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Manakah di antara bentuk aljabar berikut yang memiliki konstanta bernilai negatif? (Pilih semua yang benar)', 'text' => 'Manakah di antara bentuk aljabar berikut yang memiliki konstanta bernilai negatif? (Pilih semua yang benar)'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => '3x^2 - 4x - 7', 'content' => '3x^2 - 4x - 7'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => '5y + 12', 'content' => '5y + 12'], 'is_correct' => false],
                                ['label' => 'C', 'body' => ['text' => '2a^2 + 3b - 9', 'content' => '2a^2 + 3b - 9'], 'is_correct' => true],
                                ['label' => 'D', 'body' => ['text' => 'x^2 - 1', 'content' => 'x^2 - 1'], 'is_correct' => true],
                            ],
                        ],
                        [
                            'cp_code' => 'MTK7-CP09-BS',
                            'type' => 'bs',
                            'cognitive_level' => 'LOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Pernyataan: Nilai dari FPB dari 24 dan 36 adalah 12.', 'text' => 'Pernyataan: Nilai dari FPB dari 24 dan 36 adalah 12.'],
                            'options' => [
                                ['label' => 'BENAR', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                                ['label' => 'SALAH', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'MTK7-CP10-ISIAN',
                            'type' => 'isian',
                            'cognitive_level' => 'LOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Jika 4m + 5 = 25, maka nilai m adalah ...', 'text' => 'Jika 4m + 5 = 25, maka nilai m adalah ...', 'correct_answer' => '5'],
                            'options' => [
                                ['label' => 'JAWABAN', 'body' => ['text' => '5', 'content' => '5'], 'is_correct' => true],
                            ],
                        ],
                    ],
                ],
                'bank_7_inf' => [
                    'name' => 'Bank Soal Informatika Kelas 7 - Berpikir Komputasional & Scratch',
                    'subject' => $subjects['INF-7'],
                    'grade' => $grade7,
                    'questions' => [
                        [
                            'cp_code' => 'INF7-CP01-PG',
                            'type' => 'pg',
                            'cognitive_level' => 'LOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Memecah masalah yang kompleks menjadi sub-masalah yang lebih kecil dalam berpikir komputasional disebut ...', 'text' => 'Memecah masalah yang kompleks menjadi sub-masalah yang lebih kecil dalam berpikir komputasional disebut ...'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => 'Dekomposisi', 'content' => 'Dekomposisi'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => 'Abstraksi', 'content' => 'Abstraksi'], 'is_correct' => false],
                                ['label' => 'C', 'body' => ['text' => 'Pengenalan Pola', 'content' => 'Pengenalan Pola'], 'is_correct' => false],
                                ['label' => 'D', 'body' => ['text' => 'Algoritma', 'content' => 'Algoritma'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'INF7-CP02-PGK',
                            'type' => 'pg_kompleks',
                            'cognitive_level' => 'MOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Manakah tindakan penerapan keamanan digital (cyber hygiene) yang benar di komputer lab? (Pilih semua yang benar)', 'text' => 'Manakah tindakan penerapan keamanan digital (cyber hygiene) yang benar di komputer lab? (Pilih semua yang benar)'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => 'Mengaktifkan verifikasi 2 langkah (2FA)', 'content' => 'Mengaktifkan verifikasi 2 langkah (2FA)'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => 'Selalu Log Out setelah selesai memakai komputer lab', 'content' => 'Selalu Log Out setelah selesai memakai komputer lab'], 'is_correct' => true],
                                ['label' => 'C', 'body' => ['text' => 'Mengklik tautan hadiah mencurigakan', 'content' => 'Mengklik tautan hadiah mencurigakan'], 'is_correct' => false],
                                ['label' => 'D', 'body' => ['text' => 'Menggunakan password unik kombinasi simbol dan angka', 'content' => 'Menggunakan password unik kombinasi simbol dan angka'], 'is_correct' => true],
                            ],
                        ],
                        [
                            'cp_code' => 'INF7-CP03-BS',
                            'type' => 'bs',
                            'cognitive_level' => 'LOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Pernyataan: Pada aplikasi visual Scratch, blok warna hijau digunakan untuk blok kode Operator matematika dan logika.', 'text' => 'Pernyataan: Pada aplikasi visual Scratch, blok warna hijau digunakan untuk blok kode Operator matematika dan logika.'],
                            'options' => [
                                ['label' => 'BENAR', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                                ['label' => 'SALAH', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'INF7-CP04-MATCH',
                            'type' => 'menjodohkan',
                            'cognitive_level' => 'MOTS',
                            'weight' => 20.0,
                            'body' => ['content' => 'Pasangkan berkas digital di Kolom Kiri dengan ekstensi format filenya di Kolom Kanan!', 'text' => 'Pasangkan berkas digital di Kolom Kiri dengan ekstensi format filenya di Kolom Kanan!'],
                            'options' => [
                                ['label' => 'P1', 'body' => ['text' => '.pdf', 'content' => '.pdf'], 'is_correct' => false, 'match_key' => 'Dokumen Naskah Portabel'],
                                ['label' => 'P2', 'body' => ['text' => '.mp4', 'content' => '.mp4'], 'is_correct' => false, 'match_key' => 'Berkas Video Terkompresi'],
                                ['label' => 'P3', 'body' => ['text' => '.svg', 'content' => '.svg'], 'is_correct' => false, 'match_key' => 'Gambar Vektor Skalabel'],
                            ],
                        ],
                        [
                            'cp_code' => 'INF7-CP05-ISIAN',
                            'type' => 'isian',
                            'cognitive_level' => 'MOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Konversikan bilangan biner 1101 ke dalam sistem bilangan desimal!', 'text' => 'Konversikan bilangan biner 1101 ke dalam sistem bilangan desimal!', 'correct_answer' => '13'],
                            'options' => [
                                ['label' => 'JAWABAN', 'body' => ['text' => '13', 'content' => '13'], 'is_correct' => true],
                            ],
                        ],
                        [
                            'cp_code' => 'INF7-CP06-URAIAN',
                            'type' => 'uraian',
                            'cognitive_level' => 'HOTS',
                            'weight' => 20.0,
                            'body' => ['content' => 'Buatlah pseudocode program kasir sekolah dengan logika: jika total belanja di atas Rp100.000 mendapat diskon 10%, jika di bawahnya diskon 0%!', 'text' => 'Buatlah pseudocode program kasir sekolah dengan logika: jika total belanja di atas Rp100.000 mendapat diskon 10%, jika di bawahnya diskon 0%!'],
                            'explanation' => 'Input total; If total > 100000 then diskon = 0.1 * total else diskon = 0; total_bayar = total - diskon; Output total_bayar.',
                            'options' => [],
                        ],
                        [
                            'cp_code' => 'INF7-CP07-PG',
                            'type' => 'pg',
                            'cognitive_level' => 'LOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Shortcut keyboard standar untuk membatalkan perintah terakhir (Undo) adalah ...', 'text' => 'Shortcut keyboard standar untuk membatalkan perintah terakhir (Undo) adalah ...'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => 'Ctrl + Z', 'content' => 'Ctrl + Z'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => 'Ctrl + Y', 'content' => 'Ctrl + Y'], 'is_correct' => false],
                                ['label' => 'C', 'body' => ['text' => 'Ctrl + C', 'content' => 'Ctrl + C'], 'is_correct' => false],
                                ['label' => 'D', 'body' => ['text' => 'Ctrl + X', 'content' => 'Ctrl + X'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'INF7-CP08-PGK',
                            'type' => 'pg_kompleks',
                            'cognitive_level' => 'MOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Manakah yang termasuk media transmisi kabel jaringan? (Pilih semua yang benar)', 'text' => 'Manakah yang termasuk media transmisi kabel jaringan? (Pilih semua yang benar)'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => 'Kabel UTP / STP (Twisted Pair)', 'content' => 'Kabel UTP / STP (Twisted Pair)'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => 'Kabel Fiber Optik', 'content' => 'Kabel Fiber Optik'], 'is_correct' => true],
                                ['label' => 'C', 'body' => ['text' => 'Gelombang Mikro (Microwave)', 'content' => 'Gelombang Mikro (Microwave)'], 'is_correct' => false],
                                ['label' => 'D', 'body' => ['text' => 'Kabel Koaksial (Coaxial)', 'content' => 'Kabel Koaksial (Coaxial)'], 'is_correct' => true],
                            ],
                        ],
                        [
                            'cp_code' => 'INF7-CP09-BS',
                            'type' => 'bs',
                            'cognitive_level' => 'LOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Pernyataan: CPU disebut sebagai otak utama komputer karena menjalankan seluruh instruksi program.', 'text' => 'Pernyataan: CPU disebut sebagai otak utama komputer karena menjalankan seluruh instruksi program.'],
                            'options' => [
                                ['label' => 'BENAR', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                                ['label' => 'SALAH', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'INF7-CP10-ISIAN',
                            'type' => 'isian',
                            'cognitive_level' => 'LOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Ekstensi file proyek animasi pemrograman Scratch versi 3 adalah .sb ...', 'text' => 'Ekstensi file proyek animasi pemrograman Scratch versi 3 adalah .sb ...', 'correct_answer' => '3'],
                            'options' => [
                                ['label' => 'JAWABAN', 'body' => ['text' => '3', 'content' => '3'], 'is_correct' => true],
                            ],
                        ],
                    ],
                ],
                'bank_7_ipa' => [
                    'name' => 'Bank Soal IPA Terpadu Kelas 7 - Objek IPA, Pengukuran & Ekosistem',
                    'subject' => $subjects['IPA-7'],
                    'grade' => $grade7,
                    'questions' => [
                        [
                            'cp_code' => 'IPA7-CP01-PG',
                            'type' => 'pg',
                            'cognitive_level' => 'LOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Batu bermassa 70 gram dimasukkan ke dalam gelas ukur berisi 50 mL air hingga volume naik menjadi 78 mL. Massa jenis batu tersebut adalah ...', 'text' => 'Batu bermassa 70 gram dimasukkan ke dalam gelas ukur berisi 50 mL air hingga volume naik menjadi 78 mL. Massa jenis batu tersebut adalah ...'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => '1,40 g/cm3', 'content' => '1,40 g/cm3'], 'is_correct' => false],
                                ['label' => 'B', 'body' => ['text' => '2,00 g/cm3', 'content' => '2,00 g/cm3'], 'is_correct' => false],
                                ['label' => 'C', 'body' => ['text' => '2,50 g/cm3', 'content' => '2,50 g/cm3'], 'is_correct' => true],
                                ['label' => 'D', 'body' => ['text' => '2,80 g/cm3', 'content' => '2,80 g/cm3'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'IPA7-CP02-PGK',
                            'type' => 'pg_kompleks',
                            'cognitive_level' => 'MOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Rantai makanan kebun: Rumput -> Belalang -> Katak -> Ular -> Elang. Pernyataan yang tepat adalah ... (Pilih semua yang benar)', 'text' => 'Rantai makanan kebun: Rumput -> Belalang -> Katak -> Ular -> Elang. Pernyataan yang tepat adalah ... (Pilih semua yang benar)'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => 'Belalang menempati tingkat trofik kedua sebagai konsumen primer', 'content' => 'Belalang menempati tingkat trofik kedua sebagai konsumen primer'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => 'Jika katak dibasmi, populasi belalang cenderung melonjak tajam', 'content' => 'Jika katak dibasmi, populasi belalang cenderung melonjak tajam'], 'is_correct' => true],
                                ['label' => 'C', 'body' => ['text' => 'Energi terbesar piramida berada pada puncak elang', 'content' => 'Energi terbesar piramida berada pada puncak elang'], 'is_correct' => false],
                                ['label' => 'D', 'body' => ['text' => 'Bakteri berperan menguraikan sisa zat organik menjadi hara', 'content' => 'Bakteri berperan menguraikan sisa zat organik menjadi hara'], 'is_correct' => true],
                            ],
                        ],
                        [
                            'cp_code' => 'IPA7-CP03-BS',
                            'type' => 'bs',
                            'cognitive_level' => 'MOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Pernyataan Fisika: Selama proses peleburan es pada suhu 0 derajat Celsius, kalor yang diserap digunakan untuk merubah wujud zat, bukan untuk menaikkan suhu.', 'text' => 'Pernyataan Fisika: Selama proses peleburan es pada suhu 0 derajat Celsius, kalor yang diserap digunakan untuk merubah wujud zat, bukan untuk menaikkan suhu.'],
                            'options' => [
                                ['label' => 'BENAR', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                                ['label' => 'SALAH', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'IPA7-CP04-MATCH',
                            'type' => 'menjodohkan',
                            'cognitive_level' => 'MOTS',
                            'weight' => 20.0,
                            'body' => ['content' => 'Pasangkan organel sel di Kolom Kiri dengan fungsi fisiologisnya di Kolom Kanan!', 'text' => 'Pasangkan organel sel di Kolom Kiri dengan fungsi fisiologisnya di Kolom Kanan!'],
                            'options' => [
                                ['label' => 'P1', 'body' => ['text' => 'Penghasil energi seluler ATP', 'content' => 'Penghasil energi seluler ATP'], 'is_correct' => false, 'match_key' => 'Mitokondria'],
                                ['label' => 'P2', 'body' => ['text' => 'Tempat sintesis protein', 'content' => 'Tempat sintesis protein'], 'is_correct' => false, 'match_key' => 'Ribosom'],
                                ['label' => 'P3', 'body' => ['text' => 'Pusat kendali aktivitas dan materi genetik DNA', 'content' => 'Pusat kendali aktivitas dan materi genetik DNA'], 'is_correct' => false, 'match_key' => 'Nukleus'],
                            ],
                        ],
                        [
                            'cp_code' => 'IPA7-CP05-ISIAN',
                            'type' => 'isian',
                            'cognitive_level' => 'LOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Organel plastida pada sel tumbuhan yang mengandung pigmen klorofil disebut ...', 'text' => 'Organel plastida pada sel tumbuhan yang mengandung pigmen klorofil disebut ...', 'correct_answer' => 'Kloroplas'],
                            'options' => [
                                ['label' => 'JAWABAN', 'body' => ['text' => 'Kloroplas', 'content' => 'Kloroplas'], 'is_correct' => true],
                            ],
                        ],
                        [
                            'cp_code' => 'IPA7-CP06-URAIAN',
                            'type' => 'uraian',
                            'cognitive_level' => 'HOTS',
                            'weight' => 20.0,
                            'body' => ['content' => 'Jelaskan mekanisme terjadinya peristiwa eutrofikasi di danau akibat pupuk anorganik dan dampaknya terhadap kematian organisme air!', 'text' => 'Jelaskan mekanisme terjadinya peristiwa eutrofikasi di danau akibat pupuk anorganik dan dampaknya terhadap kematian organisme air!'],
                            'explanation' => 'Limpasan pupuk fosfat/nitrat memicu ledakan gulma eceng gondok. Cahaya terhalang, tanaman air mati. Bakteri aerob menguraikan sisa zat dan menghabiskan oksigen terlarut (anoksia), membunuh ikan.',
                            'options' => [],
                        ],
                        [
                            'cp_code' => 'IPA7-CP07-PG',
                            'type' => 'pg',
                            'cognitive_level' => 'LOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Peristiwa perubahan wujud es batu menjadi air cair disebut ...', 'text' => 'Peristiwa perubahan wujud es batu menjadi air cair disebut ...'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => 'Mencair (Melebur)', 'content' => 'Mencair (Melebur)'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => 'Membeku', 'content' => 'Membeku'], 'is_correct' => false],
                                ['label' => 'C', 'body' => ['text' => 'Menguap', 'content' => 'Menguap'], 'is_correct' => false],
                                ['label' => 'D', 'body' => ['text' => 'Menyublim', 'content' => 'Menyublim'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'IPA7-CP08-PGK',
                            'type' => 'pg_kompleks',
                            'cognitive_level' => 'MOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Manakah yang tergolong sebagai hewan berdarah panas (Homoioterm)? (Pilih semua yang benar)', 'text' => 'Manakah yang tergolong sebagai hewan berdarah panas (Homoioterm)? (Pilih semua yang benar)'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => 'Burung Elang (Aves)', 'content' => 'Burung Elang (Aves)'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => 'Kucing Domestik (Mamalia)', 'content' => 'Kucing Domestik (Mamalia)'], 'is_correct' => true],
                                ['label' => 'C', 'body' => ['text' => 'Kadal Kebun (Reptil)', 'content' => 'Kadal Kebun (Reptil)'], 'is_correct' => false],
                                ['label' => 'D', 'body' => ['text' => 'Ikan Mas (Pisces)', 'content' => 'Ikan Mas (Pisces)'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'IPA7-CP09-BS',
                            'type' => 'bs',
                            'cognitive_level' => 'LOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Pernyataan: Larutan dengan nilai pH = 3 tergolong sebagai larutan asam kuat.', 'text' => 'Pernyataan: Larutan dengan nilai pH = 3 tergolong sebagai larutan asam kuat.'],
                            'options' => [
                                ['label' => 'BENAR', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                                ['label' => 'SALAH', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'IPA7-CP10-ISIAN',
                            'type' => 'isian',
                            'cognitive_level' => 'LOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Satuan SI untuk besaran turunan gaya adalah ...', 'text' => 'Satuan SI untuk besaran turunan gaya adalah ...', 'correct_answer' => 'Newton'],
                            'options' => [
                                ['label' => 'JAWABAN', 'body' => ['text' => 'Newton', 'content' => 'Newton'], 'is_correct' => true],
                            ],
                        ],
                    ],
                ],

                // ─── TINGKAT 2 (KELAS 8) ───
                'bank_8_mtk' => [
                    'name' => 'Bank Soal Matematika Kelas 8 - SPLDV & Teorema Pythagoras',
                    'subject' => $subjects['MTK-8'],
                    'grade' => $grade8,
                    'questions' => [
                        [
                            'cp_code' => 'MTK8-CP01-PG',
                            'type' => 'pg',
                            'cognitive_level' => 'MOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Sebuah segitiga siku-siku memiliki panjang sisi tegak 9 cm dan 12 cm. Panjang sisi miring (hipotenusa) segitiga tersebut adalah ...', 'text' => 'Sebuah segitiga siku-siku memiliki panjang sisi tegak 9 cm dan 12 cm. Panjang sisi miring (hipotenusa) segitiga tersebut adalah ...'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => '13 cm', 'content' => '13 cm'], 'is_correct' => false],
                                ['label' => 'B', 'body' => ['text' => '15 cm', 'content' => '15 cm'], 'is_correct' => true],
                                ['label' => 'C', 'body' => ['text' => '17 cm', 'content' => '17 cm'], 'is_correct' => false],
                                ['label' => 'D', 'body' => ['text' => '20 cm', 'content' => '20 cm'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'MTK8-CP02-PGK',
                            'type' => 'pg_kompleks',
                            'cognitive_level' => 'MOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Manakah pasangan ukuran sisi berikut yang membentuk segitiga siku-siku (Tripel Pythagoras)? (Pilih semua yang benar)', 'text' => 'Manakah pasangan ukuran sisi berikut yang membentuk segitiga siku-siku (Tripel Pythagoras)? (Pilih semua yang benar)'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => '3 cm, 4 cm, 5 cm', 'content' => '3 cm, 4 cm, 5 cm'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => '5 cm, 12 cm, 13 cm', 'content' => '5 cm, 12 cm, 13 cm'], 'is_correct' => true],
                                ['label' => 'C', 'body' => ['text' => '6 cm, 8 cm, 11 cm', 'content' => '6 cm, 8 cm, 11 cm'], 'is_correct' => false],
                                ['label' => 'D', 'body' => ['text' => '7 cm, 24 cm, 25 cm', 'content' => '7 cm, 24 cm, 25 cm'], 'is_correct' => true],
                            ],
                        ],
                        [
                            'cp_code' => 'MTK8-CP03-BS',
                            'type' => 'bs',
                            'cognitive_level' => 'MOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Pernyataan SPLDV: Jika 2x + y = 11 dan x - y = 1, maka nilai x = 4 dan y = 3.', 'text' => 'Pernyataan SPLDV: Jika 2x + y = 11 dan x - y = 1, maka nilai x = 4 dan y = 3.'],
                            'options' => [
                                ['label' => 'BENAR', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                                ['label' => 'SALAH', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'MTK8-CP04-MATCH',
                            'type' => 'menjodohkan',
                            'cognitive_level' => 'MOTS',
                            'weight' => 20.0,
                            'body' => ['content' => 'Pasangkan persamaan garis di Kolom Kiri dengan nilai gradien (m) garisnya di Kolom Kanan!', 'text' => 'Pasangkan persamaan garis di Kolom Kiri dengan nilai gradien (m) garisnya di Kolom Kanan!'],
                            'options' => [
                                ['label' => 'P1', 'body' => ['text' => 'm = 3', 'content' => 'm = 3'], 'is_correct' => false, 'match_key' => 'y = 3x - 5'],
                                ['label' => 'P2', 'body' => ['text' => 'm = -2', 'content' => 'm = -2'], 'is_correct' => false, 'match_key' => '2x + y = 7'],
                                ['label' => 'P3', 'body' => ['text' => 'm = 1/2', 'content' => 'm = 1/2'], 'is_correct' => false, 'match_key' => 'x - 2y = 4'],
                            ],
                        ],
                        [
                            'cp_code' => 'MTK8-CP05-ISIAN',
                            'type' => 'isian',
                            'cognitive_level' => 'LOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Jika f(x) = 3x - 7, berapakah nilai dari f(6)?', 'text' => 'Jika f(x) = 3x - 7, berapakah nilai dari f(6)?', 'correct_answer' => '11'],
                            'options' => [
                                ['label' => 'JAWABAN', 'body' => ['text' => '11', 'content' => '11'], 'is_correct' => true],
                            ],
                        ],
                        [
                            'cp_code' => 'MTK8-CP06-URAIAN',
                            'type' => 'uraian',
                            'cognitive_level' => 'HOTS',
                            'weight' => 20.0,
                            'body' => ['content' => 'Harga 3 tiket dewasa dan 2 tiket anak adalah Rp180.000. Harga 1 tiket dewasa dan 4 tiket anak adalah Rp160.000. Tentukan harga 1 tiket dewasa dan 1 tiket anak dengan metode eliminasi-substitusi!', 'text' => 'Harga 3 tiket dewasa dan 2 tiket anak adalah Rp180.000. Harga 1 tiket dewasa dan 4 tiket anak adalah Rp160.000. Tentukan harga 1 tiket dewasa dan 1 tiket anak dengan metode eliminasi-substitusi!'],
                            'explanation' => 'Misal d = dewasa, a = anak. 3d + 2a = 180.000; d + 4a = 160.000. Kalikan pers 2 dengan 3: 3d + 12a = 480.000. Kurangkan: 10a = 300.000 => a = 30.000. Substitusi a: d + 120.000 = 160.000 => d = 40.000. Tiket dewasa = Rp40.000, Tiket anak = Rp30.000.',
                            'options' => [],
                        ],
                        [
                            'cp_code' => 'MTK8-CP07-PG',
                            'type' => 'pg',
                            'cognitive_level' => 'LOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Gradien dari garis dengan persamaan 2y = 4x - 6 adalah ...', 'text' => 'Gradien dari garis dengan persamaan 2y = 4x - 6 adalah ...'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => '2', 'content' => '2'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => '4', 'content' => '4'], 'is_correct' => false],
                                ['label' => 'C', 'body' => ['text' => '-3', 'content' => '-3'], 'is_correct' => false],
                                ['label' => 'D', 'body' => ['text' => '1/2', 'content' => '1/2'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'MTK8-CP08-PGK',
                            'type' => 'pg_kompleks',
                            'cognitive_level' => 'MOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Manakah titik-titik koordinat berikut yang terletak di Kuadran II (x negatif, y positif)? (Pilih semua yang benar)', 'text' => 'Manakah titik-titik koordinat berikut yang terletak di Kuadran II (x negatif, y positif)? (Pilih semua yang benar)'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => '(-3, 5)', 'content' => '(-3, 5)'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => '(-7, 2)', 'content' => '(-7, 2)'], 'is_correct' => true],
                                ['label' => 'C', 'body' => ['text' => '(4, -2)', 'content' => '(4, -2)'], 'is_correct' => false],
                                ['label' => 'D', 'body' => ['text' => '(-1, -4)', 'content' => '(-1, -4)'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'MTK8-CP09-BS',
                            'type' => 'bs',
                            'cognitive_level' => 'MOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Pernyataan: Dua garis sejajar memiliki nilai gradien kemiringan (m) yang sama besar.', 'text' => 'Pernyataan: Dua garis sejajar memiliki nilai gradien kemiringan (m) yang sama besar.'],
                            'options' => [
                                ['label' => 'BENAR', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                                ['label' => 'SALAH', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'MTK8-CP10-ISIAN',
                            'type' => 'isian',
                            'cognitive_level' => 'LOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Banyaknya rusuk pada sebuah kubus atau balok adalah ... buah', 'text' => 'Banyaknya rusuk pada sebuah kubus atau balok adalah ... buah', 'correct_answer' => '12'],
                            'options' => [
                                ['label' => 'JAWABAN', 'body' => ['text' => '12', 'content' => '12'], 'is_correct' => true],
                            ],
                        ],
                    ],
                ],
                'bank_8_inf' => [
                    'name' => 'Bank Soal Informatika Kelas 8 - Jaringan Komputer & Algoritma Percabangan',
                    'subject' => $subjects['INF-8'],
                    'grade' => $grade8,
                    'questions' => [
                        [
                            'cp_code' => 'INF8-CP01-PG',
                            'type' => 'pg',
                            'cognitive_level' => 'LOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Topologi jaringan komputer yang menghubungkan setiap simpul komputer ke switch/konsentrator pusat secara mandiri adalah topologi ...', 'text' => 'Topologi jaringan komputer yang menghubungkan setiap simpul komputer ke switch/konsentrator pusat secara mandiri adalah topologi ...'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => 'Bintang (Star)', 'content' => 'Bintang (Star)'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => 'Bus', 'content' => 'Bus'], 'is_correct' => false],
                                ['label' => 'C', 'body' => ['text' => 'Cincin (Ring)', 'content' => 'Cincin (Ring)'], 'is_correct' => false],
                                ['label' => 'D', 'body' => ['text' => 'Mesh', 'content' => 'Mesh'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'INF8-CP02-PGK',
                            'type' => 'pg_kompleks',
                            'cognitive_level' => 'MOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Manakah di antara protokol berikut yang beroperasi pada lapisan aplikasi (Application Layer) jaringan internet? (Pilih semua yang benar)', 'text' => 'Manakah di antara protokol berikut yang beroperasi pada lapisan aplikasi (Application Layer) jaringan internet? (Pilih semua yang benar)'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => 'HTTP / HTTPS (Web browsing aman)', 'content' => 'HTTP / HTTPS (Web browsing aman)'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => 'DNS (Domain Name System)', 'content' => 'DNS (Domain Name System)'], 'is_correct' => true],
                                ['label' => 'C', 'body' => ['text' => 'FTP (File Transfer Protocol)', 'content' => 'FTP (File Transfer Protocol)'], 'is_correct' => true],
                                ['label' => 'D', 'body' => ['text' => 'Kabel Fiber Optik', 'content' => 'Kabel Fiber Optik'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'INF8-CP03-BS',
                            'type' => 'bs',
                            'cognitive_level' => 'LOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Pernyataan Jaringan: Alamat IPv4 terdiri dari 32 bit biner yang umumnya ditulis dalam empat oktet desimal (contoh: 192.168.1.1).', 'text' => 'Pernyataan Jaringan: Alamat IPv4 terdiri dari 32 bit biner yang umumnya ditulis dalam empat oktet desimal (contoh: 192.168.1.1).'],
                            'options' => [
                                ['label' => 'BENAR', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                                ['label' => 'SALAH', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'INF8-CP04-MATCH',
                            'type' => 'menjodohkan',
                            'cognitive_level' => 'MOTS',
                            'weight' => 20.0,
                            'body' => ['content' => 'Pasangkan simbol flowchart di Kolom Kiri dengan fungsinya di Kolom Kanan!', 'text' => 'Pasangkan simbol flowchart di Kolom Kiri dengan fungsinya di Kolom Kanan!'],
                            'options' => [
                                ['label' => 'P1', 'body' => ['text' => 'Keputusan kondisi percabangan (Decision)', 'content' => 'Keputusan kondisi percabangan (Decision)'], 'is_correct' => false, 'match_key' => 'Belah Ketupat'],
                                ['label' => 'P2', 'body' => ['text' => 'Operasi pemrosesan atau perhitungan', 'content' => 'Operasi pemrosesan atau perhitungan'], 'is_correct' => false, 'match_key' => 'Persegi Panjang'],
                                ['label' => 'P3', 'body' => ['text' => 'Awal atau akhir program (Terminator)', 'content' => 'Awal atau akhir program (Terminator)'], 'is_correct' => false, 'match_key' => 'Oval / Kapsul'],
                            ],
                        ],
                        [
                            'cp_code' => 'INF8-CP05-ISIAN',
                            'type' => 'isian',
                            'cognitive_level' => 'LOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Server yang berfungsi menerjemahkan nama domain (seperti smp-labum.sch.id) menjadi alamat IP numerik disebut server ...', 'text' => 'Server yang berfungsi menerjemahkan nama domain (seperti smp-labum.sch.id) menjadi alamat IP numerik disebut server ...', 'correct_answer' => 'DNS'],
                            'options' => [
                                ['label' => 'JAWABAN', 'body' => ['text' => 'DNS', 'content' => 'DNS'], 'is_correct' => true],
                            ],
                        ],
                        [
                            'cp_code' => 'INF8-CP06-URAIAN',
                            'type' => 'uraian',
                            'cognitive_level' => 'HOTS',
                            'weight' => 20.0,
                            'body' => ['content' => 'Jelaskan perbedaan utama antara kabel Twisted Pair (UTP/STP) dan kabel Fiber Optic dalam hal kecepatan transmisi, jangkauan jarak, dan ketahanan terhadap interferensi elektromagnetik!', 'text' => 'Jelaskan perbedaan utama antara kabel Twisted Pair (UTP/STP) dan kabel Fiber Optic dalam hal kecepatan transmisi, jangkauan jarak, dan ketahanan terhadap interferensi elektromagnetik!'],
                            'explanation' => 'Kabel UTP mentransmisikan sinyal listrik melalui tembaga, kecepatan hingga 1-10 Gbps, jarak maks 100m, rentan interferensi elektromagnetik (EMI). Fiber optik mentransmisikan pulsa cahaya lewat serat kaca, kecepatan sangat tinggi (>100 Gbps), jarak berkilo-kilometer, kebal total terhadap interferensi gelombang elektromagnetik.',
                            'options' => [],
                        ],
                        [
                            'cp_code' => 'INF8-CP07-PG',
                            'type' => 'pg',
                            'cognitive_level' => 'LOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Fungsi agregasi dalam spreadsheet (Microsoft Excel / Google Sheets) untuk mencari rata-rata nilai angka adalah ...', 'text' => 'Fungsi agregasi dalam spreadsheet (Microsoft Excel / Google Sheets) untuk mencari rata-rata nilai angka adalah ...'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => 'AVERAGE', 'content' => 'AVERAGE'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => 'SUM', 'content' => 'SUM'], 'is_correct' => false],
                                ['label' => 'C', 'body' => ['text' => 'COUNT', 'content' => 'COUNT'], 'is_correct' => false],
                                ['label' => 'D', 'body' => ['text' => 'MEDIAN', 'content' => 'MEDIAN'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'INF8-CP08-PGK',
                            'type' => 'pg_kompleks',
                            'cognitive_level' => 'MOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Manakah tipe data dasar dalam bahasa pemrograman? (Pilih semua yang benar)', 'text' => 'Manakah tipe data dasar dalam bahasa pemrograman? (Pilih semua yang benar)'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => 'Integer (bilangan bulat)', 'content' => 'Integer (bilangan bulat)'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => 'String (teks / karakter)', 'content' => 'String (teks / karakter)'], 'is_correct' => true],
                                ['label' => 'C', 'body' => ['text' => 'Boolean (true / false)', 'content' => 'Boolean (true / false)'], 'is_correct' => true],
                                ['label' => 'D', 'body' => ['text' => 'Processor Core', 'content' => 'Processor Core'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'INF8-CP09-BS',
                            'type' => 'bs',
                            'cognitive_level' => 'LOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Pernyataan: Struktur data Antrean (Queue) beroperasi dengan prinsip FIFO (First In First Out).', 'text' => 'Pernyataan: Struktur data Antrean (Queue) beroperasi dengan prinsip FIFO (First In First Out).'],
                            'options' => [
                                ['label' => 'BENAR', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                                ['label' => 'SALAH', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'INF8-CP10-ISIAN',
                            'type' => 'isian',
                            'cognitive_level' => 'LOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Operator logika yang menghasilkan nilai TRUE hanya jika kedua kondisi sama-sama bernilai TRUE adalah operator ...', 'text' => 'Operator logika yang menghasilkan nilai TRUE hanya jika kedua kondisi sama-sama bernilai TRUE adalah operator ...', 'correct_answer' => 'AND'],
                            'options' => [
                                ['label' => 'JAWABAN', 'body' => ['text' => 'AND', 'content' => 'AND'], 'is_correct' => true],
                            ],
                        ],
                    ],
                ],
                'bank_8_ipa' => [
                    'name' => 'Bank Soal IPA Terpadu Kelas 8 - Sistem Gerak, Usaha & Pesawat Sederhana',
                    'subject' => $subjects['IPA-8'],
                    'grade' => $grade8,
                    'questions' => [
                        [
                            'cp_code' => 'IPA8-CP01-PG',
                            'type' => 'pg',
                            'cognitive_level' => 'LOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Sendi yang memungkinkan terjadinya gerakan ke segala arah, seperti hubungan antara tulang lengan atas dan gelang bahu, adalah sendi ...', 'text' => 'Sendi yang memungkinkan terjadinya gerakan ke segala arah, seperti hubungan antara tulang lengan atas dan gelang bahu, adalah sendi ...'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => 'Peluru', 'content' => 'Peluru'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => 'Engsel', 'content' => 'Engsel'], 'is_correct' => false],
                                ['label' => 'C', 'body' => ['text' => 'Putar', 'content' => 'Putar'], 'is_correct' => false],
                                ['label' => 'D', 'body' => ['text' => 'Pelana', 'content' => 'Pelana'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'IPA8-CP02-PGK',
                            'type' => 'pg_kompleks',
                            'cognitive_level' => 'MOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Manakah di antara peristiwa berikut yang menyatakan terjadinya Usaha (W) dalam konsep fisika? (Pilih semua yang benar)', 'text' => 'Manakah di antara peristiwa berikut yang menyatakan terjadinya Usaha (W) dalam konsep fisika? (Pilih semua yang benar)'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => 'Kuda menarik gerobak hingga berpindah sejauh 20 meter', 'content' => 'Kuda menarik gerobak hingga berpindah sejauh 20 meter'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => 'Siswa mendorong dinding tembok kelas sekuat tenaga namun tembok tidak bergeser', 'content' => 'Siswa mendorong dinding tembok kelas sekuat tenaga namun tembok tidak bergeser'], 'is_correct' => false],
                                ['label' => 'C', 'body' => ['text' => 'Mobil derek mengangkat mobil mogok ke atas bak truk', 'content' => 'Mobil derek mengangkat mobil mogok ke atas bak truk'], 'is_correct' => true],
                                ['label' => 'D', 'body' => ['text' => 'Buah kelapa jatuh bebas dari tangkainya menuju tanah', 'content' => 'Buah kelapa jatuh bebas dari tangkainya menuju tanah'], 'is_correct' => true],
                            ],
                        ],
                        [
                            'cp_code' => 'IPA8-CP03-BS',
                            'type' => 'bs',
                            'cognitive_level' => 'LOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Pernyataan Mekanika: Keuntungan mekanis pada tuas jenis pertama adalah perbandingan antara panjang lengan kuasa terhadap panjang lengan beban.', 'text' => 'Pernyataan Mekanika: Keuntungan mekanis pada tuas jenis pertama adalah perbandingan antara panjang lengan kuasa terhadap panjang lengan beban.'],
                            'options' => [
                                ['label' => 'BENAR', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                                ['label' => 'SALAH', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'IPA8-CP04-MATCH',
                            'type' => 'menjodohkan',
                            'cognitive_level' => 'MOTS',
                            'weight' => 20.0,
                            'body' => ['content' => 'Pasangkan alat sehari-hari di Kolom Kiri dengan jenis pesawat sederhananya di Kolom Kanan!', 'text' => 'Pasangkan alat sehari-hari di Kolom Kiri dengan jenis pesawat sederhananya di Kolom Kanan!'],
                            'options' => [
                                ['label' => 'P1', 'body' => ['text' => 'Bidang Miring', 'content' => 'Bidang Miring'], 'is_correct' => false, 'match_key' => 'Tangga jalan berliku'],
                                ['label' => 'P2', 'body' => ['text' => 'Tuas Golongan I', 'content' => 'Tuas Golongan I'], 'is_correct' => false, 'match_key' => 'Gunting kertas'],
                                ['label' => 'P3', 'body' => ['text' => 'Katrol Tetap', 'content' => 'Katrol Tetap'], 'is_correct' => false, 'match_key' => 'Roda penarik timba sumur'],
                            ],
                        ],
                        [
                            'cp_code' => 'IPA8-CP05-ISIAN',
                            'type' => 'isian',
                            'cognitive_level' => 'LOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Gaya sebesar 40 N bekerja pada sebuah benda sehingga benda berpindah sejauh 5 meter. Berapakah usaha yang dilakukan (dalam satuan Joule)?', 'text' => 'Gaya sebesar 40 N bekerja pada sebuah benda sehingga benda berpindah sejauh 5 meter. Berapakah usaha yang dilakukan (dalam satuan Joule)?', 'correct_answer' => '200'],
                            'options' => [
                                ['label' => 'JAWABAN', 'body' => ['text' => '200', 'content' => '200'], 'is_correct' => true],
                            ],
                        ],
                        [
                            'cp_code' => 'IPA8-CP06-URAIAN',
                            'type' => 'uraian',
                            'cognitive_level' => 'HOTS',
                            'weight' => 20.0,
                            'body' => ['content' => 'Sebuah balok bermassa 60 kg dinaikkan ke bak truk setinggi 1,5 meter menggunakan papan bidang miring sepanjang 3 meter (percepatan gravitasi g = 10 m/s2). Hitunglah gaya dorong minimal yang diperlukan dan keuntungan mekanis bidang miring tersebut!', 'text' => 'Sebuah balok bermassa 60 kg dinaikkan ke bak truk setinggi 1,5 meter menggunakan papan bidang miring sepanjang 3 meter (percepatan gravitasi g = 10 m/s2). Hitunglah gaya dorong minimal yang diperlukan dan keuntungan mekanis bidang miring tersebut!'],
                            'explanation' => 'W = m * g = 60 * 10 = 600 N. Keuntungan mekanis KM = s / h = 3 / 1.5 = 2. Gaya dorong F = W / KM = 600 / 2 = 300 Newton.',
                            'options' => [],
                        ],
                        [
                            'cp_code' => 'IPA8-CP07-PG',
                            'type' => 'pg',
                            'cognitive_level' => 'LOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Hukum I Newton sering disebut juga sebagai hukum ...', 'text' => 'Hukum I Newton sering disebut juga sebagai hukum ...'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => 'Kelembaman (Inersia)', 'content' => 'Kelembaman (Inersia)'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => 'Aksi Reaksi', 'content' => 'Aksi Reaksi'], 'is_correct' => false],
                                ['label' => 'C', 'body' => ['text' => 'Percepatan Gaya', 'content' => 'Percepatan Gaya'], 'is_correct' => false],
                                ['label' => 'D', 'body' => ['text' => 'Kekekalan Energi', 'content' => 'Kekekalan Energi'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'IPA8-CP08-PGK',
                            'type' => 'pg_kompleks',
                            'cognitive_level' => 'MOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Manakah pesawat sederhana yang bekerja berdasarkan prinsip bidang miring? (Pilih semua yang benar)', 'text' => 'Manakah pesawat sederhana yang bekerja berdasarkan prinsip bidang miring? (Pilih semua yang benar)'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => 'Tangga rumah bertingkat', 'content' => 'Tangga rumah bertingkat'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => 'Sekrup ulir dan paku ulir', 'content' => 'Sekrup ulir dan paku ulir'], 'is_correct' => true],
                                ['label' => 'C', 'body' => ['text' => 'Jalan berkelok di pegunungan', 'content' => 'Jalan berkelok di pegunungan'], 'is_correct' => true],
                                ['label' => 'D', 'body' => ['text' => 'Jungkat-jungkit taman bermain', 'content' => 'Jungkat-jungkit taman bermain'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'IPA8-CP09-BS',
                            'type' => 'bs',
                            'cognitive_level' => 'LOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Pernyataan: Enzim ptialin (amilase) di dalam air liur bertugas memecah karbohidrat amilum menjadi maltosa.', 'text' => 'Pernyataan: Enzim ptialin (amilase) di dalam air liur bertugas memecah karbohidrat amilum menjadi maltosa.'],
                            'options' => [
                                ['label' => 'BENAR', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                                ['label' => 'SALAH', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'IPA8-CP10-ISIAN',
                            'type' => 'isian',
                            'cognitive_level' => 'LOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Satuan SI untuk mengukur besaran Tekanan (Pressure) adalah ...', 'text' => 'Satuan SI untuk mengukur besaran Tekanan (Pressure) adalah ...', 'correct_answer' => 'Pascal'],
                            'options' => [
                                ['label' => 'JAWABAN', 'body' => ['text' => 'Pascal', 'content' => 'Pascal'], 'is_correct' => true],
                            ],
                        ],
                    ],
                ],

                // ─── TINGKAT 3 (KELAS 9) ───
                'bank_9_mtk' => [
                    'name' => 'Bank Soal Matematika Kelas 9 - Persamaan Kuadrat & Transformasi Geometri',
                    'subject' => $subjects['MTK-9'],
                    'grade' => $grade9,
                    'questions' => [
                        [
                            'cp_code' => 'MTK9-CP01-PG',
                            'type' => 'pg',
                            'cognitive_level' => 'MOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Akar-akar penyelesaian dari persamaan kuadrat x^2 - 7x + 10 = 0 adalah ...', 'text' => 'Akar-akar penyelesaian dari persamaan kuadrat x^2 - 7x + 10 = 0 adalah ...'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => 'x = 2 atau x = 5', 'content' => 'x = 2 atau x = 5'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => 'x = -2 atau x = -5', 'content' => 'x = -2 atau x = -5'], 'is_correct' => false],
                                ['label' => 'C', 'body' => ['text' => 'x = -2 atau x = 5', 'content' => 'x = -2 atau x = 5'], 'is_correct' => false],
                                ['label' => 'D', 'body' => ['text' => 'x = 1 atau x = 10', 'content' => 'x = 1 atau x = 10'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'MTK9-CP02-PGK',
                            'type' => 'pg_kompleks',
                            'cognitive_level' => 'MOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Diberikan fungsi kuadrat f(x) = x^2 - 6x + 8. Manakah pernyataan yang benar? (Pilih semua yang benar)', 'text' => 'Diberikan fungsi kuadrat f(x) = x^2 - 6x + 8. Manakah pernyataan yang benar? (Pilih semua yang benar)'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => 'Grafik parabola terbuka ke atas karena koefisien a > 0', 'content' => 'Grafik parabola terbuka ke atas karena koefisien a > 0'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => 'Sumbu simetri kurva adalah x = 3', 'content' => 'Sumbu simetri kurva adalah x = 3'], 'is_correct' => true],
                                ['label' => 'C', 'body' => ['text' => 'Nilai optimum minimum fungsi adalah y = -1', 'content' => 'Nilai optimum minimum fungsi adalah y = -1'], 'is_correct' => true],
                                ['label' => 'D', 'body' => ['text' => 'Grafik memotong sumbu Y di titik (0, -8)', 'content' => 'Grafik memotong sumbu Y di titik (0, -8)'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'MTK9-CP03-BS',
                            'type' => 'bs',
                            'cognitive_level' => 'MOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Pernyataan Transformasi: Bayangan titik A(3, -5) jika dicerminkan (refleksi) terhadap sumbu X menghasilkan koordinat A\'(3, 5).', 'text' => 'Pernyataan Transformasi: Bayangan titik A(3, -5) jika dicerminkan (refleksi) terhadap sumbu X menghasilkan koordinat A\'(3, 5).'],
                            'options' => [
                                ['label' => 'BENAR', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                                ['label' => 'SALAH', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'MTK9-CP04-MATCH',
                            'type' => 'menjodohkan',
                            'cognitive_level' => 'MOTS',
                            'weight' => 20.0,
                            'body' => ['content' => 'Pasangkan jenis transformasi geometri di Kolom Kiri dengan karakteristik bayangannya di Kolom Kanan!', 'text' => 'Pasangkan jenis transformasi geometri di Kolom Kiri dengan karakteristik bayangannya di Kolom Kanan!'],
                            'options' => [
                                ['label' => 'P1', 'body' => ['text' => 'Pergeseran posisi tanpa merubah bentuk dan ukuran', 'content' => 'Pergeseran posisi tanpa merubah bentuk dan ukuran'], 'is_correct' => false, 'match_key' => 'Translasi'],
                                ['label' => 'P2', 'body' => ['text' => 'Pencerminan terhadap garis cermin', 'content' => 'Pencerminan terhadap garis cermin'], 'is_correct' => false, 'match_key' => 'Refleksi'],
                                ['label' => 'P3', 'body' => ['text' => 'Perubahan ukuran (memperbesar/memperkecil) skala', 'content' => 'Perubahan ukuran (memperbesar/memperkecil) skala'], 'is_correct' => false, 'match_key' => 'Dilatasi'],
                            ],
                        ],
                        [
                            'cp_code' => 'MTK9-CP05-ISIAN',
                            'type' => 'isian',
                            'cognitive_level' => 'LOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Nilai diskriminan (D = b^2 - 4ac) dari persamaan kuadrat 2x^2 + 5x - 3 = 0 adalah ...', 'text' => 'Nilai diskriminan (D = b^2 - 4ac) dari persamaan kuadrat 2x^2 + 5x - 3 = 0 adalah ...', 'correct_answer' => '49'],
                            'options' => [
                                ['label' => 'JAWABAN', 'body' => ['text' => '49', 'content' => '49'], 'is_correct' => true],
                            ],
                        ],
                        [
                            'cp_code' => 'MTK9-CP06-URAIAN',
                            'type' => 'uraian',
                            'cognitive_level' => 'HOTS',
                            'weight' => 20.0,
                            'body' => ['content' => 'Sebuah peluru ditembakkan vertikal ke atas dengan rumus tinggi h(t) = 40t - 5t^2 (h dalam meter, t dalam detik). Tentukan waktu yang dibutuhkan peluru untuk mencapai tinggi maksimum, serta hitung tinggi maksimum peluru tersebut!', 'text' => 'Sebuah peluru ditembakkan vertikal ke atas dengan rumus tinggi h(t) = 40t - 5t^2 (h dalam meter, t dalam detik). Tentukan waktu yang dibutuhkan peluru untuk mencapai tinggi maksimum, serta hitung tinggi maksimum peluru tersebut!'],
                            'explanation' => 'Fungsi kuadrat a = -5, b = 40. Waktu puncak t = -b / (2a) = -40 / (2 * -5) = 4 detik. Tinggi maks h(4) = 40(4) - 5(16) = 160 - 80 = 80 meter.',
                            'options' => [],
                        ],
                        [
                            'cp_code' => 'MTK9-CP07-PG',
                            'type' => 'pg',
                            'cognitive_level' => 'LOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Akar-akar persamaan kuadrat x^2 - 5x + 6 = 0 adalah ...', 'text' => 'Akar-akar persamaan kuadrat x^2 - 5x + 6 = 0 adalah ...'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => 'x = 2 dan x = 3', 'content' => 'x = 2 dan x = 3'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => 'x = -2 dan x = -3', 'content' => 'x = -2 dan x = -3'], 'is_correct' => false],
                                ['label' => 'C', 'body' => ['text' => 'x = 1 dan x = 6', 'content' => 'x = 1 dan x = 6'], 'is_correct' => false],
                                ['label' => 'D', 'body' => ['text' => 'x = -1 dan x = -6', 'content' => 'x = -1 dan x = -6'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'MTK9-CP08-PGK',
                            'type' => 'pg_kompleks',
                            'cognitive_level' => 'MOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Manakah sifat-sifat bangun tabung yang benar? (Pilih semua yang benar)', 'text' => 'Manakah sifat-sifat bangun tabung yang benar? (Pilih semua yang benar)'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => 'Memiliki alas dan tutup berupa lingkaran kongruen', 'content' => 'Memiliki alas dan tutup berupa lingkaran kongruen'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => 'Memiliki 3 sisi (alas, tutup, dan selimut)', 'content' => 'Memiliki 3 sisi (alas, tutup, dan selimut)'], 'is_correct' => true],
                                ['label' => 'C', 'body' => ['text' => 'Tidak memiliki titik sudut', 'content' => 'Tidak memiliki titik sudut'], 'is_correct' => true],
                                ['label' => 'D', 'body' => ['text' => 'Memiliki tepat 4 titik sudut', 'content' => 'Memiliki tepat 4 titik sudut'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'MTK9-CP09-BS',
                            'type' => 'bs',
                            'cognitive_level' => 'MOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Pernyataan: Refleksi titik (3, 4) terhadap sumbu X menghasilkan titik bayangan (3, -4).', 'text' => 'Pernyataan: Refleksi titik (3, 4) terhadap sumbu X menghasilkan titik bayangan (3, -4).'],
                            'options' => [
                                ['label' => 'BENAR', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                                ['label' => 'SALAH', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'MTK9-CP10-ISIAN',
                            'type' => 'isian',
                            'cognitive_level' => 'LOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Volume bola berjari-jari r dirumuskan dengan (4/3) x pi x r pangkat ...', 'text' => 'Volume bola berjari-jari r dirumuskan dengan (4/3) x pi x r pangkat ...', 'correct_answer' => '3'],
                            'options' => [
                                ['label' => 'JAWABAN', 'body' => ['text' => '3', 'content' => '3'], 'is_correct' => true],
                            ],
                        ],
                    ],
                ],
                'bank_9_inf' => [
                    'name' => 'Bank Soal Informatika Kelas 9 - Analisis Data & Cybersecurity',
                    'subject' => $subjects['INF-9'],
                    'grade' => $grade9,
                    'questions' => [
                        [
                            'cp_code' => 'INF9-CP01-PG',
                            'type' => 'pg',
                            'cognitive_level' => 'LOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Serangan rekayasa sosial yang berupaya memancing korban memasukkan kata sandi ke halaman web tiruan disebut ...', 'text' => 'Serangan rekayasa sosial yang berupaya memancing korban memasukkan kata sandi ke halaman web tiruan disebut ...'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => 'Phishing', 'content' => 'Phishing'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => 'DDoS', 'content' => 'DDoS'], 'is_correct' => false],
                                ['label' => 'C', 'body' => ['text' => 'Malware', 'content' => 'Malware'], 'is_correct' => false],
                                ['label' => 'D', 'body' => ['text' => 'Ransomware', 'content' => 'Ransomware'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'INF9-CP02-PGK',
                            'type' => 'pg_kompleks',
                            'cognitive_level' => 'MOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Manakah fungsi statistik dasar pada aplikasi lembar kerja (spreadsheet) yang benar? (Pilih semua yang benar)', 'text' => 'Manakah fungsi statistik dasar pada aplikasi lembar kerja (spreadsheet) yang benar? (Pilih semua yang benar)'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => '=SUM(range) untuk menjumlahkan seluruh nilai angka', 'content' => '=SUM(range) untuk menjumlahkan seluruh nilai angka'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => '=AVERAGE(range) untuk menghitung nilai rerata aritmatika', 'content' => '=AVERAGE(range) untuk menghitung nilai rerata aritmatika'], 'is_correct' => true],
                                ['label' => 'C', 'body' => ['text' => '=COUNTIF(range, kriteria) untuk menghitung sel berdasarkan syarat', 'content' => '=COUNTIF(range, kriteria) untuk menghitung sel berdasarkan syarat'], 'is_correct' => true],
                                ['label' => 'D', 'body' => ['text' => '=PRINT(range) untuk mengurutkan data dari A ke Z', 'content' => '=PRINT(range) untuk mengurutkan data dari A ke Z'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'INF9-CP03-BS',
                            'type' => 'bs',
                            'cognitive_level' => 'LOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Pernyataan Keamanan: Kriptografi kunci asimetris menggunakan dua pasang kunci yang berbeda, yaitu kunci publik (public key) dan kunci privat (private key).', 'text' => 'Pernyataan Keamanan: Kriptografi kunci asimetris menggunakan dua pasang kunci yang berbeda, yaitu kunci publik (public key) dan kunci privat (private key).'],
                            'options' => [
                                ['label' => 'BENAR', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                                ['label' => 'SALAH', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'INF9-CP04-MATCH',
                            'type' => 'menjodohkan',
                            'cognitive_level' => 'MOTS',
                            'weight' => 20.0,
                            'body' => ['content' => 'Pasangkan istilah keamanan informasi di Kolom Kiri dengan definisinya di Kolom Kanan!', 'text' => 'Pasangkan istilah keamanan informasi di Kolom Kiri dengan definisinya di Kolom Kanan!'],
                            'options' => [
                                ['label' => 'P1', 'body' => ['text' => 'Perangkat perantara penyaring lalu lintas data jaringan', 'content' => 'Perangkat perantara penyaring lalu lintas data jaringan'], 'is_correct' => false, 'match_key' => 'Firewall'],
                                ['label' => 'P2', 'body' => ['text' => 'Program berbahaya yang mengunci file dan meminta tebusan', 'content' => 'Program berbahaya yang mengunci file dan meminta tebusan'], 'is_correct' => false, 'match_key' => 'Ransomware'],
                                ['label' => 'P3', 'body' => ['text' => 'Jalur koneksi terowongan data terenkripsi pribadi', 'content' => 'Jalur koneksi terowongan data terenkripsi pribadi'], 'is_correct' => false, 'match_key' => 'VPN'],
                            ],
                        ],
                        [
                            'cp_code' => 'INF9-CP05-ISIAN',
                            'type' => 'isian',
                            'cognitive_level' => 'LOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Protokol keamanan web terenkripsi yang ditandai dengan ikon gembok hijau pada address bar browser adalah ...', 'text' => 'Protokol keamanan web terenkripsi yang ditandai dengan ikon gembok hijau pada address bar browser adalah ...', 'correct_answer' => 'HTTPS'],
                            'options' => [
                                ['label' => 'JAWABAN', 'body' => ['text' => 'HTTPS', 'content' => 'HTTPS'], 'is_correct' => true],
                            ],
                        ],
                        [
                            'cp_code' => 'INF9-CP06-URAIAN',
                            'type' => 'uraian',
                            'cognitive_level' => 'HOTS',
                            'weight' => 20.0,
                            'body' => ['content' => 'Jelaskan konsep prinsip keamanan informasi CIA Triad (Confidentiality, Integrity, Availability) dan berikan contoh nyata penerapannya pada portal penilaian nilai rapor siswa!', 'text' => 'Jelaskan konsep prinsip keamanan informasi CIA Triad (Confidentiality, Integrity, Availability) dan berikan contoh nyata penerapannya pada portal penilaian nilai rapor siswa!'],
                            'explanation' => 'Confidentiality (Kerahasiaan): Nilai rapor hanya bisa dilihat siswa/ortu bersangkutan via otentikasi login. Integrity (Keutuhan): Nilai rapor tidak bisa dimanipulasi pihak tidak berhak dan ada log audit pengubahan nilai. Availability (Ketersediaan): Server portal rapor tetap online dan dapat diakses saat hari pembagian rapor tanpa down.',
                            'options' => [],
                        ],
                        [
                            'cp_code' => 'INF9-CP07-PG',
                            'type' => 'pg',
                            'cognitive_level' => 'LOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Alamat identitas unik fisik kartu jaringan komputer (Network Interface Card) pada jaringan adalah ...', 'text' => 'Alamat identitas unik fisik kartu jaringan komputer (Network Interface Card) pada jaringan adalah ...'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => 'MAC Address', 'content' => 'MAC Address'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => 'IP Address', 'content' => 'IP Address'], 'is_correct' => false],
                                ['label' => 'C', 'body' => ['text' => 'DNS Server', 'content' => 'DNS Server'], 'is_correct' => false],
                                ['label' => 'D', 'body' => ['text' => 'Subnet Mask', 'content' => 'Subnet Mask'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'INF9-CP08-PGK',
                            'type' => 'pg_kompleks',
                            'cognitive_level' => 'MOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Manakah pilar keamanan informasi CIA Triad? (Pilih semua yang benar)', 'text' => 'Manakah pilar keamanan informasi CIA Triad? (Pilih semua yang benar)'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => 'Confidentiality (Kerahasiaan)', 'content' => 'Confidentiality (Kerahasiaan)'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => 'Integrity (Integritas)', 'content' => 'Integrity (Integritas)'], 'is_correct' => true],
                                ['label' => 'C', 'body' => ['text' => 'Availability (Ketersediaan)', 'content' => 'Availability (Ketersediaan)'], 'is_correct' => true],
                                ['label' => 'D', 'body' => ['text' => 'Accessibility (Kemudahan Akses)', 'content' => 'Accessibility (Kemudahan Akses)'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'INF9-CP09-BS',
                            'type' => 'bs',
                            'cognitive_level' => 'MOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Pernyataan: Enkripsi end-to-end memastikan pesan hanya dapat dibaca oleh pengirim dan penerima yang sah.', 'text' => 'Pernyataan: Enkripsi end-to-end memastikan pesan hanya dapat dibaca oleh pengirim dan penerima yang sah.'],
                            'options' => [
                                ['label' => 'BENAR', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                                ['label' => 'SALAH', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'INF9-CP10-ISIAN',
                            'type' => 'isian',
                            'cognitive_level' => 'LOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Protokol transfer file terenkripsi aman yang berjalan di atas Secure Shell (SSH) disingkat ...', 'text' => 'Protokol transfer file terenkripsi aman yang berjalan di atas Secure Shell (SSH) disingkat ...', 'correct_answer' => 'SFTP'],
                            'options' => [
                                ['label' => 'JAWABAN', 'body' => ['text' => 'SFTP', 'content' => 'SFTP'], 'is_correct' => true],
                            ],
                        ],
                    ],
                ],
                'bank_9_ipa' => [
                    'name' => 'Bank Soal IPA Terpadu Kelas 9 - Pewarisan Sifat, Listrik & Kemagnetan',
                    'subject' => $subjects['IPA-9'],
                    'grade' => $grade9,
                    'questions' => [
                        [
                            'cp_code' => 'IPA9-CP01-PG',
                            'type' => 'pg',
                            'cognitive_level' => 'LOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Muatan listrik Q = 60 Coulomb mengalir melalui seutas kawat penghantar selama 2 menit. Kuat arus listrik yang mengalir adalah ...', 'text' => 'Muatan listrik Q = 60 Coulomb mengalir melalui seutas kawat penghantar selama 2 menit. Kuat arus listrik yang mengalir adalah ...'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => '0,5 Ampere', 'content' => '0,5 Ampere'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => '2,0 Ampere', 'content' => '2,0 Ampere'], 'is_correct' => false],
                                ['label' => 'C', 'body' => ['text' => '30 Ampere', 'content' => '30 Ampere'], 'is_correct' => false],
                                ['label' => 'D', 'body' => ['text' => '120 Ampere', 'content' => '120 Ampere'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'IPA9-CP02-PGK',
                            'type' => 'pg_kompleks',
                            'cognitive_level' => 'MOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Persilangan monohibrid dominan penuh antara kacang ercis biji bulat (BB) dengan biji keriput (bb) menghasilkan F1 seluruhnya berbiji bulat (Bb). Jika F1 disilangkan sesamanya, manakah pernyataan yang benar mengenai keturunan F2? (Pilih semua yang benar)', 'text' => 'Persilangan monohibrid dominan penuh antara kacang ercis biji bulat (BB) dengan biji keriput (bb) menghasilkan F1 seluruhnya berbiji bulat (Bb). Jika F1 disilangkan sesamanya, manakah pernyataan yang benar mengenai keturunan F2? (Pilih semua yang benar)'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => 'Perbandingan genotipe F2 adalah BB : Bb : bb = 1 : 2 : 1', 'content' => 'Perbandingan genotipe F2 adalah BB : Bb : bb = 1 : 2 : 1'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => 'Perbandingan fenotipe F2 adalah Bulat : Keriput = 3 : 1', 'content' => 'Perbandingan fenotipe F2 adalah Bulat : Keriput = 3 : 1'], 'is_correct' => true],
                                ['label' => 'C', 'body' => ['text' => 'Peluang munculnya biji keriput pada F2 adalah 25%', 'content' => 'Peluang munculnya biji keriput pada F2 adalah 25%'], 'is_correct' => true],
                                ['label' => 'D', 'body' => ['text' => 'Seluruh keturunan F2 berfenotipe keriput', 'content' => 'Seluruh keturunan F2 berfenotipe keriput'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'IPA9-CP03-BS',
                            'type' => 'bs',
                            'cognitive_level' => 'LOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Pernyataan Kemagnetan: Dua kutub magnet yang senama (sejenis) akan saling tarik-menarik jika didekatkan.', 'text' => 'Pernyataan Kemagnetan: Dua kutub magnet yang senama (sejenis) akan saling tarik-menarik jika didekatkan.'],
                            'options' => [
                                ['label' => 'BENAR', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => false],
                                ['label' => 'SALAH', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => true],
                            ],
                        ],
                        [
                            'cp_code' => 'IPA9-CP04-MATCH',
                            'type' => 'menjodohkan',
                            'cognitive_level' => 'MOTS',
                            'weight' => 20.0,
                            'body' => ['content' => 'Pasangkan produk bioteknologi di Kolom Kiri dengan mikroorganisme agen pembuatnya di Kolom Kanan!', 'text' => 'Pasangkan produk bioteknologi di Kolom Kiri dengan mikroorganisme agen pembuatnya di Kolom Kanan!'],
                            'options' => [
                                ['label' => 'P1', 'body' => ['text' => 'Rhizopus oryzae', 'content' => 'Rhizopus oryzae'], 'is_correct' => false, 'match_key' => 'Tempe kedelai'],
                                ['label' => 'P2', 'body' => ['text' => 'Lactobacillus bulgaricus', 'content' => 'Lactobacillus bulgaricus'], 'is_correct' => false, 'match_key' => 'Yogurt'],
                                ['label' => 'P3', 'body' => ['text' => 'Saccharomyces cerevisiae', 'content' => 'Saccharomyces cerevisiae'], 'is_correct' => false, 'match_key' => 'Roti mengembang'],
                            ],
                        ],
                        [
                            'cp_code' => 'IPA9-CP05-ISIAN',
                            'type' => 'isian',
                            'cognitive_level' => 'LOTS',
                            'weight' => 15.0,
                            'body' => ['content' => 'Suatu rangkaian listrik memiliki hambatan R = 20 Ohm dan dipasang pada sumber tegangan V = 220 Volt. Kuat arus listrik yang mengalir (dalam Ampere) adalah ...', 'text' => 'Suatu rangkaian listrik memiliki hambatan R = 20 Ohm dan dipasang pada sumber tegangan V = 220 Volt. Kuat arus listrik yang mengalir (dalam Ampere) adalah ...', 'correct_answer' => '11'],
                            'options' => [
                                ['label' => 'JAWABAN', 'body' => ['text' => '11', 'content' => '11'], 'is_correct' => true],
                            ],
                        ],
                        [
                            'cp_code' => 'IPA9-CP06-URAIAN',
                            'type' => 'uraian',
                            'cognitive_level' => 'HOTS',
                            'weight' => 20.0,
                            'body' => ['content' => 'Sebuah transformator step-down memiliki 1.000 lilitan primer dan 200 lilitan sekunder. Jika kumparan primer dihubungkan ke sumber tegangan AC 220 Volt, hitunglah tegangan listrik yang keluar dari kumparan sekunder serta jelaskan prinsip kerja induksi elektromagnetik pada trafo tersebut!', 'text' => 'Sebuah transformator step-down memiliki 1.000 lilitan primer dan 200 lilitan sekunder. Jika kumparan primer dihubungkan ke sumber tegangan AC 220 Volt, hitunglah tegangan listrik yang keluar dari kumparan sekunder serta jelaskan prinsip kerja induksi elektromagnetik pada trafo tersebut!'],
                            'explanation' => 'Vs / Vp = Ns / Np => Vs = (Ns/Np) * Vp = (200 / 1000) * 220 = 44 Volt. Prinsip kerja trafo didasarkan pada Hukum Faraday tentang induksi elektromagnetik: arus bolak-balik (AC) pada kumparan primer menghasilkan fluks magnetik yang berubah-ubah pada inti besi, menginduksi gaya gerak listrik (GGL) pada kumparan sekunder.',
                            'options' => [],
                        ],
                        [
                            'cp_code' => 'IPA9-CP07-PG',
                            'type' => 'pg',
                            'cognitive_level' => 'LOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Muatan listrik atom yang memiliki jumlah elektron lebih banyak daripada proton adalah ...', 'text' => 'Muatan listrik atom yang memiliki jumlah elektron lebih banyak daripada proton adalah ...'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => 'Bermuatan Negatif (Anion)', 'content' => 'Bermuatan Negatif (Anion)'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => 'Bermuatan Positif (Kation)', 'content' => 'Bermuatan Positif (Kation)'], 'is_correct' => false],
                                ['label' => 'C', 'body' => ['text' => 'Netral', 'content' => 'Netral'], 'is_correct' => false],
                                ['label' => 'D', 'body' => ['text' => 'Isotop', 'content' => 'Isotop'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'IPA9-CP08-PGK',
                            'type' => 'pg_kompleks',
                            'cognitive_level' => 'MOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Manakah yang termasuk pemanfaatan bioteknologi konvensional pangan? (Pilih semua yang benar)', 'text' => 'Manakah yang termasuk pemanfaatan bioteknologi konvensional pangan? (Pilih semua yang benar)'],
                            'options' => [
                                ['label' => 'A', 'body' => ['text' => 'Pembuatan tempe dengan jamur Rhizopus oryzae', 'content' => 'Pembuatan tempe dengan jamur Rhizopus oryzae'], 'is_correct' => true],
                                ['label' => 'B', 'body' => ['text' => 'Pembuatan yogurt dengan bakteri Lactobacillus bulgaricus', 'content' => 'Pembuatan yogurt dengan bakteri Lactobacillus bulgaricus'], 'is_correct' => true],
                                ['label' => 'C', 'body' => ['text' => 'Pembuatan keju dengan fermentasi asam laktat', 'content' => 'Pembuatan keju dengan fermentasi asam laktat'], 'is_correct' => true],
                                ['label' => 'D', 'body' => ['text' => 'Kloning domba Dolly dengan transfer inti somatis', 'content' => 'Kloning domba Dolly dengan transfer inti somatis'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'IPA9-CP09-BS',
                            'type' => 'bs',
                            'cognitive_level' => 'MOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Pernyataan: Pada persilangan monohibrid dominan penuh, perbandingan genotipe F2 adalah 1 : 2 : 1.', 'text' => 'Pernyataan: Pada persilangan monohibrid dominan penuh, perbandingan genotipe F2 adalah 1 : 2 : 1.'],
                            'options' => [
                                ['label' => 'BENAR', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                                ['label' => 'SALAH', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                            ],
                        ],
                        [
                            'cp_code' => 'IPA9-CP10-ISIAN',
                            'type' => 'isian',
                            'cognitive_level' => 'LOTS',
                            'weight' => 10.0,
                            'body' => ['content' => 'Satuan SI untuk menyatakan besaran hambatan listrik adalah ...', 'text' => 'Satuan SI untuk menyatakan besaran hambatan listrik adalah ...', 'correct_answer' => 'Ohm'],
                            'options' => [
                                ['label' => 'JAWABAN', 'body' => ['text' => 'Ohm', 'content' => 'Ohm'], 'is_correct' => true],
                            ],
                        ],
                    ],
                ],
            ];

            // 10. Instantiate 9 Question Banks & Seed Questions
            $seededBanks = [];
            $allSavedQuestions = [];

            foreach ($banksData as $key => $bankConfig) {
                $bank = QuestionBank::updateOrCreate(
                    [
                        'name' => $bankConfig['name'],
                        'subject_id' => $bankConfig['subject']->id,
                    ],
                    [
                        'school_id' => $schoolId,
                        'grade_level_id' => $bankConfig['grade']->id,
                        'owner_id' => $adminUser->id,
                    ]
                );

                $savedQ = $seedQuestions($bank, $bankConfig['questions']);
                $seededBanks[$key] = $bank;
                $allSavedQuestions[$key] = $savedQ;
            }

            // =========================================================================
            // 11. CREATE 3 EXAMS (1 FOR EACH LEVEL: KELAS 7, KELAS 8, KELAS 9)
            // =========================================================================
            $examsConfig = [
                // 1. UJIAN TINGKAT 7
                [
                    'title' => 'Asesmen Sumatif Gasal Kelas 7: Matematika & Sains Terpadu',
                    'subject' => $subjects['MTK-7'],
                    'duration' => 60,
                    'token' => 'KLS7GASAL',
                    'room' => $room7A,
                    'students' => $students7,
                    'questions' => array_merge($allSavedQuestions['bank_7_mtk'], $allSavedQuestions['bank_7_ipa'], $allSavedQuestions['bank_7_inf']),
                ],
                // 2. UJIAN TINGKAT 8
                [
                    'title' => 'Asesmen Sumatif Gasal Kelas 8: IPA & Logika Komputasi',
                    'subject' => $subjects['IPA-8'],
                    'duration' => 60,
                    'token' => 'KLS8GASAL',
                    'room' => $room8A,
                    'students' => $students8,
                    'questions' => array_merge($allSavedQuestions['bank_8_mtk'], $allSavedQuestions['bank_8_ipa'], $allSavedQuestions['bank_8_inf']),
                ],
                // 3. UJIAN TINGKAT 9
                [
                    'title' => 'Asesmen Akhir Jenjang Kelas 9: STEM & Literasi Digital',
                    'subject' => $subjects['INF-9'],
                    'duration' => 90,
                    'token' => 'KLS9AKHIR',
                    'room' => $room9A,
                    'students' => $students9,
                    'questions' => array_merge($allSavedQuestions['bank_9_mtk'], $allSavedQuestions['bank_9_inf'], $allSavedQuestions['bank_9_ipa']),
                ],
            ];

            foreach ($examsConfig as $eCfg) {
                $exam = Exam::updateOrCreate(
                    [
                        'title' => $eCfg['title'],
                    ],
                    [
                        'school_id' => $schoolId,
                        'subject_id' => $eCfg['subject']->id,
                        'created_by' => $adminUser->id,
                        'exam_type' => 'STS',
                        'duration_min' => $eCfg['duration'],
                        'start_at' => Carbon::now()->subHours(2),
                        'end_at' => Carbon::now()->addDays(14),
                        'passing_grade' => 75.0,
                        'shuffle_questions' => true,
                        'shuffle_options' => true,
                        'package_mode' => 'tunggal',
                        'show_result_mode' => 'setelah_selesai',
                        'status' => Exam::STATUS_IN_PROGRESS, // 'berlangsung'
                    ]
                );

                // Assign Questions
                ExamQuestion::where('exam_id', $exam->id)
                    ->whereNotIn('question_id', collect($eCfg['questions'])->pluck('id'))
                    ->delete();

                $order = 1;
                foreach ($eCfg['questions'] as $q) {
                    ExamQuestion::updateOrCreate(
                        [
                            'exam_id' => $exam->id,
                            'question_id' => $q->id,
                        ],
                        [
                            'sort_order' => $order++,
                            'weight_override' => $q->weight,
                        ]
                    );
                }

                // Assign Exam Room with Token
                $examRoom = ExamRoom::updateOrCreate(
                    [
                        'exam_id' => $exam->id,
                        'session_no' => 1,
                    ],
                    [
                        'room_id' => $eCfg['room']->id,
                        'proctor_id' => $adminUser->id,
                        'token' => $eCfg['token'],
                        'token_expires_at' => Carbon::now()->addDays(14),
                    ]
                );

                // Enroll All Students of this Grade Level
                foreach ($eCfg['students'] as $std) {
                    ExamParticipant::updateOrCreate(
                        [
                            'exam_id' => $exam->id,
                            'student_id' => $std->id,
                        ],
                        [
                            'exam_room_id' => $examRoom->id,
                        ]
                    );
                }
            }

            DB::commit();

            $this->command?->info('CbtMultiLevelSeeder executed successfully: 3 Grade Levels (7, 8, 9), 9 Question Banks (3 per level), 54 Questions covering all types, 3 Multi-Level Exams, and Admin edit permissions verified.');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
