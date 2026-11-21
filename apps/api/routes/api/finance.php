<?php

use App\Http\Controllers\Api\V1\Finance\FeeTypeController;
use App\Http\Controllers\Api\V1\Finance\FinanceReportController;
use App\Http\Controllers\Api\V1\Finance\InvoiceController;
use App\Http\Controllers\Api\V1\Finance\PaymentController;
use App\Http\Controllers\Api\V1\Finance\PaymentProviderSettingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // =====================================================================
    // GAP-FIN-01: Modul Keuangan Sekolah (Bendahara & Admin)
    // =====================================================================
    Route::prefix('finance')->group(function () {
        // Master Jenis Biaya (finance.fee.manage)
        Route::get('/fee-types', [FeeTypeController::class, 'index'])->middleware('permission:finance.fee.manage');
        Route::post('/fee-types', [FeeTypeController::class, 'store'])->middleware('permission:finance.fee.manage');
        Route::get('/fee-types/{id}', [FeeTypeController::class, 'show'])->middleware('permission:finance.fee.manage');
        Route::put('/fee-types/{id}', [FeeTypeController::class, 'update'])->middleware('permission:finance.fee.manage');
        Route::delete('/fee-types/{id}', [FeeTypeController::class, 'destroy'])->middleware('permission:finance.fee.manage');

        // Pengaturan Penyedia Pembayaran Pihak Ketiga (finance.fee.manage)
        Route::get('/settings/providers', [PaymentProviderSettingController::class, 'index'])->middleware('permission:finance.fee.manage');
        Route::post('/settings/providers', [PaymentProviderSettingController::class, 'store'])->middleware('permission:finance.fee.manage');
        Route::get('/settings/providers/{id}', [PaymentProviderSettingController::class, 'show'])->middleware('permission:finance.fee.manage');
        Route::put('/settings/providers/{id}', [PaymentProviderSettingController::class, 'update'])->middleware('permission:finance.fee.manage');
        Route::delete('/settings/providers/{id}', [PaymentProviderSettingController::class, 'destroy'])->middleware('permission:finance.fee.manage');

        // Tagihan / Invoices (finance.invoice.manage)
        Route::get('/invoices', [InvoiceController::class, 'index'])->middleware('permission:finance.invoice.manage');
        Route::post('/invoices', [InvoiceController::class, 'store'])->middleware('permission:finance.invoice.manage');
        Route::post('/invoices/generate-bulk', [InvoiceController::class, 'generateBulk'])->middleware('permission:finance.invoice.manage');
        Route::get('/invoices/{id}', [InvoiceController::class, 'show'])->middleware('permission:finance.invoice.manage');
        Route::post('/invoices/{id}/cancel', [InvoiceController::class, 'cancel'])->middleware('permission:finance.invoice.manage');

        // Pembayaran / Payments & Verifikasi (finance.payment.verify)
        Route::get('/payments', [PaymentController::class, 'index'])->middleware('permission:finance.payment.verify');
        Route::get('/payments/{id}', [PaymentController::class, 'show'])->middleware('permission:finance.payment.verify');
        Route::post('/payments/{id}/verify', [PaymentController::class, 'verify'])->middleware('permission:finance.payment.verify');
        Route::post('/payments/{id}/reject', [PaymentController::class, 'reject'])->middleware('permission:finance.payment.verify');
        Route::post('/payments/{id}/void', [PaymentController::class, 'void'])->middleware('permission:finance.payment.verify');
        Route::get('/payments/{id}/receipt', [PaymentController::class, 'receipt'])->middleware('permission:finance.payment.verify');

        // Laporan Keuangan (finance.report.view)
        Route::get('/reports/arrears', [FinanceReportController::class, 'arrears'])->middleware('permission:finance.report.view');
        Route::get('/reports/income', [FinanceReportController::class, 'income'])->middleware('permission:finance.report.view');
    });
});
