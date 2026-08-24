<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportAnalysisUpload extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'source',
        'path',
        'original_name',
        'row_count',
        'headers_json',
        'parse_note',
        'status',
        'parse_error',
        'chunked_upload_id',
        'size_bytes',
        'parsed_at',
    ];

    protected function casts(): array
    {
        return [
            'headers_json' => 'array',
            'row_count' => 'integer',
            'size_bytes' => 'integer',
            'parsed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chunkedUpload(): BelongsTo
    {
        return $this->belongsTo(ChunkedUpload::class);
    }

    /** @return array<string, string> */
    public static function sourceLabels(): array
    {
        return [
            'ci' => 'CI Data Base',
            'cmdm' => 'CMDM Data Base',
            'dfis' => 'DFIS Data Base',
            'ngb' => 'NGB Data Base',
            'wfm' => 'WFM Data Base',
        ];
    }

    /** Older rows predate the status column, so treat a missing value as done. */
    public function parseStatus(): string
    {
        return $this->status ?: self::STATUS_COMPLETED;
    }

    public function isBusy(): bool
    {
        return in_array($this->parseStatus(), [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }
}
