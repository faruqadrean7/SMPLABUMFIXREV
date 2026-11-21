<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('stem') && ! $this->has('body.content')) {
            $currentBody = $this->input('body', []);
            if (! is_array($currentBody)) {
                $currentBody = [];
            }
            $currentBody['content'] = (string) $this->input('stem');
            $currentBody['text'] = (string) $this->input('stem');
            if ($this->has('media_url')) {
                $currentBody['media_url'] = (string) $this->input('media_url');
            }
            $merge['body'] = $currentBody;
        } elseif ($this->has('media_url')) {
            $currentBody = $this->input('body', []);
            if (is_array($currentBody)) {
                $currentBody['media_url'] = (string) $this->input('media_url');
                $merge['body'] = $currentBody;
            }
        }

        if ($this->has('options') && is_array($this->input('options'))) {
            $options = $this->input('options');
            foreach ($options as $k => $opt) {
                if (isset($opt['text']) && ! isset($opt['body'])) {
                    $options[$k]['body'] = [
                        'text' => $opt['text'],
                        'content' => $opt['text'],
                    ];
                }
            }
            $merge['options'] = $options;
        }

        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'bank_id' => ['required', 'uuid', 'exists:question_banks,id'],
            'question_type' => ['required', 'string', 'in:pg,pg_kompleks,bs,menjodohkan,isian,uraian'],
            'body' => ['required', 'array'],
            'body.content' => ['required', 'string'],
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
            'cognitive_level.in' => 'Level kognitif harus salah satu dari: LOTS, MOTS, HOTS.',
            'difficulty.min' => 'Tingkat kesulitan minimal 1.',
            'difficulty.max' => 'Tingkat kesulitan maksimal 5.',
        ];
    }
}
