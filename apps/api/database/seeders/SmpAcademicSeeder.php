<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Menyambungkan mapel ↔ guru ↔ rombel supaya portal guru bisa dipakai:
 * input nilai, menyusun ujian, membuat soal, jurnal & presensi.
 *
 * Yang dibuat (idempoten, aman dijalankan ulang):
 *   1. subjects             : 12 mapel Kurikulum Merdeka SMP
 *   2. semesters            : Ganjil (aktif) & Genap untuk tahun ajaran berjalan
 *   3. time_slots           : 8 jam pelajaran/hari
 *   4. rooms                : beberapa ruang untuk plotting ujian
 *   5. teaching_assignments : guru pengampu per mapel per kelas (+ jam/minggu)
 *   6. grade_components     : Tugas 25 / UTS 30 / UAS 35 / Sikap 10 per mapel-kelas
 *   7. schedules            : jadwal mingguan 5 hari, tanpa guru bentrok
 *
 * Pemetaan guru → mapel: gelar pada nama dipakai lebih dulu (S.Pd.I → PAI,
 * S.Or → PJOK, S.Kom → Informatika, S.Sn → Seni). Sisanya dibagi berurutan
 * menurut nama ke mapel yang belum terisi. Data guru dari SmpDataSeeder tidak
 * menyimpan mapel, jadi pemetaan ini eksplisit dan deterministik.
 *
 *   php artisan db:seed --class=SmpAcademicSeeder
 */
class SmpAcademicSeeder extends Seeder
{
    private const SCHOOL_ID = '00000000-0000-0000-0000-000000000001';

    /**
     * kode => [nama, kelompok, jam/minggu, jumlah guru]
     *
     * Total 38 jam/kelas (dari 40 slot tersedia). PPKN & PJOK sengaja 2 jam:
     * masing-masing hanya 1 guru untuk 12 kelas, dan pada 3 jam keduanya
     * memakai 36 dari 40 slot sehingga tidak ada ruang untuk menyusun jadwal
     * tanpa bentrok.
     */
    private const MAPEL = [
        'PAI'   => ['Pendidikan Agama Islam & Budi Pekerti', 'wajib', 3, 2],
        'PPKN'  => ['Pendidikan Pancasila', 'wajib', 2, 1],
        'BIND'  => ['Bahasa Indonesia', 'wajib', 6, 2],
        'MTK'   => ['Matematika', 'wajib', 5, 2],
        'IPA'   => ['Ilmu Pengetahuan Alam (IPA)', 'wajib', 5, 2],
        'IPS'   => ['Ilmu Pengetahuan Sosial (IPS)', 'wajib', 4, 2],
        'BING'  => ['Bahasa Inggris', 'wajib', 4, 2],
        'PJOK'  => ['PJOK', 'wajib', 2, 1],
        'INF'   => ['Informatika', 'wajib', 2, 1],
        'SENI'  => ['Seni Budaya', 'wajib', 2, 1],
        'PRAK'  => ['Prakarya', 'wajib', 2, 1],
        'BK'    => ['Bimbingan Konseling (BK)', 'wajib', 1, 1],
    ];

    /** Gelar yang menandai mapel tertentu (dipakai lebih dulu saat memetakan guru). */
    private const GELAR_MAPEL = [
        'S.Pd.I.' => 'PAI',
        'S.Or.'   => 'PJOK',
        'S.Kom.'  => 'INF',
        'S.Sn.'   => 'SENI',
    ];

    /** Jam pelajaran (Senin–Jumat, 8 jam/hari). */
    private const JAM = [
        ['01', '07:00:00', '07:40:00'],
        ['02', '07:40:00', '08:20:00'],
        ['03', '08:20:00', '09:00:00'],
        ['04', '09:20:00', '10:00:00'],
        ['05', '10:00:00', '10:40:00'],
        ['06', '10:40:00', '11:20:00'],
        ['07', '12:10:00', '12:50:00'],
        ['08', '12:50:00', '13:30:00'],
    ];

