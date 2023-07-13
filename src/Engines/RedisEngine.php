<?php

namespace TeamTNT\TNTSearch\Engines;

use Exception;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Redis;
use TeamTNT\TNTSearch\Contracts\EngineContract;
use TeamTNT\TNTSearch\Exceptions\IndexNotFoundException;
use TeamTNT\TNTSearch\FileReaders\TextFileReader;
use TeamTNT\TNTSearch\Stemmer\CroatianStemmer;
use TeamTNT\TNTSearch\Stemmer\NoStemmer;
use TeamTNT\TNTSearch\Support\Collection;
use TeamTNT\TNTSearch\Support\Tokenizer;
use TeamTNT\TNTSearch\Support\TokenizerInterface;

class RedisEngine implements EngineContract
{
    use EngineTrait;

    public $indexName;
    public $config;
    public $index;
    public $stemmer;
    public $dbh;
    public $query;
    public $disableOutput = false;
    public $primaryKey;
    protected $excludePrimaryKey = true;
    public $decodeHTMLEntities;
    public $tokenizer;
    public $stopWords          = [];
    public $statementsPrepared = false;
    protected $updateInfoTableStmt;
    protected $insertWordlistStmt;
    protected $selectWordlistStmt;
    protected $updateWordlistStmt;
    public $steps                = 1000;
    public $inMemory             = true;
    protected $inMemoryTerms     = [];
    public $filereader           = null;
    public $asYouType            = false;
    public $fuzziness            = false;
    public $fuzzy_prefix_length  = 2;
    public $fuzzy_max_expansions = 50;
    public $fuzzy_distance       = 2;
    public $fuzzy_no_limit       = false;
    public $maxDocs              = 500;
    public $redis;

    public function loadConfig(array $config)
    {
        $this->config = $config;
        $this->redis  = new Redis();
        $this->redis->connect('127.0.0.1', 6379);
    }

    public function createIndex($indexName)
    {
        $this->redis->flushAll();

        $this->indexName = $indexName;

        if (isset($this->config['stemmer'])) {
            $this->setStemmer(new $this->config['stemmer']);
        }

        if (isset($this->config['tokenizer'])) {
            $this->setTokenizer(new $this->config['tokenizer']);
        }

        if (!$this->dbh) {
            $connector = $this->createConnector($this->config);
            $this->dbh = $connector->connect($this->config);
        }

        return $this;
    }

    public function updateInfoTable($key, $value)
    {
        $redisKey = $this->indexName . ':info';
        $this->redis->hset($redisKey, $key, $value);
    }

    public function getValueFromInfoTable($value)
    {
        $redisKey = $this->indexName . ':info';
        $ret      = $this->redis->hget($redisKey, $value);

        return $ret ?? null;
    }

    public function run()
    {
        if ($this->config['driver'] == "filesystem") {
            return $this->readDocumentsFromFileSystem();
        }
        $result = $this->dbh->query($this->query);

        $counter = 0;
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $counter++;

            $this->processDocument(new Collection($row));

            if ($counter % $this->steps == 0) {
                $this->info("Processed $counter rows");
            }
        }

        $this->updateInfoTable('total_documents', $counter);

