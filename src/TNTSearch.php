<?php

namespace TeamTNT\TNTSearch;

use PDO;
use TeamTNT\TNTSearch\Indexer\TNTIndexer;
use TeamTNT\TNTSearch\Stemmer\PorterStemmer;
use TeamTNT\TNTSearch\Support\Collection;
use TeamTNT\TNTSearch\Support\Expression;
use TeamTNT\TNTSearch\Support\Highlighter;
use TeamTNT\TNTSearch\Support\Tokenizer;
use TeamTNT\TNTSearch\Support\TokenizerInterface;

class TNTSearch
{
    public $config;
    public $asYouType = false;
    public $maxDocs   = 500;

    const FILESYSTEM_DRIVER = 'filesystem';

    public function loadConfig($config)
    {
        $this->config            = $config;
        $this->config['storage'] = rtrim($this->config['storage'], '/') . '/';
    }

    public function __construct()
    {
        $this->tokenizer = new Tokenizer;
    }

    public function setTokenizer(TokenizerInterface $tokenizer)
    {
        $this->tokenizer = $tokenizer;
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
        $this->setStemmer();
    }

    public function search($phrase, $numOfResults = 100)
    {
        $startTimer = microtime(true);
        $keywords   = $this->breakIntoTokens($phrase);
        $keywords   = new Collection($keywords);

        $keywords = $keywords->map(function ($keyword) {
            return $this->stemmer->stem($keyword);
        });

        $tfWeight  = 1;
        $dlWeight  = 0.5;
        $docScores = [];
        $count     = $this->totalDocumentsInCollection();

        foreach ($keywords as $index => $term) {
            $isLastKeyword = ($keywords->count() - 1) == $index;
            $df            = $this->totalMatchingDocuments($term, $isLastKeyword);
            foreach ($this->getAllDocumentsForKeyword($term, false, $isLastKeyword) as $document) {
                $docID     = $document['doc_id'];
                $tf        = $document['hit_count'];
                $idf       = log($count / $df);
                $num       = ($tfWeight + 1) * $tf;
                $denom     = $tfWeight
                     * ((1 - $dlWeight) + $dlWeight)
                     + $tf;
                $score             = $idf * ($num / $denom);
                $docScores[$docID] = isset($docScores[$docID]) ?
                $docScores[$docID] + $score : $score;
            }
        }

        arsort($docScores);

        $docs = new Collection($docScores);

        $counter = 0;
        $docs    = $docs->map(function ($doc, $key) {
            return $key;
        })->filter(function ($item) use (&$counter, $numOfResults) {
            $counter++;
            if ($counter <= $numOfResults) {
                return $item;
            }

        });
        $stopTimer = microtime(true);

        if ($this->isFileSystemIndex()) {
            return $this->filesystemMapIdsToPaths($docs)->toArray();
        }
        return [
            'ids'            => array_keys($docs->toArray()),
            'execution time' => round($stopTimer - $startTimer, 7) * 1000 . " ms",
        ];
    }

    public function searchBoolean($phrase, $numOfResults = 100)
    {
        $stack      = [];
        $startTimer = microtime(true);

        $expression = new Expression;
        $postfix    = $expression->toPostfix("|" . $phrase);

        foreach ($postfix as $token) {
            if ($token == '&') {
                $left  = array_pop($stack);
                $right = array_pop($stack);
                if (is_string($left)) {
                    $left = $this->getAllDocumentsForKeyword($this->stemmer->stem($left), true)
                        ->pluck('doc_id');
                }
                if (is_string($right)) {
                    $right = $this->getAllDocumentsForKeyword($this->stemmer->stem($right), true)
                        ->pluck('doc_id');
                }
                if (is_null($left)) {
                    $left = [];
                }

                if (is_null($right)) {
                    $right = [];
                }
                $stack[] = array_values(array_intersect($left, $right));
            } else
            if ($token == '|') {
                $left  = array_pop($stack);
                $right = array_pop($stack);

                if (is_string($left)) {
                    $left = $this->getAllDocumentsForKeyword($this->stemmer->stem($left), true)
                        ->pluck('doc_id');
                }
                if (is_string($right)) {
                    $right = $this->getAllDocumentsForKeyword($this->stemmer->stem($right), true)
                        ->pluck('doc_id');
                }
                if (is_null($left)) {
                    $left = [];
                }

                if (is_null($right)) {
                    $right = [];
                }
                $stack[] = array_unique(array_merge($left, $right));
            } else
            if ($token == '~') {
                $left = array_pop($stack);
                if (is_string($left)) {
                    $left = $this->getAllDocumentsForWhereKeywordNot($this->stemmer->stem($left), true)
                        ->pluck('doc_id');
                }
                if (is_null($left)) {
                    $left = [];
                }
                $stack[] = $left;
            } else {
                $stack[] = $token;
            }
        }
        if (count($stack)) {
            $docs = new Collection($stack[0]);
        } else {
            $docs = new Collection;
        }

        $counter = 0;
        $docs    = $docs->filter(function ($item) use (&$counter, $numOfResults) {
            $counter++;
            if ($counter <= $numOfResults) {
                return $item;
            }
        });

        $stopTimer = microtime(true);

        if ($this->isFileSystemIndex()) {
            return $this->filesystemMapIdsToPaths($docs)->toArray();
        }

        return [
            'ids'            => $docs->toArray(),
            'execution time' => round($stopTimer - $startTimer, 7) * 1000 . " ms",
        ];
    }

