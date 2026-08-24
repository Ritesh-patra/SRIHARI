<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_analysis_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source', 32);
            $table->string('path');
            $table->string('original_name');
            $table->unsignedInteger('row_count')->nullable();
            $table->json('headers_json')->nullable();
            $table->string('parse_note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_analysis_uploads');
    }
};
