<?php
namespace TeamTNT\TNTSearch\Engines;

use Exception;
use TeamTNT\TNTSearch\Connectors\FileSystemConnector;
use TeamTNT\TNTSearch\Connectors\MySqlConnector;
use TeamTNT\TNTSearch\Connectors\PostgresConnector;
use TeamTNT\TNTSearch\Connectors\SQLiteConnector;
use TeamTNT\TNTSearch\Connectors\SqlServerConnector;
use TeamTNT\TNTSearch\Support\Collection;
use TeamTNT\TNTSearch\Support\TokenizerInterface;

trait EngineTrait
{
    /**
     * @return string
     */
    public function getStoragePath()
    {
        return $this->config['storage'];
    }

    /**
     * @param array $config
     *
     * @return FileSystemConnector|MySqlConnector|PostgresConnector|SQLiteConnector|SqlServerConnector
     * @throws Exception
     */
    public function createConnector(array $config)
    {
        if (!isset($config['driver'])) {
            throw new Exception('A driver must be specified.');
        }

        switch ($config['driver']) {
            case 'mysql':
                return new MySqlConnector;
            case 'pgsql':
                return new PostgresConnector;
            case 'sqlite':
                return new SQLiteConnector;
            case 'sqlsrv':
                return new SqlServerConnector;
            case 'filesystem':
                return new FileSystemConnector;
        }
        throw new Exception("Unsupported driver [{$config['driver']}]");
    }

    public function query($query)
    {
        $this->query = $query;
    }

    public function disableOutput($value)
    {
        $this->disableOutput = $value;
    }

    public function setStemmer($stemmer)
    {
        $this->stemmer = $stemmer;
        $this->updateInfoTable('stemmer', get_class($stemmer));
    }

    public function getPrimaryKey()
    {
        if (isset($this->primaryKey)) {
            return $this->primaryKey;
        }
        return 'id';
    }

    public function stemText($text)
    {
        $stemmer = $this->getStemmer();
        $words   = $this->breakIntoTokens($text);
        $stems   = [];
        foreach ($words as $word) {
            $stems[] = $stemmer->stem($word);
        }
        return $stems;
    }

    public function getStemmer()
    {
        return $this->stemmer;
    }

    public function breakIntoTokens($text)
    {
        if ($this->decodeHTMLEntities) {
            $text = html_entity_decode($text);
        }
        return $this->tokenizer->tokenize($text, $this->stopWords);
    }

    public function info($text)
    {
        if (!$this->disableOutput) {
            echo $text . PHP_EOL;
        }
    }

    public function setInMemory($value)
    {
        $this->inMemory = $value;
    }

    public function setIndex($index)
    {
        $this->index = $index;
    }

    /**
     * @param TokenizerInterface $tokenizer
     */
    public function setTokenizer(TokenizerInterface $tokenizer)
    {
        $this->tokenizer = $tokenizer;
        $this->updateInfoTable('tokenizer', get_class($tokenizer));
    }

    public function update($id, $document)
    {
        $this->delete($id);
        $this->insert($document);
    }

    public function insert($document)
    {
        $this->processDocument(new Collection($document));
        $total = $this->totalDocumentsInCollection() + 1;
        $this->updateInfoTable('total_documents', $total);
    }

    public function includePrimaryKey()
    {
        $this->excludePrimaryKey = false;
    }

    public function setPrimaryKey($primaryKey)
    {
        $this->primaryKey = $primaryKey;
    }

    public function countWordInWordList($word)
    {
        $res = $this->getWordFromWordList($word);

        if ($res) {
            return $res['num_hits'];
        }
        return 0;
    }

    public function asYouType($value)
    {
        $this->asYouType = $value;
    }

    public function fuzziness($value)
    {
        $this->fuzziness = $value;
    }

    public function setLanguage($language = 'no')
    {
        $class = 'TeamTNT\\TNTSearch\\Stemmer\\' . ucfirst(strtolower($language)) . 'Stemmer';
        $this->setStemmer(new $class);
    }

    public function setDatabaseHandle($dbh)
    {
        $this->dbh = $dbh;
    }
}
