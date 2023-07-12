<?php

namespace TeamTNT\TNTSearch\Contracts;

interface EngineContract
{
    public function loadConfig(array $config);
    public function createIndex(string $indexName);
}
