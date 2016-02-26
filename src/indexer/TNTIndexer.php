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

        $this->index->exec("CREATE TABLE IF NOT EXISTS doclist (
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

        $insert = "INSERT INTO doclist (term_id, num_hits, num_docs) VALUES (:id, :hits, :docs)";
        $stmt = $this->index->prepare($insert);

        foreach($terms as $key => $term) {
            $stmt->bindValue(':id', $key, SQLITE3_INTEGER);
            $stmt->bindValue(':hits', $term['hits'], SQLITE3_INTEGER);
            $stmt->bindValue(':docs', $term['docs'], SQLITE3_INTEGER);
            try {
                $stmt->execute();
            } catch (\Exception $e) {
                //echo $e->getMessage() . "\n";
            }
        }
    }
}