    public function getAllDocumentsForKeyword($keyword, $noLimit = false, $isLastKeyword = false)
    {
        $word = $this->getWordlistByKeyword($keyword, $isLastKeyword);
        if (!isset($word[0])) {
            return new Collection([]);
        }
        $query = "SELECT * FROM doclist WHERE term_id = :id ORDER BY hit_count DESC LIMIT {$this->maxDocs}";
        if ($noLimit) {
            $query = "SELECT * FROM doclist WHERE term_id = :id ORDER BY hit_count DESC";
        }
        $stmtDoc = $this->index->prepare($query);

        $stmtDoc->bindValue(':id', $word[0]['id'], SQLITE3_INTEGER);
        $stmtDoc->execute();
        return new Collection($stmtDoc->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getAllDocumentsForWhereKeywordNot($keyword, $noLimit = false)
    {
        $word = $this->getWordlistByKeyword($keyword);
        if (!isset($word[0])) {
            return new Collection([]);
        }
        $query = "SELECT * FROM doclist WHERE doc_id NOT IN (SELECT doc_id FROM doclist WHERE term_id = :id) GROUP BY doc_id ORDER BY hit_count DESC LIMIT {$this->maxDocs}";
        if ($noLimit) {
            $query = "SELECT * FROM doclist WHERE doc_id NOT IN (SELECT doc_id FROM doclist WHERE term_id = :id) GROUP BY doc_id ORDER BY hit_count DESC";
        }
        $stmtDoc = $this->index->prepare($query);

        $stmtDoc->bindValue(':id', $word[0]['id'], SQLITE3_INTEGER);
        $stmtDoc->execute();
        return new Collection($stmtDoc->fetchAll(PDO::FETCH_ASSOC));
    }

    public function totalMatchingDocuments($keyword, $isLastWord = false)
    {
        $occurance = $this->getWordlistByKeyword($keyword, $isLastWord);
        if (isset($occurance[0])) {
            return $occurance[0]['num_docs'];
        }

        return 0;
    }

    public function getWordlistByKeyword($keyword, $isLastWord = false)
    {
        $searchWordlist = "SELECT * FROM wordlist WHERE term like :keyword LIMIT 1";
        $stmtWord       = $this->index->prepare($searchWordlist);

        if ($this->asYouType && $isLastWord) {
            $searchWordlist = "SELECT * FROM wordlist WHERE term like :keyword ORDER BY length(term) ASC, num_hits DESC LIMIT 1";
            $stmtWord       = $this->index->prepare($searchWordlist);
            $stmtWord->bindValue(':keyword', strtolower($keyword) . "%", SQLITE3_TEXT);
        } else {
            $stmtWord->bindValue(':keyword', strtolower($keyword), SQLITE3_TEXT);
        }
        $stmtWord->execute();
        return $stmtWord->fetchAll(PDO::FETCH_ASSOC);
    }

    public function totalDocumentsInCollection()
    {
        $query = "SELECT * FROM info WHERE key = 'total_documents'";
        $docs  = $this->index->query($query);

        return $docs->fetch(PDO::FETCH_ASSOC)['value'];
    }

    public function setStemmer()
    {
        $query = "SELECT * FROM info WHERE key = 'stemmer'";
        $docs  = $this->index->query($query);
        if ($language = $docs->fetch(PDO::FETCH_ASSOC)['value']) {
            $class         = 'TeamTNT\\TNTSearch\\Stemmer\\' . ucfirst(strtolower($language)) . 'Stemmer';
            $this->stemmer = new $class;
        } else {
            $this->stemmer = new PorterStemmer;
        }
    }

    public function isFileSystemIndex()
    {
        $query = "SELECT * FROM info WHERE key = 'driver'";
        $docs  = $this->index->query($query);

        return $docs->fetch(PDO::FETCH_ASSOC)['value'] == self::FILESYSTEM_DRIVER;
    }

    public function filesystemMapIdsToPaths($docs)
    {
        $query = "SELECT * FROM filemap WHERE id in (" . $docs->implode(', ') . ");";
        $res   = $this->index->query($query)->fetchAll(PDO::FETCH_ASSOC);

        return $docs->map(function ($key) use ($res) {
            $index = array_search($key, array_column($res, 'id'));
            return $res[$index];
        });
    }

    public function info($str)
    {
        echo $str . "\n";
    }

    public function breakIntoTokens($text)
    {
        return $this->tokenizer->tokenize($text);
    }

    public function highlight($text, $needle, $tag = 'em', $options = [])
    {
        $hl = new Highlighter;
        return $hl->highlight($text, $needle, $tag, $options);
    }

    public function snippet($words, $fulltext, $rellength = 300, $prevcount = 50, $indicator = '...')
    {
        $hl = new Highlighter;
        return $hl->extractRelevant($words, $fulltext, $rellength, $prevcount, $indicator);
    }

    public function getIndex()
    {
        $indexer           = new TNTIndexer;
        $indexer->inMemory = false;
        $indexer->setIndex($this->index);
        $indexer->setStemmer($this->stemmer);
        return $indexer;
    }
}
