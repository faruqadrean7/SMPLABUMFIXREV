<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * REVISI BATCH 3B: Pembayaran via Pihak Ketiga & Unggah Slip Pembayaran
     */
    public function up(): void
    {
        // 1. Modifikasi tabel payments
        Schema::table('payments', function (Blueprint $table) {
            // Drop foreign key & kolom school_bank_account_id
            if (Schema::hasColumn('payments', 'school_bank_account_id')) {
                $table->dropForeign(['school_bank_account_id']);
                $table->dropColumn('school_bank_account_id');
            }

            // Drop kolom payment_method
            if (Schema::hasColumn('payments', 'payment_method')) {
                $table->dropColumn('payment_method');
            }

            // Tambah kanal pembayaran & nomor referensi pihak ketiga
            $table->string('channel', 100)->default('Transfer Bank')->after('amount');
            $table->string('external_reference', 150)->after('channel');
        });

        // Unique index pada external_reference untuk status aktif (menunggu_verifikasi, terverifikasi)
        // Di PostgreSQL gunakan partial unique index
        DB::statement("CREATE UNIQUE INDEX payments_external_ref_active_unique ON payments (external_reference) WHERE status IN ('menunggu_verifikasi', 'terverifikasi');");

        // 2. Tambah kolom va_number pada invoices
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('va_number', 50)->nullable()->after('period')->index();
        });

        // 3. Hapus tabel school_bank_accounts
        Schema::dropIfExists('school_bank_accounts');

        // 4. Tambah tabel payment_provider_settings
        Schema::create('payment_provider_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable()->index();
            $table->string('name', 150); // nama penyedia, misal: Bank Jatim Virtual Account, BSI VA
            $table->text('instructions'); // instruksi teks/markdown
            $table->string('info_url', 255)->nullable(); // URL info panduan pembayaran
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['school_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_provider_settings');

        Schema::create('school_bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable()->index();
            $table->string('bank_name', 100);
            $table->string('account_number', 50);
            $table->string('account_holder', 150);
            $table->string('branch', 100)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('va_number');
        });

        DB::statement('DROP INDEX IF EXISTS payments_external_ref_active_unique;');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['channel', 'external_reference']);
            $table->enum('payment_method', ['tunai', 'transfer'])->default('transfer');
            $table->uuid('school_bank_account_id')->nullable();
        });
    }
};
