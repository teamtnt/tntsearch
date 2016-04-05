<?php

use TeamTNT\TNTSearch\TNTSearch;

class TNTIndexerTest extends PHPUnit_Framework_TestCase
{   
    protected $indexName = "testIndex";
    protected $config = [
        'driver'   => 'sqlite',
        'database' => __DIR__.'/../_files/articles.sqlite',
        'host'     => 'localhost',
        'username' => 'testUser',
        'password' => 'testPass',
        'storage'  => __DIR__.'/../_files/'
    ];

    public function testSearch()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer = $tnt->createIndex($this->indexName);
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);
        $res = $tnt->search('Juliet');
        $this->assertEquals('9, 5, 6, 7, 8', $res['ids']);

        $res = $tnt->search('Queen Mab');
        $this->assertEquals('7', $res['ids']);
    }

    public function testIfCroatianStemmerIsSet()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer = $tnt->createIndex($this->indexName);
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->setLanguage('croatian');
        $indexer->run();

        $this->index = new PDO('sqlite:' . $this->config['storage'] . $this->indexName);
        $query = "SELECT * FROM info WHERE key = 'stemmer'";
        $docs = $this->index->query($query);
        $value = $docs->fetch(PDO::FETCH_ASSOC)['value'];
        $this->assertEquals('croatian', $value);
    }

    public function testIfGermanStemmerIsSet()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer = $tnt->createIndex($this->indexName);
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->setLanguage('german');
        $indexer->run();

        $this->index = new PDO('sqlite:' . $this->config['storage'] . $this->indexName);
        $query = "SELECT * FROM info WHERE key = 'stemmer'";
        $docs = $this->index->query($query);
        $value = $docs->fetch(PDO::FETCH_ASSOC)['value'];
        $this->assertEquals('german', $value);
    }

    public function tearDown()
    {
        if(file_exists(__DIR__.'/../_files/'.$this->indexName))
            unlink(__DIR__.'/../_files/'.$this->indexName);
    }

}