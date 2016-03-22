<?php

namespace TeamTNT;

use TeamTNT\Support\Collection;
use TeamTNT\Support\Hihglighter;
use TeamTNT\Indexer\TNTIndexer;
use TeamTNT\Stemmer\PorterStemmer;
use TeamTNT\Stemmer\CroatianStemmer;
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
        $phrase = preg_replace("/[^\w\ _]+/", ' ', $phrase);
        $keywords = preg_split('/\PL+/u', $phrase, -1, PREG_SPLIT_NO_EMPTY);

        $keywords = new Collection($keywords);

        $stemmer = new CroatianStemmer();

        $keywords = $keywords->map(function($keyword) use ($stemmer) {
            return $stemmer->stem($keyword);
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
            if($counter <= $numOfResults) return $item;
        });

        if($this->isFileSystemIndex()) {
            return $this->filesystemMapIdsToPaths($docs)->toArray();
        }
        return [
            'ids' => $docs->implode(', ')
        ];
    }

    public function getAllDocumentsForKeyword($keyword)
    {
        $word = $this->getWordlistByKeyword($keyword);
        if(!isset($word[0])) {
            return [];
        }
        $query = "SELECT * FROM doclist WHERE term_id = :id";
        $stmtDoc = $this->index->prepare($query);

        $stmtDoc->bindValue(':id', $word[0]['id'], SQLITE3_INTEGER);
        $stmtDoc->execute();
        return $stmtDoc->fetchAll(PDO::FETCH_ASSOC);
    }

    public function totalMatchingDocuments($keyword)
    {
        $occurance = $this->getWordlistByKeyword($keyword);
        if(isset($occurance[0]))
            return $occurance[0]['num_docs'];
        return 0;
    }

    public function getWordlistByKeyword($keyword) 
    {
        if(isset($this->wordlist[$keyword])) {
            return $this->wordlist[$keyword];
        }
        $searchWordlist = "SELECT * FROM wordlist WHERE term like :keyword LIMIT 1";
        $stmtWord = $this->index->prepare($searchWordlist);
        $stmtWord->bindValue(':keyword', strtolower($keyword), SQLITE3_TEXT);
        $stmtWord->execute();
        $this->wordlist[$keyword] = $stmtWord->fetchAll(PDO::FETCH_ASSOC);
        return $this->wordlist[$keyword];
    }

    public function totalDocumentsInCollection()
    {
        $query = "SELECT * FROM info WHERE key = 'total_documents'";
        $docs = $this->index->query($query);

        return $docs->fetch(PDO::FETCH_ASSOC)['value'];
    }

    public function isFileSystemIndex()
    {
        $query = "SELECT * FROM info WHERE key = 'driver'";
        $docs = $this->index->query($query);

        return $docs->fetch(PDO::FETCH_ASSOC)['value'] == 'filesystem';
    }

    public function filesystemMapIdsToPaths($docs)
    {
        $query = "SELECT * FROM filemap WHERE id in (".$docs->implode(', ').");";
        $res = $this->index->query($query)->fetchAll(PDO::FETCH_ASSOC);

        return $docs->map(function($key) use ($res) {
            $index = array_search($key, array_column($res, 'id'));
            return $res[$index];
        });
    }

    public function info($str)
    {
        echo $str . "\n";
    }

    public function highlight($text, $needle, $tag = 'em', $options = [])
    {
        $hl = new Hihglighter;
        return $hl->highlight($text, $needle, $tag, $options);
    }

    public function snippet($words, $fulltext, $rellength=300, $prevcount=50, $indicator='...')
    {
        $hl = new Hihglighter;
        return $hl->extractRelevant($words, $fulltext, $rellength, $prevcount, $indicator);
    }
}