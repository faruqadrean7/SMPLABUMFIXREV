<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;

/**
 * Seeder data dummy SMP untuk SISTLABUM.
 *
 * Menghasilkan:
 *   - teachers          : 18 guru (semua mapel SMP Kurikulum Merdeka + kepsek & wakasek)
 *   - students          : 240 siswa (tingkat 7/8/9 x 4 kelas x 20 anak), semua status "aktif"
 *   - guardians         : ~480 ortu/wali (ayah + ibu per siswa)
 *   - student_guardians : relasi siswa <-> ortu (salah satu is_primary = true)
 *   - classes + class_students : rombel 7A..9D (lihat catatan di bawah)
 *
 * Cara pakai (disarankan dari DB kosong):
 *   php artisan migrate:fresh
 *   php artisan db:seed --class=SmpDataSeeder
 *
 * Catatan skema:
 *   - NIK dienkripsi at-rest pakai Crypt::encryptString (Laravel APP_KEY) dan diberi
 *     blind-index HMAC-SHA256. Set NIK_INDEX_KEY di .env (fallback ke APP_KEY).
 *   - applicant_parents (modul PPDB) sengaja TIDAK diisi: itu untuk calon pendaftar
 *     sebelum diterima, bukan siswa yang sudah aktif.
 *   - Akun login (users) TIDAK dibuat di sini — lihat catatan di akhir file.
 */
class SmpDataSeeder extends Seeder
{
    /**
     * ID sekolah/unit kerja.
     * HARUS cocok dengan baris di tabel `schools`; kalau tidak, data tetap masuk
     * (tidak ada FK) tetapi tidak terlihat di aplikasi karena semua query
     * difilter school_id.
     */
    private const SCHOOL_ID = '00000000-0000-0000-0000-000000000001';

    /** Tahun ajaran berjalan (dipakai untuk entry_date & perkiraan tahun lahir). */
    private const TAHUN_AJARAN = 2026;

    /** Tingkat & kelas: tiap tingkat 4 kelas (A–D), tiap kelas 20 siswa. */
    private const TINGKAT = [7, 8, 9];
    private const KELAS   = ['A', 'B', 'C', 'D'];
    private const PER_KELAS = 20;

    private string $hmacKey;
    private array $usedNisn = [];
    private array $usedNik  = [];
    private array $usedNip  = [];
    private array $usedNuptk = [];

    // ---- Pool nama & atribut (Indonesia) ---------------------------------

    private array $namaDepanL = [
        'Ahmad', 'Muhammad', 'Budi', 'Agus', 'Dwi', 'Eko', 'Rizki', 'Fajar', 'Hendra',
        'Bagus', 'Dimas', 'Yoga', 'Satrio', 'Arif', 'Bayu', 'Galih', 'Ivan', 'Reza',
        'Andi', 'Fikri', 'Hafiz', 'Aditya', 'Bagas', 'Candra', 'Farhan', 'Gilang',
        'Ilham', 'Krisna', 'Naufal', 'Rangga', 'Teguh', 'Wahyu', 'Zaki', 'Raka', 'Dani',
    ];

    private array $namaDepanP = [
        'Siti', 'Dewi', 'Ayu', 'Putri', 'Rina', 'Nur', 'Fitri', 'Indah', 'Lestari',
        'Wulan', 'Anisa', 'Citra', 'Dinda', 'Eka', 'Gita', 'Intan', 'Kartika', 'Lia',
        'Maya', 'Nadia', 'Rahma', 'Salsabila', 'Tiara', 'Vina', 'Yuni', 'Zahra', 'Aulia',
        'Bunga', 'Clara', 'Diana', 'Farida', 'Hani', 'Oktaviani', 'Rani', 'Sari',
    ];

    private array $namaKeluarga = [
        'Santoso', 'Wijaya', 'Pratama', 'Kusuma', 'Nugroho', 'Saputra', 'Hidayat',
        'Firmansyah', 'Ramadhan', 'Setiawan', 'Gunawan', 'Halim', 'Iskandar', 'Kurniawan',
        'Lesmana', 'Maulana', 'Nurdin', 'Permana', 'Rahardjo', 'Siregar', 'Tanjung',
        'Utomo', 'Wibowo', 'Yulianto', 'Zulkarnain', 'Haryanto', 'Suryana', 'Hartono',
    ];

