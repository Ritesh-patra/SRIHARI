<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dtr_reactivation_requests')) {
            return;
        }

        Schema::create('dtr_reactivation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dtr_survey_id')->constrained('dtr_surveys')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->text('reason')->nullable();
            $table->string('status', 32)->default('pending'); // pending | approved | rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_remarks')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('dtr_survey_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dtr_reactivation_requests');
    }
};
