<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\File;
use App\Models\Staff;
use App\Models\Teacher;
use App\Models\TeacherSalarySlip;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TeacherStaffService
{
    /**
     * BE-MST-03: List teachers with pagination, search, and relations.
     */
    public function listTeachers(Request $request): LengthAwarePaginator
    {
        $limit = min($request->integer('limit', 20), 100);
        $status = $request->query('status');
        $q = trim((string) $request->query('q', ''));

        $query = Teacher::with([
            'teachingAssignments.subject',
            'teachingAssignments.class',
            'homeroomClasses',
            'photoFile',
        ]);

        if ($status) {
            $query->where('status', $status);
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('full_name', 'like', "%{$q}%")
                    ->orWhere('nip', 'like', "%{$q}%")
                    ->orWhere('nuptk', 'like', "%{$q}%")
                    ->orWhere('position', 'like', "%{$q}%");
            });
        }

        return $query->orderBy('full_name', 'asc')->paginate($limit);
    }

    /**
     * BE-MST-03: Get detail of a teacher.
     */
    public function getTeacher(string $id): Teacher
    {
        return Teacher::with([
            'teachingAssignments.subject',
            'teachingAssignments.class',
            'teachingAssignments.semester',
            'homeroomClasses',
            'photoFile',
            'user',
            'salarySlips.file',
        ])->findOrFail($id);
    }

    /**
     * BE-MST-03: Create new teacher.
     */
    public function createTeacher(array $data, Request $request): Teacher
    {
        return DB::transaction(function () use ($data, $request) {
            $teacher = Teacher::create([
                'school_id' => $data['school_id'] ?? $request->user()?->school_id,
                'user_id' => $data['user_id'] ?? null,
                'nip' => $data['nip'] ?? null,
                'nuptk' => $data['nuptk'] ?? null,
                'full_name' => $data['full_name'],
                'position' => $data['position'] ?? null,
                'status' => $data['status'] ?? 'tetap',
                'photo_file_id' => $data['photo_file_id'] ?? null,
            ]);

            $this->logAudit(
                $request->user()?->id,
                'teacher.created',
                'Teacher',
                $teacher->id,
                null,
                $teacher->toArray(),
                $request
            );

            return $teacher;
        });
    }

    /**
     * BE-MST-03: Update teacher.
     */
    public function updateTeacher(string $id, array $data, Request $request): Teacher
    {
        return DB::transaction(function () use ($id, $data, $request) {
            $teacher = Teacher::findOrFail($id);
            $oldValue = $teacher->toArray();

            $fields = ['nip', 'nuptk', 'full_name', 'position', 'status', 'photo_file_id', 'user_id'];
            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    $teacher->{$field} = $data[$field];
                }
            }

            $teacher->save();

            $this->logAudit(
                $request->user()?->id,
                'teacher.updated',
                'Teacher',
                $teacher->id,
                $oldValue,
                $teacher->toArray(),
                $request
            );

            return $teacher;
        });
    }

    /**
     * BE-MST-03: Delete teacher.
     */
    public function deleteTeacher(string $id, Request $request): void
    {
        $teacher = Teacher::findOrFail($id);

        if ($teacher->teachingAssignments()->exists()) {
            throw new HttpException(
                409,
                'Guru tidak dapat dihapus karena masih memiliki penugasan mengajar aktif.',
                null,
                [],
                409
            );
        }

        $oldValue = $teacher->toArray();
        $teacher->delete();

        $this->logAudit(
            $request->user()?->id,
            'teacher.deleted',
            'Teacher',
            $teacher->id,
            $oldValue,
            null,
            $request
        );
    }

    /**
     * BE-MST-03: Import teachers from CSV with row-by-row validation.
     */
    public function importTeachers(UploadedFile $file, Request $request): array
    {
        $path = $file->getRealPath();
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new HttpException(400, 'Berkas tidak dapat dibaca.', null, [], 400);
        }

        $firstLine = fgets($handle);
        $delimiter = str_contains($firstLine, ';') ? ';' : ',';
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter);
        if (! $header) {
            fclose($handle);
            throw new HttpException(400, 'Berkas CSV kosong atau tidak memiliki baris header.', null, [], 400);
        }

        $headerMap = [];
        foreach ($header as $idx => $col) {
            $headerMap[strtolower(trim($col, " \t\n\r\0\x0B\"'"))] = $idx;
        }

        if (! isset($headerMap['full_name'])) {
            fclose($handle);
            throw new HttpException(400, "Format kolom CSV tidak sesuai: kolom 'full_name' wajib ada di header.", null, [], 400);
        }

        $rowNumber = 1;
        $errors = [];
        $recordsToInsert = [];
        $seenNip = [];
        $seenNuptk = [];

        $existingNip = Teacher::whereNotNull('nip')->pluck('nip')->flip()->toArray();
        $existingNuptk = Teacher::whereNotNull('nuptk')->pluck('nuptk')->flip()->toArray();

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;

            if (count(array_filter($row)) === 0) {
                continue;
            }

            $fullName = isset($headerMap['full_name']) && isset($row[$headerMap['full_name']])
                ? trim($row[$headerMap['full_name']])
                : '';
            $nip = isset($headerMap['nip']) && isset($row[$headerMap['nip']]) && trim($row[$headerMap['nip']]) !== ''
                ? trim($row[$headerMap['nip']])
                : null;
            $nuptk = isset($headerMap['nuptk']) && isset($row[$headerMap['nuptk']]) && trim($row[$headerMap['nuptk']]) !== ''
                ? trim($row[$headerMap['nuptk']])
                : null;
            $position = isset($headerMap['position']) && isset($row[$headerMap['position']])
                ? trim($row[$headerMap['position']])
                : null;
            $status = isset($headerMap['status']) && isset($row[$headerMap['status']]) && trim($row[$headerMap['status']]) !== ''
                ? strtolower(trim($row[$headerMap['status']]))
                : 'tetap';

            $rowErrors = [];

            if (empty($fullName)) {
                $rowErrors[] = 'Nama lengkap guru wajib diisi';
            }

            if ($nip !== null) {
                if (isset($existingNip[$nip]) || isset($seenNip[$nip])) {
                    $rowErrors[] = "NIP '{$nip}' sudah terdaftar";
                } else {
                    $seenNip[$nip] = true;
                }
            }

            if ($nuptk !== null) {
                if (isset($existingNuptk[$nuptk]) || isset($seenNuptk[$nuptk])) {
                    $rowErrors[] = "NUPTK '{$nuptk}' sudah terdaftar";
                } else {
                    $seenNuptk[$nuptk] = true;
                }
            }

            if (! in_array($status, ['tetap', 'honorer', 'kontrak'], true)) {
                $rowErrors[] = "Status harus salah satu dari: tetap, honorer, kontrak (diterima: '{$status}')";
            }

            if (! empty($rowErrors)) {
                $errors[] = [
                    'row' => $rowNumber,
                    'messages' => $rowErrors,
                ];
            } else {
                $recordsToInsert[] = [
                    'nip' => $nip,
                    'nuptk' => $nuptk,
                    'full_name' => $fullName,
                    'position' => $position,
                    'status' => $status,
                ];
            }
        }

        fclose($handle);

        if (! empty($errors)) {
            return [
                'success' => false,
                'total' => count($recordsToInsert) + count($errors),
                'imported' => 0,
                'failed' => count($errors),
                'errors' => $errors,
            ];
        }

        DB::transaction(function () use ($recordsToInsert, $request) {
            foreach ($recordsToInsert as $item) {
                Teacher::create([
                    'school_id' => $request->user()?->school_id,
                    'nip' => $item['nip'],
                    'nuptk' => $item['nuptk'],
                    'full_name' => $item['full_name'],
                    'position' => $item['position'],
                    'status' => $item['status'],
                ]);
            }

            $this->logAudit(
                $request->user()?->id,
                'teacher.imported',
                'Teacher',
                null,
                null,
                ['count' => count($recordsToInsert)],
                $request
            );
        });

        return [
            'success' => true,
            'total' => count($recordsToInsert),
            'imported' => count($recordsToInsert),
            'failed' => 0,
            'errors' => [],
        ];
    }

    // =========================================================================
    // STAF METHODS
    // =========================================================================

    /**
     * BE-MST-03: List staff with pagination and search.
     */
    public function listStaff(Request $request): LengthAwarePaginator
    {
        $limit = min($request->integer('limit', 20), 100);
        $unit = $request->query('unit');
        $q = trim((string) $request->query('q', ''));

        $query = Staff::with('user');

        if ($unit) {
            $query->where('unit', $unit);
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('full_name', 'like', "%{$q}%")
                    ->orWhere('position', 'like', "%{$q}%")
                    ->orWhere('unit', 'like', "%{$q}%");
            });
        }

        return $query->orderBy('full_name', 'asc')->paginate($limit);
    }

    /**
     * BE-MST-03: Get detail of staff.
     */
    public function getStaff(string $id): Staff
    {
        return Staff::with('user')->findOrFail($id);
    }

    /**
     * BE-MST-03: Create new staff.
     */
    public function createStaff(array $data, Request $request): Staff
    {
        return DB::transaction(function () use ($data, $request) {
            $staff = Staff::create([
                'school_id' => $data['school_id'] ?? $request->user()?->school_id,
                'user_id' => $data['user_id'] ?? null,
                'full_name' => $data['full_name'],
                'position' => $data['position'] ?? null,
                'unit' => $data['unit'] ?? null,
            ]);

            $this->logAudit(
                $request->user()?->id,
                'staff.created',
                'Staff',
                $staff->id,
                null,
                $staff->toArray(),
                $request
            );

            return $staff;
        });
    }

    /**
     * BE-MST-03: Update staff.
     */
    public function updateStaff(string $id, array $data, Request $request): Staff
    {
        return DB::transaction(function () use ($id, $data, $request) {
            $staff = Staff::findOrFail($id);
            $oldValue = $staff->toArray();

            $fields = ['full_name', 'position', 'unit', 'user_id'];
            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    $staff->{$field} = $data[$field];
                }
            }

            $staff->save();

            $this->logAudit(
                $request->user()?->id,
                'staff.updated',
                'Staff',
                $staff->id,
                $oldValue,
                $staff->toArray(),
                $request
            );

            return $staff;
        });
    }

    /**
     * BE-MST-03: Delete staff.
     */
    public function deleteStaff(string $id, Request $request): void
    {
        $staff = Staff::findOrFail($id);
        $oldValue = $staff->toArray();

        $staff->delete();

        $this->logAudit(
            $request->user()?->id,
            'staff.deleted',
            'Staff',
            $staff->id,
            $oldValue,
            null,
            $request
        );
    }

    /**
     * BE-MST-03: Import staff from CSV with row-by-row validation.
     */
    public function importStaff(UploadedFile $file, Request $request): array
    {
        $path = $file->getRealPath();
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new HttpException(400, 'Berkas tidak dapat dibaca.', null, [], 400);
        }

        $firstLine = fgets($handle);
        $delimiter = str_contains($firstLine, ';') ? ';' : ',';
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter);
        if (! $header) {
            fclose($handle);
            throw new HttpException(400, 'Berkas CSV kosong atau tidak memiliki baris header.', null, [], 400);
        }

        $headerMap = [];
        foreach ($header as $idx => $col) {
            $headerMap[strtolower(trim($col, " \t\n\r\0\x0B\"'"))] = $idx;
        }

        if (! isset($headerMap['full_name'])) {
            fclose($handle);
            throw new HttpException(400, "Format kolom CSV tidak sesuai: kolom 'full_name' wajib ada di header.", null, [], 400);
        }

        $rowNumber = 1;
        $errors = [];
        $recordsToInsert = [];

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;

            if (count(array_filter($row)) === 0) {
                continue;
            }

            $fullName = isset($headerMap['full_name']) && isset($row[$headerMap['full_name']])
                ? trim($row[$headerMap['full_name']])
                : '';
            $position = isset($headerMap['position']) && isset($row[$headerMap['position']])
                ? trim($row[$headerMap['position']])
                : null;
            $unit = isset($headerMap['unit']) && isset($row[$headerMap['unit']])
                ? trim($row[$headerMap['unit']])
                : null;

            if (empty($fullName)) {
                $errors[] = [
                    'row' => $rowNumber,
                    'messages' => ['Nama lengkap staf wajib diisi'],
                ];
            } else {
                $recordsToInsert[] = [
                    'full_name' => $fullName,
                    'position' => $position,
                    'unit' => $unit,
                ];
            }
        }

        fclose($handle);

        if (! empty($errors)) {
            return [
                'success' => false,
                'total' => count($recordsToInsert) + count($errors),
                'imported' => 0,
                'failed' => count($errors),
                'errors' => $errors,
            ];
        }

        DB::transaction(function () use ($recordsToInsert, $request) {
            foreach ($recordsToInsert as $item) {
                Staff::create([
                    'school_id' => $request->user()?->school_id,
                    'full_name' => $item['full_name'],
                    'position' => $item['position'],
                    'unit' => $item['unit'],
                ]);
            }

            $this->logAudit(
                $request->user()?->id,
                'staff.imported',
                'Staff',
                null,
                null,
                ['count' => count($recordsToInsert)],
                $request
            );
        });

        return [
            'success' => true,
            'total' => count($recordsToInsert),
            'imported' => count($recordsToInsert),
            'failed' => 0,
            'errors' => [],
        ];
    }

    /**
     * Helper to write an audit log entry.
     */
    protected function logAudit(
        ?string $actorId,
        string $action,
        string $entity,
        ?string $entityId,
        ?array $oldValue,
        ?array $newValue,
        Request $request
    ): void {
        try {
            AuditLog::create([
                'actor_id' => $actorId,
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // =========================================================================
    // SLIP GAJI & REKAPITULASI JAM MENGAJAR (JJM)
    // =========================================================================

    /**
     * BE-MST-03 / TCH-SAL-01: List salary slips for a teacher.
     */
    public function listSalarySlips(string $teacherId, Request $request): Collection
    {
        $teacher = Teacher::findOrFail($teacherId);
        $user = $request->user();

        if (! $this->canAccessTeacherSalary($user, $teacher)) {
            throw new HttpException(403, 'Anda tidak memiliki hak akses untuk melihat slip gaji guru ini.');
        }

        return TeacherSalarySlip::where('teacher_id', $teacherId)
            ->with(['file', 'creator'])
            ->orderBy('period_year', 'desc')
            ->orderBy('period_month', 'desc')
            ->get();
    }

    /**
     * BE-MST-03 / TCH-SAL-01: Store / upload salary slip for a teacher.
     */
    public function storeSalarySlip(string $teacherId, array $data, Request $request): TeacherSalarySlip
    {
        $teacher = Teacher::findOrFail($teacherId);
        $user = $request->user();

        if (! $user || (! $user->hasPermission('master.teacher.manage') && ! $user->hasRole(['super_admin', 'admin', 'staff_tu']))) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mengunggah slip gaji.');
        }

        $month = (int) $data['period_month'];
        $year = (int) $data['period_year'];

        $exists = TeacherSalarySlip::where('teacher_id', $teacherId)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->exists();

        if ($exists) {
            throw new HttpException(422, "Slip gaji untuk periode bulan {$month} tahun {$year} sudah pernah diunggah. Hapus slip sebelumnya bila ingin mengganti berkas.");
        }

        return DB::transaction(function () use ($teacher, $teacherId, $month, $year, $data, $request, $user) {
            $fileId = null;

            if ($request->hasFile('file')) {
                $uploaded = $request->file('file');
                $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
                if (! in_array($uploaded->getMimeType(), $allowedMimes, true)) {
                    throw new HttpException(422, 'Berkas slip gaji harus berformat PDF atau gambar (PNG/JPG).');
                }

                $storagePath = $uploaded->store("salary-slips/{$teacherId}", 'local');
                $checksum = hash_file('sha256', $uploaded->getRealPath());

                $fileRecord = File::create([
                    'school_id' => $teacher->school_id ?? $user->school_id,
                    'owner_id' => $teacher->user_id ?? $user->id,
                    'owner_type' => 'Teacher',
                    'bucket' => 'salary-slips',
                    'object_key' => $storagePath,
                    'mime_type' => $uploaded->getMimeType() ?: 'application/pdf',
                    'size_bytes' => $uploaded->getSize(),
                    'visibility' => 'privat',
                    'checksum' => $checksum,
                ]);

                $fileId = $fileRecord->id;
            }

            $basicSalary = (float) ($data['basic_salary'] ?? 0);
            $allowances = (float) ($data['allowances'] ?? 0);
            $deductions = (float) ($data['deductions'] ?? 0);
            $netSalary = isset($data['net_salary']) && $data['net_salary'] !== '' && $data['net_salary'] !== null
                ? (float) $data['net_salary']
                : ($basicSalary + $allowances - $deductions);

            $monthPad = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
            $slipNumber = ! empty($data['slip_number'])
                ? trim($data['slip_number'])
                : "PAY-{$year}/{$monthPad}/".strtoupper(substr(str_replace('-', '', $teacherId), 0, 4));

            $salarySlip = TeacherSalarySlip::create([
                'teacher_id' => $teacherId,
                'slip_number' => $slipNumber,
                'period_month' => $month,
                'period_year' => $year,
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'basic_salary' => $basicSalary,
                'allowances' => $allowances,
                'deductions' => $deductions,
                'net_salary' => $netSalary,
                'status' => $data['status'] ?? 'published',
                'file_id' => $fileId,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $this->logAudit(
                $user->id,
                'teacher.salary_slip.created',
                'TeacherSalarySlip',
                $salarySlip->id,
                null,
                [
                    'teacher_id' => $teacherId,
                    'teacher_name' => $teacher->full_name,
                    'period' => "{$month}/{$year}",
                    'net_salary' => $netSalary,
                    'has_file' => (bool) $fileId,
                ],
                $request
            );

            return $salarySlip->load(['file', 'creator']);
        });
    }

    /**
     * BE-MST-03 / TCH-SAL-01: Get detail of a specific salary slip.
     */
    public function getSalarySlip(string $teacherId, string $slipId, Request $request): TeacherSalarySlip
    {
        $teacher = Teacher::findOrFail($teacherId);
        $user = $request->user();

        if (! $this->canAccessTeacherSalary($user, $teacher)) {
            throw new HttpException(403, 'Anda tidak memiliki hak akses untuk melihat slip gaji ini.');
        }

        return TeacherSalarySlip::where('teacher_id', $teacherId)
            ->where('id', $slipId)
            ->with(['file', 'creator', 'teacher'])
            ->firstOrFail();
    }

    /**
     * BE-MST-03 / TCH-SAL-01: Download salary slip file securely.
     */
    public function downloadSalarySlip(string $teacherId, string $slipId, Request $request): StreamedResponse
    {
        $salarySlip = $this->getSalarySlip($teacherId, $slipId, $request);

        if (! $salarySlip->file) {
            throw new HttpException(404, 'Berkas fisik slip gaji tidak ditemukan pada sistem.');
        }

        $file = $salarySlip->file;
        if (! Storage::disk('local')->exists($file->object_key)) {
            throw new HttpException(404, 'Berkas penyimpanan lokal tidak ditemukan atau telah dipindahkan.');
        }

        $downloadFilename = "Slip_Gaji_{$salarySlip->teacher->full_name}_{$salarySlip->period_year}_{$salarySlip->period_month}.pdf";

        return Storage::disk('local')->download(
            $file->object_key,
            $downloadFilename,
            [
                'Content-Type' => $file->mime_type ?: 'application/pdf',
                'Cache-Control' => 'private, no-cache, must-revalidate',
            ]
        );
    }

    /**
     * BE-MST-03 / TCH-SAL-01: Delete salary slip.
     */
    public function deleteSalarySlip(string $teacherId, string $slipId, Request $request): void
    {
        $teacher = Teacher::findOrFail($teacherId);
        $user = $request->user();

        if (! $user || (! $user->hasPermission('master.teacher.manage') && ! $user->hasRole(['super_admin', 'admin', 'staff_tu']))) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk menghapus slip gaji.');
        }

        $salarySlip = TeacherSalarySlip::where('teacher_id', $teacherId)
            ->where('id', $slipId)
            ->firstOrFail();

        $oldData = $salarySlip->toArray();
        $salarySlip->delete();

        $this->logAudit(
            $user->id,
            'teacher.salary_slip.deleted',
            'TeacherSalarySlip',
            $slipId,
            $oldData,
            null,
            $request
        );
    }

    /**
     * TCH-SAL-02: Get authenticated teacher's own salary slips (Self-service portal).
     */
    public function getMySalarySlips(Request $request): Collection
    {
        $user = $request->user();
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        $teacher = Teacher::where('user_id', $user->id)->first();
        if (! $teacher) {
            return collect([]);
        }

        return TeacherSalarySlip::where('teacher_id', $teacher->id)
            ->where('status', '!=', 'draft')
            ->with('file')
            ->orderBy('period_year', 'desc')
            ->orderBy('period_month', 'desc')
            ->get();
    }

    /**
     * TCH-SAL-02: Download authenticated teacher's own salary slip (Self-service portal).
     */
    public function downloadMySalarySlip(string $slipId, Request $request): StreamedResponse
    {
        $user = $request->user();
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        $teacher = Teacher::where('user_id', $user->id)->first();
        if (! $teacher) {
            return collect([]);
        }

        $salarySlip = TeacherSalarySlip::with('teacher')->find($slipId);
        if (! $salarySlip) {
            throw new HttpException(404, 'Slip gaji tidak ditemukan.');
        }

        if ($salarySlip->teacher_id !== $teacher->id && ! $this->canAccessTeacherSalary($user, $salarySlip->teacher)) {
            throw new HttpException(403, 'Anda tidak memiliki hak akses untuk mengunduh slip gaji ini.');
        }

        return $this->downloadSalarySlip($salarySlip->teacher_id, $slipId, $request);
    }

    /**
     * TCH-JJM-01: Get teaching hours recap for teacher (JJM).
     */
    public function getTeachingHoursRecap(string $teacherId): array
    {
        $teacher = Teacher::with([
            'teachingAssignments.subject',
            'teachingAssignments.class',
            'teachingAssignments.semester',
            'homeroomClasses',
        ])->findOrFail($teacherId);

        $assignments = $teacher->teachingAssignments;
        $totalHours = (int) $assignments->sum('hours_per_week');
        $targetHours = 24;

        $breakdown = $assignments->map(function ($ta) {
            return [
                'id' => $ta->id,
                'subject_id' => $ta->subject_id,
                'subject_name' => $ta->subject?->name ?? 'Mata Pelajaran',
                'subject_code' => $ta->subject?->code ?? '-',
                'class_id' => $ta->class_id,
                'class_name' => $ta->class?->name ?? '-',
                'semester_name' => $ta->semester?->name ?? '-',
                'hours_per_week' => (int) ($ta->hours_per_week ?? 2),
            ];
        });

        return [
            'teacher' => [
                'id' => $teacher->id,
                'full_name' => $teacher->full_name,
                'nip' => $teacher->nip,
                'status' => $teacher->status,
            ],
            'total_hours' => $totalHours,
            'target_hours' => $targetHours,
            'is_target_met' => $totalHours >= $targetHours,
            'difference' => $totalHours - $targetHours,
            'assignments_count' => $assignments->count(),
            'homeroom_count' => $teacher->homeroomClasses->count(),
            'assignments' => $breakdown,
        ];
    }

    /**
     * Helper to verify if user has access to view a teacher's salary information (IDOR safe).
     */
    protected function canAccessTeacherSalary(?User $user, Teacher $teacher): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'admin', 'staff_tu']) || $user->hasPermission('master.teacher.manage')) {
            return true;
        }

        if ($teacher->user_id && $teacher->user_id === $user->id) {
            return true;
        }

        return false;
    }
}
