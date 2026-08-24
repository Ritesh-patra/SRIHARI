<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReadingUpload extends Model
{
    public const TYPE_FEEDER = 'feeder';

    public const TYPE_DTR = 'dtr';

    public const TYPE_CONSUMER = 'consumer';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'type',
        'chunked_upload_id',
        'path',
        'original_name',
        'size_bytes',
        'period_from',
        'period_to',
        'period_label',
        'status',
        'rows_total',
        'rows_imported',
        'rows_failed',
        'headers_json',
        'error',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'headers_json' => 'array',
            'size_bytes' => 'integer',
            'rows_total' => 'integer',
            'rows_imported' => 'integer',
            'rows_failed' => 'integer',
            'period_from' => 'date',
            'period_to' => 'date',
            'processed_at' => 'datetime',
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

    public function feederReadings(): HasMany
    {
        return $this->hasMany(FeederReading::class);
    }

    public function dtrReadings(): HasMany
    {
        return $this->hasMany(DtrReading::class);
    }

    public function consumerReadings(): HasMany
    {
        return $this->hasMany(ConsumerReading::class);
    }

    /** @return array<string, string> */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_FEEDER => 'Feeder consumption',
            self::TYPE_DTR => 'DTR consumption',
            self::TYPE_CONSUMER => 'Consumer consumption',
        ];
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->type] ?? ucfirst((string) $this->type);
    }

    /** Table that parsed rows for this upload type land in. */
    public static function readingTable(string $type): string
    {
        return match ($type) {
            self::TYPE_FEEDER => 'feeder_readings',
            self::TYPE_DTR => 'dtr_readings',
            self::TYPE_CONSUMER => 'consumer_readings',
            default => throw new \InvalidArgumentException("Unknown reading type [{$type}]."),
        };
    }

    public function isBusy(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }
}
