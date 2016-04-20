<?php

use TeamTNT\TNTSearch\TNTSearch;

class TNTSearchTest extends PHPUnit_Framework_TestCase
{
    protected $indexName = "testIndex";
    
    protected $config = [
        'driver'   => 'sqlite',
        'database' => __DIR__.'/_files/articles.sqlite',
        'host'     => 'localhost',
        'username' => 'testUser',
        'password' => 'testPass',
        'storage'  => __DIR__.'/_files/'
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

        $indexer = $tnt->createIndex($this->indexName);
        $indexer->disableOutput = true;
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);
        $res = $tnt->searchBoolean('romeo juliet queen');
        $this->assertEquals([7], $res['ids']);

        $res = $tnt->searchBoolean('Hamlet or Macbeth');
        $this->assertEquals([3,4,1,2], $res['ids']);

        $res = $tnt->searchBoolean('juliet -well');
        $this->assertEquals([5,6,7,8, 10], $res['ids']);

        $res = $tnt->searchBoolean('juliet -romeo');
        $this->assertEquals([10], $res['ids']);

        $res = $tnt->searchBoolean('hamlet -king');
        $this->assertEquals([2], $res['ids']);
    }

    public function tearDown()
    {
        if(file_exists(__DIR__ ."/".$this->indexName))
            unlink(__DIR__ ."/".$this->indexName);
    }
}
