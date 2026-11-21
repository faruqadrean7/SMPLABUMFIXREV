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
        Schema::table('partnerships', function (Blueprint $table) {
            $table->string('mou_number', 100)->nullable()->after('partner_name');
            $table->string('partner_type', 50)->default('instansi')->after('mou_number');
            $table->string('contact_person', 100)->nullable()->after('partner_type');
            $table->string('phone', 50)->nullable()->after('contact_person');
            $table->string('email', 100)->nullable()->after('phone');
            $table->text('address')->nullable()->after('email');
            $table->string('status', 30)->default('aktif')->after('valid_until');
            $table->text('notes')->nullable()->after('status');
        });

        Schema::table('exchange_programs', function (Blueprint $table) {
            $table->string('type', 50)->default('pelajar')->after('name');
            $table->string('destination_country', 100)->nullable()->after('type');
            $table->string('destination_institution', 200)->nullable()->after('destination_country');
            $table->integer('quota')->nullable()->after('destination_institution');
            $table->text('description')->nullable()->after('quota');
            $table->uuid('pic_teacher_id')->nullable()->after('end_date');
            $table->string('status', 30)->default('dibuka')->after('pic_teacher_id');

            $table->foreign('pic_teacher_id')->references('id')->on('teachers')->onDelete('set null');
        });

        Schema::table('exchange_participants', function (Blueprint $table) {
            $table->string('passport_number', 50)->nullable()->after('status');
            $table->text('notes')->nullable()->after('passport_number');
            $table->text('evaluation_notes')->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exchange_participants', function (Blueprint $table) {
            $table->dropColumn(['passport_number', 'notes', 'evaluation_notes']);
        });

        Schema::table('exchange_programs', function (Blueprint $table) {
            $table->dropForeign(['pic_teacher_id']);
            $table->dropColumn([
                'type',
                'destination_country',
                'destination_institution',
                'quota',
                'description',
                'pic_teacher_id',
                'status',
            ]);
        });

        Schema::table('partnerships', function (Blueprint $table) {
            $table->dropColumn([
                'mou_number',
                'partner_type',
                'contact_person',
                'phone',
                'email',
                'address',
                'status',
                'notes',
            ]);
        });
    }
};
