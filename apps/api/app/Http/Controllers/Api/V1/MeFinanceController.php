<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentProviderSetting;
use App\Models\Student;
use App\Services\Finance\FinanceService;
use App\Services\Finance\ReceiptService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MeFinanceController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected FinanceService $financeService,
        protected ReceiptService $receiptService
    ) {}

    /**
     * GET /api/v1/me/invoices
     * Daftar tagihan siswa yang sedang login (Read-only).
     */
    public function myInvoices(Request $request): JsonResponse
    {
        $user = $request->user();
        $student = $user->student;

        if (! $student) {
            return $this->errorResponse('Akun ini tidak terhubung dengan data profil siswa.', 'NOT_FOUND', 404);
        }

        $invoices = Invoice::where('student_id', $student->id)
            ->with(['items.feeType', 'payments'])
            ->orderBy('due_date', 'asc')
            ->get();

        return $this->successResponse($invoices);
    }

    /**
     * GET /api/v1/me/children/{child}/invoices
     * Daftar tagihan anak perwalian wali murid.
     * Menggunakan Gate view-child untuk mencegah IDOR.
     */
    public function childInvoices(Request $request, string $child): JsonResponse
    {
        if (! Gate::allows('view-child', $child)) {
            return $this->errorResponse(
                'Anda tidak memiliki hak akses perwalian sah terhadap siswa ini.',
                'FORBIDDEN',
                403
            );
        }

        $student = Student::find($child);
        if (! $student) {
            return $this->errorResponse('Data siswa tidak ditemukan.', 'NOT_FOUND', 404);
        }

        $invoices = Invoice::where('student_id', $student->id)
            ->with(['items.feeType', 'payments.proofFile'])
            ->orderBy('due_date', 'asc')
            ->get();

        return $this->successResponse($invoices);
    }

    /**
     * GET /api/v1/me/invoices/{id}
     * Detail satu tagihan (siswa sendiri atau anak perwalian wali murid).
     */
    public function showInvoice(Request $request, string $id): JsonResponse
    {
        $invoice = Invoice::with([
            'student.classes',
            'items.feeType',
            'payments.proofFile',
            'payments.verifiedBy',
        ])->findOrFail($id);

        if (! Gate::allows('view', $invoice)) {
            return $this->errorResponse(
                'Anda tidak memiliki wewenang untuk melihat data tagihan ini.',
                'FORBIDDEN',
                403
            );
        }

        return $this->successResponse($invoice);
    }

    /**
     * POST /api/v1/me/invoices/{id}/payments
     * Ajukan slip pembayaran pihak ketiga (Ortu).
     */
    public function submitPayment(Request $request, string $id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);

        if (! Gate::allows('pay', $invoice)) {
            return $this->errorResponse(
                'Anda tidak memiliki wewenang untuk mengajukan pembayaran pada tagihan ini.',
                'FORBIDDEN',
                403
            );
        }

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'channel' => ['required', 'string', 'max:100'],
            'external_reference' => ['required', 'string', 'max:150'],
            'payment_date' => ['required', 'date'],
            'proof_file' => ['required', 'file', 'max:2048', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $proofFile = $request->file('proof_file');

        $payment = $this->financeService->submitPayment($invoice, $validated, $proofFile, $request->user());

        return $this->successResponse($payment, 201);
    }

    /**
     * GET /api/v1/me/payments/{id}/receipt
     * Ambil data kuitansi resmi pembayaran tagihan.
     */
    public function getReceipt(Request $request, string $id): JsonResponse
    {
        $payment = Payment::findOrFail($id);

        if (! Gate::allows('view', $payment)) {
            return $this->errorResponse(
                'Anda tidak berhak mengakses kuitansi pembayaran ini.',
                'FORBIDDEN',
                403
            );
        }

        if ($payment->status !== 'terverifikasi') {
            return $this->errorResponse(
                'Kuitansi belum diterbitkan karena pembayaran belum diverifikasi oleh bendahara.',
                'INVALID_STATUS',
                400
            );
        }

        $receipt = $this->receiptService->buildReceiptData($payment);

        return $this->successResponse($receipt);
    }

    /**
     * GET /api/v1/me/payment-providers
     * Daftar penyedia pembayaran pihak ketiga & instruksi cara bayar yang aktif.
     */
    public function paymentProviders(Request $request): JsonResponse
    {
        $providers = PaymentProviderSetting::where('is_active', true)
            ->orderBy('name')
            ->get();

        return $this->successResponse($providers);
    }
}
