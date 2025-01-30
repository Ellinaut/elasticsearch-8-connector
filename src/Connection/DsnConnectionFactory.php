<?php

namespace Ellinaut\ElasticsearchConnector\Connection;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;

/**
 * @author Philipp Marien <philipp@ellinaut.dev>
 */
class DsnConnectionFactory implements ConnectionFactoryInterface
{
    /**
     * @var string
     */
    private $dsn;

    /**
     * DsnConnectionFactory constructor.
     * @param string $dsn
     */
    public function __construct(string $dsn)
    {
        $this->dsn = $dsn;
    }

    /**
     * @return Client
     */
    public function createConnection(): Client
    {
        return ClientBuilder::create()->setHosts([$this->dsn])->build();
    }
}