    public function run(): void
    {
        $now = now();

        $semester = $this->seedSemesters($now);
        $subjects = $this->seedSubjects($now);
        $slots = $this->seedTimeSlots($now);
        $this->seedRooms($now);

        $teachersBySubject = $this->mapTeachersToSubjects($subjects);

        $this->seedTeachingAssignments($subjects, $teachersBySubject, $semester, $now);
        $this->seedGradeComponents($subjects, $semester, $now);
        $this->seedSchedules($subjects, $teachersBySubject, $semester, $slots, $now);

        $this->command?->info('Selesai: mapel, semester, penugasan guru, komponen nilai, dan jadwal tersambung.');
    }

    // ------------------------------------------------------------------
    //  1. SEMESTER
    // ------------------------------------------------------------------
    private function seedSemesters($now): object
    {
        $year = DB::table('academic_years')->where('is_active', true)->first()
            ?? DB::table('academic_years')->orderByDesc('start_date')->first();

        if (! $year) {
            throw new \RuntimeException('Tidak ada academic_years. Jalankan SmpDataSeeder lebih dulu.');
        }

        $defs = [
            ['Ganjil', $year->start_date, $this->tengahTahun($year->start_date, $year->end_date), true],
            ['Genap', $this->tengahTahun($year->start_date, $year->end_date), $year->end_date, false],
        ];

        $active = null;
        foreach ($defs as [$name, $start, $end, $isActive]) {
            $existing = DB::table('semesters')
                ->where('academic_year_id', $year->id)
                ->where('name', $name)
                ->first();

            if ($existing) {
                if ($isActive) {
                    $active = $existing;
                }

                continue;
            }

            $id = (string) Str::uuid7();
            DB::table('semesters')->insert([
                'id' => $id,
                'academic_year_id' => $year->id,
                'name' => $name,
                'start_date' => $start,
                'end_date' => $end,
                'is_active' => $isActive,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($isActive) {
                $active = DB::table('semesters')->where('id', $id)->first();
            }
        }

        // Pastikan hanya satu semester aktif.
        DB::table('semesters')->where('academic_year_id', $year->id)->where('id', '!=', $active->id)
            ->update(['is_active' => false]);

        $this->command?->info("  - Semester aktif: {$year->name} {$active->name}");

        return $active;
    }

    private function tengahTahun(string $start, string $end): string
    {
        $s = strtotime($start);
        $e = strtotime($end);

        return date('Y-m-d', $s + intdiv($e - $s, 2));
    }

    // ------------------------------------------------------------------
    //  2. MAPEL
    // ------------------------------------------------------------------
    /** @return array<string, object> kode => row subjects */
    private function seedSubjects($now): array
    {
        $out = [];

        foreach (self::MAPEL as $code => [$name, $group, $hours, $teacherCount]) {
            $existing = DB::table('subjects')->where('code', $code)->where('school_id', self::SCHOOL_ID)->first();

            if (! $existing) {
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
                $existing = DB::table('subjects')->where('id', $id)->first();
            }

            $out[$code] = $existing;
        }

        $this->command?->info('  - '.count($out).' mata pelajaran siap.');

        return $out;
    }

    // ------------------------------------------------------------------
    //  3. JAM PELAJARAN & RUANG
    // ------------------------------------------------------------------
    private function seedTimeSlots($now): array
    {
        $out = [];
        foreach (self::JAM as [$name, $start, $end]) {
            $existing = DB::table('time_slots')
                ->where('school_id', self::SCHOOL_ID)
                ->where('start_time', $start)
                ->first();

            if (! $existing) {
                $id = (string) Str::uuid7();
                DB::table('time_slots')->insert([
                    'id' => $id,
                    'school_id' => self::SCHOOL_ID,
                    'name' => 'Jam '.$name,
                    'start_time' => $start,
                    'end_time' => $end,
                    'day_type' => 'efektif',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $existing = DB::table('time_slots')->where('id', $id)->first();
            }

            $out[] = $existing;
        }

        $this->command?->info('  - '.count($out).' jam pelajaran siap.');

        return $out;
    }

    private function seedRooms($now): void
    {
        $defs = [
            ['LABKOM1', 'Lab Komputer 1', 'laboratorium', 32],
            ['LABIPA', 'Lab IPA', 'laboratorium', 32],
            ['PERPUS', 'Perpustakaan', 'perpustakaan', 40],
            ['AULA', 'Aula Serbaguna', 'aula', 120],
        ];

        $n = 0;
        foreach ($defs as [$code, $name, $type, $capacity]) {
            $exists = DB::table('rooms')->where('code', $code)->where('school_id', self::SCHOOL_ID)->exists();
            if ($exists) {
                continue;
            }
            DB::table('rooms')->insert([
                'id' => (string) Str::uuid7(),
                'school_id' => self::SCHOOL_ID,
                'code' => $code,
                'name' => $name,
                'room_type' => $type,
                'capacity' => $capacity,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $n++;
        }

        $this->command?->info("  - {$n} ruang ditambahkan.");
    }

    // ------------------------------------------------------------------
    //  4. PEMETAAN GURU -> MAPEL
    // ------------------------------------------------------------------
    /** @return array<string, array<int, object>> kode mapel => daftar guru */
    private function mapTeachersToSubjects(array $subjects): array
    {
        $teachers = DB::table('teachers')->whereNull('deleted_at')->orderBy('full_name')->get();
        if ($teachers->isEmpty()) {
            throw new \RuntimeException('Tidak ada guru. Jalankan SmpDataSeeder lebih dulu.');
        }

        $buckets = [];
        foreach (self::MAPEL as $code => $def) {
            $buckets[$code] = [];
        }

        // 1) Gelar yang menandai mapel tertentu.
        $sisa = [];
        foreach ($teachers as $t) {
            $matched = null;
            foreach (self::GELAR_MAPEL as $gelar => $code) {
                if (str_contains($t->full_name, $gelar)) {
                    $matched = $code;
                    break;
                }
            }

            if ($matched && count($buckets[$matched]) < self::MAPEL[$matched][3]) {
                $buckets[$matched][] = $t;
            } else {
                $sisa[] = $t;
            }
        }

        // 2) Sisanya dibagi berurutan ke mapel yang kuotanya belum penuh.
        $i = 0;
        foreach (self::MAPEL as $code => $def) {
            while (count($buckets[$code]) < $def[3] && $i < count($sisa)) {
                $buckets[$code][] = $sisa[$i];
                $i++;
            }
        }

        // 3) Kalau guru kurang, isi dari mapel lain yang punya lebih dari 1.
        foreach ($buckets as $code => $list) {
            if (! empty($list)) {
                continue;
            }
            foreach ($buckets as $other => $otherList) {
                if (count($otherList) > 1) {
                    $buckets[$code][] = array_pop($buckets[$other]);
                    break;
                }
            }
        }

        $ringkas = [];
        foreach ($buckets as $code => $list) {
            $ringkas[] = $code.'='.count($list);
        }
        $this->command?->info('  - Guru per mapel: '.implode(', ', $ringkas));

        return $buckets;
    }

    // ------------------------------------------------------------------
    //  5. PENUGASAN GURU (teaching_assignments)
    // ------------------------------------------------------------------
    private function seedTeachingAssignments(array $subjects, array $teachersBySubject, object $semester, $now): void
    {
        $classes = DB::table('classes')->whereNull('deleted_at')->orderBy('name')->get();
        if ($classes->isEmpty()) {
            throw new \RuntimeException('Tidak ada kelas. Jalankan SmpDataSeeder lebih dulu.');
        }

        $inserted = 0;

        foreach ($subjects as $code => $subject) {
            $gurus = $teachersBySubject[$code] ?? [];
            if (empty($gurus)) {
                continue;
            }

            // Bagikan kelas ke guru mapel ini secara merata.
            foreach ($classes as $idx => $class) {
                $guru = $gurus[$idx % count($gurus)];

                $exists = DB::table('teaching_assignments')
                    ->where('semester_id', $semester->id)
                    ->where('class_id', $class->id)
                    ->where('subject_id', $subject->id)
                    ->exists();

                if ($exists) {
                    // Jam/minggu bisa berubah antar versi seeder — selaraskan.
                    DB::table('teaching_assignments')
                        ->where('semester_id', $semester->id)
                        ->where('class_id', $class->id)
                        ->where('subject_id', $subject->id)
                        ->update(['hours_per_week' => self::MAPEL[$code][2], 'updated_at' => $now]);

                    continue;
                }

                DB::table('teaching_assignments')->insert([
                    'id' => (string) Str::uuid7(),
                    'semester_id' => $semester->id,
                    'class_id' => $class->id,
                    'subject_id' => $subject->id,
                    'teacher_id' => $guru->id,
                    'hours_per_week' => self::MAPEL[$code][2],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $inserted++;
            }
        }

        $this->command?->info("  - {$inserted} penugasan guru (mapel x kelas) dibuat.");
    }

    // ------------------------------------------------------------------
    //  6. KOMPONEN NILAI
    // ------------------------------------------------------------------
    private function seedGradeComponents(array $subjects, object $semester, $now): void
    {
        $classes = DB::table('classes')->whereNull('deleted_at')->get();
        $komponen = [
            ['TGS', 'Tugas & Praktik', 25],
            ['UTS', 'Ujian Tengah Semester', 30],
            ['UAS', 'Ujian Akhir Semester', 35],
            ['SIKAP', 'Sikap & Kehadiran', 10],
        ];

        $inserted = 0;
        foreach ($subjects as $subject) {
            foreach ($classes as $class) {
                foreach ($komponen as [$code, $name, $weight]) {
                    $exists = DB::table('grade_components')
                        ->where('subject_id', $subject->id)
                        ->where('class_id', $class->id)
                        ->where('semester_id', $semester->id)
                        ->where('code', $code)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('grade_components')->insert([
                        'id' => (string) Str::uuid7(),
                        'subject_id' => $subject->id,
                        'semester_id' => $semester->id,
                        'class_id' => $class->id,
                        'code' => $code,
                        'name' => $name,
                        'weight' => $weight,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $inserted++;
                }
            }
        }

        $this->command?->info("  - {$inserted} komponen nilai dibuat (bobot total 100 per mapel-kelas).");
    }

    // ------------------------------------------------------------------
    //  7. JADWAL (bipartit per jam + beberapa percobaan + perbaikan)
    // ------------------------------------------------------------------
    /**
     * Tiap jam diselesaikan sebagai pencocokan bipartit kelas -> guru (Kuhn),
     * sehingga satu guru tidak pernah mengajar dua kelas pada jam yang sama.
     *
     * Data ini padat: PPKN & PJOK masing-masing hanya 1 guru untuk 12 kelas
     * (36 dari 40 jam), jadi pencocokan greedy bisa terpojok. Karena itu
     * dijalankan beberapa percobaan dengan urutan kelas berbeda, diambil hasil
     * terbaik, lalu sisa jam diperbaiki dengan pass penukaran.
     */
    private function seedSchedules(array $subjects, array $teachersBySubject, object $semester, array $slots, $now): void
    {
        if (DB::table('schedules')->where('semester_id', $semester->id)->exists()) {
            $this->command?->info('  - Jadwal sudah ada, dilewati.');

            return;
        }

        $classes = DB::table('classes')->whereNull('deleted_at')->orderBy('name')->get()->values();
        $days = [1, 2, 3, 4, 5];

        // Sisa unit awal per kelas: classId => list of ['subject_id','teacher_id']
        $awal = [];
        foreach ($classes as $class) {
            $units = [];
            foreach ($subjects as $code => $subject) {
                $assignment = DB::table('teaching_assignments')
                    ->where('semester_id', $semester->id)
                    ->where('class_id', $class->id)
                    ->where('subject_id', $subject->id)
                    ->first();

                if (! $assignment) {
                    continue;
                }

                for ($h = 0; $h < self::MAPEL[$code][2]; $h++) {
                    $units[] = ['subject_id' => $subject->id, 'teacher_id' => $assignment->teacher_id];
                }
            }
            $awal[$class->id] = $units;
        }

        $slotGrid = [];
        foreach ($days as $day) {
            foreach ($slots as $i => $slot) {
                $slotGrid[] = ['day' => $day, 'index' => $i, 'slot_id' => $slot->id];
            }
        }

        $best = null;
        $bestScore = -1;

        foreach ([1, 2, 3, 4, 5, 6] as $attempt) {
            [$assignments, $sisa] = $this->cobaSusunJadwal($classes, $slotGrid, $awal, $attempt);

            $terpasang = count($assignments);
            if ($terpasang > $bestScore) {
                $bestScore = $terpasang;
                $best = ['assignments' => $assignments, 'sisa' => $sisa];
            }

            if ($terpasang === count($classes) * count($slotGrid)) {
                break; // sudah penuh
            }
        }

        // Pass perbaikan: tempatkan sisa jam ke slot yang masih longgar.
        $repaired = $this->perbaikiSisa($best['assignments'], $best['sisa'], $slotGrid);

        foreach ($repaired['assignments'] as $a) {
            DB::table('schedules')->insert([
                'id' => (string) Str::uuid7(),
                'semester_id' => $semester->id,
                'class_id' => $a['class_id'],
                'subject_id' => $a['subject_id'],
                'teacher_id' => $a['teacher_id'],
                'room_id' => null,
                'time_slot_id' => $a['slot_id'],
                'day_of_week' => $a['day'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->command?->info('  - '.count($repaired['assignments']).' baris jadwal dibuat untuk '.count($classes).' kelas.');

        foreach ($classes as $class) {
            $left = count($repaired['sisa'][$class->id] ?? []);
            if ($left > 0) {
                $this->command?->warn("    ! {$class->name}: {$left} jam belum terjadwal.");
            }
        }
    }

    /**
     * Satu percobaan penyusunan penuh (semua slot).
     *
     * @return array{0: array<int, array>, 1: array<string, array>} [assignments, sisa per kelas]
     */
    private function cobaSusunJadwal($classes, array $slotGrid, array $awal, int $attempt): array
    {
        $sisa = $awal;
        $assignments = [];

        foreach ($slotGrid as $slotPos => $slot) {
            $matchTeacher = [];
            $matchClass = [];

            // Urutan kelas: percobaan ganjil pakai yang paling terbatas dulu,
            // percobaan genap pakai urutan diacak agar tidak terjebak pola sama.
            $urutan = $classes->all();
            if ($attempt % 2 === 0) {
                mt_srand($attempt * 977 + $slotPos);
                shuffle($urutan);
            } else {
                usort($urutan, fn ($a, $b) => count(array_unique(array_column($sisa[$a->id], 'teacher_id')))
                    <=> count(array_unique(array_column($sisa[$b->id], 'teacher_id'))));
            }

            foreach ($urutan as $class) {
                $visited = [];
                $this->cobaPasangkan($class->id, $sisa, $matchTeacher, $matchClass, $visited);
            }

            foreach ($matchClass as $classId => $teacherId) {
                $idx = null;
                foreach ($sisa[$classId] as $k => $u) {
                    if ($u['teacher_id'] === $teacherId) {
                        $idx = $k;
                        break;
                    }
                }
                if ($idx === null) {
                    continue;
                }

                $unit = $sisa[$classId][$idx];
                unset($sisa[$classId][$idx]);
                $sisa[$classId] = array_values($sisa[$classId]);

                $assignments[] = [
                    'class_id' => $classId,
                    'subject_id' => $unit['subject_id'],
                    'teacher_id' => $teacherId,
                    'day' => $slot['day'],
                    'slot_id' => $slot['slot_id'],
                    'slot_pos' => $slotPos,
                ];
            }
        }

        return [$assignments, $sisa];
    }

    /**
     * Tempelkan jam yang tersisa ke slot di mana kelas belum punya pelajaran
     * dan gurunya juga belum mengajar. Kalau tidak ada slot bebas, tukar dengan
     * pelajaran lain yang gurunya longgar di slot asal.
     *
     * @return array{assignments: array, sisa: array}
     */
    private function perbaikiSisa(array $assignments, array $sisa, array $slotGrid): array
    {
        $occupied = [];   // classId|slotPos => index assignment
        $busyTeacher = []; // teacherId|slotPos => true

        foreach ($assignments as $i => $a) {
            $occupied[$a['class_id'].'|'.$a['slot_pos']] = $i;
            $busyTeacher[$a['teacher_id'].'|'.$a['slot_pos']] = true;
        }

        foreach ($sisa as $classId => $units) {
            foreach ($units as $k => $unit) {
                $placed = false;

                foreach ($slotGrid as $slotPos => $slot) {
                    if (isset($occupied[$classId.'|'.$slotPos])) {
                        continue;
                    }
                    if (isset($busyTeacher[$unit['teacher_id'].'|'.$slotPos])) {
                        continue;
                    }

                    $assignments[] = [
                        'class_id' => $classId,
                        'subject_id' => $unit['subject_id'],
                        'teacher_id' => $unit['teacher_id'],
                        'day' => $slot['day'],
                        'slot_id' => $slot['slot_id'],
                        'slot_pos' => $slotPos,
                    ];
                    $occupied[$classId.'|'.$slotPos] = count($assignments) - 1;
                    $busyTeacher[$unit['teacher_id'].'|'.$slotPos] = true;

                    unset($sisa[$classId][$k]);
                    $placed = true;
                    break;
                }

                if (! $placed) {
                    // Tukar: cari slot milik kelas ini yang gurunya bebas di slot tujuan.
                    foreach ($slotGrid as $slotPos => $slot) {
                        $occIdx = $occupied[$classId.'|'.$slotPos] ?? null;
                        if ($occIdx === null) {
                            continue;
                        }
                        if (isset($busyTeacher[$unit['teacher_id'].'|'.$slotPos])) {
                            continue;
                        }

                        $lain = $assignments[$occIdx];
                        // Guru pelajaran lain itu harus bebas di slot tempat unit ini akan pergi.
                        if (isset($busyTeacher[$lain['teacher_id'].'|'.$slotPos])) {
                            continue;
                        }

                        $assignments[$occIdx]['subject_id'] = $unit['subject_id'];
                        $assignments[$occIdx]['teacher_id'] = $unit['teacher_id'];
                        $busyTeacher[$unit['teacher_id'].'|'.$slotPos] = true;

                        // Unit lama menjadi sisa (akan dicoba lagi di iterasi berikut).
                        $sisa[$classId][] = ['subject_id' => $lain['subject_id'], 'teacher_id' => $lain['teacher_id']];
                        unset($sisa[$classId][$k]);
                        $placed = true;
                        break;
                    }
                }
            }
            $sisa[$classId] = array_values($sisa[$classId] ?? []);
        }

        return ['assignments' => $assignments, 'sisa' => $sisa];
    }

    /** Berapa jam lagi yang masih harus dijadwalkan untuk guru ini di semua kelas. */
    private function bebanTersisa(string $teacherId, array $sisa): int
    {
        $n = 0;
        foreach ($sisa as $units) {
            foreach ($units as $u) {
                if ($u['teacher_id'] === $teacherId) {
                    $n++;
                }
            }
        }

        return $n;
    }

    /**
     * Kuhn's augmenting path: coba beri kelas sebuah guru; kalau guru sudah
     * dipakai kelas lain, coba pindahkan kelas itu ke guru lain.
     */
    private function cobaPasangkan(string $classId, array $sisa, array &$matchTeacher, array &$matchClass, array &$visited): bool
    {
        $kandidat = array_values(array_unique(array_column($sisa[$classId] ?? [], 'teacher_id')));

        // Guru yang bebannya paling padat dicocokkan lebih dulu: kalau tidak,
        // sisa jam kelas bisa menumpuk pada guru yang slotnya sudah habis.
        usort($kandidat, fn ($a, $b) => $this->bebanTersisa($b, $sisa) <=> $this->bebanTersisa($a, $sisa));

        foreach ($kandidat as $teacherId) {
            if (isset($visited[$teacherId])) {
                continue;
            }
            $visited[$teacherId] = true;

            $pemilik = $matchTeacher[$teacherId] ?? null;

            if ($pemilik === null || $this->cobaPasangkan($pemilik, $sisa, $matchTeacher, $matchClass, $visited)) {
                $matchTeacher[$teacherId] = $classId;
                $matchClass[$classId] = $teacherId;

                return true;
            }
        }

        return false;
    }
}
