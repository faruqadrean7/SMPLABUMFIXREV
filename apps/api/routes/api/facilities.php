<?php

use App\Http\Controllers\Api\V1\Sar\AssetAuditController;
use App\Http\Controllers\Api\V1\Sar\AssetController;
use App\Http\Controllers\Api\V1\Sar\BookingController;
use App\Http\Controllers\Api\V1\Sar\FacilityController;
use App\Http\Controllers\Api\V1\Sar\LabController;
use App\Http\Controllers\Api\V1\Sar\LibraryController;
use App\Http\Controllers\Api\V1\Sar\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // =====================================================================
    // BE-SAR-01: Sarana & Prasarana - Booking Fasilitas & Ruang
    // =====================================================================
    // Fasilitas
    Route::get('/facilities', [FacilityController::class, 'index']);
    Route::get('/facilities/{id}', [FacilityController::class, 'show']);
    Route::post('/facilities', [FacilityController::class, 'store']);
    Route::put('/facilities/{id}', [FacilityController::class, 'update']);
    Route::delete('/facilities/{id}', [FacilityController::class, 'destroy']);

    // Peminjaman & Cek Ketersediaan
    Route::get('/bookings/availability', [BookingController::class, 'availability']);
    Route::get('/bookings/summary', [BookingController::class, 'summary']);
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{id}', [BookingController::class, 'show']);
    Route::patch('/bookings/{id}/decision', [BookingController::class, 'decision']);
    Route::delete('/bookings/{id}', [BookingController::class, 'cancel']);

    // =====================================================================
    // BE-SAR-02: Sarana & Prasarana - Aset & Inventaris QR
    // =====================================================================
    // Aset & Lookup QR
    Route::get('/assets/summary', [AssetController::class, 'summary']);
    Route::get('/assets', [AssetController::class, 'index']);
    Route::get('/assets/by-code/{code}', [AssetController::class, 'byCode']);
    Route::get('/assets/{id}/qr', [AssetController::class, 'qr']);
    Route::get('/assets/{id}', [AssetController::class, 'show']);
    Route::post('/assets', [AssetController::class, 'store']);
    Route::put('/assets/{id}', [AssetController::class, 'update']);
    Route::delete('/assets/{id}', [AssetController::class, 'destroy']);

    // Sesi Stok Opname Aset
    Route::get('/asset-audits', [AssetAuditController::class, 'index']);
    Route::get('/asset-audits/{id}', [AssetAuditController::class, 'show']);
    Route::post('/asset-audits', [AssetAuditController::class, 'store']);
    Route::patch('/asset-audits/{id}', [AssetAuditController::class, 'update']);

    // =====================================================================
    // BE-SAR-03: Sarana & Prasarana - Helpdesk Perbaikan Sarpras
    // =====================================================================
    Route::get('/tickets/summary', [TicketController::class, 'summary']);
    Route::get('/tickets/technicians', [TicketController::class, 'technicians']);
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets/{id}', [TicketController::class, 'show']);
    Route::post('/tickets/{id}/dispose', [TicketController::class, 'dispose']);
    Route::patch('/tickets/{id}/status', [TicketController::class, 'status']);
    Route::post('/tickets/{id}/comments', [TicketController::class, 'comment']);

    // =====================================================================
    // BE-SAR-04: Sarana & Prasarana - Perpustakaan & Alat Lab
    // =====================================================================
    // Perpustakaan: Buku & Ringkasan
    Route::get('/library/summary', [LibraryController::class, 'summary']);
    Route::get('/library/books', [LibraryController::class, 'indexBooks']);
    Route::post('/library/books', [LibraryController::class, 'storeBook']);
    Route::get('/library/books/{id}', [LibraryController::class, 'showBook']);
    Route::put('/library/books/{id}', [LibraryController::class, 'updateBook']);
    Route::delete('/library/books/{id}', [LibraryController::class, 'destroyBook']);

    // Perpustakaan: Peminjaman & Pengembalian
    Route::get('/library/loans', [LibraryController::class, 'indexLoans']);
    Route::post('/library/loans', [LibraryController::class, 'storeLoan']);
    Route::get('/library/loans/{id}', [LibraryController::class, 'showLoan']);
    Route::post('/library/loans/{id}/return', [LibraryController::class, 'returnLoan']);

    // Laboratorium: Alat Lab & Ringkasan
    Route::get('/lab-equipment/summary', [LabController::class, 'summary']);
    Route::get('/lab-equipment', [LabController::class, 'indexEquipment']);
    Route::post('/lab-equipment', [LabController::class, 'storeEquipment']);
    Route::get('/lab-equipment/{id}', [LabController::class, 'showEquipment']);
    Route::put('/lab-equipment/{id}', [LabController::class, 'updateEquipment']);
    Route::delete('/lab-equipment/{id}', [LabController::class, 'destroyEquipment']);

    // Laboratorium: Peminjaman & Pengembalian Alat Lab
    Route::get('/lab-loans', [LabController::class, 'indexLoans']);
    Route::post('/lab-loans', [LabController::class, 'storeLoan']);
    Route::get('/lab-loans/{id}', [LabController::class, 'showLoan']);
    Route::post('/lab-loans/{id}/return', [LabController::class, 'returnLoan']);
});
