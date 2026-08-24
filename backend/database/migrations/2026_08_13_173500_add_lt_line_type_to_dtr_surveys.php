<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dtr_surveys', function (Blueprint $table) {
            if (! Schema::hasColumn('dtr_surveys', 'lt_line_type')) {
                $table->string('lt_line_type', 32)->nullable()->after('dtr_condition');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dtr_surveys', function (Blueprint $table) {
            if (Schema::hasColumn('dtr_surveys', 'lt_line_type')) {
                $table->dropColumn('lt_line_type');
            }
        });
    }
};
