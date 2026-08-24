<?php

namespace App\Support;

use App\Jobs\ImportReadingUpload;
use App\Jobs\ParseReportAnalysisUpload;
use App\Models\ChunkedUpload;
use App\Models\ReadingUpload;
use App\Models\ReportAnalysisUpload;
use Illuminate\Support\Facades\Storage;

/**
 * Turns a finished chunked upload into the domain record for its purpose, and
 * kicks off parsing.
 *
 * Parsing is dispatched afterResponse() so it starts immediately even though
 * production runs QUEUE_CONNECTION=sync with no worker; `seas:process-uploads`
 * on the scheduler cron is the safety net when that PHP process is cut short.
 */
class UploadCompletionRouter
{
    /** @return list<string> */
    public static function purposes(): array
    {
        return [ChunkedUpload::PURPOSE_REPORT_ANALYSIS, ChunkedUpload::PURPOSE_READING];
    }

    /** Where the merged file should land on the uploads disk. */
    public static function targetPath(ChunkedUpload $upload): string
    {
        $userId = (int) $upload->user_id;
        $extension = strtolower((string) ($upload->extension ?: pathinfo((string) $upload->original_name, PATHINFO_EXTENSION) ?: 'bin'));
        $stamp = now()->format('Ymd_His');
        $meta = $upload->meta();

        return match ($upload->purpose) {
            ChunkedUpload::PURPOSE_REPORT_ANALYSIS => sprintf(
                'report_analysis/%d/%s_%s.%s',
                $userId,
                (string) ($meta['source'] ?? 'source'),
                $stamp,
                $extension
            ),
            ChunkedUpload::PURPOSE_READING => sprintf(
                'reading_uploads/%d/%s_%s.%s',
                $userId,
                (string) ($meta['type'] ?? 'reading'),
                $stamp,
                $extension
            ),
            default => sprintf('chunked_uploads/%d/%s.%s', $userId, $upload->uuid, $extension),
        };
    }

    /**
     * Create (or refresh) the domain record and queue its parse.
     *
     * @return array{type: string, id: int|null, status: string, message: string, redirect: string|null}
     */
    public static function handle(ChunkedUpload $upload): array
    {
        return match ($upload->purpose) {
            ChunkedUpload::PURPOSE_REPORT_ANALYSIS => self::handleReportAnalysis($upload),
            ChunkedUpload::PURPOSE_READING => self::handleReading($upload),
            default => [
                'type' => (string) $upload->purpose,
                'id' => null,
                'status' => ReportAnalysisUpload::STATUS_COMPLETED,
                'message' => 'File uploaded.',
                'redirect' => null,
            ],
        };
    }

    /** Progress payload for the status endpoint. */
    public static function domainStatus(ChunkedUpload $upload): ?array
    {
        if ($upload->purpose === ChunkedUpload::PURPOSE_REPORT_ANALYSIS) {
            $row = ReportAnalysisUpload::query()->where('chunked_upload_id', $upload->id)->first();
            if (! $row) {
                return null;
            }

            return [
                'type' => ChunkedUpload::PURPOSE_REPORT_ANALYSIS,
                'id' => (int) $row->id,
                'status' => $row->parseStatus(),
                'row_count' => $row->row_count,
                'parse_note' => $row->parse_note,
                'error' => $row->parse_error,
            ];
        }

        if ($upload->purpose === ChunkedUpload::PURPOSE_READING) {
            $row = ReadingUpload::query()->where('chunked_upload_id', $upload->id)->first();
            if (! $row) {
                return null;
            }

            return [
                'type' => ChunkedUpload::PURPOSE_READING,
                'id' => (int) $row->id,
                'status' => (string) $row->status,
                'rows_total' => $row->rows_total,
                'rows_imported' => $row->rows_imported,
                'rows_failed' => $row->rows_failed,
                'error' => $row->error,
            ];
        }

        return null;
    }

    private static function handleReportAnalysis(ChunkedUpload $upload): array
    {
        $meta = $upload->meta();
        $source = (string) ($meta['source'] ?? '');
        if (! array_key_exists($source, ReportAnalysisUpload::sourceLabels())) {
            throw new \RuntimeException('Unknown report analysis source.');
        }

        $disk = (string) config('uploads.disk', 'local');
        $userId = (int) $upload->user_id;

        $existing = ReportAnalysisUpload::query()
            ->where('user_id', $userId)
            ->where('source', $source)
            ->first();

        if ($existing && $existing->path && $existing->path !== $upload->path && Storage::disk($disk)->exists($existing->path)) {
            Storage::disk($disk)->delete($existing->path);
        }

        $row = ReportAnalysisUpload::updateOrCreate(
            ['user_id' => $userId, 'source' => $source],
            [
                'path' => $upload->path,
                'original_name' => $upload->original_name,
                'row_count' => null,
                'headers_json' => null,
                'parse_note' => null,
                'status' => ReportAnalysisUpload::STATUS_PENDING,
                'parse_error' => null,
                'chunked_upload_id' => $upload->id,
                'size_bytes' => $upload->total_size,
                'parsed_at' => null,
            ]
        );

        ParseReportAnalysisUpload::dispatch($row->id)->afterResponse();

        $label = ReportAnalysisUpload::sourceLabels()[$source] ?? $source;

        return [
            'type' => ChunkedUpload::PURPOSE_REPORT_ANALYSIS,
            'id' => (int) $row->id,
            'status' => ReportAnalysisUpload::STATUS_PENDING,
            'message' => "{$label} uploaded: {$upload->original_name} — reading headers…",
            'redirect' => route('report-analysis.index'),
        ];
    }

    private static function handleReading(ChunkedUpload $upload): array
    {
        $meta = $upload->meta();
        $type = (string) ($meta['type'] ?? '');
        if (! array_key_exists($type, ReadingUpload::typeLabels())) {
            throw new \RuntimeException('Unknown reading type.');
        }

        $row = ReadingUpload::create([
            'user_id' => (int) $upload->user_id,
            'type' => $type,
            'chunked_upload_id' => $upload->id,
            'path' => $upload->path,
            'original_name' => $upload->original_name,
            'size_bytes' => $upload->total_size,
            'period_from' => $meta['period_from'] ?? null,
            'period_to' => $meta['period_to'] ?? null,
            'period_label' => $meta['period_label'] ?? null,
            'status' => ReadingUpload::STATUS_PENDING,
        ]);

        ImportReadingUpload::dispatch($row->id)->afterResponse();

        $label = ReadingUpload::typeLabels()[$type] ?? $type;

        return [
            'type' => ChunkedUpload::PURPOSE_READING,
            'id' => (int) $row->id,
            'status' => ReadingUpload::STATUS_PENDING,
            'message' => "{$label} uploaded: {$upload->original_name} — import queued.",
            'redirect' => route('reading-uploads.index'),
        ];
    }
}
