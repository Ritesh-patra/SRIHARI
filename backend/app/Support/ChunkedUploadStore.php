<?php

namespace App\Support;

use App\Models\ChunkedUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Filesystem side of chunked uploads: part naming, resume discovery and the
 * stream-copy merge. Nothing here ever reads a whole file into memory.
 */
class ChunkedUploadStore
{
    private const COPY_BUFFER = 1048576;

    public static function disk(): string
    {
        return (string) config('uploads.disk', 'local');
    }

    public static function partName(int $index): string
    {
        return 'part_'.str_pad((string) $index, 6, '0', STR_PAD_LEFT);
    }

    public static function partRelativePath(ChunkedUpload $upload, int $index): string
    {
        return $upload->chunkDirectory().'/'.self::partName($index);
    }

    public static function chunkDirectoryAbsolute(ChunkedUpload $upload): string
    {
        return Storage::disk(self::disk())->path($upload->chunkDirectory());
    }

    /** @return list<int> Sorted indexes already on disk. */
    public static function receivedIndexes(ChunkedUpload $upload): array
    {
        $dir = self::chunkDirectoryAbsolute($upload);
        if (! is_dir($dir)) {
            return [];
        }

        $indexes = [];
        foreach ((array) scandir($dir) as $entry) {
            if (! is_string($entry) || ! str_starts_with($entry, 'part_')) {
                continue;
            }
            $indexes[] = (int) substr($entry, 5);
        }

        sort($indexes);

        return $indexes;
    }

    public static function hasPart(ChunkedUpload $upload, int $index): bool
    {
        return is_file(self::chunkDirectoryAbsolute($upload).DIRECTORY_SEPARATOR.self::partName($index));
    }

    public static function storePart(ChunkedUpload $upload, int $index, UploadedFile $file): void
    {
        $dir = self::chunkDirectoryAbsolute($upload);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Could not create the chunk directory — check storage permissions.');
        }

        // Write to a scratch name first so a half-written part is never treated as received.
        $target = $dir.DIRECTORY_SEPARATOR.self::partName($index);
        $scratch = $target.'.tmp';

        $file->move($dir, basename($scratch));

        if (! @rename($scratch, $target)) {
            @unlink($scratch);
            throw new \RuntimeException('Could not persist the uploaded chunk.');
        }
    }

    /**
     * Stream every part into $targetRelative on the uploads disk.
     *
     * @return int Bytes written.
     */
    public static function merge(ChunkedUpload $upload, string $targetRelative): int
    {
        $disk = Storage::disk(self::disk());
        $targetAbsolute = $disk->path($targetRelative);
        $targetDir = dirname($targetAbsolute);

        if (! is_dir($targetDir) && ! @mkdir($targetDir, 0775, true) && ! is_dir($targetDir)) {
            throw new \RuntimeException('Could not create the destination directory — check storage permissions.');
        }

        $out = fopen($targetAbsolute, 'wb');
        if ($out === false) {
            throw new \RuntimeException('Could not open the destination file for writing.');
        }

        $written = 0;

        try {
            $dir = self::chunkDirectoryAbsolute($upload);
            for ($index = 0; $index < $upload->total_chunks; $index++) {
                $partPath = $dir.DIRECTORY_SEPARATOR.self::partName($index);
                if (! is_file($partPath)) {
                    throw new \RuntimeException("Chunk {$index} is missing — please re-upload the file.");
                }

                $in = fopen($partPath, 'rb');
                if ($in === false) {
                    throw new \RuntimeException("Chunk {$index} could not be read.");
                }

                try {
                    $copied = stream_copy_to_stream($in, $out, -1, 0);
                    if ($copied === false) {
                        throw new \RuntimeException("Chunk {$index} could not be merged.");
                    }
                    $written += $copied;
                } finally {
                    fclose($in);
                }
            }
        } catch (\Throwable $e) {
            fclose($out);
            @unlink($targetAbsolute);

            throw $e;
        }

        fclose($out);

        return $written;
    }

    public static function cleanupChunks(ChunkedUpload $upload): void
    {
        self::deleteDirectory(self::chunkDirectoryAbsolute($upload));
    }

    public static function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach ((array) scandir($dir) as $entry) {
            if (! is_string($entry) || $entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$entry;
            if (is_dir($path)) {
                self::deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    public static function copyBufferSize(): int
    {
        return self::COPY_BUFFER;
    }
}
