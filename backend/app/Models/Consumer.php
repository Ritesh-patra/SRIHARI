<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consumer extends Model
{
    public const SOURCE_MI = 'mi';

    public const SOURCE_MASTER = 'master';

    protected $fillable = [
        'dtr_id',
        'feeder_id',
        'pole_id',
        'name',
        'phone',
        'ivrs',
        'account_no',
        'msn',
        'address',
        'phase',
        'is_active',
        'source',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function dtr(): BelongsTo
    {
        return $this->belongsTo(Dtr::class);
    }

    public function feeder(): BelongsTo
    {
        return $this->belongsTo(Feeder::class);
    }

    public function pole(): BelongsTo
    {
        return $this->belongsTo(Pole::class);
    }
}
