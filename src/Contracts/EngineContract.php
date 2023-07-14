<?php

namespace TeamTNT\TNTSearch\Contracts;

interface EngineContract
{
    public function loadConfig(array $config);
    public function createIndex(string $indexName);
    public function updateInfoTable(string $key, string $value);
    public function getValueFromInfoTable(string $value);
    public function run();
    public function processDocument($row);
    public function saveToIndex($stems, $docId);
    public function selectIndex($indexName);
    public function saveWordlist($stems);
    public function saveDoclist($terms, $docId);
    public function saveHitList($stems, $docId, $termsList);
    public function delete($documentId);
    public function totalDocumentsInCollection();
    public function getWordFromWordList($word);
    public function fuzzySearch($keyword);
    public function readDocumentsFromFileSystem();
    public function getAllDocumentsForStrictKeyword($word, $noLimit);
    public function getAllDocumentsForFuzzyKeyword($words, $noLimit);
    public function getAllDocumentsForWhereKeywordNot($keyword, $noLimit);
    public function flushIndex($indexName);
}
