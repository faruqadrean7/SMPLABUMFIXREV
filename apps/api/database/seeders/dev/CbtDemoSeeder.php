<?php

namespace Database\Seeders;

use App\Models\ClassStudent;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\ExamQuestion;
use App\Models\ExamRoom;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Role;
use App\Models\Room;
use App\Models\SchoolClass;
use App\Models\SiteSetting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CbtDemoSeeder extends Seeder
{
    /**
     * Canonical School ID default for testing / staging.
     */
    public const DEFAULT_SCHOOL_ID = '00000000-0000-0000-0000-000000000001';

    /**
     * Safety check to ensure seeder only runs in local or staging environments with opt-in flag.
     *
     * @throws \RuntimeException
     */
    public function checkEnvironmentSafety(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('CbtDemoSeeder dilarang keras dijalankan pada lingkungan production!');
        }

        if (! app()->environment('testing')) {
            $optIn = filter_var(env('CBT_DEMO_SEED_ENABLED', env('UAT_SEED_ENABLED', false)), FILTER_VALIDATE_BOOLEAN);
            if (! in_array(app()->environment(), ['staging', 'local'], true) || ! $optIn) {
                throw new \RuntimeException('CbtDemoSeeder hanya boleh dijalankan di lingkungan staging/local dengan opt-in CBT_DEMO_SEED_ENABLED=true atau UAT_SEED_ENABLED=true.');
            }
        }
    }

    /**
     * Seed CBT Demonstration Exam and 20 Questions for siswa.lab.001.
     */
    public function run(): void
    {
        $this->checkEnvironmentSafety();

        $schoolId = SiteSetting::first()?->school_id
            ?? Student::first()?->school_id
            ?? self::DEFAULT_SCHOOL_ID;

        DB::beginTransaction();

        try {
            // 1. Roles & Proctor/Teacher User
            $teacherRole = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
            $studentRole = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

            $proctorUser = User::where('username', 'guru.mtk.staging')->first()
                ?? User::where('username', 'admin_cbt')->first()
                ?? User::firstOrCreate(
                    ['username' => 'guru.cbt.demo'],
                    [
                        'email' => 'guru.cbt.demo@smp-labum.sch.id',
                        'password_hash' => Hash::make('Password123!'),
                        'status' => 'active',
                        'must_change_password' => false,
                    ]
                );

            if (! $proctorUser->roles()->where('role_id', $teacherRole->id)->exists()) {
                $proctorUser->roles()->attach($teacherRole->id);
            }

            // 2. Resolve Main Student (siswa.lab.001)
            $studentUser = User::where('username', 'siswa.lab.001')->first();
            if (! $studentUser) {
                $studentUser = User::firstOrCreate(
                    ['username' => 'siswa.lab.001'],
                    [
                        'email' => 'siswa.lab.001@smp-labum.sch.id',
                        'password_hash' => Hash::make('Password123!'),
                        'status' => 'active',
                        'must_change_password' => false,
                    ]
                );
                if (! $studentUser->roles()->where('role_id', $studentRole->id)->exists()) {
                    $studentUser->roles()->attach($studentRole->id);
                }
            }

            $student = Student::firstOrCreate(
                ['user_id' => $studentUser->id],
                [
                    'school_id' => $schoolId,
                    'nis' => '26001',
                    'nisn' => '0099001001',
                    'nik_enc' => '3578010101090001',
                    'full_name' => 'Siswa UAT Lab 001',
                    'gender' => 'L',
                    'birth_place' => 'Malang',
                    'birth_date' => '2012-05-15',
                    'religion' => 'Islam',
                    'address' => 'Jl. Simpang Bogor No. 1, Malang',
                    'status' => 'aktif',
                    'entry_date' => '2026-07-01',
                ]
            );

            // 2b. Siswa UAT 002 (untuk pengujian multi-siswa & konkurensi)
            $studentUser2 = User::where('username', 'siswa.lab.002')->first();
            if (! $studentUser2) {
                $studentUser2 = User::firstOrCreate(
                    ['email' => 'siswa.lab.002@smp-labum.sch.id'],
                    [
                        'username' => 'siswa.lab.002',
                        'password_hash' => Hash::make('Password123!'),
                        'status' => 'active',
                        'must_change_password' => false,
                    ]
                );
            }
            if (! $studentUser2->roles()->where('role_id', $studentRole->id)->exists()) {
                $studentUser2->roles()->attach($studentRole->id);
            }

            $student2 = Student::firstOrCreate(
                ['user_id' => $studentUser2->id],
                [
                    'school_id' => $schoolId,
                    'nis' => '26002',
                    'nisn' => '0099001002',
                    'nik_enc' => '3578010101090002',
                    'full_name' => 'Siswa UAT Lab 002',
                    'gender' => 'P',
                    'birth_place' => 'Malang',
                    'birth_date' => '2012-08-20',
                    'religion' => 'Islam',
                    'address' => 'Jl. Simpang Bogor No. 2, Malang',
                    'status' => 'aktif',
                    'entry_date' => '2026-07-01',
                ]
            );

            // 3. Subject & Room
            $subject = Subject::firstOrCreate(
                ['code' => 'INF-07'],
                [
                    'school_id' => $schoolId,
                    'name' => 'Informatika',
                    'subject_group' => 'wajib',
                ]
            );

            $room = Room::firstOrCreate(
                ['code' => 'LAB-KOMP-01'],
                [
                    'school_id' => $schoolId,
                    'name' => 'Laboratorium Komputer 1',
                    'room_type' => 'laboratorium',
                    'capacity' => 40,
                ]
            );

            // 4. Question Bank
            $questionBank = QuestionBank::firstOrCreate(
                [
                    'name' => 'Bank Soal Asesmen Informatika Kelas 7',
                    'subject_id' => $subject->id,
                ],
                [
                    'school_id' => $schoolId,
                    'owner_id' => $proctorUser->id,
                ]
            );

            // 5. 20 Questions Covering All 6 Types
            $questionsData = [
                // 1-6: Pilihan Ganda (PG)
                [
                    'cp_code' => 'CP-DEMO-01',
                    'type' => 'pg',
                    'body' => ['content' => 'Komponen perangkat keras komputer yang berfungsi sebagai otak pemrosesan data adalah ...', 'text' => 'Komponen perangkat keras komputer yang berfungsi sebagai otak pemrosesan data adalah ...'],
                    'weight' => 5.0,
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Central Processing Unit (CPU)', 'content' => 'Central Processing Unit (CPU)'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Random Access Memory (RAM)', 'content' => 'Random Access Memory (RAM)'], 'is_correct' => false],
                        ['label' => 'C', 'body' => ['text' => 'Hard Disk Drive (HDD)', 'content' => 'Hard Disk Drive (HDD)'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => 'Power Supply Unit (PSU)', 'content' => 'Power Supply Unit (PSU)'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'CP-DEMO-02',
                    'type' => 'pg',
                    'body' => ['content' => 'Perangkat masukan (input device) yang digunakan untuk mendeteksi posisi pointer adalah ...', 'text' => 'Perangkat masukan (input device) yang digunakan untuk mendeteksi posisi pointer adalah ...'],
                    'weight' => 5.0,
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Mouse', 'content' => 'Mouse'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Proyektor', 'content' => 'Proyektor'], 'is_correct' => false],
                        ['label' => 'C', 'body' => ['text' => 'Printer', 'content' => 'Printer'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => 'Speaker', 'content' => 'Speaker'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'CP-DEMO-03',
                    'type' => 'pg',
                    'body' => ['content' => 'Protokol jaringan yang digunakan secara standar untuk mentransfer halaman web terenkripsi adalah ...', 'text' => 'Protokol jaringan yang digunakan secara standar untuk mentransfer halaman web terenkripsi adalah ...'],
                    'weight' => 5.0,
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'HTTPS', 'content' => 'HTTPS'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'FTP', 'content' => 'FTP'], 'is_correct' => false],
                        ['label' => 'C', 'body' => ['text' => 'SMTP', 'content' => 'SMTP'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => 'Telnet', 'content' => 'Telnet'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'CP-DEMO-04',
                    'type' => 'pg',
                    'body' => ['content' => 'Struktur data linier yang menganut prinsip LIFO (Last In First Out) adalah ...', 'text' => 'Struktur data linier yang menganut prinsip LIFO (Last In First Out) adalah ...'],
                    'weight' => 5.0,
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Stack', 'content' => 'Stack'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Queue', 'content' => 'Queue'], 'is_correct' => false],
                        ['label' => 'C', 'body' => ['text' => 'Graph', 'content' => 'Graph'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => 'Tree', 'content' => 'Tree'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'CP-DEMO-05',
                    'type' => 'pg',
                    'body' => ['content' => 'Bilangan biner 1010 dalam sistem desimal bernilai ...', 'text' => 'Bilangan biner 1010 dalam sistem desimal bernilai ...'],
                    'weight' => 5.0,
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => '10', 'content' => '10'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => '8', 'content' => '8'], 'is_correct' => false],
                        ['label' => 'C', 'body' => ['text' => '12', 'content' => '12'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => '14', 'content' => '14'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'CP-DEMO-06',
                    'type' => 'pg',
                    'body' => ['content' => 'Langkah-langkah berurutan dan logis untuk menyelesaikan suatu masalah komputasi disebut ...', 'text' => 'Langkah-langkah berurutan dan logis untuk menyelesaikan suatu masalah komputasi disebut ...'],
                    'weight' => 5.0,
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Algoritma', 'content' => 'Algoritma'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Enkripsi', 'content' => 'Enkripsi'], 'is_correct' => false],
                        ['label' => 'C', 'body' => ['text' => 'Kompilasi', 'content' => 'Kompilasi'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => 'Dekomposisi', 'content' => 'Dekomposisi'], 'is_correct' => false],
                    ],
                ],

                // 7-10: Pilihan Ganda Kompleks (PG Kompleks / Multi-Select)
                [
                    'cp_code' => 'CP-DEMO-07',
                    'type' => 'pg_kompleks',
                    'body' => ['content' => 'Manakah di antara perangkat berikut yang termasuk dalam perangkat keluaran (output devices)? (Pilih beberapa)', 'text' => 'Manakah di antara perangkat berikut yang termasuk dalam perangkat keluaran (output devices)? (Pilih beberapa)'],
                    'weight' => 5.0,
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Monitor', 'content' => 'Monitor'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Printer', 'content' => 'Printer'], 'is_correct' => true],
                        ['label' => 'C', 'body' => ['text' => 'Scanner', 'content' => 'Scanner'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => 'Mikrofon', 'content' => 'Mikrofon'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'CP-DEMO-08',
                    'type' => 'pg_kompleks',
                    'body' => ['content' => 'Pilihlah karakteristik utama dari media penyimpanan Solid State Drive (SSD)! (Pilih beberapa)', 'text' => 'Pilihlah karakteristik utama dari media penyimpanan Solid State Drive (SSD)! (Pilih beberapa)'],
                    'weight' => 5.0,
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Menggunakan chip memori flash non-volatil', 'content' => 'Menggunakan chip memori flash non-volatil'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Kecepatan baca dan tulis lebih tinggi dari HDD', 'content' => 'Kecepatan baca dan tulis lebih tinggi dari HDD'], 'is_correct' => true],
                        ['label' => 'C', 'body' => ['text' => 'Menggunakan piringan magnetik berputar', 'content' => 'Menggunakan piringan magnetik berputar'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => 'Seluruh data otomatis terhapus saat listrik mati', 'content' => 'Seluruh data otomatis terhapus saat listrik mati'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'CP-DEMO-09',
                    'type' => 'pg_kompleks',
                    'body' => ['content' => 'Manakah di antara berikut yang merupakan 4 pilar utama Computational Thinking? (Pilih beberapa)', 'text' => 'Manakah di antara berikut yang merupakan 4 pilar utama Computational Thinking? (Pilih beberapa)'],
                    'weight' => 5.0,
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Dekomposisi', 'content' => 'Dekomposisi'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Pengenalan Pola', 'content' => 'Pengenalan Pola'], 'is_correct' => true],
                        ['label' => 'C', 'body' => ['text' => 'Abstraksi', 'content' => 'Abstraksi'], 'is_correct' => true],
                        ['label' => 'D', 'body' => ['text' => 'Monopoli', 'content' => 'Monopoli'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'CP-DEMO-10',
                    'type' => 'pg_kompleks',
                    'body' => ['content' => 'Pilihlah tindakan etis dan aman dalam beraktivitas di ruang digital! (Pilih beberapa)', 'text' => 'Pilihlah tindakan etis dan aman dalam beraktivitas di ruang digital! (Pilih beberapa)'],
                    'weight' => 5.0,
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Mengaktifkan Autentikasi Dua Faktor (2FA)', 'content' => 'Mengaktifkan Autentikasi Dua Faktor (2FA)'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Menjaga kerahasiaan kata sandi pribadi', 'content' => 'Menjaga kerahasiaan kata sandi pribadi'], 'is_correct' => true],
                        ['label' => 'C', 'body' => ['text' => 'Menggunakan kata sandi tanggal lahir untuk semua akun', 'content' => 'Menggunakan kata sandi tanggal lahir untuk semua akun'], 'is_correct' => false],
                        ['label' => 'D', 'body' => ['text' => 'Mengunduh software bajakan tanpa lisensi', 'content' => 'Mengunduh software bajakan tanpa lisensi'], 'is_correct' => false],
                    ],
                ],

                // 11-13: Benar - Salah (BS)
                [
                    'cp_code' => 'CP-DEMO-11',
                    'type' => 'bs',
                    'body' => ['content' => 'Pernyataan: Memori RAM (Random Access Memory) bersifat volatil, sehingga data di dalamnya hilang saat komputer dimatikan.', 'text' => 'Pernyataan: Memori RAM (Random Access Memory) bersifat volatil, sehingga data di dalamnya hilang saat komputer dimatikan.'],
                    'weight' => 5.0,
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'CP-DEMO-12',
                    'type' => 'bs',
                    'body' => ['content' => 'Pernyataan: Alamat IPv4 menggunakan panjang bit sebesar 128 bit numerik.', 'text' => 'Pernyataan: Alamat IPv4 menggunakan panjang bit sebesar 128 bit numerik.'],
                    'weight' => 5.0,
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => false],
                        ['label' => 'B', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => true],
                    ],
                ],
                [
                    'cp_code' => 'CP-DEMO-13',
                    'type' => 'bs',
                    'body' => ['content' => 'Pernyataan: Sistem operasi Linux didistribusikan di bawah lisensi perangkat lunak sumber terbuka (open source).', 'text' => 'Pernyataan: Sistem operasi Linux didistribusikan di bawah lisensi perangkat lunak sumber terbuka (open source).'],
                    'weight' => 5.0,
                    'options' => [
                        ['label' => 'A', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                        ['label' => 'B', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                    ],
                ],

                // 14-16: Menjodohkan (Matching)
                [
                    'cp_code' => 'CP-DEMO-14',
                    'type' => 'menjodohkan',
                    'body' => ['content' => 'Pasangkanlah perangkat keras berikut dengan kategori fungsinya masing-masing!', 'text' => 'Pasangkanlah perangkat keras berikut dengan kategori fungsinya masing-masing!'],
                    'weight' => 5.0,
                    'options' => [
                        ['label' => 'P1', 'body' => ['text' => 'Keyboard', 'content' => 'Keyboard'], 'is_correct' => false, 'match_key' => 'Input'],
                        ['label' => 'P2', 'body' => ['text' => 'Monitor LCD', 'content' => 'Monitor LCD'], 'is_correct' => false, 'match_key' => 'Output'],
                        ['label' => 'P3', 'body' => ['text' => 'Hard Disk Drive', 'content' => 'Hard Disk Drive'], 'is_correct' => false, 'match_key' => 'Storage'],
                        ['label' => 'P4', 'body' => ['text' => 'Processor', 'content' => 'Processor'], 'is_correct' => false, 'match_key' => 'Processing'],
                    ],
                ],
                [
                    'cp_code' => 'CP-DEMO-15',
                    'type' => 'menjodohkan',
                    'body' => ['content' => 'Pasangkanlah ekstensi file dengan tipe dokumen yang sesuai!', 'text' => 'Pasangkanlah ekstensi file dengan tipe dokumen yang sesuai!'],
                    'weight' => 5.0,
                    'options' => [
                        ['label' => 'P1', 'body' => ['text' => '.docx', 'content' => '.docx'], 'is_correct' => false, 'match_key' => 'Dokumen Teks'],
                        ['label' => 'P2', 'body' => ['text' => '.xlsx', 'content' => '.xlsx'], 'is_correct' => false, 'match_key' => 'Lembar Sebar'],
                        ['label' => 'P3', 'body' => ['text' => '.jpg', 'content' => '.jpg'], 'is_correct' => false, 'match_key' => 'Gambar'],
                        ['label' => 'P4', 'body' => ['text' => '.mp4', 'content' => '.mp4'], 'is_correct' => false, 'match_key' => 'Video'],
                    ],
                ],
                [
                    'cp_code' => 'CP-DEMO-16',
                    'type' => 'menjodohkan',
                    'body' => ['content' => 'Pasangkanlah topologi jaringan komputer dengan ciri utamanya!', 'text' => 'Pasangkanlah topologi jaringan komputer dengan ciri utamanya!'],
                    'weight' => 5.0,
                    'options' => [
                        ['label' => 'P1', 'body' => ['text' => 'Topologi Star', 'content' => 'Topologi Star'], 'is_correct' => false, 'match_key' => 'Hub atau Switch Sentral'],
                        ['label' => 'P2', 'body' => ['text' => 'Topologi Bus', 'content' => 'Topologi Bus'], 'is_correct' => false, 'match_key' => 'Kabel Backbone Tunggal'],
                        ['label' => 'P3', 'body' => ['text' => 'Topologi Ring', 'content' => 'Topologi Ring'], 'is_correct' => false, 'match_key' => 'Sirkuit Lingkaran Tertutup'],
                    ],
                ],

                // 17-18: Isian Singkat
                [
                    'cp_code' => 'CP-DEMO-17',
                    'type' => 'isian',
                    'body' => [
                        'content' => 'Singkatan antarmuka pengguna berbasis grafis dengan elemen jendela, ikon, dan menu adalah ...',
                        'text' => 'Singkatan antarmuka pengguna berbasis grafis dengan elemen jendela, ikon, dan menu adalah ...',
                        'correct_answer' => 'GUI',
                    ],
                    'weight' => 5.0,
                    'options' => [
                        ['label' => 'JAWABAN', 'body' => ['text' => 'GUI', 'content' => 'GUI'], 'is_correct' => true],
                    ],
                ],
                [
                    'cp_code' => 'CP-DEMO-18',
                    'type' => 'isian',
                    'body' => [
                        'content' => 'Satuan standar frekuensi kecepatan kerja clock prosesor modern disingkat ...',
                        'text' => 'Satuan standar frekuensi kecepatan kerja clock prosesor modern disingkat ...',
                        'correct_answer' => 'GHz',
                    ],
                    'weight' => 5.0,
                    'options' => [
                        ['label' => 'JAWABAN', 'body' => ['text' => 'GHz', 'content' => 'GHz'], 'is_correct' => true],
                    ],
                ],

                // 19-20: Uraian / Esai
                [
                    'cp_code' => 'CP-DEMO-19',
                    'type' => 'uraian',
                    'body' => [
                        'content' => 'Jelaskan perbedaan mendasar antara memori utama (RAM) dan penyimpanan sekunder (SSD/HDD) pada sistem komputer!',
                        'text' => 'Jelaskan perbedaan mendasar antara memori utama (RAM) dan penyimpanan sekunder (SSD/HDD) pada sistem komputer!',
                    ],
                    'weight' => 5.0,
                    'explanation' => 'RAM bersifat volatil (sementara) berkecepatan tinggi, sedangkan SSD/HDD bersifat non-volatil (permanen).',
                    'options' => [],
                ],
                [
                    'cp_code' => 'CP-DEMO-20',
                    'type' => 'uraian',
                    'body' => [
                        'content' => 'Sebutkan dan jelaskan secara singkat manfaat berpikir komputasional dalam menyelesaikan masalah sehari-hari!',
                        'text' => 'Sebutkan dan jelaskan secara singkat manfaat berpikir komputasional dalam menyelesaikan masalah sehari-hari!',
                    ],
                    'weight' => 5.0,
                    'explanation' => 'Membantu menyusun solusi terstruktur dengan dekomposisi masalah, pengenalan pola, abstraksi, dan langkah algoritma.',
                    'options' => [],
                ],
            ];

            $savedQuestions = [];
            foreach ($questionsData as $qItem) {
                $question = Question::updateOrCreate(
                    [
                        'bank_id' => $questionBank->id,
                        'cp_code' => $qItem['cp_code'],
                    ],
                    [
                        'question_type' => $qItem['type'],
                        'body' => $qItem['body'],
                        'cognitive_level' => 'MOTS',
                        'difficulty' => 2,
                        'weight' => $qItem['weight'],
                        'explanation' => $qItem['explanation'] ?? null,
                        'status' => 'aktif',
                    ]
                );

                // Sinkronisasi opsi
                $existingOptionLabels = [];
                foreach ($qItem['options'] as $opt) {
                    $existingOptionLabels[] = $opt['label'];
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

                // Hapus opsi lama bila ada perubahan
                QuestionOption::where('question_id', $question->id)
                    ->whereNotIn('label', $existingOptionLabels)
                    ->delete();

                $savedQuestions[] = $question;
            }

            // 6. Exam Aktif (Durasi 30 Menit, Acak Soal & Opsi)
            $exam = Exam::updateOrCreate(
                [
                    'title' => 'Asesmen Harian Informatika 7 - Digital Readiness (Demo)',
                ],
                [
                    'school_id' => $schoolId,
                    'subject_id' => $subject->id,
                    'created_by' => $proctorUser->id,
                    'exam_type' => 'formatif',
                    'duration_min' => 30,
                    'start_at' => Carbon::now()->subMinutes(15),
                    'end_at' => Carbon::now()->addDays(7),
                    'passing_grade' => 75.0,
                    'shuffle_questions' => true,
                    'shuffle_options' => true,
                    'package_mode' => 'tunggal',
                    'show_result_mode' => 'setelah_selesai',
                    'status' => Exam::STATUS_IN_PROGRESS,
                ]
            );

            // Hubungkan butir soal ke ujian
            $sortOrder = 1;
            foreach ($savedQuestions as $q) {
                ExamQuestion::updateOrCreate(
                    [
                        'exam_id' => $exam->id,
                        'question_id' => $q->id,
                    ],
                    [
                        'sort_order' => $sortOrder++,
                        'weight_override' => $q->weight,
                    ]
                );
            }

            // 7. Exam Room + Token DEMO30
            $examRoom = ExamRoom::updateOrCreate(
                [
                    'exam_id' => $exam->id,
                    'token' => 'DEMO30',
                ],
                [
                    'room_id' => $room->id,
                    'proctor_id' => $proctorUser->id,
                    'session_no' => 1,
                    'token_expires_at' => Carbon::now()->addDays(7),
                ]
            );

            // 8. Daftarkan siswa.lab.001, siswa.lab.002, dan seluruh siswa di kelas 7-A
            $targetStudentIds = [$student->id, $student2->id];

            $class7A = SchoolClass::where('name', '7-A')->first();
            if ($class7A) {
                $classStudentIds = ClassStudent::where('class_id', $class7A->id)->pluck('student_id')->all();
                $targetStudentIds = array_unique(array_merge($targetStudentIds, $classStudentIds));
            }

            foreach ($targetStudentIds as $targetStudentId) {
                ExamParticipant::updateOrCreate(
                    [
                        'exam_id' => $exam->id,
                        'student_id' => $targetStudentId,
                    ],
                    [
                        'exam_room_id' => $examRoom->id,
                    ]
                );
            }

            DB::commit();

            $this->command?->info("CBT_DEMO_SEEDED: Exam ID={$exam->id}, Token=DEMO30, Duration=30m, Questions=20, Room={$room->name}");
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
