<?php

namespace App\Http\Requests\Cbt;

use App\Models\QuestionBank;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuestionMediaRequest extends FormRequest
{
    /**
     * Otorisasi pemohon: Anti-IDOR.
     * Hanya pemilik bank soal atau Super Admin / Admin TU yang dapat mengunggah media ke bank soal ini.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        // Super Admin dan Admin TU memiliki akses menyeluruh
        if (method_exists($user, 'hasRole') && ($user->hasRole('super_admin') || $user->hasRole('admin_tu'))) {
            return true;
        }

        $bankId = $this->input('bank_id');
        if ($bankId) {
            $bank = QuestionBank::find($bankId);
            if ($bank && $bank->owner_id !== $user->id) {
                return false;
            }
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'bank_id' => ['required', 'uuid', 'exists:question_banks,id'],
            'file' => ['required', 'file', 'image', 'mimes:jpeg,png,webp,gif', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'bank_id.required' => 'Bank soal wajib dipilih.',
            'bank_id.uuid' => 'Format ID bank soal tidak valid.',
            'bank_id.exists' => 'Bank soal tidak ditemukan.',
            'file.required' => 'Berkas gambar wajib diunggah.',
            'file.image' => 'Berkas yang diunggah harus berupa gambar.',
            'file.mimes' => 'Format gambar harus salah satu dari: JPEG, PNG, WebP, GIF.',
            'file.max' => 'Ukuran gambar maksimal 2 MB (2.048 KB).',
        ];
    }
}
