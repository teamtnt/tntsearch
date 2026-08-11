<?php

use TeamTNT\TNTSearch\Engines\RedisEngine;
use TeamTNT\TNTSearch\Engines\SqliteEngine;
use TeamTNT\TNTSearch\Exceptions\IndexNotFoundException;
use TeamTNT\TNTSearch\TNTSearch;

class TNTSearchTest extends PHPUnit\Framework\TestCase
{
    protected $indexName = "testIndex";

    protected $config = [
        'driver'     => 'sqlite',
        'engine'     => 'TeamTNT\TNTSearch\Engines\RedisEngine',
        'redis_host' => '127.0.0.1',
        'redis_port' => '6379',
        'database'   => __DIR__ . '/_files/articles.sqlite',
        'host'       => 'localhost',
        'username'   => 'testUser',
        'password'   => 'testPass',
        'storage'    => __DIR__ . '/_files/',
        'stemmer'    => \TeamTNT\TNTSearch\Stemmer\PorterStemmer::class
    ];

    public function testLoadConfig()
    {
        $tnt = new TNTSearch();
        $tnt->loadConfig($this->config);
        $this->assertArrayHasKey('driver', $tnt->config);
        $this->assertArrayHasKey('database', $tnt->config);
        $this->assertArrayHasKey('host', $tnt->config);
        $this->assertArrayHasKey('username', $tnt->config);
        $this->assertArrayHasKey('password', $tnt->config);
        $this->assertArrayHasKey('storage', $tnt->config);
        $this->assertArrayHasKey('stemmer', $tnt->config);
    }

    public function testCreateIndex()
    {
        $tnt = new TNTSearch();
        $tnt->loadConfig($this->config);
        $indexer = $tnt->createIndex($this->indexName);
        $this->assertInstanceOf('TeamTNT\TNTSearch\Engines\EngineInterface', $indexer);

        if ($this->config['engine'] == 'TeamTNT\TNTSearch\Engines\SqliteEngine') {
            $this->assertFileExists($indexer->getStoragePath() . $this->indexName);
        }

    }

