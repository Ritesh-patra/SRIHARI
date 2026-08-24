<?php

namespace App\Models;

use App\Support\SurveyPhotoStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeederSurveySldPhoto extends Model
{
    protected $fillable = [
        'feeder_survey_id',
        'path',
        'uploaded_by',
    ];

    protected $appends = [
        'url',
    ];

    public function feederSurvey(): BelongsTo
    {
        return $this->belongsTo(FeederSurvey::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): ?string
    {
        return SurveyPhotoStorage::url($this->path);
    }
}
