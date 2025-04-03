<?php

namespace TeamTNT\TNTSearch\Connectors;

use Exception;

class FileSystemConnector extends Connector implements ConnectorInterface
{
    /**
     * Establish a database connection.
     *
     * @param  array  $config
     * @return null
     *
     * @throws \InvalidArgumentException
     */
    public function connect(array $config)
    {
        return null;
    }
}