    private array $kota = [
        'Jakarta', 'Bandung', 'Surabaya', 'Semarang', 'Yogyakarta', 'Surakarta', 'Malang',
        'Bekasi', 'Depok', 'Bogor', 'Tangerang', 'Cirebon', 'Tegal', 'Magelang',
        'Purwokerto', 'Klaten', 'Sidoarjo', 'Gresik', 'Kediri', 'Madiun',
    ];

    private array $agama = [
        'Islam', 'Islam', 'Islam', 'Islam', 'Islam', 'Islam', 'Islam', 'Islam',
        'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu',
    ];

    private array $pekerjaanAyah = [
        'Wiraswasta', 'Karyawan Swasta', 'Pegawai Negeri Sipil', 'Pedagang', 'Petani',
        'Buruh', 'TNI/Polri', 'Guru', 'Dokter', 'Pengusaha', 'Sopir', 'Montir', 'Nelayan',
        'Pensiunan', 'Dosen',
    ];

    private array $pekerjaanIbu = [
        'Ibu Rumah Tangga', 'Ibu Rumah Tangga', 'Ibu Rumah Tangga', 'Karyawan Swasta',
        'Guru', 'Pedagang', 'Wiraswasta', 'Perawat', 'Bidan', 'Pegawai Negeri Sipil',
    ];

    private array $jalan = [
        'Melati', 'Mawar', 'Kenanga', 'Merdeka', 'Diponegoro', 'Sudirman', 'Ahmad Yani',
        'Gatot Subroto', 'Pahlawan', 'Kartini', 'Cempaka', 'Anggrek', 'Flamboyan',
        'Teratai', 'Garuda', 'Kemuning', 'Rajawali', 'Cendrawasih',
    ];

    /**
     * Rencana mapel (Kurikulum Merdeka SMP) -> jumlah guru.
     * Dipakai untuk membangun daftar guru; jadi acuan saat tabel teaching_assignments siap.
     */
    private array $mapelPlan = [
        ['mapel' => 'Pendidikan Agama Islam & Budi Pekerti', 'gelar' => 'S.Pd.I.', 'jumlah' => 2],
        ['mapel' => 'Pendidikan Pancasila',                  'gelar' => 'S.Pd.',   'jumlah' => 1],
        ['mapel' => 'Bahasa Indonesia',                      'gelar' => 'S.Pd.',   'jumlah' => 2],
        ['mapel' => 'Matematika',                            'gelar' => 'S.Pd.',   'jumlah' => 2],
        ['mapel' => 'Ilmu Pengetahuan Alam (IPA)',           'gelar' => 'S.Pd.',   'jumlah' => 2],
        ['mapel' => 'Ilmu Pengetahuan Sosial (IPS)',         'gelar' => 'S.Pd.',   'jumlah' => 2],
        ['mapel' => 'Bahasa Inggris',                        'gelar' => 'S.Pd.',   'jumlah' => 2],
        ['mapel' => 'PJOK',                                  'gelar' => 'S.Or.',   'jumlah' => 1],
        ['mapel' => 'Informatika',                           'gelar' => 'S.Kom.',  'jumlah' => 1],
        ['mapel' => 'Seni Budaya',                           'gelar' => 'S.Sn.',   'jumlah' => 1],
        ['mapel' => 'Prakarya',                              'gelar' => 'S.Pd.',   'jumlah' => 1],
        ['mapel' => 'Bimbingan Konseling (BK)',              'gelar' => 'S.Pd.',   'jumlah' => 1],
    ];

    public function run(): void
    {
        $this->hmacKey = (string) (env('NIK_INDEX_KEY') ?: config('app.key'));

        $now = Carbon::now();
        $this->command?->info('Menyemai data SMP SISTLABUM...');

        $teachers = $this->seedTeachers($now);
        $students = $this->seedStudentsWithGuardians($now);

        // Rombel hanya dibuat kalau skemanya memang tersedia (dulu belum ada).
        if (Schema::hasTable('classes') && Schema::hasTable('class_students')) {
            $this->seedClasses($students, $now);
        } else {
            $this->command?->warn('  ! Tabel classes/class_students tidak ada — rombel dilewati.');
        }

        $this->command?->info(sprintf(
            'Selesai: %d guru, %d siswa, %d ortu/wali.',
            count($teachers),
            count(self::TINGKAT) * count(self::KELAS) * self::PER_KELAS,
            count(self::TINGKAT) * count(self::KELAS) * self::PER_KELAS * 2
        ));
    }

