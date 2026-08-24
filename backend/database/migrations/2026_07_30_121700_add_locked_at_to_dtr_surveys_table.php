<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('dtr_surveys', 'locked_at')) {
            Schema::table('dtr_surveys', function (Blueprint $table) {
                $table->timestamp('locked_at')->nullable()->after('reviewed_at');
            });
        }

        // Existing pending/approved surveys should appear locked for manager Unlock UX.
        DB::table('dtr_surveys')
            ->whereIn('status', ['pending_approval', 'approved'])
            ->whereNull('locked_at')
            ->update(['locked_at' => now()]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('dtr_surveys', 'locked_at')) {
            Schema::table('dtr_surveys', function (Blueprint $table) {
                $table->dropColumn('locked_at');
            });
        }
    }
};
