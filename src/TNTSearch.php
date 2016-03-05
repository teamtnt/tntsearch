<?php

namespace TeamTNT;

use TeamTNT\Support\Collection;
use TeamTNT\Indexer\TNTIndexer;
use TeamTNT\Stemmer\PorterStemmer;
use PDO;

class TNTSearch
{
    public $config;

    public function loadConfig($config)
    {
        $this->config = $config;
        $this->config['storage'] = rtrim($this->config['storage'], '/') . '/';
    }

    public function createIndex($indexName)
    {
        $indexer = new TNTIndexer;
        $indexer->loadConfig($this->config);
        return $indexer->createIndex($indexName);
    }

    public function selectIndex($indexName)
    {
        $this->index = new PDO('sqlite:' . $this->config['storage'] . $indexName);
        $this->index->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function search($phrase, $numOfResults = 100)
    {
        $this->info("Searching for $phrase");

        $keywords = preg_split("/[ ,;\n\r\t]+/", trim($phrase));
        $keywords = new Collection($keywords);

        $stemmer = new PorterStemmer();

        $keywords = $keywords->map(function($keyword) use ($stemmer) {
            return $stemmer->Stem($keyword);
        });

        $tfWeight = 1; $dlWeight = 0.5;
        $docScores = [];

        $count = $this->totalDocumentsInCollection();
        foreach($keywords as $term) {
            $df = $this->totalMatchingDocuments( $term );
            foreach($this->getAllDocumentsForKeyword($term) as $document) {
                $docID = $document['doc_id'];
                $tf    = $document['hit_count'];
                $docLength = 0;
                $idf = log($count/$df);
                $num = ($tfWeight + 1) * $tf;
                $denom = $tfWeight
                    * ((1 - $dlWeight) + $dlWeight)
                    + $tf;
                $score = $idf * ($num/$denom);
                $docScores[$docID] = isset($docScores[$docID]) ?
                    $docScores[$docID] + $score : $score;
            }
        }

        arsort($docScores);

        $docs = new Collection($docScores);

        $counter = 0;
        $docs = $docs->map(function($doc, $key) {
            return $key;
        })->filter(function($item) use (&$counter, $numOfResults) {
            $counter++;
            if($counter < $numOfResults) return $item;
        });

        return [
            'rows' => $docs->implode(', ')
        ];
    }

    public function getAllDocumentsForKeyword($keyword)
    {
        $query = "SELECT * FROM doclist WHERE term_id = :id";
        $stmtDoc = $this->index->prepare($query);
        $stmtDoc->bindValue(':id', crc32(strtolower($keyword)), SQLITE3_INTEGER);
        $stmtDoc->execute();
        return $stmtDoc->fetchAll(PDO::FETCH_ASSOC);
    }

    public function totalMatchingDocuments($keyword)
    {
        $searchWordlist = "SELECT * FROM wordlist WHERE term_id = :id";
        $stmtWord = $this->index->prepare($searchWordlist);
        $stmtWord->bindValue(':id', crc32(strtolower($keyword)), SQLITE3_INTEGER);
        $stmtWord->execute();
        $occurance = $stmtWord->fetchAll(PDO::FETCH_ASSOC);
        if(isset($occurance[0]))
            return $occurance[0]['num_docs'];
        return 0;
    }

    public function totalDocumentsInCollection()
    {
        $query = "SELECT * FROM info WHERE key = 'total_documents'";
        $docs = $this->index->query($query);

        return $docs->fetch(PDO::FETCH_ASSOC)['value'];
    }

    public function info($str)
    {
        echo $str . "\n";
    }
}