    // =====================================================================
    //  GURU
    // =====================================================================

    private function seedTeachers(Carbon $now): array
    {
        $rows = [];
        $meta = []; // dokumentasi mapel per guru (tidak masuk tabel teachers)
        $index = 0;

        foreach ($this->mapelPlan as $plan) {
            for ($i = 0; $i < $plan['jumlah']; $i++) {
                $laki   = (bool) mt_rand(0, 1);
                $depan  = $laki ? $this->pick($this->namaDepanL) : $this->pick($this->namaDepanP);
                $belakang = $this->pick($this->namaKeluarga);
                $nama   = trim("{$depan} {$belakang}, {$plan['gelar']}");

                // Kepala sekolah & wakasek diambil dari guru senior pertama.
                $position = 'Guru Mata Pelajaran';
                if ($index === 0) {
                    $position = 'Kepala Sekolah';
                } elseif ($index === 1) {
                    $position = 'Wakil Kepala Sekolah Bidang Kurikulum';
                } elseif ($index === 2) {
                    $position = 'Wakil Kepala Sekolah Bidang Kesiswaan';
                }

                // Status kepegawaian: kepsek/wakasek & sebagian guru = tetap (PNS).
                $status = $index < 3 ? 'tetap' : $this->pick(['tetap', 'tetap', 'honorer', 'kontrak']);
                $isPns  = $status === 'tetap';

                $rows[] = [
                    'id'            => Uuid::uuid7()->toString(),
                    'school_id'     => self::SCHOOL_ID,
                    'user_id'       => null,
                    'nip'           => $isPns ? $this->uniqueNip() : null,
                    'nuptk'         => $this->uniqueNuptk(),
                    'full_name'     => $nama,
                    'position'      => $position,
                    'status'        => $status,
                    'photo_file_id' => null,
                    'created_at'    => $now->format('Y-m-d H:i:s'),
                    'updated_at'    => $now->format('Y-m-d H:i:s'),
                    'deleted_at'    => null,
                ];
                $meta[] = ['nama' => $nama, 'mapel' => $plan['mapel']];
                $index++;
            }
        }

        DB::table('teachers')->insert($rows);

        // --- Rencana penugasan wali kelas --------------------------------
        // Wali kelas 7A..9D diambil dari 12 guru pertama yang bukan kepsek.
        $waliKelasPlan = [];
        $kelasList = [];
        foreach (self::TINGKAT as $t) {
            foreach (self::KELAS as $k) {
                $kelasList[] = "{$t}{$k}";
            }
        }
        $kandidatWali = array_values(array_filter(
            $rows,
            fn ($r) => $r['position'] !== 'Kepala Sekolah'
        ));
        foreach ($kelasList as $i => $kelas) {
            if (isset($kandidatWali[$i])) {
                $waliKelasPlan[$kelas] = [
                    'id'   => $kandidatWali[$i]['id'],
                    'nama' => $kandidatWali[$i]['full_name'],
                ];
            }
        }

        $this->command?->info('  - '.count($rows).' guru dibuat. Rencana wali kelas:');
        foreach ($waliKelasPlan as $kelas => $wali) {
            $this->command?->line("      {$kelas} => {$wali['nama']}");
        }

        $this->waliKelasPlan = $waliKelasPlan;

        return $rows;
    }

    /** @var array<string, array{id:string,nama:string}> */
    private array $waliKelasPlan = [];

    // =====================================================================
    //  SISWA + ORTU/WALI
    // =====================================================================

