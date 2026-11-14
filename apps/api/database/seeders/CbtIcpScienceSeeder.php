<?php

namespace Database\Seeders;

use App\Models\GradeLevel;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\School;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CbtIcpScienceSeeder extends Seeder
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

            // 1. Dapatkan atau pastikan subject Cambridge Native & Science tersedia
            $subject = Subject::where('code', 'ICP-DEMO')->first()
                ?? Subject::where('name', 'like', '%Cambridge%')->first()
                ?? Subject::firstOrCreate(
                    ['code' => 'ICP-DEMO'],
                    [
                        'school_id' => $schoolId,
                        'name' => 'Cambridge Native & Science',
                        'subject_group' => 'muatan_nasional',
                    ]
                );

            // 2. Dapatkan Grade Level Kelas 7
            $grade7 = GradeLevel::where('school_id', $schoolId)
                ->where(fn ($q) => $q->where('sort_order', 7)->orWhere('name', 'LIKE', '%7%')->orWhere('name', 'LIKE', '%VII%'))
                ->first()
                ?? GradeLevel::firstOrCreate(['school_id' => $schoolId, 'sort_order' => 7], ['name' => 'Kelas 7']);

            // 3. Dapatkan User Guru ICP
            $guruIcp = User::where('username', 'guru.icp')->first()
                ?? User::where('email', 'guru.icp@labschool.sch.id')->first()
                ?? User::first();

            // 4. Buat Question Bank
            $bank = QuestionBank::firstOrCreate(
                [
                    'school_id' => $schoolId,
                    'subject_id' => $subject->id,
                    'name' => 'Cambridge Lower Secondary Science Checkpoint - Kelas VII ICP',
                ],
                [
                    'grade_level_id' => $grade7->id,
                    'owner_id' => $guruIcp->id,
                ]
            );

            // 5. Data 10 Soal CBT
            $questionsData = [
                [
                    'cp_code' => 'ICP-BIO-01',
                    'cognitive_level' => 'LOTS',
                    'difficulty' => 1,
                    'weight' => '1.00',
                    'text' => 'Di bawah mikroskop cahaya, seorang siswa mengamati sel tumbuhan dan sel hewan. Struktur sel berikut yang hanya ditemukan pada sel tumbuhan dan berfungsi memberikan bentuk yang kaku serta perlindungan mekanik adalah ...',
                    'explanation' => 'Dinding sel yang tersusun dari selulosa hanya dimiliki oleh sel tumbuhan (serta fungi dan bakteri tertentu), berfungsi melindungi dan menjaga bentuk kaku sel. Sel hewan hanya memiliki membran sel yang fleksibel.',
                    'options' => [
                        ['label' => 'A', 'text' => 'Membran sel (cell membrane)', 'is_correct' => false],
                        ['label' => 'B', 'text' => 'Dinding sel (cell wall)', 'is_correct' => true],
                        ['label' => 'C', 'text' => 'Mitokondria (mitochondria)', 'is_correct' => false],
                        ['label' => 'D', 'text' => 'Sitoplasma (cytoplasm)', 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'ICP-BIO-02',
                    'cognitive_level' => 'MOTS',
                    'difficulty' => 2,
                    'weight' => '1.00',
                    'text' => "Persamaan kata umum untuk proses fotosintesis pada tumbuhan hijau adalah:\nKarbon Dioksida + Air --(Cahaya Matahari / Klorofil)--> Glukosa + Gas X\nGas X yang dihasilkan dari reaksi fotosintesis tersebut adalah ...",
                    'explanation' => 'Produk sampingan dari fotolisis air dalam proses fotosintesis adalah gas Oksigen (O2), yang kemudian dilepaskan ke atmosfer melalui stomata daun.',
                    'options' => [
                        ['label' => 'A', 'text' => 'Nitrogen (N2)', 'is_correct' => false],
                        ['label' => 'B', 'text' => 'Karbon monoksida (CO)', 'is_correct' => false],
                        ['label' => 'C', 'text' => 'Oksigen (O2)', 'is_correct' => true],
                        ['label' => 'D', 'text' => 'Hidrogen (H2)', 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'ICP-CHM-01',
                    'cognitive_level' => 'LOTS',
                    'difficulty' => 1,
                    'weight' => '1.00',
                    'text' => 'Ketika es batu dipanaskan, es berubah wujud menjadi air, dan jika pemanasan dilanjutkan, air akan mendidih menjadi uap air. Menurut teori partikel zat (particle theory of matter), perubahan yang terjadi pada partikel zat ketika mencair adalah ...',
                    'explanation' => 'Saat zat padat mencair, penyerapan energi kalor meningkatkan energi kinetik partikel sehingga gaya tarik antarpartikel melemah dan partikel dapat menggelincir melewati satu sama lain.',
                    'options' => [
                        ['label' => 'A', 'text' => 'Partikel bertambah ukurannya dan bergerak lebih lambat', 'is_correct' => false],
                        ['label' => 'B', 'text' => 'Partikel mulai bergerak bebas melewati satu sama lain karena gaya tarik antarpartikel melemah', 'is_correct' => true],
                        ['label' => 'C', 'text' => 'Partikel terurai menjadi atom-atom penyusunnya secara permanen', 'is_correct' => false],
                        ['label' => 'D', 'text' => 'Jarak antarpartikel menjadi sangat rapat dan tersusun teratur', 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'ICP-CHM-02',
                    'cognitive_level' => 'MOTS',
                    'difficulty' => 2,
                    'weight' => '1.00',
                    'text' => "Seorang siswa menguji larutan pembersih dapur menggunakan kertas lakmus merah dan lakmus biru. Hasil pengujian menunjukkan:\n1. Kertas lakmus merah berubah menjadi biru.\n2. Kertas lakmus biru tetap biru.\nBerdasarkan hasil uji tersebut, larutan tersebut bersifat ...",
                    'explanation' => 'Larutan yang mengubah lakmus merah menjadi biru dan mempertahankan lakmus biru adalah larutan basa (alkalis) dengan nilai pH di atas 7.',
                    'options' => [
                        ['label' => 'A', 'text' => 'Asam kuat dengan pH < 3', 'is_correct' => false],
                        ['label' => 'B', 'text' => 'Netral dengan pH = 7', 'is_correct' => false],
                        ['label' => 'C', 'text' => 'Basa dengan pH > 7', 'is_correct' => true],
                        ['label' => 'D', 'text' => 'Asam lemah dengan pH 5–6', 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'ICP-PHY-01',
                    'cognitive_level' => 'MOTS',
                    'difficulty' => 2,
                    'weight' => '1.00',
                    'text' => "Sebuah balok ditarik oleh dua gaya horizontal yang bekerja pada garis yang sama:\n* Gaya F1 = 35 N ke arah kanan.\n* Gaya F2 = 15 N ke arah kiri.\nBesar dan arah resultan gaya yang bekerja pada balok tersebut adalah ...",
                    'explanation' => 'Resultan dua gaya berlawanan arah adalah selisih keduanya: R = F1 - F2 = 35 N - 15 N = 20 N mengikuti arah gaya yang lebih besar (ke arah kanan).',
                    'options' => [
                        ['label' => 'A', 'text' => '50 N ke arah kanan', 'is_correct' => false],
                        ['label' => 'B', 'text' => '20 N ke arah kanan', 'is_correct' => true],
                        ['label' => 'C', 'text' => '20 N ke arah kiri', 'is_correct' => false],
                        ['label' => 'D', 'text' => '50 N ke arah kiri', 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'ICP-PHY-02',
                    'cognitive_level' => 'LOTS',
                    'difficulty' => 1,
                    'weight' => '1.00',
                    'text' => 'Ketika sebuah bola dilepaskan dari puncak lintasan luncur dan berguling menuruni bidang miring, perubahan bentuk energi utama yang terjadi adalah ...',
                    'explanation' => 'Saat benda bergerak turun dari ketinggian, energi potensial gravitasinya berkurang dan dikonversikan menjadi energi kinetik (energi gerak).',
                    'options' => [
                        ['label' => 'A', 'text' => 'Energi kimia berubah menjadi energi listrik', 'is_correct' => false],
                        ['label' => 'B', 'text' => 'Energi potensial gravitasi berubah menjadi energi kinetik', 'is_correct' => true],
                        ['label' => 'C', 'text' => 'Energi kinetik berubah menjadi energi potensial elastis', 'is_correct' => false],
                        ['label' => 'D', 'text' => 'Energi panas berubah menjadi energi kimia', 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'ICP-BIO-03',
                    'cognitive_level' => 'MOTS',
                    'difficulty' => 2,
                    'weight' => '1.00',
                    'text' => 'Enzim amilase (ptialin) yang terdapat pada kelenjar ludah rongga mulut manusia berfungsi untuk memecah ...',
                    'explanation' => 'Enzim ptialin (amilase ludah) berfungsi menghidrolisis polisakarida (amilum/pati) menjadi disakarida sederhana seperti maltosa di dalam rongga mulut.',
                    'options' => [
                        ['label' => 'A', 'text' => 'Protein menjadi asam amino', 'is_correct' => false],
                        ['label' => 'B', 'text' => 'Lemak menjadi asam lemak dan gliserol', 'is_correct' => false],
                        ['label' => 'C', 'text' => 'Karbohidrat/pati (starch) menjadi gula sederhana (maltosa)', 'is_correct' => true],
                        ['label' => 'D', 'text' => 'Asam nukleat menjadi nukleotida', 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'ICP-CHM-03',
                    'cognitive_level' => 'HOTS',
                    'difficulty' => 3,
                    'weight' => '1.00',
                    'text' => 'Pita magnesium (Mg) dimasukkan ke dalam tabung reaksi yang berisi asam klorida encer (HCl). Terjadi gelembung gas yang cepat dan tabung reaksi terasa hangat saat disentuh. Gas yang terbentuk dan jenis reaksi termokimia tersebut berturut-turut adalah ...',
                    'explanation' => 'Reaksi logam dengan asam menghasilkan garam dan gas hidrogen (Mg + 2HCl -> MgCl2 + H2). Pelepasan panas ke lingkungan (tabung terasa hangat) menandakan reaksi bersifat eksotermik.',
                    'options' => [
                        ['label' => 'A', 'text' => 'Gas Klorin (Cl2), reaksi endoterm', 'is_correct' => false],
                        ['label' => 'B', 'text' => 'Gas Oksigen (O2), reaksi endoterm', 'is_correct' => false],
                        ['label' => 'C', 'text' => 'Gas Karbon Dioksida (CO2), reaksi eksoterm', 'is_correct' => false],
                        ['label' => 'D', 'text' => 'Gas Hidrogen (H2), reaksi eksoterm', 'is_correct' => true],
                    ],
                ],
                [
                    'cp_code' => 'ICP-PHY-03',
                    'cognitive_level' => 'MOTS',
                    'difficulty' => 2,
                    'weight' => '1.00',
                    'text' => "Perhatikan pernyataan-pernyataan berikut mengenai sifat perambatan gelombang bunyi dan cahaya:\n1. Gelombang bunyi membutuhkan medium materi untuk merambat.\n2. Gelombang cahaya dapat merambat melalui ruang hampa udara (vakum).\n3. Bunyi merambat lebih cepat di udara daripada di zat padat (besi).\n4. Cepat rambat cahaya jauh lebih tinggi daripada cepat rambat bunyi.\nPernyataan yang benar adalah ...",
                    'explanation' => 'Pernyataan 3 salah karena bunyi merambat paling cepat di zat padat (rapat partikel tinggi), bukan di udara. Pernyataan 1, 2, dan 4 bernilai benar.',
                    'options' => [
                        ['label' => 'A', 'text' => '1, 2, dan 3', 'is_correct' => false],
                        ['label' => 'B', 'text' => '1, 2, dan 4', 'is_correct' => true],
                        ['label' => 'C', 'text' => '2, 3, dan 4', 'is_correct' => false],
                        ['label' => 'D', 'text' => '1, 3, dan 4', 'is_correct' => false],
                    ],
                ],
                [
                    'cp_code' => 'ICP-BIO-04',
                    'cognitive_level' => 'HOTS',
                    'difficulty' => 3,
                    'weight' => '1.00',
                    'text' => "Perhatikan rantai makanan pada ekosistem kebun sekolah:\nRumput -> Belalang -> Katak -> Ular\nJika populasi katak mengalami kepunahan secara mendadak akibat penggunaan insektisida berlebih, dampak ekologis langsung yang paling mungkin terjadi adalah ...",
                    'explanation' => 'Katak adalah predator utama belalang dan mangsa utama ular. Tanpa katak, populasi belalang lepas dari kontrol pemangsa sehingga bertambah banyak, sedangkan ular kekurangan mangsa langsung sehingga populasinya menurun.',
                    'options' => [
                        ['label' => 'A', 'text' => 'Populasi belalang menurun drastis dan populasi ular meningkat pesat', 'is_correct' => false],
                        ['label' => 'B', 'text' => 'Populasi rumput meningkat karena belalang berpindah memakan ular', 'is_correct' => false],
                        ['label' => 'C', 'text' => 'Populasi belalang meningkat tajam sedangkan populasi ular mengalami penurunan', 'is_correct' => true],
                        ['label' => 'D', 'text' => 'Semua produsen dan konsumen langsung mati secara serentak', 'is_correct' => false],
                    ],
                ],
            ];

            foreach ($questionsData as $qItem) {
                $question = Question::updateOrCreate(
                    [
                        'bank_id' => $bank->id,
                        'cp_code' => $qItem['cp_code'],
                    ],
                    [
                        'question_type' => 'pg',
                        'body' => [
                            'text' => $qItem['text'],
                            'content' => $qItem['text'],
                        ],
                        'cognitive_level' => $qItem['cognitive_level'],
                        'difficulty' => $qItem['difficulty'],
                        'weight' => $qItem['weight'],
                        'explanation' => $qItem['explanation'],
                        'version' => 1,
                        'status' => 'aktif',
                    ]
                );

                // Buat opsi A, B, C, D
                foreach ($qItem['options'] as $opt) {
                    QuestionOption::updateOrCreate(
                        [
                            'question_id' => $question->id,
                            'label' => $opt['label'],
                        ],
                        [
                            'body' => [
                                'text' => $opt['text'],
                                'content' => $opt['text'],
                            ],
                            'is_correct' => $opt['is_correct'],
                        ]
                    );
                }
            }
        });
    }
}
