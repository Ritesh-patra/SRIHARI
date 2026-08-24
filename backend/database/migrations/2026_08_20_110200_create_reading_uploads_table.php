<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reading_uploads')) {
            return;
        }

        Schema::create('reading_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->unsignedBigInteger('chunked_upload_id')->nullable();
            $table->string('path');
            $table->string('original_name');
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('period_label', 64)->nullable();
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('rows_total')->nullable();
            $table->unsignedInteger('rows_imported')->nullable();
            $table->unsignedInteger('rows_failed')->nullable();
            $table->json('headers_json')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('status');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_uploads');
    }
};
