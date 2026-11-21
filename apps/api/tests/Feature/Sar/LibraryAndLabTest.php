<?php

namespace Tests\Feature\Sar;

use App\Models\LabEquipment;
use App\Models\LabLoan;
use App\Models\LibraryBook;
use App\Models\Loan;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LibraryAndLabTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $librarianUser;

    protected User $laboranUser;

    protected User $studentUser;

    protected User $otherStudentUser;

    protected Room $labFisika;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleLibrarian = Role::firstOrCreate(['code' => 'librarian'], ['name' => 'Pustakawan', 'is_system' => true]);
        $roleLaboran = Role::firstOrCreate(['code' => 'laboran'], ['name' => 'Laboran', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $permLib = Permission::firstOrCreate(['code' => 'library.book.manage'], ['description' => 'Mengelola buku perpustakaan']);
        $permLab = Permission::firstOrCreate(['code' => 'lab.equipment.manage'], ['description' => 'Mengelola alat laboratorium']);

        $roleLibrarian->permissions()->syncWithoutDetaching([$permLib->id]);
        $roleLaboran->permissions()->syncWithoutDetaching([$permLab->id]);
        $roleAdmin->permissions()->syncWithoutDetaching([$permLib->id, $permLab->id]);

        $this->adminUser = User::create([
            'username' => 'admin_sar4',
            'email' => 'admin_sar4@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->librarianUser = User::create([
            'username' => 'pustakawan_rudi',
            'email' => 'rudi_perpus@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->librarianUser->roles()->attach($roleLibrarian->id);

        $this->laboranUser = User::create([
            'username' => 'laboran_siti',
            'email' => 'siti_lab@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->laboranUser->roles()->attach($roleLaboran->id);

        $this->studentUser = User::create([
            'username' => 'siswa_budi',
            'email' => 'budi_siswa@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($roleStudent->id);

        $this->otherStudentUser = User::create([
            'username' => 'siswa_ani',
            'email' => 'ani_siswa@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->otherStudentUser->roles()->attach($roleStudent->id);

        $this->labFisika = Room::create([
            'name' => 'Laboratorium Fisika',
            'code' => 'LAB-FIS-1',
            'room_type' => 'laboratorium',
            'capacity' => 36,
        ]);
    }

    public function test_be_sar_04_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/library/books')->assertStatus(401);
        $this->getJson('/api/v1/lab-equipment')->assertStatus(401);
        $this->getJson('/api/v1/library/loans')->assertStatus(401);
        $this->getJson('/api/v1/lab-loans')->assertStatus(401);
    }

    public function test_be_sar_04_librarian_can_create_and_update_library_book(): void
    {
        $payload = [
            'title' => 'Fisika Dasar Jilid 1',
            'author' => 'Halliday & Resnick',
            'publisher' => 'Erlangga',
            'publish_year' => 2021,
            'isbn' => '978-602-01-1234-5',
            'category' => 'Sains & Fisika',
            'book_type' => 'fisik',
            'stock' => 5,
            'location_shelf' => 'Rak A-03',
        ];

        $res = $this->actingAs($this->librarianUser)
            ->postJson('/api/v1/library/books', $payload);

        $res->assertStatus(201)
            ->assertJsonPath('data.title', 'Fisika Dasar Jilid 1')
            ->assertJsonPath('data.stock', 5)
            ->assertJsonPath('data.available_stock', 5);

        $bookId = $res->json('data.id');

        // Update
        $updateRes = $this->actingAs($this->librarianUser)
            ->putJson("/api/v1/library/books/{$bookId}", [
                'stock' => 7,
                'location_shelf' => 'Rak B-01',
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.stock', 7)
            ->assertJsonPath('data.location_shelf', 'Rak B-01');

        $this->assertDatabaseHas('library_books', [
            'id' => $bookId,
            'stock' => 7,
            'location_shelf' => 'Rak B-01',
        ]);
    }

    public function test_be_sar_04_regular_user_cannot_create_or_update_library_book(): void
    {
        $this->actingAs($this->studentUser)
            ->postJson('/api/v1/library/books', [
                'title' => 'Buku Siswa',
                'author' => 'Penulis Siswa',
                'stock' => 1,
            ])
            ->assertStatus(403);

        $book = LibraryBook::create([
            'title' => 'Biologi Sel',
            'author' => 'Campbell',
            'stock' => 3,
        ]);

        $this->actingAs($this->studentUser)
            ->putJson("/api/v1/library/books/{$book->id}", [
                'title' => 'Buku Ganti Judul',
            ])
            ->assertStatus(403);
    }

    public function test_be_sar_04_validation_error_when_creating_book_with_missing_fields(): void
    {
        $this->actingAs($this->librarianUser)
            ->postJson('/api/v1/library/books', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'author']);
    }

    public function test_be_sar_04_anyone_can_search_and_list_books_with_available_stock(): void
    {
        $book1 = LibraryBook::create([
            'title' => 'Kalkulus Lanjut',
            'author' => 'Purcell',
            'category' => 'Matematika',
            'book_type' => 'fisik',
            'stock' => 2,
        ]);

        $book2 = LibraryBook::create([
            'title' => 'Kimia Organik',
            'author' => 'Fessenden',
            'category' => 'Kimia',
            'book_type' => 'fisik',
            'stock' => 4,
        ]);

        // Pinjam 1 eksemplar buku 1
        Loan::create([
            'book_id' => $book1->id,
            'borrower_id' => $this->studentUser->id,
            'loan_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addDays(7)->toDateString(),
            'status' => 'dipinjam',
        ]);

        $res = $this->actingAs($this->studentUser)
            ->getJson('/api/v1/library/books?search=kalkulus');

        $res->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Kalkulus Lanjut')
            ->assertJsonPath('data.0.stock', 2)
            ->assertJsonPath('data.0.available_stock', 1);
    }

    public function test_be_sar_04_cannot_delete_book_with_active_loans(): void
    {
        $book = LibraryBook::create([
            'title' => 'Sejarah Indonesia',
            'author' => 'Nugroho',
            'stock' => 1,
        ]);

        Loan::create([
            'book_id' => $book->id,
            'borrower_id' => $this->studentUser->id,
            'loan_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addDays(7)->toDateString(),
            'status' => 'dipinjam',
        ]);

        $this->actingAs($this->librarianUser)
            ->deleteJson("/api/v1/library/books/{$book->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'HTTP_ERROR');
    }

    public function test_be_sar_04_can_delete_book_without_active_loans(): void
    {
        $book = LibraryBook::create([
            'title' => 'Novel Laskar Pelangi',
            'author' => 'Andrea Hirata',
            'stock' => 2,
        ]);

        $this->actingAs($this->librarianUser)
            ->deleteJson("/api/v1/library/books/{$book->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('library_books', ['id' => $book->id]);
    }

    public function test_be_sar_04_user_can_borrow_book_when_stock_available(): void
    {
        $book = LibraryBook::create([
            'title' => 'Algoritma Pemrograman',
            'author' => 'Munir',
            'stock' => 1,
        ]);

        $res = $this->actingAs($this->studentUser)
            ->postJson('/api/v1/library/loans', [
                'book_id' => $book->id,
                'due_date' => Carbon::now()->addDays(7)->toDateString(),
                'notes' => 'Tugas kuliah umum pemrograman',
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.borrower_id', $this->studentUser->id)
            ->assertJsonPath('data.status', 'dipinjam')
            ->assertJsonPath('data.fine_amount', 0);

        // Setelah dipinjam, stok tersedia menjadi 0
        $this->assertEquals(0, $book->fresh()->available_stock);
    }

    public function test_be_sar_04_user_cannot_borrow_book_when_stock_exhausted(): void
    {
        $book = LibraryBook::create([
            'title' => 'Atlas Dunia',
            'author' => 'Gramedia',
            'stock' => 1,
        ]);

        // Pinjam stok yang ada
        Loan::create([
            'book_id' => $book->id,
            'borrower_id' => $this->otherStudentUser->id,
            'loan_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addDays(7)->toDateString(),
            'status' => 'dipinjam',
        ]);

        // Siswa lain mencoba meminjam
        $this->actingAs($this->studentUser)
            ->postJson('/api/v1/library/loans', [
                'book_id' => $book->id,
                'due_date' => Carbon::now()->addDays(7)->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'HTTP_ERROR');
    }

    public function test_be_sar_04_user_cannot_borrow_on_behalf_of_others_without_privilege(): void
    {
        $book = LibraryBook::create([
            'title' => 'Ensiklopedia Hewan',
            'author' => 'National Geographic',
            'stock' => 2,
        ]);

        // Siswa mencoba meminjam atas nama siswa lain
        $this->actingAs($this->studentUser)
            ->postJson('/api/v1/library/loans', [
                'book_id' => $book->id,
                'borrower_id' => $this->otherStudentUser->id,
                'due_date' => Carbon::now()->addDays(7)->toDateString(),
            ])
            ->assertStatus(403);
    }

    public function test_be_sar_04_return_book_on_time_has_zero_fine(): void
    {
        $book = LibraryBook::create([
            'title' => 'Kamus Besar Bahasa Indonesia',
            'author' => 'Kemdikbud',
            'stock' => 2,
        ]);

        $loan = Loan::create([
            'book_id' => $book->id,
            'borrower_id' => $this->studentUser->id,
            'loan_date' => Carbon::now()->subDays(5)->toDateString(),
            'due_date' => Carbon::now()->addDays(2)->toDateString(),
            'status' => 'dipinjam',
        ]);

        $res = $this->actingAs($this->studentUser)
            ->postJson("/api/v1/library/loans/{$loan->id}/return", [
                'returned_at' => Carbon::now()->toDateString(),
            ]);

        $res->assertStatus(200)
            ->assertJsonPath('data.status', 'dikembalikan')
            ->assertJsonPath('data.fine_amount', 0)
            ->assertJsonPath('data.fine_paid', true);
    }

    public function test_be_sar_04_return_book_late_calculates_fine_automatically(): void
    {
        $book = LibraryBook::create([
            'title' => 'Fisika Kuantum',
            'author' => 'Griffiths',
            'stock' => 2,
        ]);

        // Terlambat 3 hari
        $dueDate = Carbon::now()->subDays(3)->toDateString();
        $loan = Loan::create([
            'book_id' => $book->id,
            'borrower_id' => $this->studentUser->id,
            'loan_date' => Carbon::now()->subDays(10)->toDateString(),
            'due_date' => $dueDate,
            'status' => 'dipinjam',
        ]);

        // Kembalikan hari ini (terlambat 3 hari -> denda 3 x 1.000 = 3.000)
        $res = $this->actingAs($this->librarianUser)
            ->postJson("/api/v1/library/loans/{$loan->id}/return", [
                'returned_at' => Carbon::now()->toDateString(),
                'fine_paid' => true,
            ]);

        $res->assertStatus(200)
            ->assertJsonPath('data.status', 'dikembalikan')
            ->assertJsonPath('data.fine_amount', 3000)
            ->assertJsonPath('data.fine_paid', true);

        $this->assertDatabaseHas('loans', [
            'id' => $loan->id,
            'status' => 'dikembalikan',
            'fine_amount' => 3000,
            'fine_paid' => true,
        ]);
    }

    public function test_be_sar_04_cannot_return_already_returned_book(): void
    {
        $book = LibraryBook::create([
            'title' => 'Statistika Terapan',
            'author' => 'Walpole',
            'stock' => 2,
        ]);

        $loan = Loan::create([
            'book_id' => $book->id,
            'borrower_id' => $this->studentUser->id,
            'loan_date' => Carbon::now()->subDays(5)->toDateString(),
            'due_date' => Carbon::now()->addDays(2)->toDateString(),
            'returned_at' => Carbon::now()->toDateString(),
            'status' => 'dikembalikan',
        ]);

        $this->actingAs($this->studentUser)
            ->postJson("/api/v1/library/loans/{$loan->id}/return", [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'HTTP_ERROR');
    }

    public function test_be_sar_04_scoping_student_only_views_own_loans(): void
    {
        $book = LibraryBook::create([
            'title' => 'Buku Umum',
            'author' => 'Penulis',
            'stock' => 5,
        ]);

        // Pinjaman siswa 1
        Loan::create([
            'book_id' => $book->id,
            'borrower_id' => $this->studentUser->id,
            'loan_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addDays(7)->toDateString(),
            'status' => 'dipinjam',
        ]);

        // Pinjaman siswa 2
        Loan::create([
            'book_id' => $book->id,
            'borrower_id' => $this->otherStudentUser->id,
            'loan_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addDays(7)->toDateString(),
            'status' => 'dipinjam',
        ]);

        // Siswa 1 hanya melihat miliknya (1 item)
        $resStudent = $this->actingAs($this->studentUser)->getJson('/api/v1/library/loans');
        $resStudent->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.borrower_id', $this->studentUser->id);

        // Pustakawan melihat semua (2 item)
        $resLib = $this->actingAs($this->librarianUser)->getJson('/api/v1/library/loans');
        $resLib->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_be_sar_04_laboran_can_create_and_update_lab_equipment(): void
    {
        $payload = [
            'room_id' => $this->labFisika->id,
            'code' => 'EQ-FIS-001',
            'name' => 'Mikroskop Binokuler Olympus',
            'category' => 'Optik',
            'stock' => 8,
            'condition' => 'baik',
            'notes' => 'Pembelian TA 2025/2026',
        ];

        $res = $this->actingAs($this->laboranUser)
            ->postJson('/api/v1/lab-equipment', $payload);

        $res->assertStatus(201)
            ->assertJsonPath('data.name', 'Mikroskop Binokuler Olympus')
            ->assertJsonPath('data.stock', 8)
            ->assertJsonPath('data.available_stock', 8);

        $equipmentId = $res->json('data.id');

        // Update
        $updateRes = $this->actingAs($this->laboranUser)
            ->putJson("/api/v1/lab-equipment/{$equipmentId}", [
                'stock' => 10,
                'condition' => 'baik',
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.stock', 10);

        $this->assertDatabaseHas('lab_equipment', [
            'id' => $equipmentId,
            'stock' => 10,
        ]);
    }

    public function test_be_sar_04_regular_user_cannot_create_lab_equipment(): void
    {
        $this->actingAs($this->studentUser)
            ->postJson('/api/v1/lab-equipment', [
                'room_id' => $this->labFisika->id,
                'name' => 'Alat Ilegal',
                'stock' => 1,
            ])
            ->assertStatus(403);
    }

    public function test_be_sar_04_can_filter_lab_equipment_by_room_and_search(): void
    {
        $eq1 = LabEquipment::create([
            'room_id' => $this->labFisika->id,
            'code' => 'EQ-FIS-10',
            'name' => 'Neraca Ohaus 311g',
            'category' => 'Alat Ukur',
            'stock' => 5,
        ]);

        $roomKimia = Room::create([
            'name' => 'Laboratorium Kimia',
            'code' => 'LAB-KIM-1',
            'room_type' => 'laboratorium',
            'capacity' => 30,
        ]);

        $eq2 = LabEquipment::create([
            'room_id' => $roomKimia->id,
            'code' => 'EQ-KIM-01',
            'name' => 'Buret Asam 50ml',
            'category' => 'Gelas',
            'stock' => 15,
        ]);

        // Filter by room lab fisika
        $res = $this->actingAs($this->studentUser)
            ->getJson("/api/v1/lab-equipment?room_id={$this->labFisika->id}");

        $res->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Neraca Ohaus 311g');
    }

    public function test_be_sar_04_user_can_borrow_lab_equipment_for_practicum(): void
    {
        $equipment = LabEquipment::create([
            'room_id' => $this->labFisika->id,
            'name' => 'Multimeter Digital',
            'code' => 'EQ-FIS-99',
            'stock' => 4,
        ]);

        $res = $this->actingAs($this->studentUser)
            ->postJson('/api/v1/lab-loans', [
                'equipment_id' => $equipment->id,
                'quantity' => 2,
                'due_date' => Carbon::now()->addDays(1)->toDateString(),
                'purpose' => 'Praktikum Rangkaian Arus Searah',
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.equipment_id', $equipment->id)
            ->assertJsonPath('data.quantity', 2)
            ->assertJsonPath('data.status', 'dipinjam');

        // Sisa stok tersedia = 4 - 2 = 2
        $this->assertEquals(2, $equipment->fresh()->available_stock);
    }

    public function test_be_sar_04_user_cannot_borrow_lab_equipment_exceeding_available_stock(): void
    {
        $equipment = LabEquipment::create([
            'room_id' => $this->labFisika->id,
            'name' => 'Osiloskop Analog',
            'stock' => 1,
        ]);

        $this->actingAs($this->studentUser)
            ->postJson('/api/v1/lab-loans', [
                'equipment_id' => $equipment->id,
                'quantity' => 2, // minta 2 padahal hanya ada 1
                'due_date' => Carbon::now()->addDays(1)->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'HTTP_ERROR');
    }

    public function test_be_sar_04_return_lab_equipment_updates_status_and_stock(): void
    {
        $equipment = LabEquipment::create([
            'room_id' => $this->labFisika->id,
            'name' => 'Catu Daya DC',
            'stock' => 3,
        ]);

        $loan = LabLoan::create([
            'equipment_id' => $equipment->id,
            'borrower_id' => $this->studentUser->id,
            'loan_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addDays(2)->toDateString(),
            'quantity' => 3,
            'status' => 'dipinjam',
        ]);

        // Sisa stok saat dipinjam = 0
        $this->assertEquals(0, $equipment->fresh()->available_stock);

        // Pengembalian
        $res = $this->actingAs($this->laboranUser)
            ->postJson("/api/v1/lab-loans/{$loan->id}/return", [
                'notes' => 'Alat dikembalikan dalam keadaan lengkap dan bersih.',
            ]);

        $res->assertStatus(200)
            ->assertJsonPath('data.status', 'dikembalikan');

        // Setelah dikembalikan, stok tersedia kembali 3
        $this->assertEquals(3, $equipment->fresh()->available_stock);
    }

    public function test_be_sar_04_cannot_delete_lab_equipment_with_active_loans(): void
    {
        $equipment = LabEquipment::create([
            'room_id' => $this->labFisika->id,
            'name' => 'Termometer Digital',
            'stock' => 2,
        ]);

        LabLoan::create([
            'equipment_id' => $equipment->id,
            'borrower_id' => $this->studentUser->id,
            'loan_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addDays(1)->toDateString(),
            'quantity' => 1,
            'status' => 'dipinjam',
        ]);

        $this->actingAs($this->laboranUser)
            ->deleteJson("/api/v1/lab-equipment/{$equipment->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'HTTP_ERROR');
    }

    public function test_be_sar_04_library_summary_metrics_returns_accurate_counts(): void
    {
        $book = LibraryBook::create([
            'title' => 'Biologi Sel Molekuler',
            'author' => 'Alberts',
            'category' => 'Sains',
            'book_type' => 'fisik',
            'stock' => 10,
        ]);

        Loan::create([
            'book_id' => $book->id,
            'borrower_id' => $this->studentUser->id,
            'loan_date' => Carbon::now()->subDays(10)->toDateString(),
            'due_date' => Carbon::now()->subDays(3)->toDateString(),
            'status' => 'dipinjam',
            'fine_amount' => 0,
            'fine_paid' => false,
        ]);

        $res = $this->actingAs($this->librarianUser)
            ->getJson('/api/v1/library/summary');

        $res->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_books',
                    'total_stock',
                    'active_loans',
                    'overdue_loans',
                    'total_fines_unpaid',
                    'category_counts',
                    'book_types',
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, $res->json('data.total_books'));
        $this->assertGreaterThanOrEqual(1, $res->json('data.active_loans'));
        $this->assertGreaterThanOrEqual(1, $res->json('data.overdue_loans'));
    }

    public function test_be_sar_04_lab_summary_metrics_returns_accurate_counts(): void
    {
        $equipment = LabEquipment::create([
            'room_id' => $this->labFisika->id,
            'name' => 'Neraca Ohaus 311g',
            'category' => 'Pengukuran',
            'stock' => 5,
            'condition' => 'baik',
        ]);

        LabLoan::create([
            'equipment_id' => $equipment->id,
            'borrower_id' => $this->studentUser->id,
            'loan_date' => Carbon::now()->subDays(5)->toDateString(),
            'due_date' => Carbon::now()->subDays(1)->toDateString(),
            'quantity' => 2,
            'status' => 'dipinjam',
        ]);

        $res = $this->actingAs($this->laboranUser)
            ->getJson('/api/v1/lab-equipment/summary');

        $res->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_equipment',
                    'total_units',
                    'active_loans',
                    'overdue_loans',
                    'conditions_breakdown',
                    'category_counts',
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, $res->json('data.total_equipment'));
        $this->assertGreaterThanOrEqual(1, $res->json('data.active_loans'));
        $this->assertGreaterThanOrEqual(1, $res->json('data.overdue_loans'));
    }

    public function test_be_sar_04_prevents_double_borrowing_of_same_book(): void
    {
        $book = LibraryBook::create([
            'title' => 'Struktur Aljabar',
            'author' => 'Dummit',
            'book_type' => 'fisik',
            'stock' => 5,
        ]);

        // Peminjaman pertama
        $this->actingAs($this->studentUser)
            ->postJson('/api/v1/library/loans', [
                'book_id' => $book->id,
                'due_date' => Carbon::now()->addDays(7)->toDateString(),
            ])
            ->assertStatus(201);

        // Peminjaman kedua oleh siswa yang sama untuk buku yang sama harus ditolak
        $this->actingAs($this->studentUser)
            ->postJson('/api/v1/library/loans', [
                'book_id' => $book->id,
                'due_date' => Carbon::now()->addDays(7)->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Peminjam masih memiliki status peminjaman aktif untuk buku ini.');
    }

    public function test_be_sar_04_multi_tenant_isolation_prevents_cross_school_access_on_library(): void
    {
        $schoolA = '99999999-0000-0000-0000-000000000001';
        $schoolB = '99999999-0000-0000-0000-000000000002';

        $this->librarianUser->school_id = $schoolA;
        $this->librarianUser->save();

        $bookSchoolB = LibraryBook::create([
            'school_id' => $schoolB,
            'title' => 'Buku Rahasia Sekolah B',
            'author' => 'Pengarang B',
            'stock' => 3,
        ]);

        // Akses detail dari pustakawan sekolah A harus ditolak 403
        $this->actingAs($this->librarianUser)
            ->getJson("/api/v1/library/books/{$bookSchoolB->id}")
            ->assertStatus(403);

        // Update dari pustakawan sekolah A harus ditolak 403
        $this->actingAs($this->librarianUser)
            ->putJson("/api/v1/library/books/{$bookSchoolB->id}", ['stock' => 10])
            ->assertStatus(403);

        // Peminjaman buku sekolah B oleh user sekolah A harus ditolak
        $this->studentUser->school_id = $schoolA;
        $this->studentUser->save();

        $this->actingAs($this->studentUser)
            ->postJson('/api/v1/library/loans', [
                'book_id' => $bookSchoolB->id,
                'due_date' => Carbon::now()->addDays(7)->toDateString(),
            ])
            ->assertStatus(403);
    }

    public function test_be_sar_04_multi_tenant_isolation_prevents_cross_school_access_on_lab(): void
    {
        $schoolA = '99999999-0000-0000-0000-000000000001';
        $schoolB = '99999999-0000-0000-0000-000000000002';

        $this->laboranUser->school_id = $schoolA;
        $this->laboranUser->save();

        $equipmentSchoolB = LabEquipment::create([
            'school_id' => $schoolB,
            'room_id' => $this->labFisika->id,
            'name' => 'Spektrofotometer Sekolah B',
            'stock' => 1,
        ]);

        // Akses detail alat lab sekolah B harus ditolak 403
        $this->actingAs($this->laboranUser)
            ->getJson("/api/v1/lab-equipment/{$equipmentSchoolB->id}")
            ->assertStatus(403);

        // Update alat lab sekolah B harus ditolak 403
        $this->actingAs($this->laboranUser)
            ->putJson("/api/v1/lab-equipment/{$equipmentSchoolB->id}", ['stock' => 5])
            ->assertStatus(403);

        // Peminjaman alat lab sekolah B oleh siswa sekolah A harus ditolak 403
        $this->studentUser->school_id = $schoolA;
        $this->studentUser->save();

        $this->actingAs($this->studentUser)
            ->postJson('/api/v1/lab-loans', [
                'equipment_id' => $equipmentSchoolB->id,
                'due_date' => Carbon::now()->addDays(2)->toDateString(),
                'quantity' => 1,
            ])
            ->assertStatus(403);
    }
}