    /**
     * @return array<int, array{id:string,tingkat:int,kelas:string,nis:string,nama:string}>
     */
    private function seedStudentsWithGuardians(Carbon $now): array
    {
        $students = [];
        $guardians = [];
        $pivots = [];
        $nisUrut = [];
        $roster = [];

        foreach (self::TINGKAT as $tingkat) {
            // Tahun masuk & rentang tahun lahir per tingkat (TA 2026/2027).
            $tahunMasuk = self::TAHUN_AJARAN - ($tingkat - 7); // 7->2026, 8->2025, 9->2024
            $entryDate  = Carbon::create($tahunMasuk, 7, 15)->format('Y-m-d');
            $tahunLahir = 2026 - ($tingkat - 7) - 13;          // 7->2013, 8->2012, 9->2011
            $nisUrut[$tahunMasuk] = $nisUrut[$tahunMasuk] ?? 0;

            foreach (self::KELAS as $kelas) {
                $labelKelas = "{$tingkat}{$kelas}";

                for ($n = 0; $n < self::PER_KELAS; $n++) {
                    $laki     = (bool) mt_rand(0, 1);
                    $keluarga = $this->pick($this->namaKeluarga);
                    $depan    = $laki ? $this->pick($this->namaDepanL) : $this->pick($this->namaDepanP);
                    $tengah   = mt_rand(0, 1)
                        ? ($laki ? $this->pick($this->namaDepanL) : $this->pick($this->namaDepanP))
                        : null;
                    $namaSiswa = trim(implode(' ', array_filter([$depan, $tengah, $keluarga])));

                    $birth = Carbon::create($tahunLahir, mt_rand(1, 12), mt_rand(1, 28));
                    $nik   = $this->generateNik($birth, $laki);
                    $alamat = $this->generateAlamat();

                    $nisUrut[$tahunMasuk]++;
                    $studentId = Uuid::uuid7()->toString();
                    $nis = $tahunMasuk . str_pad((string) $nisUrut[$tahunMasuk], 4, '0', STR_PAD_LEFT);

                    $students[] = [
                        'id'            => $studentId,
                        'school_id'     => self::SCHOOL_ID,
                        'user_id'       => null,
                        'nis'           => $nis,
                        'nisn'          => $this->uniqueNisn(),
                        'nik_enc'       => Crypt::encryptString($nik),
                        'nik_hash'      => hash_hmac('sha256', $nik, $this->hmacKey),
                        'full_name'     => $namaSiswa,
                        'gender'        => $laki ? 'L' : 'P',
                        'birth_place'   => $this->pick($this->kota),
                        'birth_date'    => $birth->format('Y-m-d'),
                        'religion'      => $this->pick($this->agama),
                        'address'       => $alamat,
                        'photo_file_id' => null,
                        'status'        => 'aktif',
                        'program_type'  => $this->pickProgram(),
                        'entry_date'    => $entryDate,
                        'graduated_at'  => null,
                        'created_at'    => $now->format('Y-m-d H:i:s'),
                        'updated_at'    => $now->format('Y-m-d H:i:s'),
                        'deleted_at'    => null,
                    ];

                    $roster[] = [
                        'id'      => $studentId,
                        'tingkat' => $tingkat,
                        'kelas'   => $labelKelas,
                        'nis'     => $nis,
                        'nama'    => $namaSiswa,
                    ];

                    // --- Ortu: ayah + ibu, salah satu primary --------------
                    $ayahPrimary = mt_rand(1, 100) <= 60;

                    $ayahId = Uuid::uuid7()->toString();
                    $guardians[] = [
                        'id'         => $ayahId,
                        'school_id'  => self::SCHOOL_ID,
                        'user_id'    => null,
                        'full_name'  => trim($this->pick($this->namaDepanL) . ' ' . $keluarga),
                        'phone'      => $this->generatePhone(),
                        'occupation' => $this->pick($this->pekerjaanAyah),
                        'address'    => $alamat,
                        'created_at' => $now->format('Y-m-d H:i:s'),
                        'updated_at' => $now->format('Y-m-d H:i:s'),
                        'deleted_at' => null,
                    ];
                    $pivots[] = [
                        'student_id'   => $studentId,
                        'guardian_id'  => $ayahId,
                        'relationship' => 'ayah',
                        'is_primary'   => $ayahPrimary,
                        'created_at'   => $now->format('Y-m-d H:i:s'),
                        'updated_at'   => $now->format('Y-m-d H:i:s'),
                    ];

                    $ibuId = Uuid::uuid7()->toString();
                    $guardians[] = [
                        'id'         => $ibuId,
                        'school_id'  => self::SCHOOL_ID,
                        'user_id'    => null,
                        'full_name'  => trim($this->pick($this->namaDepanP) . ' ' . $keluarga),
                        'phone'      => $this->generatePhone(),
                        'occupation' => $this->pick($this->pekerjaanIbu),
                        'address'    => $alamat,
                        'created_at' => $now->format('Y-m-d H:i:s'),
                        'updated_at' => $now->format('Y-m-d H:i:s'),
                        'deleted_at' => null,
                    ];
                    $pivots[] = [
                        'student_id'   => $studentId,
                        'guardian_id'  => $ibuId,
                        'relationship' => 'ibu',
                        'is_primary'   => ! $ayahPrimary,
                        'created_at'   => $now->format('Y-m-d H:i:s'),
                        'updated_at'   => $now->format('Y-m-d H:i:s'),
                    ];
                }
            }
        }

        foreach (array_chunk($students, 100) as $chunk) {
            DB::table('students')->insert($chunk);
        }
        foreach (array_chunk($guardians, 100) as $chunk) {
            DB::table('guardians')->insert($chunk);
        }
        foreach (array_chunk($pivots, 200) as $chunk) {
            DB::table('student_guardians')->insert($chunk);
        }

        $this->command?->info('  - '.count($students).' siswa + '.count($guardians).' ortu/wali dibuat.');

        return $roster;
    }

