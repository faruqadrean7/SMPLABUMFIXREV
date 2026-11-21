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
        'js', 'jsp', 'asp', 'aspx', 'py', 'pl', 'cgi', 'html', 'htm', 'xhtml',
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
        'text/x-php',
        'text/x-shellscript',
        'application/x-csh',
    ];

    public function rules(): array
    {
        return [
            'filename' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'max:100'],
            'size_bytes' => ['required', 'integer', 'min:1', 'max:52428800'], // Maks 50MB
            'bucket' => ['nullable', 'string', 'max:100'],
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

            if (in_array($extension, self::DISALLOWED_EXTENSIONS, true)) {
                $validator->errors()->add('filename', 'Ekstensi berkas ini dilarang demi keamanan sistem.');
            }

            $mimeType = strtolower(trim((string) $this->input('mime_type', '')));
            if (in_array($mimeType, self::DISALLOWED_MIME_TYPES, true)) {
                $validator->errors()->add('mime_type', 'Tipe konten (MIME) berkas ini dilarang demi keamanan sistem.');
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
