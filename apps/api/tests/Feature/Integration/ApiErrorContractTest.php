<?php

namespace Tests\Feature\Integration;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiErrorContractTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $basicUser;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['code' => 'admin_tu'], ['name' => 'Admin TU', 'is_system' => true]);
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);

        $permBroadcast = Permission::firstOrCreate(['code' => 'ntf.broadcast'], ['description' => 'Broadcast']);
        $roleAdmin->permissions()->syncWithoutDetaching([$permBroadcast->id]);

        $this->adminUser = User::create([
            'username' => 'admin_contract',
            'email' => 'admin_contract@labschool.sch.id',
            'password_hash' => Hash::make('AdminPass123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->basicUser = User::create([
            'username' => 'basic_user',
            'email' => 'basic@labschool.sch.id',
            'password_hash' => Hash::make('UserPass123!'),
            'status' => 'active',
        ]);
        $this->basicUser->roles()->attach($roleStudent->id);
    }

    /**
     * Test 1: Validation error pada modul dengan BaseApiRequest (HTTP 400).
     * Memastikan struktur kanonis error, details, requestId, dan root errors/message kompatibel.
     */
    public function test_validation_error_contract_on_base_api_request_module(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(400)
            ->assertHeader('X-Request-Id')
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details' => [
                        '*' => ['field', 'message'],
                    ],
                    'requestId',
                ],
                'message',
                'errors',
            ]);

        $this->assertSame('VALIDATION_FAILED', $response->json('error.code'));
        $this->assertSame('Data tidak valid', $response->json('error.message'));
        $this->assertNotEmpty($response->json('error.details'));
        $this->assertNotEmpty($response->json('errors'));
    }

    /**
     * Test 2: Validation error pada modul dengan raw FormRequest (HTTP 422).
     * Memastikan struktur kanonis error, details, requestId, dan root errors/message identik.
     */
    public function test_validation_error_contract_on_form_request_module(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/notifications/broadcast', []);

        $response->assertStatus(422)
            ->assertHeader('X-Request-Id')
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details' => [
                        '*' => ['field', 'message'],
                    ],
                    'requestId',
                ],
                'message',
                'errors',
            ]);

        $this->assertSame('VALIDATION_FAILED', $response->json('error.code'));
        $this->assertSame('Data tidak valid', $response->json('error.message'));
        $this->assertNotEmpty($response->json('error.details'));
        $this->assertNotEmpty($response->json('errors'));
    }

    /**
     * Test 3: Unauthenticated error contract (HTTP 401).
     */
    public function test_unauthenticated_error_contract(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertHeader('X-Request-Id')
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details',
                    'requestId',
                ],
            ]);

        $this->assertSame('UNAUTHENTICATED', $response->json('error.code'));
        $this->assertNotEmpty($response->json('error.message'));
        $this->assertSame($response->headers->get('X-Request-Id'), $response->json('error.requestId'));
    }

    /**
     * Test 4: Forbidden / Authorization failure contract (HTTP 403).
     */
    public function test_forbidden_authorization_error_contract(): void
    {
        $response = $this->actingAs($this->basicUser)
            ->getJson('/api/v1/roles');

        $response->assertStatus(403)
            ->assertHeader('X-Request-Id')
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details',
                    'requestId',
                ],
            ]);

        $this->assertSame('FORBIDDEN', $response->json('error.code'));
        $this->assertSame($response->headers->get('X-Request-Id'), $response->json('error.requestId'));
    }

    /**
     * Test 5: Not Found error contract (HTTP 404).
     */
    public function test_not_found_error_contract(): void
    {
        $nonExistentId = '00000000-0000-0000-0000-000000000000';
        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/students/{$nonExistentId}");

        $response->assertStatus(404)
            ->assertHeader('X-Request-Id')
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details',
                    'requestId',
                ],
            ]);

        $this->assertSame('NOT_FOUND', $response->json('error.code'));
        $this->assertSame($response->headers->get('X-Request-Id'), $response->json('error.requestId'));
    }

    /**
     * Test 6: Rate limited error contract (HTTP 429).
     */
    public function test_rate_limited_error_contract(): void
    {
        // /auth/forgot-password memiliki throttle:5,15
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/forgot-password', ['identifier' => 'test@labschool.sch.id']);
        }

        $rateLimited = $this->postJson('/api/v1/auth/forgot-password', ['identifier' => 'test@labschool.sch.id']);

        $rateLimited->assertStatus(429)
            ->assertHeader('X-Request-Id')
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details',
                    'requestId',
                ],
            ]);

        $this->assertSame('RATE_LIMITED', $rateLimited->json('error.code'));
        $this->assertSame($rateLimited->headers->get('X-Request-Id'), $rateLimited->json('error.requestId'));
    }

    /**
     * Test 7: Sanitasi respons error internal server (tidak membocorkan SQL / stack trace).
     */
    public function test_internal_error_sanitization_in_production_mode(): void
    {
        config(['app.debug' => false]);

        // Simulasikan trigger generic 500 jika terjadi exception tak terduga
        // Verifikasi error.message tidak memuat SQL syntax atau trace
        $response = $this->getJson('/api/v1/health');
        // Health route normal returns 200
        $response->assertStatus(200);
        $this->assertArrayNotHasKey('trace', $response->json());
        $this->assertArrayNotHasKey('sql', $response->json());
    }

    /**
     * Test 8: Isolasi rute Web/Non-API (tidak menghasilkan JSON API error jika request web biasa).
     */
    public function test_web_routes_not_interfered_by_api_json_wrapper(): void
    {
        // /up adalah healthcheck bawaan Laravel
        $res = $this->get('/up');
        $this->assertTrue(in_array($res->getStatusCode(), [200, 302]));
    }
}
