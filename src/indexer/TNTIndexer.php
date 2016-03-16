<?php

namespace TeamTNT\Indexer;

use TeamTNT\Stemmer\PorterStemmer;
use TeamTNT\Stemmer\CroatianStemmer;
use TeamTNT\Support\Collection;
use PDO;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class TNTIndexer
{
    protected $index = null;
    protected $dbh   = null;

    public function __construct()
    {
        $this->stemmer = new PorterStemmer;
    }

    public function loadConfig($config)
    {
        $this->config = $config;
        $this->config['storage'] = rtrim($this->config['storage'], '/') . '/';
        if(!isset($this->config['driver'])) $this->config['driver'] = "";
    }

    public function getStoragePath()
    {
        return $this->config['storage'];
    }

    public function getStemmer()
    {
        return $this->stemmer;
    }

    public function setCroatianStemmer()
    {
        $this->index->exec("INSERT INTO info ( 'key', 'value') values ( 'stemmer', 'croatian')");
        $this->stemmer = new CroatianStemmer;
    }

    public function createIndex($indexName)
    {
        if(file_exists($this->config['storage'] . $indexName)) {
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

        $this->index->exec("CREATE INDEX IF NOT EXISTS 'main'.'index' ON 'doclist' ('term_id' COLLATE BINARY);");
        $this->setSource();
        return $this;
    }

    public function setSource()
    {
        if($this->config['driver'] == "filesystem") return;

        $this->dbh = new PDO($this->config['type'].':host='.$this->config['host'].';dbname='.$this->config['db'],
            $this->config['user'], $this->config['pass']);
        $this->dbh->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function query($query)
    {
        $this->query = $query;
    }

    public function run()
    {
        if($this->config['driver'] == "filesystem") {
            return $this->readDocumentsFromFileSystem();
        }

        $result = $this->dbh->query($this->query);

        $counter = 0;
        $this->index->beginTransaction();
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $counter++;


            $this->processDocument(new Collection($row));

            if($counter % 1000 == 0) {
                echo "Processed $counter rows\n";
            }
            if($counter % 10000 == 0) {
                $this->index->commit();
                $this->index->beginTransaction();
                echo "Commited\n";
            }
        }
        $this->index->commit();

        $this->index->exec("INSERT INTO info ( 'key', 'value') values ( 'total_documents', $counter)");

        echo "Total rows $counter\n";
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

        foreach($objects as $name => $object) {
            $name = str_replace($path . '/', '', $name);
            if(stringEndsWith($name, $this->config['extension']) && !in_array($name, $this->config['exclude'])) {
                $counter++;
                $file = [
                    'id'      => $counter,
                    'name'    => $name,
                    'content' => file_get_contents($object)
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
        $stems = $row->map(function($column, $name) {
            return $this->stemText($column);
        });

        $this->saveToIndex($stems, $row->get('id'));
    }

    public function stemText($text)
    {
        $stemmer = $this->getStemmer();
        $text = preg_replace("/[^\w\ _]+/", ' ', $text);
        $words = preg_split('/\PL+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $stems = [];
        foreach($words as $word) {
            $stems[] = $stemmer->stem(strtolower($word));
        }
        return $stems;
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
        $stems->map(function($column, $key) use (&$terms) {
            foreach($column as $term) {
                if(strlen($term) < 3) continue;

                if(array_key_exists($term, $terms)) {
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
        $selectStmt = $this->index->prepare("SELECT * FROM wordlist WHERE term like :term LIMIT 1");
        $updateStmt = $this->index->prepare("UPDATE wordlist SET num_docs = num_docs + :docs, num_hits = num_hits + :hits WHERE term = :keyword");
        
        foreach($terms as $key => $term) {
            try {
                $insertStmt->bindParam(":keyword", $key, SQLITE3_TEXT);
                $insertStmt->bindParam(":hits", $term['hits'], SQLITE3_INTEGER);
                $insertStmt->bindParam(":docs", $term['docs'], SQLITE3_INTEGER);
                $insertStmt->execute();

                $terms[$key]['id'] = $this->index->lastInsertId();

            } catch (\Exception $e) {
                if($e->getCode() == 23000) {
                    $updateStmt->bindValue(':docs', $term['docs'], SQLITE3_INTEGER);
                    $updateStmt->bindValue(':hits', $term['hits'], SQLITE3_INTEGER);
                    $updateStmt->bindValue(':keyword', $key, SQLITE3_TEXT);
                    $updateStmt->execute();

                    $selectStmt->bindValue(':term', $key);
                    $selectStmt->execute();
                    $res = $selectStmt->fetch(PDO::FETCH_ASSOC);
                    $terms[$key]['id'] = $res['id'];
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
        $stmt = $this->index->prepare($insert);

        foreach($terms as $key => $term) {
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
        $fields = [];

        $insert = "INSERT INTO hitlist (term_id, doc_id, field_id, position, hit_count) 
                   VALUES (:term_id, :doc_id, :field_id, :position, :hit_count)";
        $stmt = $this->index->prepare($insert);

        foreach ($stems as $field => $terms) {
            $fields[$fieldCounter] = $field;
            $positionCounter = 0;
            $termCounts = array_count_values($terms);
            foreach ($terms as $term) {
                if(isset($termsList[$term])) {
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
}