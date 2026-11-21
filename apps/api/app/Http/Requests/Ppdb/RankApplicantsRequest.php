<?php

namespace App\Http\Requests\Ppdb;

use App\Http\Requests\BaseApiRequest;

class RankApplicantsRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'track_id' => ['nullable', 'uuid', 'exists:ppdb_tracks,id'],
            'waitlist_quota' => ['nullable', 'integer', 'min:0'],
            'scores' => ['nullable', 'array'],
            'scores.*.applicant_id' => ['required_with:scores', 'uuid', 'exists:applicants,id'],
            'scores.*.score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'scores.*.rapor_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'scores.*.test_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'scores.*.track_id' => ['nullable', 'uuid', 'exists:ppdb_tracks,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'track_id.exists' => 'Jalur pendaftaran tidak ditemukan',
            'waitlist_quota.min' => 'Kuota cadangan minimal 0',
            'scores.*.applicant_id.exists' => 'Pendaftar tidak ditemukan',
            'scores.*.score.min' => 'Skor minimal adalah 0',
            'scores.*.score.max' => 'Skor maksimal adalah 100',
        ];
    }
}
