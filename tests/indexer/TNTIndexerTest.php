<?php

class TNTIndexerTest extends \PHPUnit_Framework_TestCase
{
    public function testSource()
    {
        $indexer = new \TeamTNT\Indexer\TNTIndexer;
        $indexer->source([]);
    }
}
