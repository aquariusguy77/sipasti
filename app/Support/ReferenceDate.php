<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Tanggal acuan bagi lapisan antarmuka dan perintah terjadwal.
 *
 * Mesin indikator tidak pernah memanggil waktu saat ini. Tanggal acuan
 * ditentukan di tepi sistem, yaitu di sini, lalu diteruskan sebagai parameter.
 */
class ReferenceDate
{
    public static function resolve(?string $override = null): Carbon
    {
        $value = $override ?: config('sipasti.reference_date');

        return $value ? Carbon::parse($value)->startOfDay() : Carbon::today();
    }
}
