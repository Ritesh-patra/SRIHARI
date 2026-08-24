<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('substation_surveys')) {
            return;
        }

        Schema::create('substation_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surveyor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('surveyed_at')->nullable();

            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('circle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('division_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('substation_id')->nullable()->constrained()->nullOnDelete();

            $table->string('substation_code')->nullable();
            $table->string('substation_name')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('gps_accuracy', 8, 2)->nullable();

            $table->string('substation_type')->nullable(); // 33/11 KV, 11/0.4 KV, …
            $table->decimal('capacity_mva', 10, 3)->nullable();
            $table->unsignedInteger('transformer_count')->nullable();
            $table->string('incoming_voltage')->nullable();
            $table->string('outgoing_voltage')->nullable();
            $table->unsignedInteger('feeder_count_declared')->nullable();

            $table->string('meter_number')->nullable();
            $table->string('meter_make')->nullable();
            $table->string('meter_serial_no')->nullable();
            $table->string('metering_type')->nullable();
            $table->string('ct_ratio')->nullable();
            $table->string('pt_ratio')->nullable();
            $table->string('mf')->nullable(); // multiplying factor
            $table->string('meter_condition')->nullable();
            $table->boolean('meter_working')->nullable();

            $table->string('substation_photo')->nullable();
            $table->string('meter_photo')->nullable();
            $table->string('nameplate_photo')->nullable();
            $table->string('sld_photo')->nullable();

            $table->text('observation')->nullable();
            $table->text('remarks')->nullable();

            $table->string('status')->default('draft'); // draft | pending_approval | approved | rejected
            $table->text('review_remarks')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->index('substation_id');
            $table->index('status');
            $table->index('surveyor_id');
            $table->index('zone_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('substation_surveys');
    }
};
