<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Modul Keuangan Minimal SISTLABUM (GAP-FIN-01)
     */
    public function up(): void
    {
        // 1. Master Jenis Biaya Sekolah
        Schema::create('fee_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable()->index();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->enum('category', ['spp', 'gedung', 'seragam', 'kegiatan', 'lainnya'])->default('lainnya')->index();
            $table->unsignedBigInteger('default_amount')->default(0); // Nominal rupiah integer
            $table->boolean('is_recurring')->default(false); // Tagihan berulang bulanan/semesteran
            $table->boolean('is_active')->default(true)->index();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'is_active']);
        });

        // 2. Rekening Bank Tujuan Transfer Sekolah
        Schema::create('school_bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable()->index();
            $table->string('bank_name', 100); // misal: Bank Jatim, BCA, Mandiri
            $table->string('account_number', 50);
            $table->string('account_holder', 150);
            $table->string('branch', 100)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['school_id', 'is_active']);
        });

        // 3. Tagihan Siswa (Invoices)
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable()->index();
            $table->string('invoice_number', 60)->unique(); // misal: INV/2026/10/0001
            $table->uuid('student_id')->index();
            $table->uuid('academic_year_id')->nullable()->index();
            $table->string('period', 20)->nullable()->index(); // misal: 2026-10 (SPP Oktober 2026)
            $table->date('due_date')->index(); // Tanggal jatuh tempo
            $table->unsignedBigInteger('total_amount'); // Total nominal tagihan (integer rupiah)
            $table->unsignedBigInteger('paid_amount')->default(0); // Total terbayar terverifikasi (cached)
            $table->enum('status', ['belum_bayar', 'sebagian', 'lunas', 'dibatalkan'])->default('belum_bayar')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->onDelete('set null');
            $table->index(['student_id', 'status']);
            $table->index(['student_id', 'period']);
        });

        // 4. Rincian Item Tagihan (Invoice Items)
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('invoice_id')->index();
            $table->uuid('fee_type_id')->index();
            $table->string('description', 255);
            $table->unsignedBigInteger('amount'); // Nominal item (integer rupiah)
            $table->unsignedBigInteger('discount')->default(0); // Potongan/keringanan (integer rupiah)
            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('fee_type_id')->references('id')->on('fee_types')->onDelete('restrict');
        });

        // 5. Pembayaran Tagihan (Payments)
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable()->index();
            $table->uuid('invoice_id')->index();
            $table->uuid('school_bank_account_id')->nullable()->index();
            $table->unsignedBigInteger('amount'); // Nominal dibayar (integer rupiah)
            $table->enum('payment_method', ['tunai', 'transfer'])->default('transfer')->index();
            $table->date('payment_date');
            $table->uuid('proof_file_id')->nullable()->index();
            $table->enum('status', ['menunggu_verifikasi', 'terverifikasi', 'ditolak', 'void'])->default('menunggu_verifikasi')->index();
            $table->uuid('submitted_by')->index(); // User yang menginput/mengajukan
            $table->uuid('verified_by')->nullable()->index(); // User bendahara yang memverifikasi/void
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable(); // Alasan jika ditolak atau divoid
            $table->string('receipt_number', 60)->nullable()->unique(); // misal: KWT/2026/10/0001 (terbit saat terverifikasi)
            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('restrict');
            $table->foreign('school_bank_account_id')->references('id')->on('school_bank_accounts')->onDelete('set null');
            $table->foreign('proof_file_id')->references('id')->on('files')->onDelete('set null');
            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['invoice_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('school_bank_accounts');
        Schema::dropIfExists('fee_types');
    }
};
