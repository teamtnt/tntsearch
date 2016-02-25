<?php

namespace TeamTNT;

use TeamTNT\Indexer\TNTIndexer;

class TNTSearch
{
    public function createIndex($indexName)
    {
        $indexer = new TNTIndexer;
        return $indexer->createIndex($indexName);
    }
}