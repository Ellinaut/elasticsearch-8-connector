<?php

namespace Ellinaut\ElasticsearchConnector\Connection;

/**
 * @author Philipp Marien <philipp@ellinaut.dev>
 */
interface ResponseHandlerInterface
{
    /**
     * @param string $method
     * @param array $response
     */
    public function handleResponse(string $method, array $response): void;
}
