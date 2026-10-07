<?php

/**
 * Titik masuk SIPASTI di Vercel (runtime vercel-php).
 *
 * Vercel menjalankan PHP sebagai fungsi tanpa server dengan filesystem hanya
 * baca, kecuali /tmp. Berkas ini mengarahkan seluruh jalur tulis Laravel ke
 * /tmp, lalu menyiapkan basis data SQLite demonstrasi pada permulaan dingin.
 *
 * Akibatnya data bersifat sementara: setiap instans fungsi memulai dari data
 * simulasi yang sama, dan perubahan tidak dibagi antar instans. Ini memadai
 * untuk demonstrasi purwarupa, tidak untuk pemakaian sebenarnya.
 */

$defaults = [
    'APP_ENV' => 'demo',
    'APP_DEBUG' => 'false',
    'LOG_CHANNEL' => 'stderr',
    'SESSION_DRIVER' => 'cookie',
    'CACHE_STORE' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => '/tmp/sipasti.sqlite',
    'SIPASTI_REFERENCE_DATE' => '2026-10-07',
    'LARAVEL_STORAGE_PATH' => '/tmp/storage',
    'VIEW_COMPILED_PATH' => '/tmp/storage/framework/views',
    'APP_CONFIG_CACHE' => '/tmp/cache/config.php',
    'APP_EVENTS_CACHE' => '/tmp/cache/events.php',
    'APP_PACKAGES_CACHE' => '/tmp/cache/packages.php',
    'APP_ROUTES_CACHE' => '/tmp/cache/routes.php',
    'APP_SERVICES_CACHE' => '/tmp/cache/services.php',
];

foreach ($defaults as $key => $value) {
    if (getenv($key) === false) {
        putenv("{$key}={$value}");
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
}

if (blank_env('APP_KEY')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "APP_KEY belum diatur. Tambahkan variabel lingkungan APP_KEY pada pengaturan proyek Vercel, lalu deploy ulang.\n";
    exit;
}

// Vercel selalu melayani lewat HTTPS di belakang proksi.
$_SERVER['HTTPS'] = 'on';

foreach (['/tmp/cache', '/tmp/storage/app', '/tmp/storage/logs', '/tmp/storage/framework/views',
    '/tmp/storage/framework/cache/data', '/tmp/storage/framework/sessions'] as $dir) {
    is_dir($dir) || mkdir($dir, 0755, true);
}

define('LARAVEL_START', microtime(true));

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';

// Basis data demonstrasi disusun pada permulaan dingin. Penanda siap ditulis
// sesudah migrasi dan seeder selesai, sehingga permintaan yang terputus di
// tengah jalan tidak meninggalkan basis data setengah jadi.
$database = getenv('DB_DATABASE');
$ready = $database.'.ready';

if (getenv('DB_CONNECTION') === 'sqlite' && ! file_exists($ready)) {
    @unlink($database);
    touch($database);

    $app->make(Illuminate\Contracts\Console\Kernel::class)
        ->call('migrate', ['--force' => true, '--seed' => true]);

    touch($ready);
}

$app->handleRequest(Illuminate\Http\Request::capture());

function blank_env(string $key): bool
{
    $value = getenv($key);

    return $value === false || trim($value) === '';
}
