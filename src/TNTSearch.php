<?php

namespace TeamTNT;

use TeamTNT\Support\Collection;
use TeamTNT\Indexer\TNTIndexer;
use PDO;

class TNTSearch
{
    public function createIndex($indexName, $path)
    {
        $indexer = new TNTIndexer;
        return $indexer->createIndex($indexName, $path);
    }

    public function selectIndex($indexName, $path = "")
    {
        $this->index = new PDO('sqlite:' . $path . $indexName);
        $this->index->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function search($phrase)
    {
        $this->info("Searching for $phrase");

        $searchDoclist = "SELECT * FROM doclist WHERE term_id = :id LIMIT 1000";
        $stmtDoc = $this->index->prepare($searchDoclist);
        $stmtDoc->bindValue(':id', crc32(strtolower($phrase)), SQLITE3_INTEGER);
        $stmtDoc->execute();

        $searchWordlist = "SELECT * FROM wordlist WHERE term_id = :id LIMIT 1";
        $stmtWord = $this->index->prepare($searchWordlist);
        $stmtWord->bindValue(':id', crc32(strtolower($phrase)), SQLITE3_INTEGER);
        $stmtWord->execute();

        $docsCollection = new Collection($stmtDoc->fetchAll(PDO::FETCH_ASSOC));
        $docs = $docsCollection->map(function($doc) {
           return $doc['doc_id'];
        });

        $occurance = $stmtWord->fetchAll(PDO::FETCH_ASSOC)[0];

        return [
            'info' => [
                'hits' => $occurance['num_hits'],
                'docs' => $occurance['num_docs'],
            ],
            'rows' => $docs->implode(', ')
        ];
    }

    public function info($str)
    {
        echo $str . "\n";
    }
}