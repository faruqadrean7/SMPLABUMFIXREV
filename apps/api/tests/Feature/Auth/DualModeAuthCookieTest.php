<?php

namespace Tests\Feature\Auth;

use App\Models\RefreshToken;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DualModeAuthCookieTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithRole(string $roleCode, string $username): User
    {
        $role = Role::firstOrCreate(['code' => $roleCode], ['name' => ucfirst($roleCode), 'is_system' => true]);
        $user = User::create([
            'username' => $username,
            'email' => "{$username}@test.local",
            'password_hash' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_p1_01_web_cookie_transport_hides_refresh_token_from_json_and_rotates_via_httponly_cookie(): void
    {
        $user = $this->createUserWithRole('student', 'siswa_web_cookie');

        // 1. Login Web mengirim X-Auth-Token-Transport: cookie
        $login = $this->withHeader('X-Auth-Token-Transport', 'cookie')
            ->postJson('/api/v1/auth/login', [
                'identifier' => $user->username,
                'password' => 'Password123!',
                'platform' => 'web',
            ]);

        $login->assertStatus(200);
        $this->assertNotEmpty($login->json('data.tokens.accessToken'));
        $this->assertNull($login->json('data.tokens.refreshToken'), 'Refresh token tidak boleh diekspos ke JS body saat mode cookie.');

        $cookie = collect($login->headers->getCookies())
            ->first(fn ($c) => $c->getName() === 'sistlabum_refresh_token');

        $this->assertNotNull($cookie, 'Cookie HttpOnly sistlabum_refresh_token wajib diterbitkan.');
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('/api/v1/auth', $cookie->getPath());
        $this->assertSame('lax', strtolower((string) $cookie->getSameSite()));
        $firstRefreshValue = $cookie->getValue();
        $this->assertNotEmpty($firstRefreshValue);

        // 2. Refresh tanpa body JSON, hanya membawa cookie HttpOnly
        $refresh = $this->withCredentials()
            ->withHeader('X-Auth-Token-Transport', 'cookie')
            ->withUnencryptedCookie('sistlabum_refresh_token', $firstRefreshValue)
            ->postJson('/api/v1/auth/refresh', []);

        $refresh->assertStatus(200);
        $this->assertNotEmpty($refresh->json('data.tokens.accessToken'));
        $this->assertNull($refresh->json('data.tokens.refreshToken'));

        $rotatedCookie = collect($refresh->headers->getCookies())
            ->first(fn ($c) => $c->getName() === 'sistlabum_refresh_token');
        $this->assertNotNull($rotatedCookie);
        $this->assertNotSame($firstRefreshValue, $rotatedCookie->getValue());

        // 3. Logout menggunakan cookie HttpOnly mencabut refresh token & menghapus cookie
        $accessToken = $refresh->json('data.tokens.accessToken');
        $logout = $this->withCredentials()
            ->withHeader('Authorization', 'Bearer '.$accessToken)
            ->withHeader('X-Auth-Token-Transport', 'cookie')
            ->withUnencryptedCookie('sistlabum_refresh_token', $rotatedCookie->getValue())
            ->postJson('/api/v1/auth/logout', []);

        $logout->assertStatus(200);
        $this->assertSame(
            0,
            RefreshToken::where('user_id', $user->id)->whereNull('revoked_at')->count()
        );

        $clearedCookie = collect($logout->headers->getCookies())
            ->first(fn ($c) => $c->getName() === 'sistlabum_refresh_token');
        $this->assertNotNull($clearedCookie);
        $this->assertLessThanOrEqual(time(), $clearedCookie->getExpiresTime());
    }

    public function test_p1_01_admin_web_cookie_transport_sets_httponly_cookie_without_json_refresh_token(): void
    {
        $admin = $this->createUserWithRole('admin_tu', 'admin_web_cookie');

        $login = $this->withHeader('X-Auth-Token-Transport', 'cookie')
            ->postJson('/api/v1/auth/admin/login', [
                'identifier' => $admin->username,
                'password' => 'Password123!',
            ]);

        $login->assertStatus(200);
        $this->assertNotEmpty($login->json('data.tokens.accessToken'));
        $this->assertNull($login->json('data.tokens.refreshToken'));

        $cookie = collect($login->headers->getCookies())
            ->first(fn ($c) => $c->getName() === 'sistlabum_refresh_token');
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
    }

    public function test_p1_01_android_mobile_client_still_receives_json_refresh_token_and_rotates_via_body(): void
    {
        $student = $this->createUserWithRole('student', 'siswa_android_dual');

        // Klien Android tidak mengirim header X-Auth-Token-Transport: cookie
        $login = $this->postJson('/api/v1/auth/login', [
            'identifier' => $student->username,
            'password' => 'Password123!',
            'platform' => 'android',
        ]);

        $login->assertStatus(200);
        $refreshToken = $login->json('data.tokens.refreshToken');
        $this->assertNotEmpty($refreshToken, 'Klien Android wajib tetap menerima refreshToken di payload JSON.');

        $refresh = $this->postJson('/api/v1/auth/refresh', [
            'refreshToken' => $refreshToken,
        ]);

        $refresh->assertStatus(200);
        $this->assertNotEmpty($refresh->json('data.tokens.refreshToken'));
        $this->assertNotSame($refreshToken, $refresh->json('data.tokens.refreshToken'));
    }
}
