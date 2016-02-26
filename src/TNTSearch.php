<?php

namespace TeamTNT;

use TeamTNT\Indexer\TNTIndexer;

class TNTSearch
{
    public function createIndex($indexName, $path)
    {
        $indexer = new TNTIndexer;
        return $indexer->createIndex($indexName, $path);
    }
}