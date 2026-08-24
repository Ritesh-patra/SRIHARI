<?php

namespace App\Http\Controllers;

use App\Jobs\ParseReportAnalysisUpload;
use App\Models\ChunkedUpload;
use App\Models\ReportAnalysisUpload;
use App\Support\ReportAnalysisFileParser;
use App\Support\UploadCompletionRouter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ReportAnalysisController extends Controller
{
    public function index(Request $request)
    {
        $labels = ReportAnalysisUpload::sourceLabels();
        $uploads = ReportAnalysisUpload::query()
            ->where('user_id', $request->user()->id)
            ->get()
            ->keyBy('source');

        $selection = $request->session()->get('report_analysis.selection');

        return view('report-analysis.index', [
            'labels' => $labels,
            'uploads' => $uploads,
            'selection' => $selection,
            'chunkSize' => (int) config('uploads.chunk_size', 1048576),
            'maxTotalMb' => (int) config('uploads.max_total_mb', 300),
            'allowedExtensions' => (array) config('uploads.allowed_extensions', ['csv', 'txt', 'xlsx', 'xls']),
        ]);
    }

    /**
     * Legacy direct upload — still supported for small files and no-JS fallback.
     * Large files come in through the chunked uploader, which creates the row in
     * UploadCompletionRouter instead.
     */
    public function upload(Request $request)
    {
        $sourceKeys = array_keys(ReportAnalysisUpload::sourceLabels());

        // A completed chunked upload can be handed over by uuid.
        if ($request->filled('chunked_upload_uuid')) {
            return $this->adoptChunkedUpload($request);
        }

        $maxKilobytes = max(1, (int) config('uploads.max_total_mb', 300)) * 1024;

        $data = $request->validate([
            'source' => ['required', Rule::in($sourceKeys)],
            'file' => [
                'required',
                'file',
                'max:'.$maxKilobytes,
                Rule::extensions((array) config('uploads.allowed_extensions', ['csv', 'txt', 'xlsx', 'xls'])),
            ],
        ]);

        $userId = (int) $request->user()->id;
        $source = $data['source'];
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $disk = (string) config('uploads.disk', 'local');

        $dir = "report_analysis/{$userId}";
        $storedName = $source.'_'.now()->format('Ymd_His').'.'.$ext;
        $size = (int) $file->getSize();
        $originalName = $file->getClientOriginalName();
        $path = $file->storeAs($dir, $storedName, $disk);

        $existing = ReportAnalysisUpload::query()
            ->where('user_id', $userId)
            ->where('source', $source)
            ->first();

        if ($existing && $existing->path && $existing->path !== $path && Storage::disk($disk)->exists($existing->path)) {
            Storage::disk($disk)->delete($existing->path);
        }

        // Small files stay instant; anything bigger is handed to the background command.
        $inlineLimit = (int) config('uploads.inline_parse_max_bytes', 8388608);
        $parseNow = $size > 0 && $size <= $inlineLimit;

        $parsed = $parseNow
            ? ReportAnalysisFileParser::inspect(Storage::disk($disk)->path($path), $ext)
            : ['headers' => null, 'row_count' => null, 'parse_note' => null];

        $row = ReportAnalysisUpload::updateOrCreate(
            ['user_id' => $userId, 'source' => $source],
            [
                'path' => $path,
                'original_name' => $originalName,
                'row_count' => $parsed['row_count'],
                'headers_json' => $parsed['headers'],
                'parse_note' => $parsed['parse_note'],
                'status' => $parseNow ? ReportAnalysisUpload::STATUS_COMPLETED : ReportAnalysisUpload::STATUS_PENDING,
                'parse_error' => null,
                'chunked_upload_id' => null,
                'size_bytes' => $size,
                'parsed_at' => $parseNow ? now() : null,
            ]
        );

        if (! $parseNow) {
            ParseReportAnalysisUpload::dispatch($row->id)->afterResponse();
        }

        $label = ReportAnalysisUpload::sourceLabels()[$source] ?? $source;
        $suffix = $parseNow ? '' : ' — reading headers in the background';

        return back()->with('success', "{$label} uploaded: {$originalName}{$suffix}");
    }

    public function saveSelection(Request $request)
    {
        $uploadedKeys = ReportAnalysisUpload::query()
            ->where('user_id', $request->user()->id)
            ->pluck('source')
            ->all();

        $data = $request->validate([
            'compare_count' => ['required', 'integer', Rule::in([2, 3, 4])],
            'sources' => ['required', 'array'],
            'sources.*' => ['string', Rule::in(array_keys(ReportAnalysisUpload::sourceLabels()))],
        ]);

        $sources = array_values(array_unique($data['sources']));
        $count = (int) $data['compare_count'];

        if (count($sources) !== $count) {
            return back()->withErrors([
                'sources' => "Select exactly {$count} uploaded sources.",
            ])->withInput();
        }

        foreach ($sources as $key) {
            if (! in_array($key, $uploadedKeys, true)) {
                return back()->withErrors([
                    'sources' => 'Only successfully uploaded sources can be selected.',
                ])->withInput();
            }
        }

        $request->session()->put('report_analysis.selection', [
            'compare_count' => $count,
            'sources' => $sources,
            'saved_at' => now()->toIso8601String(),
        ]);

        return back()->with('success', 'Selection saved — comparison rules will be configured next');
    }

    private function adoptChunkedUpload(Request $request)
    {
        $request->validate([
            'chunked_upload_uuid' => ['required', 'uuid'],
        ]);

        $upload = ChunkedUpload::query()
            ->where('uuid', $request->input('chunked_upload_uuid'))
            ->where('purpose', ChunkedUpload::PURPOSE_REPORT_ANALYSIS)
            ->firstOrFail();

        $user = $request->user();
        if ((int) $upload->user_id !== (int) $user->id && ! $user->isAdmin()) {
            abort(403, 'You cannot access this upload.');
        }

        if ($upload->status !== ChunkedUpload::STATUS_COMPLETED) {
            return back()->withErrors(['file' => 'That upload has not finished yet.']);
        }

        $result = UploadCompletionRouter::handle($upload);

        return back()->with('success', $result['message']);
    }
}
