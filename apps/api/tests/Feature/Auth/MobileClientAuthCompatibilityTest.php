<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MobileClientAuthCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $studentUser;

    protected User $teacherUser;

    protected User $guardianUser;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Roles
        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa']);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru']);
        $roleGuardian = Role::firstOrCreate(['code' => 'guardian'], ['name' => 'Wali']);
        $roleAdmin = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Admin']);

        // 2. Student User & Profile
        $this->studentUser = User::create([
            'username' => 'mobile_student',
            'email' => 'student@mobile.test',
            'password_hash' => Hash::make('MobilePass123!'),
            'status' => 'active',
        ]);
        $this->studentUser->roles()->attach($roleStudent->id);
        Student::create([
            'user_id' => $this->studentUser->id,
            'nis' => '88001',
            'nisn' => '0088001',
            'nik_enc' => 'enc_88001',
            'full_name' => 'Siswa Mobile Test',
            'gender' => 'L',
            'birth_place' => 'Malang',
            'birth_date' => '2012-01-01',
            'status' => 'aktif',
            'entry_date' => '2025-07-15',
        ]);

        // 3. Teacher User & Profile
        $this->teacherUser = User::create([
            'username' => 'mobile_teacher',
            'email' => 'teacher@mobile.test',
            'password_hash' => Hash::make('MobilePass123!'),
            'status' => 'active',
        ]);
        $this->teacherUser->roles()->attach($roleTeacher->id);
        Teacher::create([
            'user_id' => $this->teacherUser->id,
            'nip' => '198501012010011001',
            'full_name' => 'Guru Mobile Test',
            'position' => 'Guru Matematika',
            'status' => 'tetap',
        ]);

        // 4. Guardian User
        $this->guardianUser = User::create([
            'username' => 'mobile_guardian',
            'email' => 'guardian@mobile.test',
            'password_hash' => Hash::make('MobilePass123!'),
            'status' => 'active',
        ]);
        $this->guardianUser->roles()->attach($roleGuardian->id);

        // 5. Admin User
        $this->adminUser = User::create([
            'username' => 'mobile_admin_attempt',
            'email' => 'admin@mobile.test',
            'password_hash' => Hash::make('MobilePass123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);
    }

    /**
     * BATCH 5B - A.7: Endpoint login civitas mobile berfungsi normal untuk siswa, guru, dan ortu.
     */
    public function test_batch5b_mobile_auth_login_succeeds_for_civitas_roles(): void
    {
        $civitasAccounts = [
            'student' => $this->studentUser,
            'teacher' => $this->teacherUser,
            'guardian' => $this->guardianUser,
        ];

        foreach ($civitasAccounts as $persona => $user) {
            $this->app['auth']->forgetGuards();
            $response = $this->postJson('/api/v1/auth/login', [
                'identifier' => $user->username,
                'password' => 'MobilePass123!',
                'platform' => 'android',
                'device_name' => 'Android Pixel 8',
            ]);

            $response->assertStatus(200);
            $response->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'username', 'email', 'roles'],
                    'tokens' => ['accessToken', 'tokenType', 'expiresIn', 'refreshToken'],
                ],
            ]);

            $token = $response->json('data.tokens.accessToken');
            $refreshToken = $response->json('data.tokens.refreshToken');
            $this->assertNotEmpty($token);
            $this->assertNotEmpty($refreshToken);

            // Verifikasi GET /api/v1/auth/me memakai token tersebut
            $meResponse = $this->withHeader('Authorization', "Bearer {$token}")
                ->getJson('/api/v1/auth/me');
            $meResponse->assertStatus(200);
            $meResponse->assertJsonPath('data.user.username', $user->username);

            // Verifikasi POST /api/v1/auth/refresh
            $refreshResponse = $this->postJson('/api/v1/auth/refresh', [
                'refreshToken' => $refreshToken,
            ]);
            $refreshResponse->assertStatus(200);
            $refreshResponse->assertJsonStructure([
                'data' => [
                    'tokens' => ['accessToken', 'tokenType', 'expiresIn', 'refreshToken'],
                ],
            ]);

            // Verifikasi POST /api/v1/auth/logout
            $logoutResponse = $this->withHeader('Authorization', "Bearer {$token}")
                ->postJson('/api/v1/auth/logout', [
                    'refreshToken' => $refreshToken,
                ]);
            $logoutResponse->assertStatus(200);
        }
    }

    /**
     * BATCH 5B - A.7: Akun shell admin ditolak di endpoint civitas/mobile (401 generic).
     */
    public function test_batch5b_mobile_auth_strictly_rejects_admin_shell_accounts(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'mobile_admin_attempt',
            'password' => 'MobilePass123!',
            'platform' => 'android',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'error' => [
                'code' => 'UNAUTHENTICATED',
                'message' => 'Kredensial yang diberikan tidak cocok dengan data kami.',
            ],
        ]);
    }
}
