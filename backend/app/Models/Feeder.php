<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Feeder extends Model
{
    use SoftDeletes;

    protected $fillable = ['substation_id', 'code', 'name', 'is_active', 'latitude', 'longitude'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function substation(): BelongsTo
    {
        return $this->belongsTo(Substation::class);
    }

    public function dtrs(): HasMany
    {
        return $this->hasMany(Dtr::class);
    }

    public function consumers(): HasMany
    {
        return $this->hasMany(Consumer::class);
    }
}
