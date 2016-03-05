<?php

namespace TeamTNT\Indexer;

use TeamTNT\Stemmer\PorterStemmer;
use TeamTNT\Support\Collection;
use PDO;

class TNTIndexer
{
    protected $index = null;
    protected $dbh   = null;

    public function loadConfig($config)
    {
        $this->config = $config;
        $this->config['storage'] = rtrim($this->config['storage'], '/') . '/';
    }

    public function getStoragePath()
    {
        return $this->config['storage'];
    }

    public function createIndex($indexName)
    {
        $this->index = new PDO('sqlite:' . $this->config['storage'] . $indexName);
        $this->index->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->index->exec("CREATE TABLE IF NOT EXISTS wordlist (
                    term_id INTEGER PRIMARY KEY,
                    num_hits INTEGER,
                    num_docs INTEGER)");

        $this->index->exec("CREATE TABLE IF NOT EXISTS doclist (
                    term_id INTEGER,
                    doc_id INTEGER,
                    hit_count INTEGER)");

        $this->index->exec("CREATE INDEX IF NOT EXISTS 'main'.'index' ON 'doclist' ('term_id' COLLATE BINARY);");
        $this->setSource();
        return $this;
    }

    public function setSource()
    {
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
        $result = $this->dbh->query($this->query);

        $counter = 0;
        $this->index->beginTransaction();
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $counter++;


            $this->processRow(new Collection($row));

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
        echo "Total rows $counter\n";
    }

    public function processRow($row)
    {
        $stems = $row->map(function($column, $name) {
            return $this->stemText($column);
        });

        $this->saveToIndex($stems, $row->get('id'));
    }

    public function stemText($text)
    {
        $stemmer = new PorterStemmer();
        $words = preg_split("/[ ,;\n\r\t]+/", trim($text));

        $stems = [];
        foreach($words as $word) {
            if(strlen($word) < 3) continue;
            $stems[] = $stemmer->Stem(strtolower($word));
        }
        return $stems;
    }

    public function saveToIndex($stems, $docId)
    {
        $terms = $this->saveWordlist($stems);
        $this->saveDoclist($terms, $docId);
    }

    public function saveWordlist($stems)
    {
        $terms = [];
        $stems->map(function($column, $key) use (&$terms) {
            foreach($column as $term) {
                $crc32 = crc32($term);

                if(array_key_exists($crc32, $terms)) {
                    $terms[$crc32]['hits']++;
                    $terms[$crc32]['docs'] = 1;
                } else {
                    $terms[$crc32] = [
                        'hits' => 1,
                        'docs' => 1
                    ];
                }
            }
        });

        $insert = "INSERT INTO wordlist (term_id, num_hits, num_docs) VALUES (:id, :hits, :docs)";
        $stmt = $this->index->prepare($insert);

        foreach($terms as $key => $term) {
            $stmt->bindValue(':id', $key, SQLITE3_INTEGER);
            $stmt->bindValue(':hits', $term['hits'], SQLITE3_INTEGER);
            $stmt->bindValue(':docs', $term['docs'], SQLITE3_INTEGER);
            try {
                $stmt->execute();
            } catch (\Exception $e) {
                //we have a duplicate
                if($e->getCode() == 23000) {
                    $res = $this->index->query("SELECT * FROM wordlist WHERE term_id = $key");
                    $res = $res->fetch(PDO::FETCH_ASSOC);
                    $term['hits'] += $res['num_hits'];
                    $term['docs'] += $res['num_docs'];
                    $insert_stmt = $this->index->prepare("UPDATE wordlist SET num_docs = :docs, num_hits = :hits WHERE term_id = :term");
                    $insert_stmt->bindValue(':docs', $term['docs'], SQLITE3_INTEGER);
                    $insert_stmt->bindValue(':hits', $term['hits'], SQLITE3_INTEGER);
                    $insert_stmt->bindValue(':term', $key, SQLITE3_INTEGER);
                    $insert_stmt->execute();
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
            $stmt->bindValue(':id', $key, SQLITE3_INTEGER);
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

}