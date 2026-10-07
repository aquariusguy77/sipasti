<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionContact extends Model
{
    protected $guarded = [];

    protected $casts = [
        'letter_date' => 'date',
        'response_date' => 'date',
    ];

    public function immigrationCase(): BelongsTo
    {
        return $this->belongsTo(ImmigrationCase::class);
    }
}
