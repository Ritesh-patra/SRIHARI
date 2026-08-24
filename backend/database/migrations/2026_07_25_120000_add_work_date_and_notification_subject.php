<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_assignments', function (Blueprint $table) {
            $table->date('work_date')->nullable()->after('status');
            $table->index(['status', 'work_date']);
        });

        Schema::table('app_notifications', function (Blueprint $table) {
            $table->string('subject_type')->nullable()->after('link');
            $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
            $table->index(['user_id', 'subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::table('work_assignments', function (Blueprint $table) {
            $table->dropIndex(['status', 'work_date']);
            $table->dropColumn('work_date');
        });

        Schema::table('app_notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'subject_type', 'subject_id']);
            $table->dropColumn(['subject_type', 'subject_id']);
        });
    }
};
