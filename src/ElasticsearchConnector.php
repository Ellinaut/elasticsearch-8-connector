<?php

namespace Ellinaut\ElasticsearchConnector;

use Elastic\Elasticsearch\Client;
use Ellinaut\ElasticsearchConnector\Connection\ResponseHandlerInterface;
use Ellinaut\ElasticsearchConnector\Document\DocumentMigratorInterface;
use Ellinaut\ElasticsearchConnector\Connection\ConnectionFactoryInterface;
use Ellinaut\ElasticsearchConnector\Exception\MissingIndexManagerException;
use Ellinaut\ElasticsearchConnector\Index\IndexManagerInterface;
use Ellinaut\ElasticsearchConnector\Index\PipelineManagerInterface;
use Ellinaut\ElasticsearchConnector\NameProvider\NameProviderInterface;

/**
 * @author Philipp Marien <philipp@ellinaut.dev>
 */
class ElasticsearchConnector
{
    /**
     * @var ConnectionFactoryInterface
     */
    private $connectionFactory;

    /**
     * @var NameProviderInterface
     */
    private $indexNameProvider;

    /**
     * @var NameProviderInterface
     */
    private $pipelineNameProvider;

    /**
     * @var ResponseHandlerInterface|null
     */
    private $responseHandler;

    /**
     * @var IndexManagerInterface[]
     */
    private $indexManagers = [];

    /**
     * @var PipelineManagerInterface[]
     */
    private $pipelineManagers;

    /**
     * @var DocumentMigratorInterface[]
     */
    private $documentMigrators = [];

    /**
     * @var Client|null
     */
    private $connection;

    /**
     * @var int
     */
    private $maxQueueSize;

    /**
     * @var int
     */
    private $queueSize = 0;

    /**
     * @var string|null
     */
    private $queuePipeline;

    /**
     * @var array
     */
    private $executionQueue = [];

    /**
     * @var bool
     */
    private $forceRefresh;

    /**
     * @param ConnectionFactoryInterface $connectionFactory
     * @param NameProviderInterface $indexNameProvider
     * @param NameProviderInterface $pipelineNameProvider
     * @param ResponseHandlerInterface|null $responseHandler
     * @param int $bulkSize
     * @param bool $forceRefresh
     */
    public function __construct(
        ConnectionFactoryInterface $connectionFactory,
        NameProviderInterface $indexNameProvider,
        NameProviderInterface $pipelineNameProvider,
        ?ResponseHandlerInterface $responseHandler = null,
        int $bulkSize = 50,
        bool $forceRefresh = true
    ) {
        $this->connectionFactory = $connectionFactory;
        $this->indexNameProvider = $indexNameProvider;
        $this->pipelineNameProvider = $pipelineNameProvider;
        $this->responseHandler = $responseHandler;
        $this->maxQueueSize = $bulkSize;
        $this->forceRefresh = $forceRefresh;
    }

    /**
     * @param string $internalIndexName
     * @param IndexManagerInterface $indexManager
     */
    public function addIndexManager(string $internalIndexName, IndexManagerInterface $indexManager): void
    {
        $this->indexManagers[$internalIndexName] = $indexManager;
    }

    /**
     * @param string $internalPipelineName
     * @param PipelineManagerInterface $pipelineManager
     */
    public function addPipelineManager(string $internalPipelineName, PipelineManagerInterface $pipelineManager): void
    {
        $this->pipelineManagers[$internalPipelineName] = $pipelineManager;
    }

    /**
     * @param string $internalIndexName
     * @param DocumentMigratorInterface $documentMigrator
     */
    public function addDocumentMigrator(string $internalIndexName, DocumentMigratorInterface $documentMigrator): void
    {
        $this->documentMigrators[$internalIndexName] = $documentMigrator;
    }

    public function executeSetupProcess(): void
    {
        $this->createPipelines();

        foreach (array_keys($this->indexManagers) as $internalIndexName) {
            $this->createIndexIfNotExist($internalIndexName);
        }
    }

