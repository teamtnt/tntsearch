<?php

namespace TeamTNT\TNTSearch;

use PDO;
use TeamTNT\TNTSearch\Engines\RedisEngine;
use TeamTNT\TNTSearch\Engines\SqliteEngine;
use TeamTNT\TNTSearch\Exceptions\IndexNotFoundException;
use TeamTNT\TNTSearch\Indexer\TNTIndexer;
use TeamTNT\TNTSearch\Stemmer\NoStemmer;
use TeamTNT\TNTSearch\Support\Collection;
use TeamTNT\TNTSearch\Support\Expression;
use TeamTNT\TNTSearch\Support\Highlighter;
use TeamTNT\TNTSearch\Support\Tokenizer;
use TeamTNT\TNTSearch\Support\TokenizerInterface;

class TNTSearch
{
    public $config;
    public $tokenizer = null;
    public $index     = null;
    public $stemmer   = null;
    protected $dbh    = null;
    public $engine;
    /**
     * @param array $config
     *
     * @see https://github.com/teamtnt/tntsearch#examples
     */
    public function loadConfig(array $config)
    {
        $this->config            = $config;
        $this->config['storage'] = rtrim($this->config['storage'], '/') . '/';

        // Check if 'engine' key is set in the config
        if (!isset($this->config['engine'])) {
            $this->config['engine'] = \TeamTNT\TNTSearch\Engines\SqliteEngine::class;
        }

        // Create the engine instance based on the config
        $engine       = $this->config['engine'];
        $this->engine = new $engine;

        $this->engine->loadConfig($config);
    }

    public function __construct()
    {
        $this->tokenizer = new Tokenizer;
    }

    /**
     * @param PDO $dbh
     */
    public function setDatabaseHandle(PDO $dbh)
    {
        $this->dbh = $dbh;
    }

    /**
     * @param string $indexName
     * @param boolean $disableOutput
     *
     * @return TNTIndexer
     */
    public function createIndex($indexName, $disableOutput = false)
    {
        $indexer = new TNTIndexer($this->engine);
        $indexer->loadConfig($this->config);
        $indexer->disableOutput($disableOutput);

        if ($this->dbh) {
            $indexer->setDatabaseHandle($this->dbh);
        }
        return $indexer->createIndex($indexName);
    }

    /**
     * @param string $indexName
     *
     * @throws IndexNotFoundException
     */
    public function selectIndex($indexName)
    {
        $this->engine->selectIndex($indexName);
        $this->setStemmer();
        $this->setTokenizer();
    }

    /**
     * @param string $phrase
     * @param int    $numOfResults
     *
     * @return array
     */
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
        $noLimit   = $this->engine->fuzzy_no_limit;

        foreach ($keywords as $index => $term) {
            $isLastKeyword = ($keywords->count() - 1) == $index;
            $df            = $this->totalMatchingDocuments($term, $isLastKeyword);
            $idf           = log($count / max(1, $df));
            foreach ($this->getAllDocumentsForKeyword($term, $noLimit, $isLastKeyword) as $document) {
                $docID = $document['doc_id'];
                $tf    = $document['hit_count'];
                $num   = ($tfWeight + 1) * $tf;
                $denom = $tfWeight
                     * ((1 - $dlWeight) + $dlWeight)
                     + $tf;
                $score             = $idf * ($num / $denom);
                $docScores[$docID] = isset($docScores[$docID]) ?
                $docScores[$docID] + $score : $score;
            }
        }

        arsort($docScores);

        $docs = new Collection($docScores);

        $totalHits = $docs->count();

        $docs = $docs->map(function ($doc, $key) {
            return $key;
        })->take($numOfResults);

        $stopTimer = microtime(true);

        if ($this->isFileSystemIndex()) {
            return $this->filesystemMapIdsToPaths($docs)->toArray();
        }

