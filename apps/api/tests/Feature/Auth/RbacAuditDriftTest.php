<?php

namespace Tests\Feature\Auth;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RbacAuditDriftTest extends TestCase
{
    use RefreshDatabase;

    /**
     * POST-AUDIT-02: Verifikasi audit drift menghasilkan status CLEAN pada database yang baru di-seed.
     */
    public function test_audit_drift_command_reports_clean_on_fresh_seeded_database(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->artisan('rbac:audit-drift')
            ->expectsOutputToContain('SISTLABUM - Laporan Audit Permission Drift (Read-Only)')
            ->expectsOutputToContain('Integritas RBAC kanonis terpelihara dengan baik.')
            ->assertExitCode(0);
    }

    /**
     * POST-AUDIT-02: Verifikasi deteksi kebocoran izin administratif (prohibited leak) dan kegagalan status (exit code 1).
     */
    public function test_audit_drift_command_detects_prohibited_security_leaks(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Sengaja kontaminasi peran siswa dengan permission membuat bank soal CBT
        $studentRole = Role::where('code', 'student')->firstOrFail();
        $cbtBankPerm = Permission::where('code', 'cbt.bank.write')->firstOrFail();
        $studentRole->permissions()->attach($cbtBankPerm->id);

        $this->artisan('rbac:audit-drift')
            ->expectsOutputToContain('DITEMUKAN KEBOCORAN IZIN TERLARANG!')
            ->expectsOutputToContain('cbt.bank.write')
            ->assertExitCode(1);
    }

    /**
     * POST-AUDIT-02: Verifikasi pembedaan antara kustomisasi sah (custom extensions) dan kebocoran terlarang.
     */
    public function test_audit_drift_command_differentiates_custom_extensions_from_prohibited_leaks(): void
    {
        $this->seed(DatabaseSeeder::class);

        $teacherRole = Role::where('code', 'teacher')->firstOrFail();

        // Tambahkan custom permission yang sah (bukan izin berbahaya)
        $customPerm = Permission::firstOrCreate(
            ['code' => 'custom.event.view'],
            ['description' => 'Izin khusus melihat agenda']
        );
        $teacherRole->permissions()->attach($customPerm->id);

        $this->artisan('rbac:audit-drift')
            ->expectsOutputToContain('Kustomisasi Sah (Custom Extensions)')
            ->expectsOutputToContain('custom.event.view')
            ->assertExitCode(0);
    }

    /**
     * POST-AUDIT-02: Verifikasi deteksi peran tidak dikenal (unknown roles).
     */
    public function test_audit_drift_command_detects_unknown_roles(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Buat role asing
        Role::create(['code' => 'unrecognized_thirdparty_role', 'name' => 'Thirdparty Plugin']);

        $this->artisan('rbac:audit-drift')
            ->expectsOutputToContain('Peran Tidak Dikenal (Unknown Roles): unrecognized_thirdparty_role')
            ->assertExitCode(0);
    }

    /**
     * POST-AUDIT-02: Verifikasi output JSON format terstruktur dan aman tanpa kebocoran kredensial.
     */
    public function test_audit_drift_command_json_output_structure_and_safety(): void
    {
        $this->seed(DatabaseSeeder::class);

        Artisan::call('rbac:audit-drift', ['--json' => true]);
        $output = Artisan::output();

        // Validasi JSON dapat di-parse dan memiliki struktur yang benar
        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('status', $decoded);
        $this->assertArrayHasKey('summary', $decoded);
        $this->assertSame('CLEAN', $decoded['status']);

        // Validasi tidak ada password, secret, token dalam buffer output
        $this->assertStringNotContainsString('password', (string) $output);
        $this->assertStringNotContainsString('secret', (string) $output);
        $this->assertStringNotContainsString('token', (string) $output);
    }
}
