<?php

namespace Tests\Feature\Security;

use App\Models\Extracurricular;
use App\Models\Role;
use App\Models\User;
use App\Services\Rpt\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * P1-40: Perlindungan Paginasi API terhadap permintaan per_page/limit tidak wajar (OOM / DoS).
 */
class PaginationClampSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(
            ['code' => 'super_admin'],
            ['name' => 'Super Administrator', 'is_system' => true]
        );

        $this->admin = User::create([
            'username' => 'admin_p1_40',
            'email' => 'admin_p1_40@sekolah.sch.id',
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->admin->roles()->attach($role->id);
    }

    public function test_p1_40_unbounded_endpoints_clamp_per_page_between_1_and_100(): void
    {
        Sanctum::actingAs($this->admin);

        $ekskul = Extracurricular::create([
            'name' => 'Pramuka Uji Paginasi',
            'category' => 'wajib',
            'quota' => 30,
            'is_active' => true,
        ]);

        $endpoints = [
            '/api/v1/character-notes',
            '/api/v1/grades',
            '/api/v1/materials',
            '/api/v1/assignments',
            '/api/v1/question-banks',
            '/api/v1/report-description-templates',
            '/api/v1/extracurricular-members?extracurricular_id='.$ekskul->id,
            '/api/v1/counseling/cases',
            '/api/v1/counseling/referrals',
            '/api/v1/asset-audits',
            '/api/v1/facilities',
        ];

        foreach ($endpoints as $endpoint) {
            $separator = str_contains($endpoint, '?') ? '&' : '?';

            $tooLarge = $this->getJson($endpoint.$separator.'per_page=999999');
            $tooLarge->assertStatus(200)
                ->assertJsonPath('meta.limit', 100);

            $negative = $this->getJson($endpoint.$separator.'per_page=-5');
            $negative->assertStatus(200)
                ->assertJsonPath('meta.limit', 1);

            $zero = $this->getJson($endpoint.$separator.'per_page=0');
            $zero->assertStatus(200)
                ->assertJsonPath('meta.limit', 1);
        }
    }

    public function test_p1_40_report_endpoint_rejects_above_100_and_service_clamps_direct_calls(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/reports/attendance?per_page=500')
            ->assertStatus(422);

        $this->getJson('/api/v1/reports/attendance?per_page=0')
            ->assertStatus(422);

        $this->getJson('/api/v1/reports/attendance?per_page=100')
            ->assertStatus(200)
            ->assertJsonPath('data.meta.per_page', 100);

        /** @var ReportService $reportService */
        $reportService = app(ReportService::class);
        $request = Request::create('/api/v1/reports/attendance', 'GET');
        $request->setUserResolver(fn () => $this->admin);

        foreach (ReportService::VALID_TYPES as $type) {
            $high = $reportService->generateReport($type, ['per_page' => 999999], $request);
            $this->assertSame(100, $high['meta']['per_page']);

            $low = $reportService->generateReport($type, ['per_page' => -5], $request);
            $this->assertSame(1, $low['meta']['per_page']);
        }
    }

    public function test_p1_40_master_and_cms_endpoints_clamp_limit_and_per_page_safely(): void
    {
        Sanctum::actingAs($this->admin);

        $limitEndpoints = [
            '/api/v1/roles',
            '/api/v1/users',
            '/api/v1/audit-logs',
            '/api/v1/academic-years',
            '/api/v1/students',
            '/api/v1/teachers',
            '/api/v1/staff',
            '/api/v1/guardians',
            '/api/v1/classes',
            '/api/v1/subjects',
            '/api/v1/teaching-assignments',
            '/api/v1/rooms',
            '/api/v1/posts',
        ];

        foreach ($limitEndpoints as $endpoint) {
            $this->getJson($endpoint.'?limit=999999')
                ->assertStatus(200)
                ->assertJsonPath('meta.limit', 100);

            $this->getJson($endpoint.'?limit=-5')
                ->assertStatus(200)
                ->assertJsonPath('meta.limit', 1);

            $this->getJson($endpoint.'?limit=0')
                ->assertStatus(200)
                ->assertJsonPath('meta.limit', 1);
        }

        $this->getJson('/api/v1/public/posts?limit=999')
            ->assertStatus(200)
            ->assertJsonPath('meta.limit', 50);

        $this->getJson('/api/v1/public/posts?limit=-3')
            ->assertStatus(200)
            ->assertJsonPath('meta.limit', 1);
    }
}
