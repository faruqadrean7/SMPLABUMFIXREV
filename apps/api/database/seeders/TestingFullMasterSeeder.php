<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AcademicYear;
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

        DB::transaction(function () use ($hash, $schoolId, $password, &$cred) {
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
                $u = User::updateOrCreate(
                    ['username' => $sa['username']],
                    ['school_id' => $schoolId, 'email' => $sa['email'], 'password_hash' => $hash, 'status' => 'active', 'must_change_password' => false]
                );
                $u->roles()->syncWithoutDetaching([$roleId($sa['role'])]);
                // BK juga punya profil teacher agar portal guru/BK berfungsi
                if ($sa['role'] === 'counselor') {
                    Teacher::firstOrCreate(['user_id' => $u->id], ['school_id' => $schoolId, 'full_name' => 'Dra. Konselor BK', 'position' => 'Bimbingan Konseling', 'status' => 'tetap']);
                }
                $cred['staff'][] = ['username' => $sa['username'], 'label' => $sa['label'], 'email' => $sa['email']];
            }

            // ---- Tahun ajaran & semester ----
            $now = Carbon::now();
            $yearStart = $now->month >= 7 ? $now->year : $now->year - 1;
            $ay = AcademicYear::firstOrCreate(
                ['school_id' => $schoolId, 'name' => $yearStart.'/'.($yearStart + 1)],
                ['start_date' => $yearStart.'-07-01', 'end_date' => ($yearStart + 1).'-06-30', 'is_active' => true]
            );
            $sem = Semester::firstOrCreate(
                ['academic_year_id' => $ay->id, 'name' => $now->month >= 7 ? 'Ganjil' : 'Genap'],
                ['start_date' => $yearStart.'-07-01', 'end_date' => ($yearStart + 1).'-06-30', 'is_active' => true]
            );

            // ---- Tingkat (7, 8, 9) ----
            $levels = [];
            foreach (['VII' => 7, 'VIII' => 8, 'IX' => 9] as $rom => $n) {
                $levels[$n] = GradeLevel::firstOrCreate(['school_id' => $schoolId, 'name' => "Kelas {$rom}"], ['sort_order' => $n]);
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
                $u = User::updateOrCreate(
                    ['username' => 'guru'.$num],
                    ['school_id' => $schoolId, 'email' => 'guru'.$num.'@labschool.sch.id', 'password_hash' => $hash, 'status' => 'active', 'must_change_password' => false]
                );
                $u->roles()->syncWithoutDetaching([$roleId('teacher'), $roleId('homeroom')]);
                $t = Teacher::firstOrCreate(
                    ['user_id' => $u->id],
                    ['school_id' => $schoolId, 'nip' => '1990'.str_pad((string) ($i + 1), 14, '0', STR_PAD_LEFT), 'full_name' => $namaGuru[$i], 'position' => 'Guru '.$subjects[$subjCode]->name, 'status' => 'tetap']
                );
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
                    $label = $lvlNum.chr(65 + $s); // 7A, 7B, ...
                    $room = Room::firstOrCreate(
                        ['code' => 'R-'.$label],
                        ['school_id' => $schoolId, 'name' => 'Ruang Kelas '.$label, 'room_type' => 'kelas', 'capacity' => 32]
                    );
                    $homeroom = $teachers[$classIndex] ?? null; // 20 guru -> kelas ke-21 tanpa wali
                    $class = SchoolClass::firstOrCreate(
                        ['school_id' => $schoolId, 'academic_year_id' => $ay->id, 'name' => 'Kelas '.$label],
                        ['grade_level_id' => $levels[$lvlNum]->id, 'homeroom_teacher_id' => $homeroom?->id, 'capacity' => 32]
                    );
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
            $taCache = [];
            foreach ($classes as $ci => $c) {
                $periodCounter = 0;
                for ($day = 1; $day <= 5; $day++) {
                    foreach ($slots as $slotIdx => $slot) {
                        $subjCode = $subjectCodes[$periodCounter % count($subjectCodes)];
                        $subject = $subjects[$subjCode];
                        $pool = $teachersBySubject[$subjCode];
                        $teacher = $pool[($ci + $periodCounter) % count($pool)];

                        $taKey = $sem->id.$c['model']->id.$subject->id.$teacher->id;
                        if (! isset($taCache[$taKey])) {
                            $taCache[$taKey] = TeachingAssignment::firstOrCreate(
                                ['semester_id' => $sem->id, 'class_id' => $c['model']->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id],
                                ['hours_per_week' => 3]
                            );
                        }

                        Schedule::updateOrCreate(
                            ['semester_id' => $sem->id, 'class_id' => $c['model']->id, 'day_of_week' => $day, 'time_slot_id' => $slot->id],
                            ['subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'room_id' => $c['room']->id]
                        );
                        $periodCounter++;
                    }
                }
            }

            // ---- File dummy untuk lampiran ----
            $dummyFile = File::firstOrCreate(
                ['object_key' => 'seeds/contoh-dokumen.pdf'],
                ['school_id' => $schoolId, 'bucket' => 'documents', 'mime_type' => 'application/pdf', 'size_bytes' => 102400, 'visibility' => 'privat']
            );

            // ---- 420 Siswa (20/kelas) + 420 Orang Tua ----
            $firstL = ['Ahmad', 'Budi', 'Dimas', 'Eko', 'Fajar', 'Galih', 'Hafiz', 'Irfan', 'Joko', 'Kevin', 'Lukman', 'Mahendra', 'Naufal', 'Oki', 'Putra', 'Rafi', 'Satria', 'Taufik', 'Umar', 'Wahyu'];
            $firstP = ['Annisa', 'Bunga', 'Citra', 'Dewi', 'Elisa', 'Fitri', 'Gita', 'Hana', 'Indah', 'Jelita', 'Karina', 'Laras', 'Melati', 'Nadia', 'Oktavia', 'Puspita', 'Qori', 'Rahma', 'Salsa', 'Tiara'];
            $last = ['Pratama', 'Santoso', 'Wijaya', 'Kusuma', 'Saputra', 'Wibowo', 'Nugroho', 'Hidayat', 'Siregar', 'Ramadhan', 'Maulana', 'Firmansyah'];

            $gcounter = 0;
            $classStudentsMap = []; // ci => [Student,...]
            foreach ($classes as $ci => $c) {
                for ($n = 1; $n <= 20; $n++) {
                    $gcounter++;
                    $pad = str_pad((string) $gcounter, 3, '0', STR_PAD_LEFT);
                    $isMale = ($gcounter % 2 !== 0);
                    $nm = ($isMale ? $firstL[($gcounter) % 20] : $firstP[($gcounter) % 20]).' '.$last[$gcounter % count($last)];

                    $su = User::updateOrCreate(
                        ['username' => 'siswa'.$pad],
                        ['school_id' => $schoolId, 'email' => 'siswa'.$pad.'@labschool.sch.id', 'password_hash' => $hash, 'status' => 'active', 'must_change_password' => false]
                    );
                    $su->roles()->syncWithoutDetaching([$roleId('student')]);
                    $student = Student::firstOrCreate(
                        ['user_id' => $su->id],
                        [
                            'school_id' => $schoolId,
                            'nis' => '2026'.$pad,
                            'nisn' => '00'.str_pad((string) (1000000 + $gcounter), 8, '0', STR_PAD_LEFT),
                            'nik_enc' => 'enc_'.str_pad((string) $gcounter, 16, '0', STR_PAD_LEFT),
                            'full_name' => $nm,
                            'gender' => $isMale ? 'L' : 'P',
                            'birth_place' => 'Malang',
                            'birth_date' => (2026 - 6 - $c['level']).'-0'.(($gcounter % 9) + 1).'-1'.($gcounter % 9),
                            'religion' => 'Islam',
                            'address' => 'Jl. Simulasi No. '.$gcounter.', Malang',
                            'status' => 'aktif',
                            'entry_date' => $yearStart.'-07-15',
                        ]
                    );
                    ClassStudent::firstOrCreate(['class_id' => $c['model']->id, 'student_id' => $student->id], ['joined_at' => $yearStart.'-07-15']);
                    $classStudentsMap[$ci][] = $student;

                    // Orang tua (1:1)
                    $gu = User::updateOrCreate(
                        ['username' => 'ortu'.$pad],
                        ['school_id' => $schoolId, 'email' => 'ortu'.$pad.'@gmail.com', 'password_hash' => $hash, 'status' => 'active', 'must_change_password' => false]
                    );
                    $gu->roles()->syncWithoutDetaching([$roleId('guardian')]);
                    $guardian = Guardian::firstOrCreate(
                        ['user_id' => $gu->id],
                        ['school_id' => $schoolId, 'full_name' => ($isMale ? 'Bapak ' : 'Ibu ').$nm, 'phone' => '0812'.str_pad((string) $gcounter, 8, '0', STR_PAD_LEFT), 'occupation' => 'Wiraswasta']
                    );
                    DB::table('student_guardians')->updateOrInsert(
                        ['student_id' => $student->id, 'guardian_id' => $guardian->id],
                        ['relationship' => $isMale ? 'ayah' : 'ibu', 'is_primary' => true, 'updated_at' => now(), 'created_at' => now()]
                    );

                    $cred['students'][] = ['username' => 'siswa'.$pad, 'name' => $nm, 'kelas' => $c['label']];
                    $cred['guardians'][] = ['username' => 'ortu'.$pad, 'name' => ($isMale ? 'Bapak ' : 'Ibu ').$nm, 'anak' => $nm, 'kelas' => $c['label']];
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

    private function writeCredentialsFile(array $cred, string $password): void
    {
        $md = "# Kredensial Akun Testing — SISTLABUM\n\n";
        $md .= "> Dibuat otomatis oleh `TestingFullMasterSeeder`. **Password semua akun sama.**\n\n";
        $md .= "**Password (semua akun): `{$password}`**\n\n";
        $md .= "Login admin/kepsek: `/secret-admin-gate-login` (pakai 2FA). Login guru/siswa/ortu: `/masuk`.\n\n";
        $md .= "Ringkasan: ".count($cred['staff'])." staf, ".count($cred['teachers'])." guru, ".count($cred['students'])." siswa, ".count($cred['guardians'])." orang tua.\n\n";

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

        file_put_contents(base_path('19_KREDENSIAL_AKUN_TESTING.md'), $md);
    }
}