    public function testSearchBoolean()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer                = $tnt->createIndex($this->indexName);
        $indexer->disableOutput = true;
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);
        $res = $tnt->searchBoolean('romeo juliet queen');
        $this->assertEquals([7], $res['ids']);

        $res = $tnt->searchBoolean('Hamlet or Macbeth');

        $this->assertContains(3, $res['ids']);
        $this->assertContains(4, $res['ids']);
        $this->assertContains(1, $res['ids']);
        $this->assertContains(2, $res['ids']);
        $this->assertEquals(4, $res['hits']);

        $res = $tnt->searchBoolean('juliet ~well');

        $this->assertCount(5, $res['ids']);
        $this->assertContains(5, $res['ids']);
        $this->assertContains(6, $res['ids']);
        $this->assertContains(7, $res['ids']);
        $this->assertContains(8, $res['ids']);
        $this->assertContains(10, $res['ids']);

        $res = $tnt->searchBoolean('juliet ~romeo');
        $this->assertEquals([10], $res['ids']);

        $res = $tnt->searchBoolean('hamlet ~king');
        $this->assertEquals([2], $res['ids']);

        $res = $tnt->searchBoolean('hamlet superman');
        $this->assertEquals([], $res['ids']);

        $res = $tnt->searchBoolean('hamlet or superman');
        $this->assertEquals([1, 2], $res['ids']);

        $res = $tnt->searchBoolean('hamlet');
        $this->assertEquals([1, 2], $res['ids']);

        $res = $tnt->searchBoolean('eldred ~bar');
        $this->assertEquals([11], $res['ids']);

        $res = $tnt->searchBoolean('Eldred ~bar');
        $this->assertEquals([11], $res['ids']);
    }

    /**
     * https://github.com/teamtnt/tntsearch/issues/60
     */
    public function testTotalDocumentCountOnIndexUpdate()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer = $tnt->createIndex($this->indexName);
        $indexer->disableOutput(true);
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);
        $this->assertEquals(12, $tnt->totalDocumentsInCollection());

        $index = $tnt->getIndex();
        //first we test if the total number of documents will decrease
        $index->delete(12);
        $this->assertEquals(11, $tnt->totalDocumentsInCollection());

        //now we try with a document that does not exist, the total number should increase for 1
        $index->update(1234, ['id' => '1234', 'title' => 'updated title', 'article' => 'updated article']);

        $this->assertEquals(12, $tnt->totalDocumentsInCollection());
    }

    public function testPrimaryKeyIncludedInResult()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer                = $tnt->createIndex($this->indexName);
        $indexer->disableOutput = true;
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->includePrimaryKey();
        $indexer->run();

        $tnt->selectIndex($this->indexName);
        $res = $tnt->search(3);
        $this->assertEquals([3], $res['ids']);

    }

    public function testPrimaryKeyNotIncludedInResult()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer = $tnt->createIndex($this->indexName);
        $indexer->disableOutput(true);
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);
        $res = $tnt->search(3);
        $this->assertEquals([], $res['ids']);
    }

    public function testIndexUpdate()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer                = $tnt->createIndex($this->indexName);
        $indexer->disableOutput = true;
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);

        $index = $tnt->getIndex();
        $count = $index->countWordInWordList('titl');

        $this->assertTrue($count == 0, 'Word titl should be 0');
        $index->insert(['id' => '11', 'title' => 'new title', 'article' => 'new article']);

        $count = $index->countWordInWordList('titl');
        $this->assertEquals(1, $count, 'Word titl should be 1');

        $docCount = $index->countDocHitsInWordList('juliet');
        $this->assertEquals(6, $docCount, 'Juliet should occur in 6 documents');

        $index->insert(['id' => '12', 'title' => 'juliet', 'article' => 'new article about juliet']);
        $count = $index->countWordInWordList('juliet');
        $this->assertEquals(9, $count, 'Word juliet should be 9');

        $docCount = $index->countDocHitsInWordList('juliet');
        $this->assertEquals(7, $docCount, 'Juliet should occur in 7 documents');

        $index->delete(12);
        $count = $index->countWordInWordList('juliet');
        $this->assertEquals(7, $count, 'Word juliet should be 7 after delete');

        $docCount = $index->countDocHitsInWordList('juliet');
        $this->assertEquals(6, $docCount, 'Juliet should occur in 6 documents after delete');

        $count = $index->countWordInWordList('romeo');
        $this->assertEquals(5, $count, 'Word romeo should be 5');

        $index->update(11, ['id' => '11', 'title' => 'romeo', 'article' => 'new article about romeo']);

        $count = $index->countWordInWordList('romeo');
        $this->assertEquals(7, $count, 'Word romeo should be 7');

    }

    public function testMultipleSearch()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer = $tnt->createIndex($this->indexName);
        $indexer->disableOutput(true);
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);

        $res = $tnt->search('Othello');
        $this->assertEmpty($res['ids']);
        $this->assertEquals(12, $tnt->totalDocumentsInCollection());

        $index = $tnt->getIndex();

        $count = $index->countWordInWordList('Othello');
        $this->assertTrue($count == 0, 'Word Othello should be 0');
        $index->insert(['id' => 13, 'title' => 'Othello', 'article' => 'For she had eyes and chose me.']);
        $count = $index->countWordInWordList('Othello');

        $this->assertEquals(1, $count, 'Word Othello should be 1');
        $this->assertEquals(13, $tnt->totalDocumentsInCollection());

        $tnt->selectIndex($this->indexName);

        $res = $tnt->search('Othello');
        $this->assertEquals([13], $res['ids']);
    }

    public function testAsYouType()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer = $tnt->createIndex($this->indexName);
        $indexer->disableOutput(true);
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);
        $tnt->asYouType(true);
        $res = $tnt->search('k');
        $this->assertEquals([1], $res['ids']);
    }

    public function testHits()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer                = $tnt->createIndex($this->indexName);
        $indexer->disableOutput = true;
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);

        $res = $tnt->search('juliet');
        $this->assertEquals(6, $res['hits']);
    }

    public function testFuzzySearch()
    {
        $tnt = new TNTSearch;

        $tnt->loadConfig($this->config);

        $indexer                = $tnt->createIndex($this->indexName);
        $indexer->disableOutput = true;
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);
        $tnt->fuzziness(true);

        $res = $tnt->search('juleit');
        $this->assertEquals("9", $res['ids'][0]);

        $res = $tnt->search('quen');
        $this->assertEquals("7", $res['ids'][0]);

        $res = $tnt->search('asdf');
        $this->assertEquals([], $res['ids']);
    }

    public function testFuzzySearchMultipleWordsFound()
    {
        $tnt = new TNTSearch();
        $tnt->loadConfig($this->config);
        $indexer                = $tnt->createIndex($this->indexName);
        $indexer->disableOutput = true;
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);
        $index = $tnt->getIndex();

        $index->insert(['id' => '14', 'title' => '199x', 'article' => 'Nineties with the x...']);
        $index->insert(['id' => '15', 'title' => '199y', 'article' => 'Nineties with the y...']);
        $tnt->fuzziness(true);
        $res = $tnt->search('199');
        $this->assertContains(14, $res['ids']);
        $this->assertContains(15, $res['ids']);
    }

    public function testFuzzySearchOnExactMatchWithNoLimit()
    {
        $tnt = new TNTSearch();
        $tnt->loadConfig($this->config);
        $indexer                = $tnt->createIndex($this->indexName);
        $indexer->disableOutput = true;
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);
        $index = $tnt->getIndex();

        $index->insert(['id' => '14', 'title' => '199x', 'article' => 'Nineties with the x...']);
        $index->insert(['id' => '15', 'title' => '199y', 'article' => 'Nineties with the y...']);
        $tnt->fuzziness(true);
        $res = $tnt->search('199x');
        $this->assertEquals([14], $res['ids']);

        $tnt->fuzzyNoLimit(true);
        $res = $tnt->search('199x');
        $this->assertEquals([14, 15], $res['ids']);
    }

    public function testIndexDoesNotExistException()
    {
        if ($this->config['engine'] == SqliteEngine::class) {
            $this->expectException(IndexNotFoundException::class);
            $this->expectExceptionCode(1);
            $tnt = new TNTSearch;
            $tnt->loadConfig($this->config);
            $tnt->selectIndex('IndexThatDoesNotExist');
        }
        $this->assertTrue(true);
    }

    public function testUnsupportedDriverException()
    {
        $this->config['driver'] = 'NonExistentDriver';
        $this->expectException(Exception::class);

        $tnt = new TNTSearch;
        $tnt->loadConfig($this->config);

        $res = $tnt->engine->createConnector($this->config);
    }

    public function testStemmerIsSetOnNewIndexesBasedOnConfig()
    {
        $config            = $this->config;
        $config['stemmer'] = \TeamTNT\TNTSearch\Stemmer\GermanStemmer::class;

        $tnt = new TNTSearch();
        $tnt->loadConfig($config);
        $tnt->createIndex($this->indexName);
        $tnt->selectIndex($this->indexName);

        $this->assertInstanceOf(\TeamTNT\TNTSearch\Stemmer\GermanStemmer::class, $tnt->getStemmer());
    }

    public function testDefaultStemmerIsSetOnNewIndexesIfNoneConfigured()
    {
        $config = $this->config;
        unset($config['stemmer']);

        $tnt = new TNTSearch();
        $tnt->loadConfig($config);
        $tnt->createIndex($this->indexName);
        $tnt->selectIndex($this->indexName);

        $this->assertInstanceOf(\TeamTNT\TNTSearch\Stemmer\NoStemmer::class, $tnt->getStemmer());
    }

    public function testSwitchingIndexesDoesNotCorruptPreviousIndex()
    {
        $config           = $this->config;
        $config['engine'] = SqliteEngine::class;

        $artistsIndex = 'artists.index';
        $songsIndex   = 'songs.index';

        $tnt = new TNTSearch;
        $tnt->loadConfig($config);

        // Index the first collection (artists).
        $artistsIndexer = $tnt->createIndex($artistsIndex);
        $artistsIndexer->disableOutput(true);
        $artistsIndexer->insert(['id' => 1, 'name' => 'Ambient']);

        // Switch to a second index and index into it. Before the fix the
        // prepared statements and in-memory term cache still pointed at the
        // artists index, so these terms were written to the wrong index.
        $songsIndexer = $tnt->createIndex($songsIndex);
        $songsIndexer->disableOutput(true);
        $songsIndexer->insert(['id' => 1, 'title' => 'Tony Anderson - The King']);

        // The song must be findable in the songs index.
        $tnt->selectIndex($songsIndex);
        $res = $tnt->search('Anderson');
        $this->assertEquals([1], $res['ids'], 'Song terms should be written to the songs index');

        // The artists index must not have been polluted with song terms.
        $tnt->selectIndex($artistsIndex);
        $res = $tnt->search('Anderson');
        $this->assertEmpty($res['ids'], 'Song terms must not leak into the artists index');

        $res = $tnt->search('Ambient');
        $this->assertEquals([1], $res['ids'], 'The artists index should still be intact');

        foreach ([$artistsIndex, $songsIndex] as $index) {
            foreach (['', '-wal', '-shm'] as $suffix) {
                $file = $config['storage'] . $index . $suffix;
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }

    public function testCreateIndexCreatesMissingStorageDirectory()
    {
        $config            = $this->config;
        $config['engine']  = SqliteEngine::class;
        $config['storage'] = __DIR__ . '/_files/nested/created/';

        // Make sure we start from a clean slate.
        @unlink($config['storage'] . $this->indexName);
        @rmdir($config['storage']);
        @rmdir(dirname($config['storage']));
        $this->assertDirectoryDoesNotExist($config['storage']);

        $tnt = new TNTSearch;
        $tnt->loadConfig($config);
        $indexer = $tnt->createIndex($this->indexName);

        $this->assertDirectoryExists($config['storage']);
        $this->assertFileExists($config['storage'] . $this->indexName);

        // Clean up the created files and directories.
        foreach (['', '-wal', '-shm'] as $suffix) {
            $file = $config['storage'] . $this->indexName . $suffix;
            if (file_exists($file)) {
                unlink($file);
            }
        }
        rmdir($config['storage']);
        rmdir(dirname($config['storage']));
    }

    public function testFuzzySearchKeepsExactMatchOutsideMaxExpansions()
    {
        $config           = $this->config;
        $config['engine'] = SqliteEngine::class;

        $tnt = new TNTSearch;
        $tnt->loadConfig($config);

        $indexer = $tnt->createIndex($this->indexName);
        $indexer->disableOutput(true);
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);
        $index = $tnt->getIndex();

        // "buzz" is the exact (low-frequency) term we search for. Several other
        // distinct terms share the fuzzy prefix "bu" and occur far more often,
        // so with fuzzy_max_expansions=2 the exact match falls outside the
        // fuzzySearch() candidate window (ordered by num_hits DESC).
        $index->insert(['id' => '20', 'title' => 'buzz', 'article' => 'buzz']);
        $frequent = ['bunny', 'bubble', 'budget', 'bucket', 'bundle'];
        $docId    = 21;
        foreach ($frequent as $word) {
            $repeated = trim(str_repeat($word . ' ', 6));
            $index->insert(['id' => (string) $docId++, 'title' => $word, 'article' => $repeated]);
        }

        $tnt->fuzziness(true);
        $tnt->setFuzzyPrefixLength(2);
        $tnt->setFuzzyMaxExpansions(2);
        $tnt->fuzzyNoLimit(true);

        // Before the fix, enabling fuzzyNoLimit discarded the exact match and
        // only the fuzzy candidates (the "bunny" docs) were returned, so the
        // "buzz" document was lost.
        $res = $tnt->search('buzz');
        $this->assertContains(20, $res['ids'], 'The exact match must be preserved');
    }

    // Issue #278: searchBoolean() must return the same shape as search(),
    // including a 'docScores' key (the Scout driver reads it unconditionally).
    public function testSearchBooleanReturnsDocScoresKey()
    {
        $config           = $this->config;
        $config['engine'] = SqliteEngine::class;

        $tnt = new TNTSearch;
        $tnt->loadConfig($config);
        $indexer = $tnt->createIndex($this->indexName);
        $indexer->disableOutput(true);
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);
        $res = $tnt->searchBoolean('romeo');

        $this->assertArrayHasKey('docScores', $res);
        $this->assertArrayHasKey('ids', $res);
    }

    // Issues #263 / #246 / #267: "-term" negation in boolean search must
    // exclude like "~term", not union the NOT set, and must not crash on two
    // negations.
    public function testBooleanDashNegationExcludesLikeTilde()
    {
        $config           = $this->config;
        $config['engine'] = SqliteEngine::class;

        $tnt = new TNTSearch;
        $tnt->loadConfig($config);
        $indexer = $tnt->createIndex($this->indexName);
        $indexer->disableOutput(true);
        $indexer->query('SELECT id, title, article FROM articles;');
        $indexer->run();

        $tnt->selectIndex($this->indexName);

        $this->assertEquals(
            $tnt->searchBoolean('juliet ~romeo')['ids'],
            $tnt->searchBoolean('juliet -romeo')['ids']
        );

        // Two negations must not throw.
        $res = $tnt->searchBoolean('juliet -romeo -queen');
        $this->assertIsArray($res['ids']);
    }

    // Issue #193: fuzzy distance must be measured in characters, not bytes, so
    // a single-character substitution in a multibyte string counts as 1.
    public function testMultibyteLevenshtein()
    {
        $engine = new SqliteEngine();

        $this->assertEquals(1, $engine->mbLevenshtein('Керол', 'Кэрол'));
        $this->assertEquals(0, $engine->mbLevenshtein('café', 'café'));
        // ASCII behaviour is unchanged.
        $this->assertEquals(3, $engine->mbLevenshtein('kitten', 'sitting'));
    }

    // Issue #130: an id with no filemap row must map to null, not silently to
    // $res[0] (array_search returning false coerced to offset 0).
    public function testFilesystemMapIdsToPathsHandlesMissingId()
    {
        $config = [
            'driver'    => 'filesystem',
            'engine'    => SqliteEngine::class,
            'storage'   => __DIR__ . '/_files/',
            'location'  => __DIR__ . '/_files/articles/',
            'extension' => 'txt',
        ];

        $tnt = new TNTSearch;
        $tnt->loadConfig($config);
        $indexer = $tnt->createIndex($this->indexName);
        $indexer->disableOutput(true);
        $indexer->run();

        $tnt->selectIndex($this->indexName);

        $mapped = $tnt->engine->filesystemMapIdsToPaths(new \TeamTNT\TNTSearch\Support\Collection([1, 99999]))->toArray();

        $this->assertNotNull($mapped[0], 'Existing id should map to a filemap row');
        $this->assertNull($mapped[1], 'Missing id should map to null, not $res[0]');
    }

    // Guards the wordlist upsert: num_hits must accumulate total occurrences
    // and num_docs the document frequency across several documents that share
    // vocabulary.
    public function testWordlistCountsAccumulateAcrossDocuments()
    {
        $config             = $this->config;
        $config['engine']   = SqliteEngine::class;
        $config['stemmer']  = \TeamTNT\TNTSearch\Stemmer\NoStemmer::class;

        $tnt = new TNTSearch;
        $tnt->loadConfig($config);
        $indexer = $tnt->createIndex($this->indexName);
        $indexer->disableOutput(true);
        $indexer->insert(['id' => 1, 'title' => '', 'article' => 'alpha alpha alpha beta beta']);
        $indexer->insert(['id' => 2, 'title' => '', 'article' => 'alpha alpha']);
        $indexer->insert(['id' => 3, 'title' => '', 'article' => 'alpha']);

        $tnt->selectIndex($this->indexName);
        $index = $tnt->getIndex();

        // "alpha": 3 + 2 + 1 = 6 occurrences across 3 documents.
        $this->assertEquals(6, $index->countWordInWordList('alpha'));
        $this->assertEquals(3, $index->countDocHitsInWordList('alpha'));

        // "beta": 2 occurrences in a single document.
        $this->assertEquals(2, $index->countWordInWordList('beta'));
        $this->assertEquals(1, $index->countDocHitsInWordList('beta'));
    }

    public function tearDown(): void
    {
        if (file_exists(__DIR__ . "/" . $this->indexName)) {
            unlink(__DIR__ . "/" . $this->indexName);
        }
    }
}
