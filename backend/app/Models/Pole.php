<?php

namespace App\Models;

use App\Support\SurveyPhotoStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pole extends Model
{
    protected $fillable = [
        'dtr_id',
        'pole_no',
        'source_type',
        'previous_pole_id',
        'houses_connected',
        'latitude',
        'longitude',
        'photo',
    ];

    protected $appends = [
        'photo_url',
    ];

    public function getPhotoUrlAttribute(): ?string
    {
        return SurveyPhotoStorage::url($this->attributes['photo'] ?? null);
    }

    public function dtr(): BelongsTo
    {
        return $this->belongsTo(Dtr::class);
    }

    public function previousPole(): BelongsTo
    {
        return $this->belongsTo(Pole::class, 'previous_pole_id');
    }

    public function consumers(): HasMany
    {
        return $this->hasMany(Consumer::class);
    }

    public function consumerSurveys(): HasMany
    {
        return $this->hasMany(ConsumerSurvey::class);
    }
}
