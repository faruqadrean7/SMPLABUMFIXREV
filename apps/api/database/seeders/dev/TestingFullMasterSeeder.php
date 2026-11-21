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
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradeLevel;
use App\Models\GradePeriod;
use App\Models\Guardian;
use App\Models\LearningMaterial;
use App\Models\PointType;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Role;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentPoint;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\TeachingMaterial;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * SEEDER DATA MASTER LENGKAP UNTUK TESTING / SIMULASI.
 *
 * Membuat: 3 tingkat (7,8,9), 10 mapel, 20 guru (beda mapel/tingkat, tiap guru wali 1 kelas),
 * 21 kelas (7 per tingkat), 420 siswa (20/kelas), 420 orang tua, jadwal seminggu penuh,
 * modul ajar, tugas, dan SEMUA tipe ujian (kuis/harian/STS/SAS/remedial/pengayaan),
 * plus poin & nilai contoh. Menulis file kredensial 19_KREDENSIAL_AKUN_TESTING.md.
 *
 * Password semua akun = SEEDER_PASSWORD (default: Password123!).
 * DILARANG di production.
 *
 * Jalankan: php artisan db:seed --class=TestingFullMasterSeeder
 */
class TestingFullMasterSeeder extends Seeder
{
    public const SCHOOL_ID = '00000000-0000-0000-0000-000000000001';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('TestingFullMasterSeeder DILARANG di production.');
        }

        $password = env('SEEDER_PASSWORD', 'Password123!');
        $hash = Hash::make($password);
        $schoolId = self::SCHOOL_ID;
        mt_srand(2026);

        // Kredensial yang akan ditulis ke MD
        $cred = ['staff' => [], 'teachers' => [], 'students' => [], 'guardians' => []];

        DB::transaction(function () use ($hash, $schoolId, &$cred) {
            // Role & permission dasar + sekolah
            (new DatabaseSeeder)->run();

            $rolesByCode = Role::pluck('id', 'code');
            $roleId = fn (string $c) => $rolesByCode[$c] ?? Role::firstOrCreate(['code' => $c], ['name' => ucfirst($c)])->id;

            // ---- Akun back-office (semua portal bisa diuji) ----
            $staffAccounts = [
                ['username' => 'superadmin', 'email' => 'superadmin@labschool.sch.id', 'role' => 'super_admin', 'label' => 'Super Admin'],
                ['username' => 'kepsek', 'email' => 'kepsek@labschool.sch.id', 'role' => 'principal', 'label' => 'Kepala Sekolah'],
                ['username' => 'bendahara', 'email' => 'bendahara@labschool.sch.id', 'role' => 'bendahara', 'label' => 'Bendahara'],
                ['username' => 'tatausaha', 'email' => 'tu@labschool.sch.id', 'role' => 'admin_tu', 'label' => 'Admin Tata Usaha'],
                ['username' => 'bk', 'email' => 'bk@labschool.sch.id', 'role' => 'counselor', 'label' => 'Guru BK / Konselor'],
            ];
            foreach ($staffAccounts as $sa) {
                $u = $this->upsertUser($sa['username'], $sa['email'], $schoolId, $hash);
                $u->roles()->syncWithoutDetaching([$roleId($sa['role'])]);
                // BK juga punya profil teacher agar portal guru/BK berfungsi
                if ($sa['role'] === 'counselor') {
                    $tBk = Teacher::firstOrNew(['user_id' => $u->id]);
                    $tBk->school_id = $schoolId;
                    if (! $tBk->full_name || $tBk->full_name === 'Dra. Konselor BK') {
                        $tBk->full_name = 'Dra. Hj. Nurul Hidayati, M.Pd.';
                    }
                    $tBk->position = 'Bimbingan Konseling';
                    $tBk->status = 'tetap';
                    $tBk->save();
                }
                $cred['staff'][] = ['username' => $sa['username'], 'label' => $sa['label'], 'email' => $sa['email']];
            }

            // ---- Tahun ajaran & semester ----
            $now = Carbon::now();
            $yearStart = $now->month >= 7 ? $now->year : $now->year - 1;
            $ay = AcademicYear::where('school_id', $schoolId)->where('is_active', true)->first();
            if (! $ay) {
                $ay = AcademicYear::firstOrCreate(
                    ['school_id' => $schoolId, 'name' => $yearStart.'/'.($yearStart + 1)],
                    ['start_date' => $yearStart.'-07-01', 'end_date' => ($yearStart + 1).'-06-30', 'is_active' => true]
                );
            }
            $targetSemName = $now->month >= 7 ? 'Ganjil' : 'Genap';
            $sem = Semester::where('academic_year_id', $ay->id)->where('is_active', true)->first();
            if (! $sem) {
                $sem = Semester::where('academic_year_id', $ay->id)
                    ->where(function ($q) use ($targetSemName) {
                        $q->where('name', $targetSemName)
                            ->orWhere('name', 'Semester '.$targetSemName);
                    })
                    ->first();
            }
            if (! $sem) {
                $sem = Semester::create([
                    'academic_year_id' => $ay->id,
                    'name' => $targetSemName,
                    'start_date' => $yearStart.'-07-01',
                    'end_date' => ($yearStart + 1).'-06-30',
                    'is_active' => true,
                ]);
            }

            // ---- Tingkat (7, 8, 9) ----
            $levels = [];
            foreach (['VII' => 7, 'VIII' => 8, 'IX' => 9] as $rom => $n) {
                $levels[$n] = GradeLevel::where('school_id', $schoolId)
                    ->where(function ($q) use ($rom, $n) {
                        $q->where('sort_order', $n)
                            ->orWhere('name', "Kelas {$rom}")
                            ->orWhere('name', "Kelas {$n}");
                    })
                    ->first();
                if (! $levels[$n]) {
                    $levels[$n] = GradeLevel::create([
                        'school_id' => $schoolId,
                        'name' => "Kelas {$rom}",
                        'sort_order' => $n,
                    ]);
                }
            }

            // ---- Mapel (10) ----
            $subjectsData = [
                ['MTK', 'Matematika', 'umum'],
                ['INDO', 'Bahasa Indonesia', 'umum'],
                ['ENG', 'Bahasa Inggris', 'umum'],
                ['IPA', 'Ilmu Pengetahuan Alam', 'umum'],
                ['IPS', 'Ilmu Pengetahuan Sosial', 'umum'],
                ['INF', 'Informatika', 'umum'],
                ['PAI', 'Pendidikan Agama Islam & Budi Pekerti', 'agama'],
                ['PPKN', 'Pendidikan Pancasila', 'umum'],
                ['PJOK', 'PJOK', 'umum'],
                ['SBK', 'Seni Budaya & Prakarya', 'umum'],
            ];
            $subjects = [];
            foreach ($subjectsData as [$code, $name, $grp]) {
                $subjects[$code] = Subject::firstOrCreate(['code' => $code], ['school_id' => $schoolId, 'name' => $name, 'subject_group' => $grp]);
            }
            $subjectCodes = array_keys($subjects);

            // ---- 20 Guru (beda mapel & tingkat) ----
            $namaGuru = [
                'Hendra Wijaya, S.Pd.', 'Rina Marlina, M.Pd.', 'Bambang Sutrisno, M.Si.', 'Sarah Amelia, S.Pd.',
                'Fajar Pratama, S.Kom.', 'Ahmad Fauzi, S.Pd.I.', 'Tri Wahyuni, S.Pd.', 'Dedi Kurniawan, S.Pd.',
                'Maya Anggraini, S.Sn.', 'Rizki Ramadhan, S.Pd.', 'Siti Nurhaliza, M.Pd.', 'Agus Setiawan, S.Pd.',
                'Dewi Lestari, S.Pd.', 'Eko Prasetyo, S.Kom.', 'Lina Marliana, S.Pd.', 'Yusuf Hamka, S.Pd.I.',
                'Ratna Sari, S.Pd.', 'Joko Susilo, S.Pd.', 'Indah Permata, S.Sn.', 'Budi Hartono, M.Pd.',
            ];
            $teachers = [];            // index => Teacher
            $teachersBySubject = [];   // code => [Teacher,...]
            for ($i = 0; $i < 20; $i++) {
                $num = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
                $subjCode = $subjectCodes[$i % count($subjectCodes)];
                $u = $this->upsertUser('guru'.$num, 'guru'.$num.'@labschool.sch.id', $schoolId, $hash);
                $u->roles()->syncWithoutDetaching([$roleId('teacher'), $roleId('homeroom')]);
                $nip = '1990'.str_pad((string) ($i + 1), 14, '0', STR_PAD_LEFT);
                $t = Teacher::where('user_id', $u->id)->first() ?? Teacher::where('nip', $nip)->first();
                if (! $t) {
                    $t = Teacher::create([
                        'user_id' => $u->id,
                        'school_id' => $schoolId,
                        'nip' => $nip,
                        'full_name' => $namaGuru[$i],
                        'position' => 'Guru '.$subjects[$subjCode]->name,
                        'status' => 'tetap',
                    ]);
                } else {
                    $t->user_id = $u->id;
                    $t->school_id = $schoolId;
                    $t->full_name = $namaGuru[$i];
                    $t->position = 'Guru '.$subjects[$subjCode]->name;
                    $t->save();
                }
                $teachers[$i] = $t;
                $teachersBySubject[$subjCode][] = $t;
                $cred['teachers'][] = ['username' => 'guru'.$num, 'name' => $namaGuru[$i], 'mapel' => $subjects[$subjCode]->name];
            }
            // Pastikan tiap mapel punya minimal 1 guru
            foreach ($subjectCodes as $c) {
                if (empty($teachersBySubject[$c])) {
                    $teachersBySubject[$c] = [$teachers[0]];
                }
            }

            // ---- 21 Kelas (7 per tingkat) + ruang per kelas ----
            $classes = [];
            $classIndex = 0;
            foreach ([7 => 'VII', 8 => 'VIII', 9 => 'IX'] as $lvlNum => $rom) {
                for ($s = 0; $s < 7; $s++) {
                    $letter = chr(65 + $s);
                    $label = $lvlNum.$letter; // 7A, 7B, ...
                    $room = Room::firstOrCreate(
                        ['code' => 'R-'.$label],
                        ['school_id' => $schoolId, 'name' => 'Ruang Kelas '.$label, 'room_type' => 'kelas', 'capacity' => 32]
                    );
                    $homeroom = $teachers[$classIndex] ?? null; // 20 guru -> kelas ke-21 tanpa wali

                    // Cari kelas yang sudah ada di tahun ajaran ini
                    $class = SchoolClass::where('academic_year_id', $ay->id)
                        ->where(function ($q) use ($label, $rom, $letter) {
                            $q->where('name', 'Kelas '.$label)
                                ->orWhere('name', $rom.' '.$letter)
                                ->orWhere('name', 'Kelas '.$rom.' '.$letter)
                                ->orWhere('name', 'Kelas '.$rom.'-'.$letter);
                        })
                        ->first();

                    if (! $class) {
                        $class = SchoolClass::create([
                            'school_id' => $schoolId,
                            'academic_year_id' => $ay->id,
                            'name' => 'Kelas '.$label,
                            'grade_level_id' => $levels[$lvlNum]->id,
                            'homeroom_teacher_id' => $homeroom?->id,
                            'capacity' => 32,
                        ]);
                    } elseif ($homeroom && ! $class->homeroom_teacher_id) {
                        $class->homeroom_teacher_id = $homeroom->id;
                        $class->save();
                    }

                    $classes[] = ['model' => $class, 'label' => $label, 'level' => $lvlNum, 'room' => $room];
                    $classIndex++;
                }
            }

            // ---- Time slots (6 jam pelajaran) ----
            $slotTimes = [
                ['Jam ke-1', '07:00', '07:40'], ['Jam ke-2', '07:40', '08:20'], ['Jam ke-3', '08:20', '09:00'],
                ['Jam ke-4', '09:20', '10:00'], ['Jam ke-5', '10:00', '10:40'], ['Jam ke-6', '10:40', '11:20'],
            ];
            $slots = [];
            foreach ($slotTimes as [$nm, $st, $en]) {
                $slots[] = TimeSlot::firstOrCreate(['school_id' => $schoolId, 'name' => $nm], ['start_time' => $st, 'end_time' => $en, 'day_type' => 'reguler']);
            }

            // ---- Jadwal seminggu penuh (Senin-Jumat x 6 jam) + penugasan mengajar ----
            // Matriks jadwal bebas bentrok (0 teacher clash, 0 room clash, 1 guru per mapel per kelas)
            // 20 guru: 10 guru genap mengajar 11 kelas genap, 10 guru ganjil mengajar 10 kelas ganjil
            $slotsCount = count($slots);
            for ($day = 1; $day <= 5; $day++) {
                foreach ($slots as $slotIdx => $slot) {
                    $p = ($day - 1) * $slotsCount + $slotIdx; // period 0 .. 29

                    // 1) 10 Kelas Ganjil (ci = 1, 3, ..., 19)
                    for ($k = 0; $k < 10; $k++) {
                        $ci = 2 * $k + 1;
                        if (! isset($classes[$ci])) {
                            continue;
                        }
                        $c = $classes[$ci];
                        $sIdx = ($k + $p) % count($subjectCodes);
                        $subjCode = $subjectCodes[$sIdx];
                        $subject = $subjects[$subjCode];
                        $pool = $teachersBySubject[$subjCode];
                        $teacher = (count($pool) > 1) ? $pool[1] : $pool[0];

                        TeachingAssignment::updateOrCreate(
                            ['semester_id' => $sem->id, 'class_id' => $c['model']->id, 'subject_id' => $subject->id],
                            ['teacher_id' => $teacher->id, 'hours_per_week' => 3]
                        );

                        Schedule::updateOrCreate(
                            ['semester_id' => $sem->id, 'class_id' => $c['model']->id, 'day_of_week' => $day, 'time_slot_id' => $slot->id],
                            ['subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'room_id' => $c['room']->id]
                        );
                    }

                    // 2) 11 Kelas Genap (ci = 0, 2, ..., 20): 10 kelas aktif, 1 kelas istirahat/mandiri
                    $freeM = $p % 11;
                    for ($m = 0; $m < 11; $m++) {
                        if ($m === $freeM) {
                            continue;
                        }
                        $ci = 2 * $m;
                        if (! isset($classes[$ci])) {
                            continue;
                        }
                        $c = $classes[$ci];
                        $r = ($m - $freeM + 11) % 11;
                        $sIdx = ($r - 1 + $p) % count($subjectCodes);
                        $subjCode = $subjectCodes[$sIdx];
                        $subject = $subjects[$subjCode];
                        $pool = $teachersBySubject[$subjCode];
                        $teacher = $pool[0];

                        TeachingAssignment::updateOrCreate(
                            ['semester_id' => $sem->id, 'class_id' => $c['model']->id, 'subject_id' => $subject->id],
                            ['teacher_id' => $teacher->id, 'hours_per_week' => 3]
                        );

                        Schedule::updateOrCreate(
                            ['semester_id' => $sem->id, 'class_id' => $c['model']->id, 'day_of_week' => $day, 'time_slot_id' => $slot->id],
                            ['subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'room_id' => $c['room']->id]
                        );
                    }
                }
            }

            // ---- File dummy untuk lampiran ----
            $dummyFile = File::firstOrCreate(
                ['object_key' => 'seeds/contoh-dokumen.pdf'],
                ['school_id' => $schoolId, 'bucket' => 'documents', 'mime_type' => 'application/pdf', 'size_bytes' => 102400, 'visibility' => 'privat']
            );

            // ---- 420 Siswa (20/kelas) + 420 Orang Tua (Nama Bervariasi & Unik) ----
            $firstMale = [
                'Aditya', 'Adrian', 'Ageng', 'Ahmad', 'Alif', 'Andika', 'Arga', 'Arya', 'Bagas', 'Bayu',
                'Bima', 'Bintang', 'Cakra', 'Danendra', 'Daffa', 'Damar', 'Dimas', 'Dzaki', 'Erlangga', 'Fadhil',
                'Fahri', 'Fajar', 'Farhan', 'Fatih', 'Galang', 'Galih', 'Gilang', 'Hafizh', 'Hanif', 'Ilham',
                'Iqbal', 'Irfan', 'Jalu', 'Kevin', 'Mahendra', 'Malik', 'Maulana', 'Nabil', 'Naufal', 'Pandu',
                'Pratama', 'Raditya', 'Rafif', 'Rangga', 'Rayhan', 'Rendy', 'Revaldo', 'Rian', 'Rizky', 'Satria',
                'Tegar', 'Wildan', 'Yoga', 'Zaki', 'Ananda', 'Aldo', 'Bramantyo', 'Christian', 'Davin', 'Ezekiel',
            ];

            $firstFemale = [
                'Adelia', 'Aisyah', 'Amanda', 'Amelia', 'Anindya', 'Annisa', 'Aqila', 'Arina', 'Aurel', 'Ayudia',
                'Bilqis', 'Cantika', 'Chelsea', 'Citra', 'Clarissa', 'Dara', 'Dea', 'Devi', 'Dewi', 'Dinda',
                'Elisa', 'Febiola', 'Felicia', 'Fitri', 'Gita', 'Hana', 'Hanum', 'Hilya', 'Indah', 'Jessica',
                'Kayla', 'Keisha', 'Kinanti', 'Laras', 'Luna', 'Marsya', 'Maudy', 'Maya', 'Melati', 'Nabila',
                'Nadia', 'Naura', 'Nayla', 'Putri', 'Raisa', 'Rania', 'Safira', 'Salma', 'Salsa', 'Syifa',
                'Tiara', 'Vania', 'Zahra', 'Alika', 'Bella', 'Chandra', 'Dahlia', 'Fathia', 'Gracia', 'Hesti',
            ];

            $middleNames = [
                'Agung', 'Bagus', 'Cahya', 'Darma', 'Elang', 'Fajar', 'Genta', 'Hadi', 'Indra', 'Jaya',
                'Kencana', 'Laksana', 'Mulya', 'Nugraha', 'Perkasa', 'Raden', 'Satya', 'Tama', 'Utama', 'Wijaya',
                'Anggraini', 'Bestari', 'Citrawati', 'Dewanti', 'Endah', 'Fitria', 'Gayatri', 'Hapsari', 'Iswari', 'Juwita',
                'Kusuma', 'Lestari', 'Maharani', 'Novita', 'Pertiwi', 'Ratnasari', 'Sukma', 'Trisna', 'Utami', 'Wulandari',
            ];

            $lastNames = [
                'Alamsyah', 'Ananta', 'Arifin', 'Baskoro', 'Basri', 'Budiman', 'Chaniago', 'Darsono', 'Fauzan', 'Ginting',
                'Hamzah', 'Harahap', 'Hardiansyah', 'Hartono', 'Hasyim', 'Hutapea', 'Iskandar', 'Kusumo', 'Lubis', 'Mahardika',
                'Mansyur', 'Marpaung', 'Nasution', 'Pamungkas', 'Pasaribu', 'Perdana', 'Pratama', 'Purnomo', 'Purwanto', 'Rasyid',
                'Rohman', 'Santoso', 'Saputro', 'Sasmita', 'Simanjuntak', 'Siregar', 'Subagyo', 'Sudarsono', 'Suherman', 'Sulistyo',
                'Sumarno', 'Suryanto', 'Susanto', 'Sutedjo', 'Suwandi', 'Syahputra', 'Tanjung', 'Tarigan', 'Utomo', 'Wahyudi',
                'Wibisono', 'Widyatama', 'Yulianto', 'Zulkarnaen', 'Firmansyah', 'Kurniawan', 'Ramadhan', 'Saputra', 'Wibowo', 'Nugroho',
            ];

            // Bank nama orang tua (Bapak / Ibu)
            $fatherFirst = [
                'Bambang', 'Joko', 'Sugeng', 'Slamet', 'Hendro', 'Agus', 'Dwi', 'Tri', 'Eko', 'Heru',
                'Budi', 'Widodo', 'Yudi', 'Agung', 'Totok', 'Arif', 'Hadi', 'Gunawan', 'Supriyadi', 'Hariyanto',
                'Wawan', 'Rahmat', 'Suroso', 'Priyono', 'Danang', 'Edi', 'Mulyono', 'Rudi', 'Sunardi', 'Teguh',
                'Wahyu', 'Yanto', 'Zainal', 'Achmad', 'Anton', 'Anwar', 'Basuki', 'Benny', 'Choirul', 'Dedy',
                'Firman', 'Herman', 'Indra', 'Johan', 'Lukman', 'Mansyur', 'Muchtar', 'Ruslan', 'Sigit', 'Syahrul',
                'Taufik', 'Usman', 'Victor', 'Wibowo', 'Sukirno', 'Sujatmiko', 'Setyawan', 'Kusnadi', 'Purwanto', 'Subagyo',
            ];

            $motherFirst = [
                'Sri', 'Siti', 'Endang', 'Retno', 'Wahyuni', 'Nurul', 'Tri', 'Dwi', 'Rina', 'Dewi',
                'Eni', 'Yuni', 'Titik', 'Yayuk', 'Sumarni', 'Sulastri', 'Suhartini', 'Ratna', 'Murtini', 'Nanik',
                'Dian', 'Erna', 'Indah', 'Lina', 'Maya', 'Nining', 'Rini', 'Santi', 'Tutik', 'Wati',
                'Yuliana', 'Ani', 'Asih', 'Bintari', 'Christina', 'Darmi', 'Elly', 'Farida', 'Hartati', 'Ismawati',
                'Juwita', 'Kusmiati', 'Laksmi', 'Maryati', 'Ningrum', 'Pudjiastuti', 'Rohana', 'Sukamti', 'Umi', 'Vina',
                'Widya', 'Yusniar', 'Kartika', 'Kusuma', 'Mardiana', 'Nurbaiti', 'Rosmini', 'Sumartini', 'Triana', 'Wulandari',
            ];

            $parentLast = [
                'Santoso', 'Hartono', 'Wijaya', 'Kusuma', 'Wibowo', 'Prasetyo', 'Hidayat', 'Kurniawan', 'Nugroho', 'Firmansyah',
                'Rahayu', 'Astuti', 'Lestari', 'Handayani', 'Anggraini', 'Susanti', 'Puspitasari', 'Purwaningsih', 'Utami', 'Setiawati',
                'Sudarsono', 'Suherman', 'Sulistyo', 'Sumarno', 'Suryanto', 'Susanto', 'Sutedjo', 'Suwandi', 'Syahputra', 'Tanjung',
                'Maharani', 'Indrawati', 'Wardhani', 'Damayanti', 'Kartikasari', 'Purnomo', 'Purwanto', 'Wahyudi', 'Wibisono', 'Widyatama',
                'Budiman', 'Iskandar', 'Pamungkas', 'Perdana', 'Pratama', 'Alamsyah', 'Harahap', 'Siregar', 'Nasution', 'Ginting',
                'Baskoro', 'Darsono', 'Hardiansyah', 'Rohman', 'Saputro', 'Subagyo', 'Yulianto', 'Zulkarnaen', 'Chaniago', 'Simanjuntak',
            ];

            $parentTitles = ['', ' S.E.', ' S.T.', ' S.Pd.', ' M.M.', ' S.H.', ' M.Si.', ' S.Kom.', ' M.Pd.', ' S.Sos.', '', '', '', ''];

            $occupations = [
                'PNS / ASN', 'Karyawan BUMN', 'Wiraswasta', 'Guru', 'Dosen Perguruan Tinggi',
                'Dokter Umum', 'Dokter Spesialis', 'Perawat', 'Apoteker', 'Insinyur Sipil',
                'Arsitek', 'Akuntan Publik', 'Notaris / PPAT', 'Pengacara / Advokat',
                'Manajer Keuangan', 'Konsultan Manajemen', 'Tenaga Ahli IT', 'Anggota TNI',
                'Anggota POLRI', 'Pedagang / Pengusaha Kuliner', 'Peneliti Balitbang',
            ];

            $phonePrefixes = ['0812', '0813', '0821', '0822', '0852', '0853', '0856', '0857', '0817', '0818', '0819', '0878', '0895', '0896'];
            $birthPlaces = ['Malang', 'Malang', 'Surabaya', 'Malang', 'Batu', 'Pasuruan', 'Blitar', 'Kediri', 'Sidoarjo'];

            $gcounter = 0;
            $classStudentsMap = []; // ci => [Student,...]
            foreach ($classes as $ci => $c) {
                for ($n = 1; $n <= 20; $n++) {
                    $gcounter++;
                    $pad = str_pad((string) $gcounter, 3, '0', STR_PAD_LEFT);
                    $isMale = ($gcounter % 2 !== 0);

                    // 1) Nama Siswa 100% Unik & Bervariasi
                    $firstPool = $isMale ? $firstMale : $firstFemale;
                    $idxFirst = ($gcounter - 1) % count($firstPool);
                    $idxMid = (intdiv($gcounter - 1, 2) * 7) % count($middleNames);
                    $idxLast = (($gcounter * 13) + intdiv($gcounter, count($firstPool))) % count($lastNames);

                    if ($gcounter % 3 === 0) {
                        $nm = $firstPool[$idxFirst].' '.$middleNames[$idxMid].' '.$lastNames[$idxLast];
                    } else {
                        $nm = $firstPool[$idxFirst].' '.$lastNames[$idxLast];
                    }

                    $su = $this->upsertUser('siswa'.$pad, 'siswa'.$pad.'@labschool.sch.id', $schoolId, $hash);
                    $su->roles()->syncWithoutDetaching([$roleId('student')]);
                    $nis = '2026'.$pad;
                    $nisn = '00'.str_pad((string) (1000000 + $gcounter), 8, '0', STR_PAD_LEFT);
                    $birthPlace = $birthPlaces[$gcounter % count($birthPlaces)];

                    $student = Student::where('user_id', $su->id)->first()
                        ?? Student::where('nis', $nis)->first()
                        ?? Student::where('nisn', $nisn)->first();

                    if (! $student) {
                        $student = Student::create([
                            'user_id' => $su->id,
                            'school_id' => $schoolId,
                            'nis' => $nis,
                            'nisn' => $nisn,
                            'nik_enc' => 'enc_'.str_pad((string) $gcounter, 16, '0', STR_PAD_LEFT),
                            'full_name' => $nm,
                            'gender' => $isMale ? 'L' : 'P',
                            'birth_place' => $birthPlace,
                            'birth_date' => (2026 - 6 - $c['level']).'-0'.(($gcounter % 9) + 1).'-1'.($gcounter % 9),
                            'religion' => 'Islam',
                            'address' => 'Jl. Simulasi No. '.$gcounter.', Malang',
                            'status' => 'aktif',
                            'entry_date' => $yearStart.'-07-15',
                        ]);
                    } else {
                        $student->user_id = $su->id;
                        $student->full_name = $nm;
                        $student->gender = $isMale ? 'L' : 'P';
                        $student->birth_place = $birthPlace;
                        $student->save();
                    }
                    ClassStudent::firstOrCreate(['class_id' => $c['model']->id, 'student_id' => $student->id], ['joined_at' => $yearStart.'-07-15']);
                    $classStudentsMap[$ci][] = $student;

                    // 2) Nama Orang Tua 100% Unik & Berbeda dari Anak (50% Ayah, 50% Ibu)
                    $isFather = ($gcounter % 2 !== 0);
                    $k = intdiv($gcounter - 1, 2); // 0 .. 209
                    if ($isFather) {
                        $fFirst = $fatherFirst[$k % count($fatherFirst)];
                        $fLast = $parentLast[(intdiv($k, count($fatherFirst)) * 17 + $k) % count($parentLast)];
                        $title = $parentTitles[($k * 7) % count($parentTitles)];
                        $gName = 'Bapak '.$fFirst.' '.$fLast.$title;
                    } else {
                        $mFirst = $motherFirst[$k % count($motherFirst)];
                        $mLast = $parentLast[(intdiv($k, count($motherFirst)) * 19 + $k) % count($parentLast)];
                        $title = $parentTitles[($k * 5) % count($parentTitles)];
                        $gName = 'Ibu '.$mFirst.' '.$mLast.$title;
                    }

                    $gOccupation = $occupations[($gcounter * 5) % count($occupations)];
                    $gPhone = $phonePrefixes[$gcounter % count($phonePrefixes)].str_pad((string) (1234000 + $gcounter * 17), 7, '0', STR_PAD_LEFT);

                    $gu = $this->upsertUser('ortu'.$pad, 'ortu'.$pad.'@gmail.com', $schoolId, $hash);
                    $gu->roles()->syncWithoutDetaching([$roleId('guardian')]);

                    $guardian = Guardian::where('user_id', $gu->id)->first();
                    if (! $guardian) {
                        $guardian = Guardian::create([
                            'user_id' => $gu->id,
                            'school_id' => $schoolId,
                            'full_name' => $gName,
                            'phone' => $gPhone,
                            'occupation' => $gOccupation,
                        ]);
                    } else {
                        $guardian->full_name = $gName;
                        $guardian->phone = $gPhone;
                        $guardian->occupation = $gOccupation;
                        $guardian->save();
                    }

                    DB::table('student_guardians')->updateOrInsert(
                        ['student_id' => $student->id, 'guardian_id' => $guardian->id],
                        ['relationship' => $isFather ? 'ayah' : 'ibu', 'is_primary' => true, 'updated_at' => now(), 'created_at' => now()]
                    );

                    $cred['students'][] = ['username' => 'siswa'.$pad, 'name' => $nm, 'kelas' => $c['label']];
                    $cred['guardians'][] = ['username' => 'ortu'.$pad, 'name' => $gName, 'anak' => $nm, 'kelas' => $c['label']];
                }
            }

            // ---- Modul ajar, materi, & tugas per kelas ----
            foreach ($classes as $ci => $c) {
                $homeroom = $teachers[$ci] ?? $teachers[0];
                $subjForClass = $subjects[$subjectCodes[$ci % count($subjectCodes)]];

                TeachingMaterial::firstOrCreate(
                    ['uploaded_by' => $homeroom->user_id, 'title' => 'Modul Ajar '.$subjForClass->name.' - '.$c['label']],
                    ['grade_level_id' => $levels[$c['level']]->id, 'subject_id' => $subjForClass->id, 'file_id' => $dummyFile->id, 'material_type' => 'modul', 'description' => 'Modul ajar Kurikulum Merdeka untuk '.$c['label'], 'version' => 1]
                );

                LearningMaterial::firstOrCreate(
                    ['class_id' => $c['model']->id, 'subject_id' => $subjForClass->id, 'title' => 'Materi 1 '.$subjForClass->name.' ('.$c['label'].')'],
                    ['teacher_id' => $homeroom->id, 'file_id' => $dummyFile->id]
                );

                $assignment = Assignment::firstOrCreate(
                    ['class_id' => $c['model']->id, 'subject_id' => $subjForClass->id, 'title' => 'Tugas 1 '.$subjForClass->name.' ('.$c['label'].')'],
                    ['teacher_id' => $homeroom->id, 'due_at' => Carbon::now()->addDays(7)->toDateTimeString()]
                );

                // Submisi contoh untuk 5 siswa pertama tiap kelas
                foreach (array_slice($classStudentsMap[$ci] ?? [], 0, 5) as $st) {
                    AssignmentSubmission::firstOrCreate(
                        ['assignment_id' => $assignment->id, 'student_id' => $st->id],
                        ['file_id' => $dummyFile->id, 'submitted_at' => Carbon::now()->subDay()->toDateTimeString(), 'score' => 80 + ($st->id ? 5 : 0)]
                    );
                }
            }

            // ---- Bank soal + soal (dipakai semua ujian) ----
            $bank = QuestionBank::firstOrCreate(
                ['school_id' => $schoolId, 'name' => 'Bank Soal Matematika Umum'],
                ['subject_id' => $subjects['MTK']->id, 'grade_level_id' => $levels[7]->id, 'owner_id' => $teachers[0]->user_id]
            );
            $questions = [];
            $qData = [
                ['CP-01', 'Hasil dari (-12) + 8 x (-3) adalah ...', ['-36', '36', '-12', '12'], 0],
                ['CP-02', 'KPK dari 12 dan 18 adalah ...', ['36', '6', '72', '216'], 0],
                ['CP-03', '3/4 + 2/3 = ...', ['17/12', '5/7', '6/12', '1'], 0],
                ['CP-04', '15% dari 200 adalah ...', ['30', '15', '20', '45'], 0],
                ['CP-05', 'Jika x + 5 = 12 maka x = ...', ['7', '17', '5', '12'], 0],
            ];
            foreach ($qData as $i => [$cp, $text, $opts, $correctIdx]) {
                $q = Question::firstOrCreate(
                    ['bank_id' => $bank->id, 'cp_code' => $cp],
                    ['question_type' => 'pg', 'body' => json_encode(['text' => $text]), 'cognitive_level' => 'MOTS', 'difficulty' => 3, 'weight' => 1.0, 'status' => 'ready']
                );
                foreach ($opts as $oi => $optText) {
                    QuestionOption::firstOrCreate(
                        ['question_id' => $q->id, 'label' => chr(65 + $oi)],
                        ['body' => json_encode(['text' => $optText]), 'is_correct' => $oi === $correctIdx]
                    );
                }
                $questions[] = $q;
            }

            // ---- SEMUA tipe ujian ----
            $examTypes = [
                ['kuis', 'Kuis Harian Matematika', 'dijadwalkan', 2],
                ['harian', 'Ulangan Harian Matematika', 'dijadwalkan', 2],
                ['STS', 'Sumatif Tengah Semester Matematika', 'berlangsung', 1],
                ['SAS', 'Sumatif Akhir Semester Matematika', 'dijadwalkan', 3],
                ['remedial', 'Remedial Matematika', 'dijadwalkan', 7],
                ['pengayaan', 'Pengayaan Matematika', 'dijadwalkan', 10],
            ];
            $firstClass = $classes[0];
            foreach ($examTypes as [$type, $title, $status, $dayOffset]) {
                $start = Carbon::now()->addDays($dayOffset)->setTime(8, 0);
                $exam = Exam::firstOrCreate(
                    ['school_id' => $schoolId, 'title' => $title],
                    [
                        'subject_id' => $subjects['MTK']->id,
                        'created_by' => $teachers[0]->user_id,
                        'exam_type' => $type,
                        'duration_min' => 60,
                        'start_at' => $start->toDateTimeString(),
                        'end_at' => (clone $start)->addHours(2)->toDateTimeString(),
                        'passing_grade' => 75.0,
                        'status' => $status,
                    ]
                );
                foreach ($questions as $qi => $q) {
                    ExamQuestion::firstOrCreate(['exam_id' => $exam->id, 'question_id' => $q->id], ['sort_order' => $qi + 1]);
                }
                $examRoom = ExamRoom::firstOrCreate(
                    ['exam_id' => $exam->id, 'room_id' => $firstClass['room']->id],
                    ['session_no' => 1, 'token' => strtoupper(Str::random(6)), 'proctor_id' => $teachers[0]->user_id]
                );
                foreach (array_slice($classStudentsMap[0] ?? [], 0, 20) as $st) {
                    ExamParticipant::firstOrCreate(['exam_id' => $exam->id, 'student_id' => $st->id], ['exam_room_id' => $examRoom->id]);
                }
            }

            // ---- Nilai & komponen contoh (mapel MTK) ----
            $comp = GradeComponent::firstOrCreate(
                ['subject_id' => $subjects['MTK']->id, 'semester_id' => $sem->id, 'code' => 'UH'],
                ['name' => 'Ulangan Harian', 'weight' => 40]
            );
            $period = GradePeriod::firstOrCreate(['semester_id' => $sem->id, 'month' => (int) $now->month], ['status' => 'open']);
            foreach (($classStudentsMap[0] ?? []) as $idx => $st) {
                Grade::firstOrCreate(
                    ['period_id' => $period->id, 'component_id' => $comp->id, 'student_id' => $st->id],
                    ['value' => 75 + ($idx % 20), 'source' => 'manual']
                );
            }

            // ---- Poin (prestasi & pelanggaran) ----
            $ptPrestasi = PointType::firstOrCreate(['school_id' => $schoolId, 'name' => 'Juara Lomba'], ['kind' => 'prestasi', 'points' => 25, 'severity' => 'rendah']);
            $ptLanggar = PointType::firstOrCreate(['school_id' => $schoolId, 'name' => 'Terlambat'], ['kind' => 'pelanggaran', 'points' => 5, 'severity' => 'rendah']);
            foreach (array_slice($classStudentsMap[0] ?? [], 0, 5) as $k => $st) {
                StudentPoint::firstOrCreate(
                    ['student_id' => $st->id, 'point_type_id' => $k % 2 === 0 ? $ptPrestasi->id : $ptLanggar->id, 'point_date' => Carbon::now()->subDays($k)->toDateString()],
                    ['recorded_by' => $teachers[0]->user_id, 'notes' => '[SEEDER] Catatan poin simulasi']
                );
            }
        });

        // ---- Tulis file kredensial MD ----
        $this->writeCredentialsFile($cred, $password);

        $this->command?->info('TestingFullMasterSeeder selesai. Kredensial: 19_KREDENSIAL_AKUN_TESTING.md');
    }

    /**
     * Cari user berdasarkan username ATAU email (agar idempoten di DB yang sudah berisi
     * data seeder lain dengan email/username yang mungkin bentrok), lalu perbarui; jika
     * tidak ada, buat baru.
     */
    private function upsertUser(string $username, string $email, string $schoolId, string $hash): User
    {
        $uByEmail = User::where('email', $email)->first();
        $uByName = User::where('username', $username)->first();

        // Utamakan user yang cocok dengan email agar tidak melanggar unique constraint email
        $u = $uByEmail ?? $uByName;

        $attrs = [
            'username' => $username,
            'email' => $email,
            'school_id' => $schoolId,
            'password_hash' => $hash,
            'status' => 'active',
            'must_change_password' => false,
        ];

        if ($u) {
            // Jika ada user lain yang memegang username ini, rename dulu user lain tsb
            if ($uByName && $uByName->id !== $u->id) {
                $uByName->username = $uByName->username.'_old_'.substr($uByName->id, 0, 8);
                $uByName->save();
            }
            $u->fill($attrs)->save();

            return $u;
        }

        return User::create($attrs);
    }

    private function writeCredentialsFile(array $cred, string $password): void
    {
        $md = "# Kredensial Akun Testing — SISTLABUM\n\n";
        $md .= "> Dibuat otomatis oleh `TestingFullMasterSeeder`. **Password semua akun sama.**\n\n";
        $md .= "**Password (semua akun): `{$password}`**\n\n";
        $md .= "Login admin/kepsek: `/secret-admin-gate-login` (pakai 2FA). Login guru/siswa/ortu: `/masuk`.\n\n";
        $md .= 'Ringkasan: '.count($cred['staff']).' staf, '.count($cred['teachers']).' guru, '.count($cred['students']).' siswa, '.count($cred['guardians'])." orang tua.\n\n";

        $md .= "## Staf / Back-office\n\n| Username | Peran | Email |\n|---|---|---|\n";
        foreach ($cred['staff'] as $s) {
            $md .= "| `{$s['username']}` | {$s['label']} | {$s['email']} |\n";
        }

        $md .= "\n## Guru (20)\n\n| Username | Nama | Mapel |\n|---|---|---|\n";
        foreach ($cred['teachers'] as $t) {
            $md .= "| `{$t['username']}` | {$t['name']} | {$t['mapel']} |\n";
        }

        $md .= "\n## Siswa (".count($cred['students'])."), per kelas\n\n| Username | Nama | Kelas |\n|---|---|---|\n";
        foreach ($cred['students'] as $s) {
            $md .= "| `{$s['username']}` | {$s['name']} | {$s['kelas']} |\n";
        }

        $md .= "\n## Orang Tua (".count($cred['guardians'])."), per anak\n\n| Username | Nama | Anak | Kelas |\n|---|---|---|---|\n";
        foreach ($cred['guardians'] as $g) {
            $md .= "| `{$g['username']}` | {$g['name']} | {$g['anak']} | {$g['kelas']} |\n";
        }

        $targetPath = file_exists(base_path('../../00_README.md'))
            ? realpath(base_path('../..')).'/19_KREDENSIAL_AKUN_TESTING.md'
            : base_path('19_KREDENSIAL_AKUN_TESTING.md');

        file_put_contents($targetPath, $md);
    }
}
