<?php

namespace TeamTNT\TNTSearch\Engines;

use PDO;
use PDOStatement;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use TeamTNT\TNTSearch\Exceptions\IndexNotFoundException;
use TeamTNT\TNTSearch\FileReaders\FileReaderInterface;
use TeamTNT\TNTSearch\Stemmer\NoStemmer;
use TeamTNT\TNTSearch\Stemmer\StemmerInterface;
use TeamTNT\TNTSearch\Support\Collection;
use TeamTNT\TNTSearch\Tokenizer\Tokenizer;
use TeamTNT\TNTSearch\Tokenizer\TokenizerInterface;

class SqliteEngine implements EngineInterface
{
    use EngineTrait;

    public string $indexName;
    public array $config;
    public PDO $index;
    public StemmerInterface $stemmer;
    public PDO $dbh;
    public string $query;
    public bool $disableOutput = false;
    public string $primaryKey;
    protected bool $excludePrimaryKey = true;
    public bool $decodeHTMLEntities = false;
    public TokenizerInterface $tokenizer;
    public array $stopWords = [];
    public bool $statementsPrepared = false;
    protected PDOStatement $updateInfoTableStmt;
    protected PDOStatement $insertWordlistStmt;
    protected PDOStatement $selectWordlistStmt;
    protected PDOStatement $updateWordlistStmt;
    protected PDOStatement $insertDoclistStmt;
    public int $steps = 1000;
    public bool $inMemory = true;
    protected array $inMemoryTerms = [];
    public ?FileReaderInterface $filereader = null;
    public bool $asYouType = false;
    public bool $fuzziness = false;
    public int $fuzzy_prefix_length = 2;
    public int $fuzzy_max_expansions = 50;
    public int $fuzzy_distance = 2;
    public bool $fuzzy_no_limit = false;
    public int $maxDocs = 500;

    /**
     * @param string $indexName
     * @return $this
     * @throws \Exception
     */
    public function createIndex(string $indexName)
    {
        $this->indexName = $indexName;

        if (!is_dir($this->config['storage'])) {
            mkdir($this->config['storage'], 0755, true);
        }

        $this->flushIndex($indexName);

        $this->index = new PDO('sqlite:' . $this->config['storage'] . $indexName);
        $this->index->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->resetIndexState();

        if ($this->config['wal']) {
            $this->index->exec("PRAGMA journal_mode=wal;");
        }

        $this->index->exec("CREATE TABLE IF NOT EXISTS wordlist (
                    id INTEGER PRIMARY KEY,
                    term TEXT UNIQUE COLLATE nocase,
                    num_hits INTEGER,
                    num_docs INTEGER)");

        $this->index->exec("CREATE UNIQUE INDEX 'main'.'index' ON wordlist ('term');");

        $this->index->exec("CREATE TABLE IF NOT EXISTS doclist (
                    term_id INTEGER,
                    doc_id INTEGER,
                    hit_count INTEGER)");

        $this->index->exec("CREATE TABLE IF NOT EXISTS fields (
                    id INTEGER PRIMARY KEY,
                    name TEXT)");

        $this->index->exec("CREATE TABLE IF NOT EXISTS hitlist (
                    term_id INTEGER,
                    doc_id INTEGER,
                    field_id INTEGER,
                    position INTEGER,
                    hit_count INTEGER)");

        $this->index->exec("CREATE TABLE IF NOT EXISTS info (
                    key TEXT,
                    value TEXT)");

        $infoStatement = $this->index->prepare("INSERT INTO info (`key`, `value`) VALUES (:key, :value);");
        $infoValues = [
            [':key' => 'total_documents', ':value' => 0],
            [':key' => 'stemmer', ':value' => NoStemmer::class],
            [':key' => 'tokenizer', ':value' => Tokenizer::class],
        ];

        foreach ($infoValues as $value) {
            $infoStatement->execute($value);
        }

        $this->index->exec("CREATE INDEX IF NOT EXISTS 'main'.'term_id_index' ON doclist ('term_id' COLLATE BINARY);");
        $this->index->exec("CREATE INDEX IF NOT EXISTS 'main'.'doc_id_index' ON doclist ('doc_id');");

        if (isset($this->config['stemmer'])) {
            $this->setStemmer(new $this->config['stemmer']);
        }

        if (isset($this->config['tokenizer'])) {
            $this->setTokenizer(new $this->config['tokenizer']);
        }

        if (!isset($this->dbh)) {
            $dbh = $this->createConnector($this->config)->connect($this->config);

            if ($dbh instanceof PDO) {
                $this->dbh = $dbh;
            }
        }

        return $this;
    }

