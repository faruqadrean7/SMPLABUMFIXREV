<?php

namespace Tests\Feature\Sar;

use App\Models\Asset;
use App\Models\AssetAudit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AssetAndAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $sarprasUser;

    protected User $teacherUser;

    protected Room $labKomputer;

    protected Room $ruangGuru;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $roleSarpras = Role::firstOrCreate(['code' => 'facility_staff'], ['name' => 'Staf Sarana & Prasarana', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);

        $permAsset = Permission::firstOrCreate(['code' => 'facility.asset.manage'], ['description' => 'Mengelola inventaris aset']);
        $roleSarpras->permissions()->syncWithoutDetaching([$permAsset->id]);
        $roleAdmin->permissions()->syncWithoutDetaching([$permAsset->id]);

        $this->adminUser = User::create([
            'username' => 'admin_asset',
            'email' => 'admin_asset@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->sarprasUser = User::create([
            'username' => 'petugas_aset',
            'email' => 'aset@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->sarprasUser->roles()->attach($roleSarpras->id);

        $this->teacherUser = User::create([
            'username' => 'guru_asep',
            'email' => 'asep@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher->id);

        $this->labKomputer = Room::create([
            'name' => 'Laboratorium Komputer 1',
            'code' => 'LAB-KOMP-1',
            'room_type' => 'laboratorium',
            'capacity' => 40,
            'is_active' => true,
        ]);

        $this->ruangGuru = Room::create([
            'name' => 'Ruang Guru Utama',
            'code' => 'R-GURU-01',
            'room_type' => 'kantor',
            'capacity' => 50,
            'is_active' => true,
        ]);
    }

    /**
     * BE-SAR-02: Staf Sarpras / Admin dapat melakukan CRUD aset.
     */
    public function test_be_sar_02_manage_asset_crud(): void
    {
        $this->app['auth']->forgetGuards();

        // 1. Create Asset
        $responseCreate = $this->actingAs($this->sarprasUser, 'sanctum')
            ->postJson('/api/v1/assets', [
                'asset_code' => 'AST-PC-LAB-001',
                'name' => 'PC Desktop All-in-One HP Pavilion',
                'category' => 'Elektronik / Komputer',
                'brand' => 'HP',
                'model' => 'Pavilion 24-k001',
                'serial_number' => 'SN-HP-202610-001',
                'room_id' => $this->labKomputer->id,
                'asset_condition' => 'baik',
                'acquired_at' => '2026-01-15',
                'notes' => 'Hibah Lab Komputer 2026',
            ]);

        $responseCreate->assertStatus(201)
            ->assertJsonPath('data.asset_code', 'AST-PC-LAB-001')
            ->assertJsonPath('data.brand', 'HP')
            ->assertJsonPath('data.room_id', $this->labKomputer->id);

        $assetId = $responseCreate->json('data.id');

        // 2. Read Detail Asset
        $responseShow = $this->actingAs($this->sarprasUser, 'sanctum')
            ->getJson("/api/v1/assets/{$assetId}");

        $responseShow->assertStatus(200)
            ->assertJsonPath('data.id', $assetId)
            ->assertJsonPath('data.name', 'PC Desktop All-in-One HP Pavilion');

        // 3. Update Asset
        $responseUpdate = $this->actingAs($this->sarprasUser, 'sanctum')
            ->putJson("/api/v1/assets/{$assetId}", [
                'name' => 'PC Desktop All-in-One HP Pavilion (Upgrade RAM 16GB)',
                'asset_condition' => 'baik',
                'notes' => 'Diupgrade pada Oktober 2026',
            ]);

        $responseUpdate->assertStatus(200)
            ->assertJsonPath('data.name', 'PC Desktop All-in-One HP Pavilion (Upgrade RAM 16GB)')
            ->assertJsonPath('data.notes', 'Diupgrade pada Oktober 2026');

        // 4. Delete Asset
        $responseDelete = $this->actingAs($this->sarprasUser, 'sanctum')
            ->deleteJson("/api/v1/assets/{$assetId}");

        $responseDelete->assertStatus(200);
        $this->assertDatabaseMissing('assets', ['id' => $assetId]);
    }

    /**
     * BE-SAR-02: Kode aset (QR/barcode) harus unik, duplikasi ditolak (422).
     */
    public function test_be_sar_02_duplicate_asset_code_rejected(): void
    {
        Asset::create([
            'asset_code' => 'AST-PROJ-001',
            'name' => 'Proyektor Epson',
            'category' => 'Elektronik',
            'asset_condition' => 'baik',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->sarprasUser, 'sanctum')
            ->postJson('/api/v1/assets', [
                'asset_code' => 'AST-PROJ-001', // Kode duplikat
                'name' => 'Proyektor Baru',
                'category' => 'Elektronik',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['asset_code']);
    }

    /**
     * BE-SAR-02: Scan / lookup aset via QR/barcode code instan.
     */
    public function test_be_sar_02_scan_lookup_asset_by_code(): void
    {
        $asset = Asset::create([
            'asset_code' => 'QR-AC-LG-2PK',
            'name' => 'AC LG Dual Inverter 2 PK',
            'category' => 'Pendingin Ruangan',
            'room_id' => $this->ruangGuru->id,
            'asset_condition' => 'baik',
        ]);

        $this->app['auth']->forgetGuards();

        // Siapa pun staf/guru terautentikasi dapat memindai QR code aset
        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->getJson("/api/v1/assets/by-code/{$asset->asset_code}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $asset->id)
            ->assertJsonPath('data.asset_code', 'QR-AC-LG-2PK')
            ->assertJsonPath('data.name', 'AC LG Dual Inverter 2 PK')
            ->assertJsonPath('data.room.name', 'Ruang Guru Utama');
    }

    /**
     * BE-SAR-02: Scan / lookup aset mengembalikan 404 jika kode tidak terdaftar.
     */
    public function test_be_sar_02_scan_lookup_asset_not_found(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->getJson('/api/v1/assets/by-code/KODE-YANG-TIDAK-ADA');

        $response->assertStatus(404);
    }

    /**
     * BE-SAR-02: Pengguna tanpa hak akses sarpras dilarang membuat atau menghapus aset.
     */
    public function test_be_sar_02_unauthorized_user_cannot_create_or_delete_asset(): void
    {
        $asset = Asset::create([
            'asset_code' => 'AST-MEJA-01',
            'name' => 'Meja Rapat',
            'category' => 'Furnitur',
            'asset_condition' => 'baik',
        ]);

        $this->app['auth']->forgetGuards();

        // 1. Guru biasa dilarang membuat aset
        $responseCreate = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/v1/assets', [
                'asset_code' => 'AST-MEJA-02',
                'name' => 'Meja Rapat Baru',
                'category' => 'Furnitur',
            ]);

        $responseCreate->assertStatus(403);

        $this->app['auth']->forgetGuards();

        // 2. Guru biasa dilarang menghapus aset
        $responseDelete = $this->actingAs($this->teacherUser, 'sanctum')
            ->deleteJson("/api/v1/assets/{$asset->id}");

        $responseDelete->assertStatus(403);
    }

    /**
     * BE-SAR-02: Staf Sarpras dapat membuat sesi stok opname aset beserta item pemeriksaannya.
     */
    public function test_be_sar_02_create_asset_audit_session_and_items(): void
    {
        $asset1 = Asset::create([
            'asset_code' => 'AST-AUD-01',
            'name' => 'Monitor Dell 24 Inch',
            'category' => 'Elektronik',
            'room_id' => $this->labKomputer->id,
            'asset_condition' => 'baik',
        ]);

        $asset2 = Asset::create([
            'asset_code' => 'AST-AUD-02',
            'name' => 'Keyboard Mechanical',
            'category' => 'Elektronik',
            'room_id' => $this->labKomputer->id,
            'asset_condition' => 'baik',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->sarprasUser, 'sanctum')
            ->postJson('/api/v1/asset-audits', [
                'title' => 'Stok Opname Lab Komputer Semester Ganjil 2026',
                'audit_date' => Carbon::now()->toDateString(),
                'room_id' => $this->labKomputer->id,
                'status' => 'proses',
                'notes' => 'Pemeriksaan fisik tahunan',
                'items' => [
                    [
                        'asset_id' => $asset1->id,
                        'is_present' => true,
                        'found_condition' => 'baik',
                        'notes' => 'Kondisi fisik prima',
                    ],
                    [
                        'asset_id' => $asset2->id,
                        'is_present' => true,
                        'found_condition' => 'rusak_ringan',
                        'notes' => 'Tombol spasi agak macet',
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Stok Opname Lab Komputer Semester Ganjil 2026')
            ->assertJsonPath('data.status', 'proses');

        $auditId = $response->json('data.id');

        $this->assertDatabaseHas('asset_audits', [
            'id' => $auditId,
            'title' => 'Stok Opname Lab Komputer Semester Ganjil 2026',
            'auditor_id' => $this->sarprasUser->id,
            'status' => 'proses',
        ]);

        $this->assertDatabaseHas('asset_audit_items', [
            'audit_id' => $auditId,
            'asset_id' => $asset2->id,
            'found_condition' => 'rusak_ringan',
        ]);
    }

    /**
     * BE-SAR-02: Menyelesaikan sesi audit (status 'selesai') secara otomatis memperbarui kondisi master aset.
     */
    public function test_be_sar_02_complete_audit_session_syncs_asset_condition(): void
    {
        $asset = Asset::create([
            'asset_code' => 'AST-SYNC-01',
            'name' => 'Printer Epson L3210',
            'category' => 'Elektronik',
            'room_id' => $this->ruangGuru->id,
            'asset_condition' => 'baik', // Awalnya baik
        ]);

        $audit = AssetAudit::create([
            'title' => 'Audit Ruang Guru',
            'audit_date' => Carbon::now()->toDateString(),
            'room_id' => $this->ruangGuru->id,
            'auditor_id' => $this->sarprasUser->id,
            'status' => 'draf',
        ]);

        $this->app['auth']->forgetGuards();

        // Update sesi audit menjadi selesai dan laporkan bahwa printer ditemukan rusak berat
        $response = $this->actingAs($this->sarprasUser, 'sanctum')
            ->patchJson("/api/v1/asset-audits/{$audit->id}", [
                'status' => 'selesai',
                'items' => [
                    [
                        'asset_id' => $asset->id,
                        'is_present' => true,
                        'found_condition' => 'rusak_berat',
                        'notes' => 'Head printer rusak dan tinta bocor',
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'selesai');

        // Verifikasi kondisi master aset otomatis terupdate menjadi rusak_berat
        $asset->refresh();
        $this->assertEquals('rusak_berat', $asset->asset_condition);

        $this->assertDatabaseHas('asset_audit_items', [
            'audit_id' => $audit->id,
            'asset_id' => $asset->id,
            'found_condition' => 'rusak_berat',
        ]);
    }

    /**
     * BE-SAR-02: Filter daftar aset berdasarkan ruangan, kategori, kondisi, dan pencarian teks.
     */
    public function test_be_sar_02_list_assets_filtering_and_search(): void
    {
        Asset::create([
            'asset_code' => 'AST-FILT-01',
            'name' => 'Router Mikrotik RB450Gx4',
            'category' => 'Jaringan',
            'room_id' => $this->labKomputer->id,
            'asset_condition' => 'baik',
        ]);

        Asset::create([
            'asset_code' => 'AST-FILT-02',
            'name' => 'Switch D-Link 24 Port',
            'category' => 'Jaringan',
            'room_id' => $this->labKomputer->id,
            'asset_condition' => 'rusak_ringan',
        ]);

        Asset::create([
            'asset_code' => 'AST-FILT-03',
            'name' => 'Kursi Ergonomis Guru',
            'category' => 'Furnitur',
            'room_id' => $this->ruangGuru->id,
            'asset_condition' => 'baik',
        ]);

        $this->app['auth']->forgetGuards();

        // 1. Filter category Jaringan
        $respCategory = $this->actingAs($this->sarprasUser, 'sanctum')
            ->getJson('/api/v1/assets?category=Jaringan');

        $respCategory->assertStatus(200)
            ->assertJsonPath('meta.total', 2);

        // 2. Filter condition rusak_ringan
        $respCondition = $this->actingAs($this->sarprasUser, 'sanctum')
            ->getJson('/api/v1/assets?asset_condition=rusak_ringan');

        $respCondition->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.asset_code', 'AST-FILT-02');

        // 3. Search text 'Mikrotik'
        $respSearch = $this->actingAs($this->sarprasUser, 'sanctum')
            ->getJson('/api/v1/assets?search=Mikrotik');

        $respSearch->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.asset_code', 'AST-FILT-01');
    }

    /**
     * BE-SAR-02: Aksi pembuatan aset dan sesi audit mencatat log jejak audit.
     */
    public function test_be_sar_02_audit_trail_recorded(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->sarprasUser, 'sanctum')
            ->postJson('/api/v1/assets', [
                'asset_code' => 'AST-AUDIT-TEST-01',
                'name' => 'Whiteboard Magnetic',
                'category' => 'Perlengkapan Kelas',
                'asset_condition' => 'baik',
            ]);

        $response->assertStatus(201);
        $assetId = $response->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->sarprasUser->id,
            'action' => 'asset.create',
            'entity' => 'assets',
            'entity_id' => $assetId,
        ]);
    }

    /**
     * BE-SAR-02: Ringkasan statistik aset dan distribusi kondisi.
     */
    public function test_be_sar_02_get_asset_summary_metrics(): void
    {
        Asset::create([
            'asset_code' => 'AST-SUMM-01',
            'name' => 'Laptop Asus Core i7',
            'category' => 'Elektronik',
            'asset_condition' => 'baik',
        ]);

        Asset::create([
            'asset_code' => 'AST-SUMM-02',
            'name' => 'Proyektor Epson',
            'category' => 'Elektronik',
            'asset_condition' => 'rusak_ringan',
        ]);

        Asset::create([
            'asset_code' => 'AST-SUMM-03',
            'name' => 'Meja Rusak Patah',
            'category' => 'Furnitur',
            'asset_condition' => 'rusak_berat',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->sarprasUser, 'sanctum')
            ->getJson('/api/v1/assets/summary');

        $response->assertStatus(200)
            ->assertJsonPath('data.total_assets', 3)
            ->assertJsonPath('data.condition_baik', 1)
            ->assertJsonPath('data.condition_rusak_ringan', 1)
            ->assertJsonPath('data.condition_rusak_berat', 1);
    }

    /**
     * BE-SAR-02: Pengambilan data label fisik QR code untuk pencetakan.
     */
    public function test_be_sar_02_get_asset_qr_label_data(): void
    {
        $asset = Asset::create([
            'asset_code' => 'AST-QR-TEST-01',
            'name' => 'Kamera DSLR Canon',
            'category' => 'Multimedia',
            'asset_condition' => 'baik',
            'room_id' => $this->labKomputer->id,
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->sarprasUser, 'sanctum')
            ->getJson("/api/v1/assets/{$asset->id}/qr");

        $response->assertStatus(200)
            ->assertJsonPath('data.qr_code', 'AST-QR-TEST-01')
            ->assertJsonPath('data.qr_payload.code', 'AST-QR-TEST-01')
            ->assertJsonPath('data.qr_payload.name', 'Kamera DSLR Canon')
            ->assertJsonPath('data.label_title', 'INVENTARIS SEKOLAH');
    }

    /**
     * BE-SAR-02: Multi-tenant scoping mencegah akses aset antar-sekolah berbeda (anti-IDOR 403).
     */
    public function test_be_sar_02_multi_tenant_isolation_prevents_cross_school_access(): void
    {
        $schoolA = '88888888-1111-2222-3333-444444444444';
        $schoolB = '88888888-5555-6666-7777-888888888888';

        $this->sarprasUser->school_id = $schoolA;
        $this->sarprasUser->save();

        $assetSchoolB = Asset::create([
            'school_id' => $schoolB,
            'asset_code' => 'AST-SCHB-001',
            'name' => 'Aset Sekolah Sebelah',
            'category' => 'Elektronik',
            'asset_condition' => 'baik',
        ]);

        $this->app['auth']->forgetGuards();

        // 1. Dilarang melihat detail aset sekolah lain
        $respShow = $this->actingAs($this->sarprasUser, 'sanctum')
            ->getJson("/api/v1/assets/{$assetSchoolB->id}");
        $respShow->assertStatus(403);

        $this->app['auth']->forgetGuards();

        // 2. Dilarang mengubah aset sekolah lain
        $respUpdate = $this->actingAs($this->sarprasUser, 'sanctum')
            ->putJson("/api/v1/assets/{$assetSchoolB->id}", [
                'name' => 'Aset Diretas',
            ]);
        $respUpdate->assertStatus(403);

        $this->app['auth']->forgetGuards();

        // 3. Dilarang menghapus aset sekolah lain
        $respDelete = $this->actingAs($this->sarprasUser, 'sanctum')
            ->deleteJson("/api/v1/assets/{$assetSchoolB->id}");
        $respDelete->assertStatus(403);
    }

    /**
     * BE-SAR-02: Multi-tenant scoping pada sesi stok opname aset (anti-IDOR 403).
     */
    public function test_be_sar_02_multi_tenant_isolation_on_asset_audits(): void
    {
        $schoolA = '77777777-1111-2222-3333-444444444444';
        $schoolB = '77777777-5555-6666-7777-888888888888';

        $this->sarprasUser->school_id = $schoolA;
        $this->sarprasUser->save();

        $auditSchoolB = AssetAudit::create([
            'school_id' => $schoolB,
            'title' => 'Audit Sekolah B',
            'audit_date' => Carbon::now()->toDateString(),
            'auditor_id' => $this->adminUser->id,
            'status' => 'draf',
        ]);

        $this->app['auth']->forgetGuards();

        // Dilarang melihat detail audit sekolah lain
        $respShow = $this->actingAs($this->sarprasUser, 'sanctum')
            ->getJson("/api/v1/asset-audits/{$auditSchoolB->id}");
        $respShow->assertStatus(403);

        $this->app['auth']->forgetGuards();

        // Dilarang mengupdate audit sekolah lain
        $respUpdate = $this->actingAs($this->sarprasUser, 'sanctum')
            ->patchJson("/api/v1/asset-audits/{$auditSchoolB->id}", [
                'status' => 'selesai',
            ]);
        $respUpdate->assertStatus(403);
    }

    /**
     * BE-SAR-02: Detail aset mengintegrasikan riwayat tiket perbaikan helpdesk (BE-SAR-03).
     */
    public function test_be_sar_02_asset_detail_includes_ticket_maintenance_history(): void
    {
        $asset = Asset::create([
            'asset_code' => 'AST-TCK-LINK-01',
            'name' => 'Proyektor Ruang Multimedia',
            'category' => 'Elektronik',
            'asset_condition' => 'rusak_ringan',
            'room_id' => $this->labKomputer->id,
        ]);

        Ticket::create([
            'ticket_number' => 'TCK-202610-9001',
            'title' => 'Lensa Proyektor Berdebu',
            'reporter_id' => $this->teacherUser->id,
            'asset_id' => $asset->id,
            'room_id' => $this->labKomputer->id,
            'description' => 'Tampilan buram saat presentasi',
            'priority' => 'sedang',
            'status' => 'selesai',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($this->sarprasUser, 'sanctum')
            ->getJson("/api/v1/assets/{$asset->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $asset->id)
            ->assertJsonPath('data.tickets.0.ticket_number', 'TCK-202610-9001')
            ->assertJsonPath('data.tickets.0.status', 'selesai');
    }
}
