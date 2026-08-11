<?php
/**
 * Generates a deterministic SQLite dataset for benchmarking index creation.
 *
 * Usage: php benchmark/generate.php [numDocs] [outFile]
 *
 * The text uses a Zipfian-ish vocabulary: a small set of very common words plus
 * a long tail of rare words. This mirrors real corpora and, importantly,
 * exercises the "term already exists in the wordlist" path heavily — which is
 * where the indexer spends most of its time.
 */

$numDocs = (int)($argv[1] ?? 20000);
$outFile = $argv[2] ?? __DIR__ . '/dataset.sqlite';

@unlink($outFile);

$pdo = new PDO('sqlite:' . $outFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE documents (id INTEGER PRIMARY KEY, title TEXT, article TEXT)");

// Build a vocabulary: 200 common words + 5000 rare words.
$common = [];
for ($i = 0; $i < 200; $i++) {
    $common[] = 'common' . $i;
}
$rare = [];
for ($i = 0; $i < 5000; $i++) {
    $rare[] = 'rare' . $i;
}

mt_srand(1234); // deterministic

$pickWord = function () use ($common, $rare) {
    // 80% of tokens come from the common set (Zipf-like skew).
    if (mt_rand(1, 100) <= 80) {
        return $common[mt_rand(0, count($common) - 1)];
    }
    return $rare[mt_rand(0, count($rare) - 1)];
};

$pdo->beginTransaction();
$stmt = $pdo->prepare("INSERT INTO documents (id, title, article) VALUES (:id, :title, :article)");

for ($d = 1; $d <= $numDocs; $d++) {
    // ~8-word title, ~120-word article.
    $title = [];
    for ($i = 0; $i < 8; $i++) {
        $title[] = $pickWord();
    }
    $article = [];
    for ($i = 0; $i < 120; $i++) {
        $article[] = $pickWord();
    }

    $stmt->execute([
        ':id'      => $d,
        ':title'   => implode(' ', $title),
        ':article' => implode(' ', $article),
    ]);

    if ($d % 5000 === 0) {
        $pdo->commit();
        $pdo->beginTransaction();
    }
}
$pdo->commit();

$size = round(filesize($outFile) / 1024 / 1024, 1);
echo "Generated {$numDocs} documents into {$outFile} ({$size} MB)\n";
