<?php

namespace Ellinaut\ElasticsearchConnector\Index;

use Elasticsearch\Client;
use Ellinaut\ElasticsearchConnector\Connection\ResponseHandlerInterface;
use Ellinaut\ElasticsearchConnector\Document\DocumentMigratorInterface;

/**
 * @author Philipp Marien <philipp@ellinaut.dev>
 */
interface IndexManagerInterface
{
    /**
     * @param string $externalIndexName
     * @param Client $connection
     * @param ResponseHandlerInterface|null $responseHandler
     */
    public function createIndex(
        string $externalIndexName,
        Client $connection,
        ?ResponseHandlerInterface $responseHandler = null
    ): void;

    /**
     * @param string $externalIndexName
     * @param Client $connection
     * @param DocumentMigratorInterface|null $documentMigrator
     * @param ResponseHandlerInterface|null $responseHandler
     */
    public function updateIndex(
        string $externalIndexName,
        Client $connection,
        ?DocumentMigratorInterface $documentMigrator = null,
        ?ResponseHandlerInterface $responseHandler = null
    ): void;

    /**
     * @param string $externalIndexName
     * @param Client $connection
     * @param ResponseHandlerInterface|null $responseHandler
     */
    public function deleteIndex(
        string $externalIndexName,
        Client $connection,
        ?ResponseHandlerInterface $responseHandler = null
    ): void;
}
