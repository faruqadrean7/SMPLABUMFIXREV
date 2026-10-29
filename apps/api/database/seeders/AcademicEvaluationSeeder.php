<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ClassStudent;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\ExamQuestion;
use App\Models\ExamRoom;
use App\Models\File;
use App\Models\GradeLevel;
use App\Models\LearningMaterial;
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
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\TeachingMaterial;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AcademicEvaluationSeeder extends Seeder
{
    public const DEFAULT_SCHOOL_ID = '00000000-0000-0000-0000-000000000001';

    public function run(): void
    {
        $schoolId = SiteSetting::first()?->school_id ?? self::DEFAULT_SCHOOL_ID;
        $defaultPassword = Hash::make('SistLabUM2026!UAT');

        DB::beginTransaction();

        try {
            // 1. Roles
            $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
            $roleHomeroom = Role::firstOrCreate(['code' => 'homeroom'], ['name' => 'Wali Kelas', 'is_system' => true]);
            $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

            // 2. Academic Year & Semester
            $academicYear = AcademicYear::firstOrCreate(
                ['school_id' => $schoolId, 'name' => '2026/2027'],
                [
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

            // 3. Grade Level & Classes
            $gradeLevel7 = GradeLevel::firstOrCreate(
                ['school_id' => $schoolId, 'name' => 'Kelas 7'],
                ['sort_order' => 7]
            );

            $room7A = Room::firstOrCreate(
                ['school_id' => $schoolId, 'code' => 'R-7A'],
                ['name' => 'Ruang Teori 7-A', 'room_type' => 'kelas', 'capacity' => 36]
            );

            $roomLabKom = Room::firstOrCreate(
                ['school_id' => $schoolId, 'code' => 'LAB-KOMP-01'],
                ['name' => 'Laboratorium Komputer 1', 'room_type' => 'laboratorium', 'capacity' => 40]
            );

            $roomLabIpa = Room::firstOrCreate(
                ['school_id' => $schoolId, 'code' => 'LAB-IPA-01'],
                ['name' => 'Laboratorium IPA Terpadu', 'room_type' => 'laboratorium', 'capacity' => 36]
            );

            // 4. Subjects
            $subjects = [
                'MTK-7' => ['name' => 'Matematika', 'group' => 'wajib'],
                'INF-7' => ['name' => 'Informatika', 'group' => 'wajib'],
                'IPA-7' => ['name' => 'Ilmu Pengetahuan Alam', 'group' => 'wajib'],
                'BIN-7' => ['name' => 'Bahasa Indonesia', 'group' => 'wajib'],
            ];

            $subjectModels = [];
            foreach ($subjects as $code => $info) {
                $subjectModels[$code] = Subject::firstOrCreate(
                    ['school_id' => $schoolId, 'code' => $code],
                    ['name' => $info['name'], 'subject_group' => $info['group']]
                );
            }

            // 5. Teachers Setup
            // Guru MTK
            $userGuruMtk = User::where('username', 'guru.mtk.staging')->first();
            if (! $userGuruMtk) {
                $userGuruMtk = User::create([
                    'school_id' => $schoolId,
                    'username' => 'guru.mtk.staging',
                    'email' => 'guru.mtk.staging@smp-labum.sch.id',
                    'password_hash' => $defaultPassword,
                    'status' => 'active',
                ]);
            }
            $userGuruMtk->roles()->syncWithoutDetaching([$roleTeacher->id]);
            $teacherMtk = Teacher::firstOrCreate(
                ['user_id' => $userGuruMtk->id],
                [
                    'school_id' => $schoolId,
                    'full_name' => 'Guru Matematika UAT, S.Pd.',
                    'nip' => '199004042015021004',
                    'status' => 'aktif',
                ]
            );

            // Wali Kelas 7-A (Pengampu Informatika)
            $userWalas7a = User::where('username', 'walas.7a.staging')->first();
            if (! $userWalas7a) {
                $userWalas7a = User::create([
                    'school_id' => $schoolId,
                    'username' => 'walas.7a.staging',
                    'email' => 'walas.7a.staging@smp-labum.sch.id',
                    'password_hash' => $defaultPassword,
                    'status' => 'active',
                ]);
            }
            $userWalas7a->roles()->syncWithoutDetaching([$roleTeacher->id, $roleHomeroom->id]);
            $teacherWalas7a = Teacher::firstOrCreate(
                ['user_id' => $userWalas7a->id],
                [
                    'school_id' => $schoolId,
                    'full_name' => 'Wali Kelas 7-A UAT, S.Pd.',
                    'nip' => '198503032010012003',
                    'status' => 'aktif',
                ]
            );

            // Guru IPA
            $userGuruIpa = User::firstOrCreate(
                ['username' => 'guru.ipa.staging'],
                [
                    'school_id' => $schoolId,
                    'email' => 'guru.ipa.staging@smp-labum.sch.id',
                    'password_hash' => $defaultPassword,
                    'status' => 'active',
                ]
            );
            $userGuruIpa->roles()->syncWithoutDetaching([$roleTeacher->id]);
            $teacherIpa = Teacher::firstOrCreate(
                ['user_id' => $userGuruIpa->id],
                [
                    'school_id' => $schoolId,
                    'full_name' => 'Bambang Sudarmanto, M.Pd.',
                    'nip' => '198705052012011005',
                    'status' => 'aktif',
                ]
            );

            // Guru Bahasa Indonesia
            $userGuruBin = User::firstOrCreate(
                ['username' => 'guru.bin.staging'],
                [
                    'school_id' => $schoolId,
                    'email' => 'guru.bin.staging@smp-labum.sch.id',
                    'password_hash' => $defaultPassword,
                    'status' => 'active',
                ]
            );
            $userGuruBin->roles()->syncWithoutDetaching([$roleTeacher->id]);
            $teacherBin = Teacher::firstOrCreate(
                ['user_id' => $userGuruBin->id],
                [
                    'school_id' => $schoolId,
                    'full_name' => 'Dewi Rahmawati, S.Pd.',
                    'nip' => '199201012016022006',
                    'status' => 'aktif',
                ]
            );

            // Class 7-A with homeroom
            $class7A = SchoolClass::firstOrCreate(
                [
                    'school_id' => $schoolId,
                    'academic_year_id' => $academicYear->id,
                    'name' => '7-A',
                ],
                [
                    'grade_level_id' => $gradeLevel7->id,
                    'homeroom_teacher_id' => $teacherWalas7a->id,
                    'room_id' => $room7A->id,
                    'order' => 1,
                    'capacity' => 36,
                ]
            );
            if ($class7A->homeroom_teacher_id !== $teacherWalas7a->id) {
                $class7A->update(['homeroom_teacher_id' => $teacherWalas7a->id]);
            }

            // 6. Teaching Assignments in Class 7-A
            TeachingAssignment::firstOrCreate(
                ['semester_id' => $semester->id, 'class_id' => $class7A->id, 'subject_id' => $subjectModels['MTK-7']->id],
                ['teacher_id' => $teacherMtk->id, 'hours_per_week' => 4]
            );
            TeachingAssignment::firstOrCreate(
                ['semester_id' => $semester->id, 'class_id' => $class7A->id, 'subject_id' => $subjectModels['INF-7']->id],
                ['teacher_id' => $teacherWalas7a->id, 'hours_per_week' => 3]
            );
            TeachingAssignment::firstOrCreate(
                ['semester_id' => $semester->id, 'class_id' => $class7A->id, 'subject_id' => $subjectModels['IPA-7']->id],
                ['teacher_id' => $teacherIpa->id, 'hours_per_week' => 4]
            );
            TeachingAssignment::firstOrCreate(
                ['semester_id' => $semester->id, 'class_id' => $class7A->id, 'subject_id' => $subjectModels['BIN-7']->id],
                ['teacher_id' => $teacherBin->id, 'hours_per_week' => 4]
            );

            // 7. Enrolled Students in Class 7-A (siswa.lab.001 - siswa.lab.010)
            $students = [];
            for ($i = 1; $i <= 10; $i++) {
                $numStr = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
                $uname = "siswa.lab.{$numStr}";
                $sUser = User::firstOrCreate(
                    ['username' => $uname],
                    [
                        'school_id' => $schoolId,
                        'email' => "{$uname}@smp-labum.sch.id",
                        'password_hash' => $defaultPassword,
                        'status' => 'active',
                    ]
                );
                $sUser->roles()->syncWithoutDetaching([$roleStudent->id]);

                $student = Student::firstOrCreate(
                    ['user_id' => $sUser->id],
                    [
                        'school_id' => $schoolId,
                        'nis' => (string) (26000 + $i),
                        'nisn' => '0099'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                        'nik_enc' => '357801'.str_pad((string) $i, 10, '0', STR_PAD_LEFT),
                        'full_name' => $i === 1 ? 'Ahmad Fauzi' : ($i === 2 ? 'Nabila Putri Andini' : "Siswa UAT Lab {$numStr}"),
                        'gender' => $i % 2 === 1 ? 'L' : 'P',
                        'birth_place' => 'Malang',
                        'birth_date' => '2012-05-15',
                        'religion' => 'Islam',
                        'status' => 'aktif',
                        'entry_date' => '2026-07-01',
                    ]
                );

                ClassStudent::firstOrCreate(
                    ['class_id' => $class7A->id, 'student_id' => $student->id],
                    ['joined_at' => '2026-07-01']
                );

                $students[] = $student;
            }

            $mainStudent = $students[0]; // siswa.lab.001

            // 8. Shared File Model for Materials & Assignment Attachment
            $samplePdf = File::firstOrCreate(
                ['object_key' => 'academic/materials/modul-pembelajaran-laboratorium-um.pdf'],
                [
                    'school_id' => $schoolId,
                    'owner_id' => $userGuruMtk->id,
                    'owner_type' => 'User',
                    'bucket' => 'academic',
                    'mime_type' => 'application/pdf',
                    'size_bytes' => 204800,
                    'visibility' => 'publik',
                ]
            );

            // 9. Modul Ajar (Learning Materials & Teaching Materials)
            $materialsData = [
                [
                    'subject' => $subjectModels['INF-7'],
                    'teacher' => $teacherWalas7a,
                    'user' => $userWalas7a,
                    'title' => 'Modul Ajar 01: Fondasi Berpikir Komputasional dan Pemrograman Dasar',
                    'description' => 'Membahas dekomposisi masalah, pengenalan pola, abstraksi, dan penyusunan algoritma Scratch interaktif.',
                    'type' => 'modul',
                ],
                [
                    'subject' => $subjectModels['MTK-7'],
                    'teacher' => $teacherMtk,
                    'user' => $userGuruMtk,
                    'title' => 'Modul Ajar 02: Bentuk Aljabar, Relasi, dan Persamaan Linear',
                    'description' => 'Pedoman KBM operasi hitung suku sejenis, persamaan linear satu variabel, dan pemodelan kontekstual.',
                    'type' => 'modul',
                ],
                [
                    'subject' => $subjectModels['IPA-7'],
                    'teacher' => $teacherIpa,
                    'user' => $userGuruIpa,
                    'title' => 'Modul Ajar 03: Metode Ilmiah, Objek IPA, dan Pengamatan Laboratorium',
                    'description' => 'Materi praktikum pengukuran besaran pokok, keselamatan kerja laboratorium, dan ekosistem sekolah.',
                    'type' => 'modul',
                ],
                [
                    'subject' => $subjectModels['BIN-7'],
                    'teacher' => $teacherBin,
                    'user' => $userGuruBin,
                    'title' => 'Modul Ajar 04: Menyusun Teks Laporan Hasil Observasi dan Prosedur',
                    'description' => 'Panduan struktur teks ilmiah populer, kaidah kebahasaan baku, dan penyusunan laporan observasi.',
                    'type' => 'modul',
                ],
            ];

            foreach ($materialsData as $mItem) {
                LearningMaterial::firstOrCreate(
                    [
                        'class_id' => $class7A->id,
                        'subject_id' => $mItem['subject']->id,
                        'title' => $mItem['title'],
                    ],
                    [
                        'teacher_id' => $mItem['teacher']->id,
                        'description' => $mItem['description'],
                        'file_id' => $samplePdf->id,
                    ]
                );

                TeachingMaterial::firstOrCreate(
                    [
                        'grade_level_id' => $gradeLevel7->id,
                        'subject_id' => $mItem['subject']->id,
                        'title' => $mItem['title'],
                    ],
                    [
                        'uploaded_by' => $mItem['user']->id,
                        'file_id' => $samplePdf->id,
                        'material_type' => $mItem['type'],
                        'description' => $mItem['description'],
                        'version' => 1,
                    ]
                );
            }

            // 10. Tugas KBM (Assignments & Submissions)
            // Tugas 1: AKTIF (Belum Dikerjakan siswa.lab.001 - Siap Dicoba Submit)
            $asg1 = Assignment::firstOrCreate(
                [
                    'class_id' => $class7A->id,
                    'subject_id' => $subjectModels['INF-7']->id,
                    'title' => 'Tugas Proyek 01: Rancang Algoritma Flowchart & Pseudocode Kasir Toko',
                ],
                [
                    'teacher_id' => $teacherWalas7a->id,
                    'description' => 'Rancanglah diagram alir (flowchart) dan pseudocode untuk sistem kasir belanja dengan logika diskon bertingkat. Unggah berkas dokumen (PDF atau JPG/PNG) rancangan Anda.',
                    'due_at' => Carbon::now()->addDays(5)->setTime(23, 59, 0),
                    'max_score' => 100.00,
                    'file_id' => $samplePdf->id,
                ]
            );

            // Tugas 2: AKTIF (Belum Dikerjakan siswa.lab.001)
            $asg2 = Assignment::firstOrCreate(
                [
                    'class_id' => $class7A->id,
                    'subject_id' => $subjectModels['MTK-7']->id,
                    'title' => 'Latihan Soal Aljabar: Pemodelan Matematika Sistem Persamaan Linear',
                ],
                [
                    'teacher_id' => $teacherMtk->id,
                    'description' => 'Selesaikan 5 permasalahan pemodelan aljabar linear pada lembar kerja siswa halaman 42. Tuliskan langkah eliminasi dan substitusi secara runtut.',
                    'due_at' => Carbon::now()->addDays(4)->setTime(16, 0, 0),
                    'max_score' => 100.00,
                ]
            );

            // Tugas 3: DIKUMPULKAN (Menunggu Penilaian Guru)
            $asg3 = Assignment::firstOrCreate(
                [
                    'class_id' => $class7A->id,
                    'subject_id' => $subjectModels['BIN-7']->id,
                    'title' => 'Teks Laporan Hasil Observasi: Flora & Fauna Kampus Laboratorium UM',
                ],
                [
                    'teacher_id' => $teacherBin->id,
                    'description' => 'Lakukan pengamatan di area arboretum atau taman sekolah, susun teks LHO lengkap dengan definisi umum, deskripsi bagian, dan deskripsi manfaat.',
                    'due_at' => Carbon::now()->subDays(1)->setTime(17, 0, 0),
                    'max_score' => 100.00,
                ]
            );
            AssignmentSubmission::firstOrCreate(
                [
                    'assignment_id' => $asg3->id,
                    'student_id' => $mainStudent->id,
                ],
                [
                    'notes' => 'Telah mengumpulkan berkas teks LHO pohon Tabebuya dan kolam ikan taman laboratorium.',
                    'submitted_at' => Carbon::now()->subDays(1)->setTime(14, 20, 0),
                    'score' => null, // Menunggu dinilai guru
                ]
            );

            // Tugas 4: DINILAI (Completed with Score & Feedback)
            $asg4 = Assignment::firstOrCreate(
                [
                    'class_id' => $class7A->id,
                    'subject_id' => $subjectModels['IPA-7']->id,
                    'title' => 'Laporan Praktikum Fisika: Pengukuran Massa Jenis Zat Padat dan Cair',
                ],
                [
                    'teacher_id' => $teacherIpa->id,
                    'description' => 'Dokumentasikan hasil eksperimen pengukuran massa menggunakan neraca ohaus dan volume dengan gelas ukur untuk menentukan massa jenis batu dan minyak goreng.',
                    'due_at' => Carbon::now()->subDays(7)->setTime(15, 0, 0),
                    'max_score' => 100.00,
                ]
            );
            AssignmentSubmission::firstOrCreate(
                [
                    'assignment_id' => $asg4->id,
                    'student_id' => $mainStudent->id,
                ],
                [
                    'notes' => 'Laporan praktikum lengkap dengan data deviasi perhitungan dan dokumentasi foto neraca.',
                    'submitted_at' => Carbon::now()->subDays(7)->setTime(11, 45, 0),
                    'score' => 92.50,
                    'feedback' => 'Analisis data sangat mendalam, perhitungan massa jenis sangat akurat, dan format tabel rapi.',
                ]
            );

            // 11. Question Banks
            $bankMtk = QuestionBank::firstOrCreate(
                [
                    'name' => 'Bank Soal Matematika Kelas 7 - Aljabar & Aritmatika',
                    'subject_id' => $subjectModels['MTK-7']->id,
                ],
                [
                    'school_id' => $schoolId,
                    'grade_level_id' => $gradeLevel7->id,
                    'owner_id' => $userGuruMtk->id,
                ]
            );

            $bankInf = QuestionBank::firstOrCreate(
                [
                    'name' => 'Bank Soal Informatika Kelas 7 - Algoritma & Komputasi',
                    'subject_id' => $subjectModels['INF-7']->id,
                ],
                [
                    'school_id' => $schoolId,
                    'grade_level_id' => $gradeLevel7->id,
                    'owner_id' => $userWalas7a->id,
                ]
            );

            $bankIpa = QuestionBank::firstOrCreate(
                [
                    'name' => 'Bank Soal IPA Kelas 7 - Sains Terpadu & Ekosistem',
                    'subject_id' => $subjectModels['IPA-7']->id,
                ],
                [
                    'school_id' => $schoolId,
                    'grade_level_id' => $gradeLevel7->id,
                    'owner_id' => $userGuruIpa->id,
                ]
            );

            // Helper to seed questions across all 6 question types
            $seedQuestionsForBank = function (QuestionBank $bank, array $rawQuestions): array {
                $saved = [];
                foreach ($rawQuestions as $item) {
                    $question = Question::updateOrCreate(
                        [
                            'bank_id' => $bank->id,
                            'cp_code' => $item['cp_code'],
                        ],
                        [
                            'question_type' => $item['type'],
                            'body' => $item['body'],
                            'cognitive_level' => $item['cognitive_level'] ?? 'MOTS',
                            'difficulty' => $item['difficulty'] ?? 3,
                            'weight' => $item['weight'] ?? 10.0,
                            'explanation' => $item['explanation'] ?? null,
                            'status' => 'aktif',
                            'version' => 1,
                        ]
                    );

                    $existingLabels = [];
                    foreach ($item['options'] ?? [] as $opt) {
                        $existingLabels[] = $opt['label'];
                        QuestionOption::updateOrCreate(
                            [
                                'question_id' => $question->id,
                                'label' => $opt['label'],
                            ],
                            [
                                'body' => $opt['body'],
                                'is_correct' => $opt['is_correct'] ?? false,
                                'match_key' => $opt['match_key'] ?? null,
                            ]
                        );
                    }

                    QuestionOption::where('question_id', $question->id)
                        ->whereNotIn('label', $existingLabels)
                        ->delete();

                    $saved[] = $question;
                }

                return $saved;
            };

            // Butir Soal Matematika (Lengkap 30 Butir Soal: 5 PG, 5 PG Kompleks, 5 BS, 5 Menjodohkan, 5 Isian, 5 Uraian)
            $mtkQuestions = [
                // ─── 1. PILIHAN GANDA (PG) SEDERHANA (5 BUTIR) ───
                [
                    'cp_code' => 'MTK-CP-01',
                    'type' => 'pg',
                    'body' => ['content' => 'Hasil penyederhanaan dari bentuk aljabar 4(2x - 3) + 5x adalah ...', 'text' => 'Hasil penyederhanaan dari bentuk aljabar 4(2x - 3) + 5x adalah ...'],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => '13x - 12', 'content' => '13x - 12'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => '13x + 12', 'content' => '13x + 12'], 'is_correct' => false],
                        ['label' => 'C', 'body' => ['text' => '8x - 12', 'content' => '8x - 12'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => '8x + 12', 'content' => '8x + 12'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'MTK-CP-02',
                    'type' => 'pg',
                    'body' => ['content' => 'Jika a = 4 dan b = -2, maka nilai dari 3a - 2b adalah ...', 'text' => 'Jika a = 4 dan b = -2, maka nilai dari 3a - 2b adalah ...'],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => '8', 'content' => '8'], 'is_correct' => false],
                        ['label' => 'B', 'body' => ['text' => '12', 'content' => '12'], 'is_correct' => false],
                        ['label' => 'C', 'body' => ['text' => '16', 'content' => '16'], 'is_correct' => true],
                        ['label' => 'D', 'body' => ['text' => '20', 'content' => '20'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'MTK-CP-03',
                    'type' => 'pg',
                    'body' => ['content' => 'Pasangan suku-suku yang sejenis pada bentuk aljabar 3x^2 - 5xy + 2x^2 + 7y adalah ...', 'text' => 'Pasangan suku-suku yang sejenis pada bentuk aljabar 3x^2 - 5xy + 2x^2 + 7y adalah ...'],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => '3x^2 dan 2x^2', 'content' => '3x^2 dan 2x^2'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => '-5xy dan 7y', 'content' => '-5xy dan 7y'], 'is_correct' => false],
                        ['label' => 'C', 'body' => ['text' => '3x^2 dan -5xy', 'content' => '3x^2 dan -5xy'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => '2x^2 dan 7y', 'content' => '2x^2 dan 7y'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'MTK-CP-04',
                    'type' => 'pg',
                    'body' => ['content' => 'Hasil penjabaran dari perkalian suku aljabar (2x + 3)(x - 4) adalah ...', 'text' => 'Hasil penjabaran dari perkalian suku aljabar (2x + 3)(x - 4) adalah ...'],
                    'weight' => 2.5,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => '2x^2 - 5x - 12', 'content' => '2x^2 - 5x - 12'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => '2x^2 + 5x - 12', 'content' => '2x^2 + 5x - 12'], 'is_correct' => false],
                        ['label' => 'C', 'body' => ['text' => '2x^2 - 11x - 12', 'content' => '2x^2 - 11x - 12'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => '2x^2 + 11x + 12', 'content' => '2x^2 + 11x + 12'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'MTK-CP-05',
                    'type' => 'pg',
                    'body' => ['content' => 'Koefisien variabel y pada persamaan linear 4x - 6y + 15 = 0 adalah ...', 'text' => 'Koefisien variabel y pada persamaan linear 4x - 6y + 15 = 0 adalah ...'],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => '4', 'content' => '4'], 'is_correct' => false],
                        ['label' => 'B', 'body' => ['text' => '-6', 'content' => '-6'], 'is_correct' => true],
                        ['label' => 'C', 'body' => ['text' => '6', 'content' => '6'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => '15', 'content' => '15'], 'is_correct' => false],
                    ],
                ],

                // ─── 2. PILIHAN GANDA KOMPLEKS (5 BUTIR) ───
                [
                    'cp_code' => 'MTK-CP-06',
                    'type' => 'pg_kompleks',
                    'body' => ['content' => 'Manakah di antara persamaan berikut yang memiliki nilai penyelesaian x = 4? (Pilih semua yang benar)', 'text' => 'Manakah di antara persamaan berikut yang memiliki nilai penyelesaian x = 4? (Pilih semua yang benar)'],
                    'weight' => 3.0,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => '2x + 3 = 11', 'content' => '2x + 3 = 11'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => '5x - 8 = 12', 'content' => '5x - 8 = 12'], 'is_correct' => true],
                        ['label' => 'C', 'body' => ['text' => '3x + 2 = 10', 'content' => '3x + 2 = 10'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => '4x - 6 = 18', 'content' => '4x - 6 = 18'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'MTK-CP-07',
                    'type' => 'pg_kompleks',
                    'body' => ['content' => 'Diberikan himpunan S = {1, 2, 3, ..., 20}. Manakah pernyataan yang benar? (Pilih semua yang benar)', 'text' => 'Diberikan himpunan S = {1, 2, 3, ..., 20}. Manakah pernyataan yang benar? (Pilih semua yang benar)'],
                    'weight' => 3.0,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Bilangan 2 adalah satu-satunya bilangan genap yang prima', 'content' => 'Bilangan 2 adalah satu-satunya bilangan genap yang prima'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Bilangan prima antara 10 dan 20 ada 4 buah (11, 13, 17, 19)', 'content' => 'Bilangan prima antara 10 dan 20 ada 4 buah (11, 13, 17, 19)'], 'is_correct' => true],
                        ['label' => 'C', 'body' => ['text' => 'Angka 9 dan 15 bukan bilangan prima karena punya lebih dari dua faktor', 'content' => 'Angka 9 dan 15 bukan bilangan prima karena punya lebih dari dua faktor'], 'is_correct' => true],
                        ['label' => 'D', 'body' => ['text' => 'Semua bilangan ganjil adalah bilangan prima', 'content' => 'Semua bilangan ganjil adalah bilangan prima'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'MTK-CP-08',
                    'type' => 'pg_kompleks',
                    'body' => ['content' => 'Manakah di antara pasangan tiga bilangan berikut yang merupakan Tripel Pythagoras? (Pilih semua yang benar)', 'text' => 'Manakah di antara pasangan tiga bilangan berikut yang merupakan Tripel Pythagoras? (Pilih semua yang benar)'],
                    'weight' => 3.0,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => '3, 4, 5', 'content' => '3, 4, 5'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => '5, 12, 13', 'content' => '5, 12, 13'], 'is_correct' => true],
                        ['label' => 'C', 'body' => ['text' => '7, 24, 25', 'content' => '7, 24, 25'], 'is_correct' => true],
                        ['label' => 'D', 'body' => ['text' => '6, 8, 12', 'content' => '6, 8, 12'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'MTK-CP-09',
                    'type' => 'pg_kompleks',
                    'body' => ['content' => 'Manakah sifat-sifat operasi hitung bentuk aljabar yang selalu berlaku? (Pilih semua yang benar)', 'text' => 'Manakah sifat-sifat operasi hitung bentuk aljabar yang selalu berlaku? (Pilih semua yang benar)'],
                    'weight' => 3.0,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Sifat Komutatif: a + b = b + a dan a x b = b x a', 'content' => 'Sifat Komutatif: a + b = b + a dan a x b = b x a'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Sifat Asosiatif: (a + b) + c = a + (b + c)', 'content' => 'Sifat Asosiatif: (a + b) + c = a + (b + c)'], 'is_correct' => true],
                        ['label' => 'C', 'body' => ['text' => 'Sifat Distributif: a(b + c) = ab + ac', 'content' => 'Sifat Distributif: a(b + c) = ab + ac'], 'is_correct' => true],
                        ['label' => 'D', 'body' => ['text' => 'Sifat Pengurangan Komutatif: a - b = b - a', 'content' => 'Sifat Pengurangan Komutatif: a - b = b - a'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'MTK-CP-10',
                    'type' => 'pg_kompleks',
                    'body' => ['content' => 'Diberikan persamaan kuadrat x^2 - 16 = 0. Nilai-nilai x yang memenuhi persamaan tersebut adalah ... (Pilih semua yang benar)', 'text' => 'Diberikan persamaan kuadrat x^2 - 16 = 0. Nilai-nilai x yang memenuhi persamaan tersebut adalah ... (Pilih semua yang benar)'],
                    'weight' => 3.0,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'x = 4', 'content' => 'x = 4'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'x = -4', 'content' => 'x = -4'], 'is_correct' => true],
                        ['label' => 'C', 'body' => ['text' => 'x = 16', 'content' => 'x = 16'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => 'x = 0', 'content' => 'x = 0'], 'is_correct' => false],
                    ],
                ],

                // ─── 3. BENAR / SALAH (5 BUTIR) ───
                [
                    'cp_code' => 'MTK-CP-11',
                    'type' => 'bs',
                    'body' => ['content' => 'Pernyataan Koperasi: Sebuah buku tulis berharga normal Rp50.000 didiskon 20%, maka harga yang dibayar adalah Rp40.000.', 'text' => 'Pernyataan Koperasi: Sebuah buku tulis berharga normal Rp50.000 didiskon 20%, maka harga yang dibayar adalah Rp40.000.'],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'MTK-CP-12',
                    'type' => 'bs',
                    'body' => ['content' => 'Pernyataan Aljabar: Bentuk persamaan 2(x + 3) = 2x + 6 merupakan suatu kesamaan identitas aljabar yang selalu bernilai benar untuk semua nilai x.', 'text' => 'Pernyataan Aljabar: Bentuk persamaan 2(x + 3) = 2x + 6 merupakan suatu kesamaan identitas aljabar yang selalu bernilai benar untuk semua nilai x.'],
                    'weight' => 2.5,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'MTK-CP-13',
                    'type' => 'bs',
                    'body' => ['content' => 'Pernyataan SPLSV: Jika 3y - 7 = 14, maka nilai y yang memenuhi persamaan tersebut adalah 8.', 'text' => 'Pernyataan SPLSV: Jika 3y - 7 = 14, maka nilai y yang memenuhi persamaan tersebut adalah 8.'],
                    'weight' => 2.5,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => false],
                        ['label' => 'B', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => true],
                    ],
                ],
                [
                    'cp_code' => 'MTK-CP-14',
                    'type' => 'bs',
                    'body' => ['content' => 'Pernyataan Gradien: Garis lurus dengan persamaan y = 3x - 4 memiliki nilai kemiringan (gradien) positif m = 3.', 'text' => 'Pernyataan Gradien: Garis lurus dengan persamaan y = 3x - 4 memiliki nilai kemiringan (gradien) positif m = 3.'],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'MTK-CP-15',
                    'type' => 'bs',
                    'body' => ['content' => 'Pernyataan Bilangan: Angka 2 adalah satu-satunya bilangan prima genap di dalam sistem bilangan bulat.', 'text' => 'Pernyataan Bilangan: Angka 2 adalah satu-satunya bilangan prima genap di dalam sistem bilangan bulat.'],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                    ],
                ],

                // ─── 4. MENJODOHKAN / MATCHING PAIR (5 BUTIR) ───
                [
                    'cp_code' => 'MTK-CP-16',
                    'type' => 'menjodohkan',
                    'body' => ['content' => 'Pasangkan rumus luas bangun datar berikut dengan nama bangun datarnya!', 'text' => 'Pasangkan rumus luas bangun datar berikut dengan nama bangun datarnya!'],
                    'weight' => 4.0,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'P1', 'body' => ['text' => 'p x l', 'content' => 'p x l'], 'is_correct' => false, 'match_key' => 'Luas Persegi Panjang'],
                        ['label' => 'P2', 'body' => ['text' => '1/2 x a x t', 'content' => '1/2 x a x t'], 'is_correct' => false, 'match_key' => 'Luas Segitiga'],
                        ['label' => 'P3', 'body' => ['text' => 'pi x r^2', 'content' => 'pi x r^2'], 'is_correct' => false, 'match_key' => 'Luas Lingkaran'],
                    ],
                ],
                [
                    'cp_code' => 'MTK-CP-17',
                    'type' => 'menjodohkan',
                    'body' => ['content' => 'Pasangkan bentuk aljabar di Kolom Kiri dengan hasil pemfaktorannya di Kolom Kanan!', 'text' => 'Pasangkan bentuk aljabar di Kolom Kiri dengan hasil pemfaktorannya di Kolom Kanan!'],
                    'weight' => 4.0,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'P1', 'body' => ['text' => '(x - 3)(x + 3)', 'content' => '(x - 3)(x + 3)'], 'is_correct' => false, 'match_key' => 'x^2 - 9'],
                        ['label' => 'P2', 'body' => ['text' => '(x + 2)(x + 3)', 'content' => '(x + 2)(x + 3)'], 'is_correct' => false, 'match_key' => 'x^2 + 5x + 6'],
                        ['label' => 'P3', 'body' => ['text' => '2x(x + 2)', 'content' => '2x(x + 2)'], 'is_correct' => false, 'match_key' => '2x^2 + 4x'],
                    ],
                ],
                [
                    'cp_code' => 'MTK-CP-18',
                    'type' => 'menjodohkan',
                    'body' => ['content' => 'Pasangkan komponen aljabar berikut dengan contoh nilainya pada bentuk 5x - 7!', 'text' => 'Pasangkan komponen aljabar berikut dengan contoh nilainya pada bentuk 5x - 7!'],
                    'weight' => 4.0,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'P1', 'body' => ['text' => 'x', 'content' => 'x'], 'is_correct' => false, 'match_key' => 'Variabel'],
                        ['label' => 'P2', 'body' => ['text' => '5', 'content' => '5'], 'is_correct' => false, 'match_key' => 'Koefisien x'],
                        ['label' => 'P3', 'body' => ['text' => '-7', 'content' => '-7'], 'is_correct' => false, 'match_key' => 'Konstanta'],
                    ],
                ],
                [
                    'cp_code' => 'MTK-CP-19',
                    'type' => 'menjodohkan',
                    'body' => ['content' => 'Pasangkan persamaan linear di Kolom Kiri dengan nilai penyelesaiannya di Kolom Kanan!', 'text' => 'Pasangkan persamaan linear di Kolom Kiri dengan nilai penyelesaiannya di Kolom Kanan!'],
                    'weight' => 4.0,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'P1', 'body' => ['text' => 'x = 5', 'content' => 'x = 5'], 'is_correct' => false, 'match_key' => '2x = 10'],
                        ['label' => 'P2', 'body' => ['text' => 'x = 3', 'content' => 'x = 3'], 'is_correct' => false, 'match_key' => '3x + 1 = 10'],
                        ['label' => 'P3', 'body' => ['text' => 'x = 2', 'content' => 'x = 2'], 'is_correct' => false, 'match_key' => '4x - 8 = 0'],
                    ],
                ],
                [
                    'cp_code' => 'MTK-CP-20',
                    'type' => 'menjodohkan',
                    'body' => ['content' => 'Pasangkan ukuran dua sisi tegak segitiga siku-siku dengan panjang hipotenusanya!', 'text' => 'Pasangkan ukuran dua sisi tegak segitiga siku-siku dengan panjang hipotenusanya!'],
                    'weight' => 4.0,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'P1', 'body' => ['text' => '5 cm', 'content' => '5 cm'], 'is_correct' => false, 'match_key' => '3 cm dan 4 cm'],
                        ['label' => 'P2', 'body' => ['text' => '13 cm', 'content' => '13 cm'], 'is_correct' => false, 'match_key' => '5 cm dan 12 cm'],
                        ['label' => 'P3', 'body' => ['text' => '25 cm', 'content' => '25 cm'], 'is_correct' => false, 'match_key' => '7 cm dan 24 cm'],
                    ],
                ],

                // ─── 5. ISIAN SINGKAT (5 BUTIR) ───
                [
                    'cp_code' => 'MTK-CP-21',
                    'type' => 'isian',
                    'body' => [
                        'content' => 'Tentukan nilai penyelesaian x dari persamaan: 5x - 14 = 2x + 16',
                        'text' => 'Tentukan nilai penyelesaian x dari persamaan: 5x - 14 = 2x + 16',
                        'correct_answer' => '10',
                    ],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'JAWABAN', 'body' => ['text' => '10', 'content' => '10'], 'is_correct' => true],
                    ],
                ],
                [
                    'cp_code' => 'MTK-CP-22',
                    'type' => 'isian',
                    'body' => [
                        'content' => 'Jika 3y - 7 = 14, maka nilai y adalah ...',
                        'text' => 'Jika 3y - 7 = 14, maka nilai y adalah ...',
                        'correct_answer' => '7',
                    ],
                    'weight' => 2.5,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'JAWABAN', 'body' => ['text' => '7', 'content' => '7'], 'is_correct' => true],
                    ],
                ],
                [
                    'cp_code' => 'MTK-CP-23',
                    'type' => 'isian',
                    'body' => [
                        'content' => 'Hasil perhitungan dari 2^4 - 3^2 adalah ...',
                        'text' => 'Hasil perhitungan dari 2^4 - 3^2 adalah ...',
                        'correct_answer' => '7',
                    ],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'JAWABAN', 'body' => ['text' => '7', 'content' => '7'], 'is_correct' => true],
                    ],
                ],
                [
                    'cp_code' => 'MTK-CP-24',
                    'type' => 'isian',
                    'body' => [
                        'content' => 'Keliling bangun persegi yang memiliki panjang sisi 15 cm adalah ... cm',
                        'text' => 'Keliling bangun persegi yang memiliki panjang sisi 15 cm adalah ... cm',
                        'correct_answer' => '60',
                    ],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'JAWABAN', 'body' => ['text' => '60', 'content' => '60'], 'is_correct' => true],
                    ],
                ],
                [
                    'cp_code' => 'MTK-CP-25',
                    'type' => 'isian',
                    'body' => [
                        'content' => 'Hasil operasi hitung dari -12 + (-18) : 3 adalah ...',
                        'text' => 'Hasil operasi hitung dari -12 + (-18) : 3 adalah ...',
                        'correct_answer' => '-18',
                    ],
                    'weight' => 2.5,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'JAWABAN', 'body' => ['text' => '-18', 'content' => '-18'], 'is_correct' => true],
                    ],
                ],

                // ─── 6. URAIAN / ESSAY HOTS (5 BUTIR) ───
                [
                    'cp_code' => 'MTK-CP-26',
                    'type' => 'uraian',
                    'body' => [
                        'content' => 'Sebuah kebun hidroponik berbentuk persegi panjang berukuran panjang (3x + 4) meter dan lebar (2x - 1) meter. Jika kelilingnya tidak boleh lebih dari 46 meter, tentukan nilai maksimum x (bilangan bulat) dan hitunglah luas kebun tersebut!',
                        'text' => 'Sebuah kebun hidroponik berbentuk persegi panjang berukuran panjang (3x + 4) meter dan lebar (2x - 1) meter. Jika kelilingnya tidak boleh lebih dari 46 meter, tentukan nilai maksimum x (bilangan bulat) dan hitunglah luas kebun tersebut!',
                    ],
                    'weight' => 4.0,
                    'cognitive_level' => 'HOTS',
                    'explanation' => 'K = 2(5x + 3) <= 46 => 10x <= 40 => x <= 4. Nilai maks x = 4. Panjang = 16 m, Lebar = 7 m, Luas = 112 m2.',
                    'options' => [],
                ],
                [
                    'cp_code' => 'MTK-CP-27',
                    'type' => 'uraian',
                    'body' => [
                        'content' => 'Sebuah toko buku menjual 3 buku tulis dan 2 pensil seharga Rp18.000. Jika harga 1 buku tulis Rp4.000, tentukan harga 1 buah pensil beserta langkah perhitungannya!',
                        'text' => 'Sebuah toko buku menjual 3 buku tulis dan 2 pensil seharga Rp18.000. Jika harga 1 buku tulis Rp4.000, tentukan harga 1 buah pensil beserta langkah perhitungannya!',
                    ],
                    'weight' => 4.0,
                    'cognitive_level' => 'HOTS',
                    'explanation' => 'Model: 3B + 2P = 18.000. 3(4.000) + 2P = 18.000 -> 12.000 + 2P = 18.000 -> 2P = 6.000 -> P = Rp3.000.',
                    'options' => [],
                ],
                [
                    'cp_code' => 'MTK-CP-28',
                    'type' => 'uraian',
                    'body' => [
                        'content' => 'Dua bilangan cacah memiliki selisih 6 dan jumlah kedua bilangan tersebut adalah 28. Tentukan kedua bilangan cacah tersebut menggunakan langkah eliminasi aljabar!',
                        'text' => 'Dua bilangan cacah memiliki selisih 6 dan jumlah kedua bilangan tersebut adalah 28. Tentukan kedua bilangan cacah tersebut menggunakan langkah eliminasi aljabar!',
                    ],
                    'weight' => 4.0,
                    'cognitive_level' => 'HOTS',
                    'explanation' => 'x - y = 6; x + y = 28. Jumlahkan: 2x = 34 => x = 17. Substitusi: 17 + y = 28 => y = 11. Bilangan pertama 17 dan bilangan kedua 11.',
                    'options' => [],
                ],
                [
                    'cp_code' => 'MTK-CP-29',
                    'type' => 'uraian',
                    'body' => [
                        'content' => 'Sebuah kolam renang bervolume 120 m3 dikuras menggunakan pompa air berdebit 400 liter per menit. Berapa jam waktu yang diperlukan untuk mengosongkan seluruh air kolam renang tersebut?',
                        'text' => 'Sebuah kolam renang bervolume 120 m3 dikuras menggunakan pompa air berdebit 400 liter per menit. Berapa jam waktu yang diperlukan untuk mengosongkan seluruh air kolam renang tersebut!',
                    ],
                    'weight' => 4.0,
                    'cognitive_level' => 'HOTS',
                    'explanation' => 'Volume = 120 m3 = 120.000 liter. Waktu = Volume / Debit = 120.000 / 400 = 300 menit = 5 jam.',
                    'options' => [],
                ],
                [
                    'cp_code' => 'MTK-CP-30',
                    'type' => 'uraian',
                    'body' => [
                        'content' => 'Koperasi sekolah meminjam modal Rp10.000.000 dengan sistem bunga tunggal 12% per tahun selama 10 bulan. Hitunglah total uang pengembalian pinjaman dan besarnya angsuran tiap bulannya!',
                        'text' => 'Koperasi sekolah meminjam modal Rp10.000.000 dengan sistem bunga tunggal 12% per tahun selama 10 bulan. Hitunglah total uang pengembalian pinjaman dan besarnya angsuran tiap bulannya!',
                    ],
                    'weight' => 4.0,
                    'cognitive_level' => 'HOTS',
                    'explanation' => 'Bunga = 10/12 * 12% * 10.000.000 = Rp1.000.000. Total pengembalian = Rp11.000.000. Angsuran per bulan = 11.000.000 / 10 = Rp1.100.000 per bulan.',
                    'options' => [],
                ],
            ];

            // Butir Soal Informatika (6 Tipe Soal)
            // Butir Soal Informatika (30 Butir Soal: 5 PG, 5 PGK, 5 BS, 5 Menjodohkan, 5 Isian, 5 Uraian)
            $infQuestions = [
                // ─── 1. PILIHAN GANDA (PG) SEDERHANA (5 BUTIR) ───
                [
                    'cp_code' => 'INF-CP-01',
                    'type' => 'pg',
                    'body' => ['content' => 'Blok perintah pada pemrograman Scratch yang digunakan untuk menjalankan script ketika bendera hijau diklik berada pada kelompok ...', 'text' => 'Blok perintah pada pemrograman Scratch yang digunakan untuk menjalankan script ketika bendera hijau diklik berada pada kelompok ...'],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Events (Kejadian)', 'content' => 'Events (Kejadian)'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Motion (Gerakan)', 'content' => 'Motion (Gerakan)'], 'is_correct' => false],
                        ['label' => 'C', 'body' => ['text' => 'Control (Kontrol)', 'content' => 'Control (Kontrol)'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => 'Sensing (Sensor)', 'content' => 'Sensing (Sensor)'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'INF-CP-02',
                    'type' => 'pg',
                    'body' => ['content' => 'Kombinasi tombol keyboard (shortcut) standar untuk menyalin (copy) objek yang dipilih ke clipboard adalah ...', 'text' => 'Kombinasi tombol keyboard (shortcut) standar untuk menyalin (copy) objek yang dipilih ke clipboard adalah ...'],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Ctrl + C', 'content' => 'Ctrl + C'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Ctrl + V', 'content' => 'Ctrl + V'], 'is_correct' => false],
                        ['label' => 'C', 'body' => ['text' => 'Ctrl + X', 'content' => 'Ctrl + X'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => 'Ctrl + Z', 'content' => 'Ctrl + Z'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'INF-CP-03',
                    'type' => 'pg',
                    'body' => ['content' => 'Protokol jaringan yang bertugas mengirimkan surat elektronik (email) antar-server adalah ...', 'text' => 'Protokol jaringan yang bertugas mengirimkan surat elektronik (email) antar-server adalah ...'],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'SMTP (Simple Mail Transfer Protocol)', 'content' => 'SMTP (Simple Mail Transfer Protocol)'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'HTTP (Hypertext Transfer Protocol)', 'content' => 'HTTP (Hypertext Transfer Protocol)'], 'is_correct' => false],
                        ['label' => 'C', 'body' => ['text' => 'FTP (File Transfer Protocol)', 'content' => 'FTP (File Transfer Protocol)'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => 'DHCP (Dynamic Host Configuration Protocol)', 'content' => 'DHCP (Dynamic Host Configuration Protocol)'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'INF-CP-04',
                    'type' => 'pg',
                    'body' => ['content' => 'Komponen perangkat keras di dalam CPU yang bertugas melakukan operasi perhitungan aritmatika dan logika perbandingan adalah ...', 'text' => 'Komponen perangkat keras di dalam CPU yang bertugas melakukan operasi perhitungan aritmatika dan logika perbandingan adalah ...'],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'ALU (Arithmetic Logic Unit)', 'content' => 'ALU (Arithmetic Logic Unit)'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'CU (Control Unit)', 'content' => 'CU (Control Unit)'], 'is_correct' => false],
                        ['label' => 'C', 'body' => ['text' => 'Register Memory', 'content' => 'Register Memory'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => 'Power Supply Unit', 'content' => 'Power Supply Unit'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'INF-CP-05',
                    'type' => 'pg',
                    'body' => ['content' => 'Format file gambar yang mendukung transparansi latar belakang (alpha channel) dan kompresi lossless adalah ...', 'text' => 'Format file gambar yang mendukung transparansi latar belakang (alpha channel) dan kompresi lossless adalah ...'],
                    'weight' => 2.5,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'PNG (.png)', 'content' => 'PNG (.png)'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'JPEG (.jpg)', 'content' => 'JPEG (.jpg)'], 'is_correct' => false],
                        ['label' => 'C', 'body' => ['text' => 'BMP (.bmp)', 'content' => 'BMP (.bmp)'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => 'MP3 (.mp3)', 'content' => 'MP3 (.mp3)'], 'is_correct' => false],
                    ],
                ],

                // ─── 2. PILIHAN GANDA KOMPLEKS (5 BUTIR) ───
                [
                    'cp_code' => 'INF-CP-06',
                    'type' => 'pg_kompleks',
                    'body' => ['content' => 'Manakah di antara berikut yang merupakan ciri-ciri algoritma yang baik? (Pilih semua yang benar)', 'text' => 'Manakah di antara berikut yang merupakan ciri-ciri algoritma yang baik? (Pilih semua yang benar)'],
                    'weight' => 3.0,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Langkah-langkahnya jelas dan tidak ambigu (Definiteness)', 'content' => 'Langkah-langkahnya jelas dan tidak ambigu (Definiteness)'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Memiliki akhir setelah sejumlah langkah tertentu (Finiteness)', 'content' => 'Memiliki akhir setelah sejumlah langkah tertentu (Finiteness)'], 'is_correct' => true],
                        ['label' => 'C', 'body' => ['text' => 'Memiliki output keluaran yang diharapkan', 'content' => 'Memiliki output keluaran yang diharapkan'], 'is_correct' => true],
                        ['label' => 'D', 'body' => ['text' => 'Berjalan selamanya dalam infinite loop tanpa terminasi', 'content' => 'Berjalan selamanya dalam infinite loop tanpa terminasi'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'INF-CP-07',
                    'type' => 'pg_kompleks',
                    'body' => ['content' => 'Manakah perangkat keras komputer yang berfungsi sebagai peranti masukan (input device)? (Pilih semua yang benar)', 'text' => 'Manakah perangkat keras komputer yang berfungsi sebagai peranti masukan (input device)? (Pilih semua yang benar)'],
                    'weight' => 3.0,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Keyboard mekanik', 'content' => 'Keyboard mekanik'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Optical Mouse', 'content' => 'Optical Mouse'], 'is_correct' => true],
                        ['label' => 'C', 'body' => ['text' => 'Monitor LCD', 'content' => 'Monitor LCD'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => 'Dokumen Scanner', 'content' => 'Dokumen Scanner'], 'is_correct' => true],
                    ],
                ],
                [
                    'cp_code' => 'INF-CP-08',
                    'type' => 'pg_kompleks',
                    'body' => ['content' => 'Pilihlah langkah-langkah kebiasaan baik untuk melindungi akun digital dari peretasan (cyber hygiene):', 'text' => 'Pilihlah langkah-langkah kebiasaan baik untuk melindungi akun digital dari peretasan (cyber hygiene):'],
                    'weight' => 3.0,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Mengaktifkan verifikasi dua langkah (2FA/MFA)', 'content' => 'Mengaktifkan verifikasi dua langkah (2FA/MFA)'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Menggunakan password unik dan panjang kombinasi huruf, angka, simbol', 'content' => 'Menggunakan password unik dan panjang kombinasi huruf, angka, simbol'], 'is_correct' => true],
                        ['label' => 'C', 'body' => ['text' => 'Menuliskan password di stiker monitor komputer umum perpustakaan', 'content' => 'Menuliskan password di stiker monitor komputer umum perpustakaan'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => 'Logout akun setelah selesai menggunakan komputer bersama', 'content' => 'Logout akun setelah selesai menggunakan komputer bersama'], 'is_correct' => true],
                    ],
                ],
                [
                    'cp_code' => 'INF-CP-09',
                    'type' => 'pg_kompleks',
                    'body' => ['content' => 'Karakteristik dari memori kerja utama (RAM - Random Access Memory) adalah: (Pilih semua yang benar)', 'text' => 'Karakteristik dari memori kerja utama (RAM - Random Access Memory) adalah: (Pilih semua yang benar)'],
                    'weight' => 3.0,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Bersifat volatil (data hilang jika aliran daya listrik terputus)', 'content' => 'Bersifat volatil (data hilang jika aliran daya listrik terputus)'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Menyimpan data instruksi sementara saat aplikasi berjalan', 'content' => 'Menyimpan data instruksi sementara saat aplikasi berjalan'], 'is_correct' => true],
                        ['label' => 'C', 'body' => ['text' => 'Kecepatan akses jauh lebih cepat dibanding hard disk atau SSD', 'content' => 'Kecepatan akses jauh lebih cepat dibanding hard disk atau SSD'], 'is_correct' => true],
                        ['label' => 'D', 'body' => ['text' => 'Menyimpan sistem operasi secara permanen selamanya tanpa arus', 'content' => 'Menyimpan sistem operasi secara permanen selamanya tanpa arus'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'INF-CP-10',
                    'type' => 'pg_kompleks',
                    'body' => ['content' => 'Manakah pilar utama dari Berpikir Komputasional (Computational Thinking)? (Pilih semua yang benar)', 'text' => 'Manakah pilar utama dari Berpikir Komputasional (Computational Thinking)? (Pilih semua yang benar)'],
                    'weight' => 3.0,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Dekomposisi (Decomposition)', 'content' => 'Dekomposisi (Decomposition)'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Pengenalan Pola (Pattern Recognition)', 'content' => 'Pengenalan Pola (Pattern Recognition)'], 'is_correct' => true],
                        ['label' => 'C', 'body' => ['text' => 'Abstraksi (Abstraction)', 'content' => 'Abstraksi (Abstraction)'], 'is_correct' => true],
                        ['label' => 'D', 'body' => ['text' => 'Perancangan Algoritma (Algorithm Design)', 'content' => 'Perancangan Algoritma (Algorithm Design)'], 'is_correct' => true],
                    ],
                ],

                // ─── 3. BENAR / SALAH (5 BUTIR) ───
                [
                    'cp_code' => 'INF-CP-11',
                    'type' => 'bs',
                    'body' => ['content' => 'Pernyataan: Dekomposisi adalah kemampuan memecah masalah besar dan rumit menjadi bagian-bagian yang lebih kecil sehingga lebih mudah diselesaikan.', 'text' => 'Pernyataan: Dekomposisi adalah kemampuan memecah masalah besar dan rumit menjadi bagian-bagian yang lebih kecil sehingga lebih mudah diselesaikan.'],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'INF-CP-12',
                    'type' => 'bs',
                    'body' => ['content' => 'Pernyataan: Firewall pada sistem jaringan berfungsi untuk menyaring dan memblokir lalu lintas data yang mencurigakan atau berbahaya.', 'text' => 'Pernyataan: Firewall pada sistem jaringan berfungsi untuk menyaring dan memblokir lalu lintas data yang mencurigakan atau berbahaya.'],
                    'weight' => 2.5,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'INF-CP-13',
                    'type' => 'bs',
                    'body' => ['content' => 'Pernyataan: Topologi jaringan berbentuk Mesh mengharuskan setiap komputer klien hanya boleh terhubung ke tepat satu komputer tetangga terdekatnya.', 'text' => 'Pernyataan: Topologi jaringan berbentuk Mesh mengharuskan setiap komputer klien hanya boleh terhubung ke tepat satu komputer tetangga terdekatnya.'],
                    'weight' => 2.5,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => false],
                        ['label' => 'B', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => true],
                    ],
                ],
                [
                    'cp_code' => 'INF-CP-14',
                    'type' => 'bs',
                    'body' => ['content' => 'Pernyataan: Phishing adalah metode rekayasa sosial yang berupaya memancing korban agar menyerahkan data sensitif seperti password atau nomor rekening bank melalui pesan palsu.', 'text' => 'Pernyataan: Phishing adalah metode rekayasa sosial yang berupaya memancing korban agar menyerahkan data sensitif seperti password atau nomor rekening bank melalui pesan palsu.'],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'INF-CP-15',
                    'type' => 'bs',
                    'body' => ['content' => 'Pernyataan: Lisensi perangkat lunak sumber terbuka (open source) melarang pengguna umum untuk mempelajari, memodifikasi, dan mendistribusikan kode sumber program.', 'text' => 'Pernyataan: Lisensi perangkat lunak sumber terbuka (open source) melarang pengguna umum untuk mempelajari, memodifikasi, dan mendistribusikan kode sumber program.'],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => false],
                        ['label' => 'B', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => true],
                    ],
                ],

                // ─── 4. MENJODOHKAN (5 BUTIR) ───
                [
                    'cp_code' => 'INF-CP-16',
                    'type' => 'menjodohkan',
                    'body' => ['content' => 'Pasangkan simbol diagram alir (flowchart) di Kolom Kiri dengan fungsinya di Kolom Kanan!', 'text' => 'Pasangkan simbol diagram alir (flowchart) di Kolom Kiri dengan fungsinya di Kolom Kanan!'],
                    'weight' => 4.0,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'P1', 'body' => ['text' => 'Mulai atau Selesai (Terminator)', 'content' => 'Mulai atau Selesai (Terminator)'], 'is_correct' => false, 'match_key' => 'Oval (Elips)'],
                        ['label' => 'P2', 'body' => ['text' => 'Proses Pengolahan Data', 'content' => 'Proses Pengolahan Data'], 'is_correct' => false, 'match_key' => 'Persegi Panjang'],
                        ['label' => 'P3', 'body' => ['text' => 'Keputusan / Kondisi Percabangan', 'content' => 'Keputusan / Kondisi Percabangan'], 'is_correct' => false, 'match_key' => 'Belah Ketupat (Diamond)'],
                        ['label' => 'P4', 'body' => ['text' => 'Input atau Output Data', 'content' => 'Input atau Output Data'], 'is_correct' => false, 'match_key' => 'Jajar Genjang'],
                    ],
                ],
                [
                    'cp_code' => 'INF-CP-17',
                    'type' => 'menjodohkan',
                    'body' => ['content' => 'Pasangkan singkatan istilah jaringan komputer di Kolom Kiri dengan kepanjangannya di Kolom Kanan!', 'text' => 'Pasangkan singkatan istilah jaringan komputer di Kolom Kiri dengan kepanjangannya di Kolom Kanan!'],
                    'weight' => 4.0,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'P1', 'body' => ['text' => 'Local Area Network', 'content' => 'Local Area Network'], 'is_correct' => false, 'match_key' => 'LAN'],
                        ['label' => 'P2', 'body' => ['text' => 'Internet Protocol', 'content' => 'Internet Protocol'], 'is_correct' => false, 'match_key' => 'IP'],
                        ['label' => 'P3', 'body' => ['text' => 'Uniform Resource Locator', 'content' => 'Uniform Resource Locator'], 'is_correct' => false, 'match_key' => 'URL'],
                    ],
                ],
                [
                    'cp_code' => 'INF-CP-18',
                    'type' => 'menjodohkan',
                    'body' => ['content' => 'Pasangkan bahasa pemrograman di Kolom Kiri dengan karakteristik utamanya di Kolom Kanan!', 'text' => 'Pasangkan bahasa pemrograman di Kolom Kiri dengan karakteristik utamanya di Kolom Kanan!'],
                    'weight' => 4.0,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'P1', 'body' => ['text' => 'Manipulasi interaktivitas halaman web browser', 'content' => 'Manipulasi interaktivitas halaman web browser'], 'is_correct' => false, 'match_key' => 'JavaScript'],
                        ['label' => 'P2', 'body' => ['text' => 'Sintaks sederhana, populer untuk Data Science & AI', 'content' => 'Sintaks sederhana, populer untuk Data Science & AI'], 'is_correct' => false, 'match_key' => 'Python'],
                        ['label' => 'P3', 'body' => ['text' => 'Bahasa kueri standar manajemen basis data relasional', 'content' => 'Bahasa kueri standar manajemen basis data relasional'], 'is_correct' => false, 'match_key' => 'SQL'],
                    ],
                ],
                [
                    'cp_code' => 'INF-CP-19',
                    'type' => 'menjodohkan',
                    'body' => ['content' => 'Pasangkan lapisan (layer) referensi OSI di Kolom Kiri dengan fungsinya di Kolom Kanan!', 'text' => 'Pasangkan lapisan (layer) referensi OSI di Kolom Kiri dengan fungsinya di Kolom Kanan!'],
                    'weight' => 4.0,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'P1', 'body' => ['text' => 'Antarmuka komunikasi langsung pengguna aplikasi', 'content' => 'Antarmuka komunikasi langsung pengguna aplikasi'], 'is_correct' => false, 'match_key' => 'Application Layer'],
                        ['label' => 'P2', 'body' => ['text' => 'Penentuan rute dan pengalamatan IP paket data', 'content' => 'Penentuan rute dan pengalamatan IP paket data'], 'is_correct' => false, 'match_key' => 'Network Layer'],
                        ['label' => 'P3', 'body' => ['text' => 'Pengiriman bit data mentah melalui media transmisi fisik', 'content' => 'Pengiriman bit data mentah melalui media transmisi fisik'], 'is_correct' => false, 'match_key' => 'Physical Layer'],
                    ],
                ],
                [
                    'cp_code' => 'INF-CP-20',
                    'type' => 'menjodohkan',
                    'body' => ['content' => 'Pasangkan pintasan keyboard (shortcut) di Kolom Kiri dengan aksi fungsinya di Kolom Kanan!', 'text' => 'Pasangkan pintasan keyboard (shortcut) di Kolom Kiri dengan aksi fungsinya di Kolom Kanan!'],
                    'weight' => 4.0,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'P1', 'body' => ['text' => 'Menyimpan dokumen berkas aktif', 'content' => 'Menyimpan dokumen berkas aktif'], 'is_correct' => false, 'match_key' => 'Ctrl + S'],
                        ['label' => 'P2', 'body' => ['text' => 'Membatalkan tindakan terakhir (Undo)', 'content' => 'Membatalkan tindakan terakhir (Undo)'], 'is_correct' => false, 'match_key' => 'Ctrl + Z'],
                        ['label' => 'P3', 'body' => ['text' => 'Membuka jendela pencarian kata/teks', 'content' => 'Membuka jendela pencarian kata/teks'], 'is_correct' => false, 'match_key' => 'Ctrl + F'],
                    ],
                ],

                // ─── 5. ISIAN SINGKAT (5 BUTIR) ───
                [
                    'cp_code' => 'INF-CP-21',
                    'type' => 'isian',
                    'body' => [
                        'content' => 'Perangkat lunak open source untuk mengolah grafis vektor berbasis kurva adalah ...',
                        'text' => 'Perangkat lunak open source untuk mengolah grafis vektor berbasis kurva adalah ...',
                        'correct_answer' => 'Inkscape',
                    ],
                    'weight' => 2.5,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'JAWABAN', 'body' => ['text' => 'Inkscape', 'content' => 'Inkscape'], 'is_correct' => true],
                    ],
                ],
                [
                    'cp_code' => 'INF-CP-22',
                    'type' => 'isian',
                    'body' => [
                        'content' => 'Bilangan biner 1010 jika dikonversikan ke dalam bilangan desimal bernilai ...',
                        'text' => 'Bilangan biner 1010 jika dikonversikan ke dalam bilangan desimal bernilai ...',
                        'correct_answer' => '10',
                    ],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'JAWABAN', 'body' => ['text' => '10', 'content' => '10'], 'is_correct' => true],
                    ],
                ],
                [
                    'cp_code' => 'INF-CP-23',
                    'type' => 'isian',
                    'body' => [
                        'content' => 'Nomor port standar terenkripsi yang digunakan oleh protokol HTTPS adalah port nomor ...',
                        'text' => 'Nomor port standar terenkripsi yang digunakan oleh protokol HTTPS adalah port nomor ...',
                        'correct_answer' => '443',
                    ],
                    'weight' => 2.5,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'JAWABAN', 'body' => ['text' => '443', 'content' => '443'], 'is_correct' => true],
                    ],
                ],
                [
                    'cp_code' => 'INF-CP-24',
                    'type' => 'isian',
                    'body' => [
                        'content' => 'Satuan kapasitas penyimpanan digital yang setara dengan 1024 Gigabyte (GB) adalah 1 ... (tulis singkatannya)',
                        'text' => 'Satuan kapasitas penyimpanan digital yang setara dengan 1024 Gigabyte (GB) adalah 1 ... (tulis singkatannya)',
                        'correct_answer' => 'TB',
                    ],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'JAWABAN', 'body' => ['text' => 'TB', 'content' => 'TB'], 'is_correct' => true],
                    ],
                ],
                [
                    'cp_code' => 'INF-CP-25',
                    'type' => 'isian',
                    'body' => [
                        'content' => 'Topologi jaringan lokal di mana setiap komputer terhubung ke satu kabel bus utama tunggal dengan terminator di kedua ujungnya disebut topologi ...',
                        'text' => 'Topologi jaringan lokal di mana setiap komputer terhubung ke satu kabel bus utama tunggal dengan terminator di kedua ujungnya disebut topologi ...',
                        'correct_answer' => 'Bus',
                    ],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'JAWABAN', 'body' => ['text' => 'Bus', 'content' => 'Bus'], 'is_correct' => true],
                    ],
                ],

                // ─── 6. URAIAN / ESAI HOTS (5 BUTIR) ───
                [
                    'cp_code' => 'INF-CP-26',
                    'type' => 'uraian',
                    'body' => [
                        'content' => 'Jelaskan mengapa Autentikasi Dua Faktor (2FA) jauh lebih aman dibandingkan hanya mengandalkan kata sandi tunggal dalam melindungi akun portal sekolah!',
                        'text' => 'Jelaskan mengapa Autentikasi Dua Faktor (2FA) jauh lebih aman dibandingkan hanya mengandalkan kata sandi tunggal dalam melindungi akun portal sekolah!',
                    ],
                    'weight' => 4.0,
                    'cognitive_level' => 'HOTS',
                    'explanation' => '2FA menggabungkan sesuatu yang diketahui (password) dengan sesuatu yang dimiliki (kode OTP / security key), sehingga jika password bocor oleh phishing, peretas tetap tidak bisa mengakses akun tanpa faktor otorisasi kedua.',
                    'options' => [],
                ],
                [
                    'cp_code' => 'INF-CP-27',
                    'type' => 'uraian',
                    'body' => [
                        'content' => 'Bandingkan arsitektur jaringan Peer-to-Peer (P2P) dengan Client-Server! Sebutkan kelebihan dan kekurangan masing-masing dalam implementasi laboratorium komputer sekolah!',
                        'text' => 'Bandingkan arsitektur jaringan Peer-to-Peer (P2P) dengan Client-Server! Sebutkan kelebihan dan kekurangan masing-masing dalam implementasi laboratorium komputer sekolah!',
                    ],
                    'weight' => 4.0,
                    'cognitive_level' => 'HOTS',
                    'explanation' => 'P2P tidak memerlukan server pusat sehingga hemat biaya tetapi sulit memusatkan hak akses dan keamanan. Client-Server memiliki server terpusat untuk autentikasi, backup terpadu, dan manajemen hak akses yang kuat, meskipun membutuhkan server berkapasitas tinggi.',
                    'options' => [],
                ],
                [
                    'cp_code' => 'INF-CP-28',
                    'type' => 'uraian',
                    'body' => [
                        'content' => 'Analisislah dampak positif dan risiko negatif dari pemanfaatan teknologi Generative AI (Kecerdasan Buatan Generatif) dalam pengerjaan tugas proyek siswa di sekolah!',
                        'text' => 'Analisislah dampak positif dan risiko negatif dari pemanfaatan teknologi Generative AI (Kecerdasan Buatan Generatif) dalam pengerjaan tugas proyek siswa di sekolah!',
                    ],
                    'weight' => 4.0,
                    'cognitive_level' => 'HOTS',
                    'explanation' => 'Dampak positif: memicu ide baru, menjelaskan konsep sulit secara interaktif, dan mempercepat riset awal. Risiko: kecurangan plagiarisme, menurunnya daya nalar kritis mandiri jika bergantung penuh tanpa verifikasi fakta (halusinasi AI).',
                    'options' => [],
                ],
                [
                    'cp_code' => 'INF-CP-29',
                    'type' => 'uraian',
                    'body' => [
                        'content' => 'Tuliskan rancangan pseudocode algoritma sederhana untuk menentukan apakah suatu nilai angka bilangan bulat yang diinput pengguna bernilai Positif, Negatif, atau Nol!',
                        'text' => 'Tuliskan rancangan pseudocode algoritma sederhana untuk menentukan apakah suatu nilai angka bilangan bulat yang diinput pengguna bernilai Positif, Negatif, atau Nol!',
                    ],
                    'weight' => 4.0,
                    'cognitive_level' => 'HOTS',
                    'explanation' => 'INPUT nilai. JIKA nilai > 0 MAKA TAMPILKAN "Positif" ELSE JIKA nilai < 0 MAKA TAMPILKAN "Negatif" ELSE TAMPILKAN "Nol" ENDIF.',
                    'options' => [],
                ],
                [
                    'cp_code' => 'INF-CP-30',
                    'type' => 'uraian',
                    'body' => [
                        'content' => 'Jelaskan konsep aturan pencadangan data "3-2-1 Backup Strategy" dan bagaimana penerapannya untuk menyelamatkan data nilai rapor sekolah dari serangan ransomware!',
                        'text' => 'Jelaskan konsep aturan pencadangan data "3-2-1 Backup Strategy" dan bagaimana penerapannya untuk menyelamatkan data nilai rapor sekolah dari serangan ransomware!',
                    ],
                    'weight' => 4.0,
                    'cognitive_level' => 'HOTS',
                    'explanation' => '3 salinan data, disimpan pada minimal 2 media penyimpanan berbeda (misal server lokal dan NAS), dan 1 salinan disimpan di lokasi offsite terisolasi (misal Cloud Backup terenkripsi immutable).',
                    'options' => [],
                ],
            ];

            // Butir Soal IPA (30 Butir Soal: 5 PG, 5 PGK, 5 BS, 5 Menjodohkan, 5 Isian, 5 Uraian)
            $ipaQuestions = [
                // ─── 1. PILIHAN GANDA (PG) SEDERHANA (5 BUTIR) ───
                [
                    'cp_code' => 'IPA-CP-01',
                    'type' => 'pg',
                    'body' => ['content' => 'Satuan baku internasional (SI) untuk mengukur besaran suhu adalah ...', 'text' => 'Satuan baku internasional (SI) untuk mengukur besaran suhu adalah ...'],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Kelvin', 'content' => 'Kelvin'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Celcius', 'content' => 'Celcius'], 'is_correct' => false],
                        ['label' => 'C', 'body' => ['text' => 'Fahrenheit', 'content' => 'Fahrenheit'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => 'Reamur', 'content' => 'Reamur'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'IPA-CP-02',
                    'type' => 'pg',
                    'body' => ['content' => 'Organel sel yang dijuluki sebagai "the powerhouse of cell" karena bertindak sebagai tempat respirasi sel dan penghasil energi ATP adalah ...', 'text' => 'Organel sel yang dijuluki sebagai "the powerhouse of cell" karena bertindak sebagai tempat respirasi sel dan penghasil energi ATP adalah ...'],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Mitokondria', 'content' => 'Mitokondria'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Ribosom', 'content' => 'Ribosom'], 'is_correct' => false],
                        ['label' => 'C', 'body' => ['text' => 'Badan Golgi', 'content' => 'Badan Golgi'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => 'Retikulum Endoplasma', 'content' => 'Retikulum Endoplasma'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'IPA-CP-03',
                    'type' => 'pg',
                    'body' => ['content' => 'Perubahan wujud zat dari wujud gas langsung menjadi padat tanpa melalui fase cair disebut ...', 'text' => 'Perubahan wujud zat dari wujud gas langsung menjadi padat tanpa melalui fase cair disebut ...'],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Mengkristal (Deposisi)', 'content' => 'Mengkristal (Deposisi)'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Menyublim', 'content' => 'Menyublim'], 'is_correct' => false],
                        ['label' => 'C', 'body' => ['text' => 'Mengembun', 'content' => 'Mengembun'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => 'Membeku', 'content' => 'Membeku'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'IPA-CP-04',
                    'type' => 'pg',
                    'body' => ['content' => 'Planet dalam tata surya yang memiliki lintasan orbit paling dekat dengan matahari adalah ...', 'text' => 'Planet dalam tata surya yang memiliki lintasan orbit paling dekat dengan matahari adalah ...'],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Merkurius', 'content' => 'Merkurius'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Venus', 'content' => 'Venus'], 'is_correct' => false],
                        ['label' => 'C', 'body' => ['text' => 'Mars', 'content' => 'Mars'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => 'Jupiter', 'content' => 'Jupiter'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'IPA-CP-05',
                    'type' => 'pg',
                    'body' => ['content' => 'Metode pemisahan campuran yang didasarkan pada perbedaan titik didih komponen-komponen penyusunnya (seperti pemisahan fraksi minyak bumi) adalah ...', 'text' => 'Metode pemisahan campuran yang didasarkan pada perbedaan titik didih komponen-komponen penyusunnya (seperti pemisahan fraksi minyak bumi) adalah ...'],
                    'weight' => 2.5,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Distilasi (Penyulingan)', 'content' => 'Distilasi (Penyulingan)'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Filtrasi (Penyaringan)', 'content' => 'Filtrasi (Penyaringan)'], 'is_correct' => false],
                        ['label' => 'C', 'body' => ['text' => 'Kromatografi', 'content' => 'Kromatografi'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => 'Kristalisasi', 'content' => 'Kristalisasi'], 'is_correct' => false],
                    ],
                ],

                // ─── 2. PILIHAN GANDA KOMPLEKS (5 BUTIR) ───
                [
                    'cp_code' => 'IPA-CP-06',
                    'type' => 'pg_kompleks',
                    'body' => ['content' => 'Manakah yang termasuk dalam kelompok besaran pokok fisika dalam Sistem Internasional (SI)? (Pilih semua yang benar)', 'text' => 'Manakah yang termasuk dalam kelompok besaran pokok fisika dalam Sistem Internasional (SI)? (Pilih semua yang benar)'],
                    'weight' => 3.0,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Panjang (meter)', 'content' => 'Panjang (meter)'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Massa (kilogram)', 'content' => 'Massa (kilogram)'], 'is_correct' => true],
                        ['label' => 'C', 'body' => ['text' => 'Waktu (sekon)', 'content' => 'Waktu (sekon)'], 'is_correct' => true],
                        ['label' => 'D', 'body' => ['text' => 'Kecepatan (meter/sekon)', 'content' => 'Kecepatan (meter/sekon)'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'IPA-CP-07',
                    'type' => 'pg_kompleks',
                    'body' => ['content' => 'Pilihlah karakteristik fisik dari partikel zat berwujud gas: (Pilih semua yang benar)', 'text' => 'Pilihlah karakteristik fisik dari partikel zat berwujud gas: (Pilih semua yang benar)'],
                    'weight' => 3.0,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Bentuk dan volumenya selalu berubah mengikuti ruang wadah', 'content' => 'Bentuk dan volumenya selalu berubah mengikuti ruang wadah'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Jarak antar-partikel sangat renggang dan gaya tarik antar-molekul sangat lemah', 'content' => 'Jarak antar-partikel sangat renggang dan gaya tarik antar-molekul sangat lemah'], 'is_correct' => true],
                        ['label' => 'C', 'body' => ['text' => 'Partikel tersusun sangat rapat dan teratur tidak dapat bergerak bebas', 'content' => 'Partikel tersusun sangat rapat dan teratur tidak dapat bergerak bebas'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => 'Dapat mengalami kompresi (pemampatan) saat diberi tekanan tinggi', 'content' => 'Dapat mengalami kompresi (pemampatan) saat diberi tekanan tinggi'], 'is_correct' => true],
                    ],
                ],
                [
                    'cp_code' => 'IPA-CP-08',
                    'type' => 'pg_kompleks',
                    'body' => ['content' => 'Manakah interaksi antarmakhluk hidup berikut yang tergolong simbiosis mutualisme? (Pilih semua yang benar)', 'text' => 'Manakah interaksi antarmakhluk hidup berikut yang tergolong simbiosis mutualisme? (Pilih semua yang benar)'],
                    'weight' => 3.0,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Lebah madu yang mengisap nektar dengan bunga yang terbantu penyerbukannya', 'content' => 'Lebah madu yang mengisap nektar dengan bunga yang terbantu penyerbukannya'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Jamur dan alga yang membentuk lumut kerak (Lichenes)', 'content' => 'Jamur dan alga yang membentuk lumut kerak (Lichenes)'], 'is_correct' => true],
                        ['label' => 'C', 'body' => ['text' => 'Tanaman benalu yang menempel dan menyerap nutrisi pohon inang', 'content' => 'Tanaman benalu yang menempel dan menyerap nutrisi pohon inang'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => 'Bakteri Rhizobium yang hidup pada bintil akar tanaman kacang-kacangan', 'content' => 'Bakteri Rhizobium yang hidup pada bintil akar tanaman kacang-kacangan'], 'is_correct' => true],
                    ],
                ],
                [
                    'cp_code' => 'IPA-CP-09',
                    'type' => 'pg_kompleks',
                    'body' => ['content' => 'Manakah yang merupakan gejala perubahan fisika pada materi? (Pilih semua yang benar)', 'text' => 'Manakah yang merupakan gejala perubahan fisika pada materi? (Pilih semua yang benar)'],
                    'weight' => 3.0,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Es batu mencair menjadi air', 'content' => 'Es batu mencair menjadi air'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Garam dapur larut sempurna di dalam air hangat', 'content' => 'Garam dapur larut sempurna di dalam air hangat'], 'is_correct' => true],
                        ['label' => 'C', 'body' => ['text' => 'Besi berkarat setelah terpapar udara dan kelembapan air hujan', 'content' => 'Besi berkarat setelah terpapar udara dan kelembapan air hujan'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => 'Beras ditumbuk halus menjadi tepung beras', 'content' => 'Beras ditumbuk halus menjadi tepung beras'], 'is_correct' => true],
                    ],
                ],
                [
                    'cp_code' => 'IPA-CP-10',
                    'type' => 'pg_kompleks',
                    'body' => ['content' => 'Komponen abiotik yang memengaruhi kelangsungan hidup ekosistem hutan tropis meliputi: (Pilih semua yang benar)', 'text' => 'Komponen abiotik yang memengaruhi kelangsungan hidup ekosistem hutan tropis meliputi: (Pilih semua yang benar)'],
                    'weight' => 3.0,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Intensitas cahaya matahari', 'content' => 'Intensitas cahaya matahari'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Kadar kelembapan tanah dan udara', 'content' => 'Kadar kelembapan tanah dan udara'], 'is_correct' => true],
                        ['label' => 'C', 'body' => ['text' => 'Suhu lingkungan rata-rata', 'content' => 'Suhu lingkungan rata-rata'], 'is_correct' => true],
                        ['label' => 'D', 'body' => ['text' => 'Populasi cacing pengurai tanah', 'content' => 'Populasi cacing pengurai tanah'], 'is_correct' => false],
                    ],
                ],

                // ─── 3. BENAR / SALAH (5 BUTIR) ───
                [
                    'cp_code' => 'IPA-CP-11',
                    'type' => 'bs',
                    'body' => ['content' => 'Pernyataan: Tumbuhan hijau berperan sebagai produsen primer dalam rantai makanan karena mampu menghasilkan makanan sendiri melalui fotosintesis.', 'text' => 'Pernyataan: Tumbuhan hijau berperan sebagai produsen primer dalam rantai makanan karena mampu menghasilkan makanan sendiri melalui fotosintesis.'],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'IPA-CP-12',
                    'type' => 'bs',
                    'body' => ['content' => 'Pernyataan: Pembakaran kertas menjadi abu dan gas karbon dioksida merupakan contoh peristiwa perubahan fisika.', 'text' => 'Pernyataan: Pembakaran kertas menjadi abu dan gas karbon dioksida merupakan contoh peristiwa perubahan fisika.'],
                    'weight' => 2.5,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => false],
                        ['label' => 'B', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => true],
                    ],
                ],
                [
                    'cp_code' => 'IPA-CP-13',
                    'type' => 'bs',
                    'body' => ['content' => 'Pernyataan: Jantung mamalia terdiri atas empat ruangan berongga, yaitu dua atrium (serambi) dan dua ventrikel (bilik).', 'text' => 'Pernyataan: Jantung mamalia terdiri atas empat ruangan berongga, yaitu dua atrium (serambi) dan dua ventrikel (bilik).'],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'IPA-CP-14',
                    'type' => 'bs',
                    'body' => ['content' => 'Pernyataan: Gaya gesek selalu memiliki arah yang searah dengan arah kecenderungan gerak benda.', 'text' => 'Pernyataan: Gaya gesek selalu memiliki arah yang searah dengan arah kecenderungan gerak benda.'],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => false],
                        ['label' => 'B', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => true],
                    ],
                ],
                [
                    'cp_code' => 'IPA-CP-15',
                    'type' => 'bs',
                    'body' => ['content' => 'Pernyataan: Kerapatan atau massa jenis (rho) zat murni didefinisikan sebagai perbandingan massa benda terhadap volume ruang yang ditempatinya.', 'text' => 'Pernyataan: Kerapatan atau massa jenis (rho) zat murni didefinisikan sebagai perbandingan massa benda terhadap volume ruang yang ditempatinya.'],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                    ],
                ],

                // ─── 4. MENJODOHKAN (5 BUTIR) ───
                [
                    'cp_code' => 'IPA-CP-16',
                    'type' => 'menjodohkan',
                    'body' => ['content' => 'Pasangkan alat ukur di Kolom Kiri dengan besaran fisika yang diukurnya di Kolom Kanan!', 'text' => 'Pasangkan alat ukur di Kolom Kiri dengan besaran fisika yang diukurnya di Kolom Kanan!'],
                    'weight' => 4.0,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'P1', 'body' => ['text' => 'Ketebalan kertas dan diameter luar tabung', 'content' => 'Ketebalan kertas dan diameter luar tabung'], 'is_correct' => false, 'match_key' => 'Mikrometer Sekrup'],
                        ['label' => 'P2', 'body' => ['text' => 'Massa benda dengan ketelitian tinggi', 'content' => 'Massa benda dengan ketelitian tinggi'], 'is_correct' => false, 'match_key' => 'Neraca Ohaus Tiga Lengan'],
                        ['label' => 'P3', 'body' => ['text' => 'Interval waktu tempuh pelari', 'content' => 'Interval waktu tempuh pelari'], 'is_correct' => false, 'match_key' => 'Stopwatch Digital'],
                        ['label' => 'P4', 'body' => ['text' => 'Derajat panas dingin suatu larutan', 'content' => 'Derajat panas dingin suatu larutan'], 'is_correct' => false, 'match_key' => 'Termometer Laboratorium'],
                    ],
                ],
                [
                    'cp_code' => 'IPA-CP-17',
                    'type' => 'menjodohkan',
                    'body' => ['content' => 'Pasangkan organ tubuh di Kolom Kiri dengan sistem biologis utamanya di Kolom Kanan!', 'text' => 'Pasangkan organ tubuh di Kolom Kiri dengan sistem biologis utamanya di Kolom Kanan!'],
                    'weight' => 4.0,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'P1', 'body' => ['text' => 'Sistem Peredaran Darah (Sirkulasi)', 'content' => 'Sistem Peredaran Darah (Sirkulasi)'], 'is_correct' => false, 'match_key' => 'Jantung'],
                        ['label' => 'P2', 'body' => ['text' => 'Sistem Pernapasan (Respirasi)', 'content' => 'Sistem Pernapasan (Respirasi)'], 'is_correct' => false, 'match_key' => 'Paru-paru'],
                        ['label' => 'P3', 'body' => ['text' => 'Sistem Pengeluaran (Ekskresi)', 'content' => 'Sistem Pengeluaran (Ekskresi)'], 'is_correct' => false, 'match_key' => 'Ginjal'],
                    ],
                ],
                [
                    'cp_code' => 'IPA-CP-18',
                    'type' => 'menjodohkan',
                    'body' => ['content' => 'Pasangkan zat nutrisi makanan di Kolom Kiri dengan fungsi faali utamanya di Kolom Kanan!', 'text' => 'Pasangkan zat nutrisi makanan di Kolom Kiri dengan fungsi faali utamanya di Kolom Kanan!'],
                    'weight' => 4.0,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'P1', 'body' => ['text' => 'Sumber energi utama siap pakai bagi tubuh', 'content' => 'Sumber energi utama siap pakai bagi tubuh'], 'is_correct' => false, 'match_key' => 'Karbohidrat'],
                        ['label' => 'P2', 'body' => ['text' => 'Pembangun struktur sel dan perbaikan jaringan rusak', 'content' => 'Pembangun struktur sel dan perbaikan jaringan rusak'], 'is_correct' => false, 'match_key' => 'Protein'],
                        ['label' => 'P3', 'body' => ['text' => 'Cadangan energi dan pelindung organ vital dari benturan', 'content' => 'Cadangan energi dan pelindung organ vital dari benturan'], 'is_correct' => false, 'match_key' => 'Lemak'],
                    ],
                ],
                [
                    'cp_code' => 'IPA-CP-19',
                    'type' => 'menjodohkan',
                    'body' => ['content' => 'Pasangkan mekanisme perpindahan kalor di Kolom Kiri dengan contoh peristiwanya di Kolom Kanan!', 'text' => 'Pasangkan mekanisme perpindahan kalor di Kolom Kiri dengan contoh peristiwanya di Kolom Kanan!'],
                    'weight' => 4.0,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'P1', 'body' => ['text' => 'Ujung sendok logam terasa panas saat mengaduk teh panas', 'content' => 'Ujung sendok logam terasa panas saat mengaduk teh panas'], 'is_correct' => false, 'match_key' => 'Konduksi'],
                        ['label' => 'P2', 'body' => ['text' => 'Aliran air mendidih yang berputar naik turun di panci', 'content' => 'Aliran air mendidih yang berputar naik turun di panci'], 'is_correct' => false, 'match_key' => 'Konveksi'],
                        ['label' => 'P3', 'body' => ['text' => 'Hangatnya sinar matahari yang sampai ke permukaan bumi melewati ruang hampa', 'content' => 'Hangatnya sinar matahari yang sampai ke permukaan bumi melewati ruang hampa'], 'is_correct' => false, 'match_key' => 'Radiasi'],
                    ],
                ],
                [
                    'cp_code' => 'IPA-CP-20',
                    'type' => 'menjodohkan',
                    'body' => ['content' => 'Pasangkan struktur lapisan bumi di Kolom Kiri dengan sifat fisiknya di Kolom Kanan!', 'text' => 'Pasangkan struktur lapisan bumi di Kolom Kiri dengan sifat fisiknya di Kolom Kanan!'],
                    'weight' => 4.0,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'P1', 'body' => ['text' => 'Lapisan terluar padat tempat berpijaknya makhluk hidup', 'content' => 'Lapisan terluar padat tempat berpijaknya makhluk hidup'], 'is_correct' => false, 'match_key' => 'Kerak Bumi (Crust)'],
                        ['label' => 'P2', 'body' => ['text' => 'Lapisan tebal batuan semi-cair kental bersuhu tinggi', 'content' => 'Lapisan tebal batuan semi-cair kental bersuhu tinggi'], 'is_correct' => false, 'match_key' => 'Mantel Bumi (Mantle)'],
                        ['label' => 'P3', 'body' => ['text' => 'Bola logam padat besi-nikel dengan tekanan dan suhu ekstrem', 'content' => 'Bola logam padat besi-nikel dengan tekanan dan suhu ekstrem'], 'is_correct' => false, 'match_key' => 'Inti Dalam (Inner Core)'],
                    ],
                ],

                // ─── 5. ISIAN SINGKAT (5 BUTIR) ───
                [
                    'cp_code' => 'IPA-CP-21',
                    'type' => 'isian',
                    'body' => [
                        'content' => 'Zat pigmen hijau daun yang berfungsi menangkap energi foton matahari saat reaksi fotosintesis disebut ...',
                        'text' => 'Zat pigmen hijau daun yang berfungsi menangkap energi foton matahari saat reaksi fotosintesis disebut ...',
                        'correct_answer' => 'Klorofil',
                    ],
                    'weight' => 2.5,
                    'cognitive_level' => 'MOTS',
                    'options' => [
                        ['label' => 'JAWABAN', 'body' => ['text' => 'Klorofil', 'content' => 'Klorofil'], 'is_correct' => true],
                    ],
                ],
                [
                    'cp_code' => 'IPA-CP-22',
                    'type' => 'isian',
                    'body' => [
                        'content' => 'Rumus molekul kimia dari senyawa air murni adalah ...',
                        'text' => 'Rumus molekul kimia dari senyawa air murni adalah ...',
                        'correct_answer' => 'H2O',
                    ],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'JAWABAN', 'body' => ['text' => 'H2O', 'content' => 'H2O'], 'is_correct' => true],
                    ],
                ],
                [
                    'cp_code' => 'IPA-CP-23',
                    'type' => 'isian',
                    'body' => [
                        'content' => 'Satuan SI yang digunakan untuk menyatakan besaran gaya gravitasi atau tegangan tali adalah ...',
                        'text' => 'Satuan SI yang digunakan untuk menyatakan besaran gaya gravitasi atau tegangan tali adalah ...',
                        'correct_answer' => 'Newton',
                    ],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'JAWABAN', 'body' => ['text' => 'Newton', 'content' => 'Newton'], 'is_correct' => true],
                    ],
                ],
                [
                    'cp_code' => 'IPA-CP-24',
                    'type' => 'isian',
                    'body' => [
                        'content' => 'Alat optik yang digunakan untuk mengamati benda-benda renik mikroskopis seperti bakteri dan jaringan sel adalah ...',
                        'text' => 'Alat optik yang digunakan untuk mengamati benda-benda renik mikroskopis seperti bakteri dan jaringan sel adalah ...',
                        'correct_answer' => 'Mikroskop',
                    ],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'JAWABAN', 'body' => ['text' => 'Mikroskop', 'content' => 'Mikroskop'], 'is_correct' => true],
                    ],
                ],
                [
                    'cp_code' => 'IPA-CP-25',
                    'type' => 'isian',
                    'body' => [
                        'content' => 'Peristiwa pembelokan arah rambat berkas cahaya ketika melewati medium yang berbeda kerapatan optiknya disebut ...',
                        'text' => 'Peristiwa pembelokan arah rambat berkas cahaya ketika melewati medium yang berbeda kerapatan optiknya disebut ...',
                        'correct_answer' => 'Pembiasan',
                    ],
                    'weight' => 2.5,
                    'cognitive_level' => 'LOTS',
                    'options' => [
                        ['label' => 'JAWABAN', 'body' => ['text' => 'Pembiasan', 'content' => 'Pembiasan'], 'is_correct' => true],
                    ],
                ],

                // ─── 6. URAIAN / ESAI HOTS (5 BUTIR) ───
                [
                    'cp_code' => 'IPA-CP-26',
                    'type' => 'uraian',
                    'body' => [
                        'content' => 'Jelaskan dampak ekologis yang akan terjadi pada suatu ekosistem persawahan apabila populasi ular sawah diburu hingga punah oleh manusia!',
                        'text' => 'Jelaskan dampak ekologis yang akan terjadi pada suatu ekosistem persawahan apabila populasi ular sawah diburu hingga punah oleh manusia!',
                    ],
                    'weight' => 4.0,
                    'cognitive_level' => 'HOTS',
                    'explanation' => 'Hilangnya ular sawah menyebabkan populasi hama tikus melonjak tajam tanpa predator alami, mengakibatkan kerusakan parah pada tanaman padi, potensi gagal panen massal bagi petani, dan runtuhnya keseimbangan piramida makanan.',
                    'options' => [],
                ],
                [
                    'cp_code' => 'IPA-CP-27',
                    'type' => 'uraian',
                    'body' => [
                        'content' => 'Jelaskan mekanisme kerja efek rumah kaca secara alami pada atmosfer bumi dan bagaimana emisi berlebih gas CO2 memicu pemanasan global (global warming)!',
                        'text' => 'Jelaskan mekanisme kerja efek rumah kaca secara alami pada atmosfer bumi dan bagaimana emisi berlebih gas CO2 memicu pemanasan global (global warming)!',
                    ],
                    'weight' => 4.0,
                    'cognitive_level' => 'HOTS',
                    'explanation' => 'Secara alami, gas rumah kaca memerangkap sebagian radiasi inframerah matahari sehingga bumi tetap hangat dan layak huni. Namun peningkatan drastis CO2 memerangkap panas berlebihan di atmosfer yang memicu kenaikan suhu bumi, pencairan es kutub, dan anomali iklim ekstrem.',
                    'options' => [],
                ],
                [
                    'cp_code' => 'IPA-CP-28',
                    'type' => 'uraian',
                    'body' => [
                        'content' => 'Mengapa zat cair di dalam pipa kapiler termometer memuai ketika dicelupkan ke dalam air panas? Jelaskan fenomena ini menggunakan teori kinetik partikel materi!',
                        'text' => 'Mengapa zat cair di dalam pipa kapiler termometer memuai ketika dicelupkan ke dalam air panas? Jelaskan fenomena ini menggunakan teori kinetik partikel materi!',
                    ],
                    'weight' => 4.0,
                    'cognitive_level' => 'HOTS',
                    'explanation' => 'Ketika menerima energi kalor, partikel zat cair menyerap energi kinetik sehingga bergetar dan bergerak lebih cepat. Gerakan yang semakin aktif ini memperlebar jarak antar-partikel zat cair sehingga volumenya mengembang dan naik di pipa kapiler termometer.',
                    'options' => [],
                ],
                [
                    'cp_code' => 'IPA-CP-29',
                    'type' => 'uraian',
                    'body' => [
                        'content' => 'Rancanglah sebuah percobaan sains sederhana (seperti uji Ingenhousz pada tanaman Hydrilla) untuk membuktikan bahwa proses fotosintesis menghasilkan gas oksigen!',
                        'text' => 'Rancanglah sebuah percobaan sains sederhana (seperti uji Ingenhousz pada tanaman Hydrilla) untuk membuktikan bahwa proses fotosintesis menghasilkan gas oksigen!',
                    ],
                    'weight' => 4.0,
                    'cognitive_level' => 'HOTS',
                    'explanation' => 'Letakkan tanaman air Hydrilla di dalam corong kaca terbalik pada beker gelas berisi air, lalu letakkan di tempat yang terkena cahaya matahari langsung. Amati gelembung udara yang terkumpul di ujung tabung reaksi, lalu uji menggunakan lidi berpijar yang akan menyala lebih terang membuktikan adanya gas oksigen.',
                    'options' => [],
                ],
                [
                    'cp_code' => 'IPA-CP-30',
                    'type' => 'uraian',
                    'body' => [
                        'content' => 'Bandingkan perbedaan struktural dan fungsional antara sel hewan dan sel tumbuhan berkaitan dengan keberadaan dinding sel, vakuola, dan kloroplas!',
                        'text' => 'Bandingkan perbedaan struktural dan fungsional antara sel hewan dan sel tumbuhan berkaitan dengan keberadaan dinding sel, vakuola, dan kloroplas!',
                    ],
                    'weight' => 4.0,
                    'cognitive_level' => 'HOTS',
                    'explanation' => 'Sel tumbuhan memiliki dinding sel selulosa kaku (memberi bentuk tetap), kloroplas berisi klorofil untuk fotosintesis, dan vakuola berukuran besar sentral. Sel hewan tidak memiliki dinding sel dan kloroplas sehingga bentuknya fleksibel, serta hanya memiliki vakuola kecil atau tidak ada.',
                    'options' => [],
                ],
            ];

            $savedMtkQuestions = $seedQuestionsForBank($bankMtk, $mtkQuestions);
            $savedInfQuestions = $seedQuestionsForBank($bankInf, $infQuestions);
            $savedIpaQuestions = $seedQuestionsForBank($bankIpa, $ipaQuestions);

            // 12. Seeding 5 EXAMS (Ulangan Harian, Mingguan, Bulanan, UTS, UAS)
            // Semua ujian berstatus 'berlangsung', rentang waktu aktif (start_at <= now, end_at >= now + 7 hari),
            // memiliki ExamRoom dengan Token yang valid, dan seluruh siswa kelas 7-A terdaftar sebagai ExamParticipant.
            $examsConfig = [
                // 1. ULANGAN HARIAN (UH)
                [
                    'title' => 'Ulangan Harian 1: Aljabar dan Persamaan Linear Satu Variabel',
                    'subject' => $subjectModels['MTK-7'],
                    'creator' => $userGuruMtk,
                    'type' => 'harian',
                    'duration' => 45,
                    'token' => 'UH2026',
                    'room' => $room7A,
                    'questions' => $savedMtkQuestions,
                    'passing_grade' => 75.0,
                ],
                // 2. ULANGAN MINGGUAN (KUIS)
                [
                    'title' => 'Kuis Mingguan 1: Logika Pemrograman & Algoritma Dasar',
                    'subject' => $subjectModels['INF-7'],
                    'creator' => $userWalas7a,
                    'type' => 'kuis',
                    'duration' => 30,
                    'token' => 'MINGGU1',
                    'room' => $roomLabKom,
                    'questions' => $savedInfQuestions,
                    'passing_grade' => 75.0,
                ],
                // 3. ULANGAN BULANAN (FORMATIF BULANAN)
                [
                    'title' => 'Asesmen Bulanan 1: Objek IPA, Pengukuran & Ekosistem Terpadu',
                    'subject' => $subjectModels['IPA-7'],
                    'creator' => $userGuruIpa,
                    'type' => 'harian',
                    'duration' => 60,
                    'token' => 'BULAN1',
                    'room' => $roomLabIpa,
                    'questions' => $savedIpaQuestions,
                    'passing_grade' => 75.0,
                ],
                // 4. UTS (SUMATIF TENGAH SEMESTER - STS)
                [
                    'title' => 'Sumatif Tengah Semester (STS) Gasal: Matematika & Penalaran Numerasi',
                    'subject' => $subjectModels['MTK-7'],
                    'creator' => $userGuruMtk,
                    'type' => 'STS',
                    'duration' => 90,
                    'token' => 'UTS2026',
                    'room' => $room7A,
                    'questions' => $savedMtkQuestions,
                    'passing_grade' => 75.0,
                ],
                // 5. UAS (SUMATIF AKHIR SEMESTER - SAS)
                [
                    'title' => 'Sumatif Akhir Semester (SAS) Gasal: Informatika & Literasi Digital',
                    'subject' => $subjectModels['INF-7'],
                    'creator' => $userWalas7a,
                    'type' => 'SAS',
                    'duration' => 90,
                    'token' => 'UAS2026',
                    'room' => $roomLabKom,
                    'questions' => $savedInfQuestions,
                    'passing_grade' => 75.0,
                ],
            ];

            foreach ($examsConfig as $cfg) {
                $exam = Exam::updateOrCreate(
                    [
                        'title' => $cfg['title'],
                    ],
                    [
                        'school_id' => $schoolId,
                        'subject_id' => $cfg['subject']->id,
                        'created_by' => $cfg['creator']->id,
                        'exam_type' => $cfg['type'],
                        'duration_min' => $cfg['duration'],
                        'start_at' => Carbon::now()->subHours(2),
                        'end_at' => Carbon::now()->addDays(7),
                        'passing_grade' => $cfg['passing_grade'],
                        'shuffle_questions' => true,
                        'shuffle_options' => true,
                        'package_mode' => 'tunggal',
                        'show_result_mode' => 'setelah_selesai',
                        'status' => Exam::STATUS_IN_PROGRESS, // 'berlangsung'
                    ]
                );

                // Assign Questions
                ExamQuestion::where('exam_id', $exam->id)
                    ->whereNotIn('question_id', collect($cfg['questions'])->pluck('id'))
                    ->delete();

                $order = 1;
                foreach ($cfg['questions'] as $q) {
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

                // Assign Room & Token
                $examRoom = ExamRoom::updateOrCreate(
                    [
                        'exam_id' => $exam->id,
                        'session_no' => 1,
                    ],
                    [
                        'room_id' => $cfg['room']->id,
                        'proctor_id' => $cfg['creator']->id,
                        'token' => $cfg['token'],
                        'token_expires_at' => Carbon::now()->addDays(7),
                    ]
                );

                // Enroll All Students of Class 7-A
                foreach ($students as $std) {
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

            $this->command?->info('AcademicEvaluationSeeder successfully executed: 5 Exams (UH, Mingguan, Bulanan, UTS, UAS), 4 Modul Ajar, 4 Tugas KBM, 3 Bank Soal, and 10 Peserta Ujian terverifikasi.');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
