<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Finance\FinanceService;
use App\Services\Finance\ReceiptService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected FinanceService $financeService,
        protected ReceiptService $receiptService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Payment::query()
            ->with([
                'invoice.student.classes',
                'submittedBy',
                'verifiedBy',
                'proofFile',
            ]);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('channel')) {
            $query->where('channel', 'ilike', "%" . $request->input('channel') . "%");
        }

        if ($request->filled('external_reference')) {
            $query->where('external_reference', 'ilike', "%" . $request->input('external_reference') . "%");
        }

        if ($request->filled('date')) {
            $query->where('payment_date', $request->input('date'));
        }

        if ($request->filled('class_id')) {
            $classId = $request->input('class_id');
            $query->whereHas('invoice.student.classes', function ($cq) use ($classId) {
                $cq->where('classes.id', $classId);
            });
        }

        if ($request->filled('invoice_id')) {
            $query->where('invoice_id', $request->input('invoice_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('receipt_number', 'ilike', "%{$search}%")
                  ->orWhere('external_reference', 'ilike', "%{$search}%")
                  ->orWhere('channel', 'ilike', "%{$search}%")
                  ->orWhereHas('invoice', function ($iq) use ($search) {
                      $iq->where('invoice_number', 'ilike', "%{$search}%")
                         ->orWhereHas('student', function ($sq) use ($search) {
                             $sq->where('full_name', 'ilike', "%{$search}%")
                                ->orWhere('nis', 'ilike', "%{$search}%");
                         });
                  });
            });
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 15)));
        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->paginatedResponse($paginator);
    }

    public function show(string $id): JsonResponse
    {
        $payment = Payment::with([
            'invoice.student.classes',
            'invoice.items.feeType',
            'submittedBy',
            'verifiedBy',
            'proofFile',
        ])->findOrFail($id);

        return $this->successResponse($payment);
    }

    public function verify(Request $request, string $id): JsonResponse
    {
        $payment = Payment::findOrFail($id);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $verified = $this->financeService->verifyPayment($payment, $validated['notes'] ?? null, $request->user());

        return $this->successResponse($verified);
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $payment = Payment::findOrFail($id);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $rejected = $this->financeService->rejectPayment($payment, $validated['reason'], $request->user());

        return $this->successResponse($rejected);
    }

    public function void(Request $request, string $id): JsonResponse
    {
        $payment = Payment::findOrFail($id);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $voided = $this->financeService->voidPayment($payment, $validated['reason'], $request->user());

        return $this->successResponse($voided);
    }

    public function receipt(string $id): JsonResponse
    {
        $payment = Payment::findOrFail($id);

        if ($payment->status !== 'terverifikasi') {
            return $this->errorResponse(
                'Kuitansi resmi hanya diterbitkan untuk pembayaran yang telah terverifikasi.',
                'INVALID_STATUS',
                400
            );
        }

        $receiptData = $this->receiptService->buildReceiptData($payment);

        return $this->successResponse($receiptData);
    }
}
