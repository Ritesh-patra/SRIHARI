<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeederReading extends Model
{
    protected $fillable = [
        'reading_upload_id',
        'feeder_id',
        'feeder_code',
        'feeder_name',
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

    public function feeder(): BelongsTo
    {
        return $this->belongsTo(Feeder::class);
    }
}
