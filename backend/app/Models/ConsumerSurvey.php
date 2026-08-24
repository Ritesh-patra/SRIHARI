<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumerSurvey extends Model
{
    protected $fillable = [
        'dtr_survey_id',
        'surveyor_id',
        'dtr_id',
        'pole_id',
        'consumer_id',
        'consumer_name',
        'phone',
        'ivrs',
        'msn',
        'meter_make',
        'phase',
        'address',
        'latitude',
        'longitude',
        'gps_accuracy',
        'meter_photo',
        'meter_condition',
        'premise_photo',
        'verification_status',
        'observation',
        'status',
        'survey_flag',
        'review_remarks',
        'reviewed_at',
        'reviewed_by',
        'surveyed_at',
    ];

    protected function casts(): array
    {
        return [
            'surveyed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'gps_accuracy' => 'float',
        ];
    }

    public function dtrSurvey(): BelongsTo
    {
        return $this->belongsTo(DtrSurvey::class);
    }

    public function surveyor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'surveyor_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function dtr(): BelongsTo
    {
        return $this->belongsTo(Dtr::class);
    }

    public function pole(): BelongsTo
    {
        return $this->belongsTo(Pole::class);
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class);
    }
}
