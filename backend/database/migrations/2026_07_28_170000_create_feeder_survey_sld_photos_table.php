<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feeder_survey_sld_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feeder_survey_id')->constrained('feeder_surveys')->cascadeOnDelete();
            $table->string('path');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['feeder_survey_id', 'id']);
        });

        // Seed history from the current sld_photo so managers still see it.
        if (Schema::hasColumn('feeder_surveys', 'sld_photo')) {
            $rows = DB::table('feeder_surveys')
                ->whereNotNull('sld_photo')
                ->where('sld_photo', '!=', '')
                ->get(['id', 'surveyor_id', 'sld_photo', 'updated_at', 'created_at']);

            foreach ($rows as $row) {
                DB::table('feeder_survey_sld_photos')->insert([
                    'feeder_survey_id' => $row->id,
                    'path' => $row->sld_photo,
                    'uploaded_by' => $row->surveyor_id,
                    'created_at' => $row->updated_at ?? $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? $row->created_at ?? now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('feeder_survey_sld_photos');
    }
};
