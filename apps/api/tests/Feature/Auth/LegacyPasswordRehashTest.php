<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LegacyPasswordRehashTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_bcrypt_user_can_login_and_is_transparently_rehashed_to_argon2id(): void
    {
        config(['hashing.driver' => 'argon2id']);

        // 1. Create a user with a native Bcrypt hash
        $legacyBcryptHash = password_hash('SecretPassword123!', PASSWORD_BCRYPT);
        $this->assertStringStartsWith('$2y$', $legacyBcryptHash);

        $user = User::create([
            'username' => 'legacyuser',
            'email' => 'legacy@labschool.sch.id',
            'password_hash' => Hash::make('temp'),
            'status' => 'active',
            'must_change_password' => false,
        ]);
        DB::table('users')->where('id', $user->id)->update(['password_hash' => $legacyBcryptHash]);

        // 2. Perform login request
        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'legacy@labschool.sch.id',
            'password' => 'SecretPassword123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.email', 'legacy@labschool.sch.id')
            ->assertJsonStructure(['data' => ['tokens' => ['accessToken', 'refreshToken']]]);

        // 3. Verify user's hash in database was automatically upgraded to Argon2id
        $user->refresh();
        $this->assertStringStartsWith('$argon2id$', $user->password_hash);
        $this->assertTrue(Hash::check('SecretPassword123!', $user->password_hash));

        // 4. Verify user can login again with the upgraded Argon2id hash
        $secondLogin = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'legacy@labschool.sch.id',
            'password' => 'SecretPassword123!',
        ]);

        $secondLogin->assertStatus(200);
    }

    public function test_invalid_password_against_legacy_bcrypt_hash_returns_401_without_crashing(): void
    {
        $legacyBcryptHash = password_hash('SecretPassword123!', PASSWORD_BCRYPT);

        $user = User::create([
            'username' => 'legacyuser2',
            'email' => 'legacy2@labschool.sch.id',
            'password_hash' => Hash::make('temp'),
            'status' => 'active',
            'must_change_password' => false,
        ]);
        DB::table('users')->where('id', $user->id)->update(['password_hash' => $legacyBcryptHash]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'legacy2@labschool.sch.id',
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }
}
