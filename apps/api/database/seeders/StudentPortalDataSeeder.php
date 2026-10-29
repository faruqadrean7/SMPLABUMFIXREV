<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Achievement;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\ClassStudent;
use App\Models\CounselingRequest;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\ExamRoom;
use App\Models\File;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradeLevel;
use App\Models\GradePeriod;
use App\Models\Role;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\SiteSetting;
use App\Models\Student;
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

class StudentPortalDataSeeder extends Seeder
{
    public const DEFAULT_SCHOOL_ID = '00000000-0000-0000-0000-000000000001';

    public function run(): void
    {
        DB::transaction(function () {
            // 1. Resolve Tenant School ID
            $schoolId = env('UAT_SCHOOL_ID') ?: env('DEFAULT_SCHOOL_ID');
            $existingSite = SiteSetting::where('setting_key', 'general_info')->first();
            if ($existingSite && $existingSite->school_id) {
                $schoolId = $existingSite->school_id;
            }
            if (empty($schoolId)) {
                $schoolId = self::DEFAULT_SCHOOL_ID;
            }

            // Ensure site setting
            SiteSetting::firstOrCreate(
                ['setting_key' => 'general_info'],
                [
                    'school_id' => $schoolId,
                    'setting_value' => [
                        'school_name' => 'SMP Laboratorium Universitas Negeri Malang',
                        'npsn' => '20533800',
                        'academic_year' => '2026/2027',
                        'semester' => 'Ganjil',
                    ],
                ]
            );

            // 2. Academic Year & Semester
            $academicYear = AcademicYear::firstOrCreate(
                ['school_id' => $schoolId, 'name' => '2026/2027'],
                [
                    'start_date' => '2026-07-01',
                    'end_date' => '2027-06-30',
                    'is_active' => true,
                ]
            );

            $semester = Semester::firstOrCreate(
                [
                    'academic_year_id' => $academicYear->id,
                    'name' => 'Ganjil',
                ],
                [
                    'start_date' => '2026-07-01',
                    'end_date' => '2026-12-31',
                    'is_active' => true,
                ]
            );

            // 3. Grade Level & Class 7-A
            $gradeLevel7 = GradeLevel::firstOrCreate(
                ['name' => 'Kelas 7'],
                [
                    'school_id' => $schoolId,
                    'level_number' => 7,
                    'sort_order' => 7,
                ]
            );

            $studentRole = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
            $teacherRole = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru Mata Pelajaran', 'is_system' => true]);
            $counselorRole = Role::firstOrCreate(['code' => 'counselor'], ['name' => 'Guru BK', 'is_system' => true]);

            $defaultPassword = Hash::make('SistLabUM2026!UAT');

            // 4. Helper User & Teacher Generator
            $makeUser = function (string $username, string $email, array $roleCodes, string $name) use ($defaultPassword): User {
                $user = User::firstOrNew(['username' => $username]);
                $user->email = $email;
                $user->password_hash = $defaultPassword;
                $user->status = 'active';
                $user->must_change_password = false;
                $user->save();

                foreach ($roleCodes as $code) {
                    $r = Role::firstOrCreate(['code' => $code], ['name' => ucfirst($code), 'is_system' => true]);
                    if (! $user->roles()->where('role_id', $r->id)->exists()) {
                        $user->roles()->attach($r->id);
                    }
                }

                return $user;
            };

            // Teachers
            $userMtk = $makeUser('guru.mtk', 'guru.mtk@smp-labum.sch.id', ['teacher'], 'Agus Santoso, M.Pd.');
            $teacherMtk = Teacher::firstOrNew(['user_id' => $userMtk->id]);
            $teacherMtk->school_id = $schoolId;
            $teacherMtk->nip = '198501012010011001';
            $teacherMtk->full_name = 'Agus Santoso, M.Pd.';
            $teacherMtk->status = 'aktif';
            $teacherMtk->position = 'Guru Matematika';
            $teacherMtk->save();

            $userInf = $makeUser('guru.inf', 'guru.inf@smp-labum.sch.id', ['teacher'], 'Rizka Amanda, S.Kom., M.Cs.');
            $teacherInf = Teacher::firstOrNew(['user_id' => $userInf->id]);
            $teacherInf->school_id = $schoolId;
            $teacherInf->nip = '199003152015022002';
            $teacherInf->full_name = 'Rizka Amanda, S.Kom., M.Cs.';
            $teacherInf->status = 'aktif';
            $teacherInf->position = 'Guru Informatika';
            $teacherInf->save();

            $userIpa = $makeUser('guru.ipa', 'guru.ipa@smp-labum.sch.id', ['teacher'], 'Hendra Gunawan, M.Si.');
            $teacherIpa = Teacher::firstOrNew(['user_id' => $userIpa->id]);
            $teacherIpa->school_id = $schoolId;
            $teacherIpa->nip = '198207202008011003';
            $teacherIpa->full_name = 'Hendra Gunawan, M.Si.';
            $teacherIpa->status = 'aktif';
            $teacherIpa->position = 'Guru IPA';
            $teacherIpa->save();

            $userBin = $makeUser('guru.bin', 'guru.bin@smp-labum.sch.id', ['teacher'], 'Wahyuni, M.Pd.');
            $teacherBin = Teacher::firstOrNew(['user_id' => $userBin->id]);
            $teacherBin->school_id = $schoolId;
            $teacherBin->nip = '198711102012022004';
            $teacherBin->full_name = 'Wahyuni, M.Pd.';
            $teacherBin->status = 'aktif';
            $teacherBin->position = 'Guru Bahasa Indonesia';
            $teacherBin->save();

            $userEng = $makeUser('guru.eng', 'guru.eng@smp-labum.sch.id', ['teacher'], 'David Christian, M.Ed.');
            $teacherEng = Teacher::firstOrNew(['user_id' => $userEng->id]);
            $teacherEng->school_id = $schoolId;
            $teacherEng->nip = '198904252014011005';
            $teacherEng->full_name = 'David Christian, M.Ed.';
            $teacherEng->status = 'aktif';
            $teacherEng->position = 'Guru Bahasa Inggris';
            $teacherEng->save();

            $userPpkn = $makeUser('guru.ppkn', 'guru.ppkn@smp-labum.sch.id', ['teacher'], 'Dewi Astuti, S.Pd.');
            $teacherPpkn = Teacher::firstOrNew(['user_id' => $userPpkn->id]);
            $teacherPpkn->school_id = $schoolId;
            $teacherPpkn->nip = '199205122016022006';
            $teacherPpkn->full_name = 'Dewi Astuti, S.Pd.';
            $teacherPpkn->status = 'aktif';
            $teacherPpkn->position = 'Guru PPKn';
            $teacherPpkn->save();

            $userBk = $makeUser('bk.guru', 'bk.guru@smp-labum.sch.id', ['counselor', 'teacher'], 'Siti Nurhaliza, S.Psi., M.Pd.');
            $teacherBk = Teacher::firstOrNew(['user_id' => $userBk->id]);
            $teacherBk->school_id = $schoolId;
            $teacherBk->nip = '198608182011012007';
            $teacherBk->full_name = 'Siti Nurhaliza, S.Psi., M.Pd.';
            $teacherBk->status = 'aktif';
            $teacherBk->position = 'Guru BK';
            $teacherBk->save();

            // Walas 7-A
            $userWalas = $makeUser('walas.7a', 'walas.7a@smp-labum.sch.id', ['homeroom', 'teacher'], 'Nur Hayati, S.Pd.');
            $teacherWalas = Teacher::firstOrNew(['user_id' => $userWalas->id]);
            $teacherWalas->school_id = $schoolId;
            $teacherWalas->nip = '198402142009022003';
            $teacherWalas->full_name = 'Nur Hayati, S.Pd.';
            $teacherWalas->status = 'aktif';
            $teacherWalas->position = 'Wali Kelas 7-A';
            $teacherWalas->save();

            // School Class 7-A
            $class7A = SchoolClass::firstOrNew([
                'academic_year_id' => $academicYear->id,
                'name' => '7-A',
            ]);
            $class7A->school_id = $schoolId;
            $class7A->grade_level_id = $gradeLevel7->id;
            $class7A->homeroom_teacher_id = $teacherWalas->id;
            $class7A->capacity = 36;
            $class7A->save();

            // 5. Subjects
            $subjectsDef = [
                ['code' => 'MTK-7', 'name' => 'Matematika', 'group' => 'wajib', 'teacher' => $teacherMtk],
                ['code' => 'INF-7', 'name' => 'Informatika', 'group' => 'wajib', 'teacher' => $teacherInf],
                ['code' => 'IPA-7', 'name' => 'Ilmu Pengetahuan Alam', 'group' => 'wajib', 'teacher' => $teacherIpa],
                ['code' => 'BIN-7', 'name' => 'Bahasa Indonesia', 'group' => 'wajib', 'teacher' => $teacherBin],
                ['code' => 'ENG-7', 'name' => 'Bahasa Inggris', 'group' => 'wajib', 'teacher' => $teacherEng],
                ['code' => 'PPKN-7', 'name' => 'Pendidikan Pancasila', 'group' => 'wajib', 'teacher' => $teacherPpkn],
            ];

            $subjectMap = [];
            foreach ($subjectsDef as $sDef) {
                $sub = Subject::firstOrCreate(
                    ['code' => $sDef['code']],
                    ['school_id' => $schoolId, 'name' => $sDef['name'], 'subject_group' => $sDef['group']]
                );
                $subjectMap[$sDef['code']] = $sub;

                TeachingAssignment::firstOrCreate(
                    [
                        'semester_id' => $semester->id,
                        'class_id' => $class7A->id,
                        'subject_id' => $sub->id,
                    ],
                    [
                        'teacher_id' => $sDef['teacher']->id,
                        'hours_per_week' => 4,
                    ]
                );
            }

            // 6. Rooms
            $room7A = Room::firstOrCreate(
                ['code' => 'R-7A'],
                ['school_id' => $schoolId, 'name' => 'Ruang Kelas 7-A', 'room_type' => 'kelas', 'capacity' => 36]
            );
            $roomLabKom = Room::firstOrCreate(
                ['code' => 'LAB-KOM-1'],
                ['school_id' => $schoolId, 'name' => 'Laboratorium Komputer 1', 'room_type' => 'laboratorium', 'capacity' => 36]
            );
            $roomLabIpa = Room::firstOrCreate(
                ['code' => 'LAB-IPA'],
                ['school_id' => $schoolId, 'name' => 'Laboratorium IPA Terpadu', 'room_type' => 'laboratorium', 'capacity' => 36]
            );
            $roomMulti = Room::firstOrCreate(
                ['code' => 'R-MULTI'],
                ['school_id' => $schoolId, 'name' => 'Ruang Multimedia & Audio Visual', 'room_type' => 'kelas', 'capacity' => 40]
            );

            // 7. Time Slots
            $slotsDef = [
                ['name' => 'JP 1 (07:00 - 07:45)', 'start' => '07:00:00', 'end' => '07:45:00'],
                ['name' => 'JP 2 (07:45 - 08:30)', 'start' => '07:45:00', 'end' => '08:30:00'],
                ['name' => 'JP 3 (08:30 - 09:15)', 'start' => '08:30:00', 'end' => '09:15:00'],
                ['name' => 'JP 4 (09:45 - 10:30)', 'start' => '09:45:00', 'end' => '10:30:00'],
                ['name' => 'JP 5 (10:30 - 11:15)', 'start' => '10:30:00', 'end' => '11:15:00'],
                ['name' => 'JP 6 (11:15 - 12:00)', 'start' => '11:15:00', 'end' => '12:00:00'],
                ['name' => 'JP 7 (12:45 - 13:30)', 'start' => '12:45:00', 'end' => '13:30:00'],
                ['name' => 'JP 8 (13:30 - 14:15)', 'start' => '13:30:00', 'end' => '14:15:00'],
            ];

            $timeSlotList = [];
            foreach ($slotsDef as $idx => $ts) {
                $timeSlotList[$idx + 1] = TimeSlot::firstOrCreate(
                    ['school_id' => $schoolId, 'name' => $ts['name']],
                    ['start_time' => $ts['start'], 'end_time' => $ts['end'], 'day_type' => 'regular']
                );
            }

            // 8. Weekly Schedules (Senin s.d. Jumat)
            $scheduleMatrix = [
                // Day 1: Senin
                ['day' => 1, 'slot' => 1, 'sub' => 'INF-7', 'teacher' => $teacherInf, 'room' => $roomLabKom],
                ['day' => 1, 'slot' => 2, 'sub' => 'INF-7', 'teacher' => $teacherInf, 'room' => $roomLabKom],
                ['day' => 1, 'slot' => 3, 'sub' => 'MTK-7', 'teacher' => $teacherMtk, 'room' => $room7A],
                ['day' => 1, 'slot' => 4, 'sub' => 'MTK-7', 'teacher' => $teacherMtk, 'room' => $room7A],
                ['day' => 1, 'slot' => 5, 'sub' => 'BIN-7', 'teacher' => $teacherBin, 'room' => $room7A],
                ['day' => 1, 'slot' => 6, 'sub' => 'BIN-7', 'teacher' => $teacherBin, 'room' => $room7A],
                ['day' => 1, 'slot' => 7, 'sub' => 'PPKN-7', 'teacher' => $teacherPpkn, 'room' => $room7A],
                ['day' => 1, 'slot' => 8, 'sub' => 'PPKN-7', 'teacher' => $teacherPpkn, 'room' => $room7A],

                // Day 2: Selasa (Today)
                ['day' => 2, 'slot' => 1, 'sub' => 'IPA-7', 'teacher' => $teacherIpa, 'room' => $roomLabIpa],
                ['day' => 2, 'slot' => 2, 'sub' => 'IPA-7', 'teacher' => $teacherIpa, 'room' => $roomLabIpa],
                ['day' => 2, 'slot' => 3, 'sub' => 'ENG-7', 'teacher' => $teacherEng, 'room' => $roomMulti],
                ['day' => 2, 'slot' => 4, 'sub' => 'ENG-7', 'teacher' => $teacherEng, 'room' => $roomMulti],
                ['day' => 2, 'slot' => 5, 'sub' => 'MTK-7', 'teacher' => $teacherMtk, 'room' => $room7A],
                ['day' => 2, 'slot' => 6, 'sub' => 'MTK-7', 'teacher' => $teacherMtk, 'room' => $room7A],
                ['day' => 2, 'slot' => 7, 'sub' => 'INF-7', 'teacher' => $teacherInf, 'room' => $roomLabKom],
                ['day' => 2, 'slot' => 8, 'sub' => 'INF-7', 'teacher' => $teacherInf, 'room' => $roomLabKom],

                // Day 3: Rabu
                ['day' => 3, 'slot' => 1, 'sub' => 'BIN-7', 'teacher' => $teacherBin, 'room' => $room7A],
                ['day' => 3, 'slot' => 2, 'sub' => 'BIN-7', 'teacher' => $teacherBin, 'room' => $room7A],
                ['day' => 3, 'slot' => 3, 'sub' => 'IPA-7', 'teacher' => $teacherIpa, 'room' => $roomLabIpa],
                ['day' => 3, 'slot' => 4, 'sub' => 'IPA-7', 'teacher' => $teacherIpa, 'room' => $roomLabIpa],
                ['day' => 3, 'slot' => 5, 'sub' => 'ENG-7', 'teacher' => $teacherEng, 'room' => $roomMulti],
                ['day' => 3, 'slot' => 6, 'sub' => 'ENG-7', 'teacher' => $teacherEng, 'room' => $roomMulti],

                // Day 4: Kamis
                ['day' => 4, 'slot' => 1, 'sub' => 'MTK-7', 'teacher' => $teacherMtk, 'room' => $room7A],
                ['day' => 4, 'slot' => 2, 'sub' => 'MTK-7', 'teacher' => $teacherMtk, 'room' => $room7A],
                ['day' => 4, 'slot' => 3, 'sub' => 'PPKN-7', 'teacher' => $teacherPpkn, 'room' => $room7A],
                ['day' => 4, 'slot' => 4, 'sub' => 'PPKN-7', 'teacher' => $teacherPpkn, 'room' => $room7A],
                ['day' => 4, 'slot' => 5, 'sub' => 'INF-7', 'teacher' => $teacherInf, 'room' => $roomLabKom],
                ['day' => 4, 'slot' => 6, 'sub' => 'INF-7', 'teacher' => $teacherInf, 'room' => $roomLabKom],

                // Day 5: Jumat
                ['day' => 5, 'slot' => 1, 'sub' => 'ENG-7', 'teacher' => $teacherEng, 'room' => $roomMulti],
                ['day' => 5, 'slot' => 2, 'sub' => 'ENG-7', 'teacher' => $teacherEng, 'room' => $roomMulti],
                ['day' => 5, 'slot' => 3, 'sub' => 'BIN-7', 'teacher' => $teacherBin, 'room' => $room7A],
                ['day' => 5, 'slot' => 4, 'sub' => 'BIN-7', 'teacher' => $teacherBin, 'room' => $room7A],
            ];

            foreach ($scheduleMatrix as $item) {
                Schedule::firstOrCreate(
                    [
                        'semester_id' => $semester->id,
                        'class_id' => $class7A->id,
                        'day_of_week' => $item['day'],
                        'time_slot_id' => $timeSlotList[$item['slot']]->id,
                    ],
                    [
                        'subject_id' => $subjectMap[$item['sub']]->id,
                        'teacher_id' => $item['teacher']->id,
                        'room_id' => $item['room']->id,
                    ]
                );
            }

            // 9. Main Student: siswa.lab.001
            $userSiswa = $makeUser('siswa.lab.001', 'siswa.lab.001@smp-labum.sch.id', ['student'], 'Faris Farhan Adrean');
            $student = Student::firstOrNew(['user_id' => $userSiswa->id]);
            $student->school_id = $schoolId;
            $student->nis = '26001';
            $student->nisn = '0099001001';
            $student->nik_enc = '3578010101090001';
            $student->full_name = 'Faris Farhan Adrean';
            $student->gender = 'L';
            $student->birth_place = 'Malang';
            $student->birth_date = '2012-05-15';
            $student->religion = 'Islam';
            $student->address = 'Jl. Simpang Bogor No. 1, Malang';
            $student->status = 'aktif';
            $student->entry_date = '2026-07-01';
            $student->save();

            ClassStudent::firstOrCreate(
                ['class_id' => $class7A->id, 'student_id' => $student->id],
                ['joined_at' => '2026-07-01']
            );

            // 4 Classmates for variety in Class 7-A
            $classmates = [
                ['username' => 'siswa.lab.002', 'nis' => '26002', 'nisn' => '0099001002', 'name' => 'Nadia Putri Ananda', 'gender' => 'P'],
                ['username' => 'siswa.lab.003', 'nis' => '26003', 'nisn' => '0099001003', 'name' => 'Bima Arya Pratama', 'gender' => 'L'],
                ['username' => 'siswa.lab.004', 'nis' => '26004', 'nisn' => '0099001004', 'name' => 'Citra Lestari Rahayu', 'gender' => 'P'],
                ['username' => 'siswa.lab.005', 'nis' => '26005', 'nisn' => '0099001005', 'name' => 'Dimas Bagus Wicaksono', 'gender' => 'L'],
            ];

            foreach ($classmates as $cm) {
                $uCm = $makeUser($cm['username'], "{$cm['username']}@smp-labum.sch.id", ['student'], $cm['name']);
                $sCm = Student::firstOrNew(['user_id' => $uCm->id]);
                $sCm->school_id = $schoolId;
                $sCm->nis = $cm['nis'];
                $sCm->nisn = $cm['nisn'];
                $sCm->nik_enc = '357801010109'.substr($cm['nis'], -4);
                $sCm->full_name = $cm['name'];
                $sCm->gender = $cm['gender'];
                $sCm->birth_place = 'Malang';
                $sCm->birth_date = '2012-06-10';
                $sCm->religion = 'Islam';
                $sCm->address = 'Jl. Veteran No. 8, Malang';
                $sCm->status = 'aktif';
                $sCm->entry_date = '2026-07-01';
                $sCm->save();

                ClassStudent::firstOrCreate(
                    ['class_id' => $class7A->id, 'student_id' => $sCm->id],
                    ['joined_at' => '2026-07-01']
                );
            }

            // 10. Attendance Records for siswa.lab.001 (25 past school days)
            $now = Carbon::parse('2026-10-20');
            $attCount = 0;
            $dayOffset = 1;
            while ($attCount < 25) {
                $targetDate = $now->copy()->subDays($dayOffset);
                $dayOffset++;
                if ($targetDate->isWeekend()) {
                    continue;
                }

                $status = 'hadir';
                $note = null;
                if ($attCount === 7) {
                    $status = 'izin';
                    $note = 'Izin menghadiri acara keluarga';
                } elseif ($attCount === 18) {
                    $status = 'sakit';
                    $note = 'Sakit flu & demam, surat dokter terlampir';
                }

                Attendance::firstOrCreate(
                    [
                        'student_id' => $student->id,
                        'att_date' => $targetDate->toDateString(),
                    ],
                    [
                        'status' => $status,
                        'source' => 'manual',
                        'note' => $note,
                        'recorded_by' => $userWalas->id,
                    ]
                );
                $attCount++;
            }

            // 11. Grade Components & Grades for all subjects
            $gradePeriod = GradePeriod::firstOrCreate(
                [
                    'semester_id' => $semester->id,
                    'month' => 10,
                ],
                [
                    'status' => 'open',
                ]
            );

            $componentCodes = [
                ['code' => 'F1', 'name' => 'Formatif 1 (Tugas Harian)', 'weight' => 20.0],
                ['code' => 'F2', 'name' => 'Formatif 2 (Praktik/Kuis)', 'weight' => 20.0],
                ['code' => 'F3', 'name' => 'Formatif 3 (Proyek/Portofolio)', 'weight' => 20.0],
                ['code' => 'STS', 'name' => 'Sumatif Tengah Semester', 'weight' => 20.0],
                ['code' => 'SAS', 'name' => 'Sumatif Akhir Semester', 'weight' => 20.0],
            ];

            $scoresPerSubject = [
                'INF-7' => ['F1' => 90.0, 'F2' => 85.0, 'F3' => 92.0, 'STS' => 88.0],
                'MTK-7' => ['F1' => 82.0, 'F2' => 85.0, 'F3' => 88.0, 'STS' => 80.0],
                'IPA-7' => ['F1' => 88.0, 'F2' => 90.0, 'F3' => 85.0, 'STS' => 86.0],
                'BIN-7' => ['F1' => 85.0, 'F2' => 84.0, 'F3' => 86.0, 'STS' => 88.0],
                'ENG-7' => ['F1' => 90.0, 'F2' => 92.0, 'F3' => 89.0, 'STS' => 91.0],
                'PPKN-7' => ['F1' => 88.0, 'F2' => 85.0, 'F3' => 87.0, 'STS' => 86.0],
            ];

            foreach ($subjectsDef as $sDef) {
                $sub = $subjectMap[$sDef['code']];
                $scores = $scoresPerSubject[$sDef['code']] ?? ['F1' => 85.0, 'STS' => 85.0];

                foreach ($componentCodes as $cDef) {
                    $comp = GradeComponent::firstOrCreate(
                        [
                            'subject_id' => $sub->id,
                            'semester_id' => $semester->id,
                            'class_id' => $class7A->id,
                            'code' => $cDef['code'],
                        ],
                        [
                            'name' => $cDef['name'],
                            'weight' => $cDef['weight'],
                        ]
                    );

                    if (isset($scores[$cDef['code']])) {
                        Grade::firstOrCreate(
                            [
                                'student_id' => $student->id,
                                'component_id' => $comp->id,
                                'period_id' => $gradePeriod->id,
                            ],
                            [
                                'value' => $scores[$cDef['code']],
                                'source' => 'manual',
                                'entered_by' => $sDef['teacher']->user_id,
                            ]
                        );
                    }
                }
            }

            // 12. Assignments & Submissions
            // Assignment 1: Aktif
            $asg1 = Assignment::firstOrCreate(
                [
                    'class_id' => $class7A->id,
                    'subject_id' => $subjectMap['INF-7']->id,
                    'title' => 'Proyek Pemrograman Scratch: Animasi Interaktif Cerita Rakyat',
                ],
                [
                    'teacher_id' => $teacherInf->id,
                    'description' => 'Buatlah satu proyek Scratch dengan minimal 2 sprite, dialog keyboard/klik, dan alur cerita rakyat Nusantara berdurasi minimal 1 menit.',
                    'due_at' => Carbon::now()->addDays(3)->setTime(23, 59, 0),
                    'max_score' => 100.0,
                ]
            );

            // Assignment 2: Dikumpulkan (menunggu dinilai)
            $asg2 = Assignment::firstOrCreate(
                [
                    'class_id' => $class7A->id,
                    'subject_id' => $subjectMap['BIN-7']->id,
                    'title' => 'Teks Prosedur: Tata Cara Pembuatan Makanan Tradisional Malang',
                ],
                [
                    'teacher_id' => $teacherBin->id,
                    'description' => 'Tuliskan teks prosedur lengkap dengan struktur: Tujuan, Alat & Bahan, serta Langkah-langkah pembuatan.',
                    'due_at' => Carbon::now()->subDays(2)->setTime(17, 0, 0),
                    'max_score' => 100.0,
                ]
            );
            AssignmentSubmission::firstOrCreate(
                [
                    'assignment_id' => $asg2->id,
                    'student_id' => $student->id,
                ],
                [
                    'notes' => 'Telah mengumpulkan berkas teks prosedur pembuatan Bakso Malang.',
                    'submitted_at' => Carbon::now()->subDays(2)->setTime(14, 30, 0),
                    'score' => null,
                ]
            );

            // Assignment 3: Dinilai
            $asg3 = Assignment::firstOrCreate(
                [
                    'class_id' => $class7A->id,
                    'subject_id' => $subjectMap['IPA-7']->id,
                    'title' => 'Laporan Observasi Komponen Biotik dan Abiotik Kebun Sekolah',
                ],
                [
                    'teacher_id' => $teacherIpa->id,
                    'description' => 'Catat 5 komponen biotik dan 5 komponen abiotik yang ada di lingkungan taman sekolah serta interaksi yang terjadi.',
                    'due_at' => Carbon::now()->subDays(10)->setTime(15, 0, 0),
                    'max_score' => 100.0,
                ]
            );
            AssignmentSubmission::firstOrCreate(
                [
                    'assignment_id' => $asg3->id,
                    'student_id' => $student->id,
                ],
                [
                    'notes' => 'Laporan lengkap dengan dokumentasi foto mikroskopis dan tabel pengamatan.',
                    'submitted_at' => Carbon::now()->subDays(10)->setTime(11, 20, 0),
                    'score' => 95.0,
                    'feedback' => 'Pengamatan sangat teliti dan detail! Analisis rantai makanan ditulis dengan runtut.',
                ]
            );

            // Assignment 4: Dinilai
            $asg4 = Assignment::firstOrCreate(
                [
                    'class_id' => $class7A->id,
                    'subject_id' => $subjectMap['MTK-7']->id,
                    'title' => 'Latihan Soal Aljabar: Penyederhanaan Suku Sejenis',
                ],
                [
                    'teacher_id' => $teacherMtk->id,
                    'description' => 'Kerjakan 10 butir soal operasi penjumlahan dan perkalian aljabar linear satu variabel.',
                    'due_at' => Carbon::now()->subDays(14)->setTime(17, 0, 0),
                    'max_score' => 100.0,
                ]
            );
            AssignmentSubmission::firstOrCreate(
                [
                    'assignment_id' => $asg4->id,
                    'student_id' => $student->id,
                ],
                [
                    'notes' => 'Semua jawaban dikerjakan lengkap dengan cara di buku berpetak.',
                    'submitted_at' => Carbon::now()->subDays(14)->setTime(16, 0, 0),
                    'score' => 88.0,
                    'feedback' => 'Bagus sekali Faris, perhitungan nomor 4 dan 5 tepat dan rapi.',
                ]
            );

            // 13. CBT Exams & Participants
            // Exam 1: Aktif Hari Ini
            $exam1 = Exam::firstOrCreate(
                ['title' => 'Penilaian Formatif Bab 2: Struktur Algoritma & Logika'],
                [
                    'school_id' => $schoolId,
                    'subject_id' => $subjectMap['INF-7']->id,
                    'created_by' => $userInf->id,
                    'exam_type' => 'formatif',
                    'duration_min' => 60,
                    'start_at' => Carbon::now()->startOfDay()->addHours(7),
                    'end_at' => Carbon::now()->endOfDay(),
                    'passing_grade' => 75.0,
                    'status' => 'berlangsung',
                    'shuffle_questions' => true,
                    'shuffle_options' => true,
                ]
            );

            $examRoom1 = ExamRoom::firstOrCreate(
                ['exam_id' => $exam1->id, 'session_no' => 1],
                [
                    'room_id' => $roomLabKom->id,
                    'proctor_id' => $userInf->id,
                    'token' => 'INF-LAB7',
                    'token_expires_at' => Carbon::now()->endOfDay(),
                ]
            );

            ExamParticipant::firstOrCreate(
                ['exam_id' => $exam1->id, 'student_id' => $student->id],
                ['exam_room_id' => $examRoom1->id]
            );

            // Exam 2: Terjadwal Mendatang
            $exam2 = Exam::firstOrCreate(
                ['title' => 'Sumatif Tengah Semester: Matematika Aljabar & Bilangan Bulat'],
                [
                    'school_id' => $schoolId,
                    'subject_id' => $subjectMap['MTK-7']->id,
                    'created_by' => $userMtk->id,
                    'exam_type' => 'sts',
                    'duration_min' => 90,
                    'start_at' => Carbon::now()->addDays(3)->setTime(8, 0, 0),
                    'end_at' => Carbon::now()->addDays(3)->setTime(10, 0, 0),
                    'passing_grade' => 75.0,
                    'status' => 'dijadwalkan',
                    'shuffle_questions' => true,
                    'shuffle_options' => true,
                ]
            );

            $examRoom2 = ExamRoom::firstOrCreate(
                ['exam_id' => $exam2->id, 'session_no' => 1],
                [
                    'room_id' => $room7A->id,
                    'proctor_id' => $userMtk->id,
                    'token' => 'MTK-STS7',
                    'token_expires_at' => Carbon::now()->addDays(3)->setTime(12, 0, 0),
                ]
            );

            ExamParticipant::firstOrCreate(
                ['exam_id' => $exam2->id, 'student_id' => $student->id],
                ['exam_room_id' => $examRoom2->id]
            );

            // Exam 3: Selesai
            $exam3 = Exam::firstOrCreate(
                ['title' => 'Kuis Harian 1: Tata Surya & Gravitasi Bumi'],
                [
                    'school_id' => $schoolId,
                    'subject_id' => $subjectMap['IPA-7']->id,
                    'created_by' => $userIpa->id,
                    'exam_type' => 'kuis',
                    'duration_min' => 45,
                    'start_at' => Carbon::now()->subDays(7)->setTime(8, 0, 0),
                    'end_at' => Carbon::now()->subDays(7)->setTime(9, 30, 0),
                    'passing_grade' => 75.0,
                    'status' => 'selesai',
                ]
            );

            $examRoom3 = ExamRoom::firstOrCreate(
                ['exam_id' => $exam3->id, 'session_no' => 1],
                [
                    'room_id' => $roomLabIpa->id,
                    'proctor_id' => $userIpa->id,
                    'token' => 'IPA-KUIS1',
                    'token_expires_at' => Carbon::now()->subDays(7)->setTime(12, 0, 0),
                ]
            );

            ExamParticipant::firstOrCreate(
                ['exam_id' => $exam3->id, 'student_id' => $student->id],
                ['exam_room_id' => $examRoom3->id]
            );

            // 14. Counseling Requests
            CounselingRequest::firstOrCreate(
                [
                    'student_id' => $student->id,
                    'topic' => 'Konsultasi Minat Bakat & Peminatan Jalur Prestasi Robotika',
                ],
                [
                    'requested_by' => $userSiswa->id,
                    'requester_role' => 'student',
                    'preferred_date' => '2026-09-10',
                    'preferred_time' => '10:00',
                    'counselor_id' => $userBk->id,
                    'status' => 'selesai',
                    'scheduled_at' => Carbon::parse('2026-09-12 10:00:00'),
                    'notes' => 'Siswa telah berdiskusi mengenai pembagian jam belajar persiapan lomba robotika tingkat provinsi.',
                ]
            );

            CounselingRequest::firstOrCreate(
                [
                    'student_id' => $student->id,
                    'topic' => 'Manajemen Waktu Belajar Menjelang Asesmen Sumatif',
                ],
                [
                    'requested_by' => $userSiswa->id,
                    'requester_role' => 'student',
                    'preferred_date' => '2026-10-22',
                    'preferred_time' => '13:00',
                    'counselor_id' => $userBk->id,
                    'status' => 'dijadwalkan',
                    'scheduled_at' => Carbon::parse('2026-10-22 13:00:00'),
                    'notes' => 'Janji temu dijadwalkan di Ruang BK Lantai 2 pukul 13:00 WIB.',
                ]
            );

            // 15. Achievements
            Achievement::firstOrCreate(
                [
                    'student_id' => $student->id,
                    'title' => 'Juara 1 Lomba Coding Siswa SMP Tingkat Kota Malang',
                ],
                [
                    'school_id' => $schoolId,
                    'rank_level' => 'Juara 1 Kota',
                    'achieved_at' => '2026-08-15',
                    'points_awarded' => 30,
                    'verification_status' => 'verified',
                    'notes' => 'Diselenggarakan oleh Dinas Pendidikan Kota Malang.',
                ]
            );

            Achievement::firstOrCreate(
                [
                    'student_id' => $student->id,
                    'title' => 'Medali Perak Olimpiade Sains Nasional (OSN) Bidang IPA',
                ],
                [
                    'school_id' => $schoolId,
                    'rank_level' => 'Medali Perak',
                    'achieved_at' => '2026-09-02',
                    'points_awarded' => 25,
                    'verification_status' => 'verified',
                    'notes' => 'Seleksi tingkat kota menuju provinsi.',
                ]
            );

            Achievement::firstOrCreate(
                [
                    'student_id' => $student->id,
                    'title' => 'Petugas Pemimpin Upacara Hari Pramuka & Disiplin Sekolah',
                ],
                [
                    'school_id' => $schoolId,
                    'rank_level' => 'Tingkat Sekolah',
                    'achieved_at' => '2026-08-14',
                    'points_awarded' => 10,
                    'verification_status' => 'verified',
                ]
            );

            // 16. School Announcements
            Announcement::firstOrCreate(
                [
                    'school_id' => $schoolId,
                    'title' => 'Jadwal Pelaksanaan Asesmen Sumatif Tengah Semester (STS) Ganjil T.A. 2026/2027',
                ],
                [
                    'author_id' => $userWalas->id,
                    'body' => 'Diberitahukan kepada seluruh siswa kelas 7 bahwa Asesmen STS akan dilaksanakan mulai pekan depan. Pastikan membawa kartu peserta dan memeriksa kesiapan akun CBT masing-masing.',
                    'target_type' => 'semua',
                    'priority' => 'urgent',
                    'is_pinned' => true,
                    'publish_at' => Carbon::now()->subDays(5),
                ]
            );

            Announcement::firstOrCreate(
                [
                    'school_id' => $schoolId,
                    'title' => 'Sesi Tambahan Pendampingan Ekstrakurikuler Robotika & Coding Club',
                ],
                [
                    'author_id' => $userInf->id,
                    'body' => 'Bagi anggota tim robotika persiapan lomba tingkat provinsi, latihan intensif akan diselenggarakan setiap hari Rabu pukul 15.00 WIB di Laboratorium Komputer 1.',
                    'target_type' => 'semua',
                    'priority' => 'normal',
                    'is_pinned' => false,
                    'publish_at' => Carbon::now()->subDays(2),
                ]
            );

            Announcement::firstOrCreate(
                [
                    'school_id' => $schoolId,
                    'title' => 'Gerakan Sekolah Sehat & Sarapan Bersama Hari Jumat',
                ],
                [
                    'author_id' => $userWalas->id,
                    'body' => 'Seluruh siswa diimbau membawa bekal menu bergizi seimbang untuk kegiatan sarapan sehat bersama di halaman utama sebelum senam pagi.',
                    'target_type' => 'semua',
                    'priority' => 'normal',
                    'is_pinned' => false,
                    'publish_at' => Carbon::now()->subDays(4),
                ]
            );

            // 17. Teaching Materials (Modul Ajar)
            $dummyFile1 = File::firstOrCreate(
                ['object_key' => 'materials/modul-ajar-informatika-kelas-7.pdf'],
                [
                    'school_id' => $schoolId,
                    'owner_id' => $userInf->id,
                    'owner_type' => 'user',
                    'bucket' => 'documents',
                    'mime_type' => 'application/pdf',
                    'size_bytes' => 1024 * 1024 * 2,
                    'visibility' => 'public',
                    'checksum' => md5('modul-ajar-inf'),
                ]
            );
            TeachingMaterial::firstOrCreate(
                ['title' => 'Modul Ajar 03: Berpikir Komputasional & Notasi Algoritma'],
                [
                    'grade_level_id' => $gradeLevel7->id,
                    'subject_id' => $subjectMap['INF-7']->id,
                    'uploaded_by' => $userInf->id,
                    'file_id' => $dummyFile1->id,
                    'material_type' => 'modul',
                    'version' => 1,
                    'description' => 'Materi konsep logika percabangan, flowchart, dan implementasi pseudocode kelas 7 fase D.',
                ]
            );

            $dummyFile2 = File::firstOrCreate(
                ['object_key' => 'materials/ringkasan-ekosistem-dan-rantai-makanan.pdf'],
                [
                    'school_id' => $schoolId,
                    'owner_id' => $userIpa->id,
                    'owner_type' => 'user',
                    'bucket' => 'documents',
                    'mime_type' => 'application/pdf',
                    'size_bytes' => 1024 * 1024 * 3,
                    'visibility' => 'public',
                    'checksum' => md5('modul-ajar-ipa'),
                ]
            );
            TeachingMaterial::firstOrCreate(
                ['title' => 'Ringkasan Materi Bab 3: Ekosistem & Biosfer Lingkungan Hidup'],
                [
                    'grade_level_id' => $gradeLevel7->id,
                    'subject_id' => $subjectMap['IPA-7']->id,
                    'uploaded_by' => $userIpa->id,
                    'file_id' => $dummyFile2->id,
                    'material_type' => 'modul',
                    'version' => 1,
                    'description' => 'Bahan pengayaan komponen biotik, rantai makanan, dan siklus biogeokimia di lingkungan sekolah.',
                ]
            );

            $dummyFile3 = File::firstOrCreate(
                ['object_key' => 'materials/diktat-aljabar-linear-smp7.pdf'],
                [
                    'school_id' => $schoolId,
                    'owner_id' => $userMtk->id,
                    'owner_type' => 'user',
                    'bucket' => 'documents',
                    'mime_type' => 'application/pdf',
                    'size_bytes' => 1024 * 1024 * 1,
                    'visibility' => 'public',
                    'checksum' => md5('modul-ajar-mtk'),
                ]
            );
            TeachingMaterial::firstOrCreate(
                ['title' => 'Diktat Latihan Soal Aljabar & Penerapan Persamaan Linear'],
                [
                    'grade_level_id' => $gradeLevel7->id,
                    'subject_id' => $subjectMap['MTK-7']->id,
                    'uploaded_by' => $userMtk->id,
                    'file_id' => $dummyFile3->id,
                    'material_type' => 'modul',
                    'version' => 2,
                    'description' => 'Kumpulan soal latihan mandiri materi bentuk aljabar dan penyelesaian kalimat terbuka.',
                ]
            );
        });
    }
}
