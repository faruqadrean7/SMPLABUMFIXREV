<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('library_books', function (Blueprint $table) {
            $table->string('publisher', 200)->nullable()->after('author');
            $table->integer('publish_year')->nullable()->after('publisher');
            $table->string('category', 100)->nullable()->after('publish_year');
            $table->uuid('cover_file_id')->nullable()->after('category');
            $table->string('location_shelf', 100)->nullable()->after('cover_file_id');

            $table->foreign('cover_file_id')->references('id')->on('files')->onDelete('set null');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->string('status', 30)->default('dipinjam')->after('returned_at');
            $table->boolean('fine_paid')->default(false)->after('fine_amount');
            $table->text('notes')->nullable()->after('fine_paid');
        });

        Schema::table('lab_equipment', function (Blueprint $table) {
            $table->string('code', 100)->nullable()->after('room_id');
            $table->string('category', 100)->nullable()->after('name');
            $table->string('condition', 30)->default('baik')->after('stock');
            $table->text('notes')->nullable()->after('condition');
        });

        Schema::table('lab_loans', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('loan_date');
            $table->integer('quantity')->default(1)->after('due_date');
            $table->string('status', 30)->default('dipinjam')->after('returned_at');
            $table->string('purpose', 255)->nullable()->after('status');
            $table->text('notes')->nullable()->after('purpose');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_loans', function (Blueprint $table) {
            $table->dropColumn(['due_date', 'quantity', 'status', 'purpose', 'notes']);
        });

        Schema::table('lab_equipment', function (Blueprint $table) {
            $table->dropColumn(['code', 'category', 'condition', 'notes']);
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['status', 'fine_paid', 'notes']);
        });

        Schema::table('library_books', function (Blueprint $table) {
            $table->dropForeign(['cover_file_id']);
            $table->dropColumn(['publisher', 'publish_year', 'category', 'cover_file_id', 'location_shelf']);
        });
    }
};
