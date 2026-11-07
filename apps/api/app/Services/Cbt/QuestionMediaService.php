<?php

namespace App\Services\Cbt;

use App\Models\File;
use App\Models\User;
use Carbon\Carbon;
use DOMDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class QuestionMediaService
{
    public const MAX_BYTES = 2097152; // 2 MB

    public const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    /**
     * BE-CBT-12: Unggah gambar media soal CBT dengan verifikasi magic bytes dan penyimpanan privat.
     *
     * @return array{file_id: string, mime_type: string, preview_url: string}
     */
    public function upload(UploadedFile $file, string $bankId, User $user): array
    {
        // 1. Validasi MIME type deklarasi
        $clientMime = $file->getClientMimeType() ?: $file->getMimeType();
        if (! in_array($clientMime, self::ALLOWED_MIMES, true)) {
            throw new HttpException(422, 'Hanya berkas gambar (JPEG, PNG, WebP, GIF) yang diperbolehkan.');
        }

        // 2. Validasi batas ukuran fisik berkas (2 MB)
        $fileSize = $file->getSize();
        if ($fileSize > self::MAX_BYTES) {
            throw new HttpException(422, 'Ukuran gambar tidak boleh melebihi 2 MB.');
        }

        // 3. Verifikasi Magic Bytes menggunakan getimagesize
        $realPath = $file->getRealPath();
        $imageInfo = @getimagesize($realPath);
        if ($imageInfo === false || empty($imageInfo[2])) {
            throw new HttpException(422, 'Berkas bukan gambar yang valid (verifikasi magic bytes gagal).');
        }

        $detectedImageType = $imageInfo[2];
        $validImageTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF];
        if (defined('IMAGETYPE_WEBP')) {
            $validImageTypes[] = IMAGETYPE_WEBP;
        }

        if (! in_array($detectedImageType, $validImageTypes, true)) {
            throw new HttpException(422, 'Format isi berkas tidak didukung atau rusak.');
        }

        // 4. Simpan ke Storage lokal (privat) dengan nama berkas acak (UUID)
        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $randomFileName = Str::uuid()->toString().'.'.$ext;
        $now = Carbon::now();
        $objectKey = "cbt/{$now->year}/{$now->format('m')}/{$randomFileName}";

        $content = file_get_contents($realPath);
        if ($content === false) {
            throw new HttpException(500, 'Gagal membaca isi berkas gambar.');
        }

        Storage::disk('local')->put($objectKey, $content);
        $checksum = hash('sha256', $content);

        // 5. Catat entri berkas ke tabel files
        $fileModel = File::create([
            'school_id' => $user->school_id,
            'owner_id' => $bankId,
            'owner_type' => 'question_bank',
            'bucket' => 'cbt',
            'object_key' => $objectKey,
            'mime_type' => $clientMime,
            'size_bytes' => $fileSize,
            'visibility' => 'privat',
            'checksum' => $checksum,
        ]);

        // 6. Buat tautan bertanda tangan sementara untuk pratinjau editor (TTL 15 menit)
        $previewUrl = $this->signedUrl($fileModel->id, 15);

        return [
            'file_id' => $fileModel->id,
            'mime_type' => $fileModel->mime_type,
            'preview_url' => $previewUrl,
        ];
    }

    /**
     * Menghasilkan URL bertanda-tangan singkat untuk mengunduh/menampilkan berkas media.
     */
    public function signedUrl(string $fileId, int $ttlMin = 15): string
    {
        return URL::temporarySignedRoute(
            'files.download.signed',
            Carbon::now()->addMinutes($ttlMin),
            ['id' => $fileId]
        );
    }

    /**
     * BE-CBT-13: Menyuntikkan atribut src bertanda-tangan pada tag <img> yang memiliki data-file-id.
     * Hanya file_id yang terdaftar valid di database tabel files yang akan disuntikkan src.
     */
    public function resolveMediaInHtml(?string $html, int $ttlMin = 15): string
    {
        if (empty($html) || stripos($html, 'data-file-id') === false) {
            return (string) $html;
        }

        $libxmlState = libxml_use_internal_errors(true);
        $doc = new DOMDocument;
        $wrapped = '<?xml encoding="utf-8" ?><div>'.$html.'</div>';
        $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($libxmlState);

        $images = $doc->getElementsByTagName('img');
        if ($images->length === 0) {
            return $html;
        }

        $fileIds = [];
        /** @var \DOMElement $img */
        foreach ($images as $img) {
            if ($img->hasAttribute('data-file-id')) {
                $fileIds[] = $img->getAttribute('data-file-id');
            }
        }

        if (empty($fileIds)) {
            return $html;
        }

        // Ambil data file dari database yang valid
        $validFiles = File::whereIn('id', $fileIds)
            ->where('bucket', 'cbt')
            ->get()
            ->keyBy('id');

        foreach ($images as $img) {
            if ($img->hasAttribute('data-file-id')) {
                $fId = $img->getAttribute('data-file-id');
                if (isset($validFiles[$fId])) {
                    $url = $this->signedUrl($fId, $ttlMin);
                    $img->setAttribute('src', $url);
                } else {
                    $img->removeAttribute('src');
                }
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
