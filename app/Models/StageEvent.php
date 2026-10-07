<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StageEvent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'opened_at' => 'date',
        'closed_at' => 'date',
    ];

    public function immigrationCase(): BelongsTo
    {
        return $this->belongsTo(ImmigrationCase::class);
    }
}
