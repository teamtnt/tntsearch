<?php

use TeamTNT\TNTSearch;

class TNTSearchTest extends PHPUnit_Framework_TestCase
{
    protected $indexName = "testIndex";
    protected $config = [
        'type'    => 'mysql',
        'db'      => 'test',
        'host'    => 'localhost',
        'user'    => 'testUser',
        'pass'    => 'testPass',
        'storage' => __DIR__
    ];

    public function testLoadConfig()
    {
        $tnt = new TNTSearch();
        $tnt->loadConfig($this->config);
        $this->assertArrayHasKey('type', $tnt->config);
        $this->assertArrayHasKey('db', $tnt->config);
        $this->assertArrayHasKey('host', $tnt->config);
        $this->assertArrayHasKey('user', $tnt->config);
        $this->assertArrayHasKey('pass', $tnt->config);
        $this->assertArrayHasKey('storage', $tnt->config);
    }

    public function testCreateIndex()
    {
        $tnt = new TNTSearch();
        $tnt->loadConfig($this->config);
        $indexer = $tnt->createIndex($this->indexName);

        $this->assertInstanceOf('TeamTNT\Indexer\TNTIndexer', $indexer);
        $this->assertFileExists($indexer->getStoragePath() . $this->indexName);
    }

    public function tearDown()
    {
        if(file_exists(__DIR__ ."/".$this->indexName))
            unlink(__DIR__ ."/".$this->indexName);
    }
}
