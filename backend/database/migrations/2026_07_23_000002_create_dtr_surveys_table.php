<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dtr_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surveyor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('surveyed_at');

            $table->foreignId('region_id')->constrained();
            $table->foreignId('circle_id')->constrained();
            $table->foreignId('division_id')->constrained();
            $table->foreignId('zone_id')->constrained();
            $table->foreignId('substation_id')->constrained();
            $table->foreignId('feeder_id')->constrained();
            $table->foreignId('dtr_id')->nullable()->constrained('dtrs')->nullOnDelete();

            $table->string('feeder_code');
            $table->string('feeder_name');
            $table->string('dtr_code');
            $table->string('dtr_name');

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('gps_accuracy', 8, 2)->nullable();

            $table->unsignedInteger('dtr_capacity_kva')->nullable();
            $table->string('dtr_condition'); // Good, Damaged, Leaning, Oil Leakage, Burnt, Other

            $table->string('smart_meter_status'); // Installed, Not Installed, Meter Missing

            $table->string('old_meter_condition')->nullable();
            $table->string('old_msn')->nullable();
            $table->string('old_meter_make')->nullable();

            $table->string('new_msn')->nullable();
            $table->string('new_meter_make')->nullable(); // Secure, HPL, Visiontek
            $table->string('new_meter_ct_ratio')->nullable();
            $table->string('new_meter_mf')->nullable();

            $table->string('dtr_overall_photo')->nullable();
            $table->string('smart_meter_photo')->nullable();

            $table->text('observation')->nullable();

            $table->string('status')->default('draft'); // draft, pending_approval, approved, rejected
            $table->text('review_remarks')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dtr_surveys');
    }
};
