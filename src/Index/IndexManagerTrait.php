<?php

namespace Ellinaut\ElasticsearchConnector\Index;

use Elastic\Elasticsearch\Client;
use Ellinaut\ElasticsearchConnector\Connection\ResponseHandlerInterface;
use Ellinaut\ElasticsearchConnector\Document\DocumentMigratorInterface;
use Ellinaut\ElasticsearchConnector\Exception\IndexAlreadyExistException;

/**
 * @author Philipp Marien <philipp@ellinaut.dev>
 */
trait IndexManagerTrait
{
    /**
     * @return array
     */
    abstract protected function getIndexDefinition(): array;

    /**
     * @param string $externalIndexName
     * @param Client $connection
     * @param ResponseHandlerInterface|null $responseHandler
     */
    public function createIndex(
        string $externalIndexName,
        Client $connection,
        ?ResponseHandlerInterface $responseHandler = null
    ): void {
        if ($this->indexExist($externalIndexName, $connection)) {
            throw new IndexAlreadyExistException($externalIndexName);
        }

        $response = $connection->indices()->create(
            [
                'index' => $externalIndexName,
                'body' => $this->getIndexDefinition()
            ]
        );

        if ($responseHandler) {
            $responseHandler->handleResponse(__METHOD__, $response);
        }
    }

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
    ): void {
        if (!$this->indexExist($externalIndexName, $connection)) {
            $this->createIndex($externalIndexName, $connection, $responseHandler);
            return;
        }

        $migrationIndexName = $externalIndexName . '__migrating';

        if ($this->indexExist($migrationIndexName, $connection)) {
            $this->deleteIndex($migrationIndexName, $connection, $responseHandler);
        }
        $this->createIndex($migrationIndexName, $connection, $responseHandler);

        // fetch documents from current index and store to new index
        $searchResult = $connection->search(
            [
                'index' => $externalIndexName,
                'scroll' => '1m',
            ]
        );

        $this->moveDocuments($searchResult, $connection, $migrationIndexName, $documentMigrator, $responseHandler);

        $context = $searchResult['_scroll_id'];
        while (true) {
            $scrollResult = $connection->scroll([
                'scroll_id' => $context,
                'scroll' => '1m',
            ]);

            if (count($scrollResult['hits']['hits']) === 0) {
                break;
            }

            $this->moveDocuments($scrollResult, $connection, $migrationIndexName, $documentMigrator, $responseHandler);

            $context = $scrollResult['_scroll_id'];
        }

        // remove old index
        $this->deleteIndex($externalIndexName, $connection, $responseHandler);

        // recreate the index
        $this->createIndex($externalIndexName, $connection, $responseHandler);

        // fetch documents from current index and store to new index
        $searchResult = $connection->search(
            [
                'index' => $migrationIndexName,
                'scroll' => '1m',
            ]
        );

        $this->moveDocuments($searchResult, $connection, $externalIndexName, null, $responseHandler);

        $context = $searchResult['_scroll_id'];
        while (true) {
            $scrollResult = $connection->scroll([
                'scroll_id' => $context,
                'scroll' => '1m',
            ]);

            if (count($scrollResult['hits']['hits']) === 0) {
                break;
            }

            $this->moveDocuments($scrollResult, $connection, $externalIndexName, null, $responseHandler);

            $context = $scrollResult['_scroll_id'];
        }

        $this->deleteIndex($migrationIndexName, $connection, $responseHandler);
    }

    /**
     * @param string $externalIndexName
     * @param Client $connection
     * @param ResponseHandlerInterface|null $responseHandler
     */
    public function deleteIndex(
        string $externalIndexName,
        Client $connection,
        ?ResponseHandlerInterface $responseHandler = null
    ): void {
        if (!$this->indexExist($externalIndexName, $connection)) {
            return;
        }

        $response = $connection->indices()->delete(['index' => $externalIndexName]);
        if ($responseHandler) {
            $responseHandler->handleResponse(__METHOD__, $response);
        }
    }

    /**
     * @param array $searchResult
     * @param Client $connection
     * @param string $toIndexName
     * @param DocumentMigratorInterface|null $documentManager
     * @param ResponseHandlerInterface|null $responseHandler
     */
    protected function moveDocuments(
        array $searchResult,
        Client $connection,
        string $toIndexName,
        ?DocumentMigratorInterface $documentManager = null,
        ?ResponseHandlerInterface $responseHandler = null
    ): void {
        if (count($searchResult['hits']['hits']) === 0) {
            return;
        }

        $request = [];
        foreach ($searchResult['hits']['hits'] as $hit) {
            $request[] = [
                'index' => [
                    '_index' => $toIndexName,
                    '_id' => $hit['_id'],
                ]
            ];
            $request[] = $documentManager ? $documentManager->migrate($hit['_source']) : $hit['_source'];
        }

        $response = $connection->bulk(['body' => $request]);
        if ($responseHandler) {
            $responseHandler->handleResponse(__METHOD__, $response);
        }
    }

    /**
     * @param string $externalIndexName
     * @param Client $connection
     * @return bool
     */
    protected function indexExist(string $externalIndexName, Client $connection): bool
    {
        return $connection->indices()->exists(['index' => $externalIndexName])->asBool();
    }
}
