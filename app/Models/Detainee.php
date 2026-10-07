<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Identitas deteni, dibatasi pada medan yang sudah diwajibkan Kartu Deteni
 * dan berita acara pendetensian (NFR4).
 *
 * Kelas ini sengaja tidak dibaca oleh mesin indikator.
 */
class Detainee extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'birth_date' => 'date',
        'travel_document_date' => 'date',
        'biometric_captured' => 'boolean',
    ];

    public function cases(): HasMany
    {
        return $this->hasMany(ImmigrationCase::class);
    }
}
