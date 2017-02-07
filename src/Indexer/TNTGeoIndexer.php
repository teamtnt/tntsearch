<?php

namespace TeamTNT\TNTSearch\Indexer;

use PDO;

class TNTGeoIndexer extends TNTIndexer
{
    public function createIndex($indexName)
    {
        $this->indexName = $indexName;

        if (file_exists($this->config['storage'].$indexName)) {
            unlink($this->config['storage'].$indexName);
        }

        $this->index = new PDO('sqlite:'.$this->config['storage'].$indexName);
        $this->index->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->index->exec("CREATE TABLE IF NOT EXISTS locations (
                    doc_id INTEGER,
                    longitude REAL,
                    latitude REAL)");

        $this->index->exec("CREATE INDEX location_index ON locations ('longitude', 'latitude');");

        $this->index->exec("CREATE TABLE IF NOT EXISTS info (key TEXT, value INTEGER)");

        $connector = $this->createConnector($this->config);
        if (!$this->dbh) {
            $this->dbh = $connector->connect($this->config);
        }
        return $this;
    }

    public function processDocument($row)
    {
        $row        = $row->toArray();
        $insertStmt = $this->index->prepare("INSERT INTO locations (doc_id, longitude, latitude) VALUES (:doc_id, :longitude, :latitude)");
        $insertStmt->bindParam(":doc_id", $row['id']);
        $insertStmt->bindParam(":longitude", $row['longitude']);
        $insertStmt->bindParam(":latitude", $row['latitude']);
        $insertStmt->execute();
    }

}
