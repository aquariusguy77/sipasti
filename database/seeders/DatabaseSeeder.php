<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LegalStatusSeeder::class,
            IndicatorSeeder::class,
            SimulatedCaseSeeder::class,
        ]);
    }
}
