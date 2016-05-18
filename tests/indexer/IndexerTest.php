<?php

use TeamTNT\TNTSearch\Indexer\TNTIndexer;
use TeamTNT\TNTSearch\TNTSearch;

class TNTIndexerTest extends PHPUnit_Framework_TestCase
{
    protected $indexName = "testIndex";
    protected $config    = [
        'driver'   => 'sqlite',
        'database' => __DIR__ . '/../_files/articles.sqlite',
        'host'     => 'localhost',
        'username' => 'testUser',
        'password' => 'testPass',
        'storage'  => __DIR__ . '/../_files/',
    ];

    public function testSearch()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer                = $tnt->createIndex($this->indexName);
        $indexer->disableOutput = true;
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);
        $tnt->asYouType = true;
        $res            = $tnt->search('Juliet');
        $this->assertEquals([9, 5, 6, 7, 8, 10], $res['ids']);

        $res = $tnt->search('Queen Mab');
        $this->assertEquals([7], $res['ids']);
    }

    public function testBreakIntoTokens()
    {
        $indexer = new TNTIndexer;

        $text = "This is some text";
        $res  = $indexer->breakIntoTokens($text);

        $this->assertContains("This", $res);
        $this->assertContains("text", $res);

        $text = "123 123 123";
        $res  = $indexer->breakIntoTokens($text);
        $this->assertContains("123", $res);

        $text = "Hi! This text contains an test@email.com. Test's email 123.";
        $res  = $indexer->breakIntoTokens($text);
        $this->assertContains("test", $res);
        $this->assertContains("email", $res);
        $this->assertContains("contains", $res);
        $this->assertContains("123", $res);

        $text = "Superman (1941)";
        $res  = $indexer->breakIntoTokens($text);
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
        $query       = "SELECT * FROM info WHERE key = 'stemmer'";
        $docs        = $this->index->query($query);
        $value       = $docs->fetch(PDO::FETCH_ASSOC)['value'];
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
        $query       = "SELECT * FROM info WHERE key = 'stemmer'";
        $docs        = $this->index->query($query);
        $value       = $docs->fetch(PDO::FETCH_ASSOC)['value'];
        $this->assertEquals('german', $value);
    }

    public function testBuildTrigrams()
    {
        $indexer  = new TNTIndexer;
        $trigrams = $indexer->buildTrigrams('created');
        $this->assertEquals('__c _cr cre rea eat ate ted ed_ d__', $trigrams);

        $trigrams = $indexer->buildTrigrams('mood');
        $this->assertEquals('__m _mo moo ood od_ d__', $trigrams);

        $trigrams = $indexer->buildTrigrams('death');
        $this->assertEquals('__d _de dea eat ath th_ h__', $trigrams);

        $trigrams = $indexer->buildTrigrams('behind');
        $this->assertEquals('__b _be beh ehi hin ind nd_ d__', $trigrams);

        $trigrams = $indexer->buildTrigrams('usually');
        $this->assertEquals('__u _us usu sua ual all lly ly_ y__', $trigrams);

        $trigrams = $indexer->buildTrigrams('created');
        $this->assertEquals('__c _cr cre rea eat ate ted ed_ d__', $trigrams);

    }

    public function tearDown()
    {
        if (file_exists(__DIR__ . '/../_files/' . $this->indexName)) {
            unlink(__DIR__ . '/../_files/' . $this->indexName);
        }

    }

}
