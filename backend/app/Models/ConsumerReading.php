<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumerReading extends Model
{
    protected $fillable = [
        'reading_upload_id',
        'consumer_id',
        'ivrs',
        'msn',
        'account_no',
        'consumer_name',
        'dtr_code',
        'feeder_code',
        'reading_date',
        'period_label',
        'kwh_import',
        'kwh_export',
        'kvah',
        'md_kw',
        'raw_json',
    ];

    protected function casts(): array
    {
        return [
            'raw_json' => 'array',
            'reading_date' => 'date',
        ];
    }

    public function readingUpload(): BelongsTo
    {
        return $this->belongsTo(ReadingUpload::class);
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class);
    }
}
