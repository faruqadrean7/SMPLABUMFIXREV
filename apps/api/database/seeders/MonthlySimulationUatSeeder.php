<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\ApplicantParent;
use App\Models\Asset;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\CharacterNote;
use App\Models\ClassStudent;
use App\Models\CounselingRequest;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\ExamQuestion;
use App\Models\ExamRoom;
use App\Models\ExamSession;
use App\Models\Facility;
use App\Models\FeeType;
use App\Models\File;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradeLevel;
use App\Models\GradePeriod;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Journal;
use App\Models\LearningMaterial;
use App\Models\LeaveRequest;
use App\Models\LibraryBook;
use App\Models\Loan;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\PaymentProviderSetting;
use App\Models\PointType;
use App\Models\Post;
use App\Models\PpdbTrack;
use App\Models\PpdbWave;
use App\Models\ProgressReport;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\ReportCard;
use App\Models\Role;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\SiteSetting;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\StudentPoint;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSalarySlip;
use App\Models\TeachingAssignment;
use App\Models\TeachingMaterial;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MonthlySimulationUatSeeder extends Seeder
{
    public const DEFAULT_SCHOOL_ID = '00000000-0000-0000-0000-000000000001';

    public function run(?int $scaleOverride = null): void
    {
        // 1. Guard against running in unauthorized environment & production
        $currentEnv = config('app.env');
        if (app()->environment('production') || $currentEnv === 'production') {
            throw new \RuntimeException('MonthlySimulationUatSeeder DILARANG dijalankan di lingkungan production demi keamanan data!');
        }

        $allowedEnvs = ['local', 'testing', 'staging'];
        $allowEnvFlag = filter_var(env('SEEDER_ALLOW_ENV', false), FILTER_VALIDATE_BOOLEAN);

        if (! in_array($currentEnv, $allowedEnvs, true) && ! $allowEnvFlag) {
            throw new \RuntimeException("MonthlySimulationUatSeeder DILARANG dijalankan di lingkungan '{$currentEnv}' tanpa flag eksplisit SEEDER_ALLOW_ENV=true.");
        }

        // BATCH 5B - A.3 (a): Hapus fallback password. Jika SEEDER_PASSWORD tidak diset, seeder gagal dengan pesan jelas.
        $password = env('SEEDER_PASSWORD');
        if (empty($password)) {
            throw new \RuntimeException('Variabel lingkungan SEEDER_PASSWORD wajib didefinisikan secara eksplisit. Fallback password dilarang demi keamanan.');
        }

        // 2. Deterministic RNG & Scale
        mt_srand(12345);
        $hasScaleOpt = $this->command && $this->command->hasOption('scale');
        $scale = $scaleOverride ?? (int) ($hasScaleOpt ? $this->command->option('scale') : env('SEEDER_SCALE', 1));
        if ($scale < 1) {
            $scale = 1;
        }

        $hashedPassword = Hash::make($password);

        DB::transaction(function () use ($scale, $password, $hashedPassword) {
            // Base RBAC roles & permissions
            (new DatabaseSeeder)->run();

            $schoolId = self::DEFAULT_SCHOOL_ID;

            // Site Settings
            SiteSetting::firstOrCreate(
                ['setting_key' => 'general_info'],
                [
                    'school_id' => $schoolId,
                    'setting_value' => json_encode([
                        'name' => 'SMP Laboratorium Universitas Negeri Malang',
                        'npsn' => '20533812',
                        'address' => 'Jl. Simpang Bogor T-7 Malang, Jawa Timur',
                        'phone' => '(0341) 551312',
                        'email' => 'info@sistlabum.sch.id',
                    ]),
                ]
            );

            // 3. Dynamic Relative Business Days (1 Bulan Terakhir / 20 Hari Kerja)
            $businessDays = [];
            $checkDate = Carbon::now()->subDays(35)->startOfDay();
            $today = Carbon::now()->endOfDay();
            while ($checkDate->lte($today)) {
                if (! $checkDate->isWeekend()) {
                    $businessDays[] = $checkDate->toDateString();
                }
                $checkDate->addDay();
            }
            $kbmDates = array_slice($businessDays, -20);
            $startDate = $kbmDates[0];
            $endDate = end($kbmDates);

            // Determine Dynamic Academic Year & Semester
            $now = Carbon::now();
            $yearStart = $now->month >= 7 ? $now->year : $now->year - 1;
            $academicYearName = $yearStart.'/'.($yearStart + 1);
            $semesterName = $now->month >= 7 ? 'Ganjil' : 'Genap';

            $academicYear = AcademicYear::firstOrCreate(
                ['school_id' => $schoolId, 'name' => $academicYearName],
                [
                    'start_date' => $yearStart.'-07-01',
                    'end_date' => ($yearStart + 1).'-06-30',
                    'is_active' => true,
                ]
            );

            $semester = Semester::firstOrCreate(
                ['academic_year_id' => $academicYear->id, 'name' => $semesterName],
                [
                    'start_date' => $startDate,
                    'end_date' => Carbon::parse($endDate)->addMonths(3)->toDateString(),
                    'is_active' => true,
                ]
            );

            // Grade Level
            $gradeLevel7 = GradeLevel::firstOrCreate(
                ['school_id' => $schoolId, 'name' => 'Kelas VII'],
                ['sort_order' => 7]
            );

            // Rooms
            $room7A = Room::firstOrCreate(
                ['school_id' => $schoolId, 'code' => 'R-7A'],
                ['name' => 'Ruang Teori 7-A', 'room_type' => 'kelas', 'capacity' => 32]
            );
            $room7B = Room::firstOrCreate(
                ['school_id' => $schoolId, 'code' => 'R-7B'],
                ['name' => 'Ruang Teori 7-B', 'room_type' => 'kelas', 'capacity' => 32]
            );
            $roomLabKom = Room::firstOrCreate(
                ['school_id' => $schoolId, 'code' => 'LAB-KOM-1'],
                ['name' => 'Laboratorium Komputer 1', 'room_type' => 'laboratorium', 'capacity' => 36]
            );
            $roomLabIpa = Room::firstOrCreate(
                ['school_id' => $schoolId, 'code' => 'LAB-IPA'],
                ['name' => 'Laboratorium IPA Terpadu', 'room_type' => 'laboratorium', 'capacity' => 36]
            );

            // Dummy File for attachments
            $dummyFile = File::firstOrCreate(
                ['object_key' => 'seeds/sample-document.pdf'],
                [
                    'school_id' => $schoolId,
                    'bucket' => 'documents',
                    'mime_type' => 'application/pdf',
                    'size_bytes' => 102400,
                    'visibility' => 'privat',
                ]
            );

            // Core Subjects
            $subjectsData = [
                ['code' => 'MTK', 'name' => 'Matematika'],
                ['code' => 'INDO', 'name' => 'Bahasa Indonesia'],
                ['code' => 'IPA', 'name' => 'Ilmu Pengetahuan Alam'],
                ['code' => 'ENG', 'name' => 'Bahasa Inggris'],
                ['code' => 'INF', 'name' => 'Informatika'],
                ['code' => 'PAI', 'name' => 'Pendidikan Agama & Budi Pekerti'],
                ['code' => 'PPKN', 'name' => 'Pendidikan Pancasila'],
                ['code' => 'PJOK', 'name' => 'Pendidikan Jasmani, Olahraga & Kesehatan'],
            ];
            $subjects = [];
            foreach ($subjectsData as $sData) {
                $subjects[$sData['code']] = Subject::firstOrCreate(
                    ['school_id' => $schoolId, 'code' => $sData['code']],
                    ['name' => $sData['name'], 'subject_group' => 'umum']
                );
            }

            // Core Roles
            $roleSuperAdmin = Role::where('code', 'super_admin')->firstOrFail();
            $roleAdminTu = Role::where('code', 'admin_tu')->firstOrFail();
            $roleBendahara = Role::where('code', 'bendahara')->firstOrFail();
            $roleTeacher = Role::where('code', 'teacher')->firstOrFail();
            $roleHomeroom = Role::where('code', 'homeroom')->firstOrFail();
            $roleCounselor = Role::where('code', 'counselor')->firstOrFail();
            $roleStudent = Role::where('code', 'student')->firstOrFail();
            $roleGuardian = Role::where('code', 'guardian')->firstOrFail();

            // 4. Back-office Admin Users
            $superAdminUser = User::firstOrCreate(
                ['username' => 'admin_demo'],
                [
                    'school_id' => $schoolId,
                    'email' => 'admin_demo@labschool.sch.id',
                    'password_hash' => $hashedPassword,
                    'status' => 'active',
                    'must_change_password' => false,
                ]
            );
            $superAdminUser->roles()->syncWithoutDetaching([$roleSuperAdmin->id]);

            // BATCH 5C: Sample News Post for Public News Route & Crawler
            Post::firstOrCreate(
                ['slug' => 'prestasi-olimpiade-sains-2026'],
                [
                    'school_id' => $schoolId,
                    'author_id' => $superAdminUser->id,
                    'title' => 'Prestasi Membanggakan di Olimpiade Sains Nasional 2026',
                    'content' => '<p>Siswa SISTLABUM berhasil meraih medali emas pada ajang kompetisi OSN 2026.</p>',
                    'status' => 'terbit',
                    'visibility' => 'publik',
                    'published_at' => now(),
                ]
            );

            $bendaharaUser = User::firstOrCreate(
                ['username' => 'bendahara_demo'],
                [
                    'school_id' => $schoolId,
                    'email' => 'bendahara_demo@labschool.sch.id',
                    'password_hash' => $hashedPassword,
                    'status' => 'active',
                    'must_change_password' => false,
                ]
            );
            $bendaharaUser->roles()->syncWithoutDetaching([$roleBendahara->id]);

            $tuUser = User::firstOrCreate(
                ['username' => 'staf_tu'],
                [
                    'school_id' => $schoolId,
                    'email' => 'tu_demo@labschool.sch.id',
                    'password_hash' => $hashedPassword,
                    'status' => 'active',
                    'must_change_password' => false,
                ]
            );
            $tuUser->roles()->syncWithoutDetaching([$roleAdminTu->id]);

            $rolePrincipal = Role::firstOrCreate(['code' => 'principal'], ['name' => 'Kepala Sekolah']);
            $roleStaff = Role::firstOrCreate(['code' => 'staff'], ['name' => 'Staf / Tenaga Kependidikan']);

            $kepsekUser = User::firstOrCreate(
                ['username' => 'kepsek_demo'],
                [
                    'school_id' => $schoolId,
                    'email' => 'kepsek_demo@labschool.sch.id',
                    'password_hash' => $hashedPassword,
                    'status' => 'active',
                    'must_change_password' => false,
                ]
            );
            $kepsekUser->roles()->syncWithoutDetaching([$rolePrincipal->id]);

            $stafCivitasUser = User::firstOrCreate(
                ['username' => 'staf_demo'],
                [
                    'school_id' => $schoolId,
                    'email' => 'staf_demo@labschool.sch.id',
                    'password_hash' => $hashedPassword,
                    'status' => 'active',
                    'must_change_password' => false,
                ]
            );
            $stafCivitasUser->roles()->syncWithoutDetaching([$roleStaff->id]);

            // 5. 10 Guru & Profiles
            $teachersData = [
                ['username' => 'guru.mtk', 'email' => 'hendra.wijaya@labschool.sch.id', 'nip' => '198101152006041001', 'name' => 'Hendra Wijaya, S.Pd.', 'mapel' => 'MTK', 'is_walas' => '7A'],
                ['username' => 'guru.indo', 'email' => 'rina.marlina@labschool.sch.id', 'nip' => '198305202008012002', 'name' => 'Rina Marlina, M.Pd.', 'mapel' => 'INDO', 'is_walas' => '7B'],
                ['username' => 'guru.bk', 'email' => 'nurul.hidayah@labschool.sch.id', 'nip' => '198509122009022003', 'name' => 'Nurul Hidayah, S.Psi.', 'mapel' => null, 'is_counselor' => true],
                ['username' => 'guru.ipa', 'email' => 'bambang.sutrisno@labschool.sch.id', 'nip' => '197903102005011004', 'name' => 'Bambang Sutrisno, M.Si.', 'mapel' => 'IPA'],
                ['username' => 'guru.inggris', 'email' => 'sarah.amelia@labschool.sch.id', 'nip' => '198807242010012005', 'name' => 'Sarah Amelia, S.Pd.', 'mapel' => 'ENG'],
                ['username' => 'guru.it', 'email' => 'fajar.pratama@labschool.sch.id', 'nip' => '199011152014021006', 'name' => 'Fajar Pratama, S.Kom.', 'mapel' => 'INF'],
                ['username' => 'guru.pai', 'email' => 'ahmad.fauzi@labschool.sch.id', 'nip' => '198204182007011007', 'name' => 'Ahmad Fauzi, S.Pd.I.', 'mapel' => 'PAI'],
                ['username' => 'guru.ppkn', 'email' => 'tri.wahyuni@labschool.sch.id', 'nip' => '198402142008012008', 'name' => 'Tri Wahyuni, S.Pd.', 'mapel' => 'PPKN'],
                ['username' => 'guru.pjok', 'email' => 'dedi.kurniawan@labschool.sch.id', 'nip' => '198606252009031009', 'name' => 'Dedi Kurniawan, S.Pd.', 'mapel' => 'PJOK'],
                ['username' => 'guru.seni', 'email' => 'maya.anggraini@labschool.sch.id', 'nip' => '198908302012022010', 'name' => 'Maya Anggraini, S.Sn.', 'mapel' => 'MTK'],
            ];

            // BATCH 5B - A.3 (c): Skala konsisten: menskalakan guru proporsional (10 * scale)
            $totalTeachersCount = 10 * $scale;
            if ($scale > 1) {
                $subjCodes = ['MTK', 'INDO', 'IPA', 'ENG', 'INF', 'PAI', 'PPKN', 'PJOK'];
                for ($ti = 11; $ti <= $totalTeachersCount; $ti++) {
                    $padTi = str_pad((string) $ti, 3, '0', STR_PAD_LEFT);
                    $assignedMapel = $subjCodes[($ti - 1) % count($subjCodes)];
                    $teachersData[] = [
                        'username' => "guru_{$padTi}",
                        'email' => "guru_{$padTi}@labschool.sch.id",
                        'nip' => '199'.str_pad((string) $ti, 15, '0', STR_PAD_LEFT),
                        'name' => "Guru Simulasi {$padTi}, S.Pd.",
                        'mapel' => $assignedMapel,
                    ];
                }
            }

            $teachers = [];
            $walas7ATeacher = null;
            $walas7BTeacher = null;
            $counselorUser = null;

            foreach ($teachersData as $tData) {
                $user = User::firstOrCreate(
                    ['username' => $tData['username']],
                    [
                        'school_id' => $schoolId,
                        'email' => $tData['email'],
                        'password_hash' => $hashedPassword,
                        'status' => 'active',
                        'must_change_password' => false,
                    ]
                );

                $rolesToAttach = [];
                if (! empty($tData['is_counselor'])) {
                    $rolesToAttach[] = $roleCounselor->id;
                    $counselorUser = $user;
                } else {
                    $rolesToAttach[] = $roleTeacher->id;
                }

                if (! empty($tData['is_walas'])) {
                    $rolesToAttach[] = $roleHomeroom->id;
                }

                $user->roles()->syncWithoutDetaching($rolesToAttach);

                $teacher = Teacher::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'school_id' => $schoolId,
                        'nip' => $tData['nip'],
                        'full_name' => $tData['name'],
                        'position' => $tData['mapel'] ?? 'Bimbingan Konseling',
                        'status' => 'tetap',
                    ]
                );

                $teachers[$tData['username']] = $teacher;

                if (($tData['is_walas'] ?? '') === '7A') {
                    $walas7ATeacher = $teacher;
                } elseif (($tData['is_walas'] ?? '') === '7B') {
                    $walas7BTeacher = $teacher;
                }
            }

            // 6. School Classes: 7-A & 7-B
            $class7A = SchoolClass::firstOrCreate(
                ['school_id' => $schoolId, 'name' => 'Kelas 7-A'],
                [
                    'grade_level_id' => $gradeLevel7->id,
                    'academic_year_id' => $academicYear->id,
                    'homeroom_teacher_id' => $walas7ATeacher?->id,
                    'capacity' => 32,
                ]
            );
            $class7B = SchoolClass::firstOrCreate(
                ['school_id' => $schoolId, 'name' => 'Kelas 7-B'],
                [
                    'grade_level_id' => $gradeLevel7->id,
                    'academic_year_id' => $academicYear->id,
                    'homeroom_teacher_id' => $walas7BTeacher?->id,
                    'capacity' => 32,
                ]
            );

            // 7. Students & Guardians (50 * $scale)
            $firstNamesL = ['Ahmad', 'Budi', 'Dimas', 'Eko', 'Fajar', 'Galih', 'Hafiz', 'Irfan', 'Joko', 'Kevin', 'Lukman', 'Mahendra', 'Naufal', 'Oki', 'Prasetyo'];
            $firstNamesP = ['Annisa', 'Bunga', 'Citra', 'Dewi', 'Elisa', 'Fitri', 'Gita', 'Hana', 'Indah', 'Jelita', 'Karina', 'Laras', 'Melati', 'Nadia', 'Putri'];
            $lastNames = ['Pratama', 'Santoso', 'Wijaya', 'Kusuma', 'Saputra', 'Wibowo', 'Nugroho', 'Hidayat', 'Siregar', 'Ramadhan'];

            $students = [];
            $totalStudents = 50 * $scale;

            for ($i = 1; $i <= $totalStudents; $i++) {
                $pad = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
                $isMale = ($i % 2 !== 0);
                $fnList = $isMale ? $firstNamesL : $firstNamesP;
                $studentFullName = $fnList[($i - 1) % count($fnList)].' '.$lastNames[($i - 1) % count($lastNames)];

                $studentUser = User::firstOrCreate(
                    ['username' => 'siswa_'.$pad],
                    [
                        'school_id' => $schoolId,
                        'email' => 'siswa'.$pad.'@labschool.sch.id',
                        'password_hash' => $hashedPassword,
                        'status' => 'active',
                        'must_change_password' => false,
                    ]
                );
                $studentUser->roles()->syncWithoutDetaching([$roleStudent->id]);

                $nis = '10'.$pad;
                $nisn = '0055100'.$pad;
                $student = Student::firstOrCreate(
                    ['user_id' => $studentUser->id],
                    [
                        'school_id' => $schoolId,
                        'nis' => $nis,
                        'nisn' => $nisn,
                        'nik_enc' => 'enc_357900'.str_pad((string) $i, 10, '0', STR_PAD_LEFT),
                        'full_name' => $studentFullName,
                        'gender' => $isMale ? 'L' : 'P',
                        'birth_place' => 'Malang',
                        'birth_date' => '2013-05-15',
                        'status' => 'aktif',
                        'entry_date' => $yearStart.'-07-15',
                    ]
                );
                $students[] = $student;

                // Assign to class (first half to 7-A, second half to 7-B)
                $targetClass = ($i <= ($totalStudents / 2)) ? $class7A : $class7B;
                ClassStudent::firstOrCreate(
                    ['class_id' => $targetClass->id, 'student_id' => $student->id],
                    ['joined_at' => $yearStart.'-07-15']
                );

                // Guardian User & Profile (1:1 with student)
                $guardianUser = User::firstOrCreate(
                    ['username' => 'ortu_'.$pad],
                    [
                        'school_id' => $schoolId,
                        'email' => 'ortu'.$pad.'@gmail.com',
                        'password_hash' => $hashedPassword,
                        'status' => 'active',
                        'must_change_password' => false,
                    ]
                );
                $guardianUser->roles()->syncWithoutDetaching([$roleGuardian->id]);

                $guardian = Guardian::firstOrCreate(
                    ['user_id' => $guardianUser->id],
                    [
                        'school_id' => $schoolId,
                        'full_name' => 'Wali '.$studentFullName,
                        'phone' => '081234567'.$pad,
                        'occupation' => 'Wiraswasta',
                    ]
                );

                DB::table('student_guardians')->updateOrInsert(
                    ['student_id' => $student->id, 'guardian_id' => $guardian->id],
                    ['relationship' => $isMale ? 'ayah' : 'ibu', 'is_primary' => true]
                );
            }

            // 8. Time Slots
            $timeSlotsData = [
                ['name' => 'Jam ke-1', 'start' => '07:00:00', 'end' => '07:45:00'],
                ['name' => 'Jam ke-2', 'start' => '07:45:00', 'end' => '08:30:00'],
                ['name' => 'Jam ke-3', 'start' => '08:45:00', 'end' => '09:30:00'],
                ['name' => 'Jam ke-4', 'start' => '09:30:00', 'end' => '10:15:00'],
                ['name' => 'Jam ke-5', 'start' => '10:30:00', 'end' => '11:15:00'],
            ];
            $timeSlots = [];
            foreach ($timeSlotsData as $slot) {
                $timeSlots[] = TimeSlot::firstOrCreate(
                    ['school_id' => $schoolId, 'name' => $slot['name']],
                    [
                        'day_type' => 'reguler',
                        'start_time' => $slot['start'],
                        'end_time' => $slot['end'],
                    ]
                );
            }

            // 9. Teaching Assignments & Weekly Schedules
            $assignPlan = [
                ['class' => $class7A, 'code' => 'MTK', 't' => 'guru.mtk', 'room' => $room7A, 'day' => 1, 'slot' => 0],
                ['class' => $class7A, 'code' => 'INDO', 't' => 'guru.indo', 'room' => $room7A, 'day' => 2, 'slot' => 1],
                ['class' => $class7A, 'code' => 'IPA', 't' => 'guru.ipa', 'room' => $roomLabIpa, 'day' => 3, 'slot' => 2],
                ['class' => $class7A, 'code' => 'INF', 't' => 'guru.it', 'room' => $roomLabKom, 'day' => 4, 'slot' => 3],
                ['class' => $class7A, 'code' => 'ENG', 't' => 'guru.inggris', 'room' => $room7A, 'day' => 5, 'slot' => 0],
                ['class' => $class7B, 'code' => 'INDO', 't' => 'guru.indo', 'room' => $room7B, 'day' => 1, 'slot' => 1],
                ['class' => $class7B, 'code' => 'MTK', 't' => 'guru.mtk', 'room' => $room7B, 'day' => 2, 'slot' => 0],
                ['class' => $class7B, 'code' => 'ENG', 't' => 'guru.inggris', 'room' => $room7B, 'day' => 3, 'slot' => 1],
                ['class' => $class7B, 'code' => 'IPA', 't' => 'guru.ipa', 'room' => $roomLabIpa, 'day' => 4, 'slot' => 2],
                ['class' => $class7B, 'code' => 'INF', 't' => 'guru.it', 'room' => $roomLabKom, 'day' => 5, 'slot' => 3],
            ];

            $schedules = [];
            foreach ($assignPlan as $ap) {
                $subj = $subjects[$ap['code']];
                $tch = $teachers[$ap['t']];

                $ta = TeachingAssignment::firstOrCreate(
                    [
                        'semester_id' => $semester->id,
                        'class_id' => $ap['class']->id,
                        'subject_id' => $subj->id,
                        'teacher_id' => $tch->id,
                    ],
                    ['hours_per_week' => 4]
                );

                $sched = Schedule::updateOrCreate(
                    [
                        'semester_id' => $semester->id,
                        'class_id' => $ap['class']->id,
                        'day_of_week' => $ap['day'],
                        'time_slot_id' => $timeSlots[$ap['slot']]->id,
                    ],
                    [
                        'subject_id' => $subj->id,
                        'teacher_id' => $tch->id,
                        'room_id' => $ap['room']->id,
                    ]
                );
                $schedules[] = $sched;
            }

            // 10. KBM Activities: 20 Days of Journals & Attendances
            $topics = [
                'Pengenalan Konsep & Tujuan Pembelajaran',
                'Diskusi Kelompok & Studi Kasus Kontekstual',
                'Praktikum dan Demonstrasi Alat Peraga',
                'Latihan Soal Formatif Berpikir Kritis',
                'Refleksi Pembelajaran dan Evaluasi Mingguan',
            ];

            // Problematic students for early warning & principal dashboard (high absence, low score)
            $problematicStudentIds = [
                $students[3]->id ?? null,  // Siswa 4 in 7-A
                $students[17]->id ?? null, // Siswa 18 in 7-B
            ];

            foreach ($kbmDates as $dayIdx => $dateStr) {
                $dayOfWeek = (Carbon::parse($dateStr)->dayOfWeekIso); // 1 = Senin, 5 = Jumat
                $daySchedules = array_filter($schedules, fn ($s) => $s->day_of_week === $dayOfWeek);

                foreach ($daySchedules as $sched) {
                    $journal = Journal::firstOrCreate(
                        [
                            'schedule_id' => $sched->id,
                            'session_date' => $dateStr,
                        ],
                        [
                            'teacher_id' => $sched->teacher_id,
                            'topic' => $topics[$dayIdx % count($topics)],
                            'method' => 'Discovery Learning & Diskusi Kolaboratif',
                            'notes' => '[SEEDER] KBM berjalan lancar dengan partisipasi aktif siswa.',
                        ]
                    );

                    // Student Attendances (Batch Upsert per Journal)
                    $classStudents = ClassStudent::where('class_id', $sched->class_id)->get();
                    $attRecords = [];
                    $nowTimestamp = now();
                    foreach ($classStudents as $cs) {
                        $isProblematic = in_array($cs->student_id, $problematicStudentIds, true);

                        // Realistis: ~92% Hadir, 4% Sakit, 2% Izin, 2% Alpa
                        $rand = abs(crc32($cs->student_id.$dayIdx)) % 100;
                        if ($isProblematic && ($dayIdx % 4 === 0)) {
                            $status = 'alpa'; // High absence for problematic students
                        } elseif ($rand < 92) {
                            $status = 'hadir';
                        } elseif ($rand < 96) {
                            $status = 'sakit';
                        } elseif ($rand < 98) {
                            $status = 'izin';
                        } else {
                            $status = 'alpa';
                        }

                        $attRecords[] = [
                            'id' => (string) Str::uuid(),
                            'journal_id' => $journal->id,
                            'student_id' => $cs->student_id,
                            'att_date' => $dateStr,
                            'status' => $status,
                            'source' => 'kelas',
                            'note' => $status === 'hadir' ? null : 'Keterangan presensi tercatat otomatis',
                            'created_at' => $nowTimestamp,
                            'updated_at' => $nowTimestamp,
                        ];
                    }

                    if (! empty($attRecords)) {
                        Attendance::upsert(
                            $attRecords,
                            ['journal_id', 'student_id'],
                            ['att_date', 'status', 'source', 'note', 'updated_at']
                        );
                    }
                }

                // Teacher self-attendances (20 days x teachers)
                foreach ($teachers as $tch) {
                    StaffAttendance::updateOrCreate(
                        [
                            'user_id' => $tch->user_id,
                            'att_date' => $dateStr,
                        ],
                        [
                            'check_in' => $dateStr.' 06:45:00',
                            'check_out' => $dateStr.' 15:30:00',
                            'status' => 'hadir',
                            'lat' => -7.9666,
                            'lng' => 112.6326,
                            'notes' => '[SEEDER] Presensi otomatis simulasi UAT',
                        ]
                    );
                }
            }

            // BATCH 5B - A.3 (b): Pemangkasan sliding-window HANYA menghapus baris yang ditandai buatan seeder ([SEEDER])
            $scheduleIds = array_map(fn ($s) => $s->id, $schedules);
            Journal::whereIn('schedule_id', $scheduleIds)
                ->where('notes', 'like', '[SEEDER]%')
                ->whereNotIn('session_date', $kbmDates)
                ->delete();

            $teacherUserIds = array_map(fn ($t) => $t->user_id, $teachers);
            StaffAttendance::whereIn('user_id', $teacherUserIds)
                ->where('notes', 'like', '[SEEDER]%')
                ->whereNotIn('att_date', $kbmDates)
                ->delete();

            // 11. Teaching Materials / Modul Ajar (RPP) per Guru & Subject
            foreach ($teachers as $username => $tch) {
                if ($tch->position !== 'Bimbingan Konseling') {
                    $firstSubj = $subjects['MTK'];
                    TeachingMaterial::firstOrCreate(
                        [
                            'uploaded_by' => $tch->user_id,
                            'title' => 'Modul Ajar Kurikulum Merdeka - '.$tch->position,
                        ],
                        [
                            'grade_level_id' => $gradeLevel7->id,
                            'subject_id' => $firstSubj->id,
                            'file_id' => $dummyFile->id,
                            'material_type' => 'modul',
                            'description' => 'Perangkat ajar dan panduan asesmen diagnostik & sumatif.',
                            'version' => 1,
                        ]
                    );
                }
            }

            // 12. LMS: Materials, Assignments & Submissions
            $lmsMat = LearningMaterial::firstOrCreate(
                [
                    'class_id' => $class7A->id,
                    'subject_id' => $subjects['MTK']->id,
                    'title' => 'Modul 1: Operasi Bilangan Bulat & Pecahan',
                ],
                [
                    'teacher_id' => $teachers['guru.mtk']->id,
                    'file_id' => $dummyFile->id,
                ]
            );

            $assignment = Assignment::firstOrCreate(
                [
                    'class_id' => $class7A->id,
                    'subject_id' => $subjects['MTK']->id,
                    'title' => 'Tugas 1: Lembar Kerja Bilangan Bulat',
                ],
                [
                    'teacher_id' => $teachers['guru.mtk']->id,
                    'due_at' => Carbon::parse($endDate)->addDays(5)->toDateTimeString(),
                ]
            );

            $class7AStudents = ClassStudent::where('class_id', $class7A->id)->get();
            foreach ($class7AStudents as $cs) {
                AssignmentSubmission::firstOrCreate(
                    [
                        'assignment_id' => $assignment->id,
                        'student_id' => $cs->student_id,
                    ],
                    [
                        'file_id' => $dummyFile->id,
                        'submitted_at' => Carbon::parse($endDate)->toDateTimeString(),
                        'score' => 85,
                    ]
                );
            }

            // 13. Academic Grading & Report Cards
            $compTugas = GradeComponent::firstOrCreate(
                ['subject_id' => $subjects['MTK']->id, 'semester_id' => $semester->id, 'code' => 'TUGAS'],
                ['name' => 'Tugas Mandiri', 'weight' => 30]
            );
            $compFormatif = GradeComponent::firstOrCreate(
                ['subject_id' => $subjects['MTK']->id, 'semester_id' => $semester->id, 'code' => 'UH'],
                ['name' => 'Tes Formatif', 'weight' => 35]
            );
            $compSumatif = GradeComponent::firstOrCreate(
                ['subject_id' => $subjects['MTK']->id, 'semester_id' => $semester->id, 'code' => 'STS'],
                ['name' => 'Sumatif Tengah Semester', 'weight' => 35]
            );

            $gradePeriod = GradePeriod::firstOrCreate(
                ['semester_id' => $semester->id, 'month' => (int) $now->month],
                ['status' => 'open']
            );

            foreach ($students as $idx => $st) {
                $isProblematic = in_array($st->id, $problematicStudentIds, true);
                $baseScore = $isProblematic ? 58.0 : (78.0 + ($idx % 18));

                Grade::firstOrCreate(
                    [
                        'period_id' => $gradePeriod->id,
                        'component_id' => $compFormatif->id,
                        'student_id' => $st->id,
                    ],
                    [
                        'value' => $baseScore,
                        'source' => 'manual',
                    ]
                );

                // Semester Report Cards (Snapshot published)
                ReportCard::firstOrCreate(
                    [
                        'student_id' => $st->id,
                        'semester_id' => $semester->id,
                    ],
                    [
                        'data_snapshot' => [
                            'student_name' => $st->full_name,
                            'nis' => $st->nis,
                            'academic_year' => $academicYear->name,
                            'semester' => $semester->name,
                            'average_score' => $baseScore,
                            'attendance_summary' => ['hadir' => 18, 'sakit' => 1, 'izin' => 1, 'alpa' => $isProblematic ? 4 : 0],
                            'academic_status' => $isProblematic ? 'Perlu Pendampingan' : 'Tuntas',
                        ],
                        'file_id' => $dummyFile->id,
                        'status' => 'published',
                        'locked_at' => Carbon::parse($endDate)->addDay()->toDateTimeString(),
                        'verify_token' => 'RC-'.strtoupper(substr(md5($st->id.$semester->id), 0, 16)),
                    ]
                );
            }

            // Character Notes & Progress Reports
            CharacterNote::firstOrCreate(
                [
                    'student_id' => $students[0]->id,
                    'period_id' => $gradePeriod->id,
                ],
                [
                    'author_id' => $walas7ATeacher->user_id,
                    'scale_data' => ['mandiri' => 4, 'gotong_royong' => 4, 'bernalar_kritis' => 4],
                    'homeroom_note' => 'Menunjukkan inisiatif kepemimpinan yang baik saat diskusi kelompok.',
                ]
            );

            ProgressReport::firstOrCreate(
                [
                    'student_id' => $students[0]->id,
                    'period_id' => $gradePeriod->id,
                ],
                [
                    'data_snapshot' => [
                        'attendance_summary' => ['hadir' => 20, 'sakit' => 0, 'izin' => 0, 'alpa' => 0],
                        'academic_progress' => 'Prestasi belajar konsisten di atas rata-rata kelas.',
                        'character_summary' => 'Disiplin dan bertanggung jawab terhadap tugas mandiri.',
                    ],
                    'status' => 'terbit',
                    'verify_token' => 'PR-'.strtoupper(substr(md5($students[0]->id.$gradePeriod->id), 0, 16)),
                ]
            );

            // 14. Student Affairs: Points, Early Warnings & BK Counseling
            $ptPrestasi = PointType::firstOrCreate(
                ['school_id' => $schoolId, 'name' => 'Juara 1 Lomba Sains'],
                ['kind' => 'prestasi', 'points' => 25, 'severity' => 'rendah']
            );
            $ptPelanggaran = PointType::firstOrCreate(
                ['school_id' => $schoolId, 'name' => 'Terlambat Datang ke Sekolah'],
                ['kind' => 'pelanggaran', 'points' => 5, 'severity' => 'rendah']
            );

            StudentPoint::firstOrCreate(
                [
                    'student_id' => $students[0]->id,
                    'point_type_id' => $ptPrestasi->id,
                    'point_date' => $endDate,
                ],
                [
                    'recorded_by' => $walas7ATeacher->user_id,
                    'notes' => 'Juara 1 Olimpiade Sains Pelajar Malang',
                ]
            );

            CounselingRequest::firstOrCreate(
                [
                    'student_id' => $students[3]->id,
                    'topic' => 'Pendampingan presensi dan motivasi belajar siswa',
                ],
                [
                    'requested_by' => $walas7ATeacher->user_id,
                    'status' => 'pending',
                ]
            );

            // 15. Finance: Invoices & Third-Party Payment Slips
            $feeSpp = FeeType::firstOrCreate(
                ['code' => 'SPP-BULANAN'],
                [
                    'school_id' => $schoolId,
                    'name' => 'Iuran SPP Bulanan',
                    'category' => 'spp',
                    'default_amount' => 350000,
                    'is_recurring' => true,
                    'is_active' => true,
                ]
            );

            PaymentProviderSetting::firstOrCreate(
                ['name' => 'Bank Jatim Virtual Account'],
                [
                    'instructions' => "1. Buka aplikasi Mobile Banking atau ATM Bank Jatim.\n2. Pilih menu Transfer Virtual Account.\n3. Masukkan nomor VA yang tertera pada tagihan.",
                    'info_url' => 'https://bankjatim.co.id/virtual-account',
                    'is_active' => true,
                ]
            );

            foreach ($students as $sIdx => $st) {
                $invNumber = sprintf('INV/%s/%02d/%04d', $now->year, $now->month, $sIdx + 1);
                $vaNumber = '9880'.str_pad((string) ($sIdx + 1001), 10, '0', STR_PAD_LEFT);

                $invoice = Invoice::firstOrCreate(
                    ['invoice_number' => $invNumber],
                    [
                        'student_id' => $st->id,
                        'period' => $now->format('Y-m'),
                        'academic_year_id' => $academicYear->id,
                        'due_date' => Carbon::parse($endDate)->addDays(10)->toDateString(),
                        'total_amount' => 350000,
                        'paid_amount' => 0,
                        'status' => 'belum_bayar',
                        'va_number' => $vaNumber,
                        'notes' => 'Tagihan SPP reguler bulanan',
                    ]
                );

                InvoiceItem::firstOrCreate(
                    ['invoice_id' => $invoice->id, 'fee_type_id' => $feeSpp->id],
                    ['amount' => 350000, 'description' => 'SPP Bulanan '.$invoice->period]
                );

                // Pembayaran (30 terverifikasi, 10 menunggu, 2 ditolak, sisa belum bayar)
                if ($sIdx < 30) {
                    Payment::firstOrCreate(
                        ['invoice_id' => $invoice->id, 'external_reference' => 'TRX-BJTM-'.($sIdx + 1000)],
                        [
                            'amount' => 350000,
                            'payment_date' => $startDate,
                            'channel' => 'Bank Jatim VA',
                            'proof_file_id' => $dummyFile->id,
                            'status' => 'terverifikasi',
                            'submitted_by' => $st->user_id,
                            'verified_by' => $bendaharaUser->id,
                            'verified_at' => Carbon::parse($startDate)->addHours(2),
                            'receipt_number' => sprintf('KW/%s/%04d', $now->year, $sIdx + 1),
                        ]
                    );
                    $invoice->update(['paid_amount' => 350000, 'status' => 'lunas']);
                } elseif ($sIdx < 40) {
                    Payment::firstOrCreate(
                        ['invoice_id' => $invoice->id, 'external_reference' => 'TRX-PENDING-'.($sIdx + 1000)],
                        [
                            'amount' => 350000,
                            'payment_date' => $endDate,
                            'channel' => 'Transfer ATM',
                            'proof_file_id' => $dummyFile->id,
                            'status' => 'menunggu_verifikasi',
                            'submitted_by' => $st->user_id,
                        ]
                    );
                } elseif ($sIdx < 42) {
                    Payment::firstOrCreate(
                        ['invoice_id' => $invoice->id, 'external_reference' => 'TRX-REJECT-'.($sIdx + 1000)],
                        [
                            'amount' => 350000,
                            'payment_date' => $endDate,
                            'channel' => 'QRIS',
                            'proof_file_id' => $dummyFile->id,
                            'status' => 'ditolak',
                            'submitted_by' => $st->user_id,
                            'verified_by' => $bendaharaUser->id,
                            'verified_at' => Carbon::parse($endDate)->addHours(1),
                            'rejection_reason' => 'Bukti transfer buram dan tidak terbaca jelas.',
                        ]
                    );
                }
            }

            // 16. Sarana Prasarana (Fasilitas, Booking, Aset, Tiket Kerusakan)
            $facLab = Facility::firstOrCreate(
                ['school_id' => $schoolId, 'name' => 'Laboratorium Komputer Sentral'],
                ['room_id' => $roomLabKom->id, 'facility_type' => 'ruang', 'capacity' => 36, 'requires_approval' => true]
            );

            Booking::firstOrCreate(
                ['facility_id' => $facLab->id, 'start_at' => $endDate.' 08:00:00'],
                [
                    'requested_by' => $teachers['guru.it']->user_id,
                    'end_at' => $endDate.' 11:00:00',
                    'purpose' => 'Simulasi Tes CBT Berbasis Komputer',
                    'status' => 'disetujui',
                    'decided_by' => $superAdminUser->id,
                ]
            );

            $assetPC = Asset::firstOrCreate(
                ['school_id' => $schoolId, 'asset_code' => 'AST-LAB-001'],
                [
                    'room_id' => $roomLabKom->id,
                    'name' => 'PC All-in-One Core i5 Lab Komputer',
                    'category' => 'Elektronik & TI',
                    'asset_condition' => 'baik',
                    'acquired_at' => $yearStart.'-01-10',
                ]
            );

            $ticket = Ticket::firstOrCreate(
                ['school_id' => $schoolId, 'description' => 'Kipas pendingin proyektor ruang 7-A mati saat jam KBM'],
                [
                    'reporter_id' => $walas7ATeacher->user_id,
                    'room_id' => $room7A->id,
                    'asset_id' => $assetPC->id,
                    'assignee_id' => $tuUser->id,
                    'priority' => 'tinggi',
                    'status' => 'diproses',
                ]
            );

            TicketEvent::firstOrCreate(
                ['ticket_id' => $ticket->id, 'event_type' => 'disposisi'],
                ['actor_id' => $tuUser->id, 'note' => 'Teknisi dijadwalkan memeriksa perangkat siang ini.']
            );

            // 17. CBT (Bank Soal, Soal, Ujian, Sesi Siswa)
            $qBank = QuestionBank::firstOrCreate(
                ['school_id' => $schoolId, 'name' => 'Bank Soal Matematika Kelas 7 Ganjil'],
                [
                    'subject_id' => $subjects['MTK']->id,
                    'grade_level_id' => $gradeLevel7->id,
                    'owner_id' => $teachers['guru.mtk']->user_id,
                ]
            );

            $question = Question::firstOrCreate(
                ['bank_id' => $qBank->id, 'cp_code' => 'CP-BIL-01'],
                [
                    'question_type' => 'pg',
                    'body' => json_encode(['text' => 'Hasil dari (-12) + 8 x (-3) adalah ...']),
                    'cognitive_level' => 'MOTS',
                    'difficulty' => 3,
                    'weight' => 1.0,
                    'status' => 'ready',
                ]
            );

            QuestionOption::firstOrCreate(
                ['question_id' => $question->id, 'label' => 'A'],
                ['body' => json_encode(['text' => '-36']), 'is_correct' => true]
            );
            QuestionOption::firstOrCreate(
                ['question_id' => $question->id, 'label' => 'B'],
                ['body' => json_encode(['text' => '36']), 'is_correct' => false]
            );

            $exam = Exam::firstOrCreate(
                ['school_id' => $schoolId, 'title' => 'Asesmen Sumatif Tengah Semester Matematika'],
                [
                    'subject_id' => $subjects['MTK']->id,
                    'created_by' => $teachers['guru.mtk']->user_id,
                    'exam_type' => 'STS',
                    'duration_min' => 60,
                    'start_at' => $endDate.' 08:00:00',
                    'end_at' => $endDate.' 12:00:00',
                    'passing_grade' => 75.0,
                    'status' => 'selesai',
                ]
            );

            ExamQuestion::firstOrCreate(
                ['exam_id' => $exam->id, 'question_id' => $question->id],
                ['sort_order' => 1]
            );

            $examRoom = ExamRoom::firstOrCreate(
                ['exam_id' => $exam->id, 'room_id' => $room7A->id],
                ['session_no' => 1, 'token' => 'MATH7A', 'proctor_id' => $teachers['guru.mtk']->user_id]
            );

            ExamParticipant::firstOrCreate(
                ['exam_id' => $exam->id, 'student_id' => $students[0]->id],
                ['exam_room_id' => $examRoom->id]
            );

            ExamSession::firstOrCreate(
                ['exam_id' => $exam->id, 'student_id' => $students[0]->id],
                [
                    'status' => 'selesai',
                    'started_at' => $endDate.' 08:05:00',
                    'deadline_at' => $endDate.' 09:05:00',
                    'submitted_at' => $endDate.' 08:50:00',
                    'seed' => 'SEED-MATH-01',
                    'score' => 92.5,
                ]
            );

            // 18. Izin/Cuti Guru
            LeaveRequest::firstOrCreate(
                ['requested_by' => $teachers['guru.ipa']->user_id, 'start_date' => $endDate],
                [
                    'student_id' => null,
                    'leave_type' => 'izin',
                    'end_date' => $endDate,
                    'reason' => 'Menghadiri Seminar Nasional Pendidikan IPA di UM',
                    'status' => 'disetujui',
                    'decided_by' => $superAdminUser->id,
                    'decision_notes' => 'Disetujui. Tugas pengganti mandiri telah diberikan kepada siswa.',
                ]
            );

            // 19. Slip Gaji 10 Guru
            $tchIdx = 1;
            foreach ($teachers as $username => $tch) {
                TeacherSalarySlip::firstOrCreate(
                    [
                        'teacher_id' => $tch->id,
                        'period_year' => $now->year,
                        'period_month' => $now->month,
                    ],
                    [
                        'slip_number' => sprintf('SLIP/%04d/%02d/%03d', $now->year, $now->month, $tchIdx),
                        'payment_date' => $startDate,
                        'basic_salary' => 4500000,
                        'allowances' => 1250000,
                        'deductions' => 150000,
                        'net_salary' => 5600000,
                        'status' => 'published',
                        'file_id' => $dummyFile->id,
                        'created_by' => $bendaharaUser->id,
                    ]
                );
                $tchIdx++;
            }

            // 20. Perpustakaan (Buku & Peminjaman)
            $book = LibraryBook::firstOrCreate(
                ['school_id' => $schoolId, 'title' => 'Matematika untuk SMP/MTs Kelas VII Kurikulum Merdeka'],
                [
                    'author' => 'Kemdikbudristek',
                    'isbn' => '978-602-244-380-3',
                    'book_type' => 'fisik',
                    'stock' => 50,
                ]
            );

            Loan::firstOrCreate(
                ['book_id' => $book->id, 'borrower_id' => $students[0]->user_id],
                [
                    'loan_date' => $startDate,
                    'due_date' => Carbon::parse($startDate)->addDays(14)->toDateString(),
                    'returned_at' => Carbon::parse($startDate)->addDays(7)->toDateString(),
                    'fine_amount' => 0,
                ]
            );

            // 21. Notifikasi
            Notification::firstOrCreate(
                ['user_id' => $students[0]->user_id, 'title' => 'Tagihan SPP Terverifikasi'],
                [
                    'notif_type' => 'finance.payment_verified',
                    'body' => 'Pembayaran tagihan SPP bulan ini berhasil diverifikasi oleh bendahara.',
                    'read_at' => now(),
                ]
            );

            // 22. Audit Logs
            AuditLog::firstOrCreate(
                ['action' => 'academic.report_card_locked', 'entity' => 'ReportCard'],
                [
                    'actor_id' => $superAdminUser->id,
                    'new_value' => ['semester' => $semester->name, 'count' => count($students)],
                    'ip' => '127.0.0.1',
                    'user_agent' => 'MonthlySimulationSeeder',
                ]
            );

            // 23. PPDB (Pendaftar Baru & Berkas)
            $wave = PpdbWave::firstOrCreate(
                ['school_id' => $schoolId, 'name' => 'PPDB Gelombang 1 '.$academicYearName],
                [
                    'open_at' => $startDate.' 00:00:00',
                    'close_at' => Carbon::parse($endDate)->addDays(30)->toDateTimeString(),
                    'result_publish_at' => Carbon::parse($endDate)->addDays(40)->toDateTimeString(),
                    'status' => 'aktif',
                ]
            );

            $trackZonasi = PpdbTrack::firstOrCreate(
                ['wave_id' => $wave->id, 'code' => 'zonasi'],
                ['name' => 'Jalur Zonasi Domisili', 'quota' => 60]
            );

            $applicantStatuses = [
                ['name' => 'Calon Siswa 01', 'status' => 'draft', 'decision' => null],
                ['name' => 'Calon Siswa 02', 'status' => 'submitted', 'decision' => null],
                ['name' => 'Calon Siswa 03', 'status' => 'verified', 'decision' => null],
                ['name' => 'Calon Siswa 04', 'status' => 'selected', 'decision' => 'diterima'],
                ['name' => 'Calon Siswa 05', 'status' => 'waitlist', 'decision' => 'cadangan'],
                ['name' => 'Calon Siswa 06', 'status' => 'rejected', 'decision' => 'ditolak'],
            ];

            foreach ($applicantStatuses as $aIdx => $appData) {
                $applicant = Applicant::firstOrCreate(
                    ['registration_no' => sprintf('PPDB-%04d-%03d', $now->year, $aIdx + 1)],
                    [
                        'wave_id' => $wave->id,
                        'track_id' => $trackZonasi->id,
                        'nik_enc' => 'enc_ppdb_357900'.($aIdx + 1),
                        'full_name' => $appData['name'],
                        'birth_date' => '2014-01-10',
                        'status' => $appData['status'],
                        'decision' => $appData['decision'],
                        'score' => 88.5,
                    ]
                );

                ApplicantParent::firstOrCreate(
                    ['applicant_id' => $applicant->id, 'parent_type' => 'ayah'],
                    ['name' => 'Wali '.$appData['name'], 'phone' => '085712345'.($aIdx + 1)]
                );

                ApplicantDocument::firstOrCreate(
                    ['applicant_id' => $applicant->id, 'document_type' => 'Kartu Keluarga'],
                    ['file_id' => $dummyFile->id, 'status' => $appData['status'] === 'verified' ? 'valid' : 'menunggu']
                );
            }
        });

        // 24. Export Credentials Table to Console & File
        $credentialRows = [
            ['Super Administrator', 'admin_demo', $password, 'Portal Admin Back-Office (2FA)'],
            ['Bendahara / Keuangan', 'bendahara_demo', $password, 'Portal Admin Keuangan (2FA)'],
            ['Kepala Sekolah', 'kepsek_demo', $password, 'Portal Eksekutif & Admin (2FA)'],
            ['Admin Tata Usaha', 'staf_tu', $password, 'Portal Admin TU'],
            ['Staf Civitas', 'staf_demo', $password, 'Portal Staf'],
            ['Wali Kelas 7-A (Guru MTK)', 'guru.mtk', $password, 'Portal Guru & Walas'],
            ['Wali Kelas 7-B (Guru B.Indo)', 'guru.indo', $password, 'Portal Guru & Walas'],
            ['Guru BK / Konselor', 'guru.bk', $password, 'Portal Guru BK & Konseling'],
            ['Siswa Kelas 7-A (Teladan)', 'siswa_001', $password, 'Portal Siswa'],
            ['Siswa Kelas 7-A (Problem)', 'siswa_004', $password, 'Portal Siswa (Alpa & Remedial)'],
            ['Siswa Kelas 7-B', 'siswa_026', $password, 'Portal Siswa'],
            ['Wali Murid Siswa 1', 'ortu_001', $password, 'Portal Orang Tua / Finansial'],
            ['Wali Murid Siswa 4', 'ortu_004', $password, 'Portal Orang Tua / Kasus BK'],
            ['Wali Murid Siswa 26', 'ortu_026', $password, 'Portal Orang Tua'],
        ];

        // Format credentials text
        $credsContent = "========================================================================================\n";
        $credsContent .= " SISTLABUM SIMULASI UAT - KREDENSIAL AKUN UJI\n";
        $credsContent .= ' Tanggal Generate: '.Carbon::now()->toDateTimeString()."\n";
        $credsContent .= " Password Default: {$password}\n";
        $credsContent .= "========================================================================================\n";
        $credsContent .= sprintf("%-28s | %-16s | %-14s | %s\n", 'Peran / Persona', 'Username', 'Password', 'Catatan Akses');
        $credsContent .= str_repeat('-', 88)."\n";
        foreach ($credentialRows as $row) {
            $credsContent .= sprintf("%-28s | %-16s | %-14s | %s\n", $row[0], $row[1], $row[2], $row[3]);
        }
        $credsContent .= "========================================================================================\n";

        // Save to storage/app/seed-credentials.txt
        $credsPath = storage_path('app/seed-credentials.txt');
        @file_put_contents($credsPath, $credsContent);

        // Display to console if command is present
        if ($this->command) {
            $this->command->info("\nSeeding Simulasi 1 Bulan Kalender Civitas Selesai!");
            $this->command->table(['Peran / Persona', 'Username', 'Password', 'Catatan Akses'], $credentialRows);
            $this->command->info("Kredensial tersimpan di: {$credsPath}\n");
        }
    }
}
