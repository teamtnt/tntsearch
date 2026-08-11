<?php
/**
 * Benchmarks index creation over the generated dataset.
 *
 * Usage: php benchmark/bench.php [datasetFile]
 */

require __DIR__ . '/../vendor/autoload.php';

use TeamTNT\TNTSearch\TNTSearch;

$dataset = $argv[1] ?? __DIR__ . '/dataset.sqlite';
$storage = __DIR__ . '/_index/';
@mkdir($storage, 0755, true);
@unlink($storage . 'bench.index');
@unlink($storage . 'bench.index-wal');
@unlink($storage . 'bench.index-shm');

$config = [
    'driver'   => 'sqlite',
    'engine'   => \TeamTNT\TNTSearch\Engines\SqliteEngine::class,
    'database' => $dataset,
    'storage'  => $storage,
];

$tnt = new TNTSearch;
$tnt->loadConfig($config);
$indexer = $tnt->createIndex('bench.index');
$indexer->disableOutput(true);
$indexer->query('SELECT id, title, article FROM documents;');

$numDocs = (new PDO('sqlite:' . $dataset))->query('SELECT COUNT(*) FROM documents')->fetchColumn();

$start   = microtime(true);
$memPre  = memory_get_usage();
$indexer->run();
$elapsed = microtime(true) - $start;

$rate = round($numDocs / $elapsed);
printf("Indexed %d docs in %.2f s  (%d docs/s)  peak mem %.0f MB\n",
    $numDocs, $elapsed, $rate, memory_get_peak_usage() / 1048576);
