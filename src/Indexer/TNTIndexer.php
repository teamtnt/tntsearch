<?php

namespace TeamTNT\TNTSearch\Indexer;

use PDO;
use TeamTNT\TNTSearch\Contracts\EngineContract;
use TeamTNT\TNTSearch\FileReaders\FileReaderInterface;
use TeamTNT\TNTSearch\FileReaders\TextFileReader;
use TeamTNT\TNTSearch\Stemmer\NoStemmer;
use TeamTNT\TNTSearch\Stemmer\Stemmer;
use TeamTNT\TNTSearch\Support\Collection;
use TeamTNT\TNTSearch\Support\Tokenizer;
use TeamTNT\TNTSearch\Support\TokenizerInterface;

class TNTIndexer
{
    protected $engine = null;
    protected $dbh = null;
    protected $primaryKey = null;
    public $stemmer = null;
    public $tokenizer = null;
    public $stopWords = [];
    public $config = [];
    protected $query = "";
    protected $wordlist = [];
    protected $decodeHTMLEntities = false;

    public $indexName = "";

    public function __construct(EngineContract $engine)
    {
        $this->engine = $engine;
        $this->engine->tokenizer = new Tokenizer;
        $this->engine->stemmer = new NoStemmer;
        $this->engine->filereader = new TextFileReader;
    }

    /**
     * @param TokenizerInterface $tokenizer
     */
    public function setTokenizer(TokenizerInterface $tokenizer)
    {
        $this->engine->setTokenizer($tokenizer);

    }

    public function setStopWords(array $stopWords)
    {
        $this->engine->setStopWords($stopWords);
    }

    /**
     * @param array $config
     */
    public function loadConfig(array $config)
    {
        $this->engine->loadConfig($config);
    }

    public function getStemmer()
    {
        return $this->engine->getStemmer();
    }

    /**
     * @return string
     */
    public function getPrimaryKey()
    {
        return $this->engine->getPrimaryKey();
    }

    /**
     * @param string $primaryKey
     */
    public function setPrimaryKey(string $primaryKey)
    {
        $this->engine->setPrimaryKey($primaryKey);
    }

    public function excludePrimaryKey()
    {
        $this->engine->excludePrimaryKey();
    }

    public function includePrimaryKey()
    {
        $this->engine->includePrimaryKey();
    }

    public function setStemmer(Stemmer $stemmer)
    {
        $this->engine->setStemmer($stemmer);
    }

    /**
     * @param string $language - one of: no, arabic, croatian, german, italian, porter, portuguese, russian, ukrainian
     */
    public function setLanguage(string $language = 'no')
    {
        $this->engine->setLanguage($language);
    }

    /**
     * @param PDO $index
     */
    public function setIndex(PDO $index)
    {
        $this->engine->setIndex($index);
    }

    public function setFileReader(FileReaderInterface $filereader)
    {
        $this->engine->filereader = $filereader;
    }

    public function createIndex(string $indexName)
    {
        return $this->engine->createIndex($indexName);
    }

    /**
     * @param PDO $dbh
     */
    public function setDatabaseHandle(PDO $dbh)
    {
        $this->dbh = $dbh;
        if ($this->dbh->getAttribute(PDO::ATTR_DRIVER_NAME) == 'mysql') {
            $this->dbh->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        }
    }

    public function query(string $query)
    {
        $this->engine->query = $query;
    }

    public function run()
    {
        $this->engine->run();
    }

    public function processDocument(Collection $row)
    {
        $this->engine->processDocument($row);
    }

    public function insert(array $document)
    {
        $this->engine->insert($document);
    }

    public function update(int $id, array $document)
    {
        $this->engine->update($id, $document);
    }

    public function delete(int $documentId)
    {
        $this->engine->delete($documentId);
    }

    public function breakIntoTokens(string $text)
    {
        return $this->engine->breakIntoTokens($text);
    }

    public function decodeHtmlEntities(bool $value = true)
    {
        $this->engine->decodeHTMLEntities = $value;
    }

    public function saveToIndex(Collection $stems, int $docId)
    {
        $this->engine->saveToIndex($stems, $docId);
    }

    /**
     * @param Collection $stems
     *
     * @return array
     */
    public function saveWordlist(Collection $stems)
    {
        return $this->engine->saveWordlist($stems);
    }

    public function saveDoclist(array $terms, int $docId)
    {
        $this->engine->saveDoclist($terms, $docId);
    }

    public function saveHitList(array $stems, int $docId, array $termsList)
    {
        $this->engine->saveHitList($stems, $docId, $termsList);
    }

    public function getWordFromWordList($word)
    {
        return $this->engine->getWordFromWordList($word);
    }

    /**
     * @param $word
     *
     * @return int
     */
    public function countWordInWordList($word)
    {
        return $this->engine->countWordInWordList($word);
    }

    /**
     * @param $word
     *
     * @return int
     */
    public function countDocHitsInWordList($word)
    {
        $res = $this->engine->getWordFromWordList($word);

        if ($res) {
            return $res['num_docs'];
        }
        return 0;
    }

    public function buildDictionary($filename, $count = -1, $hits = true, $docs = false)
    {
        $this->engine->buildDictionary($filename, $count, $hits, $docs);
    }

    /**
     * @return int
     */
    public function totalDocumentsInCollection()
    {
        return $this->engine->totalDocumentsInCollection();
    }

    /**
     * @param $keyword
     *
     * @return string
     */
    public function buildTrigrams($keyword)
    {
        $t = "__" . $keyword . "__";
        $trigrams = "";
        for ($i = 0; $i < strlen($t) - 2; $i++) {
            $trigrams .= mb_substr($t, $i, 3) . " ";
        }

        return trim($trigrams);
    }

    public function info($text)
    {
        $this->engine->info();
    }

    public function setInMemory($value)
    {
        $this->engine->setInMemory($value);
    }

    public function disableOutput($value)
    {
        $this->engine->disableOutput($value);
    }
}
