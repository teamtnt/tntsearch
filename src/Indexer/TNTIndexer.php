<?php

namespace TeamTNT\TNTSearch\Indexer;

use Exception;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use TeamTNT\TNTSearch\Connectors\FileSystemConnector;
use TeamTNT\TNTSearch\Connectors\MySqlConnector;
use TeamTNT\TNTSearch\Connectors\PostgresConnector;
use TeamTNT\TNTSearch\Connectors\SQLiteConnector;
use TeamTNT\TNTSearch\FileReaders\TextFileReader;
use TeamTNT\TNTSearch\Stemmer\CroatianStemmer;
use TeamTNT\TNTSearch\Stemmer\PorterStemmer;
use TeamTNT\TNTSearch\Support\Collection;
use TeamTNT\TNTSearch\Support\Tokenizer;
use TeamTNT\TNTSearch\Support\TokenizerInterface;

class TNTIndexer
{
    protected $index              = null;
    protected $dbh                = null;
    protected $primaryKey         = null;
    public $stemmer               = null;
    public $tokenizer             = null;
    public $filereader            = null;
    public $config                = [];
    protected $query              = "";
    protected $wordlist           = [];
    protected $inMemoryTerms      = [];
    protected $decodeHTMLEntities = false;
    public $disableOutput         = false;
    public $inMemory              = true;
    public $steps                 = 1000;
    public $indexName             = "";

    public function __construct()
    {
        $this->stemmer    = new PorterStemmer;
        $this->tokenizer  = new Tokenizer;
        $this->filereader = new TextFileReader;
    }

    public function setTokenizer(TokenizerInterface $tokenizer)
    {
        $this->tokenizer = $tokenizer;
    }

    public function loadConfig($config)
    {
        $this->config            = $config;
        $this->config['storage'] = rtrim($this->config['storage'], '/').'/';
        if (!isset($this->config['driver'])) {
            $this->config['driver'] = "";
        }

    }

    public function getStoragePath()
    {
        return $this->config['storage'];
    }

    public function getStemmer()
    {
        return $this->stemmer;
    }

    public function getPrimaryKey()
    {
        if (isset($this->primaryKey)) {
            return $this->primaryKey;
        }
        return 'id';
    }

    public function setPrimaryKey($primaryKey)
    {
        $this->primaryKey = $primaryKey;
    }

    public function setStemmer($stemmer)
    {
        $this->stemmer = $stemmer;
        $class         = get_class($stemmer);
        $this->index->exec("INSERT INTO info ( 'key', 'value') values ( 'stemmer', '$class')");
    }

    public function setCroatianStemmer()
    {
        $this->setStemmer(new CroatianStemmer);
    }

    public function setLanguage($language = 'porter')
    {
        $class = 'TeamTNT\\TNTSearch\\Stemmer\\'.ucfirst(strtolower($language)).'Stemmer';
        $this->setStemmer(new $class);
    }

    public function setIndex($index)
    {
        $this->index = $index;
    }

    public function setFileReader($filereader)
    {
        $this->filereader = $filereader;
    }

    public function createIndex($indexName)
    {
        $this->indexName = $indexName;

        if (file_exists($this->config['storage'].$indexName)) {
            unlink($this->config['storage'].$indexName);
        }

        $this->index = new PDO('sqlite:'.$this->config['storage'].$indexName);
        $this->index->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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
                    value INTEGER)");

        $this->index->exec("INSERT INTO info ( 'key', 'value') values ( 'total_documents', 0)");

        $this->index->exec("CREATE INDEX IF NOT EXISTS 'main'.'term_id_index' ON doclist ('term_id' COLLATE BINARY);");

