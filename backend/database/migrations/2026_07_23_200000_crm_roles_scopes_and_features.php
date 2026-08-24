<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('role');
            $table->boolean('force_password_change')->default(false)->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('force_password_change');
        });

        // Migrate legacy role names
        DB::table('users')->where('role', 'surveyor')->update(['role' => 'field_executive']);
        DB::table('users')->where('role', 'supervisor')->update(['role' => 'manager']);

        Schema::create('user_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('scope_type'); // region|circle|division
            $table->unsignedBigInteger('scope_id');
            $table->timestamps();
            $table->unique(['user_id', 'scope_type', 'scope_id']);
        });

        Schema::create('work_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('feeder_id')->nullable()->constrained('feeders')->nullOnDelete();
            $table->foreignId('dtr_id')->nullable()->constrained('dtrs')->nullOnDelete();
            $table->string('status')->default('open'); // open|in_progress|done
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('link')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::table('consumers', function (Blueprint $table) {
            $table->foreignId('feeder_id')->nullable()->after('dtr_id')->constrained('feeders')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('phase');
            $table->string('account_no')->nullable()->after('ivrs');
        });

        Schema::table('consumer_surveys', function (Blueprint $table) {
            // saved | not_accessible | pdc | new
            $table->string('survey_flag')->nullable()->after('status');
        });

        Schema::table('regions', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
        });
        Schema::table('circles', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
        });
        Schema::table('divisions', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
        });
        Schema::table('zones', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
        });
        Schema::table('substations', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
        });
        Schema::table('feeders', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
        });
        Schema::table('dtrs', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('dtrs', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('is_active');
        });
        Schema::table('feeders', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('is_active');
        });
        Schema::table('substations', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('is_active');
        });
        Schema::table('zones', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('is_active');
        });
        Schema::table('divisions', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('is_active');
        });
        Schema::table('circles', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('is_active');
        });
        Schema::table('regions', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('is_active');
        });
        Schema::table('consumer_surveys', function (Blueprint $table) {
            $table->dropColumn('survey_flag');
        });
        Schema::table('consumers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('feeder_id');
            $table->dropColumn(['is_active', 'account_no']);
        });
        Schema::dropIfExists('app_notifications');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('work_assignments');
        Schema::dropIfExists('user_scopes');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'force_password_change', 'last_login_at']);
        });
    }
};
