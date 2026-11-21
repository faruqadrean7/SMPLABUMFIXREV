<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\ClassStudent;
use App\Models\File;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradeLevel;
use App\Models\GradePeriod;
use App\Models\Journal;
use App\Models\LeaveRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSalarySlip;
use App\Models\TeachingAssignment;
use App\Models\TeachingMaterial;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class TeacherDemoSeeder extends Seeder
{
    public const DEFAULT_SCHOOL_ID = '00000000-0000-0000-0000-000000000001';

    /**
     * Safety check to ensure seeder only runs in local or staging environments with opt-in flag.
     *
     * @throws \RuntimeException
     */
    public function checkEnvironmentSafety(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('TeacherDemoSeeder dilarang keras dijalankan pada lingkungan production!');
        }

        if (! app()->environment('testing')) {
            $optIn = filter_var(env('TEACHER_DEMO_SEED_ENABLED', env('UAT_SEED_ENABLED', false)), FILTER_VALIDATE_BOOLEAN);
            if (! in_array(app()->environment(), ['staging', 'local'], true) || ! $optIn) {
                throw new \RuntimeException(
                    'TeacherDemoSeeder hanya diizinkan berjalan pada environment staging/local dengan TEACHER_DEMO_SEED_ENABLED=true atau UAT_SEED_ENABLED=true.'
                );
            }
        }
    }

    public function run(): void
    {
        $this->checkEnvironmentSafety();

        (new DatabaseSeeder)->run();

        $schoolId = self::DEFAULT_SCHOOL_ID;
        $defaultPassword = Hash::make('SistLabUM2026!UAT');

        // 1. Roles
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleHomeroom = Role::firstOrCreate(['code' => 'homeroom'], ['name' => 'Wali Kelas', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $roleAdminKurikulum = Role::firstOrCreate(['code' => 'admin_kurikulum'], ['name' => 'Admin Kurikulum', 'is_system' => true]);

        $kurikulumPermIds = Permission::whereIn('code', [
            'academic.schedule.manage',
            'academic.grade.read',
            'academic.grade.write',
            'cbt.bank.write',
            'cbt.exam.manage',
            'academic.report.generate',
        ])->pluck('id')->toArray();
        if (! empty($kurikulumPermIds)) {
            $roleAdminKurikulum->permissions()->syncWithoutDetaching($kurikulumPermIds);
        }

        // Helper to seed user
        $seedUser = function (string $username, string $email, array $roleCodes) use ($schoolId, $defaultPassword): User {
            $user = User::firstOrNew(['username' => $username]);
            $user->school_id = $schoolId;
            $user->email = $email;
            $user->password_hash = $defaultPassword;
            $user->status = 'active';
            $user->save();

            $roleIds = Role::whereIn('code', $roleCodes)->pluck('id');
            $user->roles()->syncWithoutDetaching($roleIds);

            return $user;
        };

        // 2. Academic Year & Semester
        $academicYear = AcademicYear::firstOrNew(['name' => '2026/2027']);
        $academicYear->school_id = $schoolId;
        $academicYear->start_date = '2026-07-01';
        $academicYear->end_date = '2027-06-30';
        $academicYear->is_active = true;
        $academicYear->save();

        $semesterGanjil = Semester::firstOrNew([
            'academic_year_id' => $academicYear->id,
            'name' => 'Ganjil',
        ]);
        $semesterGanjil->start_date = '2026-07-01';
        $semesterGanjil->end_date = '2026-12-31';
        $semesterGanjil->is_active = true;
        $semesterGanjil->save();

        // 3. Grade Level & Classes
        $gradeLevel7 = GradeLevel::firstOrNew(['name' => 'Kelas 7']);
        $gradeLevel7->school_id = $schoolId;
        $gradeLevel7->sort_order = 7;
        $gradeLevel7->save();

        // 4. Teachers: Subject Teacher, Homeroom Teacher, Other Teacher
        // a. Guru Mapel Matematika
        $userMtk = $seedUser('guru.mtk.staging', 'guru.mtk.staging@smp-labum.sch.id', ['teacher']);
        $teacherMtk = Teacher::firstOrNew(['user_id' => $userMtk->id]);
        $teacherMtk->school_id = $schoolId;
        $teacherMtk->nip = '199004042015021004';
        $teacherMtk->nuptk = '4567890123456789';
        $teacherMtk->full_name = 'Guru Matematika UAT, S.Pd.';
        $teacherMtk->position = 'Guru Mata Pelajaran Matematika';
        $teacherMtk->status = 'aktif';
        $teacherMtk->save();

        // b. Wali Kelas 7-A (Guru Bahasa Indonesia)
        $userWalas = $seedUser('walas.7a.staging', 'walas.7a.staging@smp-labum.sch.id', ['homeroom', 'teacher']);
        $teacherWalas = Teacher::firstOrNew(['user_id' => $userWalas->id]);
        $teacherWalas->school_id = $schoolId;
        $teacherWalas->nip = '198503032010012003';
        $teacherWalas->nuptk = '3456789012345678';
        $teacherWalas->full_name = 'Wali Kelas 7-A UAT, S.Pd.';
        $teacherWalas->position = 'Guru / Wali Kelas 7-A';
        $teacherWalas->status = 'aktif';
        $teacherWalas->save();

        // c. Guru Lain / Kurikulum (for IDOR tests)
        $userKurikulum = $seedUser('kurikulum.staging', 'kurikulum.staging@smp-labum.sch.id', ['admin_kurikulum', 'teacher']);
        $teacherKurikulum = Teacher::firstOrNew(['user_id' => $userKurikulum->id]);
        $teacherKurikulum->school_id = $schoolId;
        $teacherKurikulum->nip = '198002022005011002';
        $teacherKurikulum->nuptk = '2345678901234567';
        $teacherKurikulum->full_name = 'Wakil Kepala Kurikulum UAT, S.Pd., M.Pd.';
        $teacherKurikulum->position = 'Wakil Kepala Bidang Kurikulum';
        $teacherKurikulum->status = 'aktif';
        $teacherKurikulum->save();

        // Classes 7-A and 7-B
        $class7A = SchoolClass::firstOrNew([
            'academic_year_id' => $academicYear->id,
            'name' => '7-A',
        ]);
        $class7A->school_id = $schoolId;
        $class7A->grade_level_id = $gradeLevel7->id;
        $class7A->homeroom_teacher_id = $teacherWalas->id;
        $class7A->capacity = 36;
        $class7A->save();

        $class7B = SchoolClass::firstOrNew([
            'academic_year_id' => $academicYear->id,
            'name' => '7-B',
        ]);
        $class7B->school_id = $schoolId;
        $class7B->grade_level_id = $gradeLevel7->id;
        $class7B->homeroom_teacher_id = $teacherKurikulum->id;
        $class7B->capacity = 36;
        $class7B->save();

        // 5. Subjects
        $subjectMtk = Subject::firstOrNew(['code' => 'MTK-7']);
        $subjectMtk->school_id = $schoolId;
        $subjectMtk->name = 'Matematika';
        $subjectMtk->subject_group = 'wajib';
        $subjectMtk->save();

        $subjectBin = Subject::firstOrNew(['code' => 'BIN-7']);
        $subjectBin->school_id = $schoolId;
        $subjectBin->name = 'Bahasa Indonesia';
        $subjectBin->subject_group = 'wajib';
        $subjectBin->save();

        $subjectIpa = Subject::firstOrNew(['code' => 'IPA-7']);
        $subjectIpa->school_id = $schoolId;
        $subjectIpa->name = 'Ilmu Pengetahuan Alam';
        $subjectIpa->subject_group = 'wajib';
        $subjectIpa->save();

        // 6. Teaching Assignments
        // Guru MTK teaches Matematika in 7-A
        TeachingAssignment::firstOrCreate(
            [
                'semester_id' => $semesterGanjil->id,
                'class_id' => $class7A->id,
                'subject_id' => $subjectMtk->id,
            ],
            [
                'teacher_id' => $teacherMtk->id,
                'hours_per_week' => 4,
            ]
        );

        // Walas 7-A teaches Bahasa Indonesia in 7-A
        TeachingAssignment::firstOrCreate(
            [
                'semester_id' => $semesterGanjil->id,
                'class_id' => $class7A->id,
                'subject_id' => $subjectBin->id,
            ],
            [
                'teacher_id' => $teacherWalas->id,
                'hours_per_week' => 4,
            ]
        );

        // Guru Kurikulum teaches IPA in 7-B
        TeachingAssignment::firstOrCreate(
            [
                'semester_id' => $semesterGanjil->id,
                'class_id' => $class7B->id,
                'subject_id' => $subjectIpa->id,
            ],
            [
                'teacher_id' => $teacherKurikulum->id,
                'hours_per_week' => 4,
            ]
        );

        // 7. Time Slots & Schedules & Room
        $room7A = Room::firstOrCreate(
            ['name' => 'Ruang Kelas 7-A'],
            ['school_id' => $schoolId, 'code' => 'RK-7A', 'room_type' => 'kelas', 'capacity' => 36]
        );

        $room7B = Room::firstOrCreate(
            ['name' => 'Ruang Kelas 7-B'],
            ['school_id' => $schoolId, 'code' => 'RK-7B', 'room_type' => 'kelas', 'capacity' => 36]
        );

        $timeSlot1 = TimeSlot::firstOrCreate(
            ['name' => 'Jam ke-1'],
            ['school_id' => $schoolId, 'start_time' => '07:00:00', 'end_time' => '08:20:00', 'day_type' => 'reguler']
        );

        $timeSlot2 = TimeSlot::firstOrCreate(
            ['name' => 'Jam ke-2'],
            ['school_id' => $schoolId, 'start_time' => '08:20:00', 'end_time' => '09:40:00', 'day_type' => 'reguler']
        );

        $timeSlot3 = TimeSlot::firstOrCreate(
            ['name' => 'Jam ke-3'],
            ['school_id' => $schoolId, 'start_time' => '09:55:00', 'end_time' => '11:15:00', 'day_type' => 'reguler']
        );

        // Schedules for MTK 7-A across the week (Senin - Sabtu, Jam ke-1: 07:00 - 08:20)
        foreach ([1, 2, 3, 4, 5, 6] as $day) {
            Schedule::firstOrCreate(
                [
                    'semester_id' => $semesterGanjil->id,
                    'class_id' => $class7A->id,
                    'day_of_week' => $day,
                    'time_slot_id' => $timeSlot1->id,
                ],
                [
                    'subject_id' => $subjectMtk->id,
                    'teacher_id' => $teacherMtk->id,
                    'room_id' => $room7A->id,
                ]
            );
        }

        // Schedules for Walas 7-A Bahasa Indonesia across the week (Senin - Sabtu, Jam ke-2: 08:20 - 09:40)
        foreach ([1, 2, 3, 4, 5, 6] as $day) {
            Schedule::firstOrCreate(
                [
                    'semester_id' => $semesterGanjil->id,
                    'class_id' => $class7A->id,
                    'day_of_week' => $day,
                    'time_slot_id' => $timeSlot2->id,
                ],
                [
                    'subject_id' => $subjectBin->id,
                    'teacher_id' => $teacherWalas->id,
                    'room_id' => $room7A->id,
                ]
            );
        }

        // Schedules for Kurikulum 7-B IPA across the week (Senin - Sabtu, Jam ke-3: 09:55 - 11:15)
        foreach ([1, 2, 3, 4, 5, 6] as $day) {
            Schedule::firstOrCreate(
                [
                    'semester_id' => $semesterGanjil->id,
                    'class_id' => $class7B->id,
                    'day_of_week' => $day,
                    'time_slot_id' => $timeSlot3->id,
                ],
                [
                    'subject_id' => $subjectIpa->id,
                    'teacher_id' => $teacherKurikulum->id,
                    'room_id' => $room7B->id,
                ]
            );
        }

        // 8. 10 Students in 7-A
        $students = [];
        $studentNames = [
            'Ahmad Fauzi',
            'Budi Santoso',
            'Citra Dewi',
            'Dimas Pratama',
            'Eka Nurhaliza',
            'Farhan Maulana',
            'Gita Savitri',
            'Hafiz Ramadhan',
            'Indah Permatasari',
            'Joko Susilo',
        ];

        foreach ($studentNames as $idx => $name) {
            $numStr = str_pad((string) ($idx + 1), 3, '0', STR_PAD_LEFT);
            $username = "siswa.lab.{$numStr}";
            $userStu = $seedUser($username, "{$username}@smp-labum.sch.id", ['student']);

            $stu = Student::firstOrNew(['user_id' => $userStu->id]);
            $stu->school_id = $schoolId;
            $stu->nis = '26'.str_pad((string) ($idx + 1), 3, '0', STR_PAD_LEFT);
            $stu->nisn = '009900'.str_pad((string) ($idx + 1), 4, '0', STR_PAD_LEFT);
            $stu->nik_enc = '357801010109'.str_pad((string) ($idx + 1), 4, '0', STR_PAD_LEFT);
            $stu->full_name = $name;
            $stu->gender = ($idx % 2 === 0) ? 'L' : 'P';
            $stu->birth_place = 'Malang';
            $stu->birth_date = '2012-05-15';
            $stu->religion = 'Islam';
            $stu->address = 'Jl. Simpang Bogor No. '.($idx + 1).', Malang';
            $stu->status = 'aktif';
            $stu->entry_date = '2026-07-01';
            $stu->save();

            ClassStudent::firstOrCreate(
                [
                    'class_id' => $class7A->id,
                    'student_id' => $stu->id,
                ],
                [
                    'joined_at' => '2026-07-01',
                ]
            );

            $students[] = $stu;
        }

        // 9. Grade Periods (1 Open, 1 Locked)
        $openPeriod = GradePeriod::firstOrNew([
            'semester_id' => $semesterGanjil->id,
            'month' => 10,
        ]);
        $openPeriod->status = 'open';
        $openPeriod->locked_at = null;
        $openPeriod->locked_by = null;
        $openPeriod->save();

        $lockedPeriod = GradePeriod::firstOrNew([
            'semester_id' => $semesterGanjil->id,
            'month' => 9,
        ]);
        $lockedPeriod->status = 'locked';
        $lockedPeriod->locked_at = now()->subDays(15);
        $lockedPeriod->locked_by = $userKurikulum->id;
        $lockedPeriod->save();

        // 10. Grade Components for Matematika 7-A
        $componentsConfig = [
            ['code' => 'F-01', 'name' => 'Formatif 1: Operasi Aljabar', 'weight' => 20],
            ['code' => 'F-02', 'name' => 'Formatif 2: Persamaan Linier', 'weight' => 20],
            ['code' => 'S-01', 'name' => 'Sumatif 1: Bab Aljabar', 'weight' => 20],
            ['code' => 'STS', 'name' => 'Sumatif Tengah Semester', 'weight' => 20],
            ['code' => 'SAS', 'name' => 'Sumatif Akhir Semester', 'weight' => 20],
        ];

        $gradeComponents = [];
        foreach ($componentsConfig as $conf) {
            $gc = GradeComponent::firstOrNew([
                'subject_id' => $subjectMtk->id,
                'semester_id' => $semesterGanjil->id,
                'code' => $conf['code'],
            ]);
            $gc->class_id = $class7A->id;
            $gc->name = $conf['name'];
            $gc->weight = $conf['weight'];
            $gc->save();
            $gradeComponents[] = $gc;
        }

        // Seed some sample grades for Ahmad Fauzi and Budi Santoso
        if (isset($students[0], $gradeComponents[0])) {
            Grade::updateOrCreate(
                [
                    'student_id' => $students[0]->id,
                    'component_id' => $gradeComponents[0]->id,
                    'period_id' => $openPeriod->id,
                ],
                [
                    'value' => 88.5,
                    'source' => 'manual',
                    'entered_by' => $userMtk->id,
                ]
            );
        }

        // 11. Assignment & Student Submissions
        $assignment = Assignment::firstOrNew([
            'teacher_id' => $teacherMtk->id,
            'class_id' => $class7A->id,
            'subject_id' => $subjectMtk->id,
            'title' => 'Latihan Soal Aljabar dan Persamaan Linier',
        ]);
        $assignment->description = 'Selesaikan latihan soal no 1-10 di buku paket halaman 45, kumpulkan dalam format PDF.';
        $assignment->due_at = now()->addDays(5);
        $assignment->save();

        // Submissions
        if (isset($students[0])) {
            $sub1 = AssignmentSubmission::firstOrNew([
                'assignment_id' => $assignment->id,
                'student_id' => $students[0]->id,
            ]);
            $sub1->submitted_at = now()->subDays(1);
            $sub1->score = 90.00;
            $sub1->feedback = 'Penyelesaian sangat runtut dan sistematis. Pertahankan!';
            $sub1->save();
        }

        if (isset($students[1])) {
            $sub2 = AssignmentSubmission::firstOrNew([
                'assignment_id' => $assignment->id,
                'student_id' => $students[1]->id,
            ]);
            $sub2->submitted_at = now()->subHours(3);
            $sub2->score = null;
            $sub2->feedback = null;
            $sub2->save();
        }

        // 12. Teaching Materials (PDF / link)
        $materialFile = File::firstOrCreate(
            ['object_key' => 'academic/materials/modul-aljabar-dasar-7a.pdf'],
            [
                'bucket' => 'academic',
                'mime_type' => 'application/pdf',
                'size_bytes' => 1024 * 512,
                'visibility' => 'publik',
            ]
        );

        TeachingMaterial::firstOrCreate(
            [
                'title' => 'Modul 01: Operasi Aljabar Dasar Kelas 7',
                'subject_id' => $subjectMtk->id,
                'uploaded_by' => $userMtk->id,
            ],
            [
                'description' => 'Materi pengantar aljabar untuk siswa kelas 7 semester ganjil.',
                'file_id' => $materialFile->id,
                'material_type' => 'modul',
                'version' => 1,
            ]
        );

        // 13. Teaching Journal & Class Attendance
        $todaySchedule = Schedule::where('teacher_id', $teacherMtk->id)
            ->where('class_id', $class7A->id)
            ->where('subject_id', $subjectMtk->id)
            ->first();

        if ($todaySchedule) {
            $journal = Journal::firstOrCreate(
                [
                    'schedule_id' => $todaySchedule->id,
                    'teacher_id' => $teacherMtk->id,
                    'session_date' => now()->toDateString(),
                ],
                [
                    'topic' => 'Pengenalan Variabel dan Konstanta Aljabar',
                    'method' => 'Tatap Muka & Diskusi Kelompok',
                    'notes' => 'Siswa sangat aktif berdiskusi. Kendala: 2 siswa lupa membawa buku cetak.',
                ]
            );

            // Record attendance for students
            foreach ($students as $i => $stu) {
                Attendance::updateOrCreate(
                    [
                        'journal_id' => $journal->id,
                        'student_id' => $stu->id,
                    ],
                    [
                        'att_date' => now()->toDateString(),
                        'status' => ($i === 3) ? 'sakit' : (($i === 7) ? 'izin' : 'hadir'),
                        'source' => 'guru',
                        'note' => ($i === 3) ? 'Surat dokter terlampir' : null,
                        'recorded_by' => $userMtk->id,
                    ]
                );
            }
        }

        // 14. Salary Slips for Guru MTK & Walas 7-A
        // Ensure dummy file on local disk for download verification
        $dummySlipContent = "%PDF-1.4\n1 0 obj\n<< /Title (Slip Gaji Guru) >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
        Storage::disk('local')->put('salary-slips/slip-guru-mtk-2026-10.pdf', $dummySlipContent);
        Storage::disk('local')->put('salary-slips/slip-guru-mtk-2026-09.pdf', $dummySlipContent);
        Storage::disk('local')->put('salary-slips/slip-walas-7a-2026-10.pdf', $dummySlipContent);

        $fileSlipMtk10 = File::firstOrCreate(
            ['object_key' => 'salary-slips/slip-guru-mtk-2026-10.pdf'],
            [
                'bucket' => 'salary-slips',
                'mime_type' => 'application/pdf',
                'size_bytes' => strlen($dummySlipContent),
                'visibility' => 'privat',
            ]
        );

        $fileSlipMtk09 = File::firstOrCreate(
            ['object_key' => 'salary-slips/slip-guru-mtk-2026-09.pdf'],
            [
                'bucket' => 'salary-slips',
                'mime_type' => 'application/pdf',
                'size_bytes' => strlen($dummySlipContent),
                'visibility' => 'privat',
            ]
        );

        $fileSlipWalas10 = File::firstOrCreate(
            ['object_key' => 'salary-slips/slip-walas-7a-2026-10.pdf'],
            [
                'bucket' => 'salary-slips',
                'mime_type' => 'application/pdf',
                'size_bytes' => strlen($dummySlipContent),
                'visibility' => 'privat',
            ]
        );

        TeacherSalarySlip::firstOrCreate(
            [
                'teacher_id' => $teacherMtk->id,
                'period_year' => 2026,
                'period_month' => 10,
            ],
            [
                'slip_number' => 'SLIP/MTK/2026/10/001',
                'payment_date' => '2026-10-25',
                'basic_salary' => 3500000,
                'allowances' => 1450000,
                'deductions' => 200000,
                'net_salary' => 4750000,
                'status' => 'published',
                'file_id' => $fileSlipMtk10->id,
                'notes' => 'Honorarium KBM 16 jam tatap muka + tunjangan fungsional',
                'created_by' => $userKurikulum->id,
            ]
        );

        TeacherSalarySlip::firstOrCreate(
            [
                'teacher_id' => $teacherMtk->id,
                'period_year' => 2026,
                'period_month' => 9,
            ],
            [
                'slip_number' => 'SLIP/MTK/2026/09/001',
                'payment_date' => '2026-09-25',
                'basic_salary' => 3500000,
                'allowances' => 1200000,
                'deductions' => 200000,
                'net_salary' => 4500000,
                'status' => 'published',
                'file_id' => $fileSlipMtk09->id,
                'notes' => 'Honorarium KBM September 2026',
                'created_by' => $userKurikulum->id,
            ]
        );

        TeacherSalarySlip::firstOrCreate(
            [
                'teacher_id' => $teacherWalas->id,
                'period_year' => 2026,
                'period_month' => 10,
            ],
            [
                'slip_number' => 'SLIP/WLS/2026/10/002',
                'payment_date' => '2026-10-25',
                'basic_salary' => 3800000,
                'allowances' => 1600000,
                'deductions' => 200000,
                'net_salary' => 5200000,
                'status' => 'published',
                'file_id' => $fileSlipWalas10->id,
                'notes' => 'Honorarium KBM + Tunjangan Khusus Wali Kelas 7-A',
                'created_by' => $userKurikulum->id,
            ]
        );

        // 15. Leave Requests for Guru MTK
        LeaveRequest::firstOrCreate(
            [
                'requested_by' => $userMtk->id,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDay()->toDateString(),
            ],
            [
                'leave_type' => 'izin',
                'reason' => 'Menghadiri Pelatihan MGMP Matematika Tingkat Kota Malang',
                'status' => 'menunggu',
                'decided_by' => null,
            ]
        );

        LeaveRequest::firstOrCreate(
            [
                'requested_by' => $userMtk->id,
                'start_date' => now()->subDays(10)->toDateString(),
                'end_date' => now()->subDays(9)->toDateString(),
            ],
            [
                'leave_type' => 'sakit',
                'reason' => 'Istirahat medis dan pemulihan kesehatan',
                'status' => 'disetujui',
                'decided_by' => $userKurikulum->id,
                'decision_notes' => 'Disetujui. Semoga lekas pulih.',
            ]
        );

        // 16. Staff Attendance (Self Attendance) for Guru MTK today
        StaffAttendance::updateOrCreate(
            [
                'user_id' => $userMtk->id,
                'att_date' => now()->toDateString(),
            ],
            [
                'check_in' => now()->setTime(6, 45, 0),
                'status' => 'hadir',
            ]
        );
    }
}