    public function loadConfig(array $config)
    {
        $this->config = $config;
        $this->config['storage'] = rtrim($this->config['storage'], '/') . '/';

        if (!isset($this->config['driver'])) {
            $this->config['driver'] = "";
        }

        if (!isset($this->config['wal'])) {
            $this->config['wal'] = true;
        }
    }

    public function updateInfoTable(string $key, $value)
    {
        $this->updateInfoTableStmt = $this->index->prepare('UPDATE info SET value = :value WHERE key = :key');
        $this->updateInfoTableStmt->bindValue(':key', $key);
        $this->updateInfoTableStmt->bindValue(':value', $value);
        $this->updateInfoTableStmt->execute();
    }

    public function indexBeginTransaction()
    {
        $this->index->beginTransaction();
    }

    public function indexEndTransaction()
    {
        $this->index->commit();
    }

    public function run()
    {
        if ($this->config['driver'] === 'filesystem') {
            $this->readDocumentsFromFileSystem();
            return;
        }
        $result = $this->dbh->query($this->query);

        $counter = 0;
        $this->index->beginTransaction();
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $counter++;

            $this->processDocument(new Collection($row));

            if ($counter % $this->steps === 0) {
                $this->info("Processed {$counter} rows");
            }

            if ($counter % 10000 === 0) {
                $this->index->commit();
                $this->index->beginTransaction();
                $this->info("Committed");
            }
        }

        if ($counter % $this->steps !== 0) {
            $this->info("Processed {$counter} rows");
        }

        // Commit whatever transaction is still open. Using inTransaction()
        // rather than a "% 10000" heuristic avoids leaving a transaction open
        // when the row count is an exact multiple of the commit batch size —
        // which would otherwise roll back the total_documents update below.
        if ($this->index->inTransaction()) {
            $this->index->commit();
            $this->info("Committed");
        }

        $this->updateInfoTable('total_documents', $counter);

