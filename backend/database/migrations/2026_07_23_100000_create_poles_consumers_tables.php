<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dtr_id')->constrained('dtrs')->cascadeOnDelete();
            $table->string('pole_no'); // Pole-01
            $table->string('source_type')->default('dtr'); // dtr | previous_pole
            $table->foreignId('previous_pole_id')->nullable()->constrained('poles')->nullOnDelete();
            $table->unsignedInteger('houses_connected')->default(0); // kitne ghar connected
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();

            $table->unique(['dtr_id', 'pole_no']);
        });

        Schema::create('consumers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dtr_id')->constrained('dtrs')->cascadeOnDelete();
            $table->foreignId('pole_id')->nullable()->constrained('poles')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('phone', 20)->nullable(); // phone as requested
            $table->string('ivrs')->nullable();
            $table->string('msn')->nullable();
            $table->string('address')->nullable();
            $table->string('phase')->nullable();
            $table->timestamps();
        });

        Schema::create('consumer_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dtr_survey_id')->constrained('dtr_surveys')->cascadeOnDelete();
            $table->foreignId('surveyor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('dtr_id')->constrained('dtrs')->cascadeOnDelete();
            $table->foreignId('pole_id')->constrained('poles')->cascadeOnDelete();
            $table->foreignId('consumer_id')->nullable()->constrained('consumers')->nullOnDelete();

            $table->string('consumer_name')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('ivrs')->nullable();
            $table->string('msn')->nullable();
            $table->string('phase')->nullable();
            $table->string('address')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('gps_accuracy', 8, 2)->nullable();

            $table->string('meter_photo')->nullable();
            $table->text('observation')->nullable();
            $table->string('status')->default('saved'); // saved | not_accessible
            $table->timestamp('surveyed_at');
            $table->timestamps();
        });

        Schema::table('dtr_surveys', function (Blueprint $table) {
            // approved → consumer_survey_completed after Finish DTR Survey
            $table->timestamp('consumer_survey_completed_at')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('dtr_surveys', function (Blueprint $table) {
            $table->dropColumn('consumer_survey_completed_at');
        });
        Schema::dropIfExists('consumer_surveys');
        Schema::dropIfExists('consumers');
        Schema::dropIfExists('poles');
    }
};
