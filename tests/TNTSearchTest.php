<?php

use TeamTNT\TNTSearch\TNTSearch;

class TNTSearchTest extends PHPUnit_Framework_TestCase
{
    protected $indexName = "testIndex";
    protected $config = [
        'driver'   => 'sqlite',
        'database' => ':memory:',
        'host'     => 'localhost',
        'username' => 'testUser',
        'password' => 'testPass',
        'storage'  => __DIR__
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

    public function tearDown()
    {
        if(file_exists(__DIR__ ."/".$this->indexName))
            unlink(__DIR__ ."/".$this->indexName);
    }
}
