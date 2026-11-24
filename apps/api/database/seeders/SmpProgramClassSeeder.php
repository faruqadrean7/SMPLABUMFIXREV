<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * OPSI 1 — Rombel program khusus (Tahfidz & ICP) dengan mapel + jadwal sendiri.
 *
 * Yang dilakukan:
 *   1. Membuat mapel program: TAHFIDZ, ARB (tahfidz), CAM-MATH/CAM-SCI/CAM-ENG (ICP)
 *   2. Membuat 6 rombel program: 7T/8T/9T (tahfidz) dan 7I/8I/9I (ICP)
 *   3. Memindahkan siswa bertanda tahfidz/icp ke rombel program sesuai tingkatnya
 *      (siswa inklusi TETAP di rombel reguler — inklusi adalah layanan pendamping,
 *       bukan kurikulum terpisah)
 *   4. Menambah guru pembina program + guru umum pendukung
 *   5. Menyusun ulang SELURUH jadwal semester aktif untuk 18 rombel tanpa bentrok
 *   6. Membuat komponen nilai + contoh nilai mapel program agar muncul di rapor
 *
 * Idempoten: aman dijalankan berulang.
 *
 *   php artisan db:seed --class=SmpProgramClassSeeder
 */
class SmpProgramClassSeeder extends Seeder
{
    private const SCHOOL_ID = '00000000-0000-0000-0000-000000000001';

    /** Jam per minggu & maksimal jam per hari untuk tiap mapel. */
    private const PLAN = [
        'reguler' => [
            'PAI' => [3, 2], 'PPKN' => [2, 1], 'BIND' => [6, 2], 'MTK' => [5, 2],
            'IPA' => [5, 2], 'IPS' => [4, 2], 'BING' => [4, 2], 'PJOK' => [2, 1],
            'INF' => [2, 1], 'SENI' => [2, 1], 'PRAK' => [2, 1], 'BK' => [1, 1],
        ],
        'tahfidz' => [
            'TAHFIDZ' => [4, 2], 'ARB' => [2, 1], 'PAI' => [3, 2], 'PPKN' => [2, 1],
            'BIND' => [4, 2], 'MTK' => [5, 2], 'IPA' => [5, 2], 'IPS' => [4, 2],
            'BING' => [4, 2], 'PJOK' => [2, 1], 'INF' => [2, 1], 'BK' => [1, 1],
        ],
        'icp' => [
            'CAM-MATH' => [2, 1], 'CAM-SCI' => [2, 1], 'CAM-ENG' => [2, 1],
            'MTK' => [5, 2], 'IPA' => [5, 2], 'BIND' => [4, 2], 'IPS' => [4, 2],
            'BING' => [4, 2], 'PAI' => [3, 2], 'PPKN' => [2, 1], 'PJOK' => [2, 1],
            'INF' => [2, 1], 'BK' => [1, 1],
        ],
    ];

    /** Mapel baru: kode => [nama, grup]. */
    private const MAPEL_BARU = [
        'TAHFIDZ' => ['Tahfidz Al-Qur\'an', 'khusus'],
        'ARB' => ['Bahasa Arab', 'khusus'],
        'CAM-MATH' => ['Cambridge Mathematics', 'khusus'],
        'CAM-SCI' => ['Cambridge Science', 'khusus'],
        'CAM-ENG' => ['Cambridge English', 'khusus'],
    ];

    /** Guru pembina program baru: nama => [nip, posisi]. */
    private const GURU_BARU = [
        'Ust. Ahmad Fauzi, S.Pd.I' => ['198501012010011001', 'Guru Tahfidz'],
        'Ustzh. Nur Hidayah, S.Ag' => ['198703152011012002', 'Guru Bahasa Arab'],
        'Mr. James Whitfield, M.Ed' => ['198902202013011003', 'Cambridge Coordinator'],
        'Dra. Sri Wahyuni, M.Pd' => ['197804102006042004', 'Guru Pendamping Program'],
        'Bayu Prakoso, S.Pd' => ['199005172015031005', 'Guru Mata Pelajaran'],
        'Rina Kartika, S.Pd' => ['199208222016072006', 'Guru Mata Pelajaran'],
    ];

    private array $slotIds = [];

