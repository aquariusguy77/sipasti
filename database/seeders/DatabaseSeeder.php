<?php

namespace Database\Seeders;

use App\Services\IndicatorEngine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LegalStatusSeeder::class,
            IndicatorSeeder::class,
            UserSeeder::class,
            SimulatedCaseSeeder::class,
            HistoricalCaseSeeder::class,
        ]);

        // Peringatan awal dihitung pada tanggal acuan yang sama dengan perkara
        // simulasi, sehingga papan perkara langsung memperlihatkan keadaan
        // sekitar setiap tenggat.
        app(IndicatorEngine::class)->run(Carbon::parse('2026-10-07'), 'system:seeder');
    }
}
