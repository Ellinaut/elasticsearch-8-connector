<?php

namespace Ellinaut\ElasticsearchConnector\Connection;

use Elasticsearch\Client;

/**
 * @author Philipp Marien <philipp@ellinaut.dev>
 */
interface ConnectionFactoryInterface
{
    /**
     * @return Client
     */
    public function createConnection(): Client;
}