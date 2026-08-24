<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumer_surveys', function (Blueprint $table) {
            if (! Schema::hasColumn('consumer_surveys', 'meter_make')) {
                $table->string('meter_make', 80)->nullable()->after('msn');
            }
            if (! Schema::hasColumn('consumer_surveys', 'review_remarks')) {
                $table->text('review_remarks')->nullable()->after('status');
            }
            if (! Schema::hasColumn('consumer_surveys', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('review_remarks');
            }
            if (! Schema::hasColumn('consumer_surveys', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('consumer_surveys', function (Blueprint $table) {
            $table->index(['dtr_id', 'consumer_id'], 'consumer_surveys_dtr_consumer_idx');
            $table->index(['dtr_id', 'ivrs'], 'consumer_surveys_dtr_ivrs_idx');
            $table->index(['status', 'surveyed_at'], 'consumer_surveys_status_surveyed_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'can_consumer_survey_approve')) {
                $table->boolean('can_consumer_survey_approve')->default(false)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('consumer_surveys', function (Blueprint $table) {
            $table->dropIndex('consumer_surveys_dtr_consumer_idx');
            $table->dropIndex('consumer_surveys_dtr_ivrs_idx');
            $table->dropIndex('consumer_surveys_status_surveyed_idx');
            if (Schema::hasColumn('consumer_surveys', 'reviewed_by')) {
                $table->dropConstrainedForeignId('reviewed_by');
            }
            foreach (['meter_make', 'review_remarks', 'reviewed_at'] as $col) {
                if (Schema::hasColumn('consumer_surveys', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'can_consumer_survey_approve')) {
                $table->dropColumn('can_consumer_survey_approve');
            }
        });
    }
};