    // =====================================================================
    //  ROMBEL (classes + class_students)
    // =====================================================================

    /**
     * Membuat rombel 7A..9D untuk tahun ajaran berjalan, mengisi wali kelas dari
     * rencana di seedTeachers(), lalu menempelkan siswanya.
     *
     * @param  array<int, array{id:string,tingkat:int,kelas:string,nis:string,nama:string}>  $roster
     */
    private function seedClasses(array $roster, Carbon $now): void
    {
        $academicYear = DB::table('academic_years')->where('is_active', true)->first()
            ?? DB::table('academic_years')->orderByDesc('start_date')->first();

        // Skema bisa kosong: buat tahun ajaran berjalan sendiri agar rombel punya induk.
        if (! $academicYear) {
            $yearId = Uuid::uuid7()->toString();
            DB::table('academic_years')->insert([
                'id'         => $yearId,
                'school_id'  => self::SCHOOL_ID,
                'name'       => self::TAHUN_AJARAN.'/'.(self::TAHUN_AJARAN + 1),
                'start_date' => self::TAHUN_AJARAN.'-07-01',
                'end_date'   => (self::TAHUN_AJARAN + 1).'-06-30',
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $academicYear = (object) ['id' => $yearId];
            $this->command?->line('  + Tahun ajaran '.self::TAHUN_AJARAN.'/'.(self::TAHUN_AJARAN + 1).' dibuat.');
        }

        $classIds = [];
        $gradeIds = [];

        foreach (self::TINGKAT as $tingkat) {
            // Skema `grade_levels` tidak punya kolom `level`; kunci alaminya sort_order.
            $grade = DB::table('grade_levels')->where('sort_order', $tingkat)->first();
            if (! $grade) {
                $gradeId = Uuid::uuid7()->toString();
                DB::table('grade_levels')->insert([
                    'id'         => $gradeId,
                    'school_id'  => self::SCHOOL_ID,
                    'name'       => "Kelas {$tingkat}",
                    'sort_order' => $tingkat,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $grade = (object) ['id' => $gradeId];
            }
            $gradeIds[$tingkat] = $grade->id;

            foreach (self::KELAS as $kelas) {
                $label = "{$tingkat}{$kelas}";
                $existing = DB::table('classes')
                    ->where('name', $label)
                    ->where('academic_year_id', $academicYear->id)
                    ->first();

                if ($existing) {
                    $classIds[$label] = $existing->id;

                    continue;
                }

                $classId = Uuid::uuid7()->toString();
                DB::table('classes')->insert([
                    'id'                  => $classId,
                    'school_id'           => self::SCHOOL_ID,
                    'academic_year_id'    => $academicYear->id,
                    'grade_level_id'      => $gradeIds[$tingkat],
                    'major_id'            => null,
                    'homeroom_teacher_id' => $this->waliKelasPlan[$label]['id'] ?? null,
                    'name'                => $label,
                    'capacity'            => self::PER_KELAS,
                    'program_type'        => 'reguler',
                    'created_at'          => $now,
                    'updated_at'          => $now,
                    'deleted_at'          => null,
                ]);
                $classIds[$label] = $classId;
            }
        }

        $links = [];
        foreach ($roster as $s) {
            $links[] = [
                'id'         => Uuid::uuid7()->toString(),
                'class_id'   => $classIds[$s['kelas']],
                'student_id' => $s['id'],
                'joined_at'  => $now->format('Y-m-d H:i:s'),
                'left_at'    => null,
                'created_at' => $now->format('Y-m-d H:i:s'),
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ];
        }

        foreach (array_chunk($links, 200) as $chunk) {
            DB::table('class_students')->insert($chunk);
        }

        $this->command?->info('  - '.count($classIds).' rombel (7A..9D) + '.count($links).' penempatan siswa.');
    }

    // =====================================================================
    //  HELPER
    // =====================================================================

    private function pick(array $arr): string
    {
        return $arr[array_rand($arr)];
    }

    /** Mayoritas reguler, sisanya program khusus. */
    private function pickProgram(): string
    {
        return $this->pick([
            'reguler', 'reguler', 'reguler', 'reguler', 'reguler', 'reguler',
            'reguler', 'reguler', 'tahfidz', 'tahfidz', 'icp', 'inklusi',
        ]);
    }

    /** Alamat ala KTP: Jl. ... No. .. RT/RW, Kota. */
    private function generateAlamat(): string
    {
        return sprintf(
            'Jl. %s No. %d RT %02d/RW %02d, %s',
            $this->pick($this->jalan),
            mt_rand(1, 150),
            mt_rand(1, 20),
            mt_rand(1, 12),
            $this->pick($this->kota)
        );
    }

    private function generatePhone(): string
    {
        $prefix = $this->pick(['0812', '0813', '0821', '0822', '0851', '0877', '0896', '0858']);

        return $prefix . str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
    }

    /**
     * NIK 16 digit semi-realistis: 6 digit kode wilayah + ddmmyy lahir
     * (perempuan: tanggal + 40) + 4 digit urut. Dijamin unik.
     */
    private function generateNik(Carbon $birth, bool $laki): string
    {
        do {
            $wilayah = sprintf('%02d%02d%02d', mt_rand(11, 94), mt_rand(1, 20), mt_rand(1, 30));
            $tgl = (int) $birth->format('d') + ($laki ? 0 : 40);
            $tanggal = sprintf('%02d%02d%02d', $tgl, (int) $birth->format('m'), (int) $birth->format('y'));
            $urut = str_pad((string) mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $nik  = $wilayah . $tanggal . $urut;
        } while (isset($this->usedNik[$nik]));

        $this->usedNik[$nik] = true;

        return $nik;
    }

    private function uniqueNisn(): string
    {
        do {
            $nisn = (string) mt_rand(1000000000, 9999999999);
        } while (isset($this->usedNisn[$nisn]));
        $this->usedNisn[$nisn] = true;

        return $nisn;
    }

    /** NIP 18 digit (format PNS: lahir YYYYMMDD + TMT YYYYMM + gender + urut). */
    private function uniqueNip(): string
    {
        do {
            $lahir  = sprintf('%04d%02d%02d', mt_rand(1975, 1995), mt_rand(1, 12), mt_rand(1, 28));
            $tmt    = sprintf('%04d%02d', mt_rand(2005, 2020), mt_rand(1, 12));
            $gender = (string) mt_rand(1, 2);
            $urut   = str_pad((string) mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
            $nip    = $lahir . $tmt . $gender . $urut;
        } while (isset($this->usedNip[$nip]));
        $this->usedNip[$nip] = true;

        return $nip;
    }

    private function uniqueNuptk(): string
    {
        do {
            $nuptk = (string) mt_rand(1000000000000000, 9999999999999999);
        } while (isset($this->usedNuptk[$nuptk]));
        $this->usedNuptk[$nuptk] = true;

        return $nuptk;
    }
}
