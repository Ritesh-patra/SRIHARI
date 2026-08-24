<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chunked_uploads')) {
            return;
        }

        Schema::create('chunked_uploads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 64);
            $table->json('meta_json')->nullable();
            $table->string('original_name');
            $table->string('mime', 191)->nullable();
            $table->string('extension', 16)->nullable();
            $table->unsignedBigInteger('total_size');
            $table->unsignedInteger('chunk_size');
            $table->unsignedInteger('total_chunks');
            $table->unsignedInteger('received_chunks')->default(0);
            $table->string('status', 32)->default('pending');
            $table->string('path')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('user_id');
            $table->index('purpose');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chunked_uploads');
    }
};
