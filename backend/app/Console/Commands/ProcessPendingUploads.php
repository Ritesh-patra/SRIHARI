<?php

namespace App\Console\Commands;

use App\Jobs\ImportReadingUpload;
use App\Jobs\ParseReportAnalysisUpload;
use App\Models\ReadingUpload;
use App\Models\ReportAnalysisUpload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Background parser for large uploads.
 *
 * Production runs QUEUE_CONNECTION=sync on shared cPanel hosting with no
 * `queue:work` daemon, so the scheduler cron (`* * * * * php artisan schedule:run`)
 * drives this command every minute instead. A cache lock keeps overlapping cron
 * ticks from processing the same row twice.
 */
class ProcessPendingUploads extends Command
{
    protected $signature = 'seas:process-uploads
                            {--limit= : How many rows of each kind to process in this run}
                            {--only= : Restrict to "report-analysis" or "readings"}';

    protected $description = 'Parse pending Report Analysis uploads and import pending reading files';

    public function handle(): int
    {
        $lock = Cache::lock('seas:process-uploads', 1800);

        if (! $lock->get()) {
            $this->line('Another seas:process-uploads run is still working — skipping.');

            return self::SUCCESS;
        }

        @set_time_limit(0);
        @ini_set('memory_limit', (string) config('uploads.process_memory_limit', '1024M'));

        $limit = (int) ($this->option('limit') ?: config('uploads.process_limit', 2));
        $limit = max(1, $limit);
        $only = (string) ($this->option('only') ?? '');

        try {
            $this->requeueStuckRows();

            $processed = 0;

            if ($only === '' || $only === 'report-analysis') {
                $processed += $this->processReportAnalysis($limit);
            }

            if ($only === '' || $only === 'readings') {
                $processed += $this->processReadings($limit);
            }

            $this->info("Processed {$processed} upload(s).");
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }

    private function processReportAnalysis(int $limit): int
    {
        if (! Schema::hasTable('report_analysis_uploads') || ! Schema::hasColumn('report_analysis_uploads', 'status')) {
            return 0;
        }

        $rows = ReportAnalysisUpload::query()
            ->where('status', ReportAnalysisUpload::STATUS_PENDING)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($rows as $row) {
            $this->line("Parsing report analysis upload #{$row->id} ({$row->source})…");
            (new ParseReportAnalysisUpload((int) $row->id))->handle();
        }

        return $rows->count();
    }

    private function processReadings(int $limit): int
    {
        if (! Schema::hasTable('reading_uploads')) {
            return 0;
        }

        $rows = ReadingUpload::query()
            ->where('status', ReadingUpload::STATUS_PENDING)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($rows as $row) {
            $this->line("Importing reading upload #{$row->id} ({$row->type})…");
            (new ImportReadingUpload((int) $row->id))->handle();
        }

        return $rows->count();
    }

    /**
     * A PHP process killed mid-parse (max_execution_time, memory, deploy) would
     * otherwise leave rows stuck on "processing" forever. Imports purge their own
     * rows before re-reading, so retrying is safe.
     */
    private function requeueStuckRows(): void
    {
        $cutoff = now()->subMinutes(max(5, (int) config('uploads.processing_timeout_minutes', 30)));

        if (Schema::hasTable('report_analysis_uploads') && Schema::hasColumn('report_analysis_uploads', 'status')) {
            $count = ReportAnalysisUpload::query()
                ->where('status', ReportAnalysisUpload::STATUS_PROCESSING)
                ->where('updated_at', '<', $cutoff)
                ->update(['status' => ReportAnalysisUpload::STATUS_PENDING]);

            if ($count > 0) {
                $this->warn("Requeued {$count} stalled report analysis upload(s).");
            }
        }

        if (Schema::hasTable('reading_uploads')) {
            $count = ReadingUpload::query()
                ->where('status', ReadingUpload::STATUS_PROCESSING)
                ->where('updated_at', '<', $cutoff)
                ->update(['status' => ReadingUpload::STATUS_PENDING]);

            if ($count > 0) {
                $this->warn("Requeued {$count} stalled reading upload(s).");
            }
        }
    }
}
