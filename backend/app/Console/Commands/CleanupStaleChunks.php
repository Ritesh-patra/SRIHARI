<?php

namespace App\Console\Commands;

use App\Models\ChunkedUpload;
use App\Support\ChunkedUploadStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Removes chunk directories left behind by abandoned uploads.
 *
 * A cancelled 300 MB upload can otherwise sit in storage/app/private/chunks
 * indefinitely, which matters on a quota-limited cPanel account.
 */
class CleanupStaleChunks extends Command
{
    protected $signature = 'seas:cleanup-chunks {--hours= : Age threshold in hours (default from config/uploads.php)}';

    protected $description = 'Delete stale chunked-upload parts and their rows';

    public function handle(): int
    {
        if (! Schema::hasTable('chunked_uploads')) {
            $this->line('chunked_uploads table not present — nothing to clean.');

            return self::SUCCESS;
        }

        $hours = max(1, (int) ($this->option('hours') ?: config('uploads.stale_hours', 24)));
        $cutoff = now()->subHours($hours);

        $rows = ChunkedUpload::query()
            ->where('status', '!=', ChunkedUpload::STATUS_COMPLETED)
            ->where('updated_at', '<', $cutoff)
            ->get();

        $removed = 0;
        foreach ($rows as $row) {
            ChunkedUploadStore::cleanupChunks($row);
            $row->delete();
            $removed++;
        }

        $orphans = $this->removeOrphanDirectories($cutoff->getTimestamp());

        $this->info("Removed {$removed} stale upload(s) and {$orphans} orphan chunk folder(s) older than {$hours}h.");

        return self::SUCCESS;
    }

    private function removeOrphanDirectories(int $cutoffTimestamp): int
    {
        $disk = (string) config('uploads.disk', 'local');
        $root = Storage::disk($disk)->path(trim((string) config('uploads.chunk_dir', 'chunks'), '/'));

        if (! is_dir($root)) {
            return 0;
        }

        $known = ChunkedUpload::query()->pluck('uuid')->all();
        $known = array_flip($known);

        $removed = 0;
        foreach ((array) scandir($root) as $entry) {
            if (! is_string($entry) || $entry === '.' || $entry === '..') {
                continue;
            }

            $path = $root.DIRECTORY_SEPARATOR.$entry;
            if (! is_dir($path) || isset($known[$entry])) {
                continue;
            }

            if ((int) @filemtime($path) > $cutoffTimestamp) {
                continue;
            }

            ChunkedUploadStore::deleteDirectory($path);
            $removed++;
        }

        return $removed;
    }
}
