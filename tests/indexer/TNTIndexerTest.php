<?php

use TeamTNT\TNTSearch\Engines\RedisEngine;
use TeamTNT\TNTSearch\Engines\SqliteEngine;
use TeamTNT\TNTSearch\Indexer\TNTIndexer;
use TeamTNT\TNTSearch\Support\AbstractTokenizer;
use TeamTNT\TNTSearch\Support\TokenizerInterface;
use TeamTNT\TNTSearch\TNTSearch;

class TNTIndexerTest extends PHPUnit\Framework\TestCase
{
    public $index = null;

    protected $indexName = "testIndex";
    protected $config    = [
        'driver'     => 'sqlite',
        'engine'     => 'TeamTNT\TNTSearch\Engines\RedisEngine',
        'redis_host' => '127.0.0.1',
        'redis_port' => '6379',
        'database'   => __DIR__ . '/../_files/articles.sqlite',
        'host'       => 'localhost',
        'username'   => 'testUser',
        'password'   => 'testPass',
        'storage'    => __DIR__ . '/../_files/',
        'tokenizer'  => TeamTNT\TNTSearch\Support\ProductTokenizer::class

    ];

    public function testSearch()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer                = $tnt->createIndex($this->indexName);
        $indexer->disableOutput = true;
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);
        $tnt->asYouType(true);
        $res = $tnt->search('Juliet');

        //the most relevant doc has the id 9
        $this->assertEquals("9", $res['ids'][0]);

        $res = $tnt->search('Queen Mab');
        $this->assertEquals([7], $res['ids']);
    }

    public function testIndexFromFileSystem()
    {
        $config = [
            'driver'     => 'filesystem',
            'engine'     => 'TeamTNT\TNTSearch\Engines\RedisEngine',
            'redis_host' => '127.0.0.1',
            'redis_port' => '6379',
            'storage'    => __DIR__ . '/../_files/',
            'location'   => __DIR__ . '/../_files/articles/',
            'extension'  => 'txt'
        ];

        $tnt = new TNTSearch;
        $tnt->loadConfig($config);
        $indexer                = $tnt->createIndex($this->indexName);
        $indexer->disableOutput = true;
        $indexer->run();

        $tnt->selectIndex($this->indexName);

        $index = $tnt->getIndex();
        $count = $index->countWordInWordList('document');

        $this->assertTrue($count == 3, 'Word document should be 3');
        $this->assertEquals('TeamTNT\TNTSearch\Stemmer\NoStemmer', get_class($tnt->getStemmer()));
    }

    public function testIfCroatianStemmerIsSet()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer = $tnt->createIndex($this->indexName);
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->setLanguage('croatian');
        $indexer->disableOutput = true;
        $indexer->run();

        $value = $indexer->getValueFromInfoTable('stemmer');
        $this->assertEquals('TeamTNT\TNTSearch\Stemmer\CroatianStemmer', $value);

        $tnt->selectIndex($this->indexName);
        $this->assertEquals('TeamTNT\TNTSearch\Stemmer\CroatianStemmer', get_class($tnt->getStemmer()));
    }

    public function testIfGermanStemmerIsSet()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer = $tnt->createIndex($this->indexName);
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->setLanguage('german');
        $indexer->disableOutput = true;
        $indexer->run();

        $value = $indexer->getValueFromInfoTable('stemmer');

        $this->assertEquals('TeamTNT\TNTSearch\Stemmer\GermanStemmer', $value);

        $tnt->selectIndex($this->indexName);
        $this->assertEquals('TeamTNT\TNTSearch\Stemmer\GermanStemmer', get_class($tnt->getStemmer()));
    }

    public function testBuildTrigrams()
    {
        $engine   = new RedisEngine;
        $indexer  = new TNTIndexer($engine);
        $trigrams = $indexer->buildTrigrams('created');
        $this->assertEquals('__c _cr cre rea eat ate ted ed_ d__', $trigrams);

        $trigrams = $indexer->buildTrigrams('mood');
        $this->assertEquals('__m _mo moo ood od_ d__', $trigrams);

        $trigrams = $indexer->buildTrigrams('death');
        $this->assertEquals('__d _de dea eat ath th_ h__', $trigrams);

        $trigrams = $indexer->buildTrigrams('behind');
        $this->assertEquals('__b _be beh ehi hin ind nd_ d__', $trigrams);

        $trigrams = $indexer->buildTrigrams('usually');
        $this->assertEquals('__u _us usu sua ual all lly ly_ y__', $trigrams);

        $trigrams = $indexer->buildTrigrams('created');
        $this->assertEquals('__c _cr cre rea eat ate ted ed_ d__', $trigrams);

    }

    public function tearDown(): void
    {
        if (file_exists(__DIR__ . '/../_files/' . $this->indexName)) {
            unlink(__DIR__ . '/../_files/' . $this->indexName);
        }
    }

    public function testSetTokenizer()
    {

        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer = $tnt->createIndex($this->indexName);
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->setTokenizer(new SomeTokenizer);
        $indexer->disableOutput = true;
        $indexer->run();

        $this->assertInstanceOf(TokenizerInterface::class, $indexer->tokenizer);

        $res = $indexer->breakIntoTokens('Canon 70-200');
        $this->assertContains("canon", $res);
        $this->assertContains("70-200", $res);
    }

    public function testCustomPrimaryKey()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer = $tnt->createIndex($this->indexName);
        $indexer->setPrimaryKey('post_id');
        $indexer->disableOutput = true;
        $indexer->query('SELECT * FROM posts;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);
        $res = $tnt->search('second');

        //the most relevant doc has the id 9
        $this->assertEquals("2", $res['ids'][0]);
    }
}

class SomeTokenizer extends AbstractTokenizer implements TokenizerInterface
{
    protected static $pattern = '/[\s,\.]+/';

    public function tokenize($text, $stopwords = [])
    {
        return preg_split($this->getPattern(), mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
    }
}
