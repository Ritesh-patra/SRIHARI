<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('feeder_readings')) {
            return;
        }

        Schema::create('feeder_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reading_upload_id')->constrained('reading_uploads')->cascadeOnDelete();
            $table->foreignId('feeder_id')->nullable()->constrained('feeders')->nullOnDelete();
            $table->string('feeder_code', 64);
            $table->string('feeder_name', 191)->nullable();
            $table->date('reading_date')->nullable();
            $table->string('period_label', 64)->nullable();
            $table->decimal('kwh_import', 18, 3)->nullable();
            $table->decimal('kwh_export', 18, 3)->nullable();
            $table->decimal('kvah', 18, 3)->nullable();
            $table->decimal('md_kw', 14, 3)->nullable();
            $table->json('raw_json')->nullable();
            $table->timestamps();

            $table->index('feeder_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feeder_readings');
    }
};
