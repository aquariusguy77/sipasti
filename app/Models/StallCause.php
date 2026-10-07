<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StallCause extends Model
{
    public const CAUSES = ['travel_document', 'funding', 'transport', 'external_response'];

    protected $guarded = [];

    protected $casts = ['recorded_at' => 'date'];

    public function immigrationCase(): BelongsTo
    {
        return $this->belongsTo(ImmigrationCase::class);
    }
}
