<?php

namespace TeamTNT\TNTSearch\Connectors;

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