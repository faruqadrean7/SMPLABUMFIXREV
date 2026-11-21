<?php

namespace Tests\Feature\Guest;

use App\Models\File;
use App\Models\Guest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GuestSignatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $targetTeacher;

    protected User $otherTeacher;

    protected Guest $guest;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        // Setup Roles & Permissions
        $roleAdmin = Role::firstOrCreate(['code' => 'admin_tu'], ['name' => 'Admin Tata Usaha', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);

        $permRead = Permission::firstOrCreate(['code' => 'guest.book.read'], ['description' => 'Melihat buku tamu']);
        $permManage = Permission::firstOrCreate(['code' => 'guest.book.manage'], ['description' => 'Mengelola buku tamu']);

        $roleAdmin->permissions()->syncWithoutDetaching([$permRead->id, $permManage->id]);

        $this->adminUser = User::create([
            'username' => 'admintu',
            'email' => 'tu@sekolah.sch.id',
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($roleAdmin->id);

        $this->targetTeacher = User::create([
            'username' => 'gurubudi',
            'email' => 'budi@sekolah.sch.id',
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);
        $this->targetTeacher->roles()->attach($roleTeacher->id);

        $this->otherTeacher = User::create([
            'username' => 'gurusiti',
            'email' => 'siti@sekolah.sch.id',
            'password_hash' => bcrypt('password123'),
            'status' => 'active',
        ]);
        $this->otherTeacher->roles()->attach($roleTeacher->id);

        // Active guest
        $this->guest = Guest::create([
            'full_name' => 'Bapak Hendra Gunawan',
            'institution' => 'Dinas Pendidikan Kota Malang',
            'phone' => '081234567890',
            'purpose' => 'Koordinasi Pengawasan Sekolah Ramah Anak',
            'target_user_id' => $this->targetTeacher->id,
            'check_in' => Carbon::now()->subMinutes(30),
        ]);
    }

    public function test_be_guest_03_public_can_submit_canvas_base64_signature_with_sha256_hash(): void
    {
        // 1x1 transparent PNG encoded in base64
        $pngBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
        $rawBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
        $expectedHash = hash('sha256', $rawBytes);

        $response = $this->postJson("/api/v1/public/guests/{$this->guest->id}/signature", [
            'signature_data' => $pngBase64,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.guest_id', $this->guest->id)
            ->assertJsonPath('data.signature_hash', $expectedHash)
            ->assertJsonPath('data.integrity_verified', true);

        // Verify database guest update
        $this->guest->refresh();
        $this->assertNotNull($this->guest->signature_file_id);
        $this->assertEquals($expectedHash, $this->guest->signature_hash);

        // Verify File table
        $file = File::find($this->guest->signature_file_id);
        $this->assertNotNull($file);
        $this->assertEquals($expectedHash, $file->checksum);
        $this->assertEquals('image/png', $file->mime_type);
        $this->assertEquals(strlen($rawBytes), $file->size_bytes);

        // Verify storage file exists on disk and has identical content
        $this->assertTrue(Storage::disk('local')->exists($file->object_key));
        $this->assertEquals($rawBytes, Storage::disk('local')->get($file->object_key));

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'guest.signature_attached',
            'entity' => 'Guest',
            'entity_id' => $this->guest->id,
        ]);
    }

    public function test_be_guest_03_public_can_submit_uploaded_image_file_signature(): void
    {
        $fakeImage = UploadedFile::fake()->createWithContent(
            'signature_pad.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==')
        );
        $fileBytes = file_get_contents($fakeImage->getRealPath());
        $expectedHash = hash('sha256', $fileBytes);

        $response = $this->postJson("/api/v1/public/guests/{$this->guest->id}/signature", [
            'signature_file' => $fakeImage,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.guest_id', $this->guest->id)
            ->assertJsonPath('data.signature_hash', $expectedHash);

        $this->guest->refresh();
        $this->assertNotNull($this->guest->signature_file_id);
        $this->assertEquals($expectedHash, $this->guest->signature_hash);
    }

    public function test_be_guest_03_rejects_signature_for_non_existent_guest(): void
    {
        $dummyId = '00000000-0000-0000-0000-000000000000';
        $pngBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

        $response = $this->postJson("/api/v1/public/guests/{$dummyId}/signature", [
            'signature_data' => $pngBase64,
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_be_guest_03_rejects_signature_if_guest_already_checked_out(): void
    {
        $this->guest->update([
            'check_out' => Carbon::now(),
        ]);

        $pngBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

        $response = $this->postJson("/api/v1/public/guests/{$this->guest->id}/signature", [
            'signature_data' => $pngBase64,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'UNPROCESSABLE_ENTITY');
    }

    public function test_be_guest_03_rejects_tamper_attempt_to_overwrite_existing_signature(): void
    {
        $pngBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

        // First signature succeeds
        $first = $this->postJson("/api/v1/public/guests/{$this->guest->id}/signature", [
            'signature_data' => $pngBase64,
        ]);
        $first->assertStatus(201);

        // Attempting to overwrite existing signature fails
        $second = $this->postJson("/api/v1/public/guests/{$this->guest->id}/signature", [
            'signature_data' => $pngBase64,
        ]);
        $second->assertStatus(422)
            ->assertJsonPath('error.code', 'UNPROCESSABLE_ENTITY');
    }

    public function test_be_guest_03_rejects_invalid_signature_payload(): void
    {
        // Neither file nor base64 provided: validation failure -> 400 VALIDATION_FAILED
        $response = $this->postJson("/api/v1/public/guests/{$this->guest->id}/signature", []);
        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // Corrupted base64: decoded empty -> 422 UNPROCESSABLE_ENTITY
        $response2 = $this->postJson("/api/v1/public/guests/{$this->guest->id}/signature", [
            'signature_data' => 'not-a-valid-base64-string',
        ]);
        $response2->assertStatus(422)
            ->assertJsonPath('error.code', 'UNPROCESSABLE_ENTITY');
    }

    public function test_be_guest_03_staff_with_permission_can_view_signature_and_verify_integrity(): void
    {
        $pngBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
        $this->postJson("/api/v1/public/guests/{$this->guest->id}/signature", [
            'signature_data' => $pngBase64,
        ]);

        $token = $this->adminUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/guests/{$this->guest->id}/signature");

        $response->assertStatus(200)
            ->assertJsonPath('data.guest_id', $this->guest->id)
            ->assertJsonPath('data.full_name', $this->guest->full_name)
            ->assertJsonPath('data.integrity_verified', true);
    }

    public function test_be_guest_03_target_user_can_view_signature_of_their_guest(): void
    {
        $pngBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
        $this->postJson("/api/v1/public/guests/{$this->guest->id}/signature", [
            'signature_data' => $pngBase64,
        ]);

        $token = $this->targetTeacher->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/guests/{$this->guest->id}/signature");

        $response->assertStatus(200)
            ->assertJsonPath('data.guest_id', $this->guest->id);
    }

    public function test_be_guest_03_other_unauthorized_user_cannot_view_signature_403(): void
    {
        $pngBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
        $this->postJson("/api/v1/public/guests/{$this->guest->id}/signature", [
            'signature_data' => $pngBase64,
        ]);

        $token = $this->otherTeacher->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/guests/{$this->guest->id}/signature");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_be_guest_03_unauthenticated_request_cannot_view_signature_401(): void
    {
        $response = $this->getJson("/api/v1/guests/{$this->guest->id}/signature");

        $response->assertStatus(401);
    }

    public function test_be_guest_03_signature_not_found_returns_404(): void
    {
        $token = $this->adminUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/guests/{$this->guest->id}/signature");

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }
}