    /**
     * @return Client
     */
    public function getConnection(): Client
    {
        if (!$this->connection) {
            $this->connection = $this->connectionFactory->createConnection();
        }

        return $this->connection;
    }

    /**
     * @param string $externalIndexName
     * @return string
     */
    public function getInternalIndexName(string $externalIndexName): string
    {
        return $this->indexNameProvider->provideInternalName($externalIndexName);
    }

    /**
     * @param string $internalIndexName
     * @return string
     */
    public function getExternalIndexName(string $internalIndexName): string
    {
        return $this->indexNameProvider->provideExternalName($internalIndexName);
    }

    /**
     * @param string $externalPipelineName
     * @return string
     */
    public function getInternalPipelineName(string $externalPipelineName): string
    {
        return $this->pipelineNameProvider->provideInternalName($externalPipelineName);
    }

    /**
     * @param string $internalPipelineName
     * @return string
     */
    public function getExternalPipelineName(string $internalPipelineName): string
    {
        return $this->pipelineNameProvider->provideExternalName($internalPipelineName);
    }

    /**
     * @param string $internalIndexName
     * @return IndexManagerInterface
     */
    public function getIndexManager(string $internalIndexName): IndexManagerInterface
    {
        if (!array_key_exists($internalIndexName, $this->indexManagers)) {
            throw new MissingIndexManagerException($internalIndexName);
        }

        return $this->indexManagers[$internalIndexName];
    }

    /**
     * @param string $internalIndexName
     * @return DocumentMigratorInterface
     */
    public function getDocumentMigrator(string $internalIndexName): ?DocumentMigratorInterface
    {
        if (!array_key_exists($internalIndexName, $this->documentMigrators)) {
            return null;
        }

        return $this->documentMigrators[$internalIndexName];
    }

    /**
     * @param string $internalIndexName
     */
    public function createIndexIfNotExist(string $internalIndexName): void
    {
        $indexName = $this->getExternalIndexName($internalIndexName);
        if ($this->getConnection()->indices()->exists(['index' => $indexName])->asBool()) {
            return;
        }

        $this->createIndex($internalIndexName);
    }

    /**
     * @param string $internalIndexName
     */
    public function createIndex(string $internalIndexName): void
    {
        $this->getIndexManager($internalIndexName)->createIndex(
            $this->getExternalIndexName($internalIndexName),
            $this->getConnection(),
            $this->responseHandler
        );
    }

    /**
     * @param string $internalIndexName
     */
    public function recreateIndex(string $internalIndexName): void
    {
        $indexName = $this->getExternalIndexName($internalIndexName);
        if ($this->getConnection()->indices()->exists(['index' => $indexName])->asBool()) {
            $this->deleteIndex($internalIndexName);
        }

        $this->createIndex($internalIndexName);
    }

    /**
     * @param string $internalIndexName
     */
    public function updateIndex(string $internalIndexName): void
    {
        $this->getIndexManager($internalIndexName)->updateIndex(
            $this->getExternalIndexName($internalIndexName),
            $this->getConnection(),
            $this->getDocumentMigrator($internalIndexName),
            $this->responseHandler
        );
    }

    /**
     * @param string $internalIndexName
     */
    public function deleteIndex(string $internalIndexName): void
    {
        $this->getIndexManager($internalIndexName)->deleteIndex(
            $this->getExternalIndexName($internalIndexName),
            $this->getConnection(),
            $this->responseHandler
        );
    }

    /**
     * @param array|null $internalPipelineNames
     */
    public function createPipelines(?array $internalPipelineNames = null): void
    {
        foreach ($this->pipelineManagers as $internalPipelineName => $pipelineManager) {
            if (is_array($internalPipelineNames) && !in_array($internalPipelineName, $internalPipelineNames, true)) {
                continue;
            }

            $pipelineManager->createPipeline(
                $this->getExternalPipelineName($internalPipelineName),
                $this->getConnection(),
                $this->responseHandler
            );
        }
    }

