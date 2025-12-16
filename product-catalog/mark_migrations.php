<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$batch = \Illuminate\Support\Facades\DB::table('migrations')->max('batch') ?? 0;
$batch++;

\Illuminate\Support\Facades\DB::table('migrations')->insertOrIgnore([
    ['migration' => '2025_12_16_075808_create_csv_imports_table', 'batch' => $batch],
    ['migration' => '2025_12_16_075809_create_csv_import_mappings_table', 'batch' => $batch],
    ['migration' => '2025_12_16_075810_create_csv_import_logs_table', 'batch' => $batch],
]);

echo "Migrations marquées comme exécutées\n";
