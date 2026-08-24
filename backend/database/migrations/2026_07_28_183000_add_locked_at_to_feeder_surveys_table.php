<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feeder_surveys', function (Blueprint $table) {
            if (! Schema::hasColumn('feeder_surveys', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('reviewed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('feeder_surveys', function (Blueprint $table) {
            if (Schema::hasColumn('feeder_surveys', 'locked_at')) {
                $table->dropColumn('locked_at');
            }
        });
    }
};
