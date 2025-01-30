<?php

namespace Ellinaut\ElasticsearchConnector\Index;

use Elasticsearch\Client;
use Ellinaut\ElasticsearchConnector\Connection\ResponseHandlerInterface;

/**
 * @author Philipp Marien <philipp@ellinaut.dev>
 */
interface PipelineManagerInterface
{
    /**
     * @param string $externalPipelineName
     * @param Client $connection
     * @param ResponseHandlerInterface|null $responseHandler
     */
    public function createPipeline(
        string $externalPipelineName,
        Client $connection,
        ?ResponseHandlerInterface $responseHandler = null
    ): void;

    /**
     * @param string $externalPipelineName
     * @param Client $connection
     * @param ResponseHandlerInterface|null $responseHandler
     */
    public function deletePipeline(
        string $externalPipelineName,
        Client $connection,
        ?ResponseHandlerInterface $responseHandler = null
    ): void;
}
