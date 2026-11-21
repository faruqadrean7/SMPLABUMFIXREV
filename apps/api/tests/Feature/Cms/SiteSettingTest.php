<?php

namespace Tests\Feature\Cms;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SiteSettingTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected Role $adminRole;

    protected Role $guestRole;

    protected Permission $permManageSite;

    protected function setUp(): void
    {
        parent::setUp();

        // Permissions
        $this->permManageSite = Permission::create([
            'code' => 'cms.site.manage',
            'description' => 'Mengelola pengaturan situs dan profil sekolah',
        ]);

        // Roles
        $this->adminRole = Role::create([
            'code' => 'super_admin',
            'name' => 'Super Administrator',
            'is_system' => true,
        ]);
        $this->adminRole->permissions()->attach([$this->permManageSite->id]);

        $this->guestRole = Role::create([
            'code' => 'guest',
            'name' => 'Tamu',
            'is_system' => false,
        ]);

        // Users
        $this->adminUser = User::create([
            'username' => 'admin_humas',
            'email' => 'humas@labschool.sch.id',
            'password_hash' => Hash::make('AdminSecret123!'),
            'status' => 'active',
        ]);
        $this->adminUser->roles()->attach($this->adminRole->id);

        $this->regularUser = User::create([
            'username' => 'guest_user',
            'email' => 'guest@labschool.sch.id',
            'password_hash' => Hash::make('GuestSecret123!'),
            'status' => 'active',
        ]);
        $this->regularUser->roles()->attach($this->guestRole->id);
    }

    /**
     * BE-CMS-01: Public can get site profile without authentication
     */
    public function test_be_cms_01_public_can_get_site_profile_without_auth(): void
    {
        $response = $this->getJson('/api/v1/public/site');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'identity' => [
                        'name',
                        'tagline',
                        'logo_url',
                        'favicon_url',
                    ],
                    'contact' => [
                        'phone',
                        'email',
                        'whatsapp',
                    ],
                    'address' => [
                        'street',
                        'city',
                        'postal_code',
                        'latitude',
                        'longitude',
                        'maps_embed_url',
                    ],
                    'social_media' => [
                        'instagram',
                        'youtube',
                        'facebook',
                        'twitter',
                        'tiktok',
                    ],
                    'profile' => [
                        'vision',
                        'mission',
                        'principal_greeting' => [
                            'principal_name',
                            'principal_photo_url',
                            'greeting_text',
                        ],
                        'accreditation' => [
                            'grade',
                            'score',
                            'certificate_no',
                            'valid_until',
                        ],
                        'featured_programs',
                    ],
                ],
            ])
            ->assertJsonPath('data.identity.name', 'SMP Laboratorium UM')
            ->assertJsonPath('data.identity.tagline', 'Beradab, Kreatif, Berprestasi')
            ->assertJsonPath('data.contact.phone', '(0341) 588550')
            ->assertJsonPath('data.contact.email', 'info@smplab-um.sch.id')
            ->assertJsonPath('data.address.street', 'Jl. Simpang Bogor No. T-7');
    }

    /**
     * BE-CMS-01: Unauthenticated user cannot access or update admin site settings (401)
     */
    public function test_be_cms_01_unauthenticated_user_cannot_access_or_update_admin_site_settings(): void
    {
        $this->getJson('/api/v1/site-settings')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->putJson('/api/v1/site-settings', [
            'identity' => ['name' => 'Nama Baru'],
        ])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    /**
     * BE-CMS-01: User without permission cannot access or update site settings (403)
     */
    public function test_be_cms_01_user_without_permission_cannot_access_or_update_site_settings(): void
    {
        $this->actingAs($this->regularUser)
            ->getJson('/api/v1/site-settings')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($this->regularUser)
            ->putJson('/api/v1/site-settings', [
                'identity' => ['name' => 'Perubahan Ilegal'],
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * BE-CMS-01: Validation fails on invalid URLs, emails, or coordinates (400 VALIDATION_FAILED)
     */
    public function test_be_cms_01_validation_fails_on_invalid_urls_or_emails(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->putJson('/api/v1/site-settings', [
                'identity' => [
                    'logo_url' => 'not-a-valid-url',
                ],
                'contact' => [
                    'email' => 'invalid-email-address',
                ],
                'address' => [
                    'latitude' => 150.00, // Invalid: must be between -90 and 90
                ],
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /**
     * BE-CMS-01: Admin can update site settings and logs audit
     */
    public function test_be_cms_01_admin_can_update_site_settings_and_logs_audit(): void
    {
        $payload = [
            'identity' => [
                'name' => 'SMA Labschool Universitas Negeri Malang',
                'tagline' => 'Sekolah Berwawasan Global dan Berkarakter Juara',
                'logo_url' => 'https://cdn.labschool.sch.id/logo.png',
                'favicon_url' => 'https://cdn.labschool.sch.id/favicon.ico',
            ],
            'contact' => [
                'phone' => '(0341) 551999',
                'email' => 'sekretariat@labschool.sch.id',
                'whatsapp' => '081299998888',
            ],
            'address' => [
                'street' => 'Jl. Semarang No. 5',
                'city' => 'Kota Malang',
                'postal_code' => '65145',
                'latitude' => -7.9615,
                'longitude' => 112.6180,
                'maps_embed_url' => 'https://maps.google.com/embed?pb=xxx',
            ],
            'social_media' => [
                'instagram' => 'https://instagram.com/labschool_um',
                'youtube' => 'https://youtube.com/@labschool_um',
                'facebook' => 'https://facebook.com/labschoolum',
            ],
            'profile' => [
                'vision' => 'Visi Baru: Menjadi pelopor pendidikan sains masa depan.',
                'mission' => [
                    'Misi 1: Pendidikan inovatif berbasis riset.',
                    'Misi 2: Penguatan karakter berintegritas.',
                ],
                'principal_greeting' => [
                    'principal_name' => 'Prof. Dr. Supriyanto, M.Pd.',
                    'principal_photo_url' => 'https://cdn.labschool.sch.id/kepsek.jpg',
                    'greeting_text' => 'Salam hangat dan selamat datang di portal kami.',
                ],
                'accreditation' => [
                    'grade' => 'A',
                    'score' => 98,
                    'certificate_no' => 'BAN-S/M-99999/2024',
                    'valid_until' => '2029-12-31',
                ],
                'featured_programs' => [
                    [
                        'title' => 'Program Kelas Internasional (Cambridge)',
                        'description' => 'Kurikulum terakreditasi internasional.',
                        'icon' => 'globe',
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->putJson('/api/v1/site-settings', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.identity.name', 'SMA Labschool Universitas Negeri Malang')
            ->assertJsonPath('data.contact.email', 'sekretariat@labschool.sch.id')
            ->assertJsonPath('data.address.street', 'Jl. Semarang No. 5')
            ->assertJsonPath('data.profile.accreditation.score', 98);

        // Verify stored in DB
        $this->assertDatabaseHas('site_settings', [
            'setting_key' => 'identity',
        ]);
        $this->assertDatabaseHas('site_settings', [
            'setting_key' => 'profile',
        ]);

        // Audit Log
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminUser->id,
            'action' => 'site_settings.updated',
            'entity' => 'SiteSetting',
        ]);
    }

    /**
     * BE-CMS-01: Public site profile reflects updated settings
     */
    public function test_be_cms_01_public_site_reflects_updated_settings(): void
    {
        // 1. Update identity
        $this->actingAs($this->adminUser)
            ->putJson('/api/v1/site-settings', [
                'identity' => [
                    'name' => 'Portal Resmi Labschool UM Modern',
                ],
            ])
            ->assertStatus(200);

        // 2. Fetch public endpoint
        $publicResp = $this->getJson('/api/v1/public/site');

        $publicResp->assertStatus(200)
            ->assertJsonPath('data.identity.name', 'Portal Resmi Labschool UM Modern');
    }

    /**
     * BE-CMS-01: Admin can update partial sections without wiping other settings
     */
    public function test_be_cms_01_admin_can_update_partial_sections(): void
    {
        // 1. Initial full update
        $this->actingAs($this->adminUser)
            ->putJson('/api/v1/site-settings', [
                'identity' => [
                    'name' => 'SMA Labschool Awal',
                ],
                'contact' => [
                    'phone' => '0811111111',
                ],
            ])
            ->assertStatus(200);

        // 2. Update only contact
        $this->actingAs($this->adminUser)
            ->putJson('/api/v1/site-settings', [
                'contact' => [
                    'phone' => '0822222222',
                ],
            ])
            ->assertStatus(200);

        // 3. Verify identity was preserved
        $getResp = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/site-settings');

        $getResp->assertStatus(200)
            ->assertJsonPath('data.identity.name', 'SMA Labschool Awal')
            ->assertJsonPath('data.contact.phone', '0822222222');
    }

    /**
     * INT-06: Verified defaults ensure SMP Laboratorium UM identity without any SMA Labschool trace.
     */
    public function test_int_06_default_site_profile_uses_smp_laboratorium_um_not_sma_labschool(): void
    {
        $response = $this->getJson('/api/v1/public/site');

        $response->assertStatus(200)
            ->assertJsonPath('data.identity.name', 'SMP Laboratorium UM')
            ->assertJsonPath('data.identity.tagline', 'Beradab, Kreatif, Berprestasi')
            ->assertJsonPath('data.contact.phone', '(0341) 588550')
            ->assertJsonPath('data.contact.email', 'info@smplab-um.sch.id')
            ->assertJsonPath('data.contact.whatsapp', '0851-2608-8473')
            ->assertJsonPath('data.address.street', 'Jl. Simpang Bogor No. T-7')
            ->assertJsonPath('data.address.city', 'Kota Malang')
            ->assertJsonPath('data.address.postal_code', '65145')
            ->assertJsonPath('data.profile.vision', 'Mewujudkan Generasi Beriman, Berkarakter, Berprestasi, dan Berbudaya')
            ->assertJsonPath('data.profile.principal_greeting.principal_name', 'Bapak Tamyis Suliantoro, M.Pd.')
            ->assertJsonPath('data.profile.principal_greeting.greeting_text', 'Selamat datang di Portal Resmi Portal Sekolah Satu Pintu SMP Laboratorium UM.');

        // Negative assertion: no mention of SMA Labschool anywhere in identity or greeting
        $jsonString = $response->getContent();
        $this->assertStringNotContainsString('SMA Labschool', $jsonString);
    }

    /**
     * INT-06: Stored administrator settings take precedence over default fallbacks without being overwritten.
     */
    public function test_int_06_stored_admin_settings_take_precedence_over_defaults(): void
    {
        // 1. Admin saves custom site identity
        $this->actingAs($this->adminUser)
            ->putJson('/api/v1/site-settings', [
                'identity' => [
                    'name' => 'SMP Laboratorium Model Inovasi UM',
                    'tagline' => 'Sekolah Inovasi Riset Unggulan',
                ],
                'contact' => [
                    'phone' => '(0341) 588999',
                ],
            ])
            ->assertStatus(200);

        // 2. Fetch public site profile
        $response = $this->getJson('/api/v1/public/site');

        $response->assertStatus(200)
            // Custom admin values must be used
            ->assertJsonPath('data.identity.name', 'SMP Laboratorium Model Inovasi UM')
            ->assertJsonPath('data.identity.tagline', 'Sekolah Inovasi Riset Unggulan')
            ->assertJsonPath('data.contact.phone', '(0341) 588999')
            // Unset fields must still fall back gracefully to verified SMP Laboratorium defaults
            ->assertJsonPath('data.contact.email', 'info@smplab-um.sch.id')
            ->assertJsonPath('data.address.street', 'Jl. Simpang Bogor No. T-7')
            ->assertJsonPath('data.profile.principal_greeting.principal_name', 'Bapak Tamyis Suliantoro, M.Pd.');
    }

    /**
     * INT-06-FIX: Null or empty string on required invariant fields gracefully falls back to default.
     * Optional fields remain null without forced defaults.
     * Numeric zero (0 or 0.0) is preserved as a valid numeric value.
     */
    public function test_int_06_fix_null_and_empty_string_fallbacks_and_zero_preservation(): void
    {
        // 1. Admin deliberately saves null or empty string on invariant fields, null on optional fields, and 0 on numeric fields
        $this->actingAs($this->adminUser)
            ->putJson('/api/v1/site-settings', [
                'identity' => [
                    'name' => '', // Empty string on name
                    'tagline' => '   ', // Whitespace string
                    'logo_url' => null, // Optional field
                ],
                'contact' => [
                    'phone' => '', // Empty string on phone
                    'email' => null, // Null on email
                ],
                'address' => [
                    'street' => '', // Empty string on street
                    'latitude' => 0.0, // Valid equator latitude (numeric zero)
                    'longitude' => 0.0, // Valid prime meridian longitude (numeric zero)
                ],
                'social_media' => [
                    'instagram' => null, // Optional field
                    'youtube' => '', // Empty string on optional field
                ],
                'profile' => [
                    'accreditation' => [
                        'score' => 0, // Valid score 0 (numeric zero)
                    ],
                ],
            ])
            ->assertStatus(200);

        // 2. Fetch public site profile
        $response = $this->getJson('/api/v1/public/site');

        $response->assertStatus(200)
            // Invariant fields must fall back to verified SMP Laboratorium UM defaults
            ->assertJsonPath('data.identity.name', 'SMP Laboratorium UM')
            ->assertJsonPath('data.identity.tagline', 'Beradab, Kreatif, Berprestasi')
            ->assertJsonPath('data.contact.phone', '(0341) 588550')
            ->assertJsonPath('data.contact.email', 'info@smplab-um.sch.id')
            ->assertJsonPath('data.address.street', 'Jl. Simpang Bogor No. T-7')
            // Numeric 0 must be preserved, not overwritten by default coordinates or scores
            ->assertJsonPath('data.address.latitude', 0)
            ->assertJsonPath('data.address.longitude', 0)
            ->assertJsonPath('data.profile.accreditation.score', 0)
            // Optional fields remain null
            ->assertJsonPath('data.identity.logo_url', null)
            ->assertJsonPath('data.social_media.instagram', null)
            ->assertJsonPath('data.social_media.youtube', null);
    }

    /**
     * INT-06-FIX: Empty arrays fallback to default list, while custom non-empty arrays are strictly preserved.
     */
    public function test_int_06_fix_empty_arrays_vs_non_empty_arrays(): void
    {
        // 1. Admin saves an empty array for mission, but non-empty for featured_programs
        $customProgram = [
            'title' => 'Program Robotik Eksklusif',
            'description' => 'Eksplorasi AI dan robotika.',
            'icon' => 'cpu',
        ];

        $this->actingAs($this->adminUser)
            ->putJson('/api/v1/site-settings', [
                'profile' => [
                    'mission' => [], // Empty array
                    'featured_programs' => [$customProgram], // Non-empty custom array
                ],
            ])
            ->assertStatus(200);

        $response = $this->getJson('/api/v1/public/site');

        $response->assertStatus(200);
        // Empty mission must fall back to 3 default mission items
        $this->assertCount(3, $response->json('data.profile.mission'));
        // Custom featured programs must be preserved with exactly 1 item
        $this->assertCount(1, $response->json('data.profile.featured_programs'));
        $this->assertEquals('Program Robotik Eksklusif', $response->json('data.profile.featured_programs.0.title'));
    }
}