    /**
     * @param array|null $internalPipelineNames
     */
    public function deletePipelines(?array $internalPipelineNames = null): void
    {
        foreach ($this->pipelineManagers as $internalPipelineName => $pipelineManager) {
            if (is_array($internalPipelineNames) && !in_array($internalPipelineName, $internalPipelineNames, true)) {
                continue;
            }

            $pipelineManager->deletePipeline(
                $this->getExternalPipelineName($internalPipelineName),
                $this->getConnection(),
                $this->responseHandler
            );
        }
    }

    /**
     * @param string $internalIndexName
     * @param string $id
     * @param array $document
     * @param string|null $internalPipelineName
     */
    public function indexDocument(
        string $internalIndexName,
        string $id,
        array $document,
        ?string $internalPipelineName = null
    ): void {
        if ($this->maxQueueSize <= 0) {
            $this->indexDocumentImmediately(
                $internalIndexName,
                $id,
                $document,
                $internalPipelineName
            );
            return;
        }

        if ($internalPipelineName !== $this->queuePipeline) {
            // execute the queue with the last pipeline, so next commands can use the new one
            $this->executeQueueImmediately();
            $this->queuePipeline = $internalPipelineName;
        }

        $this->executionQueue[] = [
            'index' => [
                '_index' => $this->getExternalIndexName($internalIndexName),
                '_id' => $id,
            ]
        ];
        $this->executionQueue[] = $document;
        $this->queueSize++;

        $this->executeQueueIfSizeReached();
    }

    /**
     * @param string $internalIndexName
     * @param string $id
     * @param array $document
     * @param string|null $internalPipelineName
     */
    public function indexDocumentImmediately(
        string $internalIndexName,
        string $id,
        array $document,
        ?string $internalPipelineName = null
    ): void {
        $request = [
            'index' => $this->getExternalIndexName($internalIndexName),
            'id' => $id,
            'refresh' => $this->forceRefresh,
            'body' => $document,
        ];

        if ($internalPipelineName) {
            $request['pipeline'] = $this->getExternalPipelineName($internalPipelineName);
        }

        $response = $this->getConnection()->index($request);
        if ($this->responseHandler) {
            $this->responseHandler->handleResponse(__METHOD__, $response->asArray());
        }
    }

    /**
     * @param string $internalIndexName
     * @param string $id
     * @return array|null
     */
    public function retrieveDocument(string $internalIndexName, string $id): ?array
    {
        $document = $this->getConnection()->get([
            'index' => $this->getExternalIndexName($internalIndexName),
            'id' => $id,
        ]);

        if (!$document->asBool()) {
            return null;
        }

        return $document->asArray();
    }

    /**
     * @param string $internalIndexName
     * @param string $id
     * @return array|null
     */
    public function retrieveDocumentSource(string $internalIndexName, string $id): ?array
    {
        $document = $this->retrieveDocument($internalIndexName, $id);
        if (!$document) {
            return null;
        }

        if (!array_key_exists('_source', $document) || !is_array($document['_source'])) {
            return null;
        }

        return $document['_source'];
    }

    /**
     * @param string $internalIndexName
     * @param string $id
     */
    public function deleteDocument(string $internalIndexName, string $id): void
    {
        if ($this->maxQueueSize <= 0) {
            $this->deleteDocumentImmediately($internalIndexName, $id);
            return;
        }

        $this->executionQueue[] = [
            'delete' => [
                '_index' => $this->getExternalIndexName($internalIndexName),
                '_id' => $id,
            ]
        ];
        $this->queueSize++;

        $this->executeQueueIfSizeReached();
    }

