<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feeder_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surveyor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('surveyed_at')->nullable();

            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('circle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('division_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('substation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('feeder_id')->nullable()->constrained()->nullOnDelete();

            $table->string('substation_code')->nullable();
            $table->string('substation_name')->nullable();
            $table->string('feeder_code')->nullable();
            $table->string('feeder_name')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('gps_accuracy', 8, 2)->nullable();

            $table->string('feeder_voltage')->nullable();
            $table->string('metering_type')->nullable();
            $table->string('ctpt_available')->nullable();
            $table->string('me_pt_ratio')->nullable();
            $table->string('me_ct_ratio')->nullable();
            $table->string('new_mf')->nullable();
            $table->string('me_installed')->nullable();
            $table->string('me_working')->nullable();

            $table->string('new_smart_meter_installed')->nullable();
            $table->string('new_meter_number')->nullable();
            $table->string('new_meter_photo')->nullable();

            $table->string('old_meter_number')->nullable();
            $table->string('old_meter_make')->nullable();
            $table->string('old_meter_condition')->nullable();

            $table->text('remarks')->nullable();
            $table->string('status')->default('draft'); // draft | pending_approval | approved | rejected | completed
            $table->text('review_remarks')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feeder_surveys');
    }
};
