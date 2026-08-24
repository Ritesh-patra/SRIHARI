<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dtr_surveys', function (Blueprint $table) {
            // null = normal survey; pending/approved/rejected = feeder remapping review
            if (! Schema::hasColumn('dtr_surveys', 'mapping_correction_status')) {
                $table->string('mapping_correction_status', 32)->nullable()->after('consumer_survey_completed_at');
            }
            if (! Schema::hasColumn('dtr_surveys', 'master_feeder_id')) {
                $table->foreignId('master_feeder_id')
                    ->nullable()
                    ->after('mapping_correction_status')
                    ->constrained('feeders')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('dtr_surveys', 'reported_feeder_id')) {
                $table->foreignId('reported_feeder_id')
                    ->nullable()
                    ->after('master_feeder_id')
                    ->constrained('feeders')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('dtr_surveys', 'field_dtr_name')) {
                $table->string('field_dtr_name', 190)->nullable()->after('reported_feeder_id');
            }
            if (! Schema::hasColumn('dtr_surveys', 'mapping_correction_remarks')) {
                $table->text('mapping_correction_remarks')->nullable()->after('field_dtr_name');
            }
            if (! Schema::hasColumn('dtr_surveys', 'mapping_correction_reviewed_at')) {
                $table->timestamp('mapping_correction_reviewed_at')->nullable()->after('mapping_correction_remarks');
            }
            if (! Schema::hasColumn('dtr_surveys', 'mapping_correction_reviewed_by')) {
                $table->foreignId('mapping_correction_reviewed_by')
                    ->nullable()
                    ->after('mapping_correction_reviewed_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        // Index may already exist on partial deploys.
        try {
            Schema::table('dtr_surveys', function (Blueprint $table) {
                $table->index('mapping_correction_status');
            });
        } catch (\Throwable) {
            // duplicate index name — ignore
        }
    }

    public function down(): void
    {
        Schema::table('dtr_surveys', function (Blueprint $table) {
            if (Schema::hasColumn('dtr_surveys', 'mapping_correction_reviewed_by')) {
                $table->dropConstrainedForeignId('mapping_correction_reviewed_by');
            }
            if (Schema::hasColumn('dtr_surveys', 'mapping_correction_reviewed_at')) {
                $table->dropColumn('mapping_correction_reviewed_at');
            }
            if (Schema::hasColumn('dtr_surveys', 'mapping_correction_remarks')) {
                $table->dropColumn('mapping_correction_remarks');
            }
            if (Schema::hasColumn('dtr_surveys', 'field_dtr_name')) {
                $table->dropColumn('field_dtr_name');
            }
            if (Schema::hasColumn('dtr_surveys', 'reported_feeder_id')) {
                $table->dropConstrainedForeignId('reported_feeder_id');
            }
            if (Schema::hasColumn('dtr_surveys', 'master_feeder_id')) {
                $table->dropConstrainedForeignId('master_feeder_id');
            }
            if (Schema::hasColumn('dtr_surveys', 'mapping_correction_status')) {
                try {
                    $table->dropIndex(['mapping_correction_status']);
                } catch (\Throwable) {
                }
                $table->dropColumn('mapping_correction_status');
            }
        });
    }
};
