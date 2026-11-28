<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subjects') && ! Schema::hasColumn('subjects', 'kktp')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->decimal('kktp', 5, 2)->default(75.00)->after('subject_group');
            });
        }

        if (Schema::hasTable('report_description_templates')) {
            Schema::table('report_description_templates', function (Blueprint $table) {
                if (! Schema::hasColumn('report_description_templates', 'kktp_threshold')) {
                    $table->decimal('kktp_threshold', 5, 2)->nullable()->after('level');
                }
                if (! Schema::hasColumn('report_description_templates', 'min_score')) {
                    $table->decimal('min_score', 5, 2)->nullable()->after('kktp_threshold');
                }
                if (! Schema::hasColumn('report_description_templates', 'max_score')) {
                    $table->decimal('max_score', 5, 2)->nullable()->after('min_score');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('report_description_templates')) {
            Schema::table('report_description_templates', function (Blueprint $table) {
                $cols = array_values(array_filter(
                    ['kktp_threshold', 'min_score', 'max_score'],
                    fn ($col) => Schema::hasColumn('report_description_templates', $col)
                ));
                if (! empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('subjects') && Schema::hasColumn('subjects', 'kktp')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->dropColumn('kktp');
            });
        }
    }
};
