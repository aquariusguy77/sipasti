<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalStatus extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = 'code';
    protected $keyType = 'string';
    protected $guarded = [];

    protected $casts = [
        'allows_deportation' => 'boolean',
        'allows_repatriation' => 'boolean',
    ];
}
