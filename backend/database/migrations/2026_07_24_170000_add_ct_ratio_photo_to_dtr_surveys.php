<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dtr_surveys', function (Blueprint $table) {
            if (! Schema::hasColumn('dtr_surveys', 'ct_ratio_photo')) {
                $table->string('ct_ratio_photo')->nullable()->after('smart_meter_photo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dtr_surveys', function (Blueprint $table) {
            if (Schema::hasColumn('dtr_surveys', 'ct_ratio_photo')) {
                $table->dropColumn('ct_ratio_photo');
            }
        });
    }
};
