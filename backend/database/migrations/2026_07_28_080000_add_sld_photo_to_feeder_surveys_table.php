<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feeder_surveys', function (Blueprint $table) {
            if (! Schema::hasColumn('feeder_surveys', 'sld_photo')) {
                $table->string('sld_photo')->nullable()->after('new_meter_photo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('feeder_surveys', function (Blueprint $table) {
            if (Schema::hasColumn('feeder_surveys', 'sld_photo')) {
                $table->dropColumn('sld_photo');
            }
        });
    }
};
