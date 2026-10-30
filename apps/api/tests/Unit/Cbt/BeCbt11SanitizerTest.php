<?php

namespace Tests\Unit\Cbt;

use App\Services\Cbt\QuestionContentSanitizer;
use Tests\TestCase;

class BeCbt11SanitizerTest extends TestCase
{
    /**
     * TEST-BE-CBT-11-01: Memastikan script dan event handler berbahaya dibuang.
     */
    public function test_be_cbt_11_strips_script_and_event_handlers(): void
    {
        $dirty = '<p>Halo Siswa</p><script>alert("hacked")</script><img src="x" onerror="alert(1)" data-file-id="uuid-123">';
        $clean = QuestionContentSanitizer::sanitize($dirty);

        $this->assertStringNotContainsString('<script>', $clean);
        $this->assertStringNotContainsString('alert', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringContainsString('Halo Siswa', $clean);
    }

    /**
     * TEST-BE-CBT-11-02: Memastikan tag tabel dan img dipertahankan, namun atribut src dibuang.
     */
    public function test_be_cbt_11_keeps_allowed_tags_table_img(): void
    {
        $html = '<table border="1"><thead><tr><th>Header</th></tr></thead><tbody><tr><td>Cell 1</td></tr></tbody></table><img src="https://attacker.com/malicious.jpg" data-file-id="00000000-0000-0000-0000-000000000001" alt="Diagram Sel">';
        $clean = QuestionContentSanitizer::sanitize($html);

        $this->assertStringContainsString('<table>', $clean);
        $this->assertStringContainsString('<thead>', $clean);
        $this->assertStringContainsString('<th>Header</th>', $clean);
        $this->assertStringContainsString('<td>Cell 1</td>', $clean);
        $this->assertStringContainsString('<img', $clean);
        $this->assertStringContainsString('data-file-id="00000000-0000-0000-0000-000000000001"', $clean);
        $this->assertStringContainsString('alt="Diagram Sel"', $clean);
        // Atribut src harus dibuang agar penyimpanan HTML bersih dan tidak bergantung src eksternal
        $this->assertStringNotContainsString('src=', $clean);
    }

    /**
     * TEST-BE-CBT-11-03: Memastikan teks polos lolos tanpa rusak (kompatibel mundur).
     */
    public function test_be_cbt_11_plain_text_passes_through(): void
    {
        $plain = 'Berapakah hasil dari 5 + 5? Jelaskan jawabanmu.';
        $clean = QuestionContentSanitizer::sanitize($plain);

        $this->assertStringContainsString('Berapakah hasil dari 5 + 5? Jelaskan jawabanmu.', $clean);
    }

    /**
     * TEST-BE-CBT-11-04: Memastikan sanitizeBody hanya memproses body.content dan membiarkan formula/media_url.
     */
    public function test_be_cbt_11_sanitize_body_only_touches_content(): void
    {
        $body = [
            'content' => '<strong>Pertanyaan:</strong> <script>alert(1)</script>Perhatikan gambar!',
            'formula' => 'E = mc^2',
            'media_url' => 'https://legacy.school.sch.id/image.png',
        ];

        $sanitized = QuestionContentSanitizer::sanitizeBody($body);

        $this->assertIsArray($sanitized);
        $this->assertStringNotContainsString('<script>', $sanitized['content']);
        $this->assertStringContainsString('<strong>Pertanyaan:</strong>', $sanitized['content']);
        $this->assertEquals('E = mc^2', $sanitized['formula']);
        $this->assertEquals('https://legacy.school.sch.id/image.png', $sanitized['media_url']);
    }

    /**
     * TEST-BE-CBT-11-05: Sanitasi body null atau kosong mengembalikan null atau struktur kosong aman.
     */
    public function test_be_cbt_11_sanitize_body_handles_null(): void
    {
        $this->assertNull(QuestionContentSanitizer::sanitizeBody(null));
        $this->assertEquals(['content' => ''], QuestionContentSanitizer::sanitizeBody(['content' => '']));
    }
}
