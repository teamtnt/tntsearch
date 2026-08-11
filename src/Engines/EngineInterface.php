<?php

namespace TeamTNT\TNTSearch\Engines;

use TeamTNT\TNTSearch\Support\Collection;

interface EngineInterface
{
    public function loadConfig(array $config);
    public function createIndex(string $indexName);
    public function updateInfoTable(string $key, $value);
    public function getValueFromInfoTable(string $value);
    public function run();
    public function processDocument(Collection $row);
    public function saveToIndex(Collection $stems, $docId);
    public function selectIndex(string $indexName);
    public function saveWordlist(Collection $stems);
    public function saveDoclist(array $terms, $docId);
    public function saveHitList(array $stems, $docId, array $termsList);
    public function delete($documentId);
    public function totalDocumentsInCollection();
    public function getWordFromWordList(string $word);
    public function fuzzySearch(string $keyword);
    public function readDocumentsFromFileSystem();
    public function getAllDocumentsForStrictKeyword(array $word, bool $noLimit);
    public function getAllDocumentsForFuzzyKeyword(array $words, bool $noLimit);
    public function getAllDocumentsForWhereKeywordNot(string $keyword, bool $noLimit);
    public function flushIndex(string $indexName);
}
