<?php

use TeamTNT\TNTSearch\Engines\RedisEngine;
use TeamTNT\TNTSearch\Engines\SqliteEngine;
use TeamTNT\TNTSearch\Exceptions\IndexNotFoundException;
use TeamTNT\TNTSearch\TNTSearch;

class TNTSearchTest extends PHPUnit\Framework\TestCase
{
    protected $indexName = "testIndex";

    protected $config = [
        'driver'     => 'sqlite',
        'engine'     => 'TeamTNT\TNTSearch\Engines\RedisEngine',
        'redis_host' => '127.0.0.1',
        'redis_port' => '6379',
        'database'   => __DIR__ . '/_files/articles.sqlite',
        'host'       => 'localhost',
        'username'   => 'testUser',
        'password'   => 'testPass',
        'storage'    => __DIR__ . '/_files/',
        'stemmer'    => \TeamTNT\TNTSearch\Stemmer\PorterStemmer::class
    ];

    public function testLoadConfig()
    {
        $tnt = new TNTSearch();
        $tnt->loadConfig($this->config);
        $this->assertArrayHasKey('driver', $tnt->config);
        $this->assertArrayHasKey('database', $tnt->config);
        $this->assertArrayHasKey('host', $tnt->config);
        $this->assertArrayHasKey('username', $tnt->config);
        $this->assertArrayHasKey('password', $tnt->config);
        $this->assertArrayHasKey('storage', $tnt->config);
        $this->assertArrayHasKey('stemmer', $tnt->config);
    }

    public function testCreateIndex()
    {
        $tnt = new TNTSearch();
        $tnt->loadConfig($this->config);
        $indexer = $tnt->createIndex($this->indexName);
        $this->assertInstanceOf('TeamTNT\TNTSearch\Contracts\EngineContract', $indexer);

        if ($this->config['engine'] == 'TeamTNT\TNTSearch\Engines\SqliteEngine') {
            $this->assertFileExists($indexer->getStoragePath() . $this->indexName);
        }

    }

