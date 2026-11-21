<?php

namespace Tests\Feature\Ntf;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user1;

    protected User $user2;

    protected function setUp(): void
    {
        parent::setUp();

        $roleStudent = Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true]);
        $roleTeacher = Role::firstOrCreate(['code' => 'teacher'], ['name' => 'Guru', 'is_system' => true]);

        $this->user1 = User::create([
            'username' => 'siswa_pref_1',
            'email' => 'siswa_pref1@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->user1->roles()->attach($roleStudent->id);

        $this->user2 = User::create([
            'username' => 'guru_pref_2',
            'email' => 'guru_pref2@sekolah.sch.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->user2->roles()->attach($roleTeacher->id);
    }

    public function test_be_ntf_04_unauthenticated_request_returns_401(): void
    {
        $resGet = $this->getJson('/api/v1/notification-preferences');
        $resGet->assertStatus(401);

        $resPut = $this->putJson('/api/v1/notification-preferences', [
            'notif_type' => 'academic_announcement',
            'channel_wa' => false,
        ]);
        $resPut->assertStatus(401);
    }

    public function test_be_ntf_04_user_can_view_default_notification_preferences(): void
    {
        Sanctum::actingAs($this->user1);

        $res = $this->getJson('/api/v1/notification-preferences');
        $res->assertStatus(200);

        $data = $res->json('data');
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        // Pastikan seluruh tipe default aktif dan tersedia
        foreach ($data as $item) {
            $this->assertArrayHasKey('notif_type', $item);
            $this->assertArrayHasKey('label', $item);
            $this->assertArrayHasKey('supported_channels', $item);
            $this->assertTrue($item['is_enabled']);
            $this->assertTrue($item['channel_push']);
            $this->assertTrue($item['channel_wa']);
            $this->assertTrue($item['channel_email']);
        }
    }

    public function test_be_ntf_04_user_can_update_single_notification_preference(): void
    {
        Sanctum::actingAs($this->user1);

        $payload = [
            'notif_type' => 'academic_announcement',
            'channel_push' => true,
            'channel_wa' => false,
            'channel_email' => false,
            'is_enabled' => true,
        ];

        $res = $this->putJson('/api/v1/notification-preferences', $payload);
        $res->assertStatus(200);

        // Verifikasi dalam database
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $this->user1->id,
            'notif_type' => 'academic_announcement',
            'channel_push' => 1,
            'channel_wa' => 0,
            'channel_email' => 0,
            'is_enabled' => 1,
        ]);

        // Verifikasi pada respons yang dikembalikan
        $target = collect($res->json('data'))->firstWhere('notif_type', 'academic_announcement');
        $this->assertNotNull($target);
        $this->assertTrue($target['channel_push']);
        $this->assertFalse($target['channel_wa']);
        $this->assertFalse($target['channel_email']);
    }

    public function test_be_ntf_04_user_can_bulk_update_notification_preferences(): void
    {
        Sanctum::actingAs($this->user1);

        $payload = [
            'preferences' => [
                [
                    'notif_type' => 'grade_report',
                    'channel_push' => true,
                    'channel_wa' => true,
                    'channel_email' => false,
                ],
                [
                    'notif_type' => 'payment_reminder',
                    'channel_push' => false,
                    'channel_wa' => true,
                    'channel_email' => true,
                ],
            ],
        ];

        $res = $this->putJson('/api/v1/notification-preferences', $payload);
        $res->assertStatus(200);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $this->user1->id,
            'notif_type' => 'grade_report',
            'channel_email' => 0,
        ]);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $this->user1->id,
            'notif_type' => 'payment_reminder',
            'channel_push' => 0,
        ]);
    }

    public function test_be_ntf_04_user_can_disable_entire_notification_type(): void
    {
        Sanctum::actingAs($this->user1);

        $payload = [
            'notif_type' => 'counseling_update',
            'is_enabled' => false,
        ];

        $res = $this->putJson('/api/v1/notification-preferences', $payload);
        $res->assertStatus(200);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $this->user1->id,
            'notif_type' => 'counseling_update',
            'is_enabled' => 0,
        ]);

        $target = collect($res->json('data'))->firstWhere('notif_type', 'counseling_update');
        $this->assertNotNull($target);
        $this->assertFalse($target['is_enabled']);
    }

    public function test_be_ntf_04_user_preferences_are_isolated_between_users(): void
    {
        // User 1 menonaktifkan WA untuk academic_announcement
        Sanctum::actingAs($this->user1);
        $this->putJson('/api/v1/notification-preferences', [
            'notif_type' => 'academic_announcement',
            'channel_wa' => false,
        ])->assertStatus(200);

        // User 2 mengecek preferensinya -> tetap default (WA = true)
        Sanctum::actingAs($this->user2);
        $resUser2 = $this->getJson('/api/v1/notification-preferences');
        $resUser2->assertStatus(200);

        $targetUser2 = collect($resUser2->json('data'))->firstWhere('notif_type', 'academic_announcement');
        $this->assertNotNull($targetUser2);
        $this->assertTrue($targetUser2['channel_wa']);

        $this->assertDatabaseMissing('notification_preferences', [
            'user_id' => $this->user2->id,
            'notif_type' => 'academic_announcement',
        ]);
    }

    public function test_be_ntf_04_get_catalog_of_notification_types_and_channels(): void
    {
        Sanctum::actingAs($this->user1);

        $res = $this->getJson('/api/v1/notification-preferences/types');
        $res->assertStatus(200);

        $data = $res->json('data');
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        $types = collect($data)->pluck('type')->toArray();
        $this->assertContains('academic_announcement', $types);
        $this->assertContains('grade_report', $types);
        $this->assertContains('attendance_alert', $types);
        $this->assertContains('payment_reminder', $types);
        $this->assertContains('counseling_update', $types);
        $this->assertContains('system_alert', $types);
    }

    public function test_be_ntf_04_validation_error_on_invalid_notification_type(): void
    {
        Sanctum::actingAs($this->user1);

        $res = $this->putJson('/api/v1/notification-preferences', [
            'notif_type' => 'invalid_notification_type',
            'channel_wa' => false,
        ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['notif_type']);
    }
}
