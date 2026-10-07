<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $guarded = [];

    protected $casts = ['reviewed_at' => 'date'];

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }
}
