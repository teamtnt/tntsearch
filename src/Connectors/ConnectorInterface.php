<?php

namespace TeamTNT\TNTSearch\Connectors;

interface ConnectorInterface
{
    /**
     * Establish a database connection.
     *
     * @param  array  $config
     * @return null|\PDO
     */
    public function connect(array $config);
}
