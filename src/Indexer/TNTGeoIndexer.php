<?php

namespace TeamTNT\TNTSearch\Indexer;

use PDO;

class TNTGeoIndexer extends TNTIndexer
{

    public $insertStmt = null;

    public function createIndex($indexName)
    {
        if (file_exists($this->engine->config['storage'] . $indexName)) {
            unlink($this->engine->config['storage'] . $indexName);
        }

        $this->engine->index = new PDO('sqlite:' . $this->engine->config['storage'] . $indexName);
        $this->engine->index->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->engine->index->exec("CREATE TABLE IF NOT EXISTS locations (
            doc_id INTEGER,
            longitude REAL,
            latitude REAL,
            cos_lat REAL,
            sin_lat REAL,
            cos_lng REAL,
            sin_lng REAL
        )");

        $this->engine->index->exec("CREATE INDEX location_index ON locations ('longitude', 'latitude');");

        $this->engine->index->exec("CREATE TABLE IF NOT EXISTS info (key TEXT, value INTEGER)");

        $connector = $this->engine->createConnector($this->engine->config);
        if (!$this->engine->dbh) {
            $this->engine->dbh = $connector->connect($this->engine->config);
        }
        return $this;
    }

    public function processDocument($row)
    {
        $this->prepareInsertStatement();

        $docId     = $row->get($this->getPrimaryKey());
        $longitude = $row->get('longitude');
        $latitude  = $row->get('latitude');
        $cos_lat   = cos($latitude * pi() / 180);
        $sin_lat   = sin($latitude * pi() / 180);
        $cos_lng   = cos($longitude * pi() / 180);
        $sin_lng   = sin($longitude * pi() / 180);

        $this->insertStmt->bindParam(":doc_id", $docId);
        $this->insertStmt->bindParam(":longitude", $longitude);
        $this->insertStmt->bindParam(":latitude", $latitude);
        $this->insertStmt->bindParam(":cos_lat", $cos_lat);
        $this->insertStmt->bindParam(":sin_lat", $sin_lat);
        $this->insertStmt->bindParam(":cos_lng", $cos_lng);
        $this->insertStmt->bindParam(":sin_lng", $sin_lng);
        $this->insertStmt->execute();
    }

    public function prepareInsertStatement()
    {
        if (isset($this->insertStmt)) {
            return $this->insertStmt;
        }

        $this->insertStmt = $this->engine->index->prepare("INSERT INTO locations (doc_id, longitude, latitude, cos_lat, sin_lat, cos_lng, sin_lng)
            VALUES (:doc_id, :longitude, :latitude, :cos_lat, :sin_lat, :cos_lng, :sin_lng)");
    }

    public function insert($document)
    {
        $this->engine->processDocument(new Collection($document));
    }

    public function delete($documentId)
    {
        $this->engine->prepareAndExecuteStatement("DELETE FROM locations WHERE doc_id = :documentId;", [
            ['key' => ':documentId', 'value' => $documentId]
        ]);
    }
}
