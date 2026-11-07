<?php

namespace App\Services\Cbt;

use DOMDocument;
use Mews\Purifier\Purifier;

class QuestionContentSanitizer
{
    /**
     * Sanitasi string HTML soal atau opsi jawaban menggunakan profil purifier 'cbt'.
     * Menghapus XSS, tag terlarang, serta atribut src pada tag <img> (hanya menyisakan data-file-id dan atribut visual aman).
     */
    public static function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        // 1. Bersihkan markup berbahaya melalui HTMLPurifier dengan profil 'cbt'
        /** @var Purifier $purifier */
        $purifier = app('purifier');
        $clean = $purifier->clean($html, 'cbt');

        // 2. Jika tidak ada tag <img>, langsung kembalikan hasil purifier
        if (stripos($clean, '<img') === false) {
            return $clean;
        }

        // 3. Post-processing DOM: hapus atribut src pada setiap tag <img>
        return self::stripImageSrc($clean);
    }

    /**
     * Sanitasi payload body soal (format array jsonb).
     * Hanya field 'content' yang disanitasi; formula atau media_url legacy dipertahankan.
     */
    public static function sanitizeBody(?array $body): ?array
    {
        if ($body === null) {
            return null;
        }

        if (array_key_exists('content', $body) && is_string($body['content'])) {
            $body['content'] = self::sanitize($body['content']);
        }

        return $body;
    }

    /**
     * Menghapus atribut 'src' pada elemen <img> dari potongan HTML.
     */
    private static function stripImageSrc(string $html): string
    {
        $libxmlState = libxml_use_internal_errors(true);

        $doc = new DOMDocument;
        // Bungkus dengan div dan deklarasi encoding UTF-8 untuk menjaga karakter multibahasa & struktur fragment
        $wrapped = '<?xml encoding="utf-8" ?><div>'.$html.'</div>';
        $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();
        libxml_use_internal_errors($libxmlState);

        $images = $doc->getElementsByTagName('img');
        foreach ($images as $img) {
            if ($img->hasAttribute('src')) {
                $img->removeAttribute('src');
            }
        }

        $wrapper = $doc->getElementsByTagName('div')->item(0);
        if (! $wrapper) {
            return $html;
        }

        $result = '';
        foreach ($wrapper->childNodes as $child) {
            $result .= $doc->saveHTML($child);
        }

        return $result;
    }
}
