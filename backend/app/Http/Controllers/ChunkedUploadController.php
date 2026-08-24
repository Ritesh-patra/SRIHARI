<?php

namespace App\Http\Controllers;

use App\Models\ChunkedUpload;
use App\Models\ReadingUpload;
use App\Models\ReportAnalysisUpload;
use App\Support\ChunkedUploadStore;
use App\Support\UploadCompletionRouter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Resumable chunked uploads for 300 MB+ spreadsheets.
 *
 * The browser slices the file and posts one small part per request, so PHP's
 * upload_max_filesize / post_max_size only ever have to cover a single chunk
 * (1 MB by default) — which is what makes 300 MB uploads possible on shared
 * cPanel hosting.
 */
class ChunkedUploadController extends Controller
{
    /** Allow a little slack so a chunk is never rejected for boundary rounding. */
    private const CHUNK_SIZE_TOLERANCE = 4096;

    public function init(Request $request): JsonResponse
    {
        $maxBytes = max(1, (int) config('uploads.max_total_mb', 300)) * 1024 * 1024;
        $defaultChunk = max(65536, (int) config('uploads.chunk_size', 1048576));
        $allowed = (array) config('uploads.allowed_extensions', ['csv', 'txt', 'xlsx', 'xls']);

        $data = $request->validate([
            'purpose' => ['required', 'string', Rule::in(UploadCompletionRouter::purposes())],
            'original_name' => ['required', 'string', 'max:255'],
            'total_size' => ['required', 'integer', 'min:1', 'max:'.$maxBytes],
            'chunk_size' => ['nullable', 'integer', 'min:65536', 'max:33554432'],
            'mime' => ['nullable', 'string', 'max:191'],
            'uuid' => ['nullable', 'uuid'],
            'meta' => ['nullable', 'array'],
        ]);

        $originalName = basename(str_replace('\\', '/', $data['original_name']));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (! in_array($extension, $allowed, true)) {
            return response()->json([
                'message' => 'Only '.implode(', ', array_map(static fn ($e) => '.'.$e, $allowed)).' files can be uploaded.',
                'errors' => ['original_name' => ['Unsupported file type .'.$extension]],
            ], 422);
        }

        $meta = $this->validateMeta($request, $data['purpose']);

        $user = $request->user();
        $chunkSize = (int) ($data['chunk_size'] ?? $defaultChunk);
        $totalSize = (int) $data['total_size'];
        $totalChunks = (int) max(1, (int) ceil($totalSize / $chunkSize));

        $existing = $this->findResumable($request, $data, $originalName, $totalSize, $chunkSize);

        if ($existing) {
            $received = ChunkedUploadStore::receivedIndexes($existing);
            $existing->forceFill([
                'meta_json' => $meta,
                'received_chunks' => count($received),
                'status' => ChunkedUpload::STATUS_UPLOADING,
            ])->save();

            return response()->json([
                'uuid' => $existing->uuid,
                'chunk_size' => $existing->chunk_size,
                'total_chunks' => $existing->total_chunks,
                'received' => $received,
                'status' => $existing->status,
                'resumed' => true,
            ]);
        }

        $upload = ChunkedUpload::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'purpose' => $data['purpose'],
            'meta_json' => $meta,
            'original_name' => $originalName,
            'mime' => $data['mime'] ?? null,
            'extension' => $extension,
            'total_size' => $totalSize,
            'chunk_size' => $chunkSize,
            'total_chunks' => $totalChunks,
            'received_chunks' => 0,
            'status' => ChunkedUpload::STATUS_PENDING,
        ]);

        return response()->json([
            'uuid' => $upload->uuid,
            'chunk_size' => $upload->chunk_size,
            'total_chunks' => $upload->total_chunks,
            'received' => [],
            'status' => $upload->status,
            'resumed' => false,
        ], 201);
    }

    public function part(Request $request, string $uuid): JsonResponse
    {
        $upload = $this->findOwned($request, $uuid);

        if ($upload->status === ChunkedUpload::STATUS_COMPLETED) {
            return response()->json($this->progressPayload($upload));
        }

        if (! $upload->isOpen()) {
            return response()->json([
                'message' => 'This upload is no longer accepting chunks — start it again.',
            ], 409);
        }

        $request->validate([
            'index' => ['required', 'integer', 'min:0', 'max:'.max(0, $upload->total_chunks - 1)],
            'chunk' => ['required', 'file'],
        ]);

        $index = (int) $request->input('index');
        $chunk = $request->file('chunk');

        $expected = $index === $upload->total_chunks - 1
            ? $upload->total_size - ($index * $upload->chunk_size)
            : $upload->chunk_size;

        $size = (int) $chunk->getSize();
        if ($size < 1 || $size > $upload->chunk_size + self::CHUNK_SIZE_TOLERANCE || $size > $expected + self::CHUNK_SIZE_TOLERANCE) {
            return response()->json([
                'message' => "Chunk {$index} has an unexpected size ({$size} bytes).",
            ], 422);
        }

        $alreadyHad = ChunkedUploadStore::hasPart($upload, $index);
        ChunkedUploadStore::storePart($upload, $index, $chunk);

        if (! $alreadyHad) {
            $upload->increment('received_chunks');
        }

        if ($upload->status === ChunkedUpload::STATUS_PENDING) {
            $upload->forceFill(['status' => ChunkedUpload::STATUS_UPLOADING])->save();
        }

        return response()->json($this->progressPayload($upload->refresh()));
    }

    public function complete(Request $request, string $uuid): JsonResponse
    {
        $upload = $this->findOwned($request, $uuid);

        if ($upload->status === ChunkedUpload::STATUS_COMPLETED) {
            return response()->json($this->progressPayload($upload) + [
                'message' => 'Already uploaded.',
            ]);
        }

        if (! $upload->isOpen()) {
            return response()->json(['message' => 'This upload was cancelled — start it again.'], 409);
        }

        @set_time_limit(0);

        $received = ChunkedUploadStore::receivedIndexes($upload);
        if (count($received) !== $upload->total_chunks) {
            $upload->forceFill(['received_chunks' => count($received)])->save();

            return response()->json([
                'message' => 'Some chunks are still missing — upload will resume.',
                'received' => $received,
                'total_chunks' => $upload->total_chunks,
            ], 422);
        }

        $upload->forceFill([
            'status' => ChunkedUpload::STATUS_MERGING,
            'received_chunks' => count($received),
            'error' => null,
        ])->save();

        $disk = (string) config('uploads.disk', 'local');

        try {
            $target = UploadCompletionRouter::targetPath($upload);
            $written = ChunkedUploadStore::merge($upload, $target);

            if ($written !== (int) $upload->total_size) {
                Storage::disk($disk)->delete($target);

                throw new \RuntimeException("Merged size {$written} does not match the expected {$upload->total_size} bytes.");
            }

            ChunkedUploadStore::cleanupChunks($upload);

            $upload->forceFill([
                'status' => ChunkedUpload::STATUS_COMPLETED,
                'path' => $target,
                'completed_at' => now(),
                'error' => null,
            ])->save();

            $domain = UploadCompletionRouter::handle($upload);

            return response()->json($this->progressPayload($upload) + [
                'message' => $domain['message'],
                'redirect' => $domain['redirect'] ?? null,
                'domain' => $domain,
            ]);
        } catch (\Throwable $e) {
            $upload->forceFill([
                'status' => ChunkedUpload::STATUS_FAILED,
                'error' => Str::limit($e->getMessage(), 1000),
            ])->save();

            return response()->json([
                'message' => 'Upload could not be finalised: '.$e->getMessage(),
            ], 422);
        }
    }

    public function status(Request $request, string $uuid): JsonResponse
    {
        $upload = $this->findOwned($request, $uuid);

        return response()->json($this->progressPayload($upload));
    }

    public function abort(Request $request, string $uuid): JsonResponse
    {
        $upload = $this->findOwned($request, $uuid);

        ChunkedUploadStore::cleanupChunks($upload);

        if ($upload->status !== ChunkedUpload::STATUS_COMPLETED) {
            $upload->forceFill([
                'status' => ChunkedUpload::STATUS_ABORTED,
                'received_chunks' => 0,
            ])->save();
        }

        return response()->json(['status' => $upload->status]);
    }

    private function findOwned(Request $request, string $uuid): ChunkedUpload
    {
        $upload = ChunkedUpload::query()->where('uuid', $uuid)->firstOrFail();
        $user = $request->user();

        if ((int) $upload->user_id !== (int) $user->id && ! $user->isAdmin()) {
            abort(403, 'You cannot access this upload.');
        }

        return $upload;
    }

    /** @param  array<string, mixed>  $data */
    private function findResumable(Request $request, array $data, string $originalName, int $totalSize, int $chunkSize): ?ChunkedUpload
    {
        $user = $request->user();

        if (! empty($data['uuid'])) {
            $candidate = ChunkedUpload::query()
                ->where('uuid', $data['uuid'])
                ->where('user_id', $user->id)
                ->first();

            if ($candidate && $candidate->isOpen()
                && (int) $candidate->total_size === $totalSize
                && (int) $candidate->chunk_size === $chunkSize) {
                return $candidate;
            }
        }

        return ChunkedUpload::query()
            ->where('user_id', $user->id)
            ->where('purpose', $data['purpose'])
            ->where('original_name', $originalName)
            ->where('total_size', $totalSize)
            ->where('chunk_size', $chunkSize)
            ->whereIn('status', [ChunkedUpload::STATUS_PENDING, ChunkedUpload::STATUS_UPLOADING])
            ->latest('id')
            ->first();
    }

    /** @return array<string, mixed> */
    private function progressPayload(ChunkedUpload $upload): array
    {
        return [
            'uuid' => $upload->uuid,
            'status' => $upload->status,
            'received' => (int) $upload->received_chunks,
            'total_chunks' => (int) $upload->total_chunks,
            'percent' => $upload->percent(),
            'path' => $upload->path,
            'error' => $upload->error,
            'domain' => UploadCompletionRouter::domainStatus($upload),
        ];
    }

    /** @return array<string, mixed> */
    private function validateMeta(Request $request, string $purpose): array
    {
        if ($purpose === ChunkedUpload::PURPOSE_REPORT_ANALYSIS) {
            $validated = $request->validate([
                'meta.source' => ['required', 'string', Rule::in(array_keys(ReportAnalysisUpload::sourceLabels()))],
            ]);

            return ['source' => $validated['meta']['source']];
        }

        if ($purpose === ChunkedUpload::PURPOSE_READING) {
            $validated = $request->validate([
                'meta.type' => ['required', 'string', Rule::in(array_keys(ReadingUpload::typeLabels()))],
                'meta.period_from' => ['nullable', 'date'],
                'meta.period_to' => ['nullable', 'date', 'after_or_equal:meta.period_from'],
                'meta.period_label' => ['nullable', 'string', 'max:64'],
            ]);

            $meta = $validated['meta'];

            return [
                'type' => $meta['type'],
                'period_from' => $meta['period_from'] ?? null,
                'period_to' => $meta['period_to'] ?? null,
                'period_label' => $meta['period_label'] ?? null,
            ];
        }

        return [];
    }
}
