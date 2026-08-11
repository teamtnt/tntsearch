<?php
/**
 * Benchmarks the per-document insert() path with large documents, simulating
 * the "index a big PDF" workload from issue #360.
 *
 * Usage: php benchmark/bench_insert.php [numDocs] [wordsPerDoc]
 */

require __DIR__ . '/../vendor/autoload.php';

use TeamTNT\TNTSearch\TNTSearch;

$numDocs     = (int)($argv[1] ?? 5);
$wordsPerDoc = (int)($argv[2] ?? 150000); // ~ a large PDF's worth of text

$storage = __DIR__ . '/_index/';
@mkdir($storage, 0755, true);
foreach (['bigdocs.index', 'bigdocs.index-wal', 'bigdocs.index-shm'] as $f) {
    @unlink($storage . $f);
}

// Build a large document body from a Zipfian vocabulary (deterministic).
mt_srand(42);
$common = [];
for ($i = 0; $i < 300; $i++) { $common[] = 'common' . $i; }
$rare = [];
for ($i = 0; $i < 30000; $i++) { $rare[] = 'rare' . $i; }

$makeDoc = function () use ($common, $rare, $wordsPerDoc) {
    $words = [];
    for ($i = 0; $i < $wordsPerDoc; $i++) {
        $words[] = (mt_rand(1, 100) <= 70)
            ? $common[mt_rand(0, count($common) - 1)]
            : $rare[mt_rand(0, count($rare) - 1)];
    }
    return implode(' ', $words);
};

// A (throwaway) source database is required for the connector, even though we
// insert documents directly rather than seeding from it.
$sourceDb = $storage . 'source.sqlite';
@unlink($sourceDb);
$src = new PDO('sqlite:' . $sourceDb);
$src->exec('CREATE TABLE documents (id INTEGER PRIMARY KEY, content TEXT)');
$src = null;

$config = [
    'driver'   => 'sqlite',
    'engine'   => \TeamTNT\TNTSearch\Engines\SqliteEngine::class,
    'database' => $sourceDb,
    'storage'  => $storage,
];

$tnt = new TNTSearch;
$tnt->loadConfig($config);
$indexer = $tnt->createIndex('bigdocs.index');
$indexer->setPrimaryKey('id');
$indexer->disableOutput(true);

$index = $indexer;

$totalStart = microtime(true);
for ($d = 1; $d <= $numDocs; $d++) {
    $content = $makeDoc();
    $t = microtime(true);
    $index->insert(['id' => $d, 'content' => $content]);
    printf("  doc %d (%d words, %.1f MB): %.2f s\n",
        $d, $wordsPerDoc, strlen($content) / 1048576, microtime(true) - $t);
}
$elapsed = microtime(true) - $totalStart;
printf("TOTAL: %d docs in %.2f s (%.2f s/doc)\n", $numDocs, $elapsed, $elapsed / $numDocs);
