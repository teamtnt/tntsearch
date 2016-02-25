<?php

use TeamTNT\TNTSearch;

class TNTSearchTest extends PHPUnit_Framework_TestCase
{
    public $indexName = "testIndex";

    public function testCreateIndex()
    {
        $tnt = new TNTSearch;

        $this->assertFileNotExists($this->indexName);

        $res = $tnt->createIndex($this->indexName);

        $this->assertInstanceOf('TeamTNT\Indexer\TNTIndexer', $res);
        $this->assertFileExists($this->indexName);
    }

    public function tearDown()
    {
        unlink($this->indexName);
    }
}