    /**
     * @param string $internalIndexName
     * @param string $id
     */
    public function deleteDocumentImmediately(string $internalIndexName, string $id): void
    {
        $response = $this->getConnection()->delete([
            'index' => $this->getExternalIndexName($internalIndexName),
            'id' => $id,
            'refresh' => $this->forceRefresh,
        ]);

        if ($this->responseHandler) {
            $this->responseHandler->handleResponse(__METHOD__, $response->asArray());
        }
    }

    protected function executeQueueIfSizeReached(): void
    {
        if ($this->queueSize >= $this->maxQueueSize) {
            $this->executeQueueImmediately();
        }
    }

    public function executeQueueImmediately(): void
    {
        // don't execute on empty queue
        if (count($this->executionQueue) === 0) {
            return;
        }

        $bulkRequest = ['body' => $this->executionQueue, 'refresh' => $this->forceRefresh];
        if ($this->queuePipeline) {
            $bulkRequest['pipeline'] = $this->getExternalPipelineName($this->queuePipeline);
        }

        $response = $this->getConnection()->bulk($bulkRequest);
        if ($this->responseHandler) {
            $this->responseHandler->handleResponse(__METHOD__, $response->asArray());
        }

        // clear queue
        $this->queueSize = 0;
        $this->executionQueue = [];
    }

    /**
     * @param array $parameters
     * @param array $internalIndexNames
     * @return array
     */
    public function executeSearch(array $parameters, array $internalIndexNames = []): array
    {
        return $this->getConnection()->search(
            $this->buildParametersWithIndex($parameters, $internalIndexNames)
        )->asArray();
    }

    /**
     * @param array $parameters
     * @param array $internalIndexNames
     * @return int
     */
    public function executeCount(array $parameters, array $internalIndexNames = []): int
    {
        return (int)$this->getConnection()->count(
            $this->buildParametersWithIndex($parameters, $internalIndexNames)
        )->asArray()['count'];
    }

    /**
     * @param array $parameters
     * @param array $internalIndexNames
     * @return array
     */
    protected function buildParametersWithIndex(array $parameters, array $internalIndexNames = []): array
    {
        $indexNames = [];
        foreach ($internalIndexNames as $internalIndexName) {
            $indexNames[] = $this->getExternalIndexName($internalIndexName);
        }

        $parameters['index'] = count($indexNames) > 0 ? implode(',', $indexNames) : '_all';

        return $parameters;
    }

    /**
     * Rebuilds the index with zero downtime using Elasticsearch aliases.
     *
     * On the first call, migrates an existing plain index to an alias-backed versioned index.
     * On later calls, atomically swaps the alias between _a and _b versioned indices.
     *
     * @param string $internalIndexName
     */
    public function rebuildIndex(string $internalIndexName): void
    {
        $aliasName  = $this->getExternalIndexName($internalIndexName);
        $connection = $this->getConnection();

        $isAlias = $connection->indices()->existsAlias(['name' => $aliasName])->asBool();

        if ($isAlias) {
            $currentIndexName = $this->resolveAliasTarget($aliasName, $connection);
            $nextIndexName    = $this->nextVersionedIndexName($currentIndexName, $aliasName);
        } else {
            // First-time migration: the external name is still a plain index.
            $currentIndexName = $aliasName;
            $nextIndexName    = $aliasName . '__a';
        }

        // Clean up any leftover from a previously aborted rebuild.
        if ($connection->indices()->exists(['index' => $nextIndexName])->asBool()) {
            $connection->indices()->delete(['index' => $nextIndexName]);
        }

        // Build the next index with the current mapping.
        $this->getIndexManager($internalIndexName)->createIndex(
            $nextIndexName,
            $connection,
            $this->responseHandler
        );

        // Copy all documents from current to next index.
        $this->scrollAndCopyDocuments(
            $currentIndexName,
            $nextIndexName,
            $connection,
            $this->getDocumentMigrator($internalIndexName)
        );

        if ($isAlias) {
            // Atomic swap: remove the old alias target, add a new one in a single API call.
            $connection->indices()->updateAliases([
                'body' => [
                    'actions' => [
                        ['remove' => ['index' => $currentIndexName, 'alias' => $aliasName]],
                        ['add'    => ['index' => $nextIndexName,    'alias' => $aliasName]],
                    ],
                ],
            ]);

            // Remove the now-inactive versioned index.
            $connection->indices()->delete(['index' => $currentIndexName]);
        } else {
            // First-time migration:
            // Delete the real index first (brief unavailability, one-time only),
            // then create the alias pointing to the new versioned index.
            $connection->indices()->delete(['index' => $currentIndexName]);
            $connection->indices()->updateAliases([
                'body' => [
                    'actions' => [
                        ['add' => ['index' => $nextIndexName, 'alias' => $aliasName]],
                    ],
                ],
            ]);
        }
    }

