<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// Read-only metadata export; no application records or credentials are exported.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (in_array('--from-migrations', $argv, true)) {
    config(['database.default' => 'erd_memory', 'database.connections.erd_memory' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
    ]]);
    foreach (glob(__DIR__.'/../database/migrations/*.php') as $file) {
        (require $file)->up();
    }
}
$schema = DB::connection()->getSchemaBuilder();
$result = [];
foreach ($schema->getTableListing() as $table) {
    $result[$table] = [
        'columns' => $schema->getColumns($table),
        'indexes' => $schema->getIndexes($table),
        'foreign_keys' => $schema->getForeignKeys($table),
    ];
}
file_put_contents(__DIR__.'/../docs/erd-operasional-schema.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo 'Exported metadata for '.count($result)." tables.\n";
