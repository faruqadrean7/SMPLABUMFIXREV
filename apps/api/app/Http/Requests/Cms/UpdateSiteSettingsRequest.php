<?php

namespace App\Http\Requests\Cms;

use App\Http\Requests\BaseApiRequest;

class UpdateSiteSettingsRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            // Identity
            'identity' => ['nullable', 'array'],
            'identity.name' => ['nullable', 'string', 'max:150'],
            'identity.tagline' => ['nullable', 'string', 'max:255'],
            'identity.logo_url' => ['nullable', 'url', 'max:500'],
            'identity.favicon_url' => ['nullable', 'url', 'max:500'],

            // Contact
            'contact' => ['nullable', 'array'],
            'contact.phone' => ['nullable', 'string', 'max:50'],
            'contact.email' => ['nullable', 'email', 'max:100'],
            'contact.whatsapp' => ['nullable', 'string', 'max:50'],

            // Address & Coordinates
            'address' => ['nullable', 'array'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:100'],
            'address.postal_code' => ['nullable', 'string', 'max:20'],
            'address.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'address.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'address.maps_embed_url' => ['nullable', 'url', 'max:1000'],

            // Social Media
            'social_media' => ['nullable', 'array'],
            'social_media.instagram' => ['nullable', 'url', 'max:255'],
            'social_media.youtube' => ['nullable', 'url', 'max:255'],
            'social_media.facebook' => ['nullable', 'url', 'max:255'],
            'social_media.twitter' => ['nullable', 'url', 'max:255'],
            'social_media.tiktok' => ['nullable', 'url', 'max:255'],

            // Profile, Vision Mission, Principal Greeting, Accreditation, Programs
            'profile' => ['nullable', 'array'],
            'profile.vision' => ['nullable', 'string'],
            'profile.mission' => ['nullable', 'array'],
            'profile.mission.*' => ['string'],
            'profile.principal_greeting' => ['nullable', 'array'],
            'profile.principal_greeting.principal_name' => ['nullable', 'string', 'max:150'],
            'profile.principal_greeting.principal_photo_url' => ['nullable', 'url', 'max:500'],
            'profile.principal_greeting.greeting_text' => ['nullable', 'string'],
            'profile.accreditation' => ['nullable', 'array'],
            'profile.accreditation.grade' => ['nullable', 'string', 'max:10'],
            'profile.accreditation.score' => ['nullable', 'numeric', 'between:0,100'],
            'profile.accreditation.certificate_no' => ['nullable', 'string', 'max:100'],
            'profile.accreditation.valid_until' => ['nullable', 'date'],
            'profile.featured_programs' => ['nullable', 'array'],
            'profile.featured_programs.*.title' => ['required_with:profile.featured_programs', 'string', 'max:150'],
            'profile.featured_programs.*.description' => ['nullable', 'string'],
            'profile.featured_programs.*.icon' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'identity.logo_url.url' => 'Format URL logo tidak valid',
            'identity.favicon_url.url' => 'Format URL favicon tidak valid',
            'contact.email.email' => 'Format email kontak sekolah tidak valid',
            'address.latitude.between' => 'Nilai latitude harus antara -90 dan 90',
            'address.longitude.between' => 'Nilai longitude harus antara -180 dan 180',
            'address.maps_embed_url.url' => 'Format URL embed peta tidak valid',
            'social_media.instagram.url' => 'Format URL Instagram tidak valid',
            'social_media.youtube.url' => 'Format URL YouTube tidak valid',
            'social_media.facebook.url' => 'Format URL Facebook tidak valid',
            'social_media.twitter.url' => 'Format URL Twitter / X tidak valid',
            'social_media.tiktok.url' => 'Format URL TikTok tidak valid',
            'profile.principal_greeting.principal_photo_url.url' => 'Format URL foto kepala sekolah tidak valid',
            'profile.accreditation.score.between' => 'Nilai akreditasi harus antara 0 dan 100',
            'profile.accreditation.valid_until.date' => 'Format tanggal masa berlaku akreditasi tidak valid',
        ];
    }
}
