<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicYear;
use App\Models\CharacterNote;
use App\Models\ClassStudent;
use App\Models\GradeLevel;
use App\Models\GradePeriod;
use App\Models\Guardian;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CharacterNoteTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $walasUser;

    protected User $otherTeacherUser;

    protected User $student1User;

    protected User $student2User;

    protected User $guardianUser;

    protected Teacher $walas;

    protected Teacher $otherTeacher;

    protected Student $student1;

    protected Student $student2;

    protected SchoolClass $classA;

    protected Semester $semester1;

    protected GradePeriod $periodMonth8;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'admin_kurikulum'], ['name' => 'Admin Kurikulum', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Wali Murid', 'is_system' => true]);

        $permGradeRead = Permission::firstOrCreate(['code' => 'academic.grade.read'], ['description' => 'Melihat nilai']);
        $permGradeWrite = Permission::firstOrCreate(['code' => 'academic.grade.write'], ['description' => 'Menginput nilai']);
        $permScheduleManage = Permission::firstOrCreate(['code' => 'academic.schedule.manage'], ['description' => 'Kelola jadwal']);

        $roleAdmin->permissions()->syncWithoutDetaching([$permGradeRead->id, $permGradeWrite->id, $permScheduleManage->id]);
        $roleTeacher->permissions()->syncWithoutDetaching([$permGradeRead->id, $permGradeWrite->id]);
        $roleStudent->permissions()->syncWithoutDetaching([$permGradeRead->id]);
        $roleGuardian->permissions()->syncWithoutDetaching([$permGradeRead->id]);

        // Users
        $this->adminUser = User::create([
            'username' => 'adminkurikulum',
            'email' => 'kurikulum@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->walasUser = User::create([
            'username' => 'walasbudi',
            'email' => 'budi@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->walasUser->roles()->attach($roleTeacher->id);

        $this->otherTeacherUser = User::create([
            'username' => 'gurusiti',
            'email' => 'siti@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->otherTeacherUser->roles()->attach($roleTeacher->id);

        $this->student1User = User::create([
            'username' => 'siswa1',
            'email' => 'siswa1@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->student1User->roles()->attach($roleStudent->id);

        $this->student2User = User::create([
            'username' => 'siswa2',
            'email' => 'siswa2@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->student2User->roles()->attach($roleStudent->id);

        $this->guardianUser = User::create([
            'username' => 'walimurid',
            'email' => 'wali@keluarga.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->guardianUser->roles()->attach($roleGuardian->id);

        // Teacher Models
        $this->walas = Teacher::create([
            'user_id' => $this->walasUser->id,
            'nip' => '198001012005011001',
            'full_name' => 'Budi Walas, M.Pd.',
            'status' => 'tetap',
        ]);

        $this->otherTeacher = Teacher::create([
            'user_id' => $this->otherTeacherUser->id,
            'nip' => '198502022006022002',
            'full_name' => 'Siti Guru, S.Pd.',
            'status' => 'tetap',
        ]);

        // Academic Year & Semester
        $academicYear = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-15',
            'end_date' => '2027-06-20',
            'is_active' => true,
        ]);

        $this->semester1 = Semester::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'Ganjil 2026/2027',
            'semester_type' => 'ganjil',
            'start_date' => '2026-07-15',
            'end_date' => '2026-12-20',
            'is_active' => true,
        ]);

        $gradeLevel = GradeLevel::create(['name' => 'Kelas 7', 'sort_order' => 7]);

        // Class VII-A with walas
        $this->classA = SchoolClass::create([
            'academic_year_id' => $academicYear->id,
            'grade_level_id' => $gradeLevel->id,
            'homeroom_teacher_id' => $this->walas->id,
            'name' => 'VII-A',
            'capacity' => 32,
        ]);

        // Period Month 8 (Agustus)
        $this->periodMonth8 = GradePeriod::create([
            'semester_id' => $this->semester1->id,
            'month' => 8,
            'status' => 'open',
        ]);

        // Students
        $this->student1 = Student::create([
            'user_id' => $this->student1User->id,
            'nis' => '202600001',
            'nisn' => '0012345678',
            'nik_enc' => 'enc_3578010101010001',
            'full_name' => 'Aditya Siswa A',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-05-15',
            'status' => 'aktif',
            'entry_date' => '2026-07-15',
        ]);
        ClassStudent::create([
            'class_id' => $this->classA->id,
            'student_id' => $this->student1->id,
            'joined_at' => Carbon::now(),
        ]);

        $this->student2 = Student::create([
            'user_id' => $this->student2User->id,
            'nis' => '202600002',
            'nisn' => '0012345679',
            'nik_enc' => 'enc_3578010101010002',
            'full_name' => 'Bella Siswa B',
            'gender' => 'P',
            'birth_place' => 'Surabaya',
            'birth_date' => '2012-08-20',
            'status' => 'aktif',
            'entry_date' => '2026-07-15',
        ]);
        ClassStudent::create([
            'class_id' => $this->classA->id,
            'student_id' => $this->student2->id,
            'joined_at' => Carbon::now(),
        ]);

        // Guardian linked to Student 1
        $guardian = Guardian::create([
            'user_id' => $this->guardianUser->id,
            'nik_enc' => 'enc_3578010101010099',
            'full_name' => 'Bapak Aditya',
            'phone' => '081234567890',
        ]);
        $guardian->students()->attach($this->student1->id, ['relationship' => 'ayah']);
    }

    public function test_be_grd_02_homeroom_teacher_can_create_and_update_character_note(): void
    {
        $token = $this->walasUser->createToken('test')->plainTextToken;

        $scaleData = [
            'beriman_bertakwa' => 'sangat_baik',
            'gotong_royong' => 'baik',
            'mandiri' => 'membudaya',
            'bernalar_kritis' => 'berkembang',
        ];

        // 1. Create note
        $resCreate = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/character-notes', [
                'student_id' => $this->student1->id,
                'period_id' => $this->periodMonth8->id,
                'scale_data' => $scaleData,
                'homeroom_note' => 'Ananda Aditya menunjukkan kepemimpinan yang baik dan aktif berdiskusi.',
            ]);

        $resCreate->assertStatus(201)
            ->assertJsonPath('data.student_id', $this->student1->id)
            ->assertJsonPath('data.scale_data.beriman_bertakwa', 'sangat_baik')
            ->assertJsonPath('data.homeroom_note', 'Ananda Aditya menunjukkan kepemimpinan yang baik dan aktif berdiskusi.');

        $this->assertDatabaseHas('character_notes', [
            'student_id' => $this->student1->id,
            'period_id' => $this->periodMonth8->id,
            'author_id' => $this->walasUser->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->walasUser->id,
            'action' => 'character_note.saved',
        ]);

        // 2. Update note (upsert)
        $resUpdate = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/character-notes', [
                'student_id' => $this->student1->id,
                'period_id' => $this->periodMonth8->id,
                'scale_data' => array_merge($scaleData, ['bernalar_kritis' => 'sangat_baik']),
                'homeroom_note' => 'Peningkatan bernalar kritis sangat signifikan pada bulan ini.',
            ]);

        $resUpdate->assertStatus(201)
            ->assertJsonPath('data.scale_data.bernalar_kritis', 'sangat_baik')
            ->assertJsonPath('data.homeroom_note', 'Peningkatan bernalar kritis sangat signifikan pada bulan ini.');

        // Memastikan tidak duplikat baris
        $this->assertCount(1, CharacterNote::where('student_id', $this->student1->id)->get());
    }

    public function test_be_grd_02_bulk_create_character_notes_for_class(): void
    {
        $token = $this->walasUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/character-notes/bulk', [
                'period_id' => $this->periodMonth8->id,
                'notes' => [
                    [
                        'student_id' => $this->student1->id,
                        'scale_data' => ['mandiri' => 'sangat_baik', 'gotong_royong' => 'baik'],
                        'homeroom_note' => 'Catatan Aditya',
                    ],
                    [
                        'student_id' => $this->student2->id,
                        'scale_data' => ['mandiri' => 'baik', 'gotong_royong' => 'sangat_baik'],
                        'homeroom_note' => 'Catatan Bella',
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.count', 2);

        $this->assertDatabaseHas('character_notes', [
            'student_id' => $this->student1->id,
            'period_id' => $this->periodMonth8->id,
        ]);

        $this->assertDatabaseHas('character_notes', [
            'student_id' => $this->student2->id,
            'period_id' => $this->periodMonth8->id,
        ]);
    }

    public function test_be_grd_02_entering_character_note_in_locked_period_rejected_422(): void
    {
        $this->periodMonth8->update([
            'status' => 'locked',
            'locked_by' => $this->adminUser->id,
            'locked_at' => Carbon::now(),
        ]);

        $token = $this->walasUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/character-notes', [
                'student_id' => $this->student1->id,
                'period_id' => $this->periodMonth8->id,
                'scale_data' => ['mandiri' => 'baik'],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');
    }

    public function test_be_grd_02_non_homeroom_teacher_cannot_create_character_note_403(): void
    {
        // otherTeacher (Siti) bukan wali kelas dari Aditya (walas adalah Budi)
        $tokenOtherTeacher = $this->otherTeacherUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$tokenOtherTeacher}")
            ->postJson('/api/v1/character-notes', [
                'student_id' => $this->student1->id,
                'period_id' => $this->periodMonth8->id,
                'scale_data' => ['mandiri' => 'baik'],
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_be_grd_02_student_can_only_view_own_character_notes_idor_safe(): void
    {
        $note1 = CharacterNote::create([
            'student_id' => $this->student1->id,
            'period_id' => $this->periodMonth8->id,
            'scale_data' => ['mandiri' => 'sangat_baik'],
            'homeroom_note' => 'Catatan Rahasia Aditya',
            'author_id' => $this->walasUser->id,
        ]);

        $note2 = CharacterNote::create([
            'student_id' => $this->student2->id,
            'period_id' => $this->periodMonth8->id,
            'scale_data' => ['mandiri' => 'cukup'],
            'homeroom_note' => 'Catatan Rahasia Bella',
            'author_id' => $this->walasUser->id,
        ]);

        $tokenStudent1 = $this->student1User->createToken('test')->plainTextToken;

        // Siswa 1 meminta daftar: hanya melihat miliknya
        $resList = $this->withHeader('Authorization', "Bearer {$tokenStudent1}")
            ->getJson('/api/v1/character-notes');
        $resList->assertStatus(200);
        $this->assertCount(1, $resList->json('data'));
        $this->assertEquals($this->student1->id, $resList->json('data.0.student_id'));

        // Siswa 1 melihat detail miliknya
        $resDetailOwn = $this->withHeader('Authorization', "Bearer {$tokenStudent1}")
            ->getJson("/api/v1/character-notes/{$note1->id}");
        $resDetailOwn->assertStatus(200)
            ->assertJsonPath('data.id', $note1->id);

        // Siswa 1 mencoba mengintip detail catatan Siswa 2 -> 403 Forbidden
        $resForbidden = $this->withHeader('Authorization', "Bearer {$tokenStudent1}")
            ->getJson("/api/v1/character-notes/{$note2->id}");
        $resForbidden->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_be_grd_02_parent_can_view_child_character_notes(): void
    {
        $note1 = CharacterNote::create([
            'student_id' => $this->student1->id,
            'period_id' => $this->periodMonth8->id,
            'scale_data' => ['mandiri' => 'sangat_baik'],
            'homeroom_note' => 'Catatan untuk orang tua Aditya',
            'author_id' => $this->walasUser->id,
        ]);

        $note2 = CharacterNote::create([
            'student_id' => $this->student2->id,
            'period_id' => $this->periodMonth8->id,
            'scale_data' => ['mandiri' => 'cukup'],
            'homeroom_note' => 'Catatan orang tua Bella',
            'author_id' => $this->walasUser->id,
        ]);

        $tokenGuardian = $this->guardianUser->createToken('test')->plainTextToken;

        // Orang tua melihat anaknya (Aditya)
        $resDetailChild = $this->withHeader('Authorization', "Bearer {$tokenGuardian}")
            ->getJson("/api/v1/character-notes/{$note1->id}");
        $resDetailChild->assertStatus(200)
            ->assertJsonPath('data.student_id', $this->student1->id);

        // Orang tua mencoba mengintip anak orang lain (Bella) -> 403 Forbidden
        $resForbidden = $this->withHeader('Authorization', "Bearer {$tokenGuardian}")
            ->getJson("/api/v1/character-notes/{$note2->id}");
        $resForbidden->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_be_grd_02_walas_can_delete_character_note(): void
    {
        $note = CharacterNote::create([
            'student_id' => $this->student1->id,
            'period_id' => $this->periodMonth8->id,
            'scale_data' => ['mandiri' => 'sangat_baik'],
            'author_id' => $this->walasUser->id,
        ]);

        $token = $this->walasUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/character-notes/{$note->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'Catatan karakter berhasil dihapus');

        $this->assertDatabaseMissing('character_notes', ['id' => $note->id]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->walasUser->id,
            'action' => 'character_note.deleted',
        ]);
    }

    public function test_be_grd_02_cannot_delete_character_note_when_period_is_locked_422(): void
    {
        $note = CharacterNote::create([
            'student_id' => $this->student1->id,
            'period_id' => $this->periodMonth8->id,
            'scale_data' => ['mandiri' => 'sangat_baik'],
            'author_id' => $this->walasUser->id,
        ]);

        $this->periodMonth8->update([
            'status' => 'locked',
            'locked_by' => $this->adminUser->id,
            'locked_at' => Carbon::now(),
        ]);

        $token = $this->walasUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/character-notes/{$note->id}");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION');
    }

    public function test_be_grd_02_walas_can_get_class_character_notes_matrix(): void
    {
        // Berikan catatan untuk siswa 1 saja
        CharacterNote::create([
            'student_id' => $this->student1->id,
            'period_id' => $this->periodMonth8->id,
            'scale_data' => ['beriman_bertakwa' => 'sangat_baik', 'gotong_royong' => 'baik'],
            'homeroom_note' => 'Catatan Siswa 1',
            'author_id' => $this->walasUser->id,
        ]);

        $token = $this->walasUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/character-notes/matrix?class_id={$this->classA->id}&period_id={$this->periodMonth8->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total_students', 2)
            ->assertJsonPath('data.summary.complete_count', 1)
            ->assertJsonPath('data.summary.incomplete_count', 1)
            ->assertJsonPath('data.students.0.is_complete', true)
            ->assertJsonPath('data.students.1.is_complete', false);
    }

    public function test_be_grd_02_non_walas_teacher_cannot_get_class_character_notes_matrix_403(): void
    {
        $tokenOtherTeacher = $this->otherTeacherUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$tokenOtherTeacher}")
            ->getJson("/api/v1/character-notes/matrix?class_id={$this->classA->id}&period_id={$this->periodMonth8->id}");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_be_grd_02_batch_route_alias_works_and_creates_records(): void
    {
        $token = $this->walasUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/character-notes/batch', [
                'period_id' => $this->periodMonth8->id,
                'notes' => [
                    [
                        'student_id' => $this->student1->id,
                        'scale_data' => ['beriman_bertakwa' => 'baik'],
                        'homeroom_note' => 'Batch note 1',
                    ],
                    [
                        'student_id' => $this->student2->id,
                        'scale_data' => ['beriman_bertakwa' => 'sangat_baik'],
                        'homeroom_note' => 'Batch note 2',
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.count', 2);

        $this->assertDatabaseHas('character_notes', [
            'student_id' => $this->student1->id,
            'period_id' => $this->periodMonth8->id,
            'homeroom_note' => 'Batch note 1',
        ]);
    }

    public function test_be_grd_02_progress_report_integration_deterministic_lookup(): void
    {
        $scaleData = [
            'beriman_bertakwa' => 'sangat_baik',
            'gotong_royong' => 'baik',
            'mandiri' => 'membudaya',
        ];

        CharacterNote::create([
            'student_id' => $this->student1->id,
            'period_id' => $this->periodMonth8->id,
            'scale_data' => $scaleData,
            'homeroom_note' => 'Catatan Walas untuk Rapor Siswa 1',
            'author_id' => $this->walasUser->id,
        ]);

        // Pengambilan data deterministik oleh ADM-GRD-03 (E-Progress Report snapshot)
        $retrieved = CharacterNote::where('student_id', $this->student1->id)
            ->where('period_id', $this->periodMonth8->id)
            ->first();

        $this->assertNotNull($retrieved);
        $this->assertEquals('sangat_baik', $retrieved->scale_data['beriman_bertakwa']);
        $this->assertEquals('Catatan Walas untuk Rapor Siswa 1', $retrieved->homeroom_note);
        $this->assertEquals($this->walasUser->id, $retrieved->author_id);
    }
}
