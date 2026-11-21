<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\BaseApiRequest;

class StoreLeaveRequest extends BaseApiRequest
{
    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('studentId') && ! $this->has('student_id')) {
            $merge['student_id'] = $this->input('studentId');
        }
        if ($this->has('leaveType') && ! $this->has('leave_type')) {
            $merge['leave_type'] = $this->input('leaveType');
        }
        if ($this->has('startDate') && ! $this->has('start_date')) {
            $merge['start_date'] = $this->input('startDate');
        }
        if ($this->has('endDate') && ! $this->has('end_date')) {
            $merge['end_date'] = $this->input('endDate');
        }
        if ($this->has('attachmentFileId') && ! $this->has('attachment_file_id')) {
            $merge['attachment_file_id'] = $this->input('attachmentFileId');
        }

        $type = $merge['leave_type'] ?? $this->input('leave_type');
        if ($type) {
            $merge['leave_type'] = match ($type) {
                'sick', 'Sakit' => 'sakit',
                'duty', 'Dispensasi', 'dispen' => 'dispen',
                'annual', 'family', 'Izin', 'izin' => 'izin',
                default => $type,
            };
        }

        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'leave_type' => ['required', 'string', 'in:sakit,izin,dispen'],
            'start_date' => ['required', 'date', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'attachment_file_id' => ['nullable', 'uuid', 'exists:files,id'],
            'attachment_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'Siswa yang diajukan izin wajib dipilih',
            'student_id.exists' => 'Siswa tidak ditemukan di sistem',
            'leave_type.required' => 'Jenis permohonan izin wajib dipilih',
            'leave_type.in' => 'Jenis izin harus salah satu dari: sakit, izin, dispen',
            'start_date.required' => 'Tanggal mulai izin wajib diisi',
            'start_date.date_format' => 'Format tanggal mulai harus YYYY-MM-DD',
            'end_date.required' => 'Tanggal selesai izin wajib diisi',
            'end_date.date_format' => 'Format tanggal selesai harus YYYY-MM-DD',
            'end_date.after_or_equal' => 'Tanggal selesai harus sama dengan atau setelah tanggal mulai',
            'reason.required' => 'Alasan permohonan izin wajib diisi',
            'reason.min' => 'Alasan permohonan izin minimal 3 karakter',
            'attachment_file_id.exists' => 'Berkas lampiran surat tidak ditemukan di sistem',
            'attachment_file.mimes' => 'Format berkas lampiran harus berupa JPG, JPEG, PNG, atau PDF',
            'attachment_file.max' => 'Ukuran berkas lampiran maksimal 5 MB',
        ];
    }
}
