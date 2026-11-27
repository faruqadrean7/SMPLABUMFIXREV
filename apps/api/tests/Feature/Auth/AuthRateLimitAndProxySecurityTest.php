<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\BypassThrottle;
use App\Models\RefreshToken;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;

class AuthRateLimitAndProxySecurityTest extends TestCase
{
    use RefreshDatabase;

    private function createStudent(string $username): User
    {
        $role = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $user = User::create([
            'username' => $username,
            'email' => "{$username}@test.local",
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_p1_04_refresh_rotation_is_atomic_and_reuse_revokes_all_sessions_persistently(): void
    {
        $user = $this->createStudent('siswa_atomic_refresh');

        $login = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->username,
            'password' => 'Password123!',
            'platform' => 'android',
        ])->assertStatus(200);

        $firstRefresh = $login->json('data.tokens.refreshToken');

        // Rotasi pertama berhasil
        $rot1 = $this->postJson('/api/v1/auth/refresh', [
            'refreshToken' => $firstRefresh,
        ])->assertStatus(200);

        $secondRefresh = $rot1->json('data.tokens.refreshToken');
        $this->assertNotSame($firstRefresh, $secondRefresh);

        // Reuse token lama harus ditolak 401 DAN mencabut permanen semua refresh & access token user
        $this->postJson('/api/v1/auth/refresh', [
            'refreshToken' => $firstRefresh,
        ])->assertStatus(401);

        $this->assertSame(
            0,
            RefreshToken::where('user_id', $user->id)->whereNull('revoked_at')->count()
        );
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'auth.refresh_token_reuse_detected',
        ]);

        // Refresh token kedua yang tadinya sah kini juga ikut dicabut
        $this->postJson('/api/v1/auth/refresh', [
            'refreshToken' => $secondRefresh,
        ])->assertStatus(401);
    }

    public function test_p1_04_password_spraying_across_accounts_from_single_ip_triggers_429(): void
    {
        $attackerIp = '198.51.100.42';
        RateLimiter::clear('login_ip_attempts:'.$attackerIp);

        for ($i = 1; $i <= 20; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => $attackerIp])
                ->postJson('/api/v1/auth/login', [
                    'identifier' => "spray_user_{$i}@test.local",
                    'password' => 'WrongPass123!',
                ])
                ->assertStatus(401);
        }

        // Percobaan ke-21 dari IP yang sama (bahkan ke akun baru) wajib diblokir 429
        $this->withServerVariables(['REMOTE_ADDR' => $attackerIp])
            ->postJson('/api/v1/auth/login', [
                'identifier' => 'brand_new_victim@test.local',
                'password' => 'WrongPass123!',
            ])
            ->assertStatus(429);

        RateLimiter::clear('login_ip_attempts:'.$attackerIp);
    }

    public function test_p1_05_untrusted_remote_ip_cannot_spoof_x_forwarded_for(): void
    {
        $untrustedClientIp = '203.0.113.9';
        $spoofedIp = '10.0.0.99';

        $user = $this->createStudent('siswa_xff_test');

        $this->withServerVariables([
            'REMOTE_ADDR' => $untrustedClientIp,
            'HTTP_X_FORWARDED_FOR' => $spoofedIp,
        ])->postJson('/api/v1/auth/login', [
            'identifier' => $user->username,
            'password' => 'Password123!',
        ])->assertStatus(200);

        // Audit log harus mencatat REMOTE_ADDR asli, bukan header X-Forwarded-For palsu
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'auth.login',
            'ip' => $untrustedClientIp,
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'auth.login',
            'ip' => $spoofedIp,
        ]);
    }

    public function test_p1_05_bypass_throttle_enforces_real_throttling_outside_testing_env(): void
    {
        $middleware = new BypassThrottle;
        $request = Request::create('/api/v1/auth/refresh', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.77']);
        $route = new Route('POST', '/api/v1/auth/refresh', fn () => response()->json(['ok' => true]));
        $request->setRouteResolver(fn () => $route);

        $originalEnv = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            // Limit 1 request per minute -> request 1 lolos, request 2 wajib kena 429
            $resp1 = $middleware->handle($request, fn () => response()->json(['ok' => true]), 1, 1);
            $this->assertSame(200, $resp1->getStatusCode());

            $this->expectException(TooManyRequestsHttpException::class);
            $middleware->handle($request, fn () => response()->json(['ok' => true]), 1, 1);
        } finally {
            $this->app['env'] = $originalEnv;
        }
    }
}
