<?php

namespace TeamTNT\Indexer;

use TeamTNT\Stemmer\PorterStemmer;
use TeamTNT\Support\Collection;
use PDO;

class TNTIndexer
{
    protected $index = null;
    protected $dbh   = null;

    public function createIndex($indexName, $path)
    {
        $this->index = new PDO('sqlite:' . $path . $indexName);
        $this->index->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->index->exec("CREATE TABLE IF NOT EXISTS wordlist (
                    term_id INTEGER PRIMARY KEY,
                    num_hits INTEGER,
                    num_docs INTEGER)");
        return $this;
    }

    public function source($config = [])
    {
        $this->type = $config['type'];
        $this->db   = $config['db'];
        $this->host = $config['host'];
        $this->user = $config['user'];
        $this->pass = $config['pass'];

        $this->dbh = new PDO($this->type.':host='.$this->host.';dbname='.$this->db, $this->user, $this->pass);
        $this->dbh->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function query($query)
    {
        $this->query = $query;
    }


    public function loadConfiguration($config = [])
    {
        $this->storagePath = $config['storage_path'];
    }

    public function run()
    {
        $result = $this->dbh->query($this->query);

        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $this->processRow(new Collection($row));
        }
    }

    public function processRow($row)
    {
        $stems = $row->map(function($column, $name) {
            return $this->stemText($column);
        });
        $this->saveToIndex($stems);
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

    public function saveToIndex($stems)
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

        $this->index->beginTransaction();
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
        $this->index->commit();
    }
}