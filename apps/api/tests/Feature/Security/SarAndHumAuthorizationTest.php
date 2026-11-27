<?php

namespace Tests\Feature\Security;

use App\Models\Alumni;
use App\Models\Asset;
use App\Models\AssetAudit;
use App\Models\ExchangeParticipant;
use App\Models\ExchangeProgram;
use App\Models\LabEquipment;
use App\Models\LabLoan;
use App\Models\LibraryBook;
use App\Models\Loan;
use App\Models\Partnership;
use App\Models\Role;
use App\Models\Room;
use App\Models\Student;
use App\Models\TracerResponse;
use App\Models\TracerSurvey;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SarAndHumAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $librarianUser;

    protected User $laboranUser;

    protected User $sarprasUser;

    protected User $humasUser;

    protected User $teacherUser;

    protected User $studentUser;

    protected User $otherStudentUser;

    protected User $guardianUser;

    protected User $guestUser;

    protected Student $student1;

    protected Student $student2;

    protected Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $roleLibrarian = Role::firstOrCreate(['code' => 'librarian'], ['name' => 'Pustakawan', 'is_system' => true]);
        $roleLaboran = Role::firstOrCreate(['code' => 'laboran'], ['name' => 'Laboran', 'is_system' => true]);
        $roleSarpras = Role::firstOrCreate(['code' => 'facility_staff'], ['name' => 'Staf Sarpras', 'is_system' => true]);
        $roleHumas = Role::firstOrCreate(['code' => 'humas'], ['name' => 'Staf Humas', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Wali Murid', 'is_system' => true]);
        $roleGuest = Role::firstOrCreate(['code' => 'guest'], ['name' => 'Tamu PPDB', 'is_system' => true]);

        $this->librarianUser = $this->makeUser('p011_librarian', $roleLibrarian);
        $this->laboranUser = $this->makeUser('p011_laboran', $roleLaboran);
        $this->sarprasUser = $this->makeUser('p011_sarpras', $roleSarpras);
        $this->humasUser = $this->makeUser('p011_humas', $roleHumas);
        $this->teacherUser = $this->makeUser('p011_teacher', $roleTeacher);
        $this->studentUser = $this->makeUser('p011_student1', $roleStudent);
        $this->otherStudentUser = $this->makeUser('p011_student2', $roleStudent);
        $this->guardianUser = $this->makeUser('p011_guardian', $roleGuardian);
        $this->guestUser = $this->makeUser('p011_guest', $roleGuest);

        $this->student1 = Student::create([
            'user_id' => $this->studentUser->id,
            'nis' => '20261101',
            'nisn' => '0011010101',
            'nik_enc' => '3579010101011101',
            'full_name' => 'Siswa Satu P011',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2011-01-01',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $this->student2 = Student::create([
            'user_id' => $this->otherStudentUser->id,
            'nis' => '20261102',
            'nisn' => '0011020202',
            'nik_enc' => '3579010101011102',
            'full_name' => 'Siswa Dua P011',
            'gender' => 'P',
            'birth_place' => 'Malang',
            'birth_date' => '2011-02-02',
            'entry_date' => '2026-07-01',
            'status' => 'aktif',
        ]);

        $this->room = Room::create([
            'name' => 'Lab Sains P011',
            'code' => 'LAB-P11',
            'room_type' => 'laboratorium',
            'capacity' => 30,
        ]);
    }

    private function makeUser(string $username, Role $role): User
    {
        $user = User::create([
            'username' => $username,
            'email' => "{$username}@example.test",
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_p0_11_borrower_cannot_self_return_library_or_lab_loan_and_manipulate_fines(): void
    {
        $book = LibraryBook::create([
            'title' => 'Fisika Modern',
            'author' => 'Tipler',
            'stock' => 2,
        ]);

        $bookLoan = Loan::create([
            'book_id' => $book->id,
            'borrower_id' => $this->studentUser->id,
            'loan_date' => Carbon::now()->subDays(10)->toDateString(),
            'due_date' => Carbon::now()->subDays(5)->toDateString(),
            'status' => 'dipinjam',
        ]);

        // Siswa mencoba self-return + memalsukan returned_at & fine_paid -> 403
        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/library/loans/{$bookLoan->id}/return", [
                'returned_at' => Carbon::now()->subDays(6)->toDateString(),
                'fine_paid' => true,
            ])
            ->assertStatus(403);

        $this->assertSame('dipinjam', $bookLoan->fresh()->status);

        // Pustakawan sah dapat memproses pengembalian dengan denda akurat
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->librarianUser, 'sanctum')
            ->postJson("/api/v1/library/loans/{$bookLoan->id}/return", [
                'returned_at' => Carbon::now()->toDateString(),
                'fine_paid' => false,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'dikembalikan')
            ->assertJsonPath('data.fine_amount', 5000)
            ->assertJsonPath('data.fine_paid', false);

        // Lab loan self-return juga ditolak 403
        $equipment = LabEquipment::create([
            'room_id' => $this->room->id,
            'name' => 'Mikroskop Digital',
            'stock' => 3,
        ]);

        $labLoan = LabLoan::create([
            'equipment_id' => $equipment->id,
            'borrower_id' => $this->studentUser->id,
            'loan_date' => Carbon::now()->subDays(2)->toDateString(),
            'due_date' => Carbon::now()->subDay()->toDateString(),
            'quantity' => 1,
            'status' => 'dipinjam',
        ]);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/lab-loans/{$labLoan->id}/return", [
                'returned_at' => Carbon::now()->subDays(2)->toDateString(),
            ])
            ->assertStatus(403);

        $this->assertSame('dipinjam', $labLoan->fresh()->status);

        // Laboran sah berhasil memproses pengembalian
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->laboranUser, 'sanctum')
            ->postJson("/api/v1/lab-loans/{$labLoan->id}/return", [])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'dikembalikan');
    }

    public function test_p0_11_book_and_equipment_detail_hides_borrower_pii_from_non_staff(): void
    {
        $book = LibraryBook::create([
            'title' => 'Matematika Diskrit',
            'author' => 'Rosen',
            'stock' => 3,
        ]);

        Loan::create([
            'book_id' => $book->id,
            'borrower_id' => $this->otherStudentUser->id,
            'loan_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addDays(7)->toDateString(),
            'status' => 'dipinjam',
        ]);

        // Siswa tetap bisa melihat katalog & detail buku, TANPA bocor daftar peminjam lain
        $studentBookResp = $this->actingAs($this->studentUser, 'sanctum')
            ->getJson("/api/v1/library/books/{$book->id}");

        $studentBookResp->assertStatus(200)
            ->assertJsonMissingPath('data.loans');
        $this->assertStringNotContainsString($this->otherStudentUser->email, $studentBookResp->getContent());

        // Pustakawan melihat riwayat peminjam
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->librarianUser, 'sanctum')
            ->getJson("/api/v1/library/books/{$book->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.loans.0.borrower_id', $this->otherStudentUser->id);

        // Lab equipment detail menyembunyikan loans.borrower dari siswa
        $equipment = LabEquipment::create([
            'room_id' => $this->room->id,
            'name' => 'Spektrometer',
            'stock' => 2,
        ]);

        LabLoan::create([
            'equipment_id' => $equipment->id,
            'borrower_id' => $this->otherStudentUser->id,
            'loan_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addDays(3)->toDateString(),
            'quantity' => 1,
            'status' => 'dipinjam',
        ]);

        $this->app['auth']->forgetGuards();
        $studentLabResp = $this->actingAs($this->studentUser, 'sanctum')
            ->getJson("/api/v1/lab-equipment/{$equipment->id}");

        $studentLabResp->assertStatus(200)
            ->assertJsonMissingPath('data.loans');
        $this->assertStringNotContainsString($this->otherStudentUser->email, $studentLabResp->getContent());
    }

    public function test_p0_11_internal_sar_read_endpoints_restricted_to_authorized_staff(): void
    {
        $asset = Asset::create([
            'asset_code' => 'AST-P11-001',
            'name' => 'Server Rack Sekolah',
            'category' => 'IT',
            'room_id' => $this->room->id,
            'asset_condition' => 'baik',
        ]);

        $audit = AssetAudit::create([
            'title' => 'Stok Opname Semester 1',
            'audit_date' => Carbon::now()->toDateString(),
            'auditor_id' => $this->sarprasUser->id,
            'status' => 'selesai',
        ]);

        foreach ([$this->studentUser, $this->guardianUser, $this->guestUser] as $actor) {
            $this->app['auth']->forgetGuards();
            $this->actingAs($actor, 'sanctum')->getJson('/api/v1/assets')->assertStatus(403);
            $this->actingAs($actor, 'sanctum')->getJson('/api/v1/assets/summary')->assertStatus(403);
            $this->actingAs($actor, 'sanctum')->getJson("/api/v1/assets/{$asset->id}")->assertStatus(403);
            $this->actingAs($actor, 'sanctum')->getJson("/api/v1/assets/by-code/{$asset->asset_code}")->assertStatus(403);
            $this->actingAs($actor, 'sanctum')->getJson("/api/v1/assets/{$asset->id}/qr")->assertStatus(403);
            $this->actingAs($actor, 'sanctum')->getJson('/api/v1/asset-audits')->assertStatus(403);
            $this->actingAs($actor, 'sanctum')->getJson("/api/v1/asset-audits/{$audit->id}")->assertStatus(403);
            $this->actingAs($actor, 'sanctum')->getJson('/api/v1/library/summary')->assertStatus(403);
            $this->actingAs($actor, 'sanctum')->getJson('/api/v1/lab-equipment/summary')->assertStatus(403);
        }

        // Guru umum ditolak akses internal audit aset & summary perpustakaan/lab, namun diizinkan scan QR aset
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->teacherUser, 'sanctum')->getJson('/api/v1/asset-audits')->assertStatus(403);
        $this->actingAs($this->teacherUser, 'sanctum')->getJson('/api/v1/library/summary')->assertStatus(403);
        $this->actingAs($this->teacherUser, 'sanctum')->getJson('/api/v1/lab-equipment/summary')->assertStatus(403);
        $this->actingAs($this->teacherUser, 'sanctum')->getJson("/api/v1/assets/by-code/{$asset->asset_code}")->assertStatus(200);

        // Staf berwenang tetap dapat mengakses semua endpoint internalnya
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->sarprasUser, 'sanctum')->getJson('/api/v1/assets')->assertStatus(200);
        $this->actingAs($this->sarprasUser, 'sanctum')->getJson('/api/v1/asset-audits')->assertStatus(200);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->librarianUser, 'sanctum')->getJson('/api/v1/library/summary')->assertStatus(200);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->laboranUser, 'sanctum')->getJson('/api/v1/lab-equipment/summary')->assertStatus(200);
    }

    public function test_p0_11_internal_hum_read_endpoints_and_tracer_responses_protected(): void
    {
        $partnership = Partnership::create([
            'partner_name' => 'Universitas Mitra Rahasia',
            'mou_number' => 'MOU/2026/P011',
            'partner_type' => 'universitas',
            'contact_person' => 'Dr. Mitra Privat',
            'phone' => '081999888777',
            'scope' => 'Riset & Pertukaran',
            'valid_from' => Carbon::now()->subMonth()->toDateString(),
            'valid_until' => Carbon::now()->addDays(15)->toDateString(),
            'status' => 'aktif',
        ]);

        $program = ExchangeProgram::create([
            'partnership_id' => $partnership->id,
            'name' => 'Pertukaran Sains Tokyo',
            'type' => 'pelajar',
            'destination_country' => 'Jepang',
            'start_date' => Carbon::now()->addMonth()->toDateString(),
            'end_date' => Carbon::now()->addMonths(2)->toDateString(),
            'status' => 'dibuka',
        ]);

        ExchangeParticipant::create([
            'program_id' => $program->id,
            'student_id' => $this->student2->id,
            'status' => 'terdaftar',
            'passport_number' => 'X99887766SECRET',
        ]);

        $alumni1 = Alumni::create([
            'student_id' => $this->student1->id,
            'graduation_year' => 2025,
            'current_status' => 'kuliah',
            'is_public' => false,
        ]);

        $alumni2 = Alumni::create([
            'student_id' => $this->student2->id,
            'graduation_year' => 2025,
            'current_status' => 'bekerja',
            'is_public' => false,
        ]);

        $activeSurvey = TracerSurvey::create([
            'title' => 'Tracer Study 2025',
            'questions' => [['id' => 'q1', 'label' => 'Penghasilan pertama?']],
            'is_active' => true,
            'created_by' => $this->humasUser->id,
        ]);

        $inactiveSurvey = TracerSurvey::create([
            'title' => 'Draf Tracer Internal',
            'questions' => [['id' => 'q1', 'label' => 'Internal']],
            'is_active' => false,
            'created_by' => $this->humasUser->id,
        ]);

        TracerResponse::create([
            'survey_id' => $activeSurvey->id,
            'alumni_id' => $alumni2->id,
            'answers' => ['q1' => 'RAHASIA-GAJI-ALUMNI-2'],
            'submitted_at' => Carbon::now(),
        ]);

        // 1. Siswa/Ortu/Guest ditolak 403 pada partnerships & exchange participants
        foreach ([$this->studentUser, $this->guardianUser, $this->guestUser] as $actor) {
            $this->app['auth']->forgetGuards();
            $this->actingAs($actor, 'sanctum')->getJson('/api/v1/partnerships')->assertStatus(403);
            $this->actingAs($actor, 'sanctum')->getJson('/api/v1/partnerships/expiring')->assertStatus(403);
            $this->actingAs($actor, 'sanctum')->getJson("/api/v1/partnerships/{$partnership->id}")->assertStatus(403);
            $this->actingAs($actor, 'sanctum')->getJson("/api/v1/exchange-programs/{$program->id}/participants")->assertStatus(403);
        }

        // 2. Detail program pertukaran untuk siswa TIDAK membocorkan participants / nomor paspor
        $this->app['auth']->forgetGuards();
        $programResp = $this->actingAs($this->studentUser, 'sanctum')
            ->getJson("/api/v1/exchange-programs/{$program->id}");
        $programResp->assertStatus(200)
            ->assertJsonMissingPath('data.participants');
        $this->assertStringNotContainsString('X99887766SECRET', $programResp->getContent());

        // 3. Tracer survey detail untuk non-staf: aktif tidak bocor responses; nonaktif -> 403
        $this->app['auth']->forgetGuards();
        $surveyResp = $this->actingAs($this->studentUser, 'sanctum')
            ->getJson("/api/v1/tracer-surveys/{$activeSurvey->id}");
        $surveyResp->assertStatus(200)
            ->assertJsonMissingPath('data.responses');
        $this->assertStringNotContainsString('RAHASIA-GAJI-ALUMNI-2', $surveyResp->getContent());

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->studentUser, 'sanctum')
            ->getJson("/api/v1/tracer-surveys/{$inactiveSurvey->id}")
            ->assertStatus(403);

        // 4. Alumni tidak boleh mengisi tracer survey atas nama alumni lain (anti-IDOR)
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/v1/tracer-surveys/{$activeSurvey->id}/responses", [
                'alumni_id' => $alumni2->id,
                'answers' => ['q1' => 'Impersonasi'],
            ])
            ->assertStatus(403);

        // 5. Humas tetap memiliki akses penuh pada partnerships, participants, dan tracer responses
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->humasUser, 'sanctum')->getJson('/api/v1/partnerships')->assertStatus(200);
        $this->actingAs($this->humasUser, 'sanctum')->getJson('/api/v1/partnerships/expiring')->assertStatus(200);
        $this->actingAs($this->humasUser, 'sanctum')
            ->getJson("/api/v1/exchange-programs/{$program->id}/participants")
            ->assertStatus(200)
            ->assertJsonPath('data.0.passport_number', 'X99887766SECRET');
        $this->actingAs($this->humasUser, 'sanctum')
            ->getJson("/api/v1/tracer-surveys/{$activeSurvey->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.responses.0.alumni_id', $alumni2->id);
        $this->assertNotNull($alumni1->id);
    }
}
