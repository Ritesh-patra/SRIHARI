<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('report_analysis_uploads')) {
            return;
        }

        Schema::table('report_analysis_uploads', function (Blueprint $table) {
            if (! Schema::hasColumn('report_analysis_uploads', 'status')) {
                $table->string('status', 32)->default('completed');
            }
            if (! Schema::hasColumn('report_analysis_uploads', 'parse_error')) {
                $table->text('parse_error')->nullable();
            }
            if (! Schema::hasColumn('report_analysis_uploads', 'chunked_upload_id')) {
                $table->unsignedBigInteger('chunked_upload_id')->nullable();
            }
            if (! Schema::hasColumn('report_analysis_uploads', 'size_bytes')) {
                $table->unsignedBigInteger('size_bytes')->nullable();
            }
            if (! Schema::hasColumn('report_analysis_uploads', 'parsed_at')) {
                $table->timestamp('parsed_at')->nullable();
            }
        });

        if (! $this->hasIndex('report_analysis_uploads', 'report_analysis_uploads_status_index')) {
            Schema::table('report_analysis_uploads', function (Blueprint $table) {
                $table->index('status', 'report_analysis_uploads_status_index');
            });
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        try {
            foreach (Schema::getIndexes($table) as $existing) {
                if (strcasecmp((string) ($existing['name'] ?? ''), $index) === 0) {
                    return true;
                }
            }
        } catch (\Throwable) {
            // Older drivers without index introspection — assume missing.
        }

        return false;
    }

    public function down(): void
    {
        if (! Schema::hasTable('report_analysis_uploads')) {
            return;
        }

        Schema::table('report_analysis_uploads', function (Blueprint $table) {
            foreach (['status', 'parse_error', 'chunked_upload_id', 'size_bytes', 'parsed_at'] as $column) {
                if (Schema::hasColumn('report_analysis_uploads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
