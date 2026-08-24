<?php

namespace App\Http\Controllers;

use App\Jobs\ImportReadingUpload;
use App\Models\ReadingUpload;
use App\Support\SimpleXlsxExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reading Upload — Feeder / DTR / Consumer consumption files (300 MB+).
 *
 * Files arrive through the shared chunked uploader; ChunkedUploadController's
 * complete step creates the ReadingUpload row and queues the import, so this
 * controller only owns the page, history, status polling and cleanup.
 */
class ReadingUploadController extends Controller
{
    /** Rows shown per tab; the Excel export covers the full history. */
    private const HISTORY_LIMIT = 25;

    public function index(Request $request)
    {
        $labels = ReadingUpload::typeLabels();
        $type = (string) $request->query('type', ReadingUpload::TYPE_FEEDER);
        if (! array_key_exists($type, $labels)) {
            $type = ReadingUpload::TYPE_FEEDER;
        }

        $userId = (int) $request->user()->id;

        $histories = [];
        foreach (array_keys($labels) as $key) {
            $histories[$key] = ReadingUpload::query()
                ->where('user_id', $userId)
                ->where('type', $key)
                ->latest('id')
                ->limit(self::HISTORY_LIMIT)
                ->get();
        }

        $counts = ReadingUpload::query()
            ->where('user_id', $userId)
            ->select('type', DB::raw('COUNT(*) as total'))
            ->groupBy('type')
            ->pluck('total', 'type');

        $busyIds = ReadingUpload::query()
            ->where('user_id', $userId)
            ->whereIn('status', [ReadingUpload::STATUS_PENDING, ReadingUpload::STATUS_PROCESSING])
            ->pluck('id')
            ->all();

        return view('reading-uploads.index', [
            'labels' => $labels,
            'type' => $type,
            'histories' => $histories,
            'counts' => $counts,
            'busyIds' => $busyIds,
            'historyLimit' => self::HISTORY_LIMIT,
            'chunkSize' => (int) config('uploads.chunk_size', 1048576),
            'maxTotalMb' => (int) config('uploads.max_total_mb', 300),
            'allowedExtensions' => (array) config('uploads.allowed_extensions', ['csv', 'txt', 'xlsx', 'xls']),
        ]);
    }

    public function show(Request $request, ReadingUpload $readingUpload)
    {
        $this->authorizeRow($request, $readingUpload);

        $table = ReadingUpload::readingTable((string) $readingUpload->type);

        $sample = DB::table($table)
            ->where('reading_upload_id', $readingUpload->id)
            ->orderBy('id')
            ->limit(50)
            ->get();

        return view('reading-uploads.show', [
            'upload' => $readingUpload,
            'sample' => $sample,
            'labels' => ReadingUpload::typeLabels(),
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['nullable', 'string', 'max:2000'],
            'type' => ['nullable', 'string', Rule::in(array_keys(ReadingUpload::typeLabels()))],
        ]);

        $query = ReadingUpload::query()->where('user_id', $request->user()->id);

        if (! empty($data['ids'])) {
            $ids = array_slice(array_filter(array_map('intval', explode(',', $data['ids']))), 0, 200);
            $query->whereIn('id', $ids);
        } else {
            $query->whereIn('status', [ReadingUpload::STATUS_PENDING, ReadingUpload::STATUS_PROCESSING]);
            if (! empty($data['type'])) {
                $query->where('type', $data['type']);
            }
        }

        $rows = $query->latest('id')->limit(200)->get()->map(fn (ReadingUpload $row) => [
            'id' => (int) $row->id,
            'status' => (string) $row->status,
            'rows_total' => $row->rows_total,
            'rows_imported' => $row->rows_imported,
            'rows_failed' => $row->rows_failed,
            'error' => $row->error,
            'processed_at' => $row->processed_at?->timezone(config('app.timezone'))->format('d M Y, H:i'),
        ]);

        return response()->json(['uploads' => $rows]);
    }

    public function reprocess(Request $request, ReadingUpload $readingUpload)
    {
        $this->authorizeRow($request, $readingUpload);

        if ($readingUpload->isBusy()) {
            return back()->with('success', 'This file is already being processed.');
        }

        $readingUpload->forceFill([
            'status' => ReadingUpload::STATUS_PENDING,
            'error' => null,
            'processed_at' => null,
        ])->save();

        ImportReadingUpload::dispatch($readingUpload->id)->afterResponse();

        return back()->with('success', 'Re-import queued for '.$readingUpload->original_name.'.');
    }

    public function destroy(Request $request, ReadingUpload $readingUpload)
    {
        $this->authorizeRow($request, $readingUpload);

        $name = (string) $readingUpload->original_name;
        $table = ReadingUpload::readingTable((string) $readingUpload->type);

        // Chunked delete: a single DELETE over millions of rows can time out.
        do {
            $deleted = DB::table($table)
                ->where('reading_upload_id', $readingUpload->id)
                ->limit(5000)
                ->delete();
        } while ($deleted > 0);

        $disk = (string) config('uploads.disk', 'local');
        if ($readingUpload->path && Storage::disk($disk)->exists($readingUpload->path)) {
            Storage::disk($disk)->delete($readingUpload->path);
        }

        $readingUpload->delete();

        return back()->with('success', "Deleted {$name} and its parsed rows.");
    }

    public function export(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'type' => ['nullable', 'string', Rule::in(array_keys(ReadingUpload::typeLabels()))],
        ]);

        $labels = ReadingUpload::typeLabels();

        $query = ReadingUpload::query()->where('user_id', $request->user()->id);
        if (! empty($data['type'])) {
            $query->where('type', $data['type']);
        }

        $rows = $query->latest('id')->get()->map(fn (ReadingUpload $row) => [
            $row->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i'),
            $labels[$row->type] ?? $row->type,
            $row->original_name,
            $row->size_bytes !== null ? round($row->size_bytes / 1048576, 2).' MB' : '',
            $row->period_label ?: trim(($row->period_from?->format('d M Y') ?? '').' - '.($row->period_to?->format('d M Y') ?? ''), ' -'),
            $row->rows_total,
            $row->rows_imported,
            $row->rows_failed,
            ucfirst((string) $row->status),
            $row->error,
        ]);

        $suffix = ! empty($data['type']) ? '-'.$data['type'] : '';

        return SimpleXlsxExporter::download(
            'reading-uploads'.$suffix.'-'.now()->format('Ymd_His'),
            ['Date', 'Type', 'File', 'Size', 'Period', 'Rows total', 'Rows imported', 'Rows failed', 'Status', 'Error'],
            $rows
        );
    }

    private function authorizeRow(Request $request, ReadingUpload $upload): void
    {
        $user = $request->user();

        if ((int) $upload->user_id !== (int) $user->id && ! $user->isAdmin()) {
            abort(403, 'You cannot access this upload.');
        }
    }
}
