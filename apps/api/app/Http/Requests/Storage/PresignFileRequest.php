<?php

namespace App\Http\Requests\Storage;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Validator;

class PresignFileRequest extends BaseApiRequest
{
    /**
     * Dangerous file extensions prohibited from uploading.
     */
    protected const DISALLOWED_EXTENSIONS = [
        'exe', 'bat', 'cmd', 'sh', 'php', 'phtml', 'php3', 'php4', 'php5', 'phps',
        'js', 'mjs', 'cjs', 'jsp', 'asp', 'aspx', 'py', 'pl', 'cgi', 'html', 'htm', 'xhtml', 'shtml',
        'svg', 'svgz', 'xml', 'xsl', 'xslt', 'swf', 'hta', 'mhtml',
        'vbs', 'jar', 'war', 'com', 'scr', 'dll', 'msi', 'bin', 'deb', 'rpm',
    ];

    /**
     * Dangerous MIME types prohibited from uploading.
     */
    protected const DISALLOWED_MIME_TYPES = [
        'application/x-msdownload',
        'application/x-sh',
        'application/x-bat',
        'application/x-executable',
        'application/x-httpd-php',
        'application/x-php',
        'application/javascript',
        'text/javascript',
        'text/html',
        'application/xhtml+xml',
        'image/svg+xml',
        'text/svg+xml',
        'text/xml',
        'application/xml',
        'text/x-php',
        'text/x-shellscript',
        'application/x-csh',
    ];

    /**
     * Allowlist ekstensi berkas yang diizinkan di sistem sekolah.
     */
    protected const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'webp', 'gif',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt', 'rtf', 'odt', 'ods', 'odp',
        'zip', 'mp3', 'wav', 'mp4', 'webm',
    ];

    /**
     * Allowlist MIME types yang diizinkan.
     */
    protected const ALLOWED_MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/csv', 'text/plain', 'application/rtf', 'text/rtf',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.presentation',
        'application/zip', 'application/x-zip-compressed',
        'audio/mpeg', 'audio/wav', 'video/mp4', 'video/webm',
        'application/octet-stream',
    ];

    /**
     * Bucket internal terproteksi yang tidak boleh diminta bebas oleh klien.
     */
    protected const RESERVED_BUCKETS = [
        'leave-attachments',
        'complaints',
    ];

    public function rules(): array
    {
        return [
            'filename' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'max:100'],
            'size_bytes' => ['required', 'integer', 'min:1', 'max:52428800'], // Maks 50MB
            'bucket' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9_-]*$/'],
            'visibility' => ['nullable', 'string', 'in:publik,privat'],
            'purpose' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'filename.required' => 'Nama berkas wajib diisi.',
            'mime_type.required' => 'Tipe MIME berkas wajib diisi.',
            'size_bytes.required' => 'Ukuran berkas wajib diisi.',
            'size_bytes.min' => 'Ukuran berkas minimal 1 byte.',
            'size_bytes.max' => 'Ukuran berkas melebihi batas maksimum 50MB.',
            'visibility.in' => 'Visibilitas harus bernilai publik atau privat.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $filename = (string) $this->input('filename', '');
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($extension, self::DISALLOWED_EXTENSIONS, true) || ($extension !== '' && ! in_array($extension, self::ALLOWED_EXTENSIONS, true))) {
                $validator->errors()->add('filename', 'Ekstensi berkas ini dilarang demi keamanan sistem.');
            }

            $mimeType = strtolower(trim((string) $this->input('mime_type', '')));
            if (in_array($mimeType, self::DISALLOWED_MIME_TYPES, true) || ! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
                $validator->errors()->add('mime_type', 'Tipe konten (MIME) berkas ini dilarang demi keamanan sistem.');
            }

            $bucket = strtolower(trim((string) $this->input('bucket', '')));
            if ($bucket !== '' && in_array($bucket, self::RESERVED_BUCKETS, true)) {
                $validator->errors()->add('bucket', 'Nama bucket ini bersifat internal dan tidak dapat dipilih secara langsung.');
            }

            // Validasi khusus untuk purpose 'avatar' (Foto Profil Resmi)
            $purpose = strtolower(trim((string) $this->input('purpose', '')));
            if ($purpose === 'avatar') {
                $allowedAvatarMimes = ['image/jpeg', 'image/png', 'image/webp'];
                $allowedAvatarExts = ['jpg', 'jpeg', 'png', 'webp'];

                if (! in_array($mimeType, $allowedAvatarMimes, true)) {
                    $validator->errors()->add('mime_type', 'Foto profil harus berupa format gambar JPEG, PNG, atau WebP.');
                }

                if (! in_array($extension, $allowedAvatarExts, true)) {
                    $validator->errors()->add('filename', 'Ekstensi berkas foto profil harus berupa .jpg, .jpeg, .png, atau .webp.');
                }

                $sizeBytes = (int) $this->input('size_bytes', 0);
                if ($sizeBytes > 2097152) { // Maksimal 2 MB
                    $validator->errors()->add('size_bytes', 'Ukuran foto profil melebihi batas maksimum 2 MB.');
                }
            }
        });
    }
}
