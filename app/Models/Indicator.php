<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ambang disimpan sebagai data, bukan ditulis ke dalam logika program.
 * Perubahan pedoman diterapkan dengan menyunting baris tabel ini.
 */
class Indicator extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = 'code';
    protected $keyType = 'string';
    protected $guarded = [];

    protected $casts = ['active' => 'boolean'];

    public function thresholds(): array
    {
        return array_map('intval', array_filter(explode(',', (string) $this->threshold_value), 'strlen'));
    }

    public function threshold(): int
    {
        return (int) $this->threshold_value;
    }
}
