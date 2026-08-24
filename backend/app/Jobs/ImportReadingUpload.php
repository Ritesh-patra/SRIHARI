<?php

namespace App\Jobs;

use App\Models\ReadingUpload;
use App\Support\ReadingImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Streams one Feeder / DTR / Consumer consumption file into its readings table.
 *
 * Dispatched afterResponse() when the chunked upload completes and re-run by
 * `seas:process-uploads` from the scheduler, because production has no queue worker.
 */
class ImportReadingUpload implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $readingUploadId) {}

    public function handle(): void
    {
        $upload = ReadingUpload::find($this->readingUploadId);
        if (! $upload) {
            return;
        }

        $upload->forceFill([
            'status' => ReadingUpload::STATUS_PROCESSING,
            'error' => null,
            'processed_at' => null,
            'rows_total' => 0,
            'rows_imported' => 0,
            'rows_failed' => 0,
        ])->save();

        try {
            (new ReadingImporter($upload))->run();

            $upload->forceFill([
                'status' => ReadingUpload::STATUS_COMPLETED,
                'error' => null,
                'processed_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('Reading import failed', ['reading_upload_id' => $upload->id, 'error' => $e->getMessage()]);

            $upload->forceFill([
                'status' => ReadingUpload::STATUS_FAILED,
                'error' => Str::limit($e->getMessage(), 1000),
                'processed_at' => now(),
            ])->save();
        }
    }
}
