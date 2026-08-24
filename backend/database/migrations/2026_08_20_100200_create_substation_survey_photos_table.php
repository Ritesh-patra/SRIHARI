<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('substation_survey_photos')) {
            return;
        }

        Schema::create('substation_survey_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('substation_survey_id')->constrained('substation_surveys')->cascadeOnDelete();
            $table->string('path');
            $table->string('kind')->nullable(); // substation | meter | nameplate | sld | extra
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['substation_survey_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('substation_survey_photos');
    }
};
