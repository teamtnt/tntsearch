<?php

use TeamTNT\TNTSearch\Connectors\Connector;
use TeamTNT\TNTSearch\Connectors\MySqlConnector;

class ConnectorTest extends PHPUnit\Framework\TestCase
{
    public function testConfigOptionsAreMergedIntoPdoOptions()
    {
        $connector = new Connector;

        $options = $connector->getOptions([
            'options' => [
                PDO::ATTR_TIMEOUT => 5,
            ],
        ]);

        // User-supplied option is present ...
        $this->assertSame(5, $options[PDO::ATTR_TIMEOUT]);
        // ... and the defaults are preserved.
        $this->assertSame(PDO::ERRMODE_EXCEPTION, $options[PDO::ATTR_ERRMODE]);
    }

    public function testConfigOptionsCanOverrideDefaults()
    {
        $connector = new Connector;

        $options = $connector->getOptions([
            'options' => [PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING],
        ]);

        $this->assertSame(PDO::ERRMODE_WARNING, $options[PDO::ATTR_ERRMODE]);
    }

    public function testWithoutOptionsKeyDefaultsAreReturned()
    {
        $connector = new Connector;

        $options = $connector->getOptions([]);

        $this->assertSame(PDO::ERRMODE_EXCEPTION, $options[PDO::ATTR_ERRMODE]);
        $this->assertArrayNotHasKey(PDO::ATTR_TIMEOUT, $options);
    }

    public function testMySqlConnectorPassesThroughConfigOptions()
    {
        if (!extension_loaded('pdo_mysql')) {
            $this->markTestSkipped('pdo_mysql not available');
        }

        $connector = new MySqlConnector;

        $options = $connector->getOptions([
            'options' => [PDO::MYSQL_ATTR_SSL_CA => '/path/to/ca.pem'],
        ]);

        // Custom SSL option flows through ...
        $this->assertSame('/path/to/ca.pem', $options[PDO::MYSQL_ATTR_SSL_CA]);
        // ... and the MySQL-specific default is still applied.
        $this->assertFalse($options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY]);
    }
}
