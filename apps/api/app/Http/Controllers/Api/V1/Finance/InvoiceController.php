<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Finance\FinanceService;
use App\Services\Finance\InvoiceGeneratorService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected FinanceService $financeService,
        protected InvoiceGeneratorService $invoiceGeneratorService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Invoice::query()
            ->with(['student.classes', 'items.feeType']);

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->input('student_id'));
        }

        if ($request->filled('class_id')) {
            $classId = $request->input('class_id');
            $query->whereHas('student.classes', function ($q) use ($classId) {
                $q->where('classes.id', $classId);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('period')) {
            $query->where('period', $request->input('period'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'ilike', "%{$search}%")
                  ->orWhere('va_number', 'ilike', "%{$search}%")
                  ->orWhereHas('student', function ($sq) use ($search) {
                      $sq->where('full_name', 'ilike', "%{$search}%")
                         ->orWhere('nis', 'ilike', "%{$search}%")
                         ->orWhere('nisn', 'ilike', "%{$search}%");
                  });
            });
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 15)));
        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->paginatedResponse($paginator);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'academic_year_id' => ['nullable', 'uuid', 'exists:academic_years,id'],
            'period' => ['nullable', 'string', 'max:20'],
            'va_number' => ['nullable', 'string', 'max:50'],
            'due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.fee_type_id' => ['required', 'uuid', 'exists:fee_types,id'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.amount' => ['required', 'integer', 'min:1'],
            'items.*.discount' => ['nullable', 'integer', 'min:0'],
        ]);

        $invoice = $this->financeService->createInvoice($validated, $request->user());

        return $this->successResponse($invoice, 201);
    }

    public function show(string $id): JsonResponse
    {
        $invoice = Invoice::with([
            'student.classes.academicYear',
            'items.feeType',
            'payments.proofFile',
            'payments.verifiedBy',
            'payments.submittedBy',
        ])->findOrFail($id);

        return $this->successResponse($invoice);
    }

    public function generateBulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fee_type_id' => ['required', 'uuid', 'exists:fee_types,id'],
            'academic_year_id' => ['nullable', 'uuid', 'exists:academic_years,id'],
            'period' => ['required', 'string', 'max:20'],
            'due_date' => ['required', 'date'],
            'amount' => ['nullable', 'integer', 'min:1'],
            'target_type' => ['required', 'in:class,all_active'],
            'class_id' => ['nullable', 'required_if:target_type,class', 'uuid', 'exists:classes,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $result = $this->invoiceGeneratorService->generateBulk($validated, $request->user());

        return $this->successResponse($result, 201);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $cancelled = $this->financeService->cancelInvoice($invoice, $validated['reason'], $request->user());

        return $this->successResponse($cancelled);
    }
}
