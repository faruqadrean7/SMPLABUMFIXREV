<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\GradeLevel;
use App\Models\ProgramMaterial;
use App\Models\ProgramTarget;
use App\Models\ProgramTask;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentProgramProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StudentProgramProfileSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $studentRole = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

            $academicYear = AcademicYear::where('is_active', true)->first()
                ?? AcademicYear::firstOrCreate(
                    ['name' => '2024/2025'],
                    ['start_date' => '2024-07-15', 'end_date' => '2025-06-20', 'is_active' => true]
                );

            $gradeLevel = GradeLevel::where('name', 'like', '%VII%')->first()
                ?? GradeLevel::firstOrCreate(
                    ['name' => 'Kelas VII'],
                    ['sort_order' => 1]
                );

            $programs = [
                [
                    'username' => 'siswa.reguler',
                    'email' => 'siswa.reguler@labschool.sch.id',
                    'name' => 'Bintang Pratama',
                    'nis' => '26101',
                    'nisn' => '0099002101',
                    'nik' => '3573010101990101',
                    'program_type' => 'reguler',
                    'class_name' => 'VII-A Reguler',
                    'meta_data' => [
                        'kktp_summary' => [
                            'total_subjects' => 11,
                            'tuntas_count' => 10,
                            'remedial_count' => 1,
                            'average_score' => 86.4,
                        ],
                        'p5_project' => [
                            'title' => 'Gaya Hidup Berkelanjutan: Pengolahan Sampah Organik Sekolah',
                            'theme' => 'Kearifan Lokal & Lingkungan',
                            'phase' => 'Aksi Nyata & Pembuatan Produk',
                            'group_name' => 'Kelompok Cendekia 3',
                            'deadline' => '2026-11-20',
                        ],
                        'ekskul' => [
                            ['name' => 'Robotika & Coding', 'day' => 'Kamis', 'time' => '15:00 - 17:00'],
                            ['name' => 'Bina Prestasi OSN IPA', 'day' => 'Sabtu', 'time' => '08:00 - 10:00'],
                        ],
                    ],
                ],
                [
                    'username' => 'siswa.tahfidz',
                    'email' => 'siswa.tahfidz@labschool.sch.id',
                    'name' => 'Muhammad Hafizh Al-Fatih',
                    'nis' => '26102',
                    'nisn' => '0099002102',
                    'nik' => '3573010101990102',
                    'program_type' => 'tahfidz',
                    'class_name' => 'VII-B Tahfidz',
                    'meta_data' => [
                        'target_juz' => 3,
                        'current_juz' => 2,
                        'current_surah' => 'Al-Mulk: 1-30',
                        'last_tasmi_date' => '2026-10-28',
                        'last_tasmi_grade' => 'Mumtaz',
                        'tasmi_evaluator' => 'Ustadz Abdullah, S.Pd.I',
                        'mutabaah_today' => [
                            'sholat_fardhu' => 5,
                            'tilawah_pages' => 4,
                            'dhuha' => true,
                            'tahajud' => false,
                        ],
                    ],
                ],
                [
                    'username' => 'siswa.icp',
                    'email' => 'siswa.icp@labschool.sch.id',
                    'name' => 'Aisyah Valerie Zahra',
                    'nis' => '26103',
                    'nisn' => '0099002103',
                    'nik' => '3573010101990103',
                    'program_type' => 'icp',
                    'class_name' => 'VII-C ICP',
                    'meta_data' => [
                        'cambridge_candidate_id' => 'ID-MAL-2026-042',
                        'checkpoint_readiness' => [
                            'english' => ['score' => 4.8, 'target' => 6.0, 'status' => 'Ready'],
                            'math' => ['score' => 5.2, 'target' => 6.0, 'status' => 'Exceeding'],
                            'science' => ['score' => 4.5, 'target' => 6.0, 'status' => 'Ready'],
                        ],
                        'vocab_of_day' => [
                            'word' => 'Photosynthesis',
                            'phonetic' => '/ˌfəʊtəʊˈsɪnθəsɪs/',
                            'definition' => 'The process by which green plants synthesize nutrients from carbon dioxide and water.',
                            'subject' => 'Biology/Science',
                        ],
                        'upcoming_events' => [
                            ['title' => 'Cambridge Mock Checkpoint', 'date' => '2026-11-15', 'type' => 'exam'],
                            ['title' => 'English Speech & Debate Camp', 'date' => '2026-11-28', 'type' => 'workshop'],
                        ],
                    ],
                ],
                [
                    'username' => 'siswa.inklusi',
                    'email' => 'siswa.inklusi@labschool.sch.id',
                    'name' => 'Rian Ananda Putra',
                    'nis' => '26104',
                    'nisn' => '0099002104',
                    'nik' => '3573010101990104',
                    'program_type' => 'inklusi',
                    'class_name' => 'VII-D Inklusi',
                    'meta_data' => [
                        'gpk' => [
                            'id' => 'gpk-ratna-dewi',
                            'name' => 'Ibu Ratna Dewi, S.Pd (GPK)',
                            'contact_phone' => '081234567890',
                            'room' => 'Ruang Sumber / Inklusi Lab UM',
                        ],
                        'ppi_milestones' => [
                            ['id' => 'm1', 'title' => 'Menyelesaikan modul membaca teks deskriptif', 'is_completed' => true],
                            ['id' => 'm2', 'title' => 'Latihan fokus & motorik halus terarah (15 menit)', 'is_completed' => false],
                            ['id' => 'm3', 'title' => 'Komunikasi aktif dalam sesi kelompok kecil', 'is_completed' => false],
                        ],
                        'daily_mood' => [
                            'mood' => 'happy',
                            'timestamp' => '2026-10-31T07:30:00Z',
                            'note' => 'Semangat belajar dengan ceria',
                        ],
                    ],
                ],
            ];

            foreach ($programs as $p) {
                $user = User::updateOrCreate(
                    ['username' => $p['username']],
                    [
                        'email' => $p['email'],
                        'password_hash' => Hash::make('Password123!'),
                        'status' => 'active',
                        'must_change_password' => false,
                    ]
                );

                if (! $user->roles()->where('role_id', $studentRole->id)->exists()) {
                    $user->roles()->attach($studentRole->id);
                }

                $student = Student::updateOrCreate(
                    ['nis' => $p['nis']],
                    [
                        'user_id' => $user->id,
                        'nisn' => $p['nisn'],
                        'nik_enc' => $p['nik'],
                        'full_name' => $p['name'],
                        'gender' => 'L',
                        'birth_place' => 'Malang',
                        'birth_date' => '2012-05-10',
                        'status' => 'aktif',
                        'entry_date' => '2024-07-15',
                        'program_type' => $p['program_type'],
                    ]
                );

                $class = SchoolClass::firstOrCreate(
                    ['name' => $p['class_name'], 'academic_year_id' => $academicYear->id],
                    [
                        'grade_level_id' => $gradeLevel->id,
                        'capacity' => 30,
                        'program_type' => $p['program_type'],
                    ]
                );

                ClassStudent::firstOrCreate(
                    ['class_id' => $class->id, 'student_id' => $student->id],
                    ['joined_at' => '2024-07-15']
                );

                StudentProgramProfile::updateOrCreate(
                    ['student_id' => $student->id],
                    [
                        'program_type' => $p['program_type'],
                        'meta_data' => $p['meta_data'],
                    ]
                );

                // Seed sample Program Materials, Targets, & Tasks (BE-PROG-01)
                if ($p['program_type'] === 'tahfidz') {
                    ProgramMaterial::firstOrCreate(
                        ['program_type' => 'tahfidz', 'title' => 'Panduan Tajwid Makharijul Huruf & Sifatul Huruf'],
                        [
                            'description' => 'Materi standar pelafalan makharijul huruf untuk setoran Juz 30.',
                            'material_type' => 'tajwid',
                            'target_scope' => 'all',
                        ]
                    );
                    ProgramMaterial::firstOrCreate(
                        ['program_type' => 'tahfidz', 'title' => 'Murottal Juz 30 Syaikh Misyari Rasyid'],
                        [
                            'description' => 'Audio murottal untuk sarana talaqqi dan murojaah mandiri.',
                            'material_type' => 'murottal',
                            'external_url' => 'https://quran.com',
                            'target_scope' => 'all',
                        ]
                    );
                    ProgramTarget::firstOrCreate(
                        ['student_id' => $student->id, 'title' => 'Target Kelulusan Tahfidz: 3 Juz Mutqin'],
                        [
                            'program_type' => 'tahfidz',
                            'domain' => 'tahfidz',
                            'target_value' => '3 Juz',
                            'current_value' => '1.5 Juz',
                            'period' => 'Tahun Ajaran 2024/2025',
                            'status' => 'aktif',
                        ]
                    );
                    ProgramTask::firstOrCreate(
                        ['program_type' => 'tahfidz', 'title' => 'Setoran Mandiri Surah An-Naba Ayat 1-20'],
                        [
                            'instructions' => 'Rekam bacaan dengan kaidah tajwid yang benar dan unggah link rekaman atau catatan murojaah.',
                            'due_at' => now()->addDays(5),
                            'target_scope' => 'all',
                        ]
                    );
                } elseif ($p['program_type'] === 'icp') {
                    ProgramMaterial::firstOrCreate(
                        ['program_type' => 'icp', 'title' => 'Cambridge Lower Secondary Mathematics Study Guide'],
                        [
                            'description' => 'Algebra and fractions comprehensive guide with practice exercises.',
                            'material_type' => 'reading',
                            'target_scope' => 'all',
                        ]
                    );
                    ProgramMaterial::firstOrCreate(
                        ['program_type' => 'icp', 'title' => 'Past Papers Cambridge English Checkpoint 2024'],
                        [
                            'description' => 'Official examination papers for listening and reading comprehension.',
                            'material_type' => 'past_paper',
                            'target_scope' => 'all',
                        ]
                    );
                    ProgramTarget::firstOrCreate(
                        ['student_id' => $student->id, 'title' => 'Target Skor Cambridge Checkpoint: Mathematics 5.0'],
                        [
                            'program_type' => 'icp',
                            'domain' => 'math',
                            'target_value' => '5.0 / 6.0',
                            'current_value' => '4.5',
                            'period' => 'Series May 2025',
                            'status' => 'aktif',
                        ]
                    );
                    ProgramTask::firstOrCreate(
                        ['program_type' => 'icp', 'title' => 'Laboratory Report: Photosynthesis Experiment'],
                        [
                            'instructions' => 'Write a structured laboratory report of approximately 300 words following the scientific method.',
                            'due_at' => now()->addDays(7),
                            'target_scope' => 'all',
                        ]
                    );
                } elseif ($p['program_type'] === 'inklusi') {
                    ProgramMaterial::firstOrCreate(
                        ['program_type' => 'inklusi', 'title' => 'Modul Adaptif: Pengenalan Bahasa Isyarat & Ekspresi Wajah'],
                        [
                            'description' => 'Bahan ajar bergambar untuk mendukung komunikasi interaktif.',
                            'material_type' => 'modul_adaptif',
                            'target_scope' => 'all',
                        ]
                    );
                    ProgramMaterial::firstOrCreate(
                        ['program_type' => 'inklusi', 'title' => 'Panduan Regulasi Sensori & Audio Relaksasi'],
                        [
                            'description' => 'Instruksi teknik pernapasan dan relaksasi saat mengalami overstimulasi.',
                            'material_type' => 'panduan',
                            'target_scope' => 'all',
                        ]
                    );
                    ProgramTarget::firstOrCreate(
                        ['student_id' => $student->id, 'title' => 'Milestone PPI: Kemandirian Membaca & Menulis Terbimbing'],
                        [
                            'program_type' => 'inklusi',
                            'domain' => 'akademik',
                            'target_value' => '100%',
                            'current_value' => '75%',
                            'period' => 'Semester Ganjil 2024/2025',
                            'status' => 'aktif',
                        ]
                    );
                    ProgramTask::firstOrCreate(
                        ['program_type' => 'inklusi', 'title' => 'Jurnal Harian Refleksi Perasaan & Aktivitas Terbimbing'],
                        [
                            'instructions' => 'Ceritakan aktivitas yang paling menyenangkan minggu ini bersama guru pendamping khusus.',
                            'due_at' => now()->addDays(3),
                            'target_scope' => 'all',
                        ]
                    );
                }
            }
        });
    }
}