    private array $classBusy = [];

    private array $teacherBusy = [];

    private array $teacherFor = [];

    private array $subjectPerDay = [];

    private int $unplaced = 0;

    public function run(): void
    {
        $semester = DB::table('semesters')->where('is_active', true)->first()
            ?? DB::table('semesters')->orderByDesc('start_date')->first();

        if (! $semester) {
            $this->command?->error('Tidak ada semester aktif.');

            return;
        }

        $now = now();
        $this->seedExtraSlots($now);
        $this->slotIds = DB::table('time_slots')->orderBy('start_time')->pluck('id')->all();

        $subjects = $this->seedSubjects($now);
        $this->seedTeachers($now);
        $classes = $this->seedClasses($semester, $now);
        $this->moveStudents($classes, $now);
        $this->seedAssignments($subjects, $classes, $semester, $now);
        $this->rebuildSchedules($subjects, $classes, $semester, $now);
        $this->repairMissing($subjects, $semester, $now);
        $this->seedComponentsAndGrades($subjects, $classes, $semester, $now);

        $this->command?->info("rombel program: {$classes['created']} baru");
        $this->command?->info('jadwal tersusun: '.DB::table('schedules')->where('semester_id', $semester->id)->count().' baris');
        if ($this->unplaced > 0) {
            $this->command?->warn("PERINGATAN: {$this->unplaced} jam tidak mendapat slot.");
        }
    }

