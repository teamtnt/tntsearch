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
use TeamTNT\TNTSearch\Connectors\SqlServerConnector;
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
    protected $excludePrimaryKey  = true;
    public $stemmer               = null;
    public $tokenizer             = null;
    public $stopWords             = [];
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
    public $statementsPrepared    = false;

    public function __construct()
    {
        $this->stemmer    = new PorterStemmer;
        $this->tokenizer  = new Tokenizer;
        $this->filereader = new TextFileReader;
    }

    /**
     * @param TokenizerInterface $tokenizer
     */
    public function setTokenizer(TokenizerInterface $tokenizer)
    {
        $this->tokenizer = $tokenizer;
    }

    public function setStopWords(array $stopWords)
    {
        $this->stopWords = $stopWords;
    }

    /**
     * @param array $config
     */
    public function loadConfig(array $config)
    {
        $this->config            = $config;
        $this->config['storage'] = rtrim($this->config['storage'], '/').'/';
        if (!isset($this->config['driver'])) {
            $this->config['driver'] = "";
        }

    }

    /**
     * @return string
     */
    public function getStoragePath()
    {
        return $this->config['storage'];
    }

    public function getStemmer()
    {
        return $this->stemmer;
    }

    /**
     * @return string
     */
    public function getPrimaryKey()
    {
        if (isset($this->primaryKey)) {
            return $this->primaryKey;
        }
        return 'id';
    }

    /**
     * @param string $primaryKey
     */
    public function setPrimaryKey($primaryKey)
    {
        $this->primaryKey = $primaryKey;
    }

    public function excludePrimaryKey()
    {
        $this->excludePrimaryKey = true;
    }

    public function includePrimaryKey()
    {
        $this->excludePrimaryKey = false;
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

    /**
     * @param string $language  - one of: arabic, croatian, german, italian, porter, russian, ukrainian
     */
    public function setLanguage($language = 'porter')
    {
        $class = 'TeamTNT\\TNTSearch\\Stemmer\\'.ucfirst(strtolower($language)).'Stemmer';
        $this->setStemmer(new $class);
    }

    /**
     * @param PDO $index
     */
    public function setIndex($index)
    {
        $this->index = $index;
    }

    public function setFileReader($filereader)
    {
        $this->filereader = $filereader;
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
     * @param string $indexName
     *
     * @return TNTIndexer
     */
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

    /**
     * @param array $config
     *
     * @return FileSystemConnector|MySqlConnector|PostgresConnector|SQLiteConnector|SqlServerConnector
     * @throws Exception
     */
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
            case 'sqlsrv':
                return new SqlServerConnector;
            case 'filesystem':
                return new FileSystemConnector;
        }
        throw new Exception("Unsupported driver [{$config['driver']}]");
    }

    /**
     * @param PDO $dbh
     */
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
        $documentId = $row->get($this->getPrimaryKey());

        if ($this->excludePrimaryKey) {
            $row->forget($this->getPrimaryKey());
        }

        $stems = $row->map(function ($columnContent, $columnName) use ($row) {
            return $this->stemText($columnContent);
        });

        $this->saveToIndex($stems, $documentId);
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
        return $this->tokenizer->tokenize($text, $this->stopWords);
    }

    public function decodeHtmlEntities($value = true)
    {
        $this->decodeHTMLEntities = $value;
    }

    public function saveToIndex($stems, $docId)
    {
        $this->prepareStatementsForIndex();
        $terms = $this->saveWordlist($stems);
        $this->saveDoclist($terms, $docId);
        $this->saveHitList($stems, $docId, $terms);
    }

    /**
     * @param $stems
     *
     * @return array
     */
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

        foreach ($terms as $key => $term) {
            try {
                $this->insertWordlistStmt->bindParam(":keyword", $key);
                $this->insertWordlistStmt->bindParam(":hits", $term['hits']);
                $this->insertWordlistStmt->bindParam(":docs", $term['docs']);
                $this->insertWordlistStmt->execute();

                $terms[$key]['id'] = $this->index->lastInsertId();
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
                        $res               = $this->selectWordlistStmt->fetch(PDO::FETCH_ASSOC);
                        $terms[$key]['id'] = $res['id'];
                    } else {
                        $terms[$key]['id'] = $this->inMemoryTerms[$key];
                    }
                } else {
                    echo "Error while saving wordlist: ".$e->getMessage()."\n";
                }

                // Statements must be refreshed, because in this state they have error attached to them.
                $this->statementsPrepared = false;
                $this->prepareStatementsForIndex();

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

    /**
     * @param $word
     *
     * @return int
     */
    public function countWordInWordList($word)
    {
        $res = $this->getWordFromWordList($word);

        if ($res) {
            return $res['num_hits'];
        }
        return 0;
    }

    /**
     * @param $word
     *
     * @return int
     */
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

    /**
     * @return int
     */
    public function totalDocumentsInCollection()
    {
        $query = "SELECT * FROM info WHERE key = 'total_documents'";
        $docs  = $this->index->query($query);

        return $docs->fetch(PDO::FETCH_ASSOC)['value'];
    }

    /**
     * @param $keyword
     *
     * @return string
     */
    public function buildTrigrams($keyword)
    {
        $t        = "__".$keyword."__";
        $trigrams = "";
        for ($i = 0; $i < strlen($t) - 2; $i++) {
            $trigrams .= mb_substr($t, $i, 3)." ";
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
