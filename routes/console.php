<?php

use App\Services\IndicatorEngine;
use App\Services\RetentionPolicy;
use App\Support\ReferenceDate;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
 * Menjalankan mesin indikator atas seluruh perkara terbuka.
 * Tanggal acuan dapat ditetapkan agar hasil dapat diulang.
 */
Artisan::command('sipasti:evaluate {--as-of= : Tanggal acuan, bawaan hari ini}', function (IndicatorEngine $engine) {
    $asOf = ReferenceDate::resolve($this->option('as-of'));

    $raised = $engine->run($asOf, 'system:scheduler');

    $this->info("Tanggal acuan {$asOf->toDateString()}: {$raised->count()} peringatan baru atau naik tingkat.");

    foreach ($raised as $alert) {
        $this->line("  {$alert->immigrationCase->case_number}  {$alert->indicator_code}  {$alert->tier}");
    }
})->purpose('Evaluasi indikator peringatan dini atas perkara terbuka');

/*
 * Aturan retensi (NFR5). Gunakan --dry-run untuk melihat calon tanpa mengubah data.
 */
Artisan::command('sipasti:retention {--as-of= : Tanggal acuan} {--dry-run : Hanya tampilkan calon}', function (RetentionPolicy $policy) {
    $asOf = ReferenceDate::resolve($this->option('as-of'));
    $years = config('sipasti.retention_years');

    if ($this->option('dry-run')) {
        $due = $policy->due($asOf);
        $this->info("Tanggal acuan {$asOf->toDateString()}, retensi {$years} tahun: {$due->count()} deteni akan dianonimkan.");
        $due->each(fn ($d) => $this->line("  deteni #{$d->id}"));

        return;
    }

    $done = $policy->apply($asOf, 'system:scheduler');
    $this->info("Tanggal acuan {$asOf->toDateString()}, retensi {$years} tahun: {$done->count()} deteni dianonimkan.");
})->purpose('Anonimkan identitas deteni yang melewati jangka retensi');

Schedule::command('sipasti:evaluate')->dailyAt('06:00');
Schedule::command('sipasti:retention')->monthlyOn(1, '02:00');
