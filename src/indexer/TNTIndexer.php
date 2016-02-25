<?php

namespace TeamTNT\Indexer;

use PDO;

class TNTIndexer
{
    public $storagePath = "";

    public function createIndex($indexName)
    {
        $this->database = new PDO('sqlite:' . $this->storagePath . $indexName);
        return $this;
    }

    public function source($config = [])
    {
        $this->type = $config['type'];
        $this->db   = $config['db'];
        $this->host = $config['host'];
        $this->user = $config['user'];
        $this->pass = $config['pass'];

        $this->dbh = new PDO($this->type.':host='.$this->host.';dbname='.$this->db, $this->user, $this->pass);
    }

    public function query($query)
    {
        $this->query = $query;
    }

    public function run()
    {
        //$this->dbh->que
    }

    public function loadConfiguration($config = [])
    {
        $this->storagePath = $config['storage_path'];
    }
}