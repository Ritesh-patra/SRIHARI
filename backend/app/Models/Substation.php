<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Substation extends Model
{
    use SoftDeletes;

    protected $fillable = ['zone_id', 'name', 'is_active', 'latitude', 'longitude'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function feeders(): HasMany
    {
        return $this->hasMany(Feeder::class);
    }

    public function substationSurveys(): HasMany
    {
        return $this->hasMany(SubstationSurvey::class);
    }
}
