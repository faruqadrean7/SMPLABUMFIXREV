<?php

namespace App\Services\Finance;

use App\Models\FeeType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Notification;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoiceGeneratorService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Hasilkan nomor invoice unik: INV/{YYYY}/{MM}/{SEQUENCE}
     * P0-25: Gunakan advisory transaction lock PostgreSQL & query O(1) berurut agar aman dari race condition.
     */
    public function generateInvoiceNumber(): string
    {
        [$prefix, $seq] = $this->reserveNextInvoiceSequence();

        do {
            $candidate = $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            $exists = DB::table('invoices')->where('invoice_number', $candidate)->exists();
            if (! $exists) {
                return $candidate;
            }
            $seq++;
        } while ($exists);

        return $candidate;
    }

    /**
     * Ambil prefix bulan berjalan dan nomor urut berikutnya dengan advisory lock transaksi.
     *
     * @return array{0: string, 1: int}
     */
    protected function reserveNextInvoiceSequence(): array
    {
        $year = date('Y');
        $month = date('m');
        $prefix = "INV/{$year}/{$month}/";

        if (DB::getDriverName() === 'pgsql') {
            DB::selectOne('SELECT pg_advisory_xact_lock(?)', [crc32('finance_invoice_seq:'.$prefix)]);
        }

        $maxSeq = 0;
        if (DB::getDriverName() === 'pgsql') {
            // Ambil angka sequence maksimum langsung di DB (hindari full pluck O(N))
            $row = DB::selectOne(
                "SELECT MAX(CAST(SPLIT_PART(invoice_number, '/', 4) AS INTEGER)) AS max_seq
                 FROM invoices
                 WHERE invoice_number LIKE ? AND SPLIT_PART(invoice_number, '/', 4) ~ '^[0-9]+$'",
                [$prefix.'%']
            );
            $maxSeq = (int) ($row->max_seq ?? 0);
        } else {
            $existing = DB::table('invoices')
                ->where('invoice_number', 'like', $prefix.'%')
                ->pluck('invoice_number');
            foreach ($existing as $invNum) {
                $parts = explode('/', $invNum);
                $last = end($parts);
                if (ctype_digit($last) && (int) $last > $maxSeq) {
                    $maxSeq = (int) $last;
                }
            }
        }

        return [$prefix, $maxSeq + 1];
    }

    /**
     * Generate tagihan massal idempoten.
     * Tidak akan membuat duplikat jika siswa sudah memiliki tagihan aktif untuk fee_type & periode yang sama.
     */
    public function generateBulk(array $params, User $actor): array
    {
        $feeTypeId = $params['fee_type_id'];
        $feeType = FeeType::findOrFail($feeTypeId);

        $period = $params['period'] ?? date('Y-m');
        $dueDate = $params['due_date'];
        $amount = isset($params['amount']) && (int) $params['amount'] > 0
            ? (int) $params['amount']
            : (int) $feeType->default_amount;

        $targetType = $params['target_type'] ?? 'class';
        $classId = $params['class_id'] ?? null;
        $academicYearId = $params['academic_year_id'] ?? null;
        $notes = $params['notes'] ?? ('Tagihan massal: '.$feeType->name.' periode '.$period);

        // Ambil target siswa
        $studentsQuery = Student::query();
        if ($targetType === 'class') {
            if (! $classId) {
                throw ValidationException::withMessages([
                    'class_id' => ['Kelas wajib dipilih jika target tagihan adalah per kelas.'],
                ]);
            }
            $studentsQuery->whereHas('classes', function ($q) use ($classId) {
                $q->where('classes.id', $classId);
            });
        }

        // Filter hanya siswa yang aktif
        $students = $studentsQuery->whereIn('status', ['aktif', 'active'])
            ->with(['user', 'guardians.user'])
            ->get();

        $createdInvoices = [];
        $skippedCount = 0;

        DB::transaction(function () use (
            $students,
            $feeType,
            $period,
            $dueDate,
            $amount,
            $academicYearId,
            $notes,
            $actor,
            &$createdInvoices,
            &$skippedCount
        ) {
            // P0-25: Advisory lock per (fee_type, period) agar generateBulk paralel tetap idempoten
            if (DB::getDriverName() === 'pgsql') {
                DB::selectOne('SELECT pg_advisory_xact_lock(?)', [crc32("finance_bulk:{$feeType->id}:{$period}")]);
            }

            $actor->loadMissing('roles');

            $studentIds = $students->pluck('id')->all();
            $existingStudentIds = empty($studentIds)
                ? []
                : Invoice::whereIn('student_id', $studentIds)
                    ->where('period', $period)
                    ->where('status', '!=', 'dibatalkan')
                    ->whereHas('items', function ($q) use ($feeType) {
                        $q->where('fee_type_id', $feeType->id);
                    })
                    ->pluck('student_id')
                    ->map(fn ($id) => (string) $id)
                    ->flip()
                    ->all();

            [$invoicePrefix, $nextSeq] = $this->reserveNextInvoiceSequence();
            $now = now();
            $itemRows = [];
            $notifRows = [];
            $itemDescription = $feeType->name.' - Periode '.$period;
            $formattedAmount = number_format($amount, 0, ',', '.');

            foreach ($students as $student) {
                // Aturan Idempotensi: Cek apakah siswa sudah memiliki tagihan aktif dengan fee_type dan periode yang sama
                if (isset($existingStudentIds[(string) $student->id])) {
                    $skippedCount++;

                    continue;
                }

                // Buat invoice baru menggunakan nomor berurutan yang sudah direservasi
                $invoiceNumber = $invoicePrefix.str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
                $nextSeq++;

                $invoice = Invoice::create([
                    'school_id' => $student->school_id,
                    'invoice_number' => $invoiceNumber,
                    'student_id' => $student->id,
                    'academic_year_id' => $academicYearId,
                    'period' => $period,
                    'due_date' => $dueDate,
                    'total_amount' => $amount,
                    'paid_amount' => 0,
                    'status' => 'belum_bayar',
                    'notes' => $notes,
                ]);

                $itemRows[] = [
                    'id' => (string) Str::uuid(),
                    'invoice_id' => $invoice->id,
                    'fee_type_id' => $feeType->id,
                    'description' => $itemDescription,
                    'amount' => $amount,
                    'discount' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                // Audit log per invoice
                $this->auditLogService->record(
                    action: 'finance.invoice.create',
                    entity: 'Invoice',
                    entityId: $invoice->id,
                    oldValues: null,
                    newValues: [
                        'invoice_number' => $invoice->invoice_number,
                        'student_id' => $student->id,
                        'total_amount' => $amount,
                        'period' => $period,
                    ],
                    reason: 'Generate tagihan massal oleh admin/bendahara',
                    actor: $actor
                );

                // Siapkan notifikasi massal ke siswa jika memiliki akun user
                if ($student->user) {
                    $notifRows[] = [
                        'id' => (string) Str::uuid(),
                        'user_id' => $student->user->id,
                        'notif_type' => 'finance_invoice',
                        'title' => 'Tagihan Baru: '.$feeType->name,
                        'body' => "Tagihan sebesar Rp {$formattedAmount} periode {$period} telah diterbitkan dengan jatuh tempo {$dueDate}.",
                        'data' => json_encode([
                            'invoice_id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'total_amount' => $amount,
                            'due_date' => $dueDate,
                        ]),
                        'created_at' => $now,
                    ];
                }

                // Siapkan notifikasi massal ke wali murid
                foreach ($student->guardians as $guardian) {
                    if ($guardian->user) {
                        $notifRows[] = [
                            'id' => (string) Str::uuid(),
                            'user_id' => $guardian->user->id,
                            'notif_type' => 'finance_invoice',
                            'title' => 'Tagihan Baru: '.$feeType->name.' ('.$student->full_name.')',
                            'body' => "Tagihan biaya {$feeType->name} untuk putra/putri Anda {$student->full_name} sebesar Rp {$formattedAmount} telah diterbitkan. Jatuh tempo: {$dueDate}.",
                            'data' => json_encode([
                                'invoice_id' => $invoice->id,
                                'invoice_number' => $invoice->invoice_number,
                                'student_id' => $student->id,
                                'total_amount' => $amount,
                                'due_date' => $dueDate,
                            ]),
                            'created_at' => $now,
                        ];
                    }
                }

                $createdInvoices[] = $invoice;
            }

            foreach (array_chunk($itemRows, 500) as $chunk) {
                InvoiceItem::insert($chunk);
            }

            foreach (array_chunk($notifRows, 500) as $chunk) {
                Notification::insert($chunk);
            }
        });

        return [
            'total_students' => $students->count(),
            'created_count' => count($createdInvoices),
            'skipped_count' => $skippedCount,
            'fee_type' => [
                'id' => $feeType->id,
                'name' => $feeType->name,
                'code' => $feeType->code,
            ],
            'period' => $period,
            'amount' => $amount,
            'created_invoices' => $createdInvoices,
        ];
    }
}