    public function testSearchBoolean()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer                = $tnt->createIndex($this->indexName);
        $indexer->disableOutput = true;
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);
        $res = $tnt->searchBoolean('romeo juliet queen');
        $this->assertEquals([7], $res['ids']);

        $res = $tnt->searchBoolean('Hamlet or Macbeth');

        $this->assertContains("3", $res['ids']);
        $this->assertContains("4", $res['ids']);
        $this->assertContains("1", $res['ids']);
        $this->assertContains("2", $res['ids']);
        $this->assertEquals(4, $res['hits']);

        $res = $tnt->searchBoolean('juliet ~well');

        $this->assertCount(5, $res['ids']);
        $this->assertContains("5", $res['ids']);
        $this->assertContains("6", $res['ids']);
        $this->assertContains("7", $res['ids']);
        $this->assertContains("8", $res['ids']);
        $this->assertContains("10", $res['ids']);

        $res = $tnt->searchBoolean('juliet ~romeo');
        $this->assertEquals([10], $res['ids']);

        $res = $tnt->searchBoolean('hamlet ~king');
        $this->assertEquals([2], $res['ids']);

        $res = $tnt->searchBoolean('hamlet superman');
        $this->assertEquals([], $res['ids']);

        $res = $tnt->searchBoolean('hamlet or superman');
        $this->assertEquals([1, 2], $res['ids']);

        $res = $tnt->searchBoolean('hamlet');
        $this->assertEquals([1, 2], $res['ids']);

        $res = $tnt->searchBoolean('eldred ~bar');
        $this->assertEquals([11], $res['ids']);

        $res = $tnt->searchBoolean('Eldred ~bar');
        $this->assertEquals([11], $res['ids']);
    }

    /**
     * https://github.com/teamtnt/tntsearch/issues/60
     */
    public function testTotalDocumentCountOnIndexUpdate()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer = $tnt->createIndex($this->indexName);
        $indexer->disableOutput(true);
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);
        $this->assertEquals(12, $tnt->totalDocumentsInCollection());

        $index = $tnt->getIndex();
        //first we test if the total number of documents will decrease
        $index->delete(12);
        $this->assertEquals(11, $tnt->totalDocumentsInCollection());

        //now we try with a document that does not exist, the total number should increase for 1
        $index->update(1234, ['id' => '1234', 'title' => 'updated title', 'article' => 'updated article']);

        $this->assertEquals(12, $tnt->totalDocumentsInCollection());
    }

    public function testPrimaryKeyIncludedInResult()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer                = $tnt->createIndex($this->indexName);
        $indexer->disableOutput = true;
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->includePrimaryKey();
        $indexer->run();

        $tnt->selectIndex($this->indexName);
        $res = $tnt->search(3);
        $this->assertEquals([3], $res['ids']);

    }

    public function testPrimaryKeyNotIncludedInResult()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer = $tnt->createIndex($this->indexName);
        $indexer->disableOutput(true);
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);
        $res = $tnt->search(3);
        $this->assertEquals([], $res['ids']);
    }

    public function testIndexUpdate()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer                = $tnt->createIndex($this->indexName);
        $indexer->disableOutput = true;
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);

        $index = $tnt->getIndex();
        $count = $index->countWordInWordList('titl');

        $this->assertTrue($count == 0, 'Word titl should be 0');
        $index->insert(['id' => '11', 'title' => 'new title', 'article' => 'new article']);

        $count = $index->countWordInWordList('titl');
        $this->assertEquals(1, $count, 'Word titl should be 1');

        $docCount = $index->countDocHitsInWordList('juliet');
        $this->assertEquals(6, $docCount, 'Juliet should occur in 6 documents');

        $index->insert(['id' => '12', 'title' => 'juliet', 'article' => 'new article about juliet']);
        $count = $index->countWordInWordList('juliet');
        $this->assertEquals(9, $count, 'Word juliet should be 9');

        $docCount = $index->countDocHitsInWordList('juliet');
        $this->assertEquals(7, $docCount, 'Juliet should occur in 7 documents');

        $index->delete(12);
        $count = $index->countWordInWordList('juliet');
        $this->assertEquals(7, $count, 'Word juliet should be 7 after delete');

        $docCount = $index->countDocHitsInWordList('juliet');
        $this->assertEquals(6, $docCount, 'Juliet should occur in 6 documents after delete');

        $count = $index->countWordInWordList('romeo');
        $this->assertEquals(5, $count, 'Word romeo should be 5');

        $index->update(11, ['id' => '11', 'title' => 'romeo', 'article' => 'new article about romeo']);

        $count = $index->countWordInWordList('romeo');
        $this->assertEquals(7, $count, 'Word romeo should be 7');

    }

    public function testMultipleSearch()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer = $tnt->createIndex($this->indexName);
        $indexer->disableOutput(true);
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);

        $res = $tnt->search('Othello');
        $this->assertEmpty($res['ids']);
        $this->assertEquals(12, $tnt->totalDocumentsInCollection());

        $index = $tnt->getIndex();

        $count = $index->countWordInWordList('Othello');
        $this->assertTrue($count == 0, 'Word Othello should be 0');
        $index->insert(['id' => 13, 'title' => 'Othello', 'article' => 'For she had eyes and chose me.']);
        $count = $index->countWordInWordList('Othello');

        $this->assertEquals(1, $count, 'Word Othello should be 1');
        $this->assertEquals(13, $tnt->totalDocumentsInCollection());

        $tnt->selectIndex($this->indexName);

        $res = $tnt->search('Othello');
        $this->assertEquals([13], $res['ids']);
    }

    public function testAsYouType()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer = $tnt->createIndex($this->indexName);
        $indexer->disableOutput(true);
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);
        $tnt->asYouType(true);
        $res = $tnt->search('k');
        $this->assertEquals([1], $res['ids']);
    }

    public function testHits()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer                = $tnt->createIndex($this->indexName);
        $indexer->disableOutput = true;
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);

        $res = $tnt->search('juliet');
        $this->assertEquals(6, $res['hits']);
    }

    public function testFuzzySearch()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer                = $tnt->createIndex($this->indexName);
        $indexer->disableOutput = true;
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);
        $tnt->fuzziness(true);

        $res = $tnt->search('juleit');
        $this->assertEquals("9", $res['ids'][0]);

        $res = $tnt->search('quen');
        $this->assertEquals("7", $res['ids'][0]);

        $res = $tnt->search('asdf');
        $this->assertEquals([], $res['ids']);
    }

    public function testFuzzySearchMultipleWordsFound()
    {
        $tnt = new TNTSearch();
        $tnt->loadConfig($this->config);
        $indexer                = $tnt->createIndex($this->indexName);
        $indexer->disableOutput = true;
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);
        $index = $tnt->getIndex();

        $index->insert(['id' => '14', 'title' => '199x', 'article' => 'Nineties with the x...']);
        $index->insert(['id' => '15', 'title' => '199y', 'article' => 'Nineties with the y...']);
        $tnt->fuzziness(true);
        $res = $tnt->search('199');
        $this->assertContains(14, $res['ids']);
        $this->assertContains(15, $res['ids']);
    }

    public function testFuzzySearchOnExactMatchWithNoLimit()
    {
        $tnt = new TNTSearch();
        $tnt->loadConfig($this->config);
        $indexer                = $tnt->createIndex($this->indexName);
        $indexer->disableOutput = true;
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);
        $index = $tnt->getIndex();

        $index->insert(['id' => '14', 'title' => '199x', 'article' => 'Nineties with the x...']);
        $index->insert(['id' => '15', 'title' => '199y', 'article' => 'Nineties with the y...']);
        $tnt->fuzziness(true);
        $res = $tnt->search('199x');
        $this->assertEquals([14], $res['ids']);

        $tnt->fuzzyNoLimit(true);
        $res = $tnt->search('199x');
        $this->assertEquals([14, 15], $res['ids']);
    }

    public function testIndexDoesNotExistException()
    {
        if ($this->config['engine'] == SqliteEngine::class) {
            $this->expectException(IndexNotFoundException::class);
            $this->expectExceptionCode(1);
            $tnt = new TNTSearch;
            $tnt->loadConfig($this->config);
            $tnt->selectIndex('IndexThatDoesNotExist');
        }
        $this->assertTrue(true);
    }

    public function testUnsupportedDriverException()
    {
        $this->config['driver'] = 'NonExistentDriver';
        $this->expectException(Exception::class);

        $tnt = new TNTSearch;
        $tnt->loadConfig($this->config);

        $res = $tnt->engine->createConnector($this->config);
    }

    public function testStemmerIsSetOnNewIndexesBasedOnConfig()
    {
        $config            = $this->config;
        $config['stemmer'] = \TeamTNT\TNTSearch\Stemmer\GermanStemmer::class;

        $tnt = new TNTSearch();
        $tnt->loadConfig($config);
        $tnt->createIndex($this->indexName);
        $tnt->selectIndex($this->indexName);

        $this->assertInstanceOf(\TeamTNT\TNTSearch\Stemmer\GermanStemmer::class, $tnt->getStemmer());
    }

    public function testDefaultStemmerIsSetOnNewIndexesIfNoneConfigured()
    {
        $config = $this->config;
        unset($config['stemmer']);

        $tnt = new TNTSearch();
        $tnt->loadConfig($config);
        $tnt->createIndex($this->indexName);
        $tnt->selectIndex($this->indexName);

        $this->assertInstanceOf(\TeamTNT\TNTSearch\Stemmer\NoStemmer::class, $tnt->getStemmer());
    }

    public function tearDown(): void
    {
        if (file_exists(__DIR__ . "/" . $this->indexName)) {
            unlink(__DIR__ . "/" . $this->indexName);
        }
    }
}