        $this->info("Total rows {$counter}");
    }

    public function processDocument(Collection $row)
    {
        $documentId = $row->get($this->getPrimaryKey());

        if ($this->excludePrimaryKey) {
            $row->forget($this->getPrimaryKey());
        }

        $stems = $row->map(function ($columnContent) {
            if (trim((string)$columnContent) === '') {
                return [];
            }

            return $this->stemText((string)$columnContent);
        });

        $this->saveToIndex($stems, $documentId);
    }

    public function excludePrimaryKey()
    {
        $this->excludePrimaryKey = true;
    }

    public function setStopWords(array $stopWords)
    {
        $this->stopWords = $stopWords;
    }

    public function saveToIndex(Collection $stems, int $docId)
    {
        $this->prepareStatementsForIndex();
        $terms = $this->saveWordlist($stems);
        $this->saveDoclist($terms, $docId);
        $this->saveHitList($stems->toArray(), $docId, $terms);
    }

    /**
     * Index a single document.
     *
     * A large document produces thousands of wordlist/doclist writes; without a
     * surrounding transaction each of those runs as its own implicit commit
     * (one fsync per statement), which dominates the cost of indexing big
     * documents one at a time (e.g. a PDF per insert). Wrap the whole document
     * in a single transaction, unless one is already open (the bulk run()
     * path already manages its own).
     */
    public function insert(array $document)
    {
        $ownTransaction = !$this->index->inTransaction();

        if ($ownTransaction) {
            $this->index->beginTransaction();
        }

        try {
            $this->processDocument(new Collection($document));
            $total = $this->totalDocumentsInCollection() + 1;
            $this->updateInfoTable('total_documents', $total);

            if ($ownTransaction) {
                $this->index->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTransaction && $this->index->inTransaction()) {
                $this->index->rollBack();
            }
            throw $e;
        }
    }

    public function prepareStatementsForIndex()
    {
        if (!$this->statementsPrepared) {
            // Single upsert statement: on a duplicate term the counts are
            // accumulated via the excluded.* values instead of raising a
            // UNIQUE-constraint exception (which previously forced an UPDATE
            // plus a full re-prepare of every statement per repeated term).
            $this->insertWordlistStmt = $this->index->prepare(
                "INSERT INTO wordlist (term, num_hits, num_docs) VALUES (:keyword, :hits, :docs)
                 ON CONFLICT(term) DO UPDATE SET
                    num_hits = num_hits + excluded.num_hits,
                    num_docs = num_docs + excluded.num_docs"
            );
            $this->selectWordlistStmt = $this->index->prepare("SELECT id FROM wordlist WHERE term = :keyword LIMIT 1");
            $this->insertDoclistStmt  = $this->index->prepare("INSERT INTO doclist (term_id, doc_id, hit_count) VALUES (:id, :doc, :hits)");
            $this->statementsPrepared = true;
        }
    }

    /**
     * @param $stems
     *
     * @return array
     */
    public function saveWordlist(Collection $stems)
    {
        $terms = [];

        $stems->map(function ($column) use (&$terms) {
            foreach ($column as $term) {
                if (array_key_exists($term, $terms)) {
                    $terms[$term]['hits']++;
                    $terms[$term]['docs'] = 1;
                } else {
                    $terms[$term] = [
                        'hits' => 1,
                        'docs' => 1,
                        'id' => 0,
                    ];
                }
            }
        });

        foreach ($terms as $key => $term) {
            // Fast path: an already-seen term only needs its counts bumped and
            // its id is already cached, so no id lookup is required at all.
            if ($this->inMemory && isset($this->inMemoryTerms[$key])) {
                $this->insertWordlistStmt->execute([
                    ':keyword' => $key,
                    ':hits'    => $term['hits'],
                    ':docs'    => $term['docs'],
                ]);
                $terms[$key]['id'] = $this->inMemoryTerms[$key];
                continue;
            }

            // Detect insert vs. update via last_insert_rowid(): it only changes
            // when a new row is actually inserted.
            $before = (int) $this->index->lastInsertId();
            $this->insertWordlistStmt->execute([
                ':keyword' => $key,
                ':hits'    => $term['hits'],
                ':docs'    => $term['docs'],
            ]);
            $after = (int) $this->index->lastInsertId();

            if ($after !== $before) {
                $id = $after;
            } else {
                // Existing term that wasn't cached (e.g. inMemory disabled).
                $this->selectWordlistStmt->execute([':keyword' => $key]);
                $id = (int) $this->selectWordlistStmt->fetchColumn();
            }

            $terms[$key]['id'] = $id;

            if ($this->inMemory) {
                $this->inMemoryTerms[$key] = $id;
            }
        }

        return $terms;
    }

    public function saveDoclist(array $terms, int $docId)
    {
        foreach ($terms as $term) {
            $this->insertDoclistStmt->execute([
                ':id'   => $term['id'],
                ':doc'  => $docId,
                ':hits' => $term['hits'],
            ]);
        }
    }

    public function saveHitList(array $stems, int $docId, array $termsList)
    {
    }

    public function readDocumentsFromFileSystem()
    {
        $exclude = [];
        if (isset($this->config['exclude'])) {
            $exclude = $this->config['exclude'];
        }

        $this->index->exec('CREATE TABLE IF NOT EXISTS filemap (
                    id INTEGER PRIMARY KEY,
                    path TEXT)');
        $path = realpath($this->config['location']);

        $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path),
            RecursiveIteratorIterator::SELF_FIRST);
        $this->index->beginTransaction();
        $counter = 0;

        foreach ($objects as $name => $object) {
            $name = str_replace($path . '/', '', $name);

            if (is_callable($this->config['extension'])) {
                $includeFile = $this->config['extension']($object);
            } elseif (is_array($this->config['extension'])) {
                $includeFile = in_array($object->getExtension(), $this->config['extension']);
            } else {
                $includeFile = stringEndsWith($name, $this->config['extension']);
            }

            if ($includeFile && !in_array($name, $exclude)) {
                $counter++;
                $file = [
                    'id' => $counter,
                    'name' => $name,
                    'content' => $this->filereader->read($object),
                ];
                $fileCollection = new Collection($file);

                if (property_exists($this->filereader, 'fileFilterCallback')
                    && is_callable($this->filereader->fileFilterCallback)) {
                    $fileCollection = $fileCollection->filter($this->filereader->fileFilterCallback);
                }
                if (property_exists($this->filereader, 'fileMapCallback')
                    && is_callable($this->filereader->fileMapCallback)) {
                    $fileCollection = $fileCollection->map($this->filereader->fileMapCallback);
                }

                $this->processDocument($fileCollection);
                $statement = $this->index->prepare("INSERT INTO filemap ( 'id', 'path') values (:counter, :object)");
                $statement->bindParam(':counter', $counter);
                $statement->bindParam(':object', $object);
                $statement->execute();
                $this->info("Processed {$counter} {$object}");
            }
        }

        $this->index->commit();

        $this->index->exec("INSERT INTO info ('key', 'value') values ('total_documents', {$counter})");
        $this->index->exec("INSERT INTO info ('key', 'value') values ('driver', 'filesystem')");

        $this->info("Total rows {$counter}");
        $this->info("Index created: {$this->config['storage']}");
    }

    public function delete(int $documentId)
    {
        $rows = $this->prepareAndExecuteStatement("SELECT * FROM doclist WHERE doc_id = :documentId;", [
            ['key' => ':documentId', 'value' => $documentId],
        ])->fetchAll(PDO::FETCH_ASSOC);

        $updateStmt = $this->index->prepare('UPDATE wordlist SET num_docs = num_docs - 1, num_hits = num_hits - :hits WHERE id = :term_id;');

        foreach ($rows as $document) {
            $updateStmt->bindParam(":hits", $document['hit_count']);
            $updateStmt->bindParam(":term_id", $document['term_id']);
            $updateStmt->execute();
        }

        $res = $this->prepareAndExecuteStatement('DELETE FROM doclist WHERE doc_id = :documentId;', [
            ['key' => ':documentId', 'value' => $documentId],
        ]);

        $this->prepareAndExecuteStatement('DELETE FROM wordlist WHERE num_hits = 0;');

        $affected = $res->rowCount();

        if ($affected) {
            $total = $this->totalDocumentsInCollection() - 1;
            $this->updateInfoTable('total_documents', $total);
        }
    }

    public function prepareAndExecuteStatement(string $query, array $params = [])
    {
        $statemnt = $this->index->prepare($query);
        foreach ($params as $param) {
            $statemnt->bindParam($param['key'], $param['value']);
        }
        $statemnt->execute();
        return $statemnt;
    }

    /**
     * @return int
     */
    public function totalDocumentsInCollection()
    {
        $query = "SELECT * FROM info WHERE key = 'total_documents';";
        $docs = $this->index->query($query);

        return $docs->fetch(PDO::FETCH_ASSOC)['value'];
    }

    public function disableOutput(bool $value)
    {
        $this->disableOutput = $value;
    }

    public function getWordFromWordList(string $word)
    {
        $selectStmt = $this->index->prepare('SELECT * FROM wordlist WHERE term like :keyword LIMIT 1;');
        $selectStmt->bindValue(':keyword', $word);
        $selectStmt->execute();
        return $selectStmt->fetch(PDO::FETCH_ASSOC);
    }

    public function countDocHitsInWordList($word)
    {
        $res = $this->getWordFromWordList($word);

        if ($res) {
            return $res['num_docs'];
        }
        return 0;
    }

    public function buildDictionary($filename, $count = -1, $hits = true, $docs = false)
    {
        $selectStmt = $this->index->prepare('SELECT * FROM wordlist ORDER BY num_hits DESC;');
        $selectStmt->execute();

        $dictionary = "";
        $counter = 0;

        while ($row = $selectStmt->fetch(PDO::FETCH_ASSOC)) {
            $dictionary .= $row['term'];
            if ($hits) {
                $dictionary .= "\t" . $row['num_hits'];
            }

            if ($docs) {
                $dictionary .= "\t" . $row['num_docs'];
            }

            $counter++;
            if ($counter >= $count && $count > 0) {
                break;
            }

            $dictionary .= "\n";
        }

        file_put_contents($filename, $dictionary, LOCK_EX);
    }

    public function selectIndex(string $indexName)
    {
        $pathToIndex = $this->config['storage'] . $indexName;
        if (!file_exists($pathToIndex)) {
            throw new IndexNotFoundException("Index {$pathToIndex} does not exist", 1);
        }
        $this->index = new PDO('sqlite:' . $pathToIndex);
        $this->index->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->resetIndexState();
    }

    /**
     * Reset any state that is tied to a specific index connection.
     *
     * The prepared statements and the in-memory term cache are bound to the
     * previously selected index. When the connection is swapped to another
     * index (via selectIndex/createIndex) they must be discarded, otherwise
     * writes would be executed against the previous index and doclist rows
     * would reference term ids from the wrong index.
     */
    protected function resetIndexState()
    {
        $this->statementsPrepared = false;
        unset($this->insertWordlistStmt, $this->selectWordlistStmt, $this->updateWordlistStmt, $this->insertDoclistStmt);
        $this->inMemoryTerms = [];
    }

    public function getWordlistByKeyword(string $keyword, bool $isLastWord = false, bool $noLimit = false)
    {
        $searchWordlist = 'SELECT * FROM wordlist WHERE term like :keyword LIMIT 1;';
        $stmtWord = $this->index->prepare($searchWordlist);

        if ($this->asYouType && $isLastWord) {
            $searchWordlist = 'SELECT * FROM wordlist WHERE term like :keyword ORDER BY length(term) ASC, num_hits DESC LIMIT 1;';
            $stmtWord = $this->index->prepare($searchWordlist);
            $stmtWord->bindValue(':keyword', mb_strtolower($keyword) . "%");
        } else {
            $stmtWord->bindValue(':keyword', mb_strtolower($keyword));
        }
        $stmtWord->execute();
        $res = $stmtWord->fetchAll(PDO::FETCH_ASSOC);

        if ($this->fuzziness && (!isset($res[0]) || $noLimit)) {
            $fuzzyResults = $this->fuzzySearch($keyword);

            if (isset($res[0])) {
                // Preserve the exact match — fuzzySearch may drop it when it
                // falls outside fuzzy_max_expansions — without duplicating it.
                $res[0]['distance'] = 0;
                $exactId            = $res[0]['id'];
                $fuzzyResults       = array_values(array_filter($fuzzyResults, function ($row) use ($exactId) {
                    return $row['id'] != $exactId;
                }));
            }

            array_push($res, ...$fuzzyResults);
        }

        return $res;
    }

    /**
     * @param $word
     * @param $noLimit
     *
     * @return Collection
     */
    public function getAllDocumentsForStrictKeyword(array $word, bool $noLimit)
    {
        $query = "SELECT * FROM doclist WHERE term_id = :id ORDER BY hit_count DESC LIMIT {$this->maxDocs};";
        if ($noLimit) {
            $query = 'SELECT * FROM doclist WHERE term_id = :id ORDER BY hit_count DESC;';
        }
        $stmtDoc = $this->index->prepare($query);

        $stmtDoc->bindValue(':id', $word[0]['id']);
        $stmtDoc->execute();
        return new Collection($stmtDoc->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param      $keyword
     * @param bool $noLimit
     *
     * @return Collection
     */
    public function getAllDocumentsForWhereKeywordNot(string $keyword, bool $noLimit = false)
    {
        $word = $this->getWordlistByKeyword($keyword);
        if (!isset($word[0])) {
            return new Collection([]);
        }
        $query = "SELECT * FROM doclist WHERE doc_id NOT IN (SELECT doc_id FROM doclist WHERE term_id = :id) GROUP BY doc_id ORDER BY hit_count DESC LIMIT {$this->maxDocs};";
        if ($noLimit) {
            $query = 'SELECT * FROM doclist WHERE doc_id NOT IN (SELECT doc_id FROM doclist WHERE term_id = :id) GROUP BY doc_id ORDER BY hit_count DESC;';
        }
        $stmtDoc = $this->index->prepare($query);

        $stmtDoc->bindValue(':id', $word[0]['id']);
        $stmtDoc->execute();
        return new Collection($stmtDoc->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getValueFromInfoTable(string $value)
    {
        $query = "SELECT * FROM info WHERE key = '{$value}'";
        $docs = $this->index->query($query);

        if ($ret = $docs->fetch(PDO::FETCH_ASSOC)) {
            return $ret['value'];
        }

        return null;
    }

    public function filesystemMapIdsToPaths($docs)
    {
        $query = "SELECT * FROM filemap WHERE id in (" . $docs->implode(', ') . ");";
        $res = $this->index->query($query)->fetchAll(PDO::FETCH_ASSOC);

        return $docs->map(function ($key) use ($res) {
            $index = array_search($key, array_column($res, 'id'));
            // array_search returns false when the id has no filemap row;
            // $res[false] would silently coerce to $res[0] (Undefined offset).
            return $index === false ? null : $res[$index];
        });
    }

    /**
     * @param $keyword
     *
     * @return array
     */
    public function fuzzySearch(string $keyword)
    {
        $prefix = mb_substr($keyword, 0, $this->fuzzy_prefix_length);
        $searchWordlist = "SELECT * FROM wordlist WHERE term like :keyword ORDER BY num_hits DESC LIMIT {$this->fuzzy_max_expansions};";
        $stmtWord = $this->index->prepare($searchWordlist);
        $stmtWord->bindValue(':keyword', mb_strtolower($prefix) . "%");
        $stmtWord->execute();
        $matches = $stmtWord->fetchAll(PDO::FETCH_ASSOC);

        $resultSet = [];
        foreach ($matches as $match) {
            $distance = $this->mbLevenshtein($match['term'], $keyword);
            if ($distance <= $this->fuzzy_distance) {
                $match['distance'] = $distance;
                $resultSet[] = $match;
            }
        }

        // Sort the data by distance, and than by num_hits
        $distance = [];
        $hits = [];
        foreach ($resultSet as $key => $row) {
            $distance[$key] = $row['distance'];
            $hits[$key] = $row['num_hits'];
        }
        array_multisort($distance, SORT_ASC, $hits, SORT_DESC, $resultSet);

        return $resultSet;
    }

    public function getAllDocumentsForFuzzyKeyword(array $words, bool $noLimit)
    {
        $binding_params = implode(',', array_fill(0, count($words), '?'));
        $query = "SELECT * FROM doclist WHERE term_id in ($binding_params) ORDER BY CASE term_id";
        $order_counter = 1;

        foreach ($words as $word) {
            $query .= " WHEN " . $word['id'] . " THEN " . $order_counter++;
        }

        $query .= " END";

        if (!$noLimit) {
            $query .= " LIMIT {$this->maxDocs}";
        }

        $stmtDoc = $this->index->prepare($query);

        $ids = null;
        foreach ($words as $word) {
            $ids[] = $word['id'];
        }

        $stmtDoc->execute($ids);
        return new Collection($stmtDoc->fetchAll(PDO::FETCH_ASSOC));
    }

    public function flushIndex(string $indexName)
    {
        if (file_exists($this->config['storage'] . $indexName)) {
            unlink($this->config['storage'] . $indexName);
        }
    }
}
