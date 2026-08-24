<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumers', function (Blueprint $table) {
            if (! Schema::hasColumn('consumers', 'source')) {
                $table->string('source', 20)->default('mi')->after('is_active');
            }
        });

        // Existing rows are MI Done imports.
        if (Schema::hasColumn('consumers', 'source')) {
            DB::table('consumers')->update(['source' => 'mi']);
        }

        Schema::table('consumers', function (Blueprint $table) {
            $table->index(['source', 'ivrs'], 'consumers_source_ivrs_index');
            $table->index(['source', 'msn'], 'consumers_source_msn_index');
            $table->index('ivrs', 'consumers_ivrs_index');
            $table->index('msn', 'consumers_msn_index');
        });
    }

    public function down(): void
    {
        Schema::table('consumers', function (Blueprint $table) {
            $table->dropIndex('consumers_source_ivrs_index');
            $table->dropIndex('consumers_source_msn_index');
            $table->dropIndex('consumers_ivrs_index');
            $table->dropIndex('consumers_msn_index');
            if (Schema::hasColumn('consumers', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};
