<?php

namespace Database\Seeders;

use App\Models\GradeLevel;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\School;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder untuk 9 Bank Soal IPA (Ilmu Pengetahuan Alam).
 *
 * Terdiri dari 3 Tingkat (Kelas 7, Kelas 8, Kelas 9).
 * Setiap tingkat memiliki 3 kode soal / jenis bank soal:
 * - Kelas 7: IPA-7-A (Biologi & Ekologi), IPA-7-B (Fisika Dasar), IPA-7-C (Bumi & Tata Surya)
 * - Kelas 8: IPA-8-A (Fisiologi Tubuh), IPA-8-B (Mekanika & Energi), IPA-8-C (Kimia Terapan)
 * - Kelas 9: IPA-9-A (Genetika & Bioteknologi), IPA-9-B (Listrik & Magnet), IPA-9-C (Lingkungan & Teknologi)
 *
 * Setiap bank soal memuat 5 butir soal komprehensif (PG, PG Kompleks, Benar/Salah, Isian, Uraian)
 * beserta pembahasan ilmiah detail.
 *
 * Jalankan: php artisan db:seed --class=IpaQuestionBankSeeder
 */
class IpaQuestionBankSeeder extends Seeder
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

            // 1. Pastikan Mapel IPA tersedia
            $subject = Subject::where('code', 'IPA')->first()
                ?? Subject::where('code', 'LIKE', '%IPA%')->first()
                ?? Subject::firstOrCreate(
                    ['code' => 'IPA'],
                    [
                        'school_id' => $schoolId,
                        'name' => 'Ilmu Pengetahuan Alam',
                        'subject_group' => 'umum',
                    ]
                );

            // 2. Dapatkan Tingkat 7, 8, dan 9
            $levels = [
                7 => GradeLevel::where('school_id', $schoolId)->where(fn ($q) => $q->where('sort_order', 7)->orWhere('name', 'LIKE', '%7%')->orWhere('name', 'LIKE', '%VII%'))->first()
                    ?? GradeLevel::firstOrCreate(['school_id' => $schoolId, 'sort_order' => 7], ['name' => 'Kelas VII']),
                8 => GradeLevel::where('school_id', $schoolId)->where(fn ($q) => $q->where('sort_order', 8)->orWhere('name', 'LIKE', '%8%')->orWhere('name', 'LIKE', '%VIII%'))->first()
                    ?? GradeLevel::firstOrCreate(['school_id' => $schoolId, 'sort_order' => 8], ['name' => 'Kelas VIII']),
                9 => GradeLevel::where('school_id', $schoolId)->where(fn ($q) => $q->where('sort_order', 9)->orWhere('name', 'LIKE', '%9%')->orWhere('name', 'LIKE', '%IX%'))->first()
                    ?? GradeLevel::firstOrCreate(['school_id' => $schoolId, 'sort_order' => 9], ['name' => 'Kelas IX']),
            ];

            // 3. Tentukan Guru Pemilik Bank Soal (Guru Mapel IPA)
            $ipaTeacher = Teacher::where('school_id', $schoolId)->where('position', 'LIKE', '%IPA%')->first();
            $ownerUser = $ipaTeacher?->user
                ?? User::where('username', 'guru04')->first()
                ?? User::where('username', 'superadmin')->first()
                ?? User::whereHas('roles', fn ($q) => $q->whereIn('code', ['teacher', 'super_admin']))->first()
                ?? User::first();

            if (! $ownerUser) {
                // Buat user fallback jika database dalam keadaan bersih (fresh/test)
                $ownerUser = User::firstOrCreate(
                    ['username' => 'guru_ipa'],
                    [
                        'school_id' => $schoolId,
                        'email' => 'guru.ipa@labschool.sch.id',
                        'password_hash' => '$2y$12$eA8b..placeholderPasswordHashForSeederTestDummy1234567890123456',
                        'status' => 'active',
                        'must_change_password' => false,
                    ]
                );
            }

            // 4. Definisi 9 Bank Soal & Soal-soalnya (3 bank per tingkat)
            $banksData = $this->getBanksData($subject, $levels, $ownerUser, $schoolId);

            $totalQuestions = 0;
            foreach ($banksData as $bankConfig) {
                $bank = QuestionBank::updateOrCreate(
                    [
                        'school_id' => $schoolId,
                        'name' => $bankConfig['name'],
                    ],
                    [
                        'subject_id' => $bankConfig['subject_id'],
                        'grade_level_id' => $bankConfig['grade_level_id'],
                        'owner_id' => $bankConfig['owner_id'],
                    ]
                );

                foreach ($bankConfig['questions'] as $qData) {
                    $question = Question::updateOrCreate(
                        [
                            'bank_id' => $bank->id,
                            'cp_code' => $qData['cp_code'],
                        ],
                        [
                            'question_type' => $qData['question_type'],
                            'body' => $qData['body'],
                            'cognitive_level' => $qData['cognitive_level'],
                            'difficulty' => $qData['difficulty'],
                            'weight' => $qData['weight'],
                            'explanation' => $qData['explanation'],
                            'version' => 1,
                            'status' => 'aktif',
                        ]
                    );

                    // Sinkronisasi opsi jawaban
                    $question->options()->delete();
                    foreach ($qData['options'] as $opt) {
                        QuestionOption::create([
                            'question_id' => $question->id,
                            'label' => $opt['label'],
                            'body' => $opt['body'],
                            'is_correct' => $opt['is_correct'] ?? false,
                            'match_key' => $opt['match_key'] ?? null,
                        ]);
                    }

                    $totalQuestions++;
                }
            }

            $this->command?->info("Berhasil seeding 9 Bank Soal IPA ({$totalQuestions} butir soal) untuk Tingkat 7, 8, dan 9.");
        });
    }

    /**
     * Data konfigurasi 9 Bank Soal IPA beserta butir soal & pembahasannya.
     */
    private function getBanksData(Subject $subject, array $levels, User $owner, string $schoolId): array
    {
        return [
            // =========================================================================
            // TINGKAT 7 (KELAS VII)
            // =========================================================================

            // 1. Bank Soal IPA 7 - Kode: IPA-7-A
            [
                'name' => 'Bank Soal IPA 7 - Biologi & Ekologi: Sel, Keanekaragaman Hayati & Ekosistem (Kode: IPA-7-A)',
                'subject_id' => $subject->id,
                'grade_level_id' => $levels[7]->id,
                'owner_id' => $owner->id,
                'questions' => [
                    [
                        'cp_code' => 'IPA7-A-01-PG',
                        'question_type' => 'pg',
                        'cognitive_level' => 'LOTS',
                        'difficulty' => 2,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Bagian sel tumbuhan yang berfungsi mempertahankan bentuk sel dan melindungi organel di dalamnya, namun tidak dimiliki oleh sel hewan adalah ...',
                            'content' => 'Bagian sel tumbuhan yang berfungsi mempertahankan bentuk sel dan melindungi organel di dalamnya, namun tidak dimiliki oleh sel hewan adalah ...',
                        ],
                        'explanation' => 'Dinding sel tersusun atas selulosa yang kuat dan kaku, hanya dimiliki oleh sel tumbuhan untuk mempertahankan bentuk sel dan melindungi isi sel.',
                        'options' => [
                            ['label' => 'A', 'body' => ['text' => 'Dinding Sel', 'content' => 'Dinding Sel'], 'is_correct' => true],
                            ['label' => 'B', 'body' => ['text' => 'Membran Plasma', 'content' => 'Membran Plasma'], 'is_correct' => false],
                            ['label' => 'C', 'body' => ['text' => 'Mitokondria', 'content' => 'Mitokondria'], 'is_correct' => false],
                            ['label' => 'D', 'body' => ['text' => 'Ribosom', 'content' => 'Ribosom'], 'is_correct' => false],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA7-A-02-PGK',
                        'question_type' => 'pg_kompleks',
                        'cognitive_level' => 'MOTS',
                        'difficulty' => 3,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Manakah dari pernyataan berikut yang merupakan peran komponen biotik dalam interaksi ekosistem sawah? (Pilih semua yang benar)',
                            'content' => 'Manakah dari pernyataan berikut yang merupakan peran komponen biotik dalam interaksi ekosistem sawah? (Pilih semua yang benar)',
                        ],
                        'explanation' => 'Komponen biotik adalah komponen hidup (produsen padi, konsumen belalang/burung, dekomposer bakteri). Air dan tanah adalah komponen abiotik.',
                        'options' => [
                            ['label' => 'A', 'body' => ['text' => 'Tanaman padi berperan sebagai produsen utama yang mengonversi energi surya', 'content' => 'Tanaman padi berperan sebagai produsen utama yang mengonversi energi surya'], 'is_correct' => true],
                            ['label' => 'B', 'body' => ['text' => 'Belalang dan burung pipit bertindak sebagai konsumen primer pemakan tumbuhan', 'content' => 'Belalang dan burung pipit bertindak sebagai konsumen primer pemakan tumbuhan'], 'is_correct' => true],
                            ['label' => 'C', 'body' => ['text' => 'Bakteri pembusuk dan jamur berperan sebagai dekomposer pengurai materi organik', 'content' => 'Bakteri pembusuk dan jamur berperan sebagai dekomposer pengurai materi organik'], 'is_correct' => true],
                            ['label' => 'D', 'body' => ['text' => 'Air dan lumpur bertindak sebagai komponen biotik penyedia mineral hara', 'content' => 'Air dan lumpur bertindak sebagai komponen biotik penyedia mineral hara'], 'is_correct' => false],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA7-A-03-BS',
                        'question_type' => 'bs',
                        'cognitive_level' => 'MOTS',
                        'difficulty' => 3,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Pernyataan Simbiosis: Interaksi antara tanaman anggrek yang menempel pada pohon mangga merupakan contoh simbiosis mutualisme karena anggrek menyerap nutrisi langsung dari pembuluh tapis pohon mangga.',
                            'content' => 'Pernyataan Simbiosis: Interaksi antara tanaman anggrek yang menempel pada pohon mangga merupakan contoh simbiosis mutualisme karena anggrek menyerap nutrisi langsung dari pembuluh tapis pohon mangga.',
                        ],
                        'explanation' => 'Salah. Hubungan anggrek dan pohon mangga adalah simbiosis komensalisme. Anggrek hanya menempel (epifit) untuk memperoleh cahaya dan tidak merugikan pohon inang.',
                        'options' => [
                            ['label' => 'BENAR', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => false],
                            ['label' => 'SALAH', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => true],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA7-A-04-ISIAN',
                        'question_type' => 'isian',
                        'cognitive_level' => 'LOTS',
                        'difficulty' => 2,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Organel sel yang dijuluki sebagai "the powerhouse of cell" karena menjadi tempat respirasi seluler dan pembentukan molekul energi ATP adalah ...',
                            'content' => 'Organel sel yang dijuluki sebagai "the powerhouse of cell" karena menjadi tempat respirasi seluler dan pembentukan molekul energi ATP adalah ...',
                            'correct_answer' => 'Mitokondria',
                        ],
                        'explanation' => 'Mitokondria adalah organel bermembran ganda tempat sintesis energi ATP melalui siklus Krebs dan fosforilasi oksidatif.',
                        'options' => [
                            ['label' => 'JAWABAN', 'body' => ['text' => 'Mitokondria', 'content' => 'Mitokondria'], 'is_correct' => true],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA7-A-05-URAIAN',
                        'question_type' => 'uraian',
                        'cognitive_level' => 'HOTS',
                        'difficulty' => 4,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Di suatu perairan danau terjadi fenomena ledakan populasi eceng gondok (eutrofikasi) akibat akumulasi limbah pupuk pertanian. Jelaskan dampak berantai fenomena tersebut terhadap ekosistem perairan dan organisme air di danau tersebut!',
                            'content' => 'Di suatu perairan danau terjadi fenomena ledakan populasi eceng gondok (eutrofikasi) akibat akumulasi limbah pupuk pertanian. Jelaskan dampak berantai fenomena tersebut terhadap ekosistem perairan dan organisme air di danau tersebut!',
                        ],
                        'explanation' => 'Akumulasi fosfat dan nitrat memicu blooming eceng gondok yang menutupi permukaan air, menghambat penetrasi sinar matahari sehingga tumbuhan dasar mati. Pembusukan biomassa oleh bakteri menguras oksigen terlarut (Dissolved Oxygen turun drastis), menyebabkan kondisi anoksia yang memicu kematian massal ikan dan biota air.',
                        'options' => [],
                    ],
                ],
            ],

            // 2. Bank Soal IPA 7 - Kode: IPA-7-B
            [
                'name' => 'Bank Soal IPA 7 - Fisika Dasar: Pengukuran, Zat & Kalor (Kode: IPA-7-B)',
                'subject_id' => $subject->id,
                'grade_level_id' => $levels[7]->id,
                'owner_id' => $owner->id,
                'questions' => [
                    [
                        'cp_code' => 'IPA7-B-01-PG',
                        'question_type' => 'pg',
                        'cognitive_level' => 'MOTS',
                        'difficulty' => 3,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Sebuah termometer menunjukkan suhu air sebesar 50°C. Berapakah nilai suhu air tersebut jika diukur menggunakan termometer skala Reamur dan Fahrenheit berturut-turut?',
                            'content' => 'Sebuah termometer menunjukkan suhu air sebesar 50°C. Berapakah nilai suhu air tersebut jika diukur menggunakan termometer skala Reamur dan Fahrenheit berturut-turut?',
                        ],
                        'explanation' => 'R = (4/5) x C = (4/5) x 50 = 40°R. F = (9/5) x C + 32 = (9/5) x 50 + 32 = 90 + 32 = 122°F.',
                        'options' => [
                            ['label' => 'A', 'body' => ['text' => '40°R dan 122°F', 'content' => '40°R dan 122°F'], 'is_correct' => true],
                            ['label' => 'B', 'body' => ['text' => '40°R dan 90°F', 'content' => '40°R dan 90°F'], 'is_correct' => false],
                            ['label' => 'C', 'body' => ['text' => '62,5°R dan 122°F', 'content' => '62,5°R dan 122°F'], 'is_correct' => false],
                            ['label' => 'D', 'body' => ['text' => '50°R dan 100°F', 'content' => '50°R dan 100°F'], 'is_correct' => false],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA7-B-02-PGK',
                        'question_type' => 'pg_kompleks',
                        'cognitive_level' => 'LOTS',
                        'difficulty' => 2,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Manakah dari kelompok besaran berikut yang seluruh anggotanya termasuk ke dalam Besaran Pokok Sistem Internasional (SI)? (Pilih semua yang benar)',
                            'content' => 'Manakah dari kelompok besaran berikut yang seluruh anggotanya termasuk ke dalam Besaran Pokok Sistem Internasional (SI)? (Pilih semua yang benar)',
                        ],
                        'explanation' => 'Tujuh besaran pokok SI: Panjang, Massa, Waktu, Suhu, Kuat Arus Listrik, Intensitas Cahaya, dan Jumlah Zat. Kecepatan dan gaya merupakan besaran turunan.',
                        'options' => [
                            ['label' => 'A', 'body' => ['text' => 'Massa, Panjang, Waktu', 'content' => 'Massa, Panjang, Waktu'], 'is_correct' => true],
                            ['label' => 'B', 'body' => ['text' => 'Suhu, Kuat Arus Listrik, Intensitas Cahaya', 'content' => 'Suhu, Kuat Arus Listrik, Intensitas Cahaya'], 'is_correct' => true],
                            ['label' => 'C', 'body' => ['text' => 'Kecepatan, Percepatan, Gaya', 'content' => 'Kecepatan, Percepatan, Gaya'], 'is_correct' => false],
                            ['label' => 'D', 'body' => ['text' => 'Jumlah Zat, Waktu, Suhu Termodinamika', 'content' => 'Jumlah Zat, Waktu, Suhu Termodinamika'], 'is_correct' => true],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA7-B-03-BS',
                        'question_type' => 'bs',
                        'cognitive_level' => 'LOTS',
                        'difficulty' => 2,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Pernyataan Asas Black: Pada pencampuran dua zat dalam bejana terisolasi termal, jumlah kalor yang dilepaskan oleh zat bersuhu lebih tinggi persis sama dengan jumlah kalor yang diserap oleh zat bersuhu lebih rendah (Qlepas = Qterima).',
                            'content' => 'Pernyataan Asas Black: Pada pencampuran dua zat dalam bejana terisolasi termal, jumlah kalor yang dilepaskan oleh zat bersuhu lebih tinggi persis sama dengan jumlah kalor yang diserap oleh zat bersuhu lebih rendah (Qlepas = Qterima).',
                        ],
                        'explanation' => 'Benar. Asas Black adalah aplikasi hukum kekekalan energi pada pertukaran kalor: Q_lepas = Q_terima.',
                        'options' => [
                            ['label' => 'BENAR', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                            ['label' => 'SALAH', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA7-B-04-ISIAN',
                        'question_type' => 'isian',
                        'cognitive_level' => 'LOTS',
                        'difficulty' => 2,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Perubahan wujud zat dari wujud gas langsung menjadi padat tanpa melalui fase cair terlebih dahulu disebut ...',
                            'content' => 'Perubahan wujud zat dari wujud gas langsung menjadi padat tanpa melalui fase cair terlebih dahulu disebut ...',
                            'correct_answer' => 'Mengkristal',
                        ],
                        'explanation' => 'Perubahan wujud gas menjadi padat disebut mengkristal atau deposisi (misal pembentukan kristal es/salju dari uap air dingin di udara).',
                        'options' => [
                            ['label' => 'JAWABAN', 'body' => ['text' => 'Mengkristal', 'content' => 'Mengkristal'], 'is_correct' => true],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA7-B-05-URAIAN',
                        'question_type' => 'uraian',
                        'cognitive_level' => 'MOTS',
                        'difficulty' => 3,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Air bermassa 2 kg bersuhu 30°C dipanaskan hingga suhunya mencapai 80°C. Jika kalor jenis air c = 4.200 J/kg°C, hitunglah kalor total (Q) yang diserap air tersebut dan tuliskan langkah perhitungannya!',
                            'content' => 'Air bermassa 2 kg bersuhu 30°C dipanaskan hingga suhunya mencapai 80°C. Jika kalor jenis air c = 4.200 J/kg°C, hitunglah kalor total (Q) yang diserap air tersebut dan tuliskan langkah perhitungannya!',
                        ],
                        'explanation' => 'Diketahui: m = 2 kg, c = 4.200 J/kg°C, delta T = 80°C - 30°C = 50°C. Rumus: Q = m x c x delta T = 2 kg x 4.200 J/kg°C x 50°C = 420.000 Joule = 420 kJ.',
                        'options' => [],
                    ],
                ],
            ],

            // 3. Bank Soal IPA 7 - Kode: IPA-7-C
            [
                'name' => 'Bank Soal IPA 7 - Bumi & Tata Surya: Litosfer, Atmosfer & Gerak Planet (Kode: IPA-7-C)',
                'subject_id' => $subject->id,
                'grade_level_id' => $levels[7]->id,
                'owner_id' => $owner->id,
                'questions' => [
                    [
                        'cp_code' => 'IPA7-C-01-PG',
                        'question_type' => 'pg',
                        'cognitive_level' => 'LOTS',
                        'difficulty' => 2,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Lapisan atmosfer yang berada paling dekat dengan permukaan bumi dan menjadi tempat berlangsungnya fenomena cuaca seperti awan, hujan, dan petir adalah ...',
                            'content' => 'Lapisan atmosfer yang berada paling dekat dengan permukaan bumi dan menjadi tempat berlangsungnya fenomena cuaca seperti awan, hujan, dan petir adalah ...',
                        ],
                        'explanation' => 'Troposfer adalah lapisan atmosfer terbawah (0-12 km) tempat terjadinya dinamika cuaca dan iklim.',
                        'options' => [
                            ['label' => 'A', 'body' => ['text' => 'Troposfer', 'content' => 'Troposfer'], 'is_correct' => true],
                            ['label' => 'B', 'body' => ['text' => 'Stratosfer', 'content' => 'Stratosfer'], 'is_correct' => false],
                            ['label' => 'C', 'body' => ['text' => 'Mesosfer', 'content' => 'Mesosfer'], 'is_correct' => false],
                            ['label' => 'D', 'body' => ['text' => 'Termosfer', 'content' => 'Termosfer'], 'is_correct' => false],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA7-C-02-PGK',
                        'question_type' => 'pg_kompleks',
                        'cognitive_level' => 'MOTS',
                        'difficulty' => 3,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Berikut ini merupakan akibat langsung dari peristiwa gerak rotasi Bumi (perputaran bumi pada porosnya): (Pilih semua yang benar)',
                            'content' => 'Berikut ini merupakan akibat langsung dari peristiwa gerak rotasi Bumi (perputaran bumi pada porosnya): (Pilih semua yang benar)',
                        ],
                        'explanation' => 'Rotasi bumi menyebabkan pergantian siang-malam, gerak semu harian benda langit, dan perbedaan zona waktu. Pergantian musim diakibatkan oleh revolusi bumi mengelilingi matahari.',
                        'options' => [
                            ['label' => 'A', 'body' => ['text' => 'Terjadinya pergantian siang dan malam', 'content' => 'Terjadinya pergantian siang dan malam'], 'is_correct' => true],
                            ['label' => 'B', 'body' => ['text' => 'Gerak semu harian matahari dari timur ke barat', 'content' => 'Gerak semu harian matahari dari timur ke barat'], 'is_correct' => true],
                            ['label' => 'C', 'body' => ['text' => 'Perbedaan dan pembagian zona waktu di bumi', 'content' => 'Perbedaan dan pembagian zona waktu di bumi'], 'is_correct' => true],
                            ['label' => 'D', 'body' => ['text' => 'Pergantian musim di belahan bumi utara dan selatan', 'content' => 'Pergantian musim di belahan bumi utara dan selatan'], 'is_correct' => false],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA7-C-03-BS',
                        'question_type' => 'bs',
                        'cognitive_level' => 'MOTS',
                        'difficulty' => 3,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Pernyataan Gerhana: Gerhana Bulan Total terjadi ketika posisi Matahari, Bulan, dan Bumi berada dalam satu garis lurus dengan posisi Bulan berada persis di antara Matahari dan Bumi.',
                            'content' => 'Pernyataan Gerhana: Gerhana Bulan Total terjadi ketika posisi Matahari, Bulan, dan Bumi berada dalam satu garis lurus dengan posisi Bulan berada persis di antara Matahari dan Bumi.',
                        ],
                        'explanation' => 'Salah. Pada gerhana bulan, posisi Bumi berada di antara Matahari dan Bulan sehingga bayangan umbra bumi menutupi bulan. Posisi bulan di tengah adalah gerhana matahari.',
                        'options' => [
                            ['label' => 'BENAR', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => false],
                            ['label' => 'SALAH', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => true],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA7-C-04-ISIAN',
                        'question_type' => 'isian',
                        'cognitive_level' => 'LOTS',
                        'difficulty' => 2,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Lapisan gas pada stratosfer bumi yang tersusun dari molekul O3 dan berfungsi menyerap radiasi ultraviolet berbahaya dari matahari adalah lapisan ...',
                            'content' => 'Lapisan gas pada stratosfer bumi yang tersusun dari molekul O3 dan berfungsi menyerap radiasi ultraviolet berbahaya dari matahari adalah lapisan ...',
                            'correct_answer' => 'Ozon',
                        ],
                        'explanation' => 'Lapisan ozon (O3) di stratosfer menyerap sebagian besar sinar radiasi ultraviolet (UV-B) yang berpotensi memicu kanker kulit.',
                        'options' => [
                            ['label' => 'JAWABAN', 'body' => ['text' => 'Ozon', 'content' => 'Ozon'], 'is_correct' => true],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA7-C-05-URAIAN',
                        'question_type' => 'uraian',
                        'cognitive_level' => 'HOTS',
                        'difficulty' => 4,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Indonesia terletak di pertemuan tiga lempeng tektonik aktif dunia sehingga rawan mengalami gempa bumi. Sebutkan ketiga lempeng tersebut dan jelaskan langkah mitigasi keselamatan yang harus dilakukan siswa saat gempa bumi terjadi di dalam ruang kelas!',
                            'content' => 'Indonesia terletak di pertemuan tiga lempeng tektonik aktif dunia sehingga rawan mengalami gempa bumi. Sebutkan ketiga lempeng tersebut dan jelaskan langkah mitigasi keselamatan yang harus dilakukan siswa saat gempa bumi terjadi di dalam ruang kelas!',
                        ],
                        'explanation' => 'Tiga lempeng tektonik: Lempeng Indo-Australia, Lempeng Eurasia, dan Lempeng Pasifik. Mitigasi di kelas: Terapkan prinsip Drop, Cover, Hold on (berlindung di bawah meja yang kuat sambil memegangi kaki meja), lindungi kepala dengan tas/buku, jauhi jendela kaca dan lemari tinggi, setelah getaran reda segera evakuasi tertib menuju titik kumpul terbuka.',
                        'options' => [],
                    ],
                ],
            ],

            // =========================================================================
            // TINGKAT 8 (KELAS VIII)
            // =========================================================================

            // 4. Bank Soal IPA 8 - Kode: IPA-8-A
            [
                'name' => 'Bank Soal IPA 8 - Fisiologi Tubuh: Sistem Pencernaan, Peredaran Darah & Ekskresi (Kode: IPA-8-A)',
                'subject_id' => $subject->id,
                'grade_level_id' => $levels[8]->id,
                'owner_id' => $owner->id,
                'questions' => [
                    [
                        'cp_code' => 'IPA8-A-01-PG',
                        'question_type' => 'pg',
                        'cognitive_level' => 'MOTS',
                        'difficulty' => 3,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Enzim pencernaan yang disekresikan oleh pankreas menuju duodenum untuk memecah protein/peptida menjadi asam amino adalah ...',
                            'content' => 'Enzim pencernaan yang disekresikan oleh pankreas menuju duodenum untuk memecah protein/peptida menjadi asam amino adalah ...',
                        ],
                        'explanation' => 'Tripsin dihasilkan oleh pankreas dalam bentuk tripsinogen aktif untuk mencerna polipeptida menjadi peptida dan asam amino sederhana di usus halus.',
                        'options' => [
                            ['label' => 'A', 'body' => ['text' => 'Tripsin', 'content' => 'Tripsin'], 'is_correct' => true],
                            ['label' => 'B', 'body' => ['text' => 'Pepsin', 'content' => 'Pepsin'], 'is_correct' => false],
                            ['label' => 'C', 'body' => ['text' => 'Ptialin', 'content' => 'Ptialin'], 'is_correct' => false],
                            ['label' => 'D', 'body' => ['text' => 'Lipase Gastrik', 'content' => 'Lipase Gastrik'], 'is_correct' => false],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA8-A-02-PGK',
                        'question_type' => 'pg_kompleks',
                        'cognitive_level' => 'MOTS',
                        'difficulty' => 3,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Manakah pernyataan yang tepat mengenai karakteristik pembuluh balik (vena) manusia? (Pilih semua yang benar)',
                            'content' => 'Manakah pernyataan yang tepat mengenai karakteristik pembuluh balik (vena) manusia? (Pilih semua yang benar)',
                        ],
                        'explanation' => 'Pembuluh vena memiliki dinding relatif tipis kurang elastis, banyak katup searah, aliran bertekanan rendah menuju jantung, dan letaknya dangkal dekat permukaan kulit.',
                        'options' => [
                            ['label' => 'A', 'body' => ['text' => 'Dinding pembuluh lebih tipis dan kurang elastis dibanding pembuluh nadi (arteri)', 'content' => 'Dinding pembuluh lebih tipis dan kurang elastis dibanding pembuluh nadi (arteri)'], 'is_correct' => true],
                            ['label' => 'B', 'body' => ['text' => 'Memiliki banyak katup di sepanjang pembuluh untuk mencegah arus balik darah', 'content' => 'Memiliki banyak katup di sepanjang pembuluh untuk mencegah arus balik darah'], 'is_correct' => true],
                            ['label' => 'C', 'body' => ['text' => 'Umumnya membawa darah kaya karbon dioksida menuju jantung (kecuali vena pulmonalis)', 'content' => 'Umumnya membawa darah kaya karbon dioksida menuju jantung (kecuali vena pulmonalis)'], 'is_correct' => true],
                            ['label' => 'D', 'body' => ['text' => 'Darah mengalir dengan tekanan sangat tinggi berdenyut meninggalkan ventrikel', 'content' => 'Darah mengalir dengan tekanan sangat tinggi berdenyut meninggalkan ventrikel'], 'is_correct' => false],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA8-A-03-BS',
                        'question_type' => 'bs',
                        'cognitive_level' => 'MOTS',
                        'difficulty' => 3,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Pernyataan Ekskresi: Proses filtrasi darah pada nefron ginjal berlangsung di dalam tubulus kontortus distal dan menghasilkan cairan urine sesungguhnya.',
                            'content' => 'Pernyataan Ekskresi: Proses filtrasi darah pada nefron ginjal berlangsung di dalam tubulus kontortus distal dan menghasilkan cairan urine sesungguhnya.',
                        ],
                        'explanation' => 'Salah. Filtrasi terjadi di glomerulus menghasilkan urine primer. Tubulus distal berperan dalam proses augmentasi (penambahan zat sisa).',
                        'options' => [
                            ['label' => 'BENAR', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => false],
                            ['label' => 'SALAH', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => true],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA8-A-04-ISIAN',
                        'question_type' => 'isian',
                        'cognitive_level' => 'LOTS',
                        'difficulty' => 2,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Komponen sel darah berbentuk lempengan tanpa inti yang berperan vital dalam mekanisme hemostasis dan pembekuan darah ketika terjadi luka adalah ...',
                            'content' => 'Komponen sel darah berbentuk lempengan tanpa inti yang berperan vital dalam mekanisme hemostasis dan pembekuan darah ketika terjadi luka adalah ...',
                            'correct_answer' => 'Trombosit',
                        ],
                        'explanation' => 'Trombosit (keping darah) melepaskan enzim trombokinase untuk memicu reaksi kaskade pembekuan darah.',
                        'options' => [
                            ['label' => 'JAWABAN', 'body' => ['text' => 'Trombosit', 'content' => 'Trombosit'], 'is_correct' => true],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA8-A-05-URAIAN',
                        'question_type' => 'uraian',
                        'cognitive_level' => 'HOTS',
                        'difficulty' => 4,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Seorang siswa menguji kandungan sampel makanan di laboratorium. Saat ditetesi reagen Biuret, sampel berubah warna menjadi ungu. Namun saat ditetesi larutan Iodin/Lugol, sampel tidak berubah warna. Jelaskan zat makanan apa yang terkandung dan uraikan bagaimana zat tersebut dicerna di lambung manusia!',
                            'content' => 'Seorang siswa menguji kandungan sampel makanan di laboratorium. Saat ditetesi reagen Biuret, sampel berubah warna menjadi ungu. Namun saat ditetesi larutan Iodin/Lugol, sampel tidak berubah warna. Jelaskan zat makanan apa yang terkandung dan uraikan bagaimana zat tersebut dicerna di lambung manusia!',
                        ],
                        'explanation' => 'Reaksi Biuret ungu membuktikan sampel mengandung protein, sedangkan reaksi negatif iodin menunjukkan tidak adanya amilum/karbohidrat. Di organ lambung, protein dicerna secara kimiawi oleh enzim pepsin yang diaktifkan oleh asam klorida (HCl lambung, pH 1-2) untuk dihidrolisis menjadi molekul pepton/peptida.',
                        'options' => [],
                    ],
                ],
            ],

            // 5. Bank Soal IPA 8 - Kode: IPA-8-B
            [
                'name' => 'Bank Soal IPA 8 - Mekanika & Energi: Gerak Benda, Usaha & Pesawat Sederhana (Kode: IPA-8-B)',
                'subject_id' => $subject->id,
                'grade_level_id' => $levels[8]->id,
                'owner_id' => $owner->id,
                'questions' => [
                    [
                        'cp_code' => 'IPA8-B-01-PG',
                        'question_type' => 'pg',
                        'cognitive_level' => 'MOTS',
                        'difficulty' => 2,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Sebuah balok bermassa 4 kg mula-mula diam di atas permukaan lantai licin tanpa gesekan. Jika balok ditarik dengan gaya horizontal konstan sebesar 20 Newton, berapakah percepatan gerak balok tersebut?',
                            'content' => 'Sebuah balok bermassa 4 kg mula-mula diam di atas permukaan lantai licin tanpa gesekan. Jika balok ditarik dengan gaya horizontal konstan sebesar 20 Newton, berapakah percepatan gerak balok tersebut?',
                        ],
                        'explanation' => 'Sesuai Hukum II Newton: a = F / m = 20 N / 4 kg = 5 m/s².',
                        'options' => [
                            ['label' => 'A', 'body' => ['text' => '5 m/s²', 'content' => '5 m/s²'], 'is_correct' => true],
                            ['label' => 'B', 'body' => ['text' => '0,2 m/s²', 'content' => '0,2 m/s²'], 'is_correct' => false],
                            ['label' => 'C', 'body' => ['text' => '80 m/s²', 'content' => '80 m/s²'], 'is_correct' => false],
                            ['label' => 'D', 'body' => ['text' => '16 m/s²', 'content' => '16 m/s²'], 'is_correct' => false],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA8-B-02-PGK',
                        'question_type' => 'pg_kompleks',
                        'cognitive_level' => 'MOTS',
                        'difficulty' => 3,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Berikut ini merupakan alat-alat yang menerapkan prinsip tuas (pengungkit) jenis pertama, di mana titik tumpu berada di antara titik kuasa dan titik beban: (Pilih semua yang benar)',
                            'content' => 'Berikut ini merupakan alat-alat yang menerapkan prinsip tuas (pengungkit) jenis pertama, di mana titik tumpu berada di antara titik kuasa dan titik beban: (Pilih semua yang benar)',
                        ],
                        'explanation' => 'Tuas jenis 1: titik tumpu di tengah (gunting, tang, jungkat-jungkit). Gerobak roda satu adalah tuas jenis 2 (beban di tengah).',
                        'options' => [
                            ['label' => 'A', 'body' => ['text' => 'Gunting pemotong kertas', 'content' => 'Gunting pemotong kertas'], 'is_correct' => true],
                            ['label' => 'B', 'body' => ['text' => 'Jungkat-jungkit taman bermain', 'content' => 'Jungkat-jungkit taman bermain'], 'is_correct' => true],
                            ['label' => 'C', 'body' => ['text' => 'Tang penjepit kawat', 'content' => 'Tang penjepit kawat'], 'is_correct' => true],
                            ['label' => 'D', 'body' => ['text' => 'Gerobak dorong roda satu pengangkut pasir', 'content' => 'Gerobak dorong roda satu pengangkut pasir'], 'is_correct' => false],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA8-B-03-BS',
                        'question_type' => 'bs',
                        'cognitive_level' => 'LOTS',
                        'difficulty' => 2,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Pernyataan Hukum Archimedes: Benda padat akan berada dalam keadaan melayang di dalam fluida apabila massa jenis benda padat tersebut bernilai lebih besar daripada massa jenis fluida sekitarnya.',
                            'content' => 'Pernyataan Hukum Archimedes: Benda padat akan berada dalam keadaan melayang di dalam fluida apabila massa jenis benda padat tersebut bernilai lebih besar daripada massa jenis fluida sekitarnya.',
                        ],
                        'explanation' => 'Salah. Benda melayang jika massa jenis benda sama dengan massa jenis fluida. Jika massa jenis benda lebih besar, benda akan tenggelam.',
                        'options' => [
                            ['label' => 'BENAR', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => false],
                            ['label' => 'SALAH', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => true],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA8-B-04-ISIAN',
                        'question_type' => 'isian',
                        'cognitive_level' => 'LOTS',
                        'difficulty' => 1,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Kemampuan atau kapasitas suatu materi/sistem untuk melakukan kerja atau usaha mekanik didefinisikan sebagai ...',
                            'content' => 'Kemampuan atau kapasitas suatu materi/sistem untuk melakukan kerja atau usaha mekanik didefinisikan sebagai ...',
                            'correct_answer' => 'Energi',
                        ],
                        'explanation' => 'Energi adalah kemampuan untuk melakukan usaha, memiliki satuan internasional Joule (J).',
                        'options' => [
                            ['label' => 'JAWABAN', 'body' => ['text' => 'Energi', 'content' => 'Energi'], 'is_correct' => true],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA8-B-05-URAIAN',
                        'question_type' => 'uraian',
                        'cognitive_level' => 'HOTS',
                        'difficulty' => 3,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Sebuah peti bermassa 50 kg hendak dinaikkan ke bak truk berketinggian 1,5 meter menggunakan lintasan papan bidang miring sepanjang 3 meter. Jika percepatan gravitasi g = 10 m/s², hitunglah Keuntungan Mekanik (KM) bidang miring dan besar gaya kuasa minimal (F) yang dibutuhkan untuk mendorong peti tersebut!',
                            'content' => 'Sebuah peti bermassa 50 kg hendak dinaikkan ke bak truk berketinggian 1,5 meter menggunakan lintasan papan bidang miring sepanjang 3 meter. Jika percepatan gravitasi g = 10 m/s², hitunglah Keuntungan Mekanik (KM) bidang miring dan besar gaya kuasa minimal (F) yang dibutuhkan untuk mendorong peti tersebut!',
                        ],
                        'explanation' => 'Keuntungan Mekanik: KM = s / h = 3 m / 1,5 m = 2. Berat peti: W = m x g = 50 kg x 10 m/s² = 500 N. Gaya kuasa minimal: F = W / KM = 500 N / 2 = 250 Newton.',
                        'options' => [],
                    ],
                ],
            ],

            // 6. Bank Soal IPA 8 - Kode: IPA-8-C
            [
                'name' => 'Bank Soal IPA 8 - Kimia Terapan: Zat Aditif, Zat Adiktif & Sifat Bahan (Kode: IPA-8-C)',
                'subject_id' => $subject->id,
                'grade_level_id' => $levels[8]->id,
                'owner_id' => $owner->id,
                'questions' => [
                    [
                        'cp_code' => 'IPA8-C-01-PG',
                        'question_type' => 'pg',
                        'cognitive_level' => 'LOTS',
                        'difficulty' => 2,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Bahan aditif pangan alami yang berfungsi sebagai pewarna kuning sekaligus berperan sebagai senyawa antioksidan penangkal radikal bebas adalah ...',
                            'content' => 'Bahan aditif pangan alami yang berfungsi sebagai pewarna kuning sekaligus berperan sebagai senyawa antioksidan penangkal radikal bebas adalah ...',
                        ],
                        'explanation' => 'Kunyit mengandung senyawa kurkuminoid (kurkumin) yang memberikan warna kuning alami dan berfungsi sebagai antioksidan serta antimikroba.',
                        'options' => [
                            ['label' => 'A', 'body' => ['text' => 'Kunyit (Kurkumin)', 'content' => 'Kunyit (Kurkumin)'], 'is_correct' => true],
                            ['label' => 'B', 'body' => ['text' => 'Daun Suji (Klorofil)', 'content' => 'Daun Suji (Klorofil)'], 'is_correct' => false],
                            ['label' => 'C', 'body' => ['text' => 'Tartrazin sintetis', 'content' => 'Tartrazin sintetis'], 'is_correct' => false],
                            ['label' => 'D', 'body' => ['text' => 'Karamel gula', 'content' => 'Karamel gula'], 'is_correct' => false],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA8-C-02-PGK',
                        'question_type' => 'pg_kompleks',
                        'cognitive_level' => 'MOTS',
                        'difficulty' => 3,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Efek fisiologis penyalahgunaan narkotika golongan stimulan (seperti amfetamin dan sabu/metamfetamin) terhadap tubuh manusia adalah ... (Pilih semua yang benar)',
                            'content' => 'Efek fisiologis penyalahgunaan narkotika golongan stimulan (seperti amfetamin dan sabu/metamfetamin) terhadap tubuh manusia adalah ... (Pilih semua yang benar)',
                        ],
                        'explanation' => 'Stimulan memicu pelepasan dopamin masif yang menaikkan detak jantung, tekanan darah, dan rasa waspada palsu, serta menghilangkan nafsu makan. Penurunan frekuensi nafas drastis adalah efek depresan/opioid.',
                        'options' => [
                            ['label' => 'A', 'body' => ['text' => 'Meningkatkan denyut jantung dan tekanan darah secara drastis', 'content' => 'Meningkatkan denyut jantung dan tekanan darah secara drastis'], 'is_correct' => true],
                            ['label' => 'B', 'body' => ['text' => 'Menghilangkan rasa kantuk dan menurunkan nafsu makan secara ekstrem', 'content' => 'Menghilangkan rasa kantuk dan menurunkan nafsu makan secara ekstrem'], 'is_correct' => true],
                            ['label' => 'C', 'body' => ['text' => 'Memicu kecemasan berlebih, paranoia, dan gangguan halusinasi', 'content' => 'Memicu kecemasan berlebih, paranoia, dan gangguan halusinasi'], 'is_correct' => true],
                            ['label' => 'D', 'body' => ['text' => 'Menurunkan denyut nadi dan memperlambat laju pernapasan hingga koma', 'content' => 'Menurunkan denyut nadi dan memperlambat laju pernapasan hingga koma'], 'is_correct' => false],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA8-C-03-BS',
                        'question_type' => 'bs',
                        'cognitive_level' => 'MOTS',
                        'difficulty' => 2,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Pernyataan Pengawet Pangan: Natrium benzoat merupakan pengawet sintetis yang diizinkan penggunaannya dalam batas regulasi pangan BPOM untuk menghambat pertumbuhan khamir dan bakteri pada produk pangan asam.',
                            'content' => 'Pernyataan Pengawet Pangan: Natrium benzoat merupakan pengawet sintetis yang diizinkan penggunaannya dalam batas regulasi pangan BPOM untuk menghambat pertumbuhan khamir dan bakteri pada produk pangan asam.',
                        ],
                        'explanation' => 'Benar. Natrium benzoat adalah pengawet sintetik legal berstandar food-grade yang efektif pada pH asam (< 4,5).',
                        'options' => [
                            ['label' => 'BENAR', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                            ['label' => 'SALAH', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA8-C-04-ISIAN',
                        'question_type' => 'isian',
                        'cognitive_level' => 'LOTS',
                        'difficulty' => 2,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Jaringan vaskular tumbuhan yang bertugas mengangkut fotosintat (hasil fotosintesis) dari daun menuju ke seluruh organ tumbuhan adalah jaringan ...',
                            'content' => 'Jaringan vaskular tumbuhan yang bertugas mengangkut fotosintat (hasil fotosintesis) dari daun menuju ke seluruh organ tumbuhan adalah jaringan ...',
                            'correct_answer' => 'Floem',
                        ],
                        'explanation' => 'Floem (pembuluh tapis) mengangkut sukrosa hasil asimilasi daun ke organ simpan dan titik tumbuh.',
                        'options' => [
                            ['label' => 'JAWABAN', 'body' => ['text' => 'Floem', 'content' => 'Floem'], 'is_correct' => true],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA8-C-05-URAIAN',
                        'question_type' => 'uraian',
                        'cognitive_level' => 'MOTS',
                        'difficulty' => 3,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Jelaskan perbedaan mendasar antara zat aditif dan zat adiktif, serta sebutkan masing-masing 2 contoh zat aditif alami versus zat adiktif bukan narkotika yang dapat ditemui dalam kehidupan sehari-hari!',
                            'content' => 'Jelaskan perbedaan mendasar antara zat aditif dan zat adiktif, serta sebutkan masing-masing 2 contoh zat aditif alami versus zat adiktif bukan narkotika yang dapat ditemui dalam kehidupan sehari-hari!',
                        ],
                        'explanation' => 'Zat aditif adalah bahan tambahan pangan untuk memperbaiki penampilan, cita rasa, tekstur, atau umur simpan makanan (contoh alami: gula pasir sebagai pemanis, kunyit sebagai pewarna). Sedangkan zat adiktif adalah zat kimia yang dapat memicu ketergantungan fisik dan psikologis bila dikonsumsi (contoh bukan narkotika: kafein dalam kopi/teh, nikotin dalam tembakau rokok).',
                        'options' => [],
                    ],
                ],
            ],

            // =========================================================================
            // TINGKAT 9 (KELAS IX)
            // =========================================================================

            // 7. Bank Soal IPA 9 - Kode: IPA-9-A
            [
                'name' => 'Bank Soal IPA 9 - Genetika & Bioteknologi: Pewarisan Sifat & Produk Biologi (Kode: IPA-9-A)',
                'subject_id' => $subject->id,
                'grade_level_id' => $levels[9]->id,
                'owner_id' => $owner->id,
                'questions' => [
                    [
                        'cp_code' => 'IPA9-A-01-PG',
                        'question_type' => 'pg',
                        'cognitive_level' => 'MOTS',
                        'difficulty' => 3,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Kacang ercis galur murni biji bulat dominan (BB) disilangkan dengan kacang ercis biji keriput resesif (bb). Apabila keturunan F1 (Bb) disilangkan sesamanya, berapakah persentase keturunan F2 yang berfenotipe biji keriput?',
                            'content' => 'Kacang ercis galur murni biji bulat dominan (BB) disilangkan dengan kacang ercis biji keriput resesif (bb). Apabila keturunan F1 (Bb) disilangkan sesamanya, berapakah persentase keturunan F2 yang berfenotipe biji keriput?',
                        ],
                        'explanation' => 'Persilangan F1 x F1: Bb x Bb menghasilkan rasio genotipe 1 BB : 2 Bb : 1 bb. Fenotipe keriput hanya dimiliki oleh genotipe homozigot resesif bb = 1/4 = 25%.',
                        'options' => [
                            ['label' => 'A', 'body' => ['text' => '25%', 'content' => '25%'], 'is_correct' => true],
                            ['label' => 'B', 'body' => ['text' => '50%', 'content' => '50%'], 'is_correct' => false],
                            ['label' => 'C', 'body' => ['text' => '75%', 'content' => '75%'], 'is_correct' => false],
                            ['label' => 'D', 'body' => ['text' => '100%', 'content' => '100%'], 'is_correct' => false],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA9-A-02-PGK',
                        'question_type' => 'pg_kompleks',
                        'cognitive_level' => 'MOTS',
                        'difficulty' => 2,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Manakah pasangan produk bioteknologi pangan konvensional dan mikroorganisme yang berperan dalam proses fermentasinya secara benar? (Pilih semua yang benar)',
                            'content' => 'Manakah pasangan produk bioteknologi pangan konvensional dan mikroorganisme yang berperan dalam proses fermentasinya secara benar? (Pilih semua yang benar)',
                        ],
                        'explanation' => 'Tempe difermentasi oleh kapang Rhizopus oryzae; yoghurt oleh bakteri Lactobacillus bulgaricus; nata de coco oleh bakteri pembentuk selulosa Acetobacter xylinum; dan tape oleh ragi Saccharomyces cerevisiae.',
                        'options' => [
                            ['label' => 'A', 'body' => ['text' => 'Tempe kedelai dihasilkan melalui fermentasi kapang Rhizopus oryzae', 'content' => 'Tempe kedelai dihasilkan melalui fermentasi kapang Rhizopus oryzae'], 'is_correct' => true],
                            ['label' => 'B', 'body' => ['text' => 'Yoghurt susu asam dihasilkan melalui aktivitas bakteri Lactobacillus bulgaricus', 'content' => 'Yoghurt susu asam dihasilkan melalui aktivitas bakteri Lactobacillus bulgaricus'], 'is_correct' => true],
                            ['label' => 'C', 'body' => ['text' => 'Nata de coco diproduksi oleh bakteri pembentuk jalinan selulosa Acetobacter xylinum', 'content' => 'Nata de coco diproduksi oleh bakteri pembentuk jalinan selulosa Acetobacter xylinum'], 'is_correct' => true],
                            ['label' => 'D', 'body' => ['text' => 'Tape ketan dan singkong difermentasi dengan ragi Saccharomyces cerevisiae', 'content' => 'Tape ketan dan singkong difermentasi dengan ragi Saccharomyces cerevisiae'], 'is_correct' => true],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA9-A-03-BS',
                        'question_type' => 'bs',
                        'cognitive_level' => 'LOTS',
                        'difficulty' => 2,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Pernyataan Kariotipe: Sel gamet manusia (sel sperma dan sel ovum) bersifat diploid (2n) yang mengandung 46 buah kromosom lengkap.',
                            'content' => 'Pernyataan Kariotipe: Sel gamet manusia (sel sperma dan sel ovum) bersifat diploid (2n) yang mengandung 46 buah kromosom lengkap.',
                        ],
                        'explanation' => 'Salah. Sel gamet dihasilkan melalui pembelahan meiosis sehingga bersifat haploid (n = 23 kromosom). Sel somatis (tubuh) yang bersifat diploid (2n = 46 kromosom).',
                        'options' => [
                            ['label' => 'BENAR', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => false],
                            ['label' => 'SALAH', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => true],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA9-A-04-ISIAN',
                        'question_type' => 'isian',
                        'cognitive_level' => 'LOTS',
                        'difficulty' => 2,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Molekul heliks ganda berupa untaian asam nukleat pembawa kode materi genetik hereditas pada sel hidup adalah ...',
                            'content' => 'Molekul heliks ganda berupa untaian asam nukleat pembawa kode materi genetik hereditas pada sel hidup adalah ...',
                            'correct_answer' => 'DNA',
                        ],
                        'explanation' => 'DNA (Deoxyribonucleic Acid) tersusun atas rantai polinukleotida ganda heliks yang mengodekan protein dan sifat hereditas.',
                        'options' => [
                            ['label' => 'JAWABAN', 'body' => ['text' => 'DNA', 'content' => 'DNA'], 'is_correct' => true],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA9-A-05-URAIAN',
                        'question_type' => 'uraian',
                        'cognitive_level' => 'HOTS',
                        'difficulty' => 4,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Seorang pria penderita hemofilia (XhY) menikah dengan wanita dengan genotipe normal pembawa sifat / carrier (XHXh). Buatlah diagram persilangannya dan tentukan persentase kemungkinan anak perempuan mereka yang menderita penyakit hemofilia!',
                            'content' => 'Seorang pria penderita hemofilia (XhY) menikah dengan wanita dengan genotipe normal pembawa sifat / carrier (XHXh). Buatlah diagram persilangannya dan tentukan persentase kemungkinan anak perempuan mereka yang menderita penyakit hemofilia!',
                        ],
                        'explanation' => 'P: XhY x XHXh. Gamet Pria: Xh, Y; Gamet Wanita: XH, Xh. Anak perempuan: XHXh (wanita normal pembawa/carrier 50% dari anak perempuan) dan XhXh (wanita hemofilia 50% dari anak perempuan). Maka kemungkinan anak perempuan mereka menderita hemofilia adalah 50%.',
                        'options' => [],
                    ],
                ],
            ],

            // 8. Bank Soal IPA 9 - Kode: IPA-9-B
            [
                'name' => 'Bank Soal IPA 9 - Listrik & Magnet: Listrik Dinamis & Induksi Elektromagnetik (Kode: IPA-9-B)',
                'subject_id' => $subject->id,
                'grade_level_id' => $levels[9]->id,
                'owner_id' => $owner->id,
                'questions' => [
                    [
                        'cp_code' => 'IPA9-B-01-PG',
                        'question_type' => 'pg',
                        'cognitive_level' => 'MOTS',
                        'difficulty' => 3,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Dua buah partikel bermuatan listrik q1 = 2 x 10^-6 C dan q2 = 8 x 10^-6 C terpisah sejauh r = 2 cm di ruang hampa. Jika konstanta elektrostatika k = 9 x 10^9 N.m²/C², berapakah besar gaya tolak Coulomb yang terjadi?',
                            'content' => 'Dua buah partikel bermuatan listrik q1 = 2 x 10^-6 C dan q2 = 8 x 10^-6 C terpisah sejauh r = 2 cm di ruang hampa. Jika konstanta elektrostatika k = 9 x 10^9 N.m²/C², berapakah besar gaya tolak Coulomb yang terjadi?',
                        ],
                        'explanation' => 'F = k x (q1 x q2) / r² = 9x10^9 x (2x10^-6 x 8x10^-6) / (0,02)² = 9x10^9 x 16x10^-12 / 0,0004 = 0,144 / 0,0004 = 360 Newton.',
                        'options' => [
                            ['label' => 'A', 'body' => ['text' => '360 N', 'content' => '360 N'], 'is_correct' => true],
                            ['label' => 'B', 'body' => ['text' => '36 N', 'content' => '36 N'], 'is_correct' => false],
                            ['label' => 'C', 'body' => ['text' => '3.600 N', 'content' => '3.600 N'], 'is_correct' => false],
                            ['label' => 'D', 'body' => ['text' => '180 N', 'content' => '180 N'], 'is_correct' => false],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA9-B-02-PGK',
                        'question_type' => 'pg_kompleks',
                        'cognitive_level' => 'MOTS',
                        'difficulty' => 2,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Faktor-faktor yang secara langsung memengaruhi besarnya Gaya Gerak Listrik (GGL) induksi pada kumparan kawat magnet menurut Hukum Faraday adalah ... (Pilih semua yang benar)',
                            'content' => 'Faktor-faktor yang secara langsung memengaruhi besarnya Gaya Gerak Listrik (GGL) induksi pada kumparan kawat magnet menurut Hukum Faraday adalah ... (Pilih semua yang benar)',
                        ],
                        'explanation' => 'GGL induksi epsilon = -N x (dPhi/dt). Bergantung pada kecepatan gerak magnet (laju perubahan fluks), jumlah lilitan kawat (N), dan kuat medan magnet. Warna lapisan isolator kabel tidak berpengaruh.',
                        'options' => [
                            ['label' => 'A', 'body' => ['text' => 'Kecepatan gerak keluar-masuk kutub magnet terhadap kumparan', 'content' => 'Kecepatan gerak keluar-masuk kutub magnet terhadap kumparan'], 'is_correct' => true],
                            ['label' => 'B', 'body' => ['text' => 'Jumlah lilitan kawat pada kumparan', 'content' => 'Jumlah lilitan kawat pada kumparan'], 'is_correct' => true],
                            ['label' => 'C', 'body' => ['text' => 'Kekuatan medan magnet permanen yang digunakan', 'content' => 'Kekuatan medan magnet permanen yang digunakan'], 'is_correct' => true],
                            ['label' => 'D', 'body' => ['text' => 'Warna pelapis isolator kawat tembaga kumparan', 'content' => 'Warna pelapis isolator kawat tembaga kumparan'], 'is_correct' => false],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA9-B-03-BS',
                        'question_type' => 'bs',
                        'cognitive_level' => 'LOTS',
                        'difficulty' => 2,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Pernyataan Trafo: Transformator step-up berfungsi untuk menurunkan tegangan bolak-balik (AC) dan memiliki karakteristik jumlah lilitan sekunder lebih sedikit dibandingkan lilitan primer.',
                            'content' => 'Pernyataan Trafo: Transformator step-up berfungsi untuk menurunkan tegangan bolak-balik (AC) dan memiliki karakteristik jumlah lilitan sekunder lebih sedikit dibandingkan lilitan primer.',
                        ],
                        'explanation' => 'Salah. Transformator step-up menaikkan tegangan (Ns > Np dan Vs > Vp). Trafo yang menurunkan tegangan adalah transformator step-down (Ns < Np).',
                        'options' => [
                            ['label' => 'BENAR', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => false],
                            ['label' => 'SALAH', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => true],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA9-B-04-ISIAN',
                        'question_type' => 'isian',
                        'cognitive_level' => 'MOTS',
                        'difficulty' => 2,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Sebuah lampu listrik berdaya 100 Watt dinyalakan selama 5 jam setiap hari. Energi listrik yang dikonsumsi lampu tersebut dalam satu hari adalah ... kWh.',
                            'content' => 'Sebuah lampu listrik berdaya 100 Watt dinyalakan selama 5 jam setiap hari. Energi listrik yang dikonsumsi lampu tersebut dalam satu hari adalah ... kWh.',
                            'correct_answer' => '0,5',
                        ],
                        'explanation' => 'Energi E = P x t = 100 W x 5 jam = 500 Wh = 500 / 1.000 kWh = 0,5 kWh.',
                        'options' => [
                            ['label' => 'JAWABAN', 'body' => ['text' => '0,5', 'content' => '0,5'], 'is_correct' => true],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA9-B-05-URAIAN',
                        'question_type' => 'uraian',
                        'cognitive_level' => 'HOTS',
                        'difficulty' => 4,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Dua buah resistor R1 = 6 Ohm dan R2 = 12 Ohm dirangkai secara paralel, lalu hasilnya dihubungkan seri dengan resistor R3 = 4 Ohm. Rangkaian tersebut disambungkan ke sumber tegangan ideal sebesar 16 Volt. Hitunglah nilai hambatan total rangkaian dan kuat arus listrik total (I) yang mengalir dari sumber tegangan!',
                            'content' => 'Dua buah resistor R1 = 6 Ohm dan R2 = 12 Ohm dirangkai secara paralel, lalu hasilnya dihubungkan seri dengan resistor R3 = 4 Ohm. Rangkaian tersebut disambungkan ke sumber tegangan ideal sebesar 16 Volt. Hitunglah nilai hambatan total rangkaian dan kuat arus listrik total (I) yang mengalir dari sumber tegangan!',
                        ],
                        'explanation' => 'Hambatan paralel: 1/Rp = 1/6 + 1/12 = 2/12 + 1/12 = 3/12 => Rp = 12/3 = 4 Ohm. Hambatan total: R_tot = Rp + R3 = 4 + 4 = 8 Ohm. Arus total: I = V / R_tot = 16 V / 8 Ohm = 2 Ampere.',
                        'options' => [],
                    ],
                ],
            ],

            // 9. Bank Soal IPA 9 - Kode: IPA-9-C
            [
                'name' => 'Bank Soal IPA 9 - Lingkungan & Teknologi: Tanah, Materi & Teknologi Ramah Lingkungan (Kode: IPA-9-C)',
                'subject_id' => $subject->id,
                'grade_level_id' => $levels[9]->id,
                'owner_id' => $owner->id,
                'questions' => [
                    [
                        'cp_code' => 'IPA9-C-01-PG',
                        'question_type' => 'pg',
                        'cognitive_level' => 'LOTS',
                        'difficulty' => 2,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Teknologi ramah lingkungan yang memanfaatkan penguraian bahan biomassa organik oleh mikroorganisme dalam kondisi anaerobik untuk menghasilkan gas metana sebagai sumber energi alternatif adalah ...',
                            'content' => 'Teknologi ramah lingkungan yang memanfaatkan penguraian bahan biomassa organik oleh mikroorganisme dalam kondisi anaerobik untuk menghasilkan gas metana sebagai sumber energi alternatif adalah ...',
                        ],
                        'explanation' => 'Biogas dihasilkan dari proses digester fermentasi anaerob kotoran ternak atau sampah organik oleh bakteri metanogenik menghasilkan gas metana (CH4).',
                        'options' => [
                            ['label' => 'A', 'body' => ['text' => 'Biogas', 'content' => 'Biogas'], 'is_correct' => true],
                            ['label' => 'B', 'body' => ['text' => 'Biofuel Biodiesel', 'content' => 'Biofuel Biodiesel'], 'is_correct' => false],
                            ['label' => 'C', 'body' => ['text' => 'Sel Fotovoltaik Surya', 'content' => 'Sel Fotovoltaik Surya'], 'is_correct' => false],
                            ['label' => 'D', 'body' => ['text' => 'Geotermal Panas Bumi', 'content' => 'Geotermal Panas Bumi'], 'is_correct' => false],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA9-C-02-PGK',
                        'question_type' => 'pg_kompleks',
                        'cognitive_level' => 'MOTS',
                        'difficulty' => 3,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Peran organisme tanah (seperti cacing tanah, bakteri perakaran, dan fungi) dalam menjaga kesuburan tanah dan keseimbangan biosfer adalah ... (Pilih semua yang benar)',
                            'content' => 'Peran organisme tanah (seperti cacing tanah, bakteri perakaran, dan fungi) dalam menjaga kesuburan tanah dan keseimbangan biosfer adalah ... (Pilih semua yang benar)',
                        ],
                        'explanation' => 'Organisme tanah menguraikan serasah menjadi humus, menggemburkan struktur tanah memperbaiki aerasi, dan mengikat nitrogen atmosfer untuk tanaman.',
                        'options' => [
                            ['label' => 'A', 'body' => ['text' => 'Mendekomposisi materi organik mati menjadi senyawa humus penyubur tanah', 'content' => 'Mendekomposisi materi organik mati menjadi senyawa humus penyubur tanah'], 'is_correct' => true],
                            ['label' => 'B', 'body' => ['text' => 'Membentuk lorong-lorong pori tanah yang melancarkan sirkulasi udara (aerasi) dan air', 'content' => 'Membentuk lorong-lorong pori tanah yang melancarkan sirkulasi udara (aerasi) dan air'], 'is_correct' => true],
                            ['label' => 'C', 'body' => ['text' => 'Membantu fiksasi unsur hara nitrogen bebas dari atmosfer menjadi nitrat bagi tanaman', 'content' => 'Membantu fiksasi unsur hara nitrogen bebas dari atmosfer menjadi nitrat bagi tanaman'], 'is_correct' => true],
                            ['label' => 'D', 'body' => ['text' => 'Menghasilkan racun kimiawi yang mematikan seluruh akar tanaman budidaya', 'content' => 'Menghasilkan racun kimiawi yang mematikan seluruh akar tanaman budidaya'], 'is_correct' => false],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA9-C-03-BS',
                        'question_type' => 'bs',
                        'cognitive_level' => 'MOTS',
                        'difficulty' => 2,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Pernyataan Partikel Materi: Ion bermuatan positif (kation) terbentuk ketika suatu atom netral melepaskan satu atau lebih elektron valensinya, sehingga jumlah proton dalam inti atom menjadi lebih banyak daripada elektronnya.',
                            'content' => 'Pernyataan Partikel Materi: Ion bermuatan positif (kation) terbentuk ketika suatu atom netral melepaskan satu atau lebih elektron valensinya, sehingga jumlah proton dalam inti atom menjadi lebih banyak daripada elektronnya.',
                        ],
                        'explanation' => 'Benar. Kation bermuatan positif akibat pelepasan elektron. Sebaliknya, penangkapan elektron menghasilkan anion bermuatan negatif.',
                        'options' => [
                            ['label' => 'BENAR', 'body' => ['text' => 'Benar', 'content' => 'Benar'], 'is_correct' => true],
                            ['label' => 'SALAH', 'body' => ['text' => 'Salah', 'content' => 'Salah'], 'is_correct' => false],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA9-C-04-ISIAN',
                        'question_type' => 'isian',
                        'cognitive_level' => 'MOTS',
                        'difficulty' => 3,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Teknologi ramah lingkungan yang mereaksikan gas hidrogen (H2) dengan gas oksigen (O2) untuk menghasilkan arus listrik dengan produk buangan hanya berupa uap air (H2O) murni adalah teknologi sel bahan bakar atau ...',
                            'content' => 'Teknologi ramah lingkungan yang mereaksikan gas hidrogen (H2) dengan gas oksigen (O2) untuk menghasilkan arus listrik dengan produk buangan hanya berupa uap air (H2O) murni adalah teknologi sel bahan bakar atau ...',
                            'correct_answer' => 'Fuel Cell',
                        ],
                        'explanation' => 'Fuel cell adalah sel elektrokimia yang mengubah energi kimia hidrogen langsung menjadi listrik dengan emisi ramah lingkungan berupa air murni.',
                        'options' => [
                            ['label' => 'JAWABAN', 'body' => ['text' => 'Fuel Cell', 'content' => 'Fuel Cell'], 'is_correct' => true],
                        ],
                    ],
                    [
                        'cp_code' => 'IPA9-C-05-URAIAN',
                        'question_type' => 'uraian',
                        'cognitive_level' => 'HOTS',
                        'difficulty' => 4,
                        'weight' => 20.0,
                        'body' => [
                            'text' => 'Jelaskan prinsip kerja dan manfaat teknologi Lubang Resapan Biopori (LRB) serta teknologi Fitoremediasi dalam upaya konservasi lingkungan hidup di kawasan permukiman dan perkotaan!',
                            'content' => 'Jelaskan prinsip kerja dan manfaat teknologi Lubang Resapan Biopori (LRB) serta teknologi Fitoremediasi dalam upaya konservasi lingkungan hidup di kawasan permukiman dan perkotaan!',
                        ],
                        'explanation' => 'Lubang Resapan Biopori (LRB) adalah lubang silindris vertikal yang diisi sampah organik untuk memicu fauna tanah (cacing) membuat terowongan alami, sehingga laju infiltrasi air hujan meningkat drastis (mencegah banjir genangan) sekaligus menghasilkan pupuk kompos alami. Fitoremediasi adalah pemanfaatan tanaman hidup (seperti enceng gondok, lidah buaya, sansiviera) untuk menyerap, menguraikan, atau menstabilkan zat kontaminan/polutan berbahaya dari tanah dan perairan tercemar.',
                        'options' => [],
                    ],
                ],
            ],
        ];
    }
}
