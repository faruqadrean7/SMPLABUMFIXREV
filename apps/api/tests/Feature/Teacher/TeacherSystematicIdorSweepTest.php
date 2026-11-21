<?php

namespace Tests\Feature\Teacher;

use App\Models\Assignment;
use App\Models\ClassStudent;
use App\Models\Guardian;
use App\Models\LearningMaterial;
use App\Models\LeaveRequest;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\ReportCard;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSalarySlip;
use App\Models\User;
use Database\Seeders\TeacherDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TeacherSystematicIdorSweepTest extends TestCase
{
    use RefreshDatabase;

    protected User $userMtk;

    protected Teacher $teacherMtk;

    protected User $userWalas;

    protected Teacher $teacherWalas;

    protected User $userStudent;

    protected Student $student;

    protected User $userGuardian;

    protected Guardian $guardian;

    protected Student $childOfGuardian;

    protected Student $otherChild;

    protected SchoolClass $class7A;

    protected SchoolClass $class7B;

    protected Subject $subjectMtk;

    protected Semester $semesterGanjil;

    // Fixtures of Teacher B (Walas)
    protected LearningMaterial $materialOfTeacherB;

    protected Assignment $assignmentOfTeacherB;

    protected QuestionBank $bankOfTeacherB;

    protected Question $questionOfTeacherB;

    protected TeacherSalarySlip $slipOfTeacherB;

    protected ReportCard $reportOfOtherClass;

    protected LeaveRequest $leaveRequest;

    protected function setUp(): void
    {
        parent::setUp();

        (new TeacherDemoSeeder)->run();

        $this->userMtk = User::where('username', 'guru.mtk.staging')->firstOrFail();
        $this->teacherMtk = Teacher::where('user_id', $this->userMtk->id)->firstOrFail();

        $this->userWalas = User::where('username', 'walas.7a.staging')->firstOrFail();
        $this->teacherWalas = Teacher::where('user_id', $this->userWalas->id)->firstOrFail();

        $this->class7A = SchoolClass::where('name', '7-A')->firstOrFail();
        $this->class7B = SchoolClass::where('name', '7-B')->firstOrFail();

        $this->subjectMtk = Subject::where('code', 'MTK-7')->firstOrFail();
        $this->semesterGanjil = Semester::where('name', 'Ganjil')->firstOrFail();

        $this->student = Student::whereHas('classes', fn ($q) => $q->where('classes.id', $this->class7A->id))->firstOrFail();
        $this->userStudent = $this->student->user;

        // 1. Setup Fixtures of Teacher B (Walas)
        $this->materialOfTeacherB = LearningMaterial::create([
            'class_id' => $this->class7A->id,
            'subject_id' => $this->subjectMtk->id,
            'teacher_id' => $this->teacherWalas->id,
            'title' => 'Materi Milik Walas',
        ]);

        $this->assignmentOfTeacherB = Assignment::create([
            'class_id' => $this->class7A->id,
            'subject_id' => $this->subjectMtk->id,
            'teacher_id' => $this->teacherWalas->id,
            'title' => 'Tugas Milik Walas',
            'due_at' => now()->addDays(7),
        ]);

        $this->bankOfTeacherB = QuestionBank::create([
            'school_id' => $this->userWalas->school_id,
            'subject_id' => $this->subjectMtk->id,
            'owner_id' => $this->userWalas->id,
            'name' => 'Bank Soal Milik Walas',
        ]);

        $this->questionOfTeacherB = Question::create([
            'bank_id' => $this->bankOfTeacherB->id,
            'question_type' => 'pilihan_ganda',
            'body' => ['question' => 'Berapa 1+1?'],
            'weight' => 10,
        ]);

        $this->slipOfTeacherB = TeacherSalarySlip::where('teacher_id', $this->teacherWalas->id)->firstOrFail();

        // 2. Setup Wali Murid & Dua Anak Berbeda
        $userOrtu = User::firstOrCreate(
            ['username' => 'wali.murid.idor'],
            [
                'school_id' => $this->class7A->school_id,
                'email' => 'wali.idor@sistlabum.sch.id',
                'password_hash' => bcrypt('password'),
                'status' => 'active',
            ]
        );
        $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Wali Murid', 'is_system' => true]);
        $userOrtu->roles()->syncWithoutDetaching([$roleGuardian->id]);

        $this->guardian = Guardian::firstOrCreate(
            ['user_id' => $userOrtu->id],
            [
                'full_name' => 'Bapak Budi Wali Murid',
                'phone' => '081234567899',
            ]
        );
        $this->userGuardian = $userOrtu;

        // Anak kandung
        $this->childOfGuardian = $this->student;
        $this->guardian->students()->syncWithoutDetaching([
            $this->childOfGuardian->id => ['relationship' => 'ayah'],
        ]);

        // Anak orang lain (anak di 7-B)
        $userOtherStu = User::firstOrCreate(
            ['username' => 'anak.lain.idor'],
            [
                'school_id' => $this->class7B->school_id,
                'email' => 'anak.lain@sistlabum.sch.id',
                'password_hash' => bcrypt('password'),
                'status' => 'active',
            ]
        );
        $userOtherStu->roles()->syncWithoutDetaching([Role::where('code', 'student')->first()->id]);
        $this->otherChild = Student::firstOrCreate(
            ['user_id' => $userOtherStu->id],
            [
                'school_id' => $this->class7B->school_id,
                'nis' => '99003',
                'nisn' => '9900000003',
                'nik_enc' => '3578010101099998',
                'full_name' => 'Anak Orang Lain',
                'gender' => 'P',
                'birth_place' => 'Malang',
                'birth_date' => '2012-06-01',
                'religion' => 'Islam',
                'address' => 'Jl. Ijen No. 10 Malang',
                'status' => 'aktif',
                'entry_date' => '2026-07-01',
            ]
        );
        ClassStudent::firstOrCreate([
            'class_id' => $this->class7B->id,
            'student_id' => $this->otherChild->id,
        ], ['joined_at' => '2026-07-01']);

        // Rapor siswa kelas 7-B
        $this->reportOfOtherClass = ReportCard::create([
            'student_id' => $this->otherChild->id,
            'semester_id' => $this->semesterGanjil->id,
            'status' => 'draft',
            'verify_token' => 'token-lain-7b',
            'data_snapshot' => ['student' => ['id' => $this->otherChild->id]],
        ]);

        $this->leaveRequest = LeaveRequest::firstOrCreate(
            [
                'student_id' => $this->student->id,
                'start_date' => now()->addDays(2)->toDateString(),
                'end_date' => now()->addDays(3)->toDateString(),
            ],
            [
                'requested_by' => $this->userStudent->id,
                'leave_type' => 'izin',
                'reason' => 'Keperluan keluarga',
                'status' => 'menunggu_persetujuan',
            ]
        );
    }

    /**
     * Data Provider: Kumpulan skenario IDOR & Privilege Escalation lintas peran.
     */
    public static function idorScenariosProvider(): array
    {
        return [
            // 1. Teacher Cross-Account IDOR (Guru A -> Guru B data)
            'Guru A mengubah materi Guru B' => [
                'actor' => 'userMtk',
                'method' => 'PUT',
                'endpoint' => '/api/v1/materials/{material_b}',
                'payload' => ['title' => 'Materi Bajakan'],
            ],
            'Guru A menghapus materi Guru B' => [
                'actor' => 'userMtk',
                'method' => 'DELETE',
                'endpoint' => '/api/v1/materials/{material_b}',
                'payload' => [],
            ],
            'Guru A mengubah tugas Guru B' => [
                'actor' => 'userMtk',
                'method' => 'PUT',
                'endpoint' => '/api/v1/assignments/{assignment_b}',
                'payload' => ['title' => 'Tugas Bajakan'],
            ],
            'Guru A menghapus tugas Guru B' => [
                'actor' => 'userMtk',
                'method' => 'DELETE',
                'endpoint' => '/api/v1/assignments/{assignment_b}',
                'payload' => [],
            ],
            'Guru A mengubah bank soal Guru B' => [
                'actor' => 'userMtk',
                'method' => 'PUT',
                'endpoint' => '/api/v1/me/teacher/question-banks/{bank_b}',
                'payload' => ['title' => 'Bank Soal Bajakan'],
            ],
            'Guru A menghapus bank soal Guru B' => [
                'actor' => 'userMtk',
                'method' => 'DELETE',
                'endpoint' => '/api/v1/me/teacher/question-banks/{bank_b}',
                'payload' => [],
            ],
            'Guru A mengubah butir soal Guru B' => [
                'actor' => 'userMtk',
                'method' => 'PUT',
                'endpoint' => '/api/v1/me/teacher/questions/{question_b}',
                'payload' => ['body' => ['question' => 'Soal Bajakan']],
            ],
            'Guru A menghapus butir soal Guru B' => [
                'actor' => 'userMtk',
                'method' => 'DELETE',
                'endpoint' => '/api/v1/me/teacher/questions/{question_b}',
                'payload' => [],
            ],
            'Guru A mengintip slip gaji Guru B' => [
                'actor' => 'userMtk',
                'method' => 'GET',
                'endpoint' => '/api/v1/me/teacher/salary-slips/{slip_b}',
                'payload' => [],
            ],

            // 2. Student Privilege Escalation (Siswa -> Guru actions)
            'Siswa membuat bank soal guru' => [
                'actor' => 'userStudent',
                'method' => 'POST',
                'endpoint' => '/api/v1/me/teacher/question-banks',
                'payload' => ['subject_id' => '{subject_mtk}', 'title' => 'Hacked Bank', 'grade_level' => 7],
            ],
            'Siswa mengunggah materi pelajaran' => [
                'actor' => 'userStudent',
                'method' => 'POST',
                'endpoint' => '/api/v1/materials',
                'payload' => ['class_id' => '{class_7a}', 'subject_id' => '{subject_mtk}', 'title' => 'Hacked Material'],
            ],
            'Siswa membuat tugas pembelajaran' => [
                'actor' => 'userStudent',
                'method' => 'POST',
                'endpoint' => '/api/v1/assignments',
                'payload' => ['class_id' => '{class_7a}', 'subject_id' => '{subject_mtk}', 'title' => 'Hacked Task', 'due_at' => '2026-12-31 23:59:59'],
            ],
            'Siswa mengakses slip gaji guru' => [
                'actor' => 'userStudent',
                'method' => 'GET',
                'endpoint' => '/api/v1/me/teacher/salary-slips',
                'payload' => [],
            ],
            'Siswa mengakses daftar kelas guru' => [
                'actor' => 'userStudent',
                'method' => 'GET',
                'endpoint' => '/api/v1/me/teacher/classes',
                'payload' => [],
            ],

            // 3. Guardian Cross-Child IDOR (Wali Murid -> Anak Lain)
            'Wali murid melihat nilai anak lain' => [
                'actor' => 'userGuardian',
                'method' => 'GET',
                'endpoint' => '/api/v1/me/children/{other_child}/grades',
                'payload' => [],
            ],
            'Wali murid melihat absensi anak lain' => [
                'actor' => 'userGuardian',
                'method' => 'GET',
                'endpoint' => '/api/v1/me/children/{other_child}/attendance',
                'payload' => [],
            ],
            'Wali murid melihat rapor anak lain' => [
                'actor' => 'userGuardian',
                'method' => 'GET',
                'endpoint' => '/api/v1/me/children/{other_child}/report-cards',
                'payload' => [],
            ],

            // 4. Regular Teacher Privilege Escalation (Guru Biasa -> Walas / Kepsek / TU)
            'Guru biasa generate rapor siswa bukan binaan' => [
                'actor' => 'userMtk',
                'method' => 'POST',
                'endpoint' => '/api/v1/report-cards/generate',
                'payload' => ['semester_id' => '{semester}', 'student_id' => '{other_child}'],
            ],
            'Guru biasa mengesahkan rapor kelas lain' => [
                'actor' => 'userMtk',
                'method' => 'PATCH',
                'endpoint' => '/api/v1/report-cards/{report_other}/approve',
                'payload' => ['status' => 'disetujui'],
            ],
            'Guru biasa bulk lock rapor' => [
                'actor' => 'userMtk',
                'method' => 'POST',
                'endpoint' => '/api/v1/report-cards/bulk-lock',
                'payload' => ['report_ids' => ['{report_other}'], 'is_locked' => true],
            ],
            'Guru biasa memutuskan izin cuti staf' => [
                'actor' => 'userMtk',
                'method' => 'PATCH',
                'endpoint' => '/api/v1/leave-requests/{leave_id}/decision',
                'payload' => ['status' => 'disetujui'],
            ],
        ];
    }

    #[DataProvider('idorScenariosProvider')]
    public function test_systematic_idor_sweep_rejects_unauthorized_access(
        string $actor,
        string $method,
        string $endpoint,
        array $payload = []
    ): void {
        $actorUser = $this->{$actor};

        // Ganti placeholder di endpoint
        $endpoint = strtr($endpoint, [
            '{material_b}' => $this->materialOfTeacherB->id,
            '{assignment_b}' => $this->assignmentOfTeacherB->id,
            '{bank_b}' => $this->bankOfTeacherB->id,
            '{question_b}' => $this->questionOfTeacherB->id,
            '{slip_b}' => $this->slipOfTeacherB->id,
            '{subject_mtk}' => $this->subjectMtk->id,
            '{class_7a}' => $this->class7A->id,
            '{other_child}' => $this->otherChild->id,
            '{semester}' => $this->semesterGanjil->id,
            '{report_other}' => $this->reportOfOtherClass->id,
            '{leave_id}' => $this->leaveRequest->id,
        ]);

        // Ganti placeholder di payload
        $replacements = [
            '{subject_mtk}' => $this->subjectMtk->id,
            '{class_7a}' => $this->class7A->id,
            '{other_child}' => $this->otherChild->id,
            '{semester}' => $this->semesterGanjil->id,
            '{report_other}' => $this->reportOfOtherClass->id,
        ];

        $preparedPayload = [];
        foreach ($payload as $key => $value) {
            if (is_string($value)) {
                $preparedPayload[$key] = strtr($value, $replacements);
            } elseif (is_array($value)) {
                $preparedPayload[$key] = array_map(function ($item) use ($replacements) {
                    return is_string($item) ? strtr($item, $replacements) : $item;
                }, $value);
            } else {
                $preparedPayload[$key] = $value;
            }
        }

        $response = match ($method) {
            'GET' => $this->actingAs($actorUser)->getJson($endpoint),
            'POST' => $this->actingAs($actorUser)->postJson($endpoint, $preparedPayload),
            'PUT' => $this->actingAs($actorUser)->putJson($endpoint, $preparedPayload),
            'PATCH' => $this->actingAs($actorUser)->patchJson($endpoint, $preparedPayload),
            'DELETE' => $this->actingAs($actorUser)->deleteJson($endpoint),
            default => throw new \InvalidArgumentException("Metode HTTP {$method} tidak didukung"),
        };

        // Assert: HARUS 403 atau 404, TIDAK BOLEH 200 atau 500
        $status = $response->status();
        $this->assertNotEquals(200, $status, "IDOR VULNERABILITY DETECTED: Endpoint {$method} {$endpoint} returned 200 OK!");
        $this->assertNotEquals(500, $status, "CRASH DETECTED: Endpoint {$method} {$endpoint} returned 500 Internal Error!");
        $this->assertContains(
            $status,
            [403, 404],
            "Endpoint {$method} {$endpoint} expected 403 or 404, but received {$status}. Response: ".json_encode($response->json())
        );
    }
}
