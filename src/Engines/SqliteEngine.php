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

        $this->flushIndex($indexName);

        $this->index = new PDO('sqlite:' . $this->config['storage'] . $indexName);
        $this->index->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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

        if ($counter % 10000 !== 0) {
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

    public function prepareStatementsForIndex()
    {
        if (!$this->statementsPrepared) {
            $this->insertWordlistStmt = $this->index->prepare("INSERT INTO wordlist (term, num_hits, num_docs) VALUES (:keyword, :hits, :docs)");
            $this->selectWordlistStmt = $this->index->prepare("SELECT * FROM wordlist WHERE term like :keyword LIMIT 1");
            $this->updateWordlistStmt = $this->index->prepare("UPDATE wordlist SET num_docs = num_docs + :docs, num_hits = num_hits + :hits WHERE term = :keyword");
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
            try {
                $this->insertWordlistStmt->bindParam(":keyword", $key);
                $this->insertWordlistStmt->bindParam(":hits", $term['hits']);
                $this->insertWordlistStmt->bindParam(":docs", $term['docs']);
                $this->insertWordlistStmt->execute();

                $lastInsertId = $this->index->query('SELECT MAX(id) FROM wordlist')->fetchColumn();
                $terms[$key]['id'] = $lastInsertId;

                if ($this->inMemory) {
                    $this->inMemoryTerms[$key] = $terms[$key]['id'];
                }

            } catch (\Exception $e) {

                if ($e->getCode() == 23000) {
                    $this->updateWordlistStmt->bindValue(':docs', $term['docs']);
                    $this->updateWordlistStmt->bindValue(':hits', $term['hits']);
                    $this->updateWordlistStmt->bindValue(':keyword', $key);
                    $this->updateWordlistStmt->execute();
                    if (!$this->inMemory) {
                        $this->selectWordlistStmt->bindValue(':keyword', $key);
                        $this->selectWordlistStmt->execute();
                        $res = $this->selectWordlistStmt->fetch(PDO::FETCH_ASSOC);
                        $terms[$key]['id'] = $res['id'];
                    } else {
                        $terms[$key]['id'] = $this->inMemoryTerms[$key];
                    }
                } else {
                    echo "Error while saving wordlist: " . $e->getMessage() . "\n";
                }

                // Statements must be refreshed, because in this state they have error attached to them.
                $this->statementsPrepared = false;
                $this->prepareStatementsForIndex();

            }
        }

        return $terms;
    }

    public function saveDoclist(array $terms, int $docId)
    {
        $insert = 'INSERT INTO doclist (term_id, doc_id, hit_count) VALUES (:id, :doc, :hits)';
        $stmt = $this->index->prepare($insert);

        foreach ($terms as $term) {
            $stmt->bindValue(':id', $term['id']);
            $stmt->bindValue(':doc', $docId);
            $stmt->bindValue(':hits', $term['hits']);
            try {
                $stmt->execute();
            } catch (\Exception $e) {
                //we have a duplicate
                echo $e->getMessage();
            }
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
            return $this->fuzzySearch($keyword);
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
            return $res[$index];
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
            $distance = levenshtein($match['term'], $keyword);
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
