<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_id' => ['required', 'uuid', 'exists:question_banks,id'],
            'question_type' => ['required', 'string', 'in:pg,pg_kompleks,bs,menjodohkan,isian,uraian'],
            'body' => ['required', 'array'],
            'body.content' => ['required', 'string', 'max:100000'],
            'body.formula' => ['nullable', 'string'],
            'body.media_url' => ['nullable', 'string'],
            'cp_code' => ['nullable', 'string', 'max:100'],
            'cognitive_level' => ['nullable', 'string', 'in:LOTS,MOTS,HOTS'],
            'difficulty' => ['nullable', 'integer', 'min:1', 'max:5'],
            'weight' => ['nullable', 'numeric', 'min:0.1', 'max:100'],
            'explanation' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:draft,aktif,terkunci'],
            'options' => ['nullable', 'array'],
            'options.*.label' => ['required_with:options', 'string', 'max:10'],
            'options.*.body' => ['required_with:options', 'array'],
            'options.*.body.content' => ['nullable', 'string', 'max:20000'],
            'options.*.is_correct' => ['nullable', 'boolean'],
            'options.*.match_key' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'bank_id.required' => 'Bank soal wajib dipilih.',
            'bank_id.uuid' => 'Format ID bank soal tidak valid.',
            'bank_id.exists' => 'Bank soal tidak ditemukan.',
            'question_type.required' => 'Tipe soal wajib diisi.',
            'question_type.in' => 'Tipe soal harus salah satu dari: pg, pg_kompleks, bs, menjodohkan, isian, uraian.',
            'body.required' => 'Konten soal (body) wajib diisi.',
            'body.content.required' => 'Teks pertanyaan (body.content) wajib diisi.',
            'body.content.max' => 'Konten teks soal tidak boleh melebihi 100.000 karakter.',
            'options.*.body.content.max' => 'Konten pilihan jawaban tidak boleh melebihi 20.000 karakter.',
            'cognitive_level.in' => 'Level kognitif harus salah satu dari: LOTS, MOTS, HOTS.',
            'difficulty.min' => 'Tingkat kesulitan minimal 1.',
            'difficulty.max' => 'Tingkat kesulitan maksimal 5.',
        ];
    }
}
