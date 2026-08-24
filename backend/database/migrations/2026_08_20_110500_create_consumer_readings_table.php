<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('consumer_readings')) {
            return;
        }

        Schema::create('consumer_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reading_upload_id')->constrained('reading_uploads')->cascadeOnDelete();
            $table->foreignId('consumer_id')->nullable()->constrained('consumers')->nullOnDelete();
            $table->string('ivrs', 64)->nullable();
            $table->string('msn', 64)->nullable();
            $table->string('account_no', 64)->nullable();
            $table->string('consumer_name', 191)->nullable();
            $table->string('dtr_code', 64)->nullable();
            $table->string('feeder_code', 64)->nullable();
            $table->date('reading_date')->nullable();
            $table->string('period_label', 64)->nullable();
            $table->decimal('kwh_import', 18, 3)->nullable();
            $table->decimal('kwh_export', 18, 3)->nullable();
            $table->decimal('kvah', 18, 3)->nullable();
            $table->decimal('md_kw', 14, 3)->nullable();
            $table->json('raw_json')->nullable();
            $table->timestamps();

            $table->index('ivrs');
            $table->index('msn');
            $table->index('account_no');
            $table->index('dtr_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumer_readings');
    }
};
