<?php

namespace Ellinaut\ElasticsearchConnector\Index;

use Elasticsearch\Client;
use Ellinaut\ElasticsearchConnector\Connection\ResponseHandlerInterface;

/**
 * @author Philipp Marien <philipp@ellinaut.dev>
 */
trait PipelineManagerTrait
{
    /**
     * @return array
     */
    abstract protected function getPipelineDefinition(): array;

    /**
     * @param string $externalPipelineName
     * @param Client $connection
     * @param ResponseHandlerInterface|null $responseHandler
     */
    public function createPipeline(
        string $externalPipelineName,
        Client $connection,
        ?ResponseHandlerInterface $responseHandler = null
    ): void {
        $response = $connection->ingest()->putPipeline([
            'id' => $externalPipelineName,
            'body' => $this->getPipelineDefinition()
        ]);
        if ($responseHandler) {
            $responseHandler->handleResponse(__METHOD__, $response);
        }
    }

    /**
     * @param string $externalPipelineName
     * @param Client $connection
     * @param ResponseHandlerInterface|null $responseHandler
     */
    public function deletePipeline(
        string $externalPipelineName,
        Client $connection,
        ?ResponseHandlerInterface $responseHandler = null
    ): void {
        $response = $connection->ingest()->deletePipeline(['id' => $externalPipelineName]);
        if ($responseHandler) {
            $responseHandler->handleResponse(__METHOD__, $response);
        }
    }
}
