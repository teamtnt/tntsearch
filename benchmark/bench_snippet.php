<?php
/**
 * Benchmarks Highlighter::extractRelevant() (used by TNTSearch::snippet())
 * on long documents with frequent matches.
 *
 * Usage: php benchmark/bench_snippet.php [numDocs] [docBytes]
 *
 * Each document is about $docBytes of text with some multibyte words. The
 * query words occur about once every 50 words, so each document has hundreds
 * of matches. The generator is seeded, so runs are reproducible.
 */

require __DIR__ . '/../vendor/autoload.php';

use TeamTNT\TNTSearch\Support\Highlighter;

$numDocs  = (int)($argv[1] ?? 20);
$docBytes = (int)($argv[2] ?? 75000);

mt_srand(42);
$vocabulary = [
    'the', 'and', 'of', 'to', 'in', 'is', 'for', 'with', 'page', 'template',
    'content', 'field', 'model', 'request', 'config', 'module', 'file', 'user',
    'résumé', 'naïve', 'café', 'über', 'Ångström', '日本語', 'données', 'größe',
];
$queryWords = ['cache', 'syntax'];

$docs = [];
for ($d = 0; $d < $numDocs; $d++) {
    $words = [];
    $length = 0;
    while ($length < $docBytes) {
        $word = mt_rand(1, 50) === 1
            ? $queryWords[mt_rand(0, count($queryWords) - 1)]
            : $vocabulary[mt_rand(0, count($vocabulary) - 1)];
        $words[] = $word;
        $length += strlen($word) + 1;
    }
    $docs[] = implode(' ', $words);
}

$highlighter = new Highlighter;
$queries = ['cache', 'template syntax'];

printf("%d docs, %.0f KB each\n", $numDocs, strlen($docs[0]) / 1024);
foreach ($queries as $query) {
    $start = microtime(true);
    $checksum = 0;
    foreach ($docs as $doc) {
        $checksum += crc32($highlighter->extractRelevant($query, $doc));
    }
    $elapsed = microtime(true) - $start;

    printf("%-18s %9.1f ms  (snippet checksum %d)\n", '"' . $query . '"', $elapsed * 1000, $checksum);
}
