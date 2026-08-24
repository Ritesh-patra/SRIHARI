<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dtr_surveys', function (Blueprint $table) {
            // standalone = DTR→Consumer hub; feeder = Feeder→DTR flow
            $table->string('entry_source', 32)->nullable()->after('observation');
            $table->foreignId('feeder_survey_id')
                ->nullable()
                ->after('entry_source')
                ->constrained('feeder_surveys')
                ->nullOnDelete();
            $table->index(['feeder_id', 'entry_source', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('dtr_surveys', function (Blueprint $table) {
            $table->dropIndex(['feeder_id', 'entry_source', 'status']);
            $table->dropConstrainedForeignId('feeder_survey_id');
            $table->dropColumn('entry_source');
        });
    }
};
