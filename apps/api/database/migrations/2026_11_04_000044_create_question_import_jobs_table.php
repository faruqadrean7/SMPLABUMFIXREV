<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BE-CBT-13: Tabel antrean dan status impor butir soal dari DOCX.
     */
    public function up(): void
    {
        Schema::create('question_import_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('bank_id');
            $table->uuid('uploaded_by');
            $table->uuid('source_file_id')->nullable();
            $table->string('status', 20)->default('queued');
            $table->jsonb('parsed_payload')->nullable();
            $table->jsonb('parse_errors')->nullable();
            $table->unsignedInteger('imported_count')->nullable();
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->timestamps();

            $table->foreign('bank_id')
                ->references('id')
                ->on('question_banks')
                ->onDelete('cascade');

            $table->foreign('uploaded_by')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('source_file_id')
                ->references('id')
                ->on('files')
                ->onDelete('set null');

            $table->index(['bank_id', 'status']);
            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_import_jobs');
    }
};
