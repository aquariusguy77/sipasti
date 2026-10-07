<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Alert extends Model
{
    public const TIERS = ['perhatian', 'peringatan', 'kritis'];

    protected $guarded = [];

    protected $casts = ['raised_at' => 'date'];

    public function immigrationCase(): BelongsTo
    {
        return $this->belongsTo(ImmigrationCase::class);
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class, 'indicator_code', 'code');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
