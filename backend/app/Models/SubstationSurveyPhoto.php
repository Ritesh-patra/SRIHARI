<?php

namespace App\Models;

use App\Support\SurveyPhotoStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubstationSurveyPhoto extends Model
{
    protected $fillable = [
        'substation_survey_id',
        'path',
        'kind',
        'uploaded_by',
    ];

    protected $appends = [
        'url',
    ];

    public function substationSurvey(): BelongsTo
    {
        return $this->belongsTo(SubstationSurvey::class);
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
