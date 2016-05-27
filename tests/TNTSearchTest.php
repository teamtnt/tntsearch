<?php

use TeamTNT\TNTSearch\TNTSearch;

class TNTSearchTest extends PHPUnit_Framework_TestCase
{
    protected $indexName = "testIndex";

    protected $config = [
        'driver'   => 'sqlite',
        'database' => __DIR__ . '/_files/articles.sqlite',
        'host'     => 'localhost',
        'username' => 'testUser',
        'password' => 'testPass',
        'storage'  => __DIR__ . '/_files/',
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
    }

    public function testCreateIndex()
    {
        $tnt = new TNTSearch();
        $tnt->loadConfig($this->config);
        $indexer = $tnt->createIndex($this->indexName);

        $this->assertInstanceOf('TeamTNT\TNTSearch\Indexer\TNTIndexer', $indexer);
        $this->assertFileExists($indexer->getStoragePath() . $this->indexName);
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
        $this->assertEquals([3, 4, 1, 2], $res['ids']);

        $res = $tnt->searchBoolean('juliet -well');
        $this->assertEquals([5, 6, 7, 8, 10], $res['ids']);

        $res = $tnt->searchBoolean('juliet -romeo');
        $this->assertEquals([10], $res['ids']);

        $res = $tnt->searchBoolean('hamlet -king');
        $this->assertEquals([2], $res['ids']);

        $res = $tnt->searchBoolean('hamlet superman');
        $this->assertEquals([], $res['ids']);

        $res = $tnt->searchBoolean('hamlet or superman');
        $this->assertEquals([1,2], $res['ids']);

        $res = $tnt->searchBoolean('hamlet');
        $this->assertEquals([1,2], $res['ids']);

        $res = $tnt->searchBoolean('eldred -bar');
        $this->assertEquals([11], $res['ids']);

        $res = $tnt->searchBoolean('Eldred -bar');
        $this->assertEquals([11], $res['ids']);
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

    public function tearDown()
    {
        if (file_exists(__DIR__ . "/" . $this->indexName)) {
            unlink(__DIR__ . "/" . $this->indexName);
        }

    }
}
