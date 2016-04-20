<?php

use TeamTNT\TNTSearch\TNTSearch;
use TeamTNT\TNTSearch\Indexer\TNTIndexer;

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
        $indexer->disableOutput = true;
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);
        $tnt->asYouType = true;
        $res = $tnt->search('Juliet');
        $this->assertEquals([9, 5, 6, 7, 8, 10], $res['ids']);

        $res = $tnt->search('Queen Mab');
        $this->assertEquals([7], $res['ids']);
    }

    public function testBreakIntoTokens()
    {
        $indexer = new TNTIndexer;

        $text = "This is some text";
        $res = $indexer->breakIntoTokens($text);

        $this->assertContains("This", $res);
        $this->assertContains("text", $res);

        $text = "123 123 123";
        $res = $indexer->breakIntoTokens($text);
        $this->assertContains("123", $res);

        $text = "Hi! This text contains an test@email.com. Test's email 123.";
        $res = $indexer->breakIntoTokens($text);
        $this->assertContains("test@email", $res);
        $this->assertContains("contains", $res);
        $this->assertContains("123", $res);

        $text = "Superman (1941)";
        $res = $indexer->breakIntoTokens($text);
        $this->assertContains("Superman", $res);
        $this->assertContains("1941", $res);
    }

    public function testIfCroatianStemmerIsSet()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer = $tnt->createIndex($this->indexName);
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->setLanguage('croatian');
        $indexer->disableOutput = true;
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
        $indexer->disableOutput = true;
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