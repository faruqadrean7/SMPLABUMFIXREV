<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SiteSettingService
{
    /**
     * BE-CMS-01: Get compiled public site profile with robust fallbacks.
     */
    public function getSiteProfile(): array
    {
        $defaults = $this->getDefaults();
        $stored = SiteSetting::all()->pluck('setting_value', 'setting_key')->toArray();

        $profile = [];
        foreach ($defaults as $section => $defaultValues) {
            $storedSection = $stored[$section] ?? [];
            if (is_array($defaultValues)) {
                $profile[$section] = $this->resolveSection($section, $defaultValues, is_array($storedSection) ? $storedSection : []);
            } else {
                $profile[$section] = $storedSection ?: $defaultValues;
            }
        }

        return $profile;
    }

    /**
     * BE-CMS-01: Update site settings and log audit trail.
     */
    public function updateSiteSettings(array $data, Request $request): array
    {
        return DB::transaction(function () use ($data, $request) {
            $updatedKeys = [];
            $oldValues = [];

            foreach (['identity', 'contact', 'address', 'social_media', 'profile'] as $section) {
                if (array_key_exists($section, $data) && is_array($data[$section])) {
                    $setting = SiteSetting::where('setting_key', $section)->first();
                    $existing = $setting?->setting_value ?? ($this->getDefaults()[$section] ?? []);

                    $oldValues[$section] = $existing;
                    $merged = is_array($existing) ? $this->deepMerge($existing, $data[$section]) : $data[$section];

                    SiteSetting::updateOrCreate(
                        ['setting_key' => $section],
                        [
                            'school_id' => $request->user()?->school_id,
                            'setting_value' => $merged,
                        ]
                    );

                    $updatedKeys[] = $section;
                }
            }

            $currentProfile = $this->getSiteProfile();

            $this->logAudit(
                $request->user()?->id,
                'site_settings.updated',
                'SiteSetting',
                null,
                $oldValues,
                ['updated_sections' => $updatedKeys],
                $request
            );

            return $currentProfile;
        });
    }

    /**
     * BE-CMS-01: Default site profile data fallback.
     */
    public function getDefaults(): array
    {
        return [
            'identity' => [
                'name' => 'SMP Laboratorium UM',
                'tagline' => 'Beradab, Kreatif, Berprestasi',
                'logo_url' => null,
                'favicon_url' => null,
            ],
            'contact' => [
                'phone' => '(0341) 588550',
                'email' => 'info@smplab-um.sch.id',
                'whatsapp' => '0851-2608-8473',
            ],
            'address' => [
                'street' => 'Jl. Simpang Bogor No. T-7',
                'city' => 'Kota Malang',
                'postal_code' => '65145',
                'latitude' => -7.9531,
                'longitude' => 112.6144,
                'maps_embed_url' => null,
            ],
            'social_media' => [
                'instagram' => null,
                'youtube' => null,
                'facebook' => null,
                'twitter' => null,
                'tiktok' => null,
            ],
            'profile' => [
                'vision' => 'Mewujudkan Generasi Beriman, Berkarakter, Berprestasi, dan Berbudaya',
                'mission' => [
                    'Menyelenggarakan pendidikan berkualitas yang mengembangkan potensi kecerdasan intelektual, emosional, dan spiritual.',
                    'Membina budaya riset, teknologi, dan kewirausahaan sejak dini.',
                    'Menanamkan karakter berwawasan kebangsaan dan kepedulian lingkungan hidup.',
                ],
                'principal_greeting' => [
                    'principal_name' => 'Bapak Tamyis Suliantoro, M.Pd.',
                    'principal_photo_url' => null,
                    'greeting_text' => 'Selamat datang di Portal Resmi Portal Sekolah Satu Pintu SMP Laboratorium UM.',
                ],
                'accreditation' => [
                    'grade' => 'A',
                    'score' => 96,
                    'certificate_no' => 'BAN-S/M-12345/2022',
                    'valid_until' => '2027-12-31',
                ],
                'featured_programs' => [
                    [
                        'title' => 'Bilingual Science & Tech',
                        'description' => 'Kurikulum pengantar dwibahasa untuk peminatan MIPA dan teknologi digital.',
                        'icon' => 'laptop',
                    ],
                    [
                        'title' => 'Tahfidz & Karakter Qur\'ani',
                        'description' => 'Bimbingan hafalan Al-Qur\'an dan integrasi adab dalam kehidupan sekolah.',
                        'icon' => 'book-open',
                    ],
                    [
                        'title' => 'Riset Siswa & Inkubasi Karya',
                        'description' => 'Program penelitian karya ilmiah remaja dan kompetisi nasional/internasional.',
                        'icon' => 'award',
                    ],
                ],
            ],
        ];
    }

    /**
     * Helper to write an audit log entry.
     */
    protected function logAudit(
        ?string $actorId,
        string $action,
        string $entity,
        ?string $entityId,
        ?array $oldValue,
        ?array $newValue,
        Request $request
    ): void {
        try {
            AuditLog::create([
                'actor_id' => $actorId,
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * BE-CMS-01 / INT-06-FIX: Resolve section fields with field-specific fallback rules.
     *
     * Rules:
     * 1. If stored field is non-empty / valid, admin value takes precedence.
     * 2. If stored field is null or empty string for an invariant field with a default, fallback to default.
     * 3. If default is null (optional fields like social media, logo, favicon), keep null if stored is empty.
     * 4. For numeric fields (latitude, longitude, score), 0 is a valid number and never treated as empty.
     * 5. For array fields (mission, featured_programs), non-empty arrays take precedence; empty arrays fallback.
     */
    protected function resolveSection(string $section, array $defaults, array $stored): array
    {
        $resolved = [];

        foreach ($defaults as $key => $defaultValue) {
            $hasStored = array_key_exists($key, $stored);
            $storedValue = $hasStored ? $stored[$key] : null;

            if (is_array($defaultValue)) {
                if ($this->isAssoc($defaultValue)) {
                    $storedSub = is_array($storedValue) ? $storedValue : [];
                    $resolved[$key] = $this->resolveSection("{$section}.{$key}", $defaultValue, $storedSub);
                } else {
                    if (is_array($storedValue) && count($storedValue) > 0) {
                        $resolved[$key] = $storedValue;
                    } else {
                        $resolved[$key] = $defaultValue;
                    }
                }
            } elseif (is_numeric($defaultValue)) {
                if ($hasStored && ! is_null($storedValue) && $storedValue !== '' && is_numeric($storedValue)) {
                    $resolved[$key] = is_float($defaultValue) ? (float) $storedValue : (int) $storedValue;
                } else {
                    $resolved[$key] = $defaultValue;
                }
            } elseif (is_null($defaultValue)) {
                // Intentionally optional field (logo_url, favicon_url, maps_embed_url, social_media URLs, principal_photo_url)
                if ($hasStored && ! is_null($storedValue) && trim((string) $storedValue) !== '') {
                    $resolved[$key] = trim((string) $storedValue);
                } else {
                    $resolved[$key] = null;
                }
            } else {
                // Invariant string field (name, tagline, phone, email, whatsapp, street, city, vision, etc.)
                if ($hasStored && ! is_null($storedValue) && trim((string) $storedValue) !== '') {
                    $resolved[$key] = trim((string) $storedValue);
                } else {
                    $resolved[$key] = $defaultValue;
                }
            }
        }

        // Include any extra custom keys stored by admin
        foreach ($stored as $key => $storedValue) {
            if (! array_key_exists($key, $resolved)) {
                $resolved[$key] = $storedValue;
            }
        }

        return $resolved;
    }

    /**
     * Recursively merge associative arrays while preserving non-associative sub-arrays.
     */
    protected function deepMerge(array $array1, array $array2): array
    {
        $merged = $array1;
        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key]) && $this->isAssoc($value)) {
                $merged[$key] = $this->deepMerge($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * Check if an array is associative (dictionary) vs sequential list.
     */
    protected function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