        return [
            'ids'            => array_keys($docs->toArray()),
            'hits'           => $totalHits,
            'docScores'      => $docScores,
            'execution_time' => round($stopTimer - $startTimer, 7) * 1000 . " ms"
        ];
    }

    /**
     * @param string $phrase
     * @param int    $numOfResults
     *
     * @return array
     */
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

        $docs = $docs->take($numOfResults);

        $stopTimer = microtime(true);

        if ($this->isFileSystemIndex()) {
            return $this->filesystemMapIdsToPaths($docs)->toArray();
        }

        return [
            'ids'            => $docs->toArray(),
            'hits'           => $docs->count(),
            'execution_time' => round($stopTimer - $startTimer, 7) * 1000 . " ms"
        ];
    }

    /**
     * @param      $keyword
     * @param bool $noLimit
     * @param bool $isLastKeyword
     *
     * @return Collection
     */
    public function getAllDocumentsForKeyword($keyword, $noLimit = false, $isLastKeyword = false)
    {
        $word = $this->getWordlistByKeyword($keyword, $isLastKeyword, $noLimit);
        if (!isset($word[0])) {
            return new Collection([]);
        }
        if ($this->engine->fuzziness) {
            return $this->getAllDocumentsForFuzzyKeyword($word, $noLimit);
        }

        return $this->getAllDocumentsForStrictKeyword($word, $noLimit);
    }

    /**
     * @param      $keyword
     * @param bool $noLimit
     *
     * @return Collection
     */
    public function getAllDocumentsForWhereKeywordNot($keyword, $noLimit = false)
    {
        return $this->engine->getAllDocumentsForWhereKeywordNot($keyword, $noLimit);
    }

    /**
     * @param      $keyword
     * @param bool $isLastWord
     *
     * @return int
     */
    public function totalMatchingDocuments($keyword, $isLastWord = false)
    {
        $occurance = $this->getWordlistByKeyword($keyword, $isLastWord);
        if (isset($occurance[0])) {
            return $occurance[0]['num_docs'];
        }

        return 0;
    }

    /**
     * @param      $keyword
     * @param bool $isLastWord
     *
     * @return array
     */
    public function getWordlistByKeyword($keyword, $isLastWord = false, $noLimit = false)
    {
        return $this->engine->getWordlistByKeyword($keyword, $isLastWord, $noLimit);
    }

    /**
     * @param $keyword
     *
     * @return array
     */
    public function fuzzySearch($keyword)
    {
        return $this->engine->fuzzySearch($keyword);
    }

    public function totalDocumentsInCollection()
    {
        return $this->getValueFromInfoTable('total_documents');
    }

    public function getStemmer()
    {
        return $this->stemmer;
    }

    public function setStemmer()
    {
        $stemmer = $this->getValueFromInfoTable('stemmer');
        if ($stemmer) {
            $this->stemmer = new $stemmer;
        } else {
            $this->stemmer = isset($this->config['stemmer']) ? new $this->config['stemmer'] : new NoStemmer;
        }
    }

    public function setTokenizer()
    {
        $tokenizer = $this->getValueFromInfoTable('tokenizer');
        if ($tokenizer) {
            $this->tokenizer = new $tokenizer;
        } else {
            $this->tokenizer = isset($this->config['tokenizer']) ? new $this->config['tokenizer'] : new Tokenizer;
        }
    }

    /**
     * @return bool
     */
    public function isFileSystemIndex()
    {
        return $this->getValueFromInfoTable('driver') == 'filesystem';
    }

    public function getValueFromInfoTable($value)
    {
        return $this->engine->getValueFromInfoTable($value);
    }

    public function filesystemMapIdsToPaths($docs)
    {
        return $this->engine->filesystemMapIdsToPaths($docs);
    }

    public function info($str)
    {
        echo $str . "\n";
    }

    public function breakIntoTokens($text)
    {
        return $this->tokenizer->tokenize($text);
    }

    /**
     * @param        $text
     * @param        $needle
     * @param string $tag
     * @param array  $options
     *
     * @return string
     */
    public function highlight($text, $needle, $tag = 'em', $options = [])
    {
        $hl = new Highlighter($this->tokenizer);
        return $hl->highlight($text, $needle, $tag, $options);
    }

    public function snippet($words, $fulltext, $rellength = 300, $prevcount = 50, $indicator = '...')
    {
        $hl = new Highlighter($this->tokenizer);
        return $hl->extractRelevant($words, $fulltext, $rellength, $prevcount, $indicator);
    }

    /**
     * @return TNTIndexer
     */
    public function getIndex()
    {
        $indexer = new TNTIndexer($this->engine);
        $indexer->setInMemory(false);
        $indexer->setIndex($this->engine->index);
        $indexer->setStemmer($this->stemmer);
        $indexer->setTokenizer($this->tokenizer);
        return $indexer;
    }

    /**
     * @param $words
     * @param $noLimit
     *
     * @return Collection
     */
    private function getAllDocumentsForFuzzyKeyword($words, $noLimit)
    {
        return $this->engine->getAllDocumentsForFuzzyKeyword($words, $noLimit);
    }

    /**
     * @param $word
     * @param $noLimit
     *
     * @return Collection
     */
    private function getAllDocumentsForStrictKeyword($word, $noLimit)
    {
        return $this->engine->getAllDocumentsForStrictKeyword($word, $noLimit);
    }

    public function asYouType($value)
    {
        $this->engine->asYouType($value);
    }

    public function fuzziness($value)
    {
        $this->engine->fuzziness = $value;
    }

    public function fuzzyNoLimit($value)
    {
        $this->engine->fuzzy_no_limit = $value;
    }

    public function setFuzziness($value)
    {
        $this->engine->fuzziness = $value;
    }

    public function setFuzzyDistance($value)
    {
        $this->engine->fuzzy_distance = $value;
    }

    public function setFuzzyPrefixLength($value)
    {
        $this->engine->fuzzy_prefix_length = $value;
    }

    public function setFuzzyMaxExpansions($value)
    {
        $this->engine->fuzzy_max_expansions = $value;
    }

    public function setFuzzyNoLimit($value)
    {
        $this->engine->fuzzy_no_limit = $value;
    }
    public function setAsYouType($value)
    {
        $this->engine->asYouType = $value;
    }

    public function getFuzziness()
    {
        return $this->engine->fuzziness;
    }

    public function getFuzzyDistance()
    {
        return $this->engine->fuzzy_distance;
    }

    public function getFuzzyPrefixLength()
    {
        return $this->engine->fuzzy_prefix_length;
    }

    public function getFuzzyMaxExpansions()
    {
        return $this->engine->fuzzy_max_expansions;
    }
    public function getFuzzyNoLimit()
    {
        return $this->engine->fuzzy_no_limit;
    }

    public function getAsYouType()
    {
        return $this->engine->asYouType;
    }

}
