<?php

namespace App\Jobs;

use App\Models\ReportAnalysisUpload;
use App\Support\ReportAnalysisFileParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Reads headers + data-row count for one Report Analysis source.
 *
 * Dispatched afterResponse() when a chunked upload completes and re-run by
 * `seas:process-uploads` from the scheduler, because production has no queue worker.
 */
class ParseReportAnalysisUpload implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $uploadId) {}

    public function handle(): void
    {
        $upload = ReportAnalysisUpload::find($this->uploadId);
        if (! $upload) {
            return;
        }

        $upload->forceFill([
            'status' => ReportAnalysisUpload::STATUS_PROCESSING,
            'parse_error' => null,
        ])->save();

        try {
            $disk = (string) config('uploads.disk', 'local');
            $relative = (string) $upload->path;

            if ($relative === '' || ! Storage::disk($disk)->exists($relative)) {
                throw new \RuntimeException('Uploaded file is missing from storage.');
            }

            $absolute = Storage::disk($disk)->path($relative);
            $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION) ?: pathinfo((string) $upload->original_name, PATHINFO_EXTENSION));

            $parsed = ReportAnalysisFileParser::inspect($absolute, $extension);

            $upload->forceFill([
                'row_count' => $parsed['row_count'],
                'headers_json' => $parsed['headers'],
                'parse_note' => $parsed['parse_note'],
                'size_bytes' => Storage::disk($disk)->size($relative) ?: $upload->size_bytes,
                'status' => ReportAnalysisUpload::STATUS_COMPLETED,
                'parse_error' => null,
                'parsed_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('Report analysis parse failed', ['upload_id' => $upload->id, 'error' => $e->getMessage()]);

            $upload->forceFill([
                'status' => ReportAnalysisUpload::STATUS_FAILED,
                'parse_error' => \Illuminate\Support\Str::limit($e->getMessage(), 500),
                'parsed_at' => now(),
            ])->save();
        }
    }
}