        $connector = $this->createConnector($this->config);
        if (!$this->dbh) {
            $this->dbh = $connector->connect($this->config);
        }
        return $this;
    }

    public function indexBeginTransaction()
    {
        $this->index->beginTransaction();
    }

    public function indexEndTransaction()
    {
        $this->index->commit();
    }

    public function createConnector(array $config)
    {
        if (!isset($config['driver'])) {
            throw new Exception('A driver must be specified.');
        }

        switch ($config['driver']) {
            case 'mysql':
                return new MySqlConnector;
            case 'pgsql':
                return new PostgresConnector;
            case 'sqlite':
                return new SQLiteConnector;
            case 'filesystem':
                return new FileSystemConnector;
        }
        throw new Exception("Unsupported driver [{$config['driver']}]");
    }

    public function setDatabaseHandle(PDO $dbh)
    {
        $this->dbh = $dbh;
        if ($this->dbh->getAttribute(PDO::ATTR_DRIVER_NAME) == 'mysql') {
            $this->dbh->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        }
    }

    public function query($query)
    {
        $this->query = $query;
    }

    public function run()
    {
        if ($this->config['driver'] == "filesystem") {
            return $this->readDocumentsFromFileSystem();
        }

        $result = $this->dbh->query($this->query);

        $counter = 0;
        $this->index->beginTransaction();
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $counter++;

            $this->processDocument(new Collection($row));

            if ($counter % $this->steps == 0) {
                $this->info("Processed $counter rows");
            }
            if ($counter % 10000 == 0) {
                $this->index->commit();
                $this->index->beginTransaction();
                $this->info("Commited");
            }
        }
        $this->index->commit();

        $this->updateInfoTable('total_documents', $counter);

        $this->info("Total rows $counter");
    }

    public function readDocumentsFromFileSystem()
    {
        $exclude = [];
        if (isset($this->config['exclude'])) {
            $exclude = $this->config['exclude'];
        }

        $this->index->exec("CREATE TABLE IF NOT EXISTS filemap (
                    id INTEGER PRIMARY KEY,
                    path TEXT)");
        $path = realpath($this->config['location']);

        $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST);
        $this->index->beginTransaction();
        $counter = 0;

        foreach ($objects as $name => $object) {
            $name = str_replace($path.'/', '', $name);
            if (stringEndsWith($name, $this->config['extension']) && !in_array($name, $exclude)) {
                $counter++;
                $file = [
                    'id'      => $counter,
                    'name'    => $name,
                    'content' => $this->filereader->read($object)
                ];
                $this->processDocument(new Collection($file));
                $this->index->exec("INSERT INTO filemap ( 'id', 'path') values ( $counter, '$object')");
                $this->info("Processed $counter $object");
            }
        }

        $this->index->commit();

        $this->index->exec("INSERT INTO info ( 'key', 'value') values ( 'total_documents', $counter)");
        $this->index->exec("INSERT INTO info ( 'key', 'value') values ( 'driver', 'filesystem')");

        $this->info("Total rows $counter");
        $this->info("Index created: {$this->config['storage']}");
    }

    public function processDocument($row)
    {
        $stems = $row->map(function ($column, $name) {
            return $this->stemText($column);
        });
        $this->saveToIndex($stems, $row->get($this->getPrimaryKey()));
    }

    public function insert($document)
    {
        $this->processDocument(new Collection($document));
        $total = $this->totalDocumentsInCollection() + 1;
        $this->updateInfoTable('total_documents', $total);
    }

    public function update($id, $document)
    {
        $this->delete($id);
        $this->insert($document);
    }

    public function delete($documentId)
    {
        $rows = $this->prepareAndExecuteStatement("SELECT * FROM doclist WHERE doc_id = :documentId;", [
            ['key' => ':documentId', 'value' => $documentId]
        ])->fetchAll(PDO::FETCH_ASSOC);

        $updateStmt = $this->index->prepare("UPDATE wordlist SET num_docs = num_docs - 1, num_hits = num_hits - :hits WHERE id = :term_id");

        foreach ($rows as $document) {
            $updateStmt->bindParam(":hits", $document['hit_count']);
            $updateStmt->bindParam(":term_id", $document['term_id']);
            $updateStmt->execute();
        }

        $this->prepareAndExecuteStatement("DELETE FROM doclist WHERE doc_id = :documentId;", [
            ['key' => ':documentId', 'value' => $documentId]
        ]);

        $res = $this->prepareAndExecuteStatement("DELETE FROM wordlist WHERE num_hits = 0");

        $affected = $res->rowCount();

        if ($affected) {
            $total = $this->totalDocumentsInCollection() - 1;
            $this->updateInfoTable('total_documents', $total);
        }
    }

    public function updateInfoTable($key, $value)
    {
        $this->index->exec("UPDATE info SET value = $value WHERE key = '$key'");
    }

    public function stemText($text)
    {
        $stemmer = $this->getStemmer();
        $words   = $this->breakIntoTokens($text);
        $stems   = [];
        foreach ($words as $word) {
            $stems[] = $stemmer->stem($word);
        }
        return $stems;
    }

    public function breakIntoTokens($text)
    {
        if ($this->decodeHTMLEntities) {
            $text = html_entity_decode($text);
        }
        return $this->tokenizer->tokenize($text);
    }

    public function decodeHtmlEntities($value = true)
    {
        $this->decodeHTMLEntities = $value;
    }

    public function saveToIndex($stems, $docId)
    {
        $terms = $this->saveWordlist($stems);
        $this->saveDoclist($terms, $docId);
        $this->saveHitList($stems, $docId, $terms);
    }

    public function saveWordlist($stems)
    {
        $terms = [];
        $stems->map(function ($column, $key) use (&$terms) {
            foreach ($column as $term) {
                if (array_key_exists($term, $terms)) {
                    $terms[$term]['hits']++;
                    $terms[$term]['docs'] = 1;
                } else {
                    $terms[$term] = [
                        'hits' => 1,
                        'docs' => 1,
                        'id'   => 0
                    ];
                }
            }
        });

        $insertStmt = $this->index->prepare("INSERT INTO wordlist (term, num_hits, num_docs) VALUES (:keyword, :hits, :docs)");
        $selectStmt = $this->index->prepare("SELECT * FROM wordlist WHERE term like :keyword LIMIT 1");
        $updateStmt = $this->index->prepare("UPDATE wordlist SET num_docs = num_docs + :docs, num_hits = num_hits + :hits WHERE term = :keyword");

        foreach ($terms as $key => $term) {
            try {
                $insertStmt->bindParam(":keyword", $key);
                $insertStmt->bindParam(":hits", $term['hits']);
                $insertStmt->bindParam(":docs", $term['docs']);
                $insertStmt->execute();

                $terms[$key]['id'] = $this->index->lastInsertId();
                if ($this->inMemory) {
                    $this->inMemoryTerms[$key] = $terms[$key]['id'];
                }
            } catch (\Exception $e) {
                if ($e->getCode() == 23000) {
                    $updateStmt->bindValue(':docs', $term['docs']);
                    $updateStmt->bindValue(':hits', $term['hits']);
                    $updateStmt->bindValue(':keyword', $key);
                    $updateStmt->execute();
                    if (!$this->inMemory) {
                        $selectStmt->bindValue(':keyword', $key);
                        $selectStmt->execute();
                        $res               = $selectStmt->fetch(PDO::FETCH_ASSOC);
                        $terms[$key]['id'] = $res['id'];
                    } else {
                        $terms[$key]['id'] = $this->inMemoryTerms[$key];
                    }
                } else {
                    echo $e->getMessage()."\n";
                }
            }
        }
        return $terms;
    }

    public function saveDoclist($terms, $docId)
    {
        $insert = "INSERT INTO doclist (term_id, doc_id, hit_count) VALUES (:id, :doc, :hits)";
        $stmt   = $this->index->prepare($insert);

        foreach ($terms as $key => $term) {
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

    public function saveHitList($stems, $docId, $termsList)
    {
        return;
        $fieldCounter = 0;
        $fields       = [];

        $insert = "INSERT INTO hitlist (term_id, doc_id, field_id, position, hit_count)
                   VALUES (:term_id, :doc_id, :field_id, :position, :hit_count)";
        $stmt = $this->index->prepare($insert);

        foreach ($stems as $field => $terms) {
            $fields[$fieldCounter] = $field;
            $positionCounter       = 0;
            $termCounts            = array_count_values($terms);
            foreach ($terms as $term) {
                if (isset($termsList[$term])) {
                    $stmt->bindValue(':term_id', $termsList[$term]['id']);
                    $stmt->bindValue(':doc_id', $docId);
                    $stmt->bindValue(':field_id', $fieldCounter);
                    $stmt->bindValue(':position', $positionCounter);
                    $stmt->bindValue(':hit_count', $termCounts[$term]);
                    $stmt->execute();
                }
                $positionCounter++;
            }
            $fieldCounter++;
        }
    }

    public function getWordFromWordList($word)
    {
        $selectStmt = $this->index->prepare("SELECT * FROM wordlist WHERE term like :keyword LIMIT 1");
        $selectStmt->bindValue(':keyword', $word);
        $selectStmt->execute();
        return $selectStmt->fetch(PDO::FETCH_ASSOC);
    }

    public function countWordInWordList($word)
    {
        $res = $this->getWordFromWordList($word);

        if ($res) {
            return $res['num_hits'];
        }
        return 0;
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
        $selectStmt = $this->index->prepare("SELECT * FROM wordlist ORDER BY num_hits DESC;");
        $selectStmt->execute();

        $dictionary = "";
        $counter    = 0;

        while ($row = $selectStmt->fetch(PDO::FETCH_ASSOC)) {
            $dictionary .= $row['term'];
            if ($hits) {
                $dictionary .= "\t".$row['num_hits'];
            }

            if ($docs) {
                $dictionary .= "\t".$row['num_docs'];
            }

            $counter++;
            if ($counter >= $count && $count > 0) {
                break;
            }

            $dictionary .= "\n";
        }

        file_put_contents($filename, $dictionary, LOCK_EX);
    }

    public function totalDocumentsInCollection()
    {
        $query = "SELECT * FROM info WHERE key = 'total_documents'";
        $docs  = $this->index->query($query);

        return $docs->fetch(PDO::FETCH_ASSOC)['value'];
    }

    public function buildTrigrams($keyword)
    {
        $t        = "__".$keyword."__";
        $trigrams = "";
        for ($i = 0; $i < strlen($t) - 2; $i++) {
            $trigrams .= substr($t, $i, 3)." ";
        }

        return trim($trigrams);
    }

    public function prepareAndExecuteStatement($query, $params = [])
    {
        $statemnt = $this->index->prepare($query);
        foreach ($params as $param) {
            $statemnt->bindParam($param['key'], $param['value']);
        }
        $statemnt->execute();
        return $statemnt;
    }

    public function info($text)
    {
        if (!$this->disableOutput) {
            echo $text.PHP_EOL;
        }
    }
}
