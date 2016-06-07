<?php

namespace TeamTNT\TNTSearch\Indexer;

use Exception;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use TeamTNT\TNTSearch\Stemmer\CroatianStemmer;
use TeamTNT\TNTSearch\Stemmer\PorterStemmer;
use TeamTNT\TNTSearch\Support\Collection;

class TNTIndexer
{
    protected $index              = null;
    protected $dbh                = null;
    protected $wordlist           = [];
    protected $inMemoryTerms      = [];
    protected $decodeHTMLEntities = false;
    public $disableOutput         = false;
    public $inMemory              = true;
    public $steps                 = 1000;

    const FILESYSTEM_DRIVER = 'filesystem';
    const MYSQL_DRIVER      = 'mysql';
    const SQLITE_DRIVER     = 'sqlite';
    const PGSQL_DRIVER      = 'pgsql';

    public function __construct()
    {
        $this->stemmer = new PorterStemmer;
    }

    public function loadConfig($config)
    {
        $this->config            = $config;
        $this->config['storage'] = rtrim($this->config['storage'], '/') . '/';
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

    public function setStemmer($stemmer)
    {
        $this->stemmer = $stemmer;
    }

    public function setCroatianStemmer()
    {
        $this->index->exec("INSERT INTO info ( 'key', 'value') values ( 'stemmer', 'croatian')");
        $this->stemmer = new CroatianStemmer;
    }

    public function setLanguage($language = 'porter')
    {
        $this->index->exec("INSERT INTO info ( 'key', 'value') values ( 'stemmer', '$language')");
        $class         = 'TeamTNT\\TNTSearch\\Stemmer\\' . ucfirst(strtolower($language)) . 'Stemmer';
        $this->stemmer = new $class;
    }

    public function setIndex($index)
    {
        $this->index = $index;
    }

    public function createIndex($indexName)
    {
        if (file_exists($this->config['storage'] . $indexName)) {
            unlink($this->config['storage'] . $indexName);
        }

        $this->index = new PDO('sqlite:' . $this->config['storage'] . $indexName);
        $this->index->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->index->exec("CREATE TABLE IF NOT EXISTS wordlist (
                    id INTEGER PRIMARY KEY,
                    term TEXT,
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

        $this->index->exec("CREATE INDEX IF NOT EXISTS 'main'.'term_id_index' ON doclist ('term_id' COLLATE BINARY);");
        $this->setSource();
        return $this;
    }

    public function setSource()
    {
        extract($this->config, EXTR_SKIP);

        if ($driver == self::FILESYSTEM_DRIVER) {
            return;
        }

        $hostDsn   = $this->getHostDsn($this->config);
        $this->dbh = new PDO($hostDsn, $username, $password);
        if ($driver == self::MYSQL_DRIVER) {
            $this->dbh->prepare("set names utf8 collate utf8_unicode_ci")->execute();
            $this->dbh->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        }
        $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    protected function getHostDsn(array $config)
    {
        extract($config, EXTR_SKIP);

        if ($driver == self::SQLITE_DRIVER) {
            if ($database == ':memory:') {
                return 'sqlite::memory:';
            }

            $path = realpath($config['database']);

            if ($path === false) {
                throw new Exception("Database (${config['database']}) does not exist.");
            }
            return "sqlite:{$path}";
        }

        if ($driver == self::MYSQL_DRIVER) {
            return isset($port)
            ? "mysql:host={$host};port={$port};dbname={$database}"
            : "mysql:host={$host};dbname={$database}";
        }

        if ($driver == self::PGSQL_DRIVER) {
            $host = isset($host) ? "host={$host};" : '';
            $dsn  = "pgsql:{$host}dbname={$database}";
            if (isset($config['port'])) {
                $dsn .= ";port={$port}";
            }

            if (isset($config['sslmode'])) {
                $dsn .= ";sslmode={$sslmode}";
            }

            return $dsn;
        }
    }

    public function query($query)
    {
        $this->query = $query;
    }

    public function run()
    {
        if ($this->config['driver'] == self::FILESYSTEM_DRIVER) {
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

        $this->index->exec("INSERT INTO info ( 'key', 'value') values ( 'total_documents', $counter)");

        $this->info("Total rows $counter");
    }

    public function readDocumentsFromFileSystem()
    {
        $this->index->exec("CREATE TABLE IF NOT EXISTS filemap (
                    id INTEGER PRIMARY KEY,
                    path TEXT)");
        $path = realpath($this->config['location']);

        $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST);
        $this->index->beginTransaction();
        $counter = 0;

        foreach ($objects as $name => $object) {
            $name = str_replace($path . '/', '', $name);
            if (stringEndsWith($name, $this->config['extension']) && !in_array($name, $this->config['exclude'])) {
                $counter++;
                $file = [
                    'id'      => $counter,
                    'name'    => $name,
                    'content' => file_get_contents($object),
                ];
                $this->processDocument(new Collection($file));
                $this->index->exec("INSERT INTO filemap ( 'id', 'path') values ( $counter, '$object')");
                echo "Processed $counter " . $object . "\n";
            }
        }

        $this->index->commit();

        $this->index->exec("INSERT INTO info ( 'key', 'value') values ( 'total_documents', $counter)");
        $this->index->exec("INSERT INTO info ( 'key', 'value') values ( 'driver', 'filesystem')");

        echo "Total rows $counter\n";
    }

    public function processDocument($row)
    {
        $stems = $row->map(function ($column, $name) {
            return $this->stemText($column);
        });
        $this->saveToIndex($stems, $row->get('id'));
    }

    public function insert($document)
    {
        $this->processDocument(new Collection($document));
    }

    public function update($id, $document)
    {
        $this->delete($id);
        $this->insert($document);
    }

    public function delete($documentId)
    {
        $selectStmt = $this->index->prepare("SELECT * FROM doclist WHERE doc_id = :documentId;");
        $selectStmt->bindParam(":documentId", $documentId, SQLITE3_INTEGER);
        $selectStmt->execute();
        $rows = $selectStmt->fetchAll(PDO::FETCH_ASSOC);

        $updateStmt = $this->index->prepare("UPDATE wordlist SET num_docs = num_docs - 1, num_hits = num_hits - :hits WHERE id = :term_id");

        foreach ($rows as $document) {
            $updateStmt->bindParam(":hits", $document['hit_count'], SQLITE3_INTEGER);
            $updateStmt->bindParam(":term_id", $document['term_id'], SQLITE3_INTEGER);
            $updateStmt->execute();
        }

        $deleteStmt = $this->index->prepare("DELETE FROM doclist WHERE doc_id = :documentId;");
        $deleteStmt->bindParam(":documentId", $documentId, SQLITE3_INTEGER);
        $deleteStmt->execute();

        $deleteStmt = $this->index->prepare("DELETE FROM wordlist WHERE num_hits = 0");
        $deleteStmt->execute();
    }

    public function stemText($text)
    {
        $stemmer = $this->getStemmer();
        $words   = $this->breakIntoTokens($text);
        $stems   = [];
        foreach ($words as $word) {
            $stems[] = $stemmer->stem(strtolower($word));
        }
        return $stems;
    }

    public function breakIntoTokens($text)
    {
        if ($this->decodeHTMLEntities) {
            $text = html_entity_decode($text);
        }
        return preg_split("/[^\p{L}\p{N}]+/u", $text, -1, PREG_SPLIT_NO_EMPTY);
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
                        'id'   => 0,
                    ];
                }
            }
        });

        $insertStmt = $this->index->prepare("INSERT INTO wordlist (term, num_hits, num_docs) VALUES (:keyword, :hits, :docs)");
        $selectStmt = $this->index->prepare("SELECT * FROM wordlist WHERE term like :keyword LIMIT 1");
        $updateStmt = $this->index->prepare("UPDATE wordlist SET num_docs = num_docs + :docs, num_hits = num_hits + :hits WHERE term = :keyword");

        foreach ($terms as $key => $term) {
            try {
                $insertStmt->bindParam(":keyword", $key, SQLITE3_TEXT);
                $insertStmt->bindParam(":hits", $term['hits'], SQLITE3_INTEGER);
                $insertStmt->bindParam(":docs", $term['docs'], SQLITE3_INTEGER);
                $insertStmt->execute();

                $terms[$key]['id'] = $this->index->lastInsertId();
                if ($this->inMemory) {
                    $this->inMemoryTerms[$key] = $terms[$key]['id'];
                }
            } catch (\Exception $e) {
                if ($e->getCode() == 23000) {
                    $updateStmt->bindValue(':docs', $term['docs'], SQLITE3_INTEGER);
                    $updateStmt->bindValue(':hits', $term['hits'], SQLITE3_INTEGER);
                    $updateStmt->bindValue(':keyword', $key, SQLITE3_TEXT);
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
                    echo $e->getMessage() . "\n";
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
            $stmt->bindValue(':id', $term['id'], SQLITE3_INTEGER);
            $stmt->bindValue(':doc', $docId, SQLITE3_INTEGER);
            $stmt->bindValue(':hits', $term['hits'], SQLITE3_INTEGER);
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
                    $stmt->bindValue(':term_id', $termsList[$term]['id'], SQLITE3_INTEGER);
                    $stmt->bindValue(':doc_id', $docId, SQLITE3_INTEGER);
                    $stmt->bindValue(':field_id', $fieldCounter, SQLITE3_INTEGER);
                    $stmt->bindValue(':position', $positionCounter, SQLITE3_INTEGER);
                    $stmt->bindValue(':hit_count', $termCounts[$term], SQLITE3_INTEGER);
                    $stmt->execute();
                }
                $positionCounter++;
            }
            $fieldCounter++;
        }
    }

    public function countWordInWordList($word)
    {
        $selectStmt = $this->index->prepare("SELECT * FROM wordlist WHERE term like :keyword LIMIT 1");
        $selectStmt->bindValue(':keyword', $word);
        $selectStmt->execute();
        $res = $selectStmt->fetch(PDO::FETCH_ASSOC);

        if ($res) {
            return $res['num_hits'];
        }
        return 0;
    }

    public function countDocHitsInWordList($word)
    {
        $selectStmt = $this->index->prepare("SELECT * FROM wordlist WHERE term like :keyword LIMIT 1");
        $selectStmt->bindValue(':keyword', $word);
        $selectStmt->execute();
        $res = $selectStmt->fetch(PDO::FETCH_ASSOC);

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

    public function buildTrigrams($keyword)
    {
        $t        = "__" . $keyword . "__";
        $trigrams = "";
        for ($i = 0; $i < strlen($t) - 2; $i++) {
            $trigrams .= substr($t, $i, 3) . " ";
        }

        return trim($trigrams);
    }

    public function info($text)
    {
        if (!$this->disableOutput) {
            echo $text . PHP_EOL;
        }
    }
}