    /**
     * Returns the name of the single index that the given alias points to.
     *
     * @param string $aliasName
     * @param Client $connection
     * @return string
     */
    private function resolveAliasTarget(string $aliasName, Client $connection): string
    {
        $result = $connection->indices()->getAlias(['name' => $aliasName])->asArray();
        // Response structure: { 'actual_index_name' => { 'aliases' => { 'alias_name' => {} } } }
        reset($result);
        return (string) key($result);
    }

    /**
     * Returns the next versioned index name by toggling between __a and __b suffixes.
     *
     * @param string $currentIndexName  e.g. "vhs_courses__a"
     * @param string $aliasName         e.g. "vhs_courses"
     * @return string                   e.g. "vhs_courses__b"
     */
    private function nextVersionedIndexName(string $currentIndexName, string $aliasName): string
    {
        if (substr($currentIndexName, -3) === '__a') {
            return $aliasName . '__b';
        }

        return $aliasName . '__a';
    }

    /**
     * Copies all documents from one index to another using the Scroll API.
     *
     * @param string $fromIndex
     * @param string $toIndex
     * @param Client $connection
     * @param DocumentMigratorInterface|null $documentMigrator
     */
    private function scrollAndCopyDocuments(
        string $fromIndex,
        string $toIndex,
        Client $connection,
        ?DocumentMigratorInterface $documentMigrator = null
    ): void {
        $searchResult = $connection->search([
            'index'  => $fromIndex,
            'scroll' => '1m',
        ]);

        $this->bulkIndexDocuments($searchResult->asArray(), $toIndex, $connection, $documentMigrator);

        $scrollId = $searchResult['_scroll_id'];
        while (true) {
            $scrollResult = $connection->scroll([
                'scroll_id' => $scrollId,
                'scroll'    => '1m',
            ]);

            if (count($scrollResult['hits']['hits']) === 0) {
                break;
            }

            $this->bulkIndexDocuments($scrollResult->asArray(), $toIndex, $connection, $documentMigrator);
            $scrollId = $scrollResult['_scroll_id'];
        }
    }

    /**
     * Bulk-indexes a batch of hits into the target index.
     *
     * @param array $searchResult
     * @param string $toIndex
     * @param Client $connection
     * @param DocumentMigratorInterface|null $documentMigrator
     */
    private function bulkIndexDocuments(
        array $searchResult,
        string $toIndex,
        Client $connection,
        ?DocumentMigratorInterface $documentMigrator = null
    ): void {
        if (count($searchResult['hits']['hits']) === 0) {
            return;
        }

        $body = [];
        foreach ($searchResult['hits']['hits'] as $hit) {
            $body[] = ['index' => ['_index' => $toIndex, '_id' => $hit['_id']]];
            $body[] = $documentMigrator ? $documentMigrator->migrate($hit['_source']) : $hit['_source'];
        }

        $response = $connection->bulk(['body' => $body]);
        if ($this->responseHandler) {
            $this->responseHandler->handleResponse(__METHOD__, $response->asArray());
        }
    }
}
