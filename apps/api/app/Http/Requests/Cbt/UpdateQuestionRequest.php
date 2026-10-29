<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuestionRequest extends FormRequest
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
            $merge['body'] = $currentBody;
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
            'bank_id' => ['nullable', 'uuid', 'exists:question_banks,id'],
            'question_type' => ['nullable', 'string', 'in:pg,pg_kompleks,bs,menjodohkan,isian,uraian'],
            'body' => ['nullable', 'array'],
            'body.content' => ['required_with:body', 'string'],
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
}
