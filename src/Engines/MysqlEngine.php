<?php
/*
 * Copyright Blackbit digital Commerce GmbH <info@blackbit.de>
 *
 *  This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 3 of the License, or (at your option) any later version.
 *  This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA
 */

namespace TeamTNT\TNTSearch\Engines;

use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use TeamTNT\TNTSearch\Support\Collection;

class MysqlEngine extends SqliteEngine
{
    public function createIndex(string $indexName)
    {
        $this->setIndexName($indexName);

        $this->selectIndex($indexName);

        $this->flushIndex($indexName);

        $this->index->exec(
            "CREATE TABLE IF NOT EXISTS {$this->indexName}_wordlist (
                    id INTEGER PRIMARY KEY AUTO_INCREMENT,
                    term VARCHAR(255) UNIQUE,
                    num_hits INTEGER,
                    num_docs INTEGER);"
        );

        $this->index->exec("ALTER TABLE {$this->indexName}_wordlist ADD UNIQUE INDEX unique_term (`term`);");

        $this->index->exec(
            "CREATE TABLE IF NOT EXISTS {$this->indexName}_doclist (
                    term_id INTEGER,
                    doc_id VARCHAR(255),
                    hit_count INTEGER);"
        );

        $this->index->exec(
            "CREATE TABLE IF NOT EXISTS {$this->indexName}_fields (
                    id INTEGER PRIMARY KEY AUTO_INCREMENT,
                    name TEXT);"
        );

        $this->index->exec(
            "CREATE TABLE IF NOT EXISTS {$this->indexName}_hitlist (
                    term_id INTEGER,
                    doc_id VARCHAR(255),
                    field_id INTEGER,
                    position INTEGER,
                    hit_count INTEGER);"
        );

        $this->index->exec(
            "CREATE TABLE IF NOT EXISTS {$this->indexName}_info (
                    `key` VARCHAR(64),
                    `value` VARCHAR(255));"
        );

        $this->index->exec("INSERT INTO {$this->indexName}_info ( `key`, `value`) values 
            ('total_documents', 0), 
            ('stemmer', 'TeamTNT\TNTSearch\Stemmer\NoStemmer'), 
            ('tokenizer', 'TeamTNT\TNTSearch\Support\Tokenizer');"
        );

        $this->index->exec("ALTER TABLE {$this->indexName}_doclist ADD INDEX idx_term_id_hit_count (`term_id`, `hit_count` DESC);");
        $this->index->exec("ALTER TABLE {$this->indexName}_doclist ADD INDEX idx_doc_id (`doc_id`);");

        if (isset($this->config['stemmer'])) {
            $this->setStemmer(new $this->config['stemmer']);
        }

        if (isset($this->config['tokenizer'])) {
            $this->setTokenizer(new $this->config['tokenizer']);
        }

        if (!$this->dbh) {
            $connector = $this->createConnector($this->config);
            $this->dbh = $connector->connect($this->config);
        }

        return $this;
    }

    public function selectIndex(string $indexName)
    {
        if ($this->index === null || $this->indexName != $indexName) {
            $this->setIndexName($indexName);
            $this->index = new PDO('mysql:dbname='.$this->config['database'].';host='.$this->config['host'], $this->config['username'], $this->config['password']);
            $this->index->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
    }

    private function setIndexName(string $indexName)
    {
        $this->indexName = preg_replace('/[^a-z0-9_]/i', '_', $indexName);
    }

    public function flushIndex(string $indexName)
    {
        $this->index->exec("DROP TABLE IF EXISTS {$this->indexName}_wordlist;");
        $this->index->exec("DROP TABLE IF EXISTS {$this->indexName}_doclist;");
        $this->index->exec("DROP TABLE IF EXISTS {$this->indexName}_fields;");
        $this->index->exec("DROP TABLE IF EXISTS {$this->indexName}_hitlist;");
        $this->index->exec("DROP TABLE IF EXISTS {$this->indexName}_info;");
    }

    public function updateInfoTable(string $key, $value)
    {
        $this->updateInfoTableStmt = $this->index->prepare("UPDATE {$this->indexName}_info SET `value` = :value WHERE `key` = :key");
        $this->updateInfoTableStmt->bindValue(':key', $key);
        $this->updateInfoTableStmt->bindValue(':value', $value);
        $this->updateInfoTableStmt->execute();
    }

    public function getValueFromInfoTable(string $value)
    {
        $query = "SELECT * FROM {$this->indexName}_info WHERE `key` = '{$value}'";
        $docs = $this->index->query($query);

        if ($ret = $docs->fetch(PDO::FETCH_ASSOC)) {
            return $ret['value'];
        }

        return null;
    }

    public function totalDocumentsInCollection()
    {
        $query = "SELECT * FROM {$this->indexName}_info WHERE `key` = 'total_documents'";
        $docs = $this->index->query($query);

        return $docs->fetch(PDO::FETCH_ASSOC)['value'];
    }

    public function saveWordlist(Collection $stems)
    {
        $terms = [];
        $stems->map(function ($column, $key) use (&$terms) {
            foreach ($column as $term) {
                if (array_key_exists($term, $terms)) {
                    $terms[$term]['hits']++;
                    $terms[$term]['docs'] = 1;
                } else {
                    $terms[$term] = [
                        'hits' => 1,
                        'docs' => 1,
                        'id' => 0,
                    ];
                }
            }
        });

        foreach (array_chunk($terms, 1000, true) as $termChunk) {
            $insertRows = [];
            foreach ($termChunk as $key => $term) {
                $insertRows[] = '(' . $this->index->quote($key) . ', ' . $this->index->quote($term['hits']) . ', ' . $this->index->quote($term['docs']) . ')';
            }

            $this->index->exec('INSERT INTO ' . $this->indexName . '_wordlist (term, num_hits, num_docs) VALUES ' . implode(',',
                    $insertRows) . ' ON DUPLICATE KEY UPDATE num_docs=num_docs+VALUES(num_docs), num_hits=num_hits+VALUES(num_docs)');

            $termIds = $this->index->query('SELECT id, term FROM ' . $this->indexName . '_wordlist WHERE term IN (' . implode(',',
                    array_map([$this->index, 'quote'], array_keys($termChunk))) . ')');
            foreach ($termIds as $termId) {
                foreach (array_keys($termChunk) as $term) {
                    if ($term == $termId['term']) {
                        $terms[$term]['id'] = $termId['id'];
                        break;
                    }
                }
            }
        }

        return $terms;
    }

    public function saveDoclist(array $terms, int $docId)
    {
        $insertRows = [];
        foreach ($terms as $key => $term) {
            $insertRows[] = '(' . $this->index->quote($term['id']) . ', ' . $this->index->quote($docId) . ', ' . $this->index->quote($term['hits']) . ')';
        }

        $this->index->exec('INSERT INTO ' . $this->indexName . '_doclist (term_id, doc_id, hit_count) VALUES ' . implode(',',
                $insertRows) . '');
    }

    public function saveHitList(array $stems, int $docId, array $termsList)
    {
        return;
        $fieldCounter = 0;
        $fields = [];

        $insert = "INSERT INTO {$this->indexName}_hitlist (term_id, doc_id, field_id, position, hit_count)
                   VALUES (:term_id, :doc_id, :field_id, :position, :hit_count)";
        $stmt = $this->index->prepare($insert);

        foreach ($stems as $field => $terms) {
            $fields[$fieldCounter] = $field;
            $positionCounter = 0;
            $termCounts = array_count_values($terms);
            foreach ($terms as $term) {
                if (isset($termsList[$term])) {
                    $stmt->bindValue(':term_id', $termsList[$term]['id']);
                    $stmt->bindValue(':doc_id', $docId);
                    $stmt->bindValue(':field_id', $fieldCounter);
                    $stmt->bindValue(':position', $positionCounter);
                    $stmt->bindValue(':hit_count', $termCounts[$term]);
                    $stmt->execute();
                }
                $positionCounter++;
            }
            $fieldCounter++;
        }
    }

    public function delete(int $documentId)
    {
        $rows = $this->prepareAndExecuteStatement("SELECT * FROM {$this->indexName}_doclist WHERE doc_id = :documentId;",
            [
                ['key' => ':documentId', 'value' => $documentId],
            ])->fetchAll(PDO::FETCH_ASSOC);

        $updateStmt = $this->index->prepare("UPDATE {$this->indexName}_wordlist SET num_docs = num_docs - 1, num_hits = num_hits - :hits WHERE id = :term_id");

        foreach ($rows as $document) {
            $updateStmt->bindParam(':hits', $document['hit_count']);
            $updateStmt->bindParam(':term_id', $document['term_id']);
            $updateStmt->execute();
        }

        $res = $this->prepareAndExecuteStatement("DELETE FROM {$this->indexName}_doclist WHERE doc_id = :documentId;", [
            ['key' => ':documentId', 'value' => $documentId],
        ]);

        $this->prepareAndExecuteStatement("DELETE FROM {$this->indexName}_wordlist WHERE num_hits = 0;");

        $affected = $res->rowCount();

        if ($affected) {
            $total = $this->totalDocumentsInCollection() - 1;
            $this->updateInfoTable('total_documents', $total);
        }
    }

    public function getWordFromWordList(string $word)
    {
        $selectStmt = $this->index->prepare("SELECT * FROM {$this->indexName}_wordlist WHERE term like :keyword LIMIT 1;");
        $selectStmt->bindValue(':keyword', $word);
        $selectStmt->execute();
        return $selectStmt->fetch(PDO::FETCH_ASSOC);
    }

    public function buildDictionary($filename, $count = -1, $hits = true, $docs = false)
    {
        $selectStmt = $this->index->prepare("SELECT * FROM {$this->indexName}_wordlist ORDER BY num_hits DESC;");
        $selectStmt->execute();

        $dictionary = '';
        $counter = 0;

        while ($row = $selectStmt->fetch(PDO::FETCH_ASSOC)) {
            $dictionary .= $row['term'];
            if ($hits) {
                $dictionary .= "\t" . $row['num_hits'];
            }

            if ($docs) {
                $dictionary .= "\t" . $row['num_docs'];
            }

            $counter++;
            if ($counter >= $count && $count > 0) {
                break;
            }

            $dictionary .= "\n";
        }

        file_put_contents($filename, $dictionary, LOCK_EX);
    }

    public function getWordlistByKeyword(string $keyword, bool $isLastWord = false, bool $noLimit = false)
    {
        $searchWordlist = "SELECT * FROM {$this->indexName}_wordlist WHERE term like :keyword LIMIT 1;";
        $stmtWord = $this->index->prepare($searchWordlist);

        if ($this->asYouType && $isLastWord) {
            $searchWordlist = "SELECT * FROM {$this->indexName}_wordlist WHERE term like :keyword ORDER BY length(term) ASC, num_hits DESC LIMIT 1";
            $stmtWord = $this->index->prepare($searchWordlist);
            $stmtWord->bindValue(':keyword', mb_strtolower($keyword) . '%');
        } else {
            $stmtWord->bindValue(':keyword', mb_strtolower($keyword));
        }
        $stmtWord->execute();
        $res = $stmtWord->fetchAll(PDO::FETCH_ASSOC);

        if ($this->fuzziness && (!isset($res[0]) || $noLimit)) {
            return $this->fuzzySearch($keyword);
        }
        return $res;
    }

    public function fuzzySearch(string $keyword)
    {
        $prefix = mb_substr($keyword, 0, $this->fuzzy_prefix_length);
        $searchWordlist = "SELECT * FROM {$this->indexName}_wordlist WHERE term like :keyword ORDER BY num_hits DESC LIMIT {$this->fuzzy_max_expansions};";
        $stmtWord = $this->index->prepare($searchWordlist);
        $stmtWord->bindValue(':keyword', mb_strtolower($prefix) . '%');
        $stmtWord->execute();
        $matches = $stmtWord->fetchAll(PDO::FETCH_ASSOC);

        $resultSet = [];
        foreach ($matches as $match) {
            $distance = levenshtein($match['term'], $keyword);
            if ($distance <= $this->fuzzy_distance) {
                $match['distance'] = $distance;
                $resultSet[] = $match;
            }
        }

        // Sort the data by distance, and than by num_hits
        $distance = [];
        $hits = [];
        foreach ($resultSet as $key => $row) {
            $distance[$key] = $row['distance'];
            $hits[$key] = $row['num_hits'];
        }
        array_multisort($distance, SORT_ASC, $hits, SORT_DESC, $resultSet);

        return $resultSet;
    }

    public function getAllDocumentsForFuzzyKeyword(array $words, bool $noLimit)
    {
        $binding_params = implode(',', array_fill(0, count($words), '?'));
        $query = "SELECT * FROM {$this->indexName}_doclist WHERE term_id in ({$binding_params}) ORDER BY CASE term_id";
        $order_counter = 1;

        foreach ($words as $word) {
            $query .= ' WHEN ' . $word['id'] . ' THEN ' . $order_counter++;
        }

        $query .= ' END';

        if (!$noLimit) {
            $query .= " LIMIT {$this->maxDocs}";
        }

        $stmtDoc = $this->index->prepare($query);

        $ids = null;
        foreach ($words as $word) {
            $ids[] = $word['id'];
        }

        $stmtDoc->execute($ids);
        return new Collection($stmtDoc->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getAllDocumentsForWhereKeywordNot(string $keyword, bool $noLimit = false)
    {
        $word = $this->getWordlistByKeyword($keyword);
        if (!isset($word[0])) {
            return new Collection([]);
        }
        $query = "SELECT * FROM {$this->indexName}_doclist WHERE doc_id NOT IN (SELECT doc_id FROM {$this->indexName}_doclist WHERE term_id = :id) GROUP BY doc_id ORDER BY hit_count DESC LIMIT {$this->maxDocs};";
        if ($noLimit) {
            $query = "SELECT * FROM {$this->indexName}_doclist WHERE doc_id NOT IN (SELECT doc_id FROM {$this->indexName}_doclist WHERE term_id = :id) GROUP BY doc_id ORDER BY hit_count DESC;";
        }
        $stmtDoc = $this->index->prepare($query);

        $stmtDoc->bindValue(':id', $word[0]['id']);
        $stmtDoc->execute();
        return new Collection($stmtDoc->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getAllDocumentsForStrictKeyword(array $word, bool $noLimit)
    {
        $query = "SELECT * FROM {$this->indexName}_doclist WHERE term_id = :id ORDER BY hit_count DESC LIMIT {$this->maxDocs};";
        if ($noLimit) {
            $query = "SELECT * FROM {$this->indexName}_doclist WHERE term_id = :id ORDER BY hit_count DESC;";
        }
        $stmtDoc = $this->index->prepare($query);

        $stmtDoc->bindValue(':id', $word[0]['id']);
        $stmtDoc->execute();
        return new Collection($stmtDoc->fetchAll(PDO::FETCH_ASSOC));
    }

    public function readDocumentsFromFileSystem()
    {
        $exclude = [];
        if (isset($this->config['exclude'])) {
            $exclude = $this->config['exclude'];
        }

        $this->index->exec(
            "CREATE TABLE IF NOT EXISTS {$this->indexName}_filemap (
                    id INTEGER PRIMARY KEY,
                    path TEXT)"
        );
        $path = realpath($this->config['location']);

        $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path),
            RecursiveIteratorIterator::SELF_FIRST);
        $this->index->beginTransaction();
        $counter = 0;

        foreach ($objects as $name => $object) {
            $name = str_replace($path . '/', '', $name);

            if (is_callable($this->config['extension'])) {
                $includeFile = $this->config['extension']($object);
            } elseif (is_array($this->config['extension'])) {
                $includeFile = in_array($object->getExtension(), $this->config['extension']);
            } else {
                $includeFile = stringEndsWith($name, $this->config['extension']);
            }

            if ($includeFile && !in_array($name, $exclude)) {
                $counter++;
                $file = [
                    'id' => $counter,
                    'name' => $name,
                    'content' => $this->filereader->read($object),
                ];
                $fileCollection = new Collection($file);

                if (property_exists($this->filereader, 'fileFilterCallback')
                    && is_callable($this->filereader->fileFilterCallback)) {
                    $fileCollection = $fileCollection->filter($this->filereader->fileFilterCallback);
                }
                if (property_exists($this->filereader, 'fileMapCallback')
                    && is_callable($this->filereader->fileMapCallback)) {
                    $fileCollection = $fileCollection->map($this->filereader->fileMapCallback);
                }

                $this->processDocument($fileCollection);
                $statement = $this->index->prepare("INSERT INTO {$this->indexName}_filemap ('id', 'path') values (:counter, :object);");
                $statement->bindParam(':counter', $counter);
                $statement->bindParam(':object', $object);
                $statement->execute();
                $this->info("Processed {$counter} {$object}");
            }
        }

        $this->index->commit();

        $this->index->exec("INSERT INTO {$this->indexName}_info ( 'key', 'value') values ( 'total_documents', $counter),( 'driver', 'filesystem')");

        $this->info("Total rows {$counter}");
        $this->info("Index created: {$this->config['storage']}");
    }
}
