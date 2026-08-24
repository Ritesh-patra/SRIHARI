<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChunkedUpload extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_UPLOADING = 'uploading';

    public const STATUS_MERGING = 'merging';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ABORTED = 'aborted';

    public const PURPOSE_REPORT_ANALYSIS = 'report_analysis';

    public const PURPOSE_READING = 'reading';

    protected $fillable = [
        'uuid',
        'user_id',
        'purpose',
        'meta_json',
        'original_name',
        'mime',
        'extension',
        'total_size',
        'chunk_size',
        'total_chunks',
        'received_chunks',
        'status',
        'path',
        'error',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'meta_json' => 'array',
            'total_size' => 'integer',
            'chunk_size' => 'integer',
            'total_chunks' => 'integer',
            'received_chunks' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** Relative directory (on the `local` disk) holding this upload's parts. */
    public function chunkDirectory(): string
    {
        return trim((string) config('uploads.chunk_dir', 'chunks'), '/').'/'.$this->uuid;
    }

    public function percent(): int
    {
        if ($this->total_chunks < 1) {
            return 0;
        }

        return (int) min(100, round(($this->received_chunks / $this->total_chunks) * 100));
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_UPLOADING], true);
    }

    /** @return array<string, mixed> */
    public function meta(): array
    {
        return is_array($this->meta_json) ? $this->meta_json : [];
    }
}