    /**
     * 18 rombel x 38 jam tidak muat di 8 jam/hari (kapasitas guru jadi sempit).
     * Tambah jam ke-9 dan ke-10 supaya penjadwalan punya ruang.
     */
    private function seedExtraSlots($now): void
    {
        $tambahan = [
            ['09', '13:30:00', '14:10:00'],
            ['10', '14:10:00', '14:50:00'],
        ];

        foreach ($tambahan as [$nama, $mulai, $selesai]) {
            if (DB::table('time_slots')->where('start_time', $mulai)->exists()) {
                continue;
            }

            DB::table('time_slots')->insert([
                'id' => (string) Str::uuid7(),
                'school_id' => self::SCHOOL_ID,
                'name' => 'Jam '.$nama,
                'start_time' => $mulai,
                'end_time' => $selesai,
                'day_type' => 'reguler',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedSubjects($now): array
    {
        $map = [];

        foreach (self::MAPEL_BARU as $code => [$name, $group]) {
            $existing = DB::table('subjects')->where('code', $code)->whereNull('deleted_at')->first();
            if ($existing) {
                $map[$code] = $existing->id;
                continue;
            }

            $id = (string) Str::uuid7();
            DB::table('subjects')->insert([
                'id' => $id,
                'school_id' => self::SCHOOL_ID,
                'code' => $code,
                'name' => $name,
                'subject_group' => $group,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $map[$code] = $id;
        }

        // Mapel umum yang sudah ada
        foreach (array_keys(self::PLAN['reguler']) as $code) {
            $row = DB::table('subjects')->where('code', $code)->whereNull('deleted_at')->first();
            if ($row) {
                $map[$code] = $row->id;
            }
        }

        return $map;
    }

    private function seedTeachers($now): void
    {
        $roleGuru = DB::table('roles')->where('code', 'teacher')->value('id');

        foreach (self::GURU_BARU as $name => [$nip, $position]) {
            $guru = DB::table('teachers')->where('full_name', $name)->whereNull('deleted_at')->first();

            if (! $guru) {
                $teacherId = (string) Str::uuid7();
                DB::table('teachers')->insert([
                    'id' => $teacherId,
                    'school_id' => self::SCHOOL_ID,
                    'full_name' => $name,
                    'nip' => $nip,
                    'position' => $position,
                    // status = jenis kepegawaian (tetap/kontrak/honorer), bukan aktif/nonaktif
                    'status' => 'tetap',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $guru = DB::table('teachers')->where('id', $teacherId)->first();
            }

            // Guru pembina program butuh akun login supaya bisa mengisi nilai mapelnya.
            if ($guru->user_id) {
                continue;
            }

            $userId = DB::table('users')->where('username', $nip)->value('id');

            if (! $userId) {
                $userId = (string) Str::uuid7();
                DB::table('users')->insert([
                    'id' => $userId,
                    'school_id' => self::SCHOOL_ID,
                    'username' => $nip,
                    'password_hash' => bcrypt('Password123!'),
                    'status' => 'active',
                    'must_change_password' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($roleGuru && ! DB::table('user_roles')->where('user_id', $userId)->where('role_id', $roleGuru)->exists()) {
                // user_roles adalah tabel pivot tanpa kolom id.
                DB::table('user_roles')->insert([
                    'user_id' => $userId,
                    'role_id' => $roleGuru,
                ]);
            }

            DB::table('teachers')->where('id', $guru->id)->update([
                'user_id' => $userId,
                'updated_at' => $now,
            ]);
        }
    }

    /** @return array{byLevel: array, created: int} */
    private function seedClasses($semester, $now): array
    {
        $levels = DB::table('grade_levels')->orderBy('sort_order')->get();
        $homeroom = DB::table('teachers')->whereNull('deleted_at')->pluck('id')->all();

        $byLevel = [];
        $created = 0;

        foreach ($levels as $level) {
            // Nama tingkat memakai angka Romawi ("Kelas VII") — pakai sort_order.
            $angka = (string) $level->sort_order;
            if ($angka === '') {
                continue;
            }

            foreach (['tahfidz' => 'T', 'icp' => 'I'] as $program => $suffix) {
                $name = $angka.$suffix;
                $row = DB::table('classes')->where('name', $name)->whereNull('deleted_at')->first();

                if (! $row) {
                    $row = (object) [
                        'id' => (string) Str::uuid7(),
                        'name' => $name,
                    ];
                    DB::table('classes')->insert([
                        'id' => $row->id,
                        'school_id' => self::SCHOOL_ID,
                        'academic_year_id' => $semester->academic_year_id,
                        'grade_level_id' => $level->id,
                        'name' => $name,
                        'capacity' => 32,
                        'program_type' => $program,
                        'homeroom_teacher_id' => $homeroom[array_rand($homeroom)],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $created++;
                } else {
                    DB::table('classes')->where('id', $row->id)->update([
                        'program_type' => $program,
                        'grade_level_id' => $level->id,
                        'updated_at' => $now,
                    ]);
                }

                $byLevel[$angka][$program] = $row->id;
            }
        }

        return ['byLevel' => $byLevel, 'created' => $created];
    }

    private function moveStudents(array $classes, $now): void
    {
        $moved = 0;

        foreach (['tahfidz', 'icp'] as $program) {
            $students = DB::table('students')
                ->where('program_type', $program)
                ->where('status', 'aktif')
                ->whereNull('deleted_at')
                ->get(['id', 'nis']);

            foreach ($students as $student) {
                $asal = DB::table('class_students as cs')
                    ->join('classes as c', 'c.id', '=', 'cs.class_id')
                    ->join('grade_levels as gl', 'gl.id', '=', 'c.grade_level_id')
                    ->where('cs.student_id', $student->id)
                    ->select('cs.id as cs_id', 'gl.name as level_name', 'c.program_type as class_program')
                    ->first();

                if (! $asal) {
                    continue;
                }

                $angka = (string) (DB::table('grade_levels')->where('name', $asal->level_name)->value('sort_order') ?? '');
                $target = $angka && isset($classes['byLevel'][$angka][$program])
                    ? $classes['byLevel'][$angka][$program]
                    : null;

                if (! $target || $asal->cs_id === null) {
                    continue;
                }

                DB::table('class_students')->where('id', $asal->cs_id)->update([
                    'class_id' => $target,
                    'updated_at' => $now,
                ]);
                $moved++;
            }
        }

        $this->command?->info("siswa dipindah ke rombel program: {$moved}");
    }

    private function seedAssignments(array $subjects, array $classes, $semester, $now): void
    {
        $guruUmum = DB::table('teachers')->whereNull('deleted_at')->pluck('id')->all();

        // Beban guru (jam/minggu) untuk penugasan berimbang.
        $beban = array_fill_keys($guruUmum, 0);

        // Afinitas guru terhadap mapel: dari gelar pada nama (S.Pd.I -> PAI/ARB,
        // S.Or -> PJOK, S.Kom -> INF, S.Sn -> SENI) lalu dilengkapi penugasan yang sudah ada.
        $gelarMapel = [
            'S.Pd.I' => ['PAI', 'ARB'],
            'S.Ag' => ['PAI', 'ARB'],
            'S.Or' => ['PJOK'],
            'S.Kom' => ['INF'],
            'S.Sn' => ['SENI'],
            'M.Ed' => ['CAM-ENG', 'CAM-MATH', 'CAM-SCI', 'BING'],
        ];

        $afinitas = [];
        foreach (DB::table('teachers')->whereNull('deleted_at')->get(['id', 'full_name']) as $guru) {
            foreach ($gelarMapel as $gelar => $codes) {
                if (str_contains((string) $guru->full_name, $gelar)) {
                    foreach ($codes as $code) {
                        if (isset($subjects[$code])) {
                            $afinitas[$subjects[$code]][$guru->id] = true;
                        }
                    }
                }
            }
        }

        // Kalau suatu mapel belum punya guru bergelar cocok, baru pakai penugasan lama.
        foreach (DB::table('teaching_assignments')->get(['subject_id', 'teacher_id']) as $ta) {
            if (empty($afinitas[$ta->subject_id])) {
                $afinitas[$ta->subject_id][$ta->teacher_id] = true;
            }
        }

        $pembina = [
            'TAHFIDZ' => DB::table('teachers')->where('full_name', 'Ust. Ahmad Fauzi, S.Pd.I')->value('id'),
            'ARB' => DB::table('teachers')->where('full_name', 'Ustzh. Nur Hidayah, S.Ag')->value('id'),
            'CAM-MATH' => DB::table('teachers')->where('full_name', 'Mr. James Whitfield, M.Ed')->value('id'),
            'CAM-SCI' => DB::table('teachers')->where('full_name', 'Mr. James Whitfield, M.Ed')->value('id'),
            'CAM-ENG' => DB::table('teachers')->where('full_name', 'Mr. James Whitfield, M.Ed')->value('id'),
        ];

        // Semua kelas (reguler + program)
        $allClasses = DB::table('classes')->whereNull('deleted_at')->get(['id', 'name', 'program_type']);

        foreach ($allClasses as $class) {
            $plan = self::PLAN[$class->program_type] ?? self::PLAN['reguler'];

            foreach ($plan as $code => [$hours]) {
                if (! isset($subjects[$code])) {
                    continue;
                }

                $teacherId = $pembina[$code] ?? null;

                if (! $teacherId) {
                    // Pilih guru dengan beban paling ringan; utamakan yang sudah mengampu mapel ini.
                    $kandidat = $guruUmum;
                    $punyaAfinitas = array_keys($afinitas[$subjects[$code]] ?? []);
                    if (! empty($punyaAfinitas)) {
                        $kandidat = array_values(array_unique(array_merge($punyaAfinitas, $guruUmum)));
                    }

                    usort($kandidat, function ($a, $b) use ($beban, $afinitas, $subjects, $code) {
                        $affA = isset($afinitas[$subjects[$code]][$a]) ? 0 : 1;
                        $affB = isset($afinitas[$subjects[$code]][$b]) ? 0 : 1;

                        // Utamakan guru yang memang mengampu mapel ini, baru seimbangkan beban.
                        return [$affA, $beban[$a] ?? 0] <=> [$affB, $beban[$b] ?? 0];
                    });

                    $teacherId = $kandidat[0];
                }

                $beban[$teacherId] = ($beban[$teacherId] ?? 0) + $hours;

                $adaAssign = DB::table('teaching_assignments')
                    ->where('semester_id', $semester->id)
                    ->where('class_id', $class->id)
                    ->where('subject_id', $subjects[$code])
                    ->first();

                if ($adaAssign) {
                    DB::table('teaching_assignments')->where('id', $adaAssign->id)->update([
                        'teacher_id' => $teacherId,
                        'hours_per_week' => $hours,
                        'updated_at' => $now,
                    ]);
                } else {
                    DB::table('teaching_assignments')->insert([
                        'id' => (string) Str::uuid7(),
                        'semester_id' => $semester->id,
                        'class_id' => $class->id,
                        'subject_id' => $subjects[$code],
                        'teacher_id' => $teacherId,
                        'hours_per_week' => $hours,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $this->teacherFor[$class->id][$code] = $teacherId;
            }
        }
    }

    private function rebuildSchedules(array $subjects, array $classes, $semester, $now): void
    {
        DB::table('schedules')->where('semester_id', $semester->id)->delete();
        $this->classBusy = [];
        $this->teacherBusy = [];
        $this->subjectPerDay = [];

        $allClasses = DB::table('classes')->whereNull('deleted_at')->get(['id', 'name', 'program_type']);

        foreach ($allClasses as $class) {
            $plan = self::PLAN[$class->program_type] ?? self::PLAN['reguler'];

            // Antrian pelajaran: ambil satu per satu dari mapel dengan sisa jam terbanyak.
            $sisa = [];
            foreach ($plan as $code => [$hours]) {
                $sisa[$code] = $hours;
            }

            while (array_sum($sisa) > 0) {
                arsort($sisa);
                foreach ($sisa as $code => $left) {
                    if ($left <= 0) {
                        continue;
                    }
                    $this->placeLesson($class, $code, $subjects, $semester, $now, $plan);
                    $sisa[$code]--;
                    break; // satu pelajaran per putaran
                }
            }
        }
    }

    private function placeLesson($class, string $code, array $subjects, $semester, $now, array $plan): void
    {
        $maxPerDay = $plan[$code][1] ?? 2;
        $teacherId = $this->teacherFor[$class->id][$code] ?? null;

        if (! $teacherId || ! isset($subjects[$code])) {
            $this->unplaced++;

            return;
        }

        for ($day = 1; $day <= 5; $day++) {
            if (($this->subjectPerDay[$class->id][$day][$code] ?? 0) >= $maxPerDay) {
                continue;
            }

            foreach ($this->slotIds as $slotId) {
                if (($this->classBusy[$class->id][$day][$slotId] ?? false)) {
                    continue;
                }
                if (($this->teacherBusy[$teacherId][$day][$slotId] ?? false)) {
                    continue;
                }

                DB::table('schedules')->insert([
                    'id' => (string) Str::uuid7(),
                    'semester_id' => $semester->id,
                    'class_id' => $class->id,
                    'subject_id' => $subjects[$code],
                    'teacher_id' => $teacherId,
                    'time_slot_id' => $slotId,
                    'day_of_week' => $day,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $this->classBusy[$class->id][$day][$slotId] = true;
                $this->teacherBusy[$teacherId][$day][$slotId] = true;
                $this->subjectPerDay[$class->id][$day][$code] = ($this->subjectPerDay[$class->id][$day][$code] ?? 0) + 1;

                return;
            }
        }

        $this->unplaced++;
    }

    /**
     * Pelengkapan: jam yang belum dapat slot dicarikan tempat dengan guru lain
     * yang free pada slot tersebut (dan dicatat sebagai pengampu tambahan).
     */
    private function repairMissing(array $subjects, $semester, $now): void
    {
        $this->unplaced = 0;

        $classes = DB::table('classes')->whereNull('deleted_at')->get(['id', 'program_type']);

        foreach ($classes as $class) {
            $plan = self::PLAN[$class->program_type] ?? self::PLAN['reguler'];

            foreach ($plan as $code => [$hours]) {
                if (! isset($subjects[$code])) {
                    continue;
                }

                $sudah = DB::table('schedules')
                    ->where('semester_id', $semester->id)
                    ->where('class_id', $class->id)
                    ->where('subject_id', $subjects[$code])
                    ->count();

                for ($i = $sudah; $i < $hours; $i++) {
                    if (! $this->repairOne($class, $code, $subjects, $semester, $now)) {
                        $this->unplaced++;
                    }
                }
            }
        }
    }

    private function repairOne($class, string $code, array $subjects, $semester, $now): bool
    {
        $utama = $this->teacherFor[$class->id][$code] ?? null;

        $kandidat = DB::table('teachers')->whereNull('deleted_at')->pluck('id')->all();

        if ($utama) {
            $kandidat = array_values(array_unique(array_merge([$utama], $kandidat)));
        }

        for ($day = 1; $day <= 5; $day++) {
            foreach ($this->slotIds as $slotId) {
                $terpakai = DB::table('schedules')
                    ->where('semester_id', $semester->id)
                    ->where('class_id', $class->id)
                    ->where('day_of_week', $day)
                    ->where('time_slot_id', $slotId)
                    ->exists();

                if ($terpakai) {
                    continue;
                }

                foreach ($kandidat as $teacherId) {
                    $guruSibuk = DB::table('schedules')
                        ->where('semester_id', $semester->id)
                        ->where('teacher_id', $teacherId)
                        ->where('day_of_week', $day)
                        ->where('time_slot_id', $slotId)
                        ->exists();

                    if ($guruSibuk) {
                        continue;
                    }

                    DB::table('schedules')->insert([
                        'id' => (string) Str::uuid7(),
                        'semester_id' => $semester->id,
                        'class_id' => $class->id,
                        'subject_id' => $subjects[$code],
                        'teacher_id' => $teacherId,
                        'time_slot_id' => $slotId,
                        'day_of_week' => $day,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    // Catatan: teaching_assignments unik per (semester, kelas, mapel),
                    // jadi pengampu utama tetap pemegang penilaian mapel ini.
                    return true;
                }
            }
        }

        return false;
    }

    private function seedComponentsAndGrades(array $subjects, array $classes, $semester, $now): void
    {
        $programSubjects = ['TAHFIDZ', 'ARB', 'CAM-MATH', 'CAM-SCI', 'CAM-ENG'];
        $bobot = ['TGS' => 25, 'UTS' => 30, 'UAS' => 35, 'SIKAP' => 10];
        $periode = DB::table('grade_periods')->where('semester_id', $semester->id)->where('status', 'open')->first()
            ?? DB::table('grade_periods')->where('semester_id', $semester->id)->first();

        if (! $periode) {
            return;
        }

        $programClasses = DB::table('classes')
            ->whereIn('program_type', ['tahfidz', 'icp'])
            ->whereNull('deleted_at')
            ->get(['id', 'program_type']);

        foreach ($programClasses as $class) {
            // Nilai untuk SEMUA mapel di rombel ini (umum + khusus), bukan hanya mapel program,
            // supaya e-rapor tidak hanya berisi 2 baris.
            $codes = array_keys(self::PLAN[$class->program_type] ?? self::PLAN['reguler']);

            foreach ($codes as $code) {
                if (! isset($subjects[$code])) {
                    continue;
                }

                foreach ($bobot as $kodeKomponen => $weight) {
                    $componentId = DB::table('grade_components')
                        ->where('class_id', $class->id)
                        ->where('subject_id', $subjects[$code])
                        ->where('code', $kodeKomponen)
                        ->value('id');

                    if (! $componentId) {
                        $componentId = (string) Str::uuid7();
                        DB::table('grade_components')->insert([
                            'id' => $componentId,
                            'subject_id' => $subjects[$code],
                            'semester_id' => $semester->id,
                            'class_id' => $class->id,
                            'code' => $kodeKomponen,
                            'name' => match ($kodeKomponen) {
                                'TGS' => 'Tugas / Formatif',
                                'UTS' => 'Sumatif Tengah Semester',
                                'UAS' => 'Sumatif Akhir Semester',
                                default => 'Sikap',
                            },
                            'weight' => $weight,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    // Contoh nilai agar mapel program tampil di buku nilai & rapor.
                    $students = DB::table('class_students')->where('class_id', $class->id)->pluck('student_id');
                    foreach ($students as $idx => $studentId) {
                        $ada = DB::table('grades')
                            ->where('student_id', $studentId)
                            ->where('component_id', $componentId)
                            ->where('period_id', $periode->id)
                            ->exists();

                        if ($ada) {
                            continue;
                        }

                        DB::table('grades')->insert([
                            'id' => (string) Str::uuid7(),
                            'student_id' => $studentId,
                            'component_id' => $componentId,
                            'period_id' => $periode->id,
                            // Variasi per (siswa, mapel, komponen) supaya nilai antar mapel tidak identik.
                            'value' => 72 + (crc32($code.$studentId.$kodeKomponen) % 26),
                            'source' => 'manual',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }
        }
    }
}
