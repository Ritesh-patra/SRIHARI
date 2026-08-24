<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Masters that get map coordinates (poles already had them). */
    private const GEO_TABLES = ['substations', 'feeders', 'dtrs'];

    public function up(): void
    {
        foreach (self::GEO_TABLES as $name) {
            if (! Schema::hasTable($name)) {
                continue;
            }

            Schema::table($name, function (Blueprint $table) use ($name) {
                if (! Schema::hasColumn($name, 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable();
                }
                if (! Schema::hasColumn($name, 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable();
                }
            });
        }

        if (Schema::hasTable('poles') && ! Schema::hasColumn('poles', 'photo')) {
            Schema::table('poles', function (Blueprint $table) {
                $table->string('photo')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::GEO_TABLES as $name) {
            if (! Schema::hasTable($name)) {
                continue;
            }

            Schema::table($name, function (Blueprint $table) use ($name) {
                foreach (['latitude', 'longitude'] as $column) {
                    if (Schema::hasColumn($name, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('poles') && Schema::hasColumn('poles', 'photo')) {
            Schema::table('poles', function (Blueprint $table) {
                $table->dropColumn('photo');
            });
        }
    }
};
