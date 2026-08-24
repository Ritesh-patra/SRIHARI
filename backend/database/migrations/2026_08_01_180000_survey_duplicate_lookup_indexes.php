<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lookup indexes for duplicate survey guards (app-level uniqueness).
 * Full unique constraints are unsafe while rejected rows / historical duplicates may exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feeder_surveys', function (Blueprint $table) {
            $table->index(['feeder_id', 'surveyor_id', 'status'], 'feeder_surveys_feeder_surveyor_status_idx');
        });

        Schema::table('dtr_surveys', function (Blueprint $table) {
            $table->index(['dtr_id', 'status'], 'dtr_surveys_dtr_status_idx');
            $table->index(['dtr_id', 'surveyor_id'], 'dtr_surveys_dtr_surveyor_idx');
        });

        Schema::table('consumer_surveys', function (Blueprint $table) {
            $table->index(['dtr_id', 'consumer_id', 'status'], 'consumer_surveys_dtr_consumer_status_idx');
            $table->index(['dtr_id', 'ivrs', 'status'], 'consumer_surveys_dtr_ivrs_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('feeder_surveys', function (Blueprint $table) {
            $table->dropIndex('feeder_surveys_feeder_surveyor_status_idx');
        });

        Schema::table('dtr_surveys', function (Blueprint $table) {
            $table->dropIndex('dtr_surveys_dtr_status_idx');
            $table->dropIndex('dtr_surveys_dtr_surveyor_idx');
        });

        Schema::table('consumer_surveys', function (Blueprint $table) {
            $table->dropIndex('consumer_surveys_dtr_consumer_status_idx');
            $table->dropIndex('consumer_surveys_dtr_ivrs_status_idx');
        });
    }
};
