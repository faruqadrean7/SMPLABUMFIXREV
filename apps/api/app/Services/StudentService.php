<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Student;
use App\Models\StudentStatusHistory;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StudentService
{
    /**
     * BE-MST-02: List students with filters and search.
     * Scoped based on user role and permissions.
     */
    public function listStudents(Request $request): LengthAwarePaginator
    {
        $user = $request->user();
        $limit = min($request->integer('limit', 20), 100);
        $status = $request->query('status');
        $classId = $request->query('class_id');
        $q = trim((string) $request->query('q', ''));

        $query = Student::with([
            'photoFile',
            'classes' => function ($q) {
                $q->whereNull('class_students.left_at');
            },
        ]);

        // Role & Scoping restrictions
        $this->applyDataScoping($query, $user);

        // Filter by class_id
        if ($classId) {
            $query->whereHas('classes', function ($q) use ($classId) {
                $q->where('classes.id', $classId)
                    ->whereNull('class_students.left_at');
            });
        }

        // Filter by without_class (siswa belum memiliki rombel aktif)
        if ($request->boolean('without_class')) {
            $academicYearId = $request->query('academic_year_id');
            $query->whereDoesntHave('classes', function ($q) use ($academicYearId) {
                $q->whereNull('class_students.left_at');
                if ($academicYearId) {
                    $q->where('classes.academic_year_id', $academicYearId);
                }
            });
        }

        // Filter by status
        if ($status) {
            $query->where('status', $status);
        }

        // Search q (nis, nisn, full_name)
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('nis', 'like', "%{$q}%")
                    ->orWhere('nisn', 'like', "%{$q}%")
                    ->orWhere('full_name', 'like', "%{$q}%");
            });
        }

        return $query->orderBy('full_name', 'asc')->paginate($limit);
    }

    /**
     * BE-MST-02: Get single student detail with IDOR verification.
     */
    public function getStudent(string $id, Request $request): Student
    {
        $student = Student::with([
            'photoFile',
            'classes' => function ($q) {
                $q->whereNull('class_students.left_at');
            },
            'guardians',
            'statusHistory' => function ($q) {
                $q->orderBy('effective_date', 'desc')->orderBy('created_at', 'desc');
            },
        ])->findOrFail($id);

        if ($request->user()) {
            $this->verifyStudentAccess($student, $request->user());
        }

        return $student;
    }

    /**
     * BE-MST-02: Create new student with initial status history and encrypted NIK.
     */
    public function createStudent(array $data, Request $request): Student
    {
        return DB::transaction(function () use ($data, $request) {
            $status = $data['status'] ?? 'aktif';

            $student = Student::create([
                'school_id' => $data['school_id'] ?? $request->user()?->school_id,
                'nis' => $data['nis'],
                'nisn' => $data['nisn'],
                'nik_enc' => $data['nik'],
                'full_name' => $data['full_name'],
                'gender' => $data['gender'],
                'birth_place' => $data['birth_place'],
                'birth_date' => $data['birth_date'],
                'religion' => $data['religion'] ?? null,
                'address' => $data['address'] ?? null,
                'photo_file_id' => $data['photo_file_id'] ?? null,
                'status' => $status,
                'entry_date' => $data['entry_date'],
                'program_type' => $data['program_type'] ?? 'reguler',
            ]);

            // Initial status history entry
            StudentStatusHistory::create([
                'student_id' => $student->id,
                'status' => $status,
                'reason' => 'Pendaftaran siswa baru',
                'effective_date' => $data['entry_date'],
            ]);

            $this->logAudit(
                $request->user()?->id,
                'student.created',
                'Student',
                $student->id,
                null,
                $this->maskSensitiveData($student->toArray()),
                $request
            );

            return $student;
        });
    }

    /**
     * BE-MST-02: Update student details and track status change if applicable.
     */
    public function updateStudent(string $id, array $data, Request $request): Student
    {
        return DB::transaction(function () use ($id, $data, $request) {
            $student = Student::findOrFail($id);
            $oldValue = $this->maskSensitiveData($student->toArray());

            $newStatus = $data['status'] ?? null;
            if ($newStatus && $newStatus !== $student->status) {
                $effectiveDate = $data['status_effective_date'] ?? now()->toDateString();
                $reason = $data['status_reason'] ?? 'Perubahan status siswa';

                $student->status = $newStatus;
                if ($newStatus === 'lulus' && ! $student->graduated_at) {
                    $student->graduated_at = $effectiveDate;
                }

                StudentStatusHistory::create([
                    'student_id' => $student->id,
                    'status' => $newStatus,
                    'reason' => $reason,
                    'effective_date' => $effectiveDate,
                ]);
            }

            if (isset($data['nik'])) {
                $student->nik_enc = $data['nik'];
            }

            $fields = [
                'nis', 'nisn', 'full_name', 'gender', 'birth_place',
                'birth_date', 'religion', 'address', 'photo_file_id',
                'entry_date', 'graduated_at', 'program_type',
            ];

            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    $student->{$field} = $data[$field];
                }
            }

            $student->save();

            $this->logAudit(
                $request->user()?->id,
                'student.updated',
                'Student',
                $student->id,
                $oldValue,
                $this->maskSensitiveData($student->toArray()),
                $request
            );

            return $student;
        });
    }

    /**
     * BE-MST-02: Change student status specifically with reason and effective date.
     */
    public function changeStudentStatus(string $id, array $data, Request $request): Student
    {
        return DB::transaction(function () use ($id, $data, $request) {
            $student = Student::findOrFail($id);
            $oldStatus = $student->status;
            $newStatus = $data['status'];

            $student->status = $newStatus;
            if ($newStatus === 'lulus' && ! $student->graduated_at) {
                $student->graduated_at = $data['effective_date'];
            }
            $student->save();

            StudentStatusHistory::create([
                'student_id' => $student->id,
                'status' => $newStatus,
                'reason' => $data['reason'] ?? null,
                'effective_date' => $data['effective_date'],
            ]);

            $this->logAudit(
                $request->user()?->id,
                'student.status_changed',
                'Student',
                $student->id,
                ['status' => $oldStatus],
                ['status' => $newStatus, 'reason' => $data['reason'] ?? null, 'effective_date' => $data['effective_date']],
                $request
            );

            return $student;
        });
    }

    /**
     * BE-MST-02: Get status history of student.
     */
    public function getStatusHistory(string $id, Request $request): Collection
    {
        $student = Student::findOrFail($id);

        if ($request->user()) {
            $this->verifyStudentAccess($student, $request->user());
        }

        return StudentStatusHistory::where('student_id', $id)
            ->orderBy('effective_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * BE-MST-02: Soft delete student.
     */
    public function deleteStudent(string $id, Request $request): void
    {
        $student = Student::findOrFail($id);
        $oldValue = $this->maskSensitiveData($student->toArray());

        $student->delete();

        $this->logAudit(
            $request->user()?->id,
            'student.deleted',
            'Student',
            $student->id,
            $oldValue,
            null,
            $request
        );
    }

    /**
     * BE-MST-02: Import students from CSV with comprehensive row-by-row validation.
     */
    public function importStudents(UploadedFile $file, Request $request): array
    {
        $path = $file->getRealPath();
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new HttpException(400, 'Berkas tidak dapat dibaca.', null, [], 400);
        }

        // Detect delimiter
        $firstLine = fgets($handle);
        $delimiter = str_contains($firstLine, ';') ? ';' : ',';
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter);
        if (! $header) {
            fclose($handle);
            throw new HttpException(400, 'Berkas CSV kosong atau tidak memiliki baris header.', null, [], 400);
        }

        // Normalize header keys: trim, lowercase, remove quotes
        $headerMap = [];
        foreach ($header as $idx => $col) {
            $colName = strtolower(trim($col, " \t\n\r\0\x0B\"'"));
            $headerMap[$colName] = $idx;
        }

        $requiredHeaders = ['nis', 'nisn', 'nik', 'full_name', 'gender', 'birth_place', 'birth_date', 'entry_date'];
        foreach ($requiredHeaders as $req) {
            if (! isset($headerMap[$req])) {
                fclose($handle);
                throw new HttpException(400, "Format kolom CSV tidak sesuai: kolom '{$req}' wajib ada di header.", null, [], 400);
            }
        }

        $rowNumber = 1; // header is row 1
        $errors = [];
        $recordsToInsert = [];
        $seenNis = [];
        $seenNisn = [];

        // Preload existing NIS and NISN
        $existingNis = Student::pluck('nis')->flip()->toArray();
        $existingNisn = Student::pluck('nisn')->flip()->toArray();

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;

            // Skip empty rows
            if (count(array_filter($row)) === 0) {
                continue;
            }

            $rowData = [];
            foreach ($headerMap as $key => $index) {
                $rowData[$key] = isset($row[$index]) ? trim($row[$index]) : '';
            }

            $rowErrors = [];

            // 1. NIS validation
            if (empty($rowData['nis'])) {
                $rowErrors[] = 'NIS wajib diisi';
            } elseif (isset($existingNis[$rowData['nis']]) || isset($seenNis[$rowData['nis']])) {
                $rowErrors[] = "NIS '{$rowData['nis']}' sudah terdaftar";
            } else {
                $seenNis[$rowData['nis']] = true;
            }

            // 2. NISN validation
            if (empty($rowData['nisn'])) {
                $rowErrors[] = 'NISN wajib diisi';
            } elseif (isset($existingNisn[$rowData['nisn']]) || isset($seenNisn[$rowData['nisn']])) {
                $rowErrors[] = "NISN '{$rowData['nisn']}' sudah terdaftar";
            } else {
                $seenNisn[$rowData['nisn']] = true;
            }

            // 3. NIK validation
            if (empty($rowData['nik'])) {
                $rowErrors[] = 'NIK wajib diisi';
            } elseif (! preg_match('/^[0-9]{16}$/', $rowData['nik'])) {
                $rowErrors[] = 'NIK harus 16 digit angka';
            }

            // 4. Nama lengkap
            if (empty($rowData['full_name'])) {
                $rowErrors[] = 'Nama lengkap wajib diisi';
            }

            // 5. Gender
            $gender = strtoupper($rowData['gender']);
            if (! in_array($gender, ['L', 'P'], true)) {
                $rowErrors[] = 'Jenis kelamin harus L atau P';
            }

            // 6. Tanggal lahir
            if (empty($rowData['birth_date']) || ! strtotime($rowData['birth_date'])) {
                $rowErrors[] = 'Format tanggal lahir tidak valid (gunakan YYYY-MM-DD)';
            }

            // 7. Tempat lahir
            if (empty($rowData['birth_place'])) {
                $rowErrors[] = 'Tempat lahir wajib diisi';
            }

            // 8. Tanggal masuk
            if (empty($rowData['entry_date']) || ! strtotime($rowData['entry_date'])) {
                $rowErrors[] = 'Format tanggal masuk tidak valid (gunakan YYYY-MM-DD)';
            }

            if (! empty($rowErrors)) {
                $errors[] = [
                    'row' => $rowNumber,
                    'messages' => $rowErrors,
                ];
            } else {
                $recordsToInsert[] = [
                    'nis' => $rowData['nis'],
                    'nisn' => $rowData['nisn'],
                    'nik' => $rowData['nik'],
                    'full_name' => $rowData['full_name'],
                    'gender' => $gender,
                    'birth_place' => $rowData['birth_place'],
                    'birth_date' => $rowData['birth_date'],
                    'religion' => $rowData['religion'] ?? null,
                    'address' => $rowData['address'] ?? null,
                    'entry_date' => $rowData['entry_date'],
                    'status' => ! empty($rowData['status']) ? strtolower($rowData['status']) : 'aktif',
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

        // Commit all valid records
        DB::transaction(function () use ($recordsToInsert, $request) {
            foreach ($recordsToInsert as $item) {
                $student = Student::create([
                    'school_id' => $request->user()?->school_id,
                    'nis' => $item['nis'],
                    'nisn' => $item['nisn'],
                    'nik_enc' => $item['nik'],
                    'full_name' => $item['full_name'],
                    'gender' => $item['gender'],
                    'birth_place' => $item['birth_place'],
                    'birth_date' => $item['birth_date'],
                    'religion' => $item['religion'],
                    'address' => $item['address'],
                    'status' => $item['status'],
                    'entry_date' => $item['entry_date'],
                ]);

                StudentStatusHistory::create([
                    'student_id' => $student->id,
                    'status' => $item['status'],
                    'reason' => 'Import data siswa',
                    'effective_date' => $item['entry_date'],
                ]);
            }

            $this->logAudit(
                $request->user()?->id,
                'student.imported',
                'Student',
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
     * BE-MST-02: Export students to CSV stream with filters and privacy protection.
     */
    public function exportStudents(Request $request): StreamedResponse
    {
        $status = $request->query('status');
        $classId = $request->query('class_id');
        $q = trim((string) $request->query('q', ''));

        $query = Student::with(['classes' => function ($q) {
            $q->whereNull('class_students.left_at');
        }]);

        if ($classId) {
            $query->whereHas('classes', function ($q) use ($classId) {
                $q->where('classes.id', $classId)->whereNull('class_students.left_at');
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('nis', 'like', "%{$q}%")
                    ->orWhere('nisn', 'like', "%{$q}%")
                    ->orWhere('full_name', 'like', "%{$q}%");
            });
        }

        $totalExported = $query->count();

        // Audit log export action
        $this->logAudit(
            $request->user()?->id,
            'student.exported',
            'Student',
            null,
            null,
            [
                'total' => $totalExported,
                'filters' => [
                    'status' => $status,
                    'class_id' => $classId,
                    'q' => $q,
                ],
            ],
            $request
        );

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="data-siswa-'.now()->format('Ymd_His').'.csv"',
        ];

        return response()->stream(function () use ($query) {
            $output = fopen('php://output', 'w');

            // Add UTF-8 BOM for Excel compatibility
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            // Write header
            fputcsv($output, [
                'NIS',
                'NISN',
                'Nama Lengkap',
                'Jenis Kelamin',
                'Tempat Lahir',
                'Tanggal Lahir',
                'Agama',
                'Alamat',
                'Status',
                'Kelas Saat Ini',
                'Tanggal Masuk',
            ]);

            $query->chunk(200, function ($students) use ($output) {
                foreach ($students as $student) {
                    $activeClass = $student->classes->first()?->name ?? '-';

                    fputcsv($output, [
                        $student->nis,
                        $student->nisn,
                        $student->full_name,
                        $student->gender,
                        $student->birth_place,
                        $student->birth_date?->format('Y-m-d'),
                        $student->religion ?? '-',
                        $student->address ?? '-',
                        $student->status,
                        $activeClass,
                        $student->entry_date?->format('Y-m-d'),
                    ]);
                }
            });

            fclose($output);
        }, 200, $headers);
    }

    /**
     * Apply data scoping based on user roles and relationships.
     */
    protected function applyDataScoping($query, ?User $user): void
    {
        if (! $user) {
            return;
        }

        // Super admin and master.student.read see all
        if ($user->hasRole('super_admin') || $user->hasPermission('master.student.read')) {
            return;
        }

        // Student only sees self
        if ($user->hasRole('student')) {
            $query->where('user_id', $user->id);

            return;
        }

        // Guardian only sees their children
        if ($user->hasRole('guardian')) {
            $query->whereHas('guardians', function ($g) use ($user) {
                $g->where('guardians.user_id', $user->id);
            });

            return;
        }

        // Homeroom teacher sees their class students
        if ($user->hasRole('homeroom_teacher') || $user->hasRole('teacher')) {
            $query->whereHas('classes', function ($c) use ($user) {
                $c->whereHas('homeroomTeacher', function ($ht) use ($user) {
                    $ht->where('user_id', $user->id);
                })->whereNull('class_students.left_at');
            });

            return;
        }

        // Deny-by-default for other roles without permissions
        $query->whereRaw('1 = 0');
    }

    /**
     * Verify individual student access (IDOR protection).
     */
    protected function verifyStudentAccess(Student $student, User $user): void
    {
        if ($user->hasRole('super_admin') || $user->hasPermission('master.student.read')) {
            return;
        }

        if ($student->user_id === $user->id) {
            return;
        }

        if ($user->hasRole('guardian')) {
            $isLinked = DB::table('student_guardians')
                ->join('guardians', 'guardians.id', '=', 'student_guardians.guardian_id')
                ->where('guardians.user_id', $user->id)
                ->where('student_guardians.student_id', $student->id)
                ->exists();

            if ($isLinked) {
                return;
            }
        }

        if ($user->hasRole('homeroom_teacher') || $user->hasRole('teacher')) {
            $isHomeroom = DB::table('class_students')
                ->join('classes', 'classes.id', '=', 'class_students.class_id')
                ->join('teachers', 'teachers.id', '=', 'classes.homeroom_teacher_id')
                ->where('teachers.user_id', $user->id)
                ->where('class_students.student_id', $student->id)
                ->whereNull('class_students.left_at')
                ->exists();

            if ($isHomeroom) {
                return;
            }
        }

        throw new HttpException(403, 'Anda tidak memiliki hak akses untuk melihat data siswa ini.', null, [], 403);
    }

    /**
     * Mask NIK for privacy compliance in audit logs.
     */
    protected function maskSensitiveData(array $data): array
    {
        if (isset($data['nik_enc'])) {
            $nik = (string) $data['nik_enc'];
            $data['nik_enc'] = strlen($nik) >= 8
                ? substr($nik, 0, 4).'********'.substr($nik, -4)
                : '********';
        }

        return $data;
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
}