        $this->info("Total rows $counter");
    }

    public function processDocument($row)
    {
        $documentId = $row->get($this->getPrimaryKey());

        if ($this->excludePrimaryKey) {
            $row->forget($this->getPrimaryKey());
        }

        $stems = $row->map(function ($columnContent, $columnName) use ($row) {
            return $this->stemText($columnContent);
        });

        $this->saveToIndex($stems, $documentId);
    }

    public function saveToIndex($stems, $docId)
    {
        $terms = $this->saveWordlist($stems);
        $this->saveDoclist($terms, $docId);
        $this->saveHitList($stems, $docId, $terms);
    }

    public function selectIndex($indexName)
    {
        $this->indexName = $indexName;
    }

    public function saveWordlist($stems)
    {
        $terms = [];

        $stems->map(function ($column, $key) use (&$terms) {
            foreach ($column as $term) {
                if (array_key_exists($term, $terms)) {
                    $terms[$term]['num_hits']++;
                    $terms[$term]['num_docs'] = 1;
                } else {
                    $terms[$term] = [
                        'num_hits' => 1,
                        'num_docs' => 1
                    ];
                }
            }
        });

        foreach ($terms as $key => $term) {
            // Check if the term already exists in Redis
            $redisKey = $this->indexName . ':wordlist:' . $key;
            if ($this->redis->exists($redisKey)) {
                // Term already exists, retrieve existing hits and docs values
                $existingHits = $this->redis->hget($redisKey, 'num_hits');
                $existingDocs = $this->redis->hget($redisKey, 'num_docs');

                // Increment hits and docs values
                $updatedHits = $existingHits + $term['num_hits'];
                $updatedDocs = $existingDocs + $term['num_docs'];

                // Update hits and docs values in Redis
                $this->redis->hset($redisKey, 'num_hits', $updatedHits);
                $this->redis->hset($redisKey, 'num_docs', $updatedDocs);

            } else {

                // Term doesn't exist, store initial hits and docs values in Redis
                $this->redis->hset($redisKey, 'num_hits', $term['num_hits']);
                $this->redis->hset($redisKey, 'num_docs', $term['num_docs']);
            }
        }

        return $terms;
    }

    public function saveDoclist($terms, $docId)
    {
        foreach ($terms as $key => $term) {
            $redisKey = $this->indexName . ':doclist:' . $key;

            $this->redis->hset($redisKey, $docId, $term['num_hits']);
        }
    }

    public function saveHitList()
    {
        return;
    }

    public function getWordlistByKeyword($keyword, $isLastWord = false, $noLimit = false)
    {
        $redisKey = $this->indexName . ':wordlist:' . $keyword;

        if ($this->asYouType && $isLastWord) {
            // Perform custom sorting for as-you-type queries
            $wordlistKeys = $this->redis->keys($this->indexName . ':wordlist:' . $keyword . '*');
            $wordlistKeys = array_filter($wordlistKeys, function ($key) {
                return $this->redis->exists($key);
            });

            if (!empty($wordlistKeys)) {
                // Sort the wordlist keys based on length and hits
                usort($wordlistKeys, function ($a, $b) {
                    $lengthA = strlen($this->redis->hget($a, 'term'));
                    $lengthB = strlen($this->redis->hget($b, 'term'));
                    $hitsA   = $this->redis->hget($a, 'num_hits');
                    $hitsB   = $this->redis->hget($b, 'num_hits');
                    if ($lengthA == $lengthB) {
                        return $hitsB <=> $hitsA;
                    }
                    return $lengthA <=> $lengthB;
                });

                // Retrieve the wordlist entry with the highest hits
                $res = $this->redis->hgetall($wordlistKeys[0]);
                return [array_merge($res, [
                    'id'   => $this->redis->hget($wordlistKeys[0], 'id'),
                    'term' => $keyword
                ])];
            }
        } else {
            $res = $this->redis->hgetall($redisKey);
            if (!empty($res)) {
                return [array_merge($res, [
                    'id'   => $this->redis->hget($redisKey, 'id'),
                    'term' => $keyword
                ])];
            }
        }

        if ($this->fuzziness && (!$res || $noLimit)) {
            return $this->fuzzySearch($keyword);
        }

        return [];
    }

    public function getAllDocumentsForStrictKeyword($word, $noLimit)
    {
        $redisKey = $this->indexName . ':doclist:' . $word[0]['term'];

        // Get all document IDs from the hash field
        $docIds = $this->redis->hkeys($redisKey);

        // Sort the document IDs if needed
        if (!$noLimit) {
            sort($docIds);
        }

        $documents = [];

        foreach ($docIds as $docId) {
            $document = [
                'term_id'   => $word[0]['term'],
                'doc_id'    => $docId,
                'hit_count' => $this->redis->hget($redisKey, $docId)
            ];

            $documents[] = $document;
        }

        return new Collection($documents);
    }

    public function delete($documentId)
    {
        // Fetch the terms associated with the given document ID from doclist
        $doclistKey   = $this->indexName . ':doclist:*';
        $doclistTerms = $this->redis->keys($doclistKey);

        // Track the wordlist keys to be updated and the hits count per term
        $wordlistKeysToUpdate = [];
        $termsHitsDeleted     = [];

        // Track if any document ID was found and deleted
        $documentDeleted = false;

        // Remove the document ID from the associated terms in doclist
        foreach ($doclistTerms as $keyName) {
            if ($this->redis->hexists($keyName, $documentId)) {
                // Remove the document ID from the hash
                $hits = $this->redis->hget($keyName, $documentId);
                $this->redis->hdel($keyName, $documentId);

                // Add the wordlist key to the update list
                $wordlistKeysToUpdate[] = str_replace('doclist:', 'wordlist:', $keyName);

                // Track the hits deleted per term
                $termKey = str_replace([$this->indexName . ':doclist:', ':' . $documentId], '', $keyName);
                if (!isset($termsHitsDeleted[$termKey])) {
                    $termsHitsDeleted[$termKey] = $hits;
                } else {
                    $termsHitsDeleted[$termKey] += $hits;
                }

                // Set the flag indicating that a document was deleted
                $documentDeleted = true;
            }
        }

        // If no document was found and deleted, return early
        if (!$documentDeleted) {
            return;
        }

        // Update the document count and hits count in the wordlist keys
        foreach ($wordlistKeysToUpdate as $wordlistKey) {
            $termKey = str_replace($this->indexName . ':wordlist:', '', $wordlistKey);

            $this->redis->hincrby($wordlistKey, 'num_docs', -1);
            $this->redis->hincrby($wordlistKey, 'num_hits', -$termsHitsDeleted[$termKey]);

            $docsCount = $this->redis->hget($wordlistKey, 'num_docs');

            if ($docsCount == 0) {
                $this->redis->del($wordlistKey);
            }
        }

        // Update the total_documents key in the info table
        $totalDocumentsKey = $this->indexName . ':info';
        $this->redis->hincrby($totalDocumentsKey, 'total_documents', -1);
    }

    /**
     * @return int
     */
    public function totalDocumentsInCollection()
    {
        return $this->getValueFromInfoTable('total_documents');
    }

    public function getWordFromWordList($word)
    {
        $redisKey = $this->indexName . ':wordlist:' . $word;
        $result   = $this->redis->hgetall($redisKey);

        if (!empty($result)) {
            return [
                'id'       => $word,
                'term'     => $word,
                'num_hits' => $result['num_hits'],
                'num_docs' => $result['num_docs']
            ];
        }

        return null;
    }

}
