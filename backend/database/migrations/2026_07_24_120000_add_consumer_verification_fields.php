<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumer_surveys', function (Blueprint $table) {
            $table->string('meter_condition')->nullable()->after('meter_photo');
            $table->string('premise_photo')->nullable()->after('meter_condition');
            $table->string('verification_status')->nullable()->after('premise_photo'); // Verified | Updated | New Consumer
        });
    }

    public function down(): void
    {
        Schema::table('consumer_surveys', function (Blueprint $table) {
            $table->dropColumn(['meter_condition', 'premise_photo', 'verification_status']);
        });
    }
};
