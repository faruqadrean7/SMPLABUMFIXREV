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
use Illuminate\Validation\ValidationException;

class InvoiceGeneratorService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Hasilkan nomor invoice unik: INV/{YYYY}/{MM}/{SEQUENCE}
     * Contoh: INV/2026/10/0001
     */
    public function generateInvoiceNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        $prefix = "INV/{$year}/{$month}/";

        // Ambil semua invoice bulan ini yang berawalan prefix
        $existing = DB::table('invoices')
            ->where('invoice_number', 'like', $prefix . '%')
            ->pluck('invoice_number');

        $maxSeq = 0;
        foreach ($existing as $invNum) {
            $parts = explode('/', $invNum);
            $last = end($parts);
            if (ctype_digit($last)) {
                $num = (int) $last;
                if ($num > $maxSeq) {
                    $maxSeq = $num;
                }
            }
        }

        $seq = $maxSeq + 1;
        do {
            $candidate = $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            $exists = DB::table('invoices')->where('invoice_number', $candidate)->exists();
            if (! $exists) {
                return $candidate;
            }
            $seq++;
        } while ($exists);

        return $candidate;
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
        $notes = $params['notes'] ?? ("Tagihan massal: " . $feeType->name . " periode " . $period);

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
            foreach ($students as $student) {
                // Aturan Idempotensi: Cek apakah siswa sudah memiliki tagihan aktif dengan fee_type dan periode yang sama
                $alreadyExists = Invoice::where('student_id', $student->id)
                    ->where('period', $period)
                    ->where('status', '!=', 'dibatalkan')
                    ->whereHas('items', function ($q) use ($feeType) {
                        $q->where('fee_type_id', $feeType->id);
                    })
                    ->exists();

                if ($alreadyExists) {
                    $skippedCount++;
                    continue;
                }

                // Buat invoice baru
                $invoiceNumber = $this->generateInvoiceNumber();
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

                // Buat item invoice
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'fee_type_id' => $feeType->id,
                    'description' => $feeType->name . ' - Periode ' . $period,
                    'amount' => $amount,
                    'discount' => 0,
                ]);

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

                // Kirim notifikasi ke siswa jika memiliki akun user
                if ($student->user) {
                    Notification::create([
                        'user_id' => $student->user->id,
                        'notif_type' => 'finance_invoice',
                        'title' => 'Tagihan Baru: ' . $feeType->name,
                        'body' => "Tagihan sebesar Rp " . number_format($amount, 0, ',', '.') . " periode {$period} telah diterbitkan dengan jatuh tempo {$dueDate}.",
                        'data' => [
                            'invoice_id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'total_amount' => $amount,
                            'due_date' => $dueDate,
                        ],
                    ]);
                }

                // Kirim notifikasi ke wali murid
                foreach ($student->guardians as $guardian) {
                    if ($guardian->user) {
                        Notification::create([
                            'user_id' => $guardian->user->id,
                            'notif_type' => 'finance_invoice',
                            'title' => 'Tagihan Baru: ' . $feeType->name . ' (' . $student->full_name . ')',
                            'body' => "Tagihan biaya {$feeType->name} untuk putra/putri Anda {$student->full_name} sebesar Rp " . number_format($amount, 0, ',', '.') . " telah diterbitkan. Jatuh tempo: {$dueDate}.",
                            'data' => [
                                'invoice_id' => $invoice->id,
                                'invoice_number' => $invoice->invoice_number,
                                'student_id' => $student->id,
                                'total_amount' => $amount,
                                'due_date' => $dueDate,
                            ],
                        ]);
                    }
                }

                $createdInvoices[] = $invoice;